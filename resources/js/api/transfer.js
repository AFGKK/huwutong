import client from './client';

export default {
    // ── 管理端 ──
    list(params = {}) { return client.get('/transfers', { params }); },
    show(id) { return client.get(`/transfers/${id}`); },
    create(data) { return client.post('/transfers', data); },
    approve(id, data = {}) { return client.post(`/transfers/${id}/approve`, data); },
    reject(id, data) { return client.post(`/transfers/${id}/reject`, data); },
    cancel(id) { return client.post(`/transfers/${id}/cancel`); },
    stats() { return client.get('/transfers/stats'); },

    // ── 客户门户 ──
    myRequests(params = {}) { return client.get('/portal/transfers', { params }); },
    myShow(id) { return client.get(`/portal/transfers/${id}`); },
    myCancel(id) { return client.post(`/portal/transfers/${id}/cancel`); },
    transferableLicenses() { return client.get('/portal/transfers/licenses'); },
};
