import apiClient from '@/api/client'

// ─── 邀请码 ───
export function getInviteCodes(params = {}) {
  return apiClient.get('/invite-codes', { params })
}

export function generateInviteCodes(count, options = {}) {
  return apiClient.post('/invite-codes/generate', {
    count,
    max_uses: options.max_uses || 1,
    expires_at: options.expires_at || null,
    remarks: options.remarks || '',
    channel_id: options.channel_id || null,
  })
}

export function getInviteCodeStats() {
  return apiClient.get('/invite-codes/stats')
}

export function disableInviteCode(id) {
  return apiClient.delete(`/invite-codes/${id}`)
}

// ─── 渠道 ───
export function getChannels(params = {}) {
  return apiClient.get('/invite-channels', { params })
}

export function getChannel(id) {
  return apiClient.get(`/invite-channels/${id}`)
}

export function createChannel(data) {
  return apiClient.post('/invite-channels', data)
}

export function updateChannel(id, data) {
  return apiClient.put(`/invite-channels/${id}`, data)
}

export function deleteChannel(id) {
  return apiClient.delete(`/invite-channels/${id}`)
}

export function getChannelDashboard(channelId) {
  return apiClient.get(`/invite-channels/${channelId}/dashboard`)
}

// ─── 注册追踪 ───
export function getRegistrationTrackings(params = {}) {
  return apiClient.get('/registration-tracking', { params })
}

// ─── 自助门户配置 ───
export function getPortalConfig() {
  return apiClient.get('/registration-portal/config')
}

export function updatePortalConfig(data) {
  return apiClient.put('/registration-portal/config', data)
}

// ─── 验证邀请码 ───
export function validateInviteCode(code) {
  return apiClient.post('/invite-code/validate', { code })
}

// ─── 总看板 ───
export function getOverallDashboard() {
  return apiClient.get('/invite-overview/dashboard')
}
