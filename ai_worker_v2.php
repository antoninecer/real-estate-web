<?php
declare(strict_types=1);

set_time_limit(0);
require_once __DIR__.'/inc/connect.php';

echo "REI v2 AI Worker started\n";

$MODEL  = "qwen3:4b-instruct"; // Ponecháno z v1
$OLLAMA = "http://macmini:1234/api/generate";
$MAX_ATTEMPTS = 3;

// Systémový prompt zůstává stejný pro konzistenci
$SYSTEM = "Jsi analytik bytů pro rodinné bydlení v Praze.\nVyhodnoť byt a vrať pouze JSON.\nNEPIŠ text mimo JSON.\n{\n \"breakdown\":{\n  \"lokalita\":0,\n  \"komfort\":0,\n  \"dispozice\":0,\n  \"riziko\":0,\n  \"hodnota\":0\n },\n \"strengths\":\"\",\n \"weaknesses\":\"\",\n \"summary\":\"\"\n}";

while (true) {
    try {
        $pdo->beginTransaction();

        // 1. Získání úlohy z fronty s atomickým zámkem (skip locked zabrání kolizi více workerů)
        $sql = "
            SELECT q.id, q.hash_id, e.name, e.description, e.price_czk, e.usable_area, e.floor_number, 
                   e.metro_distance, e.building_condition, e.construction_type, e.elevator, e.parking, e.garage
            FROM ai_review_queue q
            JOIN estates e ON q.hash_id = e.hash_id
            WHERE q.status IN ('pending', 'error') 
              AND q.attempts < :max_attempts
            ORDER BY q.priority DESC, q.created_at ASC
            LIMIT 1
            FOR UPDATE OF q SKIP LOCKED
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':max_attempts' => $MAX_ATTEMPTS]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            $pdo->commit();
            echo "Queue empty. Sleeping 10s...\n";
            sleep(10);
            continue;
        }

        // 2. Označení jako rozpracované
        $pdo->prepare("UPDATE ai_review_queue SET status = 'processing', locked_at = NOW(), attempts = attempts + 1 WHERE id = ?")
            ->execute([$task['id']]);
        $pdo->commit();

        $hash = $task['hash_id'];
        echo "[#{$task['id']}] Processing hash $hash... ";

        // 3. Příprava dat pro Ollama (identické s v1)
        $dataBlock = "\nCena: " . ($task["price_czk"] ?? "N/A") . " Kč\nPlocha: " . ($task["usable_area"] ?? "N/A") . " m2\nPatro: " . ($task["floor_number"] ?? "N/A") . "\nMetro: " . ($task["metro_distance"] ?? "N/A") . " m\nStav: " . ($task["building_condition"] ?? "N/A") . "\nKonstrukce: " . ($task["construction_type"] ?? "N/A") . "\nVýtah: ".($task["elevator"]?"ano":"ne")."\nParkování: ".($task["parking"]?"ano":"ne")."\nGaráž: ".($task["garage"]?"ano":"ne")."\n";
        $prompt = $SYSTEM . "\n\n" . $dataBlock . "\nINZERÁT:\n" . trim($task["description"] ?? "");

        $payload = [
            "model" => $MODEL,
            "prompt" => $prompt,
            "stream" => false,
            "options" => ["temperature" => 0.1, "num_predict" => 500]
        ];

        // 4. Ollama Request
        $ch = curl_init($OLLAMA);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 180
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if (!$response) throw new Exception("CURL Error: $err");

        // 5. Parse & Score (identické s v1)
        $data = json_decode($response, true);
        $text = trim($data["response"] ?? "");
        if (preg_match('/\{.*\}/s', $text, $m)) $jsonText = $m[0]; else $jsonText = $text;
        $json = json_decode($jsonText, true);
        if (!$json) throw new Exception("JSON Parse Fail: " . substr($text, 0, 100));

        $break = $json["breakdown"] ?? [];
        $aiScore = (int)array_sum(array_values($break));
        if ($aiScore > 100) $aiScore = 100;

        $verdict = $aiScore < 30 ? "IGNORE" : ($aiScore < 60 ? "CONSIDER" : ($aiScore < 80 ? "GOOD" : "EXCEPTIONAL"));

        // 6. Uložení výsledku a update fronty
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO estate_ai_reviews (hash_id, ai_score, verdict, strengths, weaknesses, summary, breakdown, model) VALUES (:hash_id, :ai_score, :verdict, :strengths, :weaknesses, :summary, :breakdown, :model) ON CONFLICT (hash_id) DO UPDATE SET ai_score = EXCLUDED.ai_score, verdict = EXCLUDED.verdict, summary = EXCLUDED.summary, breakdown = EXCLUDED.breakdown, created_at = NOW()");
        $stmt->execute([
            ":hash_id" => $hash, ":ai_score" => $aiScore, ":verdict" => $verdict,
            ":strengths" => $json["strengths"] ?? null, ":weaknesses" => $json["weaknesses"] ?? null,
            ":summary" => $json["summary"] ?? null, ":breakdown" => json_encode($break), ":model" => $MODEL
        ]);

        $pdo->prepare("UPDATE ai_review_queue SET status = 'done', locked_at = NULL WHERE id = ?")->execute([$task['id']]);
        
        // Bonus: Update v2 profile_matches tabulky, pokud existuje záznam
        $pdo->prepare("UPDATE profile_matches SET ai_score = ?, final_score = (hard_score + ?) / 2 WHERE hash_id = ?")->execute([$aiScore, $aiScore, $hash]);

        $pdo->commit();
        echo "OK (score: $aiScore)\n";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "ERROR: " . $e->getMessage() . "\n";
        if (isset($task['id'])) {
            $pdo->prepare("UPDATE ai_review_queue SET status = 'error', last_error = ?, locked_at = NULL WHERE id = ?")
                ->execute([$e->getMessage(), $task['id']]);
        }
        sleep(2);
    }
}
