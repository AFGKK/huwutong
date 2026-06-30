/**
 * 虚拟环境/模拟器检测 API
 * M1.3-14
 */

import request from '@/utils/request';

/** 仪表盘 */
export function getVmDashboard() {
    return request.get('/admin/vm-detection/dashboard');
}

/** 已检测设备列表 */
export function getVmDevices(params) {
    return request.get('/admin/vm-detection/devices', { params });
}

/** 触发检测 */
export function detectVmDevice(deviceId) {
    return request.post(`/admin/vm-detection/detect/${deviceId}`);
}

/** 获取配置 */
export function getVmConfig() {
    return request.get('/admin/vm-detection/config');
}

/** 更新配置 */
export function updateVmConfig(data) {
    return request.put('/admin/vm-detection/config', data);
}
