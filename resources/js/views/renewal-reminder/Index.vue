<template>
  <div class="renewal-reminder-page">
    <div class="page-header">
      <h2><el-icon style="vertical-align:middle;margin-right:8px"><Bell /></el-icon>续费提醒与优化</h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 优化建议横幅 -->
    <el-row :gutter="16" class="mb-4" v-if="suggestions.length > 0">
      <el-col :span="24">
        <el-card shadow="hover" class="suggestion-card">
          <template #header>
            <span><el-icon style="vertical-align:middle;margin-right:4px"><DataAnalysis /></el-icon> 优化建议</span>
          </template>
          <el-row :gutter="12">
            <el-col v-for="s in suggestions" :key="s.type" :span="8" style="margin-bottom:8px">
              <el-alert
                :title="s.title"
                :description="s.message"
                :type="s.severity === 'critical' ? 'error' : s.severity === 'high' ? 'warning' : 'info'"
                show-icon
                :closable="false"
              />
            </el-col>
          </el-row>
        </el-card>
      </el-col>
    </el-row>

    <!-- 转化分析卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ analytics.auto_renew_rate || 0 }}%</div>
          <div class="stat-label">自动续费开通率</div>
          <div class="stat-sub">已开通 {{ analytics.auto_renew_count || 0 }} / {{ analytics.total_active || 0 }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="analytics.conversion_rate_30d >= 60 ? 'stat-success' : 'stat-danger'">
            {{ analytics.conversion_rate_30d || 0 }}%
          </div>
          <div class="stat-label">近30日续费转化率</div>
          <div class="stat-sub">续费 {{ analytics.renewed_30d || 0 }} / 过期 {{ analytics.expired_30d || 0 }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ analytics.renewed_30d || 0 }}</div>
          <div class="stat-label">近30日成功续费</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-danger">{{ analytics.expired_30d || 0 }}</div>
          <div class="stat-label">近30日已过期</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 渠道统计 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="24">
        <el-card shadow="hover">
          <template #header><span>提醒渠道分布</span></template>
          <div ref="channelChartRef" style="height:180px"></div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主内容 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- 提醒模板 -->
        <el-tab-pane label="提醒模板" name="templates">
          <div class="tab-toolbar">
            <el-button size="small" type="primary" @click="showTemplateDialog(null)">
              <el-icon><Plus /></el-icon> 新建模板
            </el-button>
            <el-button size="small" @click="handleProcessDue" :loading="processingDue">
              <el-icon><Promotion /></el-icon> 立即发送待处理提醒
            </el-button>
            <el-select v-model="tmplFilter.channel" placeholder="渠道" clearable style="width:120px;margin-left:8px">
              <el-option label="全部渠道" value="" />
              <el-option label="邮件" value="mail" />
              <el-option label="短信" value="sms" />
              <el-option label="站内信" value="in_app" />
            </el-select>
          </div>
          <el-table :data="templates" stripe v-loading="tmplLoading">
            <el-table-column prop="name" label="模板名称" min-width="140" />
            <el-table-column label="渠道" width="80">
              <template #default="{ row }">{{ channelLabel(row.channel) }}</template>
            </el-table-column>
            <el-table-column label="到期前天" width="100" align="center">
              <template #default="{ row }">{{ row.days_before }} 天</template>
            </el-table-column>
            <el-table-column label="启用" width="70" align="center">
              <template #default="{ row }">
                <el-switch v-model="row.is_active" size="small" @change="toggleTemplateActive(row)" />
              </template>
            </el-table-column>
            <el-table-column prop="subject" label="主题" min-width="180" show-overflow-tooltip />
            <el-table-column label="操作" width="160">
              <template #default="{ row }">
                <el-button size="small" text type="primary" @click="showTemplateDialog(row)">编辑</el-button>
                <el-button size="small" text type="danger" @click="handleDeleteTemplate(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- 发送记录 -->
        <el-tab-pane label="发送记录" name="logs">
          <div class="tab-toolbar">
            <el-select v-model="logFilter.status" placeholder="状态" clearable style="width:120px">
              <el-option label="全部" value="" />
              <el-option label="待发送" value="pending" />
              <el-option label="已发送" value="sent" />
              <el-option label="失败" value="failed" />
            </el-select>
            <el-select v-model="logFilter.channel" placeholder="渠道" clearable style="width:120px;margin-left:8px">
              <el-option label="全部渠道" value="" />
              <el-option label="邮件" value="mail" />
              <el-option label="短信" value="sms" />
              <el-option label="站内信" value="in_app" />
            </el-select>
          </div>
          <el-table :data="reminderLogs" stripe v-loading="logLoading">
            <el-table-column prop="subscription_id" label="订阅ID" width="80" />
            <el-table-column label="渠道" width="80">
              <template #default="{ row }">{{ channelLabel(row.channel) }}</template>
            </el-table-column>
            <el-table-column prop="template_name" label="模板" width="120" />
            <el-table-column prop="subject" label="主题" min-width="180" show-overflow-tooltip />
            <el-table-column label="状态" width="90">
              <template #default="{ row }">
                <el-tag :type="row.status === 'sent' ? 'success' : row.status === 'failed' ? 'danger' : 'warning'" size="small">
                  {{ row.status === 'sent' ? '已发送' : row.status === 'failed' ? '失败' : '待发送' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="发送时间" width="150">
              <template #default="{ row }">{{ formatTime(row.sent_at) }}</template>
            </el-table-column>
            <el-table-column label="错误" min-width="120" show-overflow-tooltip>
              <template #default="{ row }"><span class="error-text">{{ row.error }}</span></template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 模板对话框 -->
    <el-dialog v-model="templateDialog.visible" :title="templateDialog.editing ? '编辑模板' : '新建模板'" width="550px">
      <el-form :model="templateForm" label-width="110px">
        <el-form-item label="模板名称" required>
          <el-input v-model="templateForm.name" />
        </el-form-item>
        <el-form-item label="渠道" required>
          <el-select v-model="templateForm.channel" style="width:100%">
            <el-option label="邮件" value="mail" />
            <el-option label="短信" value="sms" />
            <el-option label="站内信" value="in_app" />
          </el-select>
        </el-form-item>
        <el-form-item label="到期前天" required>
          <el-input-number v-model="templateForm.days_before" :min="0" :max="365" style="width:100%" />
        </el-form-item>
        <el-form-item label="主题" v-if="templateForm.channel !== 'sms'">
          <el-input v-model="templateForm.subject" placeholder="支持模板变量：{{customer_name}} {{plan}} {{price}} {{ends_at}} {{days_left}}" />
        </el-form-item>
        <el-form-item label="内容" v-if="templateForm.channel !== 'sms'">
          <el-input v-model="templateForm.content" type="textarea" :rows="4" placeholder="支持模板变量：{{customer_name}} {{plan}} {{price}} {{ends_at}} {{days_left}}" />
        </el-form-item>
        <el-form-item label="短信内容" v-if="templateForm.channel === 'sms'">
          <el-input v-model="templateForm.sms_content" type="textarea" :rows="3" maxlength="500" show-word-limit placeholder="短信内容，支持模板变量" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="templateDialog.visible = false">取消</el-button>
        <el-button type="primary" @click="saveTemplate" :loading="savingTemplate">{{ templateDialog.editing ? '保存' : '创建' }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Bell, Refresh, Plus, Promotion, DataAnalysis } from '@element-plus/icons-vue'
import * as echarts from 'echarts'
import api from '../../api/renewalReminder'

const loading = ref(false)
const activeTab = ref('templates')

const analytics = ref({})
const suggestions = ref([])

const templates = ref([])
const tmplLoading = ref(false)
const tmplFilter = ref({ channel: '' })

const reminderLogs = ref([])
const logLoading = ref(false)
const logFilter = ref({ status: '', channel: '' })

const processingDue = ref(false)

const templateDialog = ref({ visible: false, editing: null })
const templateForm = ref({ name: '', channel: 'mail', days_before: 30, subject: '', content: '', sms_content: '' })
const savingTemplate = ref(false)

const channelChartRef = ref(null)
let channelChart = null

function formatTime(t) {
    if (!t) return '-'
    return new Date(t).toLocaleString('zh-CN')
}

function channelLabel(c) {
    const map = { mail: '邮件', sms: '短信', in_app: '站内信' }
    return map[c] || c
}

async function loadAnalytics() {
    try {
        const res = await api.getConversionAnalytics()
        analytics.value = res.data || {}
    } catch (e) { console.error('Failed to load analytics', e) }
}

async function loadSuggestions() {
    try {
        const res = await api.getOptimizationSuggestions()
        suggestions.value = res.data || []
    } catch (e) { console.error('Failed to load suggestions', e) }
}

async function loadTemplates() {
    tmplLoading.value = true
    try {
        const params = {}
        if (tmplFilter.value.channel) params.channel = tmplFilter.value.channel
        const res = await api.getTemplates(params)
        templates.value = res.data?.data || res.data || []
    } catch (e) { console.error('Failed to load templates', e) }
    finally { tmplLoading.value = false }
}

async function loadLogs() {
    logLoading.value = true
    try {
        const params = {}
        if (logFilter.value.status) params.status = logFilter.value.status
        if (logFilter.value.channel) params.channel = logFilter.value.channel
        const res = await api.getReminderLogs(params)
        reminderLogs.value = res.data?.data || res.data || []
    } catch (e) { console.error('Failed to load logs', e) }
    finally { logLoading.value = false }
}

async function refreshAll() {
    loading.value = true
    await Promise.all([loadAnalytics(), loadSuggestions(), loadTemplates(), loadLogs()])
    await nextTick()
    renderChannelChart()
    loading.value = false
}

function renderChannelChart() {
    if (!channelChartRef.value || !analytics.value.channel_stats) return
    if (channelChart) channelChart.dispose()
    channelChart = echarts.init(channelChartRef.value)
    const stats = analytics.value.channel_stats || {}
    const data = Object.entries(stats).map(([k, v]) => ({
        name: channelLabel(k),
        value: v.total || 0,
    }))
    if (data.length === 0) return
    channelChart.setOption({
        tooltip: { trigger: 'item', formatter: '{b}: {c}' },
        series: [{
            type: 'pie',
            radius: ['40%', '70%'],
            data,
            label: { show: true, formatter: '{b}: {c}' },
        }],
    })
}

// 模板 CRUD
function showTemplateDialog(row) {
    templateDialog.value.editing = row
    if (row) {
        templateForm.value = {
            name: row.name,
            channel: row.channel,
            days_before: row.days_before,
            subject: row.subject || '',
            content: row.content || '',
            sms_content: row.sms_content || '',
        }
    } else {
        templateForm.value = { name: '', channel: 'mail', days_before: 30, subject: '', content: '', sms_content: '' }
    }
    templateDialog.value.visible = true
}

function saveTemplate() {
    savingTemplate.value = true
    const data = { ...templateForm.value }
    const call = templateDialog.value.editing
        ? api.updateTemplate(templateDialog.value.editing.id, data)
        : api.createTemplate(data)
    call.then(() => {
        ElMessage.success(templateDialog.value.editing ? '模板已更新' : '模板已创建')
        templateDialog.value.visible = false
        loadTemplates()
    }).catch(e => ElMessage.error('操作失败: ' + (e.response?.data?.message || e.message)))
    .finally(() => savingTemplate.value = false)
}

async function toggleTemplateActive(row) {
    await api.updateTemplate(row.id, { is_active: row.is_active })
}

function handleDeleteTemplate(row) {
    ElMessageBox.confirm(`确定删除模板"${row.name}"?`, '确认', { type: 'warning' }).then(() => {
        api.deleteTemplate(row.id).then(() => {
            ElMessage.success('已删除')
            loadTemplates()
        })
    }).catch(() => {})
}

async function handleProcessDue() {
    processingDue.value = true
    try {
        const res = await api.processDue()
        const d = res.data || {}
        ElMessage.success(`处理完成：${d.sent || 0} 条已发送，${d.failed || 0} 条失败`)
        loadLogs()
        loadAnalytics()
    } catch (e) { ElMessage.error('处理失败: ' + (e.response?.data?.message || e.message)) }
    finally { processingDue.value = false }
}

watch(() => tmplFilter.value.channel, () => loadTemplates())
watch(() => logFilter.value.status, () => loadLogs())
watch(() => logFilter.value.channel, () => loadLogs())

onMounted(() => {
    refreshAll()
})
</script>

<style scoped>
.renewal-reminder-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 22px; }
.header-actions { display: flex; align-items: center; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-card .stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-card .stat-sub { font-size: 11px; color: #c0c4cc; margin-top: 2px; }
.stat-danger .stat-value { color: #f56c6c; }
.stat-success .stat-value { color: #67c23a; }
.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; }
.suggestion-card :deep(.el-card__body) { padding-top: 0; }
.error-text { color: #f56c6c; font-size: 12px; }
</style>
