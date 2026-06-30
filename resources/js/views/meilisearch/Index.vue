<template>
    <div class="meilisearch-page">
        <div class="page-header">
            <div>
                <h2>Meilisearch 全文搜索</h2>
                <p class="text-muted">高性能全文搜索引擎，覆盖商品、知识库、应用市场、广场、博客、公众号、用户 7 大内容类型</p>
            </div>
            <div class="header-actions">
                <el-tag :type="healthTag" size="large">
                    {{ healthLabel }}
                </el-tag>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-value primary">{{ stats.products?.in_db || 0 }}</div>
                    <div class="stat-label">商品总数 (DB)</div>
                    <div class="stat-sub" v-if="stats.products">已索引: {{ stats.products.in_meili || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-value success">{{ stats.kb_articles?.in_db || 0 }}</div>
                    <div class="stat-label">知识库文章 (DB)</div>
                    <div class="stat-sub" v-if="stats.kb_articles">已索引: {{ stats.kb_articles.in_meili || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-value warning">{{ stats.marketplace_apps?.in_db || 0 }}</div>
                    <div class="stat-label">应用市场 (DB)</div>
                    <div class="stat-sub" v-if="stats.marketplace_apps">已索引: {{ stats.marketplace_apps.in_meili || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-value">{{ stats.forum_posts?.in_db || 0 }} / {{ stats.blog_posts?.in_db || 0 }} / {{ stats.oa_articles?.in_db || 0 }}</div>
                    <div class="stat-label">广场/博客/公众号 (DB)</div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never">
            <el-tabs v-model="activeTab">
                <!-- 索引管理 -->
                <el-tab-pane label="索引管理" name="indexes">
                    <div class="toolbar">
                        <el-button type="primary" @click="setupIndex('products')" :loading="setupLoading" size="small">初始化商品</el-button>
                        <el-button type="primary" @click="setupIndex('kb_articles')" :loading="setupLoading" size="small">初始化知识库</el-button>
                        <el-button type="primary" @click="setupIndex('marketplace_apps')" :loading="setupLoading" size="small">初始化应用市场</el-button>
                        <el-tooltip content="论坛/博客/公众号/用户" placement="top">
                            <el-button type="primary" @click="setupIndex('forum_posts')" :loading="setupLoading" size="small">初始化其他</el-button>
                        </el-tooltip>
                        <el-button @click="handleSync('all')" :loading="syncLoading" type="success" size="small">同步全部</el-button>
                        <el-button @click="handleSync('products')" :loading="syncLoading" size="small">同步商品</el-button>
                        <el-button @click="handleSync('kb_articles')" :loading="syncLoading" size="small">同步知识库</el-button>
                        <el-button @click="handleSync('marketplace_apps')" :loading="syncLoading" size="small">同步应用市场</el-button>
                        <el-button @click="handleSync('forum_posts')" :loading="syncLoading" size="small">同步广场</el-button>
                        <el-button @click="handleSync('blog_posts')" :loading="syncLoading" size="small">同步博客</el-button>
                        <el-button @click="handleSync('oa_articles')" :loading="syncLoading" size="small">同步公众号</el-button>
                        <el-button @click="handleSync('users')" :loading="syncLoading" size="small">同步用户</el-button>
                        <el-button @click="refreshHealth" :icon="Refresh" size="small">刷新</el-button>
                    </div>

                    <el-table :data="indexList" v-loading="loading" stripe>
                        <el-table-column label="索引名称" prop="uid" min-width="200" />
                        <el-table-column label="主键" prop="primary_key" width="100" />
                        <el-table-column label="文档数" width="100" prop="number_of_documents" align="center" />
                        <el-table-column label="可搜索属性" min-width="200">
                            <template #default="{ row }">
                                <el-tag v-for="attr in (row.searchable || [])" :key="attr" size="small" style="margin:1px">{{ attr }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="过滤属性" min-width="180">
                            <template #default="{ row }">
                                <el-tag v-for="attr in (row.filterable || [])" :key="attr" size="small" type="success" style="margin:1px">{{ attr }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="120">
                            <template #default="{ row }">
                                <el-popconfirm title="确认清空此索引？" @confirm="handleClear(row.uid)">
                                    <template #reference><el-button size="small" type="danger">清空</el-button></template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!indexList.length && !loading" description="暂无索引，请先初始化" :image-size="50" />
                </el-tab-pane>

                <!-- 搜索测试 -->
                <el-tab-pane label="搜索测试" name="search">
                    <el-form :inline="true" @submit.prevent="doSearch">
                        <el-form-item>
                            <el-select v-model="searchIndex" style="width:150px">
                                <el-option label="商品" value="products" />
                                <el-option label="知识库" value="kb_articles" />
                                <el-option label="应用市场" value="marketplace_apps" />
                                <el-option label="广场" value="forum_posts" />
                                <el-option label="博客" value="blog_posts" />
                                <el-option label="公众号" value="oa_articles" />
                                <el-option label="用户" value="users" />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-input v-model="searchQuery" placeholder="输入搜索关键词..." style="width:300px" clearable @clear="searchResults=null" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" native-type="submit" :loading="searchLoading">搜索</el-button>
                        </el-form-item>
                    </el-form>

                    <div v-if="searchResults">
                        <div class="search-meta">
                            找到约 {{ searchResults.total }} 条结果（{{ searchResults.processing_time_ms }}ms）
                        </div>
                        <el-table :data="searchResults.hits" stripe>
                            <el-table-column label="#" width="50" type="index" />
                            <el-table-column label="标题" min-width="250">
                                <template #default="{ row }">
                                    <div v-html="row._formatted?.title || row.title"></div>
                                </template>
                            </el-table-column>
                            <el-table-column label="内容摘要" min-width="350">
                                <template #default="{ row }">
                                    <div v-html="(row._formatted?.content || row.content || '').substring(0, 200)" class="text-muted"></div>
                                </template>
                            </el-table-column>
                            <el-table-column label="得分" width="80" align="center">
                                <template #default="{ row }">{{ row._rankingScore ? row._rankingScore.toFixed(2) : '-' }}</template>
                            </el-table-column>
                        </el-table>
                        <el-empty v-if="!searchResults.hits?.length" description="无匹配结果" :image-size="40" />
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';
import api from '@/api/meilisearch';

const loading = ref(false);
const activeTab = ref('indexes');
const healthInfo = ref(null);
const indexList = ref([]);
const setupLoading = ref(false);
const syncLoading = ref(false);
const searchQuery = ref('');
const searchIndex = ref('products');
const searchLoading = ref(false);
const searchResults = ref(null);
const versionInfo = ref(null);
const stats = reactive({ products: null, kb_articles: null });

const healthTag = computed(() => {
    if (!healthInfo.value) return 'info';
    return healthInfo.value.status === 'available' ? 'success' : 'danger';
});
const healthLabel = computed(() => {
    if (!healthInfo.value) return '检查中...';
    const s = healthInfo.value.status;
    if (s === 'available') return `✅ 已连接 (${healthInfo.value.version?.pkgVersion || ''})`;
    if (s === 'unavailable') return '❌ 未连接';
    return '⚠️ 异常';
});

async function refreshHealth() {
    try {
        const [healthRes, statsRes] = await Promise.all([api.health(), api.stats()]);
        const h = healthRes.data?.data || healthRes.data;
        healthInfo.value = h;
        versionInfo.value = h;
        indexList.value = h.indexes || [];
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
        if (res.success) { ElMessage.success('索引已清空'); refreshHealth(); }
    } catch {}
}

async function doSearch() {
    if (!searchQuery.value) return;
    searchLoading.value = true;
    searchResults.value = null;
    try {
        const { data: res } = await api.search({ index: searchIndex.value, q: searchQuery.value, limit: 20 });
        searchResults.value = res.data;
    } catch { ElMessage.error('搜索失败'); }
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
.stat-value.primary { color: #409eff; }
.stat-value.warning { color: #e6a23c; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-sub { font-size: 11px; color: #c0c4cc; margin-top: 2px; }
</style>
