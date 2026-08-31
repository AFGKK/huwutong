<template>
  <div class="inventory-page">
    <h2>{{ t('inventory_page.title') }}</h2>
    <el-alert
      v-if="alerts.length > 0"
      :title="t('inventory_page.alert_low_stock', { n: alerts.length })"
      type="warning"
      show-icon
      :closable="false"
      class="mb-4"
    />

    <el-card shadow="never">
      <template #header>
        <span>{{ t('inventory_page.snapshot_title') }}</span>
      </template>

      <el-table :data="snapshot" v-loading="loading" stripe>
        <el-table-column prop="sku_code" :label="t('inventory_page.cols.sku_code')" />
        <el-table-column :label="t('inventory_page.cols.product_name')" min-width="160">
          <template #default="{ row }">
            <div>
              <div class="text-sm font-medium">{{ row.product?.name || row.name }}</div>
              <div v-if="row.product?.name && row.name !== row.product?.name" class="text-xs text-gray-400">{{ row.name }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="stock" :label="t('inventory_page.cols.stock')" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.stock <= (threshold || 10) ? 'danger' : 'success'" effect="plain">
              {{ row.stock }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="sold_count" :label="t('inventory_page.cols.sold')" width="100" align="center" />
        <el-table-column :label="t('inventory_page.cols.actions')" width="220" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="openAdjust(row)">{{ t('inventory_page.btn_adjust') }}</el-button>
            <el-button size="small" @click="openLogs(row)">{{ t('inventory_page.btn_logs') }}</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 库存调整对话框 -->
    <el-dialog v-model="adjustVisible" :title="t('inventory_page.adjust_title')" width="400px">
      <el-form ref="adjustForm" :model="adjustData" :rules="adjustRules" label-width="100px">
        <el-form-item :label="t('inventory_page.current_stock')">{{ adjustTarget?.stock ?? 0 }}</el-form-item>
        <el-form-item :label="t('inventory_page.delta_label')" prop="delta">
          <el-input-number v-model="adjustData.delta" :min="-99999" :max="99999" />
          <div class="text-gray text-xs mt-1">{{ t('inventory_page.delta_hint') }}</div>
        </el-form-item>
        <el-form-item :label="t('inventory_page.remark_label')" prop="remark">
          <el-input v-model="adjustData.remark" :placeholder="t('inventory_page.remark_ph')" maxlength="500" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="adjustVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="submitting" @click="submitAdjust">{{ t('actions.confirm') }}</el-button>
      </template>
    </el-dialog>

    <!-- 库存日志对话框 -->
    <el-dialog v-model="logVisible" :title="t('inventory_page.log_title')" width="700px">
      <el-table :data="logs" v-loading="logLoading" stripe size="small">
        <el-table-column prop="created_at" :label="t('inventory_page.cols.time')" width="160" />
        <el-table-column prop="type" :label="t('inventory_page.cols.type')" width="100">
          <template #default="{ row }">
            <el-tag :type="row.type === 'deduct' ? 'danger' : row.type === 'add' ? 'success' : 'info'" size="small">
              {{ logTypeLabel(row.type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="quantity" :label="t('inventory_page.cols.quantity')" width="80" align="center" />
        <el-table-column prop="stock_before" :label="t('inventory_page.cols.before')" width="80" align="center" />
        <el-table-column prop="stock_after" :label="t('inventory_page.cols.after')" width="80" align="center" />
        <el-table-column prop="remark" :label="t('inventory_page.remark_label')" min-width="120" />
      </el-table>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import api from '@/api/inventory'

const { t } = useI18n()

const loading = ref(false)
const snapshot = ref([])
const alerts = ref([])
const threshold = ref(10)

// Adjust dialog
const adjustVisible = ref(false)
const adjustTarget = ref(null)
const adjustData = ref({ delta: 0, remark: '' })
const submitting = ref(false)
const adjustForm = ref(null)

const adjustRules = computed(() => ({
  delta: [{ required: true, type: 'number', message: t('inventory_page.validation.delta_required') }],
}))

const logTypeLabels = computed(() => ({
  add: t('inventory_page.log_types.add'),
  deduct: t('inventory_page.log_types.deduct'),
  manual: t('inventory_page.log_types.manual'),
}))

function logTypeLabel(type) {
  return logTypeLabels.value[type] || type
}

// Log dialog
const logVisible = ref(false)
const logs = ref([])
const logLoading = ref(false)

async function fetchData() {
  loading.value = true
  try {
    const [snapRes, alertRes] = await Promise.all([
      api.snapshot(),
      api.alerts(threshold.value),
    ])
    snapshot.value = snapRes.data?.data ?? snapRes.data ?? []
    alerts.value = alertRes.data?.data ?? alertRes.data ?? []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function openAdjust(row) {
  adjustTarget.value = row
  adjustData.value = { delta: 0, remark: '' }
  adjustVisible.value = true
}

async function submitAdjust() {
  const valid = await adjustForm.value.validate().catch(() => false)
  if (!valid) return
  submitting.value = true
  try {
    await api.adjust(adjustTarget.value.id, adjustData.value)
    ElMessage.success(t('inventory_page.messages.adjusted'))
    adjustVisible.value = false
    await fetchData()
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('inventory_page.messages.adjust_failed'))
  } finally {
    submitting.value = false
  }
}

async function openLogs(row) {
  logVisible.value = true
  logLoading.value = true
  try {
    const res = await api.logs(row.id)
    logs.value = res.data?.data ?? res.data ?? []
  } catch (e) {
    console.error(e)
  } finally {
    logLoading.value = false
  }
}

onMounted(fetchData)
</script>
