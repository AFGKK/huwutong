<template>
  <div class="knowledge-base">
    <div class="page-header">
      <h2>帮助中心 / 知识库</h2>
      <div class="header-actions">
        <el-button type="primary" @click="showCreate = true">
          <el-icon><Plus /></el-icon> 写文章
        </el-button>
        <el-button @click="showCategoryDialog = true">管理分类</el-button>
      </div>
    </div>

    <!-- Stats -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-card">
            <div class="stat-value">{{ stats.published }}</div>
            <div class="stat-label">已发布</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-card">
            <div class="stat-value" style="color: #e6a23c">{{ stats.drafts }}</div>
            <div class="stat-label">草稿</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-card">
            <div class="stat-value" style="color: #909399">{{ stats.categories }}</div>
            <div class="stat-label">分类</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-card">
            <div class="stat-value" style="color: #67c23a">{{ stats.total_views }}</div>
            <div class="stat-label">总阅读</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Filters -->
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="12">
        <el-col :span="8">
          <el-input v-model="filters.search" placeholder="搜索文章..." clearable @clear="fetchArticles" @keyup.enter="fetchArticles">
            <template #prefix><el-icon><Search /></el-icon></template>
          </el-input>
        </el-col>
        <el-col :span="5">
          <el-select v-model="filters.status" placeholder="状态" clearable @change="fetchArticles" style="width: 100%">
            <el-option label="已发布" value="published" />
            <el-option label="草稿" value="draft" />
            <el-option label="已归档" value="archived" />
          </el-select>
        </el-col>
        <el-col :span="5">
          <el-select v-model="filters.category_id" placeholder="分类" clearable @change="fetchArticles" style="width: 100%">
            <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-col>
        <el-col :span="6">
          <el-button @click="fetchArticles" type="primary">筛选</el-button>
          <el-button @click="resetFilters">重置</el-button>
        </el-col>
      </el-row>
    </el-card>

    <!-- Article Table -->
    <el-card shadow="never">
      <el-table :data="articles" v-loading="loading" stripe>
        <el-table-column prop="title" label="标题" min-width="250">
          <template #default="{ row }">
            <div class="article-title">
              <span class="title-text">{{ row.title }}</span>
              <el-tag v-if="row.status === 'draft'" size="small" type="warning">草稿</el-tag>
              <el-tag v-else-if="row.status === 'archived'" size="small" type="info">归档</el-tag>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="分类" width="140">
          <template #default="{ row }">{{ row.category?.name || '—' }}</template>
        </el-table-column>
        <el-table-column label="阅读" width="70" align="center">
          <template #default="{ row }">{{ row.view_count }}</template>
        </el-table-column>
        <el-table-column label="满意度" width="100" align="center">
          <template #default="{ row }">
            <span v-if="row.helpful_count + row.unhelpful_count > 0" :class="rateColor(row)">
              {{ getSatisfaction(row) }}%
            </span>
            <span v-else class="text-gray">—</span>
          </template>
        </el-table-column>
        <el-table-column label="作者" width="120">
          <template #default="{ row }">{{ row.author?.name || '—' }}</template>
        </el-table-column>
        <el-table-column label="更新时间" width="170">
          <template #default="{ row }">{{ formatTime(row.updated_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button text size="small" @click="viewArticle(row)">编辑</el-button>
            <el-button
              v-if="row.status === 'draft'"
              text size="small" type="primary"
              @click="handlePublish(row)"
            >发布</el-button>
            <el-button
              v-if="row.status === 'published'"
              text size="small" type="warning"
              @click="handleArchive(row)"
            >归档</el-button>
            <el-popconfirm title="确认删除？" @confirm="handleDelete(row)">
              <template #reference>
                <el-button text size="small" type="danger">删除</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="page"
          :page-size="perPage"
          :total="total"
          layout="prev, pager, next, total"
          @current-change="fetchArticles"
        />
      </div>
    </el-card>

    <!-- Create/Edit Article Dialog -->
    <el-dialog v-model="showCreate" :title="editing ? '编辑文章' : '写文章'" width="800px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="标题" required>
          <el-input v-model="form.title" placeholder="文章标题" />
        </el-form-item>
        <el-form-item label="分类">
          <el-select v-model="form.category_id" placeholder="选择分类" clearable style="width: 100%">
            <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="标签">
          <el-select v-model="form.tags" multiple filterable allow-create default-first-option
            placeholder="输入标签后回车" style="width: 100%">
            <el-option v-for="t in form.tags" :key="t" :label="t" :value="t" />
          </el-select>
        </el-form-item>
        <el-form-item label="摘要">
          <el-input v-model="form.excerpt" type="textarea" :rows="2" placeholder="可选摘要" />
        </el-form-item>
        <el-form-item label="内容" required>
          <el-input v-model="form.content" type="textarea" :rows="16"
            placeholder="支持 Markdown 格式&#10;也可粘贴纯文本" />
        </el-form-item>
        <el-form-item v-if="editing" label="变更说明">
          <el-input v-model="form.change_summary" placeholder="本轮更新的简要说明（可选）" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreate = false">取消</el-button>
        <el-button @click="saveDraft">存为草稿</el-button>
        <el-button type="primary" @click="saveAndPublish">{{ editing ? '保存' : '发布' }}</el-button>
      </template>
    </el-dialog>

    <!-- Category Management Dialog -->
    <el-dialog v-model="showCategoryDialog" title="管理分类" width="500px">
      <el-table :data="categories" stripe>
        <el-table-column prop="name" label="名称" />
        <el-table-column label="文章数" width="80" align="center">
          <template #default="{ row }">{{ row.article_count || 0 }}</template>
        </el-table-column>
        <el-table-column label="操作" width="150">
          <template #default="{ row }">
            <el-button text size="small" @click="editCategory(row)">编辑</el-button>
            <el-popconfirm title="确认删除？" @confirm="handleDeleteCategory(row)">
              <template #reference>
                <el-button text size="small" type="danger">删除</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
      <div class="mt-4">
        <el-input v-model="newCategoryName" placeholder="新分类名称" style="width: 240px" class="mr-2" />
        <el-button type="primary" @click="handleCreateCategory" :disabled="!newCategoryName.trim()">添加</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus, Search } from '@element-plus/icons-vue';
