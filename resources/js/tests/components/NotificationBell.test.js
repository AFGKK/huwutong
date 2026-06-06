import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { setActivePinia, createPinia } from 'pinia';
import { ElMessage } from 'element-plus';

// 模拟 @element-plus/icons-vue
vi.mock('@element-plus/icons-vue', () => ({
    Bell: { name: 'Bell', render: () => {} },
    ArrowDown: { name: 'ArrowDown', render: () => {} },
    SwitchButton: { name: 'SwitchButton', render: () => {} },
    Key: { name: 'Key', render: () => {} },
    Lock: { name: 'Lock', render: () => {} },
    UserFilled: { name: 'UserFilled', render: () => {} },
    Fold: { name: 'Fold', render: () => {} },
    Expand: { name: 'Expand', render: () => {} },
    OfficeBuilding: { name: 'OfficeBuilding', render: () => {} },
    CircleCheck: { name: 'CircleCheck', render: () => {} },
    Setting: { name: 'Setting', render: () => {} },
}));

// 模拟 API
vi.mock('@/api/notification', () => ({
    default: {
        list: vi.fn().mockResolvedValue({
            data: {
                success: true,
                data: { data: [] },
            },
        }),
        unreadCount: vi.fn().mockResolvedValue({
            data: {
                success: true,
                data: { count: 3 },
            },
        }),
        markAllRead: vi.fn().mockResolvedValue({ data: { success: true } }),
        markRead: vi.fn().mockResolvedValue({ data: { success: true } }),
    },
}));

describe('NotificationBell.vue', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
    });

    it('renders bell icon with badge', async () => {
        const NotificationBell = (await import('@/components/NotificationBell.vue')).default;
        const wrapper = mount(NotificationBell, {
            global: {
                stubs: {
                    'el-popover': {
                        template: '<div><slot name="reference" /><slot /></div>',
                        props: ['visible'],
                    },
                    'el-badge': {
                        template: '<div><slot /></div>',
                        props: ['value', 'hidden'],
                    },
                    'el-tooltip': {
                        template: '<div><slot /></div>',
                    },
                    'el-button': {
                        template: '<button @click="$emit(\'click\')"><slot /></button>',
                        props: ['circle', 'icon'],
                    },
                    'el-empty': {
                        template: '<div>暂无通知</div>',
                    },
                    'el-tag': {
                        template: '<span><slot /></span>',
                        props: ['type', 'size', 'effect', 'round'],
                    },
                },
            },
        });

        expect(wrapper.exists()).toBe(true);
    });

    it('timeAgo returns correct relative times', async () => {
        const NotificationBell = (await import('@/components/NotificationBell.vue')).default;

        // 测试 timeAgo 函数的逻辑（通过组件内部的 getter 验证）
        const now = Date.now();
        const oneMinuteAgo = new Date(now - 30 * 1000).toISOString();
        const oneHourAgo = new Date(now - 30 * 60 * 1000).toISOString();
        const oneDayAgo = new Date(now - 5 * 3600 * 1000).toISOString();
        const longAgo = new Date(now - 10 * 86400 * 1000).toISOString();

        // 通过渲染不同时间的通知文本来验证 timeAgo
        const wrapper = mount(NotificationBell, {
            global: {
                stubs: {
                    'el-popover': { template: '<div><slot name="reference" /><slot /></div>' },
                    'el-badge': { template: '<div><slot /></div>' },
                    'el-tooltip': { template: '<div><slot /></div>' },
                    'el-button': { template: '<button><slot /></button>' },
                    'el-empty': { template: '<div>暂无通知</div>' },
                    'el-tag': { template: '<span><slot /></span>' },
                },
            },
        });

        // 验证 vm.timeAgo 函数存在
        expect(typeof wrapper.vm.timeAgo).toBe('function');

        expect(wrapper.vm.timeAgo(oneMinuteAgo)).toBe('刚刚');
        expect(wrapper.vm.timeAgo(oneHourAgo)).toContain('分钟前');
        expect(wrapper.vm.timeAgo(oneDayAgo)).toContain('小时前');
        expect(wrapper.vm.timeAgo(longAgo)).toContain('天前');
    });
});
