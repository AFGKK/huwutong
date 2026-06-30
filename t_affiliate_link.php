<?php
$token = '65|zkJgPriDShHCwvlOJkWTQgtMl99JDuhtAwaheJdo7a9c0680';
$base = 'http://127.0.0.1:8000/api';
$headers = ['Authorization: Bearer ' . $token, 'Accept: application/json', 'Content-Type: application/json'];

// 1. 生成推广链接
echo "=== 1. 生成商品推广链接 ===\n";
$ch = curl_init($base . '/store-affiliate/generate-links');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['sku_ids' => [2]]),
]);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP $http\n";

if ($http == 200 || $http == 201) {
    $result = json_decode($resp, true);
    foreach (($result['data'] ?? []) as $link) {
        echo "  商品: {$link['product_name']} - {$link['sku_name']}\n";
        echo "  链接: {$link['link']}\n";
        echo "  推广码: {$link['referral_code']}\n";
        echo "  佣金: ¥{$link['commission_amount']} ({$link['commission_rate']}%)\n";
    }
} else {
    echo $resp . "\n";
}
curl_close($ch);

// 2. 验证链接列表
echo "\n=== 2. 验证推广链接列表 ===\n";
$ch = curl_init($base . '/store-affiliate/links');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
]);
$resp = curl_exec($ch);
$data = json_decode($resp, true);
$items = $data['data'] ?? [];
echo "链接数: " . count($items) . "\n";
foreach (array_slice($items, 0, 3) as $item) {
    echo "  ID={$item['id']} | url={$item['landing_url']} | ref={$item['referral_code']}\n";
}
curl_close($ch);

echo "\n✅ 测试完成\n";
