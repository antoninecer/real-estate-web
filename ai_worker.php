<?php
declare(strict_types=1);

set_time_limit(0);

require_once __DIR__.'/inc/connect.php';

echo "AI worker start\n\n";


/* --------------------------------------------------
   PARAMETRY
-------------------------------------------------- */

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

$limit   = (int)($params["limit"] ?? 200);
$minHard = (int)($params["min_hard"] ?? 0);

echo "LIMIT: $limit\n";
echo "MIN HARD SCORE: $minHard\n\n";


/* --------------------------------------------------
   SYSTEM PROMPT
-------------------------------------------------- */

$SYSTEM = <<<PROMPT
Jsi analytik bytů pro rodinné bydlení v Praze.

Vyhodnoť byt a vrať pouze JSON.

NEPIŠ text mimo JSON.

{
 "breakdown":{
  "lokalita":0,
  "komfort":0,
  "dispozice":0,
  "riziko":0,
  "hodnota":0
 },
 "strengths":"",
 "weaknesses":"",
 "summary":""
}

Každá položka breakdown 0–20.
PROMPT;


$MODEL  = "qwen3:4b-instruct";
$OLLAMA = "http://macmini:1234/api/generate";


/* --------------------------------------------------
   NAJDI JEN BYTY BEZ AI
-------------------------------------------------- */

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
WHERE v.hard_score >= :minHard
AND NOT EXISTS (
    SELECT 1
    FROM estate_ai_reviews r
    WHERE r.hash_id = e.hash_id
)
ORDER BY v.hard_score DESC
LIMIT :limit
";

$stmt=$pdo->prepare($sql);
$stmt->bindValue(":limit",$limit,PDO::PARAM_INT);
$stmt->bindValue(":minHard",$minHard,PDO::PARAM_INT);
$stmt->execute();

$estates=$stmt->fetchAll();

$count=count($estates);

echo "Found $count estates needing AI\n\n";

if($count===0){
    echo "Nothing to evaluate\n";
    exit;
}


$totalStart=microtime(true);
$i=0;


/* --------------------------------------------------
   LOOP
-------------------------------------------------- */

foreach($estates as $e){

$i++;

$hash=$e["hash_id"];


/* DATA BLOCK */

$dataBlock="

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


/* --------------------------------------------------
   OLLAMA REQUEST
-------------------------------------------------- */

$payload=[
"model"=>$MODEL,
"prompt"=>$prompt,
"stream"=>false,
"options"=>[
"temperature"=>0.1,
"num_predict"=>500
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


/* --------------------------------------------------
   PARSE RESPONSE
-------------------------------------------------- */

$data=json_decode($response,true);

if(!isset($data["response"])){
    echo "#$i hash $hash invalid response\n";
    continue;
}

$text=trim($data["response"]);

$text=preg_replace('/```.*?\n/','',$text);
$text=str_replace("```","",$text);

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


/* --------------------------------------------------
   SCORE
-------------------------------------------------- */

$break=$json["breakdown"] ?? [];

$lok=(int)($break["lokalita"] ?? 0);
$kom=(int)($break["komfort"] ?? 0);
$dis=(int)($break["dispozice"] ?? 0);
$riz=(int)($break["riziko"] ?? 0);
$hod=(int)($break["hodnota"] ?? 0);

$aiScore = $lok+$kom+$dis+$riz+$hod;

if($aiScore>100) $aiScore=100;


/* --------------------------------------------------
   VERDICT (DETERMINISTICKÝ)
-------------------------------------------------- */

if ($aiScore < 30){
    $verdict="IGNORE";
}
elseif ($aiScore < 60){
    $verdict="CONSIDER";
}
elseif ($aiScore < 80){
    $verdict="GOOD";
}
else{
    $verdict="EXCEPTIONAL";
}


/* --------------------------------------------------
   INSERT
-------------------------------------------------- */

$stmt=$pdo->prepare("
INSERT INTO estate_ai_reviews
(hash_id,ai_score,verdict,strengths,weaknesses,summary,breakdown)
VALUES
(:hash_id,:ai_score,:verdict,:strengths,:weaknesses,:summary,:breakdown)
ON CONFLICT (hash_id) DO NOTHING
");

$stmt->execute([
":hash_id"=>$hash,
":ai_score"=>$aiScore,
":verdict"=>$verdict,
":strengths"=>$json["strengths"] ?? null,
":weaknesses"=>$json["weaknesses"] ?? null,
":summary"=>$json["summary"] ?? null,
":breakdown"=>json_encode($break)
]);


/* --------------------------------------------------
   LOG
-------------------------------------------------- */

echo "#$i hash $hash INSERT score=$aiScore verdict=$verdict ($time s)\n";

}


$total=round(microtime(true)-$totalStart,2);

echo "\nTotal time: $total s\n";

if($i>0){
echo "Average: ".round($total/$i,2)." s\n";
}

echo "\nDone\n";