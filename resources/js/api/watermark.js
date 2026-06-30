import request from '@/utils/request'

export function getWatermarkDashboard() {
  return request.get('/watermark/dashboard')
}

export function getWatermarks(params) {
  return request.get('/watermark/watermarks', { params })
}

export function getWatermark(id) {
  return request.get(`/watermark/watermarks/${id}`)
}

export function embedWatermark(data) {
  return request.post('/watermark/embed', data)
}

export function extractWatermark(watermarkId) {
  return request.get(`/watermark/watermarks/${watermarkId}`)
}

export function traceWatermark(watermarkId) {
  return request.get(`/watermark/watermarks/${watermarkId}/trace`)
}

export function revokeWatermark(watermarkId) {
  return request.post(`/watermark/watermarks/${watermarkId}/revoke`)
}

export function searchWatermarks(keyword, limit = 20) {
  return request.get('/watermark/search', { params: { keyword, limit } })
}

export function getTraces(params) {
  return request.get('/watermark/traces', { params })
}

export function createTrace(data) {
  return request.post('/watermark/traces', data)
}

export function getTamperEvents(params) {
  return request.get('/watermark/tamper-events', { params })
}

export function resolveTamperEvent(eventId, resolution) {
  return request.post(`/watermark/tamper-events/${eventId}/resolve`, { resolution })
}

export function getPolicies() {
  return request.get('/watermark/policies')
}

export function updatePolicy(policyId, data) {
  return request.put(`/watermark/policies/${policyId}`, data)
}

export function getVerificationStats(params) {
  return request.get('/watermark/verification-stats', { params })
}

export default {
  getWatermarkDashboard,
  getWatermarks,
  getWatermark,
  embedWatermark,
  extractWatermark,
  traceWatermark,
  revokeWatermark,
  searchWatermarks,
  getTraces,
  createTrace,
  getTamperEvents,
  resolveTamperEvent,
  getPolicies,
  updatePolicy,
  getVerificationStats,
}
