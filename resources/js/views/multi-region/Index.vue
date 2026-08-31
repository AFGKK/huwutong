<script setup>
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../api/multiRegion.js'

const { t, locale } = useI18n()
const P = 'multi_region_page'

const loading = ref(false)
const activeTab = ref('overview')
const dashboard = ref(null)
const dataCenters = ref([])
const failoverRules = ref([])
const failoverLogs = ref([])
const logPagination = ref({ total: 0, current_page: 1 })
const logFilters = ref({ action: '', rule_id: '', date_from: '', date_to: '' })
const healthTrendData = ref([])

const regionDeployments = ref([])
const syncLogs = ref([])
const syncPagination = ref({ total: 0, current_page: 1 })
const optimalRegion = ref(null)

const dcDialog = ref(false)
const isEditDc = ref(false)
const currentDc = ref(null)
const dcForm = ref({ name: '', code: '', region: 'asia', country_code: '', city: '', is_active: true, sort_order: 0, base_url: '', health_check_url: '', capabilities: [], status: 'healthy' })

const ruleDialog = ref(false)
const isEditRule = ref(false)
const currentRule = ref(null)
const ruleForm = ref({ name: '', primary_dc_id: '', backup_dc_id: '', trigger_type: 'latency', trigger_threshold_ms: 300, failure_count_threshold: 3, auto_failover: false, is_active: true, notes: '' })
const failoverReason = ref('')
const failoverDialog = ref(false)
const failoverAction = ref('failover')

const regionDialog = ref(false)
const isEditRegion = ref(false)
const currentRegion = ref(null)
const regionForm = ref({ region_key: '', name: '', provider: 'aws', api_url: '', weight: 100, is_primary: false, status: 'active', version: '' })

const syncDialog = ref(false)
const syncForm = ref({ source_region: '', target_region: '', data_type: 'license' })
const syncFilters = ref({ status: '', data_type: '', source_region: '' })

const statusTypes = { healthy: 'success', degraded: 'warning', down: 'danger', maintenance: 'info' }
const ruleStatusTypes = { active: 'success', failover: 'warning', restoring: '', inactive: 'info' }
const regionDeployStatusTypes = { active: 'success', degraded: 'warning', inactive: 'info' }
const syncStatusTypes = { pending: 'info', running: 'warning', completed: 'success', failed: 'danger', cancelled: '' }
const routingStrategy = ref('geo_dns')

const regionLabels = computed(() => Object.fromEntries(
    ['asia', 'europe', 'us', 'oceania', 'africa', 'south_america'].map((k) => [k, t(`${P}.regions.${k}`)])
))
const statusLabels = computed(() => Object.fromEntries(
    ['healthy', 'degraded', 'down', 'maintenance'].map((k) => [k, t(`${P}.dc_status.${k}`)])
))
const dcStatusShortOptions = computed(() =>
    ['healthy', 'degraded', 'down', 'maintenance'].map((k) => ({ value: k, label: t(`${P}.dc_status_short.${k}`) }))
)
const triggerLabels = computed(() => Object.fromEntries(
    ['latency', 'down', 'manual'].map((k) => [k, t(`${P}.trigger.${k}`)])
))
const triggerOptions = computed(() =>
    ['latency', 'down', 'manual'].map((k) => ({ value: k, label: t(`${P}.trigger.${k}`) }))
)
const ruleStatusLabels = computed(() => Object.fromEntries(
    ['active', 'failover', 'restoring', 'inactive'].map((k) => [k, t(`${P}.rule_status.${k}`)])
))
const actionLabels = computed(() => Object.fromEntries(
    ['failover', 'restore', 'manual_failover', 'manual_restore'].map((k) => [k, t(`${P}.log_action.${k}`)])
))
const regionDeployStatusLabels = computed(() => Object.fromEntries(
    ['active', 'degraded', 'inactive'].map((k) => [k, t(`${P}.region_deploy_status.${k}`)])
))
const regionDeployStatusOptions = computed(() =>
    ['active', 'degraded', 'inactive'].map((k) => ({ value: k, label: t(`${P}.region_deploy_status.${k}`) }))
)
const syncStatusLabels = computed(() => Object.fromEntries(
    ['pending', 'running', 'completed', 'failed', 'cancelled'].map((k) => [k, t(`${P}.sync_status.${k}`)])
))
const dataTypeLabels = computed(() => Object.fromEntries(
    ['license', 'customer', 'product', 'audit_log'].map((k) => [k, t(`${P}.data_type.${k}`)])
))
const dataTypeOptions = computed(() =>
    ['license', 'customer', 'product', 'audit_log'].map((k) => ({ value: k, label: t(`${P}.data_type.${k}`) }))
)
const regionKeyLabels = computed(() => Object.fromEntries(
    ['us-east', 'eu-west', 'ap-southeast'].map((k) => [k, t(`${P}.region_key.${k}`)])
))
const regionProviderIcons = { aws: 'AWS', gcp: 'GCP', azure: 'Azure' }
function providerLabel(p) {
    return regionProviderIcons[p] || (p === 'aliyun' ? t(`${P}.provider.aliyun`) : p)
}
const strategyLabels = computed(() => Object.fromEntries(
    ['geo_dns', 'latency_based', 'weighted_random'].map((k) => [k, t(`${P}.strategy.${k}`)])
))
const tabLabels = computed(() => ({
    overview: t(`${P}.tabs.data_centers`),
    failover: t(`${P}.tabs.failover`),
    'region-deploy': t(`${P}.tabs.region_deploy`),
}))
const yesLabel = computed(() => t(`${P}.yes`))
const noLabel = computed(() => t(`${P}.no`))

