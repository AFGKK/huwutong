<?php
$ch = curl_init('https://api.deepseek.com/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST => 1,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer sk-6b5a39ea99e849bfb8a51815e3e31a85'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'deepseek-chat',
        'messages' => [['role' => 'user', 'content' => 'Say hello in one sentence']],
        'stream' => false
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) echo "CURL Error: $error\n";
echo "Response: $resp\n";