import kbApi from '@/api/kb';

const loading = ref(false);
const showCreate = ref(false);
const showCategoryDialog = ref(false);
const editing = ref(false);
const page = ref(1);
const perPage = ref(15);
const total = ref(0);
const newCategoryName = ref('');

const stats = reactive({
  published: 0, drafts: 0, categories: 0, total_views: 0,
});

const filters = reactive({
  search: '', status: '', category_id: null,
});

const articles = ref([]);
const categories = ref([]);

const form = reactive({
  id: null, title: '', category_id: null, tags: [],
  excerpt: '', content: '', change_summary: '',
});

function formatTime(t) {
  if (!t) return '—';
  return new Date(t).toLocaleString('zh-CN');
}

function getSatisfaction(row) {
  const total = row.helpful_count + row.unhelpful_count;
  if (total === 0) return 0;
  return Math.round((row.helpful_count / total) * 100);
}

function rateColor(row) {
  const rate = getSatisfaction(row);
  if (rate >= 80) return 'color: #67c23a';
  if (rate >= 50) return 'color: #e6a23c';
  return 'color: #f56c6c';
}

function resetForm() {
  form.id = null;
  form.title = '';
  form.category_id = null;
  form.tags = [];
  form.excerpt = '';
  form.content = '';
  form.change_summary = '';
  editing.value = false;
}

async function loadCategories() {
  try {
    const { data: res } = await kbApi.categories();
    if (res.success) categories.value = res.data || [];
  } catch { /* ignore */ }
}

