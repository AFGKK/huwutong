import apiClient from './client';

export default {
    // ─── Onboarding 向导 ───
    dashboard() {
        return apiClient.get('/onboarding/dashboard').then(r => r.data);
    },
    currentStep() {
        return apiClient.get('/onboarding/step').then(r => r.data);
    },
    completeStep(step, data) {
        return apiClient.post(`/onboarding/step/${step}`, data).then(r => r.data);
    },
    skip(reason) {
        return apiClient.post('/onboarding/skip', { reason }).then(r => r.data);
    },
    reset() {
        return apiClient.post('/onboarding/reset').then(r => r.data);
    },

    // ─── 快速启动 ───
    quickStartItems() {
        return apiClient.get('/quick-start').then(r => r.data);
    },
    completeQuickStartItem(itemKey) {
        return apiClient.post(`/quick-start/${itemKey}/complete`).then(r => r.data);
    },

    // ─── 教程 ───
    tutorials() {
        return apiClient.get('/tutorials').then(r => r.data);
    },
    showTutorial(slug) {
        return apiClient.get(`/tutorials/${slug}`).then(r => r.data);
    },
    updateTutorialProgress(tutorialId, step) {
        return apiClient.post(`/tutorials/${tutorialId}/progress`, { step }).then(r => r.data);
    },
};
