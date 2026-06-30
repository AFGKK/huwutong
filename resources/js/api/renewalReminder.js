import apiClient from './client'

export default {
    // 模板管理
    getTemplates(params) {
        return apiClient.get('/admin/renewal-reminder/templates', { params })
    },
    createTemplate(data) {
        return apiClient.post('/admin/renewal-reminder/templates', data)
    },
    updateTemplate(id, data) {
        return apiClient.put(`/admin/renewal-reminder/templates/${id}`, data)
    },
    deleteTemplate(id) {
        return apiClient.delete(`/admin/renewal-reminder/templates/${id}`)
    },
    // 提醒发送
    processDue() {
        return apiClient.post('/admin/renewal-reminder/process-due')
    },
    // 发送记录
    getReminderLogs(params) {
        return apiClient.get('/admin/renewal-reminder/logs', { params })
    },
    // 分析优化
    getConversionAnalytics() {
        return apiClient.get('/admin/renewal-reminder/conversion-analytics')
    },
    getOptimizationSuggestions() {
        return apiClient.get('/admin/renewal-reminder/optimization-suggestions')
    },
}
