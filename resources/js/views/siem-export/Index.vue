<template>
    <div class="siem-export-container">
        <el-page-header :content="t('siem_export_page.title')" @back="$router.push('/admin/dashboard')" />

        <el-alert
            :title="t('siem_export_page.alert')"
            type="info"
            show-icon
            :closable="false"
            class="alert-info"
        />

        <!-- 概览卡片 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dashboard.total_connections }}</div>
                    <div class="stat-label">{{ t('siem_export_page.stats.total_connections') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-success">{{ dashboard.active_connections }}</div>
                    <div class="stat-label">{{ t('siem_export_page.stats.active_connections') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dashboard.recent_pushes }}</div>
                    <div class="stat-label">{{ t('siem_export_page.stats.recent_pushes') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value" :class="dashboard.recent_success_rate < 90 ? 'text-danger' : 'text-success'">
                        {{ dashboard.recent_success_rate }}%
                    </div>
                    <div class="stat-label">{{ t('siem_export_page.stats.success_rate') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 主内容 -->
        <el-card>
            <el-tabs v-model="activeTab">
                <!-- 连接管理 -->
                <el-tab-pane :label="t('siem_export_page.tabs.connections')" name="connections">
                    <div class="section-header">
                        <h3>{{ t('siem_export_page.section.connections') }}</h3>
                        <el-button type="primary" size="small" @click="openCreateDialog">{{ t('bi_export_page.toolbar.new_connection') }}</el-button>
                    </div>

                    <el-table :data="connections" v-loading="loading" stripe>
                        <el-table-column prop="name" :label="t('siem_export_page.cols.name')" min-width="150" />
                        <el-table-column :label="t('siem_export_page.cols.format')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="formatTagType(row.format)" size="small">{{ formatLabel(row.format) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="endpoint_url" :label="t('siem_export_page.cols.endpoint')" min-width="250" show-overflow-tooltip />
                        <el-table-column :label="t('siem_export_page.cols.auto_push')" width="90" align="center">
                            <template #default="{ row }">
                                <el-tag :type="row.auto_push ? 'success' : 'info'" size="small">
                                    {{ row.auto_push ? toggleLabels.on : toggleLabels.off }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('siem_export_page.cols.frequency')" width="90">
                            <template #default="{ row }">{{ frequencyLabel(row.push_frequency) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('siem_export_page.cols.status')" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                    {{ row.is_active ? statusLabels.enabled : statusLabels.disabled }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('siem_export_page.cols.actions')" width="260" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="testConnection(row)">{{ t('scim_page.actions.test') }}</el-button>
                                <el-button size="small" @click="handlePushLogs(row)">{{ t('siem_export_page.row_actions.push') }}</el-button>
                                <el-button size="small" @click="editConnection(row)">{{ t('actions.edit') }}</el-button>
                                <el-button size="small" type="danger" plain @click="handleDelete(row)">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- 推送日志 -->
                <el-tab-pane :label="t('siem_export_page.tabs.logs')" name="logs">
                    <el-select v-model="logConnectionId" :placeholder="t('siem_export_page.select_connection_ph')" style="width:300px;margin-bottom:16px" @change="fetchLogs">
                        <el-option v-for="c in connections" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                    <el-table :data="siemPushLogs" v-loading="loadingLogs" stripe>
                        <el-table-column prop="status" :label="t('siem_export_page.cols.status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'success' ? 'success' : 'danger'" size="small">
                                    {{ row.status === 'success' ? statusLabels.success : statusLabels.failed }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="records_count" :label="t('siem_export_page.cols.records_count')" width="80" align="center" />
                        <el-table-column prop="response_code" :label="t('siem_export_page.cols.http_status')" width="100" align="center" />
                        <el-table-column prop="duration_ms" :label="t('siem_export_page.cols.duration_ms')" width="100" align="center" />
                        <el-table-column prop="error_message" :label="t('siem_export_page.cols.error_message')" min-width="250" show-overflow-tooltip />
                        <el-table-column prop="created_at" :label="t('siem_export_page.cols.pushed_at')" width="170" />
                    </el-table>
                </el-tab-pane>

                <!-- 格式预览 -->
                <el-tab-pane :label="t('siem_export_page.tabs.preview')" name="preview">
                    <el-radio-group v-model="previewFormat" @change="fetchPreview" style="margin-bottom:16px">
                        <el-radio-button value="cef">{{ formatLabels.cef }}</el-radio-button>
                        <el-radio-button value="elk_json">{{ formatLabels.elk_json }}</el-radio-button>
                        <el-radio-button value="sls">{{ formatLabels.sls }}</el-radio-button>
                    </el-radio-group>
                    <el-alert
                        :title="t('siem_export_page.preview.field_mapping', { format: previewFormat })"
                        type="info"
                        show-icon
                        :closable="false"
                        class="mb-2"
                    />
                    <el-descriptions v-if="previewMappings" :column="2" border size="small">
                        <el-descriptions-item v-for="(v, k) in previewMappings" :key="k" :label="k">
                            <code>{{ v }}</code>
                        </el-descriptions-item>
                    </el-descriptions>
                    <el-divider />
                    <h4>{{ t('siem_export_page.preview.sample_output') }}</h4>
                    <pre class="preview-block">{{ previewSample }}</pre>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- 新建/编辑 Dialog -->
        <el-dialog v-model="dialogVisible" :title="isEdit ? t('siem_export_page.dialog.edit_title') : t('siem_export_page.dialog.create_title')" width="650px">
            <el-form :model="form" label-width="130px" :rules="formRules" ref="formRef">
                <el-form-item :label="t('bi_export_page.form.name')" prop="name">
                    <el-input v-model="form.name" maxlength="100" />
                </el-form-item>
                <el-form-item :label="t('siem_export_page.form.format')" prop="format">
                    <el-select v-model="form.format" style="width:100%">
                        <el-option v-for="(label, val) in formatSelectOptions" :key="val" :label="label" :value="val" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('siem_export_page.form.endpoint_url')">
                    <el-input v-model="form.endpoint_url" :placeholder="t('siem_export_page.form.endpoint_url_ph')" />
                </el-form-item>
                <el-form-item :label="t('siem_export_page.form.auth_type')">
                    <el-select v-model="form.auth_type" style="width:100%">
                        <el-option v-for="opt in authTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item v-if="form.auth_type !== 'none'" :label="t('siem_export_page.form.auth_credentials')">
                    <el-input v-model="form.auth_credentials" type="textarea" :rows="2" :placeholder="t('siem_export_page.form.auth_credentials_ph')" />
                </el-form-item>
                <el-form-item :label="t('siem_export_page.form.auto_push')">
                    <el-switch v-model="form.auto_push" />
                </el-form-item>
                <el-form-item v-if="form.auto_push" :label="t('siem_export_page.form.push_frequency')">
                    <el-select v-model="form.push_frequency" style="width:100%">
                        <el-option v-for="opt in frequencyOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('siem_export_page.form.max_batch_size')">
                    <el-input-number v-model="form.max_batch_size" :min="100" :max="10000" :step="500" />
                </el-form-item>
                <el-form-item :label="t('siem_export_page.form.enable_connection')">
                    <el-switch v-model="form.is_active" />
                </el-form-item>
                <el-form-item :label="t('siem_export_page.form.notes')">
                    <el-input v-model="form.notes" type="textarea" :rows="2" maxlength="500" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getSiemDashboard, getSiemConnections, createSiemConnection, updateSiemConnection,
    deleteSiemConnection, testSiemConnection, pushSiemLogs,
    getSiemPushLogs, getSiemConnectionStats, getSiemFormats, getSiemFormatPreview,
} from '@/api/siemExport'

const { t } = useI18n()

const activeTab = ref('connections')
const loading = ref(false)
const connections = ref([])
const dashboard = ref({
    total_connections: 0, active_connections: 0, auto_push_enabled: 0,
    format_distribution: {}, recent_pushes: 0, recent_failures: 0, recent_success_rate: 100,
})

const formatLabels = computed(() => ({
    cef: t('siem_export_page.formats.cef'),
    elk_json: t('siem_export_page.formats.elk_json'),
    sls: t('siem_export_page.formats.sls'),
}))

const frequencyLabels = computed(() => ({
    realtime: t('siem_export_page.frequencies.realtime'),
    hourly: t('siem_export_page.frequencies.hourly'),
    daily: t('siem_export_page.frequencies.daily'),
}))

const statusLabels = computed(() => ({
    enabled: t('im_integration_page.status.enabled'),
    disabled: t('im_integration_page.status.disabled'),
    success: t('im_integration_page.filters.success'),
    failed: t('im_integration_page.filters.failed'),
}))

const toggleLabels = computed(() => ({
    on: t('siem_export_page.toggle.on'),
    off: t('siem_export_page.toggle.off'),
}))

const authTypeOptions = computed(() => [
    { label: t('siem_export_page.auth_types.none'), value: 'none' },
    { label: t('siem_export_page.auth_types.bearer_token'), value: 'bearer_token' },
    { label: t('siem_export_page.auth_types.basic'), value: 'basic' },
    { label: t('siem_export_page.auth_types.api_key'), value: 'api_key' },
])

const frequencyOptions = computed(() => [
    { label: t('siem_export_page.frequencies.realtime'), value: 'realtime' },
    { label: t('siem_export_page.frequencies.hourly'), value: 'hourly' },
    { label: t('siem_export_page.frequencies.daily'), value: 'daily' },
])

// Dialog
const dialogVisible = ref(false)
const isEdit = ref(false)
const editingId = ref(null)
const form = ref({
    name: '', format: 'elk_json', endpoint_url: '', auth_type: 'none',
    auth_credentials: '', is_active: true, auto_push: false,
    push_frequency: 'realtime', max_batch_size: 1000, notes: '',
})
const formRules = computed(() => ({
    name: [{ required: true, message: t('siem_export_page.validation.name_required') }],
    format: [{ required: true, message: t('siem_export_page.validation.format_required') }],
}))
const formRef = ref(null)
const saving = ref(false)
const formatOptions = ref({})

const formatSelectOptions = computed(() => {
    const keys = Object.keys(formatOptions.value)
    const source = keys.length ? keys : Object.keys(formatLabels.value)
    return Object.fromEntries(source.map((key) => [key, formatLabels.value[key] || formatOptions.value[key] || key]))
})

// Push logs
const logConnectionId = ref(null)
const siemPushLogs = ref([])
const loadingLogs = ref(false)

// Format preview
const previewFormat = ref('cef')
const previewMappings = ref(null)
const previewSample = ref('')

function openCreateDialog() {
    isEdit.value = false
    editingId.value = null
    form.value = {
        name: '', format: 'elk_json', endpoint_url: '', auth_type: 'none',
        auth_credentials: '', is_active: true, auto_push: false,
        push_frequency: 'realtime', max_batch_size: 1000, notes: '',
    }
    dialogVisible.value = true
}

function editConnection(row) {
    isEdit.value = true
    editingId.value = row.id
    form.value = {
        name: row.name, format: row.format, endpoint_url: row.endpoint_url || '',
        auth_type: row.auth_type || 'none', auth_credentials: '',
        is_active: row.is_active, auto_push: row.auto_push,
        push_frequency: row.push_frequency || 'realtime',
        max_batch_size: row.max_batch_size || 1000, notes: row.notes || '',
    }
    dialogVisible.value = true
}

onMounted(() => {
    fetchDashboard()
    fetchConnections()
    fetchFormats()
    fetchPreview()
})

async function fetchDashboard() {
    try {
        const res = await getSiemDashboard()
        if (res.data) dashboard.value = res.data
    } catch { /* ignore */ }
}

async function fetchConnections() {
    loading.value = true
    try {
        const res = await getSiemConnections()
        connections.value = res.data?.connections || []
    } catch { connections.value = [] }
    loading.value = false
}

async function fetchFormats() {
    try {
        const res = await getSiemFormats()
        formatOptions.value = res.data?.formats || {}
    } catch { /* ignore */ }
}

async function fetchPreview() {
    try {
        const res = await getSiemFormatPreview(previewFormat.value)
        previewMappings.value = res.data?.field_mappings || null
        previewSample.value = JSON.stringify(res.data?.sample, null, 2)
    } catch { previewSample.value = t('messages.load_failed') }
}

function formatLabel(f) {
    return formatLabels.value[f] || f
}

function formatTagType(f) {
    return { cef: 'warning', elk_json: 'primary', sls: 'danger' }[f] || 'info'
}

function frequencyLabel(f) {
    return frequencyLabels.value[f] || f
}

async function handleSave() {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return
    saving.value = true
    try {
        if (isEdit.value && editingId.value) {
            await updateSiemConnection(editingId.value, form.value)
            ElMessage.success(t('siem_export_page.messages.connection_updated'))
        } else {
            await createSiemConnection(form.value)
            ElMessage.success(t('siem_export_page.messages.connection_created'))
        }
        dialogVisible.value = false
        fetchConnections()
        fetchDashboard()
    } catch (e) {
        ElMessage.error(e.message || t('messages.failed'))
    }
    saving.value = false
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(
            t('siem_export_page.confirm.delete_connection', { name: row.name }),
            t('actions.confirm'),
        )
        await deleteSiemConnection(row.id)
        ElMessage.success(t('bi_export_page.messages.deleted'))
        fetchConnections()
        fetchDashboard()
    } catch { /* ignore */ }
}

async function testConnection(row) {
    try {
        ElMessage.info(t('siem_export_page.messages.testing_connection'))
        const res = await testSiemConnection(row.id)
        ElMessage.success(t('siem_export_page.messages.test_success', {
            ms: res.data?.duration_ms || 0,
            code: res.data?.status_code,
        }))
    } catch (e) {
        ElMessage.error(e.message || t('siem_export_page.messages.test_failed'))
    }
}

async function handlePushLogs(row) {
    try {
        ElMessage.info(t('siem_export_page.messages.pushing_logs'))
        const res = await pushSiemLogs(row.id, {})
        ElMessage.success(t('siem_export_page.messages.push_success', { n: res.data?.pushed || 0 }))
        fetchDashboard()
    } catch (e) {
        ElMessage.error(e.message || t('siem_export_page.messages.push_failed'))
    }
}

async function fetchLogs() {
    if (!logConnectionId.value) return
    loadingLogs.value = true
    try {
        const res = await getSiemPushLogs(logConnectionId.value)
        siemPushLogs.value = res.data?.logs || []
    } catch { siemPushLogs.value = [] }
    loadingLogs.value = false
}
</script>

<style scoped>
.siem-export-container {
    padding: 20px;
}

.alert-info {
    margin-top: 16px;
    margin-bottom: 16px;
}

.stat-cards {
    margin-bottom: 16px;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    text-align: center;
}

.stat-label {
    font-size: 13px;
    color: #909399;
    text-align: center;
    margin-top: 6px;
}

.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; }

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.section-header h3 {
    margin: 0;
}

.preview-block {
    background: #f5f7fa;
    padding: 16px;
    border-radius: 6px;
    font-size: 12px;
    white-space: pre-wrap;
    word-break: break-all;
    max-height: 400px;
    overflow-y: auto;
}

.mb-2 {
    margin-bottom: 8px;
}
</style>
