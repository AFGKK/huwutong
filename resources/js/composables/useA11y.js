import { ref, onMounted, onUnmounted } from 'vue';
import { ARIA_LABELS, SEMANTIC_ROLES } from '@/utils/a11y';
import i18n from '@/i18n';

/**
 * 屏幕阅读器实时通告管理
 *
 * 使用 aria-live="polite" 区域向屏幕阅读器发送即时消息。
 */
export function useAriaAnnouncer() {
    const announcerId = 'a11y-announcer-polite';
    const assertiveId = 'a11y-announcer-assertive';

    /**
     * 发送一条非打断式通告（aria-live="polite"）
     */
    function announce(message, { assertive = false, clearDelay = 3000 } = {}) {
        const id = assertive ? assertiveId : announcerId;
        let region = document.getElementById(id);
        if (!region) {
            region = document.createElement('div');
            region.id = id;
            region.setAttribute('aria-live', assertive ? 'assertive' : 'polite');
            region.setAttribute('aria-atomic', 'true');
            region.setAttribute('role', SEMANTIC_ROLES.STATUS);
            region.classList.add('a11y-sr-only');
            document.body.appendChild(region);
        }
        region.textContent = '';
        // Force reflow to ensure screen readers announce repeated identical messages
        requestAnimationFrame(() => {
            region.textContent = message;
            if (clearDelay > 0) {
                setTimeout(() => { region.textContent = ''; }, clearDelay);
            }
        });
    }

    /**
     * 发送一条打断式通告（aria-live="assertive"），用于重要错误/警告
     */
    function announceAssertive(message) {
        announce(message, { assertive: true });
    }

    return { announce, announceAssertive };
}

/**
 * 全局键盘快捷键管理
 *
 * 注册/注销全局键盘快捷键，支持组合键。
 */
export function useKeyboardShortcuts(shortcuts = {}) {
    const handler = (e) => {
        for (const [key, config] of Object.entries(shortcuts)) {
            const matchCtrl = config.ctrl !== undefined;
            const matchAlt = config.alt !== undefined;
            const matchShift = config.shift !== undefined;

            const ctrlMatch = matchCtrl ? e.ctrlKey === config.ctrl : true;
            const altMatch = matchAlt ? e.altKey === config.alt : true;
            const shiftMatch = matchShift ? e.shiftKey === config.shift : true;

            // Handle simple key (no modifier)
            if (config.ctrl === undefined && config.alt === undefined && config.shift === undefined) {
                if (e.key === config.key && !e.ctrlKey && !e.altKey && !e.metaKey) {
                    e.preventDefault();
                    config.handler(e);
                    return;
                }
                continue;
            }

            // Check that other modifiers are not pressed (meta key)
            if (ctrlMatch && altMatch && shiftMatch && e.key === config.key && !e.metaKey) {
                // For Ctrl+/ or Ctrl+K etc - allow meta
                if (config.ctrl && !config.alt && !config.shift) {
                    // ctrl+K, ctrl+/ etc
                }
                e.preventDefault();
                config.handler(e);
                return;
            }
        }
    };

    onMounted(() => window.addEventListener('keydown', handler));
    onUnmounted(() => window.removeEventListener('keydown', handler));

    return { register: (key, config) => { shortcuts[key] = config; } };
}

/**
 * 焦点管理工具
 *
 * 提供 trapFocus、restoreFocus、getFocusableElements 等功能
 */
