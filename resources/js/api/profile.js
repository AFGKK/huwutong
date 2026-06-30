import request from '@/utils/request';

export default {
    /** 获取当前用户信息 */
    getUser() {
        return request.get('/user');
    },

    /** 更新个人资料 */
    updateProfile(data) {
        return request.put('/profile', data);
    },

    /** 上传头像 */
    uploadAvatar(file) {
        const formData = new FormData();
        formData.append('avatar', file);
        return request.post('/avatar/upload', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },

    /** 删除头像（恢复默认） */
    deleteAvatar() {
        return request.delete('/avatar');
    },
};
