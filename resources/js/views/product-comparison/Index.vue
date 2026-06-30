<template>
    <div class="comparison-page">
        <el-tabs v-model="activeTab">
            <el-tab-pane label="规格管理" name="specs">
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-card class="side-card">
                            <template #header>
                                <span>选择商品</span>
                            </template>
                            <el-input v-model="productSearch" placeholder="搜索商品..." size="small" clearable style="margin-bottom: 12px" />
                            <el-select v-model="selectedProductId" filterable remote :remote-method="searchProducts" :loading="searchingProducts" placeholder="选择商品" style="width: 100%" @change="loadSpecs">
                                <el-option v-for="p in productOptions" :key="p.id" :label="p.name" :value="p.id" />
                            </el-select>
                        </el-card>
                    </el-col>
                    <el-col :span="16">
                        <el-card>
                            <template #header>
                                <div class="flex-between">
                                    <span>规格编辑</span>
                                    <div v-if="selectedProductId">
                                        <el-button size="small" type="primary" @click="showAddGroup = true">添加分组</el-button>
                                        <el-button size="small" @click="loadSpecs">刷新</el-button>
                                    </div>
                                </div>
                            </template>
                            <template v-if="!selectedProductId">
                                <el-empty description="请选择商品" />
                            </template>
                            <template v-else-if="loading">
                                <el-skeleton :rows="5" animated />
                            </template>
                            <template v-else-if="specGroups.length === 0">
                                <el-empty description="该商品暂无规格，请添加规格分组" />
                            </template>
                            <template v-else>
                                <div v-for="group in specGroups" :key="group.id" class="spec-group">
                                    <div class="spec-group-header">
                                        <strong>{{ group.name }}</strong>
                                        <div>
                                            <el-button size="small" @click="showAddSpec(group.id)">添加规格</el-button>
                                            <el-button size="small" type="danger" plain @click="confirmDeleteGroup(group)">删除分组</el-button>
                                        </div>
                                    </div>
                                    <el-table :data="group.specs" stripe size="small">
                                        <el-table-column label="规格项" prop="label" />
                                        <el-table-column label="类型" width="80">
                                            <template #default="{ row }">{{ specTypeLabel(row.type) }}</template>
                                        </el-table-column>
                                        <el-table-column label="单位" width="60" prop="unit" />
                                        <el-table-column label="规格值" min-width="200">
                                            <template #default="{ row }">
                                                <div class="spec-value-cell">
                                                    <span class="value-text">{{ row.formatted_value || '-' }}</span>
                                                    <el-button size="small" link @click="editSpecValue(row)">编辑</el-button>
                                                </div>
                                            </template>
                                        </el-table-column>
                                        <el-table-column label="操作" width="120">
                                            <template #default="{ row }">
                                                <el-button size="small" link @click="showEditSpec(row)">编辑</el-button>
                                                <el-popconfirm title="确定删除？" @confirm="handleDeleteSpec(row.id)">
                                                    <template #reference>
                                                        <el-button size="small" link type="danger">删除</el-button>
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

            <el-tab-pane label="商品对比" name="compare">
                <el-card>
                    <template #header>
                        <span>商品规格对比</span>
                    </template>
                    <el-form :inline="true" size="small">
                        <el-form-item label="选择商品（2-10个）">
                            <el-select v-model="compareProductIds" multiple filterable placeholder="选择要对比的商品" style="width: 400px" @change="doCompare">
                                <el-option v-for="p in allProducts" :key="p.id" :label="p.name" :value="p.id" />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" :disabled="compareProductIds.length < 2" @click="doCompare">对比</el-button>
                        </el-form-item>
                    </el-form>

                    <template v-if="compareResult">
                        <div class="compare-header">
                            <span class="compare-title">规格对比表</span>
                        </div>
                        <div v-for="group in compareResult.groups" :key="group.name" class="compare-group">
                            <div class="compare-group-title">{{ group.name }}</div>
                            <el-table :data="group.rows" border stripe size="small">
                                <el-table-column label="规格" width="160" prop="label" fixed />
                                <el-table-column v-for="p in compareResult.products" :key="p.id" :label="p.name" :min-width="150">
                                    <template #default="{ row }">
                                        <span :class="{ 'is-highlight': isBestValue(p.id, row) }">{{ row.values[p.id] || '-' }}</span>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </template>
                    <el-empty v-else-if="compareProductIds.length < 2" description="请选择2-10个商品进行对比" />
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 添加分组 Dialog -->
        <el-dialog v-model="showAddGroup" title="添加规格分组" width="400px">
            <el-form>
                <el-form-item label="分组名称">
                    <el-input v-model="newGroupName" placeholder="如: 基本参数/性能" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAddGroup = false">取消</el-button>
                <el-button type="primary" @click="handleAddGroup">确定</el-button>
            </template>
        </el-dialog>

        <!-- 添加规格 Dialog -->
        <el-dialog v-model="showAddSpecDialog" title="添加规格项" width="500px">
            <el-form>
                <el-form-item label="规格名称">
                    <el-input v-model="newSpec.label" placeholder="如: CPU/内存" />
                </el-form-item>
                <el-form-item label="类型">
                    <el-select v-model="newSpec.type" style="width: 100%">
                        <el-option label="文本" value="text" />
                        <el-option label="数字" value="number" />
                        <el-option label="布尔值" value="boolean" />
                        <el-option label="选择" value="select" />
                    </el-select>
                </el-form-item>
                <el-form-item label="单位">
                    <el-input v-model="newSpec.unit" placeholder="如: GHz/GB" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAddSpecDialog = false">取消</el-button>
                <el-button type="primary" @click="handleAddSpec">确定</el-button>
            </template>
        </el-dialog>

        <!-- 编辑规格值 Dialog -->
        <el-dialog v-model="showEditValue" title="编辑规格值" width="400px">
            <el-form>
                <el-form-item label="规格值">
                    <el-input v-model="editingValue" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEditValue = false">取消</el-button>
                <el-button type="primary" @click="handleSaveValue">保存</el-button>
            </template>
        </el-dialog>

        <!-- 编辑规格项 Dialog -->
        <el-dialog v-model="showEditSpecDialog" title="编辑规格项" width="500px">
            <el-form>
                <el-form-item label="规格名称">
                    <el-input v-model="editSpecData.label" />
                </el-form-item>
                <el-form-item label="类型">
                    <el-select v-model="editSpecData.type" style="width: 100%">
                        <el-option label="文本" value="text" />
                        <el-option label="数字" value="number" />
                        <el-option label="布尔值" value="boolean" />
                        <el-option label="选择" value="select" />
                    </el-select>
                </el-form-item>
                <el-form-item label="单位">
                    <el-input v-model="editSpecData.unit" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEditSpecDialog = false">取消</el-button>
                <el-button type="primary" @click="handleUpdateSpec">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getProductSpecs, createSpecGroup, deleteSpecGroup,
    createSpec, updateSpec, deleteSpec, setSpecValue,
    compareProducts, getAdminSpecList,
} from '@/api/productComparison'
import apiClient from '@/api/client'

