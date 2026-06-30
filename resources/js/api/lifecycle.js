import apiClient from './client'

export default {
    getDashboard() {
        return apiClient.get('/admin/lifecycle/dashboard')
    },
    getTransitions(params) {
        return apiClient.get('/admin/lifecycle/transitions', { params })
    },
    transitionCustomer(data) {
        return apiClient.post('/admin/lifecycle/transition', data)
    },
    autoEvaluate() {
        return apiClient.post('/admin/lifecycle/auto-evaluate')
    },
    getCustomerScore(customerId) {
        return apiClient.get(`/admin/lifecycle/customer/${customerId}/score`)
    },
    suggestStage(customerId) {
        return apiClient.get(`/admin/lifecycle/customer/${customerId}/suggest`)
    },
}
