<template>
    <div class="search-center">
        <el-tabs v-model="searchMainTab" type="border-card">
            <!-- ── Tab 1: 全局搜索 ── -->
            <el-tab-pane :label="t('search_center_page.tabs.global')" name="global">
                <div class="page-header">
                    <h2>{{ t('search_center_page.title') }}</h2>
                    <p class="text-muted">{{ t('search_center_page.subtitle') }}</p>
                </div>

                <!-- ── 搜索概况 ── -->
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never" class="stat-card">
                            <div class="stat-value">{{ stats.indexed }}</div>
                            <div class="stat-label">{{ t('search_center_page.stats.indexed') }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never" class="stat-card">
                            <div class="stat-value">{{ stats.savedCount }}</div>
                            <div class="stat-label">{{ t('search_center_page.stats.saved') }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never" class="stat-card">
                            <div class="stat-value">{{ stats.recentCount }}</div>
                            <div class="stat-label">{{ t('search_center_page.stats.recent') }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never" class="stat-card">
                            <div class="stat-value">{{ stats.bookmarkCount }}</div>
                            <div class="stat-label">{{ t('search_center_page.stats.bookmarks') }}</div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-tabs v-model="activeTab">
                    <!-- ── 快速搜索 ── -->
                    <el-tab-pane :label="t('search_center_page.tabs.quick')" name="quick">
                        <el-card shadow="never">
                            <div class="quick-search-area">
                                <el-input
                                    v-model="quickQuery"
                                    :placeholder="t('search_center_page.quick.placeholder')"
                                    size="large"
                                    clearable
                                    @keyup.enter="doQuickSearch"
                                >
                                    <template #prefix>
                                        <el-icon><Search /></el-icon>
                                    </template>
                                    <template #append>
                                        <el-button @click="doQuickSearch" :icon="Search">{{ t('actions.search') }}</el-button>
                                    </template>
                                </el-input>

                                <div class="quick-type-filters">
                                    <el-checkbox-group v-model="quickSearchTypes">
                                        <el-checkbox v-for="typeItem in searchableTypes" :key="typeItem.value" :label="typeItem.value" border size="small">
                                            {{ typeItem.label }}
                                        </el-checkbox>
                                    </el-checkbox-group>
                                </div>

                                <div v-if="quickResults.length > 0" class="quick-results">
                                    <el-divider />
                                    <div class="quick-results-header">
                                        <span>{{ t('global_search.results_header', { total: quickTotal }) }}</span>
                                        <el-button text size="small" @click="openGlobalSearch">{{ t('search_center_page.quick.view_in_global') }}</el-button>
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

                                <el-empty v-else-if="searched && quickResults.length === 0" :description="t('search_center_page.quick.no_results')" />
                            </div>
                        </el-card>
                    </el-tab-pane>

                    <!-- ── 高级筛选 ── -->
                    <el-tab-pane :label="t('search_center_page.tabs.advanced')" name="advanced">
                        <el-row :gutter="16">
                            <el-col :span="6">
                                <el-card shadow="never">
                                    <template #header>
                                        <span>{{ t('search_center_page.advanced.select_module') }}</span>
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
                                            <span>{{ t('search_center_page.advanced.filter_title', { page: currentPageLabel }) }}</span>
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
                                        {{ t('search_center_page.advanced.no_filters') }}
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
                                                            :placeholder="def.placeholder || t('search_center_page.advanced.ph_input')"
                                                            clearable
                                                            @clear="debouncedSearch"
                                                            @input="debouncedSearch"
                                                        />
                                                        <!-- 下拉选择(单选) -->
                                                        <el-select
                                                            v-else-if="def.type === 'select' && !def.multiple"
                                                            v-model="currentAdvFilters[field]"
                                                            :placeholder="def.placeholder || t('search_center_page.advanced.ph_select')"
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
                                                            :placeholder="def.placeholder || t('search_center_page.advanced.ph_select_multi')"
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
                                                            :placeholder="t('search_center_page.advanced.select_field', { label: def.label })"
                                                            filterable
                                                            clearable
                                                            style="width:100%"
                                                            @change="search"
                                                        >
                                                            <el-option :label="t('search_center_page.advanced.model_fetch_hint')" value="" disabled />
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
                                                <el-button type="primary" :icon="Search" @click="search">{{ t('actions.search') }}</el-button>
                                                <el-button @click="resetAdvFilters">{{ t('actions.reset') }}</el-button>
                                            </el-col>
                                        </el-row>
                                    </el-form>
                                </el-card>
                            </el-col>
                        </el-row>

                        <!-- 高级搜索结果 -->
                        <el-card v-if="advResults.items && advResults.items.length > 0" shadow="never" class="mt-4">
                            <template #header>
                                <span>{{ t('global_search.results_header', { total: advResults.total }) }}</span>
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
                        <el-empty v-else-if="advSearched && advResults.items?.length === 0" :description="t('search_center_page.advanced.no_results')" />
                    </el-tab-pane>

                    <!-- ── 保存的搜索 ── -->
                    <el-tab-pane :label="t('search_center_page.tabs.saved')" name="saved">
                        <el-row :gutter="16">
                            <el-col :span="12">
                                <el-card shadow="never">
                                    <template #header>
                                        <div class="card-header">
                                            <span>{{ t('search_center_page.saved.my_searches') }}</span>
                                            <el-tag size="small">{{ mySavedSearches.length }}</el-tag>
                                        </div>
                                    </template>
                                    <div v-if="mySavedSearches.length === 0" class="text-muted text-center py-4">
                                        {{ t('search_center_page.saved.empty_my') }}
                                    </div>
                                    <div v-else class="saved-list">
                                        <div v-for="s in mySavedSearches" :key="s.id" class="saved-item" :style="s.color ? { borderLeftColor: s.color } : {}">
                                            <div class="saved-item-header">
                                                <el-icon v-if="s.icon" :size="16"><component :is="s.icon" /></el-icon>
                                                <el-icon v-else :size="16"><Search /></el-icon>
                                                <strong>{{ s.name }}</strong>
                                                <el-tag v-if="s.is_shared" size="small" type="warning">{{ t('search_center_page.saved.shared_tag') }}</el-tag>
                                            </div>
                                            <div v-if="s.description" class="saved-item-desc">{{ s.description }}</div>
                                            <div class="saved-item-meta">
                                                <span>{{ pageLabel(s.page) }}</span>
                                                <span>{{ t('search_center_page.saved.usage_count', { n: s.usage_count }) }}</span>
                                                <span v-if="s.last_used_at">{{ t('search_center_page.saved.last_used', { date: formatDate(s.last_used_at) }) }}</span>
                                            </div>
                                            <div class="saved-item-actions">
                                                <el-button size="small" type="primary" plain @click="applySaved(s)">{{ t('search_center_page.saved.apply') }}</el-button>
                                                <el-button size="small" @click="editSavedDialog(s)">{{ t('actions.edit') }}</el-button>
                                                <el-popconfirm :title="t('search_center_page.saved.confirm_delete')" @confirm="deleteSaved(s)">
                                                    <template #reference>
                                                        <el-button size="small" type="danger" plain>{{ t('actions.delete') }}</el-button>
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
                                            <span>{{ t('search_center_page.saved.team_shared') }}</span>
                                            <el-tag size="small">{{ sharedSearches.length }}</el-tag>
                                        </div>
                                    </template>
                                    <div v-if="sharedSearches.length === 0" class="text-muted text-center py-4">
                                        {{ t('search_center_page.saved.empty_shared') }}
                                    </div>
                                    <div v-else class="saved-list">
                                        <div v-for="s in sharedSearches" :key="s.id" class="saved-item shared">
                                            <div class="saved-item-header">
                                                <el-icon :size="16"><Share /></el-icon>
                                                <strong>{{ s.name }}</strong>
                                                <el-tag size="small">{{ s.user?.name || t('notes.unknown_user') }}</el-tag>
                                            </div>
                                            <div class="saved-item-meta">
                                                <span>{{ pageLabel(s.page) }}</span>
                                                <span>{{ t('search_center_page.saved.usage_count', { n: s.usage_count }) }}</span>
                                            </div>
                                            <div class="saved-item-actions">
                                                <el-button size="small" type="primary" plain @click="applySaved(s)">{{ t('search_center_page.saved.apply') }}</el-button>
                                            </div>
                                        </div>
                                    </div>
                                </el-card>
                            </el-col>
                        </el-row>
                    </el-tab-pane>

                    <!-- ── 常用搜索 ── -->
                    <el-tab-pane :label="t('search_center_page.tabs.frequent')" name="frequent">
                        <el-row :gutter="16">
                            <el-col v-for="s in frequentSearches" :key="s.id" :span="6" class="mb-4">
                                <el-card shadow="hover" class="frequent-card" :style="s.color ? { borderTop: `3px solid ${s.color}` } : {}" @click="applySaved(s)">
                                    <div class="frequent-icon">
                                        <el-icon :size="24" :color="s.color || '#0f172a'">
                                            <component :is="pageIcon(s.page)" />
                                        </el-icon>
                                    </div>
                                    <div class="frequent-name">{{ s.name }}</div>
                                    <div class="frequent-page">{{ pageLabel(s.page) }}</div>
                                    <div class="frequent-count">{{ t('search_center_page.frequent.used_count', { n: s.usage_count }) }}</div>
                                </el-card>
                            </el-col>
                            <el-col v-if="frequentSearches.length === 0" :span="24">
                                <el-empty :description="t('search_center_page.frequent.empty')" />
                            </el-col>
                        </el-row>
                    </el-tab-pane>
                </el-tabs>

                <!-- 编辑保存搜索对话框 -->
                <el-dialog v-model="editDialogVisible" :title="t('search_center_page.edit_dialog.title')" width="500px">
                    <el-form label-position="top" size="small">
                        <el-form-item :label="t('saved_search.name')">
                            <el-input v-model="editForm.name" maxlength="100" />
                        </el-form-item>
                        <el-form-item :label="t('search_center_page.edit_dialog.description')">
                            <el-input v-model="editForm.description" maxlength="200" type="textarea" :rows="2" />
                        </el-form-item>
                        <el-form-item :label="t('search_center_page.edit_dialog.icon')">
                            <el-select v-model="editForm.icon" clearable filterable :placeholder="t('search_center_page.edit_dialog.select_icon')">
                                <el-option v-for="icon in iconOptions" :key="icon.value" :label="icon.label" :value="icon.value">
                                    <el-icon :size="16"><component :is="icon.value" /></el-icon>
                                    <span class="ml-2">{{ icon.label }}</span>
                                </el-option>
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t('search_center_page.edit_dialog.color')">
                            <el-color-picker v-model="editForm.color" show-alpha :predefine="colorPresets" />
                        </el-form-item>
                        <el-form-item :label="t('search_center_page.edit_dialog.sort_order')">
                            <el-input-number v-model="editForm.sort_order" :min="0" />
                        </el-form-item>
                        <el-form-item>
                            <el-checkbox v-model="editForm.is_shared">{{ t('saved_search.share') }}</el-checkbox>
                        </el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="editDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                        <el-button type="primary" :loading="editSaving" @click="saveEdit">{{ t('actions.save') }}</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <!-- ── Tab 2: 保存搜索管理 ── -->
            <el-tab-pane :label="t('search_center_page.tabs.saved_mgmt')" name="saved">
                <template v-if="ss_tabVisited">
                    <div class="ss-content">
                        <el-row :gutter="20" class="mb-4">
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="text-3xl font-bold text-gray-800">{{ ss_stats.total ?? '-' }}</div>
                                    <div class="text-sm text-gray-500 mt-1">{{ t('saved_search_page.stats.total') }}</div>
                                </el-card>
                            </el-col>
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="text-3xl font-bold text-primary">{{ ss_stats.shared ?? '-' }}</div>
                                    <div class="text-sm text-gray-500 mt-1">{{ t('saved_search_page.stats.shared') }}</div>
                                </el-card>
                            </el-col>
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="text-3xl font-bold text-success">{{ ss_stats.active_users ?? '-' }}</div>
                                    <div class="text-sm text-gray-500 mt-1">{{ t('saved_search_page.stats.active_users') }}</div>
                                </el-card>
                            </el-col>
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="text-3xl font-bold text-warning">{{ ss_stats.most_used_page ?? '-' }}</div>
                                    <div class="text-sm text-gray-500 mt-1">{{ t('saved_search_page.stats.most_used_page') }}</div>
                                </el-card>
                            </el-col>
                        </el-row>

                        <el-card>
                            <template #header>
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold">{{ t('saved_search_page.list_title') }}</span>
                                    <el-select v-model="ss_pageFilter" :placeholder="t('saved_search_page.filter_page_ph')" clearable style="width:180px" @change="ss_fetchList">
                                        <el-option v-for="(label, key) in ss_pages" :key="key" :label="label" :value="key" />
                                    </el-select>
                                </div>
                            </template>

                            <el-table :data="ss_list" v-loading="ss_loading" stripe>
                                <el-table-column prop="name" :label="t('saved_search.name')" min-width="180">
                                    <template #default="{ row }">
                                        <div class="flex items-center gap-2">
                                            <el-icon v-if="row.icon" :color="row.color || '#0f172a'"><component :is="row.icon" /></el-icon>
                                            <span class="font-medium">{{ row.name }}</span>
                                            <el-tag v-if="row.is_shared" type="success" size="small">{{ t('saved_search_page.shared_badge') }}</el-tag>
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="description" :label="t('saved_search_page.col_description')" min-width="160" show-overflow-tooltip />
                                <el-table-column :label="t('saved_search_page.col_page')" width="120">
                                    <template #default="{ row }">{{ ss_pages[row.page] || row.page }}</template>
                                </el-table-column>
                                <el-table-column :label="t('saved_search_page.col_creator')" width="120">
                                    <template #default="{ row }">{{ row.user?.name || row.user?.email || '#' + row.user_id }}</template>
                                </el-table-column>
                                <el-table-column prop="usage_count" :label="t('saved_search_page.col_usage_count')" width="80" align="center" />
                                <el-table-column prop="created_at" :label="t('saved_search_page.col_created_at')" width="160" />
                                <el-table-column prop="last_used_at" :label="t('saved_search_page.col_last_used')" width="160">
                                    <template #default="{ row }">{{ row.last_used_at || '-' }}</template>
                                </el-table-column>
                                <el-table-column :label="t('saved_search_page.col_actions')" width="120" fixed="right">
                                    <template #default="{ row }">
                                        <el-button size="small" type="danger" @click="ss_handleDelete(row)">{{ t('actions.delete') }}</el-button>
                                    </template>
                                </el-table-column>
                            </el-table>

                            <div class="mt-4 flex justify-center" v-if="ss_total > ss_perPage">
                                <el-pagination v-model:current-page="ss_currentPage" :page-size="ss_perPage" :total="ss_total"
                                    layout="prev, pager, next" @current-change="ss_onPageChange" />
                            </div>
                        </el-card>
                    </div>
                </template>
            </el-tab-pane>

            <!-- ── Tab 3: 商品搜索管理 ── -->
            <el-tab-pane :label="t('search_center_page.tabs.product_mgmt')" name="product">
                <template v-if="ps_tabVisited">
                    <div class="ps-content">
                        <!-- 统计卡片 -->
                        <el-row :gutter="20" class="mb-4">
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="text-3xl font-bold text-gray-800">{{ ps_stats.total_searches ?? '-' }}</div>
                                    <div class="text-sm text-gray-500 mt-1">{{ t('product_search_page.stats.total_searches') }}</div>
                                </el-card>
                            </el-col>
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="text-3xl font-bold text-primary">{{ ps_stats.today_searches ?? '-' }}</div>
                                    <div class="text-sm text-gray-500 mt-1">{{ t('product_search_page.stats.today_searches') }}</div>
                                </el-card>
                            </el-col>
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="text-3xl font-bold text-warning">{{ ps_stats.unique_terms ?? '-' }}</div>
                                    <div class="text-sm text-gray-500 mt-1">{{ t('product_search_page.stats.unique_terms') }}</div>
                                </el-card>
                            </el-col>
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="text-3xl font-bold text-success">{{ ps_stats.zero_result_rate ?? '0%' }}</div>
                                    <div class="text-sm text-gray-500 mt-1">{{ t('product_search_page.stats.zero_result_rate') }}</div>
                                </el-card>
                            </el-col>
                        </el-row>

                        <el-row :gutter="20">
                            <!-- 热门搜索词 -->
                            <el-col :span="12">
                                <el-card class="mb-4">
                                    <template #header><span class="font-semibold">{{ t('product_search_page.hot_terms_title') }}</span></template>
                                    <div v-if="ps_hotTerms.length">
                                        <div v-for="(term, i) in ps_hotTerms" :key="i"
                                            class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                                            <div class="flex items-center gap-3">
                                                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                                                    :class="ps_rankClass(i)">{{ i + 1 }}</span>
                                                <span>{{ term.term }}</span>
                                            </div>
                                            <span class="text-sm text-gray-500">{{ t('product_search_page.count_fmt', { n: term.count }) }}</span>
                                        </div>
                                    </div>
                                    <el-empty v-else :description="t('messages.no_data')" :image-size="60" />
                                </el-card>

                                <!-- 无结果搜索词 -->
                                <el-card>
                                    <template #header><span class="font-semibold">{{ t('product_search_page.zero_result_title') }}</span></template>
                                    <div v-if="ps_zeroResultTerms.length">
                                        <div v-for="(term, i) in ps_zeroResultTerms" :key="i"
                                            class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                                            <span>{{ term.term }}</span>
                                            <el-tag type="danger" size="small">{{ t('product_search_page.count_fmt', { n: term.count }) }}</el-tag>
                                        </div>
                                    </div>
                                    <el-empty v-else :description="t('product_search_page.empty_zero_result')" :image-size="60" />
                                </el-card>
                            </el-col>

                            <!-- 搜索配置 -->
                            <el-col :span="12">
                                <el-card class="mb-4">
                                    <template #header><span class="font-semibold">{{ t('product_search_page.config_title') }}</span></template>
                                    <el-form label-position="left" label-width="140px">
                                        <el-form-item :label="t('product_search_page.config.engine')">
                                            <el-tag :type="ps_config.engine === 'meilisearch' ? 'success' : 'info'">
                                                {{ ps_engineLabel(ps_config.engine) }}
                                            </el-tag>
                                        </el-form-item>
                                        <el-form-item :label="t('product_search_page.config.per_page')">
                                            <span>{{ ps_config.search?.per_page }}</span>
                                        </el-form-item>
                                        <el-form-item :label="t('product_search_page.config.sort_options')">
                                            <div class="flex flex-wrap gap-1">
                                                <el-tag v-for="(opt, key) in ps_config.sort_options" :key="key" size="small">
                                                    {{ opt.label }}
                                                </el-tag>
                                            </div>
                                        </el-form-item>
                                        <el-form-item :label="t('product_search_page.config.filters')">
                                            <div class="flex flex-wrap gap-1">
                                                <el-tag v-for="(f, key) in ps_config.filters" :key="key"
                                                    :type="f.enabled ? 'success' : 'danger'" size="small">
                                                    {{ f.label }}
                                                </el-tag>
                                            </div>
                                        </el-form-item>
                                        <el-form-item :label="t('product_search_page.config.hot_terms')">
                                            <span>{{ ps_hotTermsSummary }}</span>
                                        </el-form-item>
                                    </el-form>
                                </el-card>

                                <!-- 搜索日志 -->
                                <el-card>
                                    <template #header>
                                        <div class="flex items-center justify-between">
                                            <span class="font-semibold">{{ t('product_search_page.logs_title') }}</span>
                                            <el-button size="small" @click="ps_fetchLogs">{{ t('product_search_page.refresh') }}</el-button>
                                        </div>
                                    </template>
                                    <el-table :data="ps_logs" v-loading="ps_logLoading" stripe max-height="300" size="small">
                                        <el-table-column prop="term" :label="t('product_search_page.columns.term')" min-width="120" />
                                        <el-table-column prop="result_count" :label="t('product_search_page.columns.result_count')" width="70" align="center">
                                            <template #default="{ row }">
                                                <el-tag v-if="row.result_count === 0" type="danger" size="small">0</el-tag>
                                                <span v-else>{{ row.result_count }}</span>
                                            </template>
                                        </el-table-column>
                                        <el-table-column prop="created_at" :label="t('product_search_page.columns.created_at')" width="150" />
                                    </el-table>
                                </el-card>
                            </el-col>
                        </el-row>
                    </div>
                </template>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Search, Share, Key, User, ChatDotSquare, Goods, Document, Coin,
    Star, StarFilled, Refresh, Delete, EditPen,
} from '@element-plus/icons-vue';
import globalSearchApi from '@/api/globalSearch';
import advancedSearchApi from '@/api/advancedSearch';
import savedSearchApi from '@/api/savedSearch';
import SavedSearchBar from '@/components/SavedSearchBar.vue';
import {
    getProductSearchStats,
    getProductSearchHotTerms,
    getProductSearchZeroResultTerms,
    getProductSearchConfig,
    getProductSearchLogs,
} from '@/api/productSearch';

const router = useRouter();
const { t } = useI18n();

// ─── Outer Tab ───
const searchMainTab = ref('global');

// ─── Tab lazy-load flags ───
const ss_tabVisited = ref(false);
const ps_tabVisited = ref(false);

watch(searchMainTab, (val) => {
    if (val === 'saved' && !ss_tabVisited.value) {
        ss_tabVisited.value = true;
        nextTick(() => {
            ss_fetchList();
        });
    }
    if (val === 'product' && !ps_tabVisited.value) {
        ps_tabVisited.value = true;
        nextTick(() => {
            ps_fetchStats();
            ps_fetchHotTerms();
            ps_fetchZeroResultTerms();
            ps_fetchConfig();
            ps_fetchLogs();
        });
    }
});

// ─── Inner Tab ───
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

const searchableTypes = computed(() => [
    { value: 'license', label: t('global_search.type_license') },
    { value: 'customer', label: t('global_search.type_customer') },
    { value: 'ticket', label: t('global_search.type_ticket') },
    { value: 'product', label: t('global_search.type_product') },
    { value: 'invoice', label: t('global_search.type_invoice') },
    { value: 'subscription', label: t('global_search.type_subscription') },
]);

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
    const c = (key) => t(`search_center_page.columns.${key}`);
    const colMap = {
        licenses: [
            { key: 'id', label: c('id') },
            { key: 'license_key', label: c('license_key'), minWidth: 200 },
            { key: 'type', label: c('type') },
            { key: 'status', label: c('status') },
            { key: 'created_at', label: c('created_at'), minWidth: 160 },
        ],
        customers: [
            { key: 'id', label: c('id') },
            { key: 'name', label: c('name') },
            { key: 'email', label: c('email'), minWidth: 180 },
            { key: 'company', label: c('company') },
            { key: 'created_at', label: c('created_at'), minWidth: 160 },
        ],
        tickets: [
            { key: 'id', label: c('id') },
            { key: 'subject', label: c('subject'), minWidth: 200 },
            { key: 'status', label: c('status') },
            { key: 'priority', label: c('priority') },
            { key: 'created_at', label: c('created_at'), minWidth: 160 },
        ],
        products: [
            { key: 'id', label: c('id') },
            { key: 'name', label: c('name') },
            { key: 'price', label: c('price') },
            { key: 'is_active', label: c('is_active') },
            { key: 'created_at', label: c('created_at'), minWidth: 160 },
        ],
        invoices: [
            { key: 'id', label: c('id') },
            { key: 'invoice_no', label: c('invoice_no'), minWidth: 180 },
            { key: 'amount', label: c('amount') },
            { key: 'status', label: c('status') },
            { key: 'created_at', label: c('created_at'), minWidth: 160 },
        ],
        subscriptions: [
            { key: 'id', label: c('id') },
            { key: 'plan', label: c('plan'), minWidth: 150 },
            { key: 'status', label: c('status') },
            { key: 'billing_period', label: c('billing_period') },
            { key: 'created_at', label: c('created_at'), minWidth: 160 },
        ],
    };
    return colMap[selectedPage.value] || [{ key: 'id', label: c('id') }];
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

const iconOptions = computed(() => [
    { value: 'Search', label: t('search_center_page.icons.search') },
    { value: 'Key', label: t('search_center_page.icons.key') },
    { value: 'User', label: t('search_center_page.icons.user') },
    { value: 'Goods', label: t('search_center_page.icons.goods') },
    { value: 'Document', label: t('search_center_page.icons.document') },
    { value: 'Coin', label: t('search_center_page.icons.coin') },
    { value: 'ChatDotSquare', label: t('search_center_page.icons.ticket') },
    { value: 'Star', label: t('search_center_page.icons.star') },
    { value: 'StarFilled', label: t('search_center_page.icons.star_filled') },
]);

const colorPresets = ['#0f172a', '#67c23a', '#e6a23c', '#f56c6c', '#909399', '#b37feb', '#36cfc9', '#ff85c0'];

const pageLabels = computed(() => ({
    licenses: t('search_center_page.pages.licenses'),
    customers: t('search_center_page.pages.customers'),
    tickets: t('search_center_page.pages.tickets'),
    products: t('search_center_page.pages.products'),
    invoices: t('search_center_page.pages.invoices'),
    subscriptions: t('search_center_page.pages.subscriptions'),
    global: t('search_center_page.pages.global'),
}));

function pageLabel(page) {
    return pageLabels.value[page] || page;
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
    resetAdvFilters();
    if (saved.filters) {
        Object.assign(currentAdvFilters, saved.filters);
    }
    advancedSearchApi.applySavedSearch(saved.id).catch(() => {});
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
            ElMessage.success(t('saved_search.updated'));
            editDialogVisible.value = false;
            await loadSavedSearches();
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || t('saved_search.save_fail'));
    } finally {
        editSaving.value = false;
    }
}

