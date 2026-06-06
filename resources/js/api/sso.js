import apiClient from './client';

export default {
    providers() {
        return apiClient.get('/sso/providers');
    },
    configure(data) {
        return apiClient.post('/sso/providers', data);
    },
    toggle(id) {
        return apiClient.post(`/sso/providers/${id}/toggle`);
    },
    loginUrl(id) {
        return apiClient.get(`/sso/providers/${id}/login-url`);
    },
    connections() {
        return apiClient.get('/sso/connections');
    },
    disconnect(id) {
        return apiClient.delete(`/sso/connections/${id}`);
    },
};
