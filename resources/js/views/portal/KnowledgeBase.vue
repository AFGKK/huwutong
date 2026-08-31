<template>
    <div class="portal-kb">
        <div class="page-header">
            <div>
                <h2>{{ $t('portal.kb_title') }}</h2>
                <p class="text-muted">{{ $t('portal.kb_subtitle') }}</p>
            </div>
        </div>

        <!-- 搜索条 -->
        <el-card shadow="never" class="search-card">
            <el-input
                v-model="searchQuery"
                :placeholder="$t('portal.kb_search_ph')"
                size="large"
                clearable
                @keyup.enter="doSearch"
                @clear="clearSearch"
            >
                <template #prefix>
                    <el-icon><Search /></el-icon>
                </template>
                <template #append>
                    <el-button @click="doSearch" :loading="searching">{{ $t('actions.search') }}</el-button>
                </template>
            </el-input>
            <div v-if="searchQuery" class="search-hint">
                {{ $t('portal.search_hint') }}
            </div>
        </el-card>

        <!-- 搜索结果 -->
        <div v-if="searchResults.length > 0" class="search-results">
            <el-card shadow="never">
                <template #header>
                    <span>{{ $t('portal.search_results_n', { n: searchTotal }) }}</span>
                </template>
                <div class="result-list">
                    <div v-for="article in searchResults" :key="article.id" class="result-item" @click="openArticle(article)">
                        <div class="result-title">{{ article.title }}</div>
                        <div class="result-excerpt">{{ article.excerpt || article.content?.substring(0, 150) }}</div>
                        <div class="result-meta">
                            <el-tag v-if="article.category" size="small" effect="plain">{{ article.category.name }}</el-tag>
                            <span class="result-date">{{ $t('portal.updated_at_label', { date: formatDate(article.updated_at) }) }}</span>
                        </div>
                    </div>
                </div>
            </el-card>
        </div>

        <!-- 分类浏览 -->
        <div v-if="!searchQuery && !searchResults.length">
            <el-row :gutter="16">
                <el-col v-for="category in categories" :key="category.id" :span="8" class="mb-4">
                    <el-card shadow="never" class="category-card" @click="selectCategory(category)">
                        <div class="category-icon">
                            <el-avatar :size="48" :style="{ background: category.color || '#0f172a' }">
                                <el-icon :size="24" color="#fff">
                                    <component :is="categoryIcon(category.name)" />
                                </el-icon>
                            </el-avatar>
                        </div>
                        <div class="category-info">
                            <div class="category-name">{{ category.name }}</div>
                            <div class="category-desc">{{ category.description || '' }}</div>
                            <div class="category-count">{{ $t('portal.articles_n', { n: category.articles_count || 0 }) }}</div>
                        </div>
                    </el-card>
                </el-col>
            </el-row>
            <el-empty v-if="!loadingCategories && categories.length === 0" :image-size="80" :description="$t('portal.no_kb')" />
        </div>

        <!-- 文章详情 Dialog -->
        <el-drawer
            v-model="showArticleDrawer"
            :title="currentArticle?.title"
            size="600px"
            direction="rtl"
        >
            <div v-if="currentArticle" class="article-detail" v-loading="loadingArticle">
                <div class="article-meta">
                    <el-tag v-if="currentArticle.category" size="small" effect="plain">
                        {{ currentArticle.category.name }}
                    </el-tag>
                    <span class="article-date">{{ $t('portal.updated_at_label', { date: formatDate(currentArticle.updated_at) }) }}</span>
                </div>

                <div class="article-content" v-html="renderedContent"></div>

                <!-- 相关文章 -->
                <div v-if="relatedArticles.length > 0" class="related-section">
                    <h4>{{ $t('portal.related_articles') }}</h4>
                    <div v-for="r in relatedArticles" :key="r.id" class="related-item" @click="openArticle(r)">
                        {{ r.title }}
                    </div>
                </div>

                <!-- 反馈 -->
                <el-divider />
                <div class="feedback-section">
                    <span class="feedback-label">{{ $t('portal.article_helpful') }}</span>
                    <div class="feedback-buttons">
                        <el-button
                            :type="feedback === 'yes' ? 'success' : 'default'"
                            :icon="CircleCheck"
                            @click="submitFeedback(true)"
                            :disabled="feedback !== null"
                        >
                            {{ $t('portal.helpful_yes') }}
                        </el-button>
                        <el-button
                            :type="feedback === 'no' ? 'danger' : 'default'"
                            :icon="Close"
                            @click="submitFeedback(false)"
                            :disabled="feedback !== null"
                        >
                            {{ $t('portal.helpful_no') }}
                        </el-button>
                    </div>
                    <div v-if="feedback" class="feedback-thanks">{{ $t('portal.feedback_thanks') }}</div>
                </div>
            </div>
        </el-drawer>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Search, CircleCheck, Close, Document, Setting, Goods, QuestionFilled } from '@element-plus/icons-vue';
