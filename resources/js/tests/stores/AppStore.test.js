import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// 模拟 localStorage
const localStorageMock = {
    store: {},
    getItem(key) { return this.store[key] || null; },
    setItem(key, value) { this.store[key] = String(value); },
    removeItem(key) { delete this.store[key]; },
    clear() { this.store = {}; },
};
Object.defineProperty(window, 'localStorage', { value: localStorageMock });

// 模拟 router（client.js 依赖它）
vi.mock('@/router', () => ({
    default: {
        push: vi.fn(),
        replace: vi.fn(),
    },
}));

// 模拟 Element Plus（client.js 响应拦截器依赖它）
vi.mock('element-plus', () => ({
    ElMessage: {
        success: vi.fn(),
        error: vi.fn(),
        warning: vi.fn(),
        info: vi.fn(),
    },
}));

describe('useAppStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();
    });

    it('初始状态正确', async () => {
        const { useAppStore } = await import('@/stores/app');
        const i18n = (await import('@/i18n')).default;
        const store = useAppStore();

        expect(store.sidebarCollapsed).toBe(false);
        expect(store.currentTitle).toBe(i18n.global.t('admin.menu.dashboard'));
    });

    it('toggleSidebar 切换侧边栏状态', async () => {
        const { useAppStore } = await import('@/stores/app');
        const store = useAppStore();

        store.toggleSidebar();
        expect(store.sidebarCollapsed).toBe(true);
        expect(localStorage.getItem('hwt_admin_sidebar_collapsed')).toBe('1');

        store.toggleSidebar();
        expect(store.sidebarCollapsed).toBe(false);
        expect(localStorage.getItem('hwt_admin_sidebar_collapsed')).toBe('0');
    });

    it('setTitle 更新标题', async () => {
        const { useAppStore } = await import('@/stores/app');
        const i18n = (await import('@/i18n')).default;
        const store = useAppStore();

        store.setTitle('License Admin');
        expect(store.currentTitle).toBe('License Admin');
        expect(document.title).toBe(`License Admin - ${i18n.global.t('admin.brand_suffix')}`);
    });

    it('notify 调用对应的 ElMessage 方法', async () => {
        const { useAppStore } = await import('@/stores/app');
        const store = useAppStore();
        const { ElMessage } = await import('element-plus');

        store.notify('success', 'ok');
        expect(ElMessage.success).toHaveBeenCalledWith('ok');

        store.notify('error', 'fail');
        expect(ElMessage.error).toHaveBeenCalledWith('fail');

        store.notify('warning', 'warn');
        expect(ElMessage.warning).toHaveBeenCalledWith('warn');

        store.notify('info', 'info');
        expect(ElMessage.info).toHaveBeenCalledWith('info');

        // 未知类型 fallback 到 info
        store.notify('unknown', 'fallback');
        expect(ElMessage.info).toHaveBeenCalledWith('fallback');
    });
});
