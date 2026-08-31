<template>
    <div class="users-page">
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4" v-for="card in statCards" :key="card.label">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value">{{ card.value }}</div>
                        <div class="stat-label">{{ card.label }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never">
            <div class="toolbar">
                <el-form :inline="true" :model="filters" size="small">
                    <el-form-item>
                        <el-input
                            v-model="filters.search"
                            :placeholder="t('users_page.search_ph')"
                            clearable
                            @clear="fetchUsers"
                            @keyup.enter="fetchUsers"
                            style="width: 260px"
                        >
                            <template #prefix>
                                <el-icon><Search /></el-icon>
                            </template>
                        </el-input>
                    </el-form-item>
                    <el-form-item>
                        <el-select v-model="filters['filter.status']" :placeholder="t('users_page.status')" clearable @change="fetchUsers" style="width: 120px">
                            <el-option :label="t('users_page.st_active')" value="active" />
                            <el-option :label="t('users_page.st_inactive')" value="inactive" />
                            <el-option :label="t('users_page.st_locked')" value="locked" />
                        </el-select>
                    </el-form-item>
                    <el-form-item v-if="isSuperAdmin">
                        <el-select v-model="filters['filter.tenant_id']" :placeholder="t('users_page.tenant')" clearable filterable @change="fetchUsers" style="width: 200px">
                            <el-option v-for="tn in tenants" :key="tn.id" :label="tn.name" :value="tn.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="fetchUsers">
                            <el-icon><Search /></el-icon>{{ t('users_page.query') }}
                        </el-button>
                    </el-form-item>
                </el-form>
                <div class="toolbar-actions">
                    <el-button type="primary" @click="openCreate">
                        <el-icon><Plus /></el-icon>{{ t('users_page.create') }}
                    </el-button>
                </div>
            </div>

            <el-table :data="users" v-loading="loading" stripe style="width: 100%">
                <el-table-column prop="id" label="ID" width="60" />
                <el-table-column :label="t('users_page.col_user')" min-width="180">
                    <template #default="{ row }">
                        <div class="user-cell">
                            <el-avatar :size="28" :src="row.avatar_url">
                                <span class="avatar-initial">{{ (row.name || '?').charAt(0).toUpperCase() }}</span>
                                <template #error>{{ (row.name || '?').charAt(0).toUpperCase() }}</template>
                            </el-avatar>
                            <div class="user-info">
                                <span class="user-name">{{ row.name }}</span>
                                <span class="user-email">{{ row.email }}</span>
                            </div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="phone" :label="t('users_page.col_phone')" width="130" />
                <el-table-column :label="t('users_page.tenant')" width="150" v-if="isSuperAdmin">
                    <template #default="{ row }">
                        {{ row.tenant?.name || '-' }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('users_page.col_roles')" width="180">
                    <template #default="{ row }">
                        <el-tag
                            v-for="role in row.roles"
                            :key="role.id"
                            size="small"
                            :type="role.name === 'super-admin' ? 'danger' : role.name === 'admin' ? 'warning' : 'info'"
                            style="margin-right: 4px"
                        >
                            {{ role.name }}
                        </el-tag>
                        <span v-if="!row.roles?.length" class="no-role">-</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('users_page.status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'active' ? 'success' : row.status === 'locked' ? 'danger' : 'info'" size="small">
                            {{ row.status === 'active' ? t('users_page.st_active') : row.status === 'locked' ? t('users_page.st_locked') : t('users_page.st_inactive') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="last_login_at" :label="t('users_page.col_last_login')" width="170">
                    <template #default="{ row }">
                        <span v-if="row.last_login_at">{{ row.last_login_at }}</span>
                        <span v-else class="no-login">{{ t('users_page.never_login') }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" :label="t('users_page.col_created')" width="170" />
                <el-table-column :label="t('users_page.col_actions')" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="openEdit(row)">{{ t('users_page.edit') }}</el-button>
                        <el-button text size="small" type="warning" @click="handleResetPassword(row)" v-if="row.id !== currentUserId">{{ t('users_page.reset_pwd') }}</el-button>
                        <el-button
                            text
                            size="small"
                            :type="row.status === 'active' ? 'danger' : 'success'"
                            @click="handleToggleStatus(row)"
                            v-if="row.id !== currentUserId"
                        >
                            {{ row.status === 'active' ? t('users_page.deactivate') : t('users_page.activate') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :total="total"
                    :page-sizes="[10, 20, 50, 100]"
                    layout="total, sizes, prev, pager, next"
                    @change="fetchUsers"
                />
            </div>
        </el-card>

        <el-dialog v-model="showDialog" :title="isEditing ? t('users_page.edit_title') : t('users_page.create_title')" width="520px">
            <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px">
                <el-form-item :label="t('users_page.name')" prop="name">
                    <el-input v-model="form.name" :placeholder="t('users_page.name_ph')" />
                </el-form-item>
                <el-form-item :label="t('users_page.email')" prop="email">
                    <el-input v-model="form.email" :placeholder="t('users_page.email_ph')" :disabled="isEditing" />
                </el-form-item>
                <el-form-item :label="t('users_page.phone')" prop="phone">
                    <el-input v-model="form.phone" :placeholder="t('users_page.phone_ph')" />
                </el-form-item>
                <el-form-item :label="t('users_page.password')" prop="password" v-if="!isEditing">
                    <el-input v-model="form.password" type="password" :placeholder="t('users_page.password_ph')" show-password />
                </el-form-item>
                <el-form-item :label="t('users_page.status')" prop="status">
                    <el-radio-group v-model="form.status">
                        <el-radio value="active">{{ t('users_page.st_active') }}</el-radio>
                        <el-radio value="inactive">{{ t('users_page.deactivate') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t('users_page.roles')" prop="roles">
                    <el-select v-model="form.roles" multiple :placeholder="t('users_page.roles_ph')" style="width: 100%">
                        <el-option v-for="r in roles" :key="r.id" :label="r.name" :value="r.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('users_page.tenant')" prop="tenant_id" v-if="isSuperAdmin && !isEditing">
                    <el-select v-model="form.tenant_id" :placeholder="t('users_page.tenant_ph')" filterable style="width: 100%">
                        <el-option v-for="tn in tenants" :key="tn.id" :label="tn.name" :value="tn.id" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="confirmSubmit">{{ isEditing ? t('users_page.save') : t('users_page.create_btn') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="showPasswordDialog" :title="t('users_page.reset_title')" width="400px">
            <el-form ref="pwdFormRef" :model="pwdForm" :rules="pwdRules" label-width="100px">
                <el-form-item :label="t('users_page.new_password')" prop="password">
                    <el-input v-model="pwdForm.password" type="password" :placeholder="t('users_page.password_ph')" show-password />
                </el-form-item>
                <el-form-item :label="t('users_page.confirm_password')" prop="password_confirmation">
                    <el-input v-model="pwdForm.password_confirmation" type="password" :placeholder="t('users_page.confirm_ph')" show-password />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showPasswordDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="submittingPwd" @click="confirmResetPassword">{{ t('users_page.confirm_reset') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Plus } from '@element-plus/icons-vue';
import adminUserApi from '@/api/adminUser';
import { useAuthStore } from '@/stores/auth';

const { t } = useI18n();
const authStore = useAuthStore();
const currentUserId = computed(() => authStore.user?.id);
const isSuperAdmin = computed(() => authStore.userRoles?.includes('super-admin'));

const stats = reactive({ total: 0, active: 0, inactive: 0, locked: 0, recent_logins: 0 });
const statCards = computed(() => [
    { label: t('users_page.stat_total'), value: stats.total, color: '#0f172a' },
    { label: t('users_page.stat_active'), value: stats.active, color: '#67c23a' },
    { label: t('users_page.stat_inactive'), value: stats.inactive, color: '#909399' },
    { label: t('users_page.stat_locked'), value: stats.locked, color: '#f56c6c' },
    { label: t('users_page.stat_recent'), value: stats.recent_logins, color: '#e6a23c' },
]);

const users = ref([]);
const loading = ref(false);
const page = ref(1);
const perPage = ref(20);
const total = ref(0);
const filters = reactive({
    search: '',
    'filter.status': '',
    'filter.tenant_id': '',
});

const roles = ref([]);
const tenants = ref([]);

async function fetchRoles() {
    try {
        await adminUserApi.list({ per_page: 999 });
        const rolesRes = await import('@/api/permission').then(m => m.default.roles());
        roles.value = rolesRes.data?.data || [];
    } catch {
        // ignore
    }
}

async function fetchTenants() {
    if (!isSuperAdmin.value) return;
    try {
        const res = await import('@/api/tenant').then(m => m.default.list());
        tenants.value = res.data?.data || [];
    } catch {
        // ignore
    }
}

async function fetchUsers() {
    loading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            ...filters,
        };
        Object.keys(params).forEach(k => { if (!params[k] && params[k] !== false) delete params[k]; });

        const [usersRes, statsRes] = await Promise.all([
            adminUserApi.list(params),
            adminUserApi.stats(),
        ]);

        users.value = usersRes.data?.data || [];
        total.value = usersRes.data?.meta?.total || usersRes.data?.total || 0;
        Object.assign(stats, statsRes.data?.data || statsRes.data || {});
    } catch {
        ElMessage.error(t('users_page.load_fail'));
    } finally {
        loading.value = false;
    }
}

const showDialog = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const submitting = ref(false);
const formRef = ref(null);

const form = reactive({
    name: '',
    email: '',
    phone: '',
    password: '',
    status: 'active',
    roles: [],
    tenant_id: null,
});

const formRules = computed(() => ({
    name: [{ required: true, message: t('users_page.name_required'), trigger: 'blur' }],
    email: [
        { required: true, message: t('users_page.email_required'), trigger: 'blur' },
        { type: 'email', message: t('users_page.email_invalid'), trigger: 'blur' },
    ],
    password: [{ min: 8, message: t('users_page.password_min'), trigger: 'blur' }],
}));

function openCreate() {
    isEditing.value = false;
    editingId.value = null;
    form.name = '';
    form.email = '';
    form.phone = '';
    form.password = '';
    form.status = 'active';
    form.roles = [];
    form.tenant_id = null;
    showDialog.value = true;
}

async function openEdit(row) {
    isEditing.value = true;
    editingId.value = row.id;
    form.name = row.name;
    form.email = row.email;
    form.phone = row.phone || '';
    form.password = '';
    form.status = row.status;
    form.roles = row.roles?.map(r => r.id) || [];
    showDialog.value = true;
}

async function confirmSubmit() {
    const valid = await formRef.value?.validate().catch(() => false);
    if (!valid) return;

    submitting.value = true;
    try {
        const payload = {
            name: form.name,
            email: form.email,
            phone: form.phone || undefined,
            status: form.status,
            roles: form.roles.length > 0 ? form.roles : undefined,
        };
        if (!isEditing.value) {
            payload.password = form.password;
            if (isSuperAdmin.value && form.tenant_id) {
                payload.tenant_id = form.tenant_id;
            }
            await adminUserApi.create(payload);
            ElMessage.success(t('users_page.create_ok'));
        } else {
            if (form.password) payload.password = form.password;
            await adminUserApi.update(editingId.value, payload);
            ElMessage.success(t('users_page.update_ok'));
        }
        showDialog.value = false;
        await fetchUsers();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('users_page.action_fail'));
    } finally {
        submitting.value = false;
    }
}

const showPasswordDialog = ref(false);
const pwdForm = reactive({ password: '', password_confirmation: '' });
const submittingPwd = ref(false);
const pwdFormRef = ref(null);
const resettingUserId = ref(null);

const pwdRules = computed(() => ({
    password: [
        { required: true, message: t('users_page.pwd_required'), trigger: 'blur' },
        { min: 8, message: t('users_page.password_min'), trigger: 'blur' },
    ],
    password_confirmation: [
        { required: true, message: t('users_page.pwd_confirm_required'), trigger: 'blur' },
        {
            validator: (_, value) => value === pwdForm.password ? Promise.resolve() : Promise.reject(new Error(t('users_page.pwd_mismatch'))),
            trigger: 'blur',
        },
    ],
}));

function handleResetPassword(row) {
    resettingUserId.value = row.id;
    pwdForm.password = '';
    pwdForm.password_confirmation = '';
    showPasswordDialog.value = true;
}

async function confirmResetPassword() {
    const valid = await pwdFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    submittingPwd.value = true;
    try {
        await adminUserApi.resetPassword(resettingUserId.value, {
            password: pwdForm.password,
            password_confirmation: pwdForm.password_confirmation,
        });
        ElMessage.success(t('users_page.pwd_ok'));
        showPasswordDialog.value = false;
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('users_page.pwd_fail'));
    } finally {
        submittingPwd.value = false;
    }
}

async function handleToggleStatus(row) {
    const action = row.status === 'active' ? t('users_page.deactivate') : t('users_page.activate');
    try {
        await ElMessageBox.confirm(t('users_page.toggle_confirm', { action, name: row.name }), t('users_page.toggle_title'), { type: 'warning' });
        await adminUserApi.toggleStatus(row.id);
        ElMessage.success(t('users_page.toggle_ok', { action }));
        await fetchUsers();
    } catch {
        // cancelled
    }
}

onMounted(() => {
    fetchUsers();
    fetchRoles();
    fetchTenants();
});
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: 700; color: var(--el-text-color-primary); }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }

.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
}
.toolbar-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

.user-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}
.user-info {
    display: flex;
    flex-direction: column;
}
.user-name { font-size: 13px; font-weight: 500; }
.user-email { font-size: 12px; color: var(--el-text-color-secondary); }
.no-role { color: var(--el-text-color-placeholder); }
.no-login { color: var(--el-text-color-placeholder); font-style: italic; }

.pagination-wrap {
    display: flex;
    justify-content: center;
    margin-top: 16px;
}
</style>
