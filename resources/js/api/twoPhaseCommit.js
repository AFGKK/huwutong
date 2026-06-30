import client from './client';

export default {
    // ═══════ 公共 API（SDK 调用） ═══════

    /**
     * Phase 1: 预申请授权
     */
    reserve(data) {
        return client.post('/license/reserve', data);
    },

    /**
     * Phase 2: 确认提交
     */
    commit(reservationToken) {
        return client.post('/license/commit', { reservation_token: reservationToken });
    },

    /**
     * 取消预留
     */
    cancelReservation(reservationToken) {
        return client.post('/license/cancel-reservation', { reservation_token: reservationToken });
    },

    /**
     * 查询预留状态
     */
    getReservationStatus(reservationToken) {
        return client.post('/license/reservation-status', { reservation_token: reservationToken });
    },

    // ═══════ 管理端 API ═══════

    /**
     * 获取 License 预留统计
     */
    getReservationStats(licenseId) {
        return client.get(`/admin/licenses/${licenseId}/reservation-stats`);
    },

    /**
     * 获取活跃预留列表
     */
    getActiveReservations(params = {}) {
        return client.get('/admin/reservations/active', { params });
    },

    /**
     * 获取预留历史
     */
    getReservationHistory(params = {}) {
        return client.get('/admin/reservations/history', { params });
    },
};
