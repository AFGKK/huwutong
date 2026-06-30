import request from '@/utils/request'

const BASE = '/api/ecommerce/refunds'

export function stats() {
  return request.get(`${BASE}/stats`)
}

export function list(params = {}) {
  return request.get(`${BASE}`, { params })
}

export function requestRefund(data) {
  return request.post(`${BASE}/request`, data)
}

export function review(refundId, data) {
  return request.post(`${BASE}/${refundId}/review`, data)
}

export default { stats, list, requestRefund, review }
