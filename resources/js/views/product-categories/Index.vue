<template>
    <div class="product-categories-page">
        <div class="page-header">
            <div class="header-left">
                <h2>产品分类管理</h2>
                <span class="header-subtitle">管理产品分类结构，支持无限级父子分类</span>
            </div>
            <div class="header-right">
                <el-button @click="handleExport">
                    <el-icon><Download /></el-icon>
                    导出
                </el-button>
                <el-button @click="handleImport">
                    <el-icon><FolderOpened /></el-icon>
                    导入
                </el-button>
                <el-button @click="openMergeDialog">合并分类</el-button>
                <el-button type="primary" @click="openCreateDialog">
                    <el-icon><Plus /></el-icon>
                    新建分类
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="stats-row">
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ statsData?.total_categories ?? '—' }}</div>
                    <div class="stat-label">分类总数</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value" style="color:#67c23a">{{ statsData?.active ?? '—' }}</div>
                    <div class="stat-label">已启用</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value" style="color:#f56c6c">{{ statsData?.inactive ?? '—' }}</div>
                    <div class="stat-label">已停用</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ statsData?.root_count ?? '—' }}</div>
                    <div class="stat-label">根分类</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ statsData?.max_depth ?? '—' }}</div>
                    <div class="stat-label">最大深度</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ statsData?.categories_with_products ?? '—' }}</div>
                    <div class="stat-label">有产品分类</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 批量操作栏 -->
        <div class="batch-bar" v-if="selectedIds.length > 0">
            <span class="batch-info">已选择 {{ selectedIds.length }} 项</span>
            <el-button size="small" type="success" @click="batchToggle(true)">批量启用</el-button>
            <el-button size="small" type="warning" @click="batchToggle(false)">批量停用</el-button>
            <el-button size="small" type="danger" @click="batchDelete">批量删除</el-button>
            <el-button size="small" text @click="selectedIds = []">取消选择</el-button>
        </div>

        <el-row :gutter="16">
            <!-- 分类树 -->
            <el-col :span="8">
                <el-card shadow="never" class="tree-card">
                    <template #header>
                        <div class="card-header">
                            <span>分类结构</span>
                            <div>
                                <el-button size="small" text @click="expandAll">展开</el-button>
                                <el-button size="small" text @click="collapseAll">收起</el-button>
                                <el-tag size="small" type="info">{{ categories.length }} 个根分类</el-tag>
                            </div>
                        </div>
                    </template>
                    <el-input v-model="searchQuery" placeholder="搜索分类..." clearable size="small" style="margin-bottom:8px" @input="onSearch" />
                    <el-tree
                        ref="treeRef"
                        :data="categories"
                        :props="{ children: 'children', label: 'name' }"
                        node-key="id"
                        highlight-current
                        :expand-on-click-node="false"
                        draggable
                        :allow-drag="() => true"
                        @node-drag-end="handleDragEnd"
                        @node-click="handleNodeClick"
                        :filter-node-method="filterNode"
                    >
                        <template #default="{ node, data }">
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
                                        @change="(v) => quickToggle(data, v)"
                                    />
                                    <el-tag v-if="!data.is_active" size="small" type="danger" effect="plain">停用</el-tag>
                                    <el-tag size="small" type="info">{{ data.products_count ?? 0 }}</el-tag>
                                </span>
                            </span>
                        </template>
                    </el-tree>
                </el-card>
            </el-col>

            <!-- 分类详情/编辑 -->
            <el-col :span="16">
                <el-card v-if="selectedCategory" shadow="never" class="detail-card">
                    <template #header>
                        <div class="card-header">
                            <div>
                                <el-breadcrumb separator="/">
                                    <el-breadcrumb-item v-for="p in categoryPath" :key="p.id">{{ p.name }}</el-breadcrumb-item>
                                </el-breadcrumb>
                            </div>
                            <div>
                                <el-tag size="small" type="info" style="margin-right:8px">{{ selectedCategory.products_count ?? categoryProducts.length }} 个产品</el-tag>
                                <el-button size="small" @click="openEditDialog(selectedCategory)">编辑</el-button>
                                <el-button size="small" type="danger" plain @click="handleDelete(selectedCategory)">删除</el-button>
                            </div>
                        </div>
                    </template>

                    <el-descriptions :column="2" border>
                        <el-descriptions-item label="名称">{{ selectedCategory.name }}</el-descriptions-item>
                        <el-descriptions-item label="标识">{{ selectedCategory.slug }}</el-descriptions-item>
                        <el-descriptions-item label="描述" :span="2">{{ selectedCategory.description || '—' }}</el-descriptions-item>
                        <el-descriptions-item label="图标">{{ selectedCategory.icon || '—' }}</el-descriptions-item>
                        <el-descriptions-item label="排序">{{ selectedCategory.sort_order }}</el-descriptions-item>
                        <el-descriptions-item label="父分类">{{ selectedCategory.parent?.name || '—' }}</el-descriptions-item>
                        <el-descriptions-item label="状态">
                            <el-tag :type="selectedCategory.is_active ? 'success' : 'danger'" size="small">
                                {{ selectedCategory.is_active ? '启用' : '停用' }}
                            </el-tag>
                        </el-descriptions-item>
                        <el-descriptions-item label="子分类数">{{ selectedCategory.children?.length || 0 }}</el-descriptions-item>
                        <el-descriptions-item label="创建时间">{{ selectedCategory.created_at }}</el-descriptions-item>
                        <el-descriptions-item label="更新时间">{{ selectedCategory.updated_at }}</el-descriptions-item>
                    </el-descriptions>

                    <!-- 分类下的产品列表 -->
                    <el-divider content-position="left">该分类下的产品</el-divider>
                    <el-table v-loading="productsLoading" :data="categoryProducts" stripe style="width:100%">
                        <el-table-column prop="name" label="产品名称" min-width="160">
                            <template #default="{ row }">
                                <el-link type="primary" :underline="false" @click="$router.push('/products')">{{ row.name }}</el-link>
                            </template>
                        </el-table-column>
                        <el-table-column prop="slug" label="标识" width="120" />
                        <el-table-column label="价格" width="100">
                            <template #default="{ row }">¥{{ row.base_price ?? '—' }}</template>
                        </el-table-column>
                        <el-table-column label="可售卖" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.is_sellable ? 'success' : 'info'" size="small">{{ row.is_sellable ? '是' : '否' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="70">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="销量" width="70" prop="sales_count" />
                    </el-table>
                    <div v-if="!categoryProducts.length && !productsLoading" style="text-align:center;padding:20px;color:#909399;">
                        该分类下暂无产品
                    </div>
                </el-card>

                <el-card v-else shadow="never" class="empty-card">
                    <el-empty description="请从左侧选择一个分类" />
                </el-card>
            </el-col>
        </el-row>

        <!-- 创建/编辑对话框 -->
        <el-dialog
            v-model="dialogVisible"
            :title="isEditing ? '编辑分类' : '新建分类'"
            width="520px"
            :close-on-click-modal="false"
        >
            <el-form ref="formRef" :model="form" :rules="rules" label-width="80px">
                <el-form-item label="名称" prop="name">
                    <el-input v-model="form.name" placeholder="请输入分类名称" maxlength="100" show-word-limit />
                </el-form-item>
                <el-form-item label="标识" prop="slug">
                    <el-input v-model="form.slug" placeholder="留空自动生成" maxlength="100">
                        <template #append>
                            <el-button @click="form.slug = $toSlug(form.name)" :disabled="!form.name">自动</el-button>
                        </template>
                    </el-input>
                </el-form-item>
                <el-form-item label="描述" prop="description">
                    <el-input v-model="form.description" type="textarea" :rows="3" maxlength="500" show-word-limit />
                </el-form-item>
                <el-form-item label="父分类" prop="parent_id">
                    <el-tree-select
                        v-model="form.parent_id"
                        :data="categoryOptions"
                        :props="{ children: 'children', label: 'name', value: 'id' }"
                        placeholder="不选则为根分类"
                        clearable
                        check-strictly
                        filterable
                        style="width: 100%;"
                    />
                </el-form-item>
                <el-form-item label="分类图">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <template v-if="form.image_url">
                            <el-image :src="form.image_url" fit="cover" style="width:60px;height:60px;border-radius:4px" />
                            <el-button size="small" type="danger" circle @click="form.image_url = ''">
                                <el-icon><Close /></el-icon>
                            </el-button>
                        </template>
                        <el-upload :show-file-list="false" :before-upload="handleImageUpload" accept="image/*">
                            <el-button size="small" type="primary" plain><el-icon><Upload /></el-icon> 上传图片</el-button>
                        </el-upload>
                    </div>
                </el-form-item>
                <el-form-item label="SEO 标题">
                    <el-input v-model="form.meta_title" placeholder="搜索引擎显示的标题" maxlength="160" show-word-limit />
                </el-form-item>
                <el-form-item label="SEO 描述">
                    <el-input v-model="form.meta_description" type="textarea" :rows="2" placeholder="搜索引擎显示的描述" maxlength="500" show-word-limit />
                </el-form-item>
                <el-form-item label="图标" prop="icon">
                    <el-input v-model="form.icon" placeholder="Element Plus 图标名" />
                </el-form-item>
                <el-form-item label="排序" prop="sort_order">
                    <el-input-number v-model="form.sort_order" :min="0" />
                </el-form-item>
                <el-form-item label="状态" prop="is_active">
                    <el-switch v-model="form.is_active" active-text="启用" inactive-text="停用" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">保存</el-button>
            </template>
        </el-dialog>

        <!-- 合并分类对话框 -->
        <el-dialog v-model="mergeDialogVisible" title="合并分类" width="480px">
            <el-form label-width="100px">
                <el-form-item label="源分类">
                    <el-tree-select
                        v-model="mergeForm.source_id"
                        :data="categoryOptions"
                        :props="{ children: 'children', label: 'name', value: 'id' }"
                        placeholder="选择要合并的分类（将被删除）"
                        clearable
                        filterable
                        style="width:100%"
                    />
                </el-form-item>
                <el-form-item label="目标分类">
                    <el-tree-select
                        v-model="mergeForm.target_id"
                        :data="categoryOptions"
                        :props="{ children: 'children', label: 'name', value: 'id' }"
                        placeholder="选择目标分类（接收产品）"
                        clearable
                        filterable
                        style="width:100%"
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="mergeDialogVisible = false">取消</el-button>
                <el-button type="warning" @click="handleMerge">确认合并</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';
