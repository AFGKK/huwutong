<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../api/seo.js'

const loading = ref(false)
const activeTab = ref('redirects')
const stats = ref(null)
const suggestions = ref([])
const sitemapEntries = ref([])
const sitemapLoading = ref(false)

// ─── 重定向管理 ───
const redirects = ref([])
const redirectPagination = ref({ total: 0, current_page: 1 })
const redirectFilters = ref({ search: '', status_code: '', is_active: 'all' })
const redirectDialog = ref(false)
const isEdit = ref(false)
const redirectForm = ref({ source_url: '', target_url: '', status_code: 301, is_active: true, is_wildcard: false, notes: '' })
const currentRedirect = ref(null)
const bulkDialog = ref(false)
const bulkText = ref('')

const statusCodeOptions = [
    { value: 301, label: '301 永久重定向' },
    { value: 302, label: '302 临时重定向' },
    { value: 307, label: '307 临时重定向' },
]

async function loadDashboard() {
    try {
        const res = await api.dashboard()
        const d = res.data.data
        stats.value = d.stats
        suggestions.value = d.suggestions || []
    } catch (e) {}
}

async function loadRedirects(page = 1) {
    loading.value = true
    try {
        const params = { page, per_page: 20 }
        if (redirectFilters.value.search) params.search = redirectFilters.value.search
        if (redirectFilters.value.status_code) params.status_code = redirectFilters.value.status_code
        if (redirectFilters.value.is_active !== 'all') params.is_active = redirectFilters.value.is_active
        const res = await api.listRedirects(params)
        const d = res.data.data
        redirects.value = d?.data || d || []
        redirectPagination.value = { total: d?.total || 0, current_page: d?.current_page || page }
    } catch (e) {} finally { loading.value = false }
}

async function loadSitemap() {
    sitemapLoading.value = true
    try {
        const res = await api.sitemap()
        sitemapEntries.value = res.data.data.entries || []
    } catch (e) {} finally { sitemapLoading.value = false }
}

function openCreate() {
    isEdit.value = false
    redirectForm.value = { source_url: '', target_url: '', status_code: 301, is_active: true, is_wildcard: false, notes: '' }
    redirectDialog.value = true
}

function openEdit(r) {
    isEdit.value = true
    currentRedirect.value = r
    redirectForm.value = { ...r }
    redirectDialog.value = true
}

async function submitRedirect() {
    try {
        if (isEdit.value) {
            await api.updateRedirect(currentRedirect.value.id, redirectForm.value)
            ElMessage.success('重定向已更新')
        } else {
            await api.storeRedirect(redirectForm.value)
            ElMessage.success('重定向已创建')
        }
        redirectDialog.value = false
        loadRedirects(redirectPagination.value.current_page)
        loadDashboard()
    } catch (e) { ElMessage.error('操作失败') }
}

async function deleteRedirect(r) {
    try {
        await ElMessageBox.confirm(`确定删除重定向 "${r.source_url}" 吗？`, '确认', { type: 'warning' })
        await api.destroyRedirect(r.id)
        ElMessage.success('已删除')
        loadRedirects(redirectPagination.value.current_page)
        loadDashboard()
    } catch (e) {}
}

async function submitBulk() {
    try {
        const lines = bulkText.value.trim().split('\n').filter(l => l.trim())
        const entries = lines.map(line => {
            const parts = line.split(/\s+/)
            return { source: parts[0], target: parts[1] || '' }
        }).filter(e => e.source && e.target)

        if (!entries.length) { ElMessage.warning('请至少输入一条有效规则'); return }

        const res = await api.bulkImport(entries)
        ElMessage.success(`已导入 ${res.data.data.imported} 条`)
        bulkDialog.value = false
        bulkText.value = ''
        loadRedirects()
        loadDashboard()
    } catch (e) { ElMessage.error('导入失败') }
}

function fmtDate(d) { return d ? new Date(d).toLocaleString('zh-CN') : '-' }

const handleTabChange = (tab) => {
    if (tab === 'sitemap') loadSitemap()
}

onMounted(() => { loadDashboard(); loadRedirects() })
</script>