import kbApi from '@/api/kb';
import i18n from '@/i18n';

const { t, locale } = useI18n();

const loadingCategories = ref(false);
const searching = ref(false);
const loadingArticle = ref(false);
const searchQuery = ref('');
const searchResults = ref([]);
const searchTotal = ref(0);
const categories = ref([]);
const currentArticle = ref(null);
const relatedArticles = ref([]);
const showArticleDrawer = ref(false);
const feedback = ref(null);
const NO_ICON = 'Document';

function categoryIcon(name) {
    const zh = (key) => i18n.global.t(`portal.kb_categories.${key}`, {}, { locale: 'zh_CN' });
    const en = (key) => i18n.global.t(`portal.kb_categories.${key}`, {}, { locale: 'en' });
    const icons = {
        getting_started: 'MagicStick',
        faq: QuestionFilled,
        integration: 'Connection',
        api: 'Monitor',
        billing: Goods,
        settings: Setting,
    };
    for (const [key, icon] of Object.entries(icons)) {
        if (name === key || name === zh(key) || name === en(key)) return icon;
    }
    return Document;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const dateLocale = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(dateStr).toLocaleString(dateLocale, {
        year: 'numeric', month: '2-digit', day: '2-digit',
    });
}

const renderedContent = computed(() => {
    if (!currentArticle.value?.content) return '';
    return currentArticle.value.content;
});

async function loadCategories() {
    loadingCategories.value = true;
    try {
        const { data: res } = await kbApi.categories();
        categories.value = res.data || [];
    } catch {
        categories.value = [];
    } finally {
        loadingCategories.value = false;
    }
}

async function doSearch() {
    const q = searchQuery.value.trim();
    if (!q) return;

    searching.value = true;
    try {
        const { data: res } = await kbApi.search({ q });
        const results = res.data;
        searchResults.value = results.data || results || [];
        searchTotal.value = results.total || searchResults.value.length;
    } catch {
        searchResults.value = [];
        searchTotal.value = 0;
    } finally {
        searching.value = false;
    }
}

function clearSearch() {
    searchResults.value = [];
    searchTotal.value = 0;
}

async function selectCategory(category) {
    searchQuery.value = category.name;
    await doSearch();
}

async function openArticle(article) {
    loadingArticle.value = true;
    showArticleDrawer.value = true;
    feedback.value = null;

    try {
        const { data: res } = await kbApi.getArticle(article.id);
        const result = res.data;
        currentArticle.value = result.article || result;
        relatedArticles.value = result.related_articles || [];
    } catch {
        if (article.content) {
            currentArticle.value = article;
            relatedArticles.value = [];
        } else {
            ElMessage.error(t('portal.article_load_failed'));
            showArticleDrawer.value = false;
        }
    } finally {
        loadingArticle.value = false;
    }
}

async function submitFeedback(isHelpful) {
    if (!currentArticle.value?.id) return;
    try {
        await kbApi.submitFeedback(currentArticle.value.id, { is_helpful: isHelpful });
        feedback.value = isHelpful ? 'yes' : 'no';
        ElMessage.success(t('portal.feedback_thanks'));
    } catch {
        ElMessage.error(t('portal.feedback_submit_failed'));
    }
}

