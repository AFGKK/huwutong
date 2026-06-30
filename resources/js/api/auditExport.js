import apiClient from './client'

// ─── 看板 ───
export function getAuditExportDashboard() {
  return apiClient.get('/audit-export/dashboard')
}

// ─── 导出任务 ───
export function getExportTasks(params = {}) {
  return apiClient.get('/audit-export/tasks', { params })
}

export function createExportTask(data) {
  return apiClient.post('/audit-export/tasks', data)
}

export function getExportTask(id) {
  return apiClient.get(`/audit-export/tasks/${id}`)
}

export function deleteExportTask(id) {
  return apiClient.delete(`/audit-export/tasks/${id}`)
}

export function downloadExportFileUrl(id) {
  return `/audit-export/tasks/${id}/download`
}

// ─── 流式导出 ───
export function streamExport(params) {
  return apiClient.post('/audit-export/stream', params, { responseType: 'blob' })
}

// ─── 定时导出计划 ───
export function getSchedules(params = {}) {
  return apiClient.get('/audit-export/schedules', { params })
}

export function createSchedule(data) {
  return apiClient.post('/audit-export/schedules', data)
}

export function updateSchedule(id, data) {
  return apiClient.put(`/audit-export/schedules/${id}`, data)
}

export function deleteSchedule(id) {
  return apiClient.delete(`/audit-export/schedules/${id}`)
}

export function toggleSchedule(id) {
  return apiClient.post(`/audit-export/schedules/${id}/toggle`)
}

// ─── 归档策略 ───
export function getArchivePolicies() {
  return apiClient.get('/audit-export/archive-policies')
}

export function upsertArchivePolicy(data) {
  return apiClient.post('/audit-export/archive-policies', data)
}

export function updateArchivePolicy(id, data) {
  return apiClient.put(`/audit-export/archive-policies/${id}`, data)
}

export function deleteArchivePolicy(id) {
  return apiClient.delete(`/audit-export/archive-policies/${id}`)
}

// ─── 归档记录 ───
export function getArchiveRecords(params = {}) {
  return apiClient.get('/audit-export/archive-records', { params })
}
