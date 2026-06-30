import request from '@/utils/request';

export function getCaseStudies(params) {
    return request.get('/case-studies', { params });
}

export function getCaseStudyDetail(id) {
    return request.get(`/case-studies/${id}`);
}

export function getFeaturedCaseStudies() {
    return request.get('/case-studies/featured');
}

export function getLogoWall() {
    return request.get('/case-studies/logo-wall');
}

export function getCaseStudyCategories() {
    return request.get('/case-studies/categories');
}

export function getIndustryTags() {
    return request.get('/case-studies/industry-tags');
}

export function createCaseStudy(data) {
    return request.post('/admin/case-studies', data, { headers: { 'Content-Type': 'multipart/form-data' } });
}

export function updateCaseStudy(id, data) {
    return request.put(`/admin/case-studies/${id}`, data, { headers: { 'Content-Type': 'multipart/form-data' } });
}

export function deleteCaseStudy(id) {
    return request.delete(`/admin/case-studies/${id}`);
}

export function uploadCaseLogo(file) {
    const form = new FormData();
    form.append('file', file);
    return request.post('/admin/case-studies/upload-logo', form);
}

export function getCaseStudyStats() {
    return request.get('/admin/case-studies/stats');
}
