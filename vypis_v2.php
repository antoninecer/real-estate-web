<?php
require_once __DIR__.'/inc/connect.php';
require_once __DIR__.'/inc/helpers.php';

$profileId = (int)($_GET['profile_id'] ?? 0);

// Pokud není vybrán profil, vezmeme první aktivní
if ($profileId === 0) {
    $profileId = (int)$pdo->query("SELECT id FROM estate_search_profiles WHERE is_active=true ORDER BY id LIMIT 1")->fetchColumn();
}

// Načtení seznamu profilů pro přepínač
$profiles = $pdo->query("SELECT id, name FROM estate_search_profiles ORDER BY id")->fetchAll();

$sql = "
    SELECT e.name, e.ward, e.price_czk, e.usable_area, pm.hard_score, pm.ai_score, pm.state, pm.last_seen_at, e.detail_url
    FROM profile_matches pm
    JOIN estates e ON pm.hash_id = e.hash_id
    WHERE pm.profile_id = :pid AND pm.state = 'active'
    ORDER BY pm.hard_score DESC, pm.ai_score DESC NULLS LAST
    LIMIT 200
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':pid' => $profileId]);
$rows = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>REI v2 - Přehled podle profilu</title>
    <?php require_once __DIR__.'/inc/head.php'; ?>
    <style>
        .badge { padding: 2px 6px; border-radius: 4px; font-size: 0.9em; font-weight: bold; }
        .score-ai { background: #e3f2fd; color: #1976d2; border: 1px solid #bbdefb; }
        .score-hard { background: #f5f5f5; color: #333; border: 1px solid #ddd; }
        .profile-nav { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e9ecef; }
    </style>
</head>
<body>
<div class="wrap">
    <?php require_once __DIR__.'/inc/menu.php'; ?>
    
    <div class="profile-nav">
        <form method="get">
            <strong>Vyberte profil:</strong>
            <select name="profile_id" onchange="this.form.submit()" style="padding: 5px; margin-left: 10px;">
                <?php foreach ($profiles as $p): ?>
                    <option value="<?=$p['id']?>" <?=$p['id']===$profileId?'selected':''?>><?=h($p['name'])?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <h2>Výsledky pro profil: <?= h(array_column($profiles, 'name', 'id')[$profileId] ?? 'Neznámý') ?></h2>
    <p class="hint">Zobrazeno posledních 200 aktivních shod.</p>

    <table>
        <thead>
            <tr>
                <th>Název</th>
                <th>Městská část</th>
                <th class="num">Cena</th>
                <th class="center">Plocha</th>
                <th class="center">Hard Score</th>
                <th class="center">AI Score</th>
                <th class="center">Naposledy viděno</th>
                <th class="center">Detail</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?=h($r['name'])?></td>
                <td><?=h($r['ward'])?></td>
                <td class="num"><?=fmtPrice($r['price_czk'])?></td>
                <td class="center"><?=$r['usable_area']?> m²</td>
                <td class="center"><span class="badge score-hard"><?=$r['hard_score']?></span></td>
                <td class="center">
                    <?php if($r['ai_score']): ?>
                        <span class="badge score-ai"><?=$r['ai_score']?></span>
                    <?php else: ?>
                        <span class="na">-</span>
                    <?php endif; ?>
                </td>
                <td class="center"><?=date('d.m. H:i', strtotime($r['last_seen_at']))?></td>
                <td class="center">
                    <a href="<?=h($r['detail_url'])?>" target="_blank" title="Otevřít na Sreality">↗</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(!$rows): ?>
                <tr><td colspan="8" class="na">Pro tento profil nebyly nalezeny žádné aktivní shody. Spusťte scan v2.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
