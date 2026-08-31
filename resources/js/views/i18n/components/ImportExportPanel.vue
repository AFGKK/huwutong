<template>
  <div class="import-export-panel">
    <el-row :gutter="24">
      <!-- Export Section -->
      <el-col :span="12">
        <el-card shadow="never" class="section-card">
          <template #header>
            <div class="flex items-center justify-between">
              <span class="font-semibold text-gray-800">{{ t('import_export_panel.export_title') }}</span>
              <el-tag type="info" size="small">Export</el-tag>
            </div>
          </template>
          <el-form label-position="top" size="small">
            <el-form-item :label="t('import_export_panel.target_locale')">
              <el-select v-model="exportForm.locale" class="!w-full" :placeholder="t('import_export_panel.select_locale')">
                <el-option
                  v-for="lang in languages"
                  :key="lang.locale"
                  :label="`${lang.flag || ''} ${lang.name}`"
                  :value="lang.locale"
                />
              </el-select>
            </el-form-item>
            <el-form-item :label="t('import_export_panel.export_format')">
              <el-radio-group v-model="exportForm.format" class="!w-full">
                <el-radio-button label="json">JSON</el-radio-button>
                <el-radio-button label="csv">CSV</el-radio-button>
                <el-radio-button label="php">PHP</el-radio-button>
                <el-radio-button label="xliff">XLIFF</el-radio-button>
              </el-radio-group>
            </el-form-item>
            <el-form-item :label="t('import_export_panel.namespace_optional')">
              <el-select v-model="exportForm.namespace_id" class="!w-full" :placeholder="t('import_export_panel.all_namespaces')" clearable>
                <el-option
                  v-for="ns in namespaces"
                  :key="ns.id"
                  :label="ns.label || ns.namespace"
                  :value="ns.id"
                />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button
                type="primary"
                @click="handleExport"
                :disabled="!exportForm.locale"
                :loading="exporting"
                class="!w-full"
              >
                <el-icon class="mr-1"><Download /></el-icon>
                {{ t('import_export_panel.export_btn') }}
              </el-button>
            </el-form-item>
          </el-form>
        </el-card>
      </el-col>

      <!-- Import Section -->
      <el-col :span="12">
        <el-card shadow="never" class="section-card">
          <template #header>
            <div class="flex items-center justify-between">
              <span class="font-semibold text-gray-800">{{ t('import_export_panel.import_title') }}</span>
              <el-tag type="warning" size="small">Import</el-tag>
            </div>
          </template>
          <el-form label-position="top" size="small">
            <el-form-item :label="t('import_export_panel.import_format')">
              <el-radio-group v-model="importForm.format" class="!w-full">
                <el-radio-button label="json">JSON</el-radio-button>
                <el-radio-button label="csv">CSV</el-radio-button>
                <el-radio-button label="xliff">XLIFF</el-radio-button>
              </el-radio-group>
            </el-form-item>
            <el-form-item :label="t('import_export_panel.upload_label')">
              <el-upload
                ref="uploadRef"
                :auto-upload="false"
                :limit="1"
                :accept="acceptFormats"
                :on-change="handleFileChange"
                :on-remove="() => importForm.file = null"
              >
                <template #trigger>
                  <el-button type="default">
                    <el-icon class="mr-1"><FolderOpened /></el-icon>
                    {{ t('import_export_panel.select_file') }}
                  </el-button>
                </template>
                <template #tip>
                  <div class="text-xs text-gray-400 mt-1">
                    {{ t('import_export_panel.formats_tip') }}
                  </div>
                </template>
              </el-upload>
            </el-form-item>
            <el-form-item>
              <el-button
                type="primary"
                @click="handleImport"
                :disabled="!importForm.file"
                :loading="importing"
                class="!w-full"
              >
                <el-icon class="mr-1"><Upload /></el-icon>
                {{ t('import_export_panel.import_btn') }}
              </el-button>
            </el-form-item>
          </el-form>
        </el-card>
      </el-col>
    </el-row>

    <!-- Import History -->
    <el-card shadow="never" class="section-card mt-4">
      <template #header>
        <div class="flex items-center justify-between">
          <span class="font-semibold text-gray-800">{{ t('import_export_panel.history_title') }}</span>
          <el-button size="small" text @click="$emit('refresh-history')">
            <el-icon><Refresh /></el-icon>
          </el-button>
        </div>
      </template>
      <el-table :data="importHistory" stripe size="small" v-if="importHistory.length > 0">
        <el-table-column :label="t('import_export_panel.cols.type')" width="80">
          <template #default="{ row }">
            <el-tag :type="row.type === 'import' ? 'warning' : 'info'" size="small">
              {{ row.type === 'import' ? t('import_export_panel.type_import') : t('import_export_panel.type_export') }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('import_export_panel.cols.format')" prop="format" width="80" />
        <el-table-column :label="t('import_export_panel.cols.status')" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'failed' ? 'danger' : 'warning'" size="small">
              {{ row.status }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('import_export_panel.cols.summary')" min-width="200">
          <template #default="{ row }">
            <span class="text-xs" v-if="row.summary">
              {{ t('import_export_panel.summary', { created: row.summary.created || 0, updated: row.summary.updated || 0, skipped: row.summary.skipped || 0 }) }}
              <span v-if="row.summary.errors?.length" class="text-red-500"> {{ t('import_export_panel.summary_errors', { n: row.summary.errors.length }) }}</span>
            </span>
          </template>
        </el-table-column>
        <el-table-column :label="t('import_export_panel.cols.time')" prop="created_at" width="160" />
      </el-table>
      <el-empty v-else :description="t('import_export_panel.empty')" :image-size="80" />
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Download, Upload, FolderOpened, Refresh } from '@element-plus/icons-vue';

const { t } = useI18n();

const props = defineProps({
  languages: { type: Array, default: () => [] },
  namespaces: { type: Array, default: () => [] },
  importHistory: { type: Array, default: () => [] },
});

const emit = defineEmits(['import', 'export', 'refresh-history']);

const exportForm = reactive({
  locale: '',
  format: 'json',
  namespace_id: null,
});
const exporting = ref(false);

async function handleExport() {
  if (!exportForm.locale) {
    ElMessage.warning(t('import_export_panel.messages.select_locale'));
    return;
  }
  exporting.value = true;
  try {
    emit('export', { ...exportForm });
  } finally {
    exporting.value = false;
  }
}

const importForm = reactive({
  format: 'json',
  file: null,
});
const importing = ref(false);
const uploadRef = ref(null);

const acceptFormats = computed(() => {
  switch (importForm.format) {
    case 'json': return '.json';
    case 'csv': return '.csv';
    case 'xliff': return '.xliff,.xlf';
    default: return '.json,.csv,.xliff,.xlf';
  }
});

function handleFileChange(uploadFile) {
  importForm.file = uploadFile.raw;
}

async function handleImport() {
  if (!importForm.file) {
    ElMessage.warning(t('import_export_panel.messages.select_file'));
    return;
  }
  importing.value = true;
  try {
    const formData = new FormData();
    formData.append('file', importForm.file);
    formData.append('format', importForm.format);
    emit('import', formData);
    importForm.file = null;
    uploadRef.value?.clearFiles();
  } finally {
    importing.value = false;
  }
}
</script>

<style scoped>
.section-card {
  border: 1px solid #e5e7eb;
  height: 100%;
}
</style>
