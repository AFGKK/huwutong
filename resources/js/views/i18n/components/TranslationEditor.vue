<template>
  <div class="translation-editor">
    <!-- Filters -->
    <div class="filters-bar mb-4 p-4 bg-gray-50 rounded-lg">
      <el-row :gutter="12" align="middle">
        <el-col :span="5">
          <el-select v-model="filters.locale" :placeholder="t('translation_editor.select_locale')" clearable class="!w-full" @change="fetchTranslations">
            <el-option
              v-for="lang in languages"
              :key="lang.locale"
              :label="`${lang.flag || ''} ${lang.name}`"
              :value="lang.locale"
            />
          </el-select>
        </el-col>
        <el-col :span="5">
          <el-select v-model="filters.namespace_id" :placeholder="t('translation_editor.select_namespace')" clearable class="!w-full" @change="fetchTranslations">
            <el-option
              v-for="ns in namespaces"
              :key="ns.id"
              :label="ns.label || ns.namespace"
              :value="ns.id"
            />
          </el-select>
        </el-col>
        <el-col :span="6">
          <el-input v-model="filters.search" :placeholder="t('translation_editor.search_placeholder')" clearable @clear="fetchTranslations" @keyup.enter="fetchTranslations">
            <template #prefix>
              <el-icon><Search /></el-icon>
            </template>
          </el-input>
        </el-col>
        <el-col :span="3">
          <el-select v-model="filters.is_published" :placeholder="t('translation_editor.publish_status')" clearable class="!w-full" @change="fetchTranslations">
            <el-option :label="t('translation_editor.published')" :value="true" />
            <el-option :label="t('translation_editor.unpublished')" :value="false" />
          </el-select>
        </el-col>
        <el-col :span="3">
          <el-checkbox v-model="filters.missing_only" @change="fetchTranslations">{{ t('translation_editor.missing_only') }}</el-checkbox>
        </el-col>
        <el-col :span="2">
          <el-button type="primary" :icon="Search" @click="fetchTranslations">{{ t('actions.search') }}</el-button>
        </el-col>
      </el-row>
    </div>

    <!-- Per-language quick stats -->
    <div v-if="dashboard" class="mb-4 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2">
      <div
        v-for="pl in dashboard.per_language"
        :key="pl.locale"
        class="text-xs p-2 rounded border cursor-pointer"
        :class="filters.locale === pl.locale ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
        @click="filters.locale = pl.locale; fetchTranslations()"
      >
        <div class="font-medium">{{ pl.name }}</div>
        <div class="flex items-center gap-2 mt-1">
          <el-progress :percentage="pl.progress" :stroke-width="6" :status="pl.progress >= 100 ? 'success' : undefined" />
        </div>
        <div class="text-gray-400 mt-0.5">
          {{ pl.published }}/{{ pl.total }}
          <span v-if="pl.missing > 0" class="text-orange-500">{{ t('translation_editor.missing_count', { n: pl.missing }) }}</span>
        </div>
      </div>
    </div>

    <!-- Bulk Actions -->
    <div class="flex items-center justify-between mb-3">
      <div class="flex items-center gap-2">
        <el-checkbox
          v-model="selectAll"
          :indeterminate="selectedIds.length > 0 && selectedIds.length < paginatedItems.length"
          @change="handleSelectAll"
        />
        <span class="text-sm text-gray-500">{{ t('translation_editor.selected_count', { n: selectedIds.length }) }}</span>
        <template v-if="selectedIds.length > 0">
          <el-button size="small" @click="handleBulkPublish(true)">{{ t('translation_editor.bulk_publish') }}</el-button>
          <el-button size="small" @click="handleBulkPublish(false)">{{ t('translation_editor.bulk_unpublish') }}</el-button>
        </template>
      </div>
      <div class="flex items-center gap-2">
        <el-tag v-if="!filters.locale" type="warning" size="small">{{ t('translation_editor.select_locale_hint') }}</el-tag>
        <el-tag v-if="!filters.namespace_id" type="info" size="small">{{ t('translation_editor.all_namespaces') }}</el-tag>
      </div>
    </div>

    <!-- Translations Table -->
    <el-table
      :data="paginatedItems"
      v-loading="loading"
      stripe
      style="width: 100%"
      max-height="550"
      @selection-change="handleSelectionChange"
    >
      <el-table-column type="selection" width="40" />
      <el-table-column label="Key" prop="key" min-width="220" show-overflow-tooltip>
        <template #default="{ row }">
          <div class="flex items-center gap-1">
            <span class="font-mono text-xs">{{ row.namespace?.namespace }}.</span>
            <span class="font-medium text-sm">{{ row.key }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column :label="t('translation_editor.cols.fallback')" min-width="200" show-overflow-tooltip>
        <template #default="{ row }">
          <span class="text-gray-400">{{ row.default_value || '-' }}</span>
        </template>
      </el-table-column>
      <el-table-column :label="t('translation_editor.cols.value')" min-width="250">
        <template #default="{ row }">
          <div class="flex items-center gap-2">
            <el-input
              v-if="editingId === row.id"
              v-model="editValue"
              size="small"
              type="textarea"
              :rows="2"
              @keyup.ctrl.enter="handleSaveInline(row)"
              @blur="handleSaveInline(row)"
            />
            <span v-else class="text-sm" :class="!row.value ? 'text-orange-400 italic' : ''" @dblclick="startInlineEdit(row)">
              {{ row.value || t('translation_editor.click_to_edit') }}
            </span>
          </div>
        </template>
      </el-table-column>
      <el-table-column :label="t('translation_editor.cols.status')" width="100" align="center">
        <template #default="{ row }">
          <el-switch
            :model-value="row.is_published"
            size="small"
            @change="(val) => handleTogglePublish(row, val)"
          />
        </template>
      </el-table-column>
      <el-table-column :label="t('translation_editor.cols.auto')" width="60" align="center">
        <template #default="{ row }">
          <el-tag v-if="row.is_auto_translated" type="warning" size="small">AI</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('translation_editor.cols.actions')" width="140" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" size="small" @click="handleAutoTranslate(row)">{{ t('translation_editor.ai_translate') }}</el-button>
          <el-button link type="primary" size="small" @click="handleViewDetail(row)">{{ t('translation_editor.detail') }}</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- Pagination -->
    <div class="flex justify-between items-center mt-4">
      <div class="text-sm text-gray-500">
        {{ t('translation_editor.pagination', { total: totalItems, perPage }) }}
      </div>
      <el-pagination
        v-model:current-page="currentPage"
        :page-size="perPage"
        :total="totalItems"
        layout="prev, pager, next"
        @current-change="handlePageChange"
      />
    </div>

    <!-- Detail Dialog -->
    <el-dialog
      v-model="detailDialog.visible"
      :title="t('translation_editor.detail_title')"
      width="600px"
      :close-on-click-modal="false"
    >
      <template v-if="detailDialog.translation">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="Key">
            <code class="text-xs">{{ detailDialog.translation.namespace?.namespace }}.{{ detailDialog.translation.key }}</code>
          </el-descriptions-item>
          <el-descriptions-item :label="t('translation_editor.cols.locale')">
            {{ detailDialog.translation.locale }}
          </el-descriptions-item>
          <el-descriptions-item :label="t('translation_editor.cols.default')" :span="2">
            <span class="text-gray-500">{{ detailDialog.translation.default_value || '-' }}</span>
          </el-descriptions-item>
          <el-descriptions-item :label="t('translation_editor.cols.value')" :span="2">
            <el-input
              v-model="detailDialog.editValue"
              type="textarea"
              :rows="3"
              :placeholder="t('translation_editor.value_placeholder')"
            />
          </el-descriptions-item>
          <el-descriptions-item :label="t('translation_editor.cols.status')">
            <el-switch v-model="detailDialog.translation.is_published" size="small" />
          </el-descriptions-item>
          <el-descriptions-item :label="t('translation_editor.ai_translated')">
            <el-tag v-if="detailDialog.translation.is_auto_translated" type="warning" size="small">{{ t('translation_editor.yes') }}</el-tag>
            <span v-else class="text-gray-400">{{ t('translation_editor.no') }}</span>
          </el-descriptions-item>
        </el-descriptions>

        <!-- History -->
        <div class="mt-4">
          <h4 class="text-sm font-medium text-gray-700 mb-2">{{ t('translation_editor.history') }}</h4>
          <el-timeline v-if="detailDialog.history?.length">
            <el-timeline-item
              v-for="h in detailDialog.history.slice(0, 20)"
              :key="h.id"
              :timestamp="h.created_at"
              placement="top"
              size="small"
            >
              <div class="text-xs">
                <el-tag :type="h.action === 'auto_translated' ? 'warning' : h.action === 'imported' ? 'info' : 'primary'" size="small">
                  {{ h.action }}
                </el-tag>
                <span v-if="h.user" class="ml-1 text-gray-500">{{ h.user.name }}</span>
              </div>
              <div v-if="h.old_value && h.new_value" class="mt-1 text-xs">
                <div class="text-gray-400 line-through">{{ h.old_value }}</div>
                <div class="text-green-600">{{ h.new_value }}</div>
              </div>
            </el-timeline-item>
          </el-timeline>
          <p v-else class="text-xs text-gray-400">{{ t('translation_editor.no_history') }}</p>
        </div>
      </template>
      <template #footer>
        <el-button @click="detailDialog.visible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="detailDialog.saving" @click="handleSaveDetail">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Search } from '@element-plus/icons-vue';
import i18nApi from '../../../api/i18n';

const { t } = useI18n();

const props = defineProps({
  languages: { type: Array, default: () => [] },
  namespaces: { type: Array, default: () => [] },
  dashboard: { type: Object, default: null },
});

defineEmits(['scan']);

const loading = ref(false);
const translations = ref([]);
const totalItems = ref(0);
const currentPage = ref(1);
const perPage = ref(50);

const filters = reactive({
  locale: '',
  namespace_id: null,
  search: '',
  is_published: null,
  missing_only: false,
});

const selectedIds = ref([]);
const selectAll = ref(false);

const editingId = ref(null);
const editValue = ref('');

const detailDialog = reactive({
  visible: false,
  translation: null,
  history: [],
  editValue: '',
  saving: false,
});

const paginatedItems = computed(() => translations.value);

async function fetchTranslations() {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value,
      ...filters,
    };
    if (params.is_published === null || params.is_published === undefined) {
      delete params.is_published;
    }
    if (!params.missing_only) delete params.missing_only;
    if (!params.locale) delete params.locale;
    if (!params.namespace_id) delete params.namespace_id;
    if (!params.search) delete params.search;

    const res = await i18nApi.getTranslations(params);
    translations.value = res.data.data?.data || [];
    totalItems.value = res.data.data?.total || 0;
    currentPage.value = res.data.data?.current_page || 1;
  } catch (e) {
    console.error('Failed to fetch translations:', e);
  } finally {
    loading.value = false;
  }
}

