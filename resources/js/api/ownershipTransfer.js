import apiClient from '@/api/client'

export function getOwnershipTransferList(params = {}) {
    return apiClient.get('/ownership-transfer', { params })
}

export function getOwnershipTransferStats() {
    return apiClient.get('/ownership-transfer/stats')
}

export function getOwnershipTransferDetail(id) {
    return apiClient.get(`/ownership-transfer/${id}`)
}

export function createOwnershipTransfer(data) {
    return apiClient.post('/ownership-transfer', data)
}

export function getTransferables(type, search = '') {
    return apiClient.get(`/ownership-transfer/transferables/${type}`, { params: { search } })
}

export function searchCustomers(query) {
    return apiClient.get('/ownership-transfer/search-customers', { params: { search: query } })
}

export function confirmBySource(id) {
    return apiClient.post(`/ownership-transfer/${id}/confirm-source`)
}

export function confirmByTarget(id) {
    return apiClient.post(`/ownership-transfer/${id}/confirm-target`)
}

export function approveOwnershipTransfer(id, notes = '') {
    return apiClient.post(`/ownership-transfer/${id}/approve`, { notes })
}

export function rejectOwnershipTransfer(id, reason = '') {
    return apiClient.post(`/ownership-transfer/${id}/reject`, { reason })
}

export function cancelOwnershipTransfer(id) {
    return apiClient.post(`/admin/ownership-transfer/${id}/cancel`)
}
