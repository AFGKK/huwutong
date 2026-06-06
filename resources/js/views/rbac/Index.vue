<template>
    <div class="rbac-page">
        <div class="page-header">
            <div class="header-left">
                <h2>权限管理</h2>
                <span class="header-subtitle">角色定义与用户权限分配</span>
            </div>
            <div class="header-right">
                <el-button type="primary" @click="activeTab = 'roles'; openRoleDialog()">
                    <el-icon><Plus /></el-icon>
                    新建角色
                </el-button>
            </div>
        </div>

        <el-card shadow="never">
            <el-tabs v-model="activeTab" @tab-change="handleTabChange">
                <el-tab-pane label="角色管理" name="roles">
                    <template #label>
                        <span><el-icon><UserFilled /></el-icon> 角色管理</span>
                    </template>

                    <!-- 搜索框 -->
                    <div class="toolbar">
                        <el-input
                            v-model="roleSearch"
                            placeholder="搜索角色名称"
                            clearable
                            style="width: 240px"
                            @keyup.enter="loadRoles"
                        />
                        <el-button type="primary" @click="loadRoles">
                            <el-icon><Search /></el-icon> 搜索
                        </el-button>
                    </div>

                    <el-table :data="roles" v-loading="rolesLoading" stripe>
                        <el-table-column label="角色名称" min-width="200" prop="name">
                            <template #default="{ row }">
                                <div class="role-name-cell">
                                    <el-tag :type="roleTagType(row.name)" size="small" effect="dark">
                                        {{ row.name }}
                                    </el-tag>
                                    <el-tag
                                        v-if="['super-admin', 'tenant-admin', 'finance', 'developer', 'readonly'].includes(row.name)"
                                        size="small"
                                        type="info"
                                        effect="plain"
                                        style="margin-left: 6px;"
                                    >
                                        系统
                                    </el-tag>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="权限数" width="100">
                            <template #default="{ row }">
                                <el-tag type="primary" effect="plain" size="small">
                                    {{ row.permissions?.length || 0 }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="创建时间" width="170" prop="created_at">
                            <template #default="{ row }">
                                {{ formatDate(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="220" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openRoleDialog(row)">
                                    编辑权限
                                </el-button>
                                <el-button
                                    v-if="!['super-admin', 'tenant-admin', 'finance', 'developer', 'readonly'].includes(row.name)"
                                    text
                                    size="small"
                                    type="danger"
                                    @click="handleDeleteRole(row)"
                                >
                                    删除
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-wrapper" v-if="roleTotal > 0">
                        <el-pagination
                            v-model:current-page="rolePage"
                            v-model:page-size="rolePerPage"
                            :total="roleTotal"
                            size="small"
                            layout="total, prev, pager, next"
                            @current-change="loadRoles"
                        />
                    </div>
                </el-tab-pane>

                <el-tab-pane label="用户角色" name="users">
                    <template #label>
                        <span><el-icon><User /></el-icon> 用户角色</span>
                    </template>

                    <el-table :data="tenantUsers" v-loading="usersLoading" stripe>
                        <el-table-column label="用户" min-width="200">
                            <template #default="{ row }">
                                <div>
                                    <span class="user-name">{{ row.name }}</span>
                                    <div class="user-email">{{ row.email }}</div>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="当前角色" min-width="250">
                            <template #default="{ row }">
                                <el-tag
                                    v-for="role in (row.roles || [])"
                                    :key="role.id"
                                    size="small"
                                    :type="roleTagType(role.name)"
                                    style="margin: 2px 4px 2px 0;"
                                >
                                    {{ role.name }}
                                </el-tag>
                                <span v-if="!row.roles?.length" class="text-muted">无角色</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">
                                    {{ row.status === 'active' ? '启用' : '停用' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="140" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openUserRoleDialog(row)">
                                    分配角色
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- 角色编辑 Dialog（选择权限） -->
        <el-dialog
            v-model="roleDialogVisible"
            :title="editingRole ? '编辑角色权限' : '新建角色'"
            width="650px"
            :close-on-click-modal="false"
        >
            <el-form
                ref="roleFormRef"
                :model="roleForm"
                :rules="roleFormRules"
                label-width="100px"
                label-position="right"
            >
                <el-form-item label="角色名称" prop="name">
                    <el-input v-model="roleForm.name" :disabled="!!editingRole && isSystemRole" placeholder="如：support-agent" />
                </el-form-item>
                <el-form-item label="权限">
                    <div class="permission-tree" v-if="permissionGroups.length">
                        <div
                            v-for="(perms, group) in permissionGroups"
                            :key="group"
                            class="perm-group"
                        >
                            <div class="perm-group-header">
                                <el-checkbox
                                    :indeterminate="isIndeterminate(group)"
                                    :model-value="isGroupAllChecked(group)"
                                    @change="(val) => toggleGroup(group, val)"
                                >
                                    <span class="perm-group-label">{{ groupLabel(group) }}</span>
                                </el-checkbox>
                            </div>
                            <div class="perm-items">
                                <el-checkbox
                                    v-for="perm in perms"
                                    :key="perm.name"
                                    v-model="roleForm.permissions"
                                    :label="perm.name"
                                    size="small"
                                    class="perm-checkbox"
                                >
                                    {{ permLabel(perm.name) }}
                                </el-checkbox>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-muted">加载权限中...</div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="roleDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="roleSubmitting" @click="submitRole">
                    {{ editingRole ? '保存' : '创建' }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 用户角色分配 Dialog -->
        <el-dialog
            v-model="userRoleDialogVisible"
            title="分配角色"
            width="450px"
            :close-on-click-modal="false"
        >
            <div v-if="editingUser">
                <div class="dialog-user-info">
                    <el-avatar :size="40" icon="UserFilled" />
                    <div>
                        <div class="user-name">{{ editingUser.name }}</div>
                        <div class="user-email">{{ editingUser.email }}</div>
                    </div>
                </div>
                <el-divider />
                <el-checkbox-group v-model="selectedRoles" class="role-checkbox-group">
                    <el-checkbox
                        v-for="role in roles"
                        :key="role.id"
                        :label="role.name"
                        class="role-checkbox"
                    >
                        <el-tag :type="roleTagType(role.name)" size="small">
                            {{ role.name }}
                        </el-tag>
                        <span class="role-perm-count">{{ role.permissions?.length || 0 }} 个权限</span>
                    </el-checkbox>
                </el-checkbox-group>
            </div>
            <template #footer>
                <el-button @click="userRoleDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="userRoleSubmitting" @click="submitUserRoles">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Plus, UserFilled, User } from '@element-plus/icons-vue';
import permissionApi from '@/api/permission';

const activeTab = ref('roles');

// ─── Roles ───
const roles = ref([]);
const rolesLoading = ref(false);
const roleTotal = ref(0);
const rolePage = ref(1);
const rolePerPage = ref(50);
const roleSearch = ref('');

// ─── Users ───
const tenantUsers = ref([]);
const usersLoading = ref(false);

// ─── Permissions ───
const allPermissions = ref([]);
const permissionGroups = computed(() => {
    const groups = {};
    for (const p of allPermissions.value) {
        const group = p.name.split('.')[0] || 'other';
        if (!groups[group]) groups[group] = [];
        groups[group].push(p);
    }
    return groups;
});

// ─── Role Dialog ───
const roleDialogVisible = ref(false);
const roleSubmitting = ref(false);
const editingRole = ref(null);
const roleFormRef = ref(null);
const roleForm = reactive({
    name: '',
    permissions: [],
});
const roleFormRules = {
    name: [{ required: true, message: '请输入角色名称', trigger: 'blur' }],
};

// ─── User Role Dialog ───
const userRoleDialogVisible = ref(false);
const userRoleSubmitting = ref(false);
const editingUser = ref(null);
const selectedRoles = ref([]);

// Helper
const systemRoles = ['super-admin', 'tenant-admin', 'finance', 'developer', 'readonly'];

function roleTagType(name) {
    const map = {
        'super-admin': 'danger',
        'tenant-admin': 'warning',
        'finance': 'success',
        'developer': 'primary',
        'readonly': 'info',
    };
    return map[name] || '';
}

function groupLabel(group) {
    const map = {
        tenants: '租户管理',
        users: '用户管理',
        customers: '客户管理',
        products: '产品管理',
        licenses: 'License 管理',
        devices: '设备管理',
        subscriptions: '订阅管理',
        invoices: '发票管理',
        earnings: '收益管理',
        logs: '日志管理',
        rbac: '权限管理',
        settings: '系统设置',
    };
    return map[group] || group;
}

function permLabel(name) {
    const map = {
        view: '查看',
        create: '创建',
        edit: '编辑',
        delete: '删除',
        activate: '激活',
        deactivate: '停用',
        revoke: '撤销',
        blacklist: '黑名单',
        cancel: '取消',
        refund: '退款',
        withdraw: '提现',
        approve_withdrawal: '审批提现',
        export: '导出',
    };
    const parts = name.split('.');
    const action = parts[parts.length - 1];
    return map[action] || action;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

const isSystemRole = computed(() => editingRole.value && systemRoles.includes(editingRole.value.name));

function isIndeterminate(group) {
    const perms = permissionGroups.value[group] || [];
    const checked = perms.filter(p => roleForm.permissions.includes(p.name)).length;
    return checked > 0 && checked < perms.length;
}

function isGroupAllChecked(group) {
    const perms = permissionGroups.value[group] || [];
    return perms.length > 0 && perms.every(p => roleForm.permissions.includes(p.name));
}

function toggleGroup(group, value) {
    const perms = permissionGroups.value[group] || [];
    const permNames = perms.map(p => p.name);
    if (value) {
        // 添加所有
        for (const name of permNames) {
            if (!roleForm.permissions.includes(name)) {
                roleForm.permissions.push(name);
            }
        }
    } else {
        // 移除所有
        roleForm.permissions = roleForm.permissions.filter(p => !permNames.includes(p));
    }
}

// 加载角色
async function loadRoles() {
    rolesLoading.value = true;
    try {
        const params = { page: rolePage.value, per_page: rolePerPage.value };
        if (roleSearch.value) params.search = roleSearch.value;
        const { data: res } = await permissionApi.roles(params);
        roles.value = res.data?.data || [];
        roleTotal.value = res.data?.total || 0;
    } catch {
        roles.value = [];
    } finally {
        rolesLoading.value = false;
    }
}

// 加载权限
async function loadPermissions() {
    try {
        const { data: res } = await permissionApi.allPermissions();
        if (res.success) {
            allPermissions.value = res.data.all || [];
        }
    } catch {
        // ignore
    }
}

// 加载用户
async function loadUsers() {
    usersLoading.value = true;
    try {
        const { data: res } = await permissionApi.tenantUsers();
        if (res.success) {
            tenantUsers.value = res.data || [];
        }
    } catch {
        tenantUsers.value = [];
    } finally {
        usersLoading.value = false;
    }
}

// Tab 切换
function handleTabChange(tab) {
    if (tab === 'users' && tenantUsers.value.length === 0) {
        loadUsers();
    }
}

// 角色 Dialog
async function openRoleDialog(role = null) {
    editingRole.value = role;
    roleForm.name = role ? role.name : '';
    roleForm.permissions = role ? (role.permissions || []).map(p => p.name || p) : [];

    // 确保权限已加载
    if (allPermissions.value.length === 0) {
        await loadPermissions();
    }

    roleDialogVisible.value = true;
}

async function submitRole() {
    const valid = await roleFormRef.value.validate().catch(() => false);
    if (!valid) return;

    roleSubmitting.value = true;
    try {
        const payload = {
            name: roleForm.name,
            permissions: roleForm.permissions,
        };
        if (editingRole.value) {
            await permissionApi.roleUpdate(editingRole.value.id, payload);
            ElMessage.success('角色更新成功');
        } else {
            await permissionApi.roleCreate(payload);
            ElMessage.success('角色创建成功');
        }
        roleDialogVisible.value = false;
        loadRoles();
    } catch {
        // handled by interceptor
    } finally {
        roleSubmitting.value = false;
    }
}

async function handleDeleteRole(row) {
    try {
        await ElMessageBox.confirm(
            `确定要删除角色 "${row.name}" 吗？`,
            '确认删除',
            { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
        );
        await permissionApi.roleDelete(row.id);
        ElMessage.success('角色已删除');
        loadRoles();
    } catch {
        // cancelled
    }
}

// 用户角色分配
async function openUserRoleDialog(user) {
    editingUser.value = user;
    selectedRoles.value = (user.roles || []).map(r => r.name);
    userRoleDialogVisible.value = true;
}

async function submitUserRoles() {
    if (!editingUser.value) return;

    userRoleSubmitting.value = true;
    try {
        await permissionApi.assignRoles(editingUser.value.id, selectedRoles.value);
        ElMessage.success('角色分配成功');
        userRoleDialogVisible.value = false;
        loadUsers();
    } catch {
        // handled by interceptor
    } finally {
        userRoleSubmitting.value = false;
    }
}

onMounted(() => {
    loadRoles();
    loadPermissions();
});
</script>

<style scoped>
.rbac-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 {
    margin: 0;
    font-size: 20px;
}
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.toolbar {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}

.role-name-cell {
    display: flex;
    align-items: center;
}

.permission-tree {
    max-height: 450px;
    overflow-y: auto;
    padding: 4px 0;
}

.perm-group {
    margin-bottom: 12px;
    padding: 8px 12px;
    border: 1px solid var(--el-border-color-light);
    border-radius: 6px;
}
.perm-group-header {
    margin-bottom: 8px;
    font-weight: 500;
}
.perm-group-label {
    font-size: 14px;
}
.perm-items {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding-left: 24px;
}
.perm-checkbox {
    margin-right: 12px !important;
}

.dialog-user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}
.user-name {
    font-weight: 500;
}
.user-email {
    font-size: 13px;
    color: var(--el-text-color-secondary);
}

.role-checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.role-checkbox {
    margin-right: 0 !important;
    padding: 8px 12px;
    border: 1px solid var(--el-border-color-light);
    border-radius: 6px;
    transition: all 0.2s;
}
.role-checkbox:hover {
    border-color: var(--el-color-primary);
    background: var(--el-color-primary-light-9);
}
.role-perm-count {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-left: 8px;
}

.text-muted { color: var(--el-text-color-placeholder); }

.pagination-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-top: 12px;
}

:deep(.el-card__body) { padding: 16px; }
</style>
