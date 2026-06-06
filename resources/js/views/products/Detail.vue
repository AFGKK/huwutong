<template>
    <div class="product-detail-page" v-loading="loading">
        <div class="page-breadcrumb">
            <el-breadcrumb>
                <el-breadcrumb-item :to="{ path: '/products' }">产品管理</el-breadcrumb-item>
                <el-breadcrumb-item>产品详情</el-breadcrumb-item>
            </el-breadcrumb>
        </div>

        <div v-if="product" class="detail-content">
            <!-- 基本信息 -->
            <el-card shadow="never" class="info-card">
                <template #header>
                    <div class="card-header">
                        <span>基本信息</span>
                        <div class="header-actions">
                            <el-button size="small" @click="openEditDialog">编辑</el-button>
                            <el-button
                                v-if="product.is_active"
                                size="small"
                                type="warning"
                                @click="toggleActive(false)"
                            >
                                下架
                            </el-button>
                            <el-button v-else size="small" type="success" @click="toggleActive(true)">
                                上架
                            </el-button>
                        </div>
                    </div>
                </template>
                <el-descriptions :column="3" border>
                    <el-descriptions-item label="产品 ID" width="120">
                        <code>{{ product.id }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item label="产品名称">
                        {{ product.name }}
                    </el-descriptions-item>
                    <el-descriptions-item label="编码">
                        <code>{{ product.slug }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item label="版本">
                        {{ product.version || '-' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="product.is_active ? 'success' : 'info'" size="small">
                            {{ product.is_active ? '上架' : '下架' }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="License 数">
                        <el-tag type="primary" effect="plain" size="small">{{ product.licenses_count || 0 }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="描述" :span="3">
                        {{ product.description || '暂无描述' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="模块" :span="3">
                        <template v-if="product.modules && product.modules.length">
                            <el-tag
                                v-for="mod in product.modules"
                                :key="mod"
                                size="small"
                                effect="plain"
                                style="margin: 2px 4px 2px 0;"
                            >
                                {{ mod }}
                            </el-tag>
                        </template>
                        <span v-else class="text-muted">无模块配置</span>
                    </el-descriptions-item>
                    <el-descriptions-item label="创建时间">
                        {{ formatDate(product.created_at) }}
                    </el-descriptions-item>
                    <el-descriptions-item label="更新时间">
                        {{ formatDate(product.updated_at) }}
                    </el-descriptions-item>
                </el-descriptions>
            </el-card>

            <!-- Feature Flags -->
            <el-card shadow="never" class="section-card">
                <template #header>
                    <div class="card-header">
                        <span>Feature Flags ({{ assignedFeatures.length }})</span>
                        <el-button size="small" type="primary" @click="showFeatureDialog = true">
                            <el-icon><Setting /></el-icon>
                            管理
                        </el-button>
                    </div>
                </template>
                <div v-if="assignedFeatures.length > 0" class="feature-list">
                    <el-tag
                        v-for="f in assignedFeatures"
                        :key="f.id"
                        size="default"
                        effect="plain"
                        type="primary"
                        style="margin: 4px 8px 4px 0;"
                    >
                        <el-icon style="margin-right: 4px;"><Flag /></el-icon>
                        {{ f.name }}
                        <code style="margin-left: 4px; font-size: 11px;">{{ f.key }}</code>
                    </el-tag>
                </div>
                <div v-else class="empty-state">
                    <el-empty :image-size="60" description="尚未分配 Feature Flag" />
                </div>
            </el-card>

            <!-- 最近的 License -->
            <el-card shadow="never" class="section-card">
                <template #header>
                    <div class="card-header">
                        <span>最近 License</span>
                        <el-button size="small" type="primary" @click="$router.push(`/licenses?product_id=${product.id}`)">
                            查看全部
                        </el-button>
                    </div>
                </template>
                <el-table :data="recentLicenses" stripe size="small">
                    <el-table-column label="License Key" min-width="220">
                        <template #default="{ row }">
                            <el-link type="primary" @click="$router.push(`/licenses/${row.id}`)">
                                <code>{{ row.license_key.substring(0, 20) }}...</code>
                            </el-link>
                        </template>
                    </el-table-column>
                    <el-table-column label="客户" width="160">
                        <template #default="{ row }">
                            {{ row.customer?.user?.name || '-' }}
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="90" prop="status">
                        <template #default="{ row }">
                            <el-tag :type="licenseStatusType(row.status)" size="small">
                                {{ licenseStatusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="过期时间" width="170" prop="expires_at">
                        <template #default="{ row }">
                            {{ formatDate(row.expires_at) }}
                        </template>
                    </el-table-column>
                    <el-table-column label="创建时间" width="170" prop="created_at">
                        <template #default="{ row }">
                            {{ formatDate(row.created_at) }}
                        </template>
                    </el-table-column>
                </el-table>
            </el-card>
        </div>

        <!-- 编辑 Dialog -->
        <el-dialog
            v-model="dialogVisible"
            title="编辑产品"
            width="600px"
            :close-on-click-modal="false"
        >
            <el-form
                ref="formRef"
                :model="form"
                :rules="formRules"
                label-width="100px"
                label-position="right"
            >
                <el-form-item label="产品名称" prop="name">
                    <el-input v-model="form.name" />
                </el-form-item>
                <el-form-item label="编码" prop="slug">
                    <el-input v-model="form.slug" />
                </el-form-item>
                <el-form-item label="版本" prop="version">
                    <el-input v-model="form.version" style="width: 200px" />
                </el-form-item>
                <el-form-item label="描述" prop="description">
                    <el-input v-model="form.description" type="textarea" :rows="3" />
                </el-form-item>
                <el-form-item label="模块" prop="modules">
                    <el-select
                        v-model="form.modules"
                        multiple
                        allow-create
                        filterable
                        default-first-option
                        placeholder="输入后回车添加"
                        style="width: 100%"
                    />
                </el-form-item>
                <el-form-item label="上架">
                    <el-switch v-model="form.is_active" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="submitForm">保存</el-button>
            </template>
        </el-dialog>

        <!-- Feature Flags 管理 Dialog -->
        <el-dialog
            v-model="showFeatureDialog"
            title="管理 Feature Flags"
            width="550px"
            :close-on-click-modal="false"
        >
            <div v-loading="featuresLoading">
                <el-empty v-if="availableFeatures.length === 0" :image-size="60" description="暂无可用 Feature Flag" />
                <el-checkbox-group v-else v-model="selectedFeatureIds" class="feature-checkbox-group">
                    <el-checkbox
                        v-for="f in availableFeatures"
                        :key="f.id"
                        :label="f.id"
                        class="feature-checkbox"
                    >
                        <div class="feature-item">
                            <span class="feature-name">{{ f.name }}</span>
                            <code class="feature-key">{{ f.key }}</code>
                            <span v-if="f.description" class="feature-desc">{{ f.description }}</span>
                        </div>
                    </el-checkbox>
                </el-checkbox-group>
            </div>
            <template #footer>
                <el-button @click="showFeatureDialog = false">取消</el-button>
                <el-button type="primary" :loading="featureSubmitting" @click="submitFeatures">
                    保存
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Setting, Flag } from '@element-plus/icons-vue';
import productApi from '@/api/product';

const route = useRoute();
const router = useRouter();
const productId = Number(route.params.id);

const loading = ref(false);
const product = ref(null);
const recentLicenses = ref([]);

// Edit dialog
const dialogVisible = ref(false);
const submitting = ref(false);
const formRef = ref(null);
const form = reactive({
    name: '', slug: '', version: '', description: '', modules: [], is_active: true,
});
const formRules = {
    name: [{ required: true, message: '请输入产品名称', trigger: 'blur' }],
    slug: [{ required: true, message: '请输入产品编码', trigger: 'blur' }],
};

// Feature flags
const showFeatureDialog = ref(false);
const featuresLoading = ref(false);
const featureSubmitting = ref(false);
const assignedFeatures = ref([]);
const availableFeatures = ref([]);
const selectedFeatureIds = ref([]);

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

function licenseStatusType(status) {
    const map = { active: 'success', expired: 'danger', suspended: 'warning', revoked: 'info', blacklisted: 'danger' };
    return map[status] || 'info';
}

function licenseStatusLabel(status) {
    const map = { active: '启用', expired: '过期', suspended: '暂停', revoked: '撤销', frozen: '冻结', blacklisted: '黑名单' };
    return map[status] || status;
}

async function loadDetail() {
    loading.value = true;
    try {
        const { data: res } = await productApi.show(productId);
        if (res.success) {
            product.value = res.data.product;
            recentLicenses.value = res.data.recent_licenses || [];
            assignedFeatures.value = res.data.product.feature_flags || [];
        }
    } catch {
        ElMessage.error('获取产品详情失败');
    } finally {
        loading.value = false;
    }
}

async function loadFeatures() {
    featuresLoading.value = true;
    try {
        const { data: res } = await productApi.features(productId);
        if (res.success) {
            assignedFeatures.value = res.data.assigned || [];
            availableFeatures.value = res.data.available || [];
            selectedFeatureIds.value = assignedFeatures.value.map(f => f.id);
        }
    } catch {
        // ignore
    } finally {
        featuresLoading.value = false;
    }
}

// 编辑
function openEditDialog() {
    if (!product.value) return;
    form.name = product.value.name;
    form.slug = product.value.slug;
    form.version = product.value.version || '';
    form.description = product.value.description || '';
    form.modules = product.value.modules ? [...product.value.modules] : [];
    form.is_active = Boolean(product.value.is_active);
    dialogVisible.value = true;
}

async function submitForm() {
    const valid = await formRef.value.validate().catch(() => false);
    if (!valid) return;

    submitting.value = true;
    try {
        await productApi.update(productId, { ...form, is_active: form.is_active ? 1 : 0 });
        ElMessage.success('产品更新成功');
        dialogVisible.value = false;
        loadDetail();
    } catch {
        // handled by interceptor
    } finally {
        submitting.value = false;
    }
}

// 上架/下架
async function toggleActive(active) {
    const action = active ? '上架' : '下架';
    try {
        await ElMessageBox.confirm(`确定要${action}该产品吗？`, '确认操作', {
            confirmButtonText: '确定', cancelButtonText: '取消', type: active ? 'info' : 'warning',
        });
        await productApi.update(productId, { is_active: active ? 1 : 0 });
        ElMessage.success(`${action}成功`);
        loadDetail();
    } catch { /* cancelled */ }
}

// Feature Flags
async function submitFeatures() {
    featureSubmitting.value = true;
    try {
        await productApi.assignFeatures(productId, selectedFeatureIds.value);
        ElMessage.success('Feature Flags 更新成功');
        showFeatureDialog.value = false;
        loadDetail();
    } catch {
        // handled by interceptor
    } finally {
        featureSubmitting.value = false;
    }
}

// 打开 Feature Dialog 时加载数据
onMounted(() => {
    loadDetail();
});
</script>

<style scoped>
.product-detail-page { padding: 20px; }

.page-breadcrumb { margin-bottom: 20px; }

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
}
.header-actions { display: flex; gap: 8px; }

.info-card { margin-bottom: 20px; }
.section-card { margin-bottom: 20px; }

.text-muted { color: var(--el-text-color-placeholder); }

code {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 12px;
    background: var(--el-fill-color-light);
    padding: 2px 6px;
    border-radius: 3px;
}

.feature-list {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
}

.empty-state {
    padding: 20px 0;
}

.feature-checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.feature-checkbox {
    margin-right: 0 !important;
    padding: 8px 12px;
    border: 1px solid var(--el-border-color-light);
    border-radius: 6px;
    transition: all 0.2s;
}
.feature-checkbox:hover {
    border-color: var(--el-color-primary);
    background: var(--el-color-primary-light-9);
}
.feature-item {
    display: flex;
    flex-direction: column;
    margin-left: 8px;
}
.feature-name {
    font-weight: 500;
    font-size: 14px;
}
.feature-key {
    font-size: 11px;
    margin-top: 2px;
}
.feature-desc {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-top: 2px;
}
</style>
