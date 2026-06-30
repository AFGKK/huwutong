import request from '@/utils/request'

export function getBudgetList(params) {
  return request({
    url: '/admin/budget-guard',
    method: 'get',
    params,
  })
}

export function getBudgetDetail(id) {
  return request({
    url: `/admin/budget-guard/${id}`,
    method: 'get',
  })
}

export function saveBudget(data) {
  return request({
    url: '/admin/budget-guard',
    method: 'post',
    data,
  })
}

export function updateBudget(id, data) {
  return request({
    url: `/admin/budget-guard/${id}`,
    method: 'put',
    data,
  })
}

export function deleteBudget(id) {
  return request({
    url: `/admin/budget-guard/${id}`,
    method: 'delete',
  })
}

export function getBudgetDashboard(params) {
  return request({
    url: '/admin/budget-guard/dashboard/data',
    method: 'get',
    params,
  })
}

export function checkBudgetSpend(data) {
  return request({
    url: '/admin/budget-guard/check-spend',
    method: 'post',
    data,
  })
}

export function requestBudgetOverride(data) {
  return request({
    url: '/admin/budget-guard/request-override',
    method: 'post',
    data,
  })
}

export function approveBudgetOverride(overrideId) {
  return request({
    url: `/admin/budget-guard/overrides/${overrideId}/approve`,
    method: 'post',
  })
}

export function rejectBudgetOverride(overrideId) {
  return request({
    url: `/admin/budget-guard/overrides/${overrideId}/reject`,
    method: 'post',
  })
}

export function getPendingOverrides() {
  return request({
    url: '/admin/budget-guard/overrides/pending',
    method: 'get',
  })
}

export function getBudgetAlertHistory(budgetId) {
  return request({
    url: `/admin/budget-guard/${budgetId}/alerts`,
    method: 'get',
  })
}
