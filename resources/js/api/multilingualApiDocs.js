import request from '@/utils/request'

/**
 * 多语言 API 文档 API (M2-115)
 */

/**
 * 获取本地化的端点列表（根据 Accept-Language）
 */
export function getLocalizedEndpoints(params) {
  return request.get('/admin/api-docs/localized-endpoints', { params })
}

/**
 * 导出国本地化的 OpenAPI 规范
 */
export function exportLocalizedOpenApi(params) {
  return request.get('/admin/api-docs/export/openapi-localized', { params })
}

/**
 * 获取支持的文档语言列表
 */
export function getSupportedLocales() {
  return request.get('/admin/api-docs/locales')
}

/**
 * 更新端点多语言翻译
 */
export function updateEndpointTranslations(id, data) {
  return request.put(`/admin/api-docs/endpoints/${id}/translations`, data)
}

/**
 * 批量导入翻译
 */
export function batchImportTranslations(data) {
  return request.post('/admin/api-docs/translations/batch-import', data)
}
