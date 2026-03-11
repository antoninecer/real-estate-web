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
    'metro'      => 'v.metro_distance',
    'hard'       => 'v.hard_score',
    'ai'         => 'v.ai_score',
    'final'      => 'v.final_score',
    'last'       => 'v.last_seen',
    'first_seen' => 'v.first_seen_at',
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
$orderSql = "
ORDER BY {$orderByCol} " . strtoupper($dir) . " NULLS LAST,
         v.final_score DESC NULLS LAST,
         v.ai_score DESC NULLS LAST,
         v.hard_score DESC NULLS LAST,
         v.last_seen DESC NULLS LAST
";

/* 3. SORT LINK */
function sortLink(string $key, string $currentSort, string $currentDir, int $profileId): array {
    $dir = 'asc';

    if ($key === $currentSort) {
        $dir = ($currentDir === 'asc') ? 'desc' : 'asc';
    }

    $arrow = '';
    if ($key === $currentSort) {
        $arrow = ($currentDir === 'asc') ? ' ▲' : ' ▼';
    }

    $params = $_GET;
    $params['sort'] = $key;
    $params['dir'] = $dir;
    $params['profile_id'] = $profileId;

    return [http_build_query($params), $arrow];
}

/* 4. FILTRY */
$minHard    = $_GET['min_hard'] ?? '';
$minAi      = $_GET['min_ai'] ?? '';
$minFinal   = $_GET['min_final'] ?? '';
$maxPrice   = $_GET['max_price'] ?? '';
$minArea    = $_GET['min_area'] ?? '';
$ward       = trim($_GET['ward'] ?? '');
$onlyFresh  = !empty($_GET['only_fresh']);
$onlyWithAi = !empty($_GET['only_with_ai']);
$limit      = (int)($_GET['limit'] ?? 50);

if ($limit <= 0) {
    $limit = 50;
}
if ($limit > 500) {
    $limit = 500;
}

$where = [
    "v.profile_id = :profile_id",
    "COALESCE(v.active, false) = true"
];
$params = [
    ':profile_id' => $profileId
];

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

if ($onlyFresh) {
    $where[] = "v.last_seen >= now() - interval '24 hours'";
}

