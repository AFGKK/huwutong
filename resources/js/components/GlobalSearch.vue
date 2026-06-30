<template>
  <div>
    <!-- 搜索快捷键提示 -->
    <div class="search-trigger" @click="openSearch">
      <el-icon><Search /></el-icon>
      <span class="trigger-text">搜索 (Ctrl+K)</span>
      <span class="trigger-shortcut">Ctrl+K</span>
    </div>

    <!-- 搜索模态框 -->
    <el-dialog v-model="visible" fullscreen :show-close="false" class="global-search-dialog" top="0">
      <div class="search-container" @keydown="handleKeydown">
        <div class="search-input-wrap">
          <el-icon class="search-icon"><Search /></el-icon>
          <input
            ref="searchInput"
            v-model="query"
            class="search-input"
            placeholder="搜索 License、客户、产品、工单、发票..."
            @input="onQueryInput"
            @keydown.enter="doSearch"
          />
          <el-button v-if="query" text @click="clearQuery" class="clear-btn">
            <el-icon><Close /></el-icon>
          </el-button>
        </div>

        <!-- 类型过滤器 -->
        <div class="type-filters">
          <el-checkbox-group v-model="selectedTypes">
            <el-checkbox
              v-for="t in allTypes"
              :key="t.value"
              :label="t.value"
              :value="t.value"
              size="small"
            >
              <el-icon :size="14" class="mr-1"><component :is="t.icon" /></el-icon>
              {{ t.label }}
            </el-checkbox>
          </el-checkbox-group>
        </div>

        <!-- 搜索结果 -->
        <div class="search-results" v-loading="searching">
          <!-- 搜索建议 -->
          <div v-if="!query && suggestions.length" class="result-section">
            <div class="section-header"><el-icon><Clock /></el-icon> 搜索建议</div>
            <div v-for="s in suggestions" :key="s.text" class="suggestion-item" @click="selectSuggestion(s)">
              <el-icon><Search /></el-icon>
              <span>{{ s.text }}</span>
              <small class="text-muted">{{ s.type }}</small>
            </div>
          </div>

          <!-- 最近搜索 -->
          <div v-if="!query && recentSearches.length && prefs?.show_recent !== false" class="result-section">
            <div class="section-header">
              <span><el-icon><Clock /></el-icon> 最近搜索</span>
              <el-button text size="small" @click.stop="clearAllRecent" class="clear-link">清除</el-button>
            </div>
            <div v-for="r in recentSearches" :key="r.id" class="recent-item" @click="selectRecent(r)">
              <el-icon><Clock /></el-icon>
              <span class="recent-query">{{ r.query }}</span>
              <small class="text-muted">{{ r.result_count }} 结果</small>
              <el-button text size="small" class="remove-btn" @click.stop="deleteRecent(r)">
                <el-icon><Close /></el-icon>
              </el-button>
            </div>
          </div>

          <!-- 搜索结果 -->
          <div v-if="query && searchResult.items?.length" class="result-section">
            <div class="section-header">
              <span>搜索结果 ({{ searchResult.total }})</span>
              <small class="text-muted">来源: {{ searchResult.source }}</small>
            </div>
            <div
              v-for="(item, idx) in searchResult.items"
              :key="item.type + '_' + item.resource_id"
              :class="['result-item', { 'result-item-active': selectedIndex === idx }]"
              @click="navigateTo(item)"
              @mouseenter="selectedIndex = idx"
            >
              <div class="result-icon">
                <el-tag :type="typeTag(item.type)" size="small" effect="plain">
                  <el-icon :size="14"><component :is="typeIconComp(item.icon)" /></el-icon>
                </el-tag>
              </div>
              <div class="result-body">
                <div class="result-title">{{ item.title }}</div>
                <div class="result-desc">{{ item.description }}</div>
              </div>
              <div class="result-meta">
                <el-tag v-if="item.status" :type="statusTag(item.status)" size="small">{{ item.status }}</el-tag>
                <el-button v-if="item.resource_id" text size="small" class="bookmark-btn" @click.stop="toggleBookmark(item)">
                  <el-icon :color="isBookmarked(item) ? '#e6a23c' : undefined">
                    <StarFilled v-if="isBookmarked(item)" /><Star v-else />
                  </el-icon>
                </el-button>
              </div>
            </div>
          </div>

          <!-- 无结果 -->
          <div v-else-if="query && !searching" class="no-results">
            <el-icon><Search /></el-icon>
            <p>未找到匹配 "{{ query }}" 的结果</p>
          </div>
        </div>

        <!-- 底部快捷键提示 -->
        <div class="search-footer">
          <span><kbd>↑</kbd><kbd>↓</kbd> 导航</span>
          <span><kbd>Enter</kbd> 打开</span>
          <span><kbd>Esc</kbd> 关闭</span>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import { ref, reactive, onMounted, onUnmounted, watch, markRaw, computed } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import {
  Search, Close, Clock, Star, StarFilled,
  Key, User, Goods, ChatDotSquare, Document, Coin, Avatar, Lock, Link, List, Monitor,
} from '@element-plus/icons-vue';
import searchApi from '../api/globalSearch';

