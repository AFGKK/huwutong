<template>
  <el-dialog v-model="visible" :title="isEdit ? '编辑小部件' : '添加小部件'" width="600px" destroy-on-close>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="110px" v-loading="saving">
      <el-form-item label="标题" prop="title">
        <el-input v-model="form.title" maxlength="200" />
      </el-form-item>
      <el-row :gutter="12">
        <el-col :span="12">
          <el-form-item label="类型" prop="type">
            <el-select v-model="form.type" style="width:100%" @change="onTypeChange">
              <el-option v-for="(lb, key) in types" :key="key" :label="lb" :value="key" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="宽度">
            <el-input-number v-model="form.layout.w" :min="1" :max="12" style="width:100%" />
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="高度">
            <el-input-number v-model="form.layout.h" :min="1" :max="6" style="width:100%" />
          </el-form-item>
        </el-col>
      </el-row>

      <el-form-item label="数据源">
        <el-select v-model="form.data_source.type" clearable style="width:100%" @change="onSourceChange">
          <el-option label="系统统计" value="stats" />
          <el-option label="License 统计" value="license_stats" />
          <el-option label="最近 License" value="recent_licenses" />
          <el-option label="最近工单" value="recent_tickets" />
          <el-option label="订阅统计" value="subscription_stats" />
          <el-option label="审计统计" value="audit_stats" />
          <el-option label="用户统计" value="user_stats" />
          <el-option label="自定义查询" value="custom_query" />
        </el-select>
      </el-form-item>

      <el-form-item label="描述">
        <el-input v-model="form.description" type="textarea" :rows="2" />
      </el-form-item>

      <el-divider>视觉选项</el-divider>
      <el-row :gutter="12">
        <el-col :span="8">
          <el-form-item label="边框">
            <el-switch v-model="visual.border" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="自动刷新(秒)">
            <el-input-number v-model="visual.refresh_interval" :min="0" :max="3600" :step="30" style="width:100%" />
          </el-form-item>
        </el-col>
      </el-row>
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
import { createWidget, updateWidget } from '../../../api/dashboard'

const props = defineProps({ dashboardId: { type: Number, default: null } })
const emit = defineEmits(['saved'])

const visible = ref(false)
const isEdit = ref(false)
const editId = ref(null)
const saving = ref(false)
const formRef = ref(null)

const types = { stat: '统计数字', chart: '图表', list: '列表', metric: '指标卡', table: '数据表格', iframe: '嵌入页面', html: '自定义HTML', alert: '告警列表', report: '报表快照' }

const defForm = () => ({
  title: '', type: 'stat', description: '',
  layout: { w: 4, h: 2 }, data_source: { type: 'stats' },
  config: {}, visual_options: {},
})
const form = reactive(defForm())
const visual = reactive({ border: true, refresh_interval: 300 })
const rules = { title: [{ required: true, message: '请输入标题', trigger: 'blur' }], type: [{ required: true, message: '请选择类型', trigger: 'change' }] }

function onTypeChange(val) {
  if (val === 'iframe') form.data_source = { type: 'none' }
  else if (val === 'html') form.data_source = { type: 'none' }
  else if (!form.data_source?.type || form.data_source.type === 'none') form.data_source = { type: 'stats' }
}

function onSourceChange(val) {
  // auto
}

function open(mode, row = null) {
  isEdit.value = mode === 'edit'
  editId.value = row?.id ?? null
  Object.assign(form, defForm())
  visual.border = true; visual.refresh_interval = 300
  if (row) {
    form.title = row.title
    form.type = row.type
    form.description = row.description || ''
    form.layout = row.layout || { w: 4, h: 2 }
    form.data_source = row.data_source || { type: 'stats' }
    form.config = row.config || {}
    form.visual_options = row.visual_options || {}
    visual.border = row.visual_options?.border ?? true
    visual.refresh_interval = row.visual_options?.refresh_interval ?? 300
  }
  visible.value = true
}

async function save() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    const data = {
      ...form,
      visual_options: { ...visual },
    }
    if (isEdit.value) {
      await updateWidget(editId.value, data)
      ElMessage.success('小部件已更新')
    } else {
      await createWidget(props.dashboardId, data)
      ElMessage.success('小部件已添加')
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
