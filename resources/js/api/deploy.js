import apiClient from './client'

export default {
    // 仪表盘
    getDashboard() {
        return apiClient.get('/admin/deploy/dashboard')
    },
    // 环境
    getEnvironments() {
        return apiClient.get('/admin/deploy/environments')
    },
    createEnvironment(data) {
        return apiClient.post('/admin/deploy/environments', data)
    },
    updateEnvironment(id, data) {
        return apiClient.put(`/admin/deploy/environments/${id}`, data)
    },
    deleteEnvironment(id) {
        return apiClient.delete(`/admin/deploy/environments/${id}`)
    },
    // 发布
    getReleases(params) {
        return apiClient.get('/admin/deploy/releases', { params })
    },
    createRelease(data) {
        return apiClient.post('/admin/deploy/releases', data)
    },
    updateRelease(id, data) {
        return apiClient.put(`/admin/deploy/releases/${id}`, data)
    },
    deleteRelease(id) {
        return apiClient.delete(`/admin/deploy/releases/${id}`)
    },
    // 部署作业
    getJobs(params) {
        return apiClient.get('/admin/deploy/jobs', { params })
    },
    getJobDetail(id) {
        return apiClient.get(`/admin/deploy/jobs/${id}`)
    },
    triggerDeploy(data) {
        return apiClient.post('/admin/deploy/trigger', data)
    },
    completeDeploy(id, data) {
        return apiClient.post(`/admin/deploy/jobs/${id}/complete`, data)
    },
    rollbackDeploy(id) {
        return apiClient.post(`/admin/deploy/jobs/${id}/rollback`)
    },
}
