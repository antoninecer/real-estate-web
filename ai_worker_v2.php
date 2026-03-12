<?php
declare(strict_types=1);

set_time_limit(0);

require_once __DIR__ . '/inc/connect.php';

echo "REI v2 AI Worker started\n";

$MODEL = "qwen3:4b-instruct";
$OLLAMA = "http://macmini:1234/api/generate";
$LOCK_NAME = "rei_ai_worker_v2";
$LOCK_TTL_MINUTES = 15;
$BATCH_LIMIT = 200;
$PROMPT_VERSION = "v3-global-plus-profile-1";

$MAIL_ENABLED = true;
$MAIL_MIN_SCORE = 70;
$MAIL_TO = 'antoninecer@gmail.com';
$MAIL_TO_NAME = 'Antonín';
$MAIL_PROVIDER = 'rightdone-zoho';

$WORKER_ID = gethostname() . ":" . getmypid() . ":" . bin2hex(random_bytes(4));

$SYSTEM = <<<PROMPT
Jsi zkušený analytik realitních inzerátů a hodnotíš vhodnost konkrétního inzerátu pro konkrétní profil zájemce.

Piš výhradně česky, stručně, věcně a gramaticky správně.

Vyhodnocuješ vhodnost podle:
1. strukturovaných dat inzerátu
2. textu inzerátu
3. profilových preferencí zájemce

Vrať pouze validní JSON bez dalšího textu.

{
  "breakdown": {
    "lokalita": 0,
    "komfort": 0,
    "dispozice": 0,
    "riziko": 0,
    "hodnota": 0
  },
  "strengths": "",
  "weaknesses": "",
  "summary": ""
}

Povinná pravidla:
- každá položka breakdown je celé číslo 0 až 20
- summary musí být krátké, věcné a bez marketingových frází
- strengths a weaknesses musí být stručné, konkrétní a založené jen na datech
- nevymýšlej si žádná fakta, která nejsou výslovně uvedena v datech nebo textu inzerátu
- pokud nějaká informace chybí, označ ji jako nejistotu nebo že není uvedena, ne jako jistý fakt
- nezaměňuj fakta za dojmy
- nezaměňuj balkon, lodžii a terasu
- nezaměňuj parkování a garáž
- nevyvozuj zákaz domácích mazlíčků, pokud to text výslovně neuvádí
- pokud profil uvádí speciální požadavky, zohledni je při hodnocení
- pokud text inzerátu naznačuje konflikt s profilem, promítni to hlavně do rizika, weaknesses a summary
- pokud profilový kontext chybí, hodnoť pouze podle obecných parametrů inzerátu
PROMPT;

function logLine(string $msg): void
{
    echo "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
}

function acquireLock(PDO $pdo, string $lockName, string $workerId, int $ttlMinutes): bool
{
    $sql = "
        INSERT INTO job_locks (lock_name, locked_until, locked_by, created_at, updated_at)
        VALUES (:lock_name, now() + (:ttl || ' minutes')::interval, :locked_by, now(), now())
        ON CONFLICT (lock_name) DO UPDATE
        SET
            locked_until = EXCLUDED.locked_until,
            locked_by = EXCLUDED.locked_by,
            updated_at = now()
        WHERE job_locks.locked_until < now()
        RETURNING lock_name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':lock_name' => $lockName,
        ':ttl' => $ttlMinutes,
        ':locked_by' => $workerId,
    ]);

    return (bool)$stmt->fetchColumn();
}

function refreshLock(PDO $pdo, string $lockName, string $workerId, int $ttlMinutes): void
{
    $sql = "
        UPDATE job_locks
        SET
            locked_until = now() + (:ttl || ' minutes')::interval,
            updated_at = now()
        WHERE lock_name = :lock_name
          AND locked_by = :locked_by
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':ttl' => $ttlMinutes,
        ':lock_name' => $lockName,
        ':locked_by' => $workerId,
    ]);
}

function releaseLock(PDO $pdo, string $lockName, string $workerId): void
{
    $stmt = $pdo->prepare("
        DELETE FROM job_locks
        WHERE lock_name = :lock_name
          AND locked_by = :locked_by
    ");
    $stmt->execute([
        ':lock_name' => $lockName,
        ':locked_by' => $workerId,
    ]);
}

function callOllama(string $url, array $payload): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 180,
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $response === null) {
        throw new RuntimeException("CURL error: " . $err);
    }

    if ($httpCode >= 400) {
        throw new RuntimeException("HTTP error from Ollama: " . $httpCode);
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new RuntimeException("Invalid JSON response from Ollama");
    }

    return $data;
}

