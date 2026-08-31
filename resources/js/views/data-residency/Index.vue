<template>
  <div class="data-residency-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Connection /></el-icon>
        {{ t(`${P}.title`) }}
      </h2>
      <div class="header-actions">
        <el-button @click="refreshAll" :loading="loading" size="small">
          <el-icon><Refresh /></el-icon> {{ t(`${P}.refresh`) }}
        </el-button>
      </div>
    </div>

    <el-alert
      :title="t(`${P}.alert`)"
      type="info" show-icon :closable="false" class="mb-4"
    />

    <el-row :gutter="16" class="mb-4">
      <el-col :span="8" v-for="(rs, code) in dashboard.regions" :key="code">
        <el-card shadow="hover" class="region-card">
          <template #header>
            <span>{{ rs.name }}</span>
            <el-tag size="small" style="float:right">{{ code }}</el-tag>
          </template>
          <el-descriptions :column="2" size="small">
            <el-descriptions-item :label="t(`${P}.region.tenants`)">{{ rs.tenant_count }}</el-descriptions-item>
            <el-descriptions-item :label="t(`${P}.region.records`)">{{ rs.record_count }}</el-descriptions-item>
            <el-descriptions-item :label="t(`${P}.region.active`)" :span="2">
              <el-tag :type="rs.active_count > 0 ? 'success' : 'info'" size="small">{{ rs.active_count }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="t(`${P}.region.compliance`)" :span="2">
              <el-space>
                <el-tag v-for="c in rs.compliance" :key="c" size="small" round>{{ c }}</el-tag>
              </el-space>
            </el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <el-tab-pane :label="t(`${P}.tabs.bindings`)" name="bindings">
          <div class="section-toolbar">
            <el-button type="primary" @click="showCreateDialog = true">
              <el-icon><Plus /></el-icon> {{ t(`${P}.new_binding`) }}
            </el-button>
          </div>
          <el-table :data="records" stripe v-loading="recordsLoading">
            <el-table-column prop="id" :label="t(`${P}.cols.id`)" width="50" />
            <el-table-column :label="t(`${P}.cols.tenant`)" width="150">
              <template #default="{ row }">{{ row.tenant?.name || 'N/A' }}</template>
            </el-table-column>
            <el-table-column prop="tenant_id" :label="t(`${P}.cols.tenant_id`)" width="70" />
            <el-table-column prop="region_code" :label="t(`${P}.cols.region`)" width="100">
              <template #default="{ row }">
                <el-tag :type="regionTag(row.region_code)" size="small">{{ row.region_code }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="data_classification" :label="t(`${P}.cols.classification`)" width="150" />
            <el-table-column prop="storage_driver" :label="t(`${P}.cols.storage`)" width="100" />
            <el-table-column :label="t(`${P}.cols.encryption`)" width="70">
              <template #default="{ row }">
                <el-tag :type="row.encryption_enabled ? 'success' : 'info'" size="small">{{ row.encryption_enabled ? t(`${P}.yes`) : t(`${P}.no`) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="retention_days" :label="t(`${P}.cols.retention`)" width="90" />
            <el-table-column prop="status" :label="t(`${P}.cols.status`)" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'active' ? 'success' : 'warning'" size="small">{{ row.status }}</el-tag>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane :label="t(`${P}.tabs.tenants`)" name="tenants">
          <el-table :data="tenantList" stripe v-loading="tenantsLoading">
            <el-table-column prop="id" :label="t(`${P}.cols.id`)" width="50" />
            <el-table-column prop="name" :label="t(`${P}.cols.tenant_name`)" min-width="200" />
            <el-table-column :label="t(`${P}.cols.data_region`)" width="160">
              <template #default="{ row }">
                <el-select v-model="row.data_region" :placeholder="t(`${P}.select_region`)" size="small" @change="val => assignTenant(row.id, val)" style="width:150px">
                  <el-option v-for="(reg, code) in regions" :key="code" :label="reg.name" :value="code" />
                </el-select>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" :label="t(`${P}.cols.created`)" width="170" />
          </el-table>
        </el-tab-pane>

        <el-tab-pane :label="t(`${P}.tabs.migrations`)" name="migrations">
          <div class="section-toolbar">
            <el-button type="warning" @click="showMigrateDialog = true">
              <el-icon><Connection /></el-icon> {{ t(`${P}.new_migration`) }}
            </el-button>
          </div>
          <el-table :data="migrations" stripe v-loading="migLoading">
            <el-table-column prop="id" :label="t(`${P}.cols.id`)" width="50" />
            <el-table-column :label="t(`${P}.cols.tenant`)" width="120">
              <template #default="{ row }">{{ row.tenant_id }}</template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.from`)" width="120">
              <template #default="{ row }"><el-tag size="small">{{ row.source_region }}</el-tag></template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.to`)" width="120">
              <template #default="{ row }"><el-tag type="success" size="small">{{ row.target_region }}</el-tag></template>
            </el-table-column>
            <el-table-column prop="data_classification" :label="t(`${P}.cols.class_short`)" width="130" />
            <el-table-column :label="t(`${P}.cols.status`)" width="100">
              <template #default="{ row }">
                <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'running' ? 'warning' : row.status === 'failed' ? 'danger' : 'info'" size="small">
                  {{ row.status }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.progress`)" width="150">
              <template #default="{ row }">
                <el-progress v-if="row.total_items" :percentage="Math.round(row.processed_items / row.total_items * 100)" :stroke-width="14" />
                <span v-else class="text-gray">-</span>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" :label="t(`${P}.cols.created`)" width="170" />
          </el-table>
        </el-tab-pane>

        <el-tab-pane :label="t(`${P}.tabs.audit`)" name="audit">
          <el-descriptions :column="2" border class="mb-4">
            <el-descriptions-item :label="t(`${P}.audit.bindings`)">{{ audit.total_bindings }}</el-descriptions-item>
            <el-descriptions-item :label="t(`${P}.audit.migrations`)">{{ audit.total_migrations }}</el-descriptions-item>
            <el-descriptions-item :label="t(`${P}.audit.completed`)">{{ audit.completed_migrations }}</el-descriptions-item>
          </el-descriptions>
          <h4 class="mb-2">{{ t(`${P}.audit.by_region`) }}</h4>
          <el-table :data="auditByRegion" stripe size="small" v-if="auditByRegion.length">
            <el-table-column prop="region" :label="t(`${P}.cols.region`)" width="140" />
            <el-table-column prop="count" :label="t(`${P}.audit.binding_count`)" width="80" />
            <el-table-column :label="t(`${P}.cols.tenant`)" min-width="300">
              <template #default="{ row }">{{ row.tenants?.join(', ') || '-' }}</template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <el-dialog v-model="showCreateDialog" :title="t(`${P}.dialog.binding_title`)" width="450px">
      <el-form :model="createForm" label-width="120px">
        <el-form-item :label="t(`${P}.cols.tenant`)" required>
          <el-select v-model="createForm.tenant_id" style="width:100%">
            <el-option v-for="tn in tenantList" :key="tn.id" :label="tn.name" :value="tn.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t(`${P}.cols.region`)" required>
          <el-select v-model="createForm.region_code" style="width:100%">
            <el-option v-for="(reg, code) in regions" :key="code" :label="reg.name" :value="code" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t(`${P}.cols.classification`)" required>
          <el-select v-model="createForm.data_classification" style="width:100%">
            <el-option v-for="(cfg, key) in classifications" :key="key" :label="key" :value="key" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleCreateRecord" :loading="creating">{{ t('actions.create') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showMigrateDialog" :title="t(`${P}.dialog.migrate_title`)" width="500px">
      <el-form :model="migrateForm" label-width="120px">
        <el-form-item :label="t(`${P}.cols.tenant`)" required>
          <el-select v-model="migrateForm.tenant_id" style="width:100%">
            <el-option v-for="tn in tenantList" :key="tn.id" :label="tn.name" :value="tn.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t(`${P}.dialog.source`)" required>
          <el-select v-model="migrateForm.source_region" style="width:100%">
            <el-option v-for="(reg, code) in regions" :key="code" :label="reg.name" :value="code" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t(`${P}.dialog.target`)" required>
          <el-select v-model="migrateForm.target_region" style="width:100%">
            <el-option v-for="(reg, code) in regions" :key="code" :label="reg.name" :value="code" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t(`${P}.cols.classification`)" required>
          <el-select v-model="migrateForm.data_classification" style="width:100%">
            <el-option v-for="(cfg, key) in classifications" :key="key" :label="key" :value="key" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showMigrateDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="warning" @click="handleStartMigration" :loading="migrating">{{ t(`${P}.dialog.start_migrate`) }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Connection, Refresh, Plus } from '@element-plus/icons-vue';
import api from '@/api/dataResidency';

const { t } = useI18n();
const P = 'data_residency_page';

const loading = ref(false);
const activeTab = ref('bindings');
const recordsLoading = ref(false);
const tenantsLoading = ref(false);
const migLoading = ref(false);
const creating = ref(false);
const migrating = ref(false);

const dashboard = reactive({ regions: {}, total_regions: 0, total_tenants_with_region: 0, total_records: 0 });
const regions = ref({});
const records = ref([]);
const tenantList = ref([]);
const migrations = ref([]);
const classifications = ref({});
const audit = ref({ total_bindings: 0, total_migrations: 0, completed_migrations: 0, records_by_region: {} });

const showCreateDialog = ref(false);
const showMigrateDialog = ref(false);

const createForm = reactive({ tenant_id: null, region_code: 'us-east', data_classification: 'customer_pii' });
const migrateForm = reactive({ tenant_id: null, source_region: '', target_region: '', data_classification: 'customer_pii' });

const auditByRegion = computed(() => {
  const data = audit.value.records_by_region || {};
  return Object.entries(data).map(([region, info]) => ({ region, ...info }));
});

function regionTag(code) {
  const map = { 'us-east': '', 'eu-central': 'warning', 'cn-shanghai': 'danger', 'ap-southeast': 'success' };
  return map[code] || '';
}

async function refreshAll() {
  loading.value = true;
  try {
    const [dashRes, regRes, classRes, tenantRes] = await Promise.all([
      api.getDashboard(),
      api.getRegions(),
      api.getClassifications(),
      api.getTenants(),
    ]);
    if (dashRes.success) Object.assign(dashboard, dashRes.data);
    if (regRes.success) regions.value = regRes.data;
    if (classRes.success) classifications.value = classRes.data;
    if (tenantRes.success) tenantList.value = tenantRes.data;
  } finally { loading.value = false; }
}

async function loadRecords() {
  recordsLoading.value = true;
  try {
    const { data } = await api.getRecords();
    if (data.success) records.value = data.data.data || data.data || [];
  } finally { recordsLoading.value = false; }
}

async function loadMigrations() {
  migLoading.value = true;
  try {
    const { data } = await api.getMigrations();
    if (data.success) migrations.value = data.data.data || data.data || [];
  } finally { migLoading.value = false; }
}

async function loadAudit() {
  const { data } = await api.getComplianceAudit();
  if (data.success) audit.value = data.data;
}

async function assignTenant(tenantId, region) {
  if (!region) return;
  try {
    await api.assignTenantRegion(tenantId, region);
    ElMessage.success(t(`${P}.messages.region_updated`));
  } catch {
    ElMessage.error(t(`${P}.messages.assign_failed`));
  }
}

async function handleCreateRecord() {
  if (!createForm.tenant_id) { ElMessage.warning(t(`${P}.messages.select_tenant`)); return; }
  creating.value = true;
  try {
    const { data } = await api.createRecord(createForm.tenant_id, createForm.region_code, createForm.data_classification);
    if (data.success) {
      ElMessage.success(data.message);
      showCreateDialog.value = false;
      await loadRecords();
    }
  } finally { creating.value = false; }
}

async function handleStartMigration() {
  if (!migrateForm.tenant_id) { ElMessage.warning(t(`${P}.messages.select_tenant`)); return; }
  migrating.value = true;
  try {
    const { data } = await api.startMigration(migrateForm.tenant_id, migrateForm.source_region, migrateForm.target_region, migrateForm.data_classification);
    if (data.success) {
      ElMessage.success(data.message);
      showMigrateDialog.value = false;
      await loadMigrations();
    }
  } finally { migrating.value = false; }
}

onMounted(async () => {
  await refreshAll();
  await Promise.all([loadRecords(), loadMigrations(), loadAudit()]);
});
</script>

<style scoped>
.data-residency-page { padding: 0; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.mb-2 { margin-bottom: 8px; }
.text-gray { color: #909399; }
.region-card { cursor: default; }
.section-toolbar { margin-bottom: 16px; }
</style>
