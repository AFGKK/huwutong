<template>
    <div class="tickets-page">
        <div class="page-header">
            <div class="header-left">
                <h2>工单管理</h2>
                <span class="header-subtitle">管理所有客户提交的工单</span>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="stats-row">
            <el-col :span="4" v-for="s in statCards" :key="s.label">
                <el-card shadow="never" class="stat-card" @click="quickFilter(s.key)" style="cursor: pointer">
                    <div class="stat-value" :style="{ color: s.color }">{{ s.value }}</div>
                    <div class="stat-label">{{ s.label }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选栏 -->
        <el-card shadow="never" class="filter-card">
            <el-form :model="filters" inline>
                <el-form-item label="搜索">
                    <el-input
                        v-model="filters.search"
                        placeholder="标题 / ID"
                        clearable
                        style="width: 200px"
                        @keyup.enter="doSearch"
                    />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="filters.status" clearable placeholder="全部状态" style="width: 130px" @change="doSearch">
                        <el-option label="待处理" value="open" />
                        <el-option label="处理中" value="in_progress" />
                        <el-option label="已解决" value="resolved" />
                        <el-option label="已关闭" value="closed" />
                    </el-select>
                </el-form-item>
                <el-form-item label="优先级">
                    <el-select v-model="filters.priority" clearable placeholder="全部优先级" style="width: 130px" @change="doSearch">
                        <el-option label="低" value="low" />
                        <el-option label="普通" value="normal" />
                        <el-option label="高" value="high" />
                        <el-option label="紧急" value="urgent" />
                    </el-select>
                </el-form-item>
                <el-form-item label="分类">
                    <el-select v-model="filters.category_id" clearable placeholder="全部分类" style="width: 150px" @change="doSearch">
                        <el-option v-for="cat in categories" :key="cat.id" :label="cat.name" :value="cat.id" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="doSearch">
                        <el-icon><Search /></el-icon> 搜索
                    </el-button>
                    <el-button @click="resetFilters">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 工单列表 -->
        <el-card shadow="never">
            <el-table :data="tickets" v-loading="loading" stripe>
                <el-table-column prop="id" label="#" width="60" />
                <el-table-column label="客户" min-width="140">
                    <template #default="{ row }">
                        <div class="customer-cell">
                            <div class="customer-name">{{ row.customer?.name || row.user?.name || '-' }}</div>
                            <div class="customer-email" v-if="row.customer?.email">{{ row.customer.email }}</div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="分类" width="100">
                    <template #default="{ row }">
                        <el-tag size="small" effect="plain">{{ row.category?.name || '-' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="标题" min-width="220">
                    <template #default="{ row }">
                        <el-link type="primary" :underline="false" @click="$router.push(`/tickets/${row.id}`)">
                            {{ row.title }}
                        </el-link>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small" effect="dark">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="优先级" width="80">
                    <template #default="{ row }">
                        <el-tag :type="priorityType(row.priority)" size="small">
                            {{ priorityLabel(row.priority) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="处理人" width="120">
                    <template #default="{ row }">
                        <span v-if="row.assigned_to">{{ row.assigned_to.name }}</span>
                        <el-tag v-else size="small" type="warning">未分配</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="回复" width="60" align="center">
                    <template #default="{ row }">
                        <el-tag round size="small">{{ row.replies_count || 0 }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="SLA" width="80">
                    <template #default="{ row }">
                        <el-tag v-if="row.sla_breached" type="danger" size="small">超时</el-tag>
                        <el-tag v-else-if="row.sla_deadline" type="warning" size="small">
                            {{ remainingTime(row.sla_deadline) }}
                        </el-tag>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="160" />
                <el-table-column label="操作" width="160" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="$router.push(`/tickets/${row.id}`)">
                            查看
                        </el-button>
                        <el-button
                            v-if="row.status === 'open'"
                            type="warning"
                            link
                            size="small"
                            @click="handleAssign(row)"
                        >
                            分配
                        </el-button>
                        <el-button
                            v-if="row.status !== 'closed'"
                            type="success"
                            link
                            size="small"
                            @click="handleQuickAction(row, 'close')"
                        >
                            关闭
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
        <el-dialog v-model="showAssignDialog" title="分配工单" width="400px">
            <el-form label-position="top">
                <el-form-item label="选择处理人">
                    <el-select v-model="assignUserId" placeholder="选择客服人员" filterable style="width: 100%">
                        <el-option v-for="u in staffUsers" :key="u.id" :label="u.name" :value="u.id">
                            <span>{{ u.name }}</span>
                            <span class="text-muted" style="float: right; font-size: 12px;">{{ u.email }}</span>
                        </el-option>
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAssignDialog = false">取消</el-button>
                <el-button type="primary" @click="confirmAssign" :loading="assigning">确认分配</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import ticketApi from '@/api/ticket';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search } from '@element-plus/icons-vue';

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

const statCards = reactive([
    { key: 'open', label: '待处理', value: '0', color: '#f56c6c' },
    { key: 'in_progress', label: '处理中', value: '0', color: '#409eff' },
    { key: 'resolved', label: '已解决', value: '0', color: '#67c23a' },
    { key: 'closed', label: '已关闭', value: '0', color: '#909399' },
    { key: 'urgent', label: '紧急', value: '0', color: '#f56c6c' },
    { key: 'breached', label: 'SLA 超时', value: '0', color: '#f56c6c' },
]);

const filters = reactive({
    search: '',
    status: '',
    priority: '',
    category_id: '',
});

const STATUS_MAP = {
    open: { type: 'danger', label: '待处理' },
    in_progress: { type: 'primary', label: '处理中' },
    resolved: { type: 'success', label: '已解决' },
    closed: { type: 'info', label: '已关闭' },
};

const PRIORITY_MAP = {
    low: { type: 'info', label: '低' },
    normal: { type: '', label: '普通' },
    high: { type: 'warning', label: '高' },
    urgent: { type: 'danger', label: '紧急' },
};

function statusType(s) { return STATUS_MAP[s]?.type || 'info'; }
function statusLabel(s) { return STATUS_MAP[s]?.label || s; }
function priorityType(p) { return PRIORITY_MAP[p]?.type || 'info'; }
function priorityLabel(p) { return PRIORITY_MAP[p]?.label || p; }

function remainingTime(deadline) {
    if (!deadline) return '-';
    const diff = new Date(deadline) - new Date();
    if (diff <= 0) return '超时';
    const hours = Math.floor(diff / (1000 * 60 * 60));
    if (hours < 1) return '<1h';
    if (hours < 24) return `${hours}h`;
    return `${Math.floor(hours / 24)}d`;
}

async function fetchCategories() {
    try {
        const { data: res } = await ticketApi.categories();
        categories.value = res.data || [];
    } catch {
        categories.value = [];
    }
}

async function fetchStaffUsers() {
    try {
        const { data: res } = await ticketApi.list({ per_page: 100, assigned: true });
        // collect unique assigned users from tickets
        const assignedUsers = new Map();
        for (const t of tickets.value) {
            if (t.assigned_to) assignedUsers.set(t.assigned_to.id, t.assigned_to);
        }
        staffUsers.value = Array.from(assignedUsers.values());
        // Also try from permissions endpoint if available
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
        const s = statsRes.data || {};
        statCards[0].value = String(s.open || 0);
        statCards[1].value = String(s.in_progress || 0);
        statCards[2].value = String(s.resolved || 0);
        statCards[3].value = String(s.closed || 0);
        statCards[4].value = String(s.urgent || 0);
        statCards[5].value = String(s.sla_breached || 0);
    } catch {
        ElMessage.error('获取工单列表失败');
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
        ElMessage.warning('请选择处理人');
        return;
    }
    assigning.value = true;
    try {
        await ticketApi.assign(assignTicketId.value, assignUserId.value);
        ElMessage.success('工单分配成功');
        showAssignDialog.value = false;
        await fetchTickets();
    } catch {
        ElMessage.error('分配失败');
    } finally {
        assigning.value = false;
    }
}

async function handleQuickAction(row, action) {
    try {
        await ElMessageBox.confirm(
            `确认${action === 'close' ? '关闭' : '执行'} #${row.id} 工单？`,
            '确认操作',
            { confirmButtonText: '确认', cancelButtonText: '取消', type: 'info' }
        );
        await ticketApi[action](row.id);
        ElMessage.success('操作成功');
        await fetchTickets();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error('操作失败');
        }
    }
}

onMounted(() => {
    fetchCategories();
    fetchTickets();
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
</style>