function parseModelJson(string $text): array
{
    $text = trim($text);
    $text = preg_replace('/```(?:json)?/i', '', $text);
    $text = str_replace('```', '', $text);

    if (preg_match('/\{.*\}/s', $text, $m)) {
        $jsonText = $m[0];
    } else {
        $jsonText = $text;
    }

    $jsonText = preg_replace('/^\s*-\s*/m', '', $jsonText);
    $json = json_decode($jsonText, true);

    if (!is_array($json)) {
        throw new RuntimeException("JSON parse failed: " . mb_substr($text, 0, 300));
    }

    return $json;
}

function clampScore(int $value): int
{
    return max(0, min(20, $value));
}

function buildProfileContext(?string $aiContext): string
{
    $ctx = trim((string)$aiContext);

    if ($ctx === '') {
        return "Bez zvláštních doplňkových preferencí. Hodnoť pouze podle obecných parametrů inzerátu.";
    }

    return $ctx;
}

function htm(?string $text): string
{
    return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function scoreVerdict(int $aiScore): string
{
    if ($aiScore < 30) {
        return 'IGNORE';
    }
    if ($aiScore < 60) {
        return 'CONSIDER';
    }
    if ($aiScore < 80) {
        return 'GOOD';
    }
    return 'EXCEPTIONAL';
}

function notificationAlreadySent(PDO $pdo, int $profileId, int $hashId, string $emailTo): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM profile_email_notifications
        WHERE profile_id = :profile_id
          AND hash_id = :hash_id
          AND email_to = :email_to
        LIMIT 1
    ");

    $stmt->execute([
        ':profile_id' => $profileId,
        ':hash_id' => $hashId,
        ':email_to' => $emailTo,
    ]);

    return (bool)$stmt->fetchColumn();
}

