<template>
  <div class="enterprise-sso-page">
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.active_idps }}</div><div class="stat-label">活跃 IdP</div></div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_idps }}</div><div class="stat-label">总 IdP</div></div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_domains }}</div><div class="stat-label">域名路由</div></div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_mappings }}</div><div class="stat-label">组映射</div></div></el-card></el-col>
    </el-row>

    <el-card class="mb-4">
      <template #header><div class="flex items-center justify-between">
        <span>🔐 企业身份提供商 (IdP)</span>
        <el-button type="primary" size="small" @click="showCreateDialog = true">新建 IdP</el-button>
      </div></template>

      <el-empty v-if="!loading && idps.length === 0" description="暂无 IdP 配置" />
      <el-table v-else :data="idps" stripe size="small">
        <el-table-column prop="name" label="名称" width="140" />
        <el-table-column prop="provider_type" label="类型" width="100">
          <template #default="{ row }"><el-tag>{{ row.provider_type }}</el-tag></template>
        </el-table-column>
        <el-table-column prop="idp_entity_id" label="IdP Entity ID" min-width="200" show-overflow-tooltip />
        <el-table-column prop="domain_routes_count" label="域名" width="60" />
        <el-table-column prop="group_mappings_count" label="组映射" width="70" />
        <el-table-column prop="is_active" label="状态" width="70">
          <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag></template>
        </el-table-column>
        <el-table-column label="操作" width="280" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="viewDetails(row)">详情</el-button>
            <el-button size="small" @click="downloadMetadata(row)">SP元数据</el-button>
            <el-button size="small" @click="runHealthCheck(row)">健康</el-button>
            <el-button size="small" type="danger" plain @click="confirmDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- IdP 详情展开 -->
    <el-drawer v-model="showDetailDrawer" :title="currentIdp?.name" size="500px" destroy-on-close>
      <el-tabs v-if="currentIdp">
        <el-tab-pane label="基本信息">
          <el-descriptions :column="1" border size="small">
            <el-descriptions-item label="名称">{{ currentIdp.name }}</el-descriptions-item>
            <el-descriptions-item label="类型">{{ currentIdp.provider_type }}</el-descriptions-item>
            <el-descriptions-item label="Entity ID">{{ currentIdp.idp_entity_id || '-' }}</el-descriptions-item>
            <el-descriptions-item label="SSO URL">{{ currentIdp.idp_sso_url || '-' }}</el-descriptions-item>
            <el-descriptions-item label="Name ID Format">{{ currentIdp.name_id_format }}</el-descriptions-item>
            <el-descriptions-item label="签名请求">{{ currentIdp.sign_authn_requests ? '是' : '否' }}</el-descriptions-item>
          </el-descriptions>
        </el-tab-pane>

        <el-tab-pane label="域名路由">
          <div class="mb-2 flex gap-2">
            <el-input v-model="newDomain" placeholder="example.com" size="small" style="width:200px" />
            <el-button size="small" type="primary" @click="addDomain">添加</el-button>
          </div>
          <el-table :data="domains" stripe size="small">
            <el-table-column prop="domain" label="域名" />
            <el-table-column prop="is_primary" label="主域名" width="70">
              <template #default="{ row }">{{ row.is_primary ? '是' : '否' }}</template>
            </el-table-column>
            <el-table-column label="操作" width="60">
              <template #default="{ row }"><el-button size="small" type="danger" plain @click="deleteDomainRow(row)">×</el-button></template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane label="组映射">
          <div class="mb-2 flex gap-2">
            <el-input v-model="newMapping.group" placeholder="IdP 组名" size="small" style="width:150px" />
            <el-input v-model="newMapping.role" placeholder="本地角色" size="small" style="width:150px" />
            <el-button size="small" type="primary" @click="addMapping">添加</el-button>
          </div>
          <el-table :data="mappings" stripe size="small">
            <el-table-column prop="idp_group_name" label="IdP 组名" />
            <el-table-column prop="local_role" label="本地角色" />
          </el-table>
        </el-tab-pane>

        <el-tab-pane label="JIT 规则">
          <el-empty v-if="jitRules.length === 0" description="无 JIT 规则" :image-size="40" />
          <div v-for="rule in jitRules" :key="rule.id" class="jit-card">
            <div class="jit-header">{{ rule.name }}</div>
            <div>默认角色: {{ rule.default_role }}</div>
            <div>自动创建: {{ rule.auto_create_users ? '是' : '否' }}</div>
            <div>域名过滤: {{ rule.email_domain_filter || '无' }}</div>
          </div>
          <el-button size="small" class="mt-2" @click="showJitDialog = true">+ 创建 JIT 规则</el-button>
        </el-tab-pane>
      </el-tabs>
    </el-drawer>

    <!-- 新建 IdP -->
    <el-dialog v-model="showCreateDialog" title="新建企业身份提供商" width="550px" destroy-on-close>
      <el-form ref="cfRef" :model="cf" :rules="cfRules" label-width="120px">
        <el-form-item label="名称" prop="name"><el-input v-model="cf.name" /></el-form-item>
        <el-form-item label="提供商类型" prop="provider_type">
          <el-select v-model="cf.provider_type" style="width:100%">
            <el-option label="Okta" value="okta" />
            <el-option label="Azure AD" value="azure_ad" />
            <el-option label="OneLogin" value="onelogin" />
            <el-option label="Generic SAML 2.0" value="generic_saml" />
          </el-select>
        </el-form-item>
        <el-form-item label="IdP Metadata XML">
          <el-input v-model="cf.idp_metadata_xml" type="textarea" :rows="6" placeholder="粘贴 IdP 的 Metadata XML 自动解析..." />
        </el-form-item>
        <el-form-item label="Name ID Format">
          <el-select v-model="cf.name_id_format" style="width:100%">
            <el-option label="Email Address" value="email" />
            <el-option label="Unspecified" value="unspecified" />
            <el-option label="Persistent" value="persistent" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleCreate">创建</el-button>
      </template>
    </el-dialog>

    <!-- JIT 规则对话框 -->
    <el-dialog v-model="showJitDialog" title="新建 JIT 规则" width="480px" destroy-on-close>
      <el-form :model="jitForm" label-width="100px">
        <el-form-item label="名称"><el-input v-model="jitForm.name" /></el-form-item>
        <el-form-item label="默认角色"><el-input v-model="jitForm.default_role" placeholder="user" /></el-form-item>
        <el-form-item label="自动创建"><el-switch v-model="jitForm.auto_create_users" /></el-form-item>
        <el-form-item label="同步属性"><el-switch v-model="jitForm.auto_update_attributes" /></el-form-item>
        <el-form-item label="域名过滤"><el-input v-model="jitForm.email_domain_filter" placeholder="example.com" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showJitDialog = false">取消</el-button>
        <el-button type="primary" @click="handleCreateJitRule">创建</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  getEnterpriseSsoStats, getIdps, createIdp, updateIdp, deleteIdp,
  getSpMetadata, parseMetadata, getDomains, createDomain, deleteDomain,
  getGroupMappings, createGroupMapping, getJitRules, createJitRule, healthCheck,
} from '../../api/enterpriseSso';

