<template>
    <div class="wishlist-page">
        <!-- 统计 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total_items }}</div>
                    <div class="stat-label">收藏总数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total_groups }}</div>
                    <div class="stat-label">收藏分组</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.sale_notify }}</div>
                    <div class="stat-label">降价通知</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.high_priority }}</div>
                    <div class="stat-label">高优先级</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 操作栏 -->
        <el-card class="action-bar">
            <el-button type="primary" size="small" @click="showAddGroup = true">新建分组</el-button>
            <el-button size="small" @click="showAddDialog = true">
                <el-icon><Plus /></el-icon> 添加商品
            </el-button>
        </el-card>

        <!-- 收藏列表（按分组） -->
        <div v-if="wishlists.length === 0">
            <el-empty description="暂无收藏，去发现商品吧！" />
        </div>
        <div v-else v-for="group in wishlists" :key="group.id" class="group-section">
            <div class="group-header">
                <div class="group-title">
                    <el-tag v-if="group.items.length" round>{{ group.items.length }}</el-tag>
                    <strong>{{ group.name }}</strong>
                </div>
                <div>
                    <el-button size="small" link @click="editGroup(group)">编辑</el-button>
                    <el-popconfirm title="删除分组将同时删除组内收藏" @confirm="handleDeleteGroup(group.id)">
                        <template #reference>
                            <el-button size="small" link type="danger">删除分组</el-button>
                        </template>
                    </el-popconfirm>
                </div>
            </div>

            <el-table v-if="group.items.length" :data="group.items" stripe border size="small">
                <el-table-column label="商品" min-width="180">
                    <template #default="{ row }">
                        <div class="product-cell">
                            <span class="product-name">{{ row.product?.name || '已删除' }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="备注" width="150">
                    <template #default="{ row }">
                        <span class="text-muted">{{ row.note || '-' }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="优先级" width="80">
                    <template #default="{ row }">
                        <el-tag :type="priorityType(row.priority)" size="small">{{ priorityLabel(row.priority) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="目标价格" width="100">
                    <template #default="{ row }">
                        {{ row.target_price ? `¥${row.target_price}` : '-' }}
                    </template>
                </el-table-column>
                <el-table-column label="通知" width="120">
                    <template #default="{ row }">
                        <el-tag v-if="row.notify_on_sale" size="small" type="warning">降价</el-tag>
                        <el-tag v-if="row.notify_on_stock" size="small" type="success">到货</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" link @click="editItem(row)">编辑</el-button>
                        <el-button size="small" link @click="showMoveDialog(row)">移动</el-button>
                        <el-popconfirm title="确定移出收藏？" @confirm="handleRemove(row.id)">
                            <template #reference>
                                <el-button size="small" link type="danger">移除</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-else :description="`${group.name} 分组暂无收藏`" />
        </div>

        <!-- 添加商品 Dialog -->
        <el-dialog v-model="showAddDialog" title="添加收藏" width="450px">
            <el-form label-position="top">
                <el-form-item label="商品ID">
                    <el-input-number v-model="addProductId" :min="1" style="width: 100%" />
                </el-form-item>
                <el-form-item label="分组">
                    <el-select v-model="addGroupId" placeholder="默认分组" clearable style="width: 100%">
                        <el-option v-for="g in wishlists" :key="g.id" :label="g.name" :value="g.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="addNote" type="textarea" :rows="2" placeholder="为什么想要这个？" />
                </el-form-item>
                <el-form-item label="优先级">
                    <el-select v-model="addPriority" style="width: 100%">
                        <el-option label="普通" :value="0" />
                        <el-option label="重要" :value="1" />
                        <el-option label="紧急" :value="2" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAddDialog = false">取消</el-button>
                <el-button type="primary" @click="handleAdd">添加</el-button>
            </template>
        </el-dialog>

        <!-- 编辑收藏 Dialog -->
        <el-dialog v-model="showEditDialog" title="编辑收藏" width="450px">
            <el-form label-position="top">
                <el-form-item label="备注">
                    <el-input v-model="editNote" type="textarea" :rows="2" />
                </el-form-item>
                <el-form-item label="优先级">
                    <el-select v-model="editPriority" style="width: 100%">
                        <el-option label="普通" :value="0" />
                        <el-option label="重要" :value="1" />
                        <el-option label="紧急" :value="2" />
                    </el-select>
                </el-form-item>
                <el-form-item label="目标价格">
                    <el-input-number v-model="editTargetPrice" :min="0" :precision="2" style="width: 100%" />
                </el-form-item>
                <el-form-item label="降价通知">
                    <el-switch v-model="editNotifySale" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEditDialog = false">取消</el-button>
                <el-button type="primary" @click="handleUpdate">保存</el-button>
            </template>
        </el-dialog>

        <!-- 移动 Dialog -->
        <el-dialog v-model="showMoveDialogVisible" title="移动收藏" width="400px">
            <el-form>
                <el-form-item label="目标分组">
                    <el-select v-model="moveTargetGroupId" style="width: 100%">
                        <el-option v-for="g in wishlists" :key="g.id" :label="g.name" :value="g.id" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showMoveDialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleMove">移动</el-button>
            </template>
        </el-dialog>

        <!-- 新建分组 Dialog -->
        <el-dialog v-model="showAddGroup" title="新建分组" width="400px">
            <el-form>
                <el-form-item label="分组名称">
                    <el-input v-model="newGroupName" placeholder="如: 待购/对比/礼物" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAddGroup = false">取消</el-button>
                <el-button type="primary" @click="handleAddGroup">创建</el-button>
            </template>
        </el-dialog>

        <!-- 编辑分组 Dialog -->
        <el-dialog v-model="showEditGroup" title="编辑分组" width="400px">
            <el-form>
                <el-form-item label="分组名称">
                    <el-input v-model="editGroupName" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEditGroup = false">取消</el-button>
                <el-button type="primary" @click="handleUpdateGroup">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
    getMyWishlists, getMyStats, addToWishlist, removeWishlistItem,
    updateWishlistItem, moveWishlistItem,
    createWishlistGroup, updateWishlistGroup, deleteWishlistGroup,
} from '@/api/wishlist'

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

function priorityType(p) {
    return ['info', 'warning', 'danger'][p] || 'info'
}

function priorityLabel(p) {
    return ['普通', '重要', '紧急'][p] || '普通'
}

async function loadData() {
    try {
        const [wishlistsRes, statsRes] = await Promise.all([getMyWishlists(), getMyStats()])
        wishlists.value = wishlistsRes.data?.data || wishlistsRes.data || []
        stats.value = statsRes.data?.data || statsRes.data || stats.value
    } catch {
        ElMessage.error('加载收藏列表失败')
    }
}

// 添加
function handleAdd() {
    if (!addProductId.value) {
        ElMessage.warning('请输入商品ID')
        return
    }
    addToWishlist({
        product_id: addProductId.value,
        group_id: addGroupId.value || undefined,
        note: addNote.value,
        priority: addPriority.value,
    }).then(() => {
        ElMessage.success('已添加')
        showAddDialog.value = false
        addProductId.value = null
        addNote.value = ''
        addPriority.value = 0
        loadData()
    }).catch(e => {
        ElMessage.error(e.response?.data?.message || '添加失败')
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
        ElMessage.success('已更新')
        showEditDialog.value = false
        loadData()
    }).catch(e => {
        ElMessage.error(e.response?.data?.message || '更新失败')
    })
}

// 移除
function handleRemove(id) {
    removeWishlistItem(id).then(() => {
        ElMessage.success('已移除')
        loadData()
    }).catch(() => {
        ElMessage.error('移除失败')
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
        ElMessage.warning('请选择目标分组')
        return
    }
    moveWishlistItem(moveItemId.value, { group_id: moveTargetGroupId.value }).then(() => {
        ElMessage.success('已移动')
        showMoveDialogVisible.value = false
        loadData()
    }).catch(e => {
        ElMessage.error(e.response?.data?.message || '移动失败')
    })
}

// 分组
function handleAddGroup() {
    if (!newGroupName.value) {
        ElMessage.warning('请输入分组名称')
        return
    }
    createWishlistGroup({ name: newGroupName.value }).then(() => {
        ElMessage.success('分组已创建')
        showAddGroup.value = false
        newGroupName.value = ''
        loadData()
    }).catch(e => {
        ElMessage.error(e.response?.data?.message || '创建失败')
    })
}

function editGroup(group) {
    editGroupId.value = group.id
    editGroupName.value = group.name
    showEditGroup.value = true
}

function handleUpdateGroup() {
    updateWishlistGroup(editGroupId.value, { name: editGroupName.value }).then(() => {
        ElMessage.success('分组已更新')
        showEditGroup.value = false
        loadData()
    }).catch(e => {
        ElMessage.error(e.response?.data?.message || '更新失败')
    })
}

function handleDeleteGroup(id) {
    deleteWishlistGroup(id).then(() => {
        ElMessage.success('分组已删除')
        loadData()
    }).catch(() => {
        ElMessage.error('删除失败')
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
