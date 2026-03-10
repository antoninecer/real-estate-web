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
    'name'       => 'v.name',
    'ward'       => 'v.ward',
    'price'      => 'v.price_czk',
    'area'       => 'v.usable_area',
    'metro'      => 'v.metro_distance',
    'tram'       => 'v.tram_distance',
    'bus'        => 'v.bus_distance',
    'hard'       => 'v.hard_score',
    'ai'         => 'v.ai_score',
    'final'      => 'v.final_score',
    'last'       => 'v.last_seen',
    'first_seen' => 'v.first_seen_at',
    'ai_date'    => 'v.ai_created_at',
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
$orderSql = "ORDER BY {$orderByCol} " . strtoupper($dir) . " NULLS LAST, v.final_score DESC NULLS LAST, v.last_seen DESC NULLS LAST";

/* 3. FILTRY */
$minHard   = $_GET['min_hard'] ?? '';
$minAi     = $_GET['min_ai'] ?? '';
$minFinal  = $_GET['min_final'] ?? '';
$maxPrice  = $_GET['max_price'] ?? '';
$minArea   = $_GET['min_area'] ?? '';
$ward      = trim($_GET['ward'] ?? '');
$parking   = !empty($_GET['parking']);
$onlyNew   = !empty($_GET['only_new']);
$onlyFresh = !empty($_GET['only_fresh']);
$hideContacted = !empty($_GET['hide_contacted']);

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
    $where[] = "(v.features ILIKE '%G%' OR v.features ILIKE '%P%')";
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

$whereSql = 'WHERE ' . implode(' AND ', $where);

/* 4. DATA */
$sql = "
    SELECT
        v.profile_match_id,
        v.profile_id,
        v.profile_name,
        v.category_type_cb,
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
        v.construction_type,
        v.energy_rating,
        v.ownership,
        v.metro_distance,
        v.tram_distance,
        v.bus_distance,
        v.detail_url,
        v.description,
        v.last_seen,
        v.active,
        v.features,

        v.hard_score,
        v.ai_score,
        v.final_score,
        v.ai_verdict,
        v.ai_reasoning,
        v.ai_created_at,
        v.ai_updated_at,

        v.state,
        v.first_seen_at,
        v.last_seen_at,
        v.notified_at,

        v.contacted_id,
        v.contacted_at,
        v.already_contacted
    FROM v_profile_match_scores_v2 v
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

function shortText(?string $text, int $limit = 120): string {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (mb_strlen($text) <= $limit) return $text;
    return mb_substr($text, 0, $limit) . '…';
}

