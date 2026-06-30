import apiClient from './client';

const aiComplianceApi = {
    dashboard() { return apiClient.get('/ai-compliance/dashboard'); },
    gapAnalysis() { return apiClient.get('/ai-compliance/gap-analysis'); },
    complianceReport() { return apiClient.get('/ai-compliance/compliance-report'); },

    // Systems
    listSystems(params) { return apiClient.get('/ai-compliance/systems', { params }); },
    showSystem(id) { return apiClient.get(`/ai-compliance/systems/${id}`); },
    storeSystem(data) { return apiClient.post('/ai-compliance/systems', data); },
    updateSystem(id, data) { return apiClient.put(`/ai-compliance/systems/${id}`, data); },
    destroySystem(id) { return apiClient.delete(`/ai-compliance/systems/${id}`); },

    // Assessments
    listAssessments(systemId, params) { return apiClient.get(`/ai-compliance/systems/${systemId}/assessments`, { params }); },
    storeAssessment(systemId, data) { return apiClient.post(`/ai-compliance/systems/${systemId}/assessments`, data); },

    // Bias
    listBiasDetections(params) { return apiClient.get('/ai-compliance/bias-detections', { params }); },
    storeBiasDetection(data) { return apiClient.post('/ai-compliance/bias-detections', data); },
    mitigateBias(id, data) { return apiClient.post(`/ai-compliance/bias-detections/${id}/mitigate`, data); },
    resolveBias(id) { return apiClient.post(`/ai-compliance/bias-detections/${id}/resolve`); },

    // Training data
    listTrainingData(systemId) { return apiClient.get(`/ai-compliance/systems/${systemId}/training-data`); },
    storeTrainingData(systemId, data) { return apiClient.post(`/ai-compliance/systems/${systemId}/training-data`, data); },
    destroyTrainingData(id) { return apiClient.delete(`/ai-compliance/training-data/${id}`); },

    // Disclosures
    listDisclosures(systemId) { return apiClient.get(`/ai-compliance/systems/${systemId}/disclosures`); },
    storeDisclosure(systemId, data) { return apiClient.post(`/ai-compliance/systems/${systemId}/disclosures`, data); },

    // Decision logs
    listDecisionLogs(params) { return apiClient.get('/ai-compliance/decision-logs', { params }); },
    showDecisionLog(id) { return apiClient.get(`/ai-compliance/decision-logs/${id}`); },
    storeDecisionLog(data) { return apiClient.post('/ai-compliance/decision-logs', data); },

    // Overrides
    listOverrides(params) { return apiClient.get('/ai-compliance/overrides', { params }); },
    storeOverride(data) { return apiClient.post('/admin/ai-compliance/overrides', data); },
    processOverride(id, data) { return apiClient.post(`/admin/ai-compliance/overrides/${id}/process`, data); },
};

export default aiComplianceApi;
