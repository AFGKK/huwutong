<template>
  <div class="kb-page">
    <!-- 页面头部 -->
    <div class="page-header">
      <h2>{{ t('kb_page.title') }}</h2>
      <div class="header-actions">
        <el-button v-if="helpMainTab === 'help'" @click="fetchAll">
          <el-icon><Refresh /></el-icon> {{ t('blog_page.refresh') }}
        </el-button>
        <el-button v-if="helpMainTab === 'tutorials'" @click="tu_fetchTutorials">
          <el-icon><Refresh /></el-icon> {{ t('tutorials_page.refresh') }}
        </el-button>
      </div>
    </div>

    <el-tabs v-model="helpMainTab" class="help-doc-tabs">
      <!-- Tab 1: 帮助中心 -->
      <el-tab-pane :label="t('kb_page.title')" name="help">
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
                  :placeholder="t('channels_page.search_article_ph')"
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
                  :placeholder="t('blog_page.select_category_ph')"
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
                <el-icon><Plus /></el-icon> {{ t('blog_page.new_post') }}
              </el-button>
              <el-button @click="showCatDialog = true">
                <el-icon><FolderOpened /></el-icon> {{ t('blog_page.category_manage') }}
              </el-button>
              <el-button @click="handleExport" :loading="exporting">
                <el-icon><Download /></el-icon> {{ t('kb_page.export_md') }}
              </el-button>
            </div>
          </div>
        </el-card>

        <!-- 批量操作栏 -->
        <el-card v-if="selectedIds.length > 0" shadow="never" class="mb-4">
          <div style="display:flex;align-items:center;gap:12px">
            <span style="font-size:13px;color:#606266">{{ t('blog_page.selected_count', { n: selectedIds.length }) }}</span>
            <el-button size="small" @click="clearSelection">{{ t('blog_page.clear_selection') }}</el-button>
            <el-button size="small" type="success" @click="handleBatchPublish" :loading="batchLoading">
              <el-icon><Upload /></el-icon> {{ t('blog_page.batch_publish') }}
            </el-button>
            <el-button size="small" type="warning" @click="handleBatchArchive" :loading="batchLoading">
              <el-icon><FolderOpened /></el-icon> {{ t('kb_page.batch_archive') }}
            </el-button>
            <el-button size="small" type="danger" @click="handleBatchDelete" :loading="batchLoading">
              <el-icon><Delete /></el-icon> {{ t('blog_page.batch_delete') }}
            </el-button>
          </div>
        </el-card>

        <!-- 文章表格 -->
        <el-card shadow="never">
          <el-table ref="tableRef" :data="articles" v-loading="loading" stripe style="width: 100%"
            @selection-change="onSelectionChange">
            <el-table-column type="selection" width="45" />
            <el-table-column prop="id" label="ID" width="60" />
            <el-table-column :label="t('blog_page.col_title')" min-width="260">
              <template #default="{ row }">
                <div class="title-cell">
                  <span class="title-text">{{ row.title }}</span>
                  <el-tag
                    v-if="row.status === 'draft'"
                    size="small"
                    type="warning"
                    style="margin-left: 6px"
                  >{{ statusLabels.draft }}</el-tag>
                  <el-tag
                    v-else-if="row.status === 'archived'"
                    size="small"
                    type="info"
                    style="margin-left: 6px"
                  >{{ statusLabels.archived }}</el-tag>
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
            <el-table-column :label="t('blog_page.col_author')" width="120">
              <template #default="{ row }">{{ row.author?.name || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('kb_page.col_views')" width="80" align="center">
              <template #default="{ row }">{{ row.view_count ?? 0 }}</template>
            </el-table-column>
            <el-table-column :label="t('kb_page.col_helpful')" width="90" align="center">
              <template #default="{ row }">
                <span v-if="(row.helpful_count ?? 0) + (row.unhelpful_count ?? 0) > 0" :style="{ color: satisfactionColor(row) }">
                  {{ satisfactionRate(row) }}%
                </span>
                <span v-else class="text-muted">—</span>
              </template>
            </el-table-column>
            <el-table-column :label="t('blog_page.col_status')" width="110" align="center">
              <template #default="{ row }">
                <el-tag
                  :type="row.status === 'published' ? 'success' : row.status === 'draft' ? 'warning' : 'info'"
                  size="small"
                >
                  {{ statusLabel(row.status) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('blog_page.col_created_at')" width="170">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column :label="t('blog_page.col_actions')" width="260" fixed="right">
              <template #default="{ row }">
                <el-button text size="small" type="primary" @click="openEdit(row)">{{ t('actions.edit') }}</el-button>
                <el-button
                  v-if="row.status === 'draft'"
                  text size="small" type="success"
                  @click="handlePublish(row)"
                >{{ t('blog_page.publish') }}</el-button>
                <el-button
                  v-if="row.status === 'published'"
                  text size="small" type="warning"
                  @click="handleArchive(row)"
                >{{ t('kb_page.archive') }}</el-button>
                <el-popconfirm :title="t('blog_page.confirm_delete_post')" @confirm="handleDelete(row)">
                  <template #reference>
                    <el-button text size="small" type="danger">{{ t('actions.delete') }}</el-button>
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
          :title="isEditing ? t('kb_page.edit_dialog_title') : t('kb_page.create_dialog_title')"
          width="720px"
          :close-on-click-modal="false"
        >
          <el-form ref="formRef" :model="form" :rules="formRules" label-width="80px">
            <el-form-item :label="t('kb_page.form.category')" prop="category_id">
              <el-select
                v-model="form.category_id"
                :placeholder="t('blog_page.select_category_ph')"
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
            <el-form-item :label="t('kb_page.form.title')" prop="title">
              <el-input v-model="form.title" :placeholder="t('kb_page.form.title_ph')" @input="onTitleInput" />
            </el-form-item>
            <el-form-item :label="t('kb_page.form.slug')" prop="slug">
              <el-input v-model="form.slug" :placeholder="t('kb_page.form.slug_ph')">
                <template #append>
                  <el-button @click="generateSlug">{{ t('kb_page.form.auto_generate') }}</el-button>
                </template>
              </el-input>
            </el-form-item>
            <el-form-item :label="t('kb_page.form.tags')" prop="tags">
              <el-select
                v-model="form.tags"
                multiple
                filterable
                allow-create
                default-first-option
                :placeholder="t('kb_page.form.tags_ph')"
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
            <el-form-item :label="t('kb_page.form.status')" prop="status">
              <el-radio-group v-model="form.status">
                <el-radio value="draft">{{ statusLabels.draft }}</el-radio>
                <el-radio value="published">{{ statusLabels.published }}</el-radio>
              </el-radio-group>
            </el-form-item>
            <el-form-item :label="t('kb_page.form.content')" prop="content">
              <el-input
                v-model="form.content"
                type="textarea"
                :rows="14"
                :placeholder="t('kb_page.form.content_ph')"
              />
            </el-form-item>
          </el-form>
          <template #footer>
            <el-button @click="showDialog = false">{{ t('actions.cancel') }}</el-button>
            <el-button type="primary" :loading="submitting" @click="confirmSubmit">
              {{ isEditing ? t('kb_page.save_changes') : t('kb_page.create_article') }}
            </el-button>
          </template>
        </el-dialog>

        <!-- 分类管理对话框 -->
        <el-dialog v-model="showCatDialog" :title="t('blog_page.category_dialog_title')" width="550px">
          <div class="mb-4">
            <el-button type="primary" size="small" @click="openCatForm" :icon="Plus">{{ t('blog_page.add_category') }}</el-button>
          </div>

          <el-form v-if="showCatForm" :model="catForm" label-width="80px" size="small" class="mb-4" :inline="true">
            <el-form-item :label="t('blog_page.name')">
              <el-input v-model="catForm.name" :placeholder="t('blog_page.category_name_ph')" style="width:150px" />
            </el-form-item>
            <el-form-item :label="t('blog_page.sort_order')">
              <el-input-number v-model="catForm.sort_order" :min="0" :max="999" style="width:100px" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="saveCat">{{ t('actions.save') }}</el-button>
              <el-button @click="showCatForm = false">{{ t('actions.cancel') }}</el-button>
            </el-form-item>
          </el-form>

          <el-table :data="categories" stripe size="small">
            <el-table-column prop="name" :label="t('blog_page.name')" min-width="140" />
            <el-table-column prop="slug" :label="t('blog_page.slug')" min-width="120">
              <template #default="{ row }"><code>{{ row.slug }}</code></template>
            </el-table-column>
            <el-table-column prop="articles_count" :label="t('blog_page.posts_count')" width="70" align="center" />
            <el-table-column prop="sort_order" :label="t('blog_page.sort_order')" width="60" align="center" />
            <el-table-column :label="t('blog_page.col_actions')" width="140">
              <template #default="{ row }">
                <el-button size="small" text type="primary" @click="editCat(row)">{{ t('actions.edit') }}</el-button>
                <el-popconfirm :title="t('blog_page.confirm_delete')" @confirm="deleteCat(row)">
                  <template #reference>
                    <el-button size="small" text type="danger" :disabled="row.articles_count > 0">{{ t('actions.delete') }}</el-button>
                  </template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
        </el-dialog>
      </el-tab-pane>

      <!-- Tab 2: 入门教程（懒加载） -->
      <el-tab-pane :label="t('tutorials_page.title')" name="tutorials">
        <template v-if="tu_tabVisited">
          <el-row :gutter="16">
            <el-col v-for="item in tu_list" :key="item.tutorial?.id" :span="12" class="mb-4">
              <el-card shadow="hover" class="tutorial-card" @click="tu_openTutorial(item.tutorial)">
                <div class="tutorial-header">
                  <div class="tutorial-icon">
                    <el-icon :size="32" color="#0f172a"><Reading /></el-icon>
                  </div>
                  <div class="tutorial-meta">
                    <h3>{{ item.tutorial?.title }}</h3>
                    <el-tag size="small" type="info">{{ tu_categoryLabel(item.tutorial?.category) }}</el-tag>
                  </div>
                </div>
                <p class="tutorial-desc">{{ item.tutorial?.description }}</p>

                <div class="tutorial-footer">
                  <div class="step-count">
                    {{ t('tutorials_page.step_count', { n: item.tutorial?.steps?.length || 0 }) }}
                  </div>
                  <div v-if="item.progress" class="progress-info">
                    <el-progress
                      v-if="item.progress.is_completed"
                      :percentage="100"
                      :stroke-width="4"
                      :width="40"
                      type="circle"
                      color="#67C23A"
                    />
                    <span v-else class="step-progress">
                      {{ t('tutorials_page.step_of', {
                        current: (item.progress.current_step || 0) + 1,
                        total: item.tutorial?.steps?.length || 0,
                      }) }}
                    </span>
                  </div>
                  <div v-else class="progress-info">
                    <span class="not-started">{{ t('tutorials_page.not_started') }}</span>
                  </div>
                </div>
              </el-card>
            </el-col>
          </el-row>

          <!-- 教程详情弹窗 -->
          <el-dialog
            v-model="tu_showDialog"
            :title="tu_activeTutorial?.title || t('tutorials_page.default_title')"
            width="650px"
            :close-on-click-modal="false"
          >
            <template v-if="tu_activeTutorial">
              <div class="tutorial-steps">
                <div class="step-counter">
                  <el-tag type="primary">
                    {{ t('tutorials_page.step_of', {
                      current: tu_currentStep + 1,
                      total: tu_activeTutorial.steps?.length || 0,
                    }) }}
                  </el-tag>
                  <el-progress
                    :percentage="tu_progressPct"
                    :stroke-width="4"
                    style="flex:1; max-width: 300px"
                  />
                </div>

                <div class="step-content">
                  <div class="step-title">
                    <el-icon :size="24" color="#0f172a"><Reading /></el-icon>
                    <h3>{{ tu_activeStep?.title }}</h3>
                  </div>
                  <div class="step-body">
                    <p>{{ tu_activeStep?.content }}</p>
                  </div>
                </div>

                <div class="step-nav">
                  <el-button
                    :disabled="tu_currentStep <= 0"
                    @click="tu_prevStep"
                  >
                    {{ t('actions.prev') }}
                  </el-button>
                  <el-button
                    v-if="tu_currentStep < (tu_activeTutorial.steps?.length || 1) - 1"
                    type="primary"
                    @click="tu_nextStep"
                  >
                    {{ t('actions.next') }}
                  </el-button>
                  <el-button
                    v-else
                    type="success"
                    @click="tu_complete"
                  >
                    {{ tu_isCompleted ? t('tutorials_page.completed') : t('tutorials_page.complete') }}
                  </el-button>
                </div>
              </div>
            </template>
          </el-dialog>
        </template>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Plus, Refresh, FolderOpened, Download, Upload, Delete, Reading } from '@element-plus/icons-vue';
import kbApi from '@/api/kb';
import onboardingApi from '@/api/onboarding';

const { t, locale } = useI18n();

// ─── 主 tab 切换 ───
const helpMainTab = ref('help');

// ===================== 帮助中心 (knowledge-base) =====================

// ─── 统计卡片 ───
const stats = reactive({
  total: 0,
  published: 0,
  drafts: 0,
  archived: 0,
  total_feedback: 0,
  avg_satisfaction: 0,
});

const statusLabels = computed(() => ({
  draft: t('blog_page.status.draft'),
  published: t('blog_page.status.published'),
  archived: t('kb_page.status.archived'),
}));

const STATUS_MAP = computed(() => ({
  published: { type: 'success', label: statusLabels.value.published },
  draft: { type: 'warning', label: statusLabels.value.draft },
  archived: { type: 'info', label: statusLabels.value.archived },
}));

function statusLabel(s) {
  return STATUS_MAP.value[s]?.label || s;
}

const statCards = computed(() => [
  { label: t('kb_page.stats.total'), value: stats.total, color: '#0f172a' },
  { label: t('kb_page.stats.published'), value: stats.published, color: '#67c23a' },
  { label: t('kb_page.stats.drafts'), value: stats.drafts, color: '#e6a23c' },
  { label: t('kb_page.stats.archived'), value: stats.archived, color: '#909399' },
  { label: t('kb_page.stats.total_feedback'), value: stats.total_feedback, color: '#b37feb' },
  { label: t('kb_page.stats.avg_helpful_rate'), value: stats.avg_satisfaction + '%', color: '#f56c6c' },
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
    await ElMessageBox.confirm(
      t('kb_page.confirm_msgs.batch_publish', { n: selectedIds.value.length }),
      t('kb_page.confirm_titles.batch_publish'),
    );
    batchLoading.value = true;
    const { data: res } = await kbApi.batchPublish(selectedIds.value);
    ElMessage.success(res?.message || t('kb_page.messages.publish_ok'));
    clearSelection();
    fetchAll();
  } catch { /* cancelled */ }
  finally { batchLoading.value = false; }
}

async function handleBatchArchive() {
  if (selectedIds.value.length === 0) return;
  try {
    await ElMessageBox.confirm(
      t('kb_page.confirm_msgs.batch_archive', { n: selectedIds.value.length }),
      t('kb_page.confirm_titles.batch_archive'),
    );
    batchLoading.value = true;
    const { data: res } = await kbApi.batchArchive(selectedIds.value);
    ElMessage.success(res?.message || t('kb_page.messages.archive_ok'));
    clearSelection();
    fetchAll();
  } catch { /* cancelled */ }
  finally { batchLoading.value = false; }
}

async function handleBatchDelete() {
  if (selectedIds.value.length === 0) return;
  try {
    await ElMessageBox.confirm(
      t('kb_page.confirm_msgs.batch_delete', { n: selectedIds.value.length }),
      t('kb_page.confirm_titles.batch_delete'),
      { type: 'warning' },
    );
    batchLoading.value = true;
    const { data: res } = await kbApi.batchDelete(selectedIds.value);
    ElMessage.success(res?.message || t('kb_page.messages.delete_ok'));
    clearSelection();
    fetchAll();
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
    ElMessage.success(t('kb_page.messages.export_ok'));
  } catch { ElMessage.error(t('kb_page.messages.export_fail')); }
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
  if (!catForm.name) { ElMessage.warning(t('kb_page.messages.category_name_required')); return; }
  try {
    if (editingCatId.value) {
      await kbApi.updateCategory(editingCatId.value, { name: catForm.name, sort_order: catForm.sort_order });
      ElMessage.success(t('kb_page.messages.category_updated'));
    } else {
      await kbApi.createCategory({ name: catForm.name, sort_order: catForm.sort_order });
      ElMessage.success(t('kb_page.messages.category_created'));
    }
    showCatForm.value = false;
    await loadCategories();
  } catch { ElMessage.error(t('messages.failed')); }
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
    ElMessage.success(t('kb_page.messages.category_deleted'));
    await loadCategories();
  } catch { ElMessage.error(t('kb_page.messages.delete_fail')); }
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

const formRules = computed(() => ({
  title: [
    { required: true, message: t('kb_page.rules.title_required'), trigger: 'blur' },
  ],
  content: [
    { required: true, message: t('kb_page.rules.content_required'), trigger: 'blur' },
  ],
}));

// ─── 工具函数 ───
function formatTime(ts) {
  if (!ts) return '—';
  const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
  return new Date(ts).toLocaleString(loc);
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
      const tags = new Set();
      const walk = (items) => {
        for (const c of items) {
          if (c.tags) c.tags.forEach(tg => tags.add(tg));
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
      const all = paginated.total || 0;

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
    ElMessage.error(t('kb_page.messages.list_fail'));
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
        ElMessage.success(t('kb_page.messages.article_updated'));
      } else {
        ElMessage.error(res.data?.message || t('kb_page.messages.update_fail'));
        return;
      }
    } else {
      const res = await kbApi.createArticle(payload);
      if (res.data?.success) {
        ElMessage.success(t('kb_page.messages.article_created'));
      } else {
        ElMessage.error(res.data?.message || t('kb_page.messages.create_fail'));
        return;
      }
    }

    showDialog.value = false;
    await fetchAll();
  } catch (err) {
    ElMessage.error(err.response?.data?.message || t('messages.failed'));
  } finally {
    submitting.value = false;
  }
}

