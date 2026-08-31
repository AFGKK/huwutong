<template>
  <div class="i18n-page">
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ t('i18n_page.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ t('i18n_page.subtitle') }}</p>
      </div>
      <div class="flex gap-2">
        <el-button @click="handleScanPhpFiles" :loading="scanLoading" :disabled="scanLoading">
          <el-icon class="mr-1"><Monitor /></el-icon>
          {{ t('i18n_page.scan_files') }}
        </el-button>
        <el-button type="primary" @click="activeTab = 'translations'" v-if="languages.length > 0">
          <el-icon class="mr-1"><Edit /></el-icon>
          {{ t('i18n_page.translation_editor') }}
        </el-button>
      </div>
    </div>

    <!-- Global Status -->
    <div v-if="dashboard" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <el-card shadow="hover" class="stat-card">
        <div class="flex items-center gap-3">
          <div class="stat-icon bg-blue-50 text-blue-600">
            <el-icon :size="22"><ChatDotRound /></el-icon>
          </div>
          <div>
            <div class="text-2xl font-bold">{{ dashboard.stats.active_languages }}/{{ dashboard.stats.total_languages }}</div>
            <div class="text-xs text-gray-500">{{ t('i18n_page.stats.active_languages') }}</div>
          </div>
        </div>
      </el-card>
      <el-card shadow="hover" class="stat-card">
        <div class="flex items-center gap-3">
          <div class="stat-icon bg-green-50 text-green-600">
            <el-icon :size="22"><Document /></el-icon>
          </div>
          <div>
            <div class="text-2xl font-bold">{{ dashboard.stats.total_namespaces }}</div>
            <div class="text-xs text-gray-500">{{ t('i18n_page.stats.namespaces') }}</div>
          </div>
        </div>
      </el-card>
      <el-card shadow="hover" class="stat-card">
        <div class="flex items-center gap-3">
          <div class="stat-icon bg-purple-50 text-purple-600">
            <el-icon :size="22"><List /></el-icon>
          </div>
          <div>
            <div class="text-2xl font-bold">{{ dashboard.stats.total_translations }}</div>
            <div class="text-xs text-gray-500">{{ t('i18n_page.stats.translations') }}</div>
          </div>
        </div>
      </el-card>
      <el-card shadow="hover" class="stat-card">
        <div class="flex items-center gap-3">
          <div class="stat-icon" :class="dashboard.stats.total_missing > 0 ? 'bg-orange-50 text-orange-600' : 'bg-green-50 text-green-600'">
            <el-icon :size="22"><Warning /></el-icon>
          </div>
          <div>
            <div class="text-2xl font-bold">{{ dashboard.stats.total_missing }}</div>
            <div class="text-xs text-gray-500">{{ t('i18n_page.stats.missing') }}</div>
          </div>
        </div>
      </el-card>
    </div>

    <!-- Main Tabs -->
    <el-card class="main-card">
      <el-tabs v-model="activeTab" class="main-tabs">
        <!-- Tab 1: Languages -->
        <el-tab-pane :label="tabLabels.languages" name="languages">
          <LanguageManager
            :languages="languages"
            :loading="languagesLoading"
            @refresh="fetchLanguages"
            @edit="openLanguageDialog"
            @delete="handleDeleteLanguage"
          />
        </el-tab-pane>

        <!-- Tab 2: Translation Editor -->
        <el-tab-pane :label="tabLabels.translations" name="translations">
          <TranslationEditor
            :languages="activeLanguages"
            :namespaces="namespaces"
            :dashboard="dashboard"
            @scan="handleScanPhpFiles"
          />
        </el-tab-pane>

        <!-- Tab 3: Import/Export -->
        <el-tab-pane :label="tabLabels.importExport" name="importExport">
          <ImportExportPanel
            :languages="activeLanguages"
            :namespaces="namespaces"
            :import-history="importHistory"
            @import="handleImport"
            @export="handleExport"
            @refresh-history="fetchImportHistory"
          />
        </el-tab-pane>

        <!-- Tab 4: Overview Dashboard -->
        <el-tab-pane :label="tabLabels.overview" name="overview">
          <OverviewDashboard :dashboard="dashboard" :loading="dashboardLoading" />
        </el-tab-pane>

        <!-- Tab 5: M3-85 翻译引擎 -->
        <el-tab-pane :label="tabLabels.engine" name="engine">
          <TranslationEngine @refresh="fetchDashboard" />
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- Language Dialog -->
    <el-dialog
      v-model="languageDialog.visible"
      :title="languageDialog.isEdit ? t('i18n_page.dialog.edit_title') : t('i18n_page.dialog.add_title')"
      width="500px"
      :close-on-click-modal="false"
    >
      <el-form
        ref="languageFormRef"
        :model="languageDialog.form"
        :rules="formRules"
        label-width="120px"
        label-position="top"
      >
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('i18n_page.form.locale')" prop="locale">
              <el-input v-model="languageDialog.form.locale" :placeholder="t('i18n_page.form.locale_ph')" :disabled="languageDialog.isEdit" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('i18n_page.form.name')" prop="name">
              <el-input v-model="languageDialog.form.name" :placeholder="t('i18n_page.form.name_ph')" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('i18n_page.form.native_name')" prop="native_name">
              <el-input v-model="languageDialog.form.native_name" :placeholder="t('i18n_page.form.native_name_ph')" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('i18n_page.form.flag')" prop="flag">
              <el-input v-model="languageDialog.form.flag" :placeholder="t('i18n_page.form.flag_ph')" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('i18n_page.form.sort_order')" prop="sort_order">
              <el-input-number v-model="languageDialog.form.sort_order" :min="0" :max="999" controls-position="right" class="!w-full" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('i18n_page.form.direction')" prop="direction">
              <el-select v-model="languageDialog.form.direction" class="!w-full">
                <el-option
                  v-for="opt in directionOptions"
                  :key="opt.value"
                  :label="opt.label"
                  :value="opt.value"
                />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item :label="t('i18n_page.form.enabled')" prop="is_active">
              <el-switch v-model="languageDialog.form.is_active" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item :label="t('i18n_page.form.default')" prop="is_default">
              <el-switch v-model="languageDialog.form.is_default" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item :label="t('i18n_page.form.rtl')" prop="is_rtl">
              <el-switch v-model="languageDialog.form.is_rtl" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="languageDialog.visible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleSaveLanguage" :loading="languageDialog.saving">
          {{ languageDialog.isEdit ? t('actions.save') : t('actions.create') }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  Monitor, Edit, ChatDotRound, Document, List, Warning,
} from '@element-plus/icons-vue';
import i18nApi from '../../api/i18n';
import LanguageManager from './components/LanguageManager.vue';
import TranslationEditor from './components/TranslationEditor.vue';
import ImportExportPanel from './components/ImportExportPanel.vue';
import OverviewDashboard from './components/OverviewDashboard.vue';
import TranslationEngine from './components/TranslationEngine.vue';

const { t } = useI18n();

const activeTab = ref('languages');

const tabLabels = computed(() => ({
  languages: t('i18n_page.tabs.languages'),
  translations: t('i18n_page.tabs.translations'),
  importExport: t('i18n_page.tabs.import_export'),
  overview: t('i18n_page.tabs.overview'),
  engine: t('i18n_page.tabs.engine'),
}));

const directionOptions = computed(() => [
  { value: 'ltr', label: t('i18n_page.direction.ltr') },
  { value: 'rtl', label: t('i18n_page.direction.rtl') },
]);

const formRules = computed(() => ({
  locale: [{ required: true, message: t('i18n_page.form.locale_required'), trigger: 'blur' }],
  name: [{ required: true, message: t('i18n_page.form.name_required'), trigger: 'blur' }],
}));

// ─── Dashboard ──────────────────────────────────────────────
const dashboard = ref(null);
const dashboardLoading = ref(false);

async function fetchDashboard() {
  dashboardLoading.value = true;
  try {
    const res = await i18nApi.getDashboard();
    dashboard.value = res.data.data;
  } catch (e) {
    console.error('Failed to fetch i18n dashboard:', e);
  } finally {
    dashboardLoading.value = false;
  }
}

// ─── Languages ──────────────────────────────────────────────
const languages = ref([]);
const activeLanguages = computed(() => languages.value.filter(l => l.is_active));
const languagesLoading = ref(false);

async function fetchLanguages() {
  languagesLoading.value = true;
  try {
    const res = await i18nApi.getLanguages();
    languages.value = res.data.data || [];
  } catch (e) {
    console.error('Failed to fetch languages:', e);
  } finally {
    languagesLoading.value = false;
  }
}

// ─── Namespaces ────────────────────────────────────────────────
const namespaces = ref([]);

async function fetchNamespaces() {
  try {
    const res = await i18nApi.getNamespaces();
    namespaces.value = res.data.data || [];
  } catch (e) {
    console.error('Failed to fetch namespaces:', e);
  }
}

// ─── Import History ──────────────────────────────────────────
const importHistory = ref([]);

async function fetchImportHistory() {
  try {
    const res = await i18nApi.getImportHistory();
    importHistory.value = res.data.data?.data || [];
  } catch (e) {
    console.error('Failed to fetch import history:', e);
  }
}

// ─── Scan ────────────────────────────────────────────────────
const scanLoading = ref(false);

async function handleScanPhpFiles() {
  scanLoading.value = true;
  try {
    const res = await i18nApi.scanPhpFiles();
    ElMessage.success(res.data.message || t('i18n_page.msg_scan_ok'));
    await fetchDashboard();
    await fetchLanguages();
    await fetchNamespaces();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('i18n_page.msg_scan_failed'));
  } finally {
    scanLoading.value = false;
  }
}

