<template>
  <div>
    <el-row :gutter="12" class="mb-4">
      <el-col :span="6">
        <el-select v-model="filterStatus" :placeholder="t('invite_code_list.cols.status')" clearable style="width:100%" @change="fetchCodes">
          <el-option :label="t('invite_code_list.all')" value="" />
          <el-option :label="t('invite_code_list.statuses.active')" value="active" />
          <el-option :label="t('invite_code_list.statuses.exhausted')" value="exhausted" />
          <el-option :label="t('invite_code_list.statuses.expired')" value="expired" />
          <el-option :label="t('invite_code_list.statuses.disabled')" value="disabled" />
        </el-select>
      </el-col>
      <el-col :span="6">
        <el-select v-model="filterChannel" :placeholder="t('invite_code_list.cols.channel')" clearable style="width:100%" @change="fetchCodes">
          <el-option :label="t('invite_code_list.all_channels')" value="" />
          <el-option v-for="ch in channels" :key="ch.id" :label="ch.name" :value="ch.id" />
        </el-select>
      </el-col>
      <el-col :span="6">
        <el-input v-model="searchCode" :placeholder="t('invite_code_list.search_ph')" clearable @clear="fetchCodes" @keyup.enter="fetchCodes" />
      </el-col>
      <el-col :span="6" class="text-right">
        <el-button type="primary" @click="openGenerate">
          <el-icon><Plus /></el-icon> {{ t('invite_code_list.generate') }}
        </el-button>
      </el-col>
    </el-row>

    <el-table :data="codes" v-loading="loading" stripe style="width:100%">
      <el-table-column prop="code" :label="t('invite_code_list.cols.code')" min-width="160">
        <template #default="{ row }">
          <span class="code-text">{{ row.code }}</span>
          <el-button text size="small" :icon="CopyDocument" @click="copyCode(row.code)" />
        </template>
      </el-table-column>
      <el-table-column :label="t('invite_code_list.cols.channel')" width="130">
        <template #default="{ row }">
          <el-tag v-if="row.channel" size="small">{{ row.channel.name }}</el-tag>
          <span v-else class="text-gray-400 text-xs">{{ t('invite_code_list.generic') }}</span>
        </template>
      </el-table-column>
      <el-table-column :label="t('invite_code_list.cols.status')" width="90">
        <template #default="{ row }">
          <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('invite_code_list.cols.usage')" width="120">
        <template #default="{ row }">
          <el-progress :percentage="usagePercent(row)" :stroke-width="14" striped>
            {{ row.used_count }}/{{ row.max_uses || '∞' }}
          </el-progress>
        </template>
      </el-table-column>
      <el-table-column :label="t('invite_code_list.cols.expires')" width="140">
        <template #default="{ row }">
          {{ row.expires_at ? formatDate(row.expires_at) : t('invite_code_list.never_expires') }}
        </template>
      </el-table-column>
      <el-table-column :label="t('invite_code_list.cols.last_used')" width="140">
        <template #default="{ row }">{{ row.last_used_at ? formatDate(row.last_used_at) : '—' }}</template>
      </el-table-column>
      <el-table-column :label="t('invite_code_list.cols.remarks')" min-width="120" prop="remarks" />
      <el-table-column :label="t('invite_code_list.cols.actions')" width="100" fixed="right">
        <template #default="{ row }">
          <el-popconfirm :title="t('invite_code_list.disable_confirm')" @confirm="disableCode(row)">
            <template #reference>
              <el-button size="small" type="danger" link :disabled="row.status !== 'active'">{{ t('actions.disable') }}</el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

    <div class="mt-4 flex justify-end" v-if="total > perPage">
      <el-pagination v-model:page-size="perPage" :total="total" :page-sizes="[20, 50, 100]"
        layout="sizes, prev, pager, next"
        @current-change="page => fetchCodes(page)" @size-change="s => { perPage = s; fetchCodes() }" />
    </div>

    <el-dialog v-model="dialogVisible" :title="t('invite_code_list.generate_title')" width="520px">
      <el-form :model="form" label-width="120px">
        <el-form-item :label="t('invite_code_list.form.count')">
          <el-input-number v-model="form.count" :min="1" :max="500" />
          <span class="form-help">{{ t('invite_code_list.form.count_hint') }}</span>
        </el-form-item>
        <el-form-item :label="t('invite_code_list.form.channel')">
          <el-select v-model="form.channel_id" clearable style="width:100%" :placeholder="t('invite_code_list.form.channel_ph')">
            <el-option v-for="ch in channels" :key="ch.id" :label="ch.name" :value="ch.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('invite_code_list.form.max_uses')">
          <el-input-number v-model="form.max_uses" :min="1" :max="10000" />
          <span class="form-help">{{ t('invite_code_list.form.max_uses_hint') }}</span>
        </el-form-item>
        <el-form-item :label="t('invite_code_list.form.expires')">
          <el-date-picker v-model="form.expires_at" type="datetime" :placeholder="t('invite_code_list.never_expires')" clearable
            value-format="YYYY-MM-DD HH:mm:ss" style="width:100%" />
        </el-form-item>
        <el-form-item :label="t('invite_code_list.cols.remarks')">
          <el-input v-model="form.remarks" type="textarea" :rows="2" maxlength="500" show-word-limit
            :placeholder="t('invite_code_list.form.remarks_ph')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleGenerate" :loading="generating">{{ t('invite_code_list.generate_btn') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Plus, CopyDocument } from '@element-plus/icons-vue'
import { getInviteCodes, generateInviteCodes, disableInviteCode, getChannels } from '../../../api/invite-codes'

const { t, locale } = useI18n()

const codes = ref([])
const channels = ref([])
const loading = ref(false)
const total = ref(0)
const perPage = ref(20)
const filterStatus = ref('')
const filterChannel = ref('')
const searchCode = ref('')
const dialogVisible = ref(false)
const generating = ref(false)
const form = ref({ count: 10, max_uses: 1, expires_at: null, remarks: '', channel_id: null })

async function fetchCodes(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: perPage.value }
    if (filterStatus.value) params.status = filterStatus.value
    if (filterChannel.value) params.channel_id = filterChannel.value
    if (searchCode.value) params.search = searchCode.value
    const res = await getInviteCodes(params)
    codes.value = res.data?.data?.data || res.data?.data || []
    total.value = res.data?.data?.total || 0
  } catch (e) {
    ElMessage.error(t('invite_code_list.messages.load_failed'))
  } finally {
    loading.value = false
  }
}

