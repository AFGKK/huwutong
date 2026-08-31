<template>
    <div class="tenants-page">
        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4" v-for="card in statCards" :key="card.label">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value">{{ card.value }}</div>
                        <div class="stat-label">{{ card.label }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选与操作栏 -->
        <el-card shadow="never">
            <div class="toolbar">
                <el-form :inline="true" :model="filters" size="small">
                    <el-form-item>
                        <el-input
                            v-model="filters.search"
                            :placeholder="t('tenants_page.search_ph')"
                            clearable
                            @clear="fetchData"
                            @keyup.enter="fetchData"
                            style="width: 260px"
                        >
                            <template #prefix><el-icon><Search /></el-icon></template>
                        </el-input>
                    </el-form-item>
                    <el-form-item>
                        <el-select v-model="filters['filter.status']" :placeholder="t('tenants_page.status')" clearable @change="fetchData" style="width: 120px">
                            <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="fetchData"><el-icon><Search /></el-icon> {{ t('actions.search') }}</el-button>
                    </el-form-item>
                </el-form>
                <el-button type="primary" @click="openCreate"><el-icon><Plus /></el-icon> {{ t('tenants_page.create_btn') }}</el-button>
            </div>

            <el-table :data="tenants" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="60" />
                <el-table-column :label="t('tenants_page.col_name')" min-width="160">
                    <template #default="{ row }">
                        <div class="tenant-name">
                            <span class="name-text">{{ row.name }}</span>
                            <span class="slug-text">{{ row.slug }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="domain" :label="t('tenants_page.col_domain')" width="180">
                    <template #default="{ row }">{{ row.domain || '-' }}</template>
                </el-table-column>
                <el-table-column prop="subscription_plan" :label="t('tenants_page.col_plan')" width="120">
                    <template #default="{ row }">{{ row.subscription_plan || '-' }}</template>
                </el-table-column>
                <el-table-column :label="t('tenants_page.col_stats')" width="180">
                    <template #default="{ row }">
                        <span class="stat-badges">
                            <el-tag size="small">{{ t('tenants_page.badge_users', { n: row.users_count || 0 }) }}</el-tag>
                            <el-tag size="small" type="success">{{ t('tenants_page.badge_customers', { n: row.customers_count || 0 }) }}</el-tag>
                            <el-tag size="small" type="warning">{{ t('tenants_page.badge_licenses', { n: row.licenses_count || 0 }) }}</el-tag>
                        </span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('tenants_page.col_status')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'active' ? 'success' : row.status === 'suspended' ? 'danger' : 'info'" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" :label="t('tenants_page.col_created')" width="170" />
                <el-table-column :label="t('tenants_page.col_actions')" width="220" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="openDetail(row)">{{ t('tenants_page.detail') }}</el-button>
                        <el-button text size="small" type="primary" @click="openEdit(row)">{{ t('actions.edit') }}</el-button>
                        <el-button
                            text size="small"
                            :type="row.status === 'active' ? 'warning' : 'success'"
                            @click="handleToggleStatus(row)"
                        >
                            {{ row.status === 'active' ? t('actions.disable') : t('actions.enable') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :total="total"
                    :page-sizes="[10, 20, 50]"
                    layout="total, sizes, prev, pager, next"
                    @change="fetchData"
                />
            </div>
        </el-card>

        <!-- 创建/编辑对话框 -->
        <el-dialog v-model="showDialog" :title="isEditing ? t('tenants_page.edit_title') : t('tenants_page.create_title')" width="600px">
            <el-form ref="formRef" :model="form" :rules="formRules" label-width="120px">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('tenants_page.form_name')" prop="name">
                            <el-input v-model="form.name" :placeholder="t('tenants_page.name_ph')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('tenants_page.form_slug')" prop="slug">
                            <el-input v-model="form.slug" :placeholder="t('tenants_page.slug_ph')" :disabled="isEditing" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t('tenants_page.form_domain')" prop="domain">
                    <el-input v-model="form.domain" :placeholder="t('tenants_page.domain_ph')" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="t('tenants_page.form_status')" prop="status">
                            <el-select v-model="form.status" style="width: 100%">
                                <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('tenants_page.form_plan')" prop="subscription_plan">
                            <el-input v-model="form.subscription_plan" :placeholder="t('tenants_page.plan_ph')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('tenants_page.form_region')" prop="data_region">
                            <el-input v-model="form.data_region" :placeholder="t('tenants_page.region_ph')" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t('tenants_page.form_mfa')" prop="mfa_policy">
                    <el-select v-model="form.mfa_policy" style="width: 200px">
                        <el-option v-for="opt in mfaOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('tenants_page.form_branding')" prop="branding">
                    <el-input v-model="form.branding" type="textarea" :rows="2" :placeholder="t('tenants_page.branding_ph')" />
                </el-form-item>
                <el-form-item :label="t('tenants_page.form_allowed_ips')" prop="allowed_ips" v-if="isEditing">
                    <el-select v-model="form.allowed_ips" multiple filterable allow-create default-first-option :placeholder="t('tenants_page.allowed_ips_ph')" style="width: 100%">
                        <el-option v-for="ip in form.allowed_ips" :key="ip" :label="ip" :value="ip" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="confirmSubmit">{{ isEditing ? t('tenants_page.save_changes') : t('tenants_page.create_tenant') }}</el-button>
            </template>
        </el-dialog>

        <!-- 详情对话框（含成员管理） -->
        <el-dialog v-model="showDetail" :title="t('tenants_page.detail_title')" width="700px">
            <template v-if="detail">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t('tenants_page.form_name')">{{ detail.name }}</el-descriptions-item>
                    <el-descriptions-item :label="t('tenants_page.form_slug')">{{ detail.slug }}</el-descriptions-item>
                    <el-descriptions-item :label="t('tenants_page.form_domain')">{{ detail.domain || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('tenants_page.form_status')">
                        <el-tag :type="detail.status === 'active' ? 'success' : detail.status === 'suspended' ? 'danger' : 'info'" size="small">
                            {{ statusLabel(detail.status) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('tenants_page.form_plan')">{{ detail.subscription_plan || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('tenants_page.form_region')">{{ detail.data_region || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('tenants_page.form_mfa')">{{ mfaLabel(detail.mfa_policy) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('tenants_page.col_created')">{{ detail.created_at }}</el-descriptions-item>
                </el-descriptions>

                <el-divider />

                <div class="section-header">
                    <span>{{ t('tenants_page.members_title', { n: detail.members?.length || 0 }) }}</span>
                    <el-button size="small" type="primary" @click="showAddMember = true">
                        <el-icon><Plus /></el-icon> {{ t('tenants_page.add_member') }}
                    </el-button>
                </div>
                <el-table :data="detail.members || []" size="small" stripe>
                    <el-table-column :label="t('tenants_page.col_user')" min-width="140">
                        <template #default="{ row }">
                            {{ row.user?.name || '-' }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('tenants_page.col_email')" width="180">
                        <template #default="{ row }">
                            {{ row.user?.email || '-' }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('tenants_page.col_role')" width="120">
                        <template #default="{ row }">
                            <el-select
                                :model-value="row.role"
                                size="small"
                                @change="(val) => handleUpdateMemberRole(detail, row, val)"
                                style="width: 100px"
                            >
                                <el-option v-for="opt in roleOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('tenants_page.col_actions')" width="80">
                        <template #default="{ row }">
                            <el-button text size="small" type="danger" @click="handleRemoveMember(detail, row)">{{ t('tenants_page.remove_member') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- 统计数据 -->
                <el-divider />
                <div class="section-header"><span>{{ t('tenants_page.stats_title') }}</span></div>
                <el-row :gutter="16">
                    <el-col :span="6" v-for="s in detailStats" :key="s.label">
                        <el-statistic :title="s.label" :value="s.value" />
                    </el-col>
                </el-row>
            </template>
            <template #footer>
                <el-button @click="showDetail = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>

        <!-- 添加成员对话框 -->
        <el-dialog v-model="showAddMember" :title="t('tenants_page.add_member_title')" width="400px">
            <el-form label-width="80px">
                <el-form-item :label="t('tenants_page.col_user')">
                    <el-select v-model="addMemberUserId" filterable remote
                        :remote-method="searchUsers"
                        :loading="searchingUsers"
                        :placeholder="t('tenants_page.search_user_ph')"
                        style="width: 100%"
                    >
                        <el-option v-for="u in searchUserResults" :key="u.id" :label="`${u.name} (${u.email})`" :value="u.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('tenants_page.col_role')">
                    <el-select v-model="addMemberRole" style="width: 100%">
                        <el-option v-for="opt in roleOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAddMember = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="addingMember" @click="confirmAddMember">{{ t('tenants_page.add_btn') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Plus } from '@element-plus/icons-vue';
import tenantApi from '@/api/tenant';
import apiClient from '@/api/client';

const { t } = useI18n();

// ─── 统计 ───
const stats = reactive({
    total: 0, active: 0, inactive: 0, suspended: 0,
    total_users: 0, avg_users_per_tenant: 0,
});

const statCards = computed(() => [
    { label: t('tenants_page.stat_total'), value: stats.total },
    { label: t('tenants_page.stat_active'), value: stats.active },
    { label: t('tenants_page.stat_inactive'), value: stats.inactive },
    { label: t('tenants_page.stat_suspended'), value: stats.suspended },
    { label: t('tenants_page.stat_total_users'), value: stats.total_users },
]);

const statusLabels = computed(() => ({
    active: t('tenants_page.st_active'),
    inactive: t('tenants_page.st_inactive'),
    suspended: t('tenants_page.st_suspended'),
}));

const statusOptions = computed(() => [
    { value: 'active', label: statusLabels.value.active },
    { value: 'inactive', label: statusLabels.value.inactive },
    { value: 'suspended', label: statusLabels.value.suspended },
]);

const mfaLabels = computed(() => ({
    optional: t('tenants_page.mfa_optional'),
    required: t('tenants_page.mfa_required'),
    disabled: t('tenants_page.mfa_disabled'),
}));

const mfaOptions = computed(() => [
    { value: 'optional', label: mfaLabels.value.optional },
    { value: 'required', label: mfaLabels.value.required },
    { value: 'disabled', label: mfaLabels.value.disabled },
]);

const roleOptions = computed(() => [
    { value: 'admin', label: t('tenants_page.role_admin') },
    { value: 'member', label: t('tenants_page.role_member') },
    { value: 'viewer', label: t('tenants_page.role_viewer') },
]);

function statusLabel(status) {
    return statusLabels.value[status] || status;
}

function mfaLabel(policy) {
    return mfaLabels.value[policy || 'optional'] || policy;
}

// ─── 列表 ───
const tenants = ref([]);
const loading = ref(false);
const page = ref(1);
const perPage = ref(20);
const total = ref(0);
const filters = reactive({
    search: '',
    'filter.status': '',
});

async function fetchData() {
    loading.value = true;
    try {
        const params = { page: page.value, per_page: perPage.value, ...filters };
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });

        const [listRes, statsRes] = await Promise.all([
            tenantApi.adminList(params),
            tenantApi.adminStats(),
        ]);

        tenants.value = listRes.data?.data || [];
        total.value = listRes.data?.meta?.total || 0;
        Object.assign(stats, statsRes.data?.data || {});
    } catch {
        ElMessage.error(t('tenants_page.load_fail'));
    } finally {
        loading.value = false;
    }
}

// ─── 创建/编辑 ───
const showDialog = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const submitting = ref(false);
const formRef = ref(null);

const form = reactive({
    name: '', slug: '', domain: '', status: 'active',
    subscription_plan: '', data_region: '',
    mfa_policy: 'optional', branding: '', allowed_ips: [],
});

const formRules = computed(() => ({
    name: [{ required: true, message: t('tenants_page.name_required'), trigger: 'blur' }],
}));

function openCreate() {
    isEditing.value = false;
    editingId.value = null;
    form.name = ''; form.slug = ''; form.domain = '';
    form.status = 'active'; form.subscription_plan = '';
    form.data_region = ''; form.mfa_policy = 'optional';
    form.branding = ''; form.allowed_ips = [];
    showDialog.value = true;
}

function openEdit(row) {
    isEditing.value = true;
    editingId.value = row.id;
    form.name = row.name;
    form.slug = row.slug || '';
    form.domain = row.domain || '';
    form.status = row.status;
    form.subscription_plan = row.subscription_plan || '';
    form.data_region = row.data_region || '';
    form.mfa_policy = row.mfa_policy || 'optional';
    form.branding = row.branding ? JSON.stringify(row.branding, null, 2) : '';
    form.allowed_ips = row.allowed_ips || [];
    showDialog.value = true;
}

async function confirmSubmit() {
    const valid = await formRef.value?.validate().catch(() => false);
    if (!valid) return;

    submitting.value = true;
    try {
        const payload = {
            name: form.name,
            slug: form.slug || undefined,
            domain: form.domain || undefined,
            status: form.status,
            subscription_plan: form.subscription_plan || undefined,
            data_region: form.data_region || undefined,
            mfa_policy: form.mfa_policy,
            branding: form.branding ? (() => { try { return JSON.parse(form.branding); } catch { return form.branding; } })() : undefined,
            allowed_ips: form.allowed_ips.length > 0 ? form.allowed_ips : undefined,
        };

        if (isEditing.value) {
            await tenantApi.adminUpdate(editingId.value, payload);
            ElMessage.success(t('tenants_page.update_ok'));
        } else {
            await tenantApi.adminCreate(payload);
            ElMessage.success(t('tenants_page.create_ok'));
        }
        showDialog.value = false;
        await fetchData();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('messages.failed'));
    } finally {
        submitting.value = false;
    }
}

// ─── 详情 ───
const showDetail = ref(false);
const detail = ref(null);

const detailStats = computed(() => {
    if (!detail.value) return [];
    return [
        { label: t('tenants_page.stat_users'), value: detail.value.users_count || 0 },
        { label: t('tenants_page.stat_customers'), value: detail.value.customers_count || 0 },
        { label: t('tenants_page.stat_licenses'), value: detail.value.licenses_count || 0 },
        { label: t('tenants_page.stat_devices'), value: detail.value.devices_count || 0 },
    ];
});

async function openDetail(row) {
    try {
        const res = await tenantApi.adminShow(row.id);
        detail.value = res.data?.data;
        showDetail.value = true;
    } catch {
        ElMessage.error(t('tenants_page.detail_load_fail'));
    }
}

// ─── 启用/停用 ───
async function handleToggleStatus(row) {
    const isActive = row.status === 'active';
    const action = t(isActive ? 'actions.disable' : 'actions.enable');
    try {
        await ElMessageBox.confirm(
            t('tenants_page.toggle_confirm', { action, name: row.name }),
            t('actions.confirm'),
            { type: 'warning' },
        );
        await tenantApi.adminToggleStatus(row.id);
        ElMessage.success(t(isActive ? 'tenants_page.deactivate_ok' : 'tenants_page.activate_ok'));
        await fetchData();
    } catch { /* cancelled */ }
}

// ─── 成员管理 ───
const showAddMember = ref(false);
const addMemberUserId = ref(null);
const addMemberRole = ref('member');
const addingMember = ref(false);
const searchUserResults = ref([]);
const searchingUsers = ref(false);

async function searchUsers(q) {
    if (q.length < 2) { searchUserResults.value = []; return; }
    searchingUsers.value = true;
    try {
        const res = await apiClient.get('/users/search', { params: { q } });
        searchUserResults.value = res.data?.data || [];
    } catch {
        searchUserResults.value = [];
    } finally {
        searchingUsers.value = false;
    }
}

async function confirmAddMember() {
    if (!addMemberUserId.value) { ElMessage.warning(t('tenants_page.select_user_first')); return; }
    addingMember.value = true;
    try {
        await tenantApi.adminAddMember(detail.value.id, {
            user_id: addMemberUserId.value,
            role: addMemberRole.value,
        });
        ElMessage.success(t('tenants_page.member_added'));
        showAddMember.value = false;
        addMemberUserId.value = null;
        const res = await tenantApi.adminShow(detail.value.id);
        detail.value = res.data?.data;
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('tenants_page.add_fail'));
    } finally {
        addingMember.value = false;
    }
}

async function handleUpdateMemberRole(tenant, member, newRole) {
    try {
        await tenantApi.adminUpdateMemberRole(tenant.id, member.id, { role: newRole });
        ElMessage.success(t('tenants_page.role_updated'));
        const res = await tenantApi.adminShow(tenant.id);
        detail.value = res.data?.data;
    } catch {
        ElMessage.error(t('tenants_page.role_update_fail'));
    }
}

async function handleRemoveMember(tenant, member) {
    try {
        await ElMessageBox.confirm(t('tenants_page.remove_confirm'), t('actions.confirm'), { type: 'warning' });
        await tenantApi.adminRemoveMember(tenant.id, member.id);
        ElMessage.success(t('tenants_page.member_removed'));
        const res = await tenantApi.adminShow(tenant.id);
        detail.value = res.data?.data;
    } catch { /* cancelled */ }
}

onMounted(() => { fetchData(); });
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-value { font-size: 26px; font-weight: 700; color: var(--el-text-color-primary); }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }

.toolbar {
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 12px; margin-bottom: 16px;
}

.tenant-name { display: flex; flex-direction: column; }
.name-text { font-weight: 500; font-size: 14px; }
.slug-text { font-size: 12px; color: var(--el-text-color-secondary); }

.stat-badges { display: flex; gap: 4px; flex-wrap: wrap; }

.pagination-wrap { display: flex; justify-content: center; margin-top: 16px; }

.section-header {
    display: flex; justify-content: space-between; align-items: center;
    font-weight: 600; font-size: 15px; margin-bottom: 12px;
}
</style>
