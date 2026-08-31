<template>
    <div class="password-policy-page">
        <el-tabs v-model="activeTab">
            <el-tab-pane :label="t(`${P}.tabs.config`)" name="config">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t(`${P}.config_title`) }}</span>
                        </div>
                    </template>

                    <el-form :model="form" label-width="180px" v-loading="loading">
                        <el-divider content-position="left">{{ t(`${P}.sections.length`) }}</el-divider>
                        <el-form-item :label="t(`${P}.fields.min_length`)">
                            <el-input-number v-model="form.min_length" :min="4" :max="256" />
                            <span class="form-help">{{ t(`${P}.help.chars`) }}</span>
                        </el-form-item>
                        <el-form-item :label="t(`${P}.fields.max_length`)">
                            <el-input-number v-model="form.max_length" :min="8" :max="256" />
                            <span class="form-help">{{ t(`${P}.help.chars`) }}</span>
                        </el-form-item>

                        <el-divider content-position="left">{{ t(`${P}.sections.complexity`) }}</el-divider>
                        <el-form-item :label="t(`${P}.fields.require_uppercase`)">
                            <el-switch v-model="form.require_uppercase" />
                        </el-form-item>
                        <el-form-item :label="t(`${P}.fields.require_lowercase`)">
                            <el-switch v-model="form.require_lowercase" />
                        </el-form-item>
                        <el-form-item :label="t(`${P}.fields.require_number`)">
                            <el-switch v-model="form.require_number" />
                        </el-form-item>
                        <el-form-item :label="t(`${P}.fields.require_special`)">
                            <el-switch v-model="form.require_special" />
                        </el-form-item>

                        <el-divider content-position="left">{{ t(`${P}.sections.history`) }}</el-divider>
                        <el-form-item :label="t(`${P}.fields.history_count`)">
                            <el-input-number v-model="form.history_count" :min="0" :max="50" />
                            <span class="form-help">{{ t(`${P}.help.history`) }}</span>
                        </el-form-item>
                        <el-form-item :label="t(`${P}.fields.expiry_days`)">
                            <el-input-number v-model="form.expiry_days" :min="0" :max="365" />
                            <span class="form-help">{{ t(`${P}.help.expiry`) }}</span>
                        </el-form-item>

                        <el-divider content-position="left">{{ t(`${P}.sections.lockout`) }}</el-divider>
                        <el-form-item :label="t(`${P}.fields.lockout_max`)">
                            <el-input-number v-model="form.lockout_max_attempts" :min="1" :max="100" />
                            <span class="form-help">{{ t(`${P}.help.times`) }}</span>
                        </el-form-item>
                        <el-form-item :label="t(`${P}.fields.lockout_duration`)">
                            <el-input-number v-model="form.lockout_duration_minutes" :min="1" :max="1440" />
                            <span class="form-help">{{ t(`${P}.help.minutes`) }}</span>
                        </el-form-item>

                        <el-divider content-position="left">{{ t(`${P}.sections.active`) }}</el-divider>
                        <el-form-item :label="t(`${P}.fields.is_active`)">
                            <el-switch v-model="form.is_active" />
                        </el-form-item>

                        <el-form-item>
                            <el-button type="primary" @click="handleSave" :loading="saving">
                                {{ t(`${P}.save`) }}
                            </el-button>
                            <el-button @click="fetchConfig">{{ t('actions.reset') }}</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t(`${P}.tabs.locked`)" name="locked">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t(`${P}.locked_title`) }}</span>
                            <el-tag type="danger">{{ t(`${P}.locked_count`, { n: total }) }}</el-tag>
                        </div>
                    </template>

                    <el-table :data="lockedAccounts" v-loading="loadingLocked" stripe style="width: 100%">
                        <el-table-column prop="id" :label="t(`${P}.cols.id`)" width="80" />
                        <el-table-column prop="name" :label="t(`${P}.cols.name`)" min-width="150" />
                        <el-table-column prop="email" :label="t(`${P}.cols.email`)" min-width="200" />
                        <el-table-column :label="t(`${P}.cols.reason`)" width="200">
                            <template #default="{ row }">
                                <el-tag type="danger" size="small">
                                    {{ t(`${P}.fail_attempts`, { n: row.login_attempts || t(`${P}.multiple`) }) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${P}.cols.until`)" width="180">
                            <template #default="{ row }">
                                {{ formatDate(row.locked_until) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${P}.cols.status`)" width="120">
                            <template #default="{ row }">
                                <el-tag type="danger" size="small">
                                    {{ getRemaining(row) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${P}.cols.actions`)" width="120" fixed="right">
                            <template #default="{ row }">
                                <el-popconfirm
                                    :title="t(`${P}.confirm_unlock`)"
                                    @confirm="handleUnlock(row.id)"
                                >
                                    <template #reference>
                                        <el-button type="primary" size="small" :icon="Unlock">
                                            {{ t(`${P}.unlock`) }}
                                        </el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-empty v-if="!loadingLocked && !lockedAccounts.length" :description="t(`${P}.empty`)" />

                    <el-pagination
                        v-if="total > 0"
                        v-model:current-page="currentPage"
                        :page-size="perPage"
                        :total="total"
                        layout="prev, pager, next"
                        class="mt-4 justify-center"
                        @current-change="fetchLockedAccounts"
                    />
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Unlock } from '@element-plus/icons-vue'
import {
    getPasswordPolicy,
    updatePasswordPolicy,
    getLockedAccounts,
    unlockAccount,
} from '@/api/password-policy'

const { t, locale } = useI18n()
const P = 'password_policy_page'
const dateLocale = computed(() => (locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'))

const activeTab = ref('config')

const form = ref({
    min_length: 8,
    max_length: 128,
    require_uppercase: true,
    require_lowercase: true,
    require_number: true,
    require_special: true,
    history_count: 5,
    expiry_days: 90,
    lockout_max_attempts: 5,
    lockout_duration_minutes: 15,
    is_active: true,
})
const loading = ref(false)
const saving = ref(false)

const lockedAccounts = ref([])
const loadingLocked = ref(false)
const total = ref(0)
const currentPage = ref(1)
const perPage = ref(20)

async function fetchConfig() {
    loading.value = true
    try {
        const res = await getPasswordPolicy()
        const data = res.data?.data
        if (data) {
            form.value = {
                min_length: data.min_length ?? 8,
                max_length: data.max_length ?? 128,
                require_uppercase: data.require_uppercase ?? true,
                require_lowercase: data.require_lowercase ?? true,
                require_number: data.require_number ?? true,
                require_special: data.require_special ?? true,
                history_count: data.history_count ?? 5,
                expiry_days: data.expiry_days ?? 90,
                lockout_max_attempts: data.lockout_max_attempts ?? 5,
                lockout_duration_minutes: data.lockout_duration_minutes ?? 15,
                is_active: data.is_active ?? true,
            }
        }
    } catch (e) {
        ElMessage.error(t(`${P}.messages.fetch_failed`))
    } finally {
        loading.value = false
    }
}

async function handleSave() {
    saving.value = true
    try {
        await updatePasswordPolicy(form.value)
        ElMessage.success(t(`${P}.messages.saved`))
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${P}.messages.save_failed`))
    } finally {
        saving.value = false
    }
}

