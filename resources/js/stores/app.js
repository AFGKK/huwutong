import { defineStore } from 'pinia';
import { ref } from 'vue';
import { ElMessage } from 'element-plus';
import i18n from '@/i18n';

const SIDEBAR_STORAGE_KEY = 'hwt_admin_sidebar_collapsed';

function readSidebarCollapsed() {
    try {
        return localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

function persistSidebarCollapsed(collapsed) {
    try {
        localStorage.setItem(SIDEBAR_STORAGE_KEY, collapsed ? '1' : '0');
    } catch {
        /* ignore quota / private mode */
    }
}

export const useAppStore = defineStore('app', () => {
    const sidebarCollapsed = ref(readSidebarCollapsed());
    const currentTitle = ref(i18n.global.t('admin.menu.dashboard'));

    function toggleSidebar() {
        sidebarCollapsed.value = !sidebarCollapsed.value;
        persistSidebarCollapsed(sidebarCollapsed.value);
    }

    function setTitle(title) {
        currentTitle.value = title;
        document.title = `${title} - ${i18n.global.t('admin.brand_suffix')}`;
    }

    function notify(type, message) {
        const map = {
            success: ElMessage.success,
            error: ElMessage.error,
            warning: ElMessage.warning,
            info: ElMessage.info,
        };
        (map[type] || ElMessage.info)(message);
    }

    return { sidebarCollapsed, currentTitle, toggleSidebar, setTitle, notify };
});
