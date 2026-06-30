import request from '@/utils/request'

const BASE = '/ecommerce/inventory'

export function snapshot() {
  return request.get(`${BASE}/snapshot`)
}

export function alerts(threshold = 10) {
  return request.get(`${BASE}/alerts`, { params: { threshold } })
}

export function logs(skuId) {
  return request.get(`${BASE}/logs/${skuId}`)
}

export function adjust(skuId, data) {
  return request.post(`${BASE}/${skuId}/adjust`, data)
}

export function initialize(skuId, data) {
  return request.post(`${BASE}/${skuId}/initialize`, data)
}

export default { snapshot, alerts, logs, adjust, initialize }
