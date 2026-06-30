import request from '@/utils/request'

/**
 * 自动开票系统 API (M2-148 🛒)
 */

export function getInvoiceStats() {
  return request.get('/auto-invoice/stats')
}

export function getInvoices(params) {
  return request.get('/auto-invoice/', { params })
}

export function getInvoiceDetail(id) {
  return request.get(`/auto-invoice/${id}`)
}

export function previewInvoice(id) {
  return request.get(`/auto-invoice/${id}/preview`, { responseType: 'text' })
}

export function generateInvoice(orderId, invoiceTitleId) {
  return request.post(`/auto-invoice/${orderId}/generate`, { invoice_title_id: invoiceTitleId })
}

export function resendInvoice(invoiceId) {
  return request.post(`/auto-invoice/${invoiceId}/resend`)
}

// 发票抬头
export function getInvoiceTitles() {
  return request.get('/auto-invoice/titles/list')
}

export function createInvoiceTitle(data) {
  return request.post('/auto-invoice/titles', data)
}

export function updateInvoiceTitle(id, data) {
  return request.put(`/auto-invoice/titles/${id}`, data)
}

export function deleteInvoiceTitle(id) {
  return request.delete(`/auto-invoice/titles/${id}`)
}
