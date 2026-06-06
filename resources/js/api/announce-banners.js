import apiClient from '@/api/client';

export function getActiveBanners() {
    return apiClient.get('/announce-banners/active');
}

export function getAnnounceBanners() {
    return apiClient.get('/announce-banners');
}

export function getAnnounceBanner(id) {
    return apiClient.get(`/announce-banners/${id}`);
}

export function createAnnounceBanner(data) {
    return apiClient.post('/announce-banners', data);
}

export function updateAnnounceBanner(id, data) {
    return apiClient.put(`/announce-banners/${id}`, data);
}

export function deleteAnnounceBanner(id) {
    return apiClient.delete(`/announce-banners/${id}`);
}
