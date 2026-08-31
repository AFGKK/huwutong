<template>
    <div class="portal-settings">
        <div class="page-header">
            <h2>{{ $t('portal.settings') }}</h2>
        </div>

        <el-row :gutter="16">
            <el-col :span="12">
                <el-card class="mb-4">
                    <template #header>
                        <span>{{ $t('portal.profile_info') }}</span>
                    </template>
                    <el-form :model="profileForm" label-position="top">
                        <el-form-item :label="$t('portal.full_name')">
                            <el-input v-model="profileForm.name" disabled />
                        </el-form-item>
                        <el-form-item :label="$t('portal.email')">
                            <el-input v-model="profileForm.email" disabled />
                            <div class="form-hint">{{ $t('portal.email_hint') }}</div>
                        </el-form-item>
                        <el-form-item :label="$t('portal.registered_at')">
                            <el-input :model-value="profileForm.created_at" disabled />
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-col>

            <el-col :span="12">
                <el-card class="mb-4">
                    <template #header>
                        <span>{{ $t('portal.change_password') }}</span>
                    </template>
                    <el-form
                        ref="passwordFormRef"
                        :model="passwordForm"
                        :rules="passwordRules"
                        label-position="top"
                    >
                        <el-form-item :label="$t('portal.current_password')" prop="current_password">
                            <el-input
                                v-model="passwordForm.current_password"
                                type="password"
                                show-password
                                :placeholder="$t('portal.current_password_ph')"
                            />
                        </el-form-item>
                        <el-form-item :label="$t('portal.new_password')" prop="new_password">
                            <el-input
                                v-model="passwordForm.new_password"
                                type="password"
                                show-password
                                :placeholder="$t('portal.new_password_ph')"
                            />
                        </el-form-item>
                        <el-form-item :label="$t('portal.confirm_password')" prop="confirm_password">
                            <el-input
                                v-model="passwordForm.confirm_password"
                                type="password"
                                show-password
                                :placeholder="$t('portal.confirm_password_ph')"
                            />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="handleChangePassword" :loading="changingPassword">
                                {{ $t('portal.change_password') }}
                            </el-button>
                        </el-form-item>
                    </el-form>
                </el-card>

                <el-card>
                    <template #header>
                        <span>{{ $t('portal.account_actions') }}</span>
                    </template>
                    <div class="account-actions">
                        <el-button type="info" plain @click="handleViewSessions" :icon="Monitor">
                            {{ $t('portal.view_sessions') }}
                        </el-button>
                        <el-button type="danger" plain @click="handleLogout" :icon="SwitchButton">
                            {{ $t('portal.logout') }}
                        </el-button>
                    </div>
                </el-card>

                <el-card class="mt-4">
                    <template #header>
                        <span>
                            <el-icon><Lock /></el-icon>
                            {{ $t('portal.data_privacy') }}
                        </span>
                    </template>
                    <div class="account-actions">
                        <el-button type="warning" plain @click="handleRequestDataExport" :icon="Download">
                            {{ $t('portal.export_gdpr') }}
                        </el-button>
                        <el-button type="danger" plain @click="handleDeleteAccount" :icon="Delete">
                            {{ $t('portal.delete_account') }}
                        </el-button>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import apiClient from '@/api/client';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Monitor, SwitchButton, Download, Delete, Lock } from '@element-plus/icons-vue';
import deletionApi from '@/api/deletion';

const { t } = useI18n();
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

const passwordRules = computed(() => ({
    current_password: [
        { required: true, message: t('portal.pwd_current_required'), trigger: 'blur' },
    ],
    new_password: [
        { required: true, message: t('portal.pwd_new_required'), trigger: 'blur' },
        { min: 8, message: t('portal.pwd_min'), trigger: 'blur' },
    ],
    confirm_password: [
        { required: true, message: t('portal.pwd_confirm_required'), trigger: 'blur' },
        {
            validator: (rule, value, callback) => {
                if (value !== passwordForm.new_password) {
                    callback(new Error(t('portal.pwd_mismatch')));
                } else {
                    callback();
                }
            },
            trigger: 'blur',
        },
    ],
}));

async function fetchProfile() {
    try {
        const { data: res } = await apiClient.get('/user');
        const user = res.data || {};
        profileForm.name = user.name || '';
        profileForm.email = user.email || '';
        profileForm.created_at = user.created_at || '';
    } catch {
        profileForm.name = authStore.userName;
        profileForm.email = authStore.userEmail;
    }
}

async function handleChangePassword() {
    const valid = await passwordFormRef.value.validate().catch(() => false);
    if (!valid) return;

    changingPassword.value = true;
    try {
        await apiClient.post('/password/change', {
            current_password: passwordForm.current_password,
            new_password: passwordForm.new_password,
            new_password_confirmation: passwordForm.confirm_password,
        });
        ElMessage.success(t('portal.pwd_changed'));
        passwordForm.current_password = '';
        passwordForm.new_password = '';
        passwordForm.confirm_password = '';
        passwordFormRef.value.resetFields();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('portal.pwd_change_failed'));
    } finally {
        changingPassword.value = false;
    }
}

function handleViewSessions() {
    router.push('/portal/sessions');
}

async function handleLogout() {
    await authStore.logout();
    router.push('/login');
}

async function handleRequestDataExport() {
    ElMessage.info(t('portal.export_preparing'));
    try {
        const res = await apiClient.post('/gdpr/requests', {
            type: 'access',
            reason: t('portal.export_reason'),
        });
        if (res.data?.success) {
            ElMessage.success(t('portal.export_submitted'));
        }
    } catch (e) {
        ElMessage.error(t('portal.export_submit_failed', { msg: e.response?.data?.message || e.message }));
    }
}

async function handleDeleteAccount() {
    try {
        const checkRes = await deletionApi.checkDeletability();
        const check = checkRes.data?.data || { can_delete: false, reasons: [] };

        if (!check.can_delete) {
            ElMessage.warning(t('portal.delete_blocked', { reasons: (check.reasons || []).join('\n') }));
            return;
        }

        const reasonsRes = await deletionApi.getCancellationReasons();
        const reasons = reasonsRes.data?.data || [];

        await ElMessageBox.confirm(
            t('portal.delete_confirm_body'),
            t('portal.delete_confirm_title'),
            {
                confirmButtonText: t('portal.delete_confirm_btn'),
                cancelButtonText: t('actions.cancel'),
                type: 'warning',
                distinguishCancelAndClose: true,
                inputType: 'select',
                inputOptions: reasons.map(r => ({ value: r.value, label: r.label })),
                inputValue: 'other',
                inputPlaceholder: t('portal.delete_reason_ph'),
                inputValidator: (value) => !!value,
                inputErrorMessage: t('portal.delete_reason_required'),
            }
        ).then(async ({ value }) => {
            const delRes = await deletionApi.requestDeletion({
                reason: value,
                confirm: true,
            });

            if (delRes.data?.success) {
                ElMessage.success(t('portal.delete_ok'));
                setTimeout(() => {
                    authStore.logout();
                    router.push('/login');
                }, 2000);
            } else {
                ElMessage.error(delRes.data?.message || t('portal.delete_failed'));
            }
        }).catch(() => {});
    } catch (e) {
        ElMessage.error(t('portal.op_failed', { msg: e.response?.data?.message || e.message }));
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
.mt-4 { margin-top: 16px; }

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
