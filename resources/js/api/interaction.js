import apiClient from '@/api/client'

export function getInteractions(params = {}) {
    return apiClient.get('/user/interactions', { params })
}

export function addFavorite(type, id) {
    return apiClient.post('/user/interactions/favorites', { type, id })
}

export function removeFavorite(type, id) {
    return apiClient.delete('/user/interactions/favorites', { data: { type, id } })
}

export function addLike(type, id) {
    return apiClient.post('/user/interactions/likes', { type, id })
}

export function removeLike(type, id) {
    return apiClient.delete('/user/interactions/likes', { data: { type, id } })
}

export function getInteractionStatus(type, id) {
    return apiClient.get('/user/interactions/status', { params: { type, id } })
}

export function getReadingStats() {
    return apiClient.get('/user/interactions/reading-stats')
}

export function getFollowingFeed(params = {}) {
    return apiClient.get('/user/interactions/following-feed', { params })
}

export function getFavoriteCollections() {
    return apiClient.get('/user/interactions/favorites/collections')
}

export function getSecurityScore() {
    return apiClient.get('/user/interactions/security-score')
}

export function saveReadingGoal(dailyGoal) {
    return apiClient.post('/user/interactions/reading-goal', { daily_goal: dailyGoal })
}

export function exportData(format = 'markdown', type = 'all') {
    return apiClient.get('/user/interactions/export', {
        params: { format, type },
        responseType: 'blob',
    })
}

export function getPreferences() {
    return apiClient.get('/user/interactions/preferences')
}

export function savePreferences(data) {
    return apiClient.post('/user/interactions/preferences', data)
}

export function getHeatmap(year) {
    return apiClient.get('/user/interactions/heatmap', { params: { year: year || new Date().getFullYear() } })
}

export function getReadingReport(period = 'monthly') {
    return apiClient.get('/user/interactions/reading-report', { params: { period } })
}

export function getRecommendations() {
    return apiClient.get('/user/interactions/recommendations')
}

export function getReadingQueue(tab = 'pending') {
    return apiClient.get('/user/interactions/reading-queue', { params: { tab } })
}

export function addToReadingQueue(type, id, note = '') {
    return apiClient.post('/user/interactions/reading-queue', { type, id, note })
}

export function removeFromReadingQueue(id) {
    return apiClient.delete(`/user/interactions/reading-queue/${id}`)
}

export function toggleReadingQueueItem(id) {
    return apiClient.put(`/user/interactions/reading-queue/${id}/toggle`)
}

export function sortReadingQueue(items) {
    return apiClient.put('/user/interactions/reading-queue/sort', { items })
}
