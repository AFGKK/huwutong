<template>
  <el-dialog v-model="visible" title="上传文件" width="550px" destroy-on-close>
    <el-form label-width="100px">
      <el-form-item label="实体类型" required>
        <el-select v-model="entityType" style="width:100%">
          <el-option v-for="(lb, key) in entityTypes" :key="key" :label="lb" :value="key" />
        </el-select>
      </el-form-item>
      <el-form-item label="导入选项">
        <el-checkbox v-model="options.update_existing">更新已存在记录</el-checkbox>
        <el-checkbox v-model="options.skip_errors">跳过错误继续导入</el-checkbox>
      </el-form-item>
      <el-form-item label="字段分隔">
        <el-radio-group v-model="options.delimiter" size="small">
          <el-radio value="auto" label="自动检测" />
          <el-radio value="," label="逗号 ," />
          <el-radio value=";" label="分号 ;" />
          <el-radio value="\t" label="Tab" />
        </el-radio-group>
      </el-form-item>
      <el-form-item label="CSV 编码">
        <el-select v-model="options.encoding" style="width:200px">
          <el-option label="UTF-8 (推荐)" value="utf-8" />
          <el-option label="GBK/GB2312" value="gbk" />
        </el-select>
      </el-form-item>
      <el-form-item label="CSV/Excel">
        <el-upload ref="uploadRef" :auto-upload="false" :show-file-list="true"
          :on-change="handleFileChange" accept=".csv,.xlsx,.xls,.txt"
          drag>
          <el-icon class="upload-icon"><UploadFilled /></el-icon>
          <div class="upload-text">拖拽文件到此处，或 <em>点击选择</em></div>
          <template #tip>
            <div class="upload-tip">
              支持 CSV (.csv) 和 Excel (.xlsx/.xls) 格式，最大 50MB
            </div>
          </template>
        </el-upload>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" @click="upload" :loading="uploading" :disabled="!selectedFile">
        {{ uploading ? '上传中...' : '上传并解析' }}
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { UploadFilled } from '@element-plus/icons-vue'
import { uploadFile } from '../../../api/dataImport'

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
    ElMessage.warning('请选择文件和实体类型')
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
    ElMessage.success('文件上传成功')
    visible.value = false
    emit('uploaded', data)
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '上传失败')
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
.upload-text em { color: #409eff; font-style: normal; }
.upload-tip { font-size: 12px; color: #909399; margin-top: 4px; }
</style>
