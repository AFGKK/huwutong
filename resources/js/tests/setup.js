import { config } from '@vue/test-utils';
import { vi } from 'vitest';

// 模拟 localStorage
const localStorageMock = {
    store: {},
    getItem(key) {
        return this.store[key] || null;
    },
    setItem(key, value) {
        this.store[key] = String(value);
    },
    removeItem(key) {
        delete this.store[key];
    },
    clear() {
        this.store = {};
    },
};

Object.defineProperty(window, 'localStorage', {
    value: localStorageMock,
});

// 模拟 Element Plus 的 ElMessage
vi.mock('element-plus', () => ({
    default: {
        install: () => {},
    },
    ElMessage: {
        success: vi.fn(),
        error: vi.fn(),
        warning: vi.fn(),
        info: vi.fn(),
    },
    ElMessageBox: {
        confirm: vi.fn(),
        alert: vi.fn(),
        prompt: vi.fn(),
    },
    ElNotification: {
        success: vi.fn(),
        error: vi.fn(),
    },
    ElLoading: {
        service: vi.fn(() => ({ close: vi.fn() })),
    },
}));

// 全局配置
config.global.mocks = {
    $route: {
        path: '/',
        name: 'Dashboard',
        params: {},
        query: {},
    },
    $router: {
        push: vi.fn(),
        replace: vi.fn(),
    },
};
