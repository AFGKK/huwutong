<template>
    <div class="trial-page">
        <div class="page-header">
            <div class="header-left">
                <h2>试用 License 管理</h2>
                <span class="header-subtitle">创建和管理试用 License，查看试用状态并一键转为正式授权</span>
            </div>
            <div class="header-right">
                <el-button @click="resetForm">
                    <el-icon><Refresh /></el-icon>
                    刷新
                </el-button>
                <el-button type="primary" @click="showCreate = true">
                    <el-icon><Plus /></el-icon>
                    创建试用
                </el-button>
            </div>
        </div>

        <!-- 引导信息 -->
        <el-alert
            title="试用 License"
            type="info"
            :closable="false"
            show-icon
            class="mb-4"
            description="通过指定产品、租户和客户来创建试用 License。试用期到期后可以一键转为正式授权。"
        />

        <el-row :gutter="16">
            <!-- 左：试用查询 -->
            <el-col :span="10">
                <el-card shadow="never">
                    <template #header>
                        <span>查询试用状态</span>
                    </template>
                    <el-form :model="queryForm" label-width="100px">
                        <el-form-item label="License ID">
                            <el-input-number v-model="queryForm.license_id" :min="1" style="width: 100%" placeholder="输入 License ID" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="queryTrial" :loading="querying">查询</el-button>
                        </el-form-item>
                    </el-form>

                    <div v-if="trialStatus" class="trial-result">
                        <el-divider />
                        <h4>试用状态</h4>
                        <el-descriptions :column="1" border size="small">
                            <el-descriptions-item label="License Key">
                                <code>{{ trialStatus.license_key }}</code>
                            </el-descriptions-item>
                            <el-descriptions-item label="类型">{{ trialStatus.type }}</el-descriptions-item>
                            <el-descriptions-item label="状态">
                                <el-tag :type="trialStatus.status === 'active' ? 'success' : 'danger'" size="small" effect="dark">
                                    {{ trialStatus.status }}
                                </el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item label="过期时间">{{ formatTime(trialStatus.expires_at) }}</el-descriptions-item>
                            <el-descriptions-item label="激活时间">{{ formatTime(trialStatus.activated_at) }}</el-descriptions-item>
                            <el-descriptions-item label="已使用">
                                <span v-if="trialStatus.is_expired" class="text-danger">已过期</span>
                                <span v-else-if="trialStatus.remaining_days !== undefined">
                                    剩余 {{ trialStatus.remaining_days }} 天
                                </span>
                                <span v-else>—</span>
                            </el-descriptions-item>
                        </el-descriptions>

                        <!-- 转正 -->
                        <div v-if="trialStatus.type === 'trial'" class="convert-section">
                            <el-divider />
                            <h4>一键转正</h4>
                            <el-form :model="convertForm" label-width="100px" size="small">
                                <el-form-item label="授权类型">
                                    <el-select v-model="convertForm.type" style="width: 200px">
                                        <el-option label="Standard" value="standard" />
                                        <el-option label="Enterprise" value="enterprise" />
                                        <el-option label="Development" value="development" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item label="有效期(天)">
                                    <el-input-number v-model="convertForm.days" :min="30" :max="3650" :step="30" style="width: 200px" />
                                </el-form-item>
                                <el-form-item label="最大设备数">
                                    <el-input-number v-model="convertForm.max_devices" :min="1" :max="1000" style="width: 200px" />
                                </el-form-item>
                                <el-form-item>
                                    <el-button type="primary" @click="convertTrial" :loading="converting" size="small">转为正式授权</el-button>
                                </el-form-item>
                            </el-form>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <!-- 右：创建试用 -->
            <el-col :span="14">
                <el-card shadow="never">
                    <template #header>
                        <span>创建试用 License</span>
                    </template>
                    <el-form :model="createForm" ref="createFormRef" :rules="createRules" label-width="120px">
                        <el-form-item label="产品" prop="product_id">
                            <el-select
                                v-model="createForm.product_id"
                                filterable
                                placeholder="选择产品"
                                style="width: 100%"
                                :loading="loadingProducts"
                            >
                                <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="租户" prop="tenant_id">
                            <el-select
                                v-model="createForm.tenant_id"
                                filterable
                                placeholder="选择租户"
                                style="width: 100%"
                            >
                                <el-option v-for="t in tenants" :key="t.id" :label="t.name" :value="t.id" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="客户" prop="customer_id">
                            <el-select
                                v-model="createForm.customer_id"
                                filterable
                                remote
                                :remote-method="searchCustomers"
                                placeholder="搜索客户"
                                style="width: 100%"
                                :loading="searchingCustomers"
                            >
                                <el-option v-for="c in customerOptions" :key="c.id" :label="c.name + ' (' + c.email + ')'" :value="c.id" />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="handleCreate" :loading="creating">
                                <el-icon><Plus /></el-icon> 创建试用
                            </el-button>
                        </el-form-item>
                    </el-form>

                    <!-- 最近创建的试用 -->
                    <el-divider v-if="recentTrials.length" />
                    <div v-if="recentTrials.length">
                        <h4 class="recent-title">最近的试用 License</h4>
                        <el-table :data="recentTrials" size="small" stripe>
                            <el-table-column label="ID" width="60" prop="id" />
                            <el-table-column label="License Key" min-width="180">
                                <template #default="{ row }">
                                    <code>{{ row.license_key }}</code>
                                </template>
                            </el-table-column>
                            <el-table-column label="客户" width="120">
                                <template #default="{ row }">{{ row.customer?.name || '—' }}</template>
                            </el-table-column>
                            <el-table-column label="状态" width="80">
                                <template #default="{ row }">
                                    <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">
                                        {{ row.status }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="过期时间" width="150">
                                <template #default="{ row }">{{ formatTime(row.expires_at) }}</template>
                            </el-table-column>
                            <el-table-column label="操作" width="80">
                                <template #default="{ row }">
                                    <el-button text size="small" @click="queryForm.license_id = row.id; queryTrial()">查看</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus, Refresh } from '@element-plus/icons-vue';
