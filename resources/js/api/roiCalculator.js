import apiClient from '@/api/client'

export function calculateRoi(params) {
    return apiClient.post('/roi-calculator/calculate', params)
}

export function getRoiDefaults(currency = 'CNY') {
    return apiClient.get('/roi-calculator/defaults', { params: { currency } })
}
