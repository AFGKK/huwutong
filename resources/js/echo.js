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

let echoInstance = null;
let echoInitialized = false;

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
        console.log('[Echo] WebSocket connecting…');
        document.dispatchEvent(new CustomEvent('echo:connecting'));
    });

    connection.bind('connected', () => {
        console.log('[Echo] WebSocket connected');
        document.dispatchEvent(new CustomEvent('echo:connected'));
    });

    connection.bind('disconnected', () => {
        console.warn('[Echo] WebSocket disconnected; will reconnect');
        document.dispatchEvent(new CustomEvent('echo:disconnected'));
    });

    connection.bind('error', (err) => {
        console.warn('[Echo] WebSocket error:', err);
        document.dispatchEvent(new CustomEvent('echo:error', { detail: err }));
    });
}

/** 登录/刷新 Token 后更新 Echo 鉴权头（供 auth store 调用） */
export function refreshEchoAuthHeaders() {
    if (!echoInstance?.connector?.pusher?.config?.auth) return;
    echoInstance.connector.pusher.config.auth.headers = buildEchoAuthHeaders();
}

/**
 * 手动重连 Echo — 在 Token 刷新或网络恢复后调用
 */
export function reconnectEcho() {
    if (!echoInstance) return;
    try {
        echoInstance.disconnect();
        setTimeout(() => {
            echoInstance.connect();
        }, 500);
    } catch (e) {
        console.warn('[Echo] Reconnect failed:', e.message);
    }
}

/**
 * 获取当前 Echo 状态
 */
export function getEchoStatus() {
    if (!echoInstance) return 'disabled';
    const state = echoInstance?.connector?.pusher?.connection?.state;
    if (state === 'connected') return 'connected';
    if (state === 'connecting') return 'connecting';
    return 'disconnected';
}

/**
 * 监听页面可见性变化，后台标签页恢复时检查并重连 WebSocket
 */
function setupVisibilityHandler() {
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && echoInstance) {
            const state = echoInstance?.connector?.pusher?.connection?.state;
            if (state !== 'connected' && state !== 'connecting') {
                console.log('[Echo] Page visible again; reconnecting WebSocket');
                reconnectEcho();
            }
        }
    });
}

/**
 * 监听在线状态变化，网络恢复时重连
 */
function setupOnlineHandler() {
    window.addEventListener('online', () => {
        console.log('[Echo] Network online; reconnecting WebSocket');
        if (echoInstance) {
            reconnectEcho();
        }
    });
}

if (!reverbKey) {
    console.info('[Echo] VITE_REVERB_APP_KEY not set; realtime disabled (polling fallback)');
    window.Echo = null;
    echoInstance = null;
} else {
    try {
        echoInstance = createEcho();
        window.Echo = echoInstance;
        bindEchoConnectionEvents(echoInstance);
        setupVisibilityHandler();
        setupOnlineHandler();
        echoInitialized = true;
    } catch (e) {
        console.warn('[Echo] Init failed; using polling fallback:', e.message);
        window.Echo = null;
        echoInstance = null;
    }
}