import { ElMessage, ElMessageBox, ElLoading } from 'element-plus';
import { Close, Upload, Download, FolderOpened } from '@element-plus/icons-vue';
import productCategoryApi from '@/api/productCategory';
import productApi from '@/api/product';

const categories = ref([]);
const categoryOptions = ref([]);
const selectedCategory = ref(null);
const categoryPath = ref([]);
const categoryProducts = ref([]);
const productsLoading = ref(false);
const dialogVisible = ref(false);
const isEditing = ref(false);
const saving = ref(false);
const treeRef = ref(null);
const formRef = ref(null);
const searchQuery = ref('');
const selectedIds = ref([]);
const statsData = ref(null);
const mergeDialogVisible = ref(false);
const mergeForm = ref({ source_id: null, target_id: null });

const form = ref({
    name: '', slug: '', description: '', parent_id: null, icon: '',
    sort_order: 0, is_active: true, image_url: '', meta_title: '', meta_description: '',
});

const rules = {
    name: [{ required: true, message: '请输入分类名称', trigger: 'blur' }],
};

const $toSlug = (str) => {
    return str.toLowerCase().replace(/[^\w\u4e00-\u9fa5]+/g, '-').replace(/^-+|-+$/g, '');
};

function unwrap(res) {
    // axios response: res.data = { success, data }, or paginated: { data, meta }
    const body = res?.data ?? res;
    return body?.data ?? body;
}

