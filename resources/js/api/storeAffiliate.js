import request from '@/utils/request'

/**
 * 商店分销/联盟推广 API (M2-149 🛒)
 */

export function getPromotableSkus() {
  return request.get('/store-affiliate/promotable-skus')
}

export function generateAffiliateLinks(skuIds, campaignId) {
  return request.post('/store-affiliate/generate-links', { sku_ids: skuIds, campaign_id: campaignId })
}

export function linkOrderToAffiliate(orderId, referralCode) {
  return request.post('/store-affiliate/link-order', { order_id: orderId, referral_code: referralCode })
}

export function settleOrderCommission(orderId) {
  return request.post(`/store-affiliate/${orderId}/settle-commission`)
}

export function getStoreAffiliateDashboard() {
  return request.get('/store-affiliate/dashboard')
}

export function getStoreAffiliateOrders(params) {
  return request.get('/store-affiliate/orders', { params })
}

export function getStoreAffiliateLinks(params) {
  return request.get('/store-affiliate/links', { params })
}
