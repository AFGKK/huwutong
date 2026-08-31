import axios from 'axios';
import { ElMessage } from 'element-plus';
import router from '@/router';
import i18n from '@/i18n';

const apiClient = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
    timeout: 30000,
    headers: {
        'Accept': 'application/json',
    },
});

// ─── Token 刷新状态 ───
let isRefreshing = false;
let failedQueue = [];

function processQueue(error, token = null) {
    failedQueue.forEach(({ resolve, reject }) => {
        if (error) {
            reject(error);
        } else {
            resolve(token);
        }
    });
    failedQueue = [];
}

// 强制登出（跳过公开页面的重定向）
const PUBLIC_ROUTES = ['Login', 'Register', 'ForgotPassword', 'Appeal', 'StatusPage', 'Community', 'Channels', 'UserProfile', 'PlazaDetail', 'InteractiveDemo', 'OaArticleDetail', 'OaEditor'];
function forceLogout(message) {
    const msg = message || i18n.global.t('messages.session_expired');
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    // 避免在公开页面强制跳转登录
    const currentRoute = router.currentRoute?.value;
    const routeName = currentRoute?.name;
    const routePath = String(currentRoute?.path || '');
    if (routeName && PUBLIC_ROUTES.includes(routeName)) {
        return; // 公开页面不跳转，仅清除 token
    }
    // fallback：按路径匹配，防止路由名匹配失败
    if (routePath.startsWith('/oa-editor') || routePath.startsWith('/oa-article/')) {
        return;
    }
    if (router.currentRoute?.value?.name !== 'Login') {
        router.push('/login');
    }
    ElMessage.error(msg);
}

// 尝试静默刷新 Token
async function tryRefreshToken() {
    const token = localStorage.getItem('auth_token');
    if (!token) return null;

    try {
        const { data: res } = await axios.post('/api/token/refresh', {}, {
            headers: { Authorization: `Bearer ${token}` },
        });
        if (res.success && res.data?.token) {
            localStorage.setItem('auth_token', res.data.token);
            import('@/echo').then(({ refreshEchoAuthHeaders }) => refreshEchoAuthHeaders()).catch(() => {});
            return res.data.token;
        }
        return null;
    } catch {
        return null;
    }
}

// ─── 请求拦截器 ───
apiClient.interceptors.request.use((config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    // 模拟登录令牌
    const impersonateToken = localStorage.getItem('impersonate_token');
    if (impersonateToken) {
        config.headers['X-Impersonate-Token'] = impersonateToken;
    }
    // 标记这是重试请求（用于拦截器中区分）
    config._retryCount = config._retryCount || 0;
    return config;
});

// ─── 响应拦截器 ───
apiClient.interceptors.response.use(
    (response) => response,
    async (error) => {
        const status = error.response?.status;
        const data = error.response?.data;
        const originalRequest = error.config;

        // 401 — 后台轮询类请求：不触发全局登出（如通知未读数、心跳）
        if (status === 401 && originalRequest.silentAuth) {
            return Promise.reject(error);
        }

        // 401 — 尝试静默刷新
        if (status === 401 && !originalRequest._retry) {
            // 防止刷新 Token 本身也触发刷新
            if (originalRequest.url?.includes('/token/refresh')) {
                forceLogout();
                return Promise.reject(error);
            }

            if (isRefreshing) {
                // 已在刷新中，排队等待
                return new Promise((resolve, reject) => {
                    failedQueue.push({ resolve, reject });
                }).then((newToken) => {
                    originalRequest.headers.Authorization = `Bearer ${newToken}`;
                    return apiClient(originalRequest);
                });
            }

            isRefreshing = true;
            originalRequest._retry = true;

            try {
                const newToken = await tryRefreshToken();
                if (newToken) {
                    processQueue(null, newToken);
                    originalRequest.headers.Authorization = `Bearer ${newToken}`;
                    import('@/echo').then(({ refreshEchoAuthHeaders }) => refreshEchoAuthHeaders()).catch(() => {});
                    return apiClient(originalRequest);
                }

                // 刷新失败
                processQueue(error);
                forceLogout();
                return Promise.reject(error);
            } catch {
                processQueue(error);
                forceLogout();
                return Promise.reject(error);
            } finally {
                isRefreshing = false;
            }
        }

        // 其他状态
        if (status === 403) {
            const msg = data?.message || i18n.global.t('messages.forbidden');
            ElMessage.error(msg);
        } else if (status === 429) {
            ElMessage.warning(i18n.global.t('messages.rate_limited'));
        } else if (status >= 500) {
            ElMessage.error(i18n.global.t('messages.internal_error'));
        }

        return Promise.reject(error);
    },
);

export default apiClient;
