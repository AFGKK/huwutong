<?php
// Direct database update
$pdo = new PDO('mysql:host=127.0.0.1;dbname=huwutong;charset=utf8mb4', 'root', '');
$stmt = $pdo->prepare("UPDATE users SET password = ? ORDER BY id ASC LIMIT 1");
$hash = password_hash('12345678', PASSWORD_BCRYPT);
$stmt->execute([$hash]);
$stmt2 = $pdo->query("SELECT email, id FROM users ORDER BY id ASC LIMIT 1");
$user = $stmt2->fetch(PDO::FETCH_ASSOC);
echo "Password reset for: " . ($user['email'] ?? 'unknown') . " (ID: " . ($user['id'] ?? '?') . ")\n";
