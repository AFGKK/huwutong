import client from './client';

export default {
    generateToken(data) {
        return client.post('/widget/token', data);
    },

    listCustomers(params = {}) {
        return client.get('/admin/customers', { params });
    },
};
