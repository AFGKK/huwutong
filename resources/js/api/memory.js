import apiClient from './client';

const memoryApi = {
    /** 仪表盘总览 */
    dashboard() {
        return apiClient.get('/memory/dashboard');
    },
    /** 记忆列表 */
    list(params = {}) {
        return apiClient.get('/memory', { params });
    },
    /** 单条详情 */
    get(id) {
        return apiClient.get(`/memory/${id}`);
    },
    /** 手动创建 */
    create(data) {
        return apiClient.post('/memory', data);
    },
    /** 更新记忆 */
    update(id, data) {
        return apiClient.put(`/memory/${id}`, data);
    },
    /** 删除记忆 */
    remove(id) {
        return apiClient.delete(`/memory/${id}`);
    },
    /** 批量删除 */
    batchRemove(ids) {
        return apiClient.post('/memory/batch-delete', { ids });
    },
    /** 确认记忆（提升置信度） */
    confirm(id) {
        return apiClient.post(`/memory/${id}/confirm`);
    },
    /** 纠正记忆 */
    correct(id, content) {
        return apiClient.put(`/memory/${id}/correct`, { content });
    },
    /** AI 从文本提取记忆 */
    extract(text) {
        return apiClient.post('/memory/extract', { text });
    },
    /** 清空所有记忆 */
    clearAll() {
        return apiClient.delete('/memory/clear-all');
    },
    /** 获取筛选选项 */
    options() {
        return apiClient.get('/memory/options');
    },
};

export default memoryApi;
