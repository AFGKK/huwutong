<template>
    <div class="hsm-page">
        <div class="page-header">
            <h2>{{ t('hsm_page.title') }}</h2>
            <el-button type="primary" @click="refresh">
                <el-icon><Refresh /></el-icon> {{ t('hsm_page.refresh') }}
            </el-button>
        </div>

        <el-row :gutter="16">
            <el-col :span="8">
                <el-card class="mb-4">
                    <template #header><span>{{ t('hsm_page.status_title') }}</span></template>
                    <div class="status-items">
                        <div class="status-item">
                            <span>{{ t('hsm_page.enabled') }}</span>
                            <el-tag :type="health.healthy ? 'success' : 'danger'" size="small">
                                {{ health.healthy ? t('hsm_page.on') : t('hsm_page.off') }}
                            </el-tag>
                        </div>
                        <div class="status-item">
                            <span>{{ t('hsm_page.provider') }}</span>
                            <code>{{ health.provider }}</code>
                        </div>
                        <div class="status-item">
                            <span>{{ t('hsm_page.health') }}</span>
                            <el-tag :type="health.healthy ? 'success' : 'warning'" size="small">
                                {{ health.healthy ? t('hsm_page.healthy') : t('hsm_page.unhealthy') }}
                            </el-tag>
                        </div>
                        <div class="status-item">
                            <span>{{ t('hsm_page.message') }}</span>
                            <span class="text-secondary">{{ health.message }}</span>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <el-col :span="8">
                <el-card class="mb-4">
                    <template #header><span>{{ t('hsm_page.stats_title') }}</span></template>
                    <div class="status-items">
                        <div class="status-item">
                            <span>{{ t('hsm_page.total_keys') }}</span>
                            <strong>{{ stats.total_keys }}</strong>
                        </div>
                        <div class="status-item">
                            <span>{{ t('hsm_page.active_keys') }}</span>
                            <strong>{{ stats.active_keys }}</strong>
                        </div>
                        <div class="status-item">
                            <span>{{ t('hsm_page.total_sigs') }}</span>
                            <strong>{{ stats.total_signatures }}</strong>
                        </div>
                        <div class="status-item">
                            <span>{{ t('hsm_page.algorithm') }}</span>
                            <el-tag size="small">Ed25519</el-tag>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <el-col :span="8">
                <el-card class="mb-4">
                    <template #header><span>{{ t('hsm_page.actions_title') }}</span></template>
                    <div class="quick-actions">
                        <el-button type="primary" @click="showInitDialog = true" class="action-btn">
                            {{ t('hsm_page.init_key') }}
                        </el-button>
                        <el-button type="warning" @click="handleRotate" class="action-btn">
                            {{ t('hsm_page.rotate') }}
                        </el-button>
                        <el-button @click="showSignDialog = true" class="action-btn">
                            {{ t('hsm_page.test_sign') }}
                        </el-button>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card>
            <template #header><span>{{ t('hsm_page.keys_title') }}</span></template>
            <el-table :data="keys" stripe v-loading="loading">
                <el-table-column prop="id" label="ID" width="60" />
                <el-table-column prop="key_label" :label="t('hsm_page.cols.label')" width="140">
                    <template #default="{ row }">
                        <code>{{ row.key_label }}</code>
                    </template>
                </el-table-column>
                <el-table-column prop="algorithm" :label="t('hsm_page.cols.algorithm')" width="100" />
                <el-table-column prop="provider" :label="t('hsm_page.cols.provider')" width="120" />
                <el-table-column :label="t('hsm_page.cols.public_key')" min-width="200">
                    <template #default="{ row }">
                        <code class="key-truncate">{{ row.public_key?.substring(0, 40) }}...</code>
                    </template>
                </el-table-column>
                <el-table-column prop="sign_count" :label="t('hsm_page.cols.sign_count')" width="100" align="right" />
                <el-table-column :label="t('hsm_page.cols.active')" width="70" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                            {{ row.is_active ? t('hsm_page.yes') : t('hsm_page.no') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" :label="t('hsm_page.cols.created')" width="170" />
            </el-table>
        </el-card>

        <el-dialog v-model="showInitDialog" :title="t('hsm_page.init_title')" width="400px">
            <el-form label-width="100px">
                <el-form-item :label="t('hsm_page.key_label')">
                    <el-input v-model="initForm.label" placeholder="license-v1" />
                </el-form-item>
                <el-form-item :label="t('hsm_page.algorithm')">
                    <el-select v-model="initForm.algorithm">
                        <el-option :label="t('hsm_page.algo_ed25519')" value="Ed25519" />
                        <el-option :label="t('hsm_page.algo_rsa')" value="RSA" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showInitDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleInit" :loading="initLoading">{{ t('actions.create') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="showSignDialog" :title="t('hsm_page.sign_title')" width="500px">
            <el-form label-width="100px">
                <el-form-item label="License Key">
                    <el-input v-model="signForm.licenseKey" placeholder="HWT-ENT-xxxx" />
                </el-form-item>
                <el-form-item :label="t('hsm_page.key')">
                    <el-select v-model="signForm.keyId" filterable>
                        <el-option v-for="k in keys" :key="k.id" :label="`#${k.id} ${k.key_label}`" :value="k.id" />
                    </el-select>
                </el-form-item>
                <div v-if="signResult" class="sign-result">
                    <p class="result-label">{{ t('hsm_page.sign_result') }}</p>
                    <code class="result-value">{{ signResult }}</code>
                </div>
            </el-form>
            <template #footer>
                <el-button @click="showSignDialog = false">{{ t('actions.close') }}</el-button>
                <el-button type="primary" @click="handleTestSign" :loading="signLoading">{{ t('hsm_page.sign') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { Refresh } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import hsmApi from '@/api/hsm'

const { t } = useI18n()

const loading = ref(false)
const keys = ref([])
const health = ref({})
const stats = ref({})
const showInitDialog = ref(false)
const showSignDialog = ref(false)
const initLoading = ref(false)
const signLoading = ref(false)
const signResult = ref('')

const initForm = ref({ label: 'license-v1', algorithm: 'Ed25519' })
const signForm = ref({ licenseKey: '', keyId: null })

async function refresh() {
    loading.value = true
    try {
        const [healthRes, statsRes, keysRes] = await Promise.all([
            hsmApi.health(),
            hsmApi.stats(),
            hsmApi.keys(),
        ])
        health.value = healthRes.data?.data || {}
        stats.value = statsRes.data?.data || {}
        keys.value = keysRes.data?.data || []
    } catch {
        health.value = { healthy: false, message: t('hsm_page.api_unavailable') }
    } finally {
        loading.value = false
    }
}

async function handleInit() {
    initLoading.value = true
    try {
        await hsmApi.init(initForm.value)
        ElMessage.success(t('hsm_page.messages.created'))
        showInitDialog.value = false
        refresh()
    } catch (e) {
        ElMessage.error(t('hsm_page.messages.create_failed') + ': ' + (e.response?.data?.message || e.message))
    } finally {
        initLoading.value = false
    }
}

async function handleRotate() {
    try {
        await ElMessageBox.confirm(t('hsm_page.messages.rotate_confirm'), t('hsm_page.rotate_confirm_title'))
        await hsmApi.rotate({ label: 'license-v1' })
        ElMessage.success(t('hsm_page.messages.rotated'))
        refresh()
    } catch { /* cancelled */ }
}

async function handleTestSign() {
    if (!signForm.value.licenseKey || !signForm.value.keyId) {
        ElMessage.warning(t('hsm_page.messages.need_sign_input'))
        return
    }
    signLoading.value = true
    try {
        const { data } = await hsmApi.sign(signForm.value)
        signResult.value = data?.data?.signature || ''
        ElMessage.success(t('hsm_page.messages.signed'))
    } catch (e) {
        ElMessage.error(t('hsm_page.messages.sign_failed') + ': ' + (e.response?.data?.message || e.message))
    } finally {
        signLoading.value = false
    }
}

onMounted(refresh)
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.mb-4 { margin-bottom: 16px; }
.status-items { display: flex; flex-direction: column; gap: 12px; }
.status-item { display: flex; justify-content: space-between; align-items: center; font-size: 14px; }
.status-item .text-secondary { color: #909399; }
.quick-actions { display: flex; flex-direction: column; gap: 8px; }
.action-btn { width: 100%; }
.key-truncate { font-size: 12px; }
.sign-result { margin-top: 16px; padding: 12px; background: #f5f7fa; border-radius: 4px; }
.result-label { font-size: 13px; margin-bottom: 4px; }
.result-value { font-size: 12px; word-break: break-all; }
</style>
