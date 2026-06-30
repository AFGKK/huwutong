<template>
  <div>
    <div class="mb-4">
      <el-space>
        <el-select v-model="filterType" @change="fetchList" style="width:140px">
          <el-option label="全部" value="" />
          <el-option label="白名单" value="whitelist" />
          <el-option label="黑名单" value="blacklist" />
        </el-select>
        <el-button type="primary" @click="openCreateDialog">添加 IP</el-button>
        <el-button @click="openBulkDialog">批量导入</el-button>
      </el-space>
    </div>

    <el-table :data="list" v-loading="loading" size="small" max-height="400">
      <el-table-column prop="ip_address" label="IP 地址" width="180">
        <template #default="{ row }">
          <code class="ip-code">{{ row.ip_address }}</code>
        </template>
      </el-table-column>
      <el-table-column prop="label" label="标签" min-width="160" />
      <el-table-column label="类型" width="80">
        <template #default="{ row }">
          <el-tag :type="row.type === 'blacklist' ? 'danger' : 'success'" size="small">
            {{ row.type === 'blacklist' ? '黑名单' : '白名单' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="70">
        <template #default="{ row }">
          <el-switch :model-value="row.is_active" size="small" @click="toggleActive(row)" />
        </template>
      </el-table-column>
      <el-table-column prop="hit_count" label="命中" width="60" align="center" />
      <el-table-column label="最近命中" width="160">
        <template #default="{ row }">{{ row.last_hit_at?.slice(0, 16) || '—' }}</template>
      </el-table-column>
      <el-table-column label="操作" width="160" fixed="right">
        <template #default="{ row }">
          <el-space>
            <el-button size="small" link @click="editRow(row)">编辑</el-button>
            <el-popconfirm title="删除此规则？" @confirm="deleteRow(row)">
              <template #reference>
                <el-button size="small" type="danger" link>删除</el-button>
              </template>
            </el-popconfirm>
          </el-space>
        </template>
      </el-table-column>
    </el-table>

    <!-- 添加/编辑对话框 -->
    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑 IP 规则' : '添加 IP 规则'" width="500px" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item label="IP 地址" prop="ip_address">
          <el-input v-model="form.ip_address" placeholder="如 192.168.1.1 或 10.0.0.0/24" />
        </el-form-item>
        <el-form-item label="类型" prop="type">
          <el-radio-group v-model="form.type">
            <el-radio value="whitelist">白名单</el-radio>
            <el-radio value="blacklist">黑名单</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="标签">
          <el-input v-model="form.label" placeholder="如：办公室网络" maxlength="200" />
        </el-form-item>
        <el-form-item label="启用">
          <el-switch v-model="form.is_active" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="saveRow" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 批量导入对话框 -->
    <el-dialog v-model="bulkVisible" title="批量导入 IP" width="550px" destroy-on-close>
      <el-form label-width="100px">
        <el-form-item label="类型">
          <el-radio-group v-model="bulkType">
            <el-radio value="whitelist">白名单</el-radio>
            <el-radio value="blacklist">黑名单</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="IP 列表">
          <el-input v-model="bulkIps" type="textarea" :rows="8"
            placeholder="每行一个 IP 地址&#10;支持 CIDR 格式如 192.168.1.0/24" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="bulkVisible = false">取消</el-button>
        <el-button type="primary" @click="doBulkImport" :loading="bulkLoading">导入</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getIpWhitelists, createIpWhitelist, updateIpWhitelist, deleteIpWhitelist, bulkImportIps } from '../../../api/securityCenter'

const loading = ref(false)
const list = ref([])
const filterType = ref('')
const dialogVisible = ref(false)
const isEdit = ref(false)
const editId = ref(null)
const saving = ref(false)
const formRef = ref(null)
const bulkVisible = ref(false)
const bulkType = ref('whitelist')
const bulkIps = ref('')
const bulkLoading = ref(false)

const form = reactive({ ip_address: '', type: 'whitelist', label: '', is_active: true })
const rules = { ip_address: [{ required: true, message: '请输入 IP 地址', trigger: 'blur' }] }

async function fetchList() {
  loading.value = true
  try {
    const { data } = await getIpWhitelists({ type: filterType.value || undefined })
    list.value = data || []
  } catch (e) { ElMessage.error('获取列表失败') }
  finally { loading.value = false }
}

function openCreateDialog() {
  isEdit.value = false
  editId.value = null
  form.ip_address = ''; form.type = 'whitelist'; form.label = ''; form.is_active = true
  dialogVisible.value = true
}

function editRow(row) {
  isEdit.value = true
  editId.value = row.id
  form.ip_address = row.ip_address
  form.type = row.type
  form.label = row.label || ''
  form.is_active = row.is_active
  dialogVisible.value = true
}

async function saveRow() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    if (isEdit.value) {
      await updateIpWhitelist(editId.value, { label: form.label, is_active: form.is_active })
      ElMessage.success('已更新')
    } else {
      await createIpWhitelist({ ip_address: form.ip_address, type: form.type, label: form.label })
      ElMessage.success('已添加')
    }
    dialogVisible.value = false
    fetchList()
  } catch (e) { ElMessage.error('保存失败') }
  finally { saving.value = false }
}

async function toggleActive(row) {
  try {
    await updateIpWhitelist(row.id, { is_active: !row.is_active })
    row.is_active = !row.is_active
  } catch (e) { ElMessage.error('操作失败') }
}

async function deleteRow(row) {
  try {
    await deleteIpWhitelist(row.id)
    ElMessage.success('已删除')
    fetchList()
  } catch (e) { ElMessage.error('删除失败') }
}

function openBulkDialog() {
  bulkVisible.value = true
  bulkType.value = 'whitelist'
  bulkIps.value = ''
}

async function doBulkImport() {
  if (!bulkIps.value.trim()) { ElMessage.warning('请输入 IP 地址'); return }
  bulkLoading.value = true
  try {
    const { data } = await bulkImportIps({ ips: bulkIps.value, type: bulkType.value })
    ElMessage.success(`成功导入 ${data.imported} 条记录`)
    bulkVisible.value = false
    fetchList()
  } catch (e) { ElMessage.error('导入失败') }
  finally { bulkLoading.value = false }
}

onMounted(fetchList)
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.ip-code { background: #f5f7fa; padding: 2px 6px; border-radius: 3px; font-family: monospace; font-size: 13px; }
</style>
