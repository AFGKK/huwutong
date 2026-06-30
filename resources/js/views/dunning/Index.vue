<template>
    <div class="dunning-page">
        <div class="page-header">
            <h2>智能催缴系统</h2>
            <div class="header-actions">
                <el-button @click="scanOverdue" :loading="scanning" type="warning">
                    <el-icon><Refresh /></el-icon> 扫描逾期发票
                </el-button>
                <el-button @click="runDunning" :loading="running">
                    <el-icon><Refresh /></el-icon> 运行催缴
                </el-button>
                <el-button @click="refreshAll" :loading="loading" type="primary">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ dashboard.total_active }}</div>
                    <div class="stat-label">进行中催缴</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card stat-success">
                    <div class="stat-value">{{ dashboard.total_resolved }}</div>
                    <div class="stat-label">已解决</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card stat-danger">
                    <div class="stat-value">{{ dashboard.total_failed }}</div>
                    <div class="stat-label">需人工介入</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card stat-warning">
                    <div class="stat-value">{{ formatMoney(dashboard.total_due_amount) }}</div>
                    <div class="stat-label">待催缴总额</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 催缴阶段分布 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="24">
                <el-card>
                    <template #header>
                        <span>催缴阶段分布</span>
                    </template>
                    <el-table v-if="dashboard.by_stage?.length" :data="dashboard.by_stage" stripe size="small">
                        <el-table-column label="阶段" width="200">
                            <template #default="{ row }">
                                <el-tag :type="stageTagType(row.current_stage)">
                                    {{ stageLabel(row.current_stage) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="count" label="数量" width="120" sortable />
                        <el-table-column prop="total" label="总金额" width="150" sortable>
                            <template #default="{ row }">{{ formatMoney(row.total) }}</template>
                        </el-table-column>
                        <el-table-column label="占比" min-width="200">
                            <template #default="{ row }">
                                <el-progress
                                    :percentage="dashboard.total_active > 0 ? Math.round(row.count / dashboard.total_active * 100) : 0"
                                    :stroke-width="16"
                                    :text-inside="true"
                                />
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else description="暂无进行中的催缴" />
                </el-card>
            </el-col>
        </el-row>

        <!-- 主要 Tabs -->
        <el-tabs v-model="activeTab" type="border-card">
            <!-- ── 催缴队列 ── -->
            <el-tab-pane label="催缴队列" name="queue">
                <el-card class="mb-4">
                    <el-form :model="queueFilters" inline @keyup.enter="fetchQueue(1)">
                        <el-form-item label="状态">
                            <el-select v-model="queueFilters.status" placeholder="全部" clearable style="width: 150px">
                                <el-option label="待处理" value="pending" />
                                <el-option label="进行中" value="in_progress" />
                                <el-option label="已解决" value="resolved" />
                                <el-option label="已失败" value="failed" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="搜索">
                            <el-input v-model="queueFilters.search" placeholder="客户/发票号" clearable style="width: 200px" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="fetchQueue(1)">搜索</el-button>
                            <el-button @click="resetQueueFilters">重置</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>
                <el-table :data="queueList" stripe style="width: 100%" @row-click="showQueueDetail">
                    <el-table-column type="index" label="#" width="50" />
                    <el-table-column label="客户" min-width="150">
                        <template #default="{ row }">
                            {{ row.customer?.name || row.customer?.user?.name || 'N/A' }}
                        </template>
                    </el-table-column>
                    <el-table-column label="发票号" width="180">
                        <template #default="{ row }">
                            {{ row.invoice?.invoice_no || 'N/A' }}
                        </template>
                    </el-table-column>
                    <el-table-column prop="amount_due" label="待缴金额" width="130" sortable>
                        <template #default="{ row }">{{ formatMoney(row.amount_due) }} {{ row.currency }}</template>
                    </el-table-column>
                    <el-table-column label="催缴阶段" width="150">
                        <template #default="{ row }">
                            <el-tag :type="stageTagType(row.current_stage)" size="small">
                                {{ stageLabel(row.current_stage) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="attempt_count" label="尝试次数" width="100" sortable />
                    <el-table-column label="状态" width="110">
                        <template #default="{ row }">
                            <el-tag :type="statusTagType(row.status)" size="small">
                                {{ statusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="下次操作" width="170" sortable>
                        <template #default="{ row }">
                            <span v-if="row.next_action_at">{{ formatDate(row.next_action_at) }}</span>
                            <span v-else class="text-gray-400">-</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="120">
                        <template #default="{ row }">
                            <el-button size="small" @click.stop="showQueueDetail(row)">详情</el-button>
                            <el-button v-if="['pending', 'in_progress'].includes(row.status)" size="small" type="success" @click.stop="handleResolve(row)">解决</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="pagination-wrapper">
                    <el-pagination
                        :current-page="queuePage"
                        :total="queueTotal"
                        :page-size="20"
                        layout="total, prev, pager, next"
                        @current-change="fetchQueue"
                    />
                </div>
            </el-tab-pane>

            <!-- ── 催缴策略 ── -->
            <el-tab-pane label="催缴策略" name="strategies">
                <div class="mb-4">
                    <el-button type="primary" @click="openStrategyDialog()">
                        <el-icon><Plus /></el-icon> 新建策略
                    </el-button>
                </div>
                <el-table :data="strategyList" stripe style="width: 100%">
                    <el-table-column type="index" label="#" width="50" />
                    <el-table-column prop="name" label="名称" min-width="180" />
                    <el-table-column prop="slug" label="标识" width="120">
                        <template #default="{ row }"><code>{{ row.slug }}</code></template>
                    </el-table-column>
                    <el-table-column prop="stageCount" label="阶段数" width="100">
                        <template #default="{ row }">{{ row.stages?.length ?? 0 }}</template>
                    </el-table-column>
                    <el-table-column prop="max_attempts" label="最大尝试" width="100" />
                    <el-table-column label="状态" width="90">
                        <template #default="{ row }">
                            <el-switch v-model="row.is_active" @change="toggleStrategyStatus(row)" />
                        </template>
                    </el-table-column>
                    <el-table-column label="排序" width="80">
                        <template #default="{ row }">{{ row.sort_order }}</template>
                    </el-table-column>
                    <el-table-column label="操作" width="150">
                        <template #default="{ row }">
                            <el-button size="small" @click="openStrategyDialog(row)">编辑</el-button>
                            <el-popconfirm title="确定删除此策略？" @confirm="deleteStrategy(row)">
                                <template #reference>
                                    <el-button size="small" type="danger">删除</el-button>
                                </template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- ── 催缴日志 ── -->
            <el-tab-pane label="催缴日志" name="logs">
                <el-card class="mb-4">
                    <el-form :model="logFilters" inline>
                        <el-form-item label="操作类型">
                            <el-select v-model="logFilters.action_taken" placeholder="全部" clearable style="width: 180px">
                                <el-option label="发送提醒" value="send_reminder" />
                                <el-option label="发送警告" value="send_warning" />
                                <el-option label="重试支付" value="retry_payment" />
                                <el-option label="降级" value="downgrade" />
                                <el-option label="暂停" value="suspend" />
                                <el-option label="人工升级" value="escalate" />
                                <el-option label="解决" value="resolve" />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="fetchLogs(1)">搜索</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>
                <el-table :data="logList" stripe size="small" style="width: 100%">
                    <el-table-column type="index" label="#" width="50" />
                    <el-table-column label="操作" width="130">
                        <template #default="{ row }">
                            <el-tag :type="actionTagType(row.action_taken)" size="small">
                                {{ actionLabel(row.action_taken) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="attempt_number" label="尝试#" width="80" />
                    <el-table-column label="渠道" width="100">
                        <template #default="{ row }">{{ row.channel || '-' }}</template>
                    </el-table-column>
                    <el-table-column label="成功" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.success ? 'success' : 'danger'" size="small">
                                {{ row.success ? '是' : '否' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="error_message" label="错误信息" min-width="250" show-overflow-tooltip />
                    <el-table-column label="时间" width="170" sortable>
                        <template #default="{ row }">{{ formatDate(row.actioned_at) }}</template>
                    </el-table-column>
                </el-table>
                <div class="pagination-wrapper">
                    <el-pagination
                        :current-page="logPage"
                        :total="logTotal"
                        :page-size="50"
                        layout="total, prev, pager, next"
                        @current-change="fetchLogs"
                    />
                </div>
            </el-tab-pane>
        </el-tabs>

        <!-- 催缴项详情对话框 -->
        <el-dialog v-model="detailVisible" title="催缴项详情" width="65%" top="5vh">
            <div v-if="detailData">
                <el-descriptions :column="2" border class="mb-4">
                    <el-descriptions-item label="客户">{{ detailData.customer?.name || 'N/A' }}</el-descriptions-item>
                    <el-descriptions-item label="发票号">{{ detailData.invoice?.invoice_no || 'N/A' }}</el-descriptions-item>
                    <el-descriptions-item label="金额">{{ formatMoney(detailData.amount_due) }} {{ detailData.currency }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="statusTagType(detailData.status)" size="small">{{ statusLabel(detailData.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="当前阶段">
                        <el-tag :type="stageTagType(detailData.current_stage)">
                            {{ stageLabel(detailData.current_stage) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="尝试次数">{{ detailData.attempt_count }}</el-descriptions-item>
                    <el-descriptions-item label="策略">{{ detailData.strategy?.name || '默认' }}</el-descriptions-item>
                    <el-descriptions-item label="加入时间">{{ formatDate(detailData.enqueued_at) }}</el-descriptions-item>
                    <el-descriptions-item label="下次操作">{{ formatDate(detailData.next_action_at) || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="备注">{{ detailData.notes || '-' }}</el-descriptions-item>
                </el-descriptions>
                <h4 class="mb-2">日志记录</h4>
                <el-table :data="detailData.logs ?? []" size="small" stripe max-height="400">
                    <el-table-column prop="attempt_number" label="尝试#" width="70" />
                    <el-table-column label="操作" width="130">
                        <template #default="{ row }">
                            <el-tag :type="actionTagType(row.action_taken)" size="small">
                                {{ actionLabel(row.action_taken) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="渠道" width="100">{{ row.channel || '-' }}</el-table-column>
                    <el-table-column label="成功" width="70">
                        <template #default="{ row }">
                            <el-tag :type="row.success ? 'success' : 'danger'" size="small">{{ row.success ? '是' : '否' }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="error_message" label="错误" min-width="200" show-overflow-tooltip />
                    <el-table-column label="时间" width="170">
                        <template #default="{ row }">{{ formatDate(row.actioned_at) }}</template>
                    </el-table-column>
                </el-table>
            </div>
        </el-dialog>

        <!-- 策略编辑对话框 -->
        <el-dialog v-model="strategyDialogVisible" :title="editingStrategy ? '编辑催缴策略' : '新建催缴策略'" width="75%" top="5vh">
            <el-form :model="strategyForm" label-width="120px" v-loading="strategySaving">
                <el-form-item label="名称" required>
                    <el-input v-model="strategyForm.name" placeholder="例如：标准催缴策略" />
                </el-form-item>
                <el-form-item label="标识" required>
                    <el-input v-model="strategyForm.slug" placeholder="例如：standard" :disabled="!!editingStrategy" />
                </el-form-item>
                <el-form-item label="描述">
                    <el-input v-model="strategyForm.description" type="textarea" :rows="2" />
                </el-form-item>
                <el-form-item label="最大尝试次数">
                    <el-input-number v-model="strategyForm.max_attempts" :min="1" :max="20" />
                </el-form-item>
                <el-form-item label="适用方案">
                    <el-select v-model="strategyForm.applicable_plans" multiple clearable placeholder="全部方案" style="width: 100%">
                        <el-option v-for="p in allPlans" :key="p" :label="p" :value="p" />
                    </el-select>
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="strategyForm.sort_order" :min="0" />
                </el-form-item>
                <el-divider>催缴阶段配置</el-divider>
                <div v-for="(stage, idx) in strategyForm.stages" :key="idx" class="stage-item">
                    <el-row :gutter="8" class="mb-2">
                        <el-col :span="4">
                            <el-form-item :label="`阶段 ${idx + 1}`" label-width="60">
                                <el-input-number v-model="stage.day" :min="0" :max="365" placeholder="天数" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="5">
                            <el-select v-model="stage.action" placeholder="操作" style="width: 100%">
                                <el-option label="发送提醒" value="send_reminder" />
                                <el-option label="发送警告" value="send_warning" />
                                <el-option label="重试支付" value="retry_payment" />
                                <el-option label="降级" value="downgrade" />
                                <el-option label="暂停" value="suspend" />
                                <el-option label="人工跟进" value="escalate" />
                            </el-select>
                        </el-col>
                        <el-col :span="4">
                            <el-select v-model="stage.channel" placeholder="渠道" style="width: 100%">
                                <el-option label="邮件" value="email" />
                                <el-option label="短信" value="sms" />
                                <el-option label="邮件+短信" value="email_and_sms" />
                                <el-option label="支付网关" value="payment_gateway" />
                                <el-option label="无" value="none" />
                            </el-select>
                        </el-col>
                        <el-col :span="9">
                            <el-input v-model="stage.subject" placeholder="主题/消息模板" />
                        </el-col>
                        <el-col :span="2">
                            <el-button type="danger" :icon="Delete" circle size="small" @click="removeStage(idx)" />
                        </el-col>
                    </el-row>
                </div>
                <el-button type="primary" @click="addStage">+ 添加阶段</el-button>
            </el-form>
            <template #footer>
                <el-button @click="strategyDialogVisible = false">取消</el-button>
                <el-button type="primary" @click="saveStrategy" :loading="strategySaving">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, Delete } from '@element-plus/icons-vue';
import dunningApi from '../../api/dunning';

const loading = ref(false);
const scanning = ref(false);
const running = ref(false);
const activeTab = ref('queue');

// Dashboard
const dashboard = reactive({
    total_active: 0,
    total_resolved: 0,
    total_failed: 0,
    total_due_amount: 0,
    by_stage: [],
    overdue_trend: [],
    action_distribution: {},
});

// Queue list
const queueList = ref([]);
const queuePage = ref(1);
const queueTotal = ref(0);
const queueFilters = reactive({
    status: '',
    search: '',
});

// Detail
const detailVisible = ref(false);
const detailData = ref(null);

// Strategies
const strategyList = ref([]);
const allPlans = ref([]);
const strategyDialogVisible = ref(false);
const editingStrategy = ref(null);
const strategySaving = ref(false);

const defaultStage = () => ({
    day: 0,
    action: 'send_reminder',
    channel: 'email',
    subject: '',
});

const strategyForm = reactive({
    name: '',
    slug: '',
    description: '',
    max_attempts: 5,
    applicable_plans: [],
    sort_order: 0,
    stages: [defaultStage()],
});

// Logs
const logList = ref([]);
const logPage = ref(1);
const logTotal = ref(0);
const logFilters = reactive({
    action_taken: '',
});

// ── Helpers ──
function formatMoney(val) {
    if (val === null || val === undefined) return '0.00';
    return Number(val).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleString('zh-CN', { hour12: false });
}

function stageLabel(stage) {
    const map = ['提醒', '第1次警告', '第2次警告', '重试支付', '降级', '暂停', '人工跟进'];
    return map[stage] || `阶段 ${stage}`;
}

function stageTagType(stage) {
    const map = ['', 'warning', 'warning', 'danger', 'danger', 'danger', 'info'];
    return map[stage] || 'info';
}

function statusLabel(status) {
    const map = { pending: '待处理', in_progress: '进行中', paid: '已支付', resolved: '已解决', failed: '已失败', expired: '已过期' };
    return map[status] || status;
}

function statusTagType(status) {
    const map = { pending: 'info', in_progress: 'warning', paid: 'success', resolved: 'success', failed: 'danger', expired: 'info' };
    return map[status] || 'info';
}

function actionLabel(action) {
    const map = { send_reminder: '发送提醒', send_warning: '发送警告', retry_payment: '重试支付', downgrade: '降级', suspend: '暂停', escalate: '人工跟进', resolve: '解决' };
    return map[action] || action;
}

function actionTagType(action) {
    const map = { send_reminder: '', send_warning: 'warning', retry_payment: 'danger', downgrade: 'warning', suspend: 'danger', escalate: 'info', resolve: 'success' };
    return map[action] || '';
}

// ── Data fetching ──
async function refreshAll() {
    loading.value = true;
    try {
        await Promise.all([
            fetchDashboard(),
            fetchQueue(),
            fetchStrategies(),
        ]);
        ElMessage.success('数据已刷新');
    } catch (e) {
        ElMessage.error('加载数据失败');
    } finally {
        loading.value = false;
    }
}

async function fetchDashboard() {
    try {
        const { data } = await dunningApi.dashboard();
        Object.assign(dashboard, data);
    } catch (e) {
        console.error('Failed to fetch dunning dashboard', e);
    }
}

async function fetchQueue(page = 1) {
    try {
        const params = { page, per_page: 20 };
        if (queueFilters.status) params.status = queueFilters.status;
        if (queueFilters.search) params.search = queueFilters.search;
        const { data } = await dunningApi.queue(params);
        queueList.value = data.data || [];
        queuePage.value = data.current_page || page;
        queueTotal.value = data.total || 0;
    } catch (e) {
        console.error('Failed to fetch dunning queue', e);
    }
}

function resetQueueFilters() {
    queueFilters.status = '';
    queueFilters.search = '';
    fetchQueue(1);
}

async function showQueueDetail(row) {
    detailVisible.value = true;
    detailData.value = null;
    try {
        const { data } = await dunningApi.showQueue(row.id);
        detailData.value = data;
    } catch (e) {
        ElMessage.error('获取详情失败');
    }
}

async function handleResolve(row) {
    try {
        await ElMessageBox.confirm(`确认解决 #${row.id} 的催缴？`, '确认', { type: 'info' });
    } catch {
        return;
    }
    try {
        await dunningApi.resolve(row.id);
        ElMessage.success('已解决');
        await fetchQueue(queuePage.value);
        await fetchDashboard();
    } catch (e) {
        ElMessage.error('操作失败');
    }
}

// ── Strategies ──
async function fetchStrategies() {
    try {
        const { data } = await dunningApi.strategies();
        strategyList.value = data || [];
    } catch (e) {
        console.error('Failed to fetch strategies', e);
    }
}

function openStrategyDialog(strategy = null) {
    editingStrategy.value = strategy;
    strategyDialogVisible.value = true;

    if (strategy) {
        Object.assign(strategyForm, {
            name: strategy.name,
            slug: strategy.slug,
            description: strategy.description || '',
            max_attempts: strategy.max_attempts ?? 5,
            applicable_plans: strategy.applicable_plans || [],
            sort_order: strategy.sort_order ?? 0,
            stages: (strategy.stages || []).length ? strategy.stages : [defaultStage()],
        });
    } else {
        Object.assign(strategyForm, {
            name: '',
            slug: '',
            description: '',
            max_attempts: 5,
            applicable_plans: [],
            sort_order: 0,
            stages: [defaultStage()],
        });
    }
}

function addStage() {
    strategyForm.stages.push(defaultStage());
}

function removeStage(idx) {
    if (strategyForm.stages.length <= 1) return;
    strategyForm.stages.splice(idx, 1);
}

async function saveStrategy() {
    strategySaving.value = true;
    try {
        const payload = { ...strategyForm };
        if (editingStrategy.value) {
            await dunningApi.updateStrategy(editingStrategy.value.id, payload);
            ElMessage.success('策略已更新');
        } else {
            await dunningApi.storeStrategy(payload);
            ElMessage.success('策略已创建');
        }
        strategyDialogVisible.value = false;
        await fetchStrategies();
    } catch (e) {
        ElMessage.error('保存失败');
    } finally {
        strategySaving.value = false;
    }
}

async function deleteStrategy(strategy) {
    try {
        await dunningApi.destroyStrategy(strategy.id);
        ElMessage.success('策略已删除');
        await fetchStrategies();
    } catch (e) {
        ElMessage.error('删除失败');
    }
}

async function toggleStrategyStatus(strategy) {
    try {
        await dunningApi.updateStrategy(strategy.id, { is_active: strategy.is_active });
    } catch (e) {
        strategy.is_active = !strategy.is_active;
        ElMessage.error('更新失败');
    }
}

// ── Actions ──
async function scanOverdue() {
    scanning.value = true;
    try {
        const { data } = await dunningApi.scanOverdue();
        ElMessage.success(`扫描完成，${data.enqueued} 条新催缴项`);
        await fetchDashboard();
        await fetchQueue(1);
    } catch (e) {
        ElMessage.error('扫描失败');
    } finally {
        scanning.value = false;
    }
}

async function runDunning() {
    running.value = true;
    try {
        const { data } = await dunningApi.run();
        ElMessage.success(`催缴运行完成：${data.processed} 条已处理`);
        await refreshAll();
    } catch (e) {
        ElMessage.error('催缴运行失败');
    } finally {
        running.value = false;
    }
}

async function fetchLogs(page = 1) {
    try {
        const params = { page, per_page: 50 };
        if (logFilters.action_taken) params.action_taken = logFilters.action_taken;
        const { data } = await dunningApi.logs(params);
        logList.value = data.data || [];
        logPage.value = data.current_page || page;
        logTotal.value = data.total || 0;
    } catch (e) {
        console.error('Failed to fetch logs', e);
    }
}

onMounted(() => {
    refreshAll();
});
</script>

<style scoped>
.dunning-page {
    padding: 16px;
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.page-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
}
.header-actions {
    display: flex;
    gap: 8px;
}
.stat-card {
    text-align: center;
    cursor: default;
}
.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1.2;
}
.stat-label {
    font-size: 0.8rem;
    color: #909399;
    margin-top: 4px;
}
.stat-success .stat-value {
    color: #67c23a;
}
.stat-danger .stat-value {
    color: #f56c6c;
}
.stat-warning .stat-value {
    color: #e6a23c;
}
.mb-4 {
    margin-bottom: 16px;
}
.mb-2 {
    margin-bottom: 8px;
}
.mt-2 {
    margin-top: 8px;
}
.pagination-wrapper {
    display: flex;
    justify-content: flex-end;
    padding: 16px 0;
}
.text-gray-400 {
    color: #c0c4cc;
}
.stage-item {
    padding: 8px;
    border: 1px solid #ebeef5;
    border-radius: 4px;
    margin-bottom: 8px;
    background: #fafafa;
}
</style>
