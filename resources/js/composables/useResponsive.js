import { ref, onMounted, onUnmounted } from 'vue';

const MOBILE_MAX = 768;
const TABLET_MAX = 1024;

/**
 * 响应式断点（与 PortalLayout / AdminLayout 一致）
 */
export function useResponsive() {
    const width = ref(typeof window !== 'undefined' ? window.innerWidth : 1200);
    const isMobile = ref(width.value <= MOBILE_MAX);
    const isTablet = ref(width.value > MOBILE_MAX && width.value <= TABLET_MAX);
    const isDesktop = ref(width.value > TABLET_MAX);

    function update() {
        const w = window.innerWidth;
        width.value = w;
        isMobile.value = w <= MOBILE_MAX;
        isTablet.value = w > MOBILE_MAX && w <= TABLET_MAX;
        isDesktop.value = w > TABLET_MAX;
    }

    onMounted(() => {
        update();
        window.addEventListener('resize', update, { passive: true });
    });

    onUnmounted(() => {
        window.removeEventListener('resize', update);
    });

    return { width, isMobile, isTablet, isDesktop };
}
