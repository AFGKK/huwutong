<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getMyNotificationPreferences,
    updateMyNotificationPreferences,
    initializeNotificationPreferences,
    updateGeneralSettings,
} from '../../api/notificationPreference.js'

const { t } = useI18n()

const preferences = ref([])
const channels = ref([])
const loading = ref(false)
const saving = ref(false)
const activeTab = ref('all')

const general = reactive({
    quiet_hours_start: null,
    quiet_hours_end: null,
    timezone: 'Asia/Shanghai',
    digest_frequency: 'none',
    in_quiet_hours: false,
})

const timezoneOptions = computed(() => [
    { value: 'Asia/Shanghai', label: t('portal.tz_shanghai') },
    { value: 'Asia/Hong_Kong', label: t('portal.tz_hongkong') },
    { value: 'Asia/Tokyo', label: t('portal.tz_tokyo') },
    { value: 'America/New_York', label: t('portal.tz_newyork') },
    { value: 'America/Los_Angeles', label: t('portal.tz_losangeles') },
    { value: 'Europe/London', label: t('portal.tz_london') },
    { value: 'Europe/Berlin', label: t('portal.tz_berlin') },
    { value: 'Australia/Sydney', label: t('portal.tz_sydney') },
    { value: 'Pacific/Auckland', label: t('portal.tz_auckland') },
])

const digestLabels = computed(() => ({
    none: t('portal.digest_none'),
    daily: t('portal.digest_daily'),
    weekly: t('portal.digest_weekly'),
    monthly: t('portal.digest_monthly'),
}))

const categoryLabels = computed(() => ({
    license_expiry: t('portal.cat_license_expiry'),
    invoice: t('portal.cat_invoice'),
    payment: t('portal.cat_payment'),
    security: t('portal.cat_security'),
    system: t('portal.cat_system'),
    promotion: t('portal.cat_promotion'),
    commission: t('portal.cat_commission'),
}))

const categoryIcons = {
    license_expiry: 'Key',
    invoice: 'Document',
    payment: 'Wallet',
    security: 'WarningFilled',
    system: 'Bell',
    promotion: 'Present',
    commission: 'Coin',
}

const channelLabels = computed(() => ({
    mail: t('portal.mail'),
    sms: t('portal.sms'),
    database: t('portal.inapp'),
}))

// 按分类组织偏好
const groupedByCategory = computed(() => {
    const groups = {}
    for (const pref of preferences.value) {
        if (!groups[pref.category]) {
            groups[pref.category] = {
                category: pref.category,
                label: categoryLabels.value[pref.category] || pref.category,
                icon: categoryIcons[pref.category] || 'Bell',
                items: [],
            }
        }
        groups[pref.category].items.push(pref)
    }
    return Object.values(groups)
})

// 过滤后的组
const filteredGroups = computed(() => {
    if (activeTab.value === 'all') return groupedByCategory.value
    return groupedByCategory.value.filter(g => g.category === activeTab.value)
})

// 计算每个分类是否全开/全关
function categoryAllEnabled(items) {
    return items.length > 0 && items.every(i => i.enabled)
}

function categoryHasEnabled(items) {
    return items.some(i => i.enabled)
}

async function loadPreferences() {
    loading.value = true
    try {
        const res = await getMyNotificationPreferences()
        preferences.value = res.data.preferences || []
        channels.value = res.data.channels || []
        const gen = res.data.general || {}
        general.quiet_hours_start = gen.quiet_hours_start || null
        general.quiet_hours_end = gen.quiet_hours_end || null
        general.timezone = gen.timezone || 'Asia/Shanghai'
        general.digest_frequency = gen.digest_frequency || 'none'
        general.in_quiet_hours = gen.in_quiet_hours || false
    } catch (e) {
        console.error('Failed to load preferences:', e)
    } finally {
        loading.value = false
    }
}

async function savePreferences() {
    saving.value = true
    try {
        await updateMyNotificationPreferences({
            preferences: preferences.value.map(p => ({
                channel: p.channel,
                category: p.category,
                enabled: p.enabled,
            })),
        })
        ElMessage.success(t('portal.notif_saved'))
    } catch (e) {
        ElMessage.error(t('portal.save_failed'))
    } finally {
        saving.value = false
    }
}