function handlePageChange(page) {
  currentPage.value = page;
  fetchTranslations();
}

function handleSelectionChange(selection) {
  selectedIds.value = selection.map(s => s.id);
}

function handleSelectAll() {
  // Handled by Element Plus table selection
}

function startInlineEdit(row) {
  editingId.value = row.id;
  editValue.value = row.value || '';
}

async function handleSaveInline(row) {
  if (editingId.value === null) return;
  try {
    await i18nApi.updateTranslation(row.id, { value: editValue.value });
    row.value = editValue.value;
    ElMessage.success(t('translation_editor.messages.updated'));
  } catch (e) {
    ElMessage.error(t('translation_editor.messages.update_failed'));
  } finally {
    editingId.value = null;
    editValue.value = '';
  }
}

async function handleTogglePublish(row, val) {
  try {
    await i18nApi.publishTranslation(row.id, val);
    row.is_published = val;
    ElMessage.success(val ? t('translation_editor.messages.published') : t('translation_editor.messages.unpublished'));
  } catch (e) {
    ElMessage.error(t('translation_editor.messages.op_failed'));
  }
}

async function handleBulkPublish(publish) {
  if (selectedIds.value.length === 0) return;
  try {
    for (const id of selectedIds.value) {
      await i18nApi.publishTranslation(id, publish);
    }
    ElMessage.success(publish ? t('translation_editor.messages.bulk_published') : t('translation_editor.messages.bulk_unpublished'));
    selectedIds.value = [];
    await fetchTranslations();
  } catch (e) {
    ElMessage.error(t('translation_editor.messages.bulk_failed'));
  }
}

