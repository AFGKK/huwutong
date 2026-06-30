<template>
  <div class="kb-page">
    <!-- 页面头部 -->
    <div class="page-header">
      <h2>帮助中心管理</h2>
      <div class="header-actions">
        <el-button @click="fetchAll">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4" v-for="card in statCards" :key="card.label">
        <el-card shadow="never" :body-style="{ padding: '16px' }">
          <div class="stat-card">
            <div class="stat-value" :style="{ color: card.color }">{{ card.value }}</div>
            <div class="stat-label">{{ card.label }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 工具栏 -->
    <el-card shadow="never" class="mb-4">
      <div class="toolbar">
        <el-form :inline="true" :model="filters" size="small">
          <el-form-item>
            <el-input
              v-model="filters.search"
              placeholder="搜索文章标题..."
              clearable
              @clear="fetchArticles"
              @keyup.enter="fetchArticles"
              style="width: 240px"
            >
              <template #prefix>
                <el-icon><Search /></el-icon>
              </template>
            </el-input>
          </el-form-item>
          <el-form-item>
            <el-select
              v-model="filters.category_id"
              placeholder="选择分类"
              clearable
              filterable
              @change="fetchArticles"
              style="width: 180px"
            >
              <el-option
                v-for="c in categories"
                :key="c.id"
                :label="c.name"
                :value="c.id"
              />
            </el-select>
          </el-form-item>
        </el-form>
        <div class="toolbar-actions">
          <el-button type="primary" @click="openCreate">
            <el-icon><Plus /></el-icon> 新建文章
          </el-button>
          <el-button @click="showCatDialog = true">
            <el-icon><FolderOpened /></el-icon> 分类管理
          </el-button>
          <el-button @click="handleExport" :loading="exporting">
            <el-icon><Download /></el-icon> 导出MD
          </el-button>
        </div>
      </div>
    </el-card>

    <!-- 批量操作栏 -->
    <el-card v-if="selectedIds.length > 0" shadow="never" class="mb-4">
      <div style="display:flex;align-items:center;gap:12px">
        <span style="font-size:13px;color:#606266">已选择 <strong>{{ selectedIds.length }}</strong> 项</span>
        <el-button size="small" @click="clearSelection">取消选择</el-button>
        <el-button size="small" type="success" @click="handleBatchPublish" :loading="batchLoading">
          <el-icon><Upload /></el-icon> 批量发布
        </el-button>
        <el-button size="small" type="warning" @click="handleBatchArchive" :loading="batchLoading">
          <el-icon><FolderOpened /></el-icon> 批量归档
        </el-button>
        <el-button size="small" type="danger" @click="handleBatchDelete" :loading="batchLoading">
          <el-icon><Delete /></el-icon> 批量删除
        </el-button>
      </div>
    </el-card>

    <!-- 文章表格 -->
    <el-card shadow="never">
      <el-table ref="tableRef" :data="articles" v-loading="loading" stripe style="width: 100%"
        @selection-change="onSelectionChange">
        <el-table-column type="selection" width="45" />
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column label="标题" min-width="260">
          <template #default="{ row }">
            <div class="title-cell">
              <span class="title-text">{{ row.title }}</span>
              <el-tag
                v-if="row.status === 'draft'"
                size="small"
                type="warning"
                style="margin-left: 6px"
              >草稿</el-tag>
              <el-tag
                v-else-if="row.status === 'archived'"
                size="small"
                type="info"
                style="margin-left: 6px"
              >已归档</el-tag>
              <el-tag
                v-if="row.category"
                size="small"
                type=""
                effect="plain"
                style="margin-left: 6px"
              >{{ row.category.name }}</el-tag>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="作者" width="120">
          <template #default="{ row }">{{ row.author?.name || '—' }}</template>
        </el-table-column>
        <el-table-column label="阅读数" width="80" align="center">
          <template #default="{ row }">{{ row.view_count ?? 0 }}</template>
        </el-table-column>
        <el-table-column label="有帮助" width="90" align="center">
          <template #default="{ row }">
            <span v-if="(row.helpful_count ?? 0) + (row.unhelpful_count ?? 0) > 0" :style="{ color: satisfactionColor(row) }">
              {{ satisfactionRate(row) }}%
            </span>
            <span v-else class="text-muted">—</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="110" align="center">
          <template #default="{ row }">
            <el-tag
              :type="row.status === 'published' ? 'success' : row.status === 'draft' ? 'warning' : 'info'"
              size="small"
            >
              {{ row.status === 'published' ? '已发布' : row.status === 'draft' ? '草稿' : '已归档' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="170">
          <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="260" fixed="right">
          <template #default="{ row }">
            <el-button text size="small" type="primary" @click="openEdit(row)">编辑</el-button>
            <el-button
              v-if="row.status === 'draft'"
              text size="small" type="success"
              @click="handlePublish(row)"
            >发布</el-button>
            <el-button
              v-if="row.status === 'published'"
              text size="small" type="warning"
              @click="handleArchive(row)"
            >归档</el-button>
            <el-popconfirm title="确认删除此文章？" @confirm="handleDelete(row)">
              <template #reference>
                <el-button text size="small" type="danger">删除</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="perPage"
          :total="total"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next"
          @change="fetchArticles"
        />
      </div>
    </el-card>

    <!-- 文章编辑对话框 -->
    <el-dialog
      v-model="showDialog"
      :title="isEditing ? '编辑文章' : '新建文章'"
      width="720px"
      :close-on-click-modal="false"
    >
      <el-form ref="formRef" :model="form" :rules="formRules" label-width="80px">
        <el-form-item label="分类" prop="category_id">
          <el-select
            v-model="form.category_id"
            placeholder="选择分类"
            clearable
            filterable
            style="width: 100%"
          >
            <el-option
              v-for="c in categories"
              :key="c.id"
              :label="c.name"
              :value="c.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="标题" prop="title">
          <el-input v-model="form.title" placeholder="请输入文章标题" @input="onTitleInput" />
        </el-form-item>
        <el-form-item label="Slug" prop="slug">
          <el-input v-model="form.slug" placeholder="自动生成，可手动修改">
            <template #append>
              <el-button @click="generateSlug">自动生成</el-button>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item label="标签" prop="tags">
          <el-select
            v-model="form.tags"
            multiple
            filterable
            allow-create
            default-first-option
            placeholder="输入标签后回车添加"
            style="width: 100%"
          >
            <el-option
              v-for="tag in allTags"
              :key="tag"
              :label="tag"
              :value="tag"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="form.status">
            <el-radio value="draft">草稿</el-radio>
            <el-radio value="published">已发布</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="内容" prop="content">
          <el-input
            v-model="form.content"
            type="textarea"
            :rows="14"
            placeholder="支持 Markdown 格式，请输入文章内容..."
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDialog = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="confirmSubmit">
          {{ isEditing ? '保存修改' : '创建文章' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- 分类管理对话框 -->
    <el-dialog v-model="showCatDialog" title="文章分类管理" width="550px">
      <div class="mb-4">
        <el-button type="primary" size="small" @click="openCatForm" :icon="Plus">添加分类</el-button>
      </div>

      <el-form v-if="showCatForm" :model="catForm" label-width="80px" size="small" class="mb-4" :inline="true">
        <el-form-item label="名称">
          <el-input v-model="catForm.name" placeholder="分类名称" style="width:150px" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="catForm.sort_order" :min="0" :max="999" style="width:100px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="saveCat">保存</el-button>
          <el-button @click="showCatForm = false">取消</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="categories" stripe size="small">
        <el-table-column prop="name" label="名称" min-width="140" />
        <el-table-column prop="slug" label="标识" min-width="120">
          <template #default="{ row }"><code>{{ row.slug }}</code></template>
        </el-table-column>
        <el-table-column prop="articles_count" label="文章数" width="70" align="center" />
        <el-table-column prop="sort_order" label="排序" width="60" align="center" />
        <el-table-column label="操作" width="140">
          <template #default="{ row }">
            <el-button size="small" text type="primary" @click="editCat(row)">编辑</el-button>
            <el-popconfirm title="确定删除?" @confirm="deleteCat(row)">
              <template #reference>
                <el-button size="small" text type="danger" :disabled="row.articles_count > 0">删除</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Plus, Refresh, FolderOpened, Download, Upload, Delete } from '@element-plus/icons-vue';
import kbApi from '@/api/kb';

// ─── 统计卡片 ───
const stats = reactive({
  total: 0,
  published: 0,
  drafts: 0,
  archived: 0,
  total_feedback: 0,
  avg_satisfaction: 0,
});

const statCards = computed(() => [
  { label: '文章总数', value: stats.total, color: '#409eff' },
  { label: '已发布', value: stats.published, color: '#67c23a' },
  { label: '草稿', value: stats.drafts, color: '#e6a23c' },
  { label: '已归档', value: stats.archived, color: '#909399' },
  { label: '反馈总数', value: stats.total_feedback, color: '#b37feb' },
  { label: '平均满意度', value: stats.avg_satisfaction + '%', color: '#f56c6c' },
]);

// ─── 列表 ───
const articles = ref([]);
const loading = ref(false);
const page = ref(1);
const perPage = ref(20);
const total = ref(0);

const filters = reactive({
  search: '',
  category_id: null,
});

const categories = ref([]);
const allTags = ref([]);

// ─── 批量操作 ───
const selectedIds = ref([]);
const tableRef = ref(null);
const batchLoading = ref(false);
const exporting = ref(false);

function onSelectionChange(rows) {
  selectedIds.value = rows.map(r => r.id);
}

function clearSelection() {
  tableRef.value?.clearSelection();
  selectedIds.value = [];
}

async function handleBatchPublish() {
  if (selectedIds.value.length === 0) return;
  try {
    await ElMessageBox.confirm(`确定发布 ${selectedIds.value.length} 篇文章？`, '批量发布');
    batchLoading.value = true;
    const { data: res } = await kbApi.batchPublish(selectedIds.value);
    ElMessage.success(res?.message || '发布成功');
    clearSelection();
    fetchArticles();
    fetchStats();
  } catch { /* cancelled */ }
  finally { batchLoading.value = false; }
}

async function handleBatchArchive() {
  if (selectedIds.value.length === 0) return;
  try {
    await ElMessageBox.confirm(`确定归档 ${selectedIds.value.length} 篇文章？`, '批量归档');
    batchLoading.value = true;
    const { data: res } = await kbApi.batchArchive(selectedIds.value);
    ElMessage.success(res?.message || '归档成功');
    clearSelection();
    fetchArticles();
    fetchStats();
  } catch { /* cancelled */ }
  finally { batchLoading.value = false; }
}

async function handleBatchDelete() {
  if (selectedIds.value.length === 0) return;
  try {
    await ElMessageBox.confirm(`确定删除 ${selectedIds.value.length} 篇文章？此操作不可恢复。`, '批量删除', { type: 'warning' });
    batchLoading.value = true;
    const { data: res } = await kbApi.batchDelete(selectedIds.value);
    ElMessage.success(res?.message || '删除成功');
    clearSelection();
    fetchArticles();
    fetchStats();
  } catch { /* cancelled */ }
  finally { batchLoading.value = false; }
}

async function handleExport() {
  exporting.value = true;
  try {
    const res = await kbApi.exportMarkdown();
    const blob = new Blob([res.data], { type: 'text/markdown;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'kb-export-' + new Date().toISOString().slice(0, 10) + '.md';
    a.click();
    URL.revokeObjectURL(url);
    ElMessage.success('导出成功');
  } catch { ElMessage.error('导出失败'); }
  finally { exporting.value = false; }
}

// ─── 分类管理 ───
const showCatDialog = ref(false);
const showCatForm = ref(false);
const editingCatId = ref(null);
const catForm = reactive({ name: '', sort_order: 0 });

function openCatForm() {
  editingCatId.value = null;
  catForm.name = '';
  catForm.sort_order = 0;
  showCatForm.value = true;
}

async function saveCat() {
  if (!catForm.name) { ElMessage.warning('请输入分类名称'); return; }
  try {
    if (editingCatId.value) {
      await kbApi.updateCategory(editingCatId.value, { name: catForm.name, sort_order: catForm.sort_order });
      ElMessage.success('分类已更新');
    } else {
      await kbApi.createCategory({ name: catForm.name, sort_order: catForm.sort_order });
      ElMessage.success('分类已创建');
    }
    showCatForm.value = false;
    await fetchCategories();
  } catch { ElMessage.error('操作失败'); }
}

function editCat(cat) {
  editingCatId.value = cat.id;
  catForm.name = cat.name;
  catForm.sort_order = cat.sort_order || 0;
  showCatForm.value = true;
}

async function deleteCat(cat) {
  try {
    await kbApi.deleteCategory(cat.id);
    ElMessage.success('分类已删除');
    await fetchCategories();
  } catch { ElMessage.error('删除失败'); }
}

// ─── 对话框 ───
const showDialog = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const submitting = ref(false);
const formRef = ref(null);

const form = reactive({
  category_id: null,
  title: '',
  slug: '',
  tags: [],
  status: 'draft',
  content: '',
});

const formRules = {
  title: [
    { required: true, message: '请输入文章标题', trigger: 'blur' },
  ],
  content: [
    { required: true, message: '请输入文章内容', trigger: 'blur' },
  ],
};

// ─── 工具函数 ───
function formatTime(t) {
  if (!t) return '—';
  return new Date(t).toLocaleString('zh-CN');
}

function satisfactionRate(row) {
  const total = (row.helpful_count ?? 0) + (row.unhelpful_count ?? 0);
  if (total === 0) return 0;
  return Math.round(((row.helpful_count ?? 0) / total) * 100);
}

function satisfactionColor(row) {
  const rate = satisfactionRate(row);
  if (rate >= 80) return '#67c23a';
  if (rate >= 50) return '#e6a23c';
  return '#f56c6c';
}

function toSlug(str) {
  return str
    .toLowerCase()
    .replace(/[^\w\u4e00-\u9fa5]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function onTitleInput(val) {
  if (!isEditing.value && !form.slug) {
    form.slug = toSlug(val);
  }
}

function generateSlug() {
  form.slug = toSlug(form.title || 'untitled');
}

// ─── 数据加载 ───
async function loadCategories() {
  try {
    const res = await kbApi.categories();
    const data = res.data;
    if (data?.success) {
      categories.value = data.data || [];
      // 收集所有标签
      const tags = new Set();
      const walk = (items) => {
        for (const c of items) {
          if (c.tags) c.tags.forEach(t => tags.add(t));
          if (c.children) walk(c.children);
        }
      };
      walk(data.data || []);
      allTags.value = [...tags];
    }
  } catch { /* ignore */ }
}

async function computeStats() {
  try {
    const res = await kbApi.adminArticles({ per_page: 1 });
    const data = res.data;
    if (data?.success) {
      const paginated = data.data || {};
      const items = paginated.data || [];
      const all = paginated.total || 0;

      // 获取全部分类数据来统计
      const allRes = await kbApi.adminArticles({ per_page: 99999 });
      const allData = allRes.data;
      const allItems = allData?.success ? (allData.data?.data || []) : [];

      stats.total = all;
      stats.published = allItems.filter(a => a.status === 'published').length;
      stats.drafts = allItems.filter(a => a.status === 'draft').length;
      stats.archived = allItems.filter(a => a.status === 'archived').length;

      let fbTotal = 0;
      let satSum = 0;
      let satCount = 0;
      for (const a of allItems) {
        const helpful = a.helpful_count ?? 0;
        const unhelpful = a.unhelpful_count ?? 0;
        fbTotal += helpful + unhelpful;
        if (helpful + unhelpful > 0) {
          satSum += (helpful / (helpful + unhelpful)) * 100;
          satCount++;
        }
      }
      stats.total_feedback = fbTotal;
      stats.avg_satisfaction = satCount > 0 ? Math.round(satSum / satCount) : 0;
    }
  } catch { /* ignore */ }
}

async function fetchArticles() {
  loading.value = true;
  try {
    const params = {
      page: page.value,
      per_page: perPage.value,
    };
    if (filters.search) params.search = filters.search;
    if (filters.category_id) params.category_id = filters.category_id;

    const res = await kbApi.adminArticles(params);
    const data = res.data;
    if (data?.success) {
      const paginated = data.data || {};
      articles.value = paginated.data || [];
      total.value = paginated.total || 0;
    }
  } catch {
    ElMessage.error('获取文章列表失败');
  } finally {
    loading.value = false;
  }
}

async function fetchAll() {
  await Promise.all([fetchArticles(), computeStats()]);
}

// ─── 对话框操作 ───
function resetForm() {
  form.category_id = null;
  form.title = '';
  form.slug = '';
  form.tags = [];
  form.status = 'draft';
  form.content = '';
}

function openCreate() {
  isEditing.value = false;
  editingId.value = null;
  resetForm();
  showDialog.value = true;
}

function openEdit(row) {
  isEditing.value = true;
  editingId.value = row.id;
  form.category_id = row.category_id ?? null;
  form.title = row.title;
  form.slug = row.slug || toSlug(row.title);
  form.tags = row.tags || [];
  form.status = row.status || 'draft';
  form.content = row.content || '';
  showDialog.value = true;
}

async function confirmSubmit() {
  const valid = await formRef.value?.validate().catch(() => false);
  if (!valid) return;

  if (!form.slug) {
    generateSlug();
  }

  submitting.value = true;
  try {
    const payload = {
      category_id: form.category_id || undefined,
      title: form.title,
      slug: form.slug,
      tags: form.tags,
      status: form.status,
      content: form.content,
    };

    if (isEditing.value && editingId.value) {
      const res = await kbApi.updateArticle(editingId.value, payload);
      if (res.data?.success) {
        ElMessage.success('文章已更新');
      } else {
        ElMessage.error(res.data?.message || '更新失败');
        return;
      }
    } else {
      const res = await kbApi.createArticle(payload);
      if (res.data?.success) {
        ElMessage.success('文章已创建');
      } else {
        ElMessage.error(res.data?.message || '创建失败');
        return;
      }
    }

    showDialog.value = false;
    await fetchAll();
  } catch (err) {
    ElMessage.error(err.response?.data?.message || '操作失败');
  } finally {
    submitting.value = false;
  }
}

// ─── 操作 ───
async function handlePublish(row) {
  try {
    const res = await kbApi.publishArticle(row.id);
    if (res.data?.success) {
      ElMessage.success('文章已发布');
      await fetchAll();
    }
  } catch {
    ElMessage.error('发布失败');
  }
}

async function handleArchive(row) {
  try {
    const res = await kbApi.archiveArticle(row.id);
    if (res.data?.success) {
      ElMessage.success('文章已归档');
      await fetchAll();
    }
  } catch {
    ElMessage.error('归档失败');
  }
}

async function handleDelete(row) {
  try {
    const res = await kbApi.deleteArticle(row.id);
    if (res.data?.success) {
      ElMessage.success('文章已删除');
      await fetchAll();
    }
  } catch {
    ElMessage.error('删除失败');
  }
}

// ─── 生命周期 ───
onMounted(() => {
  loadCategories();
  fetchAll();
});
</script>

<style scoped>
.kb-page {
  padding: 0;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
}

.mb-4 {
  margin-bottom: 16px;
}

.stat-card {
  text-align: center;
}

.stat-value {
  font-size: 26px;
  font-weight: 700;
  line-height: 1.2;
}

.stat-label {
  font-size: 13px;
  color: var(--el-text-color-secondary);
  margin-top: 6px;
}

.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.toolbar-actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
}

.title-cell {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px;
}

.title-text {
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 280px;
  display: inline-block;
  vertical-align: middle;
}

.text-muted {
  color: var(--el-text-color-placeholder);
}

.pagination-wrap {
  display: flex;
  justify-content: center;
  margin-top: 16px;
}
</style>
