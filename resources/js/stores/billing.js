import { defineStore } from 'pinia';
import { ref } from 'vue';
import billingApi from '@/api/billing';

export const useBillingStore = defineStore('billing', () => {
    const subscriptions = ref([]);
    const invoices = ref([]);
    const stats = ref(null);
    const loading = ref(false);

    async function fetchSubscriptions(params = {}) {
        loading.value = true;
        try {
            const { data: res } = await billingApi.subscriptions(params);
            if (res.success) {
                subscriptions.value = res.data?.data || res.data || [];
            }
        } finally {
            loading.value = false;
        }
        return subscriptions.value;
    }

    async function fetchInvoices(params = {}) {
        try {
            const { data: res } = await billingApi.invoices(params);
            if (res.success) {
                invoices.value = res.data?.data || res.data || [];
            }
        } catch {
            // ignore
        }
        return invoices.value;
    }

    async function fetchStats() {
        try {
            const { data: res } = await billingApi.stats();
            if (res.success) stats.value = res.data;
        } catch {
            // ignore
        }
        return stats.value;
    }

    return {
        subscriptions, invoices, stats, loading,
        fetchSubscriptions, fetchInvoices, fetchStats,
    };
});
