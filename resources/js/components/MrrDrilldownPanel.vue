<template>
  <div class="mrr-drilldown">
    <div class="panel-header">
      <h4>
        <el-icon><FolderOpened /></el-icon>
        {{ t('mrr.drilldown_title') }}
        <span v-if="yearMonth" class="text-muted" style="font-weight:400;margin-left:6px;font-size:13px">({{ yearMonth }})</span>
      </h4>
      <div class="panel-actions">
        <el-select v-model="typeFilter" :placeholder="t('mrr.type_filter')" clearable size="small" style="width:150px" @change="loadData">
          <el-option :label="t('mrr.type_all')" value="" />
          <el-option :label="t('mrr.type_new')" value="new_subscription" />
          <el-option :label="t('mrr.type_upgrade')" value="upgrade" />
          <el-option :label="t('mrr.type_downgrade')" value="downgrade" />
          <el-option :label="t('mrr.type_cancel')" value="cancellation" />
          <el-option :label="t('mrr.type_reactivate')" value="reactivation" />
          <el-option :label="t('mrr.type_price')" value="price_change" />
        </el-select>
      </div>
    </div>

    <el-table :data="details" v-loading="loading" stripe size="small" max-height="400">
      <el-table-column :label="t('mrr.col_customer')" min-width="140">
        <template #default="{ row }">
          <div>{{ row.customer?.name || '-' }}</div>
          <div class="text-muted" style="font-size:11px">{{ row.customer?.email || '' }}</div>
        </template>
      </el-table-column>
      <el-table-column :label="t('mrr.col_type')" width="120">
        <template #default="{ row }">
          <el-tag :type="changeTypeColor(row.change_type)" effect="plain" size="small">
            {{ changeTypeLabel(row.change_type) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('mrr.col_plan')" min-width="120">
        <template #default="{ row }">{{ row.plan?.name || row.subscription?.plan || '-' }}</template>
      </el-table-column>
      <el-table-column :label="t('mrr.col_prev_mrr')" width="120" align="right">
        <template #default="{ row }">¥{{ fmt(row.previous_mrr) }}</template>
      </el-table-column>
      <el-table-column :label="t('mrr.col_new_mrr')" width="120" align="right">
        <template #default="{ row }">¥{{ fmt(row.new_mrr) }}</template>
      </el-table-column>
      <el-table-column :label="t('mrr.col_impact')" width="110" align="right">
        <template #default="{ row }">
          <span :class="row.mrr_impact >= 0 ? 'text-success' : 'text-danger'">
            {{ row.mrr_impact >= 0 ? '+' : '' }}¥{{ fmt(row.mrr_impact) }}
          </span>
        </template>
      </el-table-column>
      <el-table-column :label="t('mrr.col_time')" width="150">
        <template #default="{ row }">{{ formatTime(row.occurred_at) }}</template>
      </el-table-column>
      <el-table-column :label="t('mrr.col_reason')" min-width="120">
        <template #default="{ row }">{{ row.reason || '-' }}</template>
      </el-table-column>
    </el-table>

    <div class="pagination-wrap" v-if="total > perPage">
      <el-pagination
        v-model:current-page="page"
        :page-size="perPage"
        :total="total"
        layout="prev, pager, next"
        background
        small
        @current-change="loadData"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { FolderOpened } from '@element-plus/icons-vue'
import { getMrrDrilldown } from '../api/mrr.js'

const { t, locale } = useI18n()

const props = defineProps({
  yearMonth: { type: String, default: '' },
  changeType: { type: String, default: '' },
})

const details = ref([])
const loading = ref(false)
const page = ref(1)
const perPage = ref(20)
const total = ref(0)
const typeFilter = ref(props.changeType || '')

const typeLabels = computed(() => ({
  new_subscription: t('mrr.type_new'),
  upgrade: t('mrr.type_upgrade'),
  downgrade: t('mrr.type_downgrade'),
  cancellation: t('mrr.type_cancel'),
  reactivation: t('mrr.type_reactivate'),
  price_change: t('mrr.type_price'),
}))

async function loadData() {
  loading.value = true
  try {
    const params = {
      year_month: props.yearMonth,
      change_type: typeFilter.value || undefined,
      page: page.value,
      per_page: perPage.value,
    }
    const res = await getMrrDrilldown(params)
    if (res.data.success) {
      const d = res.data.data
      details.value = d.data || []
      total.value = d.total || 0
      page.value = d.current_page || 1
    }
  } catch (e) { /* ignore */ }
  finally { loading.value = false }
}

function numberLocale() {
  return locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
}

function fmt(v) {
  return Number(v || 0).toLocaleString(numberLocale(), { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

function formatTime(date) {
  if (!date) return '-'
  const d = new Date(date)
  const loc = numberLocale()
  return d.toLocaleDateString(loc) + ' ' + d.toLocaleTimeString(loc, { hour: '2-digit', minute: '2-digit' })
}

function changeTypeColor(type) {
  return { new_subscription: 'success', upgrade: 'primary', downgrade: 'warning', cancellation: 'danger', reactivation: 'info', price_change: '' }[type] || ''
}

function changeTypeLabel(type) {
  return typeLabels.value[type] || type
}

watch(() => props.yearMonth, () => { page.value = 1; loadData() })
watch(() => props.changeType, (v) => { typeFilter.value = v || ''; page.value = 1; loadData() })

onMounted(() => { if (props.yearMonth) loadData() })
</script>

<style scoped>
.mrr-drilldown {
  margin-top: 16px;
}
.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}
.panel-header h4 {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 6px;
}
.pagination-wrap {
  margin-top: 12px;
  text-align: center;
}
.text-muted { color: #909399; font-size: 12px; }
.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; }
</style>
