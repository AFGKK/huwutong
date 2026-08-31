<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ICE Servers (JSON)
    |--------------------------------------------------------------------------
    | RTCPeerConnection iceServers 数组，优先于下方分项变量。
    | 示例：
    | [{"urls":"stun:stun.l.google.com:19302"},{"urls":"turn:turn.example.com:3478","username":"u","credential":"p"}]
    */
    'ice_servers_json' => env('ICE_SERVERS'),

    /*
    |--------------------------------------------------------------------------
    | STUN / TURN 分项配置（ICE_SERVERS 未设置时使用）
    |--------------------------------------------------------------------------
    */
    'stun_urls' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('WEBRTC_STUN_URLS', 'stun:stun.l.google.com:19302'))
    ))),

    'turn_url' => env('WEBRTC_TURN_URL'),
    'turns_url' => env('WEBRTC_TURNS_URL'),
    'turn_username' => env('WEBRTC_TURN_USERNAME'),
    'turn_credential' => env('WEBRTC_TURN_CREDENTIAL'),
];