function insertNotificationLog(
    PDO $pdo,
    int $profileId,
    int $hashId,
    string $emailTo,
    int $aiScore,
    string $provider,
    ?string $providerMessageId = null
): void {
    $stmt = $pdo->prepare("
        INSERT INTO profile_email_notifications (
            profile_id,
            hash_id,
            email_to,
            ai_score,
            sent_at,
            provider,
            provider_message_id
        ) VALUES (
            :profile_id,
            :hash_id,
            :email_to,
            :ai_score,
            now(),
            :provider,
            :provider_message_id
        )
        ON CONFLICT (profile_id, hash_id, email_to) DO NOTHING
    ");

    $stmt->execute([
        ':profile_id' => $profileId,
        ':hash_id' => $hashId,
        ':email_to' => $emailTo,
        ':ai_score' => $aiScore,
        ':provider' => $provider,
        ':provider_message_id' => $providerMessageId,
    ]);
}

function sendInterestingEstateEmail(
    array $task,
    int $aiScore,
    string $verdict,
    array $breakdownSafe,
    string $summary,
    ?string $strengths,
    ?string $weaknesses
): bool {
    global $MAIL_TO, $MAIL_TO_NAME;

    $name = trim((string)($task['name'] ?? ''));
    $profileName = trim((string)($task['profile_name'] ?? ''));
    $ward = trim((string)($task['ward'] ?? ''));
    $price = $task['price_czk'] !== null ? number_format((float)$task['price_czk'], 0, ',', ' ') . ' Kč' : '-';
    $area = $task['usable_area'] !== null ? ((string)$task['usable_area'] . ' m²') : '-';
    $floor = $task['floor_number'] !== null && $task['floor_number'] !== '' ? (string)$task['floor_number'] : '-';
    $metro = $task['metro_distance'] !== null && $task['metro_distance'] !== '' ? ((string)$task['metro_distance'] . ' m') : '-';
    $detailUrl = trim((string)($task['detail_url'] ?? ''));
    $description = trim((string)($task['description'] ?? ''));
    $shortDesc = mb_substr($description, 0, 1200);
    if (mb_strlen($description) > 1200) {
        $shortDesc .= "…";
    }

    $subject = "[REI] {$verdict} {$aiScore}/100 – {$name}";
    if ($profileName !== '') {
        $subject .= " ({$profileName})";
    }

    $htmlBody = '
        <html><body style="font-family:Arial,sans-serif;color:#111;line-height:1.5;">
            <h2 style="margin:0 0 12px 0;">Zajímavý inzerát pro profil ' . htm($profileName) . '</h2>

            <p style="margin:0 0 12px 0;">
                <strong>' . htm($name) . '</strong>
            </p>

            <table cellpadding="6" cellspacing="0" border="0" style="border-collapse:collapse;">
                <tr><td><strong>AI score</strong></td><td>' . htm((string)$aiScore) . ' / 100</td></tr>
                <tr><td><strong>Verdict</strong></td><td>' . htm($verdict) . '</td></tr>
                <tr><td><strong>Lokalita</strong></td><td>' . htm($ward) . '</td></tr>
                <tr><td><strong>Cena</strong></td><td>' . htm($price) . '</td></tr>
                <tr><td><strong>Plocha</strong></td><td>' . htm($area) . '</td></tr>
                <tr><td><strong>Patro</strong></td><td>' . htm($floor) . '</td></tr>
                <tr><td><strong>Metro</strong></td><td>' . htm($metro) . '</td></tr>
            </table>

            <h3 style="margin:18px 0 8px 0;">Shrnutí</h3>
            <p>' . nl2br(htm($summary !== '' ? $summary : '-')) . '</p>

            <h3 style="margin:18px 0 8px 0;">Silné stránky</h3>
            <p>' . nl2br(htm($strengths ?: '-')) . '</p>

            <h3 style="margin:18px 0 8px 0;">Slabé stránky</h3>
            <p>' . nl2br(htm($weaknesses ?: '-')) . '</p>

            <h3 style="margin:18px 0 8px 0;">Breakdown</h3>
            <ul>
                <li>Lokalita: ' . htm((string)$breakdownSafe['lokalita']) . '/20</li>
                <li>Komfort: ' . htm((string)$breakdownSafe['komfort']) . '/20</li>
                <li>Dispozice: ' . htm((string)$breakdownSafe['dispozice']) . '/20</li>
                <li>Riziko: ' . htm((string)$breakdownSafe['riziko']) . '/20</li>
                <li>Hodnota: ' . htm((string)$breakdownSafe['hodnota']) . '/20</li>
            </ul>

            <h3 style="margin:18px 0 8px 0;">Popis inzerátu</h3>
            <p>' . nl2br(htm($shortDesc !== '' ? $shortDesc : '-')) . '</p>';

    if ($detailUrl !== '') {
        $htmlBody .= '
            <p style="margin-top:20px;">
                <a href="' . htm($detailUrl) . '" style="display:inline-block;padding:10px 14px;background:#2c3e50;color:#fff;text-decoration:none;border-radius:6px;">
                    Otevřít detail inzerátu
                </a>
            </p>
            <p>' . htm($detailUrl) . '</p>';
    }

    $htmlBody .= '</body></html>';

    $plainBody =
        "Zajímavý inzerát pro profil {$profileName}\n\n" .
        "Název: {$name}\n" .
        "AI score: {$aiScore}/100\n" .
        "Verdict: {$verdict}\n" .
        "Lokalita: {$ward}\n" .
        "Cena: {$price}\n" .
        "Plocha: {$area}\n" .
        "Patro: {$floor}\n" .
        "Metro: {$metro}\n\n" .
        "Shrnutí:\n" . ($summary !== '' ? $summary : '-') . "\n\n" .
        "Silné stránky:\n" . ($strengths ?: '-') . "\n\n" .
        "Slabé stránky:\n" . ($weaknesses ?: '-') . "\n\n" .
        "Breakdown:\n" .
        "- Lokalita: {$breakdownSafe['lokalita']}/20\n" .
        "- Komfort: {$breakdownSafe['komfort']}/20\n" .
        "- Dispozice: {$breakdownSafe['dispozice']}/20\n" .
        "- Riziko: {$breakdownSafe['riziko']}/20\n" .
        "- Hodnota: {$breakdownSafe['hodnota']}/20\n\n" .
        ($detailUrl !== '' ? "Detail: {$detailUrl}\n\n" : '') .
        "Popis:\n" . ($shortDesc !== '' ? $shortDesc : '-') . "\n";

    return sendEmail($MAIL_TO, $MAIL_TO_NAME, $subject, $htmlBody, $plainBody);
}

try {
    if (!acquireLock($pdo, $LOCK_NAME, $WORKER_ID, $LOCK_TTL_MINUTES)) {
        logLine("Worker not started, lock already held.");
        exit(0);
    }

    logLine("Lock acquired by {$WORKER_ID}");

    $sql = "
        SELECT
            pm.profile_id,
            pm.hash_id,
            e.name,
            e.description,
            e.detail_url,
            e.price_czk,
            e.usable_area,
            e.floor_number,
            e.metro_distance,
            e.tram_distance,
            e.bus_distance,
            e.building_condition,
            e.construction_type,
            e.elevator,
            e.parking,
            e.garage,
            e.cellar,
            e.balcony,
            e.loggia,
            e.ward,
            sp.name AS profile_name,
            sp.category_type_cb,
            sp.ai_context
        FROM profile_matches pm
        JOIN estates e
          ON e.hash_id = pm.hash_id
        JOIN estate_search_profiles sp
          ON sp.id = pm.profile_id
        LEFT JOIN profile_ai_reviews air
          ON air.profile_id = pm.profile_id
         AND air.hash_id = pm.hash_id
        WHERE pm.state = 'active'
          AND e.active = true
          AND air.id IS NULL
        ORDER BY pm.last_seen_at DESC, pm.id DESC
        LIMIT :limit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $BATCH_LIMIT, PDO::PARAM_INT);
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$tasks) {
        logLine("No missing AI reviews found.");
        releaseLock($pdo, $LOCK_NAME, $WORKER_ID);
        exit(0);
    }

    logLine("Found " . count($tasks) . " items to evaluate.");

    $processed = 0;

    foreach ($tasks as $task) {
        $name = trim((string)($task['name'] ?? ''));
        $desc = trim((string)($task['description'] ?? ''));
        $profileId = (int)$task['profile_id'];
        $hashId = (int)$task['hash_id'];

        if ($name === '' && $desc === '') {
            logLine("SKIP profile_id={$profileId} hash_id={$hashId}: empty name and description");
            continue;
        }

        try { $dataBlock = <<<TXT
Profil: {$task['profile_name']}
Typ nabídky: {$task['category_type_cb']}

Název: {$task['name']}
Cena: {$task['price_czk']} Kč
Plocha: {$task['usable_area']} m2
Patro: {$task['floor_number']}
Metro: {$task['metro_distance']} m
Tram: {$task['tram_distance']} m
Bus: {$task['bus_distance']} m
Stav: {$task['building_condition']}
Konstrukce: {$task['construction_type']}
Výtah: %s
Parkování: %s
Garáž: %s
Sklep: %s
Balkon: %s
Lodžie: %s
TXT;

            $dataBlock = sprintf(
                $dataBlock,
                !empty($task['elevator']) ? 'ano' : 'ne',
                !empty($task['parking']) ? 'ano' : 'ne',
                !empty($task['garage']) ? 'ano' : 'ne',
                !empty($task['cellar']) ? 'ano' : 'ne',
                !empty($task['balcony']) ? 'ano' : 'ne',
                !empty($task['loggia']) ? 'ano' : 'ne'
            );

            $profileContext = buildProfileContext($task['ai_context'] ?? '');

            $prompt = $SYSTEM . "\n\nPROFILOVÉ PREFERENCE:\n" . $profileContext
    . "\n\nSTRUKTUROVANÁ DATA INZERÁTU:\n"
    . $dataBlock
    . "\n\nTEXT INZERÁTU:\n"
    . trim((string)($task['description'] ?? ''));
            $payload = [
                'model' => $MODEL,
                'prompt' => $prompt,
                'stream' => false,
                'options' => [
                    'temperature' => 0.1,
                    'num_predict' => 500,
                ],
            ];

            $response = callOllama($OLLAMA, $payload);
            $text = trim((string)($response['response'] ?? ''));
            $json = parseModelJson($text);

            $breakdown = $json['breakdown'] ?? [];

            $lok = clampScore((int)($breakdown['lokalita'] ?? 0));
            $kom = clampScore((int)($breakdown['komfort'] ?? 0));
            $dis = clampScore((int)($breakdown['dispozice'] ?? 0));
            $riz = clampScore((int)($breakdown['riziko'] ?? 0));
            $hod = clampScore((int)($breakdown['hodnota'] ?? 0));

            $aiScore = $lok + $kom + $dis + $riz + $hod;
            $aiScore = max(0, min(100, $aiScore));
            $verdict = scoreVerdict($aiScore);

            $summary = !empty($json['summary']) ? trim((string)$json['summary']) : '';
            $strengths = !empty($json['strengths']) ? trim((string)$json['strengths']) : null;
            $weaknesses = !empty($json['weaknesses']) ? trim((string)$json['weaknesses']) : null;
            $reasoning = $summary !== '' ? $summary : null;

            $upsert = $pdo->prepare("
                INSERT INTO profile_ai_reviews (
                    profile_id,
                    hash_id,
                    ai_score,
                    verdict,
                    reasoning,
                    strengths,
                    weaknesses,
                    summary,
                    breakdown,
                    model,
                    prompt_version,
                    created_at,
                    updated_at
                ) VALUES (
                    :profile_id,
                    :hash_id,
                    :ai_score,
                    :verdict,
                    :reasoning,
                    :strengths,
                    :weaknesses,
                    :summary,
                    :breakdown,
                    :model,
                    :prompt_version,
                    now(),
                    now()
                )
                ON CONFLICT (profile_id, hash_id) DO UPDATE SET
                    ai_score = EXCLUDED.ai_score,
                    verdict = EXCLUDED.verdict,
                    reasoning = EXCLUDED.reasoning,
                    strengths = EXCLUDED.strengths,
                    weaknesses = EXCLUDED.weaknesses,
                    summary = EXCLUDED.summary,
                    breakdown = EXCLUDED.breakdown,
                    model = EXCLUDED.model,
                    prompt_version = EXCLUDED.prompt_version,
                    updated_at = now()
            ");

            $breakdownSafe = [
                'lokalita' => $lok,
                'komfort' => $kom,
                'dispozice' => $dis,
                'riziko' => $riz,
                'hodnota' => $hod,
            ];

            $upsert->execute([
                ':profile_id' => $profileId,
                ':hash_id' => $hashId,
                ':ai_score' => $aiScore,
                ':verdict' => $verdict,
                ':reasoning' => $reasoning,
                ':strengths' => $strengths,
                ':weaknesses' => $weaknesses,
                ':summary' => $summary !== '' ? $summary : null,
                ':breakdown' => json_encode($breakdownSafe, JSON_UNESCAPED_UNICODE),
                ':model' => $MODEL,
                ':prompt_version' => $PROMPT_VERSION,
            ]);

            $processed++;
            logLine("OK profile_id={$profileId} hash_id={$hashId} ai_score={$aiScore} verdict={$verdict}");

            if (
                $MAIL_ENABLED
                && $aiScore >= $MAIL_MIN_SCORE
            ) {
                if (notificationAlreadySent($pdo, $profileId, $hashId, $MAIL_TO)) {
                    logLine("MAIL SKIP profile_id={$profileId} hash_id={$hashId}: already sent to {$MAIL_TO}");
                } else {
                    $mailOk = sendInterestingEstateEmail(
                        $task,
                        $aiScore,
                        $verdict,
                        $breakdownSafe,
                        $summary,
                        $strengths,
                        $weaknesses
                    );

                    if ($mailOk) {
                        insertNotificationLog(
                            $pdo,
                            $profileId,
                            $hashId,
                            $MAIL_TO,
                            $aiScore,
                            $MAIL_PROVIDER,
                            null
                        );

                        logLine("MAIL OK profile_id={$profileId} hash_id={$hashId} score={$aiScore} to={$MAIL_TO}");
                    } else {
                        logLine("MAIL ERROR profile_id={$profileId} hash_id={$hashId} score={$aiScore} to={$MAIL_TO}");
                    }
                }
            }

            if (($processed % 10) === 0) {
                refreshLock($pdo, $LOCK_NAME, $WORKER_ID, $LOCK_TTL_MINUTES);
                logLine("Lock refreshed.");
            }

        } catch (Throwable $e) {
            logLine("ERROR profile_id={$profileId} hash_id={$hashId}: " . $e->getMessage());
        }
    }

    releaseLock($pdo, $LOCK_NAME, $WORKER_ID);
    logLine("Worker finished. Processed {$processed} items.");

} catch (Throwable $e) {
    try {
        releaseLock($pdo, $LOCK_NAME, $WORKER_ID);
    } catch (Throwable $ignored) {
    }

    logLine("FATAL: " . $e->getMessage());
    exit(1);
}