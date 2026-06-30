import apiClient from '@/api/client'

export function getLocalizationLanguages() {
    return apiClient.get('/admin/localization/languages')
}

export function getLocalizationStats() {
    return apiClient.get('/admin/localization/stats')
}

export function getProductTranslations(productId) {
    return apiClient.get(`/admin/localization/products/${productId}/translations`)
}

export function saveProductTranslations(productId, data) {
    return apiClient.post(`/admin/localization/products/${productId}/translations`, data)
}

export function deleteProductTranslation(productId, data) {
    return apiClient.delete(`/admin/localization/products/${productId}/translations`, { data })
}

export function getPlanTranslations(planId) {
    return apiClient.get(`/admin/localization/plans/${planId}/translations`)
}

export function savePlanTranslations(planId, data) {
    return apiClient.post(`/admin/localization/plans/${planId}/translations`, data)
}

export function deletePlanTranslation(planId, data) {
    return apiClient.delete(`/admin/localization/plans/${planId}/translations`, { data })
}
