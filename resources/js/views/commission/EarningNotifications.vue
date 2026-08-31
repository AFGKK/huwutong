<template>
  <div class="earning-notification-center">
    <div class="page-header">
      <div class="header-left">
        <h2>{{ t('earning_notifications_page.title') }}</h2>
        <span class="header-subtitle">{{ t('earning_notifications_page.subtitle') }}</span>
      </div>
      <div class="header-right">
        <el-button
          v-if="stats.all?.unread > 0"
          type="primary"
          @click="handleMarkAllRead"
        >
          <el-icon><Check /></el-icon>
          {{ t('earning_notifications_page.mark_all', { n: stats.all?.unread }) }}
        </el-button>
        <el-button @click="showPreferences = true">
          <el-icon><Setting /></el-icon>
          {{ t('earning_notifications_page.preferences') }}
        </el-button>
      </div>
    </div>

    <el-row :gutter="12" class="mb-4">
      <el-col :span="4" v-for="s in typeStats" :key="s.type">
        <el-card shadow="hover" class="stat-card" :class="{ active: activeType === s.type }" @click="filterByType(s.type)">
          <div class="stat-info">
            <div class="stat-label">{{ s.label }}</div>
            <div class="stat-value">{{ s.unread }}<span class="stat-total" v-if="s.total">/{{ s.total }}</span></div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never" class="filter-card">
      <el-form :inline="true">
        <el-form-item :label="t('earning_notifications_page.cols.status')">
          <el-select v-model="filters.is_read" clearable :placeholder="t('earning_notifications_page.all')" style="width: 110px" @change="doSearch">
            <el-option :label="t('earning_notifications_page.unread')" :value="false" />
            <el-option :label="t('earning_notifications_page.read')" :value="true" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="doSearch"><el-icon><Search /></el-icon>{{ t('actions.search') }}</el-button>
          <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card shadow="never">
      <div v-if="loading" v-loading="loading" class="loading-placeholder" />

      <div v-else-if="notifications.length === 0" class="empty-state">
        <el-empty :image-size="80" :description="t('earning_notifications_page.empty')" />
      </div>

      <div v-else class="notification-list">
        <div
          v-for="item in notifications"
          :key="item.id"
          class="notification-card"
          :class="{ unread: !item.is_read }"
          @click="handleClick(item)"
        >
          <div class="notif-body">
            <div class="notif-header">
              <span class="notif-type-badge">
                <el-tag :type="typeTag(item.type)" size="small" effect="light">{{ typeLabel(item.type) }}</el-tag>
              </span>
              <span class="notif-time">{{ formatDate(item.created_at) }}</span>
              <span v-if="!item.is_read" class="unread-badge">{{ t('earning_notifications_page.unread') }}</span>
            </div>
            <div class="notif-title">{{ item.title }}</div>
            <div class="notif-content-text">{{ item.content }}</div>
            <div class="notif-amount" v-if="item.payload?.amount">
              {{ t('earning_notifications_page.amount') }}: <strong class="amount-text">¥{{ Number(item.payload.amount).toFixed(2) }}</strong>
            </div>
            <div class="notif-footer">
              <el-button v-if="!item.is_read" text size="small" type="primary" @click.stop="handleMarkRead(item)">{{ t('earning_notifications_page.mark_read') }}</el-button>
              <el-button v-if="item.payload?.action_url" text size="small" type="primary" @click.stop="navigate(item.payload.action_url)">{{ item.payload.action_text || t('actions.view_details') }}</el-button>
            </div>
          </div>
        </div>
      </div>

      <div class="pagination-wrapper" v-if="total > 0">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="perPage"
          :page-sizes="[10, 20, 50]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="loadNotifications"
          @current-change="loadNotifications"
        />
      </div>
    </el-card>

    <el-dialog v-model="showPreferences" :title="t('earning_notifications_page.pref_title')" width="600px">
      <div v-loading="prefsLoading">
        <div v-for="(typeInfo, type) in allTypes" :key="type" class="pref-item">
          <div class="pref-header">
            <span class="pref-label">{{ typeInfo.label }}</span>
            <span class="pref-desc">{{ typeInfo.desc }}</span>
          </div>
          <div class="pref-channels">
            <el-checkbox
              v-for="channel in channels"
              :key="channel.key"
              v-model="prefChannels[type]"
              :label="channel.key"
              :value="channel.key"
            >
              {{ channel.label }}
            </el-checkbox>
          </div>
        </div>
      </div>
      <template #footer>
        <el-button @click="showPreferences = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="savePreferences" :loading="savingPrefs">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Search, Check, Setting } from '@element-plus/icons-vue'
