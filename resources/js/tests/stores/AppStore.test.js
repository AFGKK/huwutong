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
        const store = useAppStore();

        expect(store.sidebarCollapsed).toBe(false);
        expect(store.currentTitle).toBe('仪表盘');
    });

    it('toggleSidebar 切换侧边栏状态', async () => {
        const { useAppStore } = await import('@/stores/app');
        const store = useAppStore();

        store.toggleSidebar();
        expect(store.sidebarCollapsed).toBe(true);

        store.toggleSidebar();
        expect(store.sidebarCollapsed).toBe(false);
    });

    it('setTitle 更新标题', async () => {
        const { useAppStore } = await import('@/stores/app');
        const store = useAppStore();

        store.setTitle('License 管理');
        expect(store.currentTitle).toBe('License 管理');
        expect(document.title).toBe('License 管理 - HWT License');
    });

    it('notify 调用对应的 ElMessage 方法', async () => {
        const { useAppStore } = await import('@/stores/app');
        const store = useAppStore();
        const { ElMessage } = await import('element-plus');

        store.notify('success', '操作成功');
        expect(ElMessage.success).toHaveBeenCalledWith('操作成功');

        store.notify('error', '操作失败');
        expect(ElMessage.error).toHaveBeenCalledWith('操作失败');

        store.notify('warning', '请注意');
        expect(ElMessage.warning).toHaveBeenCalledWith('请注意');

        store.notify('info', '信息提示');
        expect(ElMessage.info).toHaveBeenCalledWith('信息提示');

        // 未知类型 fallback 到 info
        store.notify('unknown', 'fallback');
        expect(ElMessage.info).toHaveBeenCalledWith('fallback');
    });
});
