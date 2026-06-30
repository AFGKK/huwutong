import apiClient from './client'

// ─── 概览 ───
export function getSlaDashboard() { return apiClient.get('/sla/dashboard') }

// ─── 元数据 ───
export function getMetricKeys() { return apiClient.get('/sla/metric-keys') }
export function getLevels() { return apiClient.get('/sla/levels') }

// ─── 合约 CRUD ───
export function getContracts(params) { return apiClient.get('/sla/contracts', { params }) }
export function getContract(id) { return apiClient.get(`/sla/contracts/${id}`) }
export function createContract(data) { return apiClient.post('/sla/contracts', data) }
export function updateContract(id, data) { return apiClient.put(`/sla/contracts/${id}`, data) }
export function deleteContract(id) { return apiClient.delete(`/sla/contracts/${id}`) }
export function createContractFromTemplate(templateId, data) { return apiClient.post(`/sla/contracts/from-template/${templateId}`, data) }

// ─── 指标 ───
export function createMetric(contractId, data) { return apiClient.post(`/sla/contracts/${contractId}/metrics`, data) }
export function updateMetric(id, data) { return apiClient.put(`/sla/metrics/${id}`, data) }
export function deleteMetric(id) { return apiClient.delete(`/sla/metrics/${id}`) }

// ─── 达标计算 ───
export function calculateCompliance(contractId, metricId, params) {
  return apiClient.post(`/sla/contracts/${contractId}/metrics/${metricId}/calculate`, params)
}

// ─── 报表 ───
export function getComplianceReport(contractId, params) {
  return apiClient.get(`/sla/contracts/${contractId}/compliance-report`, { params })
}

// ─── 违约 ───
export function getBreaches(params) { return apiClient.get('/sla/breaches', { params }) }
export function acknowledgeBreach(id) { return apiClient.post(`/sla/breaches/${id}/acknowledge`) }
export function resolveBreach(id, notes) { return apiClient.post(`/sla/breaches/${id}/resolve`, { notes }) }

// ─── 违约补偿 ───
export function getCompensations(params) { return apiClient.get('/sla/compensations', { params }) }
export function getCompensationStats() { return apiClient.get('/sla/compensations/stats') }
export function autoGenerateCompensations() { return apiClient.post('/sla/compensations/auto-generate') }
export function approveCompensation(id) { return apiClient.post(`/sla/compensations/${id}/approve`) }
export function issueCompensation(id) { return apiClient.post(`/sla/compensations/${id}/issue`) }
export function rejectCompensation(id, reason) { return apiClient.post(`/sla/compensations/${id}/reject`, { reason }) }