<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>营销</el-breadcrumb-item>
            <el-breadcrumb-item>SEO 优化</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 仪表盘统计 -->
        <el-row :gutter="12" class="mb-5" v-if="stats">
            <el-col :span="6"><el-card shadow="never"><div class="stat-label">SEO元数据条数</div><div class="stat-value">{{ stats.total_metadata }}</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="never"><div class="stat-label">重定向规则</div><div class="stat-value">{{ stats.total_redirects }} <span class="text-sm text-gray-400">(活跃 {{ stats.active_redirects }})</span></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="never"><div class="stat-label">重定向总命中</div><div class="stat-value text-primary">{{ stats.total_hits }}</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="never"><div class="stat-label">最高命中</div><div class="stat-value text-sm">{{ stats.most_hit_url || '-' }} <span v-if="stats.most_hit_count">({{ stats.most_hit_count }})</span></div></el-card></el-col>
        </el-row>

        <!-- 建议 -->
        <div v-if="suggestions.length" class="mb-4">
            <el-alert v-for="(s, i) in suggestions" :key="i" :title="s.message" :type="s.type" :closable="false" show-icon class="mb-1" />
        </div>

        <!-- Tabs -->
        <el-card shadow="never">
            <el-tabs v-model="activeTab" @tab-change="handleTabChange">
                <!-- Tab 1: URL 重定向 -->
                <el-tab-pane label="URL 重定向" name="redirects">
                    <div class="flex justify-between mb-3">
                        <el-form :model="redirectFilters" inline size="small">
                            <el-form-item label="搜索"><el-input v-model="redirectFilters.search" placeholder="URL" clearable class="w-40" /></el-form-item>
                            <el-form-item label="状态码"><el-select v-model="redirectFilters.status_code" clearable class="w-32"><el-option v-for="o in statusCodeOptions" :key="o.value" :label="o.label" :value="o.value" /></el-select></el-form-item>
                            <el-form-item label="状态"><el-select v-model="redirectFilters.is_active" class="w-24"><el-option label="全部" value="all" /><el-option label="活跃" value="active" /><el-option label="停用" value="inactive" /></el-select></el-form-item>
                            <el-form-item><el-button @click="loadRedirects()">筛选</el-button></el-form-item>
                        </el-form>
                        <div>
                            <el-button @click="bulkDialog = true" size="small">批量导入</el-button>
                            <el-button type="primary" @click="openCreate">新建重定向</el-button>
                        </div>
                    </div>

                    <el-table :data="redirects" v-loading="loading" stripe>
                        <el-table-column label="来源URL" min-width="200" show-overflow-tooltip>
                            <template #default="{ row }"><code class="text-sm">{{ row.source_url }}</code></template>
                        </el-table-column>
                        <el-table-column label="目标URL" min-width="200" show-overflow-tooltip>
                            <template #default="{ row }"><code class="text-sm">{{ row.target_url }}</code></template>
                        </el-table-column>
                        <el-table-column label="状态码" width="80">
                            <template #default="{ row }"><el-tag :type="row.status_code === 301 ? 'warning' : 'info'" size="small">{{ row.status_code }}</el-tag></template>
                        </el-table-column>
                        <el-table-column label="活跃" width="60">
                            <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '是' : '否' }}</el-tag></template>
                        </el-table-column>
                        <el-table-column label="通配" width="60">
                            <template #default="{ row }"><span>{{ row.is_wildcard ? '是' : '否' }}</span></template>
                        </el-table-column>
                        <el-table-column label="命中" width="60"><template #default="{ row }">{{ row.hit_count }}</template></el-table-column>
                        <el-table-column label="最近命中" width="140"><template #default="{ row }">{{ fmtDate(row.last_hit_at) }}</template></el-table-column>
                        <el-table-column label="操作" width="120" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" text @click="openEdit(row)">编辑</el-button>
                                <el-button size="small" text type="danger" @click="deleteRedirect(row)">删除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="flex justify-center mt-3">
                        <el-pagination small v-model:current-page="redirectPagination.current_page" :page-size="20" :total="redirectPagination.total" layout="prev,pager,next,total" @current-change="loadRedirects" />
                    </div>
                </el-tab-pane>

                <!-- Tab 2: 站点地图 -->
                <el-tab-pane label="站点地图" name="sitemap">
                    <div class="mb-3 text-sm text-gray-500">共 {{ sitemapEntries.length }} 条可索引内容。站点地图可通过 <code>/sitemap.xml</code> 访问。</div>
                    <el-table :data="sitemapEntries" v-loading="sitemapLoading" stripe>
                        <el-table-column label="URL" min-width="200" show-overflow-tooltip>
                            <template #default="{ row }"><code class="text-sm">{{ row.url }}</code></template>
                        </el-table-column>
                        <el-table-column label="标题" min-width="150" show-overflow-tooltip><template #default="{ row }">{{ row.title || '-' }}</template></el-table-column>
                        <el-table-column label="优先级" width="80"><template #default="{ row }"><el-tag size="small">{{ row.priority }}</el-tag></template></el-table-column>
                        <el-table-column label="更新频率" width="100"><template #default="{ row }">{{ row.change_frequency }}</template></el-table-column>
                        <el-table-column label="最后更新" width="150"><template #default="{ row }">{{ fmtDate(row.updated_at) }}</template></el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- Tab 3: SEO 指南 -->
                <el-tab-pane label="SEO 指南" name="guide">
                    <div class="p-4">
                        <h3 class="text-lg font-bold mb-4">SEO 优化最佳实践</h3>
                        <el-timeline>
                            <el-timeline-item timestamp="1. 配置页面 SEO 元数据" type="primary">
                                为每个公开页面设置 <code>meta_title</code>、<code>meta_description</code> 和 <code>meta_keywords</code>。在页面编辑器中可找到SEO设置。
                            </el-timeline-item>
                            <el-timeline-item timestamp="2. 设置 URL 重定向" type="success">
                                网站结构变更时，使用 301 重定向保持链接权重。支持通配符匹配模式（如 <code>/old-blog/*</code>）。
                            </el-timeline-item>
                            <el-timeline-item timestamp="3. 提交站点地图" type="info">
                                向 Google Search Console 提交 <code>{{ window?.location?.origin || 'https://' }}/sitemap.xml</code>，帮助搜索引擎索引您的内容。
                            </el-timeline-item>
                            <el-timeline-item timestamp="4. 结构化数据标记" type="warning">
                                为关键内容添加 JSON-LD 结构化数据（FAQ、Breadcrumb、Article Schema），提升搜索展示效果。
                            </el-timeline-item>
                            <el-timeline-item timestamp="5. 监控与优化" type="danger">
                                定期检查重定向命中统计，清理无效重定向。确保所有公开页面都有唯一的元数据，避免重复内容。
                            </el-timeline-item>
                        </el-timeline>
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- 新建/编辑重定向对话框 -->
        <el-dialog v-model="redirectDialog" :title="isEdit ? '编辑重定向' : '新建重定向'" width="550px">
            <el-form :model="redirectForm" label-width="90px">
                <el-form-item label="来源URL" required>
                    <el-input v-model="redirectForm.source_url" placeholder="/old-page 或 /old-blog/* 支持通配符" />
                </el-form-item>
                <el-form-item label="目标URL" required>
                    <el-input v-model="redirectForm.target_url" placeholder="/new-page 或 https://example.com" />
                </el-form-item>
                <el-form-item label="状态码">
                    <el-select v-model="redirectForm.status_code" class="w-full">
                        <el-option v-for="o in statusCodeOptions" :key="o.value" :label="o.label" :value="o.value" />
                    </el-select>
                </el-form-item>
                <el-form-item label="活跃">
                    <el-switch v-model="redirectForm.is_active" />
                </el-form-item>
                <el-form-item label="通配符">
                    <el-switch v-model="redirectForm.is_wildcard" />
                    <div class="text-xs text-gray-400 mt-1">启用后来源URL中的 <code>*</code> 作为通配符匹配</div>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="redirectForm.notes" type="textarea" :rows="2" placeholder="可选备注" />
                </el-form-item>
            </el-form>
            <template #footer><el-button @click="redirectDialog = false">取消</el-button><el-button type="primary" @click="submitRedirect">保存</el-button></template>
        </el-dialog>

        <!-- 批量导入对话框 -->
        <el-dialog v-model="bulkDialog" title="批量导入重定向" width="500px">
            <p class="text-sm text-gray-500 mb-2">每行一条规则，格式：<code>来源 目标 [301/302/307]</code>，用空格或Tab分隔。</p>
            <el-input v-model="bulkText" type="textarea" :rows="10" placeholder="/old-page-1 /new-page-1 301&#10;/old-page-2 /new-page-2 301&#10;/old-category/* /new-category/ 302" />
            <template #footer><el-button @click="bulkDialog = false">取消</el-button><el-button type="primary" @click="submitBulk">导入</el-button></template>
        </el-dialog>
    </div>
</template>

<style scoped>
.stat-label { font-size: 12px; color: #909399; }
.stat-value { font-size: 20px; font-weight: 700; }
.text-primary { color: #409eff; }
.text-sm { font-size: 13px; }
.w-32 { width: 130px; }
.w-40 { width: 165px; }
.w-24 { width: 100px; }
code { background: #f5f7fa; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
</style>
