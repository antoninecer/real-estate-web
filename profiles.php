<?php

require_once __DIR__.'/inc/connect.php';
require_once __DIR__.'/inc/helpers.php';

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

$conditionMap=[
1=>'Velmi dobrý',
2=>'Dobrý',
8=>'Před rekonstrukcí',
9=>'Po rekonstrukci',
4=>'Novostavba'
];

if($_SERVER['REQUEST_METHOD']==='POST'){

$rooms=$_POST['rooms']??[];
$category_sub_cb=implode(',',$rooms);

$conditions=$_POST['conditions']??[];
$building_condition=implode(',',$conditions);

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

$stmt->execute([

':category_sub_cb'=>$category_sub_cb,
':locality_region_id'=>$_POST['locality_region_id'],
':price_to'=>$_POST['price_to'],
':ownership'=>$_POST['ownership'],
':floor_number_from'=>$_POST['floor_number_from'],
':floor_number_to'=>$_POST['floor_number_to'],
':building_condition'=>$building_condition,
':balcony'=>isset($_POST['balcony']),
':cellar'=>isset($_POST['cellar']),
':usable_area_from'=>$_POST['usable_area_from'],
':limit_items'=>$_POST['limit_items'],
':id'=>$_POST['id']

]);

}

$profile=$pdo->query("
SELECT *
FROM estate_search_profiles
WHERE is_active=true
LIMIT 1
")->fetch();

$currentRooms=[];
if(!empty($profile['category_sub_cb'])) $currentRooms=explode(',',$profile['category_sub_cb']);

$currentConditions=[];
if(!empty($profile['building_condition'])) $currentConditions=explode(',',$profile['building_condition']);

?>

<!DOCTYPE html>
<html lang="cs">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Search profil</title>

<link rel="stylesheet" href="styles.css">

</head>
<body>

<div class="wrap">

<?php require_once __DIR__.'/inc/menu.php'; ?>

<h2>Search profil</h2>

<form method="post">

<input type="hidden" name="id" value="<?=$profile['id']?>">

<table>

<tr>
<td>Dispozice</td>
<td>
<?php foreach($roomMap as $id=>$label): ?>
<label style="margin-right:12px"><input type="checkbox" name="rooms[]" value="<?=$id?>" <?=in_array($id,$currentRooms)?'checked':''?>><?=$label?></label>
<?php endforeach; ?>
</td>
</tr>

<tr>
<td>Stav</td>
<td>
<?php foreach($conditionMap as $id=>$label): ?>
<label style="margin-right:12px"><input type="checkbox" name="conditions[]" value="<?=$id?>" <?=in_array($id,$currentConditions)?'checked':''?>><?=$label?></label>
<?php endforeach; ?>
</td>
</tr>

<tr>
<td>Region</td>
<td><input name="locality_region_id" value="<?=$profile['locality_region_id']?>"></td>
</tr>

<tr>
<td>Max cena</td>
<td><input name="price_to" value="<?=$profile['price_to']?>"></td>
</tr>

<tr>
<td>Patro od</td>
<td><input name="floor_number_from" value="<?=$profile['floor_number_from']?>"></td>
</tr>

<tr>
<td>Patro do</td>
<td><input name="floor_number_to" value="<?=$profile['floor_number_to']?>"></td>
</tr>

<tr>
<td>Min plocha</td>
<td><input name="usable_area_from" value="<?=$profile['usable_area_from']?>"></td>
</tr>

<tr>
<td>Ownership</td>
<td><input name="ownership" value="<?=$profile['ownership']?>"></td>
</tr>

<tr>
<td>Limit výsledků</td>
<td><input name="limit_items" value="<?=$profile['limit_items']?>"></td>
</tr>

<tr>
<td>Balkon</td>
<td><input type="checkbox" name="balcony" <?=$profile['balcony']?'checked':''?>></td>
</tr>

<tr>
<td>Sklep</td>
<td><input type="checkbox" name="cellar" <?=$profile['cellar']?'checked':''?>></td>
</tr>

</table>

<button type="submit">Uložit</button>

</form>

</div>

</body>
</html>