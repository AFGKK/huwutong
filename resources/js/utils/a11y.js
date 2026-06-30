/**
 * WCAG 2.1 AA 无障碍常量与配置
 *
 * M3-54 无障碍合规
 * 集中管理所有无障碍相关的常量、ARIA 标签、键盘快捷键定义。
 */

// ── 键盘快捷键定义 ──

export const KEYBOARD_SHORTCUTS = {
    GLOBAL_SEARCH: { key: '/', ctrl: true, label: '全局搜索', description: '打开全局搜索对话框' },
    SKIP_TO_CONTENT: { key: '1', alt: true, label: '跳转到主内容', description: '跳过导航直接到主内容区' },
    GO_TO_NAV: { key: '2', alt: true, label: '跳转到导航', description: '跳转到左侧主导航' },
    SHOW_SHORTCUTS: { key: '?', shift: true, label: '键盘快捷键帮助', description: '显示所有键盘快捷键' },
};

// ── ARIA 标签常量 ──

export const ARIA_LABELS = {
    SIDEBAR_TOGGLE: '切换侧边栏',
    SIDEBAR_NAV: '主导航',
    TOPBAR: '顶部工具栏',
    MAIN_CONTENT: '主内容区域',
    NOTIFICATIONS: '通知',
    USER_MENU: '用户菜单',
    GLOBAL_SEARCH: '全局搜索',
    TENANT_SWITCHER: '租户切换',
    LANGUAGE_SWITCHER: '语言切换',
    PAGE_LOADING: '页面加载中',
    TABLE_ACTIONS: '表格操作',
    FORM_SUBMIT: '提交表单',
    FORM_CANCEL: '取消',
    FORM_RESET: '重置',
    CLOSE_DIALOG: '关闭对话框',
    EXPAND_MENU: '展开子菜单',
    COLLAPSE_MENU: '收起子菜单',
    PAGINATION: '分页导航',
    SORT_ASC: '升序排列',
    SORT_DESC: '降序排列',
    FILTER: '筛选',
    EXPORT: '导出数据',
    IMPORT: '导入数据',
    REFRESH: '刷新数据',
    BREADCRUMB: '当前位置',
    CHART: '图表',
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
