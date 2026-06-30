import apiClient from '@/api/client'

export function getMcpDashboard() {
    return apiClient.get('/admin/multi-currency-pricing/dashboard')
}

export function getEnabledSkus(params = {}) {
    return apiClient.get('/admin/multi-currency-pricing/skus', { params })
}

export function getSkuPrices(skuId) {
    return apiClient.get(`/admin/multi-currency-pricing/skus/${skuId}/prices`)
}

export function updateSkuPrices(skuId, prices) {
    return apiClient.put(`/admin/multi-currency-pricing/skus/${skuId}/prices`, { prices })
}

export function batchUpdatePrices(skus) {
    return apiClient.post('/admin/multi-currency-pricing/batch-update', { skus })
}

export function disableMultiCurrency(skuId) {
    return apiClient.post(`/admin/multi-currency-pricing/skus/${skuId}/disable`)
}

// 公开前端接口
export function getProductCurrencyPrices(productId, currency = null) {
    const params = currency ? { currency } : {}
    return apiClient.get(`/products/${productId}/currency-prices`, { params })
}

export function getSkuDisplayPrice(skuId, currency = null) {
    const params = currency ? { currency } : {}
    return apiClient.get(`/skus/${skuId}/display-price`, { params })
}