// ─── 操作 ───
async function handlePublish(row) {
  try {
    const res = await kbApi.publishArticle(row.id);
    if (res.data?.success) {
      ElMessage.success(t('kb_page.messages.article_published'));
      await fetchAll();
    }
  } catch {
    ElMessage.error(t('kb_page.messages.publish_fail'));
  }
}

async function handleArchive(row) {
  try {
    const res = await kbApi.archiveArticle(row.id);
    if (res.data?.success) {
      ElMessage.success(t('kb_page.messages.article_archived'));
      await fetchAll();
    }
  } catch {
    ElMessage.error(t('kb_page.messages.archive_fail'));
  }
}

async function handleDelete(row) {
  try {
    const res = await kbApi.deleteArticle(row.id);
    if (res.data?.success) {
      ElMessage.success(t('kb_page.messages.article_deleted'));
      await fetchAll();
    }
  } catch {
    ElMessage.error(t('kb_page.messages.delete_fail'));
  }
}

// ===================== 入门教程 (tutorials) =====================

const tu_tabVisited = ref(false);
const tu_list = ref([]);
const tu_showDialog = ref(false);
const tu_activeTutorial = ref(null);
const tu_currentStep = ref(0);
const tu_isCompleted = ref(false);
const tu_progress = ref(null);

