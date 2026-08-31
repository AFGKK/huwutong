import apiClient from './client';



export function getAnnouncementReads(params = {}) {

    return apiClient.get('/user-chat/announcement-reads', { params });

}



export function createAnnouncementRead(data) {

    return apiClient.post('/user-chat/announcement-reads', data);

}



export function deleteAnnouncementRead(id) {

    return apiClient.delete(`/user-chat/announcement-reads/${id}`);

}

