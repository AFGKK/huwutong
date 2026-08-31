<template>
  <div>
    <el-card shadow="never" class="mb-4">
      <template #header>{{ t('stream_export.title') }}</template>
      <el-form :model="form" label-width="100px" size="small">
        <el-form-item :label="t('stream_export.format')">
          <el-radio-group v-model="form.format">
            <el-radio value="csv">CSV</el-radio>
            <el-radio value="json">JSON</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item :label="t('stream_export.log_type')">
          <el-select v-model="form.filters.type" clearable :placeholder="t('stream_export.all_types')" style="width:300px">
            <el-option v-for="(lb, key) in logTypes" :key="key" :label="lb" :value="key" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('stream_export.action_prefix')">
          <el-select v-model="form.filters.action_prefix" clearable :placeholder="t('stream_export.prefix_placeholder')" style="width:300px">
            <el-option v-for="p in prefixes" :key="p" :label="p" :value="p" />
          </el-select>
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="6">
            <el-form-item :label="t('stream_export.date_from')">
              <el-date-picker v-model="form.filters.date_from" type="date" :placeholder="t('stream_export.start')" style="width:100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item :label="t('stream_export.date_to')">
              <el-date-picker v-model="form.filters.date_to" type="date" :placeholder="t('stream_export.end')" style="width:100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item :label="t('stream_export.user_id')">
              <el-input v-model="form.filters.user_id" :placeholder="t('stream_export.user_id')" />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item :label="t('stream_export.tenant_id')">
              <el-input v-model="form.filters.tenant_id" :placeholder="t('stream_export.tenant_id')" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item :label="t('stream_export.search')">
          <el-input v-model="form.filters.search" :placeholder="t('stream_export.search_placeholder')" style="width:300px" />
        </el-form-item>
        <el-form-item :label="t('stream_export.max_rows')">
          <el-input-number v-model="form.max_rows" :min="100" :max="100000" :step="1000" />
          <span class="ml-2 text-gray-400 text-xs">{{ t('stream_export.max_hint') }}</span>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleStreamExport" :loading="exporting">
            <el-icon><Download /></el-icon> {{ t('stream_export.export_now') }}
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Download } from '@element-plus/icons-vue'
import { streamExport } from '../../../api/auditExport'

const { t } = useI18n()
const exporting = ref(false)

const form = reactive({
  format: 'csv',
  max_rows: 50000,
  filters: {},
})

const logTypes = computed(() => ({
  audit: t('stream_export.types.audit'),
  security: t('stream_export.types.security'),
  error: t('stream_export.types.error'),
  system: t('stream_export.types.system'),
}))
const prefixes = ['license.*', 'subscription.*', 'customer.*', 'invoice.*', 'device.*', 'user.*', 'security.*']

async function handleStreamExport() {
  exporting.value = true
  try {
    const response = await streamExport({ ...form })
    const blob = new Blob([response.data], { type: response.headers['content-type'] || 'text/csv' })
    const disposition = response.headers['content-disposition'] || ''
    const match = disposition.match(/filename="?(.+?)"?$/)
    const fileName = match ? match[1] : `audit_export_${Date.now()}.${form.format}`
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = fileName
    a.click()
    window.URL.revokeObjectURL(url)
    ElMessage.success(t('stream_export.messages.success'))
  } catch (e) {
    ElMessage.error(t('stream_export.messages.failed'))
  } finally {
    exporting.value = false
  }
}
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.ml-2 { margin-left: 8px; }
.text-gray-400 { color: #909399; }
.text-xs { font-size: 12px; }
</style>
