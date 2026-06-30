<template>
    <div class="footer-nav-container">
        <el-page-header :content="'页脚导航配置'" @back="$router.push('/admin/dashboard')" />

        <el-alert
            title="管理网站页脚链接，支持拖拽排序、分组管理(页脚主区/社交媒体/底部信息)、开关控制。"
            type="info"
            show-icon
            :closable="false"
            class="alert-info"
        />

        <!-- 统计 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ items.length }}</div>
                    <div class="stat-label">链接总数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-success">{{ activeCount }}</div>
                    <div class="stat-label">已启用</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ grouped.footer?.length || 0 }}</div>
                    <div class="stat-label">页脚主区</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ grouped.social?.length || 0 }}</div>
                    <div class="stat-label">社交媒体</div>
                </el-card>
            </el-col>
        </el-row>

        <el-card>
            <template #header>
                <div class="card-header">
                    <span>页脚链接管理</span>
                    <div>
                        <el-button size="small" @click="handleInitDefaults">初始化默认</el-button>
                        <el-button size="small" type="primary" @click="openCreateDialog">新建链接</el-button>
                    </div>
                </div>
            </template>

            <!-- 页脚主区 -->
            <h3 class="group-title">📋 页脚主区</h3>
            <draggable
                :list="grouped.footer || []"
                group="footer"
                item-key="id"
                handle=".drag-handle"
                :animation="200"
                @change="handleReorder"
                class="drag-list"
            >
                <template #item="{ element }">
                    <div class="drag-item">
                        <span class="drag-handle">⣿</span>
                        <el-tag :type="typeTagType(element.type)" size="small" class="item-type">{{ typeLabel(element.type) }}</el-tag>
                        <span class="item-label">{{ element.label }}</span>
                        <span class="item-url">{{ element.url || '-' }}</span>
                        <el-switch
                            :model-value="element.is_active"
                            size="small"
                            @change="handleToggle(element)"
                            class="item-toggle"
                        />
                        <el-button size="small" @click="editItem(element)" class="item-btn">编辑</el-button>
                        <el-button size="small" type="danger" plain @click="handleDelete(element)" class="item-btn">删除</el-button>
                    </div>
                </template>
            </draggable>

            <!-- 社交媒体 -->
            <h3 class="group-title">🌐 社交媒体</h3>
            <draggable
                :list="grouped.social || []"
                group="social"
                item-key="id"
                handle=".drag-handle"
                :animation="200"
                @change="handleReorder"
                class="drag-list"
            >
                <template #item="{ element }">
                    <div class="drag-item">
                        <span class="drag-handle">⣿</span>
                        <el-tag :type="typeTagType(element.type)" size="small" class="item-type">{{ typeLabel(element.type) }}</el-tag>
                        <span class="item-label">{{ element.label }}</span>
                        <span class="item-url">{{ element.url || '-' }}</span>
                        <el-switch
                            :model-value="element.is_active"
                            size="small"
                            @change="handleToggle(element)"
                            class="item-toggle"
                        />
                        <el-button size="small" @click="editItem(element)" class="item-btn">编辑</el-button>
                        <el-button size="small" type="danger" plain @click="handleDelete(element)" class="item-btn">删除</el-button>
                    </div>
                </template>
            </draggable>

            <!-- 底部信息 -->
            <h3 class="group-title">🔻 底部信息</h3>
            <draggable
                :list="grouped.bottom || []"
                group="bottom"
                item-key="id"
                handle=".drag-handle"
                :animation="200"
                @change="handleReorder"
                class="drag-list"
            >
                <template #item="{ element }">
                    <div class="drag-item">
                        <span class="drag-handle">⣿</span>
                        <el-tag :type="typeTagType(element.type)" size="small" class="item-type">{{ typeLabel(element.type) }}</el-tag>
                        <span class="item-label">{{ element.label }}</span>
                        <span class="item-url">{{ element.url || '-' }}</span>
                        <el-switch
                            :model-value="element.is_active"
                            size="small"
                            @change="handleToggle(element)"
                            class="item-toggle"
                        />
                        <el-button size="small" @click="editItem(element)" class="item-btn">编辑</el-button>
                        <el-button size="small" type="danger" plain @click="handleDelete(element)" class="item-btn">删除</el-button>
                    </div>
                </template>
            </draggable>
        </el-card>

        <!-- 新建/编辑 Dialog -->
        <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑链接' : '新建链接'" width="550px">
            <el-form :model="form" label-width="110px" :rules="formRules" ref="formRef">
                <el-form-item label="显示名称" prop="label">
                    <el-input v-model="form.label" maxlength="100" />
                </el-form-item>
                <el-form-item label="链接类型">
                    <el-select v-model="form.type" style="width:100%">
                        <el-option v-for="(label, val) in options?.link_types || {}" :key="val" :label="label" :value="val" />
                    </el-select>
                </el-form-item>
                <el-form-item label="URL">
                    <el-input v-model="form.url" placeholder="https:// 或 /page-slug" maxlength="500" />
                </el-form-item>
                <el-form-item label="图标">
                    <el-input v-model="form.icon" placeholder="图标名称" maxlength="100" />
                    <div class="form-tip">Element Plus 图标名或 FontAwesome 类名</div>
                </el-form-item>
                <el-form-item label="打开方式">
                    <el-radio-group v-model="form.target">
                        <el-radio value="_self">当前窗口</el-radio>
                        <el-radio value="_blank">新窗口</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="分组">
                    <el-select v-model="form.group" style="width:100%">
                        <el-option v-for="g in options?.groups || []" :key="g.value" :label="g.label" :value="g.value" />
                    </el-select>
                </el-form-item>
                <el-form-item label="排序值">
                    <el-input-number v-model="form.sort_order" :min="0" :max="9999" />
                    <div class="form-tip">数值越小越靠前</div>
                </el-form-item>
                <el-form-item label="启用">
                    <el-switch v-model="form.is_active" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import draggable from 'vuedraggable'