const tu_categoryKeys = ['getting_started', 'core_features', 'integration', 'advanced'];

const tu_categoryLabels = computed(() => Object.fromEntries(
  tu_categoryKeys.map((k) => [k, t(`tutorials_page.categories.${k}`)]),
));

const tu_activeStep = computed(() => {
  if (!tu_activeTutorial.value?.steps) return null;
  return tu_activeTutorial.value.steps[tu_currentStep.value] || null;
});

const tu_progressPct = computed(() => {
  if (!tu_activeTutorial.value?.steps?.length) return 0;
  return Math.round(((tu_currentStep.value + 1) / tu_activeTutorial.value.steps.length) * 100);
});

async function tu_fetchTutorials() {
  try {
    const res = await onboardingApi.tutorials();
    if (res.success) {
      tu_list.value = res.data || [];
    }
  } catch {
    ElMessage.error(t('messages.load_failed'));
  }
}

async function tu_openTutorial(tutorial) {
  if (!tutorial) return;

  const tutItem = tu_list.value.find(tut => tut.tutorial?.id === tutorial.id);

  tu_activeTutorial.value = tutorial;
  tu_currentStep.value = tutItem?.progress?.current_step || 0;
  tu_isCompleted.value = tutItem?.progress?.is_completed || false;
  tu_progress.value = tutItem?.progress;
  tu_showDialog.value = true;
}

