import request from '@/utils/request';

export function submitDemoBooking(data) {
    return request.post('/demo-booking', data);
}

export function getDemoBookingList(params) {
    return request.get('/admin/demo-booking', { params });
}

export function updateDemoBookingStatus(id, status) {
    return request.post(`/admin/demo-booking/${id}/status`, { status });
}

export function getDemoBookingStats() {
    return request.get('/admin/demo-booking/stats');
}

export function getCalendlyLink() {
    return request.get('/admin/demo-booking/calendly');
}
