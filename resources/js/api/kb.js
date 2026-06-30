import apiClient from './client';

const kbApi = {
    // Public
    categories(params) {
        return apiClient.get('/kb/categories', { params });
    },
    search(params) {
        return apiClient.get('/kb/search', { params });
    },
    getArticle(id) {
        return apiClient.get(`/kb/articles/${id}`);
    },
    submitFeedback(id, data) {
        return apiClient.post(`/kb/articles/${id}/feedback`, data);
    },
    // Admin
    adminArticles(params) {
        return apiClient.get('/kb/articles', { params });
    },
    createArticle(data) {
        return apiClient.post('/kb/articles', data);
    },
    updateArticle(id, data) {
        return apiClient.put(`/kb/articles/${id}`, data);
    },
    publishArticle(id) {
        return apiClient.post(`/kb/articles/${id}/publish`);
    },
    archiveArticle(id) {
        return apiClient.post(`/kb/articles/${id}/archive`);
    },
    deleteArticle(id) {
        return apiClient.delete(`/kb/articles/${id}`);
    },
    getVersions(id) {
        return apiClient.get(`/kb/articles/${id}/versions`);
    },
    createCategory(data) {
        return apiClient.post('/kb/categories', data);
    },
    updateCategory(id, data) {
        return apiClient.put(`/kb/categories/${id}`, data);
    },
    deleteCategory(id) {
        return apiClient.delete(`/kb/categories/${id}`);
    },
    // ─── 批量操作 ───
    batchDelete(ids) {
        return apiClient.post('/kb/batch/delete', { ids });
    },
    batchPublish(ids) {
        return apiClient.post('/kb/batch/publish', { ids });
    },
    batchArchive(ids) {
        return apiClient.post('/kb/batch/archive', { ids });
    },
    // ─── 导出 ───
    exportMarkdown() {
        return apiClient.get('/kb/export/markdown', { responseType: 'blob' });
    },
};

export default kbApi;
