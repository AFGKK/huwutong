import apiClient from './client'

// ─── 仪表盘 ───
export function getDashboards() { return apiClient.get('/admin/custom-dashboards') }
export function getDashboard(id) { return apiClient.get(`/admin/custom-dashboards/${id}`) }
export function getDashboardOverview() { return apiClient.get('/admin/custom-dashboards/overview') }
export function createDashboard(data) { return apiClient.post('/admin/custom-dashboards', data) }
export function updateDashboard(id, data) { return apiClient.put(`/admin/custom-dashboards/${id}`, data) }
export function deleteDashboard(id) { return apiClient.delete(`/admin/custom-dashboards/${id}`) }
export function setDefaultDashboard(id) { return apiClient.post(`/admin/custom-dashboards/${id}/set-default`) }
export function duplicateDashboard(id) { return apiClient.post(`/admin/custom-dashboards/${id}/duplicate`) }

// ─── Widget ───
export function createWidget(dashboardId, data) { return apiClient.post(`/admin/custom-dashboards/${dashboardId}/widgets`, data) }
export function updateWidget(widgetId, data) { return apiClient.put(`/admin/custom-dashboards/widgets/${widgetId}`, data) }
export function deleteWidget(widgetId) { return apiClient.delete(`/admin/custom-dashboards/widgets/${widgetId}`) }
export function reorderWidgets(dashboardId, order) { return apiClient.post(`/admin/custom-dashboards/${dashboardId}/widgets/reorder`, { order }) }
export function getWidgetData(widgetId) { return apiClient.get(`/admin/custom-dashboards/widgets/${widgetId}/data`) }
export function refreshWidgetData(widgetId) { return apiClient.post(`/admin/custom-dashboards/widgets/${widgetId}/refresh`) }

// ─── Widget 模板 ───
export function getWidgetTemplates(category) { return apiClient.get('/admin/custom-dashboards/widget-templates', { params: { category } }) }
