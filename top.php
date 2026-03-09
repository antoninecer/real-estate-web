<?php
require_once __DIR__.'/inc/connect.php';
require_once __DIR__.'/inc/helpers.php';


/* -----------------------------
   POVOLENÉ ŘAZENÍ
------------------------------*/

$allowedSort = [
    'name'  => 'v.name',
    'ward'  => 'v.ward',
    'price' => 'v.price_czk',
    'area'  => 'v.usable_area',
    'metro' => 'v.metro_distance',
    'hard'  => 'v.hard_score',
    'ai'    => 'r.ai_score',
    'final' => 'final_score'
];

$sort = $_GET['sort'] ?? 'final';
$dir  = strtolower($_GET['dir'] ?? 'desc');

if(!isset($allowedSort[$sort])) $sort='final';
if($dir!=='asc' && $dir!=='desc') $dir='desc';

$orderByCol=$allowedSort[$sort];

$orderSql="
ORDER BY {$orderByCol} ".strtoupper($dir)." NULLS LAST
";


/* -----------------------------
   SORT LINK
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
   QUERY
------------------------------*/

$sql="
SELECT
v.name,
v.ward,
v.price_czk,
v.usable_area,
v.metro_distance,
v.hard_score,
r.ai_score,
r.summary,
v.detail_url,

ROUND(
v.hard_score*0.6 + COALESCE(r.ai_score,0)*0.4
) AS final_score

FROM v_estates_hard_score v
LEFT JOIN estate_ai_reviews r USING(hash_id)

$orderSql

LIMIT 50
";

$stmt=$pdo->query($sql);
$rows=$stmt->fetchAll();

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

<table>

<thead>

<tr>

<?php [$qs,$arr]=sortLink('name',$sort,$dir); ?>
<th><a href="?<?=h($qs)?>">Název<?=$arr?></a></th>

<?php [$qs,$arr]=sortLink('ward',$sort,$dir); ?>
<th><a href="?<?=h($qs)?>">Městská část<?=$arr?></a></th>

<?php [$qs,$arr]=sortLink('price',$sort,$dir); ?>
<th><a href="?<?=h($qs)?>">Cena<?=$arr?></a></th>

<?php [$qs,$arr]=sortLink('area',$sort,$dir); ?>
<th><a href="?<?=h($qs)?>">Plocha<?=$arr?></a></th>

<?php [$qs,$arr]=sortLink('metro',$sort,$dir); ?>
<th><a href="?<?=h($qs)?>">Metro<?=$arr?></a></th>

<?php [$qs,$arr]=sortLink('hard',$sort,$dir); ?>
<th><a href="?<?=h($qs)?>">Hard<?=$arr?></a></th>

<?php [$qs,$arr]=sortLink('ai',$sort,$dir); ?>
<th><a href="?<?=h($qs)?>">AI<?=$arr?></a></th>

<?php [$qs,$arr]=sortLink('final',$sort,$dir); ?>
<th><a href="?<?=h($qs)?>">Final<?=$arr?></a></th>

<th>AI summary</th>

<th>Detail</th>

</tr>

</thead>

<tbody>

<?php foreach($rows as $r): ?>

<tr>

<td><?=h($r["name"])?></td>

<td><?=h($r["ward"])?></td>

<td><?=fmtPrice($r["price_czk"])?></td>

<td><?=$r["usable_area"]?> m²</td>

<td><?=metroLabel($r["metro_distance"])?></td>

<td><?=$r["hard_score"]?></td>

<td><?= $r["ai_score"] ?? '<span class="na">…</span>' ?></td>

<td><b><?=$r["final_score"]?></b></td>

<td><?= $r["summary"] ? h($r["summary"]) : '<span class="na">AI počítá…</span>' ?></td>

<td>

<?php if(!empty($r["detail_url"])): ?>

<a href="<?=h($r["detail_url"])?>" target="_blank">detail</a>

<?php else: ?>

<span class="na">N/A</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</body>
</html>