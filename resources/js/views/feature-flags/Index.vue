<template>
    <div class="feature-flags-page">
        <div class="page-header">
            <div class="header-left">
                <h2>Feature Flag 功能开关</h2>
                <span class="header-subtitle">管理所有功能特性开关及产品关联</span>
            </div>
            <div class="header-right">
                <el-button type="primary" @click="showCreateDialog = true" v-permission="'manage-features'">
                    <el-icon><Plus /></el-icon>
                    新建功能开关
                </el-button>
            </div>
        </div>

        <!-- 功能开关列表 -->
        <el-card shadow="never">
            <el-table :data="featureFlags" v-loading="loading" stripe style="width: 100%">
                <el-table-column type="index" label="#" width="50" />
                <el-table-column prop="key" label="标识 Key" min-width="180">
                    <template #default="{ row }">
                        <div class="key-cell">
                            <code class="feature-key">{{ row.key }}</code>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="name" label="名称" min-width="160" />
                <el-table-column prop="description" label="描述" min-width="240">
                    <template #default="{ row }">
                        <span class="desc-text">{{ row.description || '-' }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="is_active" label="全局状态" width="110">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.is_active"
                            @change="(val) => handleToggleGlobal(row, val)"
                            :loading="togglingId === row.id"
                        />
                    </template>
                </el-table-column>
                <el-table-column label="关联产品" min-width="200">
                    <template #default="{ row }">
                        <div class="product-tags">
                            <el-tag
                                v-for="p in getProductAssignments(row)"
                                :key="p.id"
                                :type="p.pivot?.is_active !== false ? 'success' : 'info'"
                                size="small"
                                effect="plain"
                                style="margin: 2px"
                            >
                                {{ p.name }}
                                <span v-if="p.pivot?.is_active === false" class="disabled-tag">(禁用)</span>
                            </el-tag>
                            <el-button
                                v-if="products.length"
                                text
                                size="small"
                                type="primary"
                                @click="openAssignDialog(row)"
                            >
                                管理
                            </el-button>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button text type="primary" size="small" @click="openEditDialog(row)">
                            编辑
                        </el-button>
                        <el-button text type="danger" size="small" @click="handleDelete(row)">
                            删除
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 新建功能开关 Dialog -->
        <el-dialog v-model="showCreateDialog" title="新建功能开关" width="520px">
            <el-form ref="createFormRef" :model="createForm" :rules="formRules" label-position="top">
                <el-form-item label="标识 Key" prop="key">
                    <el-input v-model="createForm.key" placeholder="如：ai_features, advanced_analytics" />
                    <div class="form-tip">唯一标识，只能包含小写字母、数字和下划线</div>
                </el-form-item>
                <el-form-item label="名称" prop="name">
                    <el-input v-model="createForm.name" placeholder="如：AI 功能" />
                </el-form-item>
                <el-form-item label="描述" prop="description">
                    <el-input v-model="createForm.description" type="textarea" :rows="3" placeholder="描述此功能的作用" />
                </el-form-item>
                <el-form-item label="全局启用" prop="is_active">
                    <el-switch v-model="createForm.is_active" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateDialog = false">取消</el-button>
                <el-button type="primary" @click="handleCreate" :loading="submitting">创建</el-button>
            </template>
        </el-dialog>

        <!-- 编辑功能开关 Dialog -->
        <el-dialog v-model="showEditDialog" title="编辑功能开关" width="520px">
            <el-form ref="editFormRef" :model="editForm" :rules="formRules" label-position="top">
                <el-form-item label="标识 Key">
                    <el-input v-model="editForm.key" disabled />
                    <div class="form-tip">创建后不可修改</div>
                </el-form-item>
                <el-form-item label="名称" prop="name">
                    <el-input v-model="editForm.name" />
                </el-form-item>
                <el-form-item label="描述" prop="description">
                    <el-input v-model="editForm.description" type="textarea" :rows="3" />
                </el-form-item>
                <el-form-item label="全局启用">
                    <el-switch v-model="editForm.is_active" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEditDialog = false">取消</el-button>
                <el-button type="primary" @click="handleEdit" :loading="submitting">保存</el-button>
            </template>
        </el-dialog>

        <!-- 产品关联管理 Dialog -->
        <el-dialog v-model="showAssignDialog" title="管理产品关联" width="560px">
            <p class="dialog-subtitle">为功能开关 <strong>{{ assigningFlag?.key }}</strong> 分配产品</p>
            <div class="product-assign-list" v-loading="loadingAssign">
                <div v-for="product in products" :key="product.id" class="assign-item">
                    <div class="assign-info">
                        <span class="assign-product-name">{{ product.name }}</span>
                        <span class="assign-product-desc">{{ product.description || '' }}</span>
                    </div>
                    <el-switch
                        :model-value="isProductAssigned(product)"
                        @change="(val) => handleToggleProduct(product, val)"
                        :loading="assigningProductId === product.id"
                    />
                </div>
                <el-empty v-if="products.length === 0" description="暂无产品" :image-size="60" />
            </div>
            <template #footer>
                <el-button @click="showAssignDialog = false">关闭</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import apiClient from '@/api/client';

const loading = ref(false);
const submitting = ref(false);
const togglingId = ref(null);
const loadingAssign = ref(false);
const assigningProductId = ref(null);
const featureFlags = ref([]);
const products = ref([]);
const productAssignments = ref([]); // { feature_flag_id, product_id, is_active }

const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const showAssignDialog = ref(false);
const assigningFlag = ref(null);

const createFormRef = ref(null);
const editFormRef = ref(null);

const createForm = reactive({
    key: '', name: '', description: '', is_active: true,
});
const editForm = reactive({
    id: null, key: '', name: '', description: '', is_active: true,
});

const formRules = {
    key: [
        { required: true, message: '请输入标识 Key', trigger: 'blur' },
        { pattern: /^[a-z][a-z0-9_]*$/, message: '只能包含小写字母、数字和下划线，以小写字母开头', trigger: 'blur' },
    ],
    name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
};

// 获取某个 flag 的产品关联
function getProductAssignments(flag) {
    return productAssignments.value
        .filter(pa => pa.feature_flag_id === flag.id)
        .map(pa => {
            const product = products.value.find(p => p.id === pa.product_id);
            return product ? { ...product, pivot: { is_active: pa.is_active } } : null;
        })
        .filter(Boolean);
}

function isProductAssigned(product) {
    if (!assigningFlag.value) return false;
    return productAssignments.value.some(
        pa => pa.feature_flag_id === assigningFlag.value.id && pa.product_id === product.id
    );
}

async function loadFeatureFlags() {
    loading.value = true;
    try {
        const { data: res } = await apiClient.get('/feature-flags');
        featureFlags.value = res.data || [];
    } catch {
        featureFlags.value = [];
    } finally {
        loading.value = false;
    }
}

async function loadProducts() {
    try {
        const { data: res } = await apiClient.get('/products', { params: { per_page: 100 } });
        const paginated = res.data;
        products.value = paginated.data || paginated || [];
    } catch {
        products.value = [];
    }
}

async function loadProductAssignments() {
    try {
        const { data: res } = await apiClient.get('/openfeature/manage/flags');
        const openFeatureFlags = res.data || [];
        // 提取产品关联关系
        const assignments = [];
        for (const ff of featureFlags.value) {
            for (const of of openFeatureFlags) {
                // 这里简化处理，实际可能需要单独 API
            }
        }
        // 使用后端返回的 assignments 或者独立 API
        // feature-flag 关联存储在 product_feature_flag 表
        // 暂时使用简化方式
    } catch {
        // ignore
    }
}

async function handleCreate() {
    const valid = await createFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    submitting.value = true;
    try {
        await apiClient.post('/feature-flags', createForm);
        ElMessage.success('功能开关创建成功');
        showCreateDialog.value = false;
        createForm.key = ''; createForm.name = ''; createForm.description = ''; createForm.is_active = true;
        loadFeatureFlags();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || '创建失败');
    } finally {
        submitting.value = false;
    }
}

