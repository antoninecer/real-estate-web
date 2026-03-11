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

/* 2. VÝRAZY */
$finalExpr = "COALESCE(
    p.final_score,
    ROUND(v.hard_score * 0.6 + COALESCE(r.ai_score, 0) * 0.4)
)";

/* 3. ŘAZENÍ */
$allowedSort = [
    'name'       => 'v.name',
    'ward'       => 'v.ward',
    'price'      => 'v.price_czk',
    'area'       => 'v.usable_area',
    'metro'      => 'v.metro_distance',
    'hard'       => 'v.hard_score',
    'ai'         => 'COALESCE(p.ai_score, r.ai_score)',
    'final'      => $finalExpr,
    'last'       => 'v.last_seen',
    'first_seen' => 'p.first_seen_at',
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
         {$finalExpr} DESC NULLS LAST,
         v.hard_score DESC NULLS LAST,
         COALESCE(p.ai_score, r.ai_score) DESC NULLS LAST,
         v.last_seen DESC NULLS LAST
";

/* 4. SORT LINK */
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

/* 5. FILTRY */
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
    "e.active = true"
];
$params = [];

if ($minHard !== '') {
    $where[] = "COALESCE(v.hard_score, 0) >= :min_hard";
    $params[':min_hard'] = (int)$minHard;
}

if ($minAi !== '') {
    $where[] = "COALESCE(p.ai_score, r.ai_score, 0) >= :min_ai";
    $params[':min_ai'] = (int)$minAi;
}

