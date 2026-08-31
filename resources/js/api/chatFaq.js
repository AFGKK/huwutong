import apiClient from './client';



export function getAdminFaqs() {

    return apiClient.get('/admin/chat-faqs');

}



export function createFaq(data) {

    return apiClient.post('/admin/chat-faqs', data);

}



export function updateFaq(id, data) {

    return apiClient.put(`/admin/chat-faqs/${id}`, data);

}



export function deleteFaq(id) {

    return apiClient.delete(`/admin/chat-faqs/${id}`);

}



export function reorderFaqs(orders) {

    return apiClient.post('/admin/chat-faqs/reorder', { orders });

}

