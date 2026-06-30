import client from './client';

export default {
    // ── DPIA ──
    listDpias(params = {}) { return client.get('/gdpr/enhancement/dpia', { params }); },
    showDpia(id) { return client.get(`/gdpr/enhancement/dpia/${id}`); },
    createDpia(data) { return client.post('/gdpr/enhancement/dpia', data); },
    updateDpia(id, data) { return client.put(`/gdpr/enhancement/dpia/${id}`, data); },
    reviewDpia(id, data) { return client.post(`/gdpr/enhancement/dpia/${id}/review`, data); },
    dpiaStats() { return client.get('/gdpr/enhancement/dpia/stats'); },

    // ── 数据泄露 ──
    listBreaches(params = {}) { return client.get('/gdpr/enhancement/breaches', { params }); },
    showBreach(id) { return client.get(`/gdpr/enhancement/breaches/${id}`); },
    createBreach(data) { return client.post('/gdpr/enhancement/breaches', data); },
    updateBreach(id, data) { return client.put(`/gdpr/enhancement/breaches/${id}`, data); },
    breachStats() { return client.get('/gdpr/enhancement/breaches/stats'); },

    // ── ROPA ──
    listRopas(params = {}) { return client.get('/gdpr/enhancement/ropa', { params }); },
    showRopa(id) { return client.get(`/gdpr/enhancement/ropa/${id}`); },
    createRopa(data) { return client.post('/gdpr/enhancement/ropa', data); },
    updateRopa(id, data) { return client.put(`/gdpr/enhancement/ropa/${id}`, data); },
    ropaStats() { return client.get('/gdpr/enhancement/ropa/stats'); },

    // ── 子处理商 ──
    listSubProcessors(params = {}) { return client.get('/gdpr/enhancement/sub-processors', { params }); },
    showSubProcessor(id) { return client.get(`/gdpr/enhancement/sub-processors/${id}`); },
    createSubProcessor(data) { return client.post('/gdpr/enhancement/sub-processors', data); },
    updateSubProcessor(id, data) { return client.put(`/gdpr/enhancement/sub-processors/${id}`, data); },

    // ── 自动决策 ──
    listAutoDecisions(params = {}) { return client.get('/gdpr/enhancement/auto-decisions', { params }); },
    showAutoDecision(id) { return client.get(`/gdpr/enhancement/auto-decisions/${id}`); },
    createAutoDecision(data) { return client.post('/gdpr/enhancement/auto-decisions', data); },
    updateAutoDecision(id, data) { return client.put(`/gdpr/enhancement/auto-decisions/${id}`, data); },

    // ── 全局统计 ──
    allStats() { return client.get('/gdpr/enhancement/all-stats'); },
};
