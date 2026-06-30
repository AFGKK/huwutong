import request from '@/utils/request'

/**
 * 定时上下架/促销管理 API (M2-151 🛒)
 */

export function getScheduledPromotions(params) {
  return request.get('/scheduled-promotions', { params })
}

export function getScheduledPromotionDetail(id) {
  return request.get(`/scheduled-promotions/${id}`)
}

export function createScheduledPromotion(data) {
  return request.post('/scheduled-promotions', data)
}

export function updateScheduledPromotion(id, data) {
  return request.put(`/scheduled-promotions/${id}`, data)
}

export function publishScheduledPromotion(id) {
  return request.post(`/scheduled-promotions/${id}/publish`)
}

export function pauseScheduledPromotion(id) {
  return request.post(`/scheduled-promotions/${id}/pause`)
}

export function deleteScheduledPromotion(id) {
  return request.delete(`/scheduled-promotions/${id}`)
}

export function getPromotionStats() {
  return request.get('/scheduled-promotions/stats')
}

export function getPromotionCalendar(month) {
  return request.get('/scheduled-promotions/calendar', { params: { month } })
}

export function getVisiblePromotions() {
  return request.get('/scheduled-promotions/visible')
}

export function checkPromotionEligibility(promotionId) {
  return request.post(`/scheduled-promotions/${promotionId}/check-eligibility`)
}
