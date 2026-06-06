import apiClient from '@/api/client'

// 用户侧
export function requestDeletion(reason = '') {
  return apiClient.post('/account/deletion', { reason })
}

export function cancelDeletion() {
  return apiClient.post('/account/deletion/cancel')
}

export function getDeletionStatus() {
  return apiClient.get('/account/deletion/status')
}

// 管理侧
export function getPendingDeletions(params = {}) {
  return apiClient.get('/account/deletions/pending', { params })
}

export function getDeletionHistory(params = {}) {
  return apiClient.get('/account/deletions/history', { params })
}

export function approveDeletion(id, adminNotes = '') {
  return apiClient.post('/account/deletions/approve', { id, admin_notes: adminNotes })
}

export function rejectDeletion(id, adminNotes = '') {
  return apiClient.post('/account/deletions/reject', { id, admin_notes: adminNotes })
}

export function getDeletionStats() {
  return apiClient.get('/account/deletions/stats')
}
