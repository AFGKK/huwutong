<template>
    <div class="users-page">
        <!-- 统计卡片 -->
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

        <!-- 筛选与操作栏 -->
        <el-card shadow="never">
            <div class="toolbar">
                <el-form :inline="true" :model="filters" size="small">
                    <el-form-item>
                        <el-input
                            v-model="filters.search"
                            placeholder="搜索用户（姓名/邮箱/手机）"
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
                        <el-select v-model="filters['filter.status']" placeholder="状态" clearable @change="fetchUsers" style="width: 120px">
                            <el-option label="活跃" value="active" />
                            <el-option label="已停用" value="inactive" />
                            <el-option label="已锁定" value="locked" />
                        </el-select>
                    </el-form-item>
                    <el-form-item v-if="isSuperAdmin">
                        <el-select v-model="filters['filter.tenant_id']" placeholder="租户" clearable filterable @change="fetchUsers" style="width: 200px">
                            <el-option v-for="t in tenants" :key="t.id" :label="t.name" :value="t.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="fetchUsers">
                            <el-icon><Search /></el-icon>查询
                        </el-button>
                    </el-form-item>
                </el-form>
                <div class="toolbar-actions">
                    <el-button type="primary" @click="openCreate">
                        <el-icon><Plus /></el-icon>新建用户
                    </el-button>
                </div>
            </div>

            <!-- 用户表格 -->
            <el-table :data="users" v-loading="loading" stripe style="width: 100%">
                <el-table-column prop="id" label="ID" width="60" />
                <el-table-column label="用户" min-width="180">
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
                <el-table-column prop="phone" label="手机" width="130" />
                <el-table-column label="租户" width="150" v-if="isSuperAdmin">
                    <template #default="{ row }">
                        {{ row.tenant?.name || '-' }}
                    </template>
                </el-table-column>
                <el-table-column label="角色" width="180">
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
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'active' ? 'success' : row.status === 'locked' ? 'danger' : 'info'" size="small">
                            {{ row.status === 'active' ? '活跃' : row.status === 'locked' ? '已锁定' : '已停用' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="last_login_at" label="最后登录" width="170">
                    <template #default="{ row }">
                        <span v-if="row.last_login_at">{{ row.last_login_at }}</span>
                        <span v-else class="no-login">从未登录</span>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="170" />
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="openEdit(row)">编辑</el-button>
                        <el-button text size="small" type="warning" @click="handleResetPassword(row)" v-if="row.id !== currentUserId">改密</el-button>
                        <el-button
                            text
                            size="small"
                            :type="row.status === 'active' ? 'danger' : 'success'"
                            @click="handleToggleStatus(row)"
                            v-if="row.id !== currentUserId"
                        >
                            {{ row.status === 'active' ? '停用' : '启用' }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 分页 -->
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

        <!-- 创建/编辑对话框 -->
        <el-dialog v-model="showDialog" :title="isEditing ? '编辑用户' : '新建用户'" width="520px">
            <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px">
                <el-form-item label="姓名" prop="name">
                    <el-input v-model="form.name" placeholder="输入用户姓名" />
                </el-form-item>
                <el-form-item label="邮箱" prop="email">
                    <el-input v-model="form.email" placeholder="输入邮箱地址" :disabled="isEditing" />
                </el-form-item>
                <el-form-item label="手机" prop="phone">
                    <el-input v-model="form.phone" placeholder="输入手机号码" />
                </el-form-item>
                <el-form-item label="密码" prop="password" v-if="!isEditing">
                    <el-input v-model="form.password" type="password" placeholder="至少 8 位" show-password />
                </el-form-item>
                <el-form-item label="状态" prop="status">
                    <el-radio-group v-model="form.status">
                        <el-radio value="active">活跃</el-radio>
                        <el-radio value="inactive">停用</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="角色" prop="roles">
                    <el-select v-model="form.roles" multiple placeholder="选择角色" style="width: 100%">
                        <el-option v-for="r in roles" :key="r.id" :label="r.name" :value="r.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="租户" prop="tenant_id" v-if="isSuperAdmin && !isEditing">
                    <el-select v-model="form.tenant_id" placeholder="选择租户" filterable style="width: 100%">
                        <el-option v-for="t in tenants" :key="t.id" :label="t.name" :value="t.id" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showDialog = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="confirmSubmit">{{ isEditing ? '保存修改' : '创建用户' }}</el-button>
            </template>
        </el-dialog>

        <!-- 重置密码对话框 -->
        <el-dialog v-model="showPasswordDialog" title="重置密码" width="400px">
            <el-form ref="pwdFormRef" :model="pwdForm" :rules="pwdRules" label-width="100px">
                <el-form-item label="新密码" prop="password">
                    <el-input v-model="pwdForm.password" type="password" placeholder="至少 8 位" show-password />
                </el-form-item>
                <el-form-item label="确认密码" prop="password_confirmation">
                    <el-input v-model="pwdForm.password_confirmation" type="password" placeholder="再次输入新密码" show-password />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showPasswordDialog = false">取消</el-button>
                <el-button type="primary" :loading="submittingPwd" @click="confirmResetPassword">确认重置</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Plus } from '@element-plus/icons-vue';
import adminUserApi from '@/api/adminUser';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();
const currentUserId = computed(() => authStore.user?.id);
const isSuperAdmin = computed(() => authStore.userRoles?.includes('super-admin'));

// ─── 统计卡片 ───
const stats = reactive({ total: 0, active: 0, inactive: 0, locked: 0, recent_logins: 0 });
const statCards = computed(() => [
    { label: '总用户', value: stats.total, color: '#409eff' },
    { label: '活跃', value: stats.active, color: '#67c23a' },
    { label: '已停用', value: stats.inactive, color: '#909399' },
    { label: '已锁定', value: stats.locked, color: '#f56c6c' },
    { label: '7 天内登录', value: stats.recent_logins, color: '#e6a23c' },
]);

// ─── 列表 ───
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
        const res = await adminUserApi.list({ per_page: 999 });
        // roles from PermissionController
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
        // 移除空筛选
        Object.keys(params).forEach(k => { if (!params[k] && params[k] !== false) delete params[k]; });

        const [usersRes, statsRes] = await Promise.all([
            adminUserApi.list(params),
            adminUserApi.stats(),
        ]);

        users.value = usersRes.data?.data || [];
        total.value = usersRes.data?.meta?.total || usersRes.data?.total || 0;
        Object.assign(stats, statsRes.data?.data || statsRes.data || {});
    } catch {
        ElMessage.error('获取用户列表失败');
    } finally {
        loading.value = false;
    }
}