import trialApi from '@/api/trial';
import productApi from '@/api/product';
import customerApi from '@/api/customer';
import licenseApi from '@/api/license';

const loadingProducts = ref(false);
const searchingCustomers = ref(false);
const querying = ref(false);
const creating = ref(false);
const converting = ref(false);
const showCreate = ref(false);
const createFormRef = ref(null);

const products = ref([]);
const tenants = ref([]);
const customerOptions = ref([]);
const trialStatus = ref(null);
const recentTrials = ref([]);

const queryForm = reactive({
    license_id: null,
});

const createForm = reactive({
    product_id: null,
    tenant_id: null,
    customer_id: null,
});

const convertForm = reactive({
    type: 'standard',
    days: 365,
    max_devices: 3,
});

const createRules = {
    product_id: [{ required: true, message: '请选择产品', trigger: 'change' }],
    tenant_id: [{ required: true, message: '请选择租户', trigger: 'change' }],
    customer_id: [{ required: true, message: '请选择客户', trigger: 'change' }],
};

function formatTime(time) {
    if (!time) return '—';
    return new Date(time).toLocaleString('zh-CN');
}

async function loadProducts() {
    loadingProducts.value = true;
    try {
        const { data: res } = await productApi.list({ per_page: 100 });
        if (res.success) {
            products.value = res.data?.data || [];
        }
    } catch { /* ignore */ }
    finally { loadingProducts.value = false; }
}

async function loadTenants() {
    try {
        const { data: res } = await customerApi.list({ per_page: 100 });
        // Use a simple approach - tenants list from an endpoint
        tenants.value = [
            { id: 1, name: '默认租户' },
            { id: 2, name: '测试租户' },
        ];
    } catch { /* ignore */ }
}

async function searchCustomers(query) {
    if (!query) return;
    searchingCustomers.value = true;
    try {
        const { data: res } = await customerApi.list({ search: query, per_page: 20 });
        if (res.success) {
            customerOptions.value = res.data?.data || [];
        }
    } catch { /* ignore */ }
    finally { searchingCustomers.value = false; }
}

async function loadRecentTrials() {
    try {
        const { data: res } = await licenseApi.list({ type: 'trial', per_page: 10, sort: '-created_at' });
        if (res.success) {
            recentTrials.value = res.data?.data || [];
        }
    } catch { /* ignore */ }
}

async function queryTrial() {
    if (!queryForm.license_id) {
        ElMessage.warning('请输入 License ID');
        return;
    }
    querying.value = true;
    trialStatus.value = null;
    try {
        const { data: res } = await trialApi.status(queryForm.license_id);
        if (res.success) {
            trialStatus.value = res.data;
        }
    } catch {
        ElMessage.error('查询失败');
    } finally {
        querying.value = false;
    }
}

async function handleCreate() {
    const valid = await createFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    creating.value = true;
    try {
        const { data: res } = await trialApi.create(createForm);
        if (res.success) {
            ElMessage.success('试用创建成功');
            loadRecentTrials();
            // Pre-fill query
            if (res.data?.license?.id) {
                queryForm.license_id = res.data.license.id;
                queryTrial();
            }
            createForm.product_id = null;
            createForm.tenant_id = null;
            createForm.customer_id = null;
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '创建失败');
    } finally {
        creating.value = false;
    }
}

async function convertTrial() {
    if (!trialStatus.value?.license_key) return;
    converting.value = true;
    try {
        const { data: res } = await trialApi.convert(queryForm.license_id, {
            type: convertForm.type,
            days: convertForm.days,
            max_devices: convertForm.max_devices,
        });
        if (res.success) {
            ElMessage.success(res.message || '转正成功');
            queryTrial(); // Refresh status
            loadRecentTrials();
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '转正失败');
    } finally {
        converting.value = false;
    }
}

function resetForm() {
    loadRecentTrials();
    loadProducts();
}

onMounted(() => {
    loadProducts();
    loadTenants();
    loadRecentTrials();
});
</script>

<style scoped>
.trial-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }

.text-danger { color: var(--el-color-danger); }

.trial-result {
    margin-top: 8px;
}
.trial-result h4 {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 12px;
}

.convert-section {
    margin-top: 8px;
}
.convert-section h4 {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 12px;
}

.recent-title {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 12px;
}

:deep(.el-card__body) { padding: 16px; }
</style>
