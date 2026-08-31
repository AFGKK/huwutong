<template>
    <div class="usage-meter-page">
        <div class="page-header">
            <h2>{{ t('usage_meter_page.title') }}</h2>
            <div class="header-meta">
                <el-tag type="info" size="small">{{ periodLabel }}</el-tag>
                <el-button @click="fetchOverview" :icon="Refresh" circle size="small" class="ml-2" />
            </div>
        </div>

        <el-row :gutter="16" class="mb-6">
            <el-col :span="6" v-for="metric in overviewMetrics" :key="metric.metric_key">
                <el-card shadow="never" class="metric-card" :class="{ 'card-warning': metric.usage_rate > 80, 'card-danger': metric.usage_rate >= 100 }">
                    <div class="metric-header">
                        <span class="metric-name">{{ metric.name }}</span>
                    </div>
                    <div class="metric-value">{{ formatNumber(metric.total) }}</div>
                    <div class="metric-footer">
                        <span v-if="metric.quota_limit !== null" class="metric-limit">
                            {{ t('usage_meter_page.quota_n', { n: formatNumber(metric.quota_limit) }) }}
                        </span>
                        <span v-else class="metric-limit text-muted">{{ t('usage_meter_page.unlimited') }}</span>
                        <span v-if="metric.usage_rate !== null" class="metric-rate" :class="rateColor(metric.usage_rate)">
                            {{ metric.usage_rate }}%
                        </span>
                    </div>
                    <el-progress
                        v-if="metric.quota_limit !== null"
                        :percentage="Math.min(metric.usage_rate || 0, 100)"
                        :color="progressColor(metric.usage_rate)"
                        :stroke-width="4"
                        class="metric-progress"
                    />
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never" class="section-card">
            <template #header>
                <div class="section-header">
                    <span>{{ t('usage_meter_page.quota_rules') }}</span>
                    <el-button type="primary" size="small" @click="showQuotaForm = true; editingQuota = null; quotaForm = defaultForm()">
                        <el-icon><Plus /></el-icon> {{ t('usage_meter_page.add_rule') }}
                    </el-button>
                </div>
            </template>

            <el-table :data="quotas" v-loading="loading.quotas" stripe>
                <el-table-column prop="metric_key" :label="t('usage_meter_page.cols.metric')" width="160">
                    <template #default="{ row }">
                        <el-tag size="small">{{ row.metric_key }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="window_type" :label="t('usage_meter_page.cols.window')" width="100">
                    <template #default="{ row }">
                        {{ windowTypeLabel(row.window_type) }}
                    </template>
                </el-table-column>
                <el-table-column prop="quota_limit" :label="t('usage_meter_page.cols.limit')" width="120" align="right" />
                <el-table-column :label="t('usage_meter_page.cols.scope')" min-width="180">
                    <template #default="{ row }">
                        <span v-if="row.license">{{ t('usage_meter_page.scope_license', { key: row.license.license_key }) }}</span>
                        <span v-else-if="row.product">{{ t('usage_meter_page.scope_product', { name: row.product.name }) }}</span>
                        <span v-else class="text-muted">{{ t('usage_meter_page.scope_global') }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="action_on_exceed" :label="t('usage_meter_page.cols.exceed')" width="120">
                    <template #default="{ row }">
                        <el-tag :type="row.action_on_exceed === 'block' ? 'danger' : row.action_on_exceed === 'warn' ? 'warning' : 'info'" size="small">
                            {{ exceedLabel(row.action_on_exceed) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="is_active" :label="t('usage_meter_page.cols.status')" width="80">
                    <template #default="{ row }">
                        <el-switch :model-value="row.is_active" disabled size="small" />
                    </template>
                </el-table-column>
                <el-table-column :label="t('usage_meter_page.cols.actions')" width="140" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" text @click="editQuota(row)">{{ t('actions.edit') }}</el-button>
                        <el-popconfirm :title="t('usage_meter_page.delete_confirm')" @confirm="deleteQuota(row)">
                            <template #reference>
                                <el-button size="small" text type="danger">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="quotas.length === 0 && !loading.quotas" class="empty-state">
                <el-empty :description="t('usage_meter_page.empty_quotas')" />
            </div>
        </el-card>

        <el-card shadow="never" class="section-card mt-4">
            <template #header>
                <div class="section-header">
                    <span>{{ t('usage_meter_page.trend_title') }}</span>
                    <div class="section-actions">
                        <el-select v-model="trendMetric" @change="fetchTrend" style="width: 200px">
                            <el-option v-for="m in metrics" :key="m.key" :label="m.name" :value="m.key" />
                        </el-select>
                        <el-select v-model="trendPeriod" @change="fetchTrend" style="width: 120px" class="ml-2">
                            <el-option value="monthly" :label="t('usage_meter_page.by_month')" />
                            <el-option value="daily" :label="t('usage_meter_page.by_day')" />
                        </el-select>
                    </div>
                </div>
            </template>

            <div v-loading="loading.trend">
                <el-table :data="trendData" v-if="trendData.length > 0" stripe>
                    <el-table-column prop="period_start" :label="t('usage_meter_page.cols.period_start')" width="140" />
                    <el-table-column prop="period_end" :label="t('usage_meter_page.cols.period_end')" width="140" />
                    <el-table-column prop="total" :label="t('usage_meter_page.cols.total')" align="right">
                        <template #default="{ row }">{{ formatNumber(row.total) }}</template>
                    </el-table-column>
                    <el-table-column prop="records" :label="t('usage_meter_page.cols.records')" align="right" />
                </el-table>
                <el-empty v-else :description="t('usage_meter_page.empty_trend')" />
            </div>
        </el-card>

        <el-dialog v-model="showQuotaForm" :title="editingQuota ? t('usage_meter_page.edit_rule') : t('usage_meter_page.add_rule_title')" width="500px">
            <el-form :model="quotaForm" :rules="quotaRules" ref="quotaFormRef" label-width="120px">
                <el-form-item :label="t('usage_meter_page.form.metric_key')" prop="metric_key">
                    <el-select v-model="quotaForm.metric_key" filterable style="width: 100%">
                        <el-option v-for="m in metrics" :key="m.key" :label="`${m.name} (${m.key})`" :value="m.key" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('usage_meter_page.form.window_type')" prop="window_type">
                    <el-radio-group v-model="quotaForm.window_type">
                        <el-radio value="monthly">{{ t('usage_meter_page.windows.monthly') }}</el-radio>
                        <el-radio value="daily">{{ t('usage_meter_page.windows.daily') }}</el-radio>
                        <el-radio value="total">{{ t('usage_meter_page.windows.total') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t('usage_meter_page.form.quota_limit')" prop="quota_limit">
                    <el-input-number v-model="quotaForm.quota_limit" :min="1" :max="999999999" style="width: 100%" />
                </el-form-item>
                <el-form-item :label="t('usage_meter_page.form.scope')">
                    <el-radio-group v-model="quotaForm.scope_type">
                        <el-radio value="global">{{ t('usage_meter_page.scope_global') }}</el-radio>
                        <el-radio value="product">{{ t('usage_meter_page.by_product') }}</el-radio>
                        <el-radio value="license">{{ t('usage_meter_page.by_license') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t('usage_meter_page.form.product')" v-if="quotaForm.scope_type === 'product'" prop="product_id">
                    <el-select v-model="quotaForm.product_id" filterable style="width: 100%" :placeholder="t('usage_meter_page.select_product')">
                        <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="License" v-if="quotaForm.scope_type === 'license'" prop="license_id">
                    <el-select v-model="quotaForm.license_id" filterable style="width: 100%" :placeholder="t('usage_meter_page.select_license')">
                        <el-option v-for="l in licenses" :key="l.id" :label="`${l.license_key} (${l.name ?? ''})`" :value="l.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('usage_meter_page.form.exceed')" prop="action_on_exceed">
                    <el-radio-group v-model="quotaForm.action_on_exceed">
                        <el-radio value="block">{{ t('usage_meter_page.exceed.block') }}</el-radio>
                        <el-radio value="warn">{{ t('usage_meter_page.exceed.warn') }}</el-radio>
                        <el-radio value="log">{{ t('usage_meter_page.exceed.log') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t('actions.enable')">
                    <el-switch v-model="quotaForm.is_active" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showQuotaForm = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="submitQuota" :loading="saving">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Plus, Refresh } from '@element-plus/icons-vue'
import usageMeterApi from '@/api/usageMeter'

const { t, locale } = useI18n()

const loading = reactive({ quotas: false, trend: false })
const overviewMetrics = ref([])
const metrics = ref([])
const quotas = ref([])
const products = ref([])
const licenses = ref([])
const periodLabel = computed(() => {
    const loc = locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
    const month = new Date().toLocaleDateString(loc, { year: 'numeric', month: 'long' })
    return t('usage_meter_page.period', { month })
})

const trendMetric = ref('api_call.activate')
const trendPeriod = ref('monthly')
const trendData = ref([])

const showQuotaForm = ref(false)
const editingQuota = ref(null)
const saving = ref(false)
const quotaFormRef = ref(null)

const defaultForm = () => ({
    metric_key: '',
    window_type: 'monthly',
    quota_limit: 1000,
    scope_type: 'global',
    product_id: null,
    license_id: null,
    action_on_exceed: 'block',
    is_active: true,
})

const quotaForm = ref(defaultForm())

const quotaRules = computed(() => ({
    metric_key: [{ required: true, message: t('usage_meter_page.validation.metric'), trigger: 'change' }],
    window_type: [{ required: true, message: t('usage_meter_page.validation.window'), trigger: 'change' }],
    quota_limit: [{ required: true, message: t('usage_meter_page.validation.limit'), trigger: 'blur' }],
}))

function formatNumber(num) {
    if (num === null || num === undefined) return '-'
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M'
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K'
    return num.toLocaleString()
}

function windowTypeLabel(type) {
    const key = { total: 'total', daily: 'daily', monthly: 'monthly', custom: 'custom' }[type]
    return key ? t(`usage_meter_page.windows.${key}`) : type
}

function exceedLabel(action) {
    const key = { block: 'block', warn: 'warn', log: 'log' }[action]
    return key ? t(`usage_meter_page.exceed.${key}`) : action
}

function rateColor(rate) {
    if (rate >= 100) return 'text-danger'
    if (rate > 80) return 'text-warning'
    return 'text-success'
}

function progressColor(rate) {
    if (rate >= 100) return '#f56c6c'
    if (rate > 80) return '#e6a23c'
    return '#0f172a'
}

async function fetchOverview() {
    try {
        const { data: res } = await usageMeterApi.getOverview()
        if (res.success) {
            overviewMetrics.value = res.data.metrics || []
        }
    } catch { /* handled by interceptor */ }
}

async function fetchMetrics() {
    try {
        const { data: res } = await usageMeterApi.getMetrics()
        if (res.success) {
            metrics.value = res.data || []
        }
    } catch { /* empty */ }
}

async function fetchQuotas() {
    loading.quotas = true
    try {
        const { data: res } = await usageMeterApi.getQuotas()
        if (res.success) {
            quotas.value = res.data?.data || []
        }
    } catch { /* empty */ }
    finally { loading.quotas = false }
}

async function fetchTrend() {
    loading.trend = true
    try {
        const { data: res } = await usageMeterApi.getStats({
            metric_key: trendMetric.value,
            period: trendPeriod.value,
            limit: 12,
        })
        if (res.success) {
            trendData.value = res.data?.data || []
        }
    } catch { /* empty */ }
    finally { loading.trend = false }
}

function editQuota(row) {
    editingQuota.value = row
    quotaForm.value = {
        metric_key: row.metric_key,
        window_type: row.window_type,
        quota_limit: row.quota_limit,
        scope_type: row.license_id ? 'license' : row.product_id ? 'product' : 'global',
        product_id: row.product_id,
        license_id: row.license_id,
        action_on_exceed: row.action_on_exceed,
        is_active: row.is_active,
    }
    showQuotaForm.value = true
}

async function submitQuota() {
    const valid = await quotaFormRef.value?.validate().catch(() => false)
    if (!valid) return

    saving.value = true
    try {
        const payload = {
            metric_key: quotaForm.value.metric_key,
            window_type: quotaForm.value.window_type,
            quota_limit: quotaForm.value.quota_limit,
            action_on_exceed: quotaForm.value.action_on_exceed,
            is_active: quotaForm.value.is_active,
        }
        if (quotaForm.value.scope_type === 'product' && quotaForm.value.product_id) {
            payload.product_id = quotaForm.value.product_id
        }
        if (quotaForm.value.scope_type === 'license' && quotaForm.value.license_id) {
            payload.license_id = quotaForm.value.license_id
        }

        const { data: res } = await usageMeterApi.upsertQuota(payload)
        if (res.success) {
            ElMessage.success(t('usage_meter_page.messages.saved'))
            showQuotaForm.value = false
            quotaForm.value = defaultForm()
            editingQuota.value = null
            await fetchQuotas()
            await fetchOverview()
        }
    } catch { /* empty */ }
    finally { saving.value = false }
}

async function deleteQuota(row) {
    try {
        const { data: res } = await usageMeterApi.deleteQuota(row.id)
        if (res.success) {
            ElMessage.success(t('usage_meter_page.messages.deleted'))
            await fetchQuotas()
            await fetchOverview()
        }
    } catch { /* empty */ }
}

onMounted(async () => {
    await Promise.all([
        fetchOverview(),
        fetchMetrics(),
        fetchQuotas(),
        fetchTrend(),
    ])
})
</script>

<style scoped>
.usage-meter-page {
    padding: 16px;
}

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.page-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}

.header-meta {
    display: flex;
    align-items: center;
    gap: 8px;
}

.mb-6 {
    margin-bottom: 24px;
}

.mt-4 {
    margin-top: 16px;
}

.ml-2 {
    margin-left: 8px;
}

.metric-card {
    transition: transform .2s;
}

.metric-card:hover {
    transform: translateY(-2px);
}

.metric-card.card-warning {
    border-left: 3px solid #e6a23c;
}

.metric-card.card-danger {
    border-left: 3px solid #f56c6c;
}

.metric-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.metric-name {
    font-size: 13px;
    color: #909399;
}

.metric-value {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 8px;
}

.metric-footer {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
}

.metric-limit {
    color: #909399;
}

.metric-rate {
    font-weight: 600;
}

.metric-progress {
    margin-top: 8px;
}

.section-card {
    margin-bottom: 16px;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.section-actions {
    display: flex;
    align-items: center;
}

.empty-state {
    padding: 40px 0;
}

.text-muted {
    color: #c0c4cc;
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
</style>
