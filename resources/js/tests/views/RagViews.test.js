import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { setActivePinia, createPinia } from 'pinia';
import { nextTick } from 'vue';

const localStorageMock = {
    store: {},
    getItem(key) { return this.store[key] || null; },
    setItem(key, value) { this.store[key] = String(value); },
    removeItem(key) { delete this.store[key]; },
    clear() { this.store = {}; },
};
Object.defineProperty(window, 'localStorage', { value: localStorageMock });

vi.mock('element-plus', () => ({
    ElMessage: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() },
    ElMessageBox: { confirm: vi.fn().mockResolvedValue('confirm'), alert: vi.fn(), prompt: vi.fn().mockResolvedValue({ value: 'test' }) },
    ElNotification: { success: vi.fn(), error: vi.fn() },
    ElLoading: { service: vi.fn(() => ({ close: vi.fn() })) },
}));

vi.mock('@/router', () => ({ default: { push: vi.fn() } }));
vi.mock('vue-router', () => ({
    useRoute: vi.fn(() => ({ params: {} })),
    useRouter: vi.fn(() => ({ push: vi.fn() })),
}));

const mockRagStats = vi.fn();
const mockRagRebuildIndex = vi.fn();

vi.mock('@/api/rag', () => ({
    default: {
        stats: (...args) => mockRagStats(...args),
        rebuildIndex: (...args) => mockRagRebuildIndex(...args),
    },
}));

const baseStubs = {
    'el-card': { template: '<div class="el-card"><slot /></div>' },
    'el-table': { template: '<div class="el-table"><slot /></div>' },
    'el-table-column': { template: '<div class="el-table-column"><slot name="default" :row="{}" :column="{}" /></div>' },
    'el-button': { template: '<button class="el-button" @click="$emit(\'click\')"><slot /></button>' },
    'el-tag': { template: '<span class="el-tag"><slot /></span>' },
    'el-icon': { template: '<i class="el-icon"><slot /></i>' },
    'el-row': { template: '<div class="el-row"><slot /></div>' },
    'el-col': { template: '<div class="el-col"><slot /></div>' },
    'el-statistic': { template: '<div class="el-statistic"><slot /></div>' },
    'el-skeleton': { template: '<div class="el-skeleton"><slot /></div>' },
    'el-empty': { template: '<div class="el-empty" />' },
    'el-space': { template: '<div class="el-space"><slot /></div>' },
    'el-alert': { template: '<div class="el-alert"><slot /></div>' },
};

describe('rag/Index.vue - RAG 知识库管理页', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();

        mockRagStats.mockResolvedValue({
            data: {
                success: true,
                data: {
                    total_documents: 10,
                    indexed_documents: 8,
                    pending_documents: 2,
                    total_chunks: 150,
                    last_indexed_at: '2026-06-05T12:00:00Z',
                },
            },
        });
    });

    it('组件能挂载', async () => {
        const RagIndex = (await import('@/views/rag/Index.vue')).default;
        const wrapper = mount(RagIndex, {
            global: { stubs: baseStubs },
        });
        expect(wrapper.exists()).toBe(true);
    });

    it('挂载后加载 RAG 统计', async () => {
        const RagIndex = (await import('@/views/rag/Index.vue')).default;
        mount(RagIndex, { global: { stubs: baseStubs } });
        await vi.dynamicImportSettled?.();
        await nextTick();
        expect(mockRagStats).toHaveBeenCalled();
    });

    it('包含知识库管理标题', async () => {
        const RagIndex = (await import('@/views/rag/Index.vue')).default;
        const wrapper = mount(RagIndex, { global: { stubs: baseStubs } });
        await nextTick();
        expect(wrapper.text()).toContain('RAG');
    });
});
