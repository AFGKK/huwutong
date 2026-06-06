import apiClient from '@/api/client'

export function getInviteCodes(params = {}) {
  return apiClient.get('/invite-codes', { params })
}

export function generateInviteCodes(count, options = {}) {
  return apiClient.post('/invite-codes/generate', {
    count,
    max_uses: options.max_uses || 1,
    expires_at: options.expires_at || null,
    remarks: options.remarks || '',
  })
}

export function getInviteCodeStats() {
  return apiClient.get('/invite-codes/stats')
}