const activeTab = ref('specs')

// ─── 规格管理 ───
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

function specTypeLabel(type) {
    return { text: '文本', number: '数字', boolean: '布尔', select: '选择' }[type] || type
}

async function loadProducts() {
    try {
        const res = await apiClient.get('/admin/products', { params: { per_page: 200 } })
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
        ElMessage.warning('请输入分组名称')
        return
    }
    try {
        await createSpecGroup(selectedProductId.value, { name: newGroupName.value })
        ElMessage.success('分组已创建')
        showAddGroup.value = false
        newGroupName.value = ''
        loadSpecs()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '创建失败')
    }
}

function confirmDeleteGroup(group) {
    ElMessageBox.confirm(`确定删除分组「${group.name}」及其所有规格项？`, '确认', { type: 'warning' }).then(async () => {
        try {
            await deleteSpecGroup(group.id)
            ElMessage.success('分组已删除')
            loadSpecs()
        } catch {
            ElMessage.error('删除失败')
        }
    }).catch(() => {})
}

function showAddSpec(groupId) {
    newSpec.value = { label: '', type: 'text', unit: '', groupId }
    showAddSpecDialog.value = true
}

async function handleAddSpec() {
    if (!newSpec.value.label) {
        ElMessage.warning('请输入规格名称')
        return
    }
    try {
        await createSpec(newSpec.value.groupId, {
            label: newSpec.value.label,
            type: newSpec.value.type,
            unit: newSpec.value.unit,
        })
        ElMessage.success('规格项已创建')
        showAddSpecDialog.value = false
        loadSpecs()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '创建失败')
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
        ElMessage.success('规格项已更新')
        showEditSpecDialog.value = false
        loadSpecs()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '更新失败')
    }
}

async function handleDeleteSpec(specId) {
    try {
        await deleteSpec(specId)
        ElMessage.success('规格项已删除')
        loadSpecs()
    } catch {
        ElMessage.error('删除失败')
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
        ElMessage.success('规格值已保存')
        showEditValue.value = false
        loadSpecs()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败')
    }
}

// ─── 商品对比 ───
const compareProductIds = ref([])
const compareResult = ref(null)

async function doCompare() {
    if (compareProductIds.value.length < 2) return
    try {
        const res = await compareProducts({ product_ids: compareProductIds.value })
        compareResult.value = res.data || res
    } catch (e) {
        ElMessage.error('对比失败')
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
