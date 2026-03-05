<?php

$dsn = "pgsql:host=127.0.0.1;port=5432;dbname=realestate";
$user = "realestate";
$password = "CHANGE_ME";

try {

    $pdo = new PDO(
        $dsn,
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

} catch (PDOException $e) {

    die("DB connection failed");

}