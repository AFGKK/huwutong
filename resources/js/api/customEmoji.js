import apiClient from './client';

export function getCustomEmojis(params = {}) {
    return apiClient.get('/admin/emoji', { params });
}

export function getCustomEmojiStats() {
    return apiClient.get('/admin/emoji/stats');
}

export function getCustomEmojiCategories() {
    return apiClient.get('/admin/emoji/categories');
}

export function createCustomEmoji(data) {
    return apiClient.post('/admin/emoji', data);
}

export function updateCustomEmoji(id, data) {
    return apiClient.put(`/admin/emoji/${id}`, data);
}

export function deleteCustomEmoji(id) {
    return apiClient.delete(`/admin/emoji/${id}`);
}

export function importCustomEmojis(data) {
    return apiClient.post('/admin/emoji/import', data);
}
