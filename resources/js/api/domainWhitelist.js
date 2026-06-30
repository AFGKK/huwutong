import apiClient from './client'

export function getDomainWhitelist(licenseId) {
  return apiClient.get(`/admin/licenses/${licenseId}/domain-whitelist`)
}

export function addDomain(licenseId, data) {
  return apiClient.post(`/admin/licenses/${licenseId}/domain-whitelist`, data)
}

export function batchAddDomains(licenseId, data) {
  return apiClient.post(`/admin/licenses/${licenseId}/domain-whitelist/batch`, data)
}

export function removeDomain(licenseId, id) {
  return apiClient.delete(`/admin/licenses/${licenseId}/domain-whitelist/${id}`)
}

export function verifyDomain(data) {
  return apiClient.post('/admin/domain-whitelist/verify', data)
}

export function getDomainLogs(licenseId) {
  return apiClient.get(`/admin/licenses/${licenseId}/domain-whitelist/logs`)
}

export function getDomainStats(licenseId) {
  return apiClient.get(`/admin/licenses/${licenseId}/domain-whitelist/stats`)
}

export function getPendingDomainApprovals() {
  return apiClient.get('/admin/domain-whitelist/approvals/pending')
}

export function approveDomain(id) {
  return apiClient.post(`/admin/domain-whitelist/approvals/${id}/approve`)
}

export function rejectDomain(id) {
  return apiClient.post(`/admin/domain-whitelist/approvals/${id}/reject`)
}
