<template>
    <div class="portal-tickets">
        <div class="page-header">
            <div>
                <h2>我的工单</h2>
                <p class="text-muted">提交问题工单，查看回复和处理进度。</p>
            </div>
            <el-button type="primary" @click="showCreateDialog = true" :icon="Plus">
                创建工单
            </el-button>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">{{ stats.open || 0 }}</div>
                        <div class="mini-label">待处理</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #409eff">{{ stats.in_progress || 0 }}</div>
                        <div class="mini-label">处理中</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #67c23a">{{ stats.resolved || 0 }}</div>
                        <div class="mini-label">已解决</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #909399">{{ stats.closed || 0 }}</div>
                        <div class="mini-label">已关闭</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选 -->
        <el-card shadow="never" class="mb-4">
            <el-form :model="filters" inline>
                <el-form-item label="状态">
                    <el-select v-model="filters.status" clearable placeholder="全部状态" style="width: 130px" @change="fetchTickets">
                        <el-option label="待处理" value="open" />
                        <el-option label="处理中" value="in_progress" />
                        <el-option label="已解决" value="resolved" />
                        <el-option label="已关闭" value="closed" />
                    </el-select>
                </el-form-item>
                <el-form-item label="分类">
                    <el-select v-model="filters.category_id" clearable placeholder="全部分类" style="width: 150px" @change="fetchTickets">
                        <el-option v-for="cat in categories" :key="cat.id" :label="cat.name" :value="cat.id" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button @click="fetchTickets" :icon="Search">搜索</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 工单列表 -->
        <el-card shadow="never">
            <el-table :data="tickets" v-loading="loading" stripe @row-click="row => $router.push(`/portal/tickets/${row.id}`)" style="cursor: pointer">
                <el-table-column prop="id" label="#" width="60" />
                <el-table-column label="分类" width="100">
                    <template #default="{ row }">
                        <el-tag size="small" effect="plain">{{ row.category?.name || '-' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="subject" label="标题" min-width="200">
                    <template #default="{ row }">
                        <span class="ticket-title">{{ row.subject || row.title }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small" effect="dark">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="priority" label="优先级" width="80">
                    <template #default="{ row }">
                        <el-tag :type="priorityType(row.priority)" size="small">
                            {{ priorityLabel(row.priority) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="回复数" width="70" align="center">
                    <template #default="{ row }">
                        <el-tag round size="small">{{ row.replies_count || 0 }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="160" />
                <el-table-column prop="updated_at" label="最后更新" width="160" />
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
        <el-dialog v-model="showCreateDialog" title="创建工单" width="600px">
            <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-position="top">
                <el-form-item label="分类" prop="category_id">
                    <el-select v-model="createForm.category_id" placeholder="选择问题分类" style="width: 100%">
                        <el-option v-for="cat in categories" :key="cat.id" :label="cat.name" :value="cat.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="标题" prop="title">
                    <el-input v-model="createForm.title" placeholder="简要描述您的问题" maxlength="200" show-word-limit />
                </el-form-item>
                <el-form-item label="描述" prop="description">
                    <el-input
                        v-model="createForm.description"
                        type="textarea"
                        :rows="6"
                        placeholder="详细描述您遇到的问题，包括：&#10;1. 发生了什么问题&#10;2. 预期应该是什么&#10;3. 相关的 License Key 或设备信息"
                    />
                </el-form-item>
                <el-form-item label="优先级" prop="priority">
                    <el-radio-group v-model="createForm.priority">
                        <el-radio value="low">低</el-radio>
                        <el-radio value="medium">普通</el-radio>
                        <el-radio value="high">高</el-radio>
                        <el-radio value="urgent">紧急</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateDialog = false">取消</el-button>
                <el-button type="primary" @click="handleCreate" :loading="creating">提交工单</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import ticketApi from '@/api/ticket';
import { ElMessage } from 'element-plus';
import { Plus, Search } from '@element-plus/icons-vue';

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

const createRules = {
    category_id: [{ required: true, message: '请选择分类', trigger: 'change' }],
    title: [
        { required: true, message: '请输入标题', trigger: 'blur' },
        { min: 4, message: '标题至少 4 个字符', trigger: 'blur' },
    ],
    description: [
        { required: true, message: '请描述您的问题', trigger: 'blur' },
        { min: 20, message: '描述至少 20 个字符', trigger: 'blur' },
    ],
    priority: [{ required: true, message: '请选择优先级', trigger: 'change' }],
};

const STATUS_MAP = {
    open: { type: 'danger', label: '待处理' },
    in_progress: { type: 'primary', label: '处理中' },
    resolved: { type: 'success', label: '已解决' },
    closed: { type: 'info', label: '已关闭' },
};

const PRIORITY_MAP = {
    low: { type: 'info', label: '低' },
    medium: { type: '', label: '普通' },
    high: { type: 'warning', label: '高' },
    urgent: { type: 'danger', label: '紧急' },
};

function statusType(s) { return STATUS_MAP[s]?.type || 'info'; }
function statusLabel(s) { return STATUS_MAP[s]?.label || s; }
function priorityType(p) { return PRIORITY_MAP[p]?.type || 'info'; }
function priorityLabel(p) { return PRIORITY_MAP[p]?.label || p; }

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
        ElMessage.error('获取工单列表失败');
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
        ElMessage.success('工单创建成功，我们会尽快处理');
        showCreateDialog.value = false;
        createForm.category_id = '';
        createForm.title = '';
        createForm.description = '';
        createForm.priority = 'medium';
        await fetchTickets();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '创建工单失败');
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
