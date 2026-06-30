<template>
  <el-dialog v-model="visible" :title="isEdit ? '编辑定时计划' : '新建定时计划'" width="550px" destroy-on-close>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="120px" v-loading="saving">
      <el-form-item label="计划名称" prop="name">
        <el-input v-model="form.name" placeholder="如：每日凌晨审计导出" maxlength="200" />
      </el-form-item>
      <el-form-item label="Cron 表达式" prop="cron_expression">
        <el-input v-model="form.cron_expression" placeholder="如：0 2 * * *" />
        <div class="text-xs text-gray-400 mt-1">格式：分 时 日 月 周</div>
      </el-form-item>
      <el-row :gutter="12">
        <el-col :span="12">
          <el-form-item label="导出格式">
            <el-select v-model="form.format" style="width:100%">
              <el-option label="CSV" value="csv" />
              <el-option label="JSON" value="json" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="最大记录数">
            <el-input-number v-model="form.max_records" :min="100" :max="100000" :step="1000" style="width:100%" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="通知邮箱">
        <el-select v-model="form.notification_emails" multiple filterable allow-create default-first-option
          placeholder="选填：输入邮箱后回车" style="width:100%">
        </el-select>
      </el-form-item>
      <el-form-item label="压缩方式">
        <el-select v-model="form.compression" style="width:100%">
          <el-option label="无压缩" value="none" />
          <el-option label="Gzip" value="gzip" />
          <el-option label="Zip" value="zip" />
        </el-select>
      </el-form-item>
      <el-divider>筛选条件</el-divider>
      <el-form-item label="日志类型">
        <el-select v-model="form.filters.type" clearable placeholder="全部" style="width:100%">
          <el-option v-for="(lb, key) in logTypes" :key="key" :label="lb" :value="key" />
        </el-select>
      </el-form-item>
      <el-form-item label="操作前缀">
        <el-input v-model="form.filters.action_prefix" placeholder="如：license.*" />
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
import { createSchedule, updateSchedule } from '../../../api/auditExport'

const emit = defineEmits(['saved'])
const visible = ref(false)
const isEdit = ref(false)
const editId = ref(null)
const saving = ref(false)
const formRef = ref(null)

const defForm = () => ({
  name: '', cron_expression: '0 2 * * *', format: 'csv',
  max_records: 50000, notification_emails: [], compression: 'none',
  filters: {}, description: '',
})

const form = reactive(defForm())
const logTypes = { audit: '审计', security: '安全', error: '错误', system: '系统' }
const rules = {
  name: [{ required: true, message: '请输入计划名称', trigger: 'blur' }],
  cron_expression: [{ required: true, message: '请输入 Cron 表达式', trigger: 'blur' }],
}

function open(mode, row = null) {
  isEdit.value = mode === 'edit'
  editId.value = row?.id ?? null
  Object.assign(form, defForm())
  if (row) {
    form.name = row.name
    form.cron_expression = row.cron_expression
    form.format = row.format || 'csv'
    form.max_records = row.max_records || 50000
    form.notification_emails = row.notification_emails || []
    form.compression = row.compression || 'none'
    form.filters = row.filters || {}
    form.description = row.description || ''
  }
  visible.value = true
}

async function save() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    const data = { ...form, is_active: true }
    if (isEdit.value) {
      await updateSchedule(editId.value, data)
      ElMessage.success('计划已更新')
    } else {
      await createSchedule(data)
      ElMessage.success('计划已创建')
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
