import { defineStore } from 'pinia';
import { ref } from 'vue';
import { ElMessage } from 'element-plus';

export const useAppStore = defineStore('app', () => {
    const sidebarCollapsed = ref(false);
    const currentTitle = ref('仪表盘');

    function toggleSidebar() {
        sidebarCollapsed.value = !sidebarCollapsed.value;
    }

    function setTitle(title) {
        currentTitle.value = title;
        document.title = `${title} - HWT License`;
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
