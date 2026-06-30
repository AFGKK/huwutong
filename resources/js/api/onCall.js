import apiClient from './client';

const onCallApi = {
    dashboard() { return apiClient.get('/on-call/dashboard'); },
    list() { return apiClient.get('/on-call'); },
    get(id) { return apiClient.get(`/on-call/${id}`); },
    create(data) { return apiClient.post('/on-call', data); },
    update(id, data) { return apiClient.put(`/on-call/${id}`, data); },
    remove(id) { return apiClient.delete(`/on-call/${id}`); },
    generate(id, days = 90) { return apiClient.post(`/on-call/${id}/generate`, { days }); },
    addMember(scheduleId, userId, sortOrder = 0) {
        return apiClient.post(`/on-call/${scheduleId}/members`, { user_id: userId, sort_order: sortOrder });
    },
    removeMember(scheduleId, memberId) {
        return apiClient.delete(`/on-call/${scheduleId}/members/${memberId}`);
    },
    createOverride(data) { return apiClient.post('/on-call/overrides', data); },
    logs(params = {}) { return apiClient.get('/on-call/logs', { params }); },
};

export default onCallApi;
