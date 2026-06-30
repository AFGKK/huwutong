<template>
    <div class="portal-settings">
        <div class="page-header">
            <h2>个人设置</h2>
        </div>

        <el-row :gutter="16">
            <!-- 个人信息 -->
            <el-col :span="12">
                <el-card class="mb-4">
                    <template #header>
                        <span>个人信息</span>
                    </template>
                    <el-form :model="profileForm" label-position="top">
                        <el-form-item label="姓名">
                            <el-input v-model="profileForm.name" disabled />
                        </el-form-item>
                        <el-form-item label="邮箱">
                            <el-input v-model="profileForm.email" disabled />
                            <div class="form-hint">如需修改邮箱，请联系管理员。</div>
                        </el-form-item>
                        <el-form-item label="注册时间">
                            <el-input :model-value="profileForm.created_at" disabled />
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-col>

            <!-- 修改密码 -->
            <el-col :span="12">
                <el-card class="mb-4">
                    <template #header>
                        <span>修改密码</span>
                    </template>
                    <el-form
                        ref="passwordFormRef"
                        :model="passwordForm"
                        :rules="passwordRules"
                        label-position="top"
                    >
                        <el-form-item label="当前密码" prop="current_password">
                            <el-input
                                v-model="passwordForm.current_password"
                                type="password"
                                show-password
                                placeholder="请输入当前密码"
                            />
                        </el-form-item>
                        <el-form-item label="新密码" prop="new_password">
                            <el-input
                                v-model="passwordForm.new_password"
                                type="password"
                                show-password
                                placeholder="请输入新密码（至少8位）"
                            />
                        </el-form-item>
                        <el-form-item label="确认新密码" prop="confirm_password">
                            <el-input
                                v-model="passwordForm.confirm_password"
                                type="password"
                                show-password
                                placeholder="请再次输入新密码"
                            />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="handleChangePassword" :loading="changingPassword">
                                修改密码
                            </el-button>
                        </el-form-item>
                    </el-form>
                </el-card>

                <!-- 账户操作 -->
                <el-card>
                    <template #header>
                        <span>账户操作</span>
                    </template>
                    <div class="account-actions">
                        <el-button type="info" plain @click="handleViewSessions" :icon="Monitor">
                            查看登录会话
                        </el-button>
                        <el-button type="danger" plain @click="handleLogout" :icon="SwitchButton">
                            退出登录
                        </el-button>
                    </div>
                </el-card>

                <!-- 数据隐私 -->
                <el-card class="mt-4">
                    <template #header>
                        <span>
                            <el-icon><Lock /></el-icon>
                            数据与隐私
                        </span>
                    </template>
                    <div class="account-actions">
                        <el-button type="warning" plain @click="handleRequestDataExport" :icon="Download">
                            导出我的数据 (GDPR)
                        </el-button>
                        <el-button type="danger" plain @click="handleDeleteAccount" :icon="Delete">
                            注销账号
                        </el-button>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import apiClient from '@/api/client';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Monitor, SwitchButton, Download, Delete, Lock } from '@element-plus/icons-vue';
import deletionApi from '@/api/deletion';

const router = useRouter();
const authStore = useAuthStore();

const passwordFormRef = ref(null);
const changingPassword = ref(false);

const profileForm = reactive({
    name: '',
    email: '',
    created_at: '',
});

const passwordForm = reactive({
    current_password: '',
    new_password: '',
    confirm_password: '',
});

const passwordRules = {
    current_password: [
        { required: true, message: '请输入当前密码', trigger: 'blur' },
    ],
    new_password: [
        { required: true, message: '请输入新密码', trigger: 'blur' },
        { min: 8, message: '密码至少 8 位', trigger: 'blur' },
    ],
    confirm_password: [
        { required: true, message: '请确认新密码', trigger: 'blur' },
        {
            validator: (rule, value, callback) => {
                if (value !== passwordForm.new_password) {
                    callback(new Error('两次输入的密码不一致'));
                } else {
                    callback();
                }
            },
            trigger: 'blur',
        },
    ],
};

