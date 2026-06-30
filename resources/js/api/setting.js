import apiClient from './client';

export default {
    // 站点设置
    grouped() {
        return apiClient.get('/settings');
    },
    all() {
        return apiClient.get('/settings/all');
    },
    update(settings) {
        return apiClient.post('/settings', { settings });
    },
    create(data) {
        return apiClient.post('/settings/create', data);
    },
    public() {
        return apiClient.get('/settings/public');
    },
    uploadImage(key, file) {
        const formData = new FormData();
        formData.append('image', file);
        formData.append('key', key);
        return apiClient.post('/settings/upload-image', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },

    // 页面管理
    pages(params) {
        return apiClient.get('/pages', { params });
    },
    pageShow(id) {
        return apiClient.get(`/pages/${id}`);
    },
    pageCreate(data) {
        return apiClient.post('/pages', data);
    },
    pageUpdate(id, data) {
        return apiClient.put(`/pages/${id}`, data);
    },
    pagePublish(id) {
        return apiClient.post(`/pages/${id}/publish`);
    },
    pageDraft(id) {
        return apiClient.post(`/pages/${id}/draft`);
    },
    pageDelete(id) {
        return apiClient.delete(`/pages/${id}`);
    },
};