import earningNotificationApi from '@/api/earningNotification'

const { t, locale } = useI18n()

const loading = ref(false)
const notifications = ref([])
const total = ref(0)
const page = ref(1)
const perPage = ref(20)

const activeType = ref(null)
const showPreferences = ref(false)
const prefsLoading = ref(false)
const savingPrefs = ref(false)
const prefChannels = reactive({})

const filters = reactive({
  is_read: '',
})

const stats = ref({
  all: { total: 0, unread: 0 },
})

const allTypes = computed(() => ({
  commission_credited: { label: t('earning_notifications_page.types.commission_credited'), desc: t('earning_notifications_page.type_descs.commission_credited') },
  commission_released: { label: t('earning_notifications_page.types.commission_released'), desc: t('earning_notifications_page.type_descs.commission_released') },
  payout_status: { label: t('earning_notifications_page.types.payout_status'), desc: t('earning_notifications_page.type_descs.payout_status') },
  monthly_report: { label: t('earning_notifications_page.types.monthly_report'), desc: t('earning_notifications_page.type_descs.monthly_report') },
  threshold_reached: { label: t('earning_notifications_page.types.threshold_reached'), desc: t('earning_notifications_page.type_descs.threshold_reached') },
  negative_balance: { label: t('earning_notifications_page.types.negative_balance'), desc: t('earning_notifications_page.type_descs.negative_balance') },
}))

const channels = computed(() => [
  { key: 'database', label: t('earning_notifications_page.channels.database') },
  { key: 'mail', label: t('earning_notifications_page.channels.mail') },
  { key: 'sms', label: t('earning_notifications_page.channels.sms') },
])

const typeStats = computed(() => {
  const result = []
  for (const [type, info] of Object.entries(allTypes.value)) {
    const s = stats.value[type] || { total: 0, unread: 0 }
    result.push({ type, ...info, ...s })
  }
  return result
})

function typeTag(type) {
  const map = {
    commission_credited: 'success',
    commission_released: 'warning',
    payout_status: 'primary',
    monthly_report: 'info',
    threshold_reached: 'danger',
    negative_balance: 'danger',
  }
  return map[type] || 'info'
}

function typeLabel(type) {
  return allTypes.value[type]?.label || type
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const loc = locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
  return new Date(dateStr).toLocaleString(loc, {
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit',
  })
}

function filterByType(type) {
  activeType.value = activeType.value === type ? null : type
  page.value = 1
  loadNotifications()
}

function resetFilters() {
  filters.is_read = ''
  activeType.value = null
  page.value = 1
  loadNotifications()
}

function doSearch() {
  page.value = 1
  loadNotifications()
}

async function loadNotifications() {
  loading.value = true
  try {
    const params = {
      page: page.value,
      per_page: perPage.value,
    }
    if (activeType.value) params.type = activeType.value
    if (filters.is_read !== '') params.is_read = filters.is_read

    const { data: res } = await earningNotificationApi.list(params)
    notifications.value = res.data?.data || []
    total.value = res.data?.meta?.total || 0
  } catch {
    notifications.value = []
  } finally {
    loading.value = false
  }
}

async function loadStats() {
  try {
    const { data: res } = await earningNotificationApi.stats()
    stats.value = res.data || {}
    if (res.data?.meta) {
      stats.value.all = res.data.meta
    }
  } catch { /* ignore */ }
}

async function handleMarkRead(item) {
  try {
    await earningNotificationApi.markRead(item.id)
    item.is_read = true
    ElMessage.success(t('earning_notifications_page.messages.marked_read'))
    loadStats()
  } catch { /* ignore */ }
}