// ─── Import / Export ─────────────────────────────────────────
async function handleImport(formData) {
  try {
    const res = await i18nApi.importTranslations(formData);
    ElMessage.success(res.data.message || t('i18n_page.msg_import_ok'));
    await fetchDashboard();
    await fetchImportHistory();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('i18n_page.msg_import_failed'));
    throw e;
  }
}

async function handleExport(data) {
  try {
    const res = await i18nApi.exportTranslations(data);
    const blob = new Blob([res.data]);
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `translations_${data.locale}.${data.format}`;
    a.click();
    URL.revokeObjectURL(url);
    ElMessage.success(t('i18n_page.msg_export_ok'));
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('i18n_page.msg_export_failed'));
  }
}

// ─── Language Dialog ─────────────────────────────────────────
const languageFormRef = ref(null);
const languageDialog = reactive({
  visible: false,
  isEdit: false,
  saving: false,
  form: {
    locale: '',
    name: '',
    native_name: '',
    flag: '',
    direction: 'ltr',
    is_rtl: false,
    is_active: true,
    is_default: false,
    sort_order: 0,
  },
});

function openLanguageDialog(lang = null) {
  if (lang) {
    languageDialog.isEdit = true;
    languageDialog.form = { ...lang };
  } else {
    languageDialog.isEdit = false;
    languageDialog.form = {
      locale: '',
      name: '',
      native_name: '',
      flag: '',
      direction: 'ltr',
      is_rtl: false,
      is_active: true,
      is_default: false,
      sort_order: 0,
    };
  }
  languageDialog.visible = true;
}

