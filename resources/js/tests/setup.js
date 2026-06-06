import { config } from '@vue/test-utils';
import { vi } from 'vitest';

// 模拟 localStorage
const localStorageMock = {
    store: {},
    getItem(key) { return this.store[key] || null; },
    setItem(key, value) { this.store[key] = String(value); },
    removeItem(key) { delete this.store[key]; },
    clear() { this.store = {}; },
};

Object.defineProperty(window, 'localStorage', { value: localStorageMock });

// 模拟 Element Plus
vi.mock('element-plus', () => ({
    default: { install: () => {} },
    ElMessage: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() },
    ElMessageBox: { confirm: vi.fn(), alert: vi.fn(), prompt: vi.fn() },
    ElNotification: { success: vi.fn(), error: vi.fn() },
    ElLoading: { service: vi.fn(() => ({ close: vi.fn() })) },
}));

// 虚拟图标组件（用于 global.components 注册）
const MockIcon = {
    name: 'MockIcon',
    render(createElement) {
        return createElement('i', { class: 'el-icon' });
    },
};

// 全局注册常用 icon 组件名（保证 <Bell /> 等可以直接在模板中使用）
const iconNames = [
    'Bell', 'ArrowDown', 'SwitchButton', 'Key', 'Lock', 'UserFilled',
    'Fold', 'Expand', 'OfficeBuilding', 'CircleCheck', 'Setting',
    'Plus', 'Search', 'Edit', 'Delete', 'Download', 'Upload',
    'Refresh', 'More', 'Close', 'Check', 'Warning', 'Info',
    'Success', 'Error', 'Question', 'WarningFilled', 'CircleClose',
    'CirclePlus', 'Remove', 'Add', 'ArrowUp', 'ArrowLeft', 'ArrowRight',
    'Bottom', 'Top', 'Sort', 'SortUp', 'SortDown', 'Rank',
    'Copy', 'Scissors', 'Link', 'Paperclip', 'Share', 'View',
    'Hide', 'Menu', 'Grid', 'List', 'DataBoard', 'TrendCharts',
    'ChatDotSquare', 'Message', 'Timer', 'Coin', 'Promotion',
    'Shield', 'Monitor', 'Tools', 'MagicStick', 'User', 'Users',
    'Phone', 'MessageBox', 'ChatLineSquare', 'ChatLineRound',
    'ChatRound', 'ChatSquare', 'ChatDotRound',
];

const globalComponents = {};
for (const name of iconNames) {
    globalComponents[name] = MockIcon;
}

// 全局配置：stubs、mocks、components、directives
config.global.stubs = {
    'el-divider': { template: '<hr class="el-divider" />' },
    'el-icon': { template: '<i class="el-icon"><slot /></i>' },
    'el-tag': { template: '<span class="el-tag"><slot /></span>' },
    'el-button': { template: '<button class="el-button"><slot /></button>' },
    'el-link': { template: '<a class="el-link"><slot /></a>' },
    'el-card': { template: '<div class="el-card"><slot /></div>' },
    'el-row': { template: '<div class="el-row"><slot /></div>' },
    'el-col': { template: '<div class="el-col"><slot /></div>' },
    'el-table': { template: '<div class="el-table"><slot /></div>' },
    'el-table-column': { template: '<div class="el-table-column"><slot name="default" :row="{}" :column="{}" /></div>' },
    'el-pagination': { template: '<div class="el-pagination" />' },
    'el-form': { template: '<form class="el-form"><slot /></form>' },
    'el-form-item': { template: '<div class="el-form-item"><slot /></div>' },
    'el-select': { template: '<div class="el-select"><slot /></div>' },
    'el-option': { template: '<div class="el-option" />' },
    'el-input': { template: '<input class="el-input" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />', props: ['modelValue'] },
    'el-input-number': { template: '<input class="el-input-number" />', props: ['modelValue'] },
    'el-date-picker': { template: '<input class="el-date-picker" />', props: ['modelValue'] },
    'el-dialog': { template: '<div v-if="modelValue" class="el-dialog"><slot /></div>', props: ['modelValue'] },
    'el-dropdown': { template: '<div class="el-dropdown"><slot /></div>' },
    'el-dropdown-menu': { template: '<div class="el-dropdown-menu"><slot /></div>' },
    'el-dropdown-item': { template: '<div class="el-dropdown-item"><slot /></div>' },
    'el-radio-group': { template: '<div class="el-radio-group"><slot /></div>' },
    'el-radio': { template: '<label class="el-radio"><slot /></label>' },
    'el-empty': { template: '<div class="el-empty" />' },
    'el-skeleton': { template: '<div class="el-skeleton"><slot /></div>' },
    'el-statistic': { template: '<div class="el-statistic"><slot /></div>' },
    'el-page-header': { template: '<div class="el-page-header"><slot /></div>' },
    'el-space': { template: '<div class="el-space"><slot /></div>' },
    'el-tabs': { template: '<div class="el-tabs"><slot /></div>' },
    'el-tab-pane': { template: '<div class="el-tab-pane"><slot /></div>' },
    'el-descriptions': { template: '<div class="el-descriptions"><slot /></div>' },
    'el-descriptions-item': { template: '<div class="el-descriptions-item"><slot /></div>' },
    'el-badge': { template: '<div class="el-badge"><slot /></div>' },
    'el-popover': { template: '<div class="el-popover"><slot name="reference" /><slot /></div>' },
    'el-tooltip': { template: '<div class="el-tooltip"><slot /></div>' },
    'el-progress': { template: '<div class="el-progress" />' },
    'el-switch': { template: '<div class="el-switch" />' },
    'el-color-picker': { template: '<div class="el-color-picker" />' },
    'el-image': { template: '<div class="el-image" />' },
};

config.global.components = globalComponents;

config.global.directives = {
    loading: { mounted: vi.fn() },
};

config.global.mocks = {
    $route: { path: '/', name: 'Dashboard', params: {}, query: {} },
    $router: { push: vi.fn(), replace: vi.fn() },
};
