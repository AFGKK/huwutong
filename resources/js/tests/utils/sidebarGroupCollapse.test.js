import { describe, it, expect } from 'vitest';
import {
    isGroupCollapsed,
    toggleGroupCollapsed,
    findGroupLabelForPath,
    expandGroupForPath,
} from '@/utils/sidebarGroupCollapse';

const groups = [
    {
        label: '核心业务',
        items: [
            { path: '/dashboard' },
            { path: '/licenses' },
            { path: '/license-analytics' },
        ],
    },
    {
        label: '应用生态',
        items: [
            { path: '/blog' },
            { path: '/moments' },
        ],
    },
];

describe('sidebarGroupCollapse', () => {
    it('defaults to collapsed when label is unset', () => {
        expect(isGroupCollapsed({}, '核心业务')).toBe(true);
        expect(isGroupCollapsed({ 核心业务: true }, '核心业务')).toBe(true);
    });

    it('treats false as expanded', () => {
        expect(isGroupCollapsed({ 核心业务: false }, '核心业务')).toBe(false);
    });

    it('toggles collapsed → expanded → collapsed', () => {
        const state = {};
        expect(toggleGroupCollapsed(state, '应用生态')).toBe(false);
        expect(isGroupCollapsed(state, '应用生态')).toBe(false);
        expect(toggleGroupCollapsed(state, '应用生态')).toBe(true);
        expect(isGroupCollapsed(state, '应用生态')).toBe(true);
    });

    it('does not expand other groups when one is toggled', () => {
        const state = {};
        toggleGroupCollapsed(state, '核心业务');
        expect(isGroupCollapsed(state, '应用生态')).toBe(true);
        expect(isGroupCollapsed(state, '核心业务')).toBe(false);
    });

    it('finds group by exact and nested path, preferring longest match', () => {
        expect(findGroupLabelForPath(groups, '/blog')).toBe('应用生态');
        expect(findGroupLabelForPath(groups, '/licenses/12')).toBe('核心业务');
        expect(findGroupLabelForPath(groups, '/license-analytics')).toBe('核心业务');
        expect(findGroupLabelForPath(groups, '/unknown')).toBeNull();
    });

    it('expandGroupForPath only opens the active group', () => {
        const state = {};
        expect(expandGroupForPath(state, groups, '/blog')).toBe('应用生态');
        expect(isGroupCollapsed(state, '应用生态')).toBe(false);
        expect(isGroupCollapsed(state, '核心业务')).toBe(true);
    });
});
