import apiClient from '@/api/client';

export function getCookieConfig() {
    return apiClient.get('/cookie-consent/config');
}

export function submitConsent(action, selectedCategories = null) {
    return apiClient.post('/cookie-consent/consent', { action, selected_categories: selectedCategories });
}

export function getAdminConfig() {
    return apiClient.get('/cookie-consent/admin-config');
}

export function updateAdminConfig(data) {
    return apiClient.put('/cookie-consent/admin-config', data);
}

export function getCookieLogs(params = {}) {
    return apiClient.get('/cookie-consent/logs', { params });
}

export function getCookieStats() {
    return apiClient.get('/cookie-consent/stats');
}
