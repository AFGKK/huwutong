import apiClient from './client';

export default {
    login(data) {
        return apiClient.post('/login', data);
    },
    logout() {
        return apiClient.post('/logout');
    },
    user() {
        return apiClient.get('/user');
    },
    register(data) {
        return apiClient.post('/register', data);
    },
    sendPhoneCode(data) {
        return apiClient.post('/phone/send-code', data);
    },
    phoneLogin(data) {
        return apiClient.post('/phone/login', data);
    },
    phoneRegister(data) {
        return apiClient.post('/phone/register', data);
    },
    refreshToken() {
        return apiClient.post('/token/refresh');
    },
};
