import client from './client';

export default {
    // 管理端
    list(params = {}) { return client.get('/feedback', { params }); },
    show(id) { return client.get(`/feedback/${id}`); },
    update(id, data) { return client.put(`/feedback/${id}`, data); },
    assign(id, userId) { return client.post(`/feedback/${id}/assign`, { user_id: userId }); },
    reply(id, message) { return client.post(`/feedback/${id}/reply`, { message }); },
    resolve(id, status) { return client.post(`/feedback/${id}/resolve`, { status }); },
    destroy(id) { return client.delete(`/feedback/${id}`); },
    stats() { return client.get('/feedback/stats'); },
    voteStats() { return client.get('/feedback/vote-stats'); },
    tags() { return client.get('/feedback/tags'); },
    createTag(data) { return client.post('/feedback/tags', data); },
    vote(id, vote) { return client.post(`/feedback/${id}/vote`, { vote }); },

    // 门户端
    myFeedback(params = {}) { return client.get('/portal/feedback', { params }); },
    myShow(id) { return client.get(`/portal/feedback/${id}`); },
    create(data) { return client.post('/portal/feedback', data); },
};
