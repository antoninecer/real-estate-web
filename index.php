<?php
require __DIR__.'/inc/menu.php';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<title>Real Estate Intelligence</title>

<style>

body{
    font-family:Arial, sans-serif;
    background:#f4f6f8;
    margin:0;
    padding:30px;
}

.wrap{
    max-width:1000px;
    margin:auto;
}

h1{
    margin-top:0;
}

.box{
    background:white;
    padding:20px;
    margin-bottom:20px;
    border-radius:8px;
    box-shadow:0 3px 10px rgba(0,0,0,0.08);
}

.links a{
    display:inline-block;
    padding:8px 14px;
    margin:5px 10px 5px 0;
    background:#2c3e50;
    color:white;
    text-decoration:none;
    border-radius:5px;
}

.links a:hover{
    background:#34495e;
}

.small{
    color:#666;
    font-size:14px;
}

</style>
</head>

<body>

<div class="wrap">

<?php require __DIR__.'/inc/menu.php'; ?>

<h1>Real Estate Intelligence</h1>

<div class="box">

<p>
Tento systém slouží k průběžnému sledování bytů na prodej v Praze a jejich
automatickému vyhodnocování.
</p>

<p>
Data jsou stahována z realitních portálů, ukládána do databáze a následně
hodnocena pomocí bodovacího systému (<b>hard score</b>).
</p>

<p>
Cílem je rychle identifikovat zajímavé nabídky podle parametrů jako:
</p>

<ul>
<li>cena</li>
<li>plocha bytu</li>
<li>dispozice</li>
<li>patro a výtah</li>
<li>vzdálenost od metra</li>
<li>další vlastnosti (balkon, sklep, parkování)</li>
</ul>

<p class="small">
Výsledky jsou řazeny podle skóre, aby bylo možné snadno vidět nejzajímavější
nabídky na trhu.
</p>

</div>


<div class="box">

<h2>Nástroje</h2>

<div class="links">

<a href="/vypis.php">Přehled bytů</a>

<a href="/profiles.php">Search profily</a>

<a href="/scoring.php">Scoring pravidla</a>

<a href="/aiprompt.php">AI analyzátor inzerátu</a>

</div>

</div>


<div class="box">

<h2>Stav systému</h2>

<ul class="small">
<li>databáze: PostgreSQL</li>
<li>sběr dat: scraping / API</li>
<li>scoring: SQL hard_score</li>
<li>AI hodnocení: lokální LLM</li>
</ul>

</div>


</div>

</body>
</html>