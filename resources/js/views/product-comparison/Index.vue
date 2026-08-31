<template>
    <div class="comparison-page">
        <el-tabs v-model="activeTab">
            <el-tab-pane :label="t('product_comparison_page.tab_specs')" name="specs">
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-card class="side-card">
                            <template #header>
                                <span>{{ t('product_comparison_page.select_product') }}</span>
                            </template>
                            <el-input v-model="productSearch" :placeholder="t('product_comparison_page.search_product_ph')" size="small" clearable style="margin-bottom: 12px" />
                            <el-select v-model="selectedProductId" filterable remote :remote-method="searchProducts" :loading="searchingProducts" :placeholder="t('product_comparison_page.select_product_ph')" style="width: 100%" @change="loadSpecs">
                                <el-option v-for="p in productOptions" :key="p.id" :label="p.name" :value="p.id" />
                            </el-select>
                        </el-card>
                    </el-col>
                    <el-col :span="16">
                        <el-card>
                            <template #header>
                                <div class="flex-between">
                                    <span>{{ t('product_comparison_page.spec_edit') }}</span>
                                    <div v-if="selectedProductId">
                                        <el-button size="small" type="primary" @click="showAddGroup = true">{{ t('product_comparison_page.add_group') }}</el-button>
                                        <el-button size="small" @click="loadSpecs">{{ t('product_comparison_page.refresh') }}</el-button>
                                    </div>
                                </div>
                            </template>
                            <template v-if="!selectedProductId">
                                <el-empty :description="t('product_comparison_page.select_product_empty')" />
                            </template>
                            <template v-else-if="loading">
                                <el-skeleton :rows="5" animated />
                            </template>
                            <template v-else-if="specGroups.length === 0">
                                <el-empty :description="t('product_comparison_page.no_specs_hint')" />
                            </template>
                            <template v-else>
                                <div v-for="group in specGroups" :key="group.id" class="spec-group">
                                    <div class="spec-group-header">
                                        <strong>{{ group.name }}</strong>
                                        <div>
                                            <el-button size="small" @click="showAddSpec(group.id)">{{ t('product_comparison_page.add_spec') }}</el-button>
                                            <el-button size="small" type="danger" plain @click="confirmDeleteGroup(group)">{{ t('product_comparison_page.delete_group') }}</el-button>
                                        </div>
                                    </div>
                                    <el-table :data="group.specs" stripe size="small">
                                        <el-table-column :label="t('product_comparison_page.col_spec_item')" prop="label" />
                                        <el-table-column :label="t('product_comparison_page.col_type')" width="80">
                                            <template #default="{ row }">{{ specTypeLabel(row.type) }}</template>
                                        </el-table-column>
                                        <el-table-column :label="t('product_comparison_page.col_unit')" width="60" prop="unit" />
                                        <el-table-column :label="t('product_comparison_page.col_spec_value')" min-width="200">
                                            <template #default="{ row }">
                                                <div class="spec-value-cell">
                                                    <span class="value-text">{{ row.formatted_value || '-' }}</span>
                                                    <el-button size="small" link @click="editSpecValue(row)">{{ t('actions.edit') }}</el-button>
                                                </div>
                                            </template>
                                        </el-table-column>
                                        <el-table-column :label="t('products_page.col_actions')" width="120">
                                            <template #default="{ row }">
                                                <el-button size="small" link @click="showEditSpec(row)">{{ t('actions.edit') }}</el-button>
                                                <el-popconfirm :title="t('product_comparison_page.confirm_delete_spec')" @confirm="handleDeleteSpec(row.id)">
                                                    <template #reference>
                                                        <el-button size="small" link type="danger">{{ t('actions.delete') }}</el-button>
                                                    </template>
                                                </el-popconfirm>
                                            </template>
                                        </el-table-column>
                                    </el-table>
                                </div>
                            </template>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <el-tab-pane :label="t('product_comparison_page.tab_compare')" name="compare">
                <el-card>
                    <template #header>
                        <span>{{ t('product_comparison_page.compare_title') }}</span>
                    </template>
                    <el-form :inline="true" size="small">
                        <el-form-item :label="t('product_comparison_page.select_products_label')">
                            <el-select v-model="compareProductIds" multiple filterable :placeholder="t('product_comparison_page.select_products_ph')" style="width: 400px" @change="doCompare">
                                <el-option v-for="p in allProducts" :key="p.id" :label="p.name" :value="p.id" />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" :disabled="compareProductIds.length < 2" @click="doCompare">{{ t('nav.compare') }}</el-button>
                        </el-form-item>
                    </el-form>

                    <template v-if="compareResult">
                        <div class="compare-header">
                            <span class="compare-title">{{ t('product_comparison_page.compare_table_title') }}</span>
                        </div>
                        <div v-for="group in compareResult.groups" :key="group.name" class="compare-group">
                            <div class="compare-group-title">{{ group.name }}</div>
                            <el-table :data="group.rows" border stripe size="small">
                                <el-table-column :label="t('product_comparison_page.col_spec')" width="160" prop="label" fixed />
                                <el-table-column v-for="p in compareResult.products" :key="p.id" :label="p.name" :min-width="150">
                                    <template #default="{ row }">
                                        <span :class="{ 'is-highlight': isBestValue(p.id, row) }">{{ row.values[p.id] || '-' }}</span>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </template>
                    <el-empty v-else-if="compareProductIds.length < 2" :description="t('product_comparison_page.compare_empty_hint')" />
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <el-dialog v-model="showAddGroup" :title="t('product_comparison_page.dialog_add_group_title')" width="400px">
            <el-form>
                <el-form-item :label="t('product_comparison_page.group_name_label')">
                    <el-input v-model="newGroupName" :placeholder="t('product_comparison_page.group_name_ph')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAddGroup = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleAddGroup">{{ t('actions.confirm') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="showAddSpecDialog" :title="t('product_comparison_page.dialog_add_spec_title')" width="500px">
            <el-form>
                <el-form-item :label="t('product_comparison_page.spec_name_label')">
                    <el-input v-model="newSpec.label" :placeholder="t('product_comparison_page.spec_name_ph')" />
                </el-form-item>
                <el-form-item :label="t('product_comparison_page.type_label')">
                    <el-select v-model="newSpec.type" style="width: 100%">
                        <el-option v-for="opt in specTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('product_comparison_page.unit_label')">
                    <el-input v-model="newSpec.unit" :placeholder="t('product_comparison_page.unit_ph')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAddSpecDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleAddSpec">{{ t('actions.confirm') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="showEditValue" :title="t('product_comparison_page.dialog_edit_value_title')" width="400px">
            <el-form>
                <el-form-item :label="t('product_comparison_page.spec_value_label')">
                    <el-input v-model="editingValue" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEditValue = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleSaveValue">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="showEditSpecDialog" :title="t('product_comparison_page.dialog_edit_spec_title')" width="500px">
            <el-form>
                <el-form-item :label="t('product_comparison_page.spec_name_label')">
                    <el-input v-model="editSpecData.label" />
                </el-form-item>
                <el-form-item :label="t('product_comparison_page.type_label')">
                    <el-select v-model="editSpecData.type" style="width: 100%">
                        <el-option v-for="opt in specTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('product_comparison_page.unit_label')">
                    <el-input v-model="editSpecData.unit" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEditSpecDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleUpdateSpec">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getProductSpecs, createSpecGroup, deleteSpecGroup,
    createSpec, updateSpec, deleteSpec, setSpecValue,
    compareProducts, getAdminSpecList,
} from '@/api/productComparison'
import productApi from '@/api/product'

const { t } = useI18n()

const activeTab = ref('specs')

const productSearch = ref('')
const searchingProducts = ref(false)
const selectedProductId = ref(null)
const productOptions = ref([])
const allProducts = ref([])
const specGroups = ref([])
const loading = ref(false)

const showAddGroup = ref(false)
const newGroupName = ref('')

const showAddSpecDialog = ref(false)
const newSpec = ref({ label: '', type: 'text', unit: '', groupId: null })

const showEditSpecDialog = ref(false)
const editSpecData = ref({ id: null, label: '', type: 'text', unit: '' })

const showEditValue = ref(false)
const editingSpecId = ref(null)
const editingValue = ref('')

const specTypeKeys = ['text', 'number', 'boolean', 'select']

const specTypeOptions = computed(() =>
    specTypeKeys.map((value) => ({
        value,
        label: t(`product_comparison_page.spec_types.${value}`),
    }))
)

function specTypeLabel(type) {
    return t(`product_comparison_page.spec_types.${type}`, type)
}

async function loadProducts() {
    try {
        const res = await productApi.list({ per_page: 200 })
        const data = res.data?.data || res.data || []
        allProducts.value = data
        productOptions.value = data.slice(0, 20)
    } catch { /* ignore */ }
}

function searchProducts(query) {
    if (!query) {
        productOptions.value = allProducts.value.slice(0, 20)
        return
    }
    productOptions.value = allProducts.value.filter(p =>
        p.name.toLowerCase().includes(query.toLowerCase())
    )
}

async function loadSpecs() {
    if (!selectedProductId.value) return
    loading.value = true
    try {
        const res = await getProductSpecs(selectedProductId.value)
        specGroups.value = res.data || res
    } catch {
        specGroups.value = []
    } finally {
        loading.value = false
    }
}

async function handleAddGroup() {
    if (!newGroupName.value) {
        ElMessage.warning(t('product_comparison_page.messages.group_name_required'))
        return
    }
    try {
        await createSpecGroup(selectedProductId.value, { name: newGroupName.value })
        ElMessage.success(t('product_comparison_page.messages.group_created'))
        showAddGroup.value = false
        newGroupName.value = ''
        loadSpecs()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('product_comparison_page.messages.create_failed'))
    }
}

function confirmDeleteGroup(group) {
    ElMessageBox.confirm(
        t('product_comparison_page.confirm_delete_group', { name: group.name }),
        t('actions.confirm'),
        { type: 'warning' },
    ).then(async () => {
        try {
            await deleteSpecGroup(group.id)
            ElMessage.success(t('product_comparison_page.messages.group_deleted'))
            loadSpecs()
        } catch {
            ElMessage.error(t('product_comparison_page.messages.delete_failed'))
        }
    }).catch(() => {})
}

function showAddSpec(groupId) {
    newSpec.value = { label: '', type: 'text', unit: '', groupId }
    showAddSpecDialog.value = true
}

async function handleAddSpec() {
    if (!newSpec.value.label) {
        ElMessage.warning(t('product_comparison_page.messages.spec_name_required'))
        return
    }
    try {
        await createSpec(newSpec.value.groupId, {
            label: newSpec.value.label,
            type: newSpec.value.type,
            unit: newSpec.value.unit,
        })
        ElMessage.success(t('product_comparison_page.messages.spec_created'))
        showAddSpecDialog.value = false
        loadSpecs()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('product_comparison_page.messages.create_failed'))
    }
}

function showEditSpec(spec) {
    editSpecData.value = { id: spec.id, label: spec.label, type: spec.type, unit: spec.unit || '' }
    showEditSpecDialog.value = true
}

async function handleUpdateSpec() {
    try {
        await updateSpec(editSpecData.value.id, {
            label: editSpecData.value.label,
            type: editSpecData.value.type,
            unit: editSpecData.value.unit,
        })
        ElMessage.success(t('product_comparison_page.messages.spec_updated'))
        showEditSpecDialog.value = false
        loadSpecs()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('product_comparison_page.messages.update_failed'))
    }
}

