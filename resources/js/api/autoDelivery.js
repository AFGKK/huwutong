import request from '@/utils/request'

/**
 * 自动发货引擎 API (M2-142 🛒)
 * 多渠道送达系统 (M2-147 🛒)
 */

export function getDeliveryDashboard() {
  return request.get('/auto-delivery/dashboard')
}

export function getDeliveryStats() {
  return request.get('/auto-delivery/stats')
}

export function getDeliveries(params) {
  return request.get('/auto-delivery/', { params })
}

export function getDeliveryDetail(id) {
  return request.get(`/auto-delivery/${id}`)
}

export function executeDelivery(orderId) {
  return request.post(`/auto-delivery/${orderId}/execute`)
}

export function retryDelivery(deliveryId) {
  return request.post(`/auto-delivery/${deliveryId}/retry`)
}

export function resendDelivery(deliveryId, channel) {
  return request.post(`/auto-delivery/${deliveryId}/resend`, { channel })
}

export function batchRetryDeliveries(deliveryIds) {
  return request.post('/auto-delivery/batch-retry', { delivery_ids: deliveryIds })
}
