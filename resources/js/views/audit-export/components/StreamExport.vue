<template>
  <div>
    <el-card shadow="never" class="mb-4">
      <template #header>流式导出（直接下载）</template>
      <el-form :model="form" label-width="100px" size="small">
        <el-form-item label="导出格式">
          <el-radio-group v-model="form.format">
            <el-radio value="csv">CSV</el-radio>
            <el-radio value="json">JSON</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="日志类型">
          <el-select v-model="form.filters.type" clearable placeholder="全部类型" style="width:300px">
            <el-option v-for="(lb, key) in logTypes" :key="key" :label="lb" :value="key" />
          </el-select>
        </el-form-item>
        <el-form-item label="操作前缀">
          <el-select v-model="form.filters.action_prefix" clearable placeholder="如 license.*" style="width:300px">
            <el-option v-for="p in prefixes" :key="p" :label="p" :value="p" />
          </el-select>
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="6">
            <el-form-item label="开始日期">
              <el-date-picker v-model="form.filters.date_from" type="date" placeholder="开始" style="width:100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="结束日期">
              <el-date-picker v-model="form.filters.date_to" type="date" placeholder="结束" style="width:100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="用户ID">
              <el-input v-model="form.filters.user_id" placeholder="用户ID" />
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="租户ID">
              <el-input v-model="form.filters.tenant_id" placeholder="租户ID" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="搜索">
          <el-input v-model="form.filters.search" placeholder="全文搜索..." style="width:300px" />
        </el-form-item>
        <el-form-item label="最大行数">
          <el-input-number v-model="form.max_rows" :min="100" :max="100000" :step="1000" />
          <span class="ml-2 text-gray-400 text-xs">最多 100,000 条</span>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleStreamExport" :loading="exporting">
            <el-icon><Download /></el-icon> 立即导出
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { Download } from '@element-plus/icons-vue'
import { streamExport } from '../../../api/auditExport'

const exporting = ref(false)

const form = reactive({
  format: 'csv',
  max_rows: 50000,
  filters: {},
})

const logTypes = { audit: '审计', security: '安全', error: '错误', system: '系统' }
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
    ElMessage.success('导出成功')
  } catch (e) {
    ElMessage.error('导出失败')
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
