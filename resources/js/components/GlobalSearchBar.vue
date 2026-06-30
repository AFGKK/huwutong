<template>
    <div class="global-search" @keydown.esc="closeSearch">
        <el-popover
            ref="popoverRef"
            :visible="showResults"
            trigger="manual"
            :width="480"
            placement="bottom-start"
            popper-class="global-search-popper"
        >
            <template #reference>
                <div class="search-trigger">
                    <el-input
                        ref="inputRef"
                        v-model="query"
                        :placeholder="placeholderText"
                        :prefix-icon="Search"
                        clearable
                        size="small"
                        style="width: 320px"
                        aria-label="全局搜索"
                        aria-autocomplete="list"
                        role="combobox"
                        aria-expanded="showResults"
                        aria-controls="global-search-results"
                        @input="onInput"
                        @focus="onFocus"
                        @keydown.down.prevent="navigate(1)"
                        @keydown.up.prevent="navigate(-1)"
                        @keydown.enter.prevent="onEnter"
                    />
                    <span class="search-shortcut" v-if="!query">Ctrl+K</span>
                </div>
            </template>

            <!-- 搜索建议 -->
            <div class="search-results" id="global-search-results" v-loading="searching" element-loading-background="rgba(0,0,0,0.02)" role="listbox">
                <div v-if="suggestions.length > 0 && query.length >= 1 && results.length === 0 && !searching" class="suggestions-section">
                    <div class="result-group-title"><el-icon size="14"><Search /></el-icon> 搜索建议</div>
                    <div
                        v-for="(s, idx) in suggestions"
                        :key="'sug-' + idx"
                        class="result-item"
                        :class="{ 'is-active': activeIndex === idx }"
                        @click="selectSuggestion(s)"
                        @mouseenter="activeIndex = idx"
                    >
                        <div class="result-title">{{ s.text }}</div>
                        <div class="result-desc">{{ s.type }}</div>
                    </div>
                </div>

                <div v-if="results.length === 0 && query.length >= 2 && !searching && suggestions.length === 0" class="no-results">
                    <el-empty :description="$t('未找到结果')" :image-size="60" />
                </div>

                <div v-for="(group, gIdx) in groupedResults" :key="gIdx" class="result-group">
                    <div class="result-group-title">
                        <el-icon size="14"><component :is="groupIcon(group.type)" /></el-icon>
                        {{ groupLabel(group.type) }}
                        <span class="result-count">{{ group.items.length }}</span>
                    </div>
                    <div
                        v-for="(item, iIdx) in group.items"
                        :key="`${group.type}-${item.resource_id}`"
                        class="result-item"
                        :class="{ 'is-active': activeIndex === flattenIndex(gIdx, iIdx) }"
                        @click="navigateTo(item)"
                        @mouseenter="activeIndex = flattenIndex(gIdx, iIdx)"
                    >
                        <div class="result-header">
                            <div class="result-title">{{ item.title }}</div>
                            <el-tag v-if="item.status" :type="statusTag(item.status)" size="small">{{ item.status }}</el-tag>
                        </div>
                        <div class="result-desc">{{ item.description }}</div>
                    </div>
                </div>

                <div v-if="results.length > 0 && totalResults > results.length" class="show-more">
                    <el-text type="info" size="small">共 {{ totalResults }} 条结果，显示前 {{ results.length }} 条</el-text>
                </div>
            </div>
        </el-popover>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { Search, Key, User, Goods, Tickets, Document, Coin } from '@element-plus/icons-vue';
import searchApi from '@/api/globalSearch';

const router = useRouter();
const inputRef = ref(null);
const popoverRef = ref(null);

const query = ref('');
const results = ref([]);
const suggestions = ref([]);
const totalResults = ref(0);
const searching = ref(false);
const showResults = ref(false);
const activeIndex = ref(-1);

let searchTimer = null;

const placeholderText = '搜索 License/客户/产品/工单/发票/订阅...';

const groupedResults = computed(() => {
    const groups = {};
    for (const item of results.value) {
        if (!groups[item.type]) {
            groups[item.type] = { type: item.type, items: [] };
        }
        groups[item.type].items.push(item);
    }
    return Object.values(groups);
});

function flattenIndex(groupIdx, itemIdx) {
    let idx = 0;
    const groups = groupedResults.value;
    for (let g = 0; g < groups.length; g++) {
        if (g < groupIdx) {
            idx += groups[g].items.length;
        } else {
            return idx + itemIdx;
        }
    }
    return idx;
}

function groupIcon(type) {
    const icons = { license: Key, customer: User, product: Goods, ticket: Tickets, invoice: Document, subscription: Coin };
    return icons[type] || Search;
}

function groupLabel(type) {
    const labels = { license: 'License', customer: '客户', product: '产品', ticket: '工单', invoice: '发票', subscription: '订阅' };
    return labels[type] || type;
}