export default {
  name: 'GlobalSearch',
  emits: ['navigate'],
  setup(props, { emit }) {
    const router = useRouter();
    const visible = ref(false);
    const query = ref('');
    const searching = ref(false);
    const searchInput = ref(null);
    const selectedIndex = ref(-1);
    const selectedTypes = ref([]);
    const searchResult = reactive({ items: [], total: 0, source: '' });
    const recentSearches = ref([]);
    const suggestions = ref([]);
    const bookmarks = ref([]);
    const prefs = ref(null);

    const allTypes = [
      { value: 'license', label: 'License', icon: markRaw(Key) },
      { value: 'customer', label: '客户', icon: markRaw(User) },
      { value: 'product', label: '产品', icon: markRaw(Goods) },
      { value: 'ticket', label: '工单', icon: markRaw(ChatDotSquare) },
      { value: 'invoice', label: '发票', icon: markRaw(Document) },
      { value: 'subscription', label: '订阅', icon: markRaw(Coin) },
    ];

    const iconMap = {
      Key: markRaw(Key), User: markRaw(User), Goods: markRaw(Goods),
      ChatDotSquare: markRaw(ChatDotSquare), Document: markRaw(Document),
      Coin: markRaw(Coin), Avatar: markRaw(Avatar), Lock: markRaw(Lock),
      Link: markRaw(Link), List: markRaw(List), Monitor: markRaw(Monitor),
      Search: markRaw(Search),
    };

    function typeIconComp(name) {
      return iconMap[name] || Search;
    }

    // ─── 键盘快捷键 ───
    function handleKeydown(e) {
      if (e.key === 'Escape') closeSearch();
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex.value = Math.min(selectedIndex.value + 1, searchResult.items.length - 1);
      }
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex.value = Math.max(selectedIndex.value - 1, 0);
      }
    }

    function globalKeydown(e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        toggleSearch();
      }
    }

    // ─── 搜索 ───
    let searchTimer = null;

    function onQueryInput() {
      if (query.value.length >= 2) {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(doSearch, 300);
      }
      if (query.value.length >= 1 && prefs.value?.show_suggestions !== false) {
        fetchSuggestions();
      }
    }

    async function doSearch() {
      if (!query.value || query.value.length < 1) return;
      searching.value = true;
      selectedIndex.value = -1;
      try {
        const params = { q: query.value };
        if (selectedTypes.value.length) params.types = selectedTypes.value;
        const { data } = await searchApi.search(params);
        if (data.success) {
          searchResult.items = data.data.items || [];
          searchResult.total = data.data.total || 0;
          searchResult.source = data.data.source || '';
        }
      } catch (e) {
        searchResult.items = [];
      } finally {
        searching.value = false;
      }
    }

    async function fetchSuggestions() {
      if (!query.value) return;
      try {
        const { data } = await searchApi.getSuggestions(query.value);
        if (data.success) suggestions.value = data.data || [];
      } catch (e) { suggestions.value = []; }
    }

    // ─── 最近搜索 ───
    async function fetchRecent() {
      try {
        const { data } = await searchApi.getRecent();
        if (data.success) recentSearches.value = data.data || [];
      } catch (e) { /* */ }
    }

    function selectRecent(r) {
      query.value = r.query;
      doSearch();
    }

    async function deleteRecent(r) {
      try {
        await searchApi.deleteRecent(r.id);
        fetchRecent();
      } catch (e) { /* */ }
    }

    async function clearAllRecent() {
      try {
        await searchApi.clearRecent();
        recentSearches.value = [];
        ElMessage.success('最近搜索已清除');
      } catch (e) { /* */ }
    }

    function selectSuggestion(s) {
      query.value = s.text;
      doSearch();
    }

    // ─── 收藏 ───
    async function fetchBookmarks() {
      try {
        const { data } = await searchApi.getBookmarks();
        if (data.success) bookmarks.value = data.data || [];
      } catch (e) { /* */ }
    }

    function isBookmarked(item) {
      return bookmarks.value.some(b =>
        b.resource_type === item.type && b.resource_id === item.resource_id
      );
    }

    async function toggleBookmark(item) {
      try {
        const { data } = await searchApi.toggleBookmark({
          resource_type: item.type,
          resource_id: item.resource_id,
        });
        if (data.success) {
          ElMessage.success(data.message);
          fetchBookmarks();
        }
      } catch (e) { /* */ }
    }

    // ─── 导航 ───
    function navigateTo(item) {
      closeSearch();
      if (item.url) {
        emit('navigate', item.url);
        router.push(item.url);
      }
    }

    // ─── 搜索面板控制 ───
    function openSearch() { visible.value = true; }
    function closeSearch() {
      visible.value = false;
      query.value = '';
      searchResult.items = [];
      searchResult.total = 0;
      selectedIndex.value = -1;
    }
    function toggleSearch() {
      if (visible.value) closeSearch();
      else openSearch();
    }
    function clearQuery() {
      query.value = '';
      searchResult.items = [];
    }

    // ─── 工具 ───
    function typeTag(type) {
      const map = { license: 'warning', customer: 'success', product: 'primary', ticket: 'info', invoice: 'danger', subscription: '' };
      return map[type] || '';
    }
    function statusTag(status) {
      const s = (status || '').toLowerCase();
      if (['active', 'success', 'completed', 'paid'].includes(s)) return 'success';
      if (['inactive', 'expired', 'failed', 'canceled'].includes(s)) return 'danger';
      if (['pending', 'grace'].includes(s)) return 'warning';
      return 'info';
    }

    // ─── 偏好 ───
    async function fetchPrefs() {
      try {
        const { data } = await searchApi.getPreferences();
        if (data.success) prefs.value = data.data;
      } catch (e) { /* */ }
    }

    watch(visible, (v) => {
      if (v) {
        setTimeout(() => searchInput.value?.focus(), 100);
        fetchRecent();
        fetchBookmarks();
        fetchPrefs();
      }
    });

    onMounted(() => {
      document.addEventListener('keydown', globalKeydown);
    });
    onUnmounted(() => {
      document.removeEventListener('keydown', globalKeydown);
    });

    return {
      visible, query, searching, searchInput, selectedIndex, selectedTypes,
      searchResult, recentSearches, suggestions, bookmarks, prefs,
      allTypes,
      handleKeydown, onQueryInput, doSearch,
      selectRecent, deleteRecent, clearAllRecent,
      selectSuggestion, isBookmarked, toggleBookmark,
      navigateTo, openSearch, closeSearch, clearQuery,
      typeTag, statusTag, typeIconComp,
    };
  },
};
</script>

