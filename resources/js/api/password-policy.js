import apiClient from '@/api/client'

export function getPasswordPolicy() {
  return apiClient.get('/password-policy/config')
}

export function updatePasswordPolicy(data) {
  return apiClient.put('/password-policy/config', data)
}

export function getLockedAccounts(params = {}) {
  return apiClient.get('/password-policy/locked-accounts', { params })
}

export function unlockAccount(userId) {
  return apiClient.post('/password-policy/unlock', { user_id: userId })
}
