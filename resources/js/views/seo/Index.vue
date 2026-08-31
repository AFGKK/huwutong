<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../api/seo.js'

const { t, locale } = useI18n()

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

const statusCodeOptions = computed(() => [
    { value: 301, label: t('seo_page.status_code.permanent_301') },
    { value: 302, label: t('seo_page.status_code.temporary_302') },
    { value: 307, label: t('seo_page.status_code.temporary_307') },
])

const activeFilterOptions = computed(() => [
    { label: t('seo_page.filter.all'), value: 'all' },
    { label: t('seo_page.filter.active'), value: 'active' },
    { label: t('seo_page.filter.inactive'), value: 'inactive' },
])

const tabLabels = computed(() => ({
    redirects: t('seo_page.tabs.redirects'),
    sitemap: t('seo_page.tabs.sitemap'),
    guide: t('seo_page.tabs.guide'),
}))

const sitemapSubmitUrl = computed(() => `${window?.location?.origin || 'https://'}/sitemap.xml`)

const guideSteps = computed(() => [
    { timestamp: t('seo_page.guide.step1_title'), type: 'primary', body: t('seo_page.guide.step1_body') },
    { timestamp: t('seo_page.guide.step2_title'), type: 'success', body: t('seo_page.guide.step2_body') },
    { timestamp: t('seo_page.guide.step3_title'), type: 'info', body: t('seo_page.guide.step3_body', { url: sitemapSubmitUrl.value }) },
    { timestamp: t('seo_page.guide.step4_title'), type: 'warning', body: t('seo_page.guide.step4_body') },
    { timestamp: t('seo_page.guide.step5_title'), type: 'danger', body: t('seo_page.guide.step5_body') },
])

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
            ElMessage.success(t('seo_page.redirect_updated'))
        } else {
            await api.storeRedirect(redirectForm.value)
            ElMessage.success(t('seo_page.redirect_created'))
        }
        redirectDialog.value = false
        loadRedirects(redirectPagination.value.current_page)
        loadDashboard()
    } catch (e) { ElMessage.error(t('messages.failed')) }
}

async function deleteRedirect(r) {
    try {
        await ElMessageBox.confirm(
            t('seo_page.delete_confirm', { url: r.source_url }),
            t('actions.confirm'),
            { type: 'warning' }
        )
        await api.destroyRedirect(r.id)
        ElMessage.success(t('seo_page.deleted'))
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

        if (!entries.length) { ElMessage.warning(t('seo_page.bulk_empty')); return }

        const res = await api.bulkImport(entries)
        ElMessage.success(t('seo_page.bulk_imported', { n: res.data.data.imported }))
        bulkDialog.value = false
        bulkText.value = ''
        loadRedirects()
        loadDashboard()
    } catch (e) { ElMessage.error(t('seo_page.import_failed')) }
}

function fmtDate(d) {
    if (!d) return '-'
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'
    return new Date(d).toLocaleString(loc)
}

const handleTabChange = (tab) => {
    if (tab === 'sitemap') loadSitemap()
}

onMounted(() => { loadDashboard(); loadRedirects() })
</script>

