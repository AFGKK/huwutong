<template>
    <div class="footer-nav-container">
        <el-page-header :content="t('footer_nav_page.title')" @back="$router.push('/admin/dashboard')" />

        <el-alert
            :title="t('footer_nav_page.alert')"
            type="info"
            show-icon
            :closable="false"
            class="alert-info"
        />

        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ items.length }}</div>
                    <div class="stat-label">{{ t('footer_nav_page.stats.total') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-success">{{ activeCount }}</div>
                    <div class="stat-label">{{ t('footer_nav_page.stats.active') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ grouped.footer?.length || 0 }}</div>
                    <div class="stat-label">{{ t('footer_nav_page.groups.footer') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ grouped.social?.length || 0 }}</div>
                    <div class="stat-label">{{ t('footer_nav_page.groups.social') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-card>
            <template #header>
                <div class="card-header">
                    <span>{{ t('footer_nav_page.manage_title') }}</span>
                    <div>
                        <el-button size="small" @click="handleInitDefaults">{{ t('footer_nav_page.init_defaults') }}</el-button>
                        <el-button size="small" type="primary" @click="openCreateDialog">{{ t('footer_nav_page.new_link') }}</el-button>
                    </div>
                </div>
            </template>

            <h3 class="group-title">{{ t('footer_nav_page.groups.footer') }}</h3>
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
                        <el-button size="small" @click="editItem(element)" class="item-btn">{{ t('actions.edit') }}</el-button>
                        <el-button size="small" type="danger" plain @click="handleDelete(element)" class="item-btn">{{ t('actions.delete') }}</el-button>
                    </div>
                </template>
            </draggable>

            <h3 class="group-title">{{ t('footer_nav_page.groups.social') }}</h3>
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
                        <el-button size="small" @click="editItem(element)" class="item-btn">{{ t('actions.edit') }}</el-button>
                        <el-button size="small" type="danger" plain @click="handleDelete(element)" class="item-btn">{{ t('actions.delete') }}</el-button>
                    </div>
                </template>
            </draggable>

            <h3 class="group-title">{{ t('footer_nav_page.groups.bottom') }}</h3>
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
                        <el-button size="small" @click="editItem(element)" class="item-btn">{{ t('actions.edit') }}</el-button>
                        <el-button size="small" type="danger" plain @click="handleDelete(element)" class="item-btn">{{ t('actions.delete') }}</el-button>
                    </div>
                </template>
            </draggable>
        </el-card>

        <el-dialog v-model="dialogVisible" :title="isEdit ? t('footer_nav_page.edit_link') : t('footer_nav_page.new_link')" width="550px">
            <el-form :model="form" label-width="110px" :rules="formRules" ref="formRef">
                <el-form-item :label="t('footer_nav_page.form.label')" prop="label">
                    <el-input v-model="form.label" maxlength="100" />
                </el-form-item>
                <el-form-item :label="t('footer_nav_page.form.type')">
                    <el-select v-model="form.type" style="width:100%">
                        <el-option v-for="(label, val) in options?.link_types || {}" :key="val" :label="label" :value="val" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('footer_nav_page.form.url')">
                    <el-input v-model="form.url" :placeholder="t('footer_nav_page.form.url_ph')" maxlength="500" />
                </el-form-item>
                <el-form-item :label="t('footer_nav_page.form.icon')">
                    <el-input v-model="form.icon" :placeholder="t('footer_nav_page.form.icon_ph')" maxlength="100" />
                    <div class="form-tip">{{ t('footer_nav_page.form.icon_tip') }}</div>
                </el-form-item>
                <el-form-item :label="t('footer_nav_page.form.target')">
                    <el-radio-group v-model="form.target">
                        <el-radio value="_self">{{ t('footer_nav_page.form.target_self') }}</el-radio>
                        <el-radio value="_blank">{{ t('footer_nav_page.form.target_blank') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t('footer_nav_page.form.group')">
                    <el-select v-model="form.group" style="width:100%">
                        <el-option v-for="g in options?.groups || []" :key="g.value" :label="g.label" :value="g.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('footer_nav_page.form.sort_order')">
                    <el-input-number v-model="form.sort_order" :min="0" :max="9999" />
                    <div class="form-tip">{{ t('footer_nav_page.form.sort_tip') }}</div>
                </el-form-item>
                <el-form-item :label="t('actions.enable')">
                    <el-switch v-model="form.is_active" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import draggable from 'vuedraggable'
import {
    getFooterNav, createFooterNavItem, updateFooterNavItem, deleteFooterNavItem,
    reorderFooterNav, toggleFooterNavItem, initDefaultFooterNav, getFooterNavOptions,
} from '@/api/footerNav'

const { t } = useI18n()

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
const formRules = computed(() => ({
    label: [{ required: true, message: t('footer_nav_page.validation.label_required') }],
}))
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
            ElMessage.success(t('footer_nav_page.messages.updated'))
        } else {
            await createFooterNavItem(form.value)
            ElMessage.success(t('footer_nav_page.messages.created'))
        }
        dialogVisible.value = false
        await fetchData()
    } catch (e) {
        ElMessage.error(e.message || t('messages.failed'))
    }
    saving.value = false
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(t('footer_nav_page.messages.delete_confirm', { label: row.label }), t('actions.confirm'))
        await deleteFooterNavItem(row.id)
        ElMessage.success(t('footer_nav_page.messages.deleted'))
        await fetchData()
    } catch { /* ignore */ }
}

async function handleToggle(row) {
    try {
        const res = await toggleFooterNavItem(row.id)
        row.is_active = res.data?.is_active ?? !row.is_active
        ElMessage.success(row.is_active ? t('footer_nav_page.messages.enabled') : t('footer_nav_page.messages.disabled'))
    } catch (e) {
        ElMessage.error(e.message || t('messages.failed'))
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
        ElMessage.success(t('footer_nav_page.messages.init_done', { n: res.data?.created || 0 }))
        await fetchData()
    } catch (e) {
        ElMessage.error(e.message || t('footer_nav_page.messages.init_failed'))
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
    background: #f1f5f9;
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
