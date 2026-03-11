<?php
require_once __DIR__.'/inc/connect.php';
require_once __DIR__.'/inc/helpers.php';

/* 1. PROFIL */
$profileId = (int)($_GET['profile_id'] ?? 0);
if ($profileId === 0) {
    $profileId = (int)$pdo->query("
        SELECT id
        FROM estate_search_profiles
        WHERE is_active = true
        ORDER BY id
        LIMIT 1
    ")->fetchColumn();
}

$profiles = $pdo->query("
    SELECT id, name
    FROM estate_search_profiles
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

/* 2. ŘAZENÍ */
$allowedSort = [
    'profile'    => 'v.profile_name',
    'name'       => 'v.name',
    'ward'       => 'v.ward',
    'price'      => 'v.price_czk',
    'area'       => 'v.usable_area',
    'floor'      => 'v.floor_number',
    'metro'      => 'v.metro_distance',
    'hard'       => 'v.hard_score',
    'ai'         => 'v.ai_score',
    'final'      => 'v.final_score',
    'last'       => 'v.last_seen',
    'first_seen' => 'v.first_seen_at',
    'ai_date'    => 'v.ai_created_at',
    'portal'     => 'v.portal',
];

$sort = $_GET['sort'] ?? 'final';
$dir  = strtolower($_GET['dir'] ?? 'desc');

if (!isset($allowedSort[$sort])) {
    $sort = 'final';
}
if (!in_array($dir, ['asc', 'desc'], true)) {
    $dir = 'desc';
}

$orderByCol = $allowedSort[$sort];
$orderSql = "ORDER BY {$orderByCol} " . strtoupper($dir) . " NULLS LAST,
                    v.final_score DESC NULLS LAST,
                    v.ai_score DESC NULLS LAST,
                    v.hard_score DESC NULLS LAST,
                    v.last_seen DESC NULLS LAST";

/* 3. FILTRY */
$minHard        = $_GET['min_hard'] ?? '';
$minAi          = $_GET['min_ai'] ?? '';
$minFinal       = $_GET['min_final'] ?? '';
$maxPrice       = $_GET['max_price'] ?? '';
$minArea        = $_GET['min_area'] ?? '';
$ward           = trim($_GET['ward'] ?? '');
$q              = trim($_GET['q'] ?? '');
$parking        = !empty($_GET['parking']);
$onlyNew        = !empty($_GET['only_new']);
$onlyFresh      = !empty($_GET['only_fresh']);
$hideContacted  = !empty($_GET['hide_contacted']);
$onlyWithAi     = !empty($_GET['only_with_ai']);

$where = ["v.profile_id = :profile_id"];
$params = [':profile_id' => $profileId];

if ($minHard !== '') {
    $where[] = "COALESCE(v.hard_score, 0) >= :min_hard";
    $params[':min_hard'] = (int)$minHard;
}
if ($minAi !== '') {
    $where[] = "COALESCE(v.ai_score, 0) >= :min_ai";
    $params[':min_ai'] = (int)$minAi;
}
if ($minFinal !== '') {
    $where[] = "COALESCE(v.final_score, 0) >= :min_final";
    $params[':min_final'] = (int)$minFinal;
}
if ($maxPrice !== '') {
    $where[] = "v.price_czk <= :max_price";
    $params[':max_price'] = (int)$maxPrice;
}
if ($minArea !== '') {
    $where[] = "v.usable_area >= :min_area";
    $params[':min_area'] = (int)$minArea;
}
if ($ward !== '') {
    $where[] = "v.ward = :ward";
    $params[':ward'] = $ward;
}
if ($parking) {
    $where[] = "(COALESCE(v.features,'') ILIKE '%G%' OR COALESCE(v.features,'') ILIKE '%P%' OR COALESCE(v.features,'') ILIKE '%gar%' OR COALESCE(v.features,'') ILIKE '%park%')";
}
if ($onlyNew) {
    $where[] = "v.first_seen_at >= now() - interval '24 hours'";
}
if ($onlyFresh) {
    $where[] = "v.last_seen >= now() - interval '24 hours'";
}
if ($hideContacted) {
    $where[] = "COALESCE(v.already_contacted, false) = false";
}
if ($onlyWithAi) {
    $where[] = "(v.ai_score IS NOT NULL OR v.ai_verdict IS NOT NULL OR r.summary IS NOT NULL)";
}
if ($q !== '') {
    $where[] = "(
        v.name ILIKE :q
        OR COALESCE(v.description, '') ILIKE :q
        OR COALESCE(v.ward, '') ILIKE :q
        OR COALESCE(v.city, '') ILIKE :q
        OR COALESCE(v.ai_reasoning, '') ILIKE :q
        OR COALESCE(v.ai_context, '') ILIKE :q
        OR COALESCE(r.summary, '') ILIKE :q
        OR COALESCE(r.strengths, '') ILIKE :q
        OR COALESCE(r.weaknesses, '') ILIKE :q
    )";
    $params[':q'] = '%' . $q . '%';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

/* 4. DATA */
$sql = "
    SELECT
        v.profile_match_id,
        v.profile_id,
        v.profile_name,
        v.ai_context,

        v.hash_id,
        v.portal,
        v.name,
        v.city,
        v.ward,
        v.price_czk,
        v.price_czk_m2,
        v.usable_area,
        v.floor_number,
        v.building_condition,
        v.metro_distance,
        v.tram_distance,
        v.bus_distance,
        v.detail_url,
        v.description,
        v.last_seen,
        v.features,

        v.hard_score,
        v.ai_score,
        v.final_score,
        v.ai_verdict,
        v.ai_reasoning,
        v.ai_created_at,

        v.first_seen_at,
        v.already_contacted,

        r.verdict     AS review_verdict,
        r.summary     AS review_summary,
        r.strengths,
        r.weaknesses,
        r.breakdown,
        r.model,
        r.created_at  AS review_created_at
    FROM v_profile_match_scores_v2 v
    LEFT JOIN estate_ai_reviews r ON r.hash_id = v.hash_id
    $whereSql
    $orderSql
    LIMIT 500
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countSql = "
    SELECT count(*)
    FROM v_profile_match_scores_v2 v
    LEFT JOIN estate_ai_reviews r ON r.hash_id = v.hash_id
    $whereSql
";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$wardStmt = $pdo->prepare("
    SELECT DISTINCT ward
    FROM v_profile_match_scores_v2
    WHERE profile_id = :profile_id
      AND ward IS NOT NULL
      AND ward <> ''
    ORDER BY ward
");
$wardStmt->execute([':profile_id' => $profileId]);
$wards = $wardStmt->fetchAll(PDO::FETCH_COLUMN);

if ($ward !== '' && !in_array($ward, $wards, true)) {
    $ward = '';
}

/* 5. POMOCNÉ */
function sortLink(string $key, string $currentSort, string $currentDir, int $profileId): array {
    $dir = ($key === $currentSort && $currentDir === 'asc') ? 'desc' : 'asc';
    $arrow = ($key === $currentSort) ? ($currentDir === 'asc' ? ' ▲' : ' ▼') : '';
    $params = $_GET;
    $params['sort'] = $key;
    $params['dir'] = $dir;
    $params['profile_id'] = $profileId;
    return [http_build_query($params), $arrow];
}

function fmtDateTimeLocal($value): string {
    if (!$value) return '-';
    $ts = strtotime((string)$value);
    if (!$ts) return '-';
    return date('d.m.Y H:i', $ts);
}

function shortText(?string $text, int $limit = 140): string {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (mb_strlen($text) <= $limit) return $text;
    return mb_substr($text, 0, $limit) . '…';
}

function aiVerdictText(array $row): string {
    $v = trim((string)($row['ai_verdict'] ?? ''));
    if ($v !== '') return $v;

    $v = trim((string)($row['review_verdict'] ?? ''));
    if ($v !== '') return $v;

    return '';
}

function popupText(array $row): string {
    $parts = [];

    if (!empty($row['ai_reasoning'])) {
        $parts[] = "AI reasoning:\n" . trim((string)$row['ai_reasoning']);
    }

    if (!empty($row['review_summary'])) {
        $parts[] = "Summary:\n" . trim((string)$row['review_summary']);
    }

    if (!empty($row['strengths'])) {
        $parts[] = "Strengths:\n" . trim((string)$row['strengths']);
    }

    if (!empty($row['weaknesses'])) {
        $parts[] = "Weaknesses:\n" . trim((string)$row['weaknesses']);
    }

    if (!empty($row['ai_context'])) {
        $parts[] = "Profilový AI context:\n" . trim((string)$row['ai_context']);
    }

    if (!empty($row['breakdown'])) {
        $parts[] = "Breakdown:\n" . json_encode($row['breakdown'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return implode("\n\n", $parts);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>AI výpis bytů</title>
    <?php require_once __DIR__.'/inc/head.php'; ?>
</head>
<body>
<div class="wrap">

    <?php require_once __DIR__.'/inc/menu.php'; ?>

    <h2>AI přehled bytů podle profilu</h2>

    <p class="hint">
        Nalezeno bytů: <b><?=h((string)$total)?></b> |
        Zdroj: <b>v_profile_match_scores_v2</b> + <b>estate_ai_reviews</b>
    </p>

    <form method="get" class="filters">
        <div style="margin-bottom:10px;">
            Profil
            <select name="profile_id">
                <?php foreach($profiles as $p): ?>
                    <option value="<?=$p['id']?>" <?=((int)$p['id'] === $profileId ? 'selected' : '')?>>
                        <?=h($p['name'])?>
                    </option>
                <?php endforeach; ?>
            </select>

            Text
            <input type="text" name="q" value="<?=h($q)?>" style="width:220px;">

            Min Hard
            <input type="number" name="min_hard" value="<?=h((string)$minHard)?>" style="width:70px;">

            Min AI
            <input type="number" name="min_ai" value="<?=h((string)$minAi)?>" style="width:70px;">

            Min Final
            <input type="number" name="min_final" value="<?=h((string)$minFinal)?>" style="width:70px;">
        </div>

        <div style="margin-bottom:10px;">
            Max cena
            <input type="number" name="max_price" value="<?=h((string)$maxPrice)?>" style="width:110px;">

            Min plocha
            <input type="number" name="min_area" value="<?=h((string)$minArea)?>" style="width:90px;">

            Městská část
            <select name="ward">
                <option value="">-- všechny --</option>
                <?php foreach($wards as $w): ?>
                    <option value="<?=h($w)?>" <?=($ward === $w ? 'selected' : '')?>><?=h($w)?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin-bottom:10px;">
            <label><input type="checkbox" name="parking" value="1" <?=($parking ? 'checked' : '')?>> Parkování</label>
            <label><input type="checkbox" name="only_new" value="1" <?=($onlyNew ? 'checked' : '')?>> Nové 24h</label>
            <label><input type="checkbox" name="only_fresh" value="1" <?=($onlyFresh ? 'checked' : '')?>> Viděné 24h</label>
            <label><input type="checkbox" name="hide_contacted" value="1" <?=($hideContacted ? 'checked' : '')?>> Skrýt kontaktované</label>
            <label><input type="checkbox" name="only_with_ai" value="1" <?=($onlyWithAi ? 'checked' : '')?>> Jen s AI</label>
        </div>

        <button>Filtrovat</button>
        <a href="aivypis.php?profile_id=<?=$profileId?>">reset</a>
    </form>

    <table>
        <thead>
        <tr>
            <?php [$qs,$arr]=sortLink('name',$sort,$dir,$profileId); ?>
            <th><a href="?<?=h($qs)?>">Název<?=$arr?></a></th>

            <?php [$qs,$arr]=sortLink('ward',$sort,$dir,$profileId); ?>
            <th><a href="?<?=h($qs)?>">Lokalita<?=$arr?></a></th>

            <?php [$qs,$arr]=sortLink('price',$sort,$dir,$profileId); ?>
            <th class="num"><a href="?<?=h($qs)?>">Cena<?=$arr?></a></th>

            <?php [$qs,$arr]=sortLink('area',$sort,$dir,$profileId); ?>
            <th class="center"><a href="?<?=h($qs)?>">Plocha<?=$arr?></a></th>

            <?php [$qs,$arr]=sortLink('metro',$sort,$dir,$profileId); ?>
            <th class="center"><a href="?<?=h($qs)?>">Metro<?=$arr?></a></th>

            <?php [$qs,$arr]=sortLink('hard',$sort,$dir,$profileId); ?>
            <th class="center"><a href="?<?=h($qs)?>">Hard<?=$arr?></a></th>

            <?php [$qs,$arr]=sortLink('ai',$sort,$dir,$profileId); ?>
            <th class="center"><a href="?<?=h($qs)?>">AI<?=$arr?></a></th>

            <?php [$qs,$arr]=sortLink('final',$sort,$dir,$profileId); ?>
            <th class="center"><a href="?<?=h($qs)?>">Final<?=$arr?></a></th>

            <th class="center">Verdict</th>
            <th>AI shrnutí</th>

            <?php [$qs,$arr]=sortLink('last',$sort,$dir,$profileId); ?>
            <th class="center"><a href="?<?=h($qs)?>">Načteno<?=$arr?></a></th>

            <th class="center">Detail</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($rows as $row): ?>
            <?php
            $hard = (int)($row['hard_score'] ?? 0);
            $ai = $row['ai_score'];
            $final = $row['final_score'];
            $verdict = aiVerdictText($row);
            $popup = popupText($row);
            $descId = 'desc_' . $row['profile_id'] . '_' . $row['hash_id'];
            ?>
            <tr>
                <td>
                    <div class="name"><?=h($row['name'] ?? '')?></div>

                    <div class="hint" style="margin:4px 0 0 0;">
                        profil: <b><?=h($row['profile_name'] ?? '')?></b>
                        <?php if (!empty($row['portal'])): ?> | portál: <?=h($row['portal'])?><?php endif; ?>
                        <?php if (!empty($row['already_contacted'])): ?> | kontaktováno<?php endif; ?>
                    </div>

                    <?php if (!empty($row['features'])): ?>
                        <div class="tags"><?=renderFeatures($row['features'])?></div>
                    <?php endif; ?>

                    <?php if (!empty($row['description'])): ?>
                        <div class="desc-preview">
                            <?=h(shortText($row['description'], 160))?>
                            <?php if (mb_strlen(trim((string)$row['description'])) > 160): ?>
                                <a href="#" class="toggle-desc-link" data-target="<?=$descId?>">více</a>
                            <?php endif; ?>
                        </div>
                        <div id="<?=$descId?>" class="desc-full" style="display:none;">
                            <?=nl2br(h($row['description']))?>
                        </div>
                    <?php endif; ?>
                </td>

                <td>
                    <?=h($row['city'] ?? '')?>
                    <?php if (!empty($row['ward'])): ?>
                        <div class="hint" style="margin:4px 0 0 0;"><?=h($row['ward'])?></div>
                    <?php endif; ?>
                </td>

                <td class="num"><?=fmtPrice($row['price_czk'] ?? null)?></td>

                <td class="center">
                    <?=h((string)($row['usable_area'] ?? ''))?>
                    <?php if (!empty($row['floor_number'])): ?>
                        <div class="hint" style="margin:4px 0 0 0;">patro <?=h((string)$row['floor_number'])?></div>
                    <?php endif; ?>
                </td>

                <td class="center"><?=metroLabel($row['metro_distance'] ?? null)?></td>

                <td class="center <?=scoreClass($hard)?>">
                    <?=$hard?>
                </td>

                <td class="center <?=scoreClass((int)($ai ?? 0))?>">
                    <?php if ($ai !== null): ?>
                        <?=h((string)(int)$ai)?>
                    <?php else: ?>
                        <span class="na">…</span>
                    <?php endif; ?>
                </td>

                <td class="center <?=scoreClass((int)($final ?? 0))?>">
                    <?php if ($final !== null): ?>
                        <?=h((string)(int)$final)?>
                    <?php else: ?>
                        <span class="na">…</span>
                    <?php endif; ?>
                </td>

                <td class="center">
                    <?php if ($verdict !== ''): ?>
                        <?=h($verdict)?>
                        <?php if ($popup !== ''): ?>
                            <div style="margin-top:6px;">
                                <button
                                    type="button"
                                    class="ai-pop-btn"
                                    data-title="<?=h($row['name'])?>"
                                    data-body="<?=h($popup)?>"
                                >detail</button>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="na">…</span>
                    <?php endif; ?>
                </td>

                <td>
                    <?php if (!empty($row['review_summary'])): ?>
                        <?=h(shortText($row['review_summary'], 180))?>
                    <?php elseif (!empty($row['ai_reasoning'])): ?>
                        <?=h(shortText($row['ai_reasoning'], 180))?>
                    <?php else: ?>
                        <span class="na">AI počítá…</span>
                    <?php endif; ?>
                </td>

                <td class="center">
                    <?=h(fmtDateTimeLocal($row['last_seen'] ?? null))?>
                    <?php if (!empty($row['ai_created_at'])): ?>
                        <div class="hint" style="margin:4px 0 0 0;">AI <?=h(fmtDateTimeLocal($row['ai_created_at']))?></div>
                    <?php endif; ?>
                </td>

                <td class="center">
                    <?php if (!empty($row['detail_url'])): ?>
                        <a href="<?=h($row['detail_url'])?>" target="_blank">Otevřít</a>
                    <?php else: ?>
                        <span class="na">N/A</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$rows): ?>
            <tr>
                <td colspan="11" class="na">Žádná data.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="aiPopupOverlay" class="ai-popup-overlay" style="display:none;">
    <div class="ai-popup-box">
        <div class="ai-popup-head">
            <div id="aiPopupTitle" class="ai-popup-title"></div>
            <button type="button" id="aiPopupClose" class="ai-popup-close">×</button>
        </div>
        <div id="aiPopupBody" class="ai-popup-body"></div>
    </div>
</div>

<script>
document.querySelectorAll('.toggle-desc-link').forEach(function(link) {
    link.addEventListener('click', function(e) {
        e.preventDefault();

        const targetId = this.getAttribute('data-target');
        const box = document.getElementById(targetId);
        if (!box) return;

        const isHidden = box.style.display === 'none' || box.style.display === '';
        box.style.display = isHidden ? 'block' : 'none';
        this.textContent = isHidden ? 'méně' : 'více';
    });
});

(function () {
    const overlay = document.getElementById('aiPopupOverlay');
    const closeBtn = document.getElementById('aiPopupClose');
    const titleEl = document.getElementById('aiPopupTitle');
    const bodyEl = document.getElementById('aiPopupBody');

    function openPopup(title, body) {
        titleEl.textContent = title || 'AI detail';
        bodyEl.textContent = body || '';
        overlay.style.display = 'flex';
    }

    function closePopup() {
        overlay.style.display = 'none';
    }

    document.querySelectorAll('.ai-pop-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openPopup(
                this.dataset.title || '',
                this.dataset.body || ''
            );
        });
    });

    closeBtn.addEventListener('click', closePopup);

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closePopup();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.style.display !== 'none') {
            closePopup();
        }
    });
})();
</script>
</body>
</html>