import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { ref, nextTick } from 'vue';

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
    ElMessageBox: { confirm: vi.fn().mockResolvedValue('confirm') },
}));

vi.mock('vue-router', () => ({
    useRoute: vi.fn(() => ({ params: { id: '1' } })),
    useRouter: vi.fn(() => ({ push: vi.fn() })),
}));

const isMobileRef = ref(false);
vi.mock('@/composables/useResponsive', () => ({
    useResponsive: () => ({
        width: ref(1200),
        isMobile: isMobileRef,
        isTablet: ref(false),
        isDesktop: ref(true),
    }),
}));

const mockGetCart = vi.fn().mockResolvedValue({
    data: {
        items: [
            { sku_id: 1, product_name: '测试商品', quantity: 1, unit_price: 99, subtotal: 99 },
        ],
    },
});
const mockGetCartSummary = vi.fn().mockResolvedValue({
    data: { data: { item_count: 1, subtotal: 99, final_amount: 99 } },
});
const mockListOrders = vi.fn().mockResolvedValue({
    data: { data: [], meta: { total: 0 } },
});
const mockGetSkus = vi.fn().mockResolvedValue({
    data: { data: { data: [], total: 0, current_page: 1, per_page: 20, last_page: 1 } },
});

vi.mock('@/api/shop', () => ({
    default: {
        getCart: (...args) => mockGetCart(...args),
        getCartSummary: (...args) => mockGetCartSummary(...args),
        listOrders: (...args) => mockListOrders(...args),
        getSkus: (...args) => mockGetSkus(...args),
        getFilterTags: vi.fn().mockResolvedValue({ data: { data: [] } }),
        getHotSearchTerms: vi.fn().mockResolvedValue({ data: { data: [] } }),
        getCategories: vi.fn().mockResolvedValue({ data: { data: [] } }),
        getSearchHistory: vi.fn().mockResolvedValue({ data: { data: [] } }),
    },
}));

const baseStubs = {
    'el-card': { template: '<div class="el-card"><slot name="header" /><slot /></div>' },
    'el-table': { template: '<div class="el-table"><slot /></div>' },
    'el-table-column': { template: '<div class="el-table-column" />' },
    'el-button': { template: '<button class="el-button"><slot /></button>' },
    'el-input': { template: '<input class="el-input" />', props: ['modelValue', 'size', 'placeholder'] },
    'el-input-number': { template: '<input type="number" class="el-input-number" />', props: ['modelValue', 'size', 'min', 'max', 'disabled'] },
    'el-select': { template: '<div class="el-select"><slot /></div>', props: ['modelValue'] },
    'el-option': { template: '<div class="el-option" />', props: ['label', 'value'] },
    'el-tag': { template: '<span class="el-tag"><slot /></span>' },
    'el-empty': { template: '<div class="el-empty"><slot /></div>' },
    'el-skeleton': { template: '<div class="el-skeleton" />' },
    'el-alert': { template: '<div class="el-alert" />' },
    'el-row': { template: '<div class="el-row"><slot /></div>' },
    'el-col': { template: '<div class="el-col"><slot /></div>' },
    'el-form': { template: '<form class="el-form"><slot /></form>', props: ['inline'] },
    'el-form-item': { template: '<div class="el-form-item"><slot /></div>' },
    'el-drawer': { template: '<div class="el-drawer"><slot /></div>', props: ['modelValue', 'size'] },
    'el-dialog': { template: '<div class="el-dialog"><slot /></div>', props: ['modelValue', 'width'] },
    'el-descriptions': { template: '<div class="el-descriptions"><slot /></div>', props: ['column'] },
    'el-descriptions-item': { template: '<div class="el-descriptions-item"><slot /></div>' },
    'el-pagination': { template: '<div class="el-pagination" />' },
    'el-divider': { template: '<hr class="el-divider" />' },
    'el-image': { template: '<img class="el-image" />' },
    'el-badge': { template: '<span class="el-badge"><slot /></span>' },
    'el-icon': { template: '<i class="el-icon"><slot /></i>' },
    'el-rate': { template: '<div class="el-rate" />' },
    'WishlistButton': { template: '<button class="wishlist-btn" />' },
    'ProductReviews': { template: '<div class="product-reviews" />' },
};

describe('Portal mobile responsive views', () => {
    beforeEach(() => {
        localStorage.clear();
        isMobileRef.value = false;
        vi.clearAllMocks();
        sessionStorage.clear();
    });

    it('Cart 桌面端渲染表格容器', async () => {
        const Cart = (await import('@/views/shop/Cart.vue')).default;
        const wrapper = mount(Cart, { global: { stubs: baseStubs } });
        await flushPromises();
        expect(wrapper.find('.table-scroll-wrap').exists()).toBe(true);
        expect(wrapper.find('.cart-mobile-list').exists()).toBe(false);
    });

    it('Cart 移动端渲染卡片列表', async () => {
        isMobileRef.value = true;
        const Cart = (await import('@/views/shop/Cart.vue')).default;
        const wrapper = mount(Cart, { global: { stubs: baseStubs } });
        await flushPromises();
        expect(wrapper.find('.cart-mobile-list').exists()).toBe(true);
        expect(wrapper.find('.table-scroll-wrap').exists()).toBe(false);
    });

    it('Checkout 移动端渲染卡片明细', async () => {
        isMobileRef.value = true;
        sessionStorage.setItem('checkout_items', JSON.stringify([
            { product_name: '测试商品', quantity: 1, price: 99, sku: { price: 99, billing_cycle: 'monthly' } },
        ]));
        const Checkout = (await import('@/views/shop/Checkout.vue')).default;
        const wrapper = mount(Checkout, { global: { stubs: baseStubs } });
        await nextTick();
        expect(wrapper.find('.checkout-mobile-list').exists()).toBe(true);
    });

    it('Orders 页面包含订单卡片布局', async () => {
        mockListOrders.mockResolvedValueOnce({
            data: {
                data: [{
                    id: 1,
                    order_no: 'ORD-001',
                    status: 'paid',
                    created_at: '2026-06-21',
                    final_amount: 99,
                    items: [{ id: 1, name: '测试商品', quantity: 1, unit_price: 99 }],
                }],
                meta: { total: 1 },
            },
        });
        const Orders = (await import('@/views/portal/Orders.vue')).default;
        const wrapper = mount(Orders, { global: { stubs: baseStubs } });
        await nextTick();
        await nextTick();
        expect(wrapper.find('.order-card').exists()).toBe(true);
        expect(wrapper.find('.portal-orders-page').exists()).toBe(true);
    });

    it('Shop Index 页面可挂载且包含商品网格', async () => {
        mockGetSkus.mockResolvedValueOnce({
            data: {
                data: {
                    data: [{ id: 1, product_id: 1, name: '测试 SKU', price: 99, sold_count: 0 }],
                    total: 1,
                    current_page: 1,
                    per_page: 20,
                    last_page: 1,
                },
            },
        });
        const ShopIndex = (await import('@/views/shop/Index.vue')).default;
        const wrapper = mount(ShopIndex, { global: { stubs: baseStubs } });
        await flushPromises();
        expect(wrapper.find('.shop-page').exists()).toBe(true);
        expect(wrapper.find('.product-grid').exists()).toBe(true);
    });
});
