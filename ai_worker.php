<?php
declare(strict_types=1);

set_time_limit(0);

require_once __DIR__.'/inc/connect.php';

echo "AI worker start\n\n";


/* ---------------------------------------------------
   PARAMETRY (CLI + GET)
--------------------------------------------------- */

$params=[];

if(php_sapi_name()==="cli"){
    global $argv;
    foreach($argv as $a){
        if(strpos($a,'=')!==false){
            [$k,$v]=explode('=',$a,2);
            $params[$k]=$v;
        }
    }
}

$params=array_merge($params,$_GET);


/* ---------------------------------------------------
   NASTAVENÍ
--------------------------------------------------- */

$profile = $params["profile"] ?? "family";
$limit   = (int)($params["limit"] ?? 200);
$minHard = (int)($params["min_hard"] ?? 0);
$prune   = (int)($params["prune"] ?? 0);

if(!in_array($profile,["family","investor"])) {
    $profile="family";
}

echo "PROFILE: $profile\n";
echo "LIMIT: $limit\n";
echo "MIN HARD SCORE: $minHard\n\n";


/* ---------------------------------------------------
   PRUNE TABULKY
--------------------------------------------------- */

if($prune){

    echo "PRUNE estate_ai_reviews\n";

    $pdo->exec("TRUNCATE estate_ai_reviews RESTART IDENTITY");

    echo "DONE\n\n";
}


/* ---------------------------------------------------
   SYSTEM PROMPT
--------------------------------------------------- */

if($profile==="investor"){

$SYSTEM=<<<PROMPT
Jsi analytik realitních investic v Praze.

Hodnotíš byt jako investiční příležitost.

Důležité:

- cena za m2
- lokalita
- dostupnost MHD
- potenciál pronájmu
- technický stav domu

Preferuj menší byty a nižší cenu.

Distribuce:

40% IGNORE
40% CHECK
15% GOOD
5% EXCEPTIONAL

Vrať pouze JSON:

{
 "ai_score":0,
 "breakdown":{
  "lokalita":0,
  "komfort":0,
  "dispozice":0,
  "riziko":0,
  "hodnota":0
 },
 "verdict":"IGNORE",
 "strengths":"",
 "weaknesses":"",
 "summary":""
}

Každá položka breakdown 0–20.
ai_score je součet (0–100).
PROMPT;

}
else{

$SYSTEM=<<<PROMPT
Jsi analytik bytů pro rodinné bydlení v Praze.

Model kupujícího:

- rodina
- pes
- dvě auta
- práce v centru

Důležité faktory:

DOPRAVA
MHD do 400 m velké plus.
Metro je velké plus.
Nad 1500 m penalizuj.

PARKOVÁNÍ
Parkování nebo garáž je velké plus.

LOKALITA
Blízkost centra je plus.
Parky a zeleň jsou velké plus.

BYT
Větší plocha je plus.
Balkon nebo terasa je velké plus.

PŘÍZEMÍ penalizuj.

Distribuce:

35% IGNORE
40% CHECK
20% GOOD
5% EXCEPTIONAL

Vrať pouze JSON:

{
 "ai_score":0,
 "breakdown":{
  "lokalita":0,
  "komfort":0,
  "dispozice":0,
  "riziko":0,
  "hodnota":0
 },
 "verdict":"IGNORE",
 "strengths":"",
 "weaknesses":"",
 "summary":""
}

Každá položka breakdown 0–20.
ai_score je součet.
PROMPT;

}

$MODEL  = "qwen3:4b-instruct";
$OLLAMA = "http://macmini:1234/api/generate";


/* ---------------------------------------------------
   VYBER INZERÁTŮ
--------------------------------------------------- */

$sql="
SELECT
e.hash_id,
e.name,
e.description,
e.price_czk,
e.usable_area,
e.floor_number,
e.metro_distance,
e.building_condition,
e.construction_type,
e.elevator,
e.parking,
e.garage,
v.hard_score
FROM estates e
JOIN v_estates_hard_score v USING(hash_id)
LEFT JOIN estate_ai_reviews r USING(hash_id)
WHERE r.hash_id IS NULL
AND v.hard_score >= :minHard
ORDER BY v.hard_score DESC
LIMIT :limit
";

$stmt=$pdo->prepare($sql);
$stmt->bindValue(":limit",$limit,PDO::PARAM_INT);
$stmt->bindValue(":minHard",$minHard,PDO::PARAM_INT);
$stmt->execute();

$estates=$stmt->fetchAll();

echo "Found ".count($estates)." estates\n\n";

