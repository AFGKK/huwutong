import apiClient from './client';

const textToSqlApi = {
    query(data) { return apiClient.post('/api/text-to-sql/query', data); },
    execute(data) { return apiClient.post('/api/text-to-sql/execute', data); },
    validate(data) { return apiClient.post('/api/text-to-sql/validate', data); },
    config() { return apiClient.get('/api/text-to-sql/config'); },
};

export default textToSqlApi;
