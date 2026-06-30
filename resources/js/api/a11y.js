import apiClient from '@/api/client';

export default {
    guidelines() {
        return apiClient.get('/a11y/guidelines');
    },
    stats() {
        return apiClient.get('/a11y/stats');
    },
    report() {
        return apiClient.get('/a11y/report');
    },
    limitations() {
        return apiClient.get('/a11y/limitations');
    },
    declaration() {
        return apiClient.get('/a11y/declaration');
    },
    checkContrast(fg, bg) {
        return apiClient.post('/a11y/contrast-check', { foreground: fg, background: bg });
    },
    getPreferences() {
        return apiClient.get('/a11y/preferences');
    },
    savePreferences(prefs) {
        return apiClient.put('/a11y/preferences', prefs);
    },
};
