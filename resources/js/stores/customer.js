import { defineStore } from 'pinia';
import { ref } from 'vue';
import customerApi from '@/api/customer';

export const useCustomerStore = defineStore('customer', () => {
    const customers = ref([]);
    const loading = ref(false);
    const stats = ref(null);
    const currentPage = ref(1);
    const lastPage = ref(1);
    const total = ref(0);

    async function fetchCustomers(params = {}) {
        loading.value = true;
        try {
            const { data: res } = await customerApi.list(params);
            if (res.success) {
                customers.value = res.data?.data || res.data || [];
                if (res.meta) {
                    currentPage.value = res.meta.current_page || 1;
                    lastPage.value = res.meta.last_page || 1;
                    total.value = res.meta.total || 0;
                }
            }
        } finally {
            loading.value = false;
        }
        return customers.value;
    }

    async function fetchStats() {
        try {
            const { data: res } = await customerApi.stats();
            if (res.success) stats.value = res.data;
        } catch {
            // ignore
        }
        return stats.value;
    }

    function removeCustomer(id) {
        customers.value = customers.value.filter(c => c.id !== id);
    }

    return {
        customers, loading, stats, currentPage, lastPage, total,
        fetchCustomers, fetchStats, removeCustomer,
    };
});
