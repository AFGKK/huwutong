import request from '@/utils/request'

const BASE = '/shop'

export default {
  // ═══ 商品 ═══
  products(params = {}) {
    return request.get(`${BASE}/products`, { params })
  },
  productDetail(id) {
    return request.get(`${BASE}/products/${id}`)
  },
  skuDetail(id) {
    return request.get(`${BASE}/skus/${id}`)
  },

  // ═══ 购物车 ═══
  cart() {
    return request.get(`${BASE}/cart`)
  },
  cartAdd(data) {
    return request.post(`${BASE}/cart/add`, data)
  },
  cartUpdate(data) {
    return request.put(`${BASE}/cart/update`, data)
  },
  cartRemove(skuId) {
    return request.delete(`${BASE}/cart/remove`, { data: { sku_id: skuId } })
  },
  cartClear() {
    return request.post(`${BASE}/cart/clear`)
  },
  cartApplyCoupon(code) {
    return request.post(`${BASE}/cart/apply-coupon`, { code })
  },
  cartRemoveCoupon() {
    return request.delete(`${BASE}/cart/coupon`)
  },

  // ═══ 订单 ═══
  orderCreate(data) {
    return request.post(`${BASE}/orders`, data)
  },
  orders(params = {}) {
    return request.get(`${BASE}/orders`, { params })
  },
  orderStats() {
    return request.get(`${BASE}/orders/stats`)
  },
  orderDetail(id) {
    return request.get(`${BASE}/orders/${id}`)
  },
  orderPay(id, data = {}) {
    return request.post(`${BASE}/orders/${id}/pay`, data)
  },
  orderCancel(id, data = {}) {
    return request.post(`${BASE}/orders/${id}/cancel`, data)
  },
  orderPaymentStatus(id) {
    return request.get(`${BASE}/orders/${id}/payment-status`)
  },

  // ═══ 发货 ═══
  deliveries(params = {}) {
    return request.get(`${BASE}/deliveries`, { params })
  },
  deliveryDetail(id) {
    return request.get(`${BASE}/deliveries/${id}`)
  },

  // ═══ 退款 ═══
  refundRequest(data) {
    return request.post(`${BASE}/refunds`, data)
  },
  refunds(params = {}) {
    return request.get(`${BASE}/refunds`, { params })
  },
}
