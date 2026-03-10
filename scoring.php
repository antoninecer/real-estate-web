<?php

require_once __DIR__.'/inc/connect.php';
require_once __DIR__.'/inc/helpers.php';

/* AKTIVNÍ SCORING PROFIL */
$scoringProfileId = (int)($_GET['scoring_profile_id'] ?? 0);

if ($scoringProfileId === 0) {
    $scoringProfileId = (int)$pdo->query("SELECT id FROM estate_scoring_profiles ORDER BY id LIMIT 1")->fetchColumn();
}

/* ULOŽENÍ BODŮ */
if (isset($_POST['save'])) {
    $stmt = $pdo->prepare("
        UPDATE estate_scoring_rules
        SET points = :points
        WHERE id = :id
          AND scoring_profile_id = :scoring_profile_id
    ");

    $stmt->execute([
        ':points' => (int)$_POST['points'],
        ':id' => (int)$_POST['save'],
        ':scoring_profile_id' => (int)$_POST['scoring_profile_id'],
    ]);

    header("Location: scoring.php?scoring_profile_id=".(int)$_POST['scoring_profile_id']);
    exit;
}

/* SMAZÁNÍ PRAVIDLA */
if (isset($_POST['delete'])) {
    $stmt = $pdo->prepare("
        DELETE FROM estate_scoring_rules
        WHERE id = :id
          AND scoring_profile_id = :scoring_profile_id
    ");

    $stmt->execute([
        ':id' => (int)$_POST['delete'],
        ':scoring_profile_id' => (int)$_POST['scoring_profile_id'],
    ]);

    header("Location: scoring.php?scoring_profile_id=".(int)$_POST['scoring_profile_id']);
    exit;
}

/* NOVÉ PRAVIDLO */
if (isset($_POST['create'])) {
    $stmt = $pdo->prepare("
        INSERT INTO estate_scoring_rules
        (scoring_profile_id, rule_group, min_value, max_value, text_match, points)
        VALUES
        (:scoring_profile_id, :rule_group, :min_value, :max_value, :text_match, :points)
    ");

    $stmt->execute([
        ':scoring_profile_id' => (int)$_POST['scoring_profile_id'],
        ':rule_group' => $_POST['rule_group'],
        ':min_value' => $_POST['min_value'] !== '' ? $_POST['min_value'] : null,
        ':max_value' => $_POST['max_value'] !== '' ? $_POST['max_value'] : null,
        ':text_match' => $_POST['text_match'] !== '' ? $_POST['text_match'] : null,
        ':points' => (int)$_POST['points']
    ]);

    header("Location: scoring.php?scoring_profile_id=".(int)$_POST['scoring_profile_id']);
    exit;
}

/* SCORING PROFILY */
$scoringProfiles = $pdo->query("
    SELECT id, name
    FROM estate_scoring_profiles
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

/* NAČTENÍ PRAVIDEL */
$stmt = $pdo->prepare("
    SELECT *
    FROM estate_scoring_rules
    WHERE scoring_profile_id = :scoring_profile_id
    ORDER BY rule_group, min_value NULLS FIRST, max_value NULLS FIRST, id
");
$stmt->execute([':scoring_profile_id' => $scoringProfileId]);
$rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Scoring pravidla</title>
    <?php require_once __DIR__.'/inc/head.php'; ?>
</head>
<body>

<div class="wrap">

<?php require_once __DIR__.'/inc/menu.php'; ?>

<h2>Scoring pravidla</h2>

<form method="get" style="margin-bottom:20px;">
    <label><b>Scoring profil:</b></label>
    <select name="scoring_profile_id" onchange="this.form.submit()">
        <?php foreach($scoringProfiles as $sp): ?>
            <option value="<?=$sp['id']?>" <?=$sp['id']==$scoringProfileId?'selected':''?>>
                <?=h($sp['name'])?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<p class="hint">
Úprava bodů se projeví okamžitě ve výpočtu hard_score pro profily, které používají tento scoring profil.
</p>

<div class="hint">
<b>Nápověda skupin:</b>
<ul>
<li><b>price</b> – cena bytu</li>
<li><b>price_m2</b> – cena za m²</li>
<li><b>area</b> – plocha bytu</li>
<li><b>metro</b> – vzdálenost od metra</li>
<li><b>tram</b> – vzdálenost od tramvaje</li>
<li><b>bus</b> – vzdálenost od autobusu</li>
<li><b>condition</b> – stav budovy</li>
<li><b>construction</b> – typ konstrukce</li>
<li><b>floor_elevator</b> – patro s výtahem</li>
<li><b>floor_no_elevator</b> – patro bez výtahu</li>
</ul>
</div>

<table>
<thead>
<tr>
<th>Skupina</th>
<th>Min</th>
<th>Max</th>
<th>Text</th>
<th>Body</th>
<th>Akce</th>
</tr>
</thead>
<tbody>

<?php foreach($rules as $r): ?>
<tr>
<form method="post">
<td><?=h($r['rule_group'])?></td>
<td><?=h($r['min_value'])?></td>
<td><?=h($r['max_value'])?></td>
<td><?=h($r['text_match'])?></td>
<td>
    <input name="points" value="<?=h($r['points'])?>" style="width:60px">
</td>
<td>
    <input type="hidden" name="scoring_profile_id" value="<?=$scoringProfileId?>">
    <button name="save" value="<?=$r['id']?>">Uložit</button>
    <button name="delete" value="<?=$r['id']?>" onclick="return confirm('Smazat pravidlo?')">Smazat</button>
</td>
</form>
</tr>
<?php endforeach; ?>

</tbody>
</table>

<h3>Nové pravidlo</h3>

<form method="post">
    <input type="hidden" name="scoring_profile_id" value="<?=$scoringProfileId?>">

    <select name="rule_group">
        <option value="price">price</option>
        <option value="price_m2">price_m2</option>
        <option value="area">area</option>
        <option value="metro">metro</option>
        <option value="tram">tram</option>
        <option value="bus">bus</option>
        <option value="condition">condition</option>
        <option value="construction">construction</option>
        <option value="floor_elevator">floor_elevator</option>
        <option value="floor_no_elevator">floor_no_elevator</option>
    </select>

    min <input name="min_value" style="width:80px">
    max <input name="max_value" style="width:80px">
    text <input name="text_match" style="width:120px">
    body <input name="points" style="width:80px">

    <button name="create" value="1">Přidat</button>
</form>

</div>
</body>
</html>