import {
    getFooterNav, createFooterNavItem, updateFooterNavItem, deleteFooterNavItem,
    reorderFooterNav, toggleFooterNavItem, initDefaultFooterNav, getFooterNavOptions,
} from '@/api/footerNav'

const items = ref([])
const grouped = ref({ footer: [], social: [], bottom: [] })
const options = ref({ link_types: {}, social_platforms: {}, groups: [] })

const dialogVisible = ref(false)
const isEdit = ref(false)
const editingId = ref(null)
const form = ref({
    label: '', type: 'custom', url: '', icon: '', target: '_self',
    group: 'footer', sort_order: 0, is_active: true,
})
const formRules = {
    label: [{ required: true, message: '请输入显示名称' }],
}
const formRef = ref(null)
const saving = ref(false)

const activeCount = computed(() => items.value.filter(i => i.is_active).length)

onMounted(async () => {
    try {
        const res = await getFooterNavOptions()
        options.value = res.data || { link_types: {}, social_platforms: {}, groups: [] }
    } catch { /* ignore */ }
    await fetchData()
})

async function fetchData() {
    try {
        const res = await getFooterNav()
        items.value = res.data?.items || []
        grouped.value = res.data?.grouped || { footer: [], social: [], bottom: [] }
    } catch { items.value = []; grouped.value = { footer: [], social: [], bottom: [] } }
}

function typeLabel(type) {
    return options.value?.link_types?.[type] || type
}

function typeTagType(type) {
    const map = { page: 'success', custom: 'info', help: 'primary', api_docs: 'warning', status: 'danger', social: '', contact: '' }
    return map[type] || 'info'
}

function openCreateDialog() {
    isEdit.value = false
    editingId.value = null
    form.value = {
        label: '', type: 'custom', url: '', icon: '', target: '_self',
        group: 'footer', sort_order: 0, is_active: true,
    }
    dialogVisible.value = true
}

function editItem(row) {
    isEdit.value = true
    editingId.value = row.id
    form.value = {
        label: row.label, type: row.type, url: row.url || '', icon: row.icon || '',
        target: row.target || '_self', group: row.group || 'footer',
        sort_order: row.sort_order || 0, is_active: row.is_active,
    }
    dialogVisible.value = true
}

async function handleSave() {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return
    saving.value = true
    try {
        if (isEdit.value && editingId.value) {
            await updateFooterNavItem(editingId.value, form.value)
            ElMessage.success('链接已更新')
        } else {
            await createFooterNavItem(form.value)
            ElMessage.success('链接已创建')
        }
        dialogVisible.value = false
        await fetchData()
    } catch (e) {
        ElMessage.error(e.message || '操作失败')
    }
    saving.value = false
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(`确定删除「${row.label}」？`, '确认')
        await deleteFooterNavItem(row.id)
        ElMessage.success('已删除')
        await fetchData()
    } catch { /* ignore */ }
}

async function handleToggle(row) {
    try {
        const res = await toggleFooterNavItem(row.id)
        row.is_active = res.data?.is_active ?? !row.is_active
        ElMessage.success(row.is_active ? '已启用' : '已禁用')
    } catch (e) {
        ElMessage.error(e.message || '操作失败')
    }
}

async function handleReorder() {
    try {
        const allItems = [
            ...(grouped.value.footer || []),
            ...(grouped.value.social || []),
            ...(grouped.value.bottom || []),
        ]
        const reorderData = allItems.map((item, idx) => ({
            id: item.id,
            sort_order: (idx + 1) * 10,
        }))
        await reorderFooterNav(reorderData)
    } catch { /* ignore */ }
}

async function handleInitDefaults() {
    try {
        const res = await initDefaultFooterNav()
        ElMessage.success(`已初始化 ${res.data?.created || 0} 个默认链接`)
        await fetchData()
    } catch (e) {
        ElMessage.error(e.message || '初始化失败')
    }
}
</script>

<style scoped>
.footer-nav-container {
    padding: 20px;
}

.alert-info {
    margin-top: 16px;
    margin-bottom: 16px;
}

.stat-cards {
    margin-bottom: 16px;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    text-align: center;
}

.stat-label {
    font-size: 13px;
    color: #909399;
    text-align: center;
    margin-top: 6px;
}

.text-success { color: #67c23a; }

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.group-title {
    margin: 16px 0 8px 0;
    font-size: 16px;
}

.drag-list {
    min-height: 40px;
    border: 1px dashed #dcdfe6;
    border-radius: 6px;
    padding: 4px;
    margin-bottom: 16px;
}

.drag-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: #f5f7fa;
    border-radius: 4px;
    margin: 4px 0;
    transition: background 0.2s;
}

.drag-item:hover {
    background: #ecf5ff;
}

.drag-handle {
    cursor: grab;
    color: #c0c4cc;
    font-size: 16px;
    user-select: none;
}

.drag-handle:active {
    cursor: grabbing;
}

.item-type {
    flex-shrink: 0;
    min-width: 70px;
    text-align: center;
}

.item-label {
    flex-shrink: 0;
    min-width: 120px;
    font-weight: 500;
}

.item-url {
    flex: 1;
    color: #909399;
    font-size: 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.item-toggle {
    flex-shrink: 0;
}

.item-btn {
    flex-shrink: 0;
}

.form-tip {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
}
</style>
