import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const authEndpoint = '/api/broadcasting/auth';

const echoConfig = {
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint,
    // 断线重连配置
    activityTimeout: 30000,
    pongTimeout: 10000,
    unavailableTimeout: 10000,
};

try {
    window.Echo = new Echo(echoConfig);

    // 监听连接事件
    window.Echo.connector.pusher.connection.bind('connecting', () => {
        console.log('[Echo] WebSocket 连接中...');
    });

    window.Echo.connector.pusher.connection.bind('connected', () => {
        console.log('[Echo] WebSocket 已连接');
        document.dispatchEvent(new CustomEvent('echo:connected'));
    });

    window.Echo.connector.pusher.connection.bind('disconnected', () => {
        console.warn('[Echo] WebSocket 已断开，将自动重连');
        document.dispatchEvent(new CustomEvent('echo:disconnected'));
    });

    window.Echo.connector.pusher.connection.bind('error', (err) => {
        console.warn('[Echo] WebSocket 连接错误:', err);
    });
} catch (e) {
    console.warn('[Echo] 初始化失败, 将使用轮询降级:', e.message);
    window.Echo = null;
}
