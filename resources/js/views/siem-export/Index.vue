<template>
    <div class="siem-export-container">
        <el-page-header :content="'SIEM 审计日志导出'" @back="$router.push('/admin/dashboard')" />

        <el-alert
            title="支持将审计日志转换为 Splunk CEF / ELK Stack JSON / 阿里云 SLS 格式，并自动推送到 SIEM 系统。"
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
                    <div class="stat-label">SIEM 连接总数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-success">{{ dashboard.active_connections }}</div>
                    <div class="stat-label">活跃连接</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dashboard.recent_pushes }}</div>
                    <div class="stat-label">近7天推送次数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value" :class="dashboard.recent_success_rate < 90 ? 'text-danger' : 'text-success'">
                        {{ dashboard.recent_success_rate }}%
                    </div>
                    <div class="stat-label">推送成功率</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 主内容 -->
        <el-card>
            <el-tabs v-model="activeTab">
                <!-- 连接管理 -->
                <el-tab-pane label="连接管理" name="connections">
                    <div class="section-header">
                        <h3>SIEM 连接</h3>
                        <el-button type="primary" size="small" @click="openCreateDialog">新建连接</el-button>
                    </div>

                    <el-table :data="connections" v-loading="loading" stripe>
                        <el-table-column prop="name" label="名称" min-width="150" />
                        <el-table-column label="格式" width="100">
                            <template #default="{ row }">
                                <el-tag :type="formatTagType(row.format)" size="small">{{ formatLabel(row.format) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="endpoint_url" label="推送地址" min-width="250" show-overflow-tooltip />
                        <el-table-column label="自动推送" width="90" align="center">
                            <template #default="{ row }">
                                <el-tag :type="row.auto_push ? 'success' : 'info'" size="small">
                                    {{ row.auto_push ? '开启' : '关闭' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="频率" width="90">
                            <template #default="{ row }">{{ frequencyLabel(row.push_frequency) }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                    {{ row.is_active ? '启用' : '停用' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="260" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="testConnection(row)">测试</el-button>
                                <el-button size="small" @click="handlePushLogs(row)">推送</el-button>
                                <el-button size="small" @click="editConnection(row)">编辑</el-button>
                                <el-button size="small" type="danger" plain @click="handleDelete(row)">删除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- 推送日志 -->
                <el-tab-pane label="推送日志" name="logs">
                    <el-select v-model="logConnectionId" placeholder="选择连接" style="width:300px;margin-bottom:16px" @change="fetchLogs">
                        <el-option v-for="c in connections" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                    <el-table :data="siemPushLogs" v-loading="loadingLogs" stripe>
                        <el-table-column prop="status" label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'success' ? 'success' : 'danger'" size="small">
                                    {{ row.status === 'success' ? '成功' : '失败' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="records_count" label="记录数" width="80" align="center" />
                        <el-table-column prop="response_code" label="HTTP状态" width="100" align="center" />
                        <el-table-column prop="duration_ms" label="耗时(ms)" width="100" align="center" />
                        <el-table-column prop="error_message" label="错误信息" min-width="250" show-overflow-tooltip />
                        <el-table-column prop="created_at" label="推送时间" width="170" />
                    </el-table>
                </el-tab-pane>

                <!-- 格式预览 -->
                <el-tab-pane label="格式预览" name="preview">
                    <el-radio-group v-model="previewFormat" @change="fetchPreview" style="margin-bottom:16px">
                        <el-radio-button value="cef">Splunk CEF</el-radio-button>
                        <el-radio-button value="elk_json">ELK Stack JSON</el-radio-button>
                        <el-radio-button value="sls">阿里云 SLS</el-radio-button>
                    </el-radio-group>
                    <el-alert
                        :title="`字段映射 (${previewFormat})`"
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
                    <h4>示例输出</h4>
                    <pre class="preview-block">{{ previewSample }}</pre>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- 新建/编辑 Dialog -->
        <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑连接' : '新建 SIEM 连接'" width="650px">
            <el-form :model="form" label-width="130px" :rules="formRules" ref="formRef">
                <el-form-item label="名称" prop="name">
                    <el-input v-model="form.name" maxlength="100" />
                </el-form-item>
                <el-form-item label="SIEM 格式" prop="format">
                    <el-select v-model="form.format" style="width:100%">
                        <el-option v-for="(label, val) in formatOptions" :key="val" :label="label" :value="val" />
                    </el-select>
                </el-form-item>
                <el-form-item label="推送端点 URL">
                    <el-input v-model="form.endpoint_url" placeholder="https://siem.example.com:8080/audit" />
                </el-form-item>
                <el-form-item label="认证方式">
                    <el-select v-model="form.auth_type" style="width:100%">
                        <el-option label="无认证" value="none" />
                        <el-option label="Bearer Token" value="bearer_token" />
                        <el-option label="Basic 认证" value="basic" />
                        <el-option label="API Key" value="api_key" />
                    </el-select>
                </el-form-item>
                <el-form-item v-if="form.auth_type !== 'none'" label="认证凭证">
                    <el-input v-model="form.auth_credentials" type="textarea" :rows="2" placeholder="Token / Key / user:pass" />
                </el-form-item>
                <el-form-item label="自动推送">
                    <el-switch v-model="form.auto_push" />
                </el-form-item>
                <el-form-item v-if="form.auto_push" label="推送频率">
                    <el-select v-model="form.push_frequency" style="width:100%">
                        <el-option label="实时" value="realtime" />
                        <el-option label="每小时" value="hourly" />
                        <el-option label="每日" value="daily" />
                    </el-select>
                </el-form-item>
                <el-form-item label="每批最大记录数">
                    <el-input-number v-model="form.max_batch_size" :min="100" :max="10000" :step="500" />
                </el-form-item>
                <el-form-item label="启用连接">
                    <el-switch v-model="form.is_active" />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="form.notes" type="textarea" :rows="2" maxlength="500" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getSiemDashboard, getSiemConnections, createSiemConnection, updateSiemConnection,
    deleteSiemConnection, testSiemConnection, pushSiemLogs,
    getSiemPushLogs, getSiemConnectionStats, getSiemFormats, getSiemFormatPreview,
} from '@/api/siemExport'

const activeTab = ref('connections')
const loading = ref(false)
const connections = ref([])
const dashboard = ref({
    total_connections: 0, active_connections: 0, auto_push_enabled: 0,
    format_distribution: {}, recent_pushes: 0, recent_failures: 0, recent_success_rate: 100,
})

// Dialog
const dialogVisible = ref(false)
const isEdit = ref(false)
const editingId = ref(null)
const form = ref({
    name: '', format: 'elk_json', endpoint_url: '', auth_type: 'none',
    auth_credentials: '', is_active: true, auto_push: false,
    push_frequency: 'realtime', max_batch_size: 1000, notes: '',
})
const formRules = {
    name: [{ required: true, message: '请输入名称' }],
    format: [{ required: true, message: '请选择格式' }],
}
const formRef = ref(null)
const saving = ref(false)
const formatOptions = ref({})

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
    } catch { previewSample.value = '加载失败' }
}

function formatLabel(f) {
    return { cef: 'Splunk CEF', elk_json: 'ELK JSON', sls: '阿里云 SLS' }[f] || f
}

function formatTagType(f) {
    return { cef: 'warning', elk_json: 'primary', sls: 'danger' }[f] || 'info'
}

function frequencyLabel(f) {
    return { realtime: '实时', hourly: '每小时', daily: '每日' }[f] || f
}

async function handleSave() {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return
    saving.value = true
    try {
        if (isEdit.value && editingId.value) {
            await updateSiemConnection(editingId.value, form.value)
            ElMessage.success('连接已更新')
        } else {
            await createSiemConnection(form.value)
            ElMessage.success('连接已创建')
        }
        dialogVisible.value = false
        fetchConnections()
        fetchDashboard()
    } catch (e) {
        ElMessage.error(e.message || '操作失败')
    }
    saving.value = false
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(`确定删除 SIEM 连接「${row.name}」？`, '确认')
        await deleteSiemConnection(row.id)
        ElMessage.success('已删除')
        fetchConnections()
        fetchDashboard()
    } catch { /* ignore */ }
}

async function testConnection(row) {
    try {
        ElMessage.info('正在测试连接...')
        const res = await testSiemConnection(row.id)
        ElMessage.success(`连接测试成功 (${res.data?.duration_ms || 0}ms, HTTP ${res.data?.status_code})`)
    } catch (e) {
        ElMessage.error(e.message || '连接测试失败')
    }
}

async function handlePushLogs(row) {
    try {
        ElMessage.info('正在推送日志...')
        const res = await pushSiemLogs(row.id, {})
        ElMessage.success(`已推送 ${res.data?.pushed || 0} 条记录`)
        fetchDashboard()
    } catch (e) {
        ElMessage.error(e.message || '推送失败')
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