async function loadDashboard() {
    try {
        const res = await api.dashboard()
        dashboard.value = res.data.data
        dataCenters.value = res.data.data.data_centers || []
        failoverRules.value = res.data.data.failover_rules || []
        regionDeployments.value = res.data.data.region_deployments || []
    } catch (e) {}
}

async function loadFailoverLogs(page = 1) {
    try {
        const params = { page, per_page: 15 }
        if (logFilters.value.action) params.action = logFilters.value.action
        if (logFilters.value.rule_id) params.rule_id = logFilters.value.rule_id
        if (logFilters.value.date_from) params.date_from = logFilters.value.date_from
        if (logFilters.value.date_to) params.date_to = logFilters.value.date_to
        const res = await api.listFailoverLogs(params)
        const d = res.data.data
        failoverLogs.value = d?.data || d || []
        logPagination.value = { total: d?.total || 0, current_page: d?.current_page || page }
    } catch (e) {}
}

async function loadRegionDeployments() {
    try {
        const res = await api.listRegionDeployments()
        regionDeployments.value = res.data.data || []
    } catch (e) {}
}

async function loadSyncLogs(page = 1) {
    try {
        const params = { page, per_page: 15 }
        if (syncFilters.value.status) params.status = syncFilters.value.status
        if (syncFilters.value.data_type) params.data_type = syncFilters.value.data_type
        if (syncFilters.value.source_region) params.source_region = syncFilters.value.source_region
        const res = await api.listSyncLogs(params)
        const d = res.data.data
        syncLogs.value = d?.data || d || []
        syncPagination.value = { total: d?.total || 0, current_page: d?.current_page || page }
    } catch (e) {}
}

async function loadHealthTrend(dcId) {
    try {
        const res = await api.healthTrend(dcId)
        healthTrendData.value = res.data.data || []
    } catch (e) {}
}

function openCreateDc() {
    isEditDc.value = false
    dcForm.value = { name: '', code: '', region: 'asia', country_code: '', city: '', is_active: true, sort_order: 0, base_url: '', health_check_url: '', capabilities: [], status: 'healthy' }
    dcDialog.value = true
}

function openEditDc(dc) {
    isEditDc.value = true
    currentDc.value = dc
    dcForm.value = { ...dc, capabilities: dc.capabilities || [] }
    dcDialog.value = true
}

async function submitDc() {
    try {
        if (isEditDc.value) {
            await api.updateDataCenter(currentDc.value.id, dcForm.value)
            ElMessage.success(t(`${P}.messages.dc_updated`))
        } else {
            await api.storeDataCenter(dcForm.value)
            ElMessage.success(t(`${P}.messages.dc_created`))
        }
        dcDialog.value = false
        loadDashboard()
    } catch (e) { ElMessage.error(t('messages.failed')) }
}

async function deleteDc(dc) {
    try {
        await ElMessageBox.confirm(t(`${P}.confirm.delete_dc`, { name: dc.name }), t('actions.confirm'), { type: 'warning' })
        await api.destroyDataCenter(dc.id)
        ElMessage.success(t(`${P}.messages.deleted`))
        loadDashboard()
    } catch (e) {}
}

async function runHealthCheck(dc) {
    try {
        await api.healthCheck(dc.id)
        ElMessage.success(t(`${P}.messages.health_check_done`, { name: dc.name }))
        loadDashboard()
    } catch (e) { ElMessage.error(t(`${P}.messages.health_check_failed`)) }
}

async function runHealthCheckAll() {
    try {
        await api.healthCheckAll()
        ElMessage.success(t(`${P}.messages.all_health_check_done`))
        loadDashboard()
    } catch (e) { ElMessage.error(t(`${P}.messages.health_check_failed`)) }
}

function openCreateRule() {
    isEditRule.value = false
    ruleForm.value = { name: '', primary_dc_id: '', backup_dc_id: '', trigger_type: 'latency', trigger_threshold_ms: 300, failure_count_threshold: 3, auto_failover: false, is_active: true, notes: '' }
    ruleDialog.value = true
}