async function loadCategories() {
    try {
        const res = await productCategoryApi.tree();
        categories.value = unwrap(res) || [];
    } catch (e) {
        ElMessage.error('加载分类失败');
    }
}

async function loadOptions() {
    try {
        const res = await productCategoryApi.options();
        const flat = unwrap(res) || [];
        const topLevel = flat.filter((c) => !c.parent_id);
        const buildTree = (items) =>
            items.map((item) => ({
                ...item,
                children: buildTree(flat.filter((c) => c.parent_id === item.id)),
            }));
        categoryOptions.value = buildTree(topLevel);
    } catch (e) {
        // ignore
    }
}

function handleNodeClick(data) {
    loadCategoryDetail(data.id);
}

async function loadCategoryDetail(id) {
    try {
        const [detailRes, productsRes] = await Promise.all([
            productCategoryApi.get(id),
            productCategoryApi.products(id),
        ]);
        selectedCategory.value = unwrap(detailRes);
        categoryProducts.value = unwrap(productsRes) || [];
        loadPath(id);
    } catch (e) {
        ElMessage.error('加载分类详情失败');
    }
}

async function loadCategoryProducts(id) {
    productsLoading.value = true;
    try {
        const res = await productCategoryApi.products(id);
        categoryProducts.value = unwrap(res) || [];
    } catch {
        // ignore
    } finally {
        productsLoading.value = false;
    }
}

