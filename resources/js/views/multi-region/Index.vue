<script setup>
import { ref, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../api/multiRegion.js'

const loading = ref(false)
const activeTab = ref('overview')
const dashboard = ref(null)
const dataCenters = ref([])
const failoverRules = ref([])
const failoverLogs = ref([])
const logPagination = ref({ total: 0, current_page: 1 })
const logFilters = ref({ action: '', rule_id: '', date_from: '', date_to: '' })
const healthTrendData = ref([])

// M3-52 区域部署
const regionDeployments = ref([])
const syncLogs = ref([])
const syncPagination = ref({ total: 0, current_page: 1 })
const optimalRegion = ref(null)

// 数据中心对话框
const dcDialog = ref(false)
const isEditDc = ref(false)
const currentDc = ref(null)
const dcForm = ref({ name: '', code: '', region: 'asia', country_code: '', city: '', is_active: true, sort_order: 0, base_url: '', health_check_url: '', capabilities: [], status: 'healthy' })

// 故障切换规则对话框
const ruleDialog = ref(false)
const isEditRule = ref(false)
const currentRule = ref(null)
const ruleForm = ref({ name: '', primary_dc_id: '', backup_dc_id: '', trigger_type: 'latency', trigger_threshold_ms: 300, failure_count_threshold: 3, auto_failover: false, is_active: true, notes: '' })
const failoverReason = ref('')
const failoverDialog = ref(false)
const failoverAction = ref('failover')

// M3-52 区域部署对话框
const regionDialog = ref(false)
const isEditRegion = ref(false)
const currentRegion = ref(null)
const regionForm = ref({ region_key: '', name: '', provider: 'aws', api_url: '', weight: 100, is_primary: false, status: 'active', version: '' })

// M3-52 数据同步对话框
const syncDialog = ref(false)
const syncForm = ref({ source_region: '', target_region: '', data_type: 'license' })
const syncFilters = ref({ status: '', data_type: '', source_region: '' })

const regionLabels = { asia: '亚洲', europe: '欧洲', us: '美洲', oceania: '大洋洲', africa: '非洲', south_america: '南美洲' }
const statusLabels = { healthy: '健康', degraded: '降级', down: '宕机', maintenance: '维护中' }
const statusTypes = { healthy: 'success', degraded: 'warning', down: 'danger', maintenance: 'info' }
const triggerLabels = { latency: '延迟触发', down: '宕机触发', manual: '手动触发' }
const ruleStatusLabels = { active: '活跃', failover: '已切换', restoring: '恢复中', inactive: '停用' }
const ruleStatusTypes = { active: 'success', failover: 'warning', restoring: '', inactive: 'info' }
const actionLabels = { failover: '故障切换', restore: '恢复', manual_failover: '手动切换', manual_restore: '手动恢复' }
const regionDeployStatusLabels = { active: '活跃', degraded: '降级', inactive: '停用' }
const regionDeployStatusTypes = { active: 'success', degraded: 'warning', inactive: 'info' }
const syncStatusLabels = { pending: '待同步', running: '同步中', completed: '已完成', failed: '失败', cancelled: '已取消' }
const syncStatusTypes = { pending: 'info', running: 'warning', completed: 'success', failed: 'danger', cancelled: '' }
const dataTypeLabels = { license: '授权数据', customer: '客户数据', product: '产品数据', audit_log: '审计日志' }
const regionKeyLabels = { 'us-east': '美东', 'eu-west': '欧洲', 'ap-southeast': '东南亚' }
const regionProviderIcons = { aws: 'AWS', gcp: 'GCP', azure: 'Azure', aliyun: '阿里云' }
const strategyLabels = { geo_dns: 'GeoDNS', latency_based: '延迟优先', weighted_random: '加权随机' }
const routingStrategy = ref('geo_dns')

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

// ─── 数据中心操作 ───
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
            ElMessage.success('数据中心已更新')
        } else {
            await api.storeDataCenter(dcForm.value)
            ElMessage.success('数据中心已创建')
        }
        dcDialog.value = false
        loadDashboard()
    } catch (e) { ElMessage.error('操作失败') }
}