function statusTag(status) {
    const s = (status || '').toLowerCase();
    if (['active', 'success', 'completed', 'paid'].includes(s)) return 'success';
    if (['inactive', 'expired', 'failed', 'canceled'].includes(s)) return 'danger';
    if (['pending', 'grace'].includes(s)) return 'warning';
    return 'info';
}

function navigate(direction) {
    const totalItems = results.value.length + suggestions.value.length;
    if (totalItems === 0) return;
    activeIndex.value = (activeIndex.value + direction + totalItems) % totalItems;
}

function onEnter() {
    const sugCount = suggestions.value.length;
    if (activeIndex.value >= 0 && activeIndex.value < sugCount) {
        selectSuggestion(suggestions.value[activeIndex.value]);
    } else if (activeIndex.value >= sugCount) {
        const itemIdx = activeIndex.value - sugCount;
        if (itemIdx < results.value.length) {
            navigateTo(results.value[itemIdx]);
        }
    } else if (results.value.length === 1) {
        navigateTo(results.value[0]);
    }
}

function navigateTo(item) {
    showResults.value = false;
    query.value = '';
    results.value = [];
    router.push(item.url);
}

function closeSearch() {
    showResults.value = false;
}

function onFocus() {
    if (results.value.length > 0 || query.value.length >= 2) {
        showResults.value = true;
    }
}

function onInput(val) {
    if (val.length >= 1) {
        showResults.value = true;
        fetchSuggestions(val);
    }
    if (val.length >= 2) {
        debouncedSearch(val);
    } else {
        results.value = [];
        totalResults.value = 0;
    }
    activeIndex.value = -1;
}

function debouncedSearch(q) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => doSearch(q), 250);
}

function selectSuggestion(s) {
    query.value = s.text;
    showResults.value = true;
    doSearch(s.text);
}

async function fetchSuggestions(q) {
    try {
        const { data } = await searchApi.getSuggestions(q, 5);
        if (data.success) {
            suggestions.value = data.data || [];
        }
    } catch { suggestions.value = []; }
}

async function doSearch(q) {
    searching.value = true;
    try {
        const { data } = await searchApi.search({ q, per_page: 8 });
        if (data.success) {
            results.value = data.data.items || [];
            totalResults.value = data.data.total || 0;
            suggestions.value = [];
        }
    } catch {
        results.value = [];
        totalResults.value = 0;
    } finally {
        searching.value = false;
    }
}

function handleGlobalKeydown(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        inputRef.value?.focus();
    } else if ((e.ctrlKey || e.metaKey) && e.key === '/') {
        // Also support Ctrl+/ from the a11y shortcut
        e.preventDefault();
        inputRef.value?.focus();
    }
    if (e.key === 'Escape') {
        closeSearch();
    }
}

function handleGlobalSearchEvent() {
    inputRef.value?.focus();
}

onMounted(() => {
    document.addEventListener('keydown', handleGlobalKeydown);
    // WCAG: Support custom event from App.vue global shortcut
    window.addEventListener('a11y:global-search', handleGlobalSearchEvent);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleGlobalKeydown);
    window.removeEventListener('a11y:global-search', handleGlobalSearchEvent);
});
</script>

<style scoped>
.search-trigger {
    display: flex;
    align-items: center;
    position: relative;
}
.search-shortcut {
    position: absolute;
    right: 8px;
    font-size: 11px;
    color: var(--el-text-color-placeholder);
    background: var(--el-fill-color-light);
    border: 1px solid var(--el-border-color-light);
    border-radius: 4px;
    padding: 0 5px;
    line-height: 18px;
    pointer-events: none;
}
</style>

<style>
.global-search-popper {
    padding: 0 !important;
}
.global-search-popper .search-results {
    max-height: 480px;
    overflow-y: auto;
    padding: 4px 0;
}
.global-search-popper .no-results {
    padding: 24px 0;
}
.global-search-popper .result-group-title {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px 4px;
    font-size: 12px;
    font-weight: 600;
    color: var(--el-text-color-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.global-search-popper .result-count {
    margin-left: auto;
    font-size: 11px;
    color: var(--el-text-color-placeholder);
}
.global-search-popper .result-item {
    padding: 8px 12px;
    cursor: pointer;
    transition: background 0.12s;
    border-left: 3px solid transparent;
}
.global-search-popper .result-item.is-active,
.global-search-popper .result-item:hover {
    background: var(--el-color-primary-light-9);
    border-left-color: var(--el-color-primary);
}
.global-search-popper .result-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.global-search-popper .result-title {
    font-size: 13px;
    font-weight: 500;
    color: var(--el-text-color-primary);
}
.global-search-popper .result-desc {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-top: 2px;
}
.global-search-popper .suggestions-section {
    padding: 4px 0;
}
.global-search-popper .show-more {
    padding: 8px 12px;
    text-align: center;
    border-top: 1px solid var(--el-border-color-light);
}
</style>
