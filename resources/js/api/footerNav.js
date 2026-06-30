import request from '@/utils/request'

export function getFooterNav() {
  return request({
    url: '/admin/footer-nav',
    method: 'get',
  })
}

export function createFooterNavItem(data) {
  return request({
    url: '/admin/footer-nav',
    method: 'post',
    data,
  })
}

export function updateFooterNavItem(id, data) {
  return request({
    url: `/admin/footer-nav/${id}`,
    method: 'put',
    data,
  })
}

export function deleteFooterNavItem(id) {
  return request({
    url: `/admin/footer-nav/${id}`,
    method: 'delete',
  })
}

export function reorderFooterNav(items) {
  return request({
    url: '/admin/footer-nav/reorder',
    method: 'post',
    data: { items },
  })
}

export function toggleFooterNavItem(id) {
  return request({
    url: `/admin/footer-nav/${id}/toggle`,
    method: 'post',
  })
}

export function initDefaultFooterNav() {
  return request({
    url: '/admin/footer-nav/init-defaults',
    method: 'post',
  })
}

export function getFooterNavOptions() {
  return request({
    url: '/admin/footer-nav/options',
    method: 'get',
  })
}