async function deleteDc(dc) {
    try {
        await ElMessageBox.confirm(`确定删除数据中心 "${dc.name}" 吗？`, '确认', { type: 'warning' })
        await api.destroyDataCenter(dc.id)
        ElMessage.success('已删除')
        loadDashboard()
    } catch (e) {}
}

async function runHealthCheck(dc) {
    try {
        await api.healthCheck(dc.id)
        ElMessage.success(`${dc.name} 健康检查完成`)
        loadDashboard()
    } catch (e) { ElMessage.error('健康检查失败') }
}

// ─── 故障切换规则操作 ───
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
            ElMessage.success('规则已更新')
        } else {
            await api.storeFailoverRule(ruleForm.value)
            ElMessage.success('规则已创建')
        }
        ruleDialog.value = false
        loadDashboard()
    } catch (e) { ElMessage.error('操作失败') }
}

async function deleteRule(rule) {
    try {
        await ElMessageBox.confirm(`确定删除规则 "${rule.name}" 吗？`, '确认', { type: 'warning' })
        await api.destroyFailoverRule(rule.id)
        ElMessage.success('已删除')
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
    if (!failoverReason.value) { ElMessage.warning('请输入操作原因'); return }
    try {
        if (failoverAction.value === 'failover') {
            await api.executeFailover(currentRule.value.id, failoverReason.value)
            ElMessage.success('故障切换已执行')
        } else {
            await api.executeRestore(currentRule.value.id, failoverReason.value)
            ElMessage.success('已恢复至主数据中心')
        }
        failoverDialog.value = false
        loadDashboard()
        loadFailoverLogs()
    } catch (e) { ElMessage.error('操作失败') }
}

async function seedDcs() {
    try {
        await api.seedDataCenters()
        ElMessage.success('默认数据中心已初始化')
        loadDashboard()
    } catch (e) { ElMessage.error('初始化失败') }
}

// ─── M3-52 区域部署操作 ───
async function seedRegions() {
    try {
        await api.seedRegionDeployments()
        ElMessage.success('三区域部署已初始化(us-east/eu-west/ap-southeast)')
        loadRegionDeployments()
        loadDashboard()
    } catch (e) { ElMessage.error('初始化失败') }
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
            ElMessage.success('区域部署已更新')
        } else {
            await api.storeRegionDeployment(regionForm.value)
            ElMessage.success('区域部署已创建')
        }
        regionDialog.value = false
        loadRegionDeployments()
        loadDashboard()
    } catch (e) { ElMessage.error('操作失败') }
}

async function deleteRegion(region) {
    try {
        await ElMessageBox.confirm(`确定删除区域部署 "${region.name}" 吗？`, '确认', { type: 'warning' })
        await api.destroyRegionDeployment(region.id)
        ElMessage.success('已删除')
        loadRegionDeployments()
        loadDashboard()
    } catch (e) {}
}

// ─── M3-52 数据同步操作 ───
function openSyncDialog() {
    syncForm.value = { source_region: '', target_region: '', data_type: 'license' }
    syncDialog.value = true
}

async function submitSync() {
    if (!syncForm.value.source_region || !syncForm.value.target_region) {
        ElMessage.warning('请选择源区域和目标区域')
        return
    }
    if (syncForm.value.source_region === syncForm.value.target_region) {
        ElMessage.warning('源区域和目标区域不能相同')
        return
    }
    try {
        await api.startDataSync(syncForm.value)
        ElMessage.success('数据同步已启动')
        syncDialog.value = false
        loadSyncLogs()
    } catch (e) { ElMessage.error('同步启动失败') }
}