function openCreateDialog() {
    isEditing.value = false;
    form.value = { name: '', slug: '', description: '', parent_id: null, icon: '', sort_order: 0, is_active: true, image_url: '', meta_title: '', meta_description: '' };
    dialogVisible.value = true;
}

function openEditDialog(category) {
    isEditing.value = true;
    form.value = {
        name: category.name, slug: category.slug, description: category.description || '',
        parent_id: category.parent_id, icon: category.icon || '', image_url: category.image_url || '',
        sort_order: category.sort_order ?? 0, is_active: category.is_active,
        meta_title: category.meta_title || '', meta_description: category.meta_description || '',
    };
    form.value._id = category.id;
    dialogVisible.value = true;
}

// ── 图片上传 ──
async function handleImageUpload(file) {
    const fd = new FormData(); fd.append('file', file);
    try {
        const { data: res } = await productApi.uploadImage(fd);
        if (res.success) { form.value.image_url = res.data.url; }
        else { ElMessage.error(res.message || '上传失败'); }
    } catch { ElMessage.error('上传失败'); }
    return false;
}

// ── 搜索/展开/收起 ──
function onSearch() { treeRef.value?.filter(searchQuery.value); }
function filterNode(value, data) { return !value || data.name.toLowerCase().includes(value.toLowerCase()); }
function expandAll() {
    categories.value.forEach(n => { if (n.id) treeRef.value?.store.nodesMap[n.id]?.expand(); });
}
function collapseAll() {
    Object.values(treeRef.value?.store.nodesMap || {}).forEach(n => n.collapse());
}

function buildOrderList(nodes, acc) {
    nodes.forEach((n, i) => {
        acc.push({ id: n.id, sort_order: (i + 1) * 10 });
        if (n.children) buildOrderList(n.children, acc);
    });
    return acc;
}

// ── 拖拽移动 ──
async function handleDragEnd(draggingNode, dropNode, dropType) {
    const node = draggingNode;
    const newParentId = dropType === 'inner' ? dropNode.data.id : (dropNode.data.parent_id || null);

    if (node.data.parent_id !== newParentId && newParentId !== node.data.id) {
        try {
            await productCategoryApi.move(node.data.id, { parent_id: newParentId });
        } catch (e) {
            ElMessage.error(e.response?.data?.message || '移动失败');
            loadCategories();
            return;
        }
    }

    const orders = buildOrderList(categories.value, []);
    try {
        await productCategoryApi.reorder(orders);
        ElMessage.success('排序已更新');
        if (selectedCategory.value?.id) loadCategoryDetail(selectedCategory.value.id);
    } catch { ElMessage.error('排序保存失败'); }
}

// ── 批量操作（使用后端 API）──
function collectIds(nodes) {
    let ids = [];
    nodes.forEach(n => { ids.push(n.id); if (n.children) ids = ids.concat(collectIds(n.children)); });
    return ids;
}
async function batchToggle(active) {
    const ids = collectIds(categories.value);
    if (!ids.length) return;
    try {
        const res = await productCategoryApi.batchToggle(ids, active);
        const msg = unwrap(res)?.message || (active ? '已全部启用' : '已全部停用');
        ElMessage.success(msg);
        selectedIds.value = [];
        loadCategories();
    } catch { ElMessage.error('操作失败'); }
}
async function batchDelete() {
    try { await ElMessageBox.confirm('确定删除所有分类？', '确认', { type: 'warning' }); } catch { return; }
    const ids = collectIds(categories.value);
    if (!ids.length) return;
    try {
        const res = await productCategoryApi.batchDelete(ids);
        const data = unwrap(res);
        ElMessage.success(data?.message || '已删除');
        if (data?.errors?.length) {
            ElMessage.warning(data.errors.join('；'));
        }
        selectedIds.value = [];
        loadCategories();
    } catch { ElMessage.error('删除失败'); }
}

// ── 快捷切换 ──
async function quickToggle(data, val) {
    try { await productCategoryApi.update(data.id, { is_active: val }); } catch { data.is_active = !val; }
}

