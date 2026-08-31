<template>
  <div class="tenant-isolation-page">
    <!-- 统计 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="3"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_tenants }}</div><div class="stat-label">{{ t('tenant_isolation_page.stats.total_tenants') }}</div></div></el-card></el-col>
      <el-col :span="3"><el-card shadow="hover"><div class="stat-item"><div class="stat-value text-success">{{ stats.active_tenants }}</div><div class="stat-label">{{ t('tenant_isolation_page.stats.active_tenants') }}</div></div></el-card></el-col>
      <el-col :span="3"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_quota_plans }}</div><div class="stat-label">{{ t('tenant_isolation_page.stats.quota_plans') }}</div></div></el-card></el-col>
      <el-col :span="3"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_audit_logs }}</div><div class="stat-label">{{ t('tenant_isolation_page.stats.audit_logs') }}</div></div></el-card></el-col>
      <el-col :span="3"><el-card shadow="hover"><div class="stat-item"><div class="stat-value text-danger">{{ stats.pending_breaches }}</div><div class="stat-label">{{ t('tenant_isolation_page.stats.pending') }}</div></div></el-card></el-col>
      <el-col :span="3"><el-card shadow="hover"><div class="stat-item"><div class="stat-value text-warning">{{ stats.near_quota_tenants }}</div><div class="stat-label">{{ t('tenant_isolation_page.stats.near_quota') }}</div></div></el-card></el-col>
      <el-col :span="3"><el-card shadow="hover"><div class="stat-item"><div class="stat-value text-danger">{{ stats.over_quota_tenants }}</div><div class="stat-label">{{ t('tenant_isolation_page.stats.over_quota') }}</div></div></el-card></el-col>
      <el-col :span="3"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.active_shares }}</div><div class="stat-label">{{ t('tenant_isolation_page.stats.active_shares') }}</div></div></el-card></el-col>
    </el-row>

    <el-tabs v-model="activeTab" type="border-card">
      <!-- ─── 配额方案管理 ─── -->
      <el-tab-pane :label="t('tenant_isolation_page.tabs.plans')" name="plans">
        <div class="tab-toolbar">
          <el-button type="primary" @click="showPlanDlg = true"><el-icon><Plus /></el-icon> {{ t('tenant_isolation_page.plans.create_btn') }}</el-button>
        </div>
        <el-row :gutter="16">
          <el-col v-for="plan in quotaPlans" :key="plan.id" :span="6" class="mb-3">
            <el-card :class="['plan-card', { 'plan-default': plan.is_default }]" shadow="hover">
              <div class="plan-header">
                <h4>{{ plan.name }}</h4>
                <el-tag v-if="plan.is_default" size="small" type="success">{{ t('tenant_isolation_page.plans.default_tag') }}</el-tag>
              </div>
              <div class="plan-price">
                <span class="price">¥{{ plan.price_monthly }}</span><span class="text-muted">{{ t('tenant_isolation_page.plans.per_month') }}</span>
              </div>
              <p class="text-muted">{{ plan.description }}</p>
              <el-divider />
              <div v-for="(val, key) in plan.limits" :key="key" class="plan-limit">
                <span class="limit-label">{{ limitLabel(key) }}</span>
                <span class="limit-value">{{ val === 999999 ? '∞' : val.toLocaleString() }}</span>
              </div>
              <el-divider />
              <div class="plan-actions">
                <el-button size="small" @click="editPlan(plan)">{{ t('actions.edit') }}</el-button>
                <el-popconfirm :title="t('tenant_isolation_page.plans.delete_confirm')" @confirm="deletePlan(plan)">
                  <template #reference><el-button size="small" type="danger" :disabled="plan.is_default">{{ t('actions.delete') }}</el-button></template>
                </el-popconfirm>
              </div>
            </el-card>
          </el-col>
        </el-row>
      </el-tab-pane>

      <!-- ─── 租户配额 ─── -->
      <el-tab-pane :label="t('tenant_isolation_page.tabs.tenants')" name="tenants">
        <div class="tab-toolbar">
          <el-form :inline="true" size="small">
            <el-form-item><el-input v-model="tenantSearch" :placeholder="t('tenant_isolation_page.tenants.search_ph')" clearable style="width:200px" /></el-form-item>
            <el-form-item><el-button type="primary" @click="fetchTenants"><el-icon><Refresh /></el-icon> {{ t('tenant_isolation_page.tenants.refresh') }}</el-button></el-form-item>
            <el-form-item><el-button @click="batchRefresh"><el-icon><DataBoard /></el-icon> {{ t('tenant_isolation_page.tenants.batch_refresh') }}</el-button></el-form-item>
          </el-form>
        </div>
        <el-table :data="tenants" v-loading="loading" stripe @row-click="showTenantQuota">
          <el-table-column prop="name" :label="t('tenant_isolation_page.tenants.col_tenant')" min-width="140" />
          <el-table-column prop="quota_plan_name" :label="t('tenant_isolation_page.tenants.col_plan')" width="100">
            <template #default="{ row }">{{ row.quota_plan?.name || '-' }}</template>
          </el-table-column>
          <el-table-column :label="t('tenant_isolation_page.tenants.col_users')" width="70"><template #default="{ row }">{{ row.users_count ?? '-' }}</template></el-table-column>
          <el-table-column :label="t('tenant_isolation_page.tenants.col_licenses')" width="80"><template #default="{ row }">{{ row.licenses_count ?? '-' }}</template></el-table-column>
          <el-table-column :label="t('tenant_isolation_page.tenants.col_devices')" width="70"><template #default="{ row }">{{ row.devices_count ?? '-' }}</template></el-table-column>
          <el-table-column :label="t('tenant_isolation_page.tenants.col_isolation')" width="100">
            <template #default="{ row }">
              <el-tag :type="row.isolation_level === 'strict' ? 'danger' : row.isolation_level === 'logical' ? 'warning' : 'info'" size="small">
                {{ isolationLabel(row.isolation_level) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('tenant_isolation_page.tenants.col_over_quota')" width="70">
            <template #default="{ row }">
              <el-tag v-if="row.over_quota_since" type="danger" size="small">{{ t('tenant_isolation_page.bool.yes') }}</el-tag>
              <span v-else class="text-success">{{ t('tenant_isolation_page.bool.no') }}</span>
            </template>
          </el-table-column>
          <el-table-column :label="t('tenant_isolation_page.col_actions')" width="200" fixed="right">
            <template #default="{ row }">
              <el-button size="small" type="primary" link @click.stop="showTenantQuota(row)"><el-icon><DataAnalysis /></el-icon> {{ t('tenant_isolation_page.tenants.btn_quota') }}</el-button>
              <el-button size="small" type="primary" link @click.stop="showAuditLogs(row)"><el-icon><List /></el-icon> {{ t('tenant_isolation_page.tenants.btn_logs') }}</el-button>
              <el-button size="small" type="primary" link @click.stop="showShares(row)"><el-icon><Share /></el-icon> {{ t('tenant_isolation_page.tenants.btn_shares') }}</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ─── 隔离审计 ─── -->
      <el-tab-pane :label="t('tenant_isolation_page.tabs.audit')" name="audit">
        <div class="tab-toolbar">
          <el-form :inline="true" size="small">
            <el-form-item>
              <el-select v-model="auditFilter.event_type" :placeholder="t('tenant_isolation_page.audit.event_type_ph')" clearable @change="fetchAuditLogs" style="width:130px">
                <el-option v-for="opt in eventTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-select v-model="auditFilter.severity" :placeholder="t('tenant_isolation_page.audit.severity_ph')" clearable @change="fetchAuditLogs" style="width:100px">
                <el-option v-for="opt in severityOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-select v-model="auditFilter.is_resolved" :placeholder="t('tenant_isolation_page.audit.status_ph')" clearable @change="fetchAuditLogs" style="width:100px">
                <el-option v-for="opt in auditStatusOptions" :key="String(opt.value)" :label="opt.label" :value="opt.value" />
              </el-select>
            </el-form-item>
          </el-form>
        </div>
        <el-table :data="auditLogs" v-loading="auditLoading" stripe>
          <el-table-column :label="t('tenant_isolation_page.audit.col_event')" width="120">
            <template #default="{ row }">{{ eventLabel(row.event_type) }}</template>
          </el-table-column>
          <el-table-column :label="t('tenant_isolation_page.audit.severity_ph')" width="70">
            <template #default="{ row }">
              <el-tag :type="row.severity === 'critical' ? 'danger' : row.severity === 'warning' ? 'warning' : 'info'" size="small">{{ severityLabel(row.severity) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="resource_type" :label="t('tenant_isolation_page.audit.col_resource')" width="100" />
          <el-table-column :label="t('tenant_isolation_page.audit.col_details')" min-width="250">
            <template #default="{ row }">{{ row.details ? JSON.stringify(row.details) : '-' }}</template>
          </el-table-column>
          <el-table-column :label="t('tenant_isolation_page.audit.col_time')" width="160">
            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
          </el-table-column>
          <el-table-column :label="t('tenant_isolation_page.audit.col_status')" width="80">
            <template #default="{ row }">
              <el-tag v-if="row.is_resolved" type="success" size="small">{{ t('tenant_isolation_page.status.resolved') }}</el-tag>
              <el-tag v-else type="danger" size="small">{{ t('tenant_isolation_page.status.pending') }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('tenant_isolation_page.col_actions')" width="100" fixed="right">
            <template #default="{ row }">
              <el-button v-if="!row.is_resolved" size="small" type="primary" link @click="resolveAudit(row)">{{ t('tenant_isolation_page.audit.resolve_btn') }}</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ─── 跨租户共享 ─── -->
      <el-tab-pane :label="t('tenant_isolation_page.tabs.shares')" name="shares">
        <div class="tab-toolbar">
          <el-button type="primary" @click="showShareDlg = true"><el-icon><Plus /></el-icon> {{ t('tenant_isolation_page.shares.create_btn') }}</el-button>
        </div>
        <el-table :data="shares" v-loading="shareLoading" stripe>
          <el-table-column prop="source_tenant.name" :label="t('tenant_isolation_page.shares.col_source')" min-width="140" />
          <el-table-column prop="target_tenant.name" :label="t('tenant_isolation_page.shares.col_target')" min-width="140" />
          <el-table-column prop="resource_type" :label="t('tenant_isolation_page.shares.col_resource_type')" width="100" />
          <el-table-column prop="resource_id" :label="t('tenant_isolation_page.shares.col_resource_id')" width="80"><template #default="{ row }">{{ row.resource_id || t('tenant_isolation_page.shares.all_resources') }}</template></el-table-column>
          <el-table-column prop="permission" :label="t('tenant_isolation_page.shares.col_permission')" width="70">
            <template #default="{ row }">{{ permissionLabel(row.permission) }}</template>
          </el-table-column>
          <el-table-column :label="t('tenant_isolation_page.shares.col_status')" width="70">
            <template #default="{ row }">
              <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">{{ row.status }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('tenant_isolation_page.shares.col_expires')" width="120"><template #default="{ row }">{{ row.expires_at ? formatTime(row.expires_at) : t('tenant_isolation_page.shares.permanent') }}</template></el-table-column>
          <el-table-column :label="t('tenant_isolation_page.col_actions')" width="100" fixed="right">
            <template #default="{ row }">
              <el-popconfirm v-if="row.status === 'active'" :title="t('tenant_isolation_page.shares.revoke_confirm')" @confirm="revokeShare(row)">
                <template #reference><el-button size="small" type="danger" link>{{ t('tenant_isolation_page.shares.revoke_btn') }}</el-button></template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- ─── 配额方案对话框 ─── -->
    <el-dialog v-model="showPlanDlg" :title="planEditMode ? t('tenant_isolation_page.plans.edit_dialog') : t('tenant_isolation_page.plans.create_dialog')" width="700px">
      <el-form ref="planFormRef" :model="planForm" :rules="planRules" label-width="100px">
        <el-row :gutter="16">
          <el-col :span="12"><el-form-item :label="t('tenant_isolation_page.form.name')" prop="name"><el-input v-model="planForm.name" /></el-form-item></el-col>
          <el-col :span="12"><el-form-item :label="t('tenant_isolation_page.form.slug')" prop="slug"><el-input v-model="planForm.slug" /></el-form-item></el-col>
        </el-row>
        <el-form-item :label="t('tenant_isolation_page.form.description')"><el-input v-model="planForm.description" type="textarea" :rows="2" /></el-form-item>
        <el-row :gutter="16">
          <el-col :span="8"><el-form-item :label="t('tenant_isolation_page.form.tier')" prop="tier"><el-select v-model="planForm.tier" style="width:100%"><el-option v-for="opt in tierOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item></el-col>
          <el-col :span="8"><el-form-item :label="t('tenant_isolation_page.form.price_monthly')"><el-input-number v-model="planForm.price_monthly" :min="0" style="width:100%" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item :label="t('tenant_isolation_page.form.price_yearly')"><el-input-number v-model="planForm.price_yearly" :min="0" style="width:100%" /></el-form-item></el-col>
        </el-row>
        <el-divider>{{ t('tenant_isolation_page.plans.limits_divider') }}</el-divider>
        <el-row :gutter="16">
          <el-col :span="8"><el-form-item :label="t('tenant_isolation_page.limits.licenses_max')"><el-input-number v-model="planForm.limits.licenses_max" :min="0" style="width:100%" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item :label="t('tenant_isolation_page.limits.devices_max')"><el-input-number v-model="planForm.limits.devices_max" :min="0" style="width:100%" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item :label="t('tenant_isolation_page.limits.users_max')"><el-input-number v-model="planForm.limits.users_max" :min="0" style="width:100%" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="8"><el-form-item :label="t('tenant_isolation_page.limits.api_keys_max')"><el-input-number v-model="planForm.limits.api_keys_max" :min="0" style="width:100%" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item :label="t('tenant_isolation_page.limits.storage_mb')"><el-input-number v-model="planForm.limits.storage_mb" :min="0" :step="1024" style="width:100%" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item :label="t('tenant_isolation_page.limits.monthly_api_calls')"><el-input-number v-model="planForm.limits.monthly_api_calls" :min="0" :step="10000" style="width:100%" /></el-form-item></el-col>
        </el-row>
        <el-form-item :label="t('tenant_isolation_page.form.is_default')"><el-switch v-model="planForm.is_default" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPlanDlg = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="planLoading" @click="submitPlan">{{ planEditMode ? t('actions.update') : t('actions.create') }}</el-button>
      </template>
    </el-dialog>

    <!-- ─── 租户配额详情抽屉 ─── -->
    <el-drawer v-model="showQuotaDrawer" :title="t('tenant_isolation_page.quota_drawer.title', { name: quotaTenant?.name || '' })" size="500px">
      <el-descriptions :column="1" border size="small" v-if="quotaTenant">
        <el-descriptions-item :label="t('tenant_isolation_page.quota_drawer.plan')">{{ quotaTenant.quota_plan?.name || t('tenant_isolation_page.quota_drawer.no_plan') }}</el-descriptions-item>
        <el-descriptions-item :label="t('tenant_isolation_page.quota_drawer.isolation')">{{ isolationLabel(quotaTenant.isolation_level) }}</el-descriptions-item>
        <el-descriptions-item :label="t('tenant_isolation_page.quota_drawer.over_quota')">
          <el-tag v-if="quotaTenant.over_quota_since" type="danger">{{ t('tenant_isolation_page.bool.yes') }}</el-tag><span v-else>{{ t('tenant_isolation_page.bool.no') }}</span>
        </el-descriptions-item>
      </el-descriptions>
      <el-table :data="quotaItems" stripe size="small" class="mt-3" v-if="quotaItems.length">
        <el-table-column prop="metric_key" :label="t('tenant_isolation_page.quota_drawer.col_metric')" width="140" />
        <el-table-column prop="current" :label="t('tenant_isolation_page.quota_drawer.col_used')" width="80" />
        <el-table-column prop="limit" :label="t('tenant_isolation_page.quota_drawer.col_limit')" width="80" />
        <el-table-column :label="t('tenant_isolation_page.quota_drawer.col_usage')" width="120">
          <template #default="{ row }">
            <el-progress :percentage="Math.min(row.percent, 100)" :status="row.percent >= 90 ? 'exception' : row.percent >= 70 ? 'warning' : 'success'" :stroke-width="16" />
          </template>
        </el-table-column>
      </el-table>
    </el-drawer>

    <!-- ─── 跨租户共享对话框 ─── -->
    <el-dialog v-model="showShareDlg" :title="t('tenant_isolation_page.shares.create_dialog')" width="500px">
      <el-form :model="shareForm" :rules="shareRules" ref="shareFormRef" label-width="100px">
        <el-form-item :label="t('tenant_isolation_page.form.source_tenant')" prop="source_tenant_id">
          <el-select v-model="shareForm.source_tenant_id" filterable style="width:100%" :placeholder="t('tenant_isolation_page.form.select_tenant_ph')">
            <el-option v-for="tn in tenants" :key="tn.id" :label="tn.name" :value="tn.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('tenant_isolation_page.form.target_tenant')" prop="target_tenant_id">
          <el-select v-model="shareForm.target_tenant_id" filterable style="width:100%" :placeholder="t('tenant_isolation_page.form.select_tenant_ph')">
            <el-option v-for="tn in tenants" :key="tn.id" :label="tn.name" :value="tn.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('tenant_isolation_page.form.resource_type')" prop="resource_type">
          <el-select v-model="shareForm.resource_type" style="width:100%">
            <el-option v-for="rt in resourceTypes" :key="rt" :label="rt" :value="rt" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('tenant_isolation_page.form.permission')" prop="permission">
          <el-select v-model="shareForm.permission" style="width:100%">
            <el-option v-for="opt in permissionOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showShareDlg = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="shareLoading" @click="submitShare">{{ t('actions.create') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Plus, Refresh, DataBoard, DataAnalysis, List, Share } from '@element-plus/icons-vue';
import isolationApi from '../../api/tenantIsolation';
import tenantApi from '../../api/tenant';

export default {
  name: 'TenantIsolation',
  components: { Plus, Refresh, DataBoard, DataAnalysis, List, Share },
  setup() {
    const { t, locale } = useI18n();

    const activeTab = ref('plans');
    const loading = ref(false);
    const auditLoading = ref(false);
    const shareLoading = ref(false);
    const planLoading = ref(false);

    // 统计
    const stats = reactive({
      total_tenants: 0, active_tenants: 0, total_quota_plans: 0,
      total_audit_logs: 0, pending_breaches: 0, near_quota_tenants: 0,
      over_quota_tenants: 0, active_shares: 0,
    });

    // 配额方案
    const quotaPlans = ref([]);
    const showPlanDlg = ref(false);
    const planEditMode = ref(false);
    const planEditId = ref(null);
    const planFormRef = ref(null);
    const planForm = reactive({
      name: '', slug: '', description: '', tier: 'free',
      price_monthly: 0, price_yearly: 0, is_default: false,
      limits: { licenses_max: 50, devices_max: 500, users_max: 10, api_keys_max: 5, storage_mb: 1024, monthly_api_calls: 100000 },
    });
    const planRules = computed(() => ({
      name: [{ required: true, message: t('tenant_isolation_page.rules.name_required') }],
      slug: [{ required: true, message: t('tenant_isolation_page.rules.slug_required') }],
      tier: [{ required: true, message: t('tenant_isolation_page.rules.tier_required') }],
    }));

    // 租户列表
    const tenants = ref([]);
    const tenantSearch = ref('');

    // 租户配额
    const showQuotaDrawer = ref(false);
    const quotaTenant = ref(null);
    const quotaItems = ref([]);

    // 审计日志
    const auditLogs = ref([]);
    const auditFilter = reactive({ event_type: '', severity: '', is_resolved: undefined });

    // 跨租户共享
    const shares = ref([]);
    const showShareDlg = ref(false);
    const shareFormRef = ref(null);
    const shareForm = reactive({
      source_tenant_id: '', target_tenant_id: '', resource_type: 'licenses', permission: 'read',
    });
    const shareRules = computed(() => ({
      source_tenant_id: [{ required: true, message: t('tenant_isolation_page.rules.source_required') }],
      target_tenant_id: [{ required: true, message: t('tenant_isolation_page.rules.target_required') }],
      resource_type: [{ required: true, message: t('tenant_isolation_page.rules.resource_type_required') }],
    }));
    const resourceTypes = ['licenses', 'products', 'templates', 'knowledge'];

    const tierOptions = computed(() => [
      { label: t('tenant_isolation_page.tiers.free'), value: 'free' },
      { label: t('tenant_isolation_page.tiers.starter'), value: 'starter' },
      { label: t('tenant_isolation_page.tiers.business'), value: 'business' },
      { label: t('tenant_isolation_page.tiers.enterprise'), value: 'enterprise' },
      { label: t('tenant_isolation_page.tiers.custom'), value: 'custom' },
    ]);

    const eventTypeOptions = computed(() => [
      { label: t('tenant_isolation_page.events.quota_breach'), value: 'quota_breach' },
      { label: t('tenant_isolation_page.events.quota_notify'), value: 'quota_notify' },
      { label: t('tenant_isolation_page.events.data_access'), value: 'data_access' },
      { label: t('tenant_isolation_page.events.isolation_change'), value: 'isolation_change' },
      { label: t('tenant_isolation_page.events.config_change'), value: 'config_change' },
    ]);

    const severityOptions = computed(() => [
      { label: t('tenant_isolation_page.severity.info'), value: 'info' },
      { label: t('tenant_isolation_page.severity.warning'), value: 'warning' },
      { label: t('tenant_isolation_page.severity.critical'), value: 'critical' },
    ]);

    const auditStatusOptions = computed(() => [
      { label: t('tenant_isolation_page.status.unresolved'), value: false },
      { label: t('tenant_isolation_page.status.resolved'), value: true },
    ]);

    const permissionOptions = computed(() => [
      { label: t('tenant_isolation_page.permissions.read'), value: 'read' },
      { label: t('tenant_isolation_page.permissions.write'), value: 'write' },
      { label: t('tenant_isolation_page.permissions.admin'), value: 'admin' },
    ]);

    const limitLabels = computed(() => ({
      licenses_max: t('tenant_isolation_page.limits.licenses_max'),
      devices_max: t('tenant_isolation_page.limits.devices_max'),
      users_max: t('tenant_isolation_page.limits.users_max'),
      api_keys_max: t('tenant_isolation_page.limits.api_keys_max'),
      storage_mb: t('tenant_isolation_page.limits.storage_mb'),
      bandwidth_gb: t('tenant_isolation_page.limits.bandwidth_gb'),
      monthly_api_calls: t('tenant_isolation_page.limits.monthly_api_calls'),
      seats_total: t('tenant_isolation_page.limits.seats_total'),
    }));

    const isolationLabels = computed(() => ({
      strict: t('tenant_isolation_page.isolation.strict'),
      logical: t('tenant_isolation_page.isolation.logical'),
      shared: t('tenant_isolation_page.isolation.shared'),
    }));

    const eventLabels = computed(() => ({
      quota_breach: t('tenant_isolation_page.events.quota_breach'),
      quota_notify: t('tenant_isolation_page.events.quota_notify'),
      data_access: t('tenant_isolation_page.events.data_access'),
      isolation_change: t('tenant_isolation_page.events.isolation_change'),
      config_change: t('tenant_isolation_page.events.config_change'),
    }));

    const severityLabels = computed(() => ({
      info: t('tenant_isolation_page.severity.info'),
      warning: t('tenant_isolation_page.severity.warning'),
      critical: t('tenant_isolation_page.severity.critical'),
    }));

    const permissionLabels = computed(() => ({
      read: t('tenant_isolation_page.permissions.read'),
      write: t('tenant_isolation_page.permissions.write'),
      admin: t('tenant_isolation_page.permissions.admin'),
    }));

    // ─── 加载数据 ───
    async function fetchDashboard() {
      try {
        const { data } = await isolationApi.getDashboard();
        if (data.success) Object.assign(stats, data.data.stats);
      } catch (e) { /* */ }
    }

    async function fetchPlans() {
      try {
        const { data } = await isolationApi.getQuotaPlans();
        if (data.success) quotaPlans.value = data.data;
      } catch (e) { /* */ }
    }

    async function fetchTenants() {
      loading.value = true;
      try {
        const params = { per_page: 200 };
        if (tenantSearch.value) params.search = tenantSearch.value;
        const { data } = await tenantApi.adminList(params);
        if (data.success) tenants.value = data.data.data || [];
      } catch (e) {
        ElMessage.error(t('tenants_page.load_fail'));
      } finally {
        loading.value = false;
      }
    }

    async function fetchAuditLogs() {
      auditLoading.value = true;
      try {
        const params = { per_page: 100 };
        if (auditFilter.event_type) params.event_type = auditFilter.event_type;
        if (auditFilter.severity) params.severity = auditFilter.severity;
        if (auditFilter.is_resolved !== undefined && auditFilter.is_resolved !== '') params.is_resolved = auditFilter.is_resolved;
        if (tenants.value.length) {
          const { data } = await isolationApi.getAuditLogs(tenants.value[0].id, params);
          if (data.success) auditLogs.value = data.data;
        }
      } catch (e) { /* */ }
      finally { auditLoading.value = false; }
    }

    async function fetchShares() {
      try {
        if (tenants.value.length) {
          const { data } = await isolationApi.getShares(tenants.value[0].id);
          if (data.success) shares.value = data.data;
        }
      } catch (e) { /* */ }
    }

    // ─── 配额方案 ───
    function resetPlanForm() {
      planEditMode.value = false;
      planEditId.value = null;
      planForm.name = ''; planForm.slug = ''; planForm.description = '';
      planForm.tier = 'free'; planForm.price_monthly = 0; planForm.price_yearly = 0;
      planForm.is_default = false;
      planForm.limits = { licenses_max: 50, devices_max: 500, users_max: 10, api_keys_max: 5, storage_mb: 1024, monthly_api_calls: 100000 };
    }

    function editPlan(plan) {
      planEditMode.value = true;
      planEditId.value = plan.id;
      Object.assign(planForm, {
        name: plan.name, slug: plan.slug, description: plan.description || '',
        tier: plan.tier, price_monthly: plan.price_monthly, price_yearly: plan.price_yearly,
        is_default: plan.is_default,
        limits: plan.limits || {},
      });
      showPlanDlg.value = true;
    }

    async function submitPlan() {
      const valid = await planFormRef.value?.validate().catch(() => false);
      if (!valid) return;
      planLoading.value = true;
      try {
        if (planEditMode.value && planEditId.value) {
          await isolationApi.updateQuotaPlan(planEditId.value, { ...planForm });
          ElMessage.success(t('tenant_isolation_page.messages.plan_updated'));
        } else {
          await isolationApi.createQuotaPlan({ ...planForm });
          ElMessage.success(t('tenant_isolation_page.messages.plan_created'));
        }
        showPlanDlg.value = false;
        resetPlanForm();
        fetchPlans();
      } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'));
      } finally {
        planLoading.value = false;
      }
    }

    async function deletePlan(plan) {
      try {
        await isolationApi.deleteQuotaPlan(plan.id);
        ElMessage.success(t('tenant_isolation_page.messages.plan_deleted'));
        fetchPlans();
      } catch (e) {
        ElMessage.error(e.response?.data?.message || t('tenant_isolation_page.messages.delete_failed'));
      }
    }

    // ─── 租户配额 ───
    async function showTenantQuota(tenant) {
      quotaTenant.value = tenant;
      try {
        const { data } = await isolationApi.getTenantQuota(tenant.id);
        if (data.success) {
          quotaItems.value = data.data.quota || [];
          showQuotaDrawer.value = true;
        }
      } catch (e) { /* */ }
    }

    async function batchRefresh() {
      try {
        const { data } = await isolationApi.batchRefresh();
        ElMessage.success(data.message || t('tenant_isolation_page.messages.batch_refresh_done'));
        fetchTenants();
      } catch (e) {
        ElMessage.error(t('tenant_isolation_page.messages.batch_refresh_failed'));
      }
    }

    // ─── 审计日志 ───
    async function showAuditLogs(tenant) {
      auditFilter.is_resolved = undefined;
      try {
        const { data } = await isolationApi.getAuditLogs(tenant.id, { per_page: 100 });
        if (data.success) {
          auditLogs.value = data.data;
          activeTab.value = 'audit';
        }
      } catch (e) { /* */ }
    }

    async function resolveAudit(row) {
      try {
        await isolationApi.resolveAuditLog(row.id);
        ElMessage.success(t('tenant_isolation_page.messages.audit_resolved'));
        fetchAuditLogs();
        fetchDashboard();
      } catch (e) { /* */ }
    }

    // ─── 共享 ───
    async function showShares(tenant) {
      try {
        const { data } = await isolationApi.getShares(tenant.id);
        if (data.success) {
          shares.value = data.data;
          activeTab.value = 'shares';
        }
      } catch (e) { /* */ }
    }

    async function submitShare() {
      const valid = await shareFormRef.value?.validate().catch(() => false);
      if (!valid) return;
      shareLoading.value = true;
      try {
        await isolationApi.createShare({ ...shareForm });
        ElMessage.success(t('tenant_isolation_page.messages.share_created'));
        showShareDlg.value = false;
        fetchShares();
      } catch (e) {
        ElMessage.error(e.response?.data?.message || t('tenant_isolation_page.messages.create_failed'));
      } finally {
        shareLoading.value = false;
      }
    }

    async function revokeShare(share) {
      try {
        await isolationApi.revokeShare(share.id);
        ElMessage.success(t('tenant_isolation_page.messages.share_revoked'));
        fetchShares();
      } catch (e) { /* */ }
    }

    // ─── 工具 ───
    function limitLabel(key) {
      return limitLabels.value[key] || key;
    }

    function isolationLabel(level) {
      return isolationLabels.value[level] || level;
    }

    function eventLabel(type) {
      return eventLabels.value[type] || type;
    }

    function severityLabel(severity) {
      return severityLabels.value[severity] || severity;
    }

    function permissionLabel(permission) {
      return permissionLabels.value[permission] || permission;
    }

    function formatTime(time) {
      if (!time) return '-';
      const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
      return new Date(time).toLocaleString(loc, { hour12: false });
    }

    onMounted(() => {
      fetchDashboard();
      fetchPlans();
      fetchTenants().then(() => {
        fetchAuditLogs();
        fetchShares();
      });
    });

    return {
      t,
      activeTab, loading, auditLoading, shareLoading, planLoading,
      stats, quotaPlans, showPlanDlg, planEditMode, planFormRef, planForm, planRules,
      tenants, tenantSearch,
      showQuotaDrawer, quotaTenant, quotaItems,
      auditLogs, auditFilter,
      shares, showShareDlg, shareFormRef, shareForm, shareRules, resourceTypes,
      tierOptions, eventTypeOptions, severityOptions, auditStatusOptions, permissionOptions,
      editPlan, resetPlanForm, submitPlan, deletePlan,
      showTenantQuota, batchRefresh, fetchTenants,
      showAuditLogs, resolveAudit, fetchAuditLogs,
      showShares, submitShare, revokeShare,
      limitLabel, isolationLabel, eventLabel, severityLabel, permissionLabel, formatTime,
    };
  },
};
</script>

<style scoped>
.tenant-isolation-page { padding: 16px; }
.stat-item { text-align: center; }
.stat-value { font-size: 22px; font-weight: 700; }
.stat-label { font-size: 12px; color: #909399; }
.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; }
.text-warning { color: #e6a23c; }
.text-muted { color: #909399; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mt-3 { margin-top: 12px; }
.tab-toolbar { margin-bottom: 12px; }
.plan-card { border-radius: 8px; transition: transform .2s; }
.plan-card:hover { transform: translateY(-2px); }
.plan-default { border: 2px solid #67c23a; }
.plan-header { display: flex; justify-content: space-between; align-items: center; }
.plan-price { margin: 8px 0; }
.plan-price .price { font-size: 24px; font-weight: 700; color: #0f172a; }
.plan-limit { display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; }
.limit-label { color: #606266; }
.limit-value { font-weight: 600; }
.plan-actions { display: flex; gap: 8px; }
</style>