async function handleMarkAllRead() {
  try {
    await earningNotificationApi.markAllRead(activeType.value)
    notifications.value.forEach(n => n.is_read = true)
    ElMessage.success(t('earning_notifications_page.messages.marked_all'))
    loadStats()
  } catch { /* ignore */ }
}

function handleClick(item) {
  if (!item.is_read) {
    handleMarkRead(item)
  }
  if (item.payload?.action_url) {
    navigate(item.payload.action_url)
  }
}

function navigate(url) {
  const router = window.__router
  if (router) {
    router.push(url)
  } else {
    window.location.href = url
  }
}

async function loadPreferences() {
  prefsLoading.value = true
  try {
    const { data: res } = await earningNotificationApi.preferences()
    const prefs = res.data?.types || {}

    for (const type of Object.keys(allTypes.value)) {
      if (!prefChannels[type]) prefChannels[type] = []
    }

    for (const [type, chs] of Object.entries(prefs)) {
      if (allTypes.value[type] && Array.isArray(chs)) {
        prefChannels[type] = [...chs]
      }
    }
  } catch { /* ignore */ }
  finally { prefsLoading.value = false }
}

async function savePreferences() {
  savingPrefs.value = true
  try {
    await earningNotificationApi.updatePreferences({
      types: { ...prefChannels },
    })
    ElMessage.success(t('earning_notifications_page.messages.pref_saved'))
    showPreferences.value = false
  } catch { /* ignore */ }
  finally { savingPrefs.value = false }
}

onMounted(() => {
  loadNotifications()
  loadStats()
  loadPreferences()
})
</script>

<style scoped>
.earning-notification-center { padding: 20px; }

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
  font-size: 13px;
  color: var(--el-text-color-secondary);
  margin-left: 12px;
}
.header-right { display: flex; gap: 8px; }

.mb-4 { margin-bottom: 16px; }

.stat-card { cursor: pointer; transition: all 0.2s; }
.stat-card:hover { transform: translateY(-2px); }
.stat-card.active { border-color: var(--el-color-primary); }
.stat-info { display: flex; align-items: baseline; gap: 4px; }
.stat-label { font-size: 12px; color: var(--el-text-color-secondary); }
.stat-value { font-size: 18px; font-weight: 600; color: var(--el-color-primary); }
.stat-total { font-size: 12px; color: var(--el-text-color-secondary); font-weight: 400; }

.filter-card { margin-bottom: 16px; }

.notification-list { display: flex; flex-direction: column; gap: 8px; }

.notification-card {
  display: flex;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 8px;
  border: 1px solid var(--el-border-color-lighter);
  cursor: pointer;
  transition: all 0.2s;
}
.notification-card:hover { background: var(--el-fill-color-light); }
.notification-card.unread { background: #f1f5f9; border-color: #e2e8f0; }

.notif-body { flex: 1; min-width: 0; }
.notif-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}
.notif-type-badge { flex-shrink: 0; }
.notif-time { font-size: 12px; color: var(--el-text-color-secondary); }
.unread-badge {
  font-size: 11px;
  color: #fff;
  background: var(--el-color-danger);
  padding: 1px 6px;
  border-radius: 8px;
}
.notif-title {
  font-size: 14px;
  font-weight: 500;
  color: var(--el-text-color-primary);
  margin-bottom: 4px;
}
.notif-content-text {
  font-size: 13px;
  color: var(--el-text-color-regular);
  line-height: 1.5;
  white-space: pre-wrap;
}
.notif-amount {
  font-size: 13px;
  color: var(--el-text-color-secondary);
  margin-top: 4px;
}
.amount-text { color: var(--el-color-primary); font-weight: 600; }
.notif-footer {
  display: flex;
  gap: 8px;
  margin-top: 8px;
}

.loading-placeholder { min-height: 200px; }
.empty-state { padding: 40px 0; }

.pagination-wrapper { margin-top: 16px; display: flex; justify-content: center; }

.pref-item {
  padding: 12px 0;
  border-bottom: 1px solid var(--el-border-color-lighter);
}
.pref-header { margin-bottom: 8px; }
.pref-label { font-weight: 500; margin-right: 8px; }
.pref-desc { font-size: 12px; color: var(--el-text-color-secondary); }
.pref-channels { display: flex; gap: 16px; }
</style>
