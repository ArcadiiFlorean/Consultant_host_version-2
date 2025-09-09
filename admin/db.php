<?php
$host = 'localhost';
$dbname = 'dbbgd05fqwnbyk'; // Baza de date principală
$username = 'ukwfjvfi59ny9';
$password = '2n1iigooazzg';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Conexiunea a eșuat: " . $e->getMessage());
}
?>