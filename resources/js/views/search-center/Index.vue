<template>
    <div class="search-center">
        <div class="page-header">
            <h2>搜索中心</h2>
            <p class="text-muted">跨模块高级搜索，保存常用筛选条件，快速定位数据</p>
        </div>

        <!-- ── 搜索概况 ── -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats.indexed }}</div>
                    <div class="stat-label">已索引条目</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats.savedCount }}</div>
                    <div class="stat-label">保存的搜索</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats.recentCount }}</div>
                    <div class="stat-label">最近搜索</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats.bookmarkCount }}</div>
                    <div class="stat-label">搜索收藏</div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <!-- ── 快速搜索 ── -->
            <el-tab-pane label="快速搜索" name="quick">
                <el-card shadow="never">
                    <div class="quick-search-area">
                        <el-input
                            v-model="quickQuery"
                            placeholder="输入关键词，跨模块搜索..."
                            size="large"
                            clearable
                            @keyup.enter="doQuickSearch"
                        >
                            <template #prefix>
                                <el-icon><Search /></el-icon>
                            </template>
                            <template #append>
                                <el-button @click="doQuickSearch" :icon="Search">搜索</el-button>
                            </template>
                        </el-input>

                        <div class="quick-type-filters">
                            <el-checkbox-group v-model="quickSearchTypes">
                                <el-checkbox v-for="t in searchableTypes" :key="t.value" :label="t.value" border size="small">
                                    {{ t.label }}
                                </el-checkbox>
                            </el-checkbox-group>
                        </div>

                        <div v-if="quickResults.length > 0" class="quick-results">
                            <el-divider />
                            <div class="quick-results-header">
                                <span>搜索结果 ({{ quickTotal }})</span>
                                <el-button text size="small" @click="openGlobalSearch">在全局搜索中查看全部</el-button>
                            </div>
                            <div class="quick-result-list">
                                <div v-for="item in quickResults" :key="item.type + '_' + item.resource_id" class="quick-result-item" @click="navigateTo(item)">
                                    <el-icon :size="18"><component :is="item.icon" /></el-icon>
                                    <div class="quick-result-info">
                                        <div class="quick-result-title">{{ item.title }}</div>
                                        <div class="quick-result-desc">{{ item.description }}</div>
                                    </div>
                                    <el-tag size="small" type="info">{{ item.type }}</el-tag>
                                </div>
                            </div>
                        </div>

                        <el-empty v-else-if="searched && quickResults.length === 0" description="未找到匹配结果" />
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- ── 高级筛选 ── -->
            <el-tab-pane label="高级筛选" name="advanced">
                <el-row :gutter="16">
                    <el-col :span="6">
                        <el-card shadow="never">
                            <template #header>
                                <span>选择模块</span>
                            </template>
                            <el-menu :default-active="selectedPage" class="page-menu" @select="selectPage">
                                <el-menu-item v-for="p in pageDefinitions" :key="p.key" :index="p.key">
                                    <el-icon><component :is="p.icon" /></el-icon>
                                    <span>{{ p.label }}</span>
                                </el-menu-item>
                            </el-menu>
                        </el-card>
                    </el-col>

                    <el-col :span="18">
                        <el-card shadow="never">
                            <template #header>
                                <div class="adv-header">
                                    <span>{{ currentPageLabel }} - 高级筛选</span>
                                    <div class="adv-actions">
                                        <SavedSearchBar
                                            :page="selectedPage"
                                            :current-filters="currentAdvFilters"
                                            @apply="applySavedFilters"
                                        />
                                    </div>
                                </div>
                            </template>

                            <div v-if="!filterDefinitions || filterDefinitions.length === 0" class="text-center py-8 text-muted">
                                该模块暂无可用筛选器
                            </div>

                            <el-form v-else label-position="top" size="small">
                                <el-row :gutter="16">
                                    <template v-for="(def, field) in filterDefinitions" :key="field">
                                        <el-col :span="8" class="filter-item">
                                            <el-form-item :label="def.label">
                                                <!-- 文本输入 -->
                                                <el-input
                                                    v-if="def.type === 'text'"
                                                    v-model="currentAdvFilters[field]"
                                                    :placeholder="def.placeholder || '请输入'"
                                                    clearable
                                                    @clear="debouncedSearch"
                                                    @input="debouncedSearch"
                                                />
                                                <!-- 下拉选择(单选) -->
                                                <el-select
                                                    v-else-if="def.type === 'select' && !def.multiple"
                                                    v-model="currentAdvFilters[field]"
                                                    :placeholder="def.placeholder || '请选择'"
                                                    clearable
                                                    style="width:100%"
                                                    @change="search"
                                                >
                                                    <el-option v-for="opt in def.options" :key="opt.value" :label="opt.label" :value="opt.value" />
                                                </el-select>
                                                <!-- 下拉选择(多选) -->
                                                <el-select
                                                    v-else-if="def.type === 'select' && def.multiple"
                                                    v-model="currentAdvFilters[field]"
                                                    :placeholder="def.placeholder || '请选择(多选)'"
                                                    multiple
                                                    collapse-tags
                                                    collapse-tags-tooltip
                                                    clearable
                                                    style="width:100%"
                                                    @change="search"
                                                >
                                                    <el-option v-for="opt in def.options" :key="opt.value" :label="opt.label" :value="opt.value" />
                                                </el-select>
                                                <!-- 模型选择 -->
                                                <el-select
                                                    v-else-if="def.type === 'model-select'"
                                                    v-model="currentAdvFilters[field]"
                                                    :placeholder="'选择' + def.label"
                                                    filterable
                                                    clearable
                                                    style="width:100%"
                                                    @change="search"
                                                >
                                                    <!-- Model options would ideally be fetched from API -->
                                                    <el-option label="查询API获取" value="" disabled />
                                                </el-select>
                                                <!-- 日期 -->
                                                <el-date-picker
                                                    v-else-if="def.type === 'date'"
                                                    v-model="currentAdvFilters[field]"
                                                    type="date"
                                                    :placeholder="def.label"
                                                    value-format="YYYY-MM-DD"
                                                    clearable
                                                    style="width:100%"
                                                    @change="search"
                                                />
                                                <!-- 数字 -->
                                                <el-input-number
                                                    v-else-if="def.type === 'number'"
                                                    v-model="currentAdvFilters[field]"
                                                    :min="def.min || 0"
                                                    :max="def.max || 999999"
                                                    :placeholder="def.label"
                                                    controls-position="right"
                                                    style="width:100%"
                                                    @change="search"
                                                />
                                                <!-- 布尔 -->
                                                <el-switch
                                                    v-else-if="def.type === 'boolean'"
                                                    v-model="currentAdvFilters[field]"
                                                    active-value="1"
                                                    inactive-value="0"
                                                    @change="search"
                                                />
                                            </el-form-item>
                                        </el-col>
                                    </template>
                                </el-row>

                                <el-row :gutter="16">
                                    <el-col :span="24">
                                        <el-button type="primary" :icon="Search" @click="search">搜索</el-button>
                                        <el-button @click="resetAdvFilters">重置</el-button>
                                    </el-col>
                                </el-row>
                            </el-form>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- 高级搜索结果 -->
                <el-card v-if="advResults.items && advResults.items.length > 0" shadow="never" class="mt-4">
                    <template #header>
                        <span>搜索结果 ({{ advResults.total }})</span>
                    </template>
                    <el-table :data="advResults.items" border stripe v-loading="advLoading" @row-click="navigateToRow">
                        <el-table-column type="index" width="50" />
                        <el-table-column
                            v-for="col in displayColumns"
                            :key="col.key"
                            :prop="col.key"
                            :label="col.label"
                            :min-width="col.minWidth || 120"
                            show-overflow-tooltip
                        />
                    </el-table>
                    <div class="pagination-wrap" v-if="advResults.total > advResults.per_page">
                        <el-pagination
                            v-model:current-page="advPage"
                            :page-size="advResults.per_page"
                            :total="advResults.total"
                            layout="prev, pager, next, total"
                            @current-change="search"
                        />
                    </div>
                </el-card>
                <el-empty v-else-if="advSearched && advResults.items?.length === 0" description="未找到匹配结果" />
            </el-tab-pane>

            <!-- ── 保存的搜索 ── -->
            <el-tab-pane label="保存的搜索" name="saved">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header>
                                <div class="card-header">
                                    <span>我的搜索</span>
                                    <el-tag size="small">{{ mySavedSearches.length }}</el-tag>
                                </div>
                            </template>
                            <div v-if="mySavedSearches.length === 0" class="text-muted text-center py-4">
                                暂无保存的搜索。在高级筛选页面设置条件后保存。
                            </div>
                            <div v-else class="saved-list">
                                <div v-for="s in mySavedSearches" :key="s.id" class="saved-item" :style="s.color ? { borderLeftColor: s.color } : {}">
                                    <div class="saved-item-header">
                                        <el-icon v-if="s.icon" :size="16"><component :is="s.icon" /></el-icon>
                                        <el-icon v-else :size="16"><Search /></el-icon>
                                        <strong>{{ s.name }}</strong>
                                        <el-tag v-if="s.is_shared" size="small" type="warning">共享</el-tag>
                                    </div>
                                    <div v-if="s.description" class="saved-item-desc">{{ s.description }}</div>
                                    <div class="saved-item-meta">
                                        <span>{{ pageLabel(s.page) }}</span>
                                        <span>使用 {{ s.usage_count }} 次</span>
                                        <span v-if="s.last_used_at">最近 {{ formatDate(s.last_used_at) }}</span>
                                    </div>
                                    <div class="saved-item-actions">
                                        <el-button size="small" type="primary" plain @click="applySaved(s)">应用</el-button>
                                        <el-button size="small" @click="editSavedDialog(s)">编辑</el-button>
                                        <el-popconfirm title="确认删除?" @confirm="deleteSaved(s)">
                                            <template #reference>
                                                <el-button size="small" type="danger" plain>删除</el-button>
                                            </template>
                                        </el-popconfirm>
                                    </div>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header>
                                <div class="card-header">
                                    <span>团队共享</span>
                                    <el-tag size="small">{{ sharedSearches.length }}</el-tag>
                                </div>
                            </template>
                            <div v-if="sharedSearches.length === 0" class="text-muted text-center py-4">
                                暂无团队共享的搜索。
                            </div>
                            <div v-else class="saved-list">
                                <div v-for="s in sharedSearches" :key="s.id" class="saved-item shared">
                                    <div class="saved-item-header">
                                        <el-icon :size="16"><Share /></el-icon>
                                        <strong>{{ s.name }}</strong>
                                        <el-tag size="small">{{ s.user?.name || '未知用户' }}</el-tag>
                                    </div>
                                    <div class="saved-item-meta">
                                        <span>{{ pageLabel(s.page) }}</span>
                                        <span>使用 {{ s.usage_count }} 次</span>
                                    </div>
                                    <div class="saved-item-actions">
                                        <el-button size="small" type="primary" plain @click="applySaved(s)">应用</el-button>
                                    </div>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- ── 常用搜索 ── -->
            <el-tab-pane label="常用搜索" name="frequent">
                <el-row :gutter="16">
                    <el-col v-for="s in frequentSearches" :key="s.id" :span="6" class="mb-4">
                        <el-card shadow="hover" class="frequent-card" :style="s.color ? { borderTop: `3px solid ${s.color}` } : {}" @click="applySaved(s)">
                            <div class="frequent-icon">
                                <el-icon :size="24" :color="s.color || '#409eff'">
                                    <component :is="pageIcon(s.page)" />
                                </el-icon>
                            </div>
                            <div class="frequent-name">{{ s.name }}</div>
                            <div class="frequent-page">{{ pageLabel(s.page) }}</div>
                            <div class="frequent-count">已使用 {{ s.usage_count }} 次</div>
                        </el-card>
                    </el-col>
                    <el-col v-if="frequentSearches.length === 0" :span="24">
                        <el-empty description="暂无常用搜索。多次使用保存的搜索后，将自动出现在这里。" />
                    </el-col>
                </el-row>
            </el-tab-pane>
        </el-tabs>

        <!-- 编辑保存搜索对话框 -->
        <el-dialog v-model="editDialogVisible" title="编辑保存搜索" width="500px">
            <el-form label-position="top" size="small">
                <el-form-item label="名称">
                    <el-input v-model="editForm.name" maxlength="100" />
                </el-form-item>
                <el-form-item label="描述">
                    <el-input v-model="editForm.description" maxlength="200" type="textarea" :rows="2" />
                </el-form-item>
                <el-form-item label="图标">
                    <el-select v-model="editForm.icon" clearable filterable placeholder="选择图标">
                        <el-option v-for="icon in iconOptions" :key="icon.value" :label="icon.label" :value="icon.value">
                            <el-icon :size="16"><component :is="icon.value" /></el-icon>
                            <span class="ml-2">{{ icon.label }}</span>
                        </el-option>
                    </el-select>
                </el-form-item>
                <el-form-item label="颜色">
                    <el-color-picker v-model="editForm.color" show-alpha :predefine="colorPresets" />
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="editForm.sort_order" :min="0" />
                </el-form-item>
                <el-form-item>
                    <el-checkbox v-model="editForm.is_shared">分享给团队成员</el-checkbox>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="editDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="editSaving" @click="saveEdit">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import {
    Search, Share, Key, User, ChatDotSquare, Goods, Document, Coin,
    Star, StarFilled, Refresh, Delete, EditPen,
} from '@element-plus/icons-vue';
import globalSearchApi from '@/api/globalSearch';
import advancedSearchApi from '@/api/advancedSearch';
import savedSearchApi from '@/api/savedSearch';
import SavedSearchBar from '@/components/SavedSearchBar.vue';