if ($onlyWithAi) {
    $where[] = "(v.ai_score IS NOT NULL OR v.ai_verdict IS NOT NULL OR r.summary IS NOT NULL)";
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

/* 5. WARDS */
$wardStmt = $pdo->prepare("
    SELECT DISTINCT ward
    FROM v_profile_match_scores_v2
    WHERE profile_id = :profile_id
      AND COALESCE(active, false) = true
      AND ward IS NOT NULL
      AND ward <> ''
    ORDER BY ward
");
$wardStmt->execute([':profile_id' => $profileId]);
$wards = $wardStmt->fetchAll(PDO::FETCH_COLUMN);

if ($ward !== '' && !in_array($ward, $wards, true)) {
    $ward = '';
}

/* 6. QUERY */
$sql = "
SELECT
    v.profile_id,
    v.profile_name,
    v.hash_id,
    v.name,
    v.city,
    v.ward,
    v.price_czk,
    v.usable_area,
    v.metro_distance,
    v.hard_score,
    v.ai_score,
    v.final_score,
    v.ai_verdict,
    v.ai_reasoning,
    v.detail_url,
    v.last_seen,
    v.first_seen_at,
    v.features,
    v.active,

    r.summary,
    r.strengths,
    r.weaknesses,
    r.verdict AS review_verdict

FROM v_profile_match_scores_v2 v
LEFT JOIN estate_ai_reviews r ON r.hash_id = v.hash_id

$whereSql
$orderSql
LIMIT {$limit}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* 7. COUNT */
$countSql = "
SELECT count(*)
FROM v_profile_match_scores_v2 v
LEFT JOIN estate_ai_reviews r ON r.hash_id = v.hash_id
$whereSql
";

$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

/* 8. POMOCNÉ */
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

function verdictText(array $row): string {
    $v = trim((string)($row['ai_verdict'] ?? ''));
    if ($v !== '') return $v;

    $v = trim((string)($row['review_verdict'] ?? ''));
    if ($v !== '') return $v;

    return '';
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>TOP byty podle profilu</title>
    <?php require_once __DIR__.'/inc/head.php'; ?>
</head>
<body>

<div class="wrap">

<?php require_once __DIR__.'/inc/menu.php'; ?>

<h2>TOP byty podle kombinovaného skóre</h2>

<p class="hint">
    Profil: <b><?=h(array_column($profiles, 'name', 'id')[$profileId] ?? 'neznámý')?></b> |
    Aktivní inzeráty |
    Nalezeno: <b><?=h((string)$total)?></b>
</p>

<form method="get" class="filters">
    <input type="hidden" name="sort" value="<?=h($sort)?>">
    <input type="hidden" name="dir" value="<?=h($dir)?>">

    Profil
    <select name="profile_id">
        <?php foreach($profiles as $p): ?>
            <option value="<?=$p['id']?>" <?=((int)$p['id'] === $profileId ? 'selected' : '')?>>
                <?=h($p['name'])?>
            </option>
        <?php endforeach; ?>
    </select>

    Min Hard
    <input type="number" name="min_hard" value="<?=h((string)$minHard)?>" style="width:70px;">

    Min AI
    <input type="number" name="min_ai" value="<?=h((string)$minAi)?>" style="width:70px;">

    Min Final
    <input type="number" name="min_final" value="<?=h((string)$minFinal)?>" style="width:70px;">

    Max cena
    <input type="number" name="max_price" value="<?=h((string)$maxPrice)?>" style="width:110px;">

    Min plocha
    <input type="number" name="min_area" value="<?=h((string)$minArea)?>" style="width:90px;">

    Městská část
    <select name="ward">
        <option value="">-- všechny --</option>
        <?php foreach($wards as $w): ?>
            <option value="<?=h($w)?>" <?=($ward === $w ? 'selected' : '')?>>
                <?=h($w)?>
            </option>
        <?php endforeach; ?>
    </select>

    Limit
    <input type="number" name="limit" value="<?=h((string)$limit)?>" style="width:70px;">

    <label>
        <input type="checkbox" name="only_fresh" value="1" <?=($onlyFresh ? 'checked' : '')?>>
        Viděné 24h
    </label>

    <label>
        <input type="checkbox" name="only_with_ai" value="1" <?=($onlyWithAi ? 'checked' : '')?>>
        Jen s AI
    </label>

    <button>Filtrovat</button>
    <a href="top.php?profile_id=<?=$profileId?>">reset</a>
</form>

<table>
    <thead>
        <tr>
            <?php [$qs,$arr]=sortLink('name',$sort,$dir,$profileId); ?>
            <th><a href="?<?=h($qs)?>">Název<?=$arr?></a></th>

            <?php [$qs,$arr]=sortLink('ward',$sort,$dir,$profileId); ?>
            <th><a href="?<?=h($qs)?>">Městská část<?=$arr?></a></th>

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

            <th>AI summary</th>

            <?php [$qs,$arr]=sortLink('last',$sort,$dir,$profileId); ?>
            <th class="center"><a href="?<?=h($qs)?>">Naposledy<?=$arr?></a></th>

            <th class="center">Detail</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach($rows as $r): ?>
            <?php
            $hard = (int)($r['hard_score'] ?? 0);
            $ai = $r['ai_score'];
            $final = $r['final_score'];
            $summary = trim((string)($r['summary'] ?? ''));
            if ($summary === '') {
                $summary = trim((string)($r['ai_reasoning'] ?? ''));
            }
            ?>
            <tr>
                <td>
                    <div class="name"><?=h($r['name'] ?? '')?></div>
                    <div class="hint" style="margin:4px 0 0 0;">
                        <?=h($r['city'] ?? '')?>
                        <?php if (!empty($r['ward'])): ?>
                            / <?=h($r['ward'])?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($r['features'])): ?>
                        <div class="tags"><?=renderFeatures($r['features'])?></div>
                    <?php endif; ?>
                </td>

                <td><?=h($r['ward'] ?? '')?></td>

                <td class="num"><?=fmtPrice($r['price_czk'] ?? null)?></td>

                <td class="center">
                    <?=h((string)($r['usable_area'] ?? ''))?> m²
                </td>

                <td class="center"><?=metroLabel($r['metro_distance'] ?? null)?></td>

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
                        <b><?=h((string)(int)$final)?></b>
                    <?php else: ?>
                        <span class="na">…</span>
                    <?php endif; ?>
                </td>

                <td>
                    <?php if ($summary !== ''): ?>
                        <?=h(shortText($summary, 180))?>
                        <?php $v = verdictText($r); ?>
                        <?php if ($v !== ''): ?>
                            <div class="hint" style="margin:4px 0 0 0;"><?=h($v)?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="na">AI počítá…</span>
                    <?php endif; ?>
                </td>

                <td class="center">
                    <?=h(fmtDateTimeLocal($r['last_seen'] ?? null))?>
                </td>

                <td class="center">
                    <?php if (!empty($r['detail_url'])): ?>
                        <a href="<?=h($r['detail_url'])?>" target="_blank">detail</a>
                    <?php else: ?>
                        <span class="na">N/A</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$rows): ?>
            <tr>
                <td colspan="10" class="na">Žádná data.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

</div>

</body>
</html>