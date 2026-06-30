import apiClient from './client'

export default {
    getDashboard() {
        return apiClient.get('/admin/churn-prediction/dashboard')
    },
    getTrend(params) {
        return apiClient.get('/admin/churn-prediction/trend', { params })
    },
    getChurnList(params) {
        return apiClient.get('/admin/churn-prediction/list', { params })
    },
    getInterventions(params) {
        return apiClient.get('/admin/churn-prediction/interventions', { params })
    },
    createIntervention(data) {
        return apiClient.post('/admin/churn-prediction/interventions', data)
    },
    updateIntervention(id, data) {
        return apiClient.put(`/admin/churn-prediction/interventions/${id}`, data)
    },
    deleteIntervention(id) {
        return apiClient.delete(`/admin/churn-prediction/interventions/${id}`)
    },
}