const router = useRouter();

// ─── Tab ───
const activeTab = ref('quick');

// ─── Stats ───
const stats = reactive({
    indexed: 0,
    savedCount: 0,
    recentCount: 0,
    bookmarkCount: 0,
});

// ─── Quick Search ───
const quickQuery = ref('');
const quickSearchTypes = ref(['license', 'customer', 'ticket', 'product']);
const quickResults = ref([]);
const quickTotal = ref(0);
const searched = ref(false);

const searchableTypes = [
    { value: 'license', label: '许可证' },
    { value: 'customer', label: '客户' },
    { value: 'ticket', label: '工单' },
    { value: 'product', label: '产品' },
    { value: 'invoice', label: '发票' },
    { value: 'subscription', label: '订阅' },
];

async function doQuickSearch() {
    if (!quickQuery.value?.trim()) return;
    searched.value = true;
    try {
        const { data: res } = await globalSearchApi.search({
            q: quickQuery.value.trim(),
            types: quickSearchTypes.value,
            per_page: 20,
        });
        if (res.success) {
            quickResults.value = res.data.items || [];
            quickTotal.value = res.data.total || 0;
        }
    } catch {
        quickResults.value = [];
        quickTotal.value = 0;
    }
}

function openGlobalSearch() {
    // Navigate to full global search results
    window.open(`/admin/global-search?q=${encodeURIComponent(quickQuery.value)}`, '_blank');
}