function aiVerdictBadge(?string $verdict): string {
    $v = trim((string)$verdict);
    if ($v === '') return '<span class="badge badge-muted">čeká</span>';

    $lower = mb_strtolower($v);
    if (str_contains($lower, 'reject') || str_contains($lower, 'forbidden') || str_contains($lower, 'bad')) {
        return '<span class="badge badge-bad">'.h($v).'</span>';
    }
    if (str_contains($lower, 'good') || str_contains($lower, 'fit') || str_contains($lower, 'ok')) {
        return '<span class="badge badge-good">'.h($v).'</span>';
    }
    if (str_contains($lower, 'great') || str_contains($lower, 'excellent')) {
        return '<span class="badge badge-great">'.h($v).'</span>';
    }
    return '<span class="badge badge-info">'.h($v).'</span>';
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>REI v2 - <?=h(array_column($profiles, 'name', 'id')[$profileId] ?? 'Přehled')?></title>
    <?php require_once __DIR__.'/inc/head.php'; ?>
    <style>
        .summary-bar {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #ddd;
            gap: 20px;
        }

        .filters input,
        .filters select,
        .filters button {
            margin-right: 8px;
            margin-bottom: 8px;
        }

        .score-box {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 1.05em;
            display: inline-block;
            min-width: 42px;
            text-align: center;
        }

        .score-final {
            background: #673ab7;
            color: white;
            border: 1px solid #512da8;
        }

        .score-ai {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }

        .mhd-label {
            font-size: 12px;
            color: #666;
            display: block;
            margin-top: 2px;
        }

        .mhd-val {
            font-weight: bold;
            color: #333;
        }

        .meta {
            font-size: 12px;
            color: #666;
            line-height: 1.5;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .badge-good { background: #e8f5e9; color: #2e7d32; }
        .badge-great { background: #e3f2fd; color: #1565c0; }
        .badge-bad { background: #ffebee; color: #c62828; }
        .badge-info { background: #ede7f6; color: #5e35b1; }
        .badge-muted { background: #f1f3f4; color: #777; }
        .badge-contacted { background: #fff3cd; color: #8a6d3b; }

        .bubble-wrap {
            position: relative;
            display: inline-block;
            cursor: help;
        }

        .bubble {
            display: none;
            position: absolute;
            z-index: 1000;
            left: 0;
            top: 24px;
            width: 380px;
            max-width: 60vw;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-shadow: 0 8px 25px rgba(0,0,0,.15);
            padding: 12px;
            color: #222;
            font-size: 13px;
            line-height: 1.45;
            white-space: normal;
        }

        .bubble-wrap:hover .bubble {
            display: block;
        }

        .desc-preview {
            color: #666;
            font-size: 12px;
            max-width: 340px;
        }

        .small-muted {
            color: #777;
            font-size: 11px;
        }

        .nowrap {
            white-space: nowrap;
        }

        .name-col {
            min-width: 260px;
        }

        .detail-link {
            text-decoration: none;
            font-size: 20px;
        }
    </style>
</head>
<body>
<div class="wrap">
    <?php require_once __DIR__.'/inc/menu.php'; ?>

    <div class="summary-bar">
        <form method="get" id="profileForm">
            <strong>Aktivní profil:</strong>
            <select name="profile_id" onchange="document.getElementById('profileForm').submit()" style="padding:5px; font-size:1.05em; border-radius:4px;">
                <?php foreach($profiles as $p): ?>
                    <option value="<?=$p['id']?>" <?=($p['id'] == $profileId ? 'selected' : '')?>><?=h($p['name'])?></option>
                <?php endforeach; ?>
            </select>
            <?php
            foreach($_GET as $k => $v) {
                if ($k === 'profile_id') continue;
                if (is_array($v)) continue;
                echo '<input type="hidden" name="'.h($k).'" value="'.h($v).'">';
            }
            ?>
        </form>

        <div style="text-align:right;">
            <div style="font-size:1.15em;">Celkem <b><?=h((string)$total)?></b> nabídek</div>
            <div class="small-muted">Zdroj: v_profile_match_scores_v2</div>
        </div>
    </div>

    <form method="get" class="filters">
        <input type="hidden" name="profile_id" value="<?=$profileId?>">

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
                <option value="<?=h($w)?>" <?=($ward === $w ? 'selected' : '')?>><?=h($w)?></option>
            <?php endforeach; ?>
        </select>

        <label><input type="checkbox" name="parking" value="1" <?=($parking ? 'checked' : '')?>> Parkování</label>
        <label><input type="checkbox" name="only_new" value="1" <?=($onlyNew ? 'checked' : '')?>> Nové 24h</label>
        <label><input type="checkbox" name="only_fresh" value="1" <?=($onlyFresh ? 'checked' : '')?>> Viděné 24h</label>
        <label><input type="checkbox" name="hide_contacted" value="1" <?=($hideContacted ? 'checked' : '')?>> Skrýt kontaktované</label>

        <button>Filtrovat</button>
        <a href="vypis_v2.php?profile_id=<?=$profileId?>">reset</a>
    </form>

    <table>
        <thead>
            <tr>
                <?php [$qs,$arr]=sortLink('name',$sort,$dir,$profileId); ?>
                <th class="name-col"><a href="?<?=h($qs)?>">Název<?=$arr?></a></th>

                <?php [$qs,$arr]=sortLink('ward',$sort,$dir,$profileId); ?>
                <th><a href="?<?=h($qs)?>">Lokalita<?=$arr?></a></th>

                <?php [$qs,$arr]=sortLink('price',$sort,$dir,$profileId); ?>
                <th class="num"><a href="?<?=h($qs)?>">Cena<?=$arr?></a></th>

                <?php [$qs,$arr]=sortLink('area',$sort,$dir,$profileId); ?>
                <th class="center"><a href="?<?=h($qs)?>">Plocha<?=$arr?></a></th>

                <th>Vzdálenosti</th>

                <?php [$qs,$arr]=sortLink('final',$sort,$dir,$profileId); ?>
                <th class="center"><a href="?<?=h($qs)?>">Final<?=$arr?></a></th>

                <?php [$qs,$arr]=sortLink('hard',$sort,$dir,$profileId); ?>
                <th class="center"><a href="?<?=h($qs)?>">Hard<?=$arr?></a></th>

                <?php [$qs,$arr]=sortLink('ai',$sort,$dir,$profileId); ?>
                <th class="center"><a href="?<?=h($qs)?>">AI<?=$arr?></a></th>

                <?php [$qs,$arr]=sortLink('last',$sort,$dir,$profileId); ?>
                <th class="center"><a href="?<?=h($qs)?>">Načteno<?=$arr?></a></th>

                <?php [$qs,$arr]=sortLink('ai_date',$sort,$dir,$profileId); ?>
                <th class="center"><a href="?<?=h($qs)?>">AI datum<?=$arr?></a></th>

                <th class="center">AI verdict</th>
                <th class="center">Detail</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($rows as $row): ?>
                <tr>
                    <td class="name-col">
                        <div style="font-weight:bold; margin-bottom:4px;"><?=h($row['name'])?></div>

                        <div class="meta">
                            <?=h($row['city'] ?? '')?><?=($row['city'] && $row['ward']) ? ' / ' : ''?><?=h($row['ward'] ?? '')?><br>
                            <?=h($row['features'] ?? '-')?>
                            <?php if (!empty($row['already_contacted'])): ?>
                                <br><span class="badge badge-contacted">už kontaktováno</span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($row['description'])): ?>
                        <?php $descId = 'desc_' . $row['profile_id'] . '_' . $row['hash_id']; ?>
                        <div class="desc-preview" style="margin-top:6px;">
                            <?=h(shortText($row['description'], 160))?>
                            <?php if (mb_strlen(trim((string)$row['description'])) > 160): ?>
                            <a href="#" class="toggle-desc-link" data-target="<?=$descId?>">více</a>
                            <?php endif; ?>
                        </div>

                        <div id="<?=$descId?>" class="desc-full" style="display:none; margin-top:8px;">
                            <?=nl2br(h($row['description']))?>
                        </div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <small><?=h($row['ward'])?></small>
                    </td>

                    <td class="num nowrap"><?=fmtPrice($row['price_czk'])?></td>

                    <td class="center nowrap">
                        <?=h((string)$row['usable_area'])?> m²
                        <?php if (!empty($row['floor_number'])): ?>
                            <div class="small-muted">patro <?=h((string)$row['floor_number'])?></div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div class="mhd-label">Metro: <span class="mhd-val"><?=metroLabel($row['metro_distance'])?></span></div>
                        <div class="mhd-label">Tram: <span class="mhd-val"><?=($row['tram_distance'] ? h((string)$row['tram_distance']).' m' : '-')?></span></div>
                        <div class="mhd-label">Bus: <span class="mhd-val"><?=($row['bus_distance'] ? h((string)$row['bus_distance']).' m' : '-')?></span></div>
                    </td>

                    <td class="center">
                        <div class="score-box score-final"><?=h((string)($row['final_score'] ?? 0))?></div>
                    </td>

                    <td class="center">
                        <div class="<?=scoreClass((int)($row['hard_score'] ?? 0))?>" style="font-weight:bold; padding:4px;">
                            <?=h((string)($row['hard_score'] ?? 0))?>
                        </div>
                    </td>

                    <td class="center">
                        <?php if ((int)($row['ai_score'] ?? 0) > 0): ?>
                            <div class="score-box score-ai"><?=h((string)$row['ai_score'])?></div>
                        <?php else: ?>
                            <span class="badge badge-muted" title="Čeká na AI nebo ještě nebylo vyhodnoceno">-</span>
                        <?php endif; ?>
                    </td>

                    <td class="center nowrap">
                        <?=h(fmtDateTimeLocal($row['last_seen']))?>
                        <?php if (!empty($row['first_seen_at'])): ?>
                            <div class="small-muted">poprvé <?=h(fmtDateTimeLocal($row['first_seen_at']))?></div>
                        <?php endif; ?>
                    </td>

                    <td class="center nowrap">
                        <?=h(fmtDateTimeLocal($row['ai_created_at']))?>
                    </td>

                    <td class="center">
                        <div><?=aiVerdictBadge($row['ai_verdict'] ?? '')?></div>

                        <?php if (!empty($row['ai_reasoning'])): ?>
                            <div class="bubble-wrap" style="margin-top:6px;">
                                <span class="badge badge-info">popis</span>
                                <div class="bubble">
                                    <div style="font-weight:bold; margin-bottom:8px;">AI popis</div>
                                    <div><?=nl2br(h($row['ai_reasoning']))?></div>

                                    <?php if (!empty($row['ai_context'])): ?>
                                        <hr style="margin:10px 0; border:none; border-top:1px solid #eee;">
                                        <div style="font-weight:bold; margin-bottom:6px;">AI kontext profilu</div>
                                        <div><?=nl2br(h($row['ai_context']))?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </td>

                    <td class="center">
                        <?php if (!empty($row['detail_url'])): ?>
                            <a href="<?=h($row['detail_url'])?>" target="_blank" class="detail-link" title="Otevřít detail">↗</a>
                        <?php else: ?>
                            <span class="small-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
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
</script>
</body>
</html>