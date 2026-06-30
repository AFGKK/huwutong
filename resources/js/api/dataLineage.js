import request from '@/utils/request'

/**
 * 数据血缘追踪 API (M2-113)
 */

export function getLineageDashboard() {
  return request.get('/data-lineage/dashboard')
}

export function getLineageRecords(params) {
  return request.get('/data-lineage/', { params })
}

export function createLineageRecord(data) {
  return request.post('/data-lineage/', data)
}

export function getObjectLineage(params) {
  return request.get('/data-lineage/show', { params })
}

export function getLineageChain(id) {
  return request.get(`/data-lineage/chain/${id}`)
}

export function getTrackedObjects(params) {
  return request.get('/data-lineage/tracked-objects', { params })
}

export function exportLineageCsv() {
  return request({
    url: '/data-lineage/export',
    method: 'get',
    responseType: 'blob',
  })
}
