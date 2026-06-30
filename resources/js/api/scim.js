import request from '@/utils/request'

/**
 * SCIM 自动用户同步 API (M2-51)
 */

export function getScimDashboard() {
  return request.get('/scim/dashboard')
}

export function getScimConfigs() {
  return request.get('/scim/configs')
}

export function createScimConfig(data) {
  return request.post('/scim/configs', data)
}

export function updateScimConfig(id, data) {
  return request.put(`/scim/configs/${id}`, data)
}

export function deleteScimConfig(id) {
  return request.delete(`/scim/configs/${id}`)
}

export function testScimConnection(id) {
  return request.post(`/scim/configs/${id}/test`)
}

export function syncScimNow(id) {
  return request.post(`/scim/configs/${id}/sync`)
}

export function getScimSyncLogs(id, params) {
  return request.get(`/scim/configs/${id}/logs`, { params })
}

export function getScimProviderOptions(provider) {
  return request.get(`/scim/provider-options/${provider}`)
}

export function getScimDefaultMapping() {
  return request.get('/scim/default-mapping')
}
