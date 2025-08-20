<?php
$host = 'localhost';
$dbname = 'breastfeeding_consulting';
$username = 'root';
$password = ''; // sau parola dacă ai

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Conexiunea a eșuat: " . $e->getMessage());
}
