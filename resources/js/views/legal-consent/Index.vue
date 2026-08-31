<template>
    <div class="legal-consent-page">
        <el-tabs v-model="activeTab">
            <el-tab-pane :label="t(`${P}.tabs.manage`)" name="manage">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t(`${P}.manage_title`) }}</span>
                            <el-button type="primary" :icon="Plus" @click="openCreateDialog">
                                {{ t(`${P}.new_version`) }}
                            </el-button>
                        </div>
                    </template>

                    <el-table :data="consents" v-loading="loading" stripe style="width: 100%">
                        <el-table-column prop="id" :label="t(`${P}.cols.id`)" width="60" />
                        <el-table-column :label="t(`${P}.cols.type`)" width="160">
                            <template #default="{ row }">
                                <el-tag :type="row.type === 'privacy_policy' ? 'primary' : 'success'" size="small">
                                    {{ typeLabel(row.type) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="version" :label="t(`${P}.cols.version`)" width="100" />
                        <el-table-column :label="t(`${P}.cols.current`)" width="100">
                            <template #default="{ row }">
                                <el-tag v-if="row.is_current" type="success" size="small" effect="dark">{{ t(`${P}.active`) }}</el-tag>
                                <el-tag v-else type="info" size="small">{{ t(`${P}.draft`) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${P}.cols.effective`)" width="180">
                            <template #default="{ row }">
                                {{ row.effective_at ? formatDate(row.effective_at) : '-' }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${P}.cols.created`)" width="180">
                            <template #default="{ row }">
                                {{ formatDate(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${P}.cols.actions`)" width="220" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="viewConsent(row)">{{ t('actions.view') }}</el-button>
                                <el-button
                                    v-if="!row.is_current"
                                    size="small"
                                    type="warning"
                                    @click="handlePublish(row)"
                                >
                                    {{ t(`${P}.publish`) }}
                                </el-button>
                                <el-popconfirm
                                    v-if="!row.is_current"
                                    :title="t(`${P}.confirm_delete`)"
                                    @confirm="handleDelete(row)"
                                >
                                    <template #reference>
                                        <el-button size="small" type="danger" plain>{{ t('actions.delete') }}</el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-empty v-if="!loading && !consents.length" :description="t(`${P}.empty_versions`)" />

                    <el-pagination
                        v-if="total > 0"
                        v-model:current-page="currentPage"
                        :page-size="perPage"
                        :total="total"
                        layout="prev, pager, next"
                        class="mt-4 justify-center"
                        @current-change="fetchConsents"
                    />
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t(`${P}.tabs.logs`)" name="logs">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t(`${P}.logs_title`) }}</span>
                        </div>
                    </template>

                    <el-table :data="logs" v-loading="loadingLogs" stripe style="width: 100%">
                        <el-table-column :label="t(`${P}.cols.user`)" width="160">
                            <template #default="{ row }">
                                {{ row.user?.name || '-' }}
                                <span class="text-muted">({{ row.user?.email || '-' }})</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${P}.cols.consent_type`)" width="160">
                            <template #default="{ row }">
                                <el-tag :type="row.legalConsent?.type === 'privacy_policy' ? 'primary' : 'success'" size="small">
                                    {{ typeLabel(row.legalConsent?.type) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${P}.cols.version`)" width="80" prop="legalConsent?.version" />
                        <el-table-column :label="t(`${P}.cols.consented`)" width="180">
                            <template #default="{ row }">
                                {{ formatDate(row.consented_at || row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${P}.cols.ip`)" width="150" prop="ip_address" />
                    </el-table>

                    <el-empty v-if="!loadingLogs && !logs.length" :description="t(`${P}.empty_logs`)" />
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <el-dialog v-model="dialogVisible" :title="isEditing ? t(`${P}.edit_title`) : t(`${P}.create_title`)" width="700px">
            <el-form :model="form" label-width="120px">
                <el-form-item :label="t(`${P}.form.type`)">
                    <el-select v-model="form.type" :disabled="isEditing">
                        <el-option :label="t(`${P}.types.privacy_full`)" value="privacy_policy" />
                        <el-option :label="t(`${P}.types.terms_full`)" value="terms_of_service" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t(`${P}.form.version`)">
                    <el-input v-model="form.version" :placeholder="t(`${P}.form.version_ph`)" :disabled="isEditing" />
                </el-form-item>
                <el-form-item :label="t(`${P}.form.effective`)" v-if="!isEditing">
                    <el-date-picker
                        v-model="form.effective_at"
                        type="datetime"
                        :placeholder="t(`${P}.form.effective_now`)"
                        clearable
                        value-format="YYYY-MM-DD HH:mm:ss"
                    />
                </el-form-item>
                <el-form-item :label="t(`${P}.form.content`)">
                    <el-input
                        v-model="form.content"
                        type="textarea"
                        :rows="12"
                        :placeholder="t(`${P}.form.content_ph`)"
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">
                    {{ isEditing ? t('actions.save') : t('actions.create') }}
                </el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="viewDialogVisible" :title="t(`${P}.view_title`)" width="700px">
            <template v-if="viewingConsent">
                <div class="view-meta">
                    <el-tag :type="viewingConsent.type === 'privacy_policy' ? 'primary' : 'success'" size="small">
                        {{ typeLabel(viewingConsent.type) }}
                    </el-tag>
                    <span class="ml-2">v{{ viewingConsent.version }}</span>
                    <el-tag v-if="viewingConsent.is_current" type="success" size="small" class="ml-2" effect="dark">{{ t(`${P}.active`) }}</el-tag>
                </div>
                <el-divider />
                <div class="view-content">{{ viewingConsent.content }}</div>
            </template>
            <template #footer>
                <el-button @click="viewDialogVisible = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
    getLegalConsents,
    createLegalConsent,
    updateLegalConsent,
    publishLegalConsent,
    getConsentLogs,
    deleteLegalConsent,
} from '@/api/legal-consent'

const { t, locale } = useI18n()
const P = 'legal_consent_page'

const dateLocale = computed(() => (locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'))

const activeTab = ref('manage')

const consents = ref([])
const loading = ref(false)
const total = ref(0)
const currentPage = ref(1)
const perPage = ref(20)
const typeFilter = ref('')

const logs = ref([])
const loadingLogs = ref(false)

const dialogVisible = ref(false)
const isEditing = ref(false)
const editingId = ref(null)
const saving = ref(false)
const form = ref({
    type: 'privacy_policy',
    version: '',
    content: '',
    effective_at: null,
})

const viewDialogVisible = ref(false)
const viewingConsent = ref(null)

function typeLabel(type) {
    if (type === 'privacy_policy') return t(`${P}.types.privacy`)
    if (type === 'terms_of_service') return t(`${P}.types.terms`)
    return type || '-'
}

async function fetchConsents() {
    loading.value = true
    try {
        const params = { page: currentPage.value, per_page: perPage.value }
        if (typeFilter.value) params.type = typeFilter.value
        const res = await getLegalConsents(params)
        consents.value = res.data?.data?.data || []
        total.value = res.data?.data?.total || 0
    } catch (e) {
        ElMessage.error(t(`${P}.messages.fetch_failed`))
    } finally {
        loading.value = false
    }
}

async function fetchLogs() {
    loadingLogs.value = true
    try {
        const res = await getConsentLogs({ per_page: 50 })
        logs.value = res.data?.data?.data || []
    } catch (e) {
        ElMessage.error(t(`${P}.messages.fetch_logs_failed`))
    } finally {
        loadingLogs.value = false
    }
}

function openCreateDialog() {
    isEditing.value = false
    editingId.value = null
    form.value = { type: 'privacy_policy', version: '', content: '', effective_at: null }
    dialogVisible.value = true
}

function viewConsent(row) {
    viewingConsent.value = row
    viewDialogVisible.value = true
}

async function handleSave() {
    saving.value = true
    try {
        if (isEditing.value) {
            await updateLegalConsent(editingId.value, { content: form.value.content })
            ElMessage.success(t(`${P}.messages.updated`))
        } else {
            await createLegalConsent(form.value)
            ElMessage.success(t(`${P}.messages.created`))
        }
        dialogVisible.value = false
        fetchConsents()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${P}.messages.save_failed`))
    } finally {
        saving.value = false
    }
}

async function handlePublish(row) {
    try {
        await ElMessageBox.confirm(
            t(`${P}.confirm_publish`, { type: typeLabel(row.type), version: row.version }),
            t(`${P}.publish_title`),
            { confirmButtonText: t(`${P}.confirm_publish_btn`), cancelButtonText: t('actions.cancel'), type: 'warning' },
        )
        await publishLegalConsent(row.id)
        ElMessage.success(t(`${P}.messages.published`))
        fetchConsents()
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(t(`${P}.messages.publish_failed`))
        }
    }
}

async function handleDelete(row) {
    try {
        await deleteLegalConsent(row.id)
        ElMessage.success(t(`${P}.messages.deleted`))
        fetchConsents()
    } catch (e) {
        ElMessage.error(t(`${P}.messages.delete_failed`))
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleString(dateLocale.value, {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    })
}

onMounted(() => {
    fetchConsents()
    fetchLogs()
})
</script>

<style scoped>
.legal-consent-page {
    max-width: 1100px;
    margin: 0 auto;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.text-muted {
    color: #999;
    font-size: 12px;
}

.ml-2 {
    margin-left: 8px;
}

.mt-4 {
    margin-top: 16px;
}

.justify-center {
    display: flex;
    justify-content: center;
}

.view-meta {
    margin-bottom: 8px;
    display: flex;
    align-items: center;
}

.view-content {
    white-space: pre-wrap;
    font-size: 14px;
    line-height: 1.8;
    max-height: 60vh;
    overflow-y: auto;
}
</style>
