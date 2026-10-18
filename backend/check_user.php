<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Get database connection
$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=Wavixa',
    'root',
    ''
);

// Check user
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute(['shaheedmhmed@gmail.com']);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'user' => $user,
], JSON_PRETTY_PRINT);
?>
