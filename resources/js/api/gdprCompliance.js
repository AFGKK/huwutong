import apiClient from '@/api/client'

// ─── 数据主体请求 (DSR) 用户侧 ───

export function submitGdprRequest(data) {
  return apiClient.post('/gdpr/requests', data)
}

export function getMyRequests() {
  return apiClient.get('/gdpr/my-requests')
}

export function downloadGdprExport(requestId) {
  return apiClient.get(`/gdpr/requests/${requestId}/download`, { responseType: 'blob' })
}

// ─── 数据主体请求 (DSR) 管理侧 ───

export function getGdprRequests(params = {}) {
  return apiClient.get('/gdpr/requests', { params })
}

export function getGdprRequest(id) {
  return apiClient.get(`/gdpr/requests/${id}`)
}

export function processGdprRequest(id) {
  return apiClient.post(`/gdpr/requests/${id}/process`)
}

export function reviewGdprRequest(id, data) {
  return apiClient.post(`/gdpr/requests/${id}/review`, data)
}

export function getGdprStats() {
  return apiClient.get('/gdpr/stats')
}

// ─── DPA 管理 ───

export function getDpaList(params = {}) {
  return apiClient.get('/gdpr/dpa', { params })
}

export function createDpa(data) {
  return apiClient.post('/gdpr/dpa', data)
}

export function updateDpa(id, data) {
  return apiClient.put(`/gdpr/dpa/${id}`, data)
}

export function publishDpa(id) {
  return apiClient.post(`/gdpr/dpa/${id}/publish`)
}

export function signDpa(id) {
  return apiClient.post(`/gdpr/dpa/${id}/sign`)
}

export function getMyDpaStatus() {
  return apiClient.get('/gdpr/dpa/my-status')
}
