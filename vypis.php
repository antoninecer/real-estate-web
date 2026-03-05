<?php

require_once __DIR__.'/inc/connect.php';
require_once __DIR__.'/inc/helpers.php';

$allowedSort = [
    'name' => 'name',
    'ward' => 'ward',
    'price' => 'price_czk',
    'area' => 'usable_area',
    'floor' => 'floor_number',
    'condition' => 'building_condition',
    'metro' => 'metro_distance',
    'score' => 'hard_score',
    'last' => 'last_seen',
];

$sort = $_GET['sort'] ?? 'score';
$dir  = strtolower($_GET['dir'] ?? 'desc');

if (!isset($allowedSort[$sort])) $sort = 'score';
if ($dir !== 'asc' && $dir !== 'desc') $dir = 'desc';

$orderByCol = $allowedSort[$sort];

$orderSql = "
ORDER BY {$orderByCol} ".strtoupper($dir)." NULLS LAST,
         hard_score DESC NULLS LAST,
         last_seen DESC NULLS LAST
";

function sortLink(string $key,string $currentSort,string $currentDir):array{

    $dir='asc';

    if($key===$currentSort){
        $dir=($currentDir==='asc')?'desc':'asc';
    }

    $arrow='';

    if($key===$currentSort){
        $arrow=($currentDir==='asc')?' ▲':' ▼';
    }

    $qs=http_build_query([
        'sort'=>$key,
        'dir'=>$dir
    ]);

    return [$qs,$arrow];
}

$minScore = $_GET['min_score'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$minArea  = $_GET['min_area'] ?? '';
$ward     = $_GET['ward'] ?? '';

$where=[];
$params=[];

if($minScore!==''){
    $where[]="hard_score >= :min_score";
    $params[':min_score']=(int)$minScore;
}

if($maxPrice!==''){
    $where[]="price_czk <= :max_price";
    $params[':max_price']=(int)$maxPrice;
}

if($minArea!==''){
    $where[]="usable_area >= :min_area";
    $params[':min_area']=(int)$minArea;
}

if($ward!==''){
    $where[]="ward = :ward";
    $params[':ward']=$ward;
}

$whereSql='';
if($where){
    $whereSql="WHERE ".implode(" AND ",$where);
}

/* počet bytů po filtraci */

$countSql="
SELECT count(*)
FROM v_estates_hard_score
$whereSql
";

$stmt=$pdo->prepare($countSql);
$stmt->execute($params);
$total=$stmt->fetchColumn();

/* seznam ward pro dropdown */

$wards=$pdo->query("
SELECT DISTINCT ward
FROM v_estates_hard_score
WHERE ward IS NOT NULL
ORDER BY ward
")->fetchAll(PDO::FETCH_COLUMN);

/* hlavní query */

try{

$sql="
SELECT
name,
ward,
price_czk,
usable_area,
floor_number,
building_condition,
metro_distance,
features,
hard_score,
detail_url,
last_seen
FROM v_estates_hard_score
$whereSql
$orderSql
LIMIT 500
";

$stmt=$pdo->prepare($sql);
$stmt->execute($params);
$rows=$stmt->fetchAll();

}catch(PDOException $e){

http_response_code(500);
echo "<pre>Chyba databáze: ".h($e->getMessage())."</pre>";
exit;

}

?>
<!DOCTYPE html>
<html lang="cs">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Přehled pražských bytů</title>

<link rel="stylesheet" href="styles.css">

</head>

<body>

<div class="wrap">

<?php require_once __DIR__.'/inc/menu.php'; ?>

<h2>Přehled pražských bytů podle hard_score</h2>

<p class="hint">
Nalezeno bytů: <b><?=h($total)?></b>
</p>

<form method="get" class="filters">

Min score
<input type="number" name="min_score" value="<?=h($minScore)?>">

Max cena
<input type="number" name="max_price" value="<?=h($maxPrice)?>">

Min plocha
<input type="number" name="min_area" value="<?=h($minArea)?>">

Městská část
<select name="ward">

<option value="">-- všechny --</option>

<?php foreach($wards as $w): ?>

<option value="<?=h($w)?>" <?=($ward==$w?'selected':'')?>>
<?=h($w)?>
</option>

<?php endforeach; ?>

</select>

<button>Filtrovat</button>

<a href="vypis.php">reset</a>

</form>

<table>

<thead>
<tr>

<?php [$qs,$arr]=sortLink('name',$sort,$dir); ?>
<th><a href="?<?=h($qs)?>">Název<?=$arr?></a></th>

<?php [$qs,$arr]=sortLink('ward',$sort,$dir); ?>
<th><a href="?<?=h($qs)?>">Městská část<?=$arr?></a></th>

<?php [$qs,$arr]=sortLink('price',$sort,$dir); ?>
<th class="num"><a href="?<?=h($qs)?>">Cena<?=$arr?></a></th>

<?php [$qs,$arr]=sortLink('area',$sort,$dir); ?>
<th class="center"><a href="?<?=h($qs)?>">Plocha<?=$arr?></a></th>

<?php [$qs,$arr]=sortLink('floor',$sort,$dir); ?>
<th class="center"><a href="?<?=h($qs)?>">Podlaží<?=$arr?></a></th>

<?php [$qs,$arr]=sortLink('condition',$sort,$dir); ?>
<th><a href="?<?=h($qs)?>">Stav<?=$arr?></a></th>

<?php [$qs,$arr]=sortLink('metro',$sort,$dir); ?>
<th class="center"><a href="?<?=h($qs)?>">Metro<?=$arr?></a></th>

<th>Features</th>

<?php [$qs,$arr]=sortLink('score',$sort,$dir); ?>
<th class="center"><a href="?<?=h($qs)?>">Hard score<?=$arr?></a></th>

<?php [$qs,$arr]=sortLink('last',$sort,$dir); ?>
<th class="center"><a href="?<?=h($qs)?>">Naposledy viděno<?=$arr?></a></th>

<th class="center">Detail</th>

</tr>
</thead>

<tbody>

<?php foreach($rows as $row): ?>

<?php $score=(int)($row['hard_score']??0); ?>

<tr>

<td class="name"><?=h($row['name']??'')?></td>

<td><?=h($row['ward']??'')?></td>

<td class="num"><?=fmtPrice($row['price_czk']??null)?></td>

<td class="center"><?= (int)($row['usable_area']??0) ?></td>

<td class="center"><?= (int)($row['floor_number']??0) ?></td>

<td><?=h($row['building_condition']??'')?></td>

<td class="center"><?=metroLabel($row['metro_distance']??null)?></td>

<td class="tags"><?=renderFeatures($row['features']??null)?></td>

<td class="center <?=scoreClass($score)?>">
<?=$score?>
</td>

<td class="center"><?=h($row['last_seen']??'')?></td>

<td class="center">

<?php if(!empty($row['detail_url'])): ?>

<a href="<?=h($row['detail_url'])?>" target="_blank">Otevřít</a>

<?php else: ?>

<span class="na">N/A</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

<?php if(!$rows): ?>

<tr>
<td colspan="11" class="na">Žádná data.</td>
</tr>

<?php endif; ?>

</tbody>
</table>

</div>

</body>
</html>