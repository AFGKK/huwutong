<template>
    <div class="meilisearch-page">
        <div class="page-header">
            <div>
                <h2>{{ t('meilisearch_page.title') }}</h2>
                <p class="text-muted">{{ t('meilisearch_page.subtitle') }}</p>
            </div>
            <div class="header-actions">
                <el-tag :type="healthTag" size="large">
                    {{ healthLabel }}
                </el-tag>
            </div>
        </div>

        <el-alert
            v-if="healthInfo && healthInfo.status !== 'available'"
            type="warning"
            show-icon
            :closable="false"
            class="mb-4"
            :title="healthInfo.message || t('meilisearch_page.alert.disconnected_title')"
        >
            <template #default>
                <p>{{ healthInfo.hint || t('meilisearch_page.alert.disconnected_hint') }}</p>
                <ul v-if="healthInfo.start_commands" class="setup-hints">
                    <li><strong>Windows:</strong> <code>{{ healthInfo.start_commands.windows }}</code></li>
                    <li><strong>Docker:</strong> <code>{{ healthInfo.start_commands.docker }}</code></li>
                </ul>
                <p v-if="healthInfo.rebuild_command">
                    {{ t('meilisearch_page.alert.rebuild_label') }}<code>{{ healthInfo.rebuild_command }}</code>
                </p>
            </template>
        </el-alert>

        <el-alert
            v-else-if="healthInfo && healthInfo.status === 'available' && autoSync.observer_enabled"
            type="success"
            show-icon
            :closable="false"
            class="mb-4"
            :title="t('meilisearch_page.alert.auto_sync_title')"
        >
            <template #default>
                <p>{{ t('meilisearch_page.alert.auto_sync_hint') }}</p>
                <p v-if="autoSync.queue" class="mt-1">{{ t('meilisearch_page.alert.queue_hint', { queue: autoSync.queue_name || 'default' }) }}</p>
            </template>
        </el-alert>

        <el-alert
            v-else-if="healthInfo && healthInfo.status === 'available' && !autoSync.observer_enabled"
            type="info"
            show-icon
            :closable="false"
            class="mb-4"
            :title="t('meilisearch_page.alert.auto_sync_off_title')"
        >
            <template #default>
                <p>{{ t('meilisearch_page.alert.auto_sync_off_hint') }}</p>
            </template>
        </el-alert>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-value primary">{{ stats.products?.in_db || 0 }}</div>
                    <div class="stat-label">{{ t('meilisearch_page.stats.products_db') }}</div>
                    <div class="stat-sub" v-if="stats.products">{{ t('meilisearch_page.stats.indexed', { n: stats.products.in_meili || 0 }) }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-value success">{{ stats.kb_articles?.in_db || 0 }}</div>
                    <div class="stat-label">{{ t('meilisearch_page.stats.kb_db') }}</div>
                    <div class="stat-sub" v-if="stats.kb_articles">{{ t('meilisearch_page.stats.indexed', { n: stats.kb_articles.in_meili || 0 }) }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-value warning">{{ stats.marketplace_apps?.in_db || 0 }}</div>
                    <div class="stat-label">{{ t('meilisearch_page.stats.marketplace_db') }}</div>
                    <div class="stat-sub" v-if="stats.marketplace_apps">{{ t('meilisearch_page.stats.indexed', { n: stats.marketplace_apps.in_meili || 0 }) }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-value">{{ stats.forum_posts?.in_db || 0 }} / {{ stats.blog_posts?.in_db || 0 }} / {{ stats.oa_articles?.in_db || 0 }}</div>
                    <div class="stat-label">{{ t('meilisearch_page.stats.community_blog_oa_db') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never">
            <el-tabs v-model="activeTab">
                <!-- 索引管理 -->
                <el-tab-pane :label="t('meilisearch_page.tabs.indexes')" name="indexes">
                    <div class="toolbar">
                        <el-button type="primary" @click="setupIndex('products')" :loading="setupLoading" size="small">{{ t('meilisearch_page.btn.setup_products') }}</el-button>
                        <el-button type="primary" @click="setupIndex('kb_articles')" :loading="setupLoading" size="small">{{ t('meilisearch_page.btn.setup_kb') }}</el-button>
                        <el-button type="primary" @click="setupIndex('marketplace_apps')" :loading="setupLoading" size="small">{{ t('meilisearch_page.btn.setup_marketplace') }}</el-button>
                        <el-tooltip :content="t('meilisearch_page.btn.setup_other_tip')" placement="top">
                            <el-button type="primary" @click="setupIndex('forum_posts')" :loading="setupLoading" size="small">{{ t('meilisearch_page.btn.setup_other') }}</el-button>
                        </el-tooltip>
                        <el-button @click="handleSync('all')" :loading="syncLoading" type="success" size="small">{{ t('meilisearch_page.btn.sync_all') }}</el-button>
                        <el-button @click="handleSync('products')" :loading="syncLoading" size="small">{{ t('meilisearch_page.btn.sync_products') }}</el-button>
                        <el-button @click="handleSync('kb_articles')" :loading="syncLoading" size="small">{{ t('meilisearch_page.btn.sync_kb') }}</el-button>
                        <el-button @click="handleSync('marketplace_apps')" :loading="syncLoading" size="small">{{ t('meilisearch_page.btn.sync_marketplace') }}</el-button>
                        <el-button @click="handleSync('forum_posts')" :loading="syncLoading" size="small">{{ t('meilisearch_page.btn.sync_forum') }}</el-button>
                        <el-button @click="handleSync('blog_posts')" :loading="syncLoading" size="small">{{ t('meilisearch_page.btn.sync_blog') }}</el-button>
                        <el-button @click="handleSync('oa_articles')" :loading="syncLoading" size="small">{{ t('meilisearch_page.btn.sync_oa') }}</el-button>
                        <el-button @click="handleSync('users')" :loading="syncLoading" size="small">{{ t('meilisearch_page.btn.sync_users') }}</el-button>
                        <el-button @click="handleRebuild" :loading="rebuildLoading" type="warning" size="small">{{ t('meilisearch_page.btn.rebuild_all') }}</el-button>
                        <el-button @click="refreshHealth" :icon="Refresh" size="small">{{ t('meilisearch_page.refresh') }}</el-button>
                    </div>

                    <el-table :data="indexList" v-loading="loading" stripe>
                        <el-table-column :label="t('meilisearch_page.cols.uid')" prop="uid" min-width="200" />
                        <el-table-column :label="t('meilisearch_page.cols.primary_key')" prop="primary_key" width="100" />
                        <el-table-column :label="t('meilisearch_page.cols.doc_count')" width="100" prop="number_of_documents" align="center" />
                        <el-table-column :label="t('meilisearch_page.cols.searchable')" min-width="200">
                            <template #default="{ row }">
                                <el-tag v-for="attr in (row.searchable || [])" :key="attr" size="small" style="margin:1px">{{ attr }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('meilisearch_page.cols.filterable')" min-width="180">
                            <template #default="{ row }">
                                <el-tag v-for="attr in (row.filterable || [])" :key="attr" size="small" type="success" style="margin:1px">{{ attr }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('meilisearch_page.cols.actions')" width="120">
                            <template #default="{ row }">
                                <el-popconfirm :title="t('meilisearch_page.confirm_clear')" @confirm="handleClear(row.uid)">
                                    <template #reference><el-button size="small" type="danger">{{ t('meilisearch_page.clear') }}</el-button></template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!indexList.length && !loading" :description="t('meilisearch_page.empty_indexes')" :image-size="50" />
                </el-tab-pane>

                <!-- 搜索测试 -->
                <el-tab-pane :label="t('meilisearch_page.tabs.search')" name="search">
                    <el-form :inline="true" @submit.prevent="doSearch">
                        <el-form-item>
                            <el-select v-model="searchIndex" style="width:150px">
                                <el-option
                                    v-for="opt in searchIndexOptions"
                                    :key="opt.value"
                                    :label="opt.label"
                                    :value="opt.value"
                                />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-input v-model="searchQuery" :placeholder="t('meilisearch_page.search_ph')" style="width:300px" clearable @clear="searchResults=null" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" native-type="submit" :loading="searchLoading">{{ t('actions.search') }}</el-button>
                        </el-form-item>
                    </el-form>

                    <div v-if="searchResults">
                        <div class="search-meta">
                            {{ t('meilisearch_page.results_meta', { total: searchResults.total, ms: searchResults.processing_time_ms }) }}
                        </div>
                        <el-table :data="searchResults.hits" stripe>
                            <el-table-column label="#" width="50" type="index" />
                            <el-table-column :label="t('meilisearch_page.cols.title')" min-width="250">
                                <template #default="{ row }">
                                    <div v-html="row._formatted?.title || row.title"></div>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('meilisearch_page.cols.excerpt')" min-width="350">
                                <template #default="{ row }">
                                    <div v-html="(row._formatted?.content || row.content || '').substring(0, 200)" class="text-muted"></div>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('meilisearch_page.cols.score')" width="80" align="center">
                                <template #default="{ row }">{{ row._rankingScore ? row._rankingScore.toFixed(2) : '-' }}</template>
                            </el-table-column>
                        </el-table>
                        <el-empty v-if="!searchResults.hits?.length" :description="t('search_center_page.quick.no_results')" :image-size="40" />
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';
import api from '@/api/meilisearch';

const { t } = useI18n();

const loading = ref(false);
const activeTab = ref('indexes');
const healthInfo = ref(null);
const indexList = ref([]);
const autoSync = reactive({
    observer_enabled: true,
    queue: false,
    queue_name: 'default',
    scheduled: true,
    mode: 'incremental',
});
const setupLoading = ref(false);
const syncLoading = ref(false);
const rebuildLoading = ref(false);
const searchQuery = ref('');
const searchIndex = ref('products');
const searchLoading = ref(false);
const searchResults = ref(null);
const versionInfo = ref(null);
const stats = reactive({ products: null, kb_articles: null });

const searchIndexKeys = ['products', 'kb_articles', 'marketplace_apps', 'forum_posts', 'blog_posts', 'oa_articles', 'users'];

const searchIndexOptions = computed(() => searchIndexKeys.map((value) => ({
    value,
    label: t(`meilisearch_page.indexes.${value}`),
})));

const healthTag = computed(() => {
    if (!healthInfo.value) return 'info';
    return healthInfo.value.status === 'available' ? 'success' : 'danger';
});
const healthLabel = computed(() => {
    if (!healthInfo.value) return t('meilisearch_page.health.checking');
    const s = healthInfo.value.status;
    if (s === 'available') return t('meilisearch_page.health.connected', { version: healthInfo.value.version?.pkgVersion || '' });
    if (s === 'unavailable') return t('meilisearch_page.health.disconnected');
    return t('meilisearch_page.health.error');
});

async function refreshHealth() {
    try {
        const [healthRes, statsRes] = await Promise.all([api.health(), api.stats()]);
        const h = healthRes.data?.data || healthRes.data;
        healthInfo.value = h;
        versionInfo.value = h;
        indexList.value = h.indexes || [];
        if (h.auto_sync && typeof h.auto_sync === 'object') {
            Object.assign(autoSync, h.auto_sync);
        }
        const s = statsRes.data?.data || statsRes.data;
        Object.assign(stats, s);
    } catch { healthInfo.value = { status: 'error' }; }
}

async function setupIndex(index) {
    setupLoading.value = true;
    try {
        const { data: res } = await api.setupIndex(index);
        if (res.success) { ElMessage.success(res.message); refreshHealth(); }
    } catch {} finally { setupLoading.value = false; }
}

async function handleRebuild() {
    rebuildLoading.value = true;
    try {
        const { data: res } = await api.rebuild();
        if (res.success) {
            ElMessage.success(res.message || t('meilisearch_page.messages.rebuild_ok'));
            refreshHealth();
        }
    } catch {
        ElMessage.error(t('meilisearch_page.messages.rebuild_fail'));
    } finally {
        rebuildLoading.value = false;
    }
}

async function handleSync(type) {
    syncLoading.value = true;
    try {
        const { data: res } = await api.sync(type);
        if (res.success) { ElMessage.success(res.message); refreshHealth(); }
    } catch {} finally { syncLoading.value = false; }
}

async function handleClear(uid) {
    try {
        const { data: res } = await api.clear(uid);
        if (res.success) { ElMessage.success(t('meilisearch_page.messages.clear_ok')); refreshHealth(); }
    } catch {}
}

async function doSearch() {
    if (!searchQuery.value) return;
    searchLoading.value = true;
    searchResults.value = null;
    try {
        const { data: res } = await api.search({ index: searchIndex.value, q: searchQuery.value, limit: 20 });
        searchResults.value = res.data;
    } catch { ElMessage.error(t('meilisearch_page.messages.search_fail')); }
    finally { searchLoading.value = false; }
}

onMounted(refreshHealth);
</script>

<style scoped>
.meilisearch-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.text-muted { color: var(--el-text-color-secondary); font-size: 13px; }
.mb-4 { margin-bottom: 16px; }
.header-actions { display: flex; align-items: center; gap: 8px; }
.toolbar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
.search-meta { font-size: 13px; color: #909399; margin-bottom: 12px; }
.stat-value { font-size: 22px; font-weight: 600; color: #303133; }
.stat-value.success { color: #67c23a; }
.stat-value.primary { color: #0f172a; }
.stat-value.warning { color: #e6a23c; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-sub { font-size: 11px; color: #c0c4cc; margin-top: 2px; }
.setup-hints { margin: 8px 0 0; padding-left: 18px; font-size: 13px; }
.setup-hints code { font-size: 12px; }
</style>
