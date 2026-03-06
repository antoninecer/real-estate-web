<?php
declare(strict_types=1);

require_once __DIR__.'/inc/connect.php';

if (php_sapi_name() !== 'cli') {
    echo "This script can run only from CLI.\n";
    exit(1);
}

echo "AI review table prune\n";
echo "---------------------\n";

try {

    $pdo->exec("TRUNCATE TABLE estate_ai_reviews RESTART IDENTITY CASCADE");

    echo "estate_ai_reviews truncated successfully.\n";

} catch (Throwable $e) {

    echo "ERROR: ".$e->getMessage()."\n";
    exit(1);

}

echo "Done.\n";