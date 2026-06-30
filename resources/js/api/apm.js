import apiClient from '@/api/client'

export function getApmOverview(period = 24) {
  return apiClient.get('/apm/overview', { params: { period } })
}

export function getSlowRequests() {
  return apiClient.get('/apm/slow-requests')
}

export function getSlowestRoutes(limit = 10) {
  return apiClient.get('/apm/slowest-routes', { params: { limit } })
}

export function getApmRecord(id) {
  return apiClient.get(`/apm/records/${id}`)
}

export function pruneApmData() {
  return apiClient.post('/apm/prune')
}

export function getApmOtelStatus() {
  return apiClient.get('/apm/otel-status')
}

export function getApmConfig() {
  return apiClient.get('/apm/config')
}

export function getApmDashboard(period = 24) {
  return apiClient.get('/apm/dashboard', { params: { period } })
}