function navigateTo(item) {
    if (item.url) {
        router.push(item.url);
    }
}

// ─── Advanced Search ───
const selectedPage = ref('licenses');
const pageDefinitions = ref([]);
const filterDefinitions = ref(null);
const currentAdvFilters = reactive({});
const advResults = reactive({ items: [], total: 0, page: 1, per_page: 20 });
const advLoading = ref(false);
const advSearched = ref(false);
const advPage = ref(1);
let debounceTimer = null;

const currentPageLabel = computed(() => {
    const def = pageDefinitions.value.find(p => p.key === selectedPage.value);
    return def?.label || selectedPage.value;
});

const displayColumns = computed(() => {
    // Return basic columns based on page type
    const colMap = {
        licenses: [
            { key: 'id', label: 'ID' },
            { key: 'license_key', label: '许可证密钥', minWidth: 200 },
            { key: 'type', label: '类型' },
            { key: 'status', label: '状态' },
            { key: 'created_at', label: '创建时间', minWidth: 160 },
        ],
        customers: [
            { key: 'id', label: 'ID' },
            { key: 'name', label: '姓名' },
            { key: 'email', label: '邮箱', minWidth: 180 },
            { key: 'company', label: '公司' },
            { key: 'created_at', label: '创建时间', minWidth: 160 },
        ],
        tickets: [
            { key: 'id', label: 'ID' },
            { key: 'subject', label: '主题', minWidth: 200 },
            { key: 'status', label: '状态' },
            { key: 'priority', label: '优先级' },
            { key: 'created_at', label: '创建时间', minWidth: 160 },
        ],
        products: [
            { key: 'id', label: 'ID' },
            { key: 'name', label: '名称' },
            { key: 'price', label: '价格' },
            { key: 'is_active', label: '启用' },
            { key: 'created_at', label: '创建时间', minWidth: 160 },
        ],
        invoices: [
            { key: 'id', label: 'ID' },
            { key: 'invoice_no', label: '发票号', minWidth: 180 },
            { key: 'amount', label: '金额' },
            { key: 'status', label: '状态' },
            { key: 'created_at', label: '创建时间', minWidth: 160 },
        ],
        subscriptions: [
            { key: 'id', label: 'ID' },
            { key: 'plan', label: '方案', minWidth: 150 },
            { key: 'status', label: '状态' },
            { key: 'billing_period', label: '计费周期' },
            { key: 'created_at', label: '创建时间', minWidth: 160 },
        ],
    };
    return colMap[selectedPage.value] || [{ key: 'id', label: 'ID' }];
});

