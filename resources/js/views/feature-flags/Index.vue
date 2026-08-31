<template>
    <div class="feature-flags-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('feature_flags_page.title') }}</h2>
                <span class="header-subtitle">{{ t('feature_flags_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button type="primary" @click="showCreateDialog = true" v-permission="'manage-features'">
                    <el-icon><Plus /></el-icon>
                    {{ t('feature_flags_page.create_btn') }}
                </el-button>
            </div>
        </div>

        <!-- 功能开关列表 -->
        <el-card shadow="never">
            <el-table :data="featureFlags" v-loading="loading" stripe style="width: 100%">
                <el-table-column type="index" label="#" width="50" />
                <el-table-column prop="key" :label="t('feature_flags_page.col_key')" min-width="180">
                    <template #default="{ row }">
                        <div class="key-cell">
                            <code class="feature-key">{{ row.key }}</code>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="name" :label="t('feature_flags_page.col_name')" min-width="160" />
                <el-table-column prop="description" :label="t('feature_flags_page.col_description')" min-width="240">
                    <template #default="{ row }">
                        <span class="desc-text">{{ row.description || '-' }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="is_active" :label="t('feature_flags_page.col_global_status')" width="110">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.is_active"
                            @change="(val) => handleToggleGlobal(row, val)"
                            :loading="togglingId === row.id"
                        />
                    </template>
                </el-table-column>
                <el-table-column :label="t('feature_flags_page.col_products')" min-width="200">
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
                                <span v-if="p.pivot?.is_active === false" class="disabled-tag">{{ t('feature_flags_page.disabled_tag') }}</span>
                            </el-tag>
                            <el-button
                                v-if="products.length"
                                text
                                size="small"
                                type="primary"
                                @click="openAssignDialog(row)"
                            >
                                {{ t('feature_flags_page.manage') }}
                            </el-button>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('feature_flags_page.col_actions')" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button text type="primary" size="small" @click="openEditDialog(row)">
                            {{ t('actions.edit') }}
                        </el-button>
                        <el-button text type="danger" size="small" @click="handleDelete(row)">
                            {{ t('actions.delete') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 新建功能开关 Dialog -->
        <el-dialog v-model="showCreateDialog" :title="t('feature_flags_page.create_dialog_title')" width="520px">
            <el-form ref="createFormRef" :model="createForm" :rules="formRules" label-position="top">
                <el-form-item :label="t('feature_flags_page.key_label')" prop="key">
                    <el-input v-model="createForm.key" :placeholder="t('feature_flags_page.key_ph')" />
                    <div class="form-tip">{{ t('feature_flags_page.key_tip') }}</div>
                </el-form-item>
                <el-form-item :label="t('feature_flags_page.name_label')" prop="name">
                    <el-input v-model="createForm.name" :placeholder="t('feature_flags_page.name_ph')" />
                </el-form-item>
                <el-form-item :label="t('feature_flags_page.description_label')" prop="description">
                    <el-input v-model="createForm.description" type="textarea" :rows="3" :placeholder="t('feature_flags_page.description_ph')" />
                </el-form-item>
                <el-form-item :label="t('feature_flags_page.global_enable_label')" prop="is_active">
                    <el-switch v-model="createForm.is_active" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleCreate" :loading="submitting">{{ t('actions.create') }}</el-button>
            </template>
        </el-dialog>

        <!-- 编辑功能开关 Dialog -->
        <el-dialog v-model="showEditDialog" :title="t('feature_flags_page.edit_dialog_title')" width="520px">
            <el-form ref="editFormRef" :model="editForm" :rules="formRules" label-position="top">
                <el-form-item :label="t('feature_flags_page.key_label')">
                    <el-input v-model="editForm.key" disabled />
                    <div class="form-tip">{{ t('feature_flags_page.key_immutable_tip') }}</div>
                </el-form-item>
                <el-form-item :label="t('feature_flags_page.name_label')" prop="name">
                    <el-input v-model="editForm.name" />
                </el-form-item>
                <el-form-item :label="t('feature_flags_page.description_label')" prop="description">
                    <el-input v-model="editForm.description" type="textarea" :rows="3" />
                </el-form-item>
                <el-form-item :label="t('feature_flags_page.global_enable_label')">
                    <el-switch v-model="editForm.is_active" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEditDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleEdit" :loading="submitting">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- 产品关联管理 Dialog -->
        <el-dialog v-model="showAssignDialog" :title="t('feature_flags_page.assign_dialog_title')" width="560px">
            <p class="dialog-subtitle">{{ t('feature_flags_page.assign_dialog_subtitle', { key: assigningFlag?.key }) }}</p>
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
                <el-empty v-if="products.length === 0" :description="t('feature_flags_page.no_products')" :image-size="60" />
            </div>
            <template #footer>
                <el-button @click="showAssignDialog = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import featureFlagApi from '@/api/featureFlag';
import productApi from '@/api/product';

const { t } = useI18n();

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

const formRules = computed(() => ({
    key: [
        { required: true, message: t('feature_flags_page.key_required'), trigger: 'blur' },
        { pattern: /^[a-z][a-z0-9_]*$/, message: t('feature_flags_page.key_pattern'), trigger: 'blur' },
    ],
    name: [{ required: true, message: t('feature_flags_page.name_required'), trigger: 'blur' }],
}));

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
        const { data: res } = await featureFlagApi.list();
        featureFlags.value = res.data || [];
    } catch {
        featureFlags.value = [];
    } finally {
        loading.value = false;
    }
}

async function loadProducts() {
    try {
        const { data: res } = await productApi.list({ per_page: 100 });
        const paginated = res.data;
        products.value = paginated.data || paginated || [];
    } catch {
        products.value = [];
    }
}

async function loadProductAssignments() {
    try {
        // 从 product_feature_flag 表加载关联数据
        const { data: res } = await featureFlagApi.assignments();
        productAssignments.value = res.data || [];
    } catch {
        productAssignments.value = [];
    }
}

async function handleCreate() {
    const valid = await createFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    submitting.value = true;
    try {
        await featureFlagApi.create(createForm);
        ElMessage.success(t('feature_flags_page.create_ok'));
        showCreateDialog.value = false;
        createForm.key = ''; createForm.name = ''; createForm.description = ''; createForm.is_active = true;
        loadFeatureFlags();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('feature_flags_page.create_fail'));
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
        await featureFlagApi.update(editForm.id, {
            name: editForm.name,
            description: editForm.description,
            is_active: editForm.is_active,
        });
        ElMessage.success(t('feature_flags_page.update_ok'));
        showEditDialog.value = false;
        loadFeatureFlags();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('feature_flags_page.update_fail'));
    } finally {
        submitting.value = false;
    }
}

async function handleDelete(flag) {
    try {
        await ElMessageBox.confirm(
            t('feature_flags_page.delete_confirm', { key: flag.key }),
            t('feature_flags_page.delete_title'),
            { confirmButtonText: t('actions.delete'), cancelButtonText: t('actions.cancel'), type: 'warning' }
        );
        await featureFlagApi.destroy(flag.id);
        ElMessage.success(t('feature_flags_page.delete_ok'));
        loadFeatureFlags();
    } catch { /* cancelled */ }
}

async function handleToggleGlobal(flag, val) {
    togglingId.value = flag.id;
    try {
        await featureFlagApi.toggle(flag.id, val);
        ElMessage.success(val ? t('feature_flags_page.enabled_ok') : t('feature_flags_page.disabled_ok'));
    } catch {
        flag.is_active = !val;
        ElMessage.error(t('feature_flags_page.action_fail'));
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
        await featureFlagApi.assign({
            product_id: product.id,
            feature_flag_id: assigningFlag.value.id,
            is_active: enable,
        });
        ElMessage.success(enable ? t('feature_flags_page.assigned_ok') : t('feature_flags_page.unassigned_ok'));
        // 刷新产品关联数据
        loadFeatureFlags();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('feature_flags_page.action_fail'));
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
