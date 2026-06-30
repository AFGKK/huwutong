import request from '@/utils/request';

export function getIncidentAlertingStatus() {
    return request.get('/admin/incident-alerting/status');
}

export function testPagerDuty() {
    return request.post('/admin/incident-alerting/test-pagerduty');
}

export function testOpsGenie() {
    return request.post('/admin/incident-alerting/test-opsgenie');
}

export function sendTestAlert(channel, severity = 'warning') {
    return request.post('/admin/incident-alerting/send-test', { channel, severity });
}

export function pushAlert(channel, summary, severity, details = {}) {
    return request.post('/admin/incident-alerting/push', { channel, summary, severity, details });
}

export function getPagerDutyEvents() {
    return request.get('/admin/incident-alerting/pagerduty/events');
}

export function getOpsGenieAlerts() {
    return request.get('/admin/incident-alerting/opsgenie/alerts');
}
