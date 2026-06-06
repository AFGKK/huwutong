import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import licenseApi from '@/api/license';

export const useLicenseStore = defineStore('license', () => {
    const licenses = ref([]);
    const loading = ref(false);
    const stats = ref(null);
    const currentPage = ref(1);
    const lastPage = ref(1);
    const total = ref(0);

    const activeLicenses = computed(() =>
        licenses.value.filter(l => l.status === 'active'),
    );
    const expiringSoon = computed(() =>
        licenses.value.filter(l => {
            if (!l.expires_at) return false;
            const days = (new Date(l.expires_at) - new Date()) / 86400000;
            return days > 0 && days <= 30;
        }),
    );

    async function fetchLicenses(params = {}) {
        loading.value = true;
        try {
            const { data: res } = await licenseApi.list(params);
            if (res.success) {
                licenses.value = res.data?.data || res.data || [];
                if (res.meta) {
                    currentPage.value = res.meta.current_page || 1;
                    lastPage.value = res.meta.last_page || 1;
                    total.value = res.meta.total || 0;
                }
            }
        } finally {
            loading.value = false;
        }
        return licenses.value;
    }

    async function fetchStats() {
        try {
            const { data: res } = await licenseApi.stats();
            if (res.success) stats.value = res.data;
        } catch {
            // ignore
        }
        return stats.value;
    }

    function removeLicense(id) {
        licenses.value = licenses.value.filter(l => l.id !== id);
    }

    function addLicense(license) {
        licenses.value.unshift(license);
    }

    function updateLicense(updated) {
        const idx = licenses.value.findIndex(l => l.id === updated.id);
        if (idx >= 0) licenses.value[idx] = updated;
    }

    return {
        licenses, loading, stats, currentPage, lastPage, total,
        activeLicenses, expiringSoon,
        fetchLicenses, fetchStats, removeLicense, addLicense, updateLicense,
    };
});
