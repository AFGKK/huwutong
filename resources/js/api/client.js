import axios from 'axios';
import { ElMessage } from 'element-plus';
import router from '@/router';

const apiClient = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// 请求拦截器
apiClient.interceptors.request.use((config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// 响应拦截器
apiClient.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        const data = error.response?.data;

        if (status === 401) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user');
            router.push('/login');
            ElMessage.error('登录已过期，请重新登录');
        } else if (status === 403) {
            const msg = data?.message || '没有权限执行此操作';
            ElMessage.error(msg);
        } else if (status === 429) {
            ElMessage.warning('请求过于频繁，请稍后再试');
        } else if (status >= 500) {
            ElMessage.error('服务器内部错误，请稍后再试');
        }

        return Promise.reject(error);
    },
);

export default apiClient;
