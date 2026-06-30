import apiClient from './client'

export default {
    // ─── 概览 ───
    dashboard() {
        return apiClient.get('/admin/contracts/dashboard').then(r => r.data)
    },
    trends(days = 7) {
        return apiClient.get('/admin/contracts/trends', { params: { days } }).then(r => r.data)
    },
    types() {
        return apiClient.get('/admin/contracts/types').then(r => r.data)
    },

    // ─── 合约管理 ───
    contracts(params) {
        return apiClient.get('/admin/contracts', { params }).then(r => r.data)
    },
    showContract(id) {
        return apiClient.get(`/admin/contracts/${id}`).then(r => r.data)
    },
    storeContract(data) {
        return apiClient.post('/admin/contracts', data).then(r => r.data)
    },
    updateContract(id, data) {
        return apiClient.put(`/admin/contracts/${id}`, data).then(r => r.data)
    },
    deleteContract(id) {
        return apiClient.delete(`/admin/contracts/${id}`).then(r => r.data)
    },
    seedContracts() {
        return apiClient.post('/admin/contracts/seed').then(r => r.data)
    },
    evaluateContract(id, context = {}) {
        return apiClient.post(`/admin/contracts/${id}/evaluate`, { context }).then(r => r.data)
    },

    // ─── 合约分配 ───
    assignments(contractId) {
        return apiClient.get(`/admin/contracts/${contractId}/assignments`).then(r => r.data)
    },
    storeAssignment(data) {
        return apiClient.post('/admin/contracts/assignments', data).then(r => r.data)
    },
    updateAssignment(id, data) {
        return apiClient.put(`/admin/contracts/assignments/${id}`, data).then(r => r.data)
    },
    deleteAssignment(id) {
        return apiClient.delete(`/admin/contracts/assignments/${id}`).then(r => r.data)
    },
    entityAssignments(entityType, entityId) {
        return apiClient.get('/admin/contracts/entity-assignments', { params: { assignable_type: entityType, assignable_id: entityId } }).then(r => r.data)
    },
    evaluateEntity(entityType, entityId, context = {}) {
        return apiClient.post('/admin/contracts/entity-evaluate', { assignable_type: entityType, assignable_id: entityId, context }).then(r => r.data)
    },

    // ─── 评估日志 ───
    evaluationLogs(params) {
        return apiClient.get('/admin/contracts/evaluation-logs', { params }).then(r => r.data)
    },
}
