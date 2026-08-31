<template>
  <div>
    <div class="mb-4">
      <el-space>
        <el-select v-model="filterType" @change="fetchList" style="width:140px">
          <el-option :label="t('ip_whitelist_panel.all')" value="" />
          <el-option :label="t('ip_whitelist_panel.whitelist')" value="whitelist" />
          <el-option :label="t('ip_whitelist_panel.blacklist')" value="blacklist" />
        </el-select>
        <el-button type="primary" @click="openCreateDialog">{{ t('ip_whitelist_panel.add_ip') }}</el-button>
        <el-button @click="openBulkDialog">{{ t('ip_whitelist_panel.bulk_import') }}</el-button>
      </el-space>
    </div>

    <el-table :data="list" v-loading="loading" size="small" max-height="400">
      <el-table-column prop="ip_address" :label="t('ip_whitelist_panel.cols.ip')" width="180">
        <template #default="{ row }">
          <code class="ip-code">{{ row.ip_address }}</code>
        </template>
      </el-table-column>
      <el-table-column prop="label" :label="t('ip_whitelist_panel.cols.label')" min-width="160" />
      <el-table-column :label="t('ip_whitelist_panel.cols.type')" width="80">
        <template #default="{ row }">
          <el-tag :type="row.type === 'blacklist' ? 'danger' : 'success'" size="small">
            {{ row.type === 'blacklist' ? t('ip_whitelist_panel.blacklist') : t('ip_whitelist_panel.whitelist') }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('ip_whitelist_panel.cols.status')" width="70">
        <template #default="{ row }">
          <el-switch :model-value="row.is_active" size="small" @click="toggleActive(row)" />
        </template>
      </el-table-column>
      <el-table-column prop="hit_count" :label="t('ip_whitelist_panel.cols.hits')" width="60" align="center" />
      <el-table-column :label="t('ip_whitelist_panel.cols.last_hit')" width="160">
        <template #default="{ row }">{{ row.last_hit_at?.slice(0, 16) || '—' }}</template>
      </el-table-column>
      <el-table-column :label="t('ip_whitelist_panel.cols.actions')" width="160" fixed="right">
        <template #default="{ row }">
          <el-space>
            <el-button size="small" link @click="editRow(row)">{{ t('actions.edit') }}</el-button>
            <el-popconfirm :title="t('ip_whitelist_panel.confirm_delete')" @confirm="deleteRow(row)">
              <template #reference>
                <el-button size="small" type="danger" link>{{ t('actions.delete') }}</el-button>
              </template>
            </el-popconfirm>
          </el-space>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialogVisible" :title="isEdit ? t('ip_whitelist_panel.edit_title') : t('ip_whitelist_panel.create_title')" width="500px" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item :label="t('ip_whitelist_panel.cols.ip')" prop="ip_address">
          <el-input v-model="form.ip_address" :placeholder="t('ip_whitelist_panel.ip_ph')" />
        </el-form-item>
        <el-form-item :label="t('ip_whitelist_panel.cols.type')" prop="type">
          <el-radio-group v-model="form.type">
            <el-radio value="whitelist">{{ t('ip_whitelist_panel.whitelist') }}</el-radio>
            <el-radio value="blacklist">{{ t('ip_whitelist_panel.blacklist') }}</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item :label="t('ip_whitelist_panel.cols.label')">
          <el-input v-model="form.label" :placeholder="t('ip_whitelist_panel.label_ph')" maxlength="200" />
        </el-form-item>
        <el-form-item :label="t('actions.enable')">
          <el-switch v-model="form.is_active" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="saveRow" :loading="saving">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="bulkVisible" :title="t('ip_whitelist_panel.bulk_title')" width="550px" destroy-on-close>
      <el-form label-width="100px">
        <el-form-item :label="t('ip_whitelist_panel.cols.type')">
          <el-radio-group v-model="bulkType">
            <el-radio value="whitelist">{{ t('ip_whitelist_panel.whitelist') }}</el-radio>
            <el-radio value="blacklist">{{ t('ip_whitelist_panel.blacklist') }}</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item :label="t('ip_whitelist_panel.ip_list')">
          <el-input v-model="bulkIps" type="textarea" :rows="8"
            :placeholder="t('ip_whitelist_panel.bulk_ph')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="bulkVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="doBulkImport" :loading="bulkLoading">{{ t('ip_whitelist_panel.import') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { getIpWhitelists, createIpWhitelist, updateIpWhitelist, deleteIpWhitelist, bulkImportIps } from '../../../api/securityCenter'

const { t } = useI18n()

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
const rules = computed(() => ({
  ip_address: [{ required: true, message: t('ip_whitelist_panel.validation.ip'), trigger: 'blur' }],
}))

async function fetchList() {
  loading.value = true
  try {
    const { data } = await getIpWhitelists({ type: filterType.value || undefined })
    list.value = data || []
  } catch (e) { ElMessage.error(t('ip_whitelist_panel.messages.load_failed')) }
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
      ElMessage.success(t('ip_whitelist_panel.messages.updated'))
    } else {
      await createIpWhitelist({ ip_address: form.ip_address, type: form.type, label: form.label })
      ElMessage.success(t('ip_whitelist_panel.messages.added'))
    }
    dialogVisible.value = false
    fetchList()
  } catch (e) { ElMessage.error(t('ip_whitelist_panel.messages.save_failed')) }
  finally { saving.value = false }
}

async function toggleActive(row) {
  try {
    await updateIpWhitelist(row.id, { is_active: !row.is_active })
    row.is_active = !row.is_active
  } catch (e) { ElMessage.error(t('messages.failed')) }
}

async function deleteRow(row) {
  try {
    await deleteIpWhitelist(row.id)
    ElMessage.success(t('ip_whitelist_panel.messages.deleted'))
    fetchList()
  } catch (e) { ElMessage.error(t('ip_whitelist_panel.messages.delete_failed')) }
}

function openBulkDialog() {
  bulkVisible.value = true
  bulkType.value = 'whitelist'
  bulkIps.value = ''
}

async function doBulkImport() {
  if (!bulkIps.value.trim()) { ElMessage.warning(t('ip_whitelist_panel.messages.ip_required')); return }
  bulkLoading.value = true
  try {
    const { data } = await bulkImportIps({ ips: bulkIps.value, type: bulkType.value })
    ElMessage.success(t('ip_whitelist_panel.messages.imported', { n: data.imported }))
    bulkVisible.value = false
    fetchList()
  } catch (e) { ElMessage.error(t('ip_whitelist_panel.messages.import_failed')) }
  finally { bulkLoading.value = false }
}

onMounted(fetchList)
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.ip-code { background: #f5f7fa; padding: 2px 6px; border-radius: 3px; font-family: monospace; font-size: 13px; }
</style>
