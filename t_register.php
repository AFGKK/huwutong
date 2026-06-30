<?php
$url = 'http://localhost:8000/api/register';
$data = json_encode([
    'name' => 'Test User',
    'email' => 'test11@example.com',
    'password' => 'test123456',
    'password_confirmation' => 'test123456',
]);
echo 'Request body: ' . $data . "\n";
echo 'Body length: ' . strlen($data) . "\n";
$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAccept: application/json",
        'content' => $data,
        'ignore_errors' => true,
    ],
]);
$body = file_get_contents($url, false, $ctx);
echo 'Response: ' . $body . "\n";
