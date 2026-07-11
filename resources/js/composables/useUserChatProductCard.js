import { ref } from 'vue'
import { ElMessage } from 'element-plus'
import apiClient from '@/api/client'

/**
 * 私信会话内发送商品卡片
 */
export function useUserChatProductCard({ activeConv, loadMessages }) {
    const showProductCardDialog = ref(false)
    const productCardSearch = ref('')
    const productCardList = ref([])
    const productCardLoading = ref(false)
    const productCardTotal = ref(0)
    const productCardPage = ref(1)
    const selectedProductForCard = ref(null)
    const productCardNote = ref('')
    const sendingProductCard = ref(false)

    function formatProductPrice(p) {
        if (!p) return '面议'
        if (p.sku_price_min != null && p.sku_price_min !== undefined) {
            const min = p.sku_price_min
            const max = p.sku_price_max
            if (max != null && max !== min) return `¥${min} ~ ¥${max}`
            return `¥${min}`
        }
        if (p.base_price != null && p.base_price !== undefined) return `¥${p.base_price}`
        return '面议'
    }

    function openProductCardDialog() {
        if (!activeConv.value?.id) {
            ElMessage.warning('请先选择会话')
            return
        }
        selectedProductForCard.value = null
        productCardNote.value = ''
        productCardSearch.value = ''
        productCardPage.value = 1
        showProductCardDialog.value = true
    }

    async function loadProductCards() {
        productCardLoading.value = true
        try {
            const params = { page: productCardPage.value, per_page: 20 }
            if (productCardSearch.value.trim()) params.search = productCardSearch.value.trim()
            const res = await apiClient.get('/products', { params })
            const data = res.data?.data || {}
            const items = Array.isArray(data) ? data : (data.data || [])
            productCardList.value = productCardPage.value === 1 ? items : [...productCardList.value, ...items]
            productCardTotal.value = res.data?.meta?.total || data.total || items.length
        } catch {
            if (productCardPage.value === 1) productCardList.value = []
        } finally {
            productCardLoading.value = false
        }
    }

    function searchProductCards() {
        productCardPage.value = 1
        loadProductCards()
    }

    function loadMoreProductCards() {
        productCardPage.value += 1
        loadProductCards()
    }

    async function sendProductCardMessage() {
        if (!activeConv.value?.id || !selectedProductForCard.value) return
        sendingProductCard.value = true
        const trace_id = crypto.randomUUID?.() || Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10)
        try {
            await apiClient.post('/user-chat/conversations/' + activeConv.value.id + '/send-product-card', {
                product_id: selectedProductForCard.value.id,
                content: productCardNote.value.trim() || undefined,
                trace_id,
            })
            showProductCardDialog.value = false
            await loadMessages()
            ElMessage.success('商品卡片已发送')
        } catch (e) {
            ElMessage.error(e.response?.data?.message || '发送失败')
        } finally {
            sendingProductCard.value = false
        }
    }

    return {
        showProductCardDialog,
        productCardSearch,
        productCardList,
        productCardLoading,
        productCardTotal,
        productCardPage,
        selectedProductForCard,
        productCardNote,
        sendingProductCard,
        formatProductPrice,
        openProductCardDialog,
        loadProductCards,
        searchProductCards,
        loadMoreProductCards,
        sendProductCardMessage,
    }
}
