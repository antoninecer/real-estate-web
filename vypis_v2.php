<?php
require_once __DIR__.'/inc/connect.php';
require_once __DIR__.'/inc/helpers.php';

/* 1. LOGIKA PROFILŮ (v2) */
$profileId = (int)($_GET['profile_id'] ?? 0);
if ($profileId === 0) {
    $profileId = (int)$pdo->query("SELECT id FROM estate_search_profiles WHERE is_active=true ORDER BY id LIMIT 1")->fetchColumn();
}
$profiles = $pdo->query("SELECT id, name FROM estate_search_profiles ORDER BY id")->fetchAll();

/* 2. FILTROVÁNÍ (Přeneste z v1) */
$allowedSort = [
    'name' => 'v.name',
    'ward' => 'v.ward',
    'price' => 'v.price_czk',
    'area' => 'v.usable_area',
    'floor' => 'v.floor_number',
    'condition' => 'v.building_condition',
    'metro' => 'v.metro_distance',
    'score' => 'v.hard_score',
    'last' => 'pm.last_seen_at',
];

$sort = $_GET['sort'] ?? 'score';
$dir  = strtolower($_GET['dir'] ?? 'desc');
if (!isset($allowedSort[$sort])) $sort = 'score';
if ($dir !== 'asc' && $dir !== 'desc') $dir = 'desc';

$orderByCol = $allowedSort[$sort];
$orderSql = "ORDER BY {$orderByCol} ".strtoupper($dir)." NULLS LAST, v.hard_score DESC NULLS LAST";

