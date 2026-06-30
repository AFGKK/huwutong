<template>
  <div>
    <el-alert title="登录安全策略可管理密码复杂度、会话超时、MFA 等安全配置" type="info" :closable="false" class="mb-4" />

    <el-table :data="policies" v-loading="loading" size="small">
      <el-table-column prop="name" label="策略名称" min-width="160" />
      <el-table-column prop="description" label="描述" min-width="200" show-overflow-tooltip />
      <el-table-column label="值" width="180">
        <template #default="{ row }">
          <template v-if="row.value_type === 'boolean'">
            <el-switch :model-value="row.value === 'true'" @click="togglePolicy(row)" />
          </template>
          <template v-else-if="row.value_type === 'integer'">
            <el-input-number v-model="row._editValue" :min="0" :max="99999" size="small" style="width:120px"
              @change="v => { row._editValue = v; updatePolicyValue(row) }" />
          </template>
          <template v-else-if="row.value_type === 'json'">
            <el-button size="small" link @click="editJson(row)">编辑 JSON</el-button>
          </template>
          <template v-else>
            <el-input v-model="row._editValue" size="small" style="width:160px"
              @blur="updatePolicyValue(row)" />
          </template>
        </template>
      </el-table-column>
      <el-table-column label="启用" width="65" align="center">
        <template #default="{ row }">
          <el-switch :model-value="row.is_enabled" size="small" @click="toggleEnabled(row)" />
        </template>
      </el-table-column>
    </el-table>

    <!-- JSON 编辑对话框 -->
    <el-dialog v-model="jsonVisible" title="编辑 JSON 策略" width="550px" destroy-on-close>
      <el-input v-model="jsonValue" type="textarea" :rows="8" />
      <template #footer>
        <el-button @click="jsonVisible = false">取消</el-button>
        <el-button type="primary" @click="saveJson">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getPolicies, updatePolicy } from '../../../api/securityCenter'

const loading = ref(false)
const policies = ref([])
const jsonVisible = ref(false)
const jsonValue = ref('')
const jsonTarget = ref(null)

async function fetchPolicies() {
  loading.value = true
  try {
    const { data } = await getPolicies()
    policies.value = (data || []).map(p => ({ ...p, _editValue: p.value_type === 'integer' ? parseInt(p.value || '0') : p.value || '' }))
  } catch (e) { ElMessage.error('获取策略失败') }
  finally { loading.value = false }
}

async function updatePolicyValue(row) {
  try {
    await updatePolicy(row.id, { value: String(row._editValue) })
    row.value = String(row._editValue)
  } catch (e) { ElMessage.error('更新失败') }
}

async function togglePolicy(row) {
  const newVal = row.value === 'true' ? 'false' : 'true'
  try {
    await updatePolicy(row.id, { value: newVal })
    row.value = newVal
    row._editValue = newVal
  } catch (e) { ElMessage.error('更新失败') }
}

async function toggleEnabled(row) {
  try {
    await updatePolicy(row.id, { is_enabled: !row.is_enabled })
    row.is_enabled = !row.is_enabled
  } catch (e) { ElMessage.error('更新失败') }
}

function editJson(row) {
  jsonTarget.value = row
  jsonValue.value = row.value || '[]'
  jsonVisible.value = true
}

async function saveJson() {
  try {
    JSON.parse(jsonValue.value)
    await updatePolicy(jsonTarget.value.id, { value: jsonValue.value })
    jsonTarget.value.value = jsonValue.value
    jsonTarget.value._editValue = jsonValue.value
    jsonVisible.value = false
    ElMessage.success('已更新')
  } catch (e) {
    ElMessage.error('JSON 格式无效')
  }
}

onMounted(fetchPolicies)
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
</style>
