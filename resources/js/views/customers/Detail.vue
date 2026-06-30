<template>
    <div class="customer-detail-page" v-loading="loading">
        <!-- 顶部导航 -->
        <div class="page-breadcrumb">
            <el-breadcrumb>
                <el-breadcrumb-item :to="{ path: '/customers' }">客户管理</el-breadcrumb-item>
                <el-breadcrumb-item>客户详情</el-breadcrumb-item>
            </el-breadcrumb>
        </div>

        <div v-if="customer" class="detail-content">
            <!-- 基本信息卡片 -->
            <el-card shadow="never" class="info-card">
                <template #header>
                    <div class="card-header">
                        <div class="card-header-left" style="display: flex; align-items: center; gap: 12px;">
                            <el-avatar :size="48" :src="customer.user?.avatar_url" shape="square">
                                <span class="avatar-initial">{{ (customer.user?.name || '?').charAt(0).toUpperCase() }}</span>
                                <template #error>{{ (customer.user?.name || '?').charAt(0).toUpperCase() }}</template>
                            </el-avatar>
                            <span>基本信息</span>
                        </div>
                        <div class="header-actions">
                            <el-button size="small" @click="openEditDialog">编辑</el-button>
                            <el-button
                                v-if="customer.status === 'active'"
                                size="small"
                                type="warning"
                                @click="handleStatusChange('inactive')"
                            >
                                停用
                            </el-button>
                            <el-button
                                v-if="customer.status !== 'active'"
                                size="small"
                                type="success"
                                @click="handleStatusChange('active')"
                            >
                                启用
                            </el-button>
                        </div>
                    </div>
                </template>
                <el-descriptions :column="3" border>
                    <el-descriptions-item label="客户 ID" width="120">
                        <code>{{ customer.id }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item label="关联用户">
                        <template v-if="customer.user">
                            <div>{{ customer.user.name }}</div>
                            <div style="font-size: 12px; color: var(--el-text-color-secondary);">
                                {{ customer.user.email }}
                                <template v-if="customer.user.email && customer.user.phone"> · </template>
                                {{ customer.user.phone }}
                            </div>
                        </template>
                        <span v-else class="text-muted">未关联</span>
                    </el-descriptions-item>
                    <el-descriptions-item label="类型">
                        <el-tag :type="customer.type === 'enterprise' ? 'warning' : 'info'" size="small">
                            {{ customer.type === 'enterprise' ? '企业' : '个人' }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="等级">
                        <el-tag :type="levelTagType(customer.level)" size="small">
                            {{ levelLabel(customer.level) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="customer.status === 'active' ? 'success' : customer.status === 'suspended' ? 'danger' : 'info'" size="small">
                            {{ statusLabel(customer.status) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="License 数">
                        <el-tag type="primary" effect="plain" size="small">{{ customer.licenses_count || 0 }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="创建时间">
                        {{ formatDate(customer.created_at) }}
                    </el-descriptions-item>
                    <el-descriptions-item label="更新时间">
                        {{ formatDate(customer.updated_at) }}
                    </el-descriptions-item>
                </el-descriptions>
            </el-card>

            <!-- 标签 -->
            <el-card shadow="never" class="section-card">
                <template #header>
                    <span>标签</span>
                </template>
                <TagSelector
                    taggable-type="customer"
                    :taggable-id="customer.id"
                    :tags="customer.tags || []"
                />
            </el-card>

            <!-- License 列表（Tabs 切换） -->
            <el-card shadow="never" class="section-card">
                <template #header>
                    <div class="card-header">
                        <span>关联 License</span>
                        <el-button
                            size="small"
                            type="primary"
                            @click="$router.push(`/licenses?customer_id=${customer.id}`)"
                        >
                            查看全部
                        </el-button>
                    </div>
                </template>
                <el-table
                    :data="licenses"
                    v-loading="licensesLoading"
                    stripe
                    size="small"
                    @sort-change="handleLicenseSort"
                >
                    <el-table-column label="License Key" min-width="220">
                        <template #default="{ row }">
                            <el-link type="primary" @click="$router.push(`/licenses/${row.id}`)">
                                <code>{{ row.license_key.substring(0, 20) }}...</code>
                            </el-link>
                        </template>
                    </el-table-column>
                    <el-table-column label="产品" width="150" prop="product.name">
                        <template #default="{ row }">
                            {{ row.product?.name || '-' }}
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="90" prop="status" sortable="custom">
                        <template #default="{ row }">
                            <el-tag :type="licenseStatusType(row.status)" size="small">
                                {{ licenseStatusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="过期时间" width="170" prop="expires_at" sortable="custom">
                        <template #default="{ row }">
                            {{ formatDate(row.expires_at) }}
                        </template>
                    </el-table-column>
                    <el-table-column label="创建时间" width="170" prop="created_at" sortable="custom">
                        <template #default="{ row }">
                            {{ formatDate(row.created_at) }}
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="100" fixed="right">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="$router.push(`/licenses/${row.id}`)">
                                详情
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="pagination-wrapper" v-if="licenseTotal > 0">
                    <el-pagination
                        v-model:current-page="licensePage"
                        v-model:page-size="licensePerPage"
                        :total="licenseTotal"
                        size="small"
                        layout="total, prev, pager, next"
                        @current-change="loadLicenses"
                    />
                </div>
            </el-card>

            <!-- 关联设备 -->
            <el-card shadow="never" class="section-card" v-if="devices.length > 0">
                <template #header>
                    <div class="card-header">
                        <span>关联设备 ({{ devices.length }})</span>
                    </div>
                </template>
                <el-table :data="devices" stripe size="small">
                    <el-table-column label="设备指纹" min-width="200">
                        <template #default="{ row }">
                            <code class="fingerprint">{{ row.fingerprint.substring(0, 24) }}...</code>
                        </template>
                    </el-table-column>
                    <el-table-column label="主机名" width="150" prop="hostname">
                        <template #default="{ row }">
                            {{ row.hostname || '-' }}
                        </template>
                    </el-table-column>
                    <el-table-column label="平台" width="100" prop="platform" />
                    <el-table-column label="信任评分" width="100" prop="trust_score">
                        <template #default="{ row }">
                            <el-tag
                                :type="(row.trust_score || 0) >= 80 ? 'success' : (row.trust_score || 0) >= 50 ? 'warning' : 'danger'"
                                size="small"
                            >
                                {{ row.trust_score || 0 }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="最后激活" width="170" prop="last_activated_at">
                        <template #default="{ row }">
                            {{ formatDate(row.last_activated_at) }}
                        </template>
                    </el-table-column>
                    <el-table-column label="所属 License" width="220">
                        <template #default="{ row }">
                            <el-link
                                v-if="row.license_id"
                                type="primary"
                                @click="$router.push(`/licenses/${row.license_id}`)"
                            >
                                <code>#{{ row.license_id }}</code>
                            </el-link>
                            <span v-else class="text-muted">-</span>
                        </template>
                    </el-table-column>
                </el-table>
            </el-card>
        </div>

        <!-- 编辑 Dialog -->
        <el-dialog
            v-model="dialogVisible"
            title="编辑客户"
            width="500px"
            :close-on-click-modal="false"
        >
            <el-form
                ref="formRef"
                :model="form"
                :rules="formRules"
                label-width="100px"
                label-position="right"
            >
                <el-form-item label="客户类型" prop="type">
                    <el-radio-group v-model="form.type">
                        <el-radio value="individual">个人</el-radio>
                        <el-radio value="enterprise">企业</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="等级" prop="level">
                    <el-select v-model="form.level" style="width: 100%">
                        <el-option label="Free" value="free" />
                        <el-option label="Pro" value="pro" />
                        <el-option label="Enterprise" value="enterprise" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态" prop="status">
                    <el-select v-model="form.status" style="width: 100%">
                        <el-option label="启用" value="active" />
                        <el-option label="停用" value="inactive" />
                        <el-option label="暂停" value="suspended" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="submitForm">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import customerApi from '@/api/customer';
import TagSelector from '@/components/TagSelector.vue';

const route = useRoute();
const router = useRouter();
const customerId = Number(route.params.id);

const loading = ref(false);
const customer = ref(null);
const devices = ref([]);

// License 分页
const licenses = ref([]);
const licensesLoading = ref(false);
const licenseTotal = ref(0);
const licensePage = ref(1);
const licensePerPage = ref(10);
const licenseSort = ref('-created_at');

// Dialog
const dialogVisible = ref(false);
const submitting = ref(false);
const formRef = ref(null);

const form = reactive({
    type: 'individual',
    level: 'free',
    status: 'active',
});

const formRules = {
    type: [{ required: true, message: '请选择客户类型', trigger: 'change' }],
    level: [{ required: true, message: '请选择等级', trigger: 'change' }],
    status: [{ required: true, message: '请选择状态', trigger: 'change' }],
};

function levelTagType(level) {
    const map = { free: 'info', pro: 'primary', enterprise: 'warning' };
    return map[level] || 'info';
}

function levelLabel(level) {
    const map = { free: 'Free', pro: 'Pro', enterprise: 'Enterprise' };
    return map[level] || level;
}

function statusLabel(status) {
    const map = { active: '启用', inactive: '停用', suspended: '暂停' };
    return map[status] || status;
}

function licenseStatusType(status) {
    const map = { active: 'success', expired: 'danger', suspended: 'warning', revoked: 'info', blacklisted: 'danger' };
    return map[status] || 'info';
}

function licenseStatusLabel(status) {
    const map = { active: '启用', expired: '过期', suspended: '暂停', revoked: '撤销', frozen: '冻结', blacklisted: '黑名单' };
    return map[status] || status;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

async function loadDetail() {
    loading.value = true;
    try {
        const { data: res } = await customerApi.show(customerId);
        if (res.success) {
            customer.value = res.data.customer;
            devices.value = res.data.devices || [];
        }
    } catch {
        ElMessage.error('获取客户详情失败');
    } finally {
        loading.value = false;
    }
}

async function loadLicenses() {
    licensesLoading.value = true;
    try {
        const params = {
            page: licensePage.value,
            per_page: licensePerPage.value,
            sort: licenseSort.value,
        };
        const { data: res } = await customerApi.licenses(customerId, params);
        licenses.value = res.data?.data || [];
        licenseTotal.value = res.data?.total || 0;
    } catch {
        licenses.value = [];
    } finally {
        licensesLoading.value = false;
    }
}

function handleLicenseSort({ prop, order }) {
    if (!order) {
        licenseSort.value = '-created_at';
    } else {
        licenseSort.value = (order === 'desc' ? '-' : '') + (prop || 'created_at');
    }
    loadLicenses();
}

// 编辑
function openEditDialog() {
    if (!customer.value) return;
    form.type = customer.value.type;
    form.level = customer.value.level;
    form.status = customer.value.status;
    dialogVisible.value = true;
}

async function submitForm() {
    const valid = await formRef.value.validate().catch(() => false);
    if (!valid) return;

    submitting.value = true;
    try {
        await customerApi.update(customerId, { ...form });
        ElMessage.success('客户更新成功');
        dialogVisible.value = false;
        loadDetail();
    } catch {
        // handled by interceptor
    } finally {
        submitting.value = false;
    }
}

// 状态变更
async function handleStatusChange(newStatus) {
    const action = newStatus === 'active' ? '启用' : '停用';
    try {
        await ElMessageBox.confirm(
            `确定要${action}该客户吗？`,
            '确认操作',
            { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
        );
        await customerApi.update(customerId, { status: newStatus });
        ElMessage.success(`${action}成功`);
        loadDetail();
    } catch {
        // cancelled or error
    }
}

onMounted(() => {
    loadDetail();
    loadLicenses();
});
</script>

<style scoped>
.customer-detail-page {
    padding: 20px;
}

.page-breadcrumb {
    margin-bottom: 20px;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
}
.header-actions {
    display: flex;
    gap: 8px;
}

.info-card {
    margin-bottom: 20px;
}

.section-card {
    margin-bottom: 20px;
}

.text-muted {
    color: var(--el-text-color-placeholder);
}

code {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 12px;
    background: var(--el-fill-color-light);
    padding: 2px 6px;
    border-radius: 3px;
}

.fingerprint {
    font-size: 11px;
}

.pagination-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-top: 12px;
}
</style>
