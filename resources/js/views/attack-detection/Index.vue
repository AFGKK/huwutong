<template>
    <div class="attack-page">
        <h2>{{ t('attack_detection_page.title') }}</h2>

        <el-row :gutter="20" class="stats-row">
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.total || 0 }}</div><div class="stat-label">{{ t('attack_detection_page.stats.total') }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value danger">{{ stats.open || 0 }}</div><div class="stat-label">{{ t('attack_detection_page.stats.open') }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value warning">{{ stats.blocked || 0 }}</div><div class="stat-label">{{ t('attack_detection_page.stats.blocked') }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ stats.resolved || 0 }}</div><div class="stat-label">{{ t('attack_detection_page.stats.resolved') }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.falsePositive || 0 }}</div><div class="stat-label">{{ t('attack_detection_page.stats.false_positive') }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ stats.activeBlocks || 0 }}</div><div class="stat-label">{{ t('attack_detection_page.stats.active_blocks') }}</div></div></el-card></el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <el-tab-pane :label="t('attack_detection_page.tabs.events')" name="events">
                <div class="toolbar">
                    <el-select v-model="filterType" :placeholder="t('attack_detection_page.cols.type')" clearable style="width:140px;margin-right:8px" @change="loadEvents">
                        <el-option v-for="(cfg,key) in detectorConfig" :key="key" :label="key" :value="key" />
                    </el-select>
                    <el-select v-model="filterSeverity" :placeholder="t('attack_detection_page.cols.severity')" clearable style="width:120px;margin-right:8px" @change="loadEvents">
                        <el-option :label="t('attack_detection_page.severities.critical')" value="critical" />
                        <el-option :label="t('attack_detection_page.severities.warning')" value="warning" />
                        <el-option :label="t('attack_detection_page.severities.info')" value="info" />
                    </el-select>
                    <el-button @click="loadEvents">{{ t('actions.refresh') }}</el-button>
                </div>
                <el-table :data="events" v-loading="loading" stripe @row-click="showDetail">
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column prop="attack_type" :label="t('attack_detection_page.cols.type')" width="120"><template #default="{row}"><el-tag size="small">{{ row.attack_type }}</el-tag></template></el-table-column>
                    <el-table-column prop="severity" :label="t('attack_detection_page.cols.severity')" width="80"><template #default="{row}"><el-tag :type="row.severity==='critical'?'danger':row.severity==='warning'?'warning':'info'" size="small">{{ row.severity }}</el-tag></template></el-table-column>
                    <el-table-column prop="source_ip" :label="t('attack_detection_page.cols.source_ip')" width="140" />
                    <el-table-column prop="description" :label="t('attack_detection_page.cols.description')" min-width="300" show-overflow-tooltip />
                    <el-table-column prop="status" :label="t('attack_detection_page.cols.status')" width="100"><template #default="{row}"><el-tag :type="row.status==='resolved'?'success':row.status==='blocked'?'warning':'danger'" size="small">{{ row.status }}</el-tag></template></el-table-column>
                    <el-table-column prop="detected_at" :label="t('attack_detection_page.cols.detected')" width="170" />
                </el-table>
            </el-tab-pane>

            <el-tab-pane :label="t('attack_detection_page.tabs.detail')" name="detail" :disabled="!selectedEvent">
                <div v-if="selectedEvent">
                    <el-descriptions :column="2" border>
                        <el-descriptions-item :label="t('attack_detection_page.cols.type')">{{ selectedEvent.attack_type }}</el-descriptions-item>
                        <el-descriptions-item :label="t('attack_detection_page.cols.severity')"><el-tag :type="selectedEvent.severity==='critical'?'danger':'warning'">{{ selectedEvent.severity }}</el-tag></el-descriptions-item>
                        <el-descriptions-item :label="t('attack_detection_page.cols.confidence')">{{ (selectedEvent.confidence * 100).toFixed(0) }}%</el-descriptions-item>
                        <el-descriptions-item :label="t('attack_detection_page.cols.source_ip')">{{ selectedEvent.source_ip }}</el-descriptions-item>
                        <el-descriptions-item :label="t('attack_detection_page.cols.status')">{{ selectedEvent.status }}</el-descriptions-item>
                        <el-descriptions-item :label="t('attack_detection_page.cols.action')">{{ selectedEvent.action_taken || '-' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('attack_detection_page.cols.description')" :span="2">{{ selectedEvent.description }}</el-descriptions-item>
                    </el-descriptions>
                    <div style="margin-top:12px">
                        <el-select v-model="statusUpdate" style="width:140px;margin-right:8px">
                            <el-option :label="t('attack_detection_page.statuses.investigating')" value="investigating" />
                            <el-option :label="t('attack_detection_page.statuses.blocked')" value="blocked" />
                            <el-option :label="t('attack_detection_page.statuses.resolved')" value="resolved" />
                            <el-option :label="t('attack_detection_page.statuses.false_positive')" value="false_positive" />
                        </el-select>
                        <el-button type="primary" @click="handleUpdateStatus">{{ t('attack_detection_page.update_status') }}</el-button>
                        <el-button type="danger" @click="handleBlockIp(selectedEvent.source_ip)" v-if="selectedEvent.source_ip">{{ t('attack_detection_page.block_this_ip') }}</el-button>
                    </div>

                    <el-card v-if="selectedEvent.context" shadow="never" style="margin-top:16px">
                        <template #header><span>{{ t('attack_detection_page.context') }}</span></template>
                        <pre class="json-view">{{ JSON.stringify(selectedEvent.context, null, 2) }}</pre>
                    </el-card>
                </div>
            </el-tab-pane>

            <el-tab-pane :label="t('attack_detection_page.tabs.blocks')" name="blocks">
                <div class="toolbar">
                    <el-button type="primary" @click="showBlockDialog = true">{{ t('attack_detection_page.manual_block') }}</el-button>
                    <el-button @click="loadIpBlocks">{{ t('actions.refresh') }}</el-button>
                </div>
                <el-table :data="ipBlocks" v-loading="blocksLoading" stripe>
                    <el-table-column prop="ip" label="IP" width="150" />
                    <el-table-column prop="reason" :label="t('attack_detection_page.cols.reason')" min-width="250" show-overflow-tooltip />
                    <el-table-column prop="attack_type" :label="t('attack_detection_page.cols.type')" width="100" />
                    <el-table-column prop="confidence" :label="t('attack_detection_page.cols.confidence')" width="80"><template #default="{row}">{{ (row.confidence * 100).toFixed(0) }}%</template></el-table-column>
                    <el-table-column :label="t('attack_detection_page.cols.permanent')" width="70" align="center"><template #default="{row}"><el-tag :type="row.is_permanent?'danger':'info'" size="small">{{ row.is_permanent ? t('attack_detection_page.yes') : t('attack_detection_page.no') }}</el-tag></template></el-table-column>
                    <el-table-column prop="expires_at" :label="t('attack_detection_page.cols.expires')" width="170" />
                    <el-table-column :label="t('attack_detection_page.cols.actions')" width="100">
                        <template #default="{row}">
                            <el-button link type="danger" size="small" @click="handleUnblockIp(row.ip)">{{ t('attack_detection_page.unblock') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <el-dialog v-model="showBlockDialog" :title="t('attack_detection_page.block_dialog')" width="450px">
                    <el-form :model="blockForm" label-width="100px">
                        <el-form-item :label="t('attack_detection_page.ip_address')"><el-input v-model="blockForm.ip" placeholder="8.8.8.8" /></el-form-item>
                        <el-form-item :label="t('attack_detection_page.cols.reason')"><el-input v-model="blockForm.reason" type="textarea" :rows="2" /></el-form-item>
                        <el-form-item :label="t('attack_detection_page.duration')">
                            <el-select v-model="blockForm.duration_minutes" style="width:100%">
                                <el-option :label="t('attack_detection_page.durations.m30')" :value="30" />
                                <el-option :label="t('attack_detection_page.durations.h1')" :value="60" />
                                <el-option :label="t('attack_detection_page.durations.h6')" :value="360" />
                                <el-option :label="t('attack_detection_page.durations.h24')" :value="1440" />
                                <el-option :label="t('attack_detection_page.durations.permanent')" :value="0" />
                            </el-select>
                        </el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showBlockDialog = false">{{ t('actions.cancel') }}</el-button>
                        <el-button type="danger" @click="handleBlockConfirm">{{ t('attack_detection_page.block') }}</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getAttackDashboard, getAttackEvents, getAttackEventDetail, updateAttackEventStatus, getIpBlocks, blockIp, unblockIp } from '@/api/attackDetection'

const { t } = useI18n()

const activeTab = ref('events')
const stats = ref({})
const events = ref([])
const loading = ref(false)
const selectedEvent = ref(null)
const statusUpdate = ref('investigating')
const filterType = ref('')
const filterSeverity = ref('')
const detectorConfig = ref({})
const ipBlocks = ref([])
const blocksLoading = ref(false)
const showBlockDialog = ref(false)
const blockForm = reactive({ ip: '', reason: '', duration_minutes: 60 })

async function loadDashboard() {
    try { stats.value = await getAttackDashboard() } catch (e) { console.error(e) }
}
async function loadEvents() {
    loading.value = true
    try {
        const params = { per_page: 50 }
        if (filterType.value) params.attack_type = filterType.value
        if (filterSeverity.value) params.severity = filterSeverity.value
        const r = await getAttackEvents(params)
        events.value = r.data || []
    } catch (e) { console.error(e) } finally { loading.value = false }
}
async function loadIpBlocks() {
    blocksLoading.value = true
    try { const r = await getIpBlocks({ per_page: 50 }); ipBlocks.value = r.data || [] }
    catch (e) { console.error(e) } finally { blocksLoading.value = false }
}
async function showDetail(row) {
    try { const r = await getAttackEventDetail(row.id); selectedEvent.value = r; statusUpdate.value = r.status; activeTab.value = 'detail' }
    catch (e) { ElMessage.error(t('attack_detection_page.messages.detail_failed')) }
}
async function handleUpdateStatus() {
    try { await updateAttackEventStatus(selectedEvent.value.id, statusUpdate.value); ElMessage.success(t('attack_detection_page.messages.status_ok')); loadEvents() }
    catch (e) { ElMessage.error(t('attack_detection_page.messages.update_failed')) }
}
async function handleBlockIp(ip) {
    try {
        await ElMessageBox.confirm(t('attack_detection_page.block_confirm', { ip }), t('actions.confirm'))
        await blockIp({ ip, reason: t('attack_detection_page.auto_block_reason'), duration_minutes: 60 })
        ElMessage.success(t('attack_detection_page.messages.blocked'))
        loadIpBlocks()
    } catch (e) { if (e !== 'cancel') ElMessage.error(t('attack_detection_page.messages.block_failed')) }
}
async function handleBlockConfirm() {
    try {
        await blockIp({
            ip: blockForm.ip,
            reason: blockForm.reason,
            duration_minutes: blockForm.duration_minutes || 60,
            is_permanent: blockForm.duration_minutes === 0,
        })
        ElMessage.success(t('attack_detection_page.messages.blocked'))
        showBlockDialog.value = false
        loadIpBlocks()
    } catch (e) { ElMessage.error(t('attack_detection_page.messages.block_failed')) }
}
async function handleUnblockIp(ip) {
    try { await unblockIp(ip); ElMessage.success(t('attack_detection_page.messages.unblocked')); loadIpBlocks() }
    catch (e) { ElMessage.error(t('attack_detection_page.messages.unblock_failed')) }
}

onMounted(() => {
    loadDashboard(); loadEvents(); loadIpBlocks()
})
</script>

<style scoped>
.attack-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; padding: 6px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #0f172a; }
.stat-value.danger { color: #f56c6c; }
.stat-value.warning { color: #e6a23c; }
.stat-value.success { color: #67c23a; }
.stat-value.info { color: #909399; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.toolbar { margin-bottom: 16px; display: flex; align-items: center; flex-wrap: wrap; }
.json-view { background: #f5f7fa; padding: 12px; border-radius: 4px; font-size: 12px; max-height: 300px; overflow: auto; }
</style>