async function fetchLockedAccounts() {
    loadingLocked.value = true
    try {
        const res = await getLockedAccounts({
            page: currentPage.value,
            per_page: perPage.value,
        })
        lockedAccounts.value = res.data?.data?.data || []
        total.value = res.data?.data?.total || 0
    } catch (e) {
        ElMessage.error(t(`${P}.messages.locked_failed`))
    } finally {
        loadingLocked.value = false
    }
}

async function handleUnlock(userId) {
    try {
        await unlockAccount(userId)
        ElMessage.success(t(`${P}.messages.unlocked`))
        fetchLockedAccounts()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${P}.messages.unlock_failed`))
    }
}

function getRemaining(row) {
    if (!row.locked_until) return t(`${P}.normal`)
    const remaining = new Date(row.locked_until) - new Date()
    if (remaining <= 0) return t(`${P}.unlocking_soon`)
    const mins = Math.ceil(remaining / 60000)
    return t(`${P}.unlock_in`, { n: mins })
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleString(dateLocale.value, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    })
}

onMounted(() => {
    fetchConfig()
})
</script>

<style scoped>
.password-policy-page {
    max-width: 900px;
    margin: 0 auto;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.form-help {
    color: #999;
    font-size: 12px;
    margin-left: 8px;
}

.mt-4 {
    margin-top: 16px;
}

.justify-center {
    display: flex;
    justify-content: center;
}
</style>