async function tu_nextStep() {
  if (!tu_activeTutorial.value) return;

  const nextStep = tu_currentStep.value + 1;
  tu_currentStep.value = nextStep;

  try {
    await onboardingApi.updateTutorialProgress(tu_activeTutorial.value.id, nextStep);
  } catch { /* ignore */ }
}

async function tu_prevStep() {
  if (tu_currentStep.value > 0) {
    tu_currentStep.value--;
  }
}

async function tu_complete() {
  if (!tu_activeTutorial.value || tu_isCompleted.value) return;

  try {
    const lastStep = (tu_activeTutorial.value.steps?.length || 1) - 1;
    await onboardingApi.updateTutorialProgress(tu_activeTutorial.value.id, lastStep);
    tu_isCompleted.value = true;
    ElMessage.success(t('tutorials_page.messages.completed'));

    await tu_fetchTutorials();
  } catch {
    ElMessage.error(t('messages.failed'));
  }
}

function tu_categoryLabel(cat) {
  return tu_categoryLabels.value[cat] || cat || t('tutorials_page.categories.general');
}

// ─── 懒加载：首次切换到 tutorials tab 时加载数据 ───
watch(helpMainTab, (val) => {
  if (val === 'tutorials' && !tu_tabVisited.value) {
    tu_tabVisited.value = true;
    tu_fetchTutorials();
  }
});

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

