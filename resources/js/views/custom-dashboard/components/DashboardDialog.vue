<template>
  <el-dialog v-model="visible" :title="isEdit ? '编辑仪表盘' : '新建仪表盘'" width="500px" destroy-on-close>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="100px" v-loading="saving">
      <el-form-item label="名称" prop="name">
        <el-input v-model="form.name" placeholder="如：运营总览" maxlength="200" />
      </el-form-item>
      <el-form-item label="描述">
        <el-input v-model="form.description" type="textarea" :rows="2" />
      </el-form-item>
      <el-row :gutter="12">
        <el-col :span="12">
          <el-form-item label="布局类型">
            <el-select v-model="form.layout_type" style="width:100%">
              <el-option label="网格布局" value="grid" />
              <el-option label="自由布局" value="free" />
              <el-option label="弹性布局" value="flex" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="列数">
            <el-input-number v-model="form.columns" :min="1" :max="24" style="width:100%" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="标签">
        <el-select v-model="form.tags" multiple filterable allow-create default-first-option style="width:100%">
          <el-option v-for="t in ['运营','管理','技术','商务']" :key="t" :label="t" :value="t" />
        </el-select>
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
import { createDashboard, updateDashboard } from '../../../api/dashboard'

const emit = defineEmits(['saved'])
const visible = ref(false)
const isEdit = ref(false)
const editId = ref(null)
const saving = ref(false)
const formRef = ref(null)

const defForm = () => ({ name: '', description: '', layout_type: 'grid', columns: 12, tags: [] })
const form = reactive(defForm())
const rules = { name: [{ required: true, message: '请输入名称', trigger: 'blur' }] }

function open(mode, row = null) {
  isEdit.value = mode === 'edit'
  editId.value = row?.id ?? null
  Object.assign(form, defForm())
  if (row) {
    form.name = row.name
    form.description = row.description || ''
    form.layout_type = row.layout_type || 'grid'
    form.columns = row.columns || 12
    form.tags = row.tags || []
  }
  visible.value = true
}

async function save() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    if (isEdit.value) {
      await updateDashboard(editId.value, { ...form })
      ElMessage.success('仪表盘已更新')
    } else {
      await createDashboard({ ...form })
      ElMessage.success('仪表盘已创建')
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
