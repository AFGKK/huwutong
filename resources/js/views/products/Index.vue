<template>
    <div class="products-page">
        <div class="page-header">
            <div class="header-left">
                <h2>产品管理</h2>
                <span class="header-subtitle">管理产品与模块配置</span>
            </div>
            <div class="header-right">
                <el-button type="primary" @click="openCreateDialog">
                    <el-icon><Plus /></el-icon>
                    新建产品
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="stats-row" v-if="stats">
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats.total }}</div>
                    <div class="stat-label">产品总数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value" style="color: var(--el-color-success);">{{ stats.active }}</div>
                    <div class="stat-label">已上架</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value" style="color: var(--el-color-warning);">{{ stats.total_licenses }}</div>
                    <div class="stat-label">总 License 数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
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
                        <span v-else class="text-muted">暂无数据</span>
                    </div>
                    <div class="stat-label">热门产品</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 搜索栏 -->
        <el-card shadow="never" class="filter-card">
            <el-form :model="filters" inline>
                <el-form-item label="搜索">
                    <el-input
                        v-model="filters.search"
                        placeholder="产品名称 / 编码 / 描述"
                        clearable
                        style="width: 240px"
                        @keyup.enter="doSearch"
                    />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="filters.is_active" clearable placeholder="全部" style="width: 110px" @change="doSearch">
                        <el-option label="上架" :value="true" />
                        <el-option label="下架" :value="false" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="doSearch">
                        <el-icon><Search /></el-icon>
                        搜索
                    </el-button>
                    <el-button @click="resetFilters">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 批量操作 -->
        <div class="batch-bar" v-if="selectedIds.length > 0">
            <span class="batch-info">已选择 {{ selectedIds.length }} 项</span>
            <el-button size="small" type="success" @click="batchAction('activate')">批量上架</el-button>
            <el-button size="small" type="warning" @click="batchAction('deactivate')">批量下架</el-button>
            <el-button size="small" type="primary" @click="batchAction('set_sellable')">设为可售卖</el-button>
            <el-button size="small" @click="batchAction('set_not_sellable')">取消售卖</el-button>
            <el-button size="small" type="danger" @click="batchAction('delete')">批量删除</el-button>
            <el-button size="small" text @click="selectedIds = []">取消选择</el-button>
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
                <el-table-column label="产品名称" min-width="180" prop="name" sortable="custom">
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
                <el-table-column label="编码" width="130" prop="slug" sortable="custom">
                    <template #default="{ row }">
                        <code>{{ row.slug }}</code>
                    </template>
                </el-table-column>
                <el-table-column label="版本" width="100" prop="version" sortable="custom">
                    <template #default="{ row }">
                        {{ row.version || '-' }}
                    </template>
                </el-table-column>
                <el-table-column label="模块" min-width="160">
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
                <el-table-column label="售卖" width="70">
                    <template #default="{ row }">
                        <el-tag :type="row.is_sellable ? 'success' : 'info'" size="small" effect="plain">
                            {{ row.is_sellable ? '可售' : '—' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="精选" width="70" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.is_featured ? 'warning' : 'info'" size="small" effect="plain">
                            {{ row.is_featured ? '精选' : '—' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="价格" width="90" prop="base_price" sortable="custom">
                    <template #default="{ row }">
                        {{ row.base_price ? '¥' + row.base_price : '—' }}
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="70" prop="is_active" sortable="custom">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                            {{ row.is_active ? '上架' : '下架' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="License" width="75" prop="licenses_count" sortable="custom">
                    <template #default="{ row }">
                        <el-tag type="primary" effect="plain" size="small">{{ row.licenses_count || 0 }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="创建时间" width="170" prop="created_at" sortable="custom">
                    <template #default="{ row }">
                        {{ formatDate(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="170" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="$router.push(`/products/${row.id}`)">
                            详情
                        </el-button>
                        <el-button text size="small" type="primary" @click="openEditDialog(row)">
                            编辑
                        </el-button>
                        <el-dropdown trigger="click" @command="(cmd) => handleAction(cmd, row)">
                            <el-button text size="small" type="primary">
                                更多 <el-icon><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="clone">克隆</el-dropdown-item>
                                    <el-dropdown-item
                                        v-if="row.is_active"
                                        command="deactivate"
                                    >
                                        下架
                                    </el-dropdown-item>
                                    <el-dropdown-item
                                        v-if="!row.is_active"
                                        command="activate"
                                    >
                                        上架
                                    </el-dropdown-item>
                                    <el-dropdown-item
                                        v-if="row.is_sellable"
                                        command="set_not_sellable"
                                    >
                                        取消售卖
                                    </el-dropdown-item>
                                    <el-dropdown-item
                                        v-if="!row.is_sellable"
                                        command="set_sellable"
                                    >
                                        设为可售卖
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
            :title="editingId ? '编辑产品' : '新建产品'"
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
                    <!-- ═══ Tab 1: 基本信息 ═══ -->
                    <el-tab-pane label="📋 基本信息">
                        <el-row :gutter="20">
                            <el-col :span="12">
                                <el-form-item label="产品名称" prop="name">
                                    <el-input v-model="form.name" placeholder="如：HWT License Pro" @input="autoGenerateSlug" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="12">
                                <el-form-item label="编码" prop="slug">
                                    <el-input v-model="form.slug" placeholder="唯一标识，自动生成">
                                        <template #append><el-button text @click="autoGenerateSlug(true)">🔄</el-button></template>
                                    </el-input>
                                </el-form-item>
                            </el-col>
                        </el-row>
                        <el-row :gutter="20">
                            <el-col :span="8">
                                <el-form-item label="版本" prop="version">
                                    <el-input v-model="form.version" placeholder="如：2.1.0" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item label="分类" prop="category_id">
                                    <el-select v-model="form.category_id" clearable placeholder="选择分类" style="width:100%">
                                        <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item label="上架">
                                    <el-switch v-model="form.is_active" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                        <el-form-item label="简短描述" prop="description">
                            <el-input v-model="form.description" type="textarea" :rows="2"
                                placeholder="显示在产品卡片和列表，建议 100 字以内（选填）"
                                maxlength="200" show-word-limit />
                        </el-form-item>
                    </el-tab-pane>

                    <!-- ═══ Tab 2: 描述与图片 ═══ -->
                    <el-tab-pane label="🖼️ 描述与图片">
                        <el-form-item label="详细描述" prop="long_description" style="width:100%">
                            <div style="width:100%">
                                <PlazaEditor v-model="form.long_description" :height="300"
                                    placeholder="输入产品详细介绍，支持富文本格式（加粗、列表、链接等）" />
                            </div>
                            <div style="font-size:12px;color:#909399;margin-top:4px">用于产品详情页的「详细描述」区块，支持 HTML 富文本</div>
                        </el-form-item>
                        <el-row :gutter="20" style="margin-top:16px">
                            <el-col :span="12">
                                <el-form-item label="主图">
                                    <div class="image-upload-wrapper">
                                        <template v-if="form.image_url">
                                            <div class="image-preview">
                                                <el-image :src="form.image_url" fit="cover" style="width:120px;height:120px;border-radius:6px" />
                                                <el-button class="image-remove-btn" size="small" type="danger" circle @click="form.image_url=''"><el-icon><Close /></el-icon></el-button>
                                            </div>
                                        </template>
                                        <el-upload :show-file-list="false" :before-upload="handleMainImageUpload" accept="image/jpeg,image/png,image/gif,image/webp">
                                            <el-button type="primary" plain size="small"><el-icon><Upload /></el-icon> 上传主图</el-button>
                                        </el-upload>
                                    </div>
                                </el-form-item>
                            </el-col>
                            <el-col :span="12">
                                <el-form-item label="轮播图">
                                    <div class="images-upload-wrapper">
                                        <div class="images-list" v-if="form.images && form.images.length">
                                            <div v-for="(img, idx) in form.images" :key="idx" class="image-preview">
                                                <el-image :src="img" fit="cover" style="width:72px;height:72px;border-radius:4px" />
                                                <el-button class="image-remove-btn" size="small" type="danger" circle @click="form.images.splice(idx,1)"><el-icon><Close /></el-icon></el-button>
                                            </div>
                                        </div>
                                        <el-upload multiple :show-file-list="false" :before-upload="handleCarouselImageUpload" accept="image/jpeg,image/png,image/gif,image/webp">
                                            <el-button type="primary" plain size="small"><el-icon><Plus /></el-icon> 添加</el-button>
                                        </el-upload>
                                        <div style="font-size:11px;color:#909399;margin-top:4px">支持多选，建议 800×800px</div>
                                    </div>
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-tab-pane>

                    <!-- ═══ Tab 3: 高级设置 ═══ -->
                    <el-tab-pane label="⚙️ 高级设置">
                        <el-row :gutter="20">
                            <el-col :span="12">
                                <el-form-item label="模块标签" prop="modules">
                                    <el-select v-model="form.modules" multiple allow-create filterable default-first-option placeholder="输入后回车添加" style="width:100%">
                                        <el-option v-for="mod in moduleSuggestions" :key="mod" :label="mod" :value="mod" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :span="12">
                                <el-form-item label="内容标签">
                                    <el-select v-model="form.tags" multiple allow-create filterable default-first-option placeholder="输入后回车添加" style="width:100%">
                                        <el-option v-for="t in ['热销','推荐','新品','限量','企业版','专业版']" :key="t" :label="t" :value="t" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                        </el-row>
                        <div class="form-section-title" style="margin-top:8px">电商设置</div>
                        <el-row :gutter="20">
                            <el-col :span="8">
                                <el-form-item label="可售卖">
                                    <el-switch v-model="form.is_sellable" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item label="精选产品">
                                    <el-switch v-model="form.is_featured" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item label="基础价格">
                                    <el-input-number v-model="form.base_price" :precision="2" :min="0" style="width:100%"><template #prefix>¥</template></el-input-number>
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item label="初始销量">
                                    <el-input-number v-model="form.sales_count" :min="0" style="width:100%" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-tab-pane>
                </el-tabs>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="submitForm">
                    {{ editingId ? '保存' : '创建' }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Plus, ArrowDown, Upload, Close } from '@element-plus/icons-vue';
import productApi from '@/api/product';
import categoryApi from '@/api/productCategory';
import PlazaEditor from '@/components/PlazaEditor.vue';

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
async function loadCategories() {
    try {
        const res = await categoryApi.options();
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

const formRules = {
    name: [
        { required: true, message: '请输入产品名称', trigger: 'blur' },
        { max: 255, message: '产品名称不能超过255个字符', trigger: 'blur' },
    ],
    slug: [
        { required: true, message: '请输入产品编码', trigger: 'blur' },
        { max: 100, message: '产品编码不能超过100个字符', trigger: 'blur' },
    ],
};

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
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
            ElMessage.success(res.message || '批量操作完成');
            selectedIds.value = [];
            await loadProducts();
            await loadStats();
        }
    } catch {
        ElMessage.error('批量操作失败');
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

// Dialog
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

// Slug 自动生成
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

// 图片上传
async function handleMainImageUpload(file) {
    const fd = new FormData();
    fd.append('file', file);
    try {
        const { data: res } = await productApi.uploadImage(fd);
        if (res.success) {
            form.image_url = res.data.url;
        } else {
            ElMessage.error(res.message || '上传失败');
        }
    } catch {
        ElMessage.error('图片上传失败');
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
            ElMessage.error(res.message || '上传失败');
        }
    } catch {
        ElMessage.error('图片上传失败');
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
            ElMessage.success('产品更新成功');
        } else {
            await productApi.create(payload);
            ElMessage.success('产品创建成功');
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

// 上架/下架
async function handleAction(cmd, row) {
    if (cmd === 'activate') {
        try {
            await ElMessageBox.confirm('确定要上架该产品吗？', '确认操作', {
                confirmButtonText: '确定', cancelButtonText: '取消', type: 'info',
            });
            await productApi.update(row.id, { is_active: 1 });
            ElMessage.success('已上架');
            loadProducts();
            loadStats();
        } catch { /* cancelled */ }
    } else if (cmd === 'deactivate') {
        try {
            await ElMessageBox.confirm('确定要下架该产品吗？下架后无法创建新的 License。', '确认操作', {
                confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning',
            });
            await productApi.update(row.id, { is_active: 0 });
            ElMessage.success('已下架');
            loadProducts();
            loadStats();
        } catch { /* cancelled */ }
    } else if (cmd === 'clone') {
        try {
            const { data: res } = await productApi.clone(row.id);
            if (res.success) { ElMessage.success('产品已克隆'); loadProducts(); }
        } catch { ElMessage.error('克隆失败'); }
    } else if (cmd === 'set_sellable' || cmd === 'set_not_sellable') {
        try {
            await productApi.update(row.id, { is_sellable: cmd === 'set_sellable' });
            ElMessage.success(cmd === 'set_sellable' ? '已设为可售卖' : '已取消售卖');
            loadProducts();
        } catch { ElMessage.error('操作失败'); }
    }
}

onMounted(() => {
    loadProducts();
    loadStats();
    loadCategories();
});
</script>

<style scoped>
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
    background: #409eff;
    border-radius: 2px;
    flex-shrink: 0;
}
</style>
