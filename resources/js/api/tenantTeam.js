import apiClient from './client';

const tenantTeamApi = {
    overview() { return apiClient.get('/team'); },
    members() { return apiClient.get('/team/members'); },
    invite(data) { return apiClient.post('/team/invite', data); },
    acceptInvitation(token) { return apiClient.post('/team/invitations/accept', { token }); },
    declineInvitation(token) { return apiClient.post('/team/invitations/decline', { token }); },
    cancelInvitation(id) { return apiClient.post(`/team/invitations/${id}/cancel`); },
    resendInvitation(id) { return apiClient.post(`/team/invitations/${id}/resend`); },
    pendingInvitations() { return apiClient.get('/team/invitations/pending'); },
    updateMemberRole(id, data) { return apiClient.put(`/team/members/${id}/role`, data); },
    removeMember(id) { return apiClient.delete(`/team/members/${id}`); },
    transferAdmin(data) { return apiClient.post('/team/transfer-admin', data); },
    leave() { return apiClient.post('/team/leave'); },
};

export default tenantTeamApi;
