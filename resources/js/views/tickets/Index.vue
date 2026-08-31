<template>
    <div class="tickets-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('tickets_page.title') }}</h2>
                <span class="header-subtitle">{{ t('tickets_page.subtitle') }}</span>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="stats-row">
            <el-col :span="4" v-for="s in statCards" :key="s.key">
                <el-card shadow="never" class="stat-card" @click="quickFilter(s.key)" style="cursor: pointer">
                    <div class="stat-value" :style="{ color: s.color }">{{ s.value }}</div>
                    <div class="stat-label">{{ s.label }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选栏 -->
        <el-card shadow="never" class="filter-card">
            <el-form :model="filters" inline>
                <el-form-item :label="t('tickets_page.search')">
                    <el-input
                        v-model="filters.search"
                        :placeholder="t('tickets_page.search_ph')"
                        clearable
                        style="width: 200px"
                        @keyup.enter="doSearch"
                    />
                </el-form-item>
                <el-form-item :label="t('tickets_page.status')">
                    <el-select v-model="filters.status" clearable :placeholder="t('tickets_page.all_status')" style="width: 130px" @change="doSearch">
                        <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('tickets_page.priority')">
                    <el-select v-model="filters.priority" clearable :placeholder="t('tickets_page.all_priority')" style="width: 130px" @change="doSearch">
                        <el-option v-for="opt in priorityOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('tickets_page.category')">
                    <el-select v-model="filters.category_id" clearable :placeholder="t('tickets_page.all_category')" style="width: 150px" @change="doSearch">
                        <el-option v-for="cat in categories" :key="cat.id" :label="cat.name" :value="cat.id" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="doSearch">
                        <el-icon><Search /></el-icon> {{ t('actions.search') }}
                    </el-button>
                    <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
                    <el-button @click="handleExport" :loading="exporting">
                        <el-icon><Download /></el-icon> {{ t('tickets_page.export_csv') }}
                    </el-button>
                </el-form-item>
            </el-form>
            <div class="filter-bar-footer">
                <SavedSearchBar
                    page="tickets"
                    :current-filters="filters"
                    @apply="applySavedFilters"
                />
            </div>
        </el-card>

        <!-- 批量操作栏 -->
        <el-card v-if="selectedIds.length > 0" shadow="never" class="mb-4">
            <div style="display:flex;align-items:center;gap:12px;font-size:13px">
                <span>{{ t('tickets_page.selected_count', { n: selectedIds.length }) }}</span>
                <el-button size="small" @click="clearSelection">{{ t('tickets_page.clear_selection') }}</el-button>
                <el-button size="small" type="success" @click="handleBatchClose" :loading="batchLoading">
                    <el-icon><CircleCheck /></el-icon> {{ t('tickets_page.batch_close') }}
                </el-button>
                <el-button size="small" type="primary" @click="showBatchAssign = true" :loading="batchLoading">
                    <el-icon><UserFilled /></el-icon> {{ t('tickets_page.batch_assign') }}
                </el-button>
                <el-button size="small" type="danger" @click="handleBatchDelete" :loading="batchLoading">
                    <el-icon><Delete /></el-icon> {{ t('tickets_page.batch_delete') }}
                </el-button>
            </div>
        </el-card>

        <!-- 工单列表 -->
        <el-card shadow="never">
            <el-table ref="tableRef" :data="tickets" v-loading="loading" stripe
                @selection-change="onSelectionChange">
                <el-table-column type="selection" width="45" />
                <el-table-column prop="id" label="#" width="60" />
                <el-table-column :label="t('tickets_page.col_customer')" min-width="140">
                    <template #default="{ row }">
                        <div class="customer-cell">
                            <div class="customer-name">{{ row.customer?.name || row.user?.name || '-' }}</div>
                            <div class="customer-email" v-if="row.customer?.email">{{ row.customer.email }}</div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('tickets_page.col_category')" width="100">
                    <template #default="{ row }">
                        <el-tag size="small" effect="plain">{{ row.category?.name || '-' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('tickets_page.col_title')" min-width="220">
                    <template #default="{ row }">
                        <el-link type="primary" :underline="'never'" @click="$router.push(`/tickets/${row.id}`)">
                            {{ row.subject || row.title }}
                        </el-link>
                    </template>
                </el-table-column>
                <el-table-column :label="t('tickets_page.col_status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small" effect="dark">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('tickets_page.col_priority')" width="80">
                    <template #default="{ row }">
                        <el-tag :type="priorityType(row.priority)" size="small">
                            {{ priorityLabel(row.priority) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('tickets_page.col_assignee')" width="120">
                    <template #default="{ row }">
                        <span v-if="row.assigned_to">{{ row.assigned_to.name }}</span>
                        <el-tag v-else size="small" type="warning">{{ t('tickets_page.unassigned') }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('tickets_page.col_replies')" width="60" align="center">
                    <template #default="{ row }">
                        <el-tag round size="small">{{ row.replies_count || 0 }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('tickets_page.col_sla')" width="80">
                    <template #default="{ row }">
                        <el-tag v-if="row.sla_breached" type="danger" size="small">{{ t('tickets_page.sla_breached') }}</el-tag>
                        <el-tag v-else-if="row.sla_deadline" type="warning" size="small">
                            {{ remainingTime(row.sla_deadline) }}
                        </el-tag>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" :label="t('tickets_page.col_created')" width="160" />
                <el-table-column :label="t('tickets_page.col_actions')" width="160" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="$router.push(`/tickets/${row.id}`)">
                            {{ t('actions.view') }}
                        </el-button>
                        <el-button
                            v-if="row.status === 'open'"
                            type="warning"
                            link
                            size="small"
                            @click="handleAssign(row)"
                        >
                            {{ t('tickets_page.assign') }}
                        </el-button>
                        <el-button
                            v-if="row.status !== 'closed'"
                            type="success"
                            link
                            size="small"
                            @click="handleQuickAction(row, 'close')"
                        >
                            {{ t('tickets_page.close') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :total="total"
                    :page-sizes="[10, 20, 50]"
                    layout="total, sizes, prev, pager, next"
                    @current-change="fetchTickets"
                    @size-change="fetchTickets"
                />
            </div>
        </el-card>

        <!-- 分配对话框 -->
        <el-dialog v-model="showAssignDialog" :title="t('tickets_page.assign_dialog_title')" width="400px">
            <el-form label-position="top">
                <el-form-item :label="t('tickets_page.select_assignee')">
                    <el-select v-model="assignUserId" :placeholder="t('tickets_page.select_staff_ph')" filterable style="width: 100%">
                        <el-option v-for="u in staffUsers" :key="u.id" :label="u.name" :value="u.id">
                            <span>{{ u.name }}</span>
                            <span class="text-muted" style="float: right; font-size: 12px;">{{ u.email }}</span>
                        </el-option>
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAssignDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="confirmAssign" :loading="assigning">{{ t('tickets_page.confirm_assign') }}</el-button>
            </template>
        </el-dialog>

        <!-- 批量分配对话框 -->
        <el-dialog v-model="showBatchAssign" :title="t('tickets_page.batch_assign_dialog_title')" width="400px">
            <el-form label-position="top">
                <el-form-item :label="t('tickets_page.select_assignee')">
                    <el-select v-model="batchAssignUserId" :placeholder="t('tickets_page.select_staff_ph')" filterable style="width: 100%">
                        <el-option v-for="u in staffUsers" :key="u.id" :label="u.name" :value="u.id">
                            <span>{{ u.name }}</span>
                            <span class="text-muted" style="float: right; font-size: 12px;">{{ u.email }}</span>
                        </el-option>
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showBatchAssign = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="confirmBatchAssign" :loading="batchLoading">{{ t('tickets_page.confirm_assign') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import ticketApi from '@/api/ticket';
import SavedSearchBar from '@/components/SavedSearchBar.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, CircleCheck, UserFilled, Delete, Download } from '@element-plus/icons-vue';

const { t } = useI18n();

const loading = ref(false);
const tickets = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(10);
const categories = ref([]);
const staffUsers = ref([]);

const showAssignDialog = ref(false);
const assigning = ref(false);
const assignUserId = ref(null);
const assignTicketId = ref(null);

// ─── 批量操作 ───
const selectedIds = ref([]);
const tableRef = ref(null);
const batchLoading = ref(false);
const showBatchAssign = ref(false);
const batchAssignUserId = ref(null);
const exporting = ref(false);

function onSelectionChange(rows) {
    selectedIds.value = rows.map(r => r.id);
}

function clearSelection() {
    tableRef.value?.clearSelection();
    selectedIds.value = [];
}

async function handleBatchClose() {
    if (selectedIds.value.length === 0) return;
    try {
        await ElMessageBox.confirm(
            t('tickets_page.batch_close_confirm', { n: selectedIds.value.length }),
            t('tickets_page.batch_close_title'),
        );
        batchLoading.value = true;
        const { data: res } = await ticketApi.batchClose(selectedIds.value);
        ElMessage.success(res?.message || t('tickets_page.close_ok'));
        clearSelection();
        fetchTickets();
    } catch { /* cancelled */ }
    finally { batchLoading.value = false; }
}

async function confirmBatchAssign() {
    if (!batchAssignUserId.value) { ElMessage.warning(t('tickets_page.select_assignee_required')); return; }
    batchLoading.value = true;
    try {
        const { data: res } = await ticketApi.batchAssign(selectedIds.value, batchAssignUserId.value);
        ElMessage.success(res?.message || t('tickets_page.assign_ok'));
        showBatchAssign.value = false;
        clearSelection();
        fetchTickets();
    } catch { ElMessage.error(t('tickets_page.assign_fail')); }
    finally { batchLoading.value = false; }
}

async function handleBatchDelete() {
    if (selectedIds.value.length === 0) return;
    try {
        await ElMessageBox.confirm(
            t('tickets_page.batch_delete_confirm', { n: selectedIds.value.length }),
            t('tickets_page.batch_delete_title'),
            { type: 'warning' },
        );
        batchLoading.value = true;
        const { data: res } = await ticketApi.batchDelete(selectedIds.value);
        ElMessage.success(res?.message || t('tickets_page.delete_ok'));
        clearSelection();
        fetchTickets();
    } catch { /* cancelled */ }
    finally { batchLoading.value = false; }
}

async function handleExport() {
    exporting.value = true;
    try {
        const params = {};
        if (filters.status) params.status = filters.status;
        if (filters.priority) params.priority = filters.priority;
        const res = await ticketApi.exportCsv(params);
        const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'tickets-export-' + new Date().toISOString().slice(0, 10) + '.csv';
        a.click();
        URL.revokeObjectURL(url);
        ElMessage.success(t('tickets_page.export_ok'));
    } catch { ElMessage.error(t('tickets_page.export_fail')); }
    finally { exporting.value = false; }
}

const statValues = reactive({
    open: '0',
    in_progress: '0',
    resolved: '0',
    closed: '0',
    urgent: '0',
    breached: '0',
});

const statCards = computed(() => [
    { key: 'open', label: t('tickets_page.st_open'), value: statValues.open, color: '#f56c6c' },
    { key: 'in_progress', label: t('tickets_page.st_in_progress'), value: statValues.in_progress, color: '#0f172a' },
    { key: 'resolved', label: t('tickets_page.st_resolved'), value: statValues.resolved, color: '#67c23a' },
    { key: 'closed', label: t('tickets_page.st_closed'), value: statValues.closed, color: '#909399' },
    { key: 'urgent', label: t('tickets_page.st_urgent'), value: statValues.urgent, color: '#f56c6c' },
    { key: 'breached', label: t('tickets_page.st_sla_breached'), value: statValues.breached, color: '#f56c6c' },
]);

const filters = reactive({
    search: '',
    status: '',
    priority: '',
    category_id: '',
});

const statusOptions = computed(() => [
    { value: 'open', label: t('tickets_page.st_open') },
    { value: 'in_progress', label: t('tickets_page.st_in_progress') },
    { value: 'resolved', label: t('tickets_page.st_resolved') },
    { value: 'closed', label: t('tickets_page.st_closed') },
]);

const priorityOptions = computed(() => [
    { value: 'low', label: t('tickets_page.pri_low') },
    { value: 'normal', label: t('tickets_page.pri_normal') },
    { value: 'high', label: t('tickets_page.pri_high') },
    { value: 'urgent', label: t('tickets_page.pri_urgent') },
]);

const STATUS_MAP = computed(() => ({
    open: { type: 'danger', label: t('tickets_page.st_open') },
    in_progress: { type: 'primary', label: t('tickets_page.st_in_progress') },
    resolved: { type: 'success', label: t('tickets_page.st_resolved') },
    closed: { type: 'info', label: t('tickets_page.st_closed') },
}));

const PRIORITY_MAP = computed(() => ({
    low: { type: 'info', label: t('tickets_page.pri_low') },
    medium: { type: '', label: t('tickets_page.pri_normal') },
    normal: { type: '', label: t('tickets_page.pri_normal') },
    high: { type: 'warning', label: t('tickets_page.pri_high') },
    urgent: { type: 'danger', label: t('tickets_page.pri_urgent') },
}));

function statusType(s) { return STATUS_MAP.value[s]?.type || 'info'; }
function statusLabel(s) { return STATUS_MAP.value[s]?.label || s; }
function priorityType(p) { return PRIORITY_MAP.value[p]?.type || 'info'; }
function priorityLabel(p) { return PRIORITY_MAP.value[p]?.label || p; }

function remainingTime(deadline) {
    if (!deadline) return '-';
    const diff = new Date(deadline) - new Date();
    if (diff <= 0) return t('tickets_page.sla_breached');
    const hours = Math.floor(diff / (1000 * 60 * 60));
    if (hours < 1) return t('tickets_page.remaining_lt1h');
    if (hours < 24) return t('tickets_page.remaining_hours', { n: hours });
    return t('tickets_page.remaining_days', { n: Math.floor(hours / 24) });
}

async function fetchCategories() {
    try {
        const { data: res } = await ticketApi.categories();
        categories.value = res.data?.data || [];
    } catch {
        categories.value = [];
    }
}

async function fetchStaffUsers() {
    try {
        const { data: res } = await ticketApi.list({ per_page: 100, assigned: true });
        const items = res.data?.data || res.data || [];
        const assignedUsers = new Map();
        for (const t of items) {
            if (t.assignee) assignedUsers.set(t.assignee.id, t.assignee);
        }
        staffUsers.value = Array.from(assignedUsers.values());
    } catch {
        staffUsers.value = [];
    }
}

async function fetchTickets() {
    loading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            sort: '-updated_at',
        };
        if (filters.search) params.search = filters.search;
        if (filters.status) params.status = filters.status;
        if (filters.priority) params.priority = filters.priority;
        if (filters.category_id) params.category_id = filters.category_id;

        const { data: res } = await ticketApi.list(params);
        tickets.value = res.data?.data || [];
        total.value = res.data?.total || 0;

        // Stats
        const { data: statsRes } = await ticketApi.stats();
        const s = statsRes.data?.data || {};
        statValues.open = String(s.open || 0);
        statValues.in_progress = String(s.in_progress || 0);
        statValues.resolved = String(s.resolved || 0);
        statValues.closed = String(s.closed || 0);
        statValues.urgent = String(s.urgent || 0);
        statValues.breached = String(s.sla_breached || 0);
    } catch {
        ElMessage.error(t('tickets_page.list_fail'));
    } finally {
        loading.value = false;
    }
}

function doSearch() {
    page.value = 1;
    fetchTickets();
}

function resetFilters() {
    filters.search = '';
    filters.status = '';
    filters.priority = '';
    filters.category_id = '';
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

function quickFilter(key) {
    filters.status = key === 'breached' ? '' : key;
    filters.priority = key === 'urgent' ? 'urgent' : '';
    doSearch();
}

function handleAssign(row) {
    assignTicketId.value = row.id;
    assignUserId.value = row.assigned_to?.id || null;
    showAssignDialog.value = true;
}

async function confirmAssign() {
    if (!assignUserId.value) {
        ElMessage.warning(t('tickets_page.select_assignee_required'));
        return;
    }
    assigning.value = true;
    try {
        await ticketApi.assign(assignTicketId.value, assignUserId.value);
        ElMessage.success(t('tickets_page.assign_ticket_ok'));
        showAssignDialog.value = false;
        await fetchTickets();
    } catch {
        ElMessage.error(t('tickets_page.assign_fail'));
    } finally {
        assigning.value = false;
    }
}

async function handleQuickAction(row, action) {
    try {
        const message = action === 'close'
            ? t('tickets_page.quick_close_confirm', { id: row.id })
            : t('tickets_page.quick_action_confirm', { id: row.id });
        await ElMessageBox.confirm(
            message,
            t('tickets_page.confirm_action_title'),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'info' }
        );
        await ticketApi[action](row.id);
        ElMessage.success(t('messages.success'));
        await fetchTickets();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(t('messages.failed'));
        }
    }
}

onMounted(() => {
    fetchCategories();
    fetchTickets();
    fetchStaffUsers();
});
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.header-left h2 { margin: 0 0 4px; }
.header-subtitle { font-size: 14px; color: #909399; }

.stats-row { margin-bottom: 16px; }

.stat-card {
    text-align: center;
    padding: 8px 0;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-value {
    font-size: 26px;
    font-weight: 700;
}

.stat-label {
    font-size: 14px;
    color: #909399;
    margin-top: 4px;
}

.filter-card { margin-bottom: 16px; }

.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    padding: 16px 0 0;
}

.customer-cell .customer-name {
    font-size: 14px;
    font-weight: 500;
    color: #303133;
}

.customer-cell .customer-email {
    font-size: 11px;
    color: #909399;
}

.text-muted { color: #909399; }
.filter-bar-footer {
    display: flex;
    justify-content: flex-end;
    padding-top: 8px;
    border-top: 1px solid var(--el-border-color-extra-light);
    margin-top: 8px;
}
</style>