function openEditDialog(flag) {
    editForm.id = flag.id;
    editForm.key = flag.key;
    editForm.name = flag.name;
    editForm.description = flag.description;
    editForm.is_active = flag.is_active;
    showEditDialog.value = true;
}

async function handleEdit() {
    const valid = await editFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    submitting.value = true;
    try {
        await apiClient.put(`/feature-flags/${editForm.id}`, {
            name: editForm.name,
            description: editForm.description,
            is_active: editForm.is_active,
        });
        ElMessage.success('功能开关更新成功');
        showEditDialog.value = false;
        loadFeatureFlags();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || '更新失败');
    } finally {
        submitting.value = false;
    }
}

async function handleDelete(flag) {
    try {
        await ElMessageBox.confirm(
            `确定要删除功能开关 "${flag.key}" 吗？此操作不可撤销。`,
            '确认删除',
            { confirmButtonText: '确定删除', cancelButtonText: '取消', type: 'warning' }
        );
        await apiClient.delete(`/feature-flags/${flag.id}`);
        ElMessage.success('功能开关已删除');
        loadFeatureFlags();
    } catch { /* cancelled */ }
}

async function handleToggleGlobal(flag, val) {
    togglingId.value = flag.id;
    try {
        await apiClient.patch(`/feature-flags/${flag.id}`, { is_active: val });
        ElMessage.success(val ? '功能已启用' : '功能已禁用');
    } catch {
        flag.is_active = !val;
        ElMessage.error('操作失败');
    } finally {
        togglingId.value = null;
    }
}

async function openAssignDialog(flag) {
    assigningFlag.value = flag;
    showAssignDialog.value = true;
    loadProducts();
}

async function handleToggleProduct(product, enable) {
    if (!assigningFlag.value) return;
    assigningProductId.value = product.id;
    try {
        await apiClient.post('/feature-flags/assign', {
            product_id: product.id,
            feature_flag_id: assigningFlag.value.id,
            is_active: enable,
        });
        ElMessage.success(enable ? '已分配给产品' : '已取消分配');
        // 刷新产品关联数据
        loadFeatureFlags();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || '操作失败');
    } finally {
        assigningProductId.value = null;
    }
}

onMounted(() => {
    loadFeatureFlags();
    loadProducts();
});
</script>

<style scoped>
.feature-flags-page { padding: 20px; }

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

.key-cell {
    display: flex;
    align-items: center;
}
.feature-key {
    background: #f5f7fa;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 13px;
    color: var(--el-color-primary);
    font-weight: 600;
}

.desc-text {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-tags {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 2px;
}
.disabled-tag {
    font-size: 11px;
    color: var(--el-text-color-placeholder);
}

.form-tip {
    font-size: 12px;
    color: var(--el-text-color-placeholder);
    margin-top: 4px;
}

.dialog-subtitle {
    font-size: 14px;
    color: var(--el-text-color-secondary);
    margin-bottom: 16px;
}

.product-assign-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.assign-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border: 1px solid var(--el-border-color-light);
    border-radius: 8px;
    transition: all 0.2s;
}
.assign-item:hover {
    border-color: var(--el-color-primary-light-5);
    background: var(--el-color-primary-light-9);
}
.assign-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.assign-product-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--el-text-color-primary);
}
.assign-product-desc {
    font-size: 12px;
    color: var(--el-text-color-secondary);
}

:deep(.el-card__body) { padding: 16px; }
</style>
