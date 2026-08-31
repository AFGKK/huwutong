import request from '@/utils/request';

export function uploadFile(file, type, isPublic = null) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', type);
    if (isPublic !== null && isPublic !== undefined) {
        formData.append('is_public', isPublic ? '1' : '0');
    }
    return request.post('/admin/cloud-upload', formData);
}
export function getUploadedFiles(params) {
    return request.get('/admin/cloud-upload', { params });
}
export function deleteUploadedFile(id) {
    return request.delete(`/admin/cloud-upload/${id}`);
}
export function getFileUrl(id) {
    return request.get(`/admin/cloud-upload/${id}/url`);
}
export function getCloudUploadDashboard() {
    return request.get('/admin/cloud-upload/dashboard');
}
export function getPreviewData(id) {
    return request.get(`/admin/cloud-upload/${id}/preview`);
}
export function toggleFileVisibility(id) {
    return request.patch(`/admin/cloud-upload/${id}/visibility`);
}