function selectPage(page) {
    selectedPage.value = page;
    loadFilterDefinitions();
    resetAdvFilters();
    advResults.items = [];
    advResults.total = 0;
    advSearched.value = false;
}

function loadFilterDefinitions() {
    advancedSearchApi.getFilterDefinitions(selectedPage.value).then(({ data: res }) => {
        if (res.success) {
            filterDefinitions.value = res.data.filters;
        }
    }).catch(() => {
        filterDefinitions.value = null;
    });
}

function resetAdvFilters() {
    Object.keys(currentAdvFilters).forEach(k => delete currentAdvFilters[k]);
    advResults.items = [];
    advResults.total = 0;
    advSearched.value = false;
}

function debouncedSearch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(search, 500);
}

async function search() {
    advSearched.value = true;
    advLoading.value = true;
    try {
        const { data: res } = await advancedSearchApi.advancedSearch(selectedPage.value, { ...currentAdvFilters }, {
            page: advPage.value,
            perPage: 20,
        });
        if (res.success) {
            advResults.items = res.data.items || [];
            advResults.total = res.data.total || 0;
            advResults.per_page = res.data.per_page || 20;
        }
    } catch {
        advResults.items = [];
        advResults.total = 0;
    } finally {
        advLoading.value = false;
    }
}