// ─── 创建 / 编辑 ───
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

const formRules = {
    name: [{ required: true, message: '请输入姓名', trigger: 'blur' }],
    email: [
        { required: true, message: '请输入邮箱', trigger: 'blur' },
        { type: 'email', message: '邮箱格式不正确', trigger: 'blur' },
    ],
    password: [{ min: 8, message: '密码至少 8 位', trigger: 'blur' }],
};

function openCreate() {
    isEditing.value = false;
    editingId.value = null;
    form.name = '';
    form.email = '';
    form.phone = '';
    form.password = '';
    form.status = 'active';
    form.roles = [];
    form.tenant_id = isSuperAdmin.value ? null : null;
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
            ElMessage.success('用户创建成功');
        } else {
            if (form.password) payload.password = form.password;
            await adminUserApi.update(editingId.value, payload);
            ElMessage.success('用户更新成功');
        }
        showDialog.value = false;
        await fetchUsers();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '操作失败');
    } finally {
        submitting.value = false;
    }
}

// ─── 重置密码 ───
const showPasswordDialog = ref(false);
const pwdForm = reactive({ password: '', password_confirmation: '' });
const submittingPwd = ref(false);
const pwdFormRef = ref(null);
const resettingUserId = ref(null);

const pwdRules = {
    password: [
        { required: true, message: '请输入新密码', trigger: 'blur' },
        { min: 8, message: '密码至少 8 位', trigger: 'blur' },
    ],
    password_confirmation: [
        { required: true, message: '请确认新密码', trigger: 'blur' },
        {
            validator: (_, value) => value === pwdForm.password ? Promise.resolve() : Promise.reject(new Error('两次密码输入不一致')),
            trigger: 'blur',
        },
    ],
};

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
        ElMessage.success('密码已重置');
        showPasswordDialog.value = false;
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '密码重置失败');
    } finally {
        submittingPwd.value = false;
    }
}

// ─── 启用/停用 ───
async function handleToggleStatus(row) {
    const action = row.status === 'active' ? '停用' : '启用';
    try {
        await ElMessageBox.confirm(`确定要${action}用户「${row.name}」吗？`, '确认操作', { type: 'warning' });
        await adminUserApi.toggleStatus(row.id);
        ElMessage.success(`用户已${action}`);
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
