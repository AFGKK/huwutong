import request from '@/utils/request'

const BASE = '/api/ecommerce/security'

export function stats() {
  return request.get(`${BASE}/stats`)
}

export function logs(params = {}) {
  return request.get(`${BASE}/logs`, { params })
}

export function preCheck(data) {
  return request.post(`${BASE}/pre-check`, data)
}

export default { stats, logs, preCheck }