export function useFocusManager() {
    const FOCUSABLE = [
        'a[href]',
        'area[href]',
        'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        'button:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
        'iframe',
        '[contenteditable]',
    ].join(', ');

    /**
     * 获取容器内所有可聚焦元素
     */
    function getFocusableElements(container) {
        if (!container) return [];
        return Array.from(container.querySelectorAll(FOCUSABLE));
    }

    /**
     * 将焦点限制在容器内（弹窗、对话框等使用）
     */
    function trapFocus(container, options = {}) {
        if (!container) return () => {};

        const { initialFocus, returnFocusTo } = options;
        const previouslyFocused = returnFocusTo !== false
            ? (returnFocusTo || document.activeElement)
            : null;

        const focusableElements = getFocusableElements(container);
        const firstFocusable = focusableElements[0];
        const lastFocusable = focusableElements[focusableElements.length - 1];

        // Focus the initial element
        if (initialFocus && focusableElements.includes(initialFocus)) {
            initialFocus.focus();
        } else if (firstFocusable) {
            firstFocusable.focus();
        }

        const handleKeyDown = (e) => {
            if (e.key !== 'Tab') return;

            const currentFocusable = getFocusableElements(container);
            const first = currentFocusable[0];
            const last = currentFocusable[currentFocusable.length - 1];

            if (e.shiftKey) {
                if (document.activeElement === first) {
                    e.preventDefault();
                    last?.focus();
                }
            } else {
                if (document.activeElement === last || !container.contains(document.activeElement)) {
                    e.preventDefault();
                    first?.focus();
                }
            }
        };

        document.addEventListener('keydown', handleKeyDown);

        // Return cleanup function
        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            if (previouslyFocused && previouslyFocused.focus && document.body.contains(previouslyFocused)) {
                previouslyFocused.focus();
            }
        };
    }

    /**
     * 聚焦到指定元素或选择器
     */
    function focusElement(elOrSelector) {
        const el = typeof elOrSelector === 'string'
            ? document.querySelector(elOrSelector)
            : elOrSelector;
        if (el && typeof el.focus === 'function') {
            el.focus({ preventScroll: false });
            el.scrollIntoView?.({ behavior: 'smooth', block: 'nearest' });
        }
    }

    return { getFocusableElements, trapFocus, focusElement };
}

/**
 * 页面标题和焦点管理
 *
 * 路由变化时更新页面标题并管理焦点。
 */
export function usePageAnnouncer() {
    const { announce } = useAriaAnnouncer();
    const previousTitle = ref(document.title);

    /**
     * 更新页面标题，并向屏幕阅读器通告
     */
    function updatePageTitle(title) {
        previousTitle.value = document.title;
        document.title = title;
        announce(i18n.global.t('a11y.navigated_to', { title }));
    }

    /**
     * 聚焦到主内容区域的指定元素
     */
    function focusMainContent(selector = '#main-content') {
        const el = document.querySelector(selector);
        if (el && typeof el.focus === 'function') {
            el.setAttribute('tabindex', '-1');
            el.focus({ preventScroll: false });
        }
    }

    return { updatePageTitle, focusMainContent };
}

/**
 * 侧边栏/菜单键盘导航
 *
 * 为侧边栏菜单提供上下箭头导航支持。
 */
export function useMenuKeyboardNavigation() {
    /**
     * 在菜单项之间进行键盘导航
     */
    function handleMenuKeydown(e, items, currentIndex) {
        const { key } = e;

        switch (key) {
            case 'ArrowDown':
                e.preventDefault();
                const nextIndex = Math.min(currentIndex + 1, items.length - 1);
                items[nextIndex]?.focus();
                return nextIndex;

            case 'ArrowUp':
                e.preventDefault();
                const prevIndex = Math.max(currentIndex - 1, 0);
                items[prevIndex]?.focus();
                return prevIndex;

            case 'Home':
                e.preventDefault();
                items[0]?.focus();
                return 0;

            case 'End':
                e.preventDefault();
                items[items.length - 1]?.focus();
                return items.length - 1;

            default:
                return currentIndex;
        }
    }

    /**
     * 为菜单项添加 ARIA 属性
     */
    function getMenuItemProps(index, activeIndex, level = 0) {
        return {
            role: 'menuitem',
            tabindex: index === activeIndex ? '0' : '-1',
            'aria-current': index === activeIndex ? 'page' : undefined,
            'aria-level': level + 1,
        };
    }

    return { handleMenuKeydown, getMenuItemProps };
}
