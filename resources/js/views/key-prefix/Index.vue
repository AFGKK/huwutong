<template>
  <div class="key-prefix-management">
    <el-breadcrumb separator="/" class="mb-4">
      <el-breadcrumb-item :to="{ path: '/admin' }">{{ t('nav.home') }}</el-breadcrumb-item>
      <el-breadcrumb-item>{{ t('key_prefix_page.breadcrumb_core') }}</el-breadcrumb-item>
      <el-breadcrumb-item>{{ t('key_prefix_page.breadcrumb_title') }}</el-breadcrumb-item>
    </el-breadcrumb>

    <el-card style="margin-top:0">
      <template #header>
        <div class="card-header">
          <span>{{ t('key_prefix_page.current_format_title') }}</span>
        </div>
      </template>

      <el-descriptions :column="2" border>
        <el-descriptions-item :label="t('key_prefix_page.prefix_definition')" :span="2">
          <template v-for="(item, index) in prefixEditions" :key="item.tag">
            <template v-if="index > 0"> — </template>
            <el-tag :color="item.color" class="text-white" size="small">{{ item.tag }}</el-tag>
            {{ item.label }}
          </template>
        </el-descriptions-item>
        <el-descriptions-item :label="t('key_prefix_page.format_description')" :span="2">
          <code>{{ t('key_prefix_page.format_pattern') }}</code>
          <br />
          <small>{{ t('key_prefix_page.format_example', { example: 'HWT-ENT-A3F2C8D1E9B07456-1A2B' }) }}</small>
        </el-descriptions-item>
        <el-descriptions-item :label="t('key_prefix_page.total_licenses')">
          <el-tag>{{ totalLicenses }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('key_prefix_page.formatted_count')">
          <el-tag type="success">{{ formattedCount }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('key_prefix_page.pending_format')">
          <el-tag type="warning">{{ totalLicenses - formattedCount }}</el-tag>
        </el-descriptions-item>
      </el-descriptions>

      <div style="margin-top:16px">
        <el-button type="primary" @click="handleBatchFormat">
          <el-icon><Refresh /></el-icon>{{ t('key_prefix_page.format_selected') }}
        </el-button>
        <el-button type="warning" @click="handleMigrateAll" :loading="migrating">
          <el-icon><MagicStick /></el-icon>{{ t('key_prefix_page.migrate_all') }}
        </el-button>
      </div>

      <div v-if="migrateStats" class="mt-3 p-3 bg-blue-50 rounded">
        <p><strong>{{ t('key_prefix_page.migrate_result_title') }}:</strong></p>
        <p>{{ t('key_prefix_page.migrate_stats', {
          total: migrateStats.total,
          updated: migrateStats.updated,
          skipped: migrateStats.skipped,
        }) }}</p>
        <p v-if="migrateStats.errors?.length" class="text-danger">
          {{ t('key_prefix_page.migrate_errors', { count: migrateStats.errors.length }) }}
        </p>
      </div>
    </el-card>

    <!-- License 列表预览 -->
    <el-card style="margin-top:20px">
      <template #header>
        <div class="card-header">
          <span>{{ t('key_prefix_page.license_list_title') }}</span>
          <el-input
            v-model="searchKeyword"
            :placeholder="t('key_prefix_page.search_key_ph')"
            clearable
            style="width:200px"
            @keyup.enter="loadLicenses"
          />
        </div>
      </template>

      <el-table :data="licenses" v-loading="loading" stripe @selection-change="selectedLicenses = $event">
        <el-table-column type="selection" width="45" />
        <el-table-column prop="id" :label="t('key_prefix_page.col_id')" width="70" />
        <el-table-column prop="license_key" :label="t('key_prefix_page.col_license_key')" min-width="220">
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
        <el-table-column prop="type" :label="t('key_prefix_page.col_type')" width="120">
          <template #default="{ row }">
            <el-tag effect="plain" size="small">{{ row.type }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" :label="t('key_prefix_page.col_status')" width="90">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'danger'" effect="plain" size="small">
              {{ row.status }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="customer?.name" :label="t('key_prefix_page.col_customer')" min-width="140" />
        <el-table-column :label="t('key_prefix_page.col_actions')" width="100">
          <template #default="{ row }">
            <el-button size="small" text @click="handleFormatSingle(row)">{{ t('key_prefix_page.format_btn') }}</el-button>
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
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { Refresh, MagicStick } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getLicenseKeyFormat, batchLicenseKeyFormat } from '@/api/keyPrefix'
import { getLicenses } from '@/api/license'

const { t } = useI18n()

const totalLicenses = ref(0)
const formattedCount = ref(0)
const licenses = ref([])
const selectedLicenses = ref([])
const loading = ref(false)
const migrating = ref(false)
const page = ref(1)
const total = ref(0)
const searchKeyword = ref('')

const prefixEditions = computed(() => [
  { tag: 'HWT-TRIAL', color: '#909399', label: t('key_prefix_page.edition_trial') },
  { tag: 'HWT-STD', color: '#67C23A', label: t('key_prefix_page.edition_standard') },
  { tag: 'HWT-PRO', color: '#0f172a', label: t('key_prefix_page.edition_professional') },
  { tag: 'HWT-ENT', color: '#E6A23C', label: t('key_prefix_page.edition_enterprise') },
  { tag: 'HWT-DEV', color: '#B37FEB', label: t('key_prefix_page.edition_dev') },
])

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
  if (!key) return t('key_prefix_page.unknown')
  if (key.startsWith('HWT-ENT-')) return t('key_prefix_page.edition_enterprise')
  if (key.startsWith('HWT-PRO-')) return t('key_prefix_page.edition_professional')
  if (key.startsWith('HWT-TRIAL-')) return t('key_prefix_page.edition_trial')
  if (key.startsWith('HWT-STD-')) return t('key_prefix_page.edition_standard')
  if (key.startsWith('HWT-DEV-')) return t('key_prefix_page.edition_dev')
  return t('key_prefix_page.unknown')
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

  // 通过 ENT/PRO 前缀模式统计已格式化数量
  getLicenses({ search: 'ENT-', per_page: 1 }).then(r => {
    const d = r.data ?? r
    formattedCount.value = (d.total ?? 0)
  })
}

function handleFormatSingle(row) {
  getLicenseKeyFormat(row.id).then(res => {
    const info = res.data ?? res
    ElMessageBox.alert(
      t('key_prefix_page.format_info_body', {
        current: row.license_key,
        formatted: info.formatted,
        type: info.label,
      }),
      t('key_prefix_page.format_info_title'),
      { confirmButtonText: t('actions.confirm'), dangerouslyUseHTMLString: true }
    )
  })
}

function handleBatchFormat() {
  if (!selectedLicenses.value.length) {
    ElMessage.warning(t('key_prefix_page.select_license_first'))
    return
  }
  const ids = selectedLicenses.value.map(l => l.id)
  batchLicenseKeyFormat(ids).then(res => {
    ElMessage.success(t('key_prefix_page.batch_processed', { count: res.data?.total ?? 0 }))
    loadLicenses()
    loadStats()
  })
}

function handleMigrateAll() {
  ElMessageBox.confirm(
    t('key_prefix_page.migrate_confirm_body'),
    t('key_prefix_page.migrate_confirm_title'),
    {
      confirmButtonText: t('key_prefix_page.migrate_confirm_btn'),
      cancelButtonText: t('actions.cancel'),
      type: 'warning',
      dangerouslyUseHTMLString: true,
    }
  ).then(() => {
    migrating.value = true
    import('@/api/keyPrefix').then(m => m.migrateLicenseKeyPrefixes())
      .then(() => {
        ElMessage.success(t('key_prefix_page.migrate_success'))
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
