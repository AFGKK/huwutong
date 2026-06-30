import apiClient from './client';

const proactiveApi = {
    /** 获取洞察列表 */
    list(params = {}) {
        return apiClient.get('/insights', { params });
    },
    /** 洞察统计 */
    stats() {
        return apiClient.get('/insights/stats');
    },
    /** 洞察类型列表 */
    types() {
        return apiClient.get('/insights/types');
    },
    /** 标记为已读 */
    markRead(id) {
        return apiClient.post(`/insights/${id}/read`);
    },
    /** 忽略洞察 */
    dismiss(id) {
        return apiClient.post(`/insights/${id}/dismiss`);
    },
    /** 全部标记为已读 */
    markAllRead() {
        return apiClient.post('/insights/mark-all-read');
    },
};

export default proactiveApi;