// ─── M3-52 区域健康检查 ───
async function runAllRegionHealth() {
    try {
        await api.checkAllRegionHealth()
        ElMessage.success('所有区域健康检查完成')
        loadRegionDeployments()
        loadDashboard()
    } catch (e) { ElMessage.error('检查失败') }
}

async function runCrossRegionHealth() {
    try {
        await api.crossRegionHealthCheck()
        ElMessage.success('区域间互检完成')
    } catch (e) { ElMessage.error('互检失败') }
}

// ─── M3-52 GeoDNS路由查询 ───
async function queryOptimalRegion() {
    try {
        const res = await api.getOptimalRegion()
        optimalRegion.value = res.data.data
    } catch (e) { ElMessage.error('查询失败') }
}

function fmtDate(d) { return d ? new Date(d).toLocaleString('zh-CN') : '-' }
function fmtDateShort(d) { return d ? new Date(d).toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }) : '-' }

onMounted(() => { loadDashboard(); loadFailoverLogs(); loadRegionDeployments(); loadSyncLogs() })
</script>

<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>基础设施</el-breadcrumb-item>
            <el-breadcrumb-item>多数据中心</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 概述统计 -->
        <el-row :gutter="12" class="mb-5" v-if="dashboard?.stats">
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">数据中心</div><div class="stat-value">{{ dashboard.stats.total_dcs }}</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">健康</div><div class="stat-value text-success">{{ dashboard.stats.healthy_dcs }}</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">宕机</div><div class="stat-value text-danger">{{ dashboard.stats.down_dcs }}</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">故障规则</div><div class="stat-value">{{ dashboard.stats.total_rules }}</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">已切换</div><div class="stat-value text-warning">{{ dashboard.stats.failover_rules }}</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">平均延迟</div><div class="stat-value text-sm">{{ dashboard.stats.avg_latency }}ms</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">区域部署</div><div class="stat-value">{{ dashboard.stats.total_region_deployments }}</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">区域健康</div><div class="stat-value text-success">{{ dashboard.stats.healthy_regions }}</div></el-card></el-col>
        </el-row>

        <!-- Tabs -->
        <el-card shadow="never">
            <el-tabs v-model="activeTab">
                <el-tab-pane label="数据中心" name="overview">
                    <div class="flex justify-between mb-3">
                        <span class="text-sm text-gray-500">{{ dataCenters.length }} 个数据中心</span>
                        <div>
                            <el-button size="small" @click="seedDcs" v-if="dataCenters.length === 0">初始化默认数据中心</el-button>
                            <el-button size="small" @click="api.healthCheckAll().then(() => { ElMessage.success('所有健康检查完成'); loadDashboard() })">全部健康检查</el-button>
                            <el-button type="primary" size="small" @click="openCreateDc">新建数据中心</el-button>
                        </div>
                    </div>

                    <el-table :data="dataCenters" stripe>
                        <el-table-column label="名称" width="140"><template #default="{ row }"><span class="font-bold">{{ row.name }}</span></template></el-table-column>
                        <el-table-column label="代码" width="140"><template #default="{ row }"><code>{{ row.code }}</code></template></el-table-column>
                        <el-table-column label="区域" width="80"><template #default="{ row }">{{ regionLabels[row.region] || row.region }}</template></el-table-column>
                        <el-table-column label="城市" width="80"><template #default="{ row }">{{ row.city || '-' }}</template></el-table-column>
                        <el-table-column label="状态" width="80"><template #default="{ row }"><el-tag :type="statusTypes[row.status]" size="small">{{ statusLabels[row.status] }}</el-tag></template></el-table-column>
                        <el-table-column label="延迟" width="80"><template #default="{ row }"><span :class="{ 'text-danger': row.current_latency_ms > 200 }">{{ row.current_latency_ms ? row.current_latency_ms + 'ms' : '-' }}</span></template></el-table-column>
                        <el-table-column label="负载" width="70"><template #default="{ row }">{{ row.current_load ? row.current_load + '%' : '-' }}</template></el-table-column>
                        <el-table-column label="能力" min-width="160"><template #default="{ row }"><el-tag v-for="c in row.capabilities" :key="c" size="small" class="mr-1">{{ c }}</el-tag></template></el-table-column>
                        <el-table-column label="活跃" width="60"><template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '是' : '否' }}</el-tag></template></el-table-column>
                        <el-table-column label="操作" width="180" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="runHealthCheck(row)">检测</el-button>
                                <el-button size="small" text @click="openEditDc(row)">编辑</el-button>
                                <el-button size="small" text type="danger" @click="deleteDc(row)">删除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <el-tab-pane label="故障切换" name="failover">
                    <div class="flex justify-between mb-3">
                        <span class="text-sm text-gray-500">{{ failoverRules.length }} 条规则</span>
                        <el-button type="primary" size="small" @click="openCreateRule">新建规则</el-button>
                    </div>

                    <el-table :data="failoverRules" stripe>
                        <el-table-column label="规则名称" width="140"><template #default="{ row }"><span class="font-bold">{{ row.name }}</span></template></el-table-column>
                        <el-table-column label="主数据中心" width="120"><template #default="{ row }">{{ row.primary_dc?.name || '-' }}</template></el-table-column>
                        <el-table-column label="备数据中心" width="120"><template #default="{ row }">{{ row.backup_dc?.name || '-' }}</template></el-table-column>
                        <el-table-column label="触发方式" width="90"><template #default="{ row }">{{ triggerLabels[row.trigger_type] || row.trigger_type }}</template></el-table-column>
                        <el-table-column label="阈值" width="80"><template #default="{ row }">{{ row.trigger_type === 'latency' ? row.trigger_threshold_ms + 'ms' : (row.failure_count_threshold + '次') }}</template></el-table-column>
                        <el-table-column label="自动切换" width="70"><template #default="{ row }"><el-tag :type="row.auto_failover ? 'success' : 'info'" size="small">{{ row.auto_failover ? '是' : '否' }}</el-tag></template></el-table-column>
                        <el-table-column label="状态" width="80"><template #default="{ row }"><el-tag :type="ruleStatusTypes[row.status]" size="small">{{ ruleStatusLabels[row.status] }}</el-tag></template></el-table-column>
                        <el-table-column label="最后切换" width="140"><template #default="{ row }">{{ fmtDate(row.last_failover_at) }}</template></el-table-column>
                        <el-table-column label="操作" width="200" fixed="right">
                            <template #default="{ row }">
                                <el-button v-if="row.status === 'active'" size="small" type="warning" @click="openFailoverDialog(row, 'failover')">切换</el-button>
                                <el-button v-if="row.status === 'failover'" size="small" type="success" @click="openFailoverDialog(row, 'restore')">恢复</el-button>
                                <el-button size="small" text @click="openEditRule(row)">编辑</el-button>
                                <el-button size="small" text type="danger" @click="deleteRule(row)">删除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-divider />

                    <div class="flex justify-between mb-3">
                        <span class="font-bold">故障切换日志</span>
                        <el-button size="small" @click="api.autoFailoverCheck().then(r => { ElMessage.success('检测完成'); loadFailoverLogs(); loadDashboard() })">自动检测</el-button>
                    </div>

                    <el-table :data="failoverLogs" stripe>
                        <el-table-column label="时间" width="150"><template #default="{ row }">{{ fmtDate(row.created_at) }}</template></el-table-column>
                        <el-table-column label="操作" width="100"><template #default="{ row }"><el-tag :type="row.action.includes('failover') ? 'warning' : 'success'" size="small">{{ actionLabels[row.action] || row.action }}</el-tag></template></el-table-column>
                        <el-table-column label="从" width="100"><template #default="{ row }">{{ row.from_dc }}</template></el-table-column>
                        <el-table-column label="到" width="100"><template #default="{ row }">{{ row.to_dc }}</template></el-table-column>
                        <el-table-column label="自动" width="60"><template #default="{ row }">{{ row.is_automatic ? '是' : '否' }}</template></el-table-column>
                        <el-table-column label="原因" min-width="200"><template #default="{ row }">{{ row.trigger_reason || '-' }}</template></el-table-column>
                    </el-table>
                    <div class="flex justify-center mt-3">
                        <el-pagination small v-model:current-page="logPagination.current_page" :page-size="15" :total="logPagination.total" layout="prev,pager,next,total" @current-change="loadFailoverLogs" />
                    </div>
                </el-tab-pane>

                <!-- ═══════ M3-52 区域部署 ═══════ -->
                <el-tab-pane label="区域部署" name="region-deploy">
                    <div class="flex justify-between mb-3">
                        <span class="text-sm text-gray-500">{{ regionDeployments.length }} 个区域</span>
                        <div>
                            <el-button size="small" @click="seedRegions" v-if="regionDeployments.length === 0">初始化三区域部署</el-button>
                            <el-button size="small" @click="runAllRegionHealth">区域健康检查</el-button>
                            <el-button size="small" @click="runCrossRegionHealth">区域间互检</el-button>
                            <el-button size="small" @click="queryOptimalRegion">最优路由查询</el-button>
                            <el-button type="primary" size="small" @click="openCreateRegion">新建区域部署</el-button>
                        </div>
                    </div>

                    <!-- 区域部署统计卡片 -->
                    <el-row :gutter="12" class="mb-4" v-if="dashboard?.stats">
                        <el-col :span="6"><el-card shadow="never"><div class="stat-label">部署区域</div><div class="stat-value">{{ dashboard.stats.total_region_deployments }}</div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="never"><div class="stat-label">健康区域</div><div class="stat-value text-success">{{ dashboard.stats.healthy_regions }}</div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="never"><div class="stat-label">异常区域</div><div class="stat-value text-danger">{{ dashboard.stats.unhealthy_regions }}</div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="never"><div class="stat-label">路由策略</div><div class="stat-value text-sm">{{ strategyLabels[routingStrategy] || routingStrategy || 'geo_dns' }}</div></el-card></el-col>
                    </el-row>

                    <!-- 最优路由结果 -->
                    <el-card v-if="optimalRegion" shadow="never" class="mb-4">
                        <div class="flex justify-between items-center">
                            <span class="font-bold">当前最优路由</span>
                            <el-tag type="success" size="small">{{ optimalRegion.strategy }}</el-tag>
                        </div>
                        <div class="mt-2">
                            <span class="mr-4">区域: <strong>{{ regionKeyLabels[optimalRegion.region] || optimalRegion.region }}</strong></span>
                            <span class="mr-4" v-if="optimalRegion.api_url">API: <code>{{ optimalRegion.api_url }}</code></span>
                            <span>主区域: <el-tag :type="optimalRegion.is_primary ? 'success' : 'info'" size="small">{{ optimalRegion.is_primary ? '是' : '否' }}</el-tag></span>
                        </div>
                    </el-card>

                    <!-- 区域部署列表 -->
                    <el-table :data="regionDeployments" stripe>
                        <el-table-column label="区域键" width="110"><template #default="{ row }"><code>{{ row.region_key }}</code></template></el-table-column>
                        <el-table-column label="名称" width="100"><template #default="{ row }"><span class="font-bold">{{ row.name }}</span></template></el-table-column>
                        <el-table-column label="提供商" width="80"><template #default="{ row }">{{ regionProviderIcons[row.provider] || row.provider }}</template></el-table-column>
                        <el-table-column label="API URL" min-width="200"><template #default="{ row }"><code class="text-xs">{{ row.api_url || '-' }}</code></template></el-table-column>
                        <el-table-column label="状态" width="80"><template #default="{ row }"><el-tag :type="regionDeployStatusTypes[row.status]" size="small">{{ regionDeployStatusLabels[row.status] }}</el-tag></template></el-table-column>
                        <el-table-column label="主区域" width="70"><template #default="{ row }"><el-tag :type="row.is_primary ? 'success' : 'info'" size="small">{{ row.is_primary ? '是' : '否' }}</el-tag></template></el-table-column>
                        <el-table-column label="权重" width="60"><template #default="{ row }">{{ row.weight }}</template></el-table-column>
                        <el-table-column label="健康" width="60"><template #default="{ row }"><el-tag :type="row.is_healthy ? 'success' : 'danger'" size="small">{{ row.is_healthy ? '正常' : '异常' }}</el-tag></template></el-table-column>
                        <el-table-column label="连续失败" width="70"><template #default="{ row }"><span :class="{ 'text-danger': row.consecutive_failures > 2 }">{{ row.consecutive_failures || 0 }}</span></template></el-table-column>
                        <el-table-column label="版本" width="70"><template #default="{ row }">{{ row.version || '-' }}</template></el-table-column>
                        <el-table-column label="最后检查" width="140"><template #default="{ row }">{{ fmtDate(row.last_health_check_at) }}</template></el-table-column>
                        <el-table-column label="操作" width="180" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" text @click="openEditRegion(row)">编辑</el-button>
                                <el-button size="small" text type="danger" @click="deleteRegion(row)">删除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-divider />

                    <!-- 跨区域数据同步 -->
                    <div class="flex justify-between mb-3">
                        <span class="font-bold">跨区域数据同步</span>
                        <el-button type="primary" size="small" @click="openSyncDialog" :disabled="regionDeployments.length < 2">发起同步</el-button>
                    </div>

                    <el-table :data="syncLogs" stripe>
                        <el-table-column label="时间" width="140"><template #default="{ row }">{{ fmtDateShort(row.created_at) }}</template></el-table-column>
                        <el-table-column label="源区域" width="90"><template #default="{ row }"><code>{{ row.source_region }}</code></template></el-table-column>
                        <el-table-column label="目标区域" width="90"><template #default="{ row }"><code>{{ row.target_region }}</code></template></el-table-column>
                        <el-table-column label="数据类型" width="100"><template #default="{ row }">{{ dataTypeLabels[row.data_type] || row.data_type }}</template></el-table-column>
                        <el-table-column label="状态" width="80"><template #default="{ row }"><el-tag :type="syncStatusTypes[row.status]" size="small">{{ syncStatusLabels[row.status] }}</el-tag></template></el-table-column>
                        <el-table-column label="总记录" width="60"><template #default="{ row }">{{ row.items_count }}</template></el-table-column>
                        <el-table-column label="成功" width="60"><template #default="{ row }"><span class="text-success">{{ row.items_synced }}</span></template></el-table-column>
                        <el-table-column label="失败" width="60"><template #default="{ row }"><span class="text-danger">{{ row.items_failed }}</span></template></el-table-column>
                        <el-table-column label="错误" min-width="150"><template #default="{ row }">{{ row.error_message || '-' }}</template></el-table-column>
                        <el-table-column label="开始" width="140"><template #default="{ row }">{{ fmtDate(row.started_at) }}</template></el-table-column>
                        <el-table-column label="完成" width="140"><template #default="{ row }">{{ fmtDate(row.completed_at) }}</template></el-table-column>
                    </el-table>
                    <div class="flex justify-center mt-3">
                        <el-pagination small v-model:current-page="syncPagination.current_page" :page-size="15" :total="syncPagination.total" layout="prev,pager,next,total" @current-change="loadSyncLogs" />
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- 数据中心对话框 -->
        <el-dialog v-model="dcDialog" :title="isEditDc ? '编辑数据中心' : '新建数据中心'" width="550px">
            <el-form :model="dcForm" label-width="100px">
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item label="名称"><el-input v-model="dcForm.name" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item label="代码"><el-input v-model="dcForm.code" placeholder="ap-northeast-1" /></el-form-item></el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item label="区域"><el-select v-model="dcForm.region" class="w-full"><el-option v-for="(l,k) in regionLabels" :key="k" :label="l" :value="k" /></el-select></el-form-item></el-col>
                    <el-col :span="12"><el-form-item label="状态"><el-select v-model="dcForm.status" class="w-full"><el-option label="健康" value="healthy" /><el-option label="降级" value="degraded" /><el-option label="宕机" value="down" /><el-option label="维护" value="maintenance" /></el-select></el-form-item></el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item label="国家"><el-input v-model="dcForm.country_code" maxlength="5" placeholder="JP/SG/US" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item label="城市"><el-input v-model="dcForm.city" /></el-form-item></el-col>
                </el-row>
                <el-form-item label="基础URL"><el-input v-model="dcForm.base_url" placeholder="https://api-tokyo.example.com" /></el-form-item>
                <el-form-item label="健康检查URL"><el-input v-model="dcForm.health_check_url" placeholder="https://api-tokyo.example.com/health" /></el-form-item>
                <el-form-item label="能力"><el-checkbox-group v-model="dcForm.capabilities"><el-checkbox v-for="c in ['compute','storage','database','cache','queue']" :key="c" :label="c" /></el-checkbox-group></el-form-item>
                <el-row :gutter="12">
                    <el-col :span="8"><el-form-item label="排序"><el-input-number v-model="dcForm.sort_order" :min="0" style="width:100%" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item label="活跃"><el-switch v-model="dcForm.is_active" /></el-form-item></el-col>
                </el-row>
            </el-form>
            <template #footer><el-button @click="dcDialog = false">取消</el-button><el-button type="primary" @click="submitDc">保存</el-button></template>
        </el-dialog>

        <!-- 故障切换规则对话框 -->
        <el-dialog v-model="ruleDialog" :title="isEditRule ? '编辑规则' : '新建故障切换规则'" width="550px">
            <el-form :model="ruleForm" label-width="130px">
                <el-form-item label="规则名称"><el-input v-model="ruleForm.name" /></el-form-item>
                <el-form-item label="主数据中心"><el-select v-model="ruleForm.primary_dc_id" class="w-full"><el-option v-for="dc in dataCenters" :key="dc.id" :label="dc.name" :value="dc.id" /></el-select></el-form-item>
                <el-form-item label="备数据中心"><el-select v-model="ruleForm.backup_dc_id" class="w-full"><el-option v-for="dc in dataCenters" :key="dc.id" :label="dc.name" :value="dc.id" /></el-select></el-form-item>
                <el-form-item label="触发方式"><el-select v-model="ruleForm.trigger_type" class="w-full"><el-option label="延迟触发" value="latency" /><el-option label="宕机触发" value="down" /><el-option label="手动触发" value="manual" /></el-select></el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item v-if="ruleForm.trigger_type === 'latency'" label="延迟阈值(ms)"><el-input-number v-model="ruleForm.trigger_threshold_ms" :min="1" :max="10000" style="width:100%" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item label="失败次数阈值"><el-input-number v-model="ruleForm.failure_count_threshold" :min="1" :max="20" style="width:100%" /></el-form-item></el-col>
                </el-row>
                <el-form-item label="自动故障切换"><el-switch v-model="ruleForm.auto_failover" /></el-form-item>
                <el-form-item label="活跃"><el-switch v-model="ruleForm.is_active" /></el-form-item>
                <el-form-item label="备注"><el-input v-model="ruleForm.notes" type="textarea" :rows="2" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="ruleDialog = false">取消</el-button><el-button type="primary" @click="submitRule">保存</el-button></template>
        </el-dialog>

        <!-- 执行故障切换/恢复对话框 -->
        <el-dialog v-model="failoverDialog" :title="failoverAction === 'failover' ? '执行故障切换' : '恢复至主数据中心'" width="450px">
            <p class="mb-3">规则：<strong>{{ currentRule?.name }}</strong></p>
            <p class="mb-3 text-sm">将流量从 <code>{{ currentRule?.primary_dc?.name }}</code> {{ failoverAction === 'failover' ? '切换到' : '恢复到' }} <code>{{ failoverAction === 'failover' ? currentRule?.backup_dc?.name : currentRule?.primary_dc?.name }}</code></p>
            <el-input v-model="failoverReason" type="textarea" :rows="3" placeholder="请输入操作原因..." />
            <template #footer><el-button @click="failoverDialog = false">取消</el-button><el-button :type="failoverAction === 'failover' ? 'warning' : 'success'" @click="submitFailoverAction">{{ failoverAction === 'failover' ? '执行切换' : '执行恢复' }}</el-button></template>
        </el-dialog>

        <!-- M3-52 区域部署对话框 -->
        <el-dialog v-model="regionDialog" :title="isEditRegion ? '编辑区域部署' : '新建区域部署'" width="550px">
            <el-form :model="regionForm" label-width="110px">
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item label="区域键"><el-input v-model="regionForm.region_key" placeholder="us-east" :disabled="isEditRegion" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item label="名称"><el-input v-model="regionForm.name" placeholder="美东" /></el-form-item></el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item label="提供商"><el-select v-model="regionForm.provider" class="w-full"><el-option label="AWS" value="aws" /><el-option label="GCP" value="gcp" /><el-option label="Azure" value="azure" /><el-option label="阿里云" value="aliyun" /></el-select></el-form-item></el-col>
                    <el-col :span="12"><el-form-item label="状态"><el-select v-model="regionForm.status" class="w-full"><el-option label="活跃" value="active" /><el-option label="降级" value="degraded" /><el-option label="停用" value="inactive" /></el-select></el-form-item></el-col>
                </el-row>
                <el-form-item label="API URL"><el-input v-model="regionForm.api_url" placeholder="https://api-us.huwutong.com" /></el-form-item>
                <el-row :gutter="12">
                    <el-col :span="8"><el-form-item label="权重"><el-input-number v-model="regionForm.weight" :min="0" :max="10000" style="width:100%" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item label="主区域"><el-switch v-model="regionForm.is_primary" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item label="版本"><el-input v-model="regionForm.version" placeholder="v2.1.0" /></el-form-item></el-col>
                </el-row>
            </el-form>
            <template #footer><el-button @click="regionDialog = false">取消</el-button><el-button type="primary" @click="submitRegion">保存</el-button></template>
        </el-dialog>

        <!-- M3-52 数据同步对话框 -->
        <el-dialog v-model="syncDialog" title="发起跨区域数据同步" width="450px">
            <el-form :model="syncForm" label-width="100px">
                <el-form-item label="源区域"><el-select v-model="syncForm.source_region" class="w-full"><el-option v-for="dep in regionDeployments" :key="dep.region_key" :label="dep.name + ' (' + dep.region_key + ')'" :value="dep.region_key" /></el-select></el-form-item>
                <el-form-item label="目标区域"><el-select v-model="syncForm.target_region" class="w-full"><el-option v-for="dep in regionDeployments" :key="dep.region_key" :label="dep.name + ' (' + dep.region_key + ')'" :value="dep.region_key" /></el-select></el-form-item>
                <el-form-item label="数据类型"><el-select v-model="syncForm.data_type" class="w-full"><el-option label="授权数据" value="license" /><el-option label="客户数据" value="customer" /><el-option label="产品数据" value="product" /><el-option label="审计日志" value="audit_log" /></el-select></el-form-item>
            </el-form>
            <template #footer><el-button @click="syncDialog = false">取消</el-button><el-button type="primary" @click="submitSync">发起同步</el-button></template>
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
