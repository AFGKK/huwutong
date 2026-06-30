import apiClient from './client'

// 概览
export function getAuditDashboard() { return apiClient.get('/audit-visualization/dashboard') }

// 趋势
export function getAuditTrend(params) { return apiClient.get('/audit-visualization/trend', { params }) }

// Top 排名
export function getTopActions(params) { return apiClient.get('/audit-visualization/top-actions', { params }) }
export function getTopUsers(params) { return apiClient.get('/audit-visualization/top-users', { params }) }
export function getTopIps(params) { return apiClient.get('/audit-visualization/top-ips', { params }) }

// 分布
export function getHourlyDistribution(params) { return apiClient.get('/audit-visualization/hourly-distribution', { params }) }
export function getTypeDistribution(params) { return apiClient.get('/audit-visualization/type-distribution', { params }) }
export function getCategoryDistribution(params) { return apiClient.get('/audit-visualization/category-distribution', { params }) }

// 异常检测
export function detectAnomalies() { return apiClient.post('/audit-visualization/detect-anomalies') }
export function getAnomalies(params) { return apiClient.get('/audit-visualization/anomalies', { params }) }
export function updateAnomalyStatus(id, status) { return apiClient.put(`/audit-visualization/anomalies/${id}/status`, { status }) }

// 聚合
export function aggregateAuditData(params) { return apiClient.post('/audit-visualization/aggregate', params) }
