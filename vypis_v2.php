<?php
require_once __DIR__.'/inc/connect.php';
require_once __DIR__.'/inc/helpers.php';

/* 1. LOGIKA PROFILŮ */
$profileId = (int)($_GET['profile_id'] ?? 0);
if ($profileId === 0) {
    $profileId = (int)$pdo->query("SELECT id FROM estate_search_profiles WHERE is_active=true ORDER BY id LIMIT 1")->fetchColumn();
}
$profiles = $pdo->query("SELECT id, name FROM estate_search_profiles ORDER BY id")->fetchAll();

/* 2. FILTROVÁNÍ A ŘAZENÍ */
$allowedSort = [
    'name' => 'v.name',
    'ward' => 'v.ward',
    'price' => 'v.price_czk',
    'area' => 'v.usable_area',
    'metro' => 'v.metro_distance',
    'tram' => 'v.tram_distance',
    'bus' => 'v.bus_distance',
    'hard' => 'v.hard_score',
    'ai' => 'r.ai_score',
    'complex' => 'composite_score',
    'last' => 'pm.last_seen_at',
];

$sort = $_GET['sort'] ?? 'complex'; // Default řazení podle komplexního skóre
$dir  = strtolower($_GET['dir'] ?? 'desc');
if (!isset($allowedSort[$sort])) $sort = 'complex';
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

/* 3. DATA FETCHING S VÝPOČTEM KOMPLEXNÍHO SKÓRE */
// Komplexní skóre = průměr Hard a AI (pokud AI chybí, bere se jen Hard)
$sql = "
    SELECT 
        v.name, v.ward, v.price_czk, v.usable_area, v.floor_number, 
        v.building_condition, v.metro_distance, v.tram_distance, v.bus_distance,
        v.features, v.hard_score, v.detail_url, pm.last_seen_at, 
        COALESCE(r.ai_score, 0) as ai_score,
        ROUND((v.hard_score + COALESCE(r.ai_score, v.hard_score)) / 2.0) as composite_score
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

$countSql = "SELECT count(*) FROM profile_matches pm JOIN v_estates_hard_score v ON pm.hash_id = v.hash_id $whereSql";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();

$wards = $pdo->query("SELECT DISTINCT ward FROM v_estates_hard_score WHERE ward IS NOT NULL ORDER BY ward")->fetchAll(PDO::FETCH_COLUMN);

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
    <style>
        .score-box { padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 1.1em; display: inline-block; min-width: 35px; text-align: center; }
        .score-complex { background: #673ab7; color: white; border: 1px solid #512da8; }
        .mhd-label { font-size: 0.8em; color: #666; display: block; margin-top: 2px; }
        .mhd-val { font-weight: bold; color: #333; }
    </style>
</head>
<body>
<div class="wrap">
    <?php require_once __DIR__.'/inc/menu.php'; ?>

    <div style="background:#f8f9fa; padding:15px; border-radius:8px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; border: 1px solid #ddd;">
        <form method="get" id="profileForm">
            <strong>Aktivní profil:</strong>
            <select name="profile_id" onchange="document.getElementById('profileForm').submit()" style="padding: 5px; font-size: 1.1em; border-radius: 4px;">
                <?php foreach($profiles as $p): ?>
                    <option value="<?=$p['id']?>" <?=($p['id']==$profileId?'selected':'')?>><?=h($p['name'])?></option>
                <?php endforeach; ?>
            </select>
            <?php foreach($_GET as $k=>$v) if(!in_array($k, ['profile_id', 'sort', 'dir'])) echo '<input type="hidden" name="'.h($k).'" value="'.h($v).'">'; ?>
        </form>
        <div style="text-align: right;">
            <span style="font-size: 1.2em;">Celkem <b><?=h($total)?></b> nabídek</span>
        </div>
    </div>

    <form method="get" class="filters">
        <input type="hidden" name="profile_id" value="<?=$profileId?>">
        Min Hard score <input type="number" name="min_score" value="<?=h($minScore)?>" style="width: 60px;">
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
                <th><a href="?<?=h($qs)?>">Lokalita<?=$arr?></a></th>
                <?php [$qs,$arr]=sortLink('price',$sort,$dir,$profileId); ?>
                <th class="num"><a href="?<?=h($qs)?>">Cena<?=$arr?></a></th>
                <?php [$qs,$arr]=sortLink('area',$sort,$dir,$profileId); ?>
                <th class="center"><a href="?<?=h($qs)?>">Plocha<?=$arr?></a></th>
                <th>Vzdálenosti (MHD)</th>
                <?php [$qs,$arr]=sortLink('complex',$sort,$dir,$profileId); ?>
                <th class="center"><a href="?<?=h($qs)?>">K-Score<?=$arr?></a></th>
                <?php [$qs,$arr]=sortLink('hard',$sort,$dir,$profileId); ?>
                <th class="center"><a href="?<?=h($qs)?>">Hard<?=$arr?></a></th>
                <?php [$qs,$arr]=sortLink('ai',$sort,$dir,$profileId); ?>
                <th class="center"><a href="?<?=h($qs)?>">AI<?=$arr?></a></th>
                <th class="center">Detail</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($rows as $row): ?>
            <tr>
                <td class="name"><?=h($row['name'])?></td>
                <td><small><?=h($row['ward'])?></small></td>
                <td class="num"><?=fmtPrice($row['price_czk'])?></td>
                <td class="center"><?=(int)$row['usable_area']?> m²</td>
                <td>
                    <div class="mhd-label">Metro: <span class="mhd-val"><?=metroLabel($row['metro_distance'])?></span></div>
                    <div class="mhd-label">Tram: <span class="mhd-val"><?=$row['tram_distance'] ? $row['tram_distance'].'m' : '-'?></span></div>
                    <div class="mhd-label">Bus: <span class="mhd-val"><?=$row['bus_distance'] ? $row['bus_distance'].'m' : '-'?></span></div>
                </td>
                <td class="center">
                    <div class="score-box score-complex" title="Průměr Hard a AI skóre"><?=$row['composite_score']?></div>
                </td>
                <td class="center">
                    <div class="<?=scoreClass((int)$row['hard_score'])?>" style="font-weight:bold; padding:4px;"><?=$row['hard_score']?></div>
                </td>
                <td class="center">
                    <?php if($row['ai_score'] > 0): ?>
                        <div style="background:#e3f2fd; color:#1976d2; border:1px solid #bbdefb; padding:4px; border-radius:4px; font-weight:bold;"><?=$row['ai_score']?></div>
                    <?php else: ?>
                        <span class="na" title="Čeká na AI frontu">-</span>
                    <?php endif; ?>
                </td>
                <td class="center"><a href="<?=h($row['detail_url'])?>" target="_blank" style="text-decoration:none; font-size:1.5em;">↗</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
