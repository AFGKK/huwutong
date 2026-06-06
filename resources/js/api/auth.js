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
};
