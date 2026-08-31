<template>
  <el-dialog v-model="visible" :title="t('upload_dialog.title')" width="550px" destroy-on-close>
    <el-form label-width="100px">
      <el-form-item :label="t('upload_dialog.entity_type')" required>
        <el-select v-model="entityType" style="width:100%">
          <el-option v-for="(lb, key) in entityTypes" :key="key" :label="lb" :value="key" />
        </el-select>
      </el-form-item>
      <el-form-item :label="t('upload_dialog.options')">
        <el-checkbox v-model="options.update_existing">{{ t('upload_dialog.update_existing') }}</el-checkbox>
        <el-checkbox v-model="options.skip_errors">{{ t('upload_dialog.skip_errors') }}</el-checkbox>
      </el-form-item>
      <el-form-item :label="t('upload_dialog.delimiter')">
        <el-radio-group v-model="options.delimiter" size="small">
          <el-radio value="auto">{{ t('upload_dialog.delim_auto') }}</el-radio>
          <el-radio value=",">{{ t('upload_dialog.delim_comma') }}</el-radio>
          <el-radio value=";">{{ t('upload_dialog.delim_semicolon') }}</el-radio>
          <el-radio value="\t">Tab</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item :label="t('upload_dialog.encoding')">
        <el-select v-model="options.encoding" style="width:200px">
          <el-option :label="t('upload_dialog.encoding_utf8')" value="utf-8" />
          <el-option label="GBK/GB2312" value="gbk" />
        </el-select>
      </el-form-item>
      <el-form-item label="CSV/Excel">
        <el-upload ref="uploadRef" :auto-upload="false" :show-file-list="true"
          :on-change="handleFileChange" accept=".csv,.xlsx,.xls,.txt"
          drag>
          <el-icon class="upload-icon"><UploadFilled /></el-icon>
          <div class="upload-text">{{ t('upload_dialog.drag_hint') }} <em>{{ t('upload_dialog.click_select') }}</em></div>
          <template #tip>
            <div class="upload-tip">
              {{ t('upload_dialog.tip') }}
            </div>
          </template>
        </el-upload>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">{{ t('actions.cancel') }}</el-button>
      <el-button type="primary" @click="upload" :loading="uploading" :disabled="!selectedFile">
        {{ uploading ? t('upload_dialog.uploading') : t('upload_dialog.upload_parse') }}
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { UploadFilled } from '@element-plus/icons-vue'
import { uploadFile } from '../../../api/dataImport'

const { t } = useI18n()
const props = defineProps({ entityTypes: { type: Object, default: () => ({}) } })
const emit = defineEmits(['uploaded'])

const visible = ref(false)
const uploading = ref(false)
const selectedFile = ref(null)
const entityType = ref('licenses')
const uploadRef = ref(null)
const options = reactive({ update_existing: true, skip_errors: false, delimiter: 'auto', encoding: 'utf-8' })

function handleFileChange(file) {
  selectedFile.value = file.raw
}

async function upload() {
  if (!selectedFile.value || !entityType.value) {
    ElMessage.warning(t('upload_dialog.messages.select_required'))
    return
  }
  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)
    formData.append('entity_type', entityType.value)
    formData.append('options[update_existing]', options.update_existing ? '1' : '0')
    formData.append('options[skip_errors]', options.skip_errors ? '1' : '0')

    const { data } = await uploadFile(formData)
    ElMessage.success(t('upload_dialog.messages.success'))
    visible.value = false
    emit('uploaded', data.data)
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('upload_dialog.messages.failed'))
  } finally {
    uploading.value = false
  }
}

function open() {
  visible.value = true
  selectedFile.value = null
  entityType.value = 'licenses'
  options.update_existing = true
  options.skip_errors = false
  options.delimiter = 'auto'
  options.encoding = 'utf-8'
  uploadRef.value?.clearFiles()
}

defineExpose({ open })
</script>

<style scoped>
.upload-icon { font-size: 48px; color: #c0c4cc; margin-bottom: 8px; }
.upload-text { font-size: 14px; color: #606266; }
.upload-text em { color: #0f172a; font-style: normal; }
.upload-tip { font-size: 12px; color: #909399; margin-top: 4px; }
</style>
