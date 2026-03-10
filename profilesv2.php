<?php

require_once __DIR__.'/inc/connect.php';
require_once __DIR__.'/inc/helpers.php';

/* MAPY */
$roomMap=[
2=>'1+kk', 3=>'1+1', 4=>'2+kk', 5=>'2+1', 6=>'3+kk', 7=>'3+1', 8=>'4+kk', 9=>'4+1', 10=>'5+kk', 11=>'5+1', 12=>'6+', 16=>'Atypický'
];

$conditionMap=[
1=>'Velmi dobrý', 2=>'Dobrý', 8=>'Před rekonstrukcí', 9=>'Po rekonstrukci', 4=>'Novostavba'
];

/* SPUSTIT SCAN */
if(isset($_GET['run_scan'])){
    $url="https://n8n.rightdone.eu/webhook/srealityscan";
    $ch=curl_init($url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_TIMEOUT,2);
    curl_exec($ch);
    curl_close($ch);
    header("Location: profilesv2.php?scan=1");
    exit;
}

/* ULOZENI PROFILU */
if($_SERVER['REQUEST_METHOD']==='POST'){
    $id = $_POST['id'] ?: null;
    $name = $_POST['name'] ?: 'Nový profil';
    $is_active = !empty($_POST['is_active']) ? 'true' : 'false';
    $category_type_cb = (int)$_POST['category_type_cb'];
    
    $rooms = $_POST['rooms']??[];
    $category_sub_cb = implode(',',$rooms);
    $conditions = $_POST['conditions']??[];
    $building_condition = implode(',',$conditions);
    
    $balcony = !empty($_POST['balcony']) ? 'true' : 'false';
    $cellar  = !empty($_POST['cellar']) ? 'true' : 'false';
    $floor_from = $_POST['floor_number_from']!=='' ? (int)$_POST['floor_number_from'] : null;
    $floor_to   = $_POST['floor_number_to']!=='' ? (int)$_POST['floor_number_to'] : null;
    $price_to = (int)str_replace(' ','',$_POST['price_to']);
    $locality_region_id = (int)$_POST['locality_region_id'];
    $usable_area_from = (int)$_POST['usable_area_from'];
    $limit_items = (int)$_POST['limit_items'];
    $ownership = (int)$_POST['ownership'];

    if($id){
        $stmt=$pdo->prepare("
            UPDATE estate_search_profiles SET
            name=:name, is_active=:is_active, category_type_cb=:category_type_cb,
            category_sub_cb=:category_sub_cb, locality_region_id=:locality_region_id,
            price_to=:price_to, ownership=:ownership, floor_number_from=:floor_number_from,
            floor_number_to=:floor_number_to, building_condition=:building_condition,
            balcony=:balcony, cellar=:cellar, usable_area_from=:usable_area_from,
            limit_items=:limit_items, updated_at=now()
            WHERE id=:id
        ");
        $stmt->bindValue(':id',$id);
    } else {
        $stmt=$pdo->prepare("
            INSERT INTO estate_search_profiles (
                name, is_active, category_type_cb, category_sub_cb, locality_region_id,
                price_to, ownership, floor_number_from, floor_number_to,
                building_condition, balcony, cellar, usable_area_from, limit_items
            ) VALUES (
                :name, :is_active, :category_type_cb, :category_sub_cb, :locality_region_id,
                :price_to, :ownership, :floor_number_from, :floor_number_to,
                :building_condition, :balcony, :cellar, :usable_area_from, :limit_items
            )
        ");
    }
    
    $stmt->bindValue(':name',$name);
    $stmt->bindValue(':is_active',$is_active, PDO::PARAM_STR); // PostgreSQL boolean as string
    $stmt->bindValue(':category_type_cb',$category_type_cb);
    $stmt->bindValue(':category_sub_cb',$category_sub_cb);
    $stmt->bindValue(':locality_region_id',$locality_region_id);
    $stmt->bindValue(':price_to',$price_to);
    $stmt->bindValue(':ownership',$ownership);
    $stmt->bindValue(':floor_number_from',$floor_from);
    $stmt->bindValue(':floor_number_to',$floor_to);
    $stmt->bindValue(':building_condition',$building_condition);
    $stmt->bindValue(':balcony',$balcony, PDO::PARAM_STR);
    $stmt->bindValue(':cellar',$cellar, PDO::PARAM_STR);
    $stmt->bindValue(':usable_area_from',$usable_area_from);
    $stmt->bindValue(':limit_items',$limit_items);
    $stmt->execute();

    header("Location: profilesv2.php?saved=1");
    exit;
}

/* REZIM EDITACE / PRIDANI */
$editMode = isset($_GET['id']) || isset($_GET['add']);
$profile = null;

if(isset($_GET['id'])){
    $stmt = $pdo->prepare("SELECT * FROM estate_search_profiles WHERE id = :id");
    $stmt->execute(['id' => $_GET['id']]);
    $profile = $stmt->fetch();
}

$currentRooms = ($profile && !empty($profile['category_sub_cb'])) ? explode(',',$profile['category_sub_cb']) : [];
$currentConditions = ($profile && !empty($profile['building_condition'])) ? explode(',',$profile['building_condition']) : [];

/* SEZNAM */
$profiles = $pdo->query("SELECT * FROM estate_search_profiles ORDER BY id DESC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Search profily v2</title>
    <?php require_once __DIR__.'/inc/head.php'; ?>
    <style>
        .status-on { color: green; font-weight: bold; }
        .status-off { color: gray; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table th, table td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        .btn-add { background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; display: inline-block; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="wrap">
    <?php require_once __DIR__.'/inc/menu.php'; ?>

    <?php if(!$editMode): ?>
        <h2>Seznam vyhledávacích profilů</h2>
        
        <?php if(isset($_GET['saved'])): ?><div style="color:green;margin-bottom:10px">Uloženo</div><?php endif; ?>
        <?php if(isset($_GET['scan'])): ?><div style="color:green;margin-bottom:10px">Scan spuštěn</div><?php endif; ?>

        <a href="?add=1" class="btn-add">+ Přidat nový profil</a>
        <a href="?run_scan=1" style="margin-left:10px"><button type="button">Spustit scan (vše aktivní)</button></a>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Název</th>
                    <th>Typ</th>
                    <th>Aktivní</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($profiles as $p): ?>
                <tr>
                    <td><?=$p['id']?></td>
                    <td><b><?=$p['name']?></b></td>
                    <td><?=$p['category_type_cb']==1 ? 'Prodej' : 'Pronájem'?></td>
                    <td><span class="<?=$p['is_active']?'status-on':'status-off'?>"><?=$p['is_active']?'ANO':'NE'?></span></td>
                    <td><a href="?id=<?=$p['id']?>">Upravit</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php else: ?>
        <h2><?=isset($_GET['add'])?'Nový profil':'Upravit profil: '.$profile['name']?></h2>
        <a href="profilesv2.php">&laquo; Zpět na seznam</a><br><br>

        <form method="post">
            <input type="hidden" name="id" value="<?=$profile['id']??''?>">
            
            <table>
                <tr>
                    <td>Název profilu</td>
                    <td><input name="name" value="<?=$profile['name']??''?>" style="width:300px" required></td>
                </tr>
                <tr>
                    <td>Aktivní</td>
                    <td><input type="checkbox" name="is_active" value="1" <?=($profile['is_active']??true)?'checked':''?>></td>
                </tr>
                <tr>
                    <td>Typ nabídky</td>
                    <td>
                        <label><input type="radio" name="category_type_cb" value="1" <?=($profile['category_type_cb']??1)==1?'checked':''?>> Prodej</label>
                        <label style="margin-left:15px"><input type="radio" name="category_type_cb" value="2" <?=($profile['category_type_cb']??1)==2?'checked':''?>> Pronájem</label>
                    </td>
                </tr>
                <tr>
                    <td>Dispozice</td>
                    <td>
                        <?php foreach($roomMap as $rid=>$label): ?>
                        <label style="margin-right:10px"><input type="checkbox" name="rooms[]" value="<?=$rid?>" <?=in_array($rid,$currentRooms)?'checked':''?>> <?=$label?></label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <td>Stav</td>
                    <td>
                        <?php foreach($conditionMap as $cid=>$label): ?>
                        <label style="margin-right:10px"><input type="checkbox" name="conditions[]" value="<?=$cid?>" <?=in_array($cid,$currentConditions)?'checked':''?>> <?=$label?></label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr><td>Region ID</td><td><input name="locality_region_id" value="<?=$profile['locality_region_id']??10?>"></td></tr>
                <tr><td>Max cena</td><td><input name="price_to" id="price_to" value="<?=number_format($profile['price_to']??0,0,'',' ')?>"></td></tr>
                <tr><td>Patro od</td><td><input name="floor_number_from" value="<?=$profile['floor_number_from']??''?>"></td></tr>
                <tr><td>Patro do</td><td><input name="floor_number_to" value="<?=$profile['floor_number_to']??''?>"></td></tr>
                <tr><td>Min plocha</td><td><input name="usable_area_from" value="<?=$profile['usable_area_from']??0?>"></td></tr>
                <tr><td>Vlastnictví</td><td><input name="ownership" value="<?=$profile['ownership']??''?>"></td></tr>
                <tr><td>Limit výsledků</td><td><input name="limit_items" value="<?=$profile['limit_items']??50?>"></td></tr>
                <tr><td>Balkon</td><td><input type="checkbox" name="balcony" value="1" <?=($profile['balcony']??false)?'checked':''?>></td></tr>
                <tr><td>Sklep</td><td><input type="checkbox" name="cellar" value="1" <?=($profile['cellar']??false)?'checked':''?>></td></tr>
            </table>

            <button type="submit">Uložit profil</button>
        </form>
    <?php endif; ?>
</div>

<script>
document.getElementById('price_to')?.addEventListener('input', function(){
    let caret = this.selectionStart;
    let raw = this.value.replace(/\s/g,'');
    this.value = raw.replace(/\B(?=(\d{3})+(?!\d))/g,' ');
    this.setSelectionRange(caret,caret);
});
</script>
</body>
</html>