$totalStart=microtime(true);
$i=0;


/* ---------------------------------------------------
   LOOP
--------------------------------------------------- */

foreach($estates as $e){

$i++;

$hash=$e["hash_id"];


/* DATA BLOCK */

$dataBlock="

DATA
Cena: {$e["price_czk"]} Kč
Plocha: {$e["usable_area"]} m2
Patro: {$e["floor_number"]}
Metro: {$e["metro_distance"]} m
Stav: {$e["building_condition"]}
Konstrukce: {$e["construction_type"]}
Výtah: ".($e["elevator"]?"ano":"ne")."
Parkování: ".($e["parking"]?"ano":"ne")."
Garáž: ".($e["garage"]?"ano":"ne")."
Hard score: {$e["hard_score"]}

";

$desc=trim($e["description"] ?? "");

$prompt=$SYSTEM."\n\n".$dataBlock."\nINZERÁT:\n".$desc;


/* ---------------------------------------------------
   OLLAMA REQUEST
--------------------------------------------------- */

$payload=[
"model"=>$MODEL,
"prompt"=>$prompt,
"stream"=>false,
"options"=>[
"temperature"=>0.1,
"num_predict"=>600
]
];

$start=microtime(true);

$ch=curl_init($OLLAMA);

curl_setopt_array($ch,[
CURLOPT_RETURNTRANSFER=>true,
CURLOPT_POST=>true,
CURLOPT_HTTPHEADER=>["Content-Type: application/json"],
CURLOPT_POSTFIELDS=>json_encode($payload),
CURLOPT_TIMEOUT=>180
]);

$response=curl_exec($ch);
$err=curl_error($ch);
curl_close($ch);

$time=round(microtime(true)-$start,2);

if(!$response){
    echo "#$i hash $hash CURL ERROR $err\n";
    continue;
}


/* ---------------------------------------------------
   PARSE RESPONSE
--------------------------------------------------- */

$data=json_decode($response,true);

if(!isset($data["response"])){
    echo "#$i hash $hash invalid response\n";
    continue;
}

$text=trim($data["response"]);

/* odstran code fences */

$text=preg_replace('/```.*?\n/','',$text);
$text=str_replace("```","",$text);

/* najdi JSON */

if(preg_match('/\{.*\}/s',$text,$m)){
    $jsonText=$m[0];
}else{
    $jsonText=$text;
}

$json=json_decode($jsonText,true);

if(!$json){

    echo "#$i hash $hash JSON parse fail ($time s)\n";
    echo $text."\n\n";
    continue;

}


/* ---------------------------------------------------
   OPRAVA AI SCORE (hlavní fix)
--------------------------------------------------- */

$break=$json["breakdown"] ?? [];

$aiScore =
(int)($break["lokalita"] ?? 0) +
(int)($break["komfort"] ?? 0) +
(int)($break["dispozice"] ?? 0) +
(int)($break["riziko"] ?? 0) +
(int)($break["hodnota"] ?? 0);

/* fallback */

if($aiScore===0 && isset($json["ai_score"])){
    $aiScore=(int)$json["ai_score"];
}

if($aiScore>100) $aiScore=100;


/* ---------------------------------------------------
   DB INSERT
--------------------------------------------------- */

$stmt=$pdo->prepare("
INSERT INTO estate_ai_reviews
(hash_id,ai_score,verdict,strengths,weaknesses,summary,breakdown)
VALUES
(:hash_id,:ai_score,:verdict,:strengths,:weaknesses,:summary,:breakdown)
");

$stmt->execute([
":hash_id"=>$hash,
":ai_score"=>$aiScore,
":verdict"=>$json["verdict"] ?? null,
":strengths"=>$json["strengths"] ?? null,
":weaknesses"=>$json["weaknesses"] ?? null,
":summary"=>$json["summary"] ?? null,
":breakdown"=>json_encode($break)
]);


/* ---------------------------------------------------
   LOG
--------------------------------------------------- */

echo "#$i hash $hash INSERT score=$aiScore verdict=".$json["verdict"]." ($time s)\n";

echo " breakdown: ".json_encode($break)."\n";
echo " + ".$json["strengths"]."\n";
echo " - ".$json["weaknesses"]."\n\n";

}


/* ---------------------------------------------------
   STATISTIKA
--------------------------------------------------- */

$total=round(microtime(true)-$totalStart,2);

echo "\nTotal time: $total s\n";

if($i>0){
echo "Average: ".round($total/$i,2)." s\n";
}

echo "\nDone\n";