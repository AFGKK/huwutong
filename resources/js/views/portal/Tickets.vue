<template>
    <div class="portal-tickets">
        <div class="page-header">
            <div>
                <h2>{{ $t('portal.tickets_title') }}</h2>
                <p class="text-muted">{{ $t('portal.tickets_subtitle') }}</p>
            </div>
            <el-button type="primary" @click="showCreateDialog = true" :icon="Plus">
                {{ $t('portal.create_ticket') }}
            </el-button>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">{{ stats.open || 0 }}</div>
                        <div class="mini-label">{{ $t('portal.ticket_open') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #0f172a">{{ stats.in_progress || 0 }}</div>
                        <div class="mini-label">{{ $t('portal.ticket_progress') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #67c23a">{{ stats.resolved || 0 }}</div>
                        <div class="mini-label">{{ $t('portal.ticket_resolved') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #909399">{{ stats.closed || 0 }}</div>
                        <div class="mini-label">{{ $t('portal.ticket_closed') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选 -->
        <el-card shadow="never" class="mb-4">
            <el-form :model="filters" inline>
                <el-form-item :label="$t('portal.status')">
                    <el-select v-model="filters.status" clearable :placeholder="$t('portal.all_statuses')" style="width: 130px" @change="fetchTickets">
                        <el-option :label="$t('portal.ticket_open')" value="open" />
                        <el-option :label="$t('portal.ticket_progress')" value="in_progress" />
                        <el-option :label="$t('portal.ticket_resolved')" value="resolved" />
                        <el-option :label="$t('portal.ticket_closed')" value="closed" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('portal.category')">
                    <el-select v-model="filters.category_id" clearable :placeholder="$t('portal.all_categories')" style="width: 150px" @change="fetchTickets">
                        <el-option v-for="cat in categories" :key="cat.id" :label="cat.name" :value="cat.id" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button @click="fetchTickets" :icon="Search">{{ $t('actions.search') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 工单列表 -->
        <el-card shadow="never">
            <el-table :data="tickets" v-loading="loading" stripe @row-click="row => $router.push(`/portal/tickets/${row.id}`)" style="cursor: pointer">
                <el-table-column prop="id" label="#" width="60" />
                <el-table-column :label="$t('portal.category')" width="100">
                    <template #default="{ row }">
                        <el-tag size="small" effect="plain">{{ row.category?.name || '-' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="subject" :label="$t('portal.subject')" min-width="200">
                    <template #default="{ row }">
                        <span class="ticket-title">{{ row.subject || row.title }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small" effect="dark">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="priority" :label="$t('portal.priority')" width="80">
                    <template #default="{ row }">
                        <el-tag :type="priorityType(row.priority)" size="small">
                            {{ priorityLabel(row.priority) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.replies')" width="70" align="center">
                    <template #default="{ row }">
                        <el-tag round size="small">{{ row.replies_count || 0 }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" :label="$t('portal.created_at')" width="160" />
                <el-table-column prop="updated_at" :label="$t('portal.updated_at')" width="160" />
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

        <!-- 创建工单对话框 -->
        <el-dialog v-model="showCreateDialog" :title="$t('portal.create_ticket')" width="600px">
            <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-position="top">
                <el-form-item :label="$t('portal.category')" prop="category_id">
                    <el-select v-model="createForm.category_id" :placeholder="$t('portal.select_category')" style="width: 100%">
                        <el-option v-for="cat in categories" :key="cat.id" :label="cat.name" :value="cat.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('portal.subject')" prop="title">
                    <el-input v-model="createForm.title" :placeholder="$t('portal.ticket_subject_ph')" maxlength="200" show-word-limit />
                </el-form-item>
                <el-form-item :label="$t('portal.ticket_desc')" prop="description">
                    <el-input
                        v-model="createForm.description"
                        type="textarea"
                        :rows="6"
                        :placeholder="$t('portal.ticket_desc_ph')"
                    />
                </el-form-item>
                <el-form-item :label="$t('portal.priority')" prop="priority">
                    <el-radio-group v-model="createForm.priority">
                        <el-radio value="low">{{ $t('portal.priority_low') }}</el-radio>
                        <el-radio value="medium">{{ $t('portal.priority_medium') }}</el-radio>
                        <el-radio value="high">{{ $t('portal.priority_high') }}</el-radio>
                        <el-radio value="urgent">{{ $t('portal.priority_urgent') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateDialog = false">{{ $t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleCreate" :loading="creating">{{ $t('portal.submit_ticket') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ticketApi from '@/api/ticket';
import { ElMessage } from 'element-plus';
import { Plus, Search } from '@element-plus/icons-vue';

const { t } = useI18n();

const loading = ref(false);
const creating = ref(false);
const tickets = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(10);
const categories = ref([]);
const showCreateDialog = ref(false);
const createFormRef = ref(null);

const stats = reactive({
    open: 0,
    in_progress: 0,
    resolved: 0,
    closed: 0,
});

const filters = reactive({
    status: '',
    category_id: '',
});

const createForm = reactive({
    category_id: '',
    title: '',
    description: '',
    priority: 'medium',
});

const createRules = computed(() => ({
    category_id: [{ required: true, message: t('portal.rule_category'), trigger: 'change' }],
    title: [
        { required: true, message: t('portal.rule_title'), trigger: 'blur' },
        { min: 4, message: t('portal.rule_title_min'), trigger: 'blur' },
    ],
    description: [
        { required: true, message: t('portal.rule_desc'), trigger: 'blur' },
        { min: 20, message: t('portal.rule_desc_min'), trigger: 'blur' },
    ],
    priority: [{ required: true, message: t('portal.rule_priority'), trigger: 'change' }],
}));

function statusType(s) {
    const map = { open: 'danger', in_progress: 'primary', resolved: 'success', closed: 'info' };
    return map[s] || 'info';
}
function statusLabel(s) {
    const map = {
        open: t('portal.ticket_open'),
        in_progress: t('portal.ticket_progress'),
        resolved: t('portal.ticket_resolved'),
        closed: t('portal.ticket_closed'),
    };
    return map[s] || s;
}
function priorityType(p) {
    const map = { low: 'info', medium: '', high: 'warning', urgent: 'danger' };
    return map[p] || 'info';
}
function priorityLabel(p) {
    const map = {
        low: t('portal.priority_low'),
        medium: t('portal.priority_medium'),
        high: t('portal.priority_high'),
        urgent: t('portal.priority_urgent'),
    };
    return map[p] || p;
}

async function fetchCategories() {
    try {
        const { data: res } = await ticketApi.categories();
        categories.value = res.data || [];
    } catch {
        categories.value = [];
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
        if (filters.status) params.status = filters.status;
        if (filters.category_id) params.category_id = filters.category_id;

        const { data: res } = await ticketApi.myTickets(params);
        const pageData = res.data || {};
        tickets.value = pageData.data || [];
        total.value = pageData.total || 0;

        // Stats
        const { data: statsRes } = await ticketApi.stats();
        const s = statsRes.data || {};
        stats.open = s.open || 0;
        stats.in_progress = s.in_progress || 0;
        stats.resolved = s.resolved || 0;
        stats.closed = s.closed || 0;
    } catch {
        ElMessage.error(t('portal.ticket_list_failed'));
    } finally {
        loading.value = false;
    }
}

async function handleCreate() {
    const valid = await createFormRef.value.validate().catch(() => false);
    if (!valid) return;

    creating.value = true;
    try {
        await ticketApi.create({
            category_id: createForm.category_id,
            subject: createForm.title,
            description: createForm.description,
            priority: createForm.priority,
        });
        ElMessage.success(t('portal.ticket_created'));
        showCreateDialog.value = false;
        createForm.category_id = '';
        createForm.title = '';
        createForm.description = '';
        createForm.priority = 'medium';
        await fetchTickets();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('portal.ticket_create_failed'));
    } finally {
        creating.value = false;
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
    align-items: flex-start;
    margin-bottom: 20px;
}

.page-header h2 { margin: 0 0 4px; }

.text-muted { color: #909399; font-size: 14px; margin: 0; }

.mb-4 { margin-bottom: 16px; }

.mini-stat {
    text-align: center;
    padding: 8px 0;
}

.mini-value {
    font-size: 26px;
    font-weight: 700;
    color: #303133;
}

.mini-label {
    font-size: 14px;
    color: #909399;
    margin-top: 4px;
}

.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    padding: 16px 0 0;
}

.ticket-title {
    font-weight: 500;
    color: #303133;
}
</style>