function openEditRule(rule) {
    isEditRule.value = true
    currentRule.value = rule
    ruleForm.value = { ...rule, primary_dc_id: rule.primary_dc_id, backup_dc_id: rule.backup_dc_id }
    ruleDialog.value = true
}

async function submitRule() {
    try {
        if (isEditRule.value) {
            await api.updateFailoverRule(currentRule.value.id, ruleForm.value)
            ElMessage.success(t(`${P}.messages.rule_updated`))
        } else {
            await api.storeFailoverRule(ruleForm.value)
            ElMessage.success(t(`${P}.messages.rule_created`))
        }
        ruleDialog.value = false
        loadDashboard()
    } catch (e) { ElMessage.error(t('messages.failed')) }
}

async function deleteRule(rule) {
    try {
        await ElMessageBox.confirm(t(`${P}.confirm.delete_rule`, { name: rule.name }), t('actions.confirm'), { type: 'warning' })
        await api.destroyFailoverRule(rule.id)
        ElMessage.success(t(`${P}.messages.deleted`))
        loadDashboard()
    } catch (e) {}
}

function openFailoverDialog(rule, action) {
    currentRule.value = rule
    failoverAction.value = action
    failoverReason.value = ''
    failoverDialog.value = true
}

async function submitFailoverAction() {
    if (!failoverReason.value) { ElMessage.warning(t(`${P}.messages.reason_required`)); return }
    try {
        if (failoverAction.value === 'failover') {
            await api.executeFailover(currentRule.value.id, failoverReason.value)
            ElMessage.success(t(`${P}.messages.failover_executed`))
        } else {
            await api.executeRestore(currentRule.value.id, failoverReason.value)
            ElMessage.success(t(`${P}.messages.restored_primary`))
        }
        failoverDialog.value = false
        loadDashboard()
        loadFailoverLogs()
    } catch (e) { ElMessage.error(t('messages.failed')) }
}

async function seedDcs() {
    try {
        await api.seedDataCenters()
        ElMessage.success(t(`${P}.messages.seed_dcs_done`))
        loadDashboard()
    } catch (e) { ElMessage.error(t(`${P}.messages.seed_failed`)) }
}

async function seedRegions() {
    try {
        await api.seedRegionDeployments()
        ElMessage.success(t(`${P}.messages.seed_regions_done`))
        loadRegionDeployments()
        loadDashboard()
    } catch (e) { ElMessage.error(t(`${P}.messages.seed_failed`)) }
}

function openCreateRegion() {
    isEditRegion.value = false
    regionForm.value = { region_key: '', name: '', provider: 'aws', api_url: '', weight: 100, is_primary: false, status: 'active', version: '' }
    regionDialog.value = true
}

function openEditRegion(region) {
    isEditRegion.value = true
    currentRegion.value = region
    regionForm.value = { ...region }
    regionDialog.value = true
}

async function submitRegion() {
    try {
        if (isEditRegion.value) {
            await api.updateRegionDeployment(currentRegion.value.id, regionForm.value)
            ElMessage.success(t(`${P}.messages.region_updated`))
        } else {
            await api.storeRegionDeployment(regionForm.value)
            ElMessage.success(t(`${P}.messages.region_created`))
        }
        regionDialog.value = false
        loadRegionDeployments()
        loadDashboard()
    } catch (e) { ElMessage.error(t('messages.failed')) }
}

async function deleteRegion(region) {
    try {
        await ElMessageBox.confirm(t(`${P}.confirm.delete_region`, { name: region.name }), t('actions.confirm'), { type: 'warning' })
        await api.destroyRegionDeployment(region.id)
        ElMessage.success(t(`${P}.messages.deleted`))
        loadRegionDeployments()
        loadDashboard()
    } catch (e) {}
}

function openSyncDialog() {
    syncForm.value = { source_region: '', target_region: '', data_type: 'license' }
    syncDialog.value = true
}

async function submitSync() {
    if (!syncForm.value.source_region || !syncForm.value.target_region) {
        ElMessage.warning(t(`${P}.messages.select_regions`))
        return
    }
    if (syncForm.value.source_region === syncForm.value.target_region) {
        ElMessage.warning(t(`${P}.messages.same_region`))
        return
    }
    try {
        await api.startDataSync(syncForm.value)
        ElMessage.success(t(`${P}.messages.sync_started`))
        syncDialog.value = false
        loadSyncLogs()
    } catch (e) { ElMessage.error(t(`${P}.messages.sync_start_failed`)) }
}

async function runAllRegionHealth() {
    try {
        await api.checkAllRegionHealth()
        ElMessage.success(t(`${P}.messages.all_region_health_done`))
        loadRegionDeployments()
        loadDashboard()
    } catch (e) { ElMessage.error(t(`${P}.messages.check_failed`)) }
}

