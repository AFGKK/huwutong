import request from '@/utils/request'

export function getSiemDashboard() {
  return request({
    url: '/admin/siem-export/dashboard',
    method: 'get',
  })
}

export function getSiemFormats() {
  return request({
    url: '/admin/siem-export/formats',
    method: 'get',
  })
}

export function getSiemFormatPreview(format) {
  return request({
    url: `/admin/siem-export/format-preview/${format}`,
    method: 'get',
  })
}

export function getSiemConnections() {
  return request({
    url: '/admin/siem-export',
    method: 'get',
  })
}

export function createSiemConnection(data) {
  return request({
    url: '/admin/siem-export',
    method: 'post',
    data,
  })
}

export function updateSiemConnection(id, data) {
  return request({
    url: `/admin/siem-export/${id}`,
    method: 'put',
    data,
  })
}

export function deleteSiemConnection(id) {
  return request({
    url: `/admin/siem-export/${id}`,
    method: 'delete',
  })
}

export function testSiemConnection(id) {
  return request({
    url: `/admin/siem-export/${id}/test`,
    method: 'post',
  })
}

export function pushSiemLogs(id, data) {
  return request({
    url: `/admin/siem-export/${id}/push`,
    method: 'post',
    data,
  })
}

export function getSiemPushLogs(id) {
  return request({
    url: `/admin/siem-export/${id}/logs`,
    method: 'get',
  })
}

export function getSiemConnectionStats(id) {
  return request({
    url: `/admin/siem-export/${id}/stats`,
    method: 'get',
  })
}
