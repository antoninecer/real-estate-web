<?php

require_once __DIR__.'/inc/connect.php';
require_once __DIR__.'/inc/helpers.php';


/* DISPOZICE */

$roomMap=[
2=>'1+kk',
3=>'1+1',
4=>'2+kk',
5=>'2+1',
6=>'3+kk',
7=>'3+1',
8=>'4+kk',
9=>'4+1',
10=>'5+kk',
11=>'5+1',
12=>'6+',
16=>'Atypický'
];


/* STAV */

$conditionMap=[
1=>'Velmi dobrý',
2=>'Dobrý',
8=>'Před rekonstrukcí',
9=>'Po rekonstrukci',
4=>'Novostavba'
];


/* SPUSTIT SCAN */

if(isset($_GET['run_scan'])){

$url="https://n8n.rightdone.eu/webhook/srealityscan";

$ch=curl_init($url);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_TIMEOUT,2);
curl_exec($ch);
curl_close($ch);

header("Location: profiles.php?scan=1");
exit;

}


/* ULOZENI PROFILU */

if($_SERVER['REQUEST_METHOD']==='POST'){

$rooms=$_POST['rooms']??[];
$category_sub_cb=implode(',',$rooms);

$conditions=$_POST['conditions']??[];
$building_condition=implode(',',$conditions);

$balcony = !empty($_POST['balcony']) ? true : false;
$cellar  = !empty($_POST['cellar']) ? true : false;

$floor_from = $_POST['floor_number_from']!=='' ? $_POST['floor_number_from'] : null;
$floor_to   = $_POST['floor_number_to']!=='' ? $_POST['floor_number_to'] : null;

$stmt=$pdo->prepare("
UPDATE estate_search_profiles SET
category_sub_cb=:category_sub_cb,
locality_region_id=:locality_region_id,
price_to=:price_to,
ownership=:ownership,
floor_number_from=:floor_number_from,
floor_number_to=:floor_number_to,
building_condition=:building_condition,
balcony=:balcony,
cellar=:cellar,
usable_area_from=:usable_area_from,
limit_items=:limit_items,
updated_at=now()
WHERE id=:id
");
$stmt->bindValue(':category_sub_cb',$category_sub_cb);
$stmt->bindValue(':locality_region_id',$_POST['locality_region_id']);
$stmt->bindValue(':price_to',str_replace(' ','',$_POST['price_to']));
$stmt->bindValue(':ownership',$_POST['ownership']);
$stmt->bindValue(':floor_number_from',$floor_from);
$stmt->bindValue(':floor_number_to',$floor_to);
$stmt->bindValue(':building_condition',$building_condition);
$stmt->bindValue(':balcony',$balcony,PDO::PARAM_BOOL);
$stmt->bindValue(':cellar',$cellar,PDO::PARAM_BOOL);
$stmt->bindValue(':usable_area_from',$_POST['usable_area_from']);
$stmt->bindValue(':limit_items',$_POST['limit_items']);
$stmt->bindValue(':id',$_POST['id']);

$stmt->execute();

header("Location: profiles.php?saved=1");
exit;

}


/* NACTENI PROFILU */

$profile=$pdo->query("
SELECT *
FROM estate_search_profiles
WHERE is_active=true
LIMIT 1
")->fetch();

$currentRooms=[];
if(!empty($profile['category_sub_cb']))
$currentRooms=explode(',',$profile['category_sub_cb']);

$currentConditions=[];
if(!empty($profile['building_condition']))
$currentConditions=explode(',',$profile['building_condition']);

?>

<!DOCTYPE html>
<html lang="cs">

<head>

<title>Přehled pražských bytů</title>

<?php require_once __DIR__.'/inc/head.php'; ?>

</head>
<body>

<div class="wrap">

<?php require_once __DIR__.'/inc/menu.php'; ?>

<h2>Search profil</h2>

<?php if(isset($_GET['saved'])): ?>
<div style="color:green;margin-bottom:10px">
Profil uložen
</div>
<?php endif; ?>

<?php if(isset($_GET['scan'])): ?>
<div style="color:green;margin-bottom:10px">
Scan spuštěn
</div>
<?php endif; ?>


<form method="post">

<input type="hidden" name="id" value="<?=$profile['id']?>">

<table>

<tr>
<td>Dispozice</td>
<td>
<?php foreach($roomMap as $id=>$label): ?>
<label style="margin-right:12px">
<input type="checkbox" name="rooms[]" value="<?=$id?>" <?=in_array($id,$currentRooms)?'checked':''?>>
<?=$label?>
</label>
<?php endforeach; ?>
</td>
</tr>

<tr>
<td>Stav</td>
<td>
<?php foreach($conditionMap as $id=>$label): ?>
<label style="margin-right:12px">
<input type="checkbox" name="conditions[]" value="<?=$id?>" <?=in_array($id,$currentConditions)?'checked':''?>>
<?=$label?>
</label>
<?php endforeach; ?>
</td>
</tr>

<tr>
<td>Region</td>
<td>
<input name="locality_region_id" value="<?=$profile['locality_region_id']?>">
</td>
</tr>

<tr>
<td>Max cena</td>
<td>
<input 
name="price_to" 
id="price_to"
value="<?=number_format($profile['price_to'],0,'',' ')?>">
</td>
</tr>

<tr>
<td>Patro od</td>
<td>
<input name="floor_number_from" value="<?=$profile['floor_number_from']?>">
</td>
</tr>

<tr>
<td>Patro do</td>
<td>
<input name="floor_number_to" value="<?=$profile['floor_number_to']?>">
</td>
</tr>

<tr>
<td>Min plocha</td>
<td>
<input name="usable_area_from" value="<?=$profile['usable_area_from']?>">
</td>
</tr>

<tr>
<td>Ownership</td>
<td>
<input name="ownership" value="<?=$profile['ownership']?>">
</td>
</tr>

<tr>
<td>Limit výsledků</td>
<td>
<input name="limit_items" value="<?=$profile['limit_items']?>">
</td>
</tr>

<tr>
<td>Balkon</td>
<td>
<input type="checkbox" name="balcony" value="1" <?=$profile['balcony']?'checked':''?>>
</td>
</tr>

<tr>
<td>Sklep</td>
<td>
<input type="checkbox" name="cellar" value="1" <?=$profile['cellar']?'checked':''?>>
</td>
</tr>

</table>

<br>

<button type="submit">Uložit</button>

<a href="profiles.php?run_scan=1" style="margin-left:20px">
<button type="button">Spustit scan</button>
</a>

</form>

</div>
<script>

function formatNumberInput(el){

let value = el.value.replace(/\s/g,'');
if(value === '') return;

value = parseInt(value,10).toString();
el.value = value.replace(/\B(?=(\d{3})+(?!\d))/g,' ');

}

document.getElementById('price_to').addEventListener('input',function(){

let caret = this.selectionStart;

let raw = this.value.replace(/\s/g,'');

this.value = raw.replace(/\B(?=(\d{3})+(?!\d))/g,' ');

this.setSelectionRange(caret,caret);

});

</script>
</body>
</html>