async function loadChannels() {
  try {
    const { data } = await getChannels()
    channels.value = data?.data?.data || []
  } catch (e) { /* ignore */ }
}

function openGenerate() {
  form.value = { count: 10, max_uses: 1, expires_at: null, remarks: '', channel_id: null }
  dialogVisible.value = true
}

async function handleGenerate() {
  generating.value = true
  try {
    await generateInviteCodes(form.value.count, {
      max_uses: form.value.max_uses,
      expires_at: form.value.expires_at || undefined,
      remarks: form.value.remarks,
      channel_id: form.value.channel_id || undefined,
    })
    ElMessage.success(t('invite_code_list.messages.generated', { n: form.value.count }))
    dialogVisible.value = false
    fetchCodes()
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('invite_code_list.messages.generate_failed'))
  } finally {
    generating.value = false
  }
}

async function disableCode(row) {
  try {
    await disableInviteCode(row.id)
    ElMessage.success(t('invite_code_list.messages.disabled'))
    row.status = 'disabled'
  } catch (e) {
    ElMessage.error(t('messages.failed'))
  }
}

function copyCode(code) {
  navigator.clipboard.writeText(code).then(() => ElMessage.success(t('invite_code_list.messages.copied')))
    .catch(() => ElMessage.warning(t('invite_code_list.messages.copy_failed')))
}

function statusType(status) {
  return { active: 'success', exhausted: 'warning', expired: 'info', disabled: 'danger' }[status] || 'info'
}
function statusLabel(status) {
  const key = { active: 'active', exhausted: 'exhausted', expired: 'expired', disabled: 'disabled' }[status]
  return key ? t(`invite_code_list.statuses.${key}`) : status
}
function usagePercent(row) {
  if (!row.max_uses) return 0
  return Math.min(100, Math.round((row.used_count / row.max_uses) * 100))
}
function formatDate(d) {
  if (!d) return '-'
  const loc = locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
  return new Date(d).toLocaleString(loc, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

onMounted(() => { fetchCodes(); loadChannels() })
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.text-right { text-align: right; }
.text-gray-400 { color: #909399; }
.text-xs { font-size: 12px; }
.code-text { font-family: 'Consolas', monospace; font-size: 14px; font-weight: 600; letter-spacing: 1px; }
.form-help { color: #999; font-size: 12px; margin-left: 8px; }
.flex { display: flex; }
.justify-end { justify-content: flex-end; }
:deep(.el-progress__text) { font-size: 12px !important; }
</style>