function navigateToRow(row) {
    if (!row?.id) return;
    const routes = {
        licenses: `/admin/licenses/${row.id}`,
        customers: `/admin/customers/${row.id}`,
        tickets: `/admin/tickets/${row.id}`,
        products: `/admin/products/${row.id}`,
        invoices: `/admin/invoices/${row.id}`,
        subscriptions: `/admin/subscriptions/${row.id}`,
    };
    const route = routes[selectedPage.value];
    if (route) router.push(route);
}

function applySavedFilters(filters) {
    Object.assign(currentAdvFilters, filters || {});
    search();
}

// ─── Saved Searches ───
const mySavedSearches = ref([]);
const sharedSearches = ref([]);
const frequentSearches = ref([]);
const editDialogVisible = ref(false);
const editSaving = ref(false);
const editForm = reactive({
    id: null,
    name: '',
    description: '',
    icon: '',
    color: '',
    sort_order: 0,
    is_shared: false,
});

const iconOptions = [
    { value: 'Search', label: '搜索' },
    { value: 'Key', label: '密钥' },
    { value: 'User', label: '用户' },
    { value: 'Goods', label: '产品' },
    { value: 'Document', label: '文档' },
    { value: 'Coin', label: '订阅' },
    { value: 'ChatDotSquare', label: '工单' },
    { value: 'Star', label: '星标' },
    { value: 'StarFilled', label: '热门' },
];

const colorPresets = ['#409eff', '#67c23a', '#e6a23c', '#f56c6c', '#909399', '#b37feb', '#36cfc9', '#ff85c0'];

function pageLabel(page) {
    const labels = {
        licenses: '许可证', customers: '客户', tickets: '工单',
        products: '产品', invoices: '发票', subscriptions: '订阅', global: '全局搜索',
    };
    return labels[page] || page;
}

function pageIcon(page) {
    const icons = {
        licenses: 'Key', customers: 'User', tickets: 'ChatDotSquare',
        products: 'Goods', invoices: 'Document', subscriptions: 'Coin', global: 'Search',
    };
    return icons[page] || 'Search';
}

async function loadSavedSearches() {
    try {
        const [myRes, sharedRes, freqRes] = await Promise.all([
            advancedSearchApi.getMySavedSearches(),
            advancedSearchApi.getSharedSearches(),
            advancedSearchApi.getFrequentSearches(8),
        ]);
        if (myRes.data.success) mySavedSearches.value = myRes.data.data || [];
        if (sharedRes.data.success) sharedSearches.value = sharedRes.data.data || [];
        if (freqRes.data.success) frequentSearches.value = freqRes.data.data || [];
    } catch { /* ignore */ }
}

function applySaved(saved) {
    selectedPage.value = saved.page;
    loadFilterDefinitions();
    // Set filters
    resetAdvFilters();
    if (saved.filters) {
        Object.assign(currentAdvFilters, saved.filters);
    }
    // Record usage
    advancedSearchApi.applySavedSearch(saved.id).catch(() => {});
    // Switch to advanced tab and search
    activeTab.value = 'advanced';
    search();
}