async function fetchArticles() {
  loading.value = true;
  try {
    const params = { page: page.value, per_page: perPage.value };
    if (filters.search) params.search = filters.search;
    if (filters.status) params.status = filters.status;
    if (filters.category_id) params.category_id = filters.category_id;

    const { data: res } = await kbApi.adminArticles(params);
    if (res.success) {
      articles.value = res.data?.data || [];
      total.value = res.data?.total || 0;
    }
  } catch { /* ignore */ } finally {
    loading.value = false;
  }
}

async function fetchStats() {
  try {
    const { data: res } = await kbApi.adminArticles({ per_page: 1 });
    if (res.success) {
      stats.categories = categories.value.length;
      stats.total_views = articles.value.reduce((s, a) => s + (a.view_count || 0), 0);
    }
  } catch { /* ignore */ }
}

function resetFilters() {
  filters.search = '';
  filters.status = '';
  filters.category_id = null;
  fetchArticles();
}

function viewArticle(row) {
  resetForm();
  editing.value = true;
  form.id = row.id;
  form.title = row.title;
  form.category_id = row.category_id;
  form.tags = row.tags || [];
  form.excerpt = row.excerpt || '';
  form.content = row.content;
  showCreate.value = true;
}

async function saveDraft() {
  return saveArticle('draft');
}

async function saveAndPublish() {
  return saveArticle('published');
}

async function saveArticle(status) {
  if (!form.title.trim() || !form.content.trim()) {
    ElMessage.warning('标题和内容不能为空');
    return;
  }

  try {
    if (editing.value && form.id) {
      const { data: res } = await kbApi.updateArticle(form.id, {
        ...form,
        status,
      });
      if (res.success) {
        ElMessage.success('已保存');
      }
    } else {
      const { data: res } = await kbApi.createArticle({
        ...form,
        status,
      });
      if (res.success) {
        ElMessage.success(status === 'published' ? '文章已发布' : '草稿已保存');
      }
    }
    showCreate.value = false;
    resetForm();
    fetchArticles();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '操作失败');
  }
}

async function handlePublish(row) {
  try {
    const { data: res } = await kbApi.publishArticle(row.id);
    if (res.success) {
      ElMessage.success('已发布');
      fetchArticles();
    }
  } catch { /* ignore */ }
}

async function handleArchive(row) {
  try {
    const { data: res } = await kbApi.archiveArticle(row.id);
    if (res.success) {
      ElMessage.success('已归档');
      fetchArticles();
    }
  } catch { /* ignore */ }
}

async function handleDelete(row) {
  try {
    const { data: res } = await kbApi.deleteArticle(row.id);
    if (res.success) {
      ElMessage.success('已删除');
      fetchArticles();
    }
  } catch { ElMessage.error('删除失败') }
}

async function handleCreateCategory() {
  if (!newCategoryName.value.trim()) return;
  try {
    const { data: res } = await kbApi.createCategory({ name: newCategoryName.value });
    if (res.success) {
      ElMessage.success('分类已创建');
      newCategoryName.value = '';
      loadCategories();
    }
  } catch { ElMessage.error('创建失败') }
}

function editCategory(row) {
  // Inline rename via prompt
}

async function handleDeleteCategory(row) {
  try {
    const { data: res } = await kbApi.deleteCategory(row.id);
    if (res.success) {
      ElMessage.success('已删除');
      loadCategories();
    }
  } catch { ElMessage.error('删除失败') }
}

onMounted(() => {
  loadCategories();
  fetchArticles();
});
</script>

<style scoped>
.knowledge-base {
  padding: 0;
}

.stat-card {
  text-align: center;
  padding: 8px;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #303133;
}

.stat-label {
  font-size: 13px;
  color: #909399;
  margin-top: 4px;
}

.mb-4 {
  margin-bottom: 16px;
}

.mt-4 {
  margin-top: 16px;
}

.mr-2 {
  margin-right: 8px;
}

.pagination-wrap {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}

.article-title {
  display: flex;
  align-items: center;
  gap: 8px;
}

.title-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.text-gray {
  color: #c0c4cc;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.page-header h2 {
  margin: 0;
}
</style>