if ($minFinal !== '') {
    $where[] = "{$finalExpr} >= :min_final";
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
    $where[] = "(p.ai_score IS NOT NULL OR r.ai_score IS NOT NULL OR r.summary IS NOT NULL OR p.ai_reasoning IS NOT NULL)";
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

/* 6. WARDS */
$wardStmt = $pdo->query("
    SELECT DISTINCT v.ward
    FROM v_estates_hard_score v
    JOIN estates e ON e.hash_id = v.hash_id
    WHERE e.active = true
      AND v.ward IS NOT NULL
      AND v.ward <> ''
    ORDER BY v.ward
");
$wards = $wardStmt->fetchAll(PDO::FETCH_COLUMN);

if ($ward !== '' && !in_array($ward, $wards, true)) {
    $ward = '';
}

/* 7. QUERY */
$sql = "
SELECT
    v.hash_id,
    v.name,
    v.ward,
    v.price_czk,
    v.usable_area,
    v.metro_distance,
    v.hard_score,
    v.detail_url,
    v.last_seen,
    v.features,

    r.ai_score AS review_ai_score,
    r.summary,
    r.strengths,
    r.weaknesses,
    r.verdict AS review_verdict,
    r.breakdown,
    r.model,
    r.created_at AS review_created_at,

    p.profile_id,
    p.profile_name,
    p.ai_score AS profile_ai_score,
    p.final_score AS profile_final_score,
    p.ai_verdict,
    p.ai_reasoning,
    p.ai_context,
    p.first_seen_at,
    p.ai_created_at,

    {$finalExpr} AS final_score

FROM v_estates_hard_score v
JOIN estates e
  ON e.hash_id = v.hash_id
LEFT JOIN estate_ai_reviews r
  ON r.hash_id = v.hash_id
LEFT JOIN v_profile_match_scores_v2 p
  ON p.hash_id = v.hash_id
 AND p.profile_id = :profile_id

$whereSql
$orderSql
LIMIT {$limit}
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':profile_id' => $profileId] + $params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* 8. COUNT */
$countSql = "
SELECT count(*)
FROM v_estates_hard_score v
JOIN estates e
  ON e.hash_id = v.hash_id
LEFT JOIN estate_ai_reviews r
  ON r.hash_id = v.hash_id
LEFT JOIN v_profile_match_scores_v2 p
  ON p.hash_id = v.hash_id
 AND p.profile_id = :profile_id
$whereSql
";

$stmt = $pdo->prepare($countSql);
$stmt->execute([':profile_id' => $profileId] + $params);
$total = (int)$stmt->fetchColumn();

/* 9. POMOCNÉ */
function fmtDateTimeLocal($value): string {
    if (!$value) return '-';
    $ts = strtotime((string)$value);
    if (!$ts) return '-';
    return date('d.m.Y H:i', $ts);
}

function shortText(?string $text, int $limit = 160): string {
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

function aiScoreForView(array $row) {
    if ($row['profile_ai_score'] !== null) {
        return (int)$row['profile_ai_score'];
    }
    if ($row['review_ai_score'] !== null) {
        return (int)$row['review_ai_score'];
    }
    return null;
}

function aiPopupText(array $row): string {
    $parts = [];

    $verdict = verdictText($row);
    if ($verdict !== '') {
        $parts[] = "Verdict:\n" . $verdict;
    }

    if (!empty($row['summary'])) {
        $parts[] = "Summary:\n" . trim((string)$row['summary']);
    }

    if (!empty($row['strengths'])) {
        $parts[] = "Strengths:\n" . trim((string)$row['strengths']);
    }

    if (!empty($row['weaknesses'])) {
        $parts[] = "Weaknesses:\n" . trim((string)$row['weaknesses']);
    }

    if (!empty($row['ai_reasoning'])) {
        $parts[] = "Profile AI reasoning:\n" . trim((string)$row['ai_reasoning']);
    }

    if (!empty($row['ai_context'])) {
        $parts[] = "Profile AI context:\n" . trim((string)$row['ai_context']);
    }

    if (!empty($row['model'])) {
        $parts[] = "Model:\n" . trim((string)$row['model']);
    }

    if (!empty($row['breakdown'])) {
        $breakdown = is_string($row['breakdown'])
            ? $row['breakdown']
            : json_encode($row['breakdown'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($breakdown) {
            $parts[] = "Breakdown:\n" . $breakdown;
        }
    }

    return implode("\n\n", $parts);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>TOP byty podle AI</title>
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
            <th><a href="?<?=h($qs)?>">Plocha<?=$arr?></a></th>

            <?php [$qs,$arr]=sortLink('metro',$sort,$dir,$profileId); ?>
            <th><a href="?<?=h($qs)?>">Metro<?=$arr?></a></th>

            <?php [$qs,$arr]=sortLink('hard',$sort,$dir,$profileId); ?>
            <th><a href="?<?=h($qs)?>">Hard<?=$arr?></a></th>

            <?php [$qs,$arr]=sortLink('ai',$sort,$dir,$profileId); ?>
            <th><a href="?<?=h($qs)?>">AI<?=$arr?></a></th>

            <?php [$qs,$arr]=sortLink('final',$sort,$dir,$profileId); ?>
            <th><a href="?<?=h($qs)?>">Final<?=$arr?></a></th>

            <?php [$qs,$arr]=sortLink('last',$sort,$dir,$profileId); ?>
            <th><a href="?<?=h($qs)?>">Naposledy<?=$arr?></a></th>

            <th>AI summary</th>
            <th>Detail</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach($rows as $r): ?>
            <?php
            $hard = (int)($r['hard_score'] ?? 0);
            $ai = aiScoreForView($r);
            $final = $r['final_score'] !== null ? (int)$r['final_score'] : null;

            $summary = trim((string)($r['summary'] ?? ''));
            if ($summary === '') {
                $summary = trim((string)($r['ai_reasoning'] ?? ''));
            }

            $popupText = aiPopupText($r);
            ?>
            <tr>
                <td>
                    <div class="name"><?=h($r['name'] ?? '')?></div>
                    <?php if (!empty($r['features'])): ?>
                        <div class="tags"><?=renderFeatures($r['features'])?></div>
                    <?php endif; ?>
                </td>

                <td><?=h($r['ward'] ?? '')?></td>

                <td class="num"><?=fmtPrice($r['price_czk'] ?? null)?></td>

                <td><?=h((string)($r['usable_area'] ?? ''))?> m²</td>

                <td><?=metroLabel($r['metro_distance'] ?? null)?></td>

                <td class="<?=scoreClass($hard)?>">
                    <?=$hard?>
                </td>

                <td class="<?=scoreClass((int)($ai ?? 0))?>">
                    <?php if ($ai !== null): ?>
                        <?=h((string)$ai)?>
                    <?php else: ?>
                        <span class="na">…</span>
                    <?php endif; ?>
                </td>

                <td class="<?=scoreClass((int)($final ?? 0))?>">
                    <?php if ($final !== null): ?>
                        <b><?=h((string)$final)?></b>
                    <?php else: ?>
                        <span class="na">…</span>
                    <?php endif; ?>
                </td>

                <td>
                    <?=h(fmtDateTimeLocal($r['last_seen'] ?? null))?>
                </td>

                <td>
                    <?php if ($summary !== ''): ?>
                        <?=h(shortText($summary, 180))?>
                        <?php if ($popupText !== ''): ?>
                            <div style="margin-top:4px;">
                                <button
                                    type="button"
                                    class="ai-pop-btn"
                                    data-title="<?=h($r['name'] ?? 'AI detail')?>"
                                    data-body="<?=h($popupText)?>"
                                >detail</button>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="na">AI počítá…</span>
                    <?php endif; ?>
                </td>

                <td>
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