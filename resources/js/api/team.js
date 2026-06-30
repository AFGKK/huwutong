import client from './client';

/**
 * M2-129 租户团队协作 API
 */
export default {
    overview() {
        return client.get('/team');
    },
    members(params = {}) {
        return client.get('/team/members', { params });
    },
    invite(data) {
        return client.post('/team/invite', data);
    },
    acceptInvitation(token) {
        return client.post('/team/invitations/accept', { token });
    },
    declineInvitation(token) {
        return client.post('/team/invitations/decline', { token });
    },
    pendingInvitations() {
        return client.get('/team/invitations/pending');
    },
    cancelInvitation(id) {
        return client.post(`/team/invitations/${id}/cancel`);
    },
    resendInvitation(id) {
        return client.post(`/team/invitations/${id}/resend`);
    },
    updateMemberRole(memberId, role) {
        return client.put(`/team/members/${memberId}/role`, { role });
    },
    removeMember(memberId) {
        return client.delete(`/team/members/${memberId}`);
    },
    transferAdmin(targetMemberId) {
        return client.post('/team/transfer-admin', { target_member_id: targetMemberId });
    },
    leave() {
        return client.post('/team/leave');
    },
};