<style scoped>
.search-trigger {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border: 1px solid #dcdfe6;
  border-radius: 6px;
  cursor: pointer;
  color: #909399;
  font-size: 13px;
  transition: all .2s;
  width: 240px;
}
.search-trigger:hover {
  border-color: #409eff;
  color: #409eff;
}
.trigger-shortcut {
  margin-left: auto;
  padding: 1px 6px;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  font-size: 11px;
  background: #f5f7fa;
}

.global-search-dialog :deep(.el-dialog__body) { padding: 0; }
.search-container { padding: 20px; max-width: 800px; margin: 0 auto; }

.search-input-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border: 2px solid #409eff;
  border-radius: 12px;
  background: #fff;
}
.search-icon { font-size: 20px; color: #409eff; }
.search-input {
  flex: 1;
  border: none;
  outline: none;
  font-size: 18px;
  background: transparent;
}
.clear-btn { color: #909399; }

.type-filters {
  margin-top: 12px;
  padding: 8px 4px;
}
.type-filters .mr-1 { margin-right: 4px; }

.search-results {
  margin-top: 16px;
  max-height: 60vh;
  overflow-y: auto;
}
.result-section { margin-bottom: 16px; }
.section-header {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #606266;
  font-weight: 600;
  padding: 8px 4px;
  border-bottom: 1px solid #f0f0f0;
  margin-bottom: 4px;
}
.clear-link { margin-left: auto; }

.suggestion-item, .recent-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  cursor: pointer;
  border-radius: 6px;
  font-size: 14px;
}
.suggestion-item:hover, .recent-item:hover { background: #f5f7fa; }
.recent-query { flex: 1; }
.remove-btn { opacity: 0; }
.recent-item:hover .remove-btn { opacity: 1; }

.result-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  cursor: pointer;
  border-radius: 8px;
  transition: background .15s;
}
.result-item:hover, .result-item-active { background: #ecf5ff; }
.result-icon { flex-shrink: 0; }
.result-body { flex: 1; min-width: 0; }
.result-title { font-weight: 600; font-size: 14px; }
.result-desc { font-size: 12px; color: #909399; }
.result-meta { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.bookmark-btn { opacity: 0; }
.result-item:hover .bookmark-btn { opacity: 1; }

.no-results { text-align: center; padding: 40px; color: #909399; }
.no-results .el-icon { font-size: 48px; margin-bottom: 12px; }

.search-footer {
  display: flex;
  gap: 16px;
  padding: 12px 4px 0;
  border-top: 1px solid #f0f0f0;
  margin-top: 12px;
  font-size: 12px;
  color: #909399;
}
.search-footer kbd {
  padding: 2px 6px;
  border: 1px solid #d0d0d0;
  border-radius: 4px;
  background: #f5f7fa;
  font-size: 11px;
  margin: 0 2px;
}
</style>
