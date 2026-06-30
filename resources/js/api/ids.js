import apiClient from './client'

export default {
    // ─── 概览 ───
    dashboard() {
        return apiClient.get('/admin/ids/dashboard').then(r => r.data)
    },
    trends(days = 7) {
        return apiClient.get('/admin/ids/trends', { params: { days } }).then(r => r.data)
    },

    // ─── 规则管理 ───
    rules(params) {
        return apiClient.get('/admin/ids/rules', { params }).then(r => r.data)
    },
    showRule(id) {
        return apiClient.get(`/admin/ids/rules/${id}`).then(r => r.data)
    },
    storeRule(data) {
        return apiClient.post('/admin/ids/rules', data).then(r => r.data)
    },
    updateRule(id, data) {
        return apiClient.put(`/admin/ids/rules/${id}`, data).then(r => r.data)
    },
    deleteRule(id) {
        return apiClient.delete(`/admin/ids/rules/${id}`).then(r => r.data)
    },
    seedRules() {
        return apiClient.post('/admin/ids/rules/seed').then(r => r.data)
    },
    detectionTypes() {
        return apiClient.get('/admin/ids/rules/detection-types').then(r => r.data)
    },

    // ─── 告警管理 ───
    alerts(params) {
        return apiClient.get('/admin/ids/alerts', { params }).then(r => r.data)
    },
    showAlert(id) {
        return apiClient.get(`/admin/ids/alerts/${id}`).then(r => r.data)
    },
    updateAlertStatus(id, status, notes) {
        return apiClient.put(`/admin/ids/alerts/${id}/status`, { status, notes }).then(r => r.data)
    },
    alertStatuses() {
        return apiClient.get('/admin/ids/alerts/statuses').then(r => r.data)
    },
    clearAlerts(olderThan) {
        return apiClient.post('/admin/ids/alerts/clear', { older_than: olderThan }).then(r => r.data)
    },
}
