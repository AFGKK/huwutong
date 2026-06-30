<template>
  <div class="inventory-page">
    <h2>库存管理</h2>
    <el-alert
      v-if="alerts.length > 0"
      :title="`${alerts.length} 个商品库存不足`"
      type="warning"
      show-icon
      :closable="false"
      class="mb-4"
    />

    <el-card shadow="never">
      <template #header>
        <span>库存快照</span>
      </template>

      <el-table :data="snapshot" v-loading="loading" stripe>
        <el-table-column prop="sku_code" label="SKU 编码" />
        <el-table-column label="商品名称" min-width="160">
          <template #default="{ row }">
            <div>
              <div class="text-sm font-medium">{{ row.product?.name || row.name }}</div>
              <div v-if="row.product?.name && row.name !== row.product?.name" class="text-xs text-gray-400">{{ row.name }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="stock" label="库存" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.stock <= (threshold || 10) ? 'danger' : 'success'" effect="plain">
              {{ row.stock }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="sold_count" label="已售" width="100" align="center" />
        <el-table-column label="操作" width="220" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="openAdjust(row)">调整</el-button>
            <el-button size="small" @click="openLogs(row)">日志</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 库存调整对话框 -->
    <el-dialog v-model="adjustVisible" title="调整库存" width="400px">
      <el-form ref="adjustForm" :model="adjustData" :rules="adjustRules" label-width="100px">
        <el-form-item label="当前库存">{{ adjustTarget?.stock ?? 0 }}</el-form-item>
        <el-form-item label="调整数量" prop="delta">
          <el-input-number v-model="adjustData.delta" :min="-99999" :max="99999" />
          <div class="text-gray text-xs mt-1">正数增加，负数减少</div>
        </el-form-item>
        <el-form-item label="备注" prop="remark">
          <el-input v-model="adjustData.remark" placeholder="选填" maxlength="500" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="adjustVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submitAdjust">确定</el-button>
      </template>
    </el-dialog>

    <!-- 库存日志对话框 -->
    <el-dialog v-model="logVisible" title="库存变更日志" width="700px">
      <el-table :data="logs" v-loading="logLoading" stripe size="small">
        <el-table-column prop="created_at" label="时间" width="160" />
        <el-table-column prop="type" label="类型" width="100">
          <template #default="{ row }">
            <el-tag :type="row.type === 'deduct' ? 'danger' : row.type === 'add' ? 'success' : 'info'" size="small">
              {{ row.type }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="quantity" label="变动数量" width="80" align="center" />
        <el-table-column prop="stock_before" label="前" width="80" align="center" />
        <el-table-column prop="stock_after" label="后" width="80" align="center" />
        <el-table-column prop="remark" label="备注" min-width="120" />
      </el-table>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import api from '@/api/inventory'

const loading = ref(false)
const snapshot = ref([])
const alerts = ref([])
const threshold = ref(10)

// Adjust dialog
const adjustVisible = ref(false)
const adjustTarget = ref(null)
const adjustData = ref({ delta: 0, remark: '' })
const adjustRules = { delta: [{ required: true, type: 'number', message: '请输入调整数量' }] }
const submitting = ref(false)
const adjustForm = ref(null)

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
    ElMessage.success('库存已调整')
    adjustVisible.value = false
    await fetchData()
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '调整失败')
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
