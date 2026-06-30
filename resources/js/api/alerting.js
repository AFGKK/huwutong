import apiClient from './client'

// 概览
export function getAlertDashboard() { return apiClient.get('/admin/alerting/dashboard') }

// 规则
export function getRules(params) { return apiClient.get('/admin/alerting/rules', { params }) }
export function getRule(id) { return apiClient.get(`/admin/alerting/rules/${id}`) }
export function createRule(data) { return apiClient.post('/admin/alerting/rules', data) }
export function updateRule(id, data) { return apiClient.put(`/admin/alerting/rules/${id}`, data) }
export function deleteRule(id) { return apiClient.delete(`/admin/alerting/rules/${id}`) }

// 通知渠道
export function getChannels() { return apiClient.get('/admin/alerting/channels') }
export function createChannel(data) { return apiClient.post('/admin/alerting/channels', data) }
export function updateChannel(id, data) { return apiClient.put(`/admin/alerting/channels/${id}`, data) }
export function deleteChannel(id) { return apiClient.delete(`/admin/alerting/channels/${id}`) }
export function testChannel(id) { return apiClient.post(`/admin/alerting/channels/${id}/test`) }

// 升级策略
export function getEscalations(params) { return apiClient.get('/admin/alerting/escalations', { params }) }
export function createEscalation(data) { return apiClient.post('/admin/alerting/escalations', data) }
export function updateEscalation(id, data) { return apiClient.put(`/admin/alerting/escalations/${id}`, data) }
export function deleteEscalation(id) { return apiClient.delete(`/admin/alerting/escalations/${id}`) }

// 告警事件
export function getEvents(params) { return apiClient.get('/admin/alerting/events', { params }) }
export function getEvent(id) { return apiClient.get(`/admin/alerting/events/${id}`) }
export function acknowledgeEvent(id) { return apiClient.post(`/admin/alerting/events/${id}/acknowledge`) }
export function resolveEvent(id) { return apiClient.post(`/admin/alerting/events/${id}/resolve`) }

// 统计
export function getEventStats(params) { return apiClient.get('/admin/alerting/event-stats', { params }) }

// 元数据
export function getMetricTypes() { return apiClient.get('/admin/alerting/metric-types') }
export function getSeverities() { return apiClient.get('/admin/alerting/severities') }
