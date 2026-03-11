<?php

require_once __DIR__.'/inc/connect.php';
require_once __DIR__.'/inc/helpers.php';

/* MAPY */
$roomMap = [
    2 => '1+kk', 3 => '1+1', 4 => '2+kk', 5 => '2+1', 6 => '3+kk', 7 => '3+1',
    8 => '4+kk', 9 => '4+1', 10 => '5+kk', 11 => '5+1', 12 => '6+', 16 => 'Atypický'
];

$conditionMap = [
    1 => 'Velmi dobrý', 2 => 'Dobrý', 8 => 'Před rekonstrukcí', 9 => 'Po rekonstrukci', 4 => 'Novostavba'
];

/* SCORING PROFILY */
$scoringProfiles = $pdo->query("
    SELECT id, name
    FROM estate_scoring_profiles
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

/* SPUSTIT SCAN */
if (isset($_GET['run_scan'])) {
    $url = "https://n8n.rightdone.eu/webhook/scrapper";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_exec($ch);
    curl_close($ch);

    header("Location: profilesv2.php?scan=1");
    exit;
}

/* ULOZENI PROFILU */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?: null;

    $name = trim($_POST['name'] ?? '') ?: 'Nový profil';
    $ai_context = trim($_POST['ai_context'] ?? '');
    $is_active = !empty($_POST['is_active']) ? 'true' : 'false';
    $category_type_cb = (int)($_POST['category_type_cb'] ?? 1);

    $rooms = $_POST['rooms'] ?? [];
    $category_sub_cb = implode(',', $rooms);

    $conditions = $_POST['conditions'] ?? [];
    $building_condition = implode(',', $conditions);

    $balcony = !empty($_POST['balcony']) ? 'true' : 'false';
    $cellar = !empty($_POST['cellar']) ? 'true' : 'false';

    $floor_from = $_POST['floor_number_from'] !== '' ? (int)$_POST['floor_number_from'] : null;
    $floor_to = $_POST['floor_number_to'] !== '' ? (int)$_POST['floor_number_to'] : null;
    $price_to = $_POST['price_to'] !== '' ? (int)str_replace(' ', '', $_POST['price_to']) : null;
    $locality_region_id = $_POST['locality_region_id'] !== '' ? (int)$_POST['locality_region_id'] : null;
    $usable_area_from = $_POST['usable_area_from'] !== '' ? (int)$_POST['usable_area_from'] : null;
    $limit_items = $_POST['limit_items'] !== '' ? (int)$_POST['limit_items'] : 50;
    $ownership = $_POST['ownership'] !== '' ? (int)$_POST['ownership'] : null;

    $scoringProfileId = $_POST['scoring_profile_id'] !== '' ? (int)$_POST['scoring_profile_id'] : null;
    $scoreTemplateProfileId = $_POST['score_template_profile_id'] !== '' ? (int)$_POST['score_template_profile_id'] : null;

    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE estate_search_profiles SET
                name = :name,
                ai_context = :ai_context,
                is_active = :is_active,
                category_type_cb = :category_type_cb,
                category_sub_cb = :category_sub_cb,
                locality_region_id = :locality_region_id,
                price_to = :price_to,
                ownership = :ownership,
                floor_number_from = :floor_number_from,
                floor_number_to = :floor_number_to,
                building_condition = :building_condition,
                balcony = :balcony,
                cellar = :cellar,
                usable_area_from = :usable_area_from,
                limit_items = :limit_items,
                scoring_profile_id = :scoring_profile_id,
                updated_at = now()
            WHERE id = :id
        ");

        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':ai_context', $ai_context !== '' ? $ai_context : null, $ai_context !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':is_active', $is_active, PDO::PARAM_STR);
        $stmt->bindValue(':category_type_cb', $category_type_cb, PDO::PARAM_INT);
        $stmt->bindValue(':category_sub_cb', $category_sub_cb !== '' ? $category_sub_cb : null, $category_sub_cb !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':locality_region_id', $locality_region_id, $locality_region_id !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':price_to', $price_to, $price_to !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':ownership', $ownership, $ownership !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':floor_number_from', $floor_from, $floor_from !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':floor_number_to', $floor_to, $floor_to !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':building_condition', $building_condition !== '' ? $building_condition : null, $building_condition !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':balcony', $balcony, PDO::PARAM_STR);
        $stmt->bindValue(':cellar', $cellar, PDO::PARAM_STR);
        $stmt->bindValue(':usable_area_from', $usable_area_from, $usable_area_from !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':limit_items', $limit_items, PDO::PARAM_INT);
        $stmt->bindValue(':scoring_profile_id', $scoringProfileId, $scoringProfileId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->execute();
    } else {
        $pdo->beginTransaction();

        try {
            $newScoringProfileName = $name . ' - scoring';

            $stmt = $pdo->prepare("
                INSERT INTO estate_scoring_profiles (name)
                VALUES (:name)
                RETURNING id
            ");
            $stmt->execute([
                ':name' => $newScoringProfileName
            ]);

            $newScoringProfileId = (int)$stmt->fetchColumn();

            if ($scoreTemplateProfileId) {
                $stmt = $pdo->prepare("
                    INSERT INTO estate_scoring_rules
                    (scoring_profile_id, rule_group, min_value, max_value, text_match, points)
                    SELECT
                        :new_scoring_profile_id,
                        rule_group,
                        min_value,
                        max_value,
                        text_match,
                        points
                    FROM estate_scoring_rules
                    WHERE scoring_profile_id = :template_scoring_profile_id
                ");
                $stmt->execute([
                    ':new_scoring_profile_id' => $newScoringProfileId,
                    ':template_scoring_profile_id' => $scoreTemplateProfileId
                ]);
            }

            $stmt = $pdo->prepare("
                INSERT INTO estate_search_profiles (
                    name,
                    ai_context,
                    is_active,
                    category_type_cb,
                    category_sub_cb,
                    locality_region_id,
                    price_to,
                    ownership,
                    floor_number_from,
                    floor_number_to,
                    building_condition,
                    balcony,
                    cellar,
                    usable_area_from,
                    limit_items,
                    scoring_profile_id
                ) VALUES (
                    :name,
                    :ai_context,
                    :is_active,
                    :category_type_cb,
                    :category_sub_cb,
                    :locality_region_id,
                    :price_to,
                    :ownership,
                    :floor_number_from,
                    :floor_number_to,
                    :building_condition,
                    :balcony,
                    :cellar,
                    :usable_area_from,
                    :limit_items,
                    :scoring_profile_id
                )
            ");

            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':ai_context', $ai_context !== '' ? $ai_context : null, $ai_context !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':is_active', $is_active, PDO::PARAM_STR);
            $stmt->bindValue(':category_type_cb', $category_type_cb, PDO::PARAM_INT);
            $stmt->bindValue(':category_sub_cb', $category_sub_cb !== '' ? $category_sub_cb : null, $category_sub_cb !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':locality_region_id', $locality_region_id, $locality_region_id !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':price_to', $price_to, $price_to !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':ownership', $ownership, $ownership !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':floor_number_from', $floor_from, $floor_from !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':floor_number_to', $floor_to, $floor_to !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':building_condition', $building_condition !== '' ? $building_condition : null, $building_condition !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':balcony', $balcony, PDO::PARAM_STR);
            $stmt->bindValue(':cellar', $cellar, PDO::PARAM_STR);
            $stmt->bindValue(':usable_area_from', $usable_area_from, $usable_area_from !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':limit_items', $limit_items, PDO::PARAM_INT);
            $stmt->bindValue(':scoring_profile_id', $newScoringProfileId, PDO::PARAM_INT);
            $stmt->execute();

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    header("Location: profilesv2.php?saved=1");
    exit;
}

/* REZIM EDITACE / PRIDANI */
$editMode = isset($_GET['id']) || isset($_GET['add']);
$profile = null;

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM estate_search_profiles WHERE id = :id");
    $stmt->execute(['id' => $_GET['id']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
}

$currentRooms = ($profile && !empty($profile['category_sub_cb'])) ? explode(',', $profile['category_sub_cb']) : [];
$currentConditions = ($profile && !empty($profile['building_condition'])) ? explode(',', $profile['building_condition']) : [];

/* SEZNAM */
$profiles = $pdo->query("
    SELECT sp.*, esp.name AS scoring_profile_name
    FROM estate_search_profiles sp
    LEFT JOIN estate_scoring_profiles esp ON esp.id = sp.scoring_profile_id
    ORDER BY sp.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

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
        table th, table td { padding: 8px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
        .btn-add { background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; display: inline-block; margin-bottom: 20px; }
        textarea { width: 100%; min-height: 120px; }
        .muted { color: #666; font-size: 12px; }
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
                    <th>Scoring</th>
                    <th>AI kontext</th>
                    <th>Aktivní</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($profiles as $p): ?>
                <tr>
                    <td><?=htmlspecialchars((string)$p['id'])?></td>
                    <td><b><?=htmlspecialchars($p['name'])?></b></td>
                    <td><?=((int)$p['category_type_cb'] === 1) ? 'Prodej' : 'Pronájem'?></td>
                    <td><?=htmlspecialchars($p['scoring_profile_name'] ?? '')?></td>
                    <td>
                        <?php if(!empty($p['ai_context'])): ?>
                            <?=nl2br(htmlspecialchars(mb_strimwidth($p['ai_context'], 0, 120, '...')));?>
                        <?php else: ?>
                            <span class="muted">Bez AI kontextu</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="<?=$p['is_active'] ? 'status-on' : 'status-off'?>"><?=$p['is_active'] ? 'ANO' : 'NE'?></span></td>
                    <td><a href="?id=<?=$p['id']?>">Upravit</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php else: ?>
        <h2><?=isset($_GET['add']) ? 'Nový profil' : 'Upravit profil: '.htmlspecialchars($profile['name'])?></h2>
        <a href="profilesv2.php">&laquo; Zpět na seznam</a><br><br>

        <form method="post">
            <input type="hidden" name="id" value="<?=htmlspecialchars($profile['id'] ?? '')?>">

            <table>
                <tr>
                    <td>Název profilu</td>
                    <td><input name="name" value="<?=htmlspecialchars($profile['name'] ?? '')?>" style="width:300px" required></td>
                </tr>
                <tr>
                    <td>Aktivní</td>
                    <td><input type="checkbox" name="is_active" value="1" <?=($profile['is_active'] ?? true) ? 'checked' : ''?>></td>
                </tr>
                <tr>
                    <td>Typ nabídky</td>
                    <td>
                        <label><input type="radio" name="category_type_cb" value="1" <?=($profile['category_type_cb'] ?? 1) == 1 ? 'checked' : ''?>> Prodej</label>
                        <label style="margin-left:15px"><input type="radio" name="category_type_cb" value="2" <?=($profile['category_type_cb'] ?? 1) == 2 ? 'checked' : ''?>> Pronájem</label>
                    </td>
                </tr>

                <?php if(isset($_GET['add'])): ?>
                <tr>
                    <td>Vzor scoringu</td>
                    <td>
                        <select name="score_template_profile_id">
                            <option value="">-- bez kopie --</option>
                            <?php foreach($scoringProfiles as $sp): ?>
                                <option value="<?=$sp['id']?>">
                                    <?=htmlspecialchars($sp['name'])?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="muted">Vytvoří se nový scoring profil a pravidla se zkopírují ze zvoleného vzoru.</div>
                    </td>
                </tr>
                <?php else: ?>
                <tr>
                    <td>Scoring profil</td>
                    <td>
                        <select name="scoring_profile_id">
                            <option value="">-- žádný --</option>
                            <?php foreach($scoringProfiles as $sp): ?>
                                <option value="<?=$sp['id']?>" <?=((int)($profile['scoring_profile_id'] ?? 0) === (int)$sp['id']) ? 'selected' : ''?>>
                                    <?=htmlspecialchars($sp['name'])?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php endif; ?>

                <tr>
                    <td>AI kontext<br><span class="muted">Volitelný text pro AI. Např. „Jsme 3 a máme psa. Pokud jsou zakázaní domácí mazlíčci, je to blocker.“</span></td>
                    <td>
                        <textarea name="ai_context"><?=htmlspecialchars($profile['ai_context'] ?? '')?></textarea>
                    </td>
                </tr>
                <tr>
                    <td>Dispozice</td>
                    <td>
                        <?php foreach($roomMap as $rid => $label): ?>
                            <label style="margin-right:10px">
                                <input type="checkbox" name="rooms[]" value="<?=$rid?>" <?=in_array((string)$rid, array_map('strval', $currentRooms), true) ? 'checked' : ''?>>
                                <?=$label?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <td>Stav</td>
                    <td>
                        <?php foreach($conditionMap as $cid => $label): ?>
                            <label style="margin-right:10px">
                                <input type="checkbox" name="conditions[]" value="<?=$cid?>" <?=in_array((string)$cid, array_map('strval', $currentConditions), true) ? 'checked' : ''?>>
                                <?=$label?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr><td>Region ID</td><td><input name="locality_region_id" value="<?=htmlspecialchars((string)($profile['locality_region_id'] ?? 10))?>"></td></tr>
                <tr><td>Max cena</td><td><input name="price_to" id="price_to" value="<?=htmlspecialchars(number_format((int)($profile['price_to'] ?? 0), 0, '', ' '))?>"></td></tr>
                <tr><td>Patro od</td><td><input name="floor_number_from" value="<?=htmlspecialchars((string)($profile['floor_number_from'] ?? ''))?>"></td></tr>
                <tr><td>Patro do</td><td><input name="floor_number_to" value="<?=htmlspecialchars((string)($profile['floor_number_to'] ?? ''))?>"></td></tr>
                <tr><td>Min plocha</td><td><input name="usable_area_from" value="<?=htmlspecialchars((string)($profile['usable_area_from'] ?? 0))?>"></td></tr>
                <tr><td>Vlastnictví</td><td><input name="ownership" value="<?=htmlspecialchars((string)($profile['ownership'] ?? ''))?>"></td></tr>
                <tr><td>Limit výsledků</td><td><input name="limit_items" value="<?=htmlspecialchars((string)($profile['limit_items'] ?? 50))?>"></td></tr>
                <tr><td>Balkon</td><td><input type="checkbox" name="balcony" value="1" <?=($profile['balcony'] ?? false) ? 'checked' : ''?>></td></tr>
                <tr><td>Sklep</td><td><input type="checkbox" name="cellar" value="1" <?=($profile['cellar'] ?? false) ? 'checked' : ''?>></td></tr>
            </table>

            <button type="submit">Uložit profil</button>
        </form>
    <?php endif; ?>
</div>

<script>
document.getElementById('price_to')?.addEventListener('input', function () {
    let caret = this.selectionStart;
    let raw = this.value.replace(/\s/g, '');
    this.value = raw.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    this.setSelectionRange(caret, caret);
});
</script>
</body>
</html>