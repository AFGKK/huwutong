<template>
  <div class="key-prefix-management">
    <el-breadcrumb separator="/" class="mb-4">
      <el-breadcrumb-item :to="{ path: '/admin' }">首页</el-breadcrumb-item>
      <el-breadcrumb-item>授权核心</el-breadcrumb-item>
      <el-breadcrumb-item>License Key 前缀 M3-23</el-breadcrumb-item>
    </el-breadcrumb>

    <el-card style="margin-top:0">
      <template #header>
        <div class="card-header">
          <span>当前前缀格式</span>
        </div>
      </template>

      <el-descriptions :column="2" border>
        <el-descriptions-item label="前缀定义" :span="2">
          <el-tag color="#909399" class="text-white" size="small">HWT-TRIAL</el-tag> 试用版 —
          <el-tag color="#67C23A" class="text-white" size="small">HWT-STD</el-tag> 标准版 —
          <el-tag color="#409EFF" class="text-white" size="small">HWT-PRO</el-tag> 专业版 —
          <el-tag color="#E6A23C" class="text-white" size="small">HWT-ENT</el-tag> 企业版 —
          <el-tag color="#B37FEB" class="text-white" size="small">HWT-DEV</el-tag> 开发版
        </el-descriptions-item>
        <el-descriptions-item label="格式说明" :span="2">
          <code>{PREFIX}-{16位随机Hex}-{4位校验码}</code>
          <br />
          <small>示例：<code>HWT-ENT-A3F2C8D1E9B07456-1A2B</code></small>
        </el-descriptions-item>
        <el-descriptions-item label="License 总数">
          <el-tag>{{ totalLicenses }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="已格式化">
          <el-tag type="success">{{ formattedCount }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="待格式化">
          <el-tag type="warning">{{ totalLicenses - formattedCount }}</el-tag>
        </el-descriptions-item>
      </el-descriptions>

      <div style="margin-top:16px">
        <el-button type="primary" @click="handleBatchFormat">
          <el-icon><Refresh /></el-icon>格式化选中
        </el-button>
        <el-button type="warning" @click="handleMigrateAll" :loading="migrating">
          <el-icon><MagicStick /></el-icon>迁移全部
        </el-button>
      </div>

      <div v-if="migrateStats" class="mt-3 p-3 bg-blue-50 rounded">
        <p><strong>迁移结果：</strong></p>
        <p>总计: {{ migrateStats.total }} | 已更新: <span class="text-success">{{ migrateStats.updated }}</span> | 已跳过: {{ migrateStats.skipped }}</p>
        <p v-if="migrateStats.errors?.length" class="text-danger">错误: {{ migrateStats.errors.length }} 条</p>
      </div>
    </el-card>

    <!-- License 列表预览 -->
    <el-card style="margin-top:20px">
      <template #header>
        <div class="card-header">
          <span>License 列表</span>
          <el-input
            v-model="searchKeyword"
            placeholder="搜索 Key"
            clearable
            style="width:200px"
            @keyup.enter="loadLicenses"
          />
        </div>
      </template>

      <el-table :data="licenses" v-loading="loading" stripe @selection-change="selectedLicenses = $event">
        <el-table-column type="selection" width="45" />
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="license_key" label="License Key" min-width="220">
          <template #default="{ row }">
            <div class="key-cell">
              <code :class="keyClass(row.license_key)">{{ row.license_key }}</code>
              <el-tag
                :type="keyTagType(row.license_key)"
                size="small"
                effect="plain"
                style="margin-left:8px"
              >{{ keyLabel(row.license_key) }}</el-tag>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="type" label="类型" width="120">
          <template #default="{ row }">
            <el-tag effect="plain" size="small">{{ row.type }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'danger'" effect="plain" size="small">
              {{ row.status }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="customer?.name" label="客户" min-width="140" />
        <el-table-column label="操作" width="100">
          <template #default="{ row }">
            <el-button size="small" text @click="handleFormatSingle(row)">格式化</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="page"
          :page-size="20"
          :total="total"
          layout="total, prev, pager, next"
          @current-change="loadLicenses"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Refresh, MagicStick } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getLicenseKeyFormat, batchLicenseKeyFormat } from '@/api/keyPrefix'
import { getLicenses } from '@/api/license'

const totalLicenses = ref(0)
const formattedCount = ref(0)
const licenses = ref([])
const selectedLicenses = ref([])
const loading = ref(false)
const migrating = ref(false)
const page = ref(1)
const total = ref(0)
const searchKeyword = ref('')

function keyClass(key) {
  if (!key) return ''
  if (key.startsWith('HWT-ENT-')) return 'key-enterprise'
  if (key.startsWith('HWT-PRO-')) return 'key-professional'
  if (key.startsWith('HWT-TRIAL-')) return 'key-trial'
  if (key.startsWith('HWT-STD-')) return 'key-standard'
  if (key.startsWith('HWT-DEV-')) return 'key-dev'
  return 'key-unknown'
}

function keyTagType(key) {
  if (!key) return 'info'
  if (key.startsWith('HWT-ENT-')) return 'danger'
  if (key.startsWith('HWT-PRO-')) return 'warning'
  if (key.startsWith('HWT-TRIAL-')) return 'info'
  if (key.startsWith('HWT-STD-')) return 'primary'
  if (key.startsWith('HWT-DEV-')) return ''
  return 'info'
}

function keyLabel(key) {
  if (!key) return '未知'
  if (key.startsWith('HWT-ENT-')) return '企业版'
  if (key.startsWith('HWT-PRO-')) return '专业版'
  if (key.startsWith('HWT-TRIAL-')) return '试用版'
  if (key.startsWith('HWT-STD-')) return '标准版'
  if (key.startsWith('HWT-DEV-')) return '开发版'
  return '未知'
}

function loadLicenses() {
  loading.value = true
  getLicenses({ page: page.value, per_page: 20, search: searchKeyword.value || undefined })
    .then(res => {
      const data = res.data ?? res
      licenses.value = data.data ?? data
      total.value = data.total ?? 0
    })
    .finally(() => { loading.value = false })
}

function loadStats() {
  getLicenses({ per_page: 1 })
    .then(res => {
      const data = res.data ?? res
      totalLicenses.value = data.total ?? 0
    })

  // Count formatted ones by searching with ENT/PRO prefix pattern
  getLicenses({ search: 'ENT-', per_page: 1 }).then(r => {
    const d = r.data ?? r
    formattedCount.value = (d.total ?? 0)
  })
}

function handleFormatSingle(row) {
  getLicenseKeyFormat(row.id).then(res => {
    const info = res.data ?? res
    ElMessageBox.alert(
      `当前 Key: ${row.license_key}<br/>格式化后: <code>${info.formatted}</code><br/>类型: ${info.label}`,
      'Key 格式信息',
      { confirmButtonText: '确定', dangerouslyUseHTMLString: true }
    )
  })
}

function handleBatchFormat() {
  if (!selectedLicenses.value.length) {
    ElMessage.warning('请先选择 License')
    return
  }
  const ids = selectedLicenses.value.map(l => l.id)
  batchLicenseKeyFormat(ids).then(res => {
    ElMessage.success(`已处理 ${res.data?.total ?? 0} 个 License`)
    loadLicenses()
    loadStats()
  })
}

function handleMigrateAll() {
  ElMessageBox.confirm(
    '这将批量迁移所有 License Key 到可读前缀格式（HWT-ENT/HWT-PRO/HWT-TRIAL/HWT-STD/HWT-DEV）。<br/>此操作不可逆！建议先备份数据库。',
    '确认批量迁移',
    { confirmButtonText: '执行迁移', cancelButtonText: '取消', type: 'warning', dangerouslyUseHTMLString: true }
  ).then(() => {
    migrating.value = true
    // Trigger Artisan command via API
    import('@/api/keyPrefix').then(m => m.migrateLicenseKeyPrefixes())
      .then(res => {
        ElMessage.success('所有 License Key 已迁移')
        loadLicenses()
        loadStats()
      })
      .catch(() => {})
      .finally(() => { migrating.value = false })
  }).catch(() => {})
}

onMounted(() => {
  loadStats()
  loadLicenses()
})
</script>

<style scoped>
.key-prefix-management {
  padding: 20px;
}
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.key-cell {
  display: flex;
  align-items: center;
}
.key-enterprise {
  color: #e74c3c;
  font-weight: 600;
}
.key-professional {
  color: #e67e22;
  font-weight: 600;
}
.key-trial {
  color: #3498db;
}
.key-standard {
  color: #2ecc71;
}
.key-dev {
  color: #9b59b6;
}
.key-unknown {
  color: #95a5a6;
}
.pagination-wrap {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}
code {
  background: var(--el-fill-color-light);
  padding: 2px 6px;
  border-radius: 3px;
  font-size: 13px;
}
</style>
