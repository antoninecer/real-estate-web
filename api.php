<?php

$input = json_decode(file_get_contents("php://input"), true);

$system = "";
$user   = "";

foreach ($input["messages"] as $m) {
    if ($m["role"] === "system") $system = $m["content"];
    if ($m["role"] === "user")   $user   = $m["content"];
}

$prompt = $system . "\n\nInzerát:\n" . $user;

$payload = [
    "model" => "qwen3:4b-instruct",
    "prompt" => $prompt,
    "stream" => false,
    "options" => [
        "temperature" => $input["temperature"] ?? 0.1,
        "num_predict" => $input["max_tokens"] ?? 400
    ]
];

$ch = curl_init("http://macmini:1234/api/generate");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

echo json_encode([
    "model" => "qwen3:4b-instruct",
    "response" => $data["response"] ?? ""
]);