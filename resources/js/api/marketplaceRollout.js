import client from './client';

export default {
    // ── 灰度发布 ──
    list(params = {}) { return client.get('/marketplace/rollouts', { params }); },
    show(id) { return client.get(`/marketplace/rollouts/${id}`); },
    create(data) { return client.post('/marketplace/rollouts', data); },
    update(id, data) { return client.put(`/marketplace/rollouts/${id}`, data); },
    start(id) { return client.post(`/marketplace/rollouts/${id}/start`); },
    pause(id) { return client.post(`/marketplace/rollouts/${id}/pause`); },
    complete(id) { return client.post(`/marketplace/rollouts/${id}/complete`); },
    rollback(id) { return client.post(`/marketplace/rollouts/${id}/rollback`); },
    stats(id) { return client.get(`/marketplace/rollouts/${id}/stats`); },
    availableApps() { return client.get('/marketplace/rollouts/available-apps'); },
    availableTenants(params = {}) { return client.get('/marketplace/rollouts/available-tenants', { params }); },
};
