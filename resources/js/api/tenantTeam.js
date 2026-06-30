import apiClient from './client';

const tenantTeamApi = {
    overview() { return apiClient.get('/api/team'); },
    members() { return apiClient.get('/api/team/members'); },
    invite(data) { return apiClient.post('/api/team/invite', data); },
    acceptInvitation(token) { return apiClient.post('/api/team/invitations/accept', { token }); },
    declineInvitation(token) { return apiClient.post('/api/team/invitations/decline', { token }); },
    cancelInvitation(id) { return apiClient.post(`/api/team/invitations/${id}/cancel`); },
    resendInvitation(id) { return apiClient.post(`/api/team/invitations/${id}/resend`); },
    pendingInvitations() { return apiClient.get('/api/team/invitations/pending'); },
    updateMemberRole(id, data) { return apiClient.put(`/api/team/members/${id}/role`, data); },
    removeMember(id) { return apiClient.delete(`/api/team/members/${id}`); },
    transferAdmin(data) { return apiClient.post('/api/team/transfer-admin', data); },
    leave() { return apiClient.post('/api/team/leave'); },
};

export default tenantTeamApi;
