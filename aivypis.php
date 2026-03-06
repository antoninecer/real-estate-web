<?php
require_once __DIR__.'/inc/connect.php';
require_once __DIR__.'/inc/helpers.php';


/* -----------------------------
   POVOLENÉ ŘAZENÍ
------------------------------*/

$allowedSort = [
    'name' => 'v.name',
    'ward' => 'v.ward',
    'price' => 'v.price_czk',
    'area' => 'v.usable_area',
    'floor' => 'v.floor_number',
    'condition' => 'v.building_condition',
    'metro' => 'v.metro_distance',
    'score' => 'v.hard_score',
    'ai' => 'r.ai_score',
    'last' => 'v.last_seen',
];

$sort = $_GET['sort'] ?? 'ai';
$dir  = strtolower($_GET['dir'] ?? 'desc');

if (!isset($allowedSort[$sort])) $sort = 'ai';
if ($dir !== 'asc' && $dir !== 'desc') $dir = 'desc';

$orderByCol = $allowedSort[$sort];

$orderSql = "
ORDER BY {$orderByCol} ".strtoupper($dir)." NULLS LAST,
         v.hard_score DESC NULLS LAST,
         v.last_seen DESC NULLS LAST
";


/* -----------------------------
   FUNKCE NA LINKY ŘAZENÍ
------------------------------*/

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


/* -----------------------------
   FILTRY
------------------------------*/

$minScore = $_GET['min_score'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$minArea  = $_GET['min_area'] ?? '';
$ward     = $_GET['ward'] ?? '';
$parking  = $_GET['parking'] ?? '';

$where=[];
$params=[];

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
    $where[]="v.features::text ILIKE '%garage%' OR v.features::text ILIKE '%parking%'";
}

$whereSql='';
if($where){
    $whereSql="WHERE ".implode(" AND ",$where);
}


/* -----------------------------
   POČET BYTŮ
------------------------------*/

$countSql="
SELECT count(*)
FROM v_estates_hard_score v
LEFT JOIN estate_ai_reviews r USING(hash_id)
$whereSql
";

$stmt=$pdo->prepare($countSql);
$stmt->execute($params);
$total=$stmt->fetchColumn();


/* -----------------------------
   SEZNAM MĚSTSKÝCH ČÁSTÍ
------------------------------*/

$wards=$pdo->query("
SELECT DISTINCT ward
FROM v_estates_hard_score
WHERE ward IS NOT NULL
ORDER BY ward
")->fetchAll(PDO::FETCH_COLUMN);


/* -----------------------------
   HLAVNÍ QUERY
------------------------------*/

$sql="
SELECT
v.name,
v.ward,
v.price_czk,
v.usable_area,
v.floor_number,
v.building_condition,
v.metro_distance,
v.features,
v.hard_score,
v.detail_url,
v.last_seen,

r.ai_score,
r.verdict,
r.summary

FROM v_estates_hard_score v
LEFT JOIN estate_ai_reviews r USING(hash_id)

$whereSql
$orderSql
LIMIT 500
";

$stmt=$pdo->prepare($sql);
$stmt->execute($params);
$rows=$stmt->fetchAll();

?>


<!DOCTYPE html>
<html lang="cs">
<head>

<title>AI přehled bytů</title>

<?php require_once __DIR__.'/inc/head.php'; ?>

</head>

<body>

<div class="wrap">

<?php require_once __DIR__.'/inc/menu.php'; ?>

<h2>Pražské byty – hard + AI hodnocení</h2>

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

<label style="margin-left:10px">

<input type="checkbox" name="parking" value="1" <?=($parking?'checked':'')?>>

Parkování / garáž

</label>

<button>Filtrovat</button>

<a href="aivypis.php">reset</a>

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
<th class="center"><a href="?<?=h($qs)?>">Patro<?=$arr?></a></th>

<?php [$qs,$arr]=sortLink('condition',$sort,$dir); ?>
<th><a href="?<?=h($qs)?>">Stav<?=$arr?></a></th>

<?php [$qs,$arr]=sortLink('metro',$sort,$dir); ?>
<th class="center"><a href="?<?=h($qs)?>">Metro<?=$arr?></a></th>

<th>Features</th>

<?php [$qs,$arr]=sortLink('score',$sort,$dir); ?>
<th class="center"><a href="?<?=h($qs)?>">Hard<?=$arr?></a></th>

<?php [$qs,$arr]=sortLink('ai',$sort,$dir); ?>
<th class="center"><a href="?<?=h($qs)?>">AI<?=$arr?></a></th>

<th class="center">Verdict</th>

<th>AI summary</th>

<?php [$qs,$arr]=sortLink('last',$sort,$dir); ?>
<th class="center"><a href="?<?=h($qs)?>">Naposledy<?=$arr?></a></th>

<th class="center">Detail</th>

</tr>
</thead>


<tbody>

<?php foreach($rows as $row): ?>

<?php
$score=(int)($row['hard_score']??0);
$ai=(int)($row['ai_score']??0);
?>

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

<td class="center <?=scoreClass($ai)?>">

<?php if($row['ai_score']!==null): ?>

<?=$ai?>

<?php else: ?>

<span class="na">…</span>

<?php endif; ?>

</td>

<td class="center">

<?= $row['verdict'] ? h($row['verdict']) : '<span class="na">…</span>' ?>

</td>

<td>

<?= $row['summary'] ? h($row['summary']) : '<span class="na">AI počítá…</span>' ?>

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
<td colspan="13" class="na">Žádná data.</td>
</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</body>
</html>