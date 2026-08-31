<template>
  <div class="marketing-page">
    <div class="page-header">
      <h2><el-icon style="vertical-align:middle;margin-right:8px"><Promotion /></el-icon>{{ t('marketing_page.title') }}</h2>
      <div class="header-actions">
        <el-button type="primary" @click="openCreateDialog">
          <el-icon><Plus /></el-icon> {{ t('marketing_page.create_campaign') }}
        </el-button>
        <el-button @click="refreshAll" :loading="loading" style="margin-left:8px">
          <el-icon><Refresh /></el-icon> {{ t('marketing_page.refresh') }}
        </el-button>
      </div>
    </div>

    <el-row :gutter="16" class="mb-4">
      <el-col :xs="12" :sm="8" :md="4">
        <el-card shadow="hover"><div class="stat-value primary">{{ dashboard.active_campaigns }}</div><div class="stat-label">{{ t('marketing_page.stats.active') }}</div></el-card>
      </el-col>
      <el-col :xs="12" :sm="8" :md="4">
        <el-card shadow="hover"><div class="stat-value">{{ dashboard.total_campaigns }}</div><div class="stat-label">{{ t('marketing_page.stats.total') }}</div></el-card>
      </el-col>
      <el-col :xs="12" :sm="8" :md="4">
        <el-card shadow="hover"><div class="stat-value info">{{ dashboard.draft_campaigns }}</div><div class="stat-label">{{ t('marketing_page.stats.draft') }}</div></el-card>
      </el-col>
      <el-col :xs="12" :sm="8" :md="4">
        <el-card shadow="hover"><div class="stat-value success">{{ dashboard.completed_campaigns }}</div><div class="stat-label">{{ t('marketing_page.stats.completed') }}</div></el-card>
      </el-col>
      <el-col :xs="12" :sm="8" :md="4">
        <el-card shadow="hover"><div class="stat-value">{{ dashboard.total_sent }}</div><div class="stat-label">{{ t('marketing_page.stats.total_sent') }}</div></el-card>
      </el-col>
      <el-col :xs="12" :sm="8" :md="4">
        <el-card shadow="hover"><div class="stat-value warning">{{ dashboard.open_rate }}%</div><div class="stat-label">{{ t('marketing_page.stats.open_rate') }}</div></el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16" class="mb-4" v-if="dashboard.channel_stats && dashboard.channel_stats.length > 0">
      <el-col :span="24">
        <el-card shadow="hover">
          <template #header><span>{{ t('marketing_page.channel_distribution') }}</span></template>
          <div class="channel-bar">
            <div class="channel-item" v-for="ch in dashboard.channel_stats" :key="ch.channel">
              <span class="channel-label">{{ channelLabels[ch.channel] || ch.channel }}</span>
              <el-progress :percentage="Math.round((ch.success / (ch.total || 1)) * 100)" :text-inside="true" :stroke-width="20" :status="ch.success === ch.total ? 'success' : 'warning'">
                <span>{{ ch.success }}/{{ ch.total }}</span>
              </el-progress>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <el-tab-pane :label="t('marketing_page.tabs.campaigns')" name="campaigns">
          <div class="tab-toolbar">
            <el-input v-model="search" :placeholder="t('marketing_page.search_ph')" clearable style="width:200px" @clear="loadCampaigns" @keyup.enter="loadCampaigns" />
            <el-select v-model="filterStatus" :placeholder="t('marketing_page.status')" clearable style="width:120px;margin-left:8px" @change="loadCampaigns">
              <el-option :label="t('marketing_page.all')" value="" />
              <el-option v-for="(l, k) in statusLabels" :key="k" :label="l" :value="k" />
            </el-select>
            <el-select v-model="filterType" :placeholder="t('marketing_page.type')" clearable style="width:130px;margin-left:8px" @change="loadCampaigns">
              <el-option :label="t('marketing_page.all')" value="" />
              <el-option v-for="(l, k) in typeLabels" :key="k" :label="l" :value="k" />
            </el-select>
          </div>
          <el-table :data="campaigns" stripe v-loading="campaignsLoading">
            <el-table-column :label="t('marketing_page.cols.campaign_name')" min-width="180">
              <template #default="{ row }">
                <el-button type="primary" link @click="showDetail(row)">{{ row.name }}</el-button>
                <el-tag v-if="row.is_ab_test" size="small" type="warning" style="margin-left:4px">A/B</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('marketing_page.cols.type')" width="100">
              <template #default="{ row }">{{ typeLabels[row.type] || row.type }}</template>
            </el-table-column>
            <el-table-column :label="t('marketing_page.cols.status')" width="90">
              <template #default="{ row }">
                <el-tag :type="statusTag(row.status)" size="small">{{ statusLabels[row.status] || row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('marketing_page.cols.target')" width="70" align="center">
              <template #default="{ row }">{{ row.target_count }}</template>
            </el-table-column>
            <el-table-column :label="t('marketing_page.cols.sent_open_click')" width="180">
              <template #default="{ row }">{{ row.sent_count }} / {{ row.opened_count }} / {{ row.clicked_count }}</template>
            </el-table-column>
            <el-table-column :label="t('marketing_page.cols.created_by')" width="100">
              <template #default="{ row }">{{ row.created_by_name || '-' }}</template>
            </el-table-column>
            <el-table-column :label="t('marketing_page.cols.created_at')" width="150">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column :label="t('marketing_page.cols.actions')" width="320" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'draft'" type="primary" link size="small" @click="handleLaunch(row)">{{ t('marketing_page.row_actions.launch') }}</el-button>
                <el-button v-if="row.status === 'draft'" type="warning" link size="small" @click="openEditDialog(row)">{{ t('actions.edit') }}</el-button>
                <el-button v-if="row.status === 'active'" type="warning" link size="small" @click="handleToggle(row)">{{ t('marketing_page.row_actions.pause') }}</el-button>
                <el-button v-if="row.status === 'paused'" type="primary" link size="small" @click="handleToggle(row)">{{ t('marketing_page.row_actions.resume') }}</el-button>
                <el-button v-if="['active','paused'].includes(row.status)" type="success" link size="small" @click="handleComplete(row)">{{ t('marketing_page.row_actions.complete') }}</el-button>
                <el-button v-if="['draft','active','paused'].includes(row.status)" type="info" link size="small" @click="handleSimulate(row.id)">{{ t('marketing_page.row_actions.simulate') }}</el-button>
                <el-button v-if="row.status === 'active'" type="primary" link size="small" @click="handleSend(row.id)">{{ t('marketing_page.row_actions.send') }}</el-button>
                <el-popconfirm v-if="row.status === 'draft'" :title="t('marketing_page.delete_confirm')" @confirm="handleDelete(row)">
                  <template #reference><el-button type="danger" link size="small">{{ t('actions.delete') }}</el-button></template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <el-dialog v-model="showCreateDialog" :title="t('marketing_page.create_title')" width="650px">
      <el-form :model="createForm" label-width="110px">
        <el-form-item :label="t('marketing_page.campaign_name')" required>
          <el-input v-model="createForm.name" :placeholder="t('marketing_page.name_ph')" />
        </el-form-item>
        <el-form-item :label="t('marketing_page.description')">
          <el-input v-model="createForm.description" type="textarea" :rows="2" :placeholder="t('marketing_page.desc_ph')" />
        </el-form-item>
        <el-form-item :label="t('marketing_page.type')" required>
          <el-select v-model="createForm.type" style="width:100%">
            <el-option v-for="(l, k) in typeLabels" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('marketing_page.audience_type')">
          <el-select v-model="createForm.audience_type" style="width:100%">
            <el-option :label="t('marketing_page.audience_all')" value="all" />
            <el-option :label="t('marketing_page.audience_segment')" value="segment" />
            <el-option :label="t('marketing_page.audience_custom')" value="custom" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('marketing_page.scheduled_at')">
          <el-date-picker v-model="createForm.scheduled_at" type="datetime" :placeholder="t('marketing_page.send_immediately_ph')" style="width:100%" />
        </el-form-item>
        <el-form-item :label="t('marketing_page.budget')">
          <el-input-number v-model="createForm.budget" :min="0" :precision="2" style="width:100%" />
        </el-form-item>
        <el-form-item :label="t('marketing_page.ab_test')">
          <el-switch v-model="createForm.is_ab_test" />
          <span v-if="createForm.is_ab_test" style="margin-left:8px;font-size:12px;color:#909399;">
            {{ t('marketing_page.ab_split') }} <el-input-number v-model="createForm.ab_test_split" :min="10" :max="90" size="small" style="width:80px" />% / {{ t('marketing_page.ab_split_b') }} {{ 100 - (createForm.ab_test_split || 70) }}%
          </span>
        </el-form-item>
        <el-form-item :label="t('marketing_page.trigger_events')" v-if="createForm.type === 'multi_channel'">
          <el-select v-model="createForm.trigger_events" multiple :placeholder="t('marketing_page.trigger_events_ph')" style="width:100%">
            <el-option :label="t('marketing_page.trigger_user_registered')" value="user.registered" />
            <el-option :label="t('marketing_page.trigger_user_login')" value="user.login" />
            <el-option :label="t('marketing_page.trigger_order_completed')" value="order.completed" />
            <el-option :label="t('marketing_page.trigger_subscription_expiring')" value="subscription.expiring" />
          </el-select>
          <div style="font-size:12px;color:#909399;margin-top:4px;">{{ t('marketing_page.trigger_hint') }}</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleCreate" :loading="creating">{{ t('actions.create') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showEditDialog" :title="t('marketing_page.edit_title')" width="650px">
      <el-form :model="editForm" label-width="110px">
        <el-form-item :label="t('marketing_page.campaign_name')" required>
          <el-input v-model="editForm.name" />
        </el-form-item>
        <el-form-item :label="t('marketing_page.description')">
          <el-input v-model="editForm.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item :label="t('marketing_page.scheduled_at')">
          <el-date-picker v-model="editForm.scheduled_at" type="datetime" :placeholder="t('marketing_page.send_immediately_ph')" style="width:100%" />
        </el-form-item>
        <el-form-item :label="t('marketing_page.budget')">
          <el-input-number v-model="editForm.budget" :min="0" :precision="2" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showEditDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleEdit" :loading="editing">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showDetailDialog" :title="t('marketing_page.detail_title')" width="750px" :close-on-click-modal="false">
      <template v-if="detail">
        <el-descriptions :column="2" border>
          <el-descriptions-item :label="t('marketing_page.cols.name')">{{ detail.name }}</el-descriptions-item>
          <el-descriptions-item :label="t('marketing_page.cols.status')">
            <el-tag :type="statusTag(detail.status)" size="small">{{ statusLabels[detail.status] }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('marketing_page.cols.type')">{{ typeLabels[detail.type] }}</el-descriptions-item>
          <el-descriptions-item :label="t('marketing_page.ab_test')">{{ detail.is_ab_test ? t('marketing_page.ab_yes', { split: detail.ab_test_split }) : t('marketing_page.no') }}</el-descriptions-item>
          <el-descriptions-item :label="t('marketing_page.target_audience')">{{ detail.target_count }}</el-descriptions-item>
          <el-descriptions-item :label="t('marketing_page.budget')">{{ detail.budget ? '¥' + Number(detail.budget).toFixed(2) : t('marketing_page.none') }}</el-descriptions-item>
          <el-descriptions-item :label="t('marketing_page.sent_delivered_open_click')">
            <span>{{ detail.sent_count }} / {{ detail.delivered_count }} / {{ detail.opened_count }} / {{ detail.clicked_count }}</span>
          </el-descriptions-item>
          <el-descriptions-item :label="t('marketing_page.converted_unsubscribed')">{{ detail.converted_count || 0 }} / {{ detail.unsubscribed_count || 0 }}</el-descriptions-item>
          <el-descriptions-item :label="t('marketing_page.cost_spent')">{{ detail.cost_spent ? '¥' + Number(detail.cost_spent).toFixed(2) : '¥0.00' }}</el-descriptions-item>
          <el-descriptions-item :label="t('marketing_page.created_by_time')">{{ detail.created_by_name || '-' }} / {{ formatTime(detail.created_at) }}</el-descriptions-item>
        </el-descriptions>

        <div class="section-header">
          <h4>{{ t('marketing_page.marketing_steps') }}</h4>
          <el-button v-if="detail.status === 'draft'" size="small" type="primary" @click="openStepEditor(detail)">{{ t('marketing_page.edit_steps') }}</el-button>
        </div>
        <el-table :data="detail.steps || []" size="small">
          <el-table-column :label="t('marketing_page.cols.order')" width="60" align="center">
            <template #default="{ row }">{{ row.step_order }}</template>
          </el-table-column>
          <el-table-column :label="t('marketing_page.cols.action_type')" width="140">
            <template #default="{ row }">{{ stepActionLabels[row.action_type] || row.action_type }}</template>
          </el-table-column>
          <el-table-column :label="t('marketing_page.cols.delay')" width="120">
            <template #default="{ row }">{{ formatDelay(row) }}</template>
          </el-table-column>
          <el-table-column :label="t('marketing_page.cols.conditions')" min-width="120">
            <template #default="{ row }">{{ row.conditions ? JSON.stringify(row.conditions) : t('marketing_page.no_conditions') }}</template>
          </el-table-column>
        </el-table>

        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
          <el-button @click="handleSimulate(detail.id)" :loading="simulating">{{ t('marketing_page.simulate_send') }}</el-button>
          <el-button type="primary" @click="showAnalytics(detail.id)">{{ t('marketing_page.view_analytics') }}</el-button>
          <el-button v-if="detail.status === 'active'" type="primary" @click="handleSend(detail.id)" :loading="sending">{{ t('marketing_page.execute_send') }}</el-button>
        </div>
      </template>
    </el-dialog>

    <el-dialog v-model="showStepEditor" :title="t('marketing_page.edit_steps_title')" width="700px">
      <div v-if="stepEditorSteps.length === 0" style="text-align:center;padding:24px;color:#909399;">
        {{ t('marketing_page.no_steps') }}
      </div>
      <div v-for="(step, idx) in stepEditorSteps" :key="idx" class="step-editor-item">
        <div class="step-editor-header">
          <span class="step-order">{{ t('marketing_page.step_n', { n: idx + 1 }) }}</span>
          <el-button type="danger" size="small" text @click="stepEditorSteps.splice(idx, 1)">{{ t('marketing_page.remove') }}</el-button>
        </div>
        <el-form :model="step" label-width="80px" class="step-form">
          <el-form-item :label="t('marketing_page.cols.action_type')">
            <el-select v-model="step.action_type" style="width:100%">
              <el-option v-for="(l, k) in stepActionLabels" :key="k" :label="l" :value="k" />
            </el-select>
          </el-form-item>
          <el-form-item :label="t('marketing_page.cols.delay')">
            <el-radio-group v-model="step.delay_type">
              <el-radio value="immediate">{{ t('marketing_page.delay_immediate') }}</el-radio>
              <el-radio value="delay">{{ t('marketing_page.delay_type_delay') }}</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item v-if="step.delay_type === 'delay'" :label="t('marketing_page.delay_minutes_label')">
            <el-input-number v-model="step.delay_minutes" :min="1" :max="43200" style="width:100%" />
          </el-form-item>
          <el-form-item v-if="['send_email','send_sms','send_notification'].includes(step.action_type)" :label="t('marketing_page.email_subject')">
            <el-input v-model="step.config.subject" :placeholder="t('marketing_page.subject_ph')" />
          </el-form-item>
          <el-form-item v-if="['send_email','send_sms','send_notification'].includes(step.action_type)" :label="t('marketing_page.content')">
            <el-input v-model="step.config.content" type="textarea" :rows="3" :placeholder="t('marketing_page.content_ph')" />
          </el-form-item>
        </el-form>
      </div>
      <div style="margin-top:12px;">
        <el-button @click="addStep" :icon="Plus">{{ t('marketing_page.add_step') }}</el-button>
      </div>
      <template #footer>
        <el-button @click="showStepEditor = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleSaveSteps" :loading="savingSteps">{{ t('marketing_page.save_steps') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showAnalyticsDialog" :title="t('marketing_page.analytics_title')" width="720px">
      <template v-if="analyticsData">
        <el-row :gutter="16" class="mb-4">
          <el-col :span="8"><el-card shadow="hover"><div class="stat-value primary">{{ analyticsData.channel_breakdown ? Object.values(analyticsData.channel_breakdown).reduce((a,b) => a + (b.sent || 0), 0) : 0 }}</div><div class="stat-label">{{ t('marketing_page.stats.total_sent') }}</div></el-card></el-col>
          <el-col :span="8"><el-card shadow="hover"><div class="stat-value success">{{ analyticsData.daily_trend ? analyticsData.daily_trend.reduce((a,b) => a + (b.opened || 0), 0) : 0 }}</div><div class="stat-label">{{ t('marketing_page.stats.opens') }}</div></el-card></el-col>
          <el-col :span="8"><el-card shadow="hover"><div class="stat-value warning">{{ analyticsData.ab_results ? t('marketing_page.ab_groups_fmt', { n: analyticsData.ab_results.length }) : t('marketing_page.none') }}</div><div class="stat-label">{{ t('marketing_page.ab_test_label') }}</div></el-card></el-col>
        </el-row>

        <div v-if="analyticsData.channel_breakdown && Object.keys(analyticsData.channel_breakdown).length">
          <h4>{{ t('marketing_page.channel_details') }}</h4>
          <el-table :data="channelBreakdownList" size="small">
            <el-table-column :label="t('marketing_page.cols.channel')" width="100">
              <template #default="{ row }">{{ channelLabels[row.channel] || row.channel }}</template>
            </el-table-column>
            <el-table-column :label="t('marketing_page.cols.sent')" prop="sent" width="80" />
            <el-table-column :label="t('marketing_page.cols.delivered')" prop="delivered" width="80" />
            <el-table-column :label="t('marketing_page.cols.opened')" prop="opened" width="80" />
            <el-table-column :label="t('marketing_page.cols.clicked')" prop="clicked" width="80" />
          </el-table>
        </div>

        <div v-if="analyticsData.ab_results && analyticsData.ab_results.length">
          <h4>{{ t('marketing_page.ab_results') }}</h4>
          <el-table :data="analyticsData.ab_results" size="small">
            <el-table-column :label="t('marketing_page.cols.variant')" prop="ab_variant" width="80" />
            <el-table-column :label="t('marketing_page.cols.sent')" prop="sent" width="80" />
            <el-table-column :label="t('marketing_page.cols.opened')" prop="opened" width="80" />
            <el-table-column :label="t('marketing_page.cols.clicked')" prop="clicked" width="80" />
            <el-table-column :label="t('marketing_page.cols.open_rate')">
              <template #default="{ row }">{{ row.sent > 0 ? ((row.opened / row.sent) * 100).toFixed(1) + '%' : '0%' }}</template>
            </el-table-column>
          </el-table>
        </div>

        <div v-if="analyticsData.step_stats && analyticsData.step_stats.length">
          <h4>{{ t('marketing_page.step_stats') }}</h4>
          <el-table :data="analyticsData.step_stats" size="small">
            <el-table-column :label="t('marketing_page.cols.step')" width="60">
              <template #default="{ row }">#{{ row.order }}</template>
            </el-table-column>
            <el-table-column :label="t('marketing_page.cols.action')" width="120">
              <template #default="{ row }">{{ stepActionLabels[row.action] || row.action }}</template>
            </el-table-column>
            <el-table-column :label="t('marketing_page.cols.total')" prop="total" width="80" />
            <el-table-column :label="t('marketing_page.cols.success')" prop="success" width="80" />
            <el-table-column :label="t('marketing_page.cols.success_rate')">
              <template #default="{ row }">{{ row.total > 0 ? ((row.success / row.total) * 100).toFixed(1) + '%' : '-' }}</template>
            </el-table-column>
          </el-table>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Promotion, Plus, Refresh } from '@element-plus/icons-vue'
import api from '../../api/marketingCampaign'

const { t, locale } = useI18n()

const loading = ref(false)
const activeTab = ref('campaigns')

const dashboard = reactive({
    total_campaigns: 0, active_campaigns: 0, draft_campaigns: 0, completed_campaigns: 0,
    total_sent: 0, total_delivered: 0, total_opened: 0, total_clicked: 0, total_converted: 0,
    delivery_rate: 0, open_rate: 0, click_rate: 0,
    channel_stats: [],
    type_distribution: {},
    recent_campaigns: [],
})

const statusLabels = computed(() => ({
    draft: t('marketing_page.status_labels.draft'),
    active: t('marketing_page.status_labels.active'),
    paused: t('marketing_page.status_labels.paused'),
    completed: t('marketing_page.status_labels.completed'),
    cancelled: t('marketing_page.status_labels.cancelled'),
}))
const typeLabels = computed(() => ({
    email: t('marketing_page.type_labels.email'),
    sms: t('marketing_page.type_labels.sms'),
    in_app: t('marketing_page.type_labels.in_app'),
    multi_channel: t('marketing_page.type_labels.multi_channel'),
}))
const stepActionLabels = computed(() => ({
    send_email: t('marketing_page.step_action_labels.send_email'),
    send_sms: t('marketing_page.step_action_labels.send_sms'),
    send_notification: t('marketing_page.step_action_labels.send_notification'),
    wait: t('marketing_page.step_action_labels.wait'),
    condition: t('marketing_page.step_action_labels.condition'),
    segment: t('marketing_page.step_action_labels.segment'),
}))
const channelLabels = computed(() => ({
    email: t('marketing_page.channel_labels.email'),
    sms: t('marketing_page.channel_labels.sms'),
    in_app: t('marketing_page.channel_labels.in_app'),
}))

const channelBreakdownList = computed(() => {
    if (!analyticsData.value?.channel_breakdown) return []
    const list = []
    for (const [channel, statuses] of Object.entries(analyticsData.value.channel_breakdown)) {
        list.push({
            channel,
            sent: statuses.sent || 0,
            delivered: statuses.delivered || 0,
            opened: statuses.opened || 0,
            clicked: statuses.clicked || 0,
        })
    }
    return list
})

function statusTag(s) {
    const map = { draft: 'info', active: 'success', paused: 'warning', completed: '', cancelled: 'danger' }
    return map[s] || 'info'
}

function formatTime(time) {
    if (!time) return '-'
    const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'
    return new Date(time).toLocaleString(loc)
}

function formatDelay(row) {
    if (row.delay_type === 'immediate') return t('marketing_page.delay_immediate')
    if (row.delay_type === 'delay') return t('marketing_page.delay_minutes_fmt', { n: row.delay_minutes })
    return t('marketing_page.delay_scheduled')
}

const campaigns = ref([])
const campaignsLoading = ref(false)
const search = ref('')
const filterStatus = ref('')
const filterType = ref('')

async function loadCampaigns() {
    campaignsLoading.value = true
    try {
        const params = {}
        if (search.value) params.search = search.value
        if (filterStatus.value) params.status = filterStatus.value
        if (filterType.value) params.type = filterType.value
        const res = await api.campaigns(params)
        campaigns.value = res.data?.data || res.data || []
    } catch (e) { /* handled by api layer */ }
    finally { campaignsLoading.value = false }
}

async function loadDashboard() {
    try {
        const res = await api.dashboard()
        const d = res.data || {}
        Object.assign(dashboard, d)
    } catch (e) { /* handled by api layer */ }
}

function refreshAll() {
    loading.value = true
    Promise.all([loadDashboard(), loadCampaigns()])
        .finally(() => { loading.value = false })
}

const showCreateDialog = ref(false)
const createForm = reactive({ name: '', description: '', type: 'email', audience_type: 'all', scheduled_at: null, budget: null, is_ab_test: false, ab_test_split: 70, trigger_events: [] })
const creating = ref(false)

function openCreateDialog() {
    createForm.name = ''; createForm.description = ''; createForm.type = 'email'
    createForm.audience_type = 'all'; createForm.scheduled_at = null; createForm.budget = null
    createForm.is_ab_test = false; createForm.ab_test_split = 70; createForm.trigger_events = []
    showCreateDialog.value = true
}

async function handleCreate() {
    if (!createForm.name) { ElMessage.warning(t('marketing_page.messages.name_required')); return }
    creating.value = true
    try {
        const payload = { ...createForm }
        if (!payload.is_ab_test) { delete payload.ab_test_split }
        if (payload.trigger_events && payload.trigger_events.length > 0) {
            payload.audience_filter = { trigger_events: payload.trigger_events };
            payload.type = 'multi_channel';
        }
        delete payload.trigger_events;
        await api.createCampaign(payload)
        ElMessage.success(t('marketing_page.messages.created'))
        showCreateDialog.value = false
        loadCampaigns()
        loadDashboard()
    } catch (e) { ElMessage.error(t('marketing_page.messages.create_failed', { msg: e.response?.data?.message || e.message })) }
    finally { creating.value = false }
}

const showEditDialog = ref(false)
const editForm = reactive({ id: null, name: '', description: '', scheduled_at: null, budget: null })
const editing = ref(false)

function openEditDialog(row) {
    editForm.id = row.id
    editForm.name = row.name
    editForm.description = row.description || ''
    editForm.scheduled_at = row.scheduled_at || null
    editForm.budget = row.budget || null
    showEditDialog.value = true
}

async function handleEdit() {
    if (!editForm.name) { ElMessage.warning(t('marketing_page.messages.name_required')); return }
    editing.value = true
    try {
        await api.updateCampaign(editForm.id, {
            name: editForm.name,
            description: editForm.description,
            scheduled_at: editForm.scheduled_at,
            budget: editForm.budget,
        })
        ElMessage.success(t('marketing_page.messages.updated'))
        showEditDialog.value = false
        loadCampaigns()
    } catch (e) { ElMessage.error(t('marketing_page.messages.update_failed', { msg: e.response?.data?.message || e.message })) }
    finally { editing.value = false }
}

const showDetailDialog = ref(false)
const detail = ref(null)

async function showDetail(row) {
    try {
        const res = await api.showCampaign(row.id)
        detail.value = res.data || {}
        showDetailDialog.value = true
    } catch (e) { ElMessage.error(t('marketing_page.messages.load_failed')) }
}

async function handleLaunch(row) {
    try {
        await api.launchCampaign(row.id)
        ElMessage.success(t('marketing_page.messages.launched'))
        loadCampaigns()
        loadDashboard()
    } catch (e) { ElMessage.error(t('marketing_page.messages.launch_failed', { msg: e.response?.data?.message || e.message })) }
}

async function handleToggle(row) {
    try {
        await api.toggleCampaign(row.id)
        ElMessage.success(row.status === 'active' ? t('marketing_page.messages.paused') : t('marketing_page.messages.resumed'))
        loadCampaigns()
    } catch (e) { ElMessage.error(t('marketing_page.messages.operation_failed')) }
}

async function handleComplete(row) {
    try {
        await api.completeCampaign(row.id)
        ElMessage.success(t('marketing_page.messages.completed'))
        loadCampaigns()
        loadDashboard()
    } catch (e) { ElMessage.error(t('marketing_page.messages.operation_failed')) }
}

async function handleDelete(row) {
    try {
        await api.deleteCampaign(row.id)
        ElMessage.success(t('marketing_page.messages.deleted'))
        loadCampaigns()
    } catch (e) { ElMessage.error(t('marketing_page.messages.delete_failed')) }
}

const simulating = ref(false)
async function handleSimulate(id) {
    simulating.value = true
    try {
        const res = await api.simulateSend(id)
        const d = res.data || {}
        ElMessage.success(t('marketing_page.messages.simulate_done', { n: d.sent || 0 }))
        loadCampaigns()
    } catch (e) { ElMessage.error(t('marketing_page.messages.simulate_failed')) }
    finally { simulating.value = false }
}

const sending = ref(false)
async function handleSend(id) {
    try {
        await ElMessageBox.confirm(t('marketing_page.messages.send_confirm'), t('marketing_page.messages.send_confirm_title'), {
            confirmButtonText: t('marketing_page.row_actions.send'),
            cancelButtonText: t('actions.cancel'),
            type: 'warning',
        })
    } catch { return }
    sending.value = true
    try {
        const res = await api.sendCampaign(id)
        const d = res.data || {}
        ElMessage.success(t('marketing_page.messages.send_done', { sent: d.sent || 0, failed: d.failed || 0 }))
        loadCampaigns()
    } catch (e) { ElMessage.error(t('marketing_page.messages.send_failed', { msg: e.response?.data?.message || e.message })) }
    finally { sending.value = false }
}

const showStepEditor = ref(false)
const stepEditorCampaignId = ref(null)
const stepEditorSteps = ref([])
const savingSteps = ref(false)

function openStepEditor(campaign) {
    stepEditorCampaignId.value = campaign.id
    stepEditorSteps.value = (campaign.steps || []).map(s => ({
        action_type: s.action_type || 'send_email',
        delay_type: s.delay_type || 'immediate',
        delay_minutes: s.delay_minutes || null,
        config: s.config || { subject: '', content: '' },
        conditions: s.conditions || null,
    }))
    if (stepEditorSteps.value.length === 0) {
        stepEditorSteps.value = [{ action_type: 'send_email', delay_type: 'immediate', delay_minutes: null, config: { subject: '', content: '' }, conditions: null }]
    }
    showStepEditor.value = true
}

function addStep() {
    stepEditorSteps.value.push({ action_type: 'send_email', delay_type: 'immediate', delay_minutes: null, config: { subject: '', content: '' }, conditions: null })
}

async function handleSaveSteps() {
    if (stepEditorSteps.value.length === 0) { ElMessage.warning(t('marketing_page.messages.steps_required')); return }
    savingSteps.value = true
    try {
        await api.updateSteps(stepEditorCampaignId.value, stepEditorSteps.value)
        ElMessage.success(t('marketing_page.messages.steps_saved'))
        showStepEditor.value = false
        if (detail.value && detail.value.id === stepEditorCampaignId.value) {
            const res = await api.showCampaign(stepEditorCampaignId.value)
            detail.value = res.data || {}
        }
    } catch (e) { ElMessage.error(t('marketing_page.messages.save_failed', { msg: e.response?.data?.message || e.message })) }
    finally { savingSteps.value = false }
}

const showAnalyticsDialog = ref(false)
const analyticsData = ref(null)

async function showAnalytics(id) {
    try {
        const res = await api.analytics(id)
        analyticsData.value = res.data || {}
        showAnalyticsDialog.value = true
    } catch (e) { ElMessage.error(t('marketing_page.messages.analytics_load_failed')) }
}

onMounted(() => { refreshAll() })
</script>

<style scoped>
.marketing-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 22px; }
.header-actions { display: flex; align-items: center; }
.mb-4 { margin-bottom: 16px; }

.stat-value { font-size: 24px; font-weight: 700; margin-bottom: 4px; }
.stat-label { font-size: 13px; color: #909399; }
.stat-value.primary { color: #0f172a; }
.stat-value.success { color: #67c23a; }
.stat-value.warning { color: #e6a23c; }
.stat-value.info { color: #909399; }

.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; }

.channel-bar { display: flex; flex-direction: column; gap: 12px; }
.channel-item { display: flex; align-items: center; gap: 16px; }
.channel-label { width: 60px; font-size: 13px; color: #606266; flex-shrink: 0; }

.section-header { display: flex; justify-content: space-between; align-items: center; margin: 16px 0 8px; }
.section-header h4 { margin: 0; }

.step-editor-item { border: 1px solid #e4e7ed; border-radius: 6px; padding: 12px; margin-bottom: 12px; }
.step-editor-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.step-order { font-weight: 600; font-size: 14px; }
.step-form { margin-top: 4px; }
</style>