async function handleDeleteSpec(specId) {
    try {
        await deleteSpec(specId)
        ElMessage.success(t('product_comparison_page.messages.spec_deleted'))
        loadSpecs()
    } catch {
        ElMessage.error(t('product_comparison_page.messages.delete_failed'))
    }
}

function editSpecValue(spec) {
    editingSpecId.value = spec.id
    editingValue.value = spec.value || ''
    showEditValue.value = true
}

async function handleSaveValue() {
    try {
        await setSpecValue(selectedProductId.value, editingSpecId.value, { value: editingValue.value })
        ElMessage.success(t('product_comparison_page.messages.value_saved'))
        showEditValue.value = false
        loadSpecs()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('product_comparison_page.messages.save_failed'))
    }
}

const compareProductIds = ref([])
const compareResult = ref(null)

async function doCompare() {
    if (compareProductIds.value.length < 2) return
    try {
        const res = await compareProducts({ product_ids: compareProductIds.value })
        compareResult.value = res.data || res
    } catch (e) {
        ElMessage.error(t('product_comparison_page.messages.compare_failed'))
    }
}

function isBestValue(productId, row) {
    if (row.type !== 'number') return false
    const values = Object.values(row.values).map(v => parseFloat(v)).filter(v => !isNaN(v))
    if (values.length === 0) return false
    return parseFloat(row.values[productId]) === Math.max(...values)
}

onMounted(() => {
    loadProducts()
})
</script>

<style scoped>
.comparison-page { padding: 20px; }
.side-card { margin-bottom: 16px; }
.flex-between { display: flex; justify-content: space-between; align-items: center; }
.spec-group { margin-bottom: 20px; border: 1px solid #ebeef5; border-radius: 6px; padding: 12px; }
.spec-group-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.spec-value-cell { display: flex; justify-content: space-between; align-items: center; }
.value-text { color: #606266; }
.compare-header { margin-bottom: 16px; }
.compare-title { font-size: 16px; font-weight: bold; }
.compare-group { margin-bottom: 20px; }
.compare-group-title { font-weight: bold; padding: 8px 12px; background: #f5f7fa; border-radius: 4px; margin-bottom: 8px; }
.is-highlight { color: #67c23a; font-weight: bold; }
</style>
