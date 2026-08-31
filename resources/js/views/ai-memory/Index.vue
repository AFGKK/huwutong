<template>
  <div class="p-6">
    <!-- 头部 -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold">{{ t('ai_memory_page.title') }}</h1>
        <p class="text-gray-500 text-sm mt-1">{{ t('ai_memory_page.subtitle') }}</p>
      </div>
      <div class="flex gap-2">
        <el-button type="warning" plain :disabled="!stats.total" @click="handleClearAll">
          <el-icon><Delete /></el-icon> {{ t('ai_memory_page.btn_clear_all') }}
        </el-button>
        <el-button type="primary" @click="showCreate = true">
          <el-icon><Plus /></el-icon> {{ t('ai_memory_page.btn_add_manual') }}
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-6">
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-value">{{ stats.total }}</div>
          <div class="stat-label">{{ t('ai_memory_page.stat_total') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-value">{{ stats.high_priority }}</div>
          <div class="stat-label">{{ t('ai_memory_page.stat_high_priority') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-value">{{ stats.expiring_soon }}</div>
          <div class="stat-label">{{ t('ai_memory_page.stat_expiring_soon') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-value">{{ (stats.avg_confidence * 100).toFixed(0) }}%</div>
          <div class="stat-label">{{ t('ai_memory_page.stat_avg_confidence') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 类型分布 + 分类分布 -->
    <el-row :gutter="16" class="mb-6">
      <el-col :span="12">
        <el-card shadow="never">
          <template #header><span>{{ t('ai_memory_page.section_type_distribution') }}</span></template>
          <div v-if="stats.by_type?.length" class="flex flex-wrap gap-2">
            <el-tag v-for="item in stats.by_type" :key="item.type" :type="tagType(item.type)" class="text-sm">
              {{ typeLabel(item.type) }}: {{ item.total }}
            </el-tag>
          </div>
          <el-empty v-else :description="t('messages.no_data')" :image-size="60" />
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="never">
          <template #header><span>{{ t('ai_memory_page.section_category_distribution') }}</span></template>
          <div v-if="stats.by_category?.length" class="flex flex-wrap gap-2">
            <el-tag v-for="c in stats.by_category" :key="c.category" type="info" class="text-sm">
              {{ categoryLabel(c.category) }}: {{ c.total }}
            </el-tag>
          </div>
          <el-empty v-else :description="t('messages.no_data')" :image-size="60" />
        </el-card>
      </el-col>
    </el-row>

    <!-- 最近记忆 -->
    <el-card shadow="never" class="mb-6">
      <template #header><span>{{ t('ai_memory_page.section_recent') }}</span></template>
      <div v-if="stats.recent?.length" class="space-y-2">
        <div v-for="m in stats.recent" :key="m.id" class="flex items-start gap-3 p-2 hover:bg-gray-50 rounded">
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium truncate">{{ m.content }}</div>
            <div class="text-xs text-gray-400 mt-1">
              <el-tag :type="tagType(m.type)" size="small">{{ typeLabel(m.type) }}</el-tag>
              <span class="ml-2">{{ t('ai_memory_page.confidence_label') }}: {{ (m.confidence * 100).toFixed(0) }}%</span>
              <span class="ml-2">{{ t('ai_memory_page.priority_label') }}: {{ m.priority }}</span>
              <span class="ml-2">{{ formatTime(m.created_at) }}</span>
            </div>
          </div>
        </div>
      </div>
      <el-empty v-else :description="t('ai_memory_page.empty_recent')" :image-size="80" />
    </el-card>

    <!-- 全部记忆列表 -->
    <el-card shadow="never">
      <template #header>
        <div class="flex items-center justify-between">
          <span>{{ t('ai_memory_page.section_all') }}</span>
          <div class="flex gap-2">
            <el-input v-model="filters.search" :placeholder="t('ai_memory_page.search_ph')" clearable size="small" style="width:200px" @clear="fetchList" @keyup.enter="fetchList" />
            <el-select v-model="filters.type" :placeholder="t('ai_memory_page.filter_type_ph')" clearable size="small" style="width:120px" @change="fetchList">
              <el-option v-for="opt in typeOptionList" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-select v-model="filters.category" :placeholder="t('ai_memory_page.filter_category_ph')" clearable size="small" style="width:140px" @change="fetchList">
              <el-option v-for="opt in categoryOptionList" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
          </div>
        </div>
      </template>

      <el-table :data="list" v-loading="loading" stripe style="width:100%">
        <el-table-column prop="content" :label="t('ai_memory_page.col_content')" min-width="300" show-overflow-tooltip />
        <el-table-column prop="type" :label="t('ai_memory_page.col_type')" width="80">
          <template #default="{ row }">
            <el-tag :type="tagType(row.type)" size="small">{{ typeLabel(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="category" :label="t('ai_memory_page.col_category')" width="100">
          <template #default="{ row }">
            {{ categoryLabel(row.category) }}
          </template>
        </el-table-column>
        <el-table-column prop="source" :label="t('ai_memory_page.col_source')" width="80">
          <template #default="{ row }">
            <span class="text-xs text-gray-500">{{ sourceLabel(row.source) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="confidence" :label="t('ai_memory_page.col_confidence')" width="80">
          <template #default="{ row }">
            <span :class="confidenceColor(row.confidence)">{{ (row.confidence * 100).toFixed(0) }}%</span>
          </template>
        </el-table-column>
        <el-table-column prop="priority" :label="t('ai_memory_page.col_priority')" width="70" />
        <el-table-column :label="t('ai_memory_page.col_created_at')" width="150">
          <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
        </el-table-column>
        <el-table-column :label="t('ai_memory_page.col_actions')" width="200" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handleConfirm(row)">{{ t('actions.confirm') }}</el-button>
            <el-button link type="warning" size="small" @click="handleEdit(row)">{{ t('actions.edit') }}</el-button>
            <el-popconfirm :title="t('ai_memory_page.confirm_forget')" @confirm="handleDelete(row)">
              <template #reference>
                <el-button link type="danger" size="small">{{ t('ai_memory_page.btn_forget') }}</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>

      <div class="flex justify-center mt-4" v-if="pagination.total > pagination.per_page">
        <el-pagination
          v-model:current-page="pagination.current_page"
          :page-size="pagination.per_page"
          :total="pagination.total"
          layout="prev, pager, next"
          @current-change="fetchList"
        />
      </div>
    </el-card>

    <!-- 手动添加对话框 -->
    <el-dialog v-model="showCreate" :title="t('ai_memory_page.dialog_create_title')" width="500px">
      <el-form :model="createForm" label-position="top">
        <el-form-item :label="t('ai_memory_page.label_content')" required>
          <el-input v-model="createForm.content" type="textarea" :rows="3" maxlength="2000" show-word-limit />
        </el-form-item>
        <el-form-item :label="t('ai_memory_page.label_type')">
          <el-select v-model="createForm.type" style="width:100%">
            <el-option v-for="opt in typeOptionList" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('ai_memory_page.label_category')">
          <el-select v-model="createForm.category" clearable style="width:100%">
            <el-option v-for="opt in categoryOptionList" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('ai_memory_page.label_priority')">
          <el-slider v-model="createForm.priority" :min="0" :max="255" show-input style="width:300px" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreate = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="saving" @click="handleCreate">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- 编辑对话框 -->
    <el-dialog v-model="showEdit" :title="t('ai_memory_page.dialog_edit_title')" width="500px">
      <el-form :model="editForm" label-position="top">
        <el-form-item :label="t('ai_memory_page.label_content')" required>
          <el-input v-model="editForm.content" type="textarea" :rows="3" maxlength="2000" show-word-limit />
        </el-form-item>
        <el-form-item :label="t('ai_memory_page.label_type')">
          <el-select v-model="editForm.type" style="width:100%">
            <el-option v-for="opt in typeOptionList" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('ai_memory_page.label_confidence')">
          <el-slider v-model="editForm.confidence" :min="0" :max="1" :step="0.05" show-input style="width:300px" />
        </el-form-item>
        <el-form-item :label="t('ai_memory_page.label_priority')">
          <el-slider v-model="editForm.priority" :min="0" :max="255" show-input style="width:300px" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showEdit = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="saving" @click="handleUpdate">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- AI 提取对话框 -->
    <el-dialog v-model="showExtract" :title="t('ai_memory_page.dialog_extract_title')" width="600px">
      <p class="text-sm text-gray-500 mb-3">{{ t('ai_memory_page.dialog_extract_desc') }}</p>
      <el-input v-model="extractText" type="textarea" :rows="8" :placeholder="t('ai_memory_page.extract_ph')" maxlength="10000" show-word-limit />
      <template #footer>
        <el-button @click="showExtract = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="extracting" @click="handleExtract">{{ t('ai_memory_page.btn_extract') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Delete } from '@element-plus/icons-vue';
import memoryApi from '@/api/memory';

const { t, locale } = useI18n();

// ── 状态 ──
const loading = ref(false);
const saving = ref(false);
const extracting = ref(false);
const list = ref([]);
const stats = ref({ total: 0, by_type: [], by_category: [], recent: [], expiring_soon: 0, high_priority: 0, avg_confidence: 0 });

const filters = reactive({ search: '', type: '', category: '' });
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 });

const showCreate = ref(false);
const showEdit = ref(false);
const showExtract = ref(false);
const createForm = reactive({ content: '', type: 'fact', category: null, priority: 5 });
const editForm = reactive({ id: null, content: '', type: 'fact', confidence: 0.8, priority: 5 });
const extractText = ref('');

const TYPE_KEYS = ['preference', 'fact', 'context', 'insight', 'behavior'];
const CATEGORY_KEYS = [
  'user_preference', 'user_fact', 'business_info', 'personal_info',
  'technical_context', 'project_context', 'conversation_style', 'ai_insight',
];
const SOURCE_KEYS = ['ai_extracted', 'manual', 'system', 'conversation'];

const typeLabels = computed(() => {
  const map = {};
  TYPE_KEYS.forEach((key) => { map[key] = t(`ai_memory_page.types.${key}`); });
  return map;
});

const categoryLabels = computed(() => {
  const map = {};
  CATEGORY_KEYS.forEach((key) => { map[key] = t(`ai_memory_page.categories.${key}`); });
  return map;
});

const sourceLabels = computed(() => {
  const map = {};
  SOURCE_KEYS.forEach((key) => { map[key] = t(`ai_memory_page.sources.${key}`); });
  return map;
});

const typeOptionList = computed(() =>
  TYPE_KEYS.map((value) => ({ value, label: typeLabels.value[value] })),
);

const categoryOptionList = computed(() =>
  CATEGORY_KEYS.map((value) => ({ value, label: categoryLabels.value[value] })),
);

function typeLabel(type) { return typeLabels.value[type] || type; }
function categoryLabel(category) { return categoryLabels.value[category] || category || '-'; }
function sourceLabel(source) { return sourceLabels.value[source] || source; }

function tagType(type) {
  const map = { preference: 'success', fact: 'primary', context: 'info', insight: 'warning', behavior: 'danger' };
  return map[type] || '';
}

function confidenceColor(c) {
  return c >= 0.8 ? 'text-green-600' : c >= 0.5 ? 'text-yellow-600' : 'text-gray-500';
}

function formatTime(time) {
  if (!time) return '';
  return new Date(time).toLocaleString(locale.value === 'zh_CN' ? 'zh-CN' : 'en-US');
}

// ── 数据加载 ──
async function fetchList() {
  loading.value = true;
  try {
    const params = { page: pagination.current_page, per_page: pagination.per_page, ...filters };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await memoryApi.list(params);
    list.value = res.data?.data || [];
    pagination.current_page = res.data?.meta?.current_page || 1;
    pagination.per_page = res.data?.meta?.per_page || 20;
    pagination.total = res.data?.meta?.total || 0;
  } catch (e) {
    ElMessage.error(t('ai_memory_page.messages.load_list_failed'));
  } finally {
    loading.value = false;
  }
}

async function fetchDashboard() {
  try {
    const res = await memoryApi.dashboard();
    stats.value = res.data?.data || stats.value;
  } catch (_) { /* ignore */ }
}

// ── 操作 ──
async function handleCreate() {
  if (!createForm.content.trim()) return ElMessage.warning(t('ai_memory_page.validation.content_required'));
  saving.value = true;
  try {
    await memoryApi.create({ ...createForm });
    ElMessage.success(t('ai_memory_page.messages.saved'));
    showCreate.value = false;
    createForm.content = '';
    createForm.type = 'fact';
    createForm.priority = 5;
    await fetchDashboard();
    await fetchList();
  } catch (e) {
    ElMessage.error(t('ai_memory_page.messages.save_failed'));
  } finally {
    saving.value = false;
  }
}

function handleEdit(row) {
  editForm.id = row.id;
  editForm.content = row.content;
  editForm.type = row.type;
  editForm.confidence = row.confidence;
  editForm.priority = row.priority;
  showEdit.value = true;
}

async function handleUpdate() {
  if (!editForm.content.trim()) return ElMessage.warning(t('ai_memory_page.validation.content_required'));
  saving.value = true;
  try {
    await memoryApi.update(editForm.id, {
      content: editForm.content,
      type: editForm.type,
      confidence: editForm.confidence,
      priority: editForm.priority,
    });
    ElMessage.success(t('ai_memory_page.messages.updated'));
    showEdit.value = false;
    await fetchList();
  } catch (e) {
    ElMessage.error(t('ai_memory_page.messages.update_failed'));
  } finally {
    saving.value = false;
  }
}

async function handleConfirm(row) {
  try {
    await memoryApi.confirm(row.id);
    ElMessage.success(t('ai_memory_page.messages.confirmed'));
    await fetchDashboard();
    await fetchList();
  } catch (e) {
    ElMessage.error(t('ai_memory_page.messages.confirm_failed'));
  }
}

async function handleDelete(row) {
  try {
    await memoryApi.remove(row.id);
    ElMessage.success(t('ai_memory_page.messages.forgotten'));
    await fetchDashboard();
    await fetchList();
  } catch (e) {
    ElMessage.error(t('ai_memory_page.messages.delete_failed'));
  }
}

async function handleExtract() {
  if (!extractText.value.trim()) return ElMessage.warning(t('ai_memory_page.validation.text_required'));
  extracting.value = true;
  try {
    const res = await memoryApi.extract(extractText.value);
    ElMessage.success(res.data?.message || t('ai_memory_page.messages.extract_complete'));
    showExtract.value = false;
    extractText.value = '';
    await fetchDashboard();
    await fetchList();
  } catch (e) {
    ElMessage.error(t('ai_memory_page.messages.extract_failed'));
  } finally {
    extracting.value = false;
  }
}

function handleClearAll() {
  ElMessageBox.confirm(t('ai_memory_page.confirm_clear_message'), t('ai_memory_page.confirm_clear_title'), {
    type: 'warning',
    confirmButtonText: t('ai_memory_page.confirm_clear_btn'),
    cancelButtonText: t('actions.cancel'),
  }).then(async () => {
    await memoryApi.clearAll();
    ElMessage.success(t('ai_memory_page.messages.cleared'));
    await fetchDashboard();
    await fetchList();
  }).catch(() => {});
}

// ── 初始化 ──
onMounted(() => {
  fetchDashboard();
  fetchList();
});
</script>

<style scoped>
.stat-card {
  text-align: center;
}
.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: #0f172a;
}
.stat-label {
  font-size: 13px;
  color: #909399;
  margin-top: 4px;
}
</style>
