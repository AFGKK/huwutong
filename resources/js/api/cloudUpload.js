import request from '@/utils/request';

export function uploadFile(file, type) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', type);
    return request.post('/admin/cloud-upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
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
