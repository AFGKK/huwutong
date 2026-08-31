<template>
    <div class="wishlist-page">
        <!-- 统计 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total_items }}</div>
                    <div class="stat-label">{{ t('wishlist_page.stat_total_items') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total_groups }}</div>
                    <div class="stat-label">{{ t('wishlist_page.stat_total_groups') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.sale_notify }}</div>
                    <div class="stat-label">{{ t('wishlist_page.stat_sale_notify') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.high_priority }}</div>
                    <div class="stat-label">{{ t('wishlist_page.stat_high_priority') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 操作栏 -->
        <el-card class="action-bar">
            <el-button type="primary" size="small" @click="showAddGroup = true">{{ t('wishlist_page.new_group') }}</el-button>
            <el-button size="small" @click="showAddDialog = true">
                <el-icon><Plus /></el-icon> {{ t('wishlist_page.add_product') }}
            </el-button>
        </el-card>

        <!-- 收藏列表（按分组） -->
        <div v-if="wishlists.length === 0">
            <el-empty :description="t('wishlist_page.empty')" />
        </div>
        <div v-else v-for="group in wishlists" :key="group.id" class="group-section">
            <div class="group-header">
                <div class="group-title">
                    <el-tag v-if="group.items.length" round>{{ group.items.length }}</el-tag>
                    <strong>{{ group.name }}</strong>
                </div>
                <div>
                    <el-button size="small" link @click="editGroup(group)">{{ t('actions.edit') }}</el-button>
                    <el-popconfirm :title="t('wishlist_page.delete_group_confirm')" @confirm="handleDeleteGroup(group.id)">
                        <template #reference>
                            <el-button size="small" link type="danger">{{ t('wishlist_page.delete_group') }}</el-button>
                        </template>
                    </el-popconfirm>
                </div>
            </div>

            <el-table v-if="group.items.length" :data="group.items" stripe border size="small">
                <el-table-column :label="t('checkout_page.col_product')" min-width="180">
                    <template #default="{ row }">
                        <div class="product-cell">
                            <span class="product-name">{{ row.product?.name || t('wishlist_page.product_deleted') }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('wishlist_page.col_note')" width="150">
                    <template #default="{ row }">
                        <span class="text-muted">{{ row.note || t('products_page.em_dash') }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('wishlist_page.col_priority')" width="80">
                    <template #default="{ row }">
                        <el-tag :type="priorityType(row.priority)" size="small">{{ priorityLabel(row.priority) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('wishlist_page.col_target_price')" width="100">
                    <template #default="{ row }">
                        {{ row.target_price ? `¥${row.target_price}` : t('products_page.em_dash') }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('wishlist_page.col_notify')" width="120">
                    <template #default="{ row }">
                        <el-tag v-if="row.notify_on_sale" size="small" type="warning">{{ t('wishlist_page.notify_sale') }}</el-tag>
                        <el-tag v-if="row.notify_on_stock" size="small" type="success">{{ t('wishlist_page.notify_stock') }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('products_page.col_actions')" width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" link @click="editItem(row)">{{ t('actions.edit') }}</el-button>
                        <el-button size="small" link @click="showMoveDialog(row)">{{ t('wishlist_page.move') }}</el-button>
                        <el-popconfirm :title="t('wishlist_page.remove_confirm')" @confirm="handleRemove(row.id)">
                            <template #reference>
                                <el-button size="small" link type="danger">{{ t('wishlist_page.remove') }}</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-else :description="t('wishlist_page.group_empty', { name: group.name })" />
        </div>

        <!-- 添加商品 Dialog -->
        <el-dialog v-model="showAddDialog" :title="t('wishlist_page.add_dialog_title')" width="450px">
            <el-form label-position="top">
                <el-form-item :label="t('wishlist_page.field_product_id')">
                    <el-input-number v-model="addProductId" :min="1" style="width: 100%" />
                </el-form-item>
                <el-form-item :label="t('wishlist_page.field_group')">
                    <el-select v-model="addGroupId" :placeholder="t('wishlist_page.default_group_ph')" clearable style="width: 100%">
                        <el-option v-for="g in wishlists" :key="g.id" :label="g.name" :value="g.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('wishlist_page.field_note')">
                    <el-input v-model="addNote" type="textarea" :rows="2" :placeholder="t('wishlist_page.note_ph')" />
                </el-form-item>
                <el-form-item :label="t('wishlist_page.field_priority')">
                    <el-select v-model="addPriority" style="width: 100%">
                        <el-option v-for="opt in priorityOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAddDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleAdd">{{ t('wishlist_page.add_btn') }}</el-button>
            </template>
        </el-dialog>

        <!-- 编辑收藏 Dialog -->
        <el-dialog v-model="showEditDialog" :title="t('wishlist_page.edit_dialog_title')" width="450px">
            <el-form label-position="top">
                <el-form-item :label="t('wishlist_page.field_note')">
                    <el-input v-model="editNote" type="textarea" :rows="2" />
                </el-form-item>
                <el-form-item :label="t('wishlist_page.field_priority')">
                    <el-select v-model="editPriority" style="width: 100%">
                        <el-option v-for="opt in priorityOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('wishlist_page.field_target_price')">
                    <el-input-number v-model="editTargetPrice" :min="0" :precision="2" style="width: 100%" />
                </el-form-item>
                <el-form-item :label="t('wishlist_page.notify_sale_label')">
                    <el-switch v-model="editNotifySale" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEditDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleUpdate">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- 移动 Dialog -->
        <el-dialog v-model="showMoveDialogVisible" :title="t('wishlist_page.move_dialog_title')" width="400px">
            <el-form>
                <el-form-item :label="t('wishlist_page.target_group')">
                    <el-select v-model="moveTargetGroupId" style="width: 100%">
                        <el-option v-for="g in wishlists" :key="g.id" :label="g.name" :value="g.id" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showMoveDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleMove">{{ t('wishlist_page.move') }}</el-button>
            </template>
        </el-dialog>

        <!-- 新建分组 Dialog -->
        <el-dialog v-model="showAddGroup" :title="t('wishlist_page.new_group_dialog_title')" width="400px">
            <el-form>
                <el-form-item :label="t('wishlist_page.group_name')">
                    <el-input v-model="newGroupName" :placeholder="t('wishlist_page.group_name_ph')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAddGroup = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleAddGroup">{{ t('actions.create') }}</el-button>
            </template>
        </el-dialog>

        <!-- 编辑分组 Dialog -->
        <el-dialog v-model="showEditGroup" :title="t('wishlist_page.edit_group_dialog_title')" width="400px">
            <el-form>
                <el-form-item :label="t('wishlist_page.group_name')">
                    <el-input v-model="editGroupName" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEditGroup = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleUpdateGroup">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
    getMyWishlists, getMyStats, addToWishlist, removeWishlistItem,
    updateWishlistItem, moveWishlistItem,
    createWishlistGroup, updateWishlistGroup, deleteWishlistGroup,
} from '@/api/wishlist'

const { t } = useI18n()

const stats = ref({ total_items: 0, total_groups: 0, sale_notify: 0, high_priority: 0 })
const wishlists = ref([])

// 添加
const showAddDialog = ref(false)
const addProductId = ref(null)
const addGroupId = ref(null)
const addNote = ref('')
const addPriority = ref(0)

// 编辑
const showEditDialog = ref(false)
const editItemId = ref(null)
const editNote = ref('')
const editPriority = ref(0)
const editTargetPrice = ref(null)
const editNotifySale = ref(false)

// 移动
const showMoveDialogVisible = ref(false)
const moveItemId = ref(null)
const moveTargetGroupId = ref(null)

// 分组
const showAddGroup = ref(false)
const newGroupName = ref('')
const showEditGroup = ref(false)
const editGroupId = ref(null)
const editGroupName = ref('')

const priorityOptions = computed(() => [
    { value: 0, label: t('wishlist_page.priority_normal') },
    { value: 1, label: t('wishlist_page.priority_important') },
    { value: 2, label: t('wishlist_page.priority_urgent') },
])

const priorityLabels = computed(() => Object.fromEntries(
    priorityOptions.value.map((opt) => [opt.value, opt.label]),
))

function priorityType(p) {
    return ['info', 'warning', 'danger'][p] || 'info'
}

function priorityLabel(p) {
    return priorityLabels.value[p] ?? priorityLabels.value[0]
}

async function loadData() {
    try {
        const [wishlistsRes, statsRes] = await Promise.all([getMyWishlists(), getMyStats()])
        wishlists.value = wishlistsRes.data?.data || wishlistsRes.data || []
        stats.value = statsRes.data?.data || statsRes.data || stats.value
    } catch {
        ElMessage.error(t('wishlist_page.load_failed'))
    }
}

// 添加
function handleAdd() {
    if (!addProductId.value) {
        ElMessage.warning(t('wishlist_page.product_id_required'))
        return
    }
    addToWishlist({
        product_id: addProductId.value,
        group_id: addGroupId.value || undefined,
        note: addNote.value,
        priority: addPriority.value,
    }).then(() => {
        ElMessage.success(t('wishlist_page.added_ok'))
        showAddDialog.value = false
        addProductId.value = null
        addNote.value = ''
        addPriority.value = 0
        loadData()
    }).catch(e => {
        ElMessage.error(e.response?.data?.message || t('wishlist_page.add_failed'))
    })
}

// 编辑
function editItem(row) {
    editItemId.value = row.id
    editNote.value = row.note || ''
    editPriority.value = row.priority
    editTargetPrice.value = row.target_price
    editNotifySale.value = row.notify_on_sale
    showEditDialog.value = true
}

function handleUpdate() {
    updateWishlistItem(editItemId.value, {
        note: editNote.value,
        priority: editPriority.value,
        target_price: editTargetPrice.value,
        notify_on_sale: editNotifySale.value,
    }).then(() => {
        ElMessage.success(t('wishlist_page.updated_ok'))
        showEditDialog.value = false
        loadData()
    }).catch(e => {
        ElMessage.error(e.response?.data?.message || t('wishlist_page.update_failed'))
    })
}

// 移除
function handleRemove(id) {
    removeWishlistItem(id).then(() => {
        ElMessage.success(t('wishlist_page.removed_ok'))
        loadData()
    }).catch(() => {
        ElMessage.error(t('wishlist_page.remove_failed'))
    })
}

// 移动
function showMoveDialog(row) {
    moveItemId.value = row.id
    moveTargetGroupId.value = null
    showMoveDialogVisible.value = true
}

function handleMove() {
    if (!moveTargetGroupId.value) {
        ElMessage.warning(t('wishlist_page.target_group_required'))
        return
    }
    moveWishlistItem(moveItemId.value, { group_id: moveTargetGroupId.value }).then(() => {
        ElMessage.success(t('wishlist_page.moved_ok'))
        showMoveDialogVisible.value = false
        loadData()
    }).catch(e => {
        ElMessage.error(e.response?.data?.message || t('wishlist_page.move_failed'))
    })
}

// 分组
function handleAddGroup() {
    if (!newGroupName.value) {
        ElMessage.warning(t('wishlist_page.group_name_required'))
        return
    }
    createWishlistGroup({ name: newGroupName.value }).then(() => {
        ElMessage.success(t('wishlist_page.group_created_ok'))
        showAddGroup.value = false
        newGroupName.value = ''
        loadData()
    }).catch(e => {
        ElMessage.error(e.response?.data?.message || t('wishlist_page.create_failed'))
    })
}

function editGroup(group) {
    editGroupId.value = group.id
    editGroupName.value = group.name
    showEditGroup.value = true
}

function handleUpdateGroup() {
    updateWishlistGroup(editGroupId.value, { name: editGroupName.value }).then(() => {
        ElMessage.success(t('wishlist_page.group_updated_ok'))
        showEditGroup.value = false
        loadData()
    }).catch(e => {
        ElMessage.error(e.response?.data?.message || t('wishlist_page.update_failed'))
    })
}

function handleDeleteGroup(id) {
    deleteWishlistGroup(id).then(() => {
        ElMessage.success(t('wishlist_page.group_deleted_ok'))
        loadData()
    }).catch(() => {
        ElMessage.error(t('wishlist_page.delete_failed'))
    })
}

onMounted(() => {
    loadData()
})
</script>

<style scoped>
.wishlist-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 24px; font-weight: bold; color: #303133; }
.stat-card .stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.action-bar { margin-bottom: 16px; }
.group-section { margin-bottom: 24px; }
.group-header { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #f5f7fa; border-radius: 6px; margin-bottom: 8px; }
.group-title { display: flex; align-items: center; gap: 8px; }
.product-cell { display: flex; align-items: center; gap: 8px; }
.product-name { font-weight: 500; }
.text-muted { color: #909399; font-size: 12px; }
</style>