async function handleSaveLanguage() {
  const valid = await languageFormRef.value?.validate().catch(() => false);
  if (!valid) return;

  languageDialog.saving = true;
  try {
    if (languageDialog.isEdit) {
      await i18nApi.updateLanguage(languageDialog.form.id, languageDialog.form);
      ElMessage.success(t('i18n_page.msg_language_updated'));
    } else {
      await i18nApi.createLanguage(languageDialog.form);
      ElMessage.success(t('i18n_page.msg_language_created'));
    }
    languageDialog.visible = false;
    await fetchLanguages();
    await fetchDashboard();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('messages.failed'));
  } finally {
    languageDialog.saving = false;
  }
}

async function handleDeleteLanguage(id) {
  try {
    await ElMessageBox.confirm(
      t('i18n_page.delete_confirm'),
      t('i18n_page.delete_title'),
      {
        type: 'warning',
        confirmButtonText: t('actions.delete'),
        cancelButtonText: t('actions.cancel'),
      },
    );
    await i18nApi.deleteLanguage(id);
    ElMessage.success(t('i18n_page.msg_language_deleted'));
    await fetchLanguages();
    await fetchDashboard();
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(e.response?.data?.message || t('i18n_page.msg_delete_failed'));
    }
  }
}

// ─── Init ────────────────────────────────────────────────────
onMounted(async () => {
  await Promise.all([
    fetchDashboard(),
    fetchLanguages(),
    fetchNamespaces(),
    fetchImportHistory(),
  ]);
});
</script>

<style scoped>
.i18n-page {
  padding: 24px;
  max-width: 1400px;
  margin: 0 auto;
}

.stat-card {
  :deep(.el-card__body) {
    padding: 16px;
  }
}

.stat-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.main-card {
  :deep(.el-card__body) {
    padding: 0;
  }
}

.main-tabs {
  :deep(.el-tabs__header) {
    margin: 0;
    padding: 0 16px;
    background: #f8fafc;
    border-bottom: 1px solid #e5e7eb;
  }
  :deep(.el-tabs__content) {
    padding: 20px;
  }
}
</style>
