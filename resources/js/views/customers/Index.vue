<template>
    <div class="customers-page">
        <div class="page-header">
            <div class="header-left">
                <h2>客户管理</h2>
                <span class="header-subtitle">管理所有客户账户</span>
            </div>
            <div class="header-right">
                <el-button type="primary" @click="openCreateDialog">
                    <el-icon><Plus /></el-icon>
                    新建客户
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="stats-row" v-if="stats">
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats.total }}</div>
                    <div class="stat-label">总客户数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats.enterprise }}</div>
                    <div class="stat-label">企业客户</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats.individual }}</div>
                    <div class="stat-label">个人客户</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats.by_level?.pro || 0 }}</div>
                    <div class="stat-label">Pro 等级</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选区域 -->
        <el-card shadow="never" class="filter-card">
            <el-form :model="filters" inline>
                <el-form-item label="搜索">
                    <el-input
                        v-model="filters.search"
                        placeholder="客户名称 / 邮箱 / 电话"
                        clearable
                        style="width: 220px"
                        @keyup.enter="doSearch"
                    />
                </el-form-item>
                <el-form-item label="类型">
                    <el-select v-model="filters.type" clearable placeholder="全部类型" style="width: 130px" @change="doSearch">
                        <el-option label="企业" value="enterprise" />
                        <el-option label="个人" value="individual" />
                    </el-select>
                </el-form-item>
                <el-form-item label="等级">
                    <el-select v-model="filters.level" clearable placeholder="全部等级" style="width: 130px" @change="doSearch">
                        <el-option label="Free" value="free" />
                        <el-option label="Pro" value="pro" />
                        <el-option label="Enterprise" value="enterprise" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="filters.status" clearable placeholder="全部状态" style="width: 120px" @change="doSearch">
                        <el-option label="启用" value="active" />
                        <el-option label="停用" value="inactive" />
                        <el-option label="暂停" value="suspended" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="doSearch">
                        <el-icon><Search /></el-icon>
                        搜索
                    </el-button>
                    <el-button @click="resetFilters">重置</el-button>
                </el-form-item>
            </el-form>
            <div class="filter-bar-footer">
                <SavedSearchBar
                    page="customers"
                    :current-filters="filters"
                    @apply="applySavedFilters"
                />
            </div>
        </el-card>

        <!-- 表格 -->
        <el-card shadow="never">
            <el-table
                :data="customers"
                v-loading="loading"
                stripe
                row-key="id"
                @sort-change="handleSortChange"
            >
                <el-table-column type="selection" width="40" />
                <el-table-column label="客户" min-width="220">
                    <template #default="{ row }">
                        <div class="customer-info" style="display: flex; align-items: center; gap: 10px;">
                            <el-avatar :size="32" :src="row.user?.avatar_url" shape="square">
                                <span class="avatar-initial">{{ (row.user?.name || '?').charAt(0).toUpperCase() }}</span>
                                <template #error>{{ (row.user?.name || '?').charAt(0).toUpperCase() }}</template>
                            </el-avatar>
                            <div>
                                <div class="customer-name">
                                    <el-link type="primary" @click="$router.push(`/customers/${row.id}`)">
                                        {{ row.user?.name || '未关联用户' }}
                                    </el-link>
                                </div>
                                <div class="customer-contact" v-if="row.user?.email || row.user?.phone">
                                    {{ row.user?.email }}
                                    <template v-if="row.user?.email && row.user?.phone"> · </template>
                                    {{ row.user?.phone }}
                                </div>
                            </div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="类型" width="90" prop="type" sortable="custom">
                    <template #default="{ row }">
                        <el-tag :type="row.type === 'enterprise' ? 'warning' : 'info'" size="small">
                            {{ row.type === 'enterprise' ? '企业' : '个人' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="等级" width="100" prop="level" sortable="custom">
                    <template #default="{ row }">
                        <el-tag :type="levelTagType(row.level)" size="small">
                            {{ levelLabel(row.level) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="80" prop="status" sortable="custom">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'active' ? 'success' : row.status === 'suspended' ? 'danger' : 'info'" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="License 数" width="100" prop="licenses_count" sortable="custom">
                    <template #default="{ row }">
                        <el-tag type="primary" effect="plain" size="small">{{ row.licenses_count || 0 }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="创建时间" width="170" prop="created_at" sortable="custom">
                    <template #default="{ row }">
                        {{ formatDate(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="$router.push(`/customers/${row.id}`)">
                            详情
                        </el-button>
                        <el-button text size="small" type="primary" @click="openEditDialog(row)">
                            编辑
                        </el-button>
                        <el-dropdown ref="moreActionRef" trigger="click" @command="(cmd) => handleStatusAction(cmd, row)">
                            <el-button text size="small" type="primary">
                                更多 <el-icon><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item
                                        v-if="row.status === 'active'"
                                        command="suspend"
                                        divided
                                    >
                                        停用
                                    </el-dropdown-item>
                                    <el-dropdown-item
                                        v-if="row.status === 'suspended' || row.status === 'inactive'"
                                        command="activate"
                                    >
                                        启用
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 批量操作栏 -->
            <div class="batch-bar" v-if="selectedIds.length > 0">
                <span class="batch-info">已选择 {{ selectedIds.length }} 项</span>
                <el-button size="small" @click="clearSelection">取消选择</el-button>
                <el-button size="small" type="warning" @click="batchAction('inactive')">批量停用</el-button>
                <el-button size="small" type="success" @click="batchAction('active')">批量启用</el-button>
            </div>

            <!-- 分页 -->
            <div class="pagination-wrapper" v-if="total > 0">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :page-sizes="[10, 20, 50, 100]"
                    :total="total"
                    layout="total, sizes, prev, pager, next, jumper"
                    @size-change="loadCustomers"
                    @current-change="loadCustomers"
                />
            </div>
        </el-card>

        <!-- 创建/编辑 Dialog -->
        <el-dialog
            v-model="dialogVisible"
            :title="editingId ? '编辑客户' : '新建客户'"
            width="500px"
            :close-on-click-modal="false"
        >
            <el-form
                ref="formRef"
                :model="form"
                :rules="formRules"
                label-width="100px"
                label-position="right"
            >
                <el-form-item label="客户类型" prop="type">
                    <el-radio-group v-model="form.type">
                        <el-radio value="individual">个人</el-radio>
                        <el-radio value="enterprise">企业</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="等级" prop="level">
                    <el-select v-model="form.level" style="width: 100%">
                        <el-option label="Free" value="free" />
                        <el-option label="Pro" value="pro" />
                        <el-option label="Enterprise" value="enterprise" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态" prop="status">
                    <el-select v-model="form.status" style="width: 100%">
                        <el-option label="启用" value="active" />
                        <el-option label="停用" value="inactive" />
                        <el-option label="暂停" value="suspended" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="submitForm">
                    {{ editingId ? '保存' : '创建' }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Plus, ArrowDown } from '@element-plus/icons-vue';
import customerApi from '@/api/customer';
import SavedSearchBar from '@/components/SavedSearchBar.vue';

const router = useRouter();

const loading = ref(false);
const customers = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);
const selectedIds = ref([]);
const submitting = ref(false);
const dialogVisible = ref(false);
const editingId = ref(null);
const formRef = ref(null);
const stats = ref(null);

const filters = reactive({
    search: '',
    type: '',
    level: '',
    status: '',
});
const sortField = ref('-created_at');

const form = reactive({
    type: 'individual',
    level: 'free',
    status: 'active',
});

const formRules = {
    type: [{ required: true, message: '请选择客户类型', trigger: 'change' }],
    level: [{ required: true, message: '请选择等级', trigger: 'change' }],
    status: [{ required: true, message: '请选择状态', trigger: 'change' }],
};

// 标签样式映射
function levelTagType(level) {
    const map = { free: 'info', pro: 'primary', enterprise: 'warning' };
    return map[level] || 'info';
}

function levelLabel(level) {
    const map = { free: 'Free', pro: 'Pro', enterprise: 'Enterprise' };
    return map[level] || level;
}

function statusLabel(status) {
    const map = { active: '启用', inactive: '停用', suspended: '暂停' };
    return map[status] || status;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

async function loadStats() {
    try {
        const { data: res } = await customerApi.stats();
        if (res.success) stats.value = res.data;
    } catch {
        // ignore
    }
}

async function loadCustomers() {
    loading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            sort: sortField.value,
        };
        if (filters.search) params.search = filters.search;
        if (filters.type) params['filter.type'] = filters.type;
        if (filters.level) params['filter.level'] = filters.level;
        if (filters.status) params['filter.status'] = filters.status;

        const { data: res } = await customerApi.list(params);
        customers.value = res.data?.data || [];
        total.value = res.data?.total || 0;
    } catch {
        customers.value = [];
    } finally {
        loading.value = false;
    }
}

function doSearch() {
    page.value = 1;
    loadCustomers();
}

function resetFilters() {
    filters.search = '';
    filters.type = '';
    filters.level = '';
    filters.status = '';
    doSearch();
}

// 应用保存的搜索
function applySavedFilters(savedFilters) {
    for (const [key, value] of Object.entries(savedFilters)) {
        if (key in filters) {
            filters[key] = value;
        }
    }
    doSearch();
}

function handleSortChange({ prop, order }) {
    if (!order) {
        sortField.value = '-created_at';
    } else {
        sortField.value = (order === 'desc' ? '-' : '') + (prop || 'created_at');
    }
    loadCustomers();
}

function clearSelection() {
    selectedIds.value = [];
}

// 创建/编辑 Dialog
function openCreateDialog() {
    editingId.value = null;
    form.type = 'individual';
    form.level = 'free';
    form.status = 'active';
    dialogVisible.value = true;
}

function openEditDialog(row) {
    editingId.value = row.id;
    form.type = row.type;
    form.level = row.level;
    form.status = row.status;
    dialogVisible.value = true;
}

async function submitForm() {
    const valid = await formRef.value.validate().catch(() => false);
    if (!valid) return;

    submitting.value = true;
    try {
        const payload = { ...form };
        if (editingId.value) {
            await customerApi.update(editingId.value, payload);
            ElMessage.success('客户更新成功');
        } else {
            await customerApi.create(payload);
            ElMessage.success('客户创建成功');
        }
        dialogVisible.value = false;
        loadCustomers();
        loadStats();
    } catch {
        // error handled by interceptor
    } finally {
        submitting.value = false;
    }
}

// 状态操作
async function handleStatusAction(cmd, row) {
    const actions = {
        suspend: { label: '停用', status: 'inactive', confirm: '确定要停用该客户吗？' },
        activate: { label: '启用', status: 'active', confirm: '确定要启用该客户吗？' },
    };
    const action = actions[cmd];
    if (!action) return;

    try {
        await ElMessageBox.confirm(action.confirm, '确认操作', {
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            type: 'warning',
        });
        await customerApi.update(row.id, { ...row, status: action.status });
        ElMessage.success(`${action.label}成功`);
        loadCustomers();
        loadStats();
    } catch {
        // cancelled or error
    }
}

// 批量操作
async function batchAction(targetStatus) {
    if (selectedIds.value.length === 0) {
        ElMessage.warning('请先选择客户');
        return;
    }
    const label = targetStatus === 'active' ? '启用' : '停用';
    try {
        await ElMessageBox.confirm(
            `确定要${label}选中的 ${selectedIds.value.length} 个客户吗？`,
            '批量操作',
            { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
        );
        for (const id of selectedIds.value) {
            await customerApi.update(id, { status: targetStatus });
        }
        ElMessage.success(`批量${label}成功`);
        clearSelection();
        loadCustomers();
        loadStats();
    } catch {
        // cancelled or error
    }
}

onMounted(() => {
    loadCustomers();
    loadStats();
});
</script>

<style scoped>
.customers-page { padding: 20px; }

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

.stats-row {
    margin-bottom: 16px;
}
.stat-card {
    text-align: center;
}
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--el-color-primary);
    line-height: 1.2;
}
.stat-label {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-top: 4px;
}

.filter-card {
    margin-bottom: 16px;
}

.customer-info {
    line-height: 1.4;
}
.customer-name {
    font-weight: 500;
}
.customer-contact {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-top: 2px;
}

.batch-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 0 0;
    border-top: 1px solid var(--el-border-color-lighter);
    margin-top: 12px;
}
.batch-info {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-right: 8px;
}

.pagination-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}

.el-card :deep(.el-card__body) {
    padding: 16px;
}

.filter-card :deep(.el-card__body) {
    padding: 12px 16px;
}

:deep(.el-form--inline .el-form-item) {
    margin-bottom: 0;
}
.filter-bar-footer {
    display: flex;
    justify-content: flex-end;
    padding-top: 8px;
    border-top: 1px solid var(--el-border-color-extra-light);
    margin-top: 8px;
}
</style>
