<template>
    <div class="customers-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('customers_page.title') }}</h2>
                <span class="header-subtitle">{{ t('customers_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button type="primary" @click="openCreateDialog">
                    <el-icon><Plus /></el-icon>
                    {{ t('customers_page.create_btn') }}
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="stats-row" v-if="stats">
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats.total }}</div>
                    <div class="stat-label">{{ t('customers_page.stat_total') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats.enterprise }}</div>
                    <div class="stat-label">{{ t('customers_page.stat_enterprise') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats.individual }}</div>
                    <div class="stat-label">{{ t('customers_page.stat_individual') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats.by_level?.pro || 0 }}</div>
                    <div class="stat-label">{{ t('customers_page.stat_pro_level') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选区域 -->
        <el-card shadow="never" class="filter-card">
            <el-form :model="filters" inline>
                <el-form-item :label="t('actions.search')">
                    <el-input
                        v-model="filters.search"
                        :placeholder="t('customers_page.search_ph')"
                        clearable
                        style="width: 220px"
                        @keyup.enter="doSearch"
                    />
                </el-form-item>
                <el-form-item :label="t('customers_page.type')">
                    <el-select v-model="filters.type" clearable :placeholder="t('customers_page.all_types')" style="width: 130px" @change="doSearch">
                        <el-option :label="t('customers_page.type_enterprise')" value="enterprise" />
                        <el-option :label="t('customers_page.type_individual')" value="individual" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('customers_page.level')">
                    <el-select v-model="filters.level" clearable :placeholder="t('customers_page.all_levels')" style="width: 130px" @change="doSearch">
                        <el-option :label="t('customers_page.level_free')" value="free" />
                        <el-option :label="t('customers_page.level_pro')" value="pro" />
                        <el-option :label="t('customers_page.level_enterprise')" value="enterprise" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('customers_page.status')">
                    <el-select v-model="filters.status" clearable :placeholder="t('customers_page.all_status')" style="width: 120px" @change="doSearch">
                        <el-option :label="t('customers_page.st_active')" value="active" />
                        <el-option :label="t('customers_page.st_inactive')" value="inactive" />
                        <el-option :label="t('customers_page.st_suspended')" value="suspended" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="doSearch">
                        <el-icon><Search /></el-icon>
                        {{ t('actions.search') }}
                    </el-button>
                    <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
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
                <el-table-column :label="t('customers_page.col_customer')" min-width="220">
                    <template #default="{ row }">
                        <div class="customer-info" style="display: flex; align-items: center; gap: 10px;">
                            <el-avatar :size="32" :src="row.user?.avatar_url" shape="square">
                                <span class="avatar-initial">{{ (row.user?.name || '?').charAt(0).toUpperCase() }}</span>
                                <template #error>{{ (row.user?.name || '?').charAt(0).toUpperCase() }}</template>
                            </el-avatar>
                            <div>
                                <div class="customer-name">
                                    <el-link type="primary" @click="$router.push(`/customers/${row.id}`)">
                                        {{ row.user?.name || t('customers_page.no_linked_user') }}
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
                <el-table-column :label="t('customers_page.col_type')" width="90" prop="type" sortable="custom">
                    <template #default="{ row }">
                        <el-tag :type="row.type === 'enterprise' ? 'warning' : 'info'" size="small">
                            {{ typeLabel(row.type) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('customers_page.col_level')" width="100" prop="level" sortable="custom">
                    <template #default="{ row }">
                        <el-tag :type="levelTagType(row.level)" size="small">
                            {{ levelLabel(row.level) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('customers_page.col_status')" width="80" prop="status" sortable="custom">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'active' ? 'success' : row.status === 'suspended' ? 'danger' : 'info'" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('customers_page.col_licenses')" width="100" prop="licenses_count" sortable="custom">
                    <template #default="{ row }">
                        <el-tag type="primary" effect="plain" size="small">{{ row.licenses_count || 0 }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('customers_page.col_created')" width="170" prop="created_at" sortable="custom">
                    <template #default="{ row }">
                        {{ formatDate(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('customers_page.col_actions')" width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="$router.push(`/customers/${row.id}`)">
                            {{ t('customers_page.detail') }}
                        </el-button>
                        <el-button text size="small" type="primary" @click="openEditDialog(row)">
                            {{ t('actions.edit') }}
                        </el-button>
                        <el-dropdown ref="moreActionRef" trigger="click" @command="(cmd) => handleStatusAction(cmd, row)">
                            <el-button text size="small" type="primary">
                                {{ t('actions.more') }} <el-icon><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item
                                        v-if="row.status === 'active'"
                                        command="suspend"
                                        divided
                                    >
                                        {{ t('customers_page.st_inactive') }}
                                    </el-dropdown-item>
                                    <el-dropdown-item
                                        v-if="row.status === 'suspended' || row.status === 'inactive'"
                                        command="activate"
                                    >
                                        {{ t('actions.enable') }}
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 批量操作栏 -->
            <div class="batch-bar" v-if="selectedIds.length > 0">
                <span class="batch-info">{{ t('customers_page.selected_count', { n: selectedIds.length }) }}</span>
                <el-button size="small" @click="clearSelection">{{ t('customers_page.clear_selection') }}</el-button>
                <el-button size="small" type="warning" @click="batchAction('inactive')">{{ t('customers_page.batch_deactivate') }}</el-button>
                <el-button size="small" type="success" @click="batchAction('active')">{{ t('customers_page.batch_activate') }}</el-button>
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
            :title="editingId ? t('customers_page.edit_title') : t('customers_page.create_title')"
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
                <el-form-item :label="t('customers_page.form_type')" prop="type">
                    <el-radio-group v-model="form.type">
                        <el-radio value="individual">{{ t('customers_page.type_individual') }}</el-radio>
                        <el-radio value="enterprise">{{ t('customers_page.type_enterprise') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t('customers_page.level')" prop="level">
                    <el-select v-model="form.level" style="width: 100%">
                        <el-option :label="t('customers_page.level_free')" value="free" />
                        <el-option :label="t('customers_page.level_pro')" value="pro" />
                        <el-option :label="t('customers_page.level_enterprise')" value="enterprise" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('customers_page.status')" prop="status">
                    <el-select v-model="form.status" style="width: 100%">
                        <el-option :label="t('customers_page.st_active')" value="active" />
                        <el-option :label="t('customers_page.st_inactive')" value="inactive" />
                        <el-option :label="t('customers_page.st_suspended')" value="suspended" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="submitForm">
                    {{ editingId ? t('actions.save') : t('actions.create') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Plus, ArrowDown } from '@element-plus/icons-vue';
import customerApi from '@/api/customer';
import SavedSearchBar from '@/components/SavedSearchBar.vue';

const { t, locale } = useI18n();

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

const formRules = computed(() => ({
    type: [{ required: true, message: t('customers_page.type_required'), trigger: 'change' }],
    level: [{ required: true, message: t('customers_page.level_required'), trigger: 'change' }],
    status: [{ required: true, message: t('customers_page.status_required'), trigger: 'change' }],
}));

const typeLabels = computed(() => ({
    enterprise: t('customers_page.type_enterprise'),
    individual: t('customers_page.type_individual'),
}));

const levelLabels = computed(() => ({
    free: t('customers_page.level_free'),
    pro: t('customers_page.level_pro'),
    enterprise: t('customers_page.level_enterprise'),
}));

const statusLabels = computed(() => ({
    active: t('customers_page.st_active'),
    inactive: t('customers_page.st_inactive'),
    suspended: t('customers_page.st_suspended'),
}));

function levelTagType(level) {
    const map = { free: 'info', pro: 'primary', enterprise: 'warning' };
    return map[level] || 'info';
}

function typeLabel(type) {
    return typeLabels.value[type] || type;
}

function levelLabel(level) {
    return levelLabels.value[level] || level;
}

function statusLabel(status) {
    return statusLabels.value[status] || status;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(dateStr).toLocaleString(loc, {
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
        customers.value = Array.isArray(res.data) ? res.data : (res.data?.data || []);
        total.value = res.meta?.total || res.data?.total || 0;
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
            ElMessage.success(t('customers_page.update_ok'));
        } else {
            await customerApi.create(payload);
            ElMessage.success(t('customers_page.create_ok'));
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

async function handleStatusAction(cmd, row) {
    const actions = {
        suspend: {
            status: 'inactive',
            confirm: t('customers_page.deactivate_confirm'),
            success: t('customers_page.deactivate_ok'),
        },
        activate: {
            status: 'active',
            confirm: t('customers_page.activate_confirm'),
            success: t('customers_page.activate_ok'),
        },
    };
    const action = actions[cmd];
    if (!action) return;

    try {
        await ElMessageBox.confirm(action.confirm, t('customers_page.confirm_title'), {
            confirmButtonText: t('actions.confirm'),
            cancelButtonText: t('actions.cancel'),
            type: 'warning',
        });
        await customerApi.update(row.id, { ...row, status: action.status });
        ElMessage.success(action.success);
        loadCustomers();
        loadStats();
    } catch {
        // cancelled or error
    }
}

async function batchAction(targetStatus) {
    if (selectedIds.value.length === 0) {
        ElMessage.warning(t('customers_page.select_first'));
        return;
    }
    const actionLabel = targetStatus === 'active' ? t('actions.enable') : t('customers_page.st_inactive');
    try {
        await ElMessageBox.confirm(
            t('customers_page.batch_confirm', { action: actionLabel, n: selectedIds.value.length }),
            t('customers_page.batch_title'),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning' },
        );
        for (const id of selectedIds.value) {
            await customerApi.update(id, { status: targetStatus });
        }
        ElMessage.success(
            targetStatus === 'active'
                ? t('customers_page.batch_activate_ok')
                : t('customers_page.batch_deactivate_ok'),
        );
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
