<?php

require_once __DIR__.'/inc/connect.php';
require_once __DIR__.'/inc/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = (int)($_POST['id'] ?? 0);
    $points = (int)($_POST['points'] ?? 0);

    $stmt = $pdo->prepare("
        UPDATE estate_scoring_rules
        SET points = :points
        WHERE id = :id
    ");

    $stmt->execute([
        ':points' => $points,
        ':id' => $id
    ]);

}

$rows = $pdo->query("
    SELECT *
    FROM estate_scoring_rules
    ORDER BY rule_group,min_value NULLS FIRST
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="cs">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Scoring pravidla</title>

<link rel="stylesheet" href="styles.css">

</head>

<body>

<div class="wrap">

<?php require_once __DIR__.'/inc/menu.php'; ?>

<h2>Scoring pravidla</h2>

<p class="hint">
Úprava bodů se projeví okamžitě ve výpočtu <b>hard_score</b>.
</p>

<table>

<thead>
<tr>

<th>Skupina</th>
<th>Min</th>
<th>Max</th>
<th>Text</th>
<th>Body</th>
<th>Uložit</th>

</tr>
</thead>

<tbody>

<?php foreach ($rows as $r): ?>

<tr>

<form method="post">

<td><?=h($r['rule_group'])?></td>

<td class="center">
<?= $r['min_value'] !== null ? (int)$r['min_value'] : '<span class="na">NULL</span>' ?>
</td>

<td class="center">
<?= $r['max_value'] !== null ? (int)$r['max_value'] : '<span class="na">NULL</span>' ?>
</td>

<td>
<?=h($r['text_match'])?>
</td>

<td class="center">

<input
type="number"
name="points"
value="<?= (int)$r['points'] ?>"
style="width:70px"
>

<input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

</td>

<td class="center">

<button type="submit">Uložit</button>

</td>

</form>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</body>
</html>