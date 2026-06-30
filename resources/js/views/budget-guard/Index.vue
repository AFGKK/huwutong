<template>
    <div class="budget-guard-container">
        <el-page-header :content="'消费预警 + 预算上限'" @back="$router.push('/admin/dashboard')" />

        <!-- 概览卡片 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-label">总预算</div>
                        <div class="stat-value">{{ formatCurrency(dashboard.total_budget) }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-label">已消费</div>
                        <div class="stat-value">{{ formatCurrency(dashboard.total_spent) }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-label">预算使用率</div>
                        <div class="stat-value" :class="usageClass(dashboard.overall_usage)">
                            {{ dashboard.overall_usage }}%
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-label">待审批</div>
                        <div class="stat-value">
                            <el-badge :value="dashboard.pending_overrides" :hidden="!dashboard.pending_overrides">
                                <el-button size="small" type="warning" @click="activeTab = 'overrides'">
                                    查看
                                </el-button>
                            </el-badge>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 预警阈值参考 -->
        <el-alert
            :title="`预警阈值: 警告 ${dashboard.alert_thresholds?.warning || 80}% | 严重 ${dashboard.alert_thresholds?.critical || 95}% | 拦截 ${dashboard.alert_thresholds?.hard_limit || 100}%`"
            type="info"
            show-icon
            :closable="false"
            class="threshold-alert"
        />

        <!-- 主体 Tabs -->
        <el-tabs v-model="activeTab" class="main-tabs">
            <!-- Tab: 预算配置 -->
            <el-tab-pane label="预算配置" name="config">
                <div class="section-header">
                    <h3>预算列表</h3>
                    <el-button type="primary" size="small" @click="openCreateDialog">新建预算</el-button>
                </div>

                <el-table :data="budgets" v-loading="loading" stripe>
                    <el-table-column prop="budgetable_type" label="类型" width="100" />
                    <el-table-column prop="budgetable_id" label="对象ID" width="80" />
                    <el-table-column prop="period" label="周期" width="100">
                        <template #default="{ row }">
                            <el-tag :type="periodTagType(row.period)" size="small">{{ periodLabel(row.period) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="budget_amount" label="预算金额" width="140">
                        <template #default="{ row }">
                            {{ formatCurrency(row.budget_amount) }}
                        </template>
                    </el-table-column>
                    <el-table-column label="已使用" width="140">
                        <template #default="{ row }">
                            {{ formatCurrency(row.spent_amount + row.pending_amount) }}
                        </template>
                    </el-table-column>
                    <el-table-column label="使用率" width="160">
                        <template #default="{ row }">
                            <el-progress
                                :percentage="Math.min(row.spent_amount > 0 || row.budget_amount > 0 ? Math.round((row.spent_amount + row.pending_amount) / row.budget_amount * 100) : 0, 100)"
                                :status="progressStatus(row)"
                                :stroke-width="16"
                            />
                        </template>
                    </el-table-column>
                    <el-table-column prop="status" label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">
                                {{ row.status === 'active' ? '启用' : '暂停' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="200" fixed="right">
                        <template #default="{ row }">
                            <el-button size="small" @click="viewDetail(row)">详情</el-button>
                            <el-button size="small" type="danger" plain @click="handleDelete(row)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <el-pagination
                    v-if="total > 0"
                    v-model:current-page="page"
                    :page-size="20"
                    :total="total"
                    layout="prev, pager, next"
                    @current-change="fetchBudgets"
                    class="pagination"
                />
            </el-tab-pane>

            <!-- Tab: 预警记录 -->
            <el-tab-pane label="预警记录" name="alerts">
                <el-table :data="alerts" v-loading="loadingAlerts" stripe>
                    <el-table-column prop="level" label="级别" width="120">
                        <template #default="{ row }">
                            <el-tag :type="alertLevelTag(row.level)" size="small">
                                {{ alertLevelLabel(row.level) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="usage_percentage" label="使用率" width="120">
                        <template #default="{ row }">{{ row.usage_percentage }}%</template>
                    </el-table-column>
                    <el-table-column prop="spent_at_alert" label="触发时消费" width="140">
                        <template #default="{ row }">{{ formatCurrency(row.spent_at_alert) }}</template>
                    </el-table-column>
                    <el-table-column prop="notified" label="已通知" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.notified ? 'success' : 'info'" size="small">
                                {{ row.notified ? '是' : '否' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="created_at" label="触发时间" width="180" />
                </el-table>
            </el-tab-pane>

            <!-- Tab: 超额审批 -->
            <el-tab-pane label="超额审批" name="overrides">
                <el-table :data="overrides" v-loading="loadingOverrides" stripe>
                    <el-table-column label="所属预算ID" width="120">
                        <template #default="{ row }">{{ row.budget_limit_id }}</template>
                    </el-table-column>
                    <el-table-column label="请求金额" width="120">
                        <template #default="{ row }">{{ formatCurrency(row.requested_amount) }}</template>
                    </el-table-column>
                    <el-table-column label="申请后使用率" width="130">
                        <template #default="{ row }">{{ row.override_percentage }}%</template>
                    </el-table-column>
                    <el-table-column prop="reason" label="原因" min-width="200" show-overflow-tooltip />
                    <el-table-column prop="status" label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag :type="overrideStatusTag(row.status)" size="small">
                                {{ overrideStatusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="expires_at" label="过期时间" width="180" />
                    <el-table-column label="操作" width="180" fixed="right" v-if="hasPending">
                        <template #default="{ row }">
                            <el-button v-if="row.status === 'pending'" size="small" type="success" @click="handleApprove(row)">
                                通过
                            </el-button>
                            <el-button v-if="row.status === 'pending'" size="small" type="danger" @click="handleReject(row)">
                                拒绝
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- Tab: 消费检查 -->
            <el-tab-pane label="消费检查" name="check">
                <el-card>
                    <template #header>模拟消费检查</template>
                    <el-form :model="checkForm" label-width="120px">
                        <el-form-item label="对象类型">
                            <el-select v-model="checkForm.budgetable_type">
                                <el-option label="客户" value="customer" />
                                <el-option label="租户" value="tenant" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="对象ID">
                            <el-input-number v-model="checkForm.budgetable_id" :min="1" />
                        </el-form-item>
                        <el-form-item label="消费金额">
                            <el-input-number v-model="checkForm.amount" :min="0" :precision="2" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="handleCheckSpend" :loading="checking">
                                检查
                            </el-button>
                        </el-form-item>
                    </el-form>
                    <el-alert v-if="checkResult !== null" :type="checkResult.allowed ? 'success' : 'error'" show-icon>
                        <template #title>
                            {{ checkResult.allowed ? '✅ 消费允许' : '❌ 消费被拦截' }}
                        </template>
                        {{ checkResult.reason || '无限制' }}
                    </el-alert>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 新建/编辑预算 Dialog -->
        <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑预算' : '新建预算'" width="600px">
            <el-form :model="form" label-width="120px" :rules="formRules" ref="formRef">
                <el-form-item label="对象类型" prop="budgetable_type" v-if="!isEdit">
                    <el-select v-model="form.budgetable_type">
                        <el-option label="客户" value="customer" />
                        <el-option label="租户" value="tenant" />
                    </el-select>
                </el-form-item>
                <el-form-item label="对象ID" prop="budgetable_id" v-if="!isEdit">
                    <el-input-number v-model="form.budgetable_id" :min="1" />
                </el-form-item>
                <el-form-item label="预算周期" prop="period">
                    <el-select v-model="form.period">
                        <el-option label="月度" value="monthly" />
                        <el-option label="季度" value="quarterly" />
                        <el-option label="年度" value="yearly" />
                    </el-select>
                </el-form-item>
                <el-form-item label="预算金额" prop="budget_amount">
                    <el-input-number v-model="form.budget_amount" :min="0" :precision="2" style="width:200px" />
                </el-form-item>
                <el-form-item label="货币">
                    <el-input v-model="form.currency" maxlength="3" style="width:100px" placeholder="CNY" />
                </el-form-item>
                <el-form-item label="启用通知">
                    <el-switch v-model="form.notifications_enabled" />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="form.notes" type="textarea" :rows="3" maxlength="500" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">保存</el-button>
            </template>
        </el-dialog>

        <!-- 详情 Dialog -->
        <el-dialog v-model="detailVisible" title="预算详情" width="700px">
            <template v-if="detail">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="对象类型">{{ detail.budget?.budgetable_type }}</el-descriptions-item>
                    <el-descriptions-item label="对象ID">{{ detail.budget?.budgetable_id }}</el-descriptions-item>
                    <el-descriptions-item label="周期">{{ periodLabel(detail.budget?.period) }}</el-descriptions-item>
                    <el-descriptions-item label="状态">{{ detail.budget?.status }}</el-descriptions-item>
                    <el-descriptions-item label="预算金额">{{ formatCurrency(detail.budget?.budget_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="已使用">{{ formatCurrency(detail.budget?.spent_amount + detail.budget?.pending_amount) }}</el-descriptions-item>
                    <el-descriptions-item label="使用率">
                        <el-progress
                            :percentage="Math.min(Math.round(detail.usage_percentage), 100)"
                            :status="detail.is_exceeded ? 'exception' : detail.usage_percentage > 80 ? 'warning' : 'success'"
                            :stroke-width="20"
                        />
                        <span>{{ detail.usage_percentage }}%</span>
                    </el-descriptions-item>
                    <el-descriptions-item label="剩余">{{ formatCurrency(detail.remaining) }}</el-descriptions-item>
                </el-descriptions>

                <el-divider />
                <h4>预警记录</h4>
                <el-table :data="detailAlerts" v-loading="loadingDetailAlerts" stripe size="small">
                    <el-table-column prop="level" label="级别" width="100">
                        <template #default="{ row }">
                            <el-tag :type="alertLevelTag(row.level)" size="small">{{ alertLevelLabel(row.level) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="usage_percentage" label="使用率" width="80">{{ row => row.usage_percentage }}%</el-table-column>
                    <el-table-column prop="created_at" label="时间" width="170" />
                </el-table>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getBudgetList, getBudgetDetail, saveBudget, updateBudget, deleteBudget,
    getBudgetDashboard, checkBudgetSpend, requestBudgetOverride,
    approveBudgetOverride, rejectBudgetOverride, getPendingOverrides,
    getBudgetAlertHistory,
} from '@/api/budgetGuard'

const activeTab = ref('config')
const loading = ref(false)
const budgets = ref([])
const total = ref(0)
const page = ref(1)
const dashboard = ref({
    total_budget: 0,
    total_spent: 0,
    overall_usage: 0,
    by_period: {},
    pending_overrides: 0,
    alert_thresholds: { warning: 80, critical: 95, hard_limit: 100 },
})
const alerts = ref([])
const loadingAlerts = ref(false)
const overrides = ref([])
const loadingOverrides = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)
const editingId = ref(null)
const form = ref({
    budgetable_type: 'customer',
    budgetable_id: 1,
    period: 'monthly',
    budget_amount: 1000,
    currency: 'CNY',
    notifications_enabled: true,
    notes: '',
})
const formRules = {
    budgetable_type: [{ required: true, message: '请选择对象类型' }],
    budgetable_id: [{ required: true, type: 'number', min: 1, message: '请输入对象ID' }],
    period: [{ required: true, message: '请选择周期' }],
    budget_amount: [{ required: true, type: 'number', min: 0, message: '请输入预算金额' }],
}
const formRef = ref(null)
const saving = ref(false)
const detailVisible = ref(false)
const detail = ref(null)
const detailAlerts = ref([])
const loadingDetailAlerts = ref(false)

const checkForm = ref({
    budgetable_type: 'customer',
    budgetable_id: 1,
    amount: 100,
})
const checkResult = ref(null)
const checking = ref(false)

const hasPending = computed(() => overrides.value.some(o => o.status === 'pending'))

onMounted(() => {
    fetchDashboard()
    fetchBudgets()
})

async function fetchDashboard() {
    try {
        const res = await getBudgetDashboard({ budgetable_type: 'customer', budgetable_id: 1 })
        if (res.data) dashboard.value = res.data
    } catch { /* ignore */ }
}

async function fetchBudgets() {
    loading.value = true
    try {
        const res = await getBudgetList({ page: page.value, per_page: 20 })
        budgets.value = res.data?.data || []
        total.value = res.data?.total || 0
    } catch { /* ignore */ }
    loading.value = false
}

function periodLabel(p) {
    return { monthly: '月度', quarterly: '季度', yearly: '年度' }[p] || p
}

function periodTagType(p) {
    return { monthly: 'primary', quarterly: 'warning', yearly: 'success' }[p] || 'info'
}

function formatCurrency(val) {
    return val != null ? `¥${Number(val).toLocaleString('zh-CN', { minimumFractionDigits: 2 })}` : '¥0.00'
}

function progressStatus(row) {
    const pct = row.budget_amount > 0 ? (row.spent_amount + row.pending_amount) / row.budget_amount : 0
    if (pct >= 1) return 'exception'
    if (pct >= 0.8) return 'warning'
    return 'success'
}

function usageClass(pct) {
    if (pct >= 100) return 'text-danger'
    if (pct >= 80) return 'text-warning'
    return 'text-success'
}

function alertLevelTag(level) {
    return { warning: 'warning', critical: 'danger', blocked: 'danger', info: 'info' }[level] || 'info'
}

function alertLevelLabel(level) {
    return { warning: '警告', critical: '严重', blocked: '拦截', info: '提醒' }[level] || level
}

function overrideStatusTag(status) {
    return { pending: 'warning', approved: 'success', rejected: 'danger' }[status] || 'info'
}

function overrideStatusLabel(status) {
    return { pending: '待审批', approved: '已通过', rejected: '已拒绝' }[status] || status
}

function openCreateDialog() {
    isEdit.value = false
    editingId.value = null
    form.value = {
        budgetable_type: 'customer',
        budgetable_id: 1,
        period: 'monthly',
        budget_amount: 1000,
        currency: 'CNY',
        notifications_enabled: true,
        notes: '',
    }
    dialogVisible.value = true
}

async function handleSave() {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return
    saving.value = true
    try {
        if (isEdit.value && editingId.value) {
            await updateBudget(editingId.value, form.value)
            ElMessage.success('预算已更新')
        } else {
            await saveBudget(form.value)
            ElMessage.success('预算已创建')
        }
        dialogVisible.value = false
        fetchBudgets()
        fetchDashboard()
    } catch (e) {
        ElMessage.error(e.message || '操作失败')
    }
    saving.value = false
}

async function viewDetail(row) {
    detailVisible.value = true
    detail.value = null
    detailAlerts.value = []
    try {
        const res = await getBudgetDetail(row.id)
        detail.value = res.data || res
        // fetch alerts
        loadingDetailAlerts.value = true
        const alertRes = await getBudgetAlertHistory(row.id)
        detailAlerts.value = alertRes.data?.alerts || []
        loadingDetailAlerts.value = false
    } catch { /* ignore */ }
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(`确定删除此预算配置？`, '确认')
        await deleteBudget(row.id)
        ElMessage.success('已删除')
        fetchBudgets()
        fetchDashboard()
    } catch { /* ignore */ }
}

async function handleCheckSpend() {
    checking.value = true
    try {
        const res = await checkBudgetSpend(checkForm.value)
        checkResult.value = res.data || { allowed: true }
    } catch (e) {
        checkResult.value = { allowed: false, reason: e.message || '检查失败' }
    }
    checking.value = false
}

// 切换到 overrides tab 时加载待审批
import { watch } from 'vue'
watch(activeTab, async (tab) => {
    if (tab === 'alerts') {
        await fetchAlerts()
    } else if (tab === 'overrides') {
        await fetchOverrides()
    }
})

async function fetchAlerts() {
    loadingAlerts.value = true
    try {
        // 取第一个预算的预警，或者取最近所有预警
        if (budgets.value.length > 0) {
            const res = await getBudgetAlertHistory(budgets.value[0].id)
            alerts.value = res.data?.alerts || []
        }
    } catch { /* ignore */ }
    loadingAlerts.value = false
}

async function fetchOverrides() {
    loadingOverrides.value = true
    try {
        const res = await getPendingOverrides()
        overrides.value = res.data?.overrides || []
    } catch { /* ignore */ }
    loadingOverrides.value = false
}

async function handleApprove(row) {
    try {
        await approveBudgetOverride(row.id)
        ElMessage.success('已通过')
        fetchOverrides()
        fetchDashboard()
    } catch (e) {
        ElMessage.error(e.message || '操作失败')
    }
}

async function handleReject(row) {
    try {
        await rejectBudgetOverride(row.id)
        ElMessage.success('已拒绝')
        fetchOverrides()
    } catch (e) {
        ElMessage.error(e.message || '操作失败')
    }
}
</script>

<style scoped>
.budget-guard-container {
    padding: 20px;
}

.stat-cards {
    margin-top: 20px;
    margin-bottom: 16px;
}

.stat-item {
    text-align: center;
}

.stat-label {
    font-size: 14px;
    color: #909399;
    margin-bottom: 8px;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
}

.text-success {
    color: #67c23a;
}

.text-warning {
    color: #e6a23c;
}

.text-danger {
    color: #f56c6c;
}

.threshold-alert {
    margin-bottom: 16px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.section-header h3 {
    margin: 0;
}

.pagination {
    margin-top: 16px;
    text-align: center;
}

.main-tabs {
    margin-top: 8px;
}
</style>