async function deleteSaved(saved) {
    try {
        const { data: res } = await advancedSearchApi.deleteSavedSearch(saved.id);
        if (res.success) {
            ElMessage.success(t('saved_search.deleted'));
            await loadSavedSearches();
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || t('saved_search.delete_fail'));
    }
}

function formatDate(date) {
    if (!date) return '';
    const d = new Date(date);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

// ─── Saved Search Management (ss_) ───
const ss_list = ref([]);
const ss_loading = ref(false);
const ss_currentPage = ref(1);
const ss_perPage = ref(20);
const ss_total = ref(0);
const ss_pageFilter = ref('');
const ss_stats = ref({});

const SS_PAGE_KEYS = ['licenses', 'customers', 'products', 'devices', 'invoices', 'subscriptions', 'tickets'];

const SS_PAGE_I18N = {
    licenses: 'admin.menu.licenses',
    customers: 'admin.menu.customers',
    products: 'admin.menu.products',
    devices: 'admin.menu.devices',
    invoices: 'billing_page.tab_invoices',
    subscriptions: 'admin.menu.billing',
    tickets: 'admin.menu.tickets',
};

const ss_pages = computed(() => Object.fromEntries(
    SS_PAGE_KEYS.map((key) => [key, t(SS_PAGE_I18N[key])]),
));

async function ss_fetchStats() {
    try {
        const res = await savedSearchApi.list({ page: '', per_page: 1 });
        const data = res.data;
        ss_stats.value = {
            total: data.total || 0,
            shared: ss_list.value.filter(s => s.is_shared).length,
            active_users: new Set(ss_list.value.map(s => s.user_id)).size,
            most_used_page: Object.entries(
                ss_list.value.reduce((acc, s) => { acc[s.page] = (acc[s.page] || 0) + 1; return acc; }, {})
            ).sort((a, b) => b[1] - a[1])[0]?.[0] || '-',
        };
    } catch { /* ignore */ }
}

async function ss_fetchList() {
    ss_loading.value = true;
    try {
        const params = { page: ss_currentPage.value, per_page: ss_perPage.value };
        if (ss_pageFilter.value) params.page = ss_pageFilter.value;
        const res = await savedSearchApi.list(params);
        ss_list.value = res.data.data || [];
        ss_total.value = res.data.total || 0;
    } catch { ss_list.value = []; }
    finally { ss_loading.value = false; }
}

async function ss_handleDelete(row) {
    try {
        await ElMessageBox.confirm(
            t('saved_search_page.delete_confirm', { name: row.name }),
            t('actions.confirm'),
            { type: 'warning' },
        );
        await savedSearchApi.destroy(row.id);
        ElMessage.success(t('saved_search.deleted'));
        await ss_fetchList();
        await ss_fetchStats();
    } catch { /* cancelled */ }
}

function ss_onPageChange(page) {
    ss_currentPage.value = page;
    ss_fetchList();
}

// ─── Product Search Management (ps_) ───
const ps_stats = ref({});
const ps_hotTerms = ref([]);
const ps_zeroResultTerms = ref([]);
const ps_config = ref({});
const ps_logs = ref([]);
const ps_logLoading = ref(false);

const ps_engineMap = computed(() => ({
    meilisearch: t('product_search_page.engine.meilisearch'),
    database: t('product_search_page.engine.database'),
}));

const ps_hotTermsSummary = computed(() => {
    const limit = ps_config.value.logging?.hot_terms_limit ?? 20;
    const minutes = (ps_config.value.logging?.hot_terms_cache_ttl ?? 3600) / 60;
    return t('product_search_page.config.hot_terms_summary', { limit, minutes });
});

function ps_engineLabel(engine) {
    return ps_engineMap.value[engine] ?? ps_engineMap.value.database;
}

function ps_rankClass(i) {
    if (i === 0) return 'bg-red-500 text-white';
    if (i === 1) return 'bg-orange-500 text-white';
    if (i === 2) return 'bg-yellow-500 text-white';
    return 'bg-gray-100 text-gray-600';
}

async function ps_fetchStats() {
    try {
        const res = await getProductSearchStats();
        ps_stats.value = res.data;
    } catch { /* ignore */ }
}

async function ps_fetchHotTerms() {
    try {
        const res = await getProductSearchHotTerms();
        ps_hotTerms.value = res.data.data || [];
    } catch { ps_hotTerms.value = []; }
}

async function ps_fetchZeroResultTerms() {
    try {
        const res = await getProductSearchZeroResultTerms();
        ps_zeroResultTerms.value = res.data.data || [];
    } catch { ps_zeroResultTerms.value = []; }
}

async function ps_fetchConfig() {
    try {
        const res = await getProductSearchConfig();
        ps_config.value = res.data;
    } catch { ps_config.value = {}; }
}

async function ps_fetchLogs() {
    ps_logLoading.value = true;
    try {
        const res = await getProductSearchLogs({ per_page: 20 });
        ps_logs.value = res.data.data || [];
    } catch { ps_logs.value = []; }
    finally { ps_logLoading.value = false; }
}

// ─── Init ───
async function init() {
    try {
        const { data: res } = await globalSearchApi.getDashboard();
        if (res.success) {
            stats.indexed = res.data.stats?.total_indexed || 0;
            stats.recentCount = res.data.stats?.total_recent || 0;
            stats.bookmarkCount = res.data.stats?.total_bookmarks || 0;
        }
    } catch { /* ignore */ }

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

    await loadSavedSearches();

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

/* ─── Saved Search Management styles ─── */
.ss-content .text-primary { color: #0f172a; }
.ss-content .text-success { color: #67c23a; }
.ss-content .text-warning { color: #e6a23c; }

/* ─── Product Search Management styles ─── */
.ps-content .text-primary { color: #0f172a; }
.ps-content .text-warning { color: #e6a23c; }
.ps-content .text-success { color: #67c23a; }
</style>