async function fetchProfile() {
    try {
        const { data: res } = await apiClient.get('/user');
        const user = res.data || {};
        profileForm.name = user.name || '';
        profileForm.email = user.email || '';
        profileForm.created_at = user.created_at || '';
    } catch {
        // use store as fallback
        profileForm.name = authStore.userName;
        profileForm.email = authStore.userEmail;
    }
}

async function handleChangePassword() {
    const valid = await passwordFormRef.value.validate().catch(() => false);
    if (!valid) return;

    changingPassword.value = true;
    try {
        // 使用 API 的 changePassword 端点
        await apiClient.post('/password/change', {
            current_password: passwordForm.current_password,
            new_password: passwordForm.new_password,
            new_password_confirmation: passwordForm.confirm_password,
        });
        ElMessage.success('密码修改成功');
        passwordForm.current_password = '';
        passwordForm.new_password = '';
        passwordForm.confirm_password = '';
        passwordFormRef.value.resetFields();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '密码修改失败');
    } finally {
        changingPassword.value = false;
    }
}

function handleViewSessions() {
    ElMessage.info('会话管理功能即将上线');
}

async function handleLogout() {
    await authStore.logout();
    router.push('/login');
}

async function handleRequestDataExport() {
    ElMessage.info('正在准备数据导出，请稍候...');
    try {
        const res = await apiClient.post('/api/gdpr/requests', {
            type: 'access',
            reason: '用户自助导出',
        });
        if (res.data?.success) {
            ElMessage.success('数据导出请求已提交，导出完成后将可通过邮件下载');
        }
    } catch (e) {
        ElMessage.error('提交失败: ' + (e.response?.data?.message || e.message));
    }
}

async function handleDeleteAccount() {
    try {
        // 第一步：检查可注销性
        const checkRes = await deletionApi.checkDeletability();
        const check = checkRes.data?.data || { can_delete: false, reasons: [] };

        if (!check.can_delete) {
            ElMessage.warning('账号当前无法注销：\n' + (check.reasons || []).join('\n'));
            return;
        }

        // 第二步：显示确认对话框
        const reasonsRes = await deletionApi.getCancellationReasons();
        const reasons = reasonsRes.data?.data || [];

        await ElMessageBox.confirm(
            '确定要注销账号吗？此操作不可逆。\n\n' +
            '注销后将：\n' +
            '1. 您的个人数据将被匿名化处理\n' +
            '2. 所有关联的 License 将被标记为已删除\n' +
            '3. 您将无法再次登录此账号\n\n' +
            '请选择注销原因：',
            '确认注销账号',
            {
                confirmButtonText: '确认注销',
                cancelButtonText: '取消',
                type: 'warning',
                distinguishCancelAndClose: true,
                inputType: 'select',
                inputOptions: reasons.map(r => ({ value: r.value, label: r.label })),
                inputValue: 'other',
                inputPlaceholder: '请选择注销原因',
                inputValidator: (value) => !!value,
                inputErrorMessage: '请选择注销原因',
            }
        ).then(async ({ value }) => {
            // 第三步：执行注销
            const delRes = await deletionApi.requestDeletion({
                reason: value,
                confirm: true,
            });

            if (delRes.data?.success) {
                ElMessage.success('账号已注销，数据已匿名化处理。感谢您的使用。');
                // 延迟跳转到登录页
                setTimeout(() => {
                    authStore.logout();
                    router.push('/login');
                }, 2000);
            } else {
                ElMessage.error(delRes.data?.message || '注销失败');
            }
        }).catch(() => {
            // 用户取消
        });
    } catch (e) {
        ElMessage.error('操作失败: ' + (e.response?.data?.message || e.message));
    }
}

onMounted(fetchProfile);
</script>

<style scoped>
.page-header {
    margin-bottom: 20px;
}

.page-header h2 { margin: 0; }

.mb-4 { margin-bottom: 16px; }

.form-hint {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
}

.account-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.account-actions .el-button {
    width: 100%;
}
</style>
