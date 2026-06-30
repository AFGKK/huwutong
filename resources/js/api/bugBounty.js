import request from '@/api/client';

// ─── 公开端点 ───

export function submitBugReport(data) {
    return request.post('/bug-bounty/reports', data);
}

export function getBugBountyPolicy() {
    return request.get('/bug-bounty/policy');
}

// ─── 管理员端点 ───

export function getBugReports(params = {}) {
    return request.get('/admin/bug-bounty/reports', { params });
}

export function getBugReportDetail(id) {
    return request.get(`/admin/bug-bounty/reports/${id}`);
}

export function getBugBountyStats() {
    return request.get('/admin/bug-bounty/reports/stats');
}

export function reviewBugReport(id, data) {
    return request.post(`/admin/bug-bounty/reports/${id}/review`, data);
}

export function confirmBugReport(id, data) {
    return request.post(`/admin/bug-bounty/reports/${id}/confirm`, data);
}

export function markBugFixed(id, data = {}) {
    return request.post(`/admin/bug-bounty/reports/${id}/mark-fixed`, data);
}

export function declineBugReport(id, data) {
    return request.post(`/admin/bug-bounty/reports/${id}/decline`, data);
}

export function markBugPaid(id) {
    return request.post(`/admin/bug-bounty/reports/${id}/mark-paid`);
}

export function deleteBugReport(id) {
    return request.delete(`/admin/bug-bounty/reports/${id}`);
}

export function getHallOfFame() {
    return request.get('/admin/bug-bounty/hall-of-fame');
}

export function updateHallOfFameEntry(id, data) {
    return request.put(`/admin/bug-bounty/hall-of-fame/${id}`, data);
}
