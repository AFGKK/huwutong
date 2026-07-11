<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Http;

$base = 'http://127.0.0.1:8000';
$admin = User::where('email', 'admin@huwutong.com')->firstOrFail();
$demo = User::where('email', 'demo@huwutong.com')->firstOrFail();

$adminToken = $admin->createToken('lan-verify-admin')->plainTextToken;
$demoToken = $demo->createToken('lan-verify-demo')->plainTextToken;

$results = [];

$authAdmin = Http::withToken($adminToken)->post("{$base}/broadcasting/auth", [
    'socket_id' => '1234.5678',
    'channel_name' => 'private-chat.' . $admin->id,
]);
$results['broadcast_admin'] = $authAdmin->status();

$authDemo = Http::withToken($demoToken)->post("{$base}/broadcasting/auth", [
    'socket_id' => '1234.9999',
    'channel_name' => 'private-chat.' . $demo->id,
]);
$results['broadcast_demo'] = $authDemo->status();

$call = Http::withToken($adminToken)->post("{$base}/api/calls/call", [
    'callee_id' => $demo->id,
    'call_type' => 'audio',
]);
$results['call_status'] = $call->status();
$callId = $call->json('data.call_id');

if ($callId) {
    $sig = Http::withToken($adminToken)->post("{$base}/api/calls/{$callId}/signal", [
        'type' => 'offer',
        'data' => ['type' => 'offer', 'sdp' => 'v=0'],
    ]);
    $poll = Http::withToken($demoToken)->get("{$base}/api/calls/{$callId}/signal-poll", ['type' => 'offer']);
    $results['signal_post'] = $sig->status();
    $results['signal_poll'] = $poll->status();
    $results['offer_received'] = !empty($poll->json('data.data')) ? 'yes' : 'no';

    Http::withToken($demoToken)->post("{$base}/api/calls/{$callId}/respond", ['action' => 'reject']);
}

$incoming = Http::withToken($demoToken)->get("{$base}/api/calls/incoming");
$results['incoming_poll'] = $incoming->status();

$admin->tokens()->where('name', 'lan-verify-admin')->delete();
$demo->tokens()->where('name', 'lan-verify-demo')->delete();

echo json_encode([
    'admin_id' => $admin->id,
    'demo_id' => $demo->id,
    'lan_ip' => env('REVERB_HOST'),
    'reverb_ws' => 'ws://' . env('REVERB_HOST') . ':' . env('REVERB_PORT', 8080),
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
