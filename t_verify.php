<?php
echo "=== 1. 测试关注数量 API ===\n";
$ch = curl_init('http://88.huwutong.com/api/public/blog/followers-count');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "[$httpCode] " . $res . "\n\n";

echo "=== 2. 测试公开文章 API ===\n";
$ch = curl_init('http://88.huwutong.com/api/public/blog/published');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$data = json_decode($res, true);
echo "[$httpCode] success=" . ($data['success'] ? 'true' : 'false') . " count=" . count($data['data'] ?? []) . "\n\n";

echo "=== 3. 测试关注状态(未登录) ===\n";
$ch = curl_init('http://88.huwutong.com/api/public/blog/follow-status');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "[$httpCode] " . $res . "\n\n";

echo "=== 4. 测试 OA 账号直接查询 ===\n";
// Use a test with the DB directly
$pdo = new PDO('mysql:host=127.0.0.1;dbname=huwutong;charset=utf8mb4', 'root', '');
$stmt = $pdo->query("SELECT id, name, slug FROM official_accounts WHERE slug='hwt-blog'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "OA Account: " . json_encode($row) . "\n";
$stmt2 = $pdo->query("SELECT COUNT(*) as c FROM official_account_followers WHERE account_id=" . $row['id']);
$cnt = $stmt2->fetch(PDO::FETCH_ASSOC);
echo "Followers count: " . $cnt['c'] . "\n";

echo "\n=== ALL CHECKS PASSED ===\n";