async function handleAutoTranslate(row) {
  try {
    const res = await i18nApi.autoTranslateSingle(row.id);
    Object.assign(row, res.data.data);
    ElMessage.success(t('translation_editor.messages.ai_done'));
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('translation_editor.messages.ai_failed'));
  }
}

async function handleViewDetail(row) {
  try {
    const res = await i18nApi.getTranslation(row.id);
    const data = res.data.data;
    detailDialog.translation = data.translation;
    detailDialog.history = data.history || [];
    detailDialog.editValue = data.translation.value || '';
    detailDialog.visible = true;
  } catch (e) {
    ElMessage.error(t('translation_editor.messages.detail_failed'));
  }
}

async function handleSaveDetail() {
  if (!detailDialog.translation) return;
  detailDialog.saving = true;
  try {
    await i18nApi.updateTranslation(detailDialog.translation.id, {
      value: detailDialog.editValue,
      is_published: detailDialog.translation.is_published,
    });
    ElMessage.success(t('translation_editor.messages.saved'));
    detailDialog.visible = false;
    await fetchTranslations();
  } catch (e) {
    ElMessage.error(t('translation_editor.messages.save_failed'));
  } finally {
    detailDialog.saving = false;
  }
}

watch(() => filters.locale, () => {
  currentPage.value = 1;
});
</script>

<style scoped>
.filters-bar {
  border: 1px solid #e5e7eb;
}
</style>
