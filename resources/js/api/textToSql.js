import apiClient from './client';

const textToSqlApi = {
    query(data) { return apiClient.post('/text-to-sql/query', data); },
    execute(data) { return apiClient.post('/text-to-sql/execute', data); },
    validate(data) { return apiClient.post('/text-to-sql/validate', data); },
    config() { return apiClient.get('/text-to-sql/config'); },
};

export default textToSqlApi;
