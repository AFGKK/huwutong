import apiClient from './client';

export default {
    countries() {
        return apiClient.get('/tax/countries');
    },
    regionTaxes(countryCode) {
        return apiClient.get(`/tax/region/${countryCode}`);
    },
    rates(params = {}) {
        return apiClient.get('/tax/rates', { params });
    },
    updateRate(id, data) {
        return apiClient.put(`/tax/rates/${id}`, data);
    },
    calculate(amount, countryCode, options = {}) {
        return apiClient.post('/tax/calculate', { amount, country_code: countryCode, ...options });
    },
    stats() {
        return apiClient.get('/tax/stats');
    },
    certificates(params = {}) {
        return apiClient.get('/tax/certificates', { params });
    },
    storeCertificate(data) {
        return apiClient.post('/tax/certificates', data);
    },
    approveCertificate(id, status, notes = '') {
        return apiClient.put(`/tax/certificates/${id}`, { status, notes });
    },
    deleteCertificate(id) {
        return apiClient.delete(`/tax/certificates/${id}`);
    },
};
