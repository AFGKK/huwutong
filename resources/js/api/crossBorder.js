import apiClient from './client'

// ─── 仪表盘 ───
export function getCrossBorderDashboard() { return apiClient.get('/admin/cross-border/dashboard') }

// ─── 货币转换审计 ───
export function getConversionLogs(params) { return apiClient.get('/admin/cross-border/conversion-logs', { params }) }

// ─── 跨境支付 ───
export function getCrossBorderPayments(params) { return apiClient.get('/admin/cross-border/payments', { params }) }
export function recordCrossBorderPayment(data) { return apiClient.post('/admin/cross-border/payments', data) }

// ─── 月度报表 ───
export function getMonthlyReports(params) { return apiClient.get('/admin/cross-border/monthly-reports', { params }) }
export function generateReport(month) { return apiClient.post('/admin/cross-border/generate-report', { month }) }

// ─── 合规检查 ───
export function checkCompliance(data) { return apiClient.post('/admin/cross-border/check-compliance', data) }