const loading = ref(false); const submitting = ref(false);
const idps = ref([]); const domains = ref([]); const mappings = ref([]); const jitRules = ref([]);
const currentIdp = ref(null);
const stats = reactive({ total_idps: 0, active_idps: 0, total_domains: 0, total_mappings: 0, total_jit_rules: 0 });

const showCreateDialog = ref(false); const showDetailDrawer = ref(false); const showJitDialog = ref(false);
const newDomain = ref(''); const newMapping = reactive({ group: '', role: '' });
const cf = reactive({ name: '', provider_type: 'generic_saml', idp_metadata_xml: '', name_id_format: 'email' });
const cfRules = { name: [{ required: true }], provider_type: [{ required: true }] };
const jitForm = reactive({ name: '', default_role: 'user', auto_create_users: true, auto_update_attributes: true, email_domain_filter: '' });

async function loadStats() { try { const { data } = await getEnterpriseSsoStats(); Object.assign(stats, data.data); } catch {} }
async function loadIdps() { loading.value = true; try { const { data } = await getIdps(); idps.value = data.data; } catch {} finally { loading.value = false; } }

async function handleCreate() {
  submitting.value = true;
  try {
    await createIdp({ ...cf });
    ElMessage.success('创建成功');
    showCreateDialog.value = false;
    cf.name = ''; cf.idp_metadata_xml = '';
    await loadIdps(); await loadStats();
  } catch (e) { ElMessage.error(e.response?.data?.message || '失败'); }
  finally { submitting.value = false; }
}