$minScore = $_GET['min_score'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$minArea  = $_GET['min_area'] ?? '';
$ward     = $_GET['ward'] ?? '';
$parking  = $_GET['parking'] ?? '';

$where = ["pm.profile_id = :profile_id", "pm.state = 'active'"];
$params = [':profile_id' => $profileId];

if($minScore!==''){
    $where[]="v.hard_score >= :min_score";
    $params[':min_score']=(int)$minScore;
}
if($maxPrice!==''){
    $where[]="v.price_czk <= :max_price";
    $params[':max_price']=(int)$maxPrice;
}
if($minArea!==''){
    $where[]="v.usable_area >= :min_area";
    $params[':min_area']=(int)$minArea;
}
if($ward!==''){
    $where[]="v.ward = :ward";
    $params[':ward']=$ward;
}
if($parking){
    $where[]="(v.features::text ILIKE '%garage%' OR v.features::text ILIKE '%parking%')";
}

$whereSql = "WHERE " . implode(" AND ", $where);

/* 3. DATA FETCHING */
$countSql = "SELECT count(*) FROM profile_matches pm JOIN v_estates_hard_score v ON pm.hash_id = v.hash_id $whereSql";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();

$wards = $pdo->query("SELECT DISTINCT ward FROM v_estates_hard_score WHERE ward IS NOT NULL ORDER BY ward")->fetchAll(PDO::FETCH_COLUMN);

$sql = "
    SELECT 
        v.name, v.ward, v.price_czk, v.usable_area, v.floor_number, 
        v.building_condition, v.metro_distance, v.features, v.hard_score, 
        v.detail_url, pm.last_seen_at, r.ai_score
    FROM profile_matches pm
    JOIN v_estates_hard_score v ON pm.hash_id = v.hash_id
    LEFT JOIN estate_ai_reviews r ON pm.hash_id = r.hash_id
    $whereSql
    $orderSql
    LIMIT 500
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

function sortLink(string $key, string $currentSort, string $currentDir, int $profileId): array {
    $dir = ($key === $currentSort && $currentDir === 'asc') ? 'desc' : 'asc';
    $arrow = ($key === $currentSort) ? ($currentDir === 'asc' ? ' ▲' : ' ▼') : '';
    $params = $_GET;
    $params['sort'] = $key;
    $params['dir'] = $dir;
    $params['profile_id'] = $profileId;
    return [http_build_query($params), $arrow];
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>REI v2 - <?=h(array_column($profiles, 'name', 'id')[$profileId] ?? 'Přehled')?></title>
    <?php require_once __DIR__.'/inc/head.php'; ?>
</head>
<body>
<div class="wrap">
    <?php require_once __DIR__.'/inc/menu.php'; ?>

    <div style="background:#f0f0f0; padding:15px; border-radius:5px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
        <form method="get" id="profileForm">
            <strong>Profil:</strong>
            <select name="profile_id" onchange="document.getElementById('profileForm').submit()">
                <?php foreach($profiles as $p): ?>
                    <option value="<?=$p['id']?>" <?=($p['id']==$profileId?'selected':'')?>><?=h($p['name'])?></option>
                <?php endforeach; ?>
            </select>
            <?php // Zachovat ostatní filtry při přepnutí profilu
                foreach($_GET as $k=>$v) if(!in_array($k, ['profile_id', 'sort', 'dir'])) echo '<input type="hidden" name="'.h($k).'" value="'.h($v).'">';
            ?>
        </form>
        <div>Nalezeno: <b><?=h($total)?></b></div>
    </div>

    <form method="get" class="filters">
        <input type="hidden" name="profile_id" value="<?=$profileId?>">
        Min score <input type="number" name="min_score" value="<?=h($minScore)?>">
        Max cena <input type="number" name="max_price" value="<?=h($maxPrice)?>">
        Min plocha <input type="number" name="min_area" value="<?=h($minArea)?>">
        Městská část 
        <select name="ward">
            <option value="">-- všechny --</option>
            <?php foreach($wards as $w): ?>
                <option value="<?=h($w)?>" <?=($ward==$w?'selected':'')?>><?=h($w)?></option>
            <?php endforeach; ?>
        </select>
        <label><input type="checkbox" name="parking" value="1" <?=($parking?'checked':'')?>> Parkování</label>
        <button>Filtrovat</button>
        <a href="vypis_v2.php?profile_id=<?=$profileId?>">reset</a>
    </form>

    <table>
        <thead>
            <tr>
                <?php [$qs,$arr]=sortLink('name',$sort,$dir,$profileId); ?>
                <th><a href="?<?=h($qs)?>">Název<?=$arr?></a></th>
                <?php [$qs,$arr]=sortLink('ward',$sort,$dir,$profileId); ?>
                <th><a href="?<?=h($qs)?>">Část<?=$arr?></a></th>
                <?php [$qs,$arr]=sortLink('price',$sort,$dir,$profileId); ?>
                <th class="num"><a href="?<?=h($qs)?>">Cena<?=$arr?></a></th>
                <?php [$qs,$arr]=sortLink('area',$sort,$dir,$profileId); ?>
                <th class="center"><a href="?<?=h($qs)?>">m²<?=$arr?></a></th>
                <th class="center">Stav</th>
                <th class="center">Metro</th>
                <?php [$qs,$arr]=sortLink('score',$sort,$dir,$profileId); ?>
                <th class="center"><a href="?<?=h($qs)?>">Hard<?=$arr?></a></th>
                <th class="center">AI</th>
                <?php [$qs,$arr]=sortLink('last',$sort,$dir,$profileId); ?>
                <th class="center"><a href="?<?=h($qs)?>">Viděno<?=$arr?></a></th>
                <th class="center">Detail</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($rows as $row): ?>
            <tr>
                <td class="name"><?=h($row['name'])?></td>
                <td><?=h($row['ward'])?></td>
                <td class="num"><?=fmtPrice($row['price_czk'])?></td>
                <td class="center"><?=(int)$row['usable_area']?></td>
                <td><small><?=h($row['building_condition'])?></small></td>
                <td class="center"><?=metroLabel($row['metro_distance'])?></td>
                <td class="center <?=scoreClass((int)$row['hard_score'])?>"><b><?=$row['hard_score']?></b></td>
                <td class="center">
                    <?php if($row['ai_score']): ?>
                        <span style="background:#e3f2fd; padding:2px 5px; border-radius:3px; color:#1976d2; font-weight:bold;"><?=$row['ai_score']?></span>
                    <?php else: ?> <span class="na">-</span> <?php endif; ?>
                </td>
                <td class="center" style="font-size:0.85em;"><?=date('d.m. H:i', strtotime($row['last_seen_at']))?></td>
                <td class="center"><a href="<?=h($row['detail_url'])?>" target="_blank">↗</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
