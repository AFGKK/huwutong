import apiClient from './client';

export default {
    dashboard() {
        return apiClient.get('/admin/innovation/blockchain/dashboard');
    },
    list(params = {}) {
        return apiClient.get('/admin/innovation/blockchain/licenses', { params });
    },
    createChallenge(wallet) {
        return apiClient.post('/admin/innovation/blockchain/challenge', { wallet_address: wallet });
    },
    verifyWallet(data) {
        return apiClient.post('/admin/innovation/blockchain/verify-wallet', data);
    },
};