// ── 分类路径/面包屑 ──
async function loadPath(id) {
    try {
        const res = await productCategoryApi.getPath(id);
        categoryPath.value = unwrap(res) || [];
    } catch { categoryPath.value = []; }
}

// ── 分类统计 ──
async function loadStats() {
    try {
        const res = await productCategoryApi.stats();
        statsData.value = unwrap(res);
    } catch { /* silent */ }
}

// ── 分类合并 ──
function openMergeDialog() {
    mergeForm.value = { source_id: null, target_id: null };
    mergeDialogVisible.value = true;
}
async function handleMerge() {
    if (!mergeForm.value.source_id || !mergeForm.value.target_id) {
        ElMessage.warning('请选择源分类和目标分类');
        return;
    }
    if (mergeForm.value.source_id === mergeForm.value.target_id) {
        ElMessage.warning('源分类和目标分类不能相同');
        return;
    }
    try {
        await ElMessageBox.confirm('确定合并分类？源分类将被删除，其产品将移动到目标分类。', '确认合并', { type: 'warning' });
        const res = await productCategoryApi.merge(mergeForm.value.source_id, mergeForm.value.target_id);
        const data = unwrap(res);
        ElMessage.success(data?.message || '合并成功');
        mergeDialogVisible.value = false;
        selectedCategory.value = null;
        loadCategories();
        loadStats();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || '合并失败');
        }
    }
}

// ── 导入/导出 ──
async function handleExport() {
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
        ElMessage.success('导出成功');
    } catch { ElMessage.error('导出失败'); }
}
async function handleImport() {
    try {
        const { value: csvText } = await ElMessageBox.prompt('请粘贴 CSV 内容（第一行为表头：name,slug,description,icon,parent_id,sort_order,is_active）', '导入分类', {
            inputType: 'textarea',
            inputPlaceholder: 'name,slug,description,icon,parent_id,sort_order,is_active\n电子产品,electronics,电子产品分类,electronic,,10,1\n手机,phone,手机分类,,1,5,1',
            confirmButtonText: '导入',
            cancelButtonText: '取消',
        });
        if (!csvText) return;
        const res = await productCategoryApi.importCsv(csvText);
        const data = unwrap(res);
        ElMessage.success(data?.message || '导入完成');
        if (data?.errors?.length) {
            ElMessage.warning('部分行导入失败：' + data.errors.join('；'));
        }
        loadCategories();
        loadStats();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('导入失败');
    }
}

async function handleSave() {
    const valid = await formRef.value.validate().catch(() => false);
    if (!valid) return;

    saving.value = true;
    try {
        if (isEditing.value) {
            await productCategoryApi.update(form.value._id, form.value);
            ElMessage.success('分类已更新');
        } else {
            await productCategoryApi.create(form.value);
            ElMessage.success('分类已创建');
        }
        dialogVisible.value = false;
        selectedCategory.value = null;
        await loadCategories();
        await loadOptions();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败');
    } finally {
        saving.value = false;
    }
}

async function handleDelete(category) {
    try {
        await ElMessageBox.confirm(`确定删除分类「${category.name}」？子分类将上移一级。`, '确认删除', {
            type: 'warning',
            confirmButtonText: '删除',
            cancelButtonText: '取消',
        });
        await productCategoryApi.delete(category.id);
        ElMessage.success('分类已删除');
        selectedCategory.value = null;
        await loadCategories();
        await loadOptions();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || '删除失败');
        }
    }
}

onMounted(() => {
    loadCategories();
    loadOptions();
    loadStats();
});
</script>

<style scoped>
.product-categories-page {
    padding: 20px;
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.header-left h2 {
    margin: 0;
    font-size: 20px;
}
.header-subtitle {
    font-size: 13px;
    color: #999;
    margin-left: 8px;
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
.stats-row {
    margin-bottom: 16px;
}
.stat-card {
    text-align: center;
    cursor: default;
}
.stat-card .stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #409eff;
    line-height: 1.4;
}
.stat-card .stat-label {
    font-size: 12px;
    color: #909399;
    margin-top: 2px;
}
.batch-bar {
    margin-bottom: 12px;
    padding: 8px 16px;
    background: #ecf5ff;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.batch-bar .batch-info {
    font-size: 13px;
    color: #409eff;
    margin-right: 8px;
}
</style>
