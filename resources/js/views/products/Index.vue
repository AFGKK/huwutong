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

        <!-- 表格 -->
        <el-card shadow="never">
            <el-table
                :data="products"
                v-loading="loading"
                stripe
                row-key="id"
                @sort-change="handleSortChange"
            >
                <el-table-column label="产品名称" min-width="180" prop="name" sortable="custom">
                    <template #default="{ row }">
                        <el-link type="primary" @click="$router.push(`/products/${row.id}`)">
                            {{ row.name }}
                        </el-link>
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
                <el-table-column label="状态" width="80" prop="is_active" sortable="custom">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                            {{ row.is_active ? '上架' : '下架' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="License" width="80" prop="licenses_count" sortable="custom">
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
            width="600px"
            :close-on-click-modal="false"
        >
            <el-form
                ref="formRef"
                :model="form"
                :rules="formRules"
                label-width="100px"
                label-position="right"
            >
                <el-form-item label="产品名称" prop="name">
                    <el-input v-model="form.name" placeholder="如：HWT License Pro" />
                </el-form-item>
                <el-form-item label="编码" prop="slug">
                    <el-input v-model="form.slug" placeholder="如：hwt-license-pro，唯一标识" />
                </el-form-item>
                <el-form-item label="版本" prop="version">
                    <el-input v-model="form.version" placeholder="如：2.1.0" style="width: 200px" />
                </el-form-item>
                <el-form-item label="描述" prop="description">
                    <el-input
                        v-model="form.description"
                        type="textarea"
                        :rows="3"
                        placeholder="产品描述（选填）"
                    />
                </el-form-item>
                <el-form-item label="模块" prop="modules">
                    <el-select
                        v-model="form.modules"
                        multiple
                        allow-create
                        filterable
                        default-first-option
                        placeholder="输入模块名称后回车添加"
                        style="width: 100%"
                    >
                        <el-option
                            v-for="mod in moduleSuggestions"
                            :key="mod"
                            :label="mod"
                            :value="mod"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item label="上架">
                    <el-switch v-model="form.is_active" />
                </el-form-item>
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
import { Search, Plus, ArrowDown } from '@element-plus/icons-vue';
import productApi from '@/api/product';

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

const filters = reactive({
    search: '',
    is_active: '',
});
const sortField = ref('-created_at');

const moduleSuggestions = ['core', 'trial', 'offline', 'sso', 'mfa', 'audit', 'webhook', 'openfeature', 'api'];

const form = reactive({
    name: '',
    slug: '',
    version: '',
    description: '',
    modules: [],
    is_active: true,
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
        products.value = res.data?.data || [];
        total.value = res.data?.total || 0;
    } catch {
        products.value = [];
    } finally {
        loading.value = false;
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
    form.modules = [];
    form.is_active = true;
    dialogVisible.value = true;
}

function openEditDialog(row) {
    editingId.value = row.id;
    form.name = row.name;
    form.slug = row.slug;
    form.version = row.version || '';
    form.description = row.description || '';
    form.modules = row.modules || [];
    form.is_active = Boolean(row.is_active);
    dialogVisible.value = true;
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
    }
}

onMounted(() => {
    loadProducts();
    loadStats();
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
</style>
