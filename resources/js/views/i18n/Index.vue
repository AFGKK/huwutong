<template>
  <div class="i18n-page">
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">国际化管理</h1>
        <p class="text-sm text-gray-500 mt-1">管理多语言翻译、语言包和翻译编辑器</p>
      </div>
      <div class="flex gap-2">
        <el-button @click="handleScanPhpFiles" :loading="scanLoading" :disabled="scanLoading">
          <el-icon class="mr-1"><Monitor /></el-icon>
          扫描语言文件
        </el-button>
        <el-button type="primary" @click="activeTab = 'translations'" v-if="languages.length > 0">
          <el-icon class="mr-1"><Edit /></el-icon>
          翻译编辑器
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
            <div class="text-xs text-gray-500">活跃语言</div>
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
            <div class="text-xs text-gray-500">命名空间</div>
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
            <div class="text-xs text-gray-500">翻译条目</div>
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
            <div class="text-xs text-gray-500">缺失翻译</div>
          </div>
        </div>
      </el-card>
    </div>

    <!-- Main Tabs -->
    <el-card class="main-card">
      <el-tabs v-model="activeTab" class="main-tabs">
        <!-- Tab 1: Languages -->
        <el-tab-pane label="语言管理" name="languages">
          <LanguageManager
            :languages="languages"
            :loading="languagesLoading"
            @refresh="fetchLanguages"
            @edit="openLanguageDialog"
            @delete="handleDeleteLanguage"
          />
        </el-tab-pane>

        <!-- Tab 2: Translation Editor -->
        <el-tab-pane label="翻译编辑器" name="translations">
          <TranslationEditor
            :languages="activeLanguages"
            :namespaces="namespaces"
            :dashboard="dashboard"
            @scan="handleScanPhpFiles"
          />
        </el-tab-pane>

        <!-- Tab 3: Import/Export -->
        <el-tab-pane label="导入/导出" name="importExport">
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
        <el-tab-pane label="翻译概况" name="overview">
          <OverviewDashboard :dashboard="dashboard" :loading="dashboardLoading" />
        </el-tab-pane>

        <!-- Tab 5: M3-85 翻译引擎 -->
        <el-tab-pane label="翻译引擎" name="engine">
          <TranslationEngine @refresh="fetchDashboard" />
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- Language Dialog -->
    <el-dialog
      v-model="languageDialog.visible"
      :title="languageDialog.isEdit ? '编辑语言' : '添加语言'"
      width="500px"
      :close-on-click-modal="false"
    >
      <el-form
        ref="languageFormRef"
        :model="languageDialog.form"
        :rules="languageDialog.rules"
        label-width="120px"
        label-position="top"
      >
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="语言代码" prop="locale">
              <el-input v-model="languageDialog.form.locale" placeholder="如 zh_CN, en, ja" :disabled="languageDialog.isEdit" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="语言名称" prop="name">
              <el-input v-model="languageDialog.form.name" placeholder="如 简体中文, English" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="本地名称" prop="native_name">
              <el-input v-model="languageDialog.form.native_name" placeholder="如 简体中文" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="旗帜图标" prop="flag">
              <el-input v-model="languageDialog.form.flag" placeholder="如 🇨🇳 或 flag-cn" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="排序权重" prop="sort_order">
              <el-input-number v-model="languageDialog.form.sort_order" :min="0" :max="999" controls-position="right" class="!w-full" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="文字方向" prop="direction">
              <el-select v-model="languageDialog.form.direction" class="!w-full">
                <el-option label="从左到右 (LTR)" value="ltr" />
                <el-option label="从右到左 (RTL)" value="rtl" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item label="启用" prop="is_active">
              <el-switch v-model="languageDialog.form.is_active" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="设为默认" prop="is_default">
              <el-switch v-model="languageDialog.form.is_default" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="RTL 方向" prop="is_rtl">
              <el-switch v-model="languageDialog.form.is_rtl" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="languageDialog.visible = false">取消</el-button>
        <el-button type="primary" @click="handleSaveLanguage" :loading="languageDialog.saving">
          {{ languageDialog.isEdit ? '保存' : '创建' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
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

const activeTab = ref('languages');

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
    ElMessage.success(res.data.message || 'Scan complete.');
    await fetchDashboard();
    await fetchLanguages();
    await fetchNamespaces();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || 'Scan failed.');
  } finally {
    scanLoading.value = false;
  }
}

// ─── Import / Export ─────────────────────────────────────────
async function handleImport(formData) {
  try {
    const res = await i18nApi.importTranslations(formData);
    ElMessage.success(res.data.message || 'Import completed.');
    await fetchDashboard();
    await fetchImportHistory();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || 'Import failed.');
    throw e;
  }
}

async function handleExport(data) {
  try {
    const res = await i18nApi.exportTranslations(data);
    // Download file
    const blob = new Blob([res.data]);
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `translations_${data.locale}.${data.format}`;
    a.click();
    URL.revokeObjectURL(url);
    ElMessage.success('Export completed.');
  } catch (e) {
    ElMessage.error(e.response?.data?.message || 'Export failed.');
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
  rules: {
    locale: [{ required: true, message: '请输入语言代码', trigger: 'blur' }],
    name: [{ required: true, message: '请输入语言名称', trigger: 'blur' }],
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
      ElMessage.success('Language updated.');
    } else {
      await i18nApi.createLanguage(languageDialog.form);
      ElMessage.success('Language created.');
    }
    languageDialog.visible = false;
    await fetchLanguages();
    await fetchDashboard();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || 'Operation failed.');
  } finally {
    languageDialog.saving = false;
  }
}

async function handleDeleteLanguage(id) {
  try {
    await ElMessageBox.confirm('确定要删除此语言及其所有翻译吗？', '确认删除', {
      type: 'warning',
      confirmButtonText: '删除',
      cancelButtonText: '取消',
    });
    await i18nApi.deleteLanguage(id);
    ElMessage.success('Language deleted.');
    await fetchLanguages();
    await fetchDashboard();
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(e.response?.data?.message || 'Delete failed.');
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
