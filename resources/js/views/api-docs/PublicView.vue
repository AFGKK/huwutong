<template>
  <div class="api-docs-public">
    <div class="public-header">
      <h2>{{ version?.name || 'API 文档' }}</h2>
      <p class="text-gray-500 mb-4">版本 {{ version?.version }} · {{ statusLabel(version?.status) }} · {{ totalEndpoints }} 个端点</p>
    </div>

    <!-- 搜索 -->
    <div class="mb-4 flex gap-2">
      <el-input v-model="search" placeholder="搜索端点路径/说明..." clearable style="max-width:400px" @input="onSearch" />
      <el-select v-model="groupFilter" placeholder="分组" clearable @change="filterEndpoints" style="width:160px">
        <el-option v-for="g in groups" :key="g.group" :label="g.group_label" :value="g.group" />
      </el-select>
    </div>

    <div v-loading="loading" v-if="filteredGroups.length > 0">
      <el-card v-for="group in filteredGroups" :key="group.group" class="mb-3">
        <template #header>
          <span class="font-bold">{{ group.group_label }}</span>
          <el-tag size="small" class="ml-2">{{ group.endpoints.length }} 个端点</el-tag>
        </template>
        <div v-for="ep in group.endpoints" :key="ep.id" class="endpoint-row mb-2 p-2 rounded hover:bg-gray-50 cursor-pointer" @click="showEndpoint(ep)">
          <div class="flex items-center gap-3">
            <el-tag :type="methodTag(ep.method)" size="small" effect="dark" style="width:60px;text-align:center">{{ ep.method }}</el-tag>
            <code class="path-text">{{ ep.path }}</code>
            <span class="text-sm text-gray-500 text-ellipsis">{{ ep.summary }}</span>
            <el-tag v-if="ep.status === 'deprecated'" type="danger" size="small">已废弃</el-tag>
            <el-tag v-else-if="ep.status === 'beta'" type="warning" size="small">Beta</el-tag>
          </div>
        </div>
      </el-card>
    </div>
    <el-empty v-else-if="!loading" description="暂无API文档" />

    <!-- 端点详情抽屉 -->
    <el-drawer v-model="drawerVisible" :title="selectedEp?.method + ' ' + selectedEp?.path" size="600px">
      <div v-if="selectedEp">
        <el-descriptions :column="1" border size="small" class="mb-3">
          <el-descriptions-item label="说明">{{ selectedEp.summary || '-' }}</el-descriptions-item>
          <el-descriptions-item label="分组">{{ groups.find(g => g.group === selectedEp.group)?.group_label || selectedEp.group }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="selectedEp.status === 'deprecated' ? 'danger' : 'success'" size="small">{{ selectedEp.status }}</el-tag>
          </el-descriptions-item>
        </el-descriptions>

        <div v-if="selectedEp.description" class="mb-3">
          <h4 class="text-sm font-bold mb-1">详细说明</h4>
          <p class="text-sm">{{ selectedEp.description }}</p>
        </div>

        <div v-if="selectedEp.parameters?.length" class="mb-3">
          <h4 class="text-sm font-bold mb-1">参数</h4>
          <el-table :data="selectedEp.parameters" size="small" stripe>
            <el-table-column label="名称" prop="name" width="120" />
            <el-table-column label="类型" prop="type" width="80" />
            <el-table-column label="必填" width="60">
              <template #default="{ row }">{{ row.required ? '是' : '否' }}</template>
            </el-table-column>
            <el-table-column label="说明" prop="description" min-width="200" />
          </el-table>
        </div>

        <div v-if="selectedEp.example_request" class="mb-3">
          <h4 class="text-sm font-bold mb-1">请求示例</h4>
          <pre class="code-block">{{ formatJson(selectedEp.example_request) }}</pre>
        </div>

        <div v-if="selectedEp.example_response" class="mb-3">
          <h4 class="text-sm font-bold mb-1">响应示例</h4>
          <pre class="code-block">{{ formatJson(selectedEp.example_response) }}</pre>
        </div>

        <div v-if="selectedEp.snippets?.length" class="mb-3">
          <h4 class="text-sm font-bold mb-1">代码示例</h4>
          <div v-for="s in selectedEp.snippets" :key="s.id" class="mb-2">
            <el-tag size="small">{{ s.language }}</el-tag>
            <pre class="code-block mt-1">{{ s.code }}</pre>
          </div>
        </div>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { getPublicApiDocs } from '../../api/apiDocs'

const loading = ref(false)
const search = ref('')
const groupFilter = ref('')
const drawerVisible = ref(false)
const selectedEp = ref(null)
const version = ref(null)
const totalEndpoints = ref(0)
const rawGroups = ref([])

const groups = computed(() => rawGroups.value)

const filteredGroups = computed(() => {
  let list = rawGroups.value
  if (groupFilter.value) {
    list = list.filter(g => g.group === groupFilter.value)
  }
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.map(g => ({
      ...g,
      endpoints: g.endpoints.filter(ep =>
        ep.path.toLowerCase().includes(q) ||
        (ep.summary || '').toLowerCase().includes(q)
      ),
    })).filter(g => g.endpoints.length > 0)
  }
  return list
})

function statusLabel(s) {
  return { active: '活跃', deprecated: '已废弃', beta: 'Beta', experimental: '实验性' }[s] || s
}

function methodTag(m) {
  return { GET: 'success', POST: 'primary', PUT: 'warning', PATCH: '', DELETE: 'danger' }[m] || ''
}

function showEndpoint(ep) {
  selectedEp.value = ep
  drawerVisible.value = true
}

function formatJson(v) {
  if (!v) return ''
  try { return JSON.stringify(typeof v === 'string' ? JSON.parse(v) : v, null, 2) } catch { return String(v) }
}

function onSearch() {}

function filterEndpoints() {}

async function load() {
  loading.value = true
  try {
    const { data } = await getPublicApiDocs()
    version.value = data.version || null
    totalEndpoints.value = data.total_endpoints || 0
    rawGroups.value = data.groups || []
  } catch { } finally { loading.value = false }
}

onMounted(load)
</script>

<style scoped>
.public-header { margin-bottom: 16px; }
.endpoint-row { border-left: 3px solid transparent; transition: all 0.2s; }
.endpoint-row:hover { border-left-color: #409eff; }
.path-text { font-family: 'Courier New', monospace; font-size: 13px; color: #303133; font-weight: 500; min-width: 300px; }
.code-block { background: #f5f7fa; border: 1px solid #e4e7ed; border-radius: 4px; padding: 12px; font-size: 12px; overflow-x: auto; max-height: 300px; white-space: pre-wrap; word-break: break-all; }
</style>