async function saveGeneralSettings() {
    saving.value = true
    try {
        await updateGeneralSettings({
            quiet_hours_start: general.quiet_hours_start || null,
            quiet_hours_end: general.quiet_hours_end || null,
            timezone: general.timezone,
            digest_frequency: general.digest_frequency,
        })
        ElMessage.success(t('portal.general_saved'))
    } catch (e) {
        ElMessage.error(t('portal.general_save_failed'))
    } finally {
        saving.value = false
    }
}

function togglePref(pref) {
    pref.enabled = !pref.enabled
}

function toggleCategory(category, enabled) {
    for (const pref of preferences.value) {
        if (pref.category === category) {
            pref.enabled = enabled
        }
    }
}

async function handleInitialize() {
    try {
        await ElMessageBox.confirm(t('portal.reset_notif_confirm'), t('actions.confirm'))
        await initializeNotificationPreferences()
        ElMessage.success(t('portal.reset_ok'))
        loadPreferences()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('portal.reset_failed'))
    }
}

async function handleResetAndSave() {
    await handleInitialize()
    // After initialize, save will be implicit
}

// 检查是否有未保存的变更
const hasUnsavedChanges = computed(() => true) // Always show save button

const categorySummaries = computed(() => {
    return groupedByCategory.value.map(g => ({
        category: g.category,
        label: g.label,
        email: g.items.find(i => i.channel === 'mail')?.enabled ?? false,
        sms: g.items.find(i => i.channel === 'sms')?.enabled ?? false,
        inapp: g.items.find(i => i.channel === 'database')?.enabled ?? false,
    }))
})

onMounted(loadPreferences)
</script>

