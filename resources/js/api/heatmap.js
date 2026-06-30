import apiClient from './client'

export default {
    // 仪表盘
    getDashboard() {
        return apiClient.get('/admin/heatmap/dashboard')
    },
    // 热力图数据（多层）
    getData(params) {
        return apiClient.get('/admin/heatmap/data', { params })
    },
    // 国家详情钻取
    getCountryDetail(countryCode, params) {
        return apiClient.get(`/admin/heatmap/country/${countryCode}`, { params })
    },
    // 图层管理
    getLayers() {
        return apiClient.get('/admin/heatmap/layers')
    },
    createLayer(data) {
        return apiClient.post('/admin/heatmap/layers', data)
    },
    updateLayer(id, data) {
        return apiClient.put(`/admin/heatmap/layers/${id}`, data)
    },
    deleteLayer(id) {
        return apiClient.delete(`/admin/heatmap/layers/${id}`)
    },
}
