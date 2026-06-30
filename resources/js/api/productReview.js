import apiClient from '@/api/client'

export function getProductReviews(productId, params = {}) {
    return apiClient.get(`/products/${productId}/reviews`, { params })
}

export function getProductReviewStats(productId) {
    return apiClient.get(`/products/${productId}/reviews/stats`)
}

export function createReview(data) {
    return apiClient.post('/product-reviews', data)
}

export function getAdminReviewList(params = {}) {
    return apiClient.get('/reviews', { params })
}

export function getAdminReviewDetail(id) {
    return apiClient.get(`/reviews/${id}`)
}

export function moderateReview(id, data) {
    return apiClient.post(`/reviews/${id}/moderate`, data)
}

export function replyToReview(id, data) {
    return apiClient.post(`/reviews/${id}/reply`, data)
}

export function deleteReview(id) {
    return apiClient.delete(`/reviews/${id}`)
}

export function getReviewStats() {
    return apiClient.get('/reviews/stats')
}
