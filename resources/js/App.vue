<template>
    <!-- WCAG: 跳过导航链接 -->
    <SkipToContent target="#main-content" />

    <!-- WCAG: 主内容区域由 layout 渲染 -->
    <router-view v-slot="{ Component, route }" @vue:beforeMount="beforeMountRoute">
        <component :is="Component" :key="route.path" />
    </router-view>

    <!-- WCAG: 快捷键帮助面板 -->
    <KeyboardShortcutsHelp
        :visible="shortcutsVisible"
        @close="shortcutsVisible = false"
    />
</template>

<script setup>
import { ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { KEYBOARD_SHORTCUTS } from '@/utils/a11y';
import { useAriaAnnouncer, useKeyboardShortcuts } from '@/composables/useA11y';
import SkipToContent from '@/components/SkipToContent.vue';
import KeyboardShortcutsHelp from '@/components/KeyboardShortcutsHelp.vue';

const router = useRouter();
const { announce } = useAriaAnnouncer();
const shortcutsVisible = ref(false);

// 注册全局键盘快捷键
useKeyboardShortcuts({
    GLOBAL_SEARCH: {
        ...KEYBOARD_SHORTCUTS.GLOBAL_SEARCH,
        handler: () => triggerGlobalSearch(),
    },
    SHOW_SHORTCUTS: {
        ...KEYBOARD_SHORTCUTS.SHOW_SHORTCUTS,
        handler: () => { shortcutsVisible.value = true; },
    },
});

function beforeMountRoute() {
    // 路由变化时向屏幕阅读器通告
    const route = router.currentRoute.value;
    const title = route.meta?.title || document.title;
    if (title) {
        announce(`已导航到 ${title}`);
    }
}

function triggerGlobalSearch() {
    // 派发自定义事件，GlobalSearch 组件会监听
    window.dispatchEvent(new CustomEvent('a11y:global-search'));
}

// 监听路由变化，更新页面标题
watch(
    () => router.currentRoute.value,
    (route) => {
        const title = route.meta?.title;
        if (title) {
            document.title = title + ' - HWT License 管理后台';
        }
    },
    { immediate: true },
);
</script>

<style>
/* ── WCAG 2.1 AA 全局样式 ── */

/* 屏幕阅读器专用（仅在屏幕阅读器中可见） */
.sr-only,
.a11y-sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* 跳过导航链接（仅焦点时显示） */
.skip-link {
    position: fixed;
    top: -100%;
    left: 8px;
    z-index: 10000;
    padding: 10px 20px;
    background: #409eff;
    color: #fff;
    border-radius: 0 0 4px 4px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    outline: 3px solid #a0cfff;
    outline-offset: 2px;
    transition: top 0.15s ease-in;
}

.skip-link:focus {
    top: 0;
}

/* WCAG 2.4.7: 焦点可见指示器 */
:focus-visible {
    outline: 2px solid #409eff;
    outline-offset: 2px;
    border-radius: 2px;
}

/* Element Plus 按钮焦点样式覆盖 */
.el-button:focus-visible {
    outline: 2px solid #409eff;
    outline-offset: 2px;
}

/* WCAG 1.4.1: 非文本内容的颜色对比度 — 确保图标有足够的背景对比 */
.el-icon, [class*=" el-icon-"], [class^=el-icon-] {
    color: inherit;
}

/* WCAG 2.4.3: 焦点顺序 — el-dialog 内的关闭按钮放首位 */
.el-dialog__headerbtn {
    order: -1;
}

/* 全局 font-size 基准，确保可缩放 */
html {
    font-size: 14px;
}

/* 确保表格行在焦点/悬停时有足够对比度 */
.el-table__body tr:hover > td {
    background-color: #ecf5ff;
}

/* 选中状态的颜色对比度增强 */
.el-table__body tr.current-row > td {
    background-color: #d9ecff;
}

/* WCAG 1.4.11: 非文本对比度 — 输入框边框 */
.el-input__wrapper,
.el-select__wrapper,
.el-textarea__inner {
    border-color: #c0c4cc;
}

.el-input__wrapper.is-focus,
.el-select__wrapper.is-focus {
    border-color: #409eff;
    box-shadow: 0 0 0 1px #409eff inset;
}

/* WCAG 2.4.4/2.4.9: 链接文本有下划线区分 */
a:not(.el-button):not(.skip-link) {
    text-decoration: underline;
    text-underline-offset: 2px;
}

/* 页面加载状态的 WCAG 适配 */
.app-loading[role="status"] {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    color: #606266;
    font-size: 14px;
}

.app-loading .loading-spinner {
    width: 32px;
    height: 32px;
    border: 3px solid #e4e7ed;
    border-top-color: #409eff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-bottom: 16px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
