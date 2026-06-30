<template>
  <el-dialog v-model="visible" :title="contract?.id ? '编辑 SLA 合约' : '新建 SLA 合约'" width="640px" :close-on-click-modal="false"
    @close="reset">
    <el-form ref="formRef" :model="form" :rules="rules" label-width="120px" v-loading="saving">
      <el-form-item label="名称" prop="name">
        <el-input v-model="form.name" maxlength="200" />
      </el-form-item>
      <el-form-item label="级别" prop="level">
        <el-select v-model="form.level">
          <el-option label="标准" value="standard" />
          <el-option label="高级" value="premium" />
          <el-option label="企业" value="enterprise" />
          <el-option label="自定义" value="custom" />
        </el-select>
      </el-form-item>
      <el-form-item label="关联客户">
        <el-select v-model="form.customer_id" filterable clearable placeholder="可选">
          <el-option v-for="c in customers" :key="c.id" :label="c.name || c.email" :value="c.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="描述">
        <el-input v-model="form.description" type="textarea" :rows="2" />
      </el-form-item>
      <el-form-item label="生效日期" prop="effective_date">
        <el-date-picker v-model="form.effective_date" type="date" value-format="YYYY-MM-DD" />
      </el-form-item>
      <el-form-item label="到期日期">
        <el-date-picker v-model="form.expiry_date" type="date" value-format="YYYY-MM-DD" clearable />
      </el-form-item>
      <el-form-item label="条款 (JSON)">
        <el-input v-model="form.terms" type="textarea" :rows="3" placeholder='{"response_time": 30, "availability": 99.9}' />
      </el-form-item>
      <el-form-item label="违约处罚 (JSON)">
        <el-input v-model="form.penalties" type="textarea" :rows="2" placeholder='{"credits": 5, "escalation": true}' />
      </el-form-item>
      <el-form-item label="营业时间 (JSON)">
        <el-input v-model="form.business_hours" type="textarea" :rows="2"
          placeholder='{"timezone": "Asia/Shanghai", "workdays": [1,2,3,4,5]}' />
      </el-form-item>
      <el-form-item label="作用域 (JSON)">
        <el-input v-model="form.scope" type="textarea" :rows="2" placeholder='{"modules": ["tickets", "support"]}' />
      </el-form-item>
      <el-form-item v-if="!contract?.id" label="另存为模板">
        <el-switch v-model="form.is_template" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" @click="save">{{ contract?.id ? '保存' : '创建' }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, watch, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { createContract, updateContract } from '../../../api/sla'
import customerApi from '../../../api/customer'

const props = defineProps({
  visible: { type: Boolean, default: false },
  contract: { type: Object, default: null },
})
const emit = defineEmits(['update:visible', 'saved'])

const visible = computed({
  get: () => props.visible,
  set: v => emit('update:visible', v),
})

const formRef = ref(null)
const saving = ref(false)
const customers = ref([])

const form = reactive({
  name: '', level: 'standard', customer_id: null, description: '',
  effective_date: '', expiry_date: null, is_active: true, is_template: false,
  terms: '', penalties: '', business_hours: '', scope: '',
})

const rules = {
  name: [{ required: true, message: '请输入合约名称', trigger: 'blur' }],
  effective_date: [{ required: true, message: '请选择生效日期', trigger: 'blur' }],
}

function reset() {
  form.name = ''
  form.level = 'standard'
  form.customer_id = null
  form.description = ''
  form.effective_date = ''
  form.expiry_date = null
  form.is_active = true
  form.is_template = false
  form.terms = ''
  form.penalties = ''
  form.business_hours = ''
  form.scope = ''
}

function parseJson(str) {
  if (!str || str.trim() === '') return null
  try { return JSON.parse(str) } catch { return str }
}

async function save() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    const payload = {
      ...form,
      terms: parseJson(form.terms),
      penalties: parseJson(form.penalties),
      business_hours: parseJson(form.business_hours),
      scope: parseJson(form.scope),
    }
    if (props.contract?.id) {
      await updateContract(props.contract.id, payload)
      ElMessage.success('已更新')
    } else {
      await createContract(payload)
      ElMessage.success('已创建')
    }
    emit('saved')
  } catch (e) {
    ElMessage.error('操作失败')
  } finally {
    saving.value = false
  }
}

watch(() => props.contract, (val) => {
  if (val) {
    form.name = val.name || ''
    form.level = val.level || 'standard'
    form.customer_id = val.customer_id || null
    form.description = val.description || ''
    form.effective_date = val.effective_date || ''
    form.expiry_date = val.expiry_date || null
    form.is_active = val.is_active !== false
    form.is_template = val.is_template || false
    form.terms = val.terms ? JSON.stringify(val.terms) : ''
    form.penalties = val.penalties ? JSON.stringify(val.penalties) : ''
    form.business_hours = val.business_hours ? JSON.stringify(val.business_hours) : ''
    form.scope = val.scope ? JSON.stringify(val.scope) : ''
  } else {
    reset()
  }
}, { immediate: true })

onMounted(async () => {
  try {
    const { data } = await customerApi.list()
    customers.value = Array.isArray(data) ? data : data?.data || []
  } catch { }
})
</script>