function editSavedDialog(saved) {
    editForm.id = saved.id;
    editForm.name = saved.name || '';
    editForm.description = saved.description || '';
    editForm.icon = saved.icon || '';
    editForm.color = saved.color || '';
    editForm.sort_order = saved.sort_order || 0;
    editForm.is_shared = saved.is_shared || false;
    editDialogVisible.value = true;
}

async function saveEdit() {
    if (!editForm.id) return;
    editSaving.value = true;
    try {
        const { data: res } = await advancedSearchApi.updateSavedSearch(editForm.id, {
            name: editForm.name,
            description: editForm.description,
            icon: editForm.icon || null,
            color: editForm.color || null,
            sort_order: editForm.sort_order,
            is_shared: editForm.is_shared,
        });
        if (res.success) {
            ElMessage.success('已更新');
            editDialogVisible.value = false;
            await loadSavedSearches();
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '保存失败');
    } finally {
        editSaving.value = false;
    }
}

async function deleteSaved(saved) {
    try {
        const { data: res } = await advancedSearchApi.deleteSavedSearch(saved.id);
        if (res.success) {
            ElMessage.success('已删除');
            await loadSavedSearches();
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '删除失败');
    }
}

function formatDate(date) {
    if (!date) return '';
    const d = new Date(date);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

// ─── Init ───
async function init() {
    // Load dashboard stats
    try {
        const { data: res } = await globalSearchApi.getDashboard();
        if (res.success) {
            stats.indexed = res.data.stats?.total_indexed || 0;
            stats.recentCount = res.data.stats?.total_recent || 0;
            stats.bookmarkCount = res.data.stats?.total_bookmarks || 0;
        }
    } catch { /* ignore */ }

    // Load page definitions
    try {
        const { data: res } = await advancedSearchApi.getAllFilterDefinitions();
        if (res.success) {
            pageDefinitions.value = Object.entries(res.data || {}).map(([key, val]) => ({
                key,
                label: val.label,
                icon: val.icon || 'Search',
            }));
        }
    } catch { /* ignore */ }

    // Load saved searches
    await loadSavedSearches();

    // Load initial filter definitions
    loadFilterDefinitions();
}

onMounted(init);
</script>

<style scoped>
.search-center {
    padding: 16px 24px;
}

.page-header h2 {
    margin: 0 0 4px;
    font-size: 20px;
}
.page-header .text-muted {
    margin: 0 0 16px;
    color: var(--el-text-color-secondary);
    font-size: 13px;
}

.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.py-4 { padding-top: 16px; padding-bottom: 16px; }
.py-8 { padding-top: 32px; padding-bottom: 32px; }
.text-center { text-align: center; }
.text-muted { color: var(--el-text-color-placeholder); }
.ml-2 { margin-left: 8px; }

.stat-card {
    text-align: center;
    padding: 8px 0;
}
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--el-color-primary);
}
.stat-label {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-top: 4px;
}

.quick-search-area {
    max-width: 800px;
    margin: 0 auto;
}
.quick-type-filters {
    margin-top: 12px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.quick-results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.quick-result-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.quick-result-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.2s;
}
.quick-result-item:hover {
    background: var(--el-fill-color-light);
}
.quick-result-info {
    flex: 1;
    min-width: 0;
}
.quick-result-title {
    font-weight: 500;
    font-size: 14px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.quick-result-desc {
    font-size: 12px;
    color: var(--el-text-color-secondary);
}

.adv-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-menu {
    border-right: none;
}

.filter-item {
    margin-bottom: 0;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.saved-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.saved-item {
    border: 1px solid var(--el-border-color-light);
    border-left: 3px solid var(--el-color-primary);
    border-radius: 6px;
    padding: 12px;
    transition: box-shadow 0.2s;
}
.saved-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.saved-item.shared {
    border-left-color: var(--el-color-warning);
}
.saved-item-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}
.saved-item-desc {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-bottom: 6px;
}
.saved-item-meta {
    display: flex;
    gap: 12px;
    font-size: 12px;
    color: var(--el-text-color-placeholder);
    margin-bottom: 8px;
}
.saved-item-actions {
    display: flex;
    gap: 6px;
}

.frequent-card {
    cursor: pointer;
    text-align: center;
    padding: 16px 0;
    transition: transform 0.2s, box-shadow 0.2s;
}
.frequent-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.frequent-icon {
    margin-bottom: 8px;
}
.frequent-name {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 4px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.frequent-page {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-bottom: 4px;
}
.frequent-count {
    font-size: 11px;
    color: var(--el-text-color-placeholder);
}

.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    padding: 16px 0 0;
}
</style>
