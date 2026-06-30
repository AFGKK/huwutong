import apiClient from '@/api/client'

export function getFileList(params = {}) {
    return apiClient.get('/admin/files', { params })
}

export function getFileStats(params = {}) {
    return apiClient.get('/admin/files/stats', { params })
}

export function getFileDetail(id) {
    return apiClient.get(`/admin/files/${id}`)
}

export function uploadFile(formData) {
    return apiClient.post('/admin/files/upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    })
}

export function deleteFile(id) {
    return apiClient.delete(`/admin/files/${id}`)
}

export function forceDeleteFile(id) {
    return apiClient.delete(`/admin/files/${id}/force`)
}

export function downloadFile(id, expires = 3600) {
    return apiClient.get(`/admin/files/${id}/download`, { params: { expires } })
}

export function createShareLink(id, data = {}) {
    return apiClient.post(`/admin/files/${id}/share-link`, data)
}

export function revokeShareLink(fileId, linkId) {
    return apiClient.delete(`/admin/files/${fileId}/share-links/${linkId}`)
}
