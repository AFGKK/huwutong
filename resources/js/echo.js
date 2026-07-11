import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

function buildEchoAuthHeaders() {
    const headers = { Accept: 'application/json' };
    const token = localStorage.getItem('auth_token');
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    return headers;
}

function createEcho() {
    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';
    const port = Number(import.meta.env.VITE_REVERB_PORT ?? (scheme === 'https' ? 443 : 80));

    return new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: buildEchoAuthHeaders(),
        },
        activityTimeout: 30000,
        pongTimeout: 10000,
        unavailableTimeout: 10000,
    });
}

function bindEchoConnectionEvents(echo) {
    const connection = echo?.connector?.pusher?.connection;
    if (!connection) return;

    connection.bind('connecting', () => {
        console.log('[Echo] WebSocket 连接中...');
    });

    connection.bind('connected', () => {
        console.log('[Echo] WebSocket 已连接');
        document.dispatchEvent(new CustomEvent('echo:connected'));
    });

    connection.bind('disconnected', () => {
        console.warn('[Echo] WebSocket 已断开，将自动重连');
        document.dispatchEvent(new CustomEvent('echo:disconnected'));
    });

    connection.bind('error', (err) => {
        console.warn('[Echo] WebSocket 连接错误:', err);
    });
}

/** 登录/刷新 Token 后更新 Echo 鉴权头（供 auth store 调用） */
export function refreshEchoAuthHeaders() {
    if (!window.Echo?.connector?.pusher?.config?.auth) return;
    window.Echo.connector.pusher.config.auth.headers = buildEchoAuthHeaders();
}

if (!reverbKey) {
    console.info('[Echo] 未配置 VITE_REVERB_APP_KEY，实时推送已禁用（使用轮询降级）');
    window.Echo = null;
} else {
    try {
        window.Echo = createEcho();
        bindEchoConnectionEvents(window.Echo);
    } catch (e) {
        console.warn('[Echo] 初始化失败, 将使用轮询降级:', e.message);
        window.Echo = null;
    }
}