function confirmDelete(row) {
  ElMessageBox.confirm(`删除 IdP「${row.name}」？`, '确认', { type: 'warning' })
    .then(async () => { await deleteIdp(row.id); ElMessage.success('已删除'); await loadIdps(); await loadStats(); })
    .catch(() => {});
}

async function viewDetails(row) {
  currentIdp.value = row;
  showDetailDrawer.value = true;
  try { const { data } = await getDomains(row.id); domains.value = data.data; } catch { domains.value = []; }
  try { const { data } = await getGroupMappings(row.id); mappings.value = data.data; } catch { mappings.value = []; }
  try { const { data } = await getJitRules(row.id); jitRules.value = data.data; } catch { jitRules.value = []; }
}

async function downloadMetadata(row) {
  try {
    const resp = await getSpMetadata(row.id);
    const blob = new Blob([resp.data], { type: 'application/xml' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = `sp-metadata-${row.name}.xml`; a.click();
    URL.revokeObjectURL(url);
    ElMessage.success('SP 元数据已下载');
  } catch { ElMessage.error('下载失败'); }
}

async function runHealthCheck(row) {
  try {
    const { data } = await healthCheck(row.id);
    const r = data.data.result;
    ElMessage[r.is_healthy ? 'success' : 'warning'](r.message);
  } catch { ElMessage.error('健康检查失败'); }
}

async function addDomain() {
  if (!newDomain.value || !currentIdp.value) return;
  try { await createDomain(currentIdp.value.id, { domain: newDomain.value }); newDomain.value = ''; await refreshDomains(); ElMessage.success('域名已添加'); } catch {}
}

async function deleteDomainRow(row) {
  try { await deleteDomain(row.id); await refreshDomains(); ElMessage.success('已删除'); } catch {}
}

async function refreshDomains() {
  if (!currentIdp.value) return;
  const { data } = await getDomains(currentIdp.value.id);
  domains.value = data.data;
}

async function addMapping() {
  if (!newMapping.group || !newMapping.role || !currentIdp.value) return;
  try { await createGroupMapping(currentIdp.value.id, { idp_group_name: newMapping.group, local_role: newMapping.role }); newMapping.group = ''; newMapping.role = ''; const { data } = await getGroupMappings(currentIdp.value.id); mappings.value = data.data; ElMessage.success('映射已添加'); } catch {}
}

async function handleCreateJitRule() {
  if (!currentIdp.value) return;
  try { await createJitRule(currentIdp.value.id, { ...jitForm }); showJitDialog.value = false; const { data } = await getJitRules(currentIdp.value.id); jitRules.value = data.data; ElMessage.success('JIT 规则已创建'); } catch {}
}

onMounted(() => { loadStats(); loadIdps(); });
</script>
<style scoped>
.enterprise-sso-page { padding: 20px; }
.stat-item { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: var(--el-color-primary); }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.mb-4 { margin-bottom: 16px; }
.mb-2 { margin-bottom: 8px; }
.mt-2 { margin-top: 8px; }
.flex { display: flex; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.gap-2 { gap: 8px; }
.jit-card { padding: 10px; margin-bottom: 8px; background: var(--el-fill-color-light); border-radius: 6px; font-size: 13px; }
.jit-header { font-weight: 600; margin-bottom: 4px; }
</style>