.help-doc-tabs {
  margin-top: 0;
}
.help-doc-tabs :deep(.el-tabs__header) {
  margin-bottom: 16px;
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

/* ─── Tutorials styles ─── */

.tutorial-card {
  cursor: pointer;
  transition: all 0.2s;
  height: 100%;
}
.tutorial-card:hover {
  border-color: var(--el-color-primary);
}

.tutorial-header {
  display: flex;
  gap: 14px;
  margin-bottom: 12px;
}
.tutorial-icon {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--el-color-primary-light-9);
  border-radius: 12px;
  flex-shrink: 0;
}
.tutorial-meta h3 {
  font-size: 16px;
  font-weight: 600;
  margin: 0 0 4px;
}

.tutorial-desc {
  font-size: 13px;
  color: var(--el-text-color-secondary);
  line-height: 1.5;
  margin: 0 0 16px;
}

.tutorial-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 12px;
  border-top: 1px solid var(--el-border-color-lighter);
}
.step-count { font-size: 13px; color: var(--el-text-color-secondary); }
.progress-info { display: flex; align-items: center; }
.step-progress { font-size: 13px; color: var(--el-color-primary); }
.not-started { font-size: 13px; color: var(--el-text-color-placeholder); }

/* Dialog */
.tutorial-steps { min-height: 300px; }

.step-counter {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
}

.step-content {
  margin-bottom: 24px;
}

.step-title {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}
.step-title h3 { margin: 0; font-size: 18px; font-weight: 600; }

.step-body p {
  font-size: 15px;
  line-height: 1.8;
  color: var(--el-text-color-regular);
  margin: 0;
}

.step-nav {
  display: flex;
  justify-content: space-between;
  padding-top: 20px;
  border-top: 1px solid var(--el-border-color-lighter);
}

:deep(.el-card__body) { padding: 20px; }
</style>
