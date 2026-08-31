<template>
  <div class="email-drip-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Message /></el-icon>
        {{ t('email_drip_page.title') }}
      </h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> {{ t('actions.refresh') }}
        </el-button>
      </div>
    </div>

    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_campaigns }}</div>
          <div class="stat-label">{{ t('email_drip_page.stats.campaigns') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.active_campaigns }}</div>
          <div class="stat-label">{{ t('email_drip_page.stats.active') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_sent }}</div>
          <div class="stat-label">{{ t('email_drip_page.stats.sent') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.open_rate }}% / {{ stats.click_rate }}%</div>
          <div class="stat-label">{{ t('email_drip_page.stats.rates') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="hover">
      <template #header>
        <div class="flex justify-between items-center">
          <span>{{ t('email_drip_page.campaigns') }}</span>
          <el-button type="primary" size="small" @click="showCreate">
            <el-icon><Plus /></el-icon> {{ t('email_drip_page.new_campaign') }}
          </el-button>
        </div>
      </template>
      <el-table :data="campaigns" stripe v-loading="campaignsLoading" @row-click="showDetail">
        <el-table-column :label="t('email_drip_page.cols.name')" prop="name" min-width="160" />
        <el-table-column :label="t('email_drip_page.cols.trigger')" width="140">
          <template #default="{ row }">{{ triggerLabel(row.trigger_event) }}</template>
        </el-table-column>
        <el-table-column :label="t('email_drip_page.cols.steps')" prop="sequences_count" width="80" align="center" />
        <el-table-column :label="t('email_drip_page.cols.status')" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : row.status === 'paused' ? 'warning' : 'info'" size="small">
              {{ statusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('email_drip_page.cols.created')" width="160">
          <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
        </el-table-column>
        <el-table-column :label="t('email_drip_page.cols.actions')" width="160" fixed="right">
          <template #default="{ row }">
            <el-button v-if="row.status === 'draft'" size="small" type="success" @click.stop="handleActivate(row)">{{ t('email_drip_page.start') }}</el-button>
            <el-button v-if="row.status === 'active'" size="small" type="warning" @click.stop="handlePause(row)">{{ t('email_drip_page.pause') }}</el-button>
            <el-button size="small" @click.stop="showDetail(row)">{{ t('actions.view_details') }}</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrap" v-if="pagination.total > pagination.per_page">
        <el-pagination
          v-model:current-page="pagination.current_page"
          :page-size="pagination.per_page"
          :total="pagination.total"
          layout="prev, pager, next"
          @current-change="loadCampaigns"
        />
      </div>
    </el-card>

    <el-dialog v-model="createVisible" :title="t('email_drip_page.create_title')" width="500px">
      <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px">
        <el-form-item :label="t('email_drip_page.cols.name')" prop="name">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item :label="t('email_drip_page.cols.trigger')" prop="trigger_event">
          <el-select v-model="form.trigger_event" style="width:100%">
            <el-option v-for="(cfg, key) in triggers" :key="key" :label="cfg.label" :value="key" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('email_drip_page.description')">
          <el-input v-model="form.description" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleCreate" :loading="submitting">{{ t('actions.create') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="detailVisible" :title="detailCampaign?.name" width="700px" top="5vh">
      <template v-if="detailCampaign">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item :label="t('email_drip_page.cols.trigger')">{{ triggerLabel(detailCampaign.trigger_event) }}</el-descriptions-item>
          <el-descriptions-item :label="t('email_drip_page.cols.status')">{{ statusLabel(detailCampaign.status) }}</el-descriptions-item>
          <el-descriptions-item :label="t('email_drip_page.description')" :span="2">{{ detailCampaign.description || '—' }}</el-descriptions-item>
        </el-descriptions>

        <el-divider />
        <div class="flex justify-between items-center mb-2">
          <h4>{{ t('email_drip_page.sequences') }}</h4>
          <el-button size="small" type="primary" @click="showAddSequence">{{ t('email_drip_page.add_step') }}</el-button>
        </div>

        <el-table :data="detailSequences" stripe size="small">
          <el-table-column :label="t('email_drip_page.cols.order')" prop="sort_order" width="60" align="center" />
          <el-table-column :label="t('email_drip_page.cols.name')" prop="name" min-width="120" />
          <el-table-column :label="t('email_drip_page.cols.delay')" prop="delay_days" width="80" align="center" />
          <el-table-column :label="t('email_drip_page.cols.subject')" prop="subject" min-width="180" />
          <el-table-column :label="t('email_drip_page.cols.status')" width="60">
            <template #default="{ row }">
              <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? t('actions.enable') : t('actions.disable') }}</el-tag>
            </template>
          </el-table-column>
        </el-table>

        <el-divider v-if="detailStats.length" />
        <h4 v-if="detailStats.length">{{ t('email_drip_page.step_stats') }}</h4>
        <el-table v-if="detailStats.length" :data="detailStats" stripe size="small">
          <el-table-column :label="t('email_drip_page.cols.step')" prop="name" min-width="120" />
          <el-table-column :label="t('email_drip_page.cols.sent')" prop="sent" width="60" align="center" />
          <el-table-column :label="t('email_drip_page.cols.opened')" prop="opened" width="60" align="center" />
          <el-table-column :label="t('email_drip_page.cols.clicked')" prop="clicked" width="60" align="center" />
          <el-table-column :label="t('email_drip_page.cols.open_rate')" width="80" align="center">
            <template #default="{ row }">{{ row.open_rate }}%</template>
          </el-table-column>
          <el-table-column :label="t('email_drip_page.cols.click_rate')" width="80" align="center">
            <template #default="{ row }">{{ row.click_rate }}%</template>
          </el-table-column>
        </el-table>
      </template>
    </el-dialog>

    <el-dialog v-model="sequenceVisible" :title="t('email_drip_page.add_step_title')" width="600px">
      <el-form :model="seqForm" label-width="100px">
        <el-form-item :label="t('email_drip_page.cols.name')" prop="name">
          <el-input v-model="seqForm.name" />
        </el-form-item>
        <el-form-item :label="t('email_drip_page.cols.delay')">
          <el-input-number v-model="seqForm.delay_days" :min="0" :max="90" style="width:100%" />
        </el-form-item>
        <el-form-item :label="t('email_drip_page.cols.subject')">
          <el-input v-model="seqForm.subject" />
        </el-form-item>
        <el-form-item :label="t('email_drip_page.content')">
          <el-input v-model="seqForm.content" type="textarea" :rows="6" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="sequenceVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleAddSequence" :loading="submitting">{{ t('email_drip_page.add') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Message, Refresh, Plus } from '@element-plus/icons-vue'
import dripApi from '@/api/emailDrip'

const { t, locale } = useI18n()

const loading = ref(false)
const submitting = ref(false)
const campaignsLoading = ref(false)

const stats = ref({})
const campaigns = ref([])
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 })
const triggers = ref({})
const detailCampaign = ref(null)
const detailSequences = ref([])
const detailStats = ref([])

const createVisible = ref(false)
const detailVisible = ref(false)
const sequenceVisible = ref(false)
const formRef = ref(null)
const form = reactive({ name: '', trigger_event: 'trial_registered', description: '' })
const formRules = computed(() => ({
  name: [{ required: true, message: t('email_drip_page.validation.name') }],
  trigger_event: [{ required: true }],
}))
const seqForm = reactive({ name: '', delay_days: 1, subject: '', content: '' })

function statusLabel(status) {
  const key = { draft: 'draft', active: 'active', paused: 'paused', completed: 'completed' }[status]
  return key ? t(`email_drip_page.statuses.${key}`) : status
}

onMounted(() => {
  dripApi.getTriggers().then(r => { triggers.value = r.data || {} }).catch(() => {})
  refreshAll()
})

async function refreshAll() {
  loading.value = true
  try {
    const res = await dripApi.dashboard()
    stats.value = res.data
  } finally { loading.value = false }
  loadCampaigns()
}

async function loadCampaigns() {
  campaignsLoading.value = true
  try {
    const res = await dripApi.listCampaigns({ page: pagination.current_page })
    campaigns.value = res.data.data || []
    Object.assign(pagination, res.data)
  } finally { campaignsLoading.value = false }
}

function triggerLabel(key) { return triggers.value[key]?.label || key }

function showCreate() {
  form.name = ''; form.trigger_event = 'trial_registered'; form.description = ''
  createVisible.value = true
}

async function handleCreate() {
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return
  submitting.value = true
  try {
    await dripApi.createCampaign(form)
    ElMessage.success(t('email_drip_page.messages.created'))
    createVisible.value = false
    loadCampaigns(); refreshAll()
  } finally { submitting.value = false }
}

async function showDetail(row) {
  detailCampaign.value = row
  detailSequences.value = []
  detailStats.value = []
  detailVisible.value = true
  try {
    const res = await dripApi.getCampaign(row.id)
    detailCampaign.value = res.data.campaign
    detailSequences.value = res.data.campaign?.sequences || []
    detailStats.value = res.data.sequence_stats || []
  } catch {}
}

function showAddSequence() {
  seqForm.name = ''; seqForm.delay_days = 1; seqForm.subject = ''; seqForm.content = ''
  sequenceVisible.value = true
}

async function handleAddSequence() {
  submitting.value = true
  try {
    await dripApi.addSequence(detailCampaign.value.id, seqForm)
    ElMessage.success(t('email_drip_page.messages.step_added'))
    sequenceVisible.value = false
    showDetail(detailCampaign.value)
  } finally { submitting.value = false }
}

async function handleActivate(row) {
  await dripApi.activateCampaign(row.id)
  ElMessage.success(t('email_drip_page.messages.started'))
  loadCampaigns(); refreshAll()
}

async function handlePause(row) {
  await dripApi.pauseCampaign(row.id)
  ElMessage.success(t('email_drip_page.messages.paused'))
  loadCampaigns()
}

function formatTime(time) {
  if (!time) return '—'
  const loc = locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
  return new Date(time).toLocaleString(loc, { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}
</script>

<style scoped>
.email-drip-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; cursor: default; }
.stat-value { font-size: 26px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-success { color: #67C23A; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 12px; }
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.mb-2 { margin-bottom: 8px; }
</style>
