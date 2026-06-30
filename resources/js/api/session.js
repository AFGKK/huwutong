/**
 * Session 管理 API
 * M1.4-30
 */

import request from '@/utils/request';

/** 仪表盘 */
export function getSessionDashboard() {
    return request.get('/admin/sessions/dashboard');
}

/** 会话列表 */
export function getSessions(params) {
    return request.get('/admin/sessions', { params });
}

/** 会话详情 */
export function getSessionDetail(id) {
    return request.get(`/admin/sessions/${id}`);
}

/** 踢出会话 */
export function terminateSession(id) {
    return request.post(`/admin/sessions/${id}/terminate`);
}

/** 批量踢出 */
export function batchTerminateSessions(ids) {
    return request.post('/admin/sessions/batch-terminate', { ids });
}

/** 踢出用户所有会话 */
export function terminateUserSessions(userId) {
    return request.post(`/admin/sessions/terminate-user/${userId}`);
}
