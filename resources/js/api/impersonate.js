import apiClient from '@/api/client'

export function startImpersonate(userId, reason = '') {
  return apiClient.post('/impersonate/start', { user_id: userId, reason })
}

export function stopImpersonate(token) {
  return apiClient.post('/impersonate/stop', { token })
}

export function getImpersonateSession() {
  return apiClient.get('/impersonate/session')
}

export function getImpersonateHistory(params = {}) {
  return apiClient.get('/impersonate/history', { params })
}

export function getImpersonateCandidates(params = {}) {
  return apiClient.get('/impersonate/candidates', { params })
}
