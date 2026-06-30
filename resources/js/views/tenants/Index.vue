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
                            placeholder="搜索租户（名称/域名）"
                            clearable
                            @clear="fetchData"
                            @keyup.enter="fetchData"
                            style="width: 260px"
                        >
                            <template #prefix><el-icon><Search /></el-icon></template>
                        </el-input>
                    </el-form-item>
                    <el-form-item>
                        <el-select v-model="filters['filter.status']" placeholder="状态" clearable @change="fetchData" style="width: 120px">
                            <el-option label="活跃" value="active" />
                            <el-option label="停用" value="inactive" />
                            <el-option label="暂停" value="suspended" />
                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="fetchData"><el-icon><Search /></el-icon> 查询</el-button>
                    </el-form-item>
                </el-form>
                <el-button type="primary" @click="openCreate"><el-icon><Plus /></el-icon> 新建租户</el-button>
            </div>

            <el-table :data="tenants" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="60" />
                <el-table-column label="租户名称" min-width="160">
                    <template #default="{ row }">
                        <div class="tenant-name">
                            <span class="name-text">{{ row.name }}</span>
                            <span class="slug-text">{{ row.slug }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="domain" label="域名" width="180">
                    <template #default="{ row }">{{ row.domain || '-' }}</template>
                </el-table-column>
                <el-table-column prop="subscription_plan" label="订阅方案" width="120">
                    <template #default="{ row }">{{ row.subscription_plan || '-' }}</template>
                </el-table-column>
                <el-table-column label="统计" width="180">
                    <template #default="{ row }">
                        <span class="stat-badges">
                            <el-tag size="small">{{ row.users_count || 0 }} 用户</el-tag>
                            <el-tag size="small" type="success">{{ row.customers_count || 0 }} 客户</el-tag>
                            <el-tag size="small" type="warning">{{ row.licenses_count || 0 }} License</el-tag>
                        </span>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'active' ? 'success' : row.status === 'suspended' ? 'danger' : 'info'" size="small">
                            {{ row.status === 'active' ? '活跃' : row.status === 'suspended' ? '暂停' : '停用' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="170" />
                <el-table-column label="操作" width="220" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="openDetail(row)">详情</el-button>
                        <el-button text size="small" type="primary" @click="openEdit(row)">编辑</el-button>
                        <el-button
                            text size="small"
                            :type="row.status === 'active' ? 'warning' : 'success'"
                            @click="handleToggleStatus(row)"
                        >
                            {{ row.status === 'active' ? '停用' : '启用' }}
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
        <el-dialog v-model="showDialog" :title="isEditing ? '编辑租户' : '新建租户'" width="600px">
            <el-form ref="formRef" :model="form" :rules="formRules" label-width="120px">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="名称" prop="name">
                            <el-input v-model="form.name" placeholder="公司/组织名称" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="标识" prop="slug">
                            <el-input v-model="form.slug" placeholder="唯一标识符" :disabled="isEditing" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="域名" prop="domain">
                    <el-input v-model="form.domain" placeholder="例如：tenant.example.com" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="状态" prop="status">
                            <el-select v-model="form.status" style="width: 100%">
                                <el-option label="活跃" value="active" />
                                <el-option label="停用" value="inactive" />
                                <el-option label="暂停" value="suspended" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="订阅方案" prop="subscription_plan">
                            <el-input v-model="form.subscription_plan" placeholder="如：enterprise" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="数据区域" prop="data_region">
                            <el-input v-model="form.data_region" placeholder="如：cn-north" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="MFA 策略" prop="mfa_policy">
                    <el-select v-model="form.mfa_policy" style="width: 200px">
                        <el-option label="可选" value="optional" />
                        <el-option label="强制" value="required" />
                        <el-option label="禁用" value="disabled" />
                    </el-select>
                </el-form-item>
                <el-form-item label="品牌设置" prop="branding">
                    <el-input v-model="form.branding" type="textarea" :rows="2" placeholder='JSON，如：{"primary_color":"#409eff"}' />
                </el-form-item>
                <el-form-item label="IP 白名单" prop="allowed_ips" v-if="isEditing">
                    <el-select v-model="form.allowed_ips" multiple filterable allow-create default-first-option placeholder="输入 IP 地址后回车" style="width: 100%">
                        <el-option v-for="ip in form.allowed_ips" :key="ip" :label="ip" :value="ip" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showDialog = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="confirmSubmit">{{ isEditing ? '保存修改' : '创建租户' }}</el-button>
            </template>
        </el-dialog>

        <!-- 详情对话框（含成员管理） -->
        <el-dialog v-model="showDetail" title="租户详情" width="700px">
            <template v-if="detail">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="名称">{{ detail.name }}</el-descriptions-item>
                    <el-descriptions-item label="标识">{{ detail.slug }}</el-descriptions-item>
                    <el-descriptions-item label="域名">{{ detail.domain || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="detail.status === 'active' ? 'success' : 'info'" size="small">
                            {{ detail.status === 'active' ? '活跃' : '停用' }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="订阅方案">{{ detail.subscription_plan || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="数据区域">{{ detail.data_region || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="MFA 策略">{{ detail.mfa_policy || 'optional' }}</el-descriptions-item>
                    <el-descriptions-item label="创建时间">{{ detail.created_at }}</el-descriptions-item>
                </el-descriptions>

                <el-divider />

                <div class="section-header">
                    <span>成员管理 ({{ detail.members?.length || 0 }})</span>
                    <el-button size="small" type="primary" @click="showAddMember = true">
                        <el-icon><Plus /></el-icon> 添加成员
                    </el-button>
                </div>
                <el-table :data="detail.members || []" size="small" stripe>
                    <el-table-column label="用户" min-width="140">
                        <template #default="{ row }">
                            {{ row.user?.name || '-' }}
                        </template>
                    </el-table-column>
                    <el-table-column label="邮箱" width="180">
                        <template #default="{ row }">
                            {{ row.user?.email || '-' }}
                        </template>
                    </el-table-column>
                    <el-table-column label="角色" width="120">
                        <template #default="{ row }">
                            <el-select
                                :model-value="row.role"
                                size="small"
                                @change="(val) => handleUpdateMemberRole(detail, row, val)"
                                style="width: 100px"
                            >
                                <el-option label="管理员" value="admin" />
                                <el-option label="成员" value="member" />
                                <el-option label="观察者" value="viewer" />
                            </el-select>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="80">
                        <template #default="{ row }">
                            <el-button text size="small" type="danger" @click="handleRemoveMember(detail, row)">移除</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- 统计数据 -->
                <el-divider />
                <div class="section-header"><span>统计数据</span></div>
                <el-row :gutter="16">
                    <el-col :span="6" v-for="s in detailStats" :key="s.label">
                        <el-statistic :title="s.label" :value="s.value" />
                    </el-col>
                </el-row>
            </template>
            <template #footer>
                <el-button @click="showDetail = false">关闭</el-button>
            </template>
        </el-dialog>

        <!-- 添加成员对话框 -->
        <el-dialog v-model="showAddMember" title="添加成员" width="400px">
            <el-form label-width="80px">
                <el-form-item label="用户">
                    <el-select v-model="addMemberUserId" filterable remote
                        :remote-method="searchUsers"
                        :loading="searchingUsers"
                        placeholder="搜索用户（姓名/邮箱）"
                        style="width: 100%"
                    >
                        <el-option v-for="u in searchUserResults" :key="u.id" :label="`${u.name} (${u.email})`" :value="u.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="角色">
                    <el-select v-model="addMemberRole" style="width: 100%">
                        <el-option label="管理员" value="admin" />
                        <el-option label="成员" value="member" />
                        <el-option label="观察者" value="viewer" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAddMember = false">取消</el-button>
                <el-button type="primary" :loading="addingMember" @click="confirmAddMember">添加</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Plus } from '@element-plus/icons-vue';
import tenantApi from '@/api/tenant';
import apiClient from '@/api/client';

// ─── 统计 ───
const stats = reactive({
    total: 0, active: 0, inactive: 0, suspended: 0,
    total_users: 0, avg_users_per_tenant: 0,
});

const statCards = computed(() => [
    { label: '总租户', value: stats.total },
    { label: '活跃', value: stats.active },
    { label: '已停用', value: stats.inactive },
    { label: '已暂停', value: stats.suspended },
    { label: '总用户数', value: stats.total_users },
]);

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
        ElMessage.error('获取租户列表失败');
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

const formRules = {
    name: [{ required: true, message: '请输入租户名称', trigger: 'blur' }],
};

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
            ElMessage.success('租户更新成功');
        } else {
            await tenantApi.adminCreate(payload);
            ElMessage.success('租户创建成功');
        }
        showDialog.value = false;
        await fetchData();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '操作失败');
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
        { label: '用户数', value: detail.value.users_count || 0 },
        { label: '客户数', value: detail.value.customers_count || 0 },
        { label: 'License', value: detail.value.licenses_count || 0 },
        { label: '设备', value: detail.value.devices_count || 0 },
    ];
});

async function openDetail(row) {
    try {
        const res = await tenantApi.adminShow(row.id);
        detail.value = res.data?.data;
        showDetail.value = true;
    } catch {
        ElMessage.error('获取租户详情失败');
    }
}

// ─── 启用/停用 ───
async function handleToggleStatus(row) {
    const action = row.status === 'active' ? '停用' : '启用';
    try {
        await ElMessageBox.confirm(`确定要${action}租户「${row.name}」吗？`, '确认', { type: 'warning' });
        await tenantApi.adminToggleStatus(row.id);
        ElMessage.success(`租户已${action}`);
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
    if (!addMemberUserId.value) { ElMessage.warning('请选择用户'); return; }
    addingMember.value = true;
    try {
        await tenantApi.adminAddMember(detail.value.id, {
            user_id: addMemberUserId.value,
            role: addMemberRole.value,
        });
        ElMessage.success('成员已添加');
        showAddMember.value = false;
        addMemberUserId.value = null;
        // Refresh detail
        const res = await tenantApi.adminShow(detail.value.id);
        detail.value = res.data?.data;
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '添加失败');
    } finally {
        addingMember.value = false;
    }
}

async function handleUpdateMemberRole(tenant, member, newRole) {
    try {
        await tenantApi.adminUpdateMemberRole(tenant.id, member.id, { role: newRole });
        ElMessage.success('角色已更新');
        const res = await tenantApi.adminShow(tenant.id);
        detail.value = res.data?.data;
    } catch {
        ElMessage.error('更新角色失败');
    }
}

async function handleRemoveMember(tenant, member) {
    try {
        await ElMessageBox.confirm('确定要移除此成员吗？', '确认', { type: 'warning' });
        await tenantApi.adminRemoveMember(tenant.id, member.id);
        ElMessage.success('成员已移除');
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
