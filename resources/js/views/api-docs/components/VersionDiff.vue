<template>
  <div>
    <div class="mb-4 flex items-center gap-3">
      <span class="text-sm font-bold">对比版本：</span>
      <el-select v-model="fromVersion" placeholder="旧版本（可选）" clearable filterable style="width:160px">
        <el-option v-for="v in versions" :key="v.id" :label="`${v.version} - ${v.name || v.status}`" :value="v.id" />
      </el-select>
      <span class="text-gray-400">→</span>
      <el-select v-model="toVersion" placeholder="新版本" filterable style="width:160px">
        <el-option v-for="v in versions" :key="v.id" :label="`${v.version} - ${v.name || v.status}`" :value="v.id" />
      </el-select>
      <el-button type="primary" size="small" :loading="loading" @click="compare">对比</el-button>
    </div>

    <div v-if="diff">
      <el-alert :title="diffSummary" type="info" show-icon :closable="false" class="mb-3" />

      <el-tabs v-model="diffTab" type="border-card">
        <!-- 新增端点 -->
        <el-tab-pane :label="`新增 (${diff.added?.length || 0})`" name="added">
          <el-table :data="diff.added || []" stripe size="small" v-if="diff.added?.length">
            <el-table-column label="方法" width="80">
              <template #default="{ row }"><el-tag :type="methodTag(row.method)" size="small" effect="dark" style="width:50px;text-align:center">{{ row.method }}</el-tag></template>
            </el-table-column>
            <el-table-column label="路径" prop="path" min-width="300" />
            <el-table-column label="说明" prop="summary" min-width="200" />
            <el-table-column label="分组" prop="group" width="100" />
          </el-table>
        </el-tab-pane>

        <!-- 移除端点 -->
        <el-tab-pane :label="`移除 (${diff.removed?.length || 0})`" name="removed">
          <el-table :data="diff.removed || []" stripe size="small" v-if="diff.removed?.length">
            <el-table-column label="方法" width="80">
              <template #default="{ row }"><el-tag :type="methodTag(row.method)" size="small" effect="dark" style="width:50px;text-align:center">{{ row.method }}</el-tag></template>
            </el-table-column>
            <el-table-column label="路径" prop="path" min-width="300" />
            <el-table-column label="说明" prop="summary" min-width="200" />
          </el-table>
        </el-tab-pane>

        <!-- 变更端点 -->
        <el-tab-pane :label="`变更 (${diff.changed?.length || 0})`" name="changed">
          <div v-for="(item, i) in diff.changed || []" :key="i" class="mb-3 p-3 bg-gray-50 rounded">
            <div class="flex gap-2 mb-2">
              <el-tag :type="methodTag(item.from?.method)" size="small" effect="dark" style="width:50px;text-align:center">{{ item.from?.method }}</el-tag>
              <code class="font-bold">{{ item.from?.path }}</code>
            </div>
            <el-descriptions :column="2" size="small" border>
              <el-descriptions-item label="旧说明">{{ item.from?.summary || '-' }}</el-descriptions-item>
              <el-descriptions-item label="新说明">{{ item.to?.summary || '-' }}</el-descriptions-item>
              <el-descriptions-item label="旧分组">{{ item.from?.group || '-' }}</el-descriptions-item>
              <el-descriptions-item label="新分组">{{ item.to?.group || '-' }}</el-descriptions-item>
              <el-descriptions-item label="旧状态">{{ item.from?.status || '-' }}</el-descriptions-item>
              <el-descriptions-item label="新状态">{{ item.to?.status || '-' }}</el-descriptions-item>
            </el-descriptions>
          </div>
          <el-empty v-if="!diff.changed?.length" description="无变更" />
        </el-tab-pane>

        <!-- 未变更 -->
        <el-tab-pane :label="`未变更 (${diff.unchanged?.length || 0})`" name="unchanged">
          <el-table :data="diff.unchanged || []" stripe size="small" v-if="diff.unchanged?.length">
            <el-table-column label="方法" width="80">
              <template #default="{ row }"><el-tag :type="methodTag(row.method)" size="small" effect="dark" style="width:50px;text-align:center">{{ row.method }}</el-tag></template>
            </el-table-column>
            <el-table-column label="路径" prop="path" min-width="300" />
            <el-table-column label="说明" prop="summary" min-width="200" />
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </div>

    <el-empty v-else-if="!loading" description="选择版本后点击「对比」按钮" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import apiDocsApi from '../../../api/apiDocs'

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
  return `新增 ${added} · 移除 ${removed} · 变更 ${changed} · 未变更 ${unchanged}`
})

function methodTag(m) {
  return { GET: 'success', POST: 'primary', PUT: 'warning', PATCH: '', DELETE: 'danger' }[m] || ''
}

async function compare() {
  if (!toVersion.value) {
    ElMessage.warning('请选择新版本')
    return
  }
  loading.value = true
  try {
    const { data } = await apiDocsApi.versionDiff({
      from_version_id: fromVersion.value,
      to_version_id: toVersion.value,
    })
    diff.value = data
  } catch (e) { ElMessage.error('对比失败') } finally { loading.value = false }
}

onMounted(async () => {
  try {
    const { data } = await apiDocsApi.getEndpoints({ per_page: 1 })
  } catch {}
  // load from versions API
  const { data } = await (await import('../../../api/apiVersion')).default.index()
  versions.value = Array.isArray(data) ? data : data?.data || []
})
</script>