async function runCrossRegionHealth() {
    try {
        await api.crossRegionHealthCheck()
        ElMessage.success(t(`${P}.messages.cross_check_done`))
    } catch (e) { ElMessage.error(t(`${P}.messages.cross_check_failed`)) }
}

async function queryOptimalRegion() {
    try {
        const res = await api.getOptimalRegion()
        optimalRegion.value = res.data.data
    } catch (e) { ElMessage.error(t(`${P}.messages.query_failed`)) }
}

async function runAutoFailoverCheck() {
    try {
        await api.autoFailoverCheck()
        ElMessage.success(t(`${P}.messages.auto_detect_done`))
        loadFailoverLogs()
        loadDashboard()
    } catch (e) { ElMessage.error(t(`${P}.messages.check_failed`)) }
}

function fmtDate(d) {
    if (!d) return '-'
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'
    return new Date(d).toLocaleString(loc)
}
function fmtDateShort(d) {
    if (!d) return '-'
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'
    return new Date(d).toLocaleString(loc, { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function failoverFlowText() {
    const rule = currentRule.value
    if (!rule) return ''
    const from = rule.primary_dc?.name || '-'
    const to = failoverAction.value === 'failover' ? (rule.backup_dc?.name || '-') : (rule.primary_dc?.name || '-')
    const key = failoverAction.value === 'failover' ? 'switch_to' : 'restore_to'
    return t(`${P}.failover_flow.${key}`, { from, to })
}

onMounted(() => { loadDashboard(); loadFailoverLogs(); loadRegionDeployments(); loadSyncLogs() })
</script>

<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin' }">{{ t('nav.home') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t(`${P}.breadcrumb.infrastructure`) }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t(`${P}.breadcrumb.title`) }}</el-breadcrumb-item>
        </el-breadcrumb>

        <el-row :gutter="12" class="mb-5" v-if="dashboard?.stats">
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">{{ t(`${P}.stats.data_centers`) }}</div><div class="stat-value">{{ dashboard.stats.total_dcs }}</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">{{ t(`${P}.stats.healthy`) }}</div><div class="stat-value text-success">{{ dashboard.stats.healthy_dcs }}</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">{{ t(`${P}.stats.down`) }}</div><div class="stat-value text-danger">{{ dashboard.stats.down_dcs }}</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">{{ t(`${P}.stats.failover_rules`) }}</div><div class="stat-value">{{ dashboard.stats.total_rules }}</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">{{ t(`${P}.stats.failovered`) }}</div><div class="stat-value text-warning">{{ dashboard.stats.failover_rules }}</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">{{ t(`${P}.stats.avg_latency`) }}</div><div class="stat-value text-sm">{{ dashboard.stats.avg_latency }}ms</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">{{ t(`${P}.stats.region_deployments`) }}</div><div class="stat-value">{{ dashboard.stats.total_region_deployments }}</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">{{ t(`${P}.stats.region_healthy`) }}</div><div class="stat-value text-success">{{ dashboard.stats.healthy_regions }}</div></el-card></el-col>
        </el-row>

        <el-card shadow="never">
            <el-tabs v-model="activeTab">
                <el-tab-pane :label="tabLabels.overview" name="overview">
                    <div class="flex justify-between mb-3">
                        <span class="text-sm text-gray-500">{{ t(`${P}.counts.dc`, { n: dataCenters.length }) }}</span>
                        <div>
                            <el-button size="small" @click="seedDcs" v-if="dataCenters.length === 0">{{ t(`${P}.buttons.seed_dcs`) }}</el-button>
                            <el-button size="small" @click="runHealthCheckAll">{{ t(`${P}.buttons.health_check_all`) }}</el-button>
                            <el-button type="primary" size="small" @click="openCreateDc">{{ t(`${P}.buttons.create_dc`) }}</el-button>
                        </div>
                    </div>

                    <el-table :data="dataCenters" stripe>
                        <el-table-column :label="t(`${P}.cols.name`)" width="140"><template #default="{ row }"><span class="font-bold">{{ row.name }}</span></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.code`)" width="140"><template #default="{ row }"><code>{{ row.code }}</code></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.region`)" width="80"><template #default="{ row }">{{ regionLabels[row.region] || row.region }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.city`)" width="80"><template #default="{ row }">{{ row.city || '-' }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.status`)" width="80"><template #default="{ row }"><el-tag :type="statusTypes[row.status]" size="small">{{ statusLabels[row.status] }}</el-tag></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.latency`)" width="80"><template #default="{ row }"><span :class="{ 'text-danger': row.current_latency_ms > 200 }">{{ row.current_latency_ms ? row.current_latency_ms + 'ms' : '-' }}</span></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.load`)" width="70"><template #default="{ row }">{{ row.current_load ? row.current_load + '%' : '-' }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.capabilities`)" min-width="160"><template #default="{ row }"><el-tag v-for="c in row.capabilities" :key="c" size="small" class="mr-1">{{ c }}</el-tag></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.active`)" width="60"><template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? yesLabel : noLabel }}</el-tag></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.actions`)" width="180" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="runHealthCheck(row)">{{ t(`${P}.buttons.health_check`) }}</el-button>
                                <el-button size="small" text @click="openEditDc(row)">{{ t('actions.edit') }}</el-button>
                                <el-button size="small" text type="danger" @click="deleteDc(row)">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <el-tab-pane :label="tabLabels.failover" name="failover">
                    <div class="flex justify-between mb-3">
                        <span class="text-sm text-gray-500">{{ t(`${P}.counts.rules`, { n: failoverRules.length }) }}</span>
                        <el-button type="primary" size="small" @click="openCreateRule">{{ t(`${P}.buttons.create_rule`) }}</el-button>
                    </div>

                    <el-table :data="failoverRules" stripe>
                        <el-table-column :label="t(`${P}.cols.rule_name`)" width="140"><template #default="{ row }"><span class="font-bold">{{ row.name }}</span></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.primary_dc`)" width="120"><template #default="{ row }">{{ row.primary_dc?.name || '-' }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.backup_dc`)" width="120"><template #default="{ row }">{{ row.backup_dc?.name || '-' }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.trigger_type`)" width="90"><template #default="{ row }">{{ triggerLabels[row.trigger_type] || row.trigger_type }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.threshold`)" width="80"><template #default="{ row }">{{ row.trigger_type === 'latency' ? row.trigger_threshold_ms + 'ms' : t(`${P}.unit_times`, { n: row.failure_count_threshold }) }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.auto_failover`)" width="70"><template #default="{ row }"><el-tag :type="row.auto_failover ? 'success' : 'info'" size="small">{{ row.auto_failover ? yesLabel : noLabel }}</el-tag></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.status`)" width="80"><template #default="{ row }"><el-tag :type="ruleStatusTypes[row.status]" size="small">{{ ruleStatusLabels[row.status] }}</el-tag></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.last_failover`)" width="140"><template #default="{ row }">{{ fmtDate(row.last_failover_at) }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.actions`)" width="200" fixed="right">
                            <template #default="{ row }">
                                <el-button v-if="row.status === 'active'" size="small" type="warning" @click="openFailoverDialog(row, 'failover')">{{ t(`${P}.buttons.failover`) }}</el-button>
                                <el-button v-if="row.status === 'failover'" size="small" type="success" @click="openFailoverDialog(row, 'restore')">{{ t(`${P}.buttons.restore`) }}</el-button>
                                <el-button size="small" text @click="openEditRule(row)">{{ t('actions.edit') }}</el-button>
                                <el-button size="small" text type="danger" @click="deleteRule(row)">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-divider />

                    <div class="flex justify-between mb-3">
                        <span class="font-bold">{{ t(`${P}.sections.failover_logs`) }}</span>
                        <el-button size="small" @click="runAutoFailoverCheck">{{ t(`${P}.buttons.auto_detect`) }}</el-button>
                    </div>

                    <el-table :data="failoverLogs" stripe>
                        <el-table-column :label="t(`${P}.cols.time`)" width="150"><template #default="{ row }">{{ fmtDate(row.created_at) }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.action`)" width="100"><template #default="{ row }"><el-tag :type="row.action.includes('failover') ? 'warning' : 'success'" size="small">{{ actionLabels[row.action] || row.action }}</el-tag></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.from`)" width="100"><template #default="{ row }">{{ row.from_dc }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.to`)" width="100"><template #default="{ row }">{{ row.to_dc }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.automatic`)" width="60"><template #default="{ row }">{{ row.is_automatic ? yesLabel : noLabel }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.reason`)" min-width="200"><template #default="{ row }">{{ row.trigger_reason || '-' }}</template></el-table-column>
                    </el-table>
                    <div class="flex justify-center mt-3">
                        <el-pagination small v-model:current-page="logPagination.current_page" :page-size="15" :total="logPagination.total" layout="prev,pager,next,total" @current-change="loadFailoverLogs" />
                    </div>
                </el-tab-pane>

                <el-tab-pane :label="tabLabels['region-deploy']" name="region-deploy">
                    <div class="flex justify-between mb-3">
                        <span class="text-sm text-gray-500">{{ t(`${P}.counts.regions`, { n: regionDeployments.length }) }}</span>
                        <div>
                            <el-button size="small" @click="seedRegions" v-if="regionDeployments.length === 0">{{ t(`${P}.buttons.seed_regions`) }}</el-button>
                            <el-button size="small" @click="runAllRegionHealth">{{ t(`${P}.buttons.region_health_check`) }}</el-button>
                            <el-button size="small" @click="runCrossRegionHealth">{{ t(`${P}.buttons.cross_region_check`) }}</el-button>
                            <el-button size="small" @click="queryOptimalRegion">{{ t(`${P}.buttons.optimal_route`) }}</el-button>
                            <el-button type="primary" size="small" @click="openCreateRegion">{{ t(`${P}.buttons.create_region`) }}</el-button>
                        </div>
                    </div>

                    <el-row :gutter="12" class="mb-4" v-if="dashboard?.stats">
                        <el-col :span="6"><el-card shadow="never"><div class="stat-label">{{ t(`${P}.stats.deployed_regions`) }}</div><div class="stat-value">{{ dashboard.stats.total_region_deployments }}</div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="never"><div class="stat-label">{{ t(`${P}.stats.healthy_regions`) }}</div><div class="stat-value text-success">{{ dashboard.stats.healthy_regions }}</div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="never"><div class="stat-label">{{ t(`${P}.stats.unhealthy_regions`) }}</div><div class="stat-value text-danger">{{ dashboard.stats.unhealthy_regions }}</div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="never"><div class="stat-label">{{ t(`${P}.stats.routing_strategy`) }}</div><div class="stat-value text-sm">{{ strategyLabels[routingStrategy] || routingStrategy || 'geo_dns' }}</div></el-card></el-col>
                    </el-row>

                    <el-card v-if="optimalRegion" shadow="never" class="mb-4">
                        <div class="flex justify-between items-center">
                            <span class="font-bold">{{ t(`${P}.sections.optimal_route`) }}</span>
                            <el-tag type="success" size="small">{{ optimalRegion.strategy }}</el-tag>
                        </div>
                        <div class="mt-2">
                            <span class="mr-4">{{ t(`${P}.labels.region`) }}: <strong>{{ regionKeyLabels[optimalRegion.region] || optimalRegion.region }}</strong></span>
                            <span class="mr-4" v-if="optimalRegion.api_url">API: <code>{{ optimalRegion.api_url }}</code></span>
                            <span>{{ t(`${P}.labels.primary_region`) }}: <el-tag :type="optimalRegion.is_primary ? 'success' : 'info'" size="small">{{ optimalRegion.is_primary ? yesLabel : noLabel }}</el-tag></span>
                        </div>
                    </el-card>

                    <el-table :data="regionDeployments" stripe>
                        <el-table-column :label="t(`${P}.cols.region_key`)" width="110"><template #default="{ row }"><code>{{ row.region_key }}</code></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.name`)" width="100"><template #default="{ row }"><span class="font-bold">{{ row.name }}</span></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.provider`)" width="80"><template #default="{ row }">{{ providerLabel(row.provider) }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.api_url`)" min-width="200"><template #default="{ row }"><code class="text-xs">{{ row.api_url || '-' }}</code></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.status`)" width="80"><template #default="{ row }"><el-tag :type="regionDeployStatusTypes[row.status]" size="small">{{ regionDeployStatusLabels[row.status] }}</el-tag></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.primary_region`)" width="70"><template #default="{ row }"><el-tag :type="row.is_primary ? 'success' : 'info'" size="small">{{ row.is_primary ? yesLabel : noLabel }}</el-tag></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.weight`)" width="60"><template #default="{ row }">{{ row.weight }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.health`)" width="60"><template #default="{ row }"><el-tag :type="row.is_healthy ? 'success' : 'danger'" size="small">{{ row.is_healthy ? t(`${P}.health.ok`) : t(`${P}.health.bad`) }}</el-tag></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.consecutive_failures`)" width="70"><template #default="{ row }"><span :class="{ 'text-danger': row.consecutive_failures > 2 }">{{ row.consecutive_failures || 0 }}</span></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.version`)" width="70"><template #default="{ row }">{{ row.version || '-' }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.last_check`)" width="140"><template #default="{ row }">{{ fmtDate(row.last_health_check_at) }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.actions`)" width="180" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" text @click="openEditRegion(row)">{{ t('actions.edit') }}</el-button>
                                <el-button size="small" text type="danger" @click="deleteRegion(row)">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-divider />

                    <div class="flex justify-between mb-3">
                        <span class="font-bold">{{ t(`${P}.sections.cross_region_sync`) }}</span>
                        <el-button type="primary" size="small" @click="openSyncDialog" :disabled="regionDeployments.length < 2">{{ t(`${P}.buttons.start_sync`) }}</el-button>
                    </div>

                    <el-table :data="syncLogs" stripe>
                        <el-table-column :label="t(`${P}.cols.time`)" width="140"><template #default="{ row }">{{ fmtDateShort(row.created_at) }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.source_region`)" width="90"><template #default="{ row }"><code>{{ row.source_region }}</code></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.target_region`)" width="90"><template #default="{ row }"><code>{{ row.target_region }}</code></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.data_type`)" width="100"><template #default="{ row }">{{ dataTypeLabels[row.data_type] || row.data_type }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.status`)" width="80"><template #default="{ row }"><el-tag :type="syncStatusTypes[row.status]" size="small">{{ syncStatusLabels[row.status] }}</el-tag></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.total_records`)" width="60"><template #default="{ row }">{{ row.items_count }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.success`)" width="60"><template #default="{ row }"><span class="text-success">{{ row.items_synced }}</span></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.failed`)" width="60"><template #default="{ row }"><span class="text-danger">{{ row.items_failed }}</span></template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.error`)" min-width="150"><template #default="{ row }">{{ row.error_message || '-' }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.started`)" width="140"><template #default="{ row }">{{ fmtDate(row.started_at) }}</template></el-table-column>
                        <el-table-column :label="t(`${P}.cols.completed`)" width="140"><template #default="{ row }">{{ fmtDate(row.completed_at) }}</template></el-table-column>
                    </el-table>
                    <div class="flex justify-center mt-3">
                        <el-pagination small v-model:current-page="syncPagination.current_page" :page-size="15" :total="syncPagination.total" layout="prev,pager,next,total" @current-change="loadSyncLogs" />
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <el-dialog v-model="dcDialog" :title="isEditDc ? t(`${P}.dialogs.edit_dc`) : t(`${P}.dialogs.create_dc`)" width="550px">
            <el-form :model="dcForm" label-width="100px">
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item :label="t(`${P}.form.name`)"><el-input v-model="dcForm.name" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item :label="t(`${P}.form.code`)"><el-input v-model="dcForm.code" placeholder="ap-northeast-1" /></el-form-item></el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item :label="t(`${P}.form.region`)"><el-select v-model="dcForm.region" class="w-full"><el-option v-for="(l, k) in regionLabels" :key="k" :label="l" :value="k" /></el-select></el-form-item></el-col>
                    <el-col :span="12"><el-form-item :label="t(`${P}.form.status`)"><el-select v-model="dcForm.status" class="w-full"><el-option v-for="opt in dcStatusShortOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item></el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item :label="t(`${P}.form.country`)"><el-input v-model="dcForm.country_code" maxlength="5" placeholder="JP/SG/US" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item :label="t(`${P}.form.city`)"><el-input v-model="dcForm.city" /></el-form-item></el-col>
                </el-row>
                <el-form-item :label="t(`${P}.form.base_url`)"><el-input v-model="dcForm.base_url" placeholder="https://api-tokyo.example.com" /></el-form-item>
                <el-form-item :label="t(`${P}.form.health_check_url`)"><el-input v-model="dcForm.health_check_url" placeholder="https://api-tokyo.example.com/health" /></el-form-item>
                <el-form-item :label="t(`${P}.form.capabilities`)"><el-checkbox-group v-model="dcForm.capabilities"><el-checkbox v-for="c in ['compute','storage','database','cache','queue']" :key="c" :label="c" /></el-checkbox-group></el-form-item>
                <el-row :gutter="12">
                    <el-col :span="8"><el-form-item :label="t(`${P}.form.sort_order`)"><el-input-number v-model="dcForm.sort_order" :min="0" style="width:100%" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item :label="t(`${P}.form.active`)"><el-switch v-model="dcForm.is_active" /></el-form-item></el-col>
                </el-row>
            </el-form>
            <template #footer><el-button @click="dcDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="submitDc">{{ t('actions.save') }}</el-button></template>
        </el-dialog>

        <el-dialog v-model="ruleDialog" :title="isEditRule ? t(`${P}.dialogs.edit_rule`) : t(`${P}.dialogs.create_rule`)" width="550px">
            <el-form :model="ruleForm" label-width="130px">
                <el-form-item :label="t(`${P}.form.rule_name`)"><el-input v-model="ruleForm.name" /></el-form-item>
                <el-form-item :label="t(`${P}.form.primary_dc`)"><el-select v-model="ruleForm.primary_dc_id" class="w-full"><el-option v-for="dc in dataCenters" :key="dc.id" :label="dc.name" :value="dc.id" /></el-select></el-form-item>
                <el-form-item :label="t(`${P}.form.backup_dc`)"><el-select v-model="ruleForm.backup_dc_id" class="w-full"><el-option v-for="dc in dataCenters" :key="dc.id" :label="dc.name" :value="dc.id" /></el-select></el-form-item>
                <el-form-item :label="t(`${P}.form.trigger_type`)"><el-select v-model="ruleForm.trigger_type" class="w-full"><el-option v-for="opt in triggerOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item v-if="ruleForm.trigger_type === 'latency'" :label="t(`${P}.form.latency_threshold`)"><el-input-number v-model="ruleForm.trigger_threshold_ms" :min="1" :max="10000" style="width:100%" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item :label="t(`${P}.form.failure_threshold`)"><el-input-number v-model="ruleForm.failure_count_threshold" :min="1" :max="20" style="width:100%" /></el-form-item></el-col>
                </el-row>
                <el-form-item :label="t(`${P}.form.auto_failover`)"><el-switch v-model="ruleForm.auto_failover" /></el-form-item>
                <el-form-item :label="t(`${P}.form.active`)"><el-switch v-model="ruleForm.is_active" /></el-form-item>
                <el-form-item :label="t(`${P}.form.notes`)"><el-input v-model="ruleForm.notes" type="textarea" :rows="2" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="ruleDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="submitRule">{{ t('actions.save') }}</el-button></template>
        </el-dialog>

        <el-dialog v-model="failoverDialog" :title="failoverAction === 'failover' ? t(`${P}.dialogs.execute_failover`) : t(`${P}.dialogs.restore_primary`)" width="450px">
            <p class="mb-3">{{ t(`${P}.labels.rule`) }}: <strong>{{ currentRule?.name }}</strong></p>
            <p class="mb-3 text-sm">{{ failoverFlowText() }}</p>
            <el-input v-model="failoverReason" type="textarea" :rows="3" :placeholder="t(`${P}.placeholders.reason`)" />
            <template #footer><el-button @click="failoverDialog = false">{{ t('actions.cancel') }}</el-button><el-button :type="failoverAction === 'failover' ? 'warning' : 'success'" @click="submitFailoverAction">{{ failoverAction === 'failover' ? t(`${P}.buttons.execute_failover`) : t(`${P}.buttons.execute_restore`) }}</el-button></template>
        </el-dialog>

        <el-dialog v-model="regionDialog" :title="isEditRegion ? t(`${P}.dialogs.edit_region`) : t(`${P}.dialogs.create_region`)" width="550px">
            <el-form :model="regionForm" label-width="110px">
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item :label="t(`${P}.form.region_key`)"><el-input v-model="regionForm.region_key" placeholder="us-east" :disabled="isEditRegion" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item :label="t(`${P}.form.name`)"><el-input v-model="regionForm.name" :placeholder="t(`${P}.placeholders.region_name`)" /></el-form-item></el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item :label="t(`${P}.form.provider`)"><el-select v-model="regionForm.provider" class="w-full"><el-option label="AWS" value="aws" /><el-option label="GCP" value="gcp" /><el-option label="Azure" value="azure" /><el-option :label="t(`${P}.provider.aliyun`)" value="aliyun" /></el-select></el-form-item></el-col>
                    <el-col :span="12"><el-form-item :label="t(`${P}.form.status`)"><el-select v-model="regionForm.status" class="w-full"><el-option v-for="opt in regionDeployStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item></el-col>
                </el-row>
                <el-form-item :label="t(`${P}.form.api_url`)"><el-input v-model="regionForm.api_url" placeholder="https://api-us.huwutong.com" /></el-form-item>
                <el-row :gutter="12">
                    <el-col :span="8"><el-form-item :label="t(`${P}.form.weight`)"><el-input-number v-model="regionForm.weight" :min="0" :max="10000" style="width:100%" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item :label="t(`${P}.form.primary_region`)"><el-switch v-model="regionForm.is_primary" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item :label="t(`${P}.form.version`)"><el-input v-model="regionForm.version" placeholder="v2.1.0" /></el-form-item></el-col>
                </el-row>
            </el-form>
            <template #footer><el-button @click="regionDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="submitRegion">{{ t('actions.save') }}</el-button></template>
        </el-dialog>

        <el-dialog v-model="syncDialog" :title="t(`${P}.dialogs.start_sync`)" width="450px">
            <el-form :model="syncForm" label-width="100px">
                <el-form-item :label="t(`${P}.form.source_region`)"><el-select v-model="syncForm.source_region" class="w-full"><el-option v-for="dep in regionDeployments" :key="dep.region_key" :label="dep.name + ' (' + dep.region_key + ')'" :value="dep.region_key" /></el-select></el-form-item>
                <el-form-item :label="t(`${P}.form.target_region`)"><el-select v-model="syncForm.target_region" class="w-full"><el-option v-for="dep in regionDeployments" :key="dep.region_key" :label="dep.name + ' (' + dep.region_key + ')'" :value="dep.region_key" /></el-select></el-form-item>
                <el-form-item :label="t(`${P}.form.data_type`)"><el-select v-model="syncForm.data_type" class="w-full"><el-option v-for="opt in dataTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
            </el-form>
            <template #footer><el-button @click="syncDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="submitSync">{{ t(`${P}.buttons.start_sync`) }}</el-button></template>
        </el-dialog>
    </div>
</template>

<style scoped>
.stat-label { font-size: 12px; color: #909399; }
.stat-value { font-size: 20px; font-weight: 700; }
.text-success { color: #67c23a; }
.text-warning { color: #e6a23c; }
.text-danger { color: #f56c6c; }
.text-sm { font-size: 13px; }
code { background: #f5f7fa; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
</style>
