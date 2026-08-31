/**
 * WCAG 2.1 AA 无障碍常量与配置
 *
 * M3-54 无障碍合规
 * 集中管理所有无障碍相关的常量、ARIA 标签、键盘快捷键定义。
 */

// ── 键盘快捷键定义 ──

export const KEYBOARD_SHORTCUTS = {
    GLOBAL_SEARCH: { key: '/', ctrl: true, labelKey: 'a11y.sc_global_search', descKey: 'a11y.sc_global_search_desc' },
    SKIP_TO_CONTENT: { key: '1', alt: true, labelKey: 'a11y.sc_skip_content', descKey: 'a11y.sc_skip_content_desc' },
    GO_TO_NAV: { key: '2', alt: true, labelKey: 'a11y.sc_go_nav', descKey: 'a11y.sc_go_nav_desc' },
    SHOW_SHORTCUTS: { key: '?', shift: true, labelKey: 'a11y.sc_shortcuts', descKey: 'a11y.sc_shortcuts_desc' },
};

// ── ARIA 标签常量 ──

export const ARIA_LABELS = {
    SIDEBAR_TOGGLE: 'a11y.aria.sidebar_toggle',
    SIDEBAR_NAV: 'a11y.aria.sidebar_nav',
    TOPBAR: 'a11y.aria.topbar',
    MAIN_CONTENT: 'a11y.aria.main_content',
    NOTIFICATIONS: 'a11y.aria.notifications',
    USER_MENU: 'a11y.aria.user_menu',
    GLOBAL_SEARCH: 'a11y.aria.global_search',
    TENANT_SWITCHER: 'a11y.aria.tenant_switcher',
    LANGUAGE_SWITCHER: 'a11y.aria.language_switcher',
    PAGE_LOADING: 'a11y.aria.page_loading',
    TABLE_ACTIONS: 'a11y.aria.table_actions',
    FORM_SUBMIT: 'a11y.aria.form_submit',
    FORM_CANCEL: 'a11y.aria.form_cancel',
    FORM_RESET: 'a11y.aria.form_reset',
    CLOSE_DIALOG: 'a11y.aria.close_dialog',
    EXPAND_MENU: 'a11y.aria.expand_menu',
    COLLAPSE_MENU: 'a11y.aria.collapse_menu',
    PAGINATION: 'a11y.aria.pagination',
    SORT_ASC: 'a11y.aria.sort_asc',
    SORT_DESC: 'a11y.aria.sort_desc',
    FILTER: 'a11y.aria.filter',
    EXPORT: 'a11y.aria.export',
    IMPORT: 'a11y.aria.import',
    REFRESH: 'a11y.aria.refresh',
    BREADCRUMB: 'a11y.aria.breadcrumb',
    CHART: 'a11y.aria.chart',
};

// ── 语义化角色映射 ──

export const SEMANTIC_ROLES = {
    SIDEBAR: 'navigation',
    MAIN: 'main',
    HEADER: 'banner',
    FOOTER: 'contentinfo',
    SEARCH: 'search',
    COMPLEMENTARY: 'complementary',
    FORM: 'form',
    TABLE: 'table',
    ALERT: 'alert',
    STATUS: 'status',
    TAB_PANEL: 'tabpanel',
    TAB_LIST: 'tablist',
    MENU: 'menu',
    MENU_ITEM: 'menuitem',
};

// ── 键盘支持的导航键 ──

export const NAVIGATION_KEYS = {
    ARROW_UP: 'ArrowUp',
    ARROW_DOWN: 'ArrowDown',
    ARROW_LEFT: 'ArrowLeft',
    ARROW_RIGHT: 'ArrowRight',
    ENTER: 'Enter',
    SPACE: ' ',
    ESCAPE: 'Escape',
    TAB: 'Tab',
    HOME: 'Home',
    END: 'End',
};

// ── 焦点管理 ──

export const FOCUSABLE_SELECTORS = [
    'a[href]',
    'area[href]',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    'button:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
    'iframe',
    'object',
    'embed',
    '[contenteditable]',
].join(', ');