<template>
    <div>
        <div class="mb-4">
            <h1 class="text-xl font-semibold">{{ $t('portal.notif_title') }}</h1>
            <p class="text-gray-500 text-sm mt-1">{{ $t('portal.notif_subtitle') }}</p>
        </div>

        <!-- 可用渠道概览 -->
        <el-card class="mb-5" shadow="never">
            <template #header><span class="font-semibold">{{ $t('portal.available_channels') }}</span></template>
            <el-row :gutter="12">
                <el-col :span="8" v-for="ch in channels" :key="ch.channel">
                    <div class="channel-card p-3 rounded-lg" :class="ch.verified ? 'bg-blue-50 border border-blue-200' : 'bg-gray-50 border border-gray-200'">
                        <div class="font-semibold">{{ channelLabels[ch.channel] || ch.channel }}</div>
                        <div class="text-sm text-gray-500 mt-1">{{ ch.description }}</div>
                        <el-tag v-if="ch.verified" type="success" size="small" class="mt-1">{{ $t('portal.verified') }}</el-tag>
                        <el-tag v-else type="warning" size="small" class="mt-1">{{ $t('portal.unverified') }}</el-tag>
                    </div>
                </el-col>
            </el-row>
        </el-card>

        <!-- 免打扰 & 摘要设置 (M3-29) -->
        <el-card class="mb-5" shadow="never">
            <template #header><span class="font-semibold">{{ $t('portal.quiet_hours') }}</span></template>
            <el-row :gutter="24" class="items-end">
                <el-col :span="6">
                    <el-form-item :label="$t('portal.quiet_start')">
                        <el-time-picker v-model="general.quiet_hours_start" format="HH:mm" value-format="HH:mm"
                            :placeholder="$t('portal.no_limit')" style="width:100%" is-range />
                    </el-form-item>
                </el-col>
                <el-col :span="6">
                    <el-form-item :label="$t('portal.quiet_end')">
                        <el-time-picker v-model="general.quiet_hours_end" format="HH:mm" value-format="HH:mm"
                            :placeholder="$t('portal.no_limit')" style="width:100%" is-range />
                    </el-form-item>
                </el-col>
                <el-col :span="6">
                    <el-form-item :label="$t('portal.timezone')">
                        <el-select v-model="general.timezone" style="width:100%">
                            <el-option v-for="tz in timezoneOptions" :key="tz.value" :label="tz.label" :value="tz.value" />
                        </el-select>
                    </el-form-item>
                </el-col>
                <el-col :span="6">
                    <el-form-item :label="$t('portal.digest_freq')">
                        <el-select v-model="general.digest_frequency" style="width:100%">
                            <el-option v-for="(label, val) in digestLabels" :key="val" :label="label" :value="val" />
                        </el-select>
                    </el-form-item>
                </el-col>
            </el-row>
            <div class="mt-2 flex items-center gap-4">
                <el-tag v-if="general.in_quiet_hours" type="warning" size="small">{{ $t('portal.in_quiet_now') }}</el-tag>
                <el-tag v-else type="success" size="small">{{ $t('portal.notif_normal') }}</el-tag>
                <el-button size="small" type="primary" plain @click="saveGeneralSettings" :loading="saving">{{ $t('portal.save_general') }}</el-button>
            </div>
        </el-card>

        <!-- 操作栏 -->
        <div class="flex items-center justify-between mb-4">
            <el-radio-group v-model="activeTab" size="small">
                <el-radio-button value="all">{{ $t('portal.all') }}</el-radio-button>
                <el-radio-button v-for="g in groupedByCategory" :key="g.category" :value="g.category">
                    {{ g.label }}
                </el-radio-button>
            </el-radio-group>
            <div class="flex gap-2">
                <el-button size="small" @click="handleInitialize">{{ $t('portal.restore_default') }}</el-button>
                <el-button type="primary" size="small" @click="savePreferences" :loading="saving">{{ $t('portal.save_settings') }}</el-button>
            </div>
        </div>

        <!-- 通知偏好矩阵 -->
        <div v-loading="loading">
            <el-card v-for="group in filteredGroups" :key="group.category" class="mb-4" shadow="never">
                <template #header>
                    <div class="flex items-center justify-between">
                        <span class="font-semibold">{{ group.label }}</span>
                        <div class="flex gap-2 items-center">
                            <span class="text-sm text-gray-400">{{ $t('portal.select_all') }}</span>
                            <el-switch
                                :model-value="categoryAllEnabled(group.items)"
                                size="small"
                                :loading="false"
                                @change="(val) => toggleCategory(group.category, val)"
                            />
                        </div>
                    </div>
                </template>

                <el-table :data="group.items" stripe>
                    <el-table-column :label="$t('portal.notif_channel')" width="180">
                        <template #default="{ row }">
                            <div class="flex items-center gap-2">
                                <span>{{ channelLabels[row.channel] || row.channel }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('portal.notif_type')" min-width="200">
                        <template #default="{ row }">
                            <span>{{ row.label || categoryLabels[row.category] || row.category }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('portal.enable')" width="120" align="center">
                        <template #default="{ row }">
                            <el-switch v-model="row.enabled" @click.stop />
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('portal.description')" min-width="200">
                        <template #default="{ row }">
                            <span class="text-gray-400 text-sm">
                                <template v-if="row.channel === 'mail'">{{ $t('portal.mail_hint') }}</template>
                                <template v-else-if="row.channel === 'sms'">{{ $t('portal.sms_hint') }}</template>
                                <template v-else>{{ $t('portal.inapp_hint') }}</template>
                            </span>
                        </template>
                    </el-table-column>
                </el-table>
            </el-card>

            <el-empty v-if="!loading && groupedByCategory.length === 0" :description="$t('portal.no_notif_cats')" />
        </div>

        <!-- 简略概览表 -->
        <el-card class="mb-4" shadow="never" v-if="categorySummaries.length">
            <template #header><span class="font-semibold">{{ $t('portal.overview') }}</span></template>
            <el-table :data="categorySummaries" stripe>
                <el-table-column prop="label" :label="$t('portal.notif_type')" min-width="160" />
                <el-table-column :label="$t('portal.mail')" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.email" type="success" size="small">✓</el-tag>
                        <el-tag v-else type="info" size="small">✗</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.sms')" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.sms" type="success" size="small">✓</el-tag>
                        <el-tag v-else type="info" size="small">✗</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.inapp')" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.inapp" type="success" size="small">✓</el-tag>
                        <el-tag v-else type="info" size="small">✗</el-tag>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
    </div>
</template>

<style scoped>
.channel-card {
    border-radius: 10px;
}
</style>