<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin' }">{{ t('nav.home') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('seo_page.breadcrumb.marketing') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('seo_page.breadcrumb.title') }}</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 仪表盘统计 -->
        <el-row :gutter="12" class="mb-5" v-if="stats">
            <el-col :span="6"><el-card shadow="never"><div class="stat-label">{{ t('seo_page.stats.metadata') }}</div><div class="stat-value">{{ stats.total_metadata }}</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="never"><div class="stat-label">{{ t('seo_page.stats.redirects') }}</div><div class="stat-value">{{ stats.total_redirects }} <span class="text-sm text-gray-400">({{ t('seo_page.stats.active_count', { n: stats.active_redirects }) }})</span></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="never"><div class="stat-label">{{ t('seo_page.stats.total_hits') }}</div><div class="stat-value text-primary">{{ stats.total_hits }}</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="never"><div class="stat-label">{{ t('seo_page.stats.most_hit') }}</div><div class="stat-value text-sm">{{ stats.most_hit_url || '-' }} <span v-if="stats.most_hit_count">({{ stats.most_hit_count }})</span></div></el-card></el-col>
        </el-row>

        <!-- 建议 -->
        <div v-if="suggestions.length" class="mb-4">
            <el-alert v-for="(s, i) in suggestions" :key="i" :title="s.message" :type="s.type" :closable="false" show-icon class="mb-1" />
        </div>

        <!-- Tabs -->
        <el-card shadow="never">
            <el-tabs v-model="activeTab" @tab-change="handleTabChange">
                <!-- Tab 1: URL 重定向 -->
                <el-tab-pane :label="tabLabels.redirects" name="redirects">
                    <div class="flex justify-between mb-3">
                        <el-form :model="redirectFilters" inline size="small">
                            <el-form-item :label="t('actions.search')"><el-input v-model="redirectFilters.search" :placeholder="t('seo_page.filter.url_ph')" clearable class="w-40" /></el-form-item>
                            <el-form-item :label="t('seo_page.filter.status_code')"><el-select v-model="redirectFilters.status_code" clearable class="w-32"><el-option v-for="o in statusCodeOptions" :key="o.value" :label="o.label" :value="o.value" /></el-select></el-form-item>
                            <el-form-item :label="t('seo_page.filter.status')"><el-select v-model="redirectFilters.is_active" class="w-24"><el-option v-for="o in activeFilterOptions" :key="o.value" :label="o.label" :value="o.value" /></el-select></el-form-item>
                            <el-form-item><el-button @click="loadRedirects()">{{ t('actions.filter') }}</el-button></el-form-item>
                        </el-form>
                        <div>
                            <el-button @click="bulkDialog = true" size="small">{{ t('seo_page.bulk_import') }}</el-button>
                            <el-button type="primary" @click="openCreate">{{ t('seo_page.create_redirect') }}</el-button>
                        </div>
                    </div>

                    <el-table :data="redirects" v-loading="loading" stripe>
                        <el-table-column :label="t('seo_page.col.source_url')" min-width="200" show-overflow-tooltip>
                            <template #default="{ row }"><code class="text-sm">{{ row.source_url }}</code></template>
                        </el-table-column>
                        <el-table-column :label="t('seo_page.col.target_url')" min-width="200" show-overflow-tooltip>
                            <template #default="{ row }"><code class="text-sm">{{ row.target_url }}</code></template>
                        </el-table-column>
                        <el-table-column :label="t('seo_page.col.status_code')" width="80">
                            <template #default="{ row }"><el-tag :type="row.status_code === 301 ? 'warning' : 'info'" size="small">{{ row.status_code }}</el-tag></template>
                        </el-table-column>
                        <el-table-column :label="t('seo_page.col.active')" width="60">
                            <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? t('seo_page.yes') : t('seo_page.no') }}</el-tag></template>
                        </el-table-column>
                        <el-table-column :label="t('seo_page.col.wildcard')" width="60">
                            <template #default="{ row }"><span>{{ row.is_wildcard ? t('seo_page.yes') : t('seo_page.no') }}</span></template>
                        </el-table-column>
                        <el-table-column :label="t('seo_page.col.hits')" width="60"><template #default="{ row }">{{ row.hit_count }}</template></el-table-column>
                        <el-table-column :label="t('seo_page.col.last_hit')" width="140"><template #default="{ row }">{{ fmtDate(row.last_hit_at) }}</template></el-table-column>
                        <el-table-column :label="t('seo_page.col.actions')" width="120" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" text @click="openEdit(row)">{{ t('actions.edit') }}</el-button>
                                <el-button size="small" text type="danger" @click="deleteRedirect(row)">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="flex justify-center mt-3">
                        <el-pagination small v-model:current-page="redirectPagination.current_page" :page-size="20" :total="redirectPagination.total" layout="prev,pager,next,total" @current-change="loadRedirects" />
                    </div>
                </el-tab-pane>

                <!-- Tab 2: 站点地图 -->
                <el-tab-pane :label="tabLabels.sitemap" name="sitemap">
                    <div class="mb-3 text-sm text-gray-500">{{ t('seo_page.sitemap_summary', { n: sitemapEntries.length }) }}</div>
                    <el-table :data="sitemapEntries" v-loading="sitemapLoading" stripe>
                        <el-table-column :label="t('seo_page.col.url')" min-width="200" show-overflow-tooltip>
                            <template #default="{ row }"><code class="text-sm">{{ row.url }}</code></template>
                        </el-table-column>
                        <el-table-column :label="t('seo_page.col.title')" min-width="150" show-overflow-tooltip><template #default="{ row }">{{ row.title || '-' }}</template></el-table-column>
                        <el-table-column :label="t('seo_page.col.priority')" width="80"><template #default="{ row }"><el-tag size="small">{{ row.priority }}</el-tag></template></el-table-column>
                        <el-table-column :label="t('seo_page.col.change_frequency')" width="100"><template #default="{ row }">{{ row.change_frequency }}</template></el-table-column>
                        <el-table-column :label="t('seo_page.col.last_updated')" width="150"><template #default="{ row }">{{ fmtDate(row.updated_at) }}</template></el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- Tab 3: SEO 指南 -->
                <el-tab-pane :label="tabLabels.guide" name="guide">
                    <div class="p-4">
                        <h3 class="text-lg font-bold mb-4">{{ t('seo_page.guide.title') }}</h3>
                        <el-timeline>
                            <el-timeline-item v-for="(step, i) in guideSteps" :key="i" :timestamp="step.timestamp" :type="step.type">
                                {{ step.body }}
                            </el-timeline-item>
                        </el-timeline>
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- 新建/编辑重定向对话框 -->
        <el-dialog v-model="redirectDialog" :title="isEdit ? t('seo_page.dialog.edit_title') : t('seo_page.dialog.create_title')" width="550px">
            <el-form :model="redirectForm" label-width="90px">
                <el-form-item :label="t('seo_page.dialog.source_url')" required>
                    <el-input v-model="redirectForm.source_url" :placeholder="t('seo_page.dialog.source_url_ph')" />
                </el-form-item>
                <el-form-item :label="t('seo_page.dialog.target_url')" required>
                    <el-input v-model="redirectForm.target_url" :placeholder="t('seo_page.dialog.target_url_ph')" />
                </el-form-item>
                <el-form-item :label="t('seo_page.dialog.status_code')">
                    <el-select v-model="redirectForm.status_code" class="w-full">
                        <el-option v-for="o in statusCodeOptions" :key="o.value" :label="o.label" :value="o.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('seo_page.dialog.active')">
                    <el-switch v-model="redirectForm.is_active" />
                </el-form-item>
                <el-form-item :label="t('seo_page.dialog.wildcard')">
                    <el-switch v-model="redirectForm.is_wildcard" />
                    <div class="text-xs text-gray-400 mt-1">{{ t('seo_page.dialog.wildcard_hint') }}</div>
                </el-form-item>
                <el-form-item :label="t('seo_page.dialog.notes')">
                    <el-input v-model="redirectForm.notes" type="textarea" :rows="2" :placeholder="t('seo_page.dialog.notes_ph')" />
                </el-form-item>
            </el-form>
            <template #footer><el-button @click="redirectDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="submitRedirect">{{ t('actions.save') }}</el-button></template>
        </el-dialog>

        <!-- 批量导入对话框 -->
        <el-dialog v-model="bulkDialog" :title="t('seo_page.dialog.bulk_title')" width="500px">
            <p class="text-sm text-gray-500 mb-2">{{ t('seo_page.dialog.bulk_hint') }}</p>
            <el-input v-model="bulkText" type="textarea" :rows="10" :placeholder="t('seo_page.dialog.bulk_ph')" />
            <template #footer><el-button @click="bulkDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="submitBulk">{{ t('actions.import') }}</el-button></template>
        </el-dialog>
    </div>
</template>

<style scoped>
.stat-label { font-size: 12px; color: #909399; }
.stat-value { font-size: 20px; font-weight: 700; }
.text-primary { color: #0f172a; }
.text-sm { font-size: 13px; }
.w-32 { width: 130px; }
.w-40 { width: 165px; }
.w-24 { width: 100px; }
code { background: #f5f7fa; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
</style>
