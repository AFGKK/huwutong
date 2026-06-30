import client from './client';

export default {
    // 生成嵌入令牌
    generateToken(data) {
        return client.post('/admin/widget/token', data);
    },

    // 获取客户列表（用于选择）
    listCustomers(params = {}) {
        return client.get('/admin/customers', { params });
    },
};
