import request from '@/utils/request'

/**
 * AI 智能套件 API (M2-43 ~ M2-48)
 */

// ─── M2-43 AI 收入预测 ───
export function getRevenueForecast(params) {
  return request.get('/ai/revenue-forecast', { params })
}

// ─── M2-44 AI 客户流失预警 ───
export function getChurnPrediction(params) {
  return request.get('/ai/churn-prediction', { params })
}

// ─── M2-45 AI 自适应安全阈值 ───
export function getAdaptiveSecurity(params) {
  return request.get('/ai/adaptive-security', { params })
}
export function clearAdaptiveSecurityCache() {
  return request.post('/ai/adaptive-security/clear-cache')
}

// ─── M2-46 AI 智能定价建议 ───
export function getPricingSuggestions(params) {
  return request.get('/ai/pricing-suggestions', { params })
}

// ─── M2-47 AI SDK 配置生成 ───
export function generateSdkConfig(data) {
  return request.post('/ai/sdk-config', data)
}
export function getSdkOptions() {
  return request.get('/ai/sdk-options')
}

// ─── M2-48 AI 测试用例生成 ───
export function generateTests(data) {
  return request.post('/ai/generate-tests', data)
}
export function generateTestsBatch(data) {
  return request.post('/ai/generate-tests-batch', data)
}
export function generateAllTests(data) {
  return request.post('/ai/generate-all-tests', data)
}
export function getTestFrameworks() {
  return request.get('/ai/test-frameworks')
}
