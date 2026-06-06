import { defineStore } from 'pinia';
import { ref } from 'vue';
import productApi from '@/api/product';

export const useProductStore = defineStore('product', () => {
    const products = ref([]);
    const loading = ref(false);

    async function fetchProducts(params = {}) {
        loading.value = true;
        try {
            const { data: res } = await productApi.list(params);
            if (res.success) {
                products.value = res.data?.data || res.data || [];
            }
        } finally {
            loading.value = false;
        }
        return products.value;
    }

    return { products, loading, fetchProducts };
});
