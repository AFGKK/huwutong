<template>
  <div class="data-residency-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Connection /></el-icon>
        数据本地化存储
      </h2>
      <div class="header-actions">
        <el-button @click="refreshAll" :loading="loading" size="small">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <el-alert
      title="按租户/区域指定数据存储位置 — 德国→法兰克福S3、中国→上海OSS，自动路由+合规审计"
      type="info" show-icon :closable="false" class="mb-4"
    />

    <!-- 区域统计 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="8" v-for="(rs, code) in dashboard.regions" :key="code">
        <el-card shadow="hover" class="region-card">
          <template #header>
            <span>{{ rs.name }}</span>
            <el-tag size="small" style="float:right">{{ code }}</el-tag>
          </template>
          <el-descriptions :column="2" size="small">
            <el-descriptions-item label="租户数">{{ rs.tenant_count }}</el-descriptions-item>
            <el-descriptions-item label="绑定记录">{{ rs.record_count }}</el-descriptions-item>
            <el-descriptions-item label="活跃" :span="2">
              <el-tag :type="rs.active_count > 0 ? 'success' : 'info'" size="small">{{ rs.active_count }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="合规" :span="2">
              <el-space>
                <el-tag v-for="c in rs.compliance" :key="c" size="small" round>{{ c }}</el-tag>
              </el-space>
            </el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- Tab 1: 区域绑定 -->
        <el-tab-pane label="区域绑定" name="bindings">
          <div class="section-toolbar">
            <el-button type="primary" @click="showCreateDialog = true">
              <el-icon><Plus /></el-icon> 新建绑定
            </el-button>
          </div>
          <el-table :data="records" stripe v-loading="recordsLoading">
            <el-table-column prop="id" label="ID" width="50" />
            <el-table-column label="租户" width="150">
              <template #default="{ row }">{{ row.tenant?.name || 'N/A' }}</template>
            </el-table-column>
            <el-table-column prop="tenant_id" label="租户ID" width="70" />
            <el-table-column prop="region_code" label="区域" width="100">
              <template #default="{ row }">
                <el-tag :type="regionTag(row.region_code)" size="small">{{ row.region_code }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="data_classification" label="数据分类" width="150" />
            <el-table-column prop="storage_driver" label="存储驱动" width="100" />
            <el-table-column label="加密" width="70">
              <template #default="{ row }">
                <el-tag :type="row.encryption_enabled ? 'success' : 'info'" size="small">{{ row.encryption_enabled ? '是' : '否' }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="retention_days" label="保留(天)" width="90" />
            <el-table-column prop="status" label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'active' ? 'success' : 'warning'" size="small">{{ row.status }}</el-tag>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 2: 租户区域分配 -->
        <el-tab-pane label="租户分配" name="tenants">
          <el-table :data="tenantList" stripe v-loading="tenantsLoading">
            <el-table-column prop="id" label="ID" width="50" />
            <el-table-column prop="name" label="租户名称" min-width="200" />
            <el-table-column label="数据区域" width="160">
              <template #default="{ row }">
                <el-select v-model="row.data_region" placeholder="选择区域" size="small" @change="val => assignTenant(row.id, val)" style="width:150px">
                  <el-option v-for="(reg, code) in regions" :key="code" :label="reg.name" :value="code" />
                </el-select>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" label="创建时间" width="170" />
          </el-table>
        </el-tab-pane>

        <!-- Tab 3: 数据迁移 -->
        <el-tab-pane label="数据迁移" name="migrations">
          <div class="section-toolbar">
            <el-button type="warning" @click="showMigrateDialog = true">
              <el-icon><Connection /></el-icon> 新建迁移
            </el-button>
          </div>
          <el-table :data="migrations" stripe v-loading="migLoading">
            <el-table-column prop="id" label="ID" width="50" />
            <el-table-column label="租户" width="120">{{ row.tenant_id }}</el-table-column>
            <el-table-column label="从" width="120">
              <template #default="{ row }"><el-tag size="small">{{ row.source_region }}</el-tag></template>
            </el-table-column>
            <el-table-column label="到" width="120">
              <template #default="{ row }"><el-tag type="success" size="small">{{ row.target_region }}</el-tag></template>
            </el-table-column>
            <el-table-column prop="data_classification" label="分类" width="130" />
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'running' ? 'warning' : row.status === 'failed' ? 'danger' : 'info'" size="small">
                  {{ row.status }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="进度" width="150">
              <template #default="{ row }">
                <el-progress v-if="row.total_items" :percentage="Math.round(row.processed_items / row.total_items * 100)" :stroke-width="14" />
                <span v-else class="text-gray">-</span>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" label="创建时间" width="170" />
          </el-table>
        </el-tab-pane>

        <!-- Tab 4: 合规审计 -->
        <el-tab-pane label="合规审计" name="audit">
          <el-descriptions :column="2" border class="mb-4">
            <el-descriptions-item label="总绑定数">{{ audit.total_bindings }}</el-descriptions-item>
            <el-descriptions-item label="总迁移数">{{ audit.total_migrations }}</el-descriptions-item>
            <el-descriptions-item label="已完成迁移">{{ audit.completed_migrations }}</el-descriptions-item>
          </el-descriptions>
          <h4 class="mb-2">按区域分布</h4>
          <el-table :data="auditByRegion" stripe size="small" v-if="auditByRegion.length">
            <el-table-column prop="region" label="区域" width="140" />
            <el-table-column prop="count" label="绑定数" width="80" />
            <el-table-column label="租户" min-width="300">
              <template #default="{ row }">{{ row.tenants?.join(', ') || '-' }}</template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 新建绑定对话框 -->
    <el-dialog v-model="showCreateDialog" title="新建区域绑定" width="450px">
      <el-form :model="createForm" label-width="120px">
        <el-form-item label="租户" required>
          <el-select v-model="createForm.tenant_id" style="width:100%">
            <el-option v-for="t in tenantList" :key="t.id" :label="t.name" :value="t.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="区域" required>
          <el-select v-model="createForm.region_code" style="width:100%">
            <el-option v-for="(reg, code) in regions" :key="code" :label="reg.name" :value="code" />
          </el-select>
        </el-form-item>
        <el-form-item label="数据分类" required>
          <el-select v-model="createForm.data_classification" style="width:100%">
            <el-option v-for="(cfg, key) in classifications" :key="key" :label="key" :value="key" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="handleCreateRecord" :loading="creating">创建</el-button>
      </template>
    </el-dialog>

    <!-- 新建迁移对话框 -->
    <el-dialog v-model="showMigrateDialog" title="新建数据迁移" width="500px">
      <el-form :model="migrateForm" label-width="120px">
        <el-form-item label="租户" required>
          <el-select v-model="migrateForm.tenant_id" style="width:100%">
            <el-option v-for="t in tenantList" :key="t.id" :label="t.name" :value="t.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="源区域" required>
          <el-select v-model="migrateForm.source_region" style="width:100%">
            <el-option v-for="(reg, code) in regions" :key="code" :label="reg.name" :value="code" />
          </el-select>
        </el-form-item>
        <el-form-item label="目标区域" required>
          <el-select v-model="migrateForm.target_region" style="width:100%">
            <el-option v-for="(reg, code) in regions" :key="code" :label="reg.name" :value="code" />
          </el-select>
        </el-form-item>
        <el-form-item label="数据分类" required>
          <el-select v-model="migrateForm.data_classification" style="width:100%">
            <el-option v-for="(cfg, key) in classifications" :key="key" :label="key" :value="key" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showMigrateDialog = false">取消</el-button>
        <el-button type="warning" @click="handleStartMigration" :loading="migrating">创建迁移任务</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Connection, Refresh, Plus } from '@element-plus/icons-vue';
import api from '@/api/dataResidency';

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
    ElMessage.success('租户区域已更新');
  } catch {
    ElMessage.error('分配失败');
  }
}

async function handleCreateRecord() {
  if (!createForm.tenant_id) { ElMessage.warning('请选择租户'); return; }
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
  if (!migrateForm.tenant_id) { ElMessage.warning('请选择租户'); return; }
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
