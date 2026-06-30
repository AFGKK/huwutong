<template>
  <el-dialog v-model="visible" :title="isEdit ? '编辑归档策略' : '新建归档策略'" width="520px" destroy-on-close>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="130px" v-loading="saving">
      <el-form-item label="策略名称" prop="name">
        <el-input v-model="form.name" maxlength="200" />
      </el-form-item>
      <el-form-item label="日志类型" prop="type">
        <el-select v-model="form.type" style="width:100%" :disabled="isEdit">
          <el-option v-for="(lb, key) in types" :key="key" :label="lb" :value="key" />
        </el-select>
      </el-form-item>
      <el-row :gutter="12">
        <el-col :span="12">
          <el-form-item label="归档天数" prop="archive_after_days">
            <el-input-number v-model="form.archive_after_days" :min="1" :max="3650" style="width:100%" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="清理天数" prop="delete_after_days">
            <el-input-number v-model="form.delete_after_days" :min="1" :max="3650" style="width:100%" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="12">
        <el-col :span="12">
          <el-form-item label="存储磁盘">
            <el-select v-model="form.archive_disk" style="width:100%">
              <el-option label="本地" value="local" />
              <el-option label="S3" value="s3" />
              <el-option label="公共" value="public" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="启用">
            <el-switch v-model="form.is_active" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="启用压缩">
        <el-switch v-model="form.compress_archive" />
      </el-form-item>
      <el-form-item label="描述">
        <el-input v-model="form.description" type="textarea" :rows="2" />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" @click="save" :loading="saving">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { upsertArchivePolicy, updateArchivePolicy } from '../../../api/auditExport'

const emit = defineEmits(['saved'])
const visible = ref(false)
const isEdit = ref(false)
const editId = ref(null)
const saving = ref(false)
const formRef = ref(null)

const types = { audit: '审计', security: '安全', error: '错误', system: '系统' }

const defForm = () => ({
  name: '', type: 'audit', archive_after_days: 90, delete_after_days: 365,
  archive_disk: 'local', compress_archive: true, is_active: true, description: '',
})

const form = reactive(defForm())
const rules = {
  name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
  type: [{ required: true, message: '请选择类型', trigger: 'change' }],
  archive_after_days: [{ required: true, message: '请输入归档天数', trigger: 'blur' }],
}

function open(mode, row = null) {
  isEdit.value = mode === 'edit'
  editId.value = row?.id ?? null
  Object.assign(form, defForm())
  if (row) {
    form.name = row.name
    form.type = row.type
    form.archive_after_days = row.archive_after_days
    form.delete_after_days = row.delete_after_days
    form.archive_disk = row.archive_disk || 'local'
    form.compress_archive = row.compress_archive ?? true
    form.is_active = row.is_active ?? true
    form.description = row.description || ''
  }
  visible.value = true
}

async function save() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    if (isEdit.value) {
      await updateArchivePolicy(editId.value, { ...form })
      ElMessage.success('策略已更新')
    } else {
      await upsertArchivePolicy({ ...form })
      ElMessage.success('策略已创建')
    }
    visible.value = false
    emit('saved')
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '保存失败')
  } finally {
    saving.value = false
  }
}

defineExpose({ open })
</script>
