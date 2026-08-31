<template>
  <div>
    <div class="mb-4 flex items-center gap-3">
      <span class="text-sm font-bold">{{ t('version_diff.compare_label') }}</span>
      <el-select v-model="fromVersion" :placeholder="t('version_diff.from_placeholder')" clearable filterable style="width:160px">
        <el-option v-for="v in versions" :key="v.id" :label="`${v.version} - ${v.name || v.status}`" :value="v.id" />
      </el-select>
      <span class="text-gray-400">→</span>
      <el-select v-model="toVersion" :placeholder="t('version_diff.to_placeholder')" filterable style="width:160px">
        <el-option v-for="v in versions" :key="v.id" :label="`${v.version} - ${v.name || v.status}`" :value="v.id" />
      </el-select>
      <el-button type="primary" size="small" :loading="loading" @click="compare">{{ t('version_diff.compare') }}</el-button>
    </div>

    <div v-if="diff">
      <el-alert :title="diffSummary" type="info" show-icon :closable="false" class="mb-3" />

      <el-tabs v-model="diffTab" type="border-card">
        <el-tab-pane :label="t('version_diff.tabs.added', { n: diff.added?.length || 0 })" name="added">
          <el-table :data="diff.added || []" stripe size="small" v-if="diff.added?.length">
            <el-table-column :label="t('version_diff.cols.method')" width="80">
              <template #default="{ row }"><el-tag :type="methodTag(row.method)" size="small" effect="dark" style="width:50px;text-align:center">{{ row.method }}</el-tag></template>
            </el-table-column>
            <el-table-column :label="t('version_diff.cols.path')" prop="path" min-width="300" />
            <el-table-column :label="t('version_diff.cols.summary')" prop="summary" min-width="200" />
            <el-table-column :label="t('version_diff.cols.group')" prop="group" width="100" />
          </el-table>
        </el-tab-pane>

        <el-tab-pane :label="t('version_diff.tabs.removed', { n: diff.removed?.length || 0 })" name="removed">
          <el-table :data="diff.removed || []" stripe size="small" v-if="diff.removed?.length">
            <el-table-column :label="t('version_diff.cols.method')" width="80">
              <template #default="{ row }"><el-tag :type="methodTag(row.method)" size="small" effect="dark" style="width:50px;text-align:center">{{ row.method }}</el-tag></template>
            </el-table-column>
            <el-table-column :label="t('version_diff.cols.path')" prop="path" min-width="300" />
            <el-table-column :label="t('version_diff.cols.summary')" prop="summary" min-width="200" />
          </el-table>
        </el-tab-pane>

        <el-tab-pane :label="t('version_diff.tabs.changed', { n: diff.changed?.length || 0 })" name="changed">
          <div v-for="(item, i) in diff.changed || []" :key="i" class="mb-3 p-3 bg-gray-50 rounded">
            <div class="flex gap-2 mb-2">
              <el-tag :type="methodTag(item.from?.method)" size="small" effect="dark" style="width:50px;text-align:center">{{ item.from?.method }}</el-tag>
              <code class="font-bold">{{ item.from?.path }}</code>
            </div>
            <el-descriptions :column="2" size="small" border>
              <el-descriptions-item :label="t('version_diff.old_summary')">{{ item.from?.summary || '-' }}</el-descriptions-item>
              <el-descriptions-item :label="t('version_diff.new_summary')">{{ item.to?.summary || '-' }}</el-descriptions-item>
              <el-descriptions-item :label="t('version_diff.old_group')">{{ item.from?.group || '-' }}</el-descriptions-item>
              <el-descriptions-item :label="t('version_diff.new_group')">{{ item.to?.group || '-' }}</el-descriptions-item>
              <el-descriptions-item :label="t('version_diff.old_status')">{{ item.from?.status || '-' }}</el-descriptions-item>
              <el-descriptions-item :label="t('version_diff.new_status')">{{ item.to?.status || '-' }}</el-descriptions-item>
            </el-descriptions>
          </div>
          <el-empty v-if="!diff.changed?.length" :description="t('version_diff.no_changes')" />
        </el-tab-pane>

        <el-tab-pane :label="t('version_diff.tabs.unchanged', { n: diff.unchanged?.length || 0 })" name="unchanged">
          <el-table :data="diff.unchanged || []" stripe size="small" v-if="diff.unchanged?.length">
            <el-table-column :label="t('version_diff.cols.method')" width="80">
              <template #default="{ row }"><el-tag :type="methodTag(row.method)" size="small" effect="dark" style="width:50px;text-align:center">{{ row.method }}</el-tag></template>
            </el-table-column>
            <el-table-column :label="t('version_diff.cols.path')" prop="path" min-width="300" />
            <el-table-column :label="t('version_diff.cols.summary')" prop="summary" min-width="200" />
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </div>

    <el-empty v-else-if="!loading" :description="t('version_diff.empty_hint')" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import apiDocsApi from '../../../api/apiDocs'

const { t } = useI18n()

const versions = ref([])
const fromVersion = ref(null)
const toVersion = ref(null)
const diff = ref(null)
const diffTab = ref('added')
const loading = ref(false)

const diffSummary = computed(() => {
  if (!diff.value) return ''
  const added = diff.value.added?.length || 0
  const removed = diff.value.removed?.length || 0
  const changed = diff.value.changed?.length || 0
  const unchanged = diff.value.unchanged?.length || 0
  return t('version_diff.summary', { added, removed, changed, unchanged })
})

function methodTag(m) {
  return { GET: 'success', POST: 'primary', PUT: 'warning', PATCH: '', DELETE: 'danger' }[m] || ''
}

async function compare() {
  if (!toVersion.value) {
    ElMessage.warning(t('version_diff.messages.select_to'))
    return
  }
  loading.value = true
  try {
    const { data } = await apiDocsApi.versionDiff({
      from_version_id: fromVersion.value,
      to_version_id: toVersion.value,
    })
    diff.value = data
  } catch (e) { ElMessage.error(t('version_diff.messages.compare_failed')) } finally { loading.value = false }
}

onMounted(async () => {
  try {
    await apiDocsApi.getEndpoints({ per_page: 1 })
  } catch {}
  const { data } = await (await import('../../../api/apiVersion')).default.index()
  versions.value = Array.isArray(data) ? data : data?.data || []
})
</script>
