import apiClient from './client';

const BASE = '/compliance-pack';

export default {
    // 仪表盘
    getDashboard() {
        return apiClient.get(`${BASE}/dashboard`).then(r => r.data);
    },

    // 审计问卷
    getQuestionnaire(framework) {
        return apiClient.get(`${BASE}/questionnaire`, { params: { framework } }).then(r => r.data);
    },
    getQuestionnaireResponses(reportId) {
        return apiClient.get(`${BASE}/questionnaire/responses/${reportId}`).then(r => r.data);
    },
    submitQuestionnaire(reportId, answers) {
        return apiClient.post(`${BASE}/questionnaire/submit/${reportId}`, { answers }).then(r => r.data);
    },

    // 证据收集
    getEvidenceChecklist(framework) {
        return apiClient.get(`${BASE}/evidence/checklist`, { params: { framework } }).then(r => r.data);
    },
    getEvidenceList(params) {
        return apiClient.get(`${BASE}/evidence-list`, { params }).then(r => r.data);
    },
    collectEvidence(framework, controlRef, evidenceType) {
        return apiClient.post(`${BASE}/collect-evidence`, { framework, control_ref: controlRef, evidence_type: evidenceType }).then(r => r.data);
    },
    batchCollectEvidence(framework, items) {
        return apiClient.post(`${BASE}/batch-collect-evidence`, { framework, items }).then(r => r.data);
    },
    validateEvidence(evidenceId, status, notes) {
        return apiClient.post(`${BASE}/validate-evidence/${evidenceId}`, { status, notes }).then(r => r.data);
    },

    // 差距分析
    runGapAnalysis(framework, reportId) {
        return apiClient.post(`${BASE}/gap-analysis/run`, { framework, report_id: reportId }).then(r => r.data);
    },
    getGapAnalysis(params) {
        return apiClient.get(`${BASE}/gap-analysis`, { params }).then(r => r.data);
    },
    updateRemediation(gapId, data) {
        return apiClient.put(`${BASE}/gap-analysis/${gapId}/remediation`, data).then(r => r.data);
    },

    // 策略文档
    getPolicyDocuments(framework) {
        return apiClient.get(`${BASE}/policy-documents`, { params: { framework } }).then(r => r.data);
    },
    generatePolicyDocument(docId, fieldValues) {
        return apiClient.post(`${BASE}/policy-documents/${docId}/generate`, { field_values: fieldValues }).then(r => r.data);
    },

    // 报告导出
    exportComplianceReport(reportId, format) {
        return apiClient.post(`${BASE}/reports/${reportId}/export`, { format }).then(r => r.data);
    },
};
