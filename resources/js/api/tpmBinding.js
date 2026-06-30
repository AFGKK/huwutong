import apiClient from './client';

const tpmBindingApi = {
    dashboard() { return apiClient.get('/admin/tpm-binding/dashboard'); },
    listBindings(params) { return apiClient.get('/admin/tpm-binding/bindings', { params }); },
    showBinding(id) { return apiClient.get(`/admin/tpm-binding/bindings/${id}`); },
    registerBinding(data) { return apiClient.post('/admin/tpm-binding/bindings', data); },
    verifyBinding(id, data) { return apiClient.post(`/admin/tpm-binding/bindings/${id}/verify`, data); },
    revokeBinding(id, reason) { return apiClient.post(`/admin/tpm-binding/bindings/${id}/revoke`, { reason }); },
    unlockBinding(id) { return apiClient.post(`/admin/tpm-binding/bindings/${id}/unlock`); },
    verificationHistory(id) { return apiClient.get(`/admin/tpm-binding/bindings/${id}/verification-history`); },
    checkLicense(licenseId) { return apiClient.get(`/admin/tpm-binding/check-license/${licenseId}`); },
    verificationStats(params) { return apiClient.get('/admin/tpm-binding/verification-stats', { params }); },
    tpmDevices(params) { return apiClient.get('/admin/tpm-binding/tpm-devices', { params }); },
    pruneLogs(params) { return apiClient.post('/admin/tpm-binding/prune-logs', params); },
};

export default tpmBindingApi;
