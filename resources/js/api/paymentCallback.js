import request from '@/utils/request'

/**
 * 支付回调管理 API (M2-144 🛒)
 */

export function getCallbackStats() {
  return request.get('/payment-callbacks/stats')
}

export function getCallbacks(params) {
  return request.get('/payment-callbacks/', { params })
}

export function retryCallback(id) {
  return request.post(`/payment-callbacks/${id}/retry`)
}

export function batchRetryCallbacks(ids) {
  return request.post('/payment-callbacks/batch-retry', { ids })
}

export function simulateCallback(data) {
  return request.post('/payment-callbacks/simulate', data)
}
