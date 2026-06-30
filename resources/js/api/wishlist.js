import apiClient from '@/api/client'

export function getMyWishlists() {
    return apiClient.get('/wishlist/my')
}

export function getMyStats() {
    return apiClient.get('/wishlist/my/stats')
}

export function getMyWishlistedProductIds() {
    return apiClient.get('/wishlist/my/product-ids')
}

export function checkWishlisted(productId) {
    return apiClient.get(`/wishlist/check/${productId}`)
}

export function toggleWishlist(data) {
    return apiClient.post('/wishlist/toggle', data)
}

export function addToWishlist(data) {
    return apiClient.post('/wishlist/add', data)
}

export function updateWishlistItem(id, data) {
    return apiClient.put(`/wishlist/items/${id}`, data)
}

export function removeWishlistItem(id) {
    return apiClient.delete(`/wishlist/items/${id}`)
}

export function batchRemoveWishlist(data) {
    return apiClient.post('/wishlist/batch-remove', data)
}

export function moveWishlistItem(id, data) {
    return apiClient.post(`/wishlist/items/${id}/move`, data)
}

export function createWishlistGroup(data) {
    return apiClient.post('/wishlist/groups', data)
}

export function updateWishlistGroup(id, data) {
    return apiClient.put(`/wishlist/groups/${id}`, data)
}

export function deleteWishlistGroup(id) {
    return apiClient.delete(`/wishlist/groups/${id}`)
}

export function createWishlistShare(data) {
    return apiClient.post('/wishlist/shares', data)
}

export function deleteWishlistShare(id) {
    return apiClient.delete(`/wishlist/shares/${id}`)
}

export function getGlobalWishlistStats() {
    return apiClient.get('/admin/wishlist/global-stats')
}
