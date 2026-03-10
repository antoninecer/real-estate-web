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
$PROMPT_VERSION = "v2-profile-context-1";

$WORKER_ID = gethostname() . ":" . getmypid() . ":" . bin2hex(random_bytes(4));

$SYSTEM = <<<PROMPT
Jsi analytik realitních inzerátů.

Vyhodnocuješ VHODNOST KONKRÉTNÍHO inzerátu pro KONKRÉTNÍ profil.

Musíš zohlednit:
1. strukturovaná data bytu
2. text inzerátu
3. AI kontext profilu

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

Pravidla:
- každá položka breakdown je 0 až 20
- summary má být stručné a věcné
- strengths a weaknesses mají být stručné
- pokud profil uvádí speciální požadavky (např. pes, děti, investice, pronájem, koupě), musíš je zohlednit
- pokud text inzerátu naznačuje konflikt s profilem, promítni to do rizika a summary
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
        $detailUrl = trim((string)($task['detail_url'] ?? ''));

        if ($name === '' && $desc === '') {
            logLine("SKIP profile_id={$profileId} hash_id={$hashId}: empty name and description");
            continue;
        }
        $profileId = (int)$task['profile_id'];
        $hashId = (int)$task['hash_id'];

        try {
            $dataBlock = <<<TXT
Profil: {$task['profile_name']}
Typ nabídky: {$task['category_type_cb']}
AI kontext profilu: {$task['ai_context']}

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

            $prompt = $SYSTEM . "\n\n" . $dataBlock . "\n\nINZERÁT:\n" . trim((string)($task['description'] ?? ''));

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

            if ($aiScore < 30) {
                $verdict = 'IGNORE';
            } elseif ($aiScore < 60) {
                $verdict = 'CONSIDER';
            } elseif ($aiScore < 80) {
                $verdict = 'GOOD';
            } else {
                $verdict = 'EXCEPTIONAL';
            }

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

            $reasoning = trim(
                (string)($json['summary'] ?? '')
            );

            $upsert->execute([
                ':profile_id' => $profileId,
                ':hash_id' => $hashId,
                ':ai_score' => $aiScore,
                ':verdict' => $verdict,
                ':reasoning' => $reasoning !== '' ? $reasoning : null,
                ':strengths' => !empty($json['strengths']) ? (string)$json['strengths'] : null,
                ':weaknesses' => !empty($json['weaknesses']) ? (string)$json['weaknesses'] : null,
                ':summary' => !empty($json['summary']) ? (string)$json['summary'] : null,
                ':breakdown' => json_encode([
                    'lokalita' => $lok,
                    'komfort' => $kom,
                    'dispozice' => $dis,
                    'riziko' => $riz,
                    'hodnota' => $hod,
                ], JSON_UNESCAPED_UNICODE),
                ':model' => $MODEL,
                ':prompt_version' => $PROMPT_VERSION,
            ]);

            $processed++;
            logLine("OK profile_id={$profileId} hash_id={$hashId} ai_score={$aiScore} verdict={$verdict}");

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