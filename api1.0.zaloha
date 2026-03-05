<?php
// api.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// ====== KONFIG ======
$UPSTREAM_URL = 'http://macmini:1234/v1/chat/completions'; // uprav dle sebe
$TIMEOUT_SEC  = 40;
// ====================

$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
  http_response_code(400);
  echo json_encode(['error' => ['message' => 'Empty request body']]);
  exit;
}

$in = json_decode($raw, true);
if (!is_array($in)) {
  http_response_code(400);
  echo json_encode(['error' => ['message' => 'Invalid JSON in request']]);
  exit;
}

// jednoduchý ping (ověření, že PHP běží)
if (!empty($in['ping'])) {
  echo json_encode(['ok' => true, 'time' => time()]);
  exit;
}

// validace základních polí
$model = isset($in['model']) ? (string)$in['model'] : '';
$temperature = isset($in['temperature']) ? (float)$in['temperature'] : 0.1;
$max_tokens = isset($in['max_tokens']) ? (int)$in['max_tokens'] : 250;
$messages = $in['messages'] ?? null;

if ($model === '' || !is_array($messages) || count($messages) === 0) {
  http_response_code(400);
  echo json_encode(['error' => ['message' => 'Missing model or messages']]);
  exit;
}

// omezíme extrémy (ať si neustřelíš nohu)
if ($temperature < 0) $temperature = 0;
if ($temperature > 2) $temperature = 2;
if ($max_tokens < 1) $max_tokens = 1;
if ($max_tokens > 8000) $max_tokens = 8000;

// očista messages: jen role+content, role jen system/user/assistant
$clean = [];
$allowedRoles = ['system','user','assistant'];

foreach ($messages as $m) {
  if (!is_array($m)) continue;
  $role = isset($m['role']) ? (string)$m['role'] : '';
  $content = isset($m['content']) ? (string)$m['content'] : '';
  $role = strtolower(trim($role));
  if (!in_array($role, $allowedRoles, true)) continue;
  if (trim($content) === '') continue;
  $clean[] = ['role' => $role, 'content' => $content];
}

if (count($clean) === 0) {
  http_response_code(400);
  echo json_encode(['error' => ['message' => 'No valid messages after sanitization']]);
  exit;
}

$payload = [
  'model' => $model,
  'temperature' => $temperature,
  'max_tokens' => $max_tokens,
  'messages' => $clean
];

// curl → upstream
$ch = curl_init($UPSTREAM_URL);
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CONNECTTIMEOUT => 10,
  CURLOPT_TIMEOUT => $TIMEOUT_SEC,
]);

$out = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($out === false) {
  http_response_code(502);
  echo json_encode(['error' => ['message' => 'Upstream error', 'detail' => $err]]);
  exit;
}

// pokud upstream nevrátil 200, pošli to dál, ale ať je to čitelné
if ($code < 200 || $code >= 300) {
  http_response_code(502);
  echo json_encode(['error' => ['message' => 'Upstream non-2xx', 'status' => $code, 'raw' => $out]]);
  exit;
}

// vrať upstream JSON (nepřekresluj realitu)
echo $out;

