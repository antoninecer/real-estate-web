<?php
declare(strict_types=1);
header('Content-Type: application/json');

$UPSTREAM = 'http://macmini:1234/v1/chat/completions';
$MODEL = 'qwen/qwen3-4b-2507'; // natvrdo

$in = json_decode(file_get_contents('php://input'), true);

if (!isset($in['messages'])) {
  http_response_code(400);
  echo json_encode(['error'=>'Missing messages']);
  exit;
}

$payload = [
  'model' => $MODEL,
  'temperature' => (float)($in['temperature'] ?? 0.1),
  'max_tokens' => (int)($in['max_tokens'] ?? 400),
  'messages' => $in['messages']
];

$ch = curl_init($UPSTREAM);
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);

$out = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if($out === false){
  http_response_code(502);
  echo json_encode(['error'=>$err]);
  exit;
}

echo $out;
