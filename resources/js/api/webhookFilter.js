import request from '@/utils/request'

export function getWebhookFilterOptions() {
  return request({
    url: '/admin/webhook-filters/options',
    method: 'get',
  })
}

export function getWebhookFilters(endpointId) {
  return request({
    url: `/admin/webhook-filters/endpoints/${endpointId}/filters`,
    method: 'get',
  })
}

export function createWebhookFilter(endpointId, data) {
  return request({
    url: `/admin/webhook-filters/endpoints/${endpointId}/filters`,
    method: 'post',
    data,
  })
}

export function updateWebhookFilter(endpointId, filterId, data) {
  return request({
    url: `/admin/webhook-filters/endpoints/${endpointId}/filters/${filterId}`,
    method: 'put',
    data,
  })
}

export function deleteWebhookFilter(endpointId, filterId) {
  return request({
    url: `/admin/webhook-filters/endpoints/${endpointId}/filters/${filterId}`,
    method: 'delete',
  })
}

export function testWebhookCondition(data) {
  return request({
    url: '/admin/webhook-filters/test-condition',
    method: 'post',
    data,
  })
}

export function batchTestWebhookFilters(endpointId, data) {
  return request({
    url: `/admin/webhook-filters/endpoints/${endpointId}/batch-test`,
    method: 'post',
    data,
  })
}