onMounted(() => {
    loadCategories();
});
</script>

<style scoped>
.portal-kb { padding: 20px; }

.page-header {
    margin-bottom: 20px;
}
.page-header h2 { margin: 0; font-size: 20px; }
.text-muted {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin: 4px 0 0;
}

.search-card { margin-bottom: 16px; }
.search-card :deep(.el-card__body) { padding: 16px; }
.search-hint {
    font-size: 12px;
    color: var(--el-text-color-placeholder);
    margin-top: 6px;
}

/* 搜索结果 */
.search-results { margin-bottom: 16px; }
.result-list {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.result-item {
    padding: 12px 16px;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.2s;
    border-bottom: 1px solid var(--el-border-color-light);
}
.result-item:hover {
    background: var(--el-color-primary-light-9);
}
.result-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--el-color-primary);
    margin-bottom: 4px;
}
.result-excerpt {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-bottom: 6px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.result-meta {
    display: flex;
    align-items: center;
    gap: 8px;
}
.result-date {
    font-size: 12px;
    color: var(--el-text-color-placeholder);
}

/* 分类卡片 */
.category-card {
    cursor: pointer;
    transition: all 0.2s;
}
.category-card:hover {
    border-color: var(--el-color-primary) !important;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.1);
}
.category-card :deep(.el-card__body) {
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.category-icon {
    flex-shrink: 0;
}
.category-info {
    flex: 1;
    min-width: 0;
}
.category-name {
    font-size: 16px;
    font-weight: 600;
    color: var(--el-text-color-primary);
    margin-bottom: 4px;
}
.category-desc {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-bottom: 4px;
}
.category-count {
    font-size: 12px;
    color: var(--el-text-color-placeholder);
}

.mb-4 { margin-bottom: 16px; }

/* 文章详情 */
.article-detail {
    padding: 0 4px;
}
.article-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
}
.article-date {
    font-size: 12px;
    color: var(--el-text-color-placeholder);
}
.article-content {
    font-size: 14px;
    line-height: 1.8;
    color: var(--el-text-color-primary);
}
.article-content :deep(h1),
.article-content :deep(h2),
.article-content :deep(h3) {
    margin: 24px 0 12px;
    font-weight: 600;
}
.article-content :deep(p) {
    margin: 8px 0;
}
.article-content :deep(code) {
    background: #f5f7fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 13px;
}
.article-content :deep(pre) {
    background: #f5f7fa;
    padding: 16px;
    border-radius: 6px;
    overflow-x: auto;
}
.article-content :deep(img) {
    max-width: 100%;
    border-radius: 6px;
}
.article-content :deep(ul),
.article-content :deep(ol) {
    padding-left: 20px;
    margin: 8px 0;
}
.article-content :deep(blockquote) {
    border-left: 3px solid var(--el-color-primary);
    padding: 8px 16px;
    margin: 12px 0;
    background: var(--el-color-info-light-9);
    border-radius: 0 6px 6px 0;
}

.related-section {
    margin-top: 32px;
}
.related-section h4 {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 12px;
}
.related-item {
    padding: 8px 12px;
    cursor: pointer;
    color: var(--el-color-primary);
    font-size: 14px;
    border-radius: 4px;
    transition: background 0.2s;
}
.related-item:hover {
    background: var(--el-color-primary-light-9);
}

.feedback-section {
    text-align: center;
    padding: 16px 0;
}
.feedback-label {
    display: block;
    font-size: 14px;
    color: var(--el-text-color-secondary);
    margin-bottom: 12px;
}
.feedback-buttons {
    display: flex;
    justify-content: center;
    gap: 12px;
}
.feedback-thanks {
    margin-top: 12px;
    font-size: 14px;
    color: var(--el-color-success);
    font-weight: 500;
}

:deep(.el-card__body) { padding: 16px; }
</style>
