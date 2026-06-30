<template>
  <el-dialog v-model="visible" :title="isEdit ? '编辑渠道' : '新建渠道'" width="620px" destroy-on-close>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="110px" v-loading="saving">
      <el-form-item label="渠道名称" prop="name">
        <el-input v-model="form.name" placeholder="如：618活动推广" maxlength="200" />
      </el-form-item>
      <el-row :gutter="12">
        <el-col :span="12">
          <el-form-item label="标识" prop="slug">
            <el-input v-model="form.slug" placeholder="留空自动生成" maxlength="100" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="类型" prop="type">
            <el-select v-model="form.type" style="width:100%">
              <el-option v-for="t in types" :key="t.value" :label="t.label" :value="t.value" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="描述">
        <el-input v-model="form.description" type="textarea" :rows="2" />
      </el-form-item>
      <el-row :gutter="12">
        <el-col :span="12">
          <el-form-item label="状态">
            <el-select v-model="form.status" style="width:100%">
              <el-option label="活跃" value="active" />
              <el-option label="停用" value="inactive" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="公开注册">
            <el-switch v-model="form.is_public" active-text="公开" inactive-text="私有" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="标签">
        <el-select v-model="form.tags" multiple filterable allow-create default-first-option style="width:100%">
          <el-option v-for="tag in ['推广','付费','自然','社媒','邮件']" :key="tag" :label="tag" :value="tag" />
        </el-select>
      </el-form-item>
      <el-divider>UTM 默认参数</el-divider>
      <el-row :gutter="12">
        <el-col :span="8">
          <el-input v-model="utm.source" placeholder="UTM Source" size="small" />
        </el-col>
        <el-col :span="8">
          <el-input v-model="utm.medium" placeholder="UTM Medium" size="small" />
        </el-col>
        <el-col :span="8">
          <el-input v-model="utm.campaign" placeholder="UTM Campaign" size="small" />
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
import { createChannel, updateChannel } from '../../../api/invite-codes'

const emit = defineEmits(['saved'])

const visible = ref(false)
const isEdit = ref(false)
const editId = ref(null)
const saving = ref(false)
const formRef = ref(null)
const utm = reactive({ source: '', medium: '', campaign: '' })

const types = [
  { value: 'promotional', label: '推广' },
  { value: 'marketing', label: '营销' },
  { value: 'partner', label: '合作伙伴' },
  { value: 'event', label: '活动' },
  { value: 'social', label: '社交' },
  { value: 'internal', label: '内部' },
]

const defForm = () => ({
  name: '', slug: '', description: '', type: 'promotional',
  status: 'active', is_public: false, tags: [],
  landing_config: {}, utm_defaults: {},
})

const form = reactive(defForm())

const rules = {
  name: [{ required: true, message: '请输入渠道名称', trigger: 'blur' }],
  type: [{ required: true, message: '请选择类型', trigger: 'change' }],
}

function open(mode, row = null) {
  isEdit.value = mode === 'edit'
  editId.value = row?.id ?? null
  Object.assign(form, defForm())
  utm.source = ''; utm.medium = ''; utm.campaign = ''
  if (row) {
    form.name = row.name
    form.slug = row.slug || ''
    form.description = row.description || ''
    form.type = row.type || 'promotional'
    form.status = row.status || 'active'
    form.is_public = row.is_public ?? false
    form.tags = row.tags || []
    if (row.utm_defaults) {
      utm.source = row.utm_defaults.source || ''
      utm.medium = row.utm_defaults.medium || ''
      utm.campaign = row.utm_defaults.campaign || ''
    }
  }
  visible.value = true
}

async function save() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return

  saving.value = true
  try {
    form.utm_defaults = { ...utm }
    if (isEdit.value) {
      await updateChannel(editId.value, { ...form })
      ElMessage.success('渠道已更新')
    } else {
      await createChannel({ ...form })
      ElMessage.success('渠道已创建')
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

<style scoped>
.el-divider { margin: 16px 0; }
</style>
