<template>
    <div class="log-aggregation-page">
        <div class="page-header">
            <h2>集中式日志平台</h2>
            <p class="text-muted">统一日志搜索、分析、监控面板</p>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card"><div class="stat-value">{{ stats.total_entries }}</div><div class="stat-label">日志总数</div></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" style="border-top:3px solid #f56c6c">
                    <div class="stat-card"><div class="stat-value" style="color:#f56c6c">{{ stats.error_count }}</div><div class="stat-label">错误/严重</div></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card"><div class="stat-value">{{ stats.total_indices }}</div><div class="stat-label">日志索引数</div></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card"><div class="stat-value">{{ stats.avg_duration_ms }}ms</div><div class="stat-label">平均响应时间</div></div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <!-- 搜索 -->
            <el-tab-pane label="日志搜索" name="search">
                <el-card shadow="never" class="mb-4">
                    <el-form :inline="true" :model="filters" size="small" @keyup.enter="doSearch">
                        <el-form-item label="关键词">
                            <el-input v-model="filters.q" placeholder="搜索 message / trace_id / IP" clearable style="width:220px" />
                        </el-form-item>
                        <el-form-item label="级别">
                            <el-select v-model="filters.level" clearable placeholder="全部" style="width:110px">
                                <el-option label="DEBUG" value="debug" />
                                <el-option label="INFO" value="info" />
                                <el-option label="WARNING" value="warning" />
                                <el-option label="ERROR" value="error" />
                                <el-option label="CRITICAL" value="critical" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="方式">
                            <el-select v-model="filters.method" clearable placeholder="全部" style="width:100px">
                                <el-option label="GET" value="GET" />
                                <el-option label="POST" value="POST" />
                                <el-option label="PUT" value="PUT" />
                                <el-option label="DELETE" value="DELETE" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="路径">
                            <el-input v-model="filters.path" placeholder="路径关键词" clearable style="width:150px" />
                        </el-form-item>
                        <el-form-item label="状态码">
                            <el-input v-model="filters.status_code" placeholder="如 500" clearable style="width:100px" type="number" />
                        </el-form-item>
                        <el-form-item label="最小耗时">
                            <el-input v-model="filters.duration_min" placeholder="ms" clearable style="width:100px" type="number" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="doSearch">搜索</el-button>
                            <el-button @click="resetFilters">重置</el-button>
                            <el-button text @click="showSaveDialog = true">保存搜索</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>

                <el-card shadow="never">
                    <el-table :data="entries" v-loading="searchLoading" stripe size="small" @row-click="showDetail">
                        <el-table-column prop="logged_at" label="时间" width="160">
                            <template #default="{ row }">{{ row.logged_at }}</template>
                        </el-table-column>
                        <el-table-column prop="level" label="级别" width="90">
                            <template #default="{ row }">
                                <el-tag :type="levelType(row.level)" size="small" effect="dark">{{ row.level?.toUpperCase() }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="message" label="消息" min-width="300">
                            <template #default="{ row }">
                                <div class="log-message">{{ row.message }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="request_method" label="方式" width="70">
                            <template #default="{ row }">
                                <el-tag size="small" effect="plain">{{ row.request_method }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="request_path" label="路径" width="200">
                            <template #default="{ row }">
                                <span class="text-muted">{{ row.request_path }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="duration_ms" label="耗时" width="80">
                            <template #default="{ row }">{{ row.duration_ms ? `${row.duration_ms}ms` : '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="response_status" label="状态" width="70">
                            <template #default="{ row }">
                                <el-tag :type="row.response_status >= 400 ? 'danger' : 'success'" size="small">{{ row.response_status }}</el-tag>
                            </template>
                        </el-table-column>
                    </el-table>

                    <div class="mt-4 flex-center" v-if="total > perPage">
                        <el-pagination
                            v-model:current-page="page"
                            :page-size="perPage"
                            :total="total"
                            layout="total, prev, pager, next"
                            small
                            background
                            @current-change="loadEntries"
                        />
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- 分析 -->
            <el-tab-pane label="分析看板" name="analytics">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header>级别分布</template>
                            <div v-if="levelStats.length" class="analytics-list">
                                <div v-for="s in levelStats" :key="s.level" class="analytics-item">
                                    <el-tag :type="levelType(s.level)" size="small" effect="dark">{{ s.level?.toUpperCase() }}</el-tag>
                                    <span class="analytics-count">{{ s.count }} 条</span>
                                    <span class="analytics-avg">平均 {{ Math.round(s.avg_duration) }}ms</span>
                                </div>
                            </div>
                            <el-empty v-else description="暂无数据" :image-size="60" />
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header>慢查询 Top</template>
                            <el-table :data="slowQueries" size="small" stripe v-if="slowQueries.length">
                                <el-table-column prop="request_path" label="路径" min-width="200" />
                                <el-table-column prop="duration_ms" label="耗时" width="90">
                                    <template #default="{ row }">
                                        <span class="text-danger">{{ row.duration_ms }}ms</span>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="logged_at" label="时间" width="160" />
                            </el-table>
                            <el-empty v-else description="暂无慢查询" :image-size="60" />
                        </el-card>
                    </el-col>
                </el-row>
                <el-card shadow="never" class="mt-4">
                    <template #header>请求路径统计</template>
                    <el-table :data="pathStats" size="small" stripe v-if="pathStats.length">
                        <el-table-column prop="request_path" label="路径" min-width="250" />
                        <el-table-column prop="hits" label="请求次数" width="100" />
                        <el-table-column prop="avg_duration" label="平均耗时" width="100">
                            <template #default="{ row }">{{ Math.round(row.avg_duration) }}ms</template>
                        </el-table-column>
                        <el-table-column prop="errors" label="错误数" width="80">
                            <template #default="{ row }">
                                <span :class="row.errors > 0 ? 'text-danger' : ''">{{ row.errors }}</span>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else description="暂无数据" :image-size="60" />
                </el-card>
            </el-tab-pane>

            <!-- 保存的搜索 -->
            <el-tab-pane label="保存的搜索" name="saved">
                <el-card shadow="never">
                    <el-table :data="savedSearches" stripe v-loading="savedLoading">
                        <el-table-column prop="name" label="名称" min-width="200" />
                        <el-table-column prop="creator.name" label="创建者" width="120" />
                        <el-table-column label="共享" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.is_shared ? 'success' : 'info'" size="small">{{ row.is_shared ? '共享' : '私有' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="created_at" label="创建时间" width="170" />
                        <el-table-column label="操作" width="120">
                            <template #default="{ row }">
                                <el-button size="small" text @click="applySavedSearch(row)">应用</el-button>
                                <el-popconfirm title="删除？" @confirm="deleteSavedSearch(row.id)">
                                    <template #reference><el-button size="small" text type="danger">删除</el-button></template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!savedLoading && !savedSearches.length" description="暂无保存的搜索" :image-size="60" />
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 日志详情抽屉 -->
        <el-drawer v-model="detailVisible" title="日志详情" size="550px">
            <template v-if="detail">
                <el-descriptions :column="1" border size="small">
                    <el-descriptions-item label="时间">{{ detail.logged_at }}</el-descriptions-item>
                    <el-descriptions-item label="级别">
                        <el-tag :type="levelType(detail.level)" size="small" effect="dark">{{ detail.level?.toUpperCase() }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="消息">{{ detail.message }}</el-descriptions-item>
                    <el-descriptions-item label="Trace ID">{{ detail.trace_id || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="来源">{{ detail.index?.source || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="通道">{{ detail.channel || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="文件">{{ detail.file ? `${detail.file}:${detail.line}` : '-' }}</el-descriptions-item>
                    <el-descriptions-item label="IP">{{ detail.ip || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="请求">{{ detail.request_method }} {{ detail.request_path }}</el-descriptions-item>
                    <el-descriptions-item label="响应状态">{{ detail.response_status }}</el-descriptions-item>
                    <el-descriptions-item label="耗时">{{ detail.duration_ms ? `${detail.duration_ms}ms` : '-' }}</el-descriptions-item>
                </el-descriptions>
                <h4 class="mt-4">上下文数据</h4>
                <pre class="context-json">{{ JSON.stringify(detail.context, null, 2) || '无' }}</pre>
            </template>
        </el-drawer>

        <!-- 保存搜索对话框 -->
        <el-dialog v-model="showSaveDialog" title="保存搜索条件" width="400px">
            <el-form>
                <el-form-item label="名称">
                    <el-input v-model="saveName" placeholder="如：今日 500 错误" />
                </el-form-item>
                <el-form-item>
                    <el-checkbox v-model="saveShared">共享给团队成员</el-checkbox>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showSaveDialog = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="handleSaveSearch">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import logApi from '@/api/logAggregation'

const activeTab = ref('search')
const stats = ref({ total_entries: 0, error_count: 0, total_indices: 0, avg_duration_ms: 0 })
const entries = ref([])
const total = ref(0)
const page = ref(1)
const perPage = ref(50)
const searchLoading = ref(false)
const detailVisible = ref(false)
const detail = ref(null)
const levelStats = ref([])
const slowQueries = ref([])
const pathStats = ref([])
const savedSearches = ref([])
const savedLoading = ref(false)
const showSaveDialog = ref(false)
const saveName = ref('')
const saveShared = ref(false)
const saving = ref(false)

const filters = reactive({
    q: '', level: '', method: '', path: '', status_code: '', duration_min: '',
})

function levelType(level) {
    return { debug: 'info', info: '', warning: 'warning', error: 'danger', critical: 'danger' }[level] || 'info'
}

async function loadStats() {
    try { const res = await logApi.getDashboard(); stats.value = res.data?.data || {} } catch {}
}

async function loadEntries() {
    searchLoading.value = true
    try {
        const params = { page: page.value, per_page: perPage.value, ...filters }
        Object.keys(params).forEach(k => { if (!params[k] && params[k] !== 0) delete params[k] })
        const res = await logApi.search(params)
        const d = res.data?.data || {}
        entries.value = d.data || []
        total.value = d.total || 0
    } catch { ElMessage.error('搜索失败') }
    finally { searchLoading.value = false }
}

function doSearch() { page.value = 1; loadEntries() }

function resetFilters() {
    Object.keys(filters).forEach(k => filters[k] = '')
    doSearch()
}

async function showDetail(row) {
    try {
        const res = await logApi.show(row.id)
        detail.value = res.data?.data || row
    } catch { detail.value = row }
    detailVisible.value = true
}

async function loadAnalytics() {
    try {
        const [levelRes, slowRes, pathRes] = await Promise.all([
            logApi.getLevelStats(),
            logApi.getSlowQueries(),
            logApi.getPathStats(),
        ])
        levelStats.value = levelRes.data?.data || []
        slowQueries.value = slowRes.data?.data || []
        pathStats.value = pathRes.data?.data || []
    } catch {}
}

async function loadSavedSearches() {
    savedLoading.value = true
    try { const res = await logApi.listSavedSearches(); savedSearches.value = res.data?.data || [] }
    catch {} finally { savedLoading.value = false }
}

function applySavedSearch(row) {
    if (row.filters) Object.assign(filters, row.filters)
    activeTab.value = 'search'
    doSearch()
    ElMessage.success(`已应用搜索: ${row.name}`)
}

async function handleSaveSearch() {
    if (!saveName.value.trim()) return
    saving.value = true
    try {
        await logApi.saveSearch({ name: saveName.value, filters: { ...filters }, is_shared: saveShared.value })
        ElMessage.success('已保存')
        showSaveDialog.value = false
        saveName.value = ''
        saveShared.value = false
        loadSavedSearches()
    } catch { ElMessage.error('保存失败') }
    finally { saving.value = false }
}

async function deleteSavedSearch(id) {
    try { await logApi.deleteSavedSearch(id); ElMessage.success('已删除'); loadSavedSearches() }
    catch { ElMessage.error('删除失败') }
}

onMounted(() => {
    loadStats()
    loadEntries()
    loadAnalytics()
    loadSavedSearches()
})
</script>

<style scoped>
.log-aggregation-page { padding: 20px; }
.page-header { margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 22px; font-weight: bold; }
.stat-card .stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-muted { color: #909399; font-size: 12px; }
.text-danger { color: #f56c6c; font-weight: 600; }
.flex-center { display: flex; justify-content: center; }
.log-message { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 400px; }
.analytics-list { display: flex; flex-direction: column; gap: 8px; }
.analytics-item { display: flex; align-items: center; gap: 12px; padding: 8px; background: #f8f9fa; border-radius: 6px; }
.analytics-count { flex: 1; font-weight: 600; }
.analytics-avg { color: #909399; font-size: 12px; }
.context-json { background: #f5f7fa; padding: 12px; border-radius: 6px; font-size: 12px; max-height: 300px; overflow: auto; }
</style>
