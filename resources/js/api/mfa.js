import apiClient from './client';

export default {
    list(params) {
        return apiClient.get('/mfa/devices', { params });
    },
    setup() {
        return apiClient.get('/mfa/setup');
    },
    confirm(data) {
        return apiClient.post('/mfa/confirm', data);
    },
    verify(data) {
        return apiClient.post('/mfa/verify', data);
    },
    renameDevice(id, name) {
        return apiClient.put(`/mfa/devices/${id}/rename`, { name });
    },
    deleteDevice(id) {
        return apiClient.delete(`/mfa/devices/${id}`);
    },
    recoveryStatus() {
        return apiClient.get('/mfa/recovery-codes');
    },
    regenerateCodes() {
        return apiClient.post('/mfa/recovery-codes/regenerate');
    },
    disable(data) {
        return apiClient.post('/mfa/disable', data);
    },
};
