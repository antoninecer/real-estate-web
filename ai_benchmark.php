<?php

require_once __DIR__.'/inc/connect.php';

$OLLAMA = "http://macmini:1234/api/generate";
$MODEL  = "qwen3:4b-instruct";

$SYSTEM = <<<PROMPT
Jsi přísný realitní analytik.

Vyhodnoť realitní inzerát a vrať POUZE validní JSON.

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
PROMPT;

echo "AI benchmark start\n\n";

$startTotal = microtime(true);

/* TOP 20 bytů */

$sql = "
SELECT
e.hash_id,
LEFT(e.description,1200) AS description,
v.hard_score
FROM estates e
JOIN v_estates_hard_score v USING(hash_id)
WHERE e.active = true
AND e.description IS NOT NULL
ORDER BY v.hard_score DESC
LIMIT 20
";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "Found ".count($rows)." estates\n\n";

$i = 1;

foreach($rows as $row){

    $start = microtime(true);

    $prompt = $SYSTEM."\n\nInzerát:\n".$row["description"];

    $payload = [
        "model"=>$MODEL,
        "prompt"=>$prompt,
        "stream"=>false,
        "options"=>[
            "temperature"=>0.1,
            "num_predict"=>120
        ]
    ];

    $ch = curl_init($OLLAMA);

    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>["Content-Type: application/json"],
        CURLOPT_POSTFIELDS=>json_encode($payload)
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $end = microtime(true);

    $time = round($end - $start,2);

    echo "#".$i." hash ".$row["hash_id"]." time ".$time." s\n";

    $i++;
}

$total = round(microtime(true) - $startTotal,2);

echo "\nTotal time: ".$total." seconds\n";

echo "Average per estate: ".round($total/count($rows),2)." s\n";