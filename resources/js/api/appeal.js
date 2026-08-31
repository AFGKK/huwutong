import apiClient from './client';



export function submitAppeal(data) {

    return apiClient.post('/appeal/submit', data);

}



export function lookupAppeal(email) {

    return apiClient.get('/appeal/lookup', { params: { email } });

}



export function uploadAppealAttachment(formData) {

    return apiClient.post('/im/upload', formData, {

        headers: { 'Content-Type': 'multipart/form-data' },

    });

}

