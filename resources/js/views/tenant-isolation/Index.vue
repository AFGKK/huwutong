<template>
  <div class="tenant-isolation-page">
    <!-- 统计 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="3"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_tenants }}</div><div class="stat-label">总租户</div></div></el-card></el-col>
      <el-col :span="3"><el-card shadow="hover"><div class="stat-item"><div class="stat-value text-success">{{ stats.active_tenants }}</div><div class="stat-label">活跃</div></div></el-card></el-col>
      <el-col :span="3"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_quota_plans }}</div><div class="stat-label">配额方案</div></div></el-card></el-col>
      <el-col :span="3"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_audit_logs }}</div><div class="stat-label">审计日志</div></div></el-card></el-col>
      <el-col :span="3"><el-card shadow="hover"><div class="stat-item"><div class="stat-value text-danger">{{ stats.pending_breaches }}</div><div class="stat-label">待处理</div></div></el-card></el-col>
      <el-col :span="3"><el-card shadow="hover"><div class="stat-item"><div class="stat-value text-warning">{{ stats.near_quota_tenants }}</div><div class="stat-label">接近限额</div></div></el-card></el-col>
      <el-col :span="3"><el-card shadow="hover"><div class="stat-item"><div class="stat-value text-danger">{{ stats.over_quota_tenants }}</div><div class="stat-label">超额</div></div></el-card></el-col>
      <el-col :span="3"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.active_shares }}</div><div class="stat-label">跨租户共享</div></div></el-card></el-col>
    </el-row>

    <el-tabs v-model="activeTab" type="border-card">
      <!-- ─── 配额方案管理 ─── -->
      <el-tab-pane label="配额方案" name="plans">
        <div class="tab-toolbar">
          <el-button type="primary" @click="showPlanDlg = true"><el-icon><Plus /></el-icon> 新建方案</el-button>
        </div>
        <el-row :gutter="16">
          <el-col v-for="plan in quotaPlans" :key="plan.id" :span="6" class="mb-3">
            <el-card :class="['plan-card', { 'plan-default': plan.is_default }]" shadow="hover">
              <div class="plan-header">
                <h4>{{ plan.name }}</h4>
                <el-tag v-if="plan.is_default" size="small" type="success">默认</el-tag>
              </div>
              <div class="plan-price">
                <span class="price">¥{{ plan.price_monthly }}</span><span class="text-muted">/月</span>
              </div>
              <p class="text-muted">{{ plan.description }}</p>
              <el-divider />
              <div v-for="(val, key) in plan.limits" :key="key" class="plan-limit">
                <span class="limit-label">{{ limitLabel(key) }}</span>
                <span class="limit-value">{{ val === 999999 ? '∞' : val.toLocaleString() }}</span>
              </div>
              <el-divider />
              <div class="plan-actions">
                <el-button size="small" @click="editPlan(plan)">编辑</el-button>
                <el-popconfirm title="确定删除此方案?" @confirm="deletePlan(plan)">
                  <template #reference><el-button size="small" type="danger" :disabled="plan.is_default">删除</el-button></template>
                </el-popconfirm>
              </div>
            </el-card>
          </el-col>
        </el-row>
      </el-tab-pane>

      <!-- ─── 租户配额 ─── -->
      <el-tab-pane label="租户配额" name="tenants">
        <div class="tab-toolbar">
          <el-form :inline="true" size="small">
            <el-form-item><el-input v-model="tenantSearch" placeholder="搜索租户名称" clearable style="width:200px" /></el-form-item>
            <el-form-item><el-button type="primary" @click="fetchTenants"><el-icon><Refresh /></el-icon> 刷新</el-button></el-form-item>
            <el-form-item><el-button @click="batchRefresh"><el-icon><DataBoard /></el-icon> 批量刷新用量</el-button></el-form-item>
          </el-form>
        </div>
        <el-table :data="tenants" v-loading="loading" stripe @row-click="showTenantQuota">
          <el-table-column prop="name" label="租户" min-width="140" />
          <el-table-column prop="quota_plan_name" label="配额方案" width="100">
            <template #default="{ row }">{{ row.quota_plan?.name || '-' }}</template>
          </el-table-column>
          <el-table-column label="用户" width="70"><template #default="{ row }">{{ row.users_count ?? '-' }}</template></el-table-column>
          <el-table-column label="License" width="80"><template #default="{ row }">{{ row.licenses_count ?? '-' }}</template></el-table-column>
          <el-table-column label="设备" width="70"><template #default="{ row }">{{ row.devices_count ?? '-' }}</template></el-table-column>
          <el-table-column label="隔离级别" width="100">
            <template #default="{ row }">
              <el-tag :type="row.isolation_level === 'strict' ? 'danger' : row.isolation_level === 'logical' ? 'warning' : 'info'" size="small">
                {{ isolationLabel(row.isolation_level) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="超额" width="70">
            <template #default="{ row }">
              <el-tag v-if="row.over_quota_since" type="danger" size="small">是</el-tag>
              <span v-else class="text-success">否</span>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="200" fixed="right">
            <template #default="{ row }">
              <el-button size="small" type="primary" link @click.stop="showTenantQuota(row)"><el-icon><DataAnalysis /></el-icon> 配额</el-button>
              <el-button size="small" type="primary" link @click.stop="showAuditLogs(row)"><el-icon><List /></el-icon> 日志</el-button>
              <el-button size="small" type="primary" link @click.stop="showShares(row)"><el-icon><Share /></el-icon> 共享</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ─── 隔离审计 ─── -->
      <el-tab-pane label="隔离审计" name="audit">
        <div class="tab-toolbar">
          <el-form :inline="true" size="small">
            <el-form-item>
              <el-select v-model="auditFilter.event_type" placeholder="事件类型" clearable @change="fetchAuditLogs" style="width:130px">
                <el-option label="配额超限" value="quota_breach" />
                <el-option label="配额通知" value="quota_notify" />
                <el-option label="数据访问" value="data_access" />
                <el-option label="隔离变更" value="isolation_change" />
                <el-option label="配置变更" value="config_change" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-select v-model="auditFilter.severity" placeholder="级别" clearable @change="fetchAuditLogs" style="width:100px">
                <el-option label="信息" value="info" />
                <el-option label="警告" value="warning" />
                <el-option label="严重" value="critical" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-select v-model="auditFilter.is_resolved" placeholder="状态" clearable @change="fetchAuditLogs" style="width:100px">
                <el-option label="未处理" :value="false" />
                <el-option label="已处理" :value="true" />
              </el-select>
            </el-form-item>
          </el-form>
        </div>
        <el-table :data="auditLogs" v-loading="auditLoading" stripe>
          <el-table-column label="事件" width="120">
            <template #default="{ row }">{{ eventLabel(row.event_type) }}</template>
          </el-table-column>
          <el-table-column label="级别" width="70">
            <template #default="{ row }">
              <el-tag :type="row.severity === 'critical' ? 'danger' : row.severity === 'warning' ? 'warning' : 'info'" size="small">{{ row.severity }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="resource_type" label="资源" width="100" />
          <el-table-column label="详情" min-width="250">
            <template #default="{ row }">{{ row.details ? JSON.stringify(row.details) : '-' }}</template>
          </el-table-column>
          <el-table-column label="时间" width="160">
            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
          </el-table-column>
          <el-table-column label="状态" width="80">
            <template #default="{ row }">
              <el-tag v-if="row.is_resolved" type="success" size="small">已处理</el-tag>
              <el-tag v-else type="danger" size="small">待处理</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="100" fixed="right">
            <template #default="{ row }">
              <el-button v-if="!row.is_resolved" size="small" type="primary" link @click="resolveAudit(row)">标记处理</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ─── 跨租户共享 ─── -->
      <el-tab-pane label="跨租户共享" name="shares">
        <div class="tab-toolbar">
          <el-button type="primary" @click="showShareDlg = true"><el-icon><Plus /></el-icon> 新建共享</el-button>
        </div>
        <el-table :data="shares" v-loading="shareLoading" stripe>
          <el-table-column prop="source_tenant.name" label="源租户" min-width="140" />
          <el-table-column prop="target_tenant.name" label="目标租户" min-width="140" />
          <el-table-column prop="resource_type" label="资源类型" width="100" />
          <el-table-column prop="resource_id" label="资源 ID" width="80"><template #default="{ row }">{{ row.resource_id || '全部' }}</template></el-table-column>
          <el-table-column prop="permission" label="权限" width="70" />
          <el-table-column label="状态" width="70">
            <template #default="{ row }">
              <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">{{ row.status }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="过期" width="120"><template #default="{ row }">{{ row.expires_at ? formatTime(row.expires_at) : '永久' }}</template></el-table-column>
          <el-table-column label="操作" width="100" fixed="right">
            <template #default="{ row }">
              <el-popconfirm v-if="row.status === 'active'" title="撤销此共享?" @confirm="revokeShare(row)">
                <template #reference><el-button size="small" type="danger" link>撤销</el-button></template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- ─── 配额方案对话框 ─── -->
    <el-dialog v-model="showPlanDlg" :title="planEditMode ? '编辑方案' : '新建方案'" width="700px">
      <el-form ref="planFormRef" :model="planForm" :rules="planRules" label-width="100px">
        <el-row :gutter="16">
          <el-col :span="12"><el-form-item label="名称" prop="name"><el-input v-model="planForm.name" /></el-form-item></el-col>
          <el-col :span="12"><el-form-item label="标识" prop="slug"><el-input v-model="planForm.slug" /></el-form-item></el-col>
        </el-row>
        <el-form-item label="说明"><el-input v-model="planForm.description" type="textarea" :rows="2" /></el-form-item>
        <el-row :gutter="16">
          <el-col :span="8"><el-form-item label="等级" prop="tier"><el-select v-model="planForm.tier" style="width:100%"><el-option label="免费" value="free" /><el-option label="初创" value="starter" /><el-option label="商业" value="business" /><el-option label="企业" value="enterprise" /><el-option label="定制" value="custom" /></el-select></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="月费"><el-input-number v-model="planForm.price_monthly" :min="0" style="width:100%" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="年费"><el-input-number v-model="planForm.price_yearly" :min="0" style="width:100%" /></el-form-item></el-col>
        </el-row>
        <el-divider>配额限制</el-divider>
        <el-row :gutter="16">
          <el-col :span="8"><el-form-item label="License"><el-input-number v-model="planForm.limits.licenses_max" :min="0" style="width:100%" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="设备"><el-input-number v-model="planForm.limits.devices_max" :min="0" style="width:100%" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="用户"><el-input-number v-model="planForm.limits.users_max" :min="0" style="width:100%" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="8"><el-form-item label="API 密钥"><el-input-number v-model="planForm.limits.api_keys_max" :min="0" style="width:100%" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="存储(MB)"><el-input-number v-model="planForm.limits.storage_mb" :min="0" :step="1024" style="width:100%" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="月API调用"><el-input-number v-model="planForm.limits.monthly_api_calls" :min="0" :step="10000" style="width:100%" /></el-form-item></el-col>
        </el-row>
        <el-form-item label="默认"><el-switch v-model="planForm.is_default" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPlanDlg = false">取消</el-button>
        <el-button type="primary" :loading="planLoading" @click="submitPlan">{{ planEditMode ? '更新' : '创建' }}</el-button>
      </template>
    </el-dialog>

    <!-- ─── 租户配额详情抽屉 ─── -->
    <el-drawer v-model="showQuotaDrawer" :title="'配额详情 - ' + (quotaTenant?.name || '')" size="500px">
      <el-descriptions :column="1" border size="small" v-if="quotaTenant">
        <el-descriptions-item label="配额方案">{{ quotaTenant.quota_plan?.name || '无' }}</el-descriptions-item>
        <el-descriptions-item label="隔离级别">{{ isolationLabel(quotaTenant.isolation_level) }}</el-descriptions-item>
        <el-descriptions-item label="超出配额">
          <el-tag v-if="quotaTenant.over_quota_since" type="danger">是</el-tag><span v-else>否</span>
        </el-descriptions-item>
      </el-descriptions>
      <el-table :data="quotaItems" stripe size="small" class="mt-3" v-if="quotaItems.length">
        <el-table-column prop="metric_key" label="指标" width="140" />
        <el-table-column prop="current" label="已用" width="80" />
        <el-table-column prop="limit" label="限制" width="80" />
        <el-table-column label="使用率" width="120">
          <template #default="{ row }">
            <el-progress :percentage="Math.min(row.percent, 100)" :status="row.percent >= 90 ? 'exception' : row.percent >= 70 ? 'warning' : 'success'" :stroke-width="16" />
          </template>
        </el-table-column>
      </el-table>
    </el-drawer>

    <!-- ─── 跨租户共享对话框 ─── -->
    <el-dialog v-model="showShareDlg" title="新建跨租户共享" width="500px">
      <el-form :model="shareForm" :rules="shareRules" ref="shareFormRef" label-width="100px">
        <el-form-item label="源租户" prop="source_tenant_id">
          <el-select v-model="shareForm.source_tenant_id" filterable style="width:100%" placeholder="选择租户">
            <el-option v-for="t in tenants" :key="t.id" :label="t.name" :value="t.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="目标租户" prop="target_tenant_id">
          <el-select v-model="shareForm.target_tenant_id" filterable style="width:100%" placeholder="选择租户">
            <el-option v-for="t in tenants" :key="t.id" :label="t.name" :value="t.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="资源类型" prop="resource_type">
          <el-select v-model="shareForm.resource_type" style="width:100%">
            <el-option v-for="rt in resourceTypes" :key="rt" :label="rt" :value="rt" />
          </el-select>
        </el-form-item>
        <el-form-item label="权限" prop="permission">
          <el-select v-model="shareForm.permission" style="width:100%">
            <el-option label="只读" value="read" />
            <el-option label="读写" value="write" />
            <el-option label="管理" value="admin" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showShareDlg = false">取消</el-button>
        <el-button type="primary" :loading="shareLoading" @click="submitShare">创建</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus, Refresh, DataBoard, DataAnalysis, List, Share } from '@element-plus/icons-vue';
import isolationApi from '../../api/tenantIsolation';
import tenantApi from '../../api/tenant';

export default {
  name: 'TenantIsolation',
  components: { Plus, Refresh, DataBoard, DataAnalysis, List, Share },
  setup() {
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
    const planRules = {
      name: [{ required: true, message: '请输入名称' }],
      slug: [{ required: true, message: '请输入标识' }],
      tier: [{ required: true, message: '请选择等级' }],
    };

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
    const shareRules = {
      source_tenant_id: [{ required: true, message: '请选择源租户' }],
      target_tenant_id: [{ required: true, message: '请选择目标租户' }],
      resource_type: [{ required: true, message: '请选择资源类型' }],
    };
    const resourceTypes = ['licenses', 'products', 'templates', 'knowledge'];

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
        ElMessage.error('获取租户列表失败');
      } finally {
        loading.value = false;
      }
    }

    async function fetchAuditLogs() {
      auditLoading.value = true;
      try {
        // Get first tenant's audit logs as default view
        const params = { per_page: 100 };
        if (auditFilter.event_type) params.event_type = auditFilter.event_type;
        if (auditFilter.severity) params.severity = auditFilter.severity;
        if (auditFilter.is_resolved !== undefined && auditFilter.is_resolved !== '') params.is_resolved = auditFilter.is_resolved;
        // For admin view, fetch logs for all tenants via the first tenant
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
          ElMessage.success('方案已更新');
        } else {
          await isolationApi.createQuotaPlan({ ...planForm });
          ElMessage.success('方案已创建');
        }
        showPlanDlg.value = false;
        resetPlanForm();
        fetchPlans();
      } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败');
      } finally {
        planLoading.value = false;
      }
    }

    async function deletePlan(plan) {
      try {
        await isolationApi.deleteQuotaPlan(plan.id);
        ElMessage.success('方案已删除');
        fetchPlans();
      } catch (e) {
        ElMessage.error(e.response?.data?.message || '删除失败');
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
        ElMessage.success(data.message || '批量刷新完成');
        fetchTenants();
      } catch (e) {
        ElMessage.error('批量刷新失败');
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
        ElMessage.success('已标记为已处理');
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
        ElMessage.success('共享已创建');
        showShareDlg.value = false;
        fetchShares();
      } catch (e) {
        ElMessage.error(e.response?.data?.message || '创建失败');
      } finally {
        shareLoading.value = false;
      }
    }

    async function revokeShare(share) {
      try {
        await isolationApi.revokeShare(share.id);
        ElMessage.success('共享已撤销');
        fetchShares();
      } catch (e) { /* */ }
    }

    // ─── 工具 ───
    function limitLabel(key) {
      const map = {
        licenses_max: 'License', devices_max: '设备', users_max: '用户',
        api_keys_max: 'API 密钥', storage_mb: '存储(MB)', bandwidth_gb: '带宽(GB)',
        monthly_api_calls: '月API调用', seats_total: '总位',
      };
      return map[key] || key;
    }

    function isolationLabel(level) {
      const map = { strict: '严格', logical: '逻辑', shared: '共享' };
      return map[level] || level;
    }

    function eventLabel(type) {
      const map = { quota_breach: '配额超限', quota_notify: '配额通知', data_access: '数据访问', isolation_change: '隔离变更', config_change: '配置变更' };
      return map[type] || type;
    }

    function formatTime(t) {
      if (!t) return '-';
      return new Date(t).toLocaleString('zh-CN', { hour12: false });
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
      activeTab, loading, auditLoading, shareLoading, planLoading,
      stats, quotaPlans, showPlanDlg, planEditMode, planFormRef, planForm, planRules,
      tenants, tenantSearch,
      showQuotaDrawer, quotaTenant, quotaItems,
      auditLogs, auditFilter,
      shares, showShareDlg, shareFormRef, shareForm, shareRules, resourceTypes,
      editPlan, resetPlanForm, submitPlan, deletePlan,
      showTenantQuota, batchRefresh,
      showAuditLogs, resolveAudit,
      showShares, submitShare, revokeShare,
      limitLabel, isolationLabel, eventLabel, formatTime,
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
.plan-price .price { font-size: 24px; font-weight: 700; color: #409eff; }
.plan-limit { display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; }
.limit-label { color: #606266; }
.limit-value { font-weight: 600; }
.plan-actions { display: flex; gap: 8px; }
</style>
