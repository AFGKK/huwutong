import apiClient from './client'

// 监控概览
export function getWebhookMonitorOverview() { return apiClient.get('/admin/webhook-monitor/overview') }
// 端点详情
export function getWebhookMonitorEndpoint(id) { return apiClient.get(`/admin/webhook-monitor/endpoints/${id}`) }
// 失败事件
export function getWebhookMonitorFailures(params) { return apiClient.get('/admin/webhook-monitor/failures', { params }) }
// 延迟分布
export function getWebhookLatencyDistribution(params) { return apiClient.get('/admin/webhook-monitor/latency-distribution', { params }) }
// 聚合每日统计
export function aggregateWebhookDaily(date) { return apiClient.post('/admin/webhook-monitor/aggregate-daily', { date }) }
