<template>
    <div class="products-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('products_page.title') }}</h2>
                <span class="header-subtitle">{{ t('products_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button type="primary" @click="openCreateDialog">
                    <el-icon><Plus /></el-icon>
                    {{ t('products_page.create_product') }}
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="stats-row" v-if="stats">
            <el-col :xs="12" :sm="12" :md="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats.total }}</div>
                    <div class="stat-label">{{ t('products_page.stat_total') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="12" :md="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value" style="color: var(--el-color-success);">{{ stats.active }}</div>
                    <div class="stat-label">{{ t('products_page.stat_active') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="12" :md="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value" style="color: var(--el-color-warning);">{{ stats.total_licenses }}</div>
                    <div class="stat-label">{{ t('products_page.stat_total_licenses') }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="12" :md="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value" style="font-size: 14px;">
                        <template v-if="stats.top_products && stats.top_products.length">
                            <el-tag
                                v-for="p in stats.top_products.slice(0, 3)"
                                :key="p.id"
                                size="small"
                                effect="plain"
                                style="margin: 1px 2px;"
                            >
                                {{ p.name }} ({{ p.licenses_count }})
                            </el-tag>
                        </template>
                        <span v-else class="text-muted">{{ t('products_page.no_data') }}</span>
                    </div>
                    <div class="stat-label">{{ t('products_page.stat_top_products') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="prodMainTab" class="main-tabs">
            <!-- ==================== Tab 1: 产品列表 ==================== -->
            <el-tab-pane :label="t('products_page.tab_products')" name="products">
                <!-- 搜索栏 -->
                <el-card shadow="never" class="filter-card">
                    <el-form :model="filters" inline>
                        <el-form-item :label="t('actions.search')">
                            <el-input
                                v-model="filters.search"
                                :placeholder="t('products_page.search_ph')"
                                clearable
                                style="width: 240px"
                                @keyup.enter="doSearch"
                            />
                        </el-form-item>
                        <el-form-item :label="t('products_page.status')">
                            <el-select v-model="filters.is_active" clearable :placeholder="t('products_page.all')" style="width: 110px" @change="doSearch">
                                <el-option
                                    v-for="opt in statusFilterOptions"
                                    :key="String(opt.value)"
                                    :label="opt.label"
                                    :value="opt.value"
                                />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="doSearch">
                                <el-icon><Search /></el-icon>
                                {{ t('actions.search') }}
                            </el-button>
                            <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>

                <!-- 批量操作 -->
                <div class="batch-bar" v-if="selectedIds.length > 0">
                    <span class="batch-info">{{ t('products_page.selected_count', { n: selectedIds.length }) }}</span>
                    <el-button size="small" type="success" @click="batchAction('activate')">{{ t('products_page.batch_activate') }}</el-button>
                    <el-button size="small" type="warning" @click="batchAction('deactivate')">{{ t('products_page.batch_deactivate') }}</el-button>
                    <el-button size="small" type="primary" @click="batchAction('set_sellable')">{{ t('products_page.set_sellable') }}</el-button>
                    <el-button size="small" @click="batchAction('set_not_sellable')">{{ t('products_page.set_not_sellable') }}</el-button>
                    <el-button size="small" type="danger" @click="batchAction('delete')">{{ t('products_page.batch_delete') }}</el-button>
                    <el-button size="small" text @click="selectedIds = []">{{ t('products_page.deselect') }}</el-button>
                </div>

                <!-- 表格 -->
                <el-card shadow="never">
                    <el-table
                        :data="products"
                        v-loading="loading"
                        stripe
                        row-key="id"
                        @sort-change="handleSortChange"
                        @selection-change="(sel) => selectedIds = sel.map(s => s.id)"
                    >
                        <el-table-column type="selection" width="45" />
                        <el-table-column :label="t('products_page.col_name')" min-width="180" prop="name" sortable="custom">
                            <template #default="{ row }">
                                <div class="product-name-cell">
                                    <el-avatar
                                        v-if="row.image_url"
                                        :size="32"
                                        shape="square"
                                        :src="row.image_url"
                                        style="flex-shrink: 0;"
                                    />
                                    <el-avatar
                                        v-else
                                        :size="32"
                                        shape="square"
                                        icon="Picture"
                                        style="flex-shrink: 0; background: var(--el-fill-color-light); color: var(--el-text-color-secondary);"
                                    />
                                    <el-link type="primary" @click="$router.push(`/products/${row.id}`)">
                                        {{ row.name }}
                                    </el-link>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('products_page.col_slug')" width="130" prop="slug" sortable="custom">
                            <template #default="{ row }">
                                <code>{{ row.slug }}</code>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('products_page.col_version')" width="100" prop="version" sortable="custom">
                            <template #default="{ row }">
                                {{ row.version || '-' }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('products_page.col_modules')" min-width="160">
                            <template #default="{ row }">
                                <template v-if="row.modules && row.modules.length">
                                    <el-tag
                                        v-for="mod in row.modules.slice(0, 3)"
                                        :key="mod"
                                        size="small"
                                        effect="plain"
                                        style="margin-right: 4px;"
                                    >
                                        {{ mod }}
                                    </el-tag>
                                    <el-tag v-if="row.modules.length > 3" size="small" type="info">
                                        +{{ row.modules.length - 3 }}
                                    </el-tag>
                                </template>
                                <span v-else class="text-muted">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('products_page.col_sellable')" width="70">
                            <template #default="{ row }">
                                <el-tag :type="row.is_sellable ? 'success' : 'info'" size="small" effect="plain">
                                    {{ row.is_sellable ? t('products_page.sellable_yes') : t('products_page.em_dash') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('products_page.col_featured')" width="70" align="center">
                            <template #default="{ row }">
                                <el-tag :type="row.is_featured ? 'warning' : 'info'" size="small" effect="plain">
                                    {{ row.is_featured ? t('products_page.featured_yes') : t('products_page.em_dash') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('products_page.col_price')" width="90" prop="base_price" sortable="custom">
                            <template #default="{ row }">
                                {{ row.base_price ? '¥' + row.base_price : t('products_page.em_dash') }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('products_page.col_status')" width="70" prop="is_active" sortable="custom">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                    {{ row.is_active ? t('products_page.status_active') : t('products_page.status_inactive') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="License" width="75" prop="licenses_count" sortable="custom">
                            <template #default="{ row }">
                                <el-tag type="primary" effect="plain" size="small">{{ row.licenses_count || 0 }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('products_page.col_created_at')" width="170" prop="created_at" sortable="custom">
                            <template #default="{ row }">
                                {{ formatDate(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('products_page.col_actions')" width="170" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="$router.push(`/products/${row.id}`)">
                                    {{ t('products_page.detail') }}
                                </el-button>
                                <el-button text size="small" type="primary" @click="openEditDialog(row)">
                                    {{ t('actions.edit') }}
                                </el-button>
                                <el-dropdown trigger="click" @command="(cmd) => handleAction(cmd, row)">
                                    <el-button text size="small" type="primary">
                                        {{ t('actions.more') }} <el-icon><ArrowDown /></el-icon>
                                    </el-button>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item command="clone">{{ t('products_page.clone') }}</el-dropdown-item>
                                            <el-dropdown-item
                                                v-if="row.is_active"
                                                command="deactivate"
                                            >
                                                {{ t('products_page.deactivate') }}
                                            </el-dropdown-item>
                                            <el-dropdown-item
                                                v-if="!row.is_active"
                                                command="activate"
                                            >
                                                {{ t('products_page.activate') }}
                                            </el-dropdown-item>
                                            <el-dropdown-item
                                                v-if="row.is_sellable"
                                                command="set_not_sellable"
                                            >
                                                {{ t('products_page.set_not_sellable') }}
                                            </el-dropdown-item>
                                            <el-dropdown-item
                                                v-if="!row.is_sellable"
                                                command="set_sellable"
                                            >
                                                {{ t('products_page.set_sellable') }}
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                            </template>
                        </el-table-column>
                    </el-table>

                    <!-- 分页 -->
                    <div class="pagination-wrapper" v-if="total > 0">
                        <el-pagination
                            v-model:current-page="page"
                            v-model:page-size="perPage"
                            :page-sizes="[10, 20, 50, 100]"
                            :total="total"
                            layout="total, sizes, prev, pager, next, jumper"
                            @size-change="loadProducts"
                            @current-change="loadProducts"
                        />
                    </div>
                </el-card>

                <!-- 创建/编辑 Dialog -->
                <el-dialog
                    v-model="dialogVisible"
                    :title="editingId ? t('products_page.edit_title') : t('products_page.create_title')"
                    width="780px"
                    :close-on-click-modal="false"
                    destroy-on-close
                >
                    <el-form
                        ref="formRef"
                        :model="form"
                        :rules="formRules"
                        label-position="top"
                        size="default"
                    >
                        <el-tabs type="border-card" class="product-form-tabs">
                            <el-tab-pane :label="t('products_page.tab_basic')">
                                <el-row :gutter="20">
                                    <el-col :span="12">
                                        <el-form-item :label="t('products_page.field_name')" prop="name">
                                            <el-input v-model="form.name" :placeholder="t('products_page.name_ph')" @input="autoGenerateSlug" />
                                        </el-form-item>
                                    </el-col>
                                    <el-col :span="12">
                                        <el-form-item :label="t('products_page.field_slug')" prop="slug">
                                            <el-input v-model="form.slug" :placeholder="t('products_page.slug_ph')">
                                                <template #append><el-button text @click="autoGenerateSlug(true)">{{ t('products_page.regenerate_slug') }}</el-button></template>
                                            </el-input>
                                        </el-form-item>
                                    </el-col>
                                </el-row>
                                <el-row :gutter="20">
                                    <el-col :span="8">
                                        <el-form-item :label="t('products_page.field_version')" prop="version">
                                            <el-input v-model="form.version" :placeholder="t('products_page.version_ph')" />
                                        </el-form-item>
                                    </el-col>
                                    <el-col :span="8">
                                        <el-form-item :label="t('products_page.field_category')" prop="category_id">
                                            <el-select v-model="form.category_id" clearable :placeholder="t('products_page.category_ph')" style="width:100%">
                                                <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
                                            </el-select>
                                        </el-form-item>
                                    </el-col>
                                    <el-col :span="8">
                                        <el-form-item :label="t('products_page.field_active')">
                                            <el-switch v-model="form.is_active" />
                                        </el-form-item>
                                    </el-col>
                                </el-row>
                                <el-form-item :label="t('products_page.field_description')" prop="description">
                                    <el-input v-model="form.description" type="textarea" :rows="2"
                                        :placeholder="t('products_page.description_ph')"
                                        maxlength="200" show-word-limit />
                                </el-form-item>
                            </el-tab-pane>

                            <el-tab-pane :label="t('products_page.tab_description')">
                                <el-form-item :label="t('products_page.field_long_description')" prop="long_description" style="width:100%">
                                    <div style="width:100%">
                                        <PlazaEditor v-model="form.long_description" :height="300"
                                            :placeholder="t('products_page.long_description_ph')" />
                                    </div>
                                    <div style="font-size:12px;color:#909399;margin-top:4px">{{ t('products_page.long_description_hint') }}</div>
                                </el-form-item>
                                <el-row :gutter="20" style="margin-top:16px">
                                    <el-col :span="12">
                                        <el-form-item :label="t('products_page.field_main_image')">
                                            <div class="image-upload-wrapper">
                                                <template v-if="form.image_url">
                                                    <div class="image-preview">
                                                        <el-image :src="form.image_url" fit="cover" style="width:120px;height:120px;border-radius:6px" />
                                                        <el-button class="image-remove-btn" size="small" type="danger" circle @click="form.image_url=''"><el-icon><Close /></el-icon></el-button>
                                                    </div>
                                                </template>
                                                <el-upload :show-file-list="false" :before-upload="handleMainImageUpload" accept="image/jpeg,image/png,image/gif,image/webp">
                                                    <el-button type="primary" plain size="small"><el-icon><Upload /></el-icon> {{ t('products_page.upload_main_image') }}</el-button>
                                                </el-upload>
                                            </div>
                                        </el-form-item>
                                    </el-col>
                                    <el-col :span="12">
                                        <el-form-item :label="t('products_page.field_carousel')">
                                            <div class="images-upload-wrapper">
                                                <div class="images-list" v-if="form.images && form.images.length">
                                                    <div v-for="(img, idx) in form.images" :key="idx" class="image-preview">
                                                        <el-image :src="img" fit="cover" style="width:72px;height:72px;border-radius:4px" />
                                                        <el-button class="image-remove-btn" size="small" type="danger" circle @click="form.images.splice(idx,1)"><el-icon><Close /></el-icon></el-button>
                                                    </div>
                                                </div>
                                                <el-upload multiple :show-file-list="false" :before-upload="handleCarouselImageUpload" accept="image/jpeg,image/png,image/gif,image/webp">
                                                    <el-button type="primary" plain size="small"><el-icon><Plus /></el-icon> {{ t('products_page.add_image') }}</el-button>
                                                </el-upload>
                                                <div style="font-size:11px;color:#909399;margin-top:4px">{{ t('products_page.carousel_hint') }}</div>
                                            </div>
                                        </el-form-item>
                                    </el-col>
                                </el-row>
                            </el-tab-pane>

                            <el-tab-pane :label="t('products_page.tab_advanced')">
                                <el-row :gutter="20">
                                    <el-col :span="12">
                                        <el-form-item :label="t('products_page.field_modules')" prop="modules">
                                            <el-select v-model="form.modules" multiple allow-create filterable default-first-option :placeholder="t('products_page.tag_input_ph')" style="width:100%">
                                                <el-option v-for="mod in moduleSuggestions" :key="mod" :label="mod" :value="mod" />
                                            </el-select>
                                        </el-form-item>
                                    </el-col>
                                    <el-col :span="12">
                                        <el-form-item :label="t('products_page.field_tags')">
                                            <el-select v-model="form.tags" multiple allow-create filterable default-first-option :placeholder="t('products_page.tag_input_ph')" style="width:100%">
                                                <el-option v-for="tag in tagSuggestions" :key="tag" :label="tag" :value="tag" />
                                            </el-select>
                                        </el-form-item>
                                    </el-col>
                                </el-row>
                                <div class="form-section-title" style="margin-top:8px">{{ t('products_page.section_ecommerce') }}</div>
                                <el-row :gutter="20">
                                    <el-col :span="8">
                                        <el-form-item :label="t('products_page.field_sellable')">
                                            <el-switch v-model="form.is_sellable" />
                                        </el-form-item>
                                    </el-col>
                                    <el-col :span="8">
                                        <el-form-item :label="t('products_page.field_featured')">
                                            <el-switch v-model="form.is_featured" />
                                        </el-form-item>
                                    </el-col>
                                    <el-col :span="8">
                                        <el-form-item :label="t('products_page.field_base_price')">
                                            <el-input-number v-model="form.base_price" :precision="2" :min="0" style="width:100%"><template #prefix>¥</template></el-input-number>
                                        </el-form-item>
                                    </el-col>
                                    <el-col :span="8">
                                        <el-form-item :label="t('products_page.field_sales_count')">
                                            <el-input-number v-model="form.sales_count" :min="0" style="width:100%" />
                                        </el-form-item>
                                    </el-col>
                                </el-row>
                            </el-tab-pane>
                        </el-tabs>
                    </el-form>
                    <template #footer>
                        <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                        <el-button type="primary" :loading="submitting" @click="submitForm">
                            {{ editingId ? t('actions.save') : t('actions.create') }}
                        </el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <!-- ==================== Tab 2: 产品分类 ==================== -->
            <el-tab-pane :label="t('products_page.tab_categories')" name="categories">
                <div v-if="pc_tabVisited" class="product-categories-tab">
                    <!-- 头部操作按钮 -->
                    <div class="category-header">
                        <div class="category-header-right">
                            <el-button @click="pc_handleExport">
                                <el-icon><Download /></el-icon>
                                {{ t('actions.export') }}
                            </el-button>
                            <el-button @click="pc_handleImport">
                                <el-icon><FolderOpened /></el-icon>
                                {{ t('actions.import') }}
                            </el-button>
                            <el-button @click="pc_openMergeDialog">{{ t('product_categories_page.merge_btn') }}</el-button>
                            <el-button type="primary" @click="pc_openCreateDialog">
                                <el-icon><Plus /></el-icon>
                                {{ t('product_categories_page.create_btn') }}
                            </el-button>
                        </div>
                    </div>

                    <!-- 统计卡片 -->
                    <el-row :gutter="16" class="stats-row">
                        <el-col :span="4">
                            <el-card shadow="never" class="stat-card">
                                <div class="stat-value">{{ pc_statsData?.total_categories ?? '—' }}</div>
                                <div class="stat-label">{{ t('product_categories_page.stat_total') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never" class="stat-card">
                                <div class="stat-value" style="color:#67c23a">{{ pc_statsData?.active ?? '—' }}</div>
                                <div class="stat-label">{{ t('product_categories_page.stat_active') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never" class="stat-card">
                                <div class="stat-value" style="color:#f56c6c">{{ pc_statsData?.inactive ?? '—' }}</div>
                                <div class="stat-label">{{ t('product_categories_page.stat_inactive') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never" class="stat-card">
                                <div class="stat-value">{{ pc_statsData?.root_count ?? '—' }}</div>
                                <div class="stat-label">{{ t('product_categories_page.stat_root') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never" class="stat-card">
                                <div class="stat-value">{{ pc_statsData?.max_depth ?? '—' }}</div>
                                <div class="stat-label">{{ t('product_categories_page.stat_max_depth') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never" class="stat-card">
                                <div class="stat-value">{{ pc_statsData?.categories_with_products ?? '—' }}</div>
                                <div class="stat-label">{{ t('product_categories_page.stat_with_products') }}</div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <!-- 批量操作栏 -->
                    <div class="batch-bar" v-if="pc_selectedIds.length > 0">
                        <span class="batch-info">{{ t('products_page.selected_count', { n: pc_selectedIds.length }) }}</span>
                        <el-button size="small" type="success" @click="pc_batchToggle(true)">{{ t('product_categories_page.batch_enable') }}</el-button>
                        <el-button size="small" type="warning" @click="pc_batchToggle(false)">{{ t('product_categories_page.batch_disable') }}</el-button>
                        <el-button size="small" type="danger" @click="pc_batchDelete">{{ t('products_page.batch_delete') }}</el-button>
                        <el-button size="small" text @click="pc_selectedIds = []">{{ t('products_page.deselect') }}</el-button>
                    </div>

                    <el-row :gutter="16">
                        <!-- 分类树 -->
                        <el-col :span="8">
                            <el-card shadow="never" class="tree-card">
                                <template #header>
                                    <div class="card-header">
                                        <span>{{ t('product_categories_page.tree_title') }}</span>
                                        <div>
                                            <el-button size="small" text @click="pc_expandAll">{{ t('product_categories_page.expand') }}</el-button>
                                            <el-button size="small" text @click="pc_collapseAll">{{ t('product_categories_page.collapse') }}</el-button>
                                            <el-tag size="small" type="info">{{ t('product_categories_page.root_count', { n: pc_categories.length }) }}</el-tag>
                                        </div>
                                    </div>
                                </template>
                                <el-input v-model="pc_searchQuery" :placeholder="t('product_categories_page.search_ph')" clearable size="small" style="margin-bottom:8px" @input="pc_onSearch" />
                                <el-tree
                                    ref="pc_treeRef"
                                    :data="pc_categories"
                                    :props="{ children: 'children', label: 'name' }"
                                    node-key="id"
                                    highlight-current
                                    :expand-on-click-node="false"
                                    draggable
                                    :allow-drag="() => true"
                                    @node-drag-end="pc_handleDragEnd"
                                    @node-click="pc_handleNodeClick"
                                    :filter-node-method="pc_filterNode"
                                >
                                    <template #default="{ data }">
                                        <span class="tree-node">
                                            <span class="tree-node-label">
                                                <el-icon v-if="data.icon" :size="14"><component :is="data.icon" /></el-icon>
                                                {{ data.name }}
                                            </span>
                                            <span class="tree-node-actions">
                                                <el-switch
                                                    v-if="data.products_count === 0"
                                                    v-model="data.is_active"
                                                    size="small"
                                                    style="margin-right:4px"
                                                    @click.stop
                                                    @change="(v) => pc_quickToggle(data, v)"
                                                />
                                                <el-tag v-if="!data.is_active" size="small" type="danger" effect="plain">{{ pc_statusLabels.inactive }}</el-tag>
                                                <el-tag size="small" type="info">{{ data.products_count ?? 0 }}</el-tag>
                                            </span>
                                        </span>
                                    </template>
                                </el-tree>
                            </el-card>
                        </el-col>

                        <!-- 分类详情/编辑 -->
                        <el-col :span="16">
                            <el-card v-if="pc_selectedCategory" shadow="never" class="detail-card">
                                <template #header>
                                    <div class="card-header">
                                        <div>
                                            <el-breadcrumb separator="/">
                                                <el-breadcrumb-item v-for="p in pc_categoryPath" :key="p.id">{{ p.name }}</el-breadcrumb-item>
                                            </el-breadcrumb>
                                        </div>
                                        <div>
                                            <el-tag size="small" type="info" style="margin-right:8px">{{ t('product_categories_page.product_count', { n: pc_selectedCategory.products_count ?? pc_categoryProducts.length }) }}</el-tag>
                                            <el-button size="small" @click="pc_openEditDialog(pc_selectedCategory)">{{ t('actions.edit') }}</el-button>
                                            <el-button size="small" type="danger" plain @click="pc_handleDelete(pc_selectedCategory)">{{ t('actions.delete') }}</el-button>
                                        </div>
                                    </div>
                                </template>

                                <el-descriptions :column="2" border>
                                    <el-descriptions-item :label="pc_descLabels.name">{{ pc_selectedCategory.name }}</el-descriptions-item>
                                    <el-descriptions-item :label="pc_descLabels.slug">{{ pc_selectedCategory.slug }}</el-descriptions-item>
                                    <el-descriptions-item :label="pc_descLabels.description" :span="2">{{ pc_selectedCategory.description || '—' }}</el-descriptions-item>
                                    <el-descriptions-item :label="pc_descLabels.icon">{{ pc_selectedCategory.icon || '—' }}</el-descriptions-item>
                                    <el-descriptions-item :label="pc_descLabels.sort_order">{{ pc_selectedCategory.sort_order }}</el-descriptions-item>
                                    <el-descriptions-item :label="pc_descLabels.parent">{{ pc_selectedCategory.parent?.name || '—' }}</el-descriptions-item>
                                    <el-descriptions-item :label="pc_descLabels.status">
                                        <el-tag :type="pc_selectedCategory.is_active ? 'success' : 'danger'" size="small">
                                            {{ pc_selectedCategory.is_active ? pc_statusLabels.active : pc_statusLabels.inactive }}
                                        </el-tag>
                                    </el-descriptions-item>
                                    <el-descriptions-item :label="pc_descLabels.children_count">{{ pc_selectedCategory.children?.length || 0 }}</el-descriptions-item>
                                    <el-descriptions-item :label="pc_descLabels.created_at">{{ pc_selectedCategory.created_at }}</el-descriptions-item>
                                    <el-descriptions-item :label="pc_descLabels.updated_at">{{ pc_selectedCategory.updated_at }}</el-descriptions-item>
                                </el-descriptions>

                                <!-- 分类下的产品列表 -->
                                <el-divider content-position="left">{{ t('product_categories_page.products_section') }}</el-divider>
                                <el-table v-loading="pc_productsLoading" :data="pc_categoryProducts" stripe style="width:100%">
                                    <el-table-column prop="name" :label="t('products_page.col_name')" min-width="160">
                                        <template #default="{ row }">
                                            <el-link type="primary" :underline="false" @click="$router.push('/products')">{{ row.name }}</el-link>
                                        </template>
                                    </el-table-column>
                                    <el-table-column prop="slug" :label="t('products_page.col_slug')" width="120" />
                                    <el-table-column :label="t('products_page.col_price')" width="100">
                                        <template #default="{ row }">¥{{ row.base_price ?? '—' }}</template>
                                    </el-table-column>
                                    <el-table-column :label="t('product_categories_page.col_sellable')" width="80">
                                        <template #default="{ row }">
                                            <el-tag :type="row.is_sellable ? 'success' : 'info'" size="small">{{ row.is_sellable ? t('products_page.sellable_yes') : t('products_page.em_dash') }}</el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column :label="t('products_page.col_status')" width="70">
                                        <template #default="{ row }">
                                            <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? pc_statusLabels.active : pc_statusLabels.inactive }}</el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column :label="t('product_categories_page.col_sales')" width="70" prop="sales_count" />
                                </el-table>
                                <div v-if="!pc_categoryProducts.length && !pc_productsLoading" style="text-align:center;padding:20px;color:#909399;">
                                    {{ t('product_categories_page.no_products') }}
                                </div>
                            </el-card>

                            <el-card v-else shadow="never" class="empty-card">
                                <el-empty :description="t('product_categories_page.empty_select')" />
                            </el-card>
                        </el-col>
                    </el-row>

                    <!-- 创建/编辑对话框 -->
                    <el-dialog
                        v-model="pc_dialogVisible"
                        :title="pc_isEditing ? t('product_categories_page.dialog_edit') : t('product_categories_page.dialog_create')"
                        width="520px"
                        :close-on-click-modal="false"
                    >
                        <el-form ref="pc_formRef" :model="pc_form" :rules="pc_rules" label-width="80px">
                            <el-form-item :label="t('product_categories_page.field_name')" prop="name">
                                <el-input v-model="pc_form.name" :placeholder="t('product_categories_page.name_ph')" maxlength="100" show-word-limit />
                            </el-form-item>
                            <el-form-item :label="t('product_categories_page.field_slug')" prop="slug">
                                <el-input v-model="pc_form.slug" :placeholder="t('product_categories_page.slug_auto_ph')" maxlength="100">
                                    <template #append>
                                        <el-button @click="pc_form.slug = $toSlug(pc_form.name)" :disabled="!pc_form.name">{{ t('product_categories_page.auto_btn') }}</el-button>
                                    </template>
                                </el-input>
                            </el-form-item>
                            <el-form-item :label="t('product_categories_page.field_description')" prop="description">
                                <el-input v-model="pc_form.description" type="textarea" :rows="3" maxlength="500" show-word-limit />
                            </el-form-item>
                            <el-form-item :label="t('product_categories_page.field_parent')" prop="parent_id">
                                <el-tree-select
                                    v-model="pc_form.parent_id"
                                    :data="pc_categoryOptions"
                                    :props="{ children: 'children', label: 'name', value: 'id' }"
                                    :placeholder="t('product_categories_page.parent_ph')"
                                    clearable
                                    check-strictly
                                    filterable
                                    style="width: 100%;"
                                />
                            </el-form-item>
                            <el-form-item :label="t('product_categories_page.field_image')">
                                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                    <template v-if="pc_form.image_url">
                                        <el-image :src="pc_form.image_url" fit="cover" style="width:60px;height:60px;border-radius:4px" />
                                        <el-button size="small" type="danger" circle @click="pc_form.image_url = ''">
                                            <el-icon><Close /></el-icon>
                                        </el-button>
                                    </template>
                                    <el-upload :show-file-list="false" :before-upload="pc_handleImageUpload" accept="image/*">
                                        <el-button size="small" type="primary" plain><el-icon><Upload /></el-icon> {{ t('product_categories_page.upload_image') }}</el-button>
                                    </el-upload>
                                </div>
                            </el-form-item>
                            <el-form-item :label="t('product_categories_page.seo_title')">
                                <el-input v-model="pc_form.meta_title" :placeholder="t('product_categories_page.seo_title_ph')" maxlength="160" show-word-limit />
                            </el-form-item>
                            <el-form-item :label="t('product_categories_page.seo_description')">
                                <el-input v-model="pc_form.meta_description" type="textarea" :rows="2" :placeholder="t('product_categories_page.seo_description_ph')" maxlength="500" show-word-limit />
                            </el-form-item>
                            <el-form-item :label="t('product_categories_page.field_icon')" prop="icon">
                                <el-input v-model="pc_form.icon" :placeholder="t('product_categories_page.icon_ph')" />
                            </el-form-item>
                            <el-form-item :label="t('product_categories_page.field_sort_order')" prop="sort_order">
                                <el-input-number v-model="pc_form.sort_order" :min="0" />
                            </el-form-item>
                            <el-form-item :label="t('product_categories_page.field_status')" prop="is_active">
                                <el-switch v-model="pc_form.is_active" :active-text="pc_statusLabels.active" :inactive-text="pc_statusLabels.inactive" />
                            </el-form-item>
                        </el-form>
                        <template #footer>
                            <el-button @click="pc_dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                            <el-button type="primary" @click="pc_handleSave" :loading="pc_saving">{{ t('actions.save') }}</el-button>
                        </template>
                    </el-dialog>

                    <!-- 合并分类对话框 -->
                    <el-dialog v-model="pc_mergeDialogVisible" :title="t('product_categories_page.merge_title')" width="480px">
                        <el-form label-width="100px">
                            <el-form-item :label="t('product_categories_page.merge_source')">
                                <el-tree-select
                                    v-model="pc_mergeForm.source_id"
                                    :data="pc_categoryOptions"
                                    :props="{ children: 'children', label: 'name', value: 'id' }"
                                    :placeholder="t('product_categories_page.merge_source_ph')"
                                    clearable
                                    filterable
                                    style="width:100%"
                                />
                            </el-form-item>
                            <el-form-item :label="t('product_categories_page.merge_target')">
                                <el-tree-select
                                    v-model="pc_mergeForm.target_id"
                                    :data="pc_categoryOptions"
                                    :props="{ children: 'children', label: 'name', value: 'id' }"
                                    :placeholder="t('product_categories_page.merge_target_ph')"
                                    clearable
                                    filterable
                                    style="width:100%"
                                />
                            </el-form-item>
                        </el-form>
                        <template #footer>
                            <el-button @click="pc_mergeDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                            <el-button type="warning" @click="pc_handleMerge">{{ t('product_categories_page.merge_confirm') }}</el-button>
                        </template>
                    </el-dialog>
                </div>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox, ElLoading } from 'element-plus';
import { Search, Plus, ArrowDown, Upload, Close, Download, FolderOpened } from '@element-plus/icons-vue';
import productApi from '@/api/product';
import productCategoryApi from '@/api/productCategory';
import PlazaEditor from '@/components/PlazaEditor.vue';

const { t, locale } = useI18n();

// ──────────────────── 主 Tab 状态 ────────────────────
const prodMainTab = ref('products');
const pc_tabVisited = ref(false);

// ──────────────────── 产品列表 — 状态 ────────────────────
const loading = ref(false);
const products = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);
const submitting = ref(false);
const dialogVisible = ref(false);
const editingId = ref(null);
const formRef = ref(null);
const stats = ref(null);
const selectedIds = ref([]);

const filters = reactive({
    search: '',
    is_active: '',
});
const sortField = ref('-created_at');

const moduleSuggestions = ['core', 'trial', 'offline', 'sso', 'mfa', 'audit', 'webhook', 'openfeature', 'api'];
const categories = ref([]);

const statusFilterOptions = computed(() => [
    { label: t('products_page.status_active'), value: true },
    { label: t('products_page.status_inactive'), value: false },
]);

const tagSuggestionKeys = ['tag_hot', 'tag_recommended', 'tag_new', 'tag_limited', 'tag_enterprise', 'tag_pro'];
const tagSuggestions = computed(() => tagSuggestionKeys.map((key) => t(`products_page.${key}`)));

const formRules = computed(() => ({
    name: [
        { required: true, message: t('products_page.name_required'), trigger: 'blur' },
        { max: 255, message: t('products_page.name_max'), trigger: 'blur' },
    ],
    slug: [
        { required: true, message: t('products_page.slug_required'), trigger: 'blur' },
        { max: 100, message: t('products_page.slug_max'), trigger: 'blur' },
    ],
}));

// ──────────────────── 产品列表 — 方法 ────────────────────
async function loadCategories() {
    try {
        const res = await productCategoryApi.options();
        categories.value = res.data?.data || res.data || [];
    } catch { categories.value = []; }
}

const form = reactive({
    name: '',
    slug: '',
    version: '',
    description: '',
    long_description: '',
    modules: [],
    is_active: true,
    is_sellable: false,
    is_featured: false,
    base_price: null,
    tags: [],
    image_url: '',
    images: [],
    category_id: null,
    sales_count: 0,
});

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(dateStr).toLocaleString(loc, {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

async function loadStats() {
    try {
        const { data: res } = await productApi.stats();
        if (res.success) stats.value = res.data;
    } catch {
        // ignore
    }
}

async function loadProducts() {
    loading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            sort: sortField.value,
        };
        if (filters.search) params.search = filters.search;
        if (filters.is_active !== '') params['filter.is_active'] = filters.is_active;

        const { data: res } = await productApi.list(params);
        products.value = Array.isArray(res.data) ? res.data : (res.data?.data || []);
        total.value = res.meta?.total || res.data?.total || 0;
    } catch {
        products.value = [];
    } finally {
        loading.value = false;
    }
}

async function batchAction(action) {
    try {
        const { data: res } = await productApi.batchAction(action, selectedIds.value);
        if (res.success) {
            ElMessage.success(res.message || t('products_page.batch_ok'));
            selectedIds.value = [];
            await loadProducts();
            await loadStats();
        }
    } catch {
        ElMessage.error(t('products_page.batch_fail'));
    }
}

function doSearch() {
    page.value = 1;
    loadProducts();
}

function resetFilters() {
    filters.search = '';
    filters.is_active = '';
    doSearch();
}

function handleSortChange({ prop, order }) {
    if (!order) {
        sortField.value = '-created_at';
    } else {
        sortField.value = (order === 'desc' ? '-' : '') + (prop || 'created_at');
    }
    loadProducts();
}

function openCreateDialog() {
    editingId.value = null;
    form.name = '';
    form.slug = '';
    form.version = '';
    form.description = '';
    form.long_description = '';
    form.modules = [];
    form.is_active = true;
    form.is_featured = false;
    form.image_url = '';
    form.images = [];
    form.category_id = null;
    dialogVisible.value = true;
}

function openEditDialog(row) {
    editingId.value = row.id;
    form.name = row.name;
    form.slug = row.slug;
    form.version = row.version || '';
    form.description = row.description || '';
    form.long_description = row.long_description || '';
    form.modules = row.modules || [];
    form.is_active = Boolean(row.is_active);
    form.is_sellable = Boolean(row.is_sellable);
    form.is_featured = Boolean(row.is_featured);
    form.base_price = row.base_price ?? null;
    form.sales_count = row.sales_count || 0;
    form.tags = row.tags || [];
    form.image_url = row.image_url || '';
    form.images = row.images ? [...row.images] : [];
    form.category_id = row.category_id || null;
    dialogVisible.value = true;
}

let _slugTimer = null;
function autoGenerateSlug(force = false) {
    if (_slugTimer) clearTimeout(_slugTimer);
    _slugTimer = setTimeout(() => {
        if (!form.name) return;
        if (form.slug && !force && !editingId.value) return;
        form.slug = form.name
            .toLowerCase()
            .replace(/[^a-z0-9\u4e00-\u9fa5]+/g, '-')
            .replace(/^-|-$/g, '')
            .substring(0, 100);
    }, 300);
}

async function handleMainImageUpload(file) {
    const fd = new FormData();
    fd.append('file', file);
    try {
        const { data: res } = await productApi.uploadImage(fd);
        if (res.success) {
            form.image_url = res.data.url;
        } else {
            ElMessage.error(res.message || t('products_page.upload_fail'));
        }
    } catch {
        ElMessage.error(t('products_page.upload_image_fail'));
    }
    return false;
}

async function handleCarouselImageUpload(file) {
    const fd = new FormData();
    fd.append('file', file);
    try {
        const { data: res } = await productApi.uploadImage(fd);
        if (res.success) {
            if (!form.images) form.images = [];
            form.images.push(res.data.url);
        } else {
            ElMessage.error(res.message || t('products_page.upload_fail'));
        }
    } catch {
        ElMessage.error(t('products_page.upload_image_fail'));
    }
    return false;
}

async function submitForm() {
    const valid = await formRef.value.validate().catch(() => false);
    if (!valid) return;

    submitting.value = true;
    try {
        const payload = {
            ...form,
            is_active: form.is_active ? 1 : 0,
        };
        if (editingId.value) {
            await productApi.update(editingId.value, payload);
            ElMessage.success(t('products_page.update_ok'));
        } else {
            await productApi.create(payload);
            ElMessage.success(t('products_page.create_ok'));
        }
        dialogVisible.value = false;
        loadProducts();
        loadStats();
    } catch {
        // error handled by interceptor
    } finally {
        submitting.value = false;
    }
}

async function handleAction(cmd, row) {
    if (cmd === 'activate') {
        try {
            await ElMessageBox.confirm(t('products_page.activate_confirm'), t('products_page.confirm_title'), {
                confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'info',
            });
            await productApi.update(row.id, { is_active: 1 });
            ElMessage.success(t('products_page.activated_ok'));
            loadProducts();
            loadStats();
        } catch { /* cancelled */ }
    } else if (cmd === 'deactivate') {
        try {
            await ElMessageBox.confirm(t('products_page.deactivate_confirm'), t('products_page.confirm_title'), {
                confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning',
            });
            await productApi.update(row.id, { is_active: 0 });
            ElMessage.success(t('products_page.deactivated_ok'));
            loadProducts();
            loadStats();
        } catch { /* cancelled */ }
    } else if (cmd === 'clone') {
        try {
            const { data: res } = await productApi.clone(row.id);
            if (res.success) { ElMessage.success(t('products_page.clone_ok')); loadProducts(); }
        } catch { ElMessage.error(t('products_page.clone_fail')); }
    } else if (cmd === 'set_sellable' || cmd === 'set_not_sellable') {
        try {
            await productApi.update(row.id, { is_sellable: cmd === 'set_sellable' });
            ElMessage.success(cmd === 'set_sellable' ? t('products_page.sellable_ok') : t('products_page.not_sellable_ok'));
            loadProducts();
        } catch { ElMessage.error(t('products_page.action_fail')); }
    }
}

// ──────────────────── 产品分类 — 状态（pc_ 前缀）────────────────────
const pc_statusLabels = computed(() => ({
    active: t('product_categories_page.status_active'),
    inactive: t('product_categories_page.status_inactive'),
}));

const pc_descLabels = computed(() => ({
    name: t('product_categories_page.field_name'),
    slug: t('product_categories_page.field_slug'),
    description: t('product_categories_page.field_description'),
    icon: t('product_categories_page.field_icon'),
    sort_order: t('product_categories_page.field_sort_order'),
    parent: t('product_categories_page.field_parent'),
    status: t('product_categories_page.field_status'),
    children_count: t('product_categories_page.field_children_count'),
    created_at: t('product_categories_page.field_created_at'),
    updated_at: t('product_categories_page.field_updated_at'),
}));

const pc_categories = ref([]);
const pc_categoryOptions = ref([]);
const pc_selectedCategory = ref(null);
const pc_categoryPath = ref([]);
const pc_categoryProducts = ref([]);
const pc_productsLoading = ref(false);
const pc_dialogVisible = ref(false);
const pc_isEditing = ref(false);
const pc_saving = ref(false);
const pc_treeRef = ref(null);
const pc_formRef = ref(null);
const pc_searchQuery = ref('');
const pc_selectedIds = ref([]);
const pc_statsData = ref(null);
const pc_mergeDialogVisible = ref(false);
const pc_mergeForm = ref({ source_id: null, target_id: null });

const pc_form = ref({
    name: '', slug: '', description: '', parent_id: null, icon: '',
    sort_order: 0, is_active: true, image_url: '', meta_title: '', meta_description: '',
});

const pc_rules = computed(() => ({
    name: [{ required: true, message: t('product_categories_page.rule_name_required'), trigger: 'blur' }],
}));

// ──────────────────── 产品分类 — 工具函数 ────────────────────
const $toSlug = (str) => {
    return str.toLowerCase().replace(/[^\w\u4e00-\u9fa5]+/g, '-').replace(/^-+|-+$/g, '');
};

function pc_unwrap(res) {
    const body = res?.data ?? res;
    return body?.data ?? body;
}

// ──────────────────── 产品分类 — 方法 ────────────────────
async function pc_loadCategories() {
    try {
        const res = await productCategoryApi.tree();
        pc_categories.value = pc_unwrap(res) || [];
    } catch (e) {
        ElMessage.error(t('product_categories_page.load_failed'));
    }
}

async function pc_loadOptions() {
    try {
        const res = await productCategoryApi.options();
        const flat = pc_unwrap(res) || [];
        const topLevel = flat.filter((c) => !c.parent_id);
        const buildTree = (items) =>
            items.map((item) => ({
                ...item,
                children: buildTree(flat.filter((c) => c.parent_id === item.id)),
            }));
        pc_categoryOptions.value = buildTree(topLevel);
    } catch (e) {
        // ignore
    }
}

function pc_handleNodeClick(data) {
    pc_loadCategoryDetail(data.id);
}

async function pc_loadCategoryDetail(id) {
    try {
        const [detailRes, productsRes] = await Promise.all([
            productCategoryApi.get(id),
            productCategoryApi.products(id),
        ]);
        pc_selectedCategory.value = pc_unwrap(detailRes);
        pc_categoryProducts.value = pc_unwrap(productsRes) || [];
        pc_loadPath(id);
    } catch (e) {
        ElMessage.error(t('product_categories_page.load_detail_failed'));
    }
}

async function pc_loadPath(id) {
    try {
        const res = await productCategoryApi.getPath(id);
        pc_categoryPath.value = pc_unwrap(res) || [];
    } catch { pc_categoryPath.value = []; }
}

function pc_openCreateDialog() {
    pc_isEditing.value = false;
    pc_form.value = { name: '', slug: '', description: '', parent_id: null, icon: '', sort_order: 0, is_active: true, image_url: '', meta_title: '', meta_description: '' };
    pc_dialogVisible.value = true;
}

function pc_openEditDialog(category) {
    pc_isEditing.value = true;
    pc_form.value = {
        name: category.name, slug: category.slug, description: category.description || '',
        parent_id: category.parent_id, icon: category.icon || '', image_url: category.image_url || '',
        sort_order: category.sort_order ?? 0, is_active: category.is_active,
        meta_title: category.meta_title || '', meta_description: category.meta_description || '',
    };
    pc_form.value._id = category.id;
    pc_dialogVisible.value = true;
}

async function pc_handleImageUpload(file) {
    const fd = new FormData(); fd.append('file', file);
    try {
        const { data: res } = await productApi.uploadImage(fd);
        if (res.success) { pc_form.value.image_url = res.data.url; }
        else { ElMessage.error(res.message || t('product_categories_page.upload_failed')); }
    } catch { ElMessage.error(t('product_categories_page.upload_failed')); }
    return false;
}

function pc_onSearch() { pc_treeRef.value?.filter(pc_searchQuery.value); }
function pc_filterNode(value, data) { return !value || data.name.toLowerCase().includes(value.toLowerCase()); }
function pc_expandAll() {
    pc_categories.value.forEach(n => { if (n.id) pc_treeRef.value?.store.nodesMap[n.id]?.expand(); });
}
function pc_collapseAll() {
    Object.values(pc_treeRef.value?.store.nodesMap || {}).forEach(n => n.collapse());
}

function pc_buildOrderList(nodes, acc) {
    nodes.forEach((n, i) => {
        acc.push({ id: n.id, sort_order: (i + 1) * 10 });
        if (n.children) pc_buildOrderList(n.children, acc);
    });
    return acc;
}

async function pc_handleDragEnd(draggingNode, dropNode, dropType) {
    const node = draggingNode;
    const newParentId = dropType === 'inner' ? dropNode.data.id : (dropNode.data.parent_id || null);

    if (node.data.parent_id !== newParentId && newParentId !== node.data.id) {
        try {
            await productCategoryApi.move(node.data.id, { parent_id: newParentId });
        } catch (e) {
            ElMessage.error(e.response?.data?.message || t('product_categories_page.move_failed'));
            pc_loadCategories();
            return;
        }
    }

    const orders = pc_buildOrderList(pc_categories.value, []);
    try {
        await productCategoryApi.reorder(orders);
        ElMessage.success(t('product_categories_page.reorder_ok'));
        if (pc_selectedCategory.value?.id) pc_loadCategoryDetail(pc_selectedCategory.value.id);
    } catch { ElMessage.error(t('product_categories_page.reorder_failed')); }
}

function pc_collectIds(nodes) {
    let ids = [];
    nodes.forEach(n => { ids.push(n.id); if (n.children) ids = ids.concat(pc_collectIds(n.children)); });
    return ids;
}

async function pc_batchToggle(active) {
    const ids = pc_collectIds(pc_categories.value);
    if (!ids.length) return;
    try {
        const res = await productCategoryApi.batchToggle(ids, active);
        const msg = pc_unwrap(res)?.message || (active ? t('product_categories_page.batch_enabled') : t('product_categories_page.batch_disabled'));
        ElMessage.success(msg);
        pc_selectedIds.value = [];
        pc_loadCategories();
    } catch { ElMessage.error(t('messages.failed')); }
}

async function pc_batchDelete() {
    try {
        await ElMessageBox.confirm(
            t('product_categories_page.batch_delete_confirm'),
            t('product_categories_page.confirm_title'),
            { type: 'warning', confirmButtonText: t('actions.delete'), cancelButtonText: t('actions.cancel') },
        );
    } catch { return; }
    const ids = pc_collectIds(pc_categories.value);
    if (!ids.length) return;
    try {
        const res = await productCategoryApi.batchDelete(ids);
        const data = pc_unwrap(res);
        ElMessage.success(data?.message || t('product_categories_page.deleted_ok'));
        if (data?.errors?.length) {
            ElMessage.warning(data.errors.join('；'));
        }
        pc_selectedIds.value = [];
        pc_loadCategories();
    } catch { ElMessage.error(t('product_categories_page.delete_failed')); }
}

async function pc_quickToggle(data, val) {
    try { await productCategoryApi.update(data.id, { is_active: val }); } catch { data.is_active = !val; }
}

async function pc_loadStats() {
    try {
        const res = await productCategoryApi.stats();
        pc_statsData.value = pc_unwrap(res);
    } catch { /* silent */ }
}

function pc_openMergeDialog() {
    pc_mergeForm.value = { source_id: null, target_id: null };
    pc_mergeDialogVisible.value = true;
}

async function pc_handleMerge() {
    if (!pc_mergeForm.value.source_id || !pc_mergeForm.value.target_id) {
        ElMessage.warning(t('product_categories_page.merge_select_both'));
        return;
    }
    if (pc_mergeForm.value.source_id === pc_mergeForm.value.target_id) {
        ElMessage.warning(t('product_categories_page.merge_same_error'));
        return;
    }
    try {
        await ElMessageBox.confirm(
            t('product_categories_page.merge_confirm_msg'),
            t('product_categories_page.merge_confirm_title'),
            { type: 'warning', confirmButtonText: t('product_categories_page.merge_confirm'), cancelButtonText: t('actions.cancel') },
        );
        const res = await productCategoryApi.merge(pc_mergeForm.value.source_id, pc_mergeForm.value.target_id);
        const data = pc_unwrap(res);
        ElMessage.success(data?.message || t('product_categories_page.merge_ok'));
        pc_mergeDialogVisible.value = false;
        pc_selectedCategory.value = null;
        pc_loadCategories();
        pc_loadStats();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || t('product_categories_page.merge_failed'));
        }
    }
}

async function pc_handleExport() {
    try {
        const res = await productCategoryApi.exportCsv();
        const url = window.URL.createObjectURL(new Blob([res.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', 'product-categories.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
        ElMessage.success(t('product_categories_page.export_ok'));
    } catch { ElMessage.error(t('product_categories_page.export_failed')); }
}

async function pc_handleImport() {
    try {
        const { value: csvText } = await ElMessageBox.prompt(
            t('product_categories_page.import_prompt'),
            t('product_categories_page.import_title'),
            {
                inputType: 'textarea',
                inputPlaceholder: t('product_categories_page.import_ph'),
                confirmButtonText: t('actions.import'),
                cancelButtonText: t('actions.cancel'),
            },
        );
        if (!csvText) return;
        const res = await productCategoryApi.importCsv(csvText);
        const data = pc_unwrap(res);
        ElMessage.success(data?.message || t('product_categories_page.import_ok'));
        if (data?.errors?.length) {
            ElMessage.warning(t('product_categories_page.import_partial') + data.errors.join('；'));
        }
        pc_loadCategories();
        pc_loadStats();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('product_categories_page.import_failed'));
    }
}

async function pc_handleSave() {
    const valid = await pc_formRef.value.validate().catch(() => false);
    if (!valid) return;

    pc_saving.value = true;
    try {
        if (pc_isEditing.value) {
            await productCategoryApi.update(pc_form.value._id, pc_form.value);
            ElMessage.success(t('product_categories_page.update_ok'));
        } else {
            await productCategoryApi.create(pc_form.value);
            ElMessage.success(t('product_categories_page.create_ok'));
        }
        pc_dialogVisible.value = false;
        pc_selectedCategory.value = null;
        await pc_loadCategories();
        await pc_loadOptions();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'));
    } finally {
        pc_saving.value = false;
    }
}

async function pc_handleDelete(category) {
    try {
        await ElMessageBox.confirm(
            t('product_categories_page.delete_confirm', { name: category.name }),
            t('product_categories_page.delete_title'),
            {
                type: 'warning',
                confirmButtonText: t('actions.delete'),
                cancelButtonText: t('actions.cancel'),
            },
        );
        await productCategoryApi.delete(category.id);
        ElMessage.success(t('product_categories_page.delete_ok'));
        pc_selectedCategory.value = null;
        await pc_loadCategories();
        await pc_loadOptions();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || t('product_categories_page.delete_failed'));
        }
    }
}

// ──────────────────── 懒加载 Tab ────────────────────
watch(prodMainTab, (val) => {
    if (val === 'categories' && !pc_tabVisited.value) {
        pc_tabVisited.value = true;
        pc_loadCategories();
        pc_loadOptions();
        pc_loadStats();
    }
});

// ──────────────────── 生命周期 ────────────────────
onMounted(() => {
    loadProducts();
    loadStats();
    loadCategories();
});
</script>

<style scoped>
/* ── 产品列表 样式 ── */
.products-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 {
    margin: 0;
    font-size: 20px;
}
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.main-tabs { margin-top: 0; }
.main-tabs :deep(.el-tabs__header) { margin-bottom: 16px; }

.stats-row { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--el-color-primary);
    line-height: 1.2;
}
.stat-label {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-top: 4px;
}

.filter-card { margin-bottom: 16px; }

.text-muted { color: var(--el-text-color-placeholder); }

code {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 12px;
    background: var(--el-fill-color-light);
    padding: 2px 6px;
    border-radius: 3px;
}

.pagination-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}

.el-card :deep(.el-card__body) { padding: 16px; }
.filter-card :deep(.el-card__body) { padding: 12px 16px; }
:deep(.el-form--inline .el-form-item) { margin-bottom: 0; }

.product-name-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}

.image-upload-wrapper {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    flex-wrap: wrap;
}
.images-upload-wrapper {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.images-list {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.image-preview {
    position: relative;
    display: inline-block;
}
.image-remove-btn {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 20px;
    height: 20px;
    padding: 0;
}

.form-section-title {
    font-size: 14px;
    font-weight: 600;
    color: #1f2937;
    padding-bottom: 8px;
    margin-bottom: 16px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 6px;
}
.form-section-title::before {
    content: '';
    display: inline-block;
    width: 3px;
    height: 14px;
    background: #0f172a;
    border-radius: 2px;
    flex-shrink: 0;
}

/* ── 产品分类 Tab 样式 ── */
.product-categories-tab {
    /* 不需要额外 padding，tab 内容区自带 */
}

.category-header {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin-bottom: 16px;
}
.category-header-right {
    display: flex;
    gap: 8px;
}

.tree-card, .detail-card, .empty-card {
    min-height: 400px;
}
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.tree-node {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    padding-right: 8px;
}
.tree-node-label {
    display: flex;
    align-items: center;
    gap: 4px;
}
.tree-node-actions {
    display: flex;
    gap: 4px;
    align-items: center;
}

.batch-bar {
    margin-bottom: 12px;
    padding: 8px 16px;
    background: #f1f5f9;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.batch-bar .batch-info {
    font-size: 13px;
    color: #0f172a;
    margin-right: 8px;
}

@media (max-width: 768px) {
    .products-page { padding: 12px; }
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    .header-left { display: flex; flex-direction: column; gap: 4px; }
    .header-subtitle { margin-left: 0; }
    .header-right { width: 100%; }
    .header-right .el-button { width: 100%; }
    .filter-card :deep(.el-form--inline .el-form-item) {
        display: block;
        margin-right: 0;
        margin-bottom: 12px;
    }
    .filter-card :deep(.el-input),
    .filter-card :deep(.el-select) {
        width: 100% !important;
    }
    .batch-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .stat-value { font-size: 22px; }
    .products-page :deep(.el-table) {
        display: block;
        overflow-x: auto;
    }
}
</style>
