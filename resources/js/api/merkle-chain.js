import apiClient from '@/api/client'

export function getMerkleStats() {
  return apiClient.get('/merkle/stats')
}

export function verifyMerkleChain(logId = null) {
  const params = {}
  if (logId) params.log_id = logId
  return apiClient.get('/merkle/verify', { params })
}

export function triggerMerkleAnchor(force = false, backfill = false) {
  return apiClient.post('/merkle/anchor', { force, backfill })
}

export function getMerkleAnchors() {
  return apiClient.get('/merkle/anchors')
}

export function backfillMerkleHashes() {
  return apiClient.post('/merkle/backfill')
}
