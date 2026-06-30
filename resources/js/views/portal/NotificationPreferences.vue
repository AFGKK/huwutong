<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getMyNotificationPreferences,
    updateMyNotificationPreferences,
    initializeNotificationPreferences,
    updateGeneralSettings,
} from '../../api/notificationPreference.js'

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

// 时区选项
const timezoneOptions = [
    { value: 'Asia/Shanghai', label: '中国标准时间 (UTC+8)' },
    { value: 'Asia/Hong_Kong', label: '香港时间 (UTC+8)' },
    { value: 'Asia/Tokyo', label: '日本时间 (UTC+9)' },
    { value: 'America/New_York', label: '美东时间 (UTC-5)' },
    { value: 'America/Los_Angeles', label: '美西时间 (UTC-8)' },
    { value: 'Europe/London', label: '伦敦时间 (UTC+0)' },
    { value: 'Europe/Berlin', label: '柏林时间 (UTC+1)' },
    { value: 'Australia/Sydney', label: '悉尼时间 (UTC+10)' },
    { value: 'Pacific/Auckland', label: '奥克兰时间 (UTC+12)' },
]

const digestLabels = {
    none: '不发送摘要',
    daily: '每日摘要',
    weekly: '每周摘要',
    monthly: '每月摘要',
}

// 所有分类名称映射
const categoryLabels = {
    license_expiry: 'License 到期提醒',
    invoice: '发票/账单通知',
    payment: '支付通知',
    security: '安全提醒',
    system: '系统公告',
    promotion: '营销推广',
    commission: '佣金通知',
}

const categoryIcons = {
    license_expiry: 'Key',
    invoice: 'Document',
    payment: 'Wallet',
    security: 'WarningFilled',
    system: 'Bell',
    promotion: 'Present',
    commission: 'Coin',
}

const channelLabels = {
    mail: '📧 邮件',
    sms: '📱 短信',
    database: '🔔 站内信',
}

// 按分类组织偏好
const groupedByCategory = computed(() => {
    const groups = {}
    for (const pref of preferences.value) {
        if (!groups[pref.category]) {
            groups[pref.category] = {
                category: pref.category,
                label: categoryLabels[pref.category] || pref.category,
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
        ElMessage.success('通知偏好已保存')
    } catch (e) {
        ElMessage.error('保存失败')
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
        ElMessage.success('通用设置已保存')
    } catch (e) {
        ElMessage.error('保存通用设置失败')
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
        await ElMessageBox.confirm('将重置所有通知偏好为默认值，确定继续？', '确认')
        await initializeNotificationPreferences()
        ElMessage.success('已重置为默认设置')
        loadPreferences()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('重置失败')
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
            <h1 class="text-xl font-semibold">通知偏好设置</h1>
            <p class="text-gray-500 text-sm mt-1">管理您希望接收哪些类型的通知，以及通过什么渠道接收。</p>
        </div>

        <!-- 可用渠道概览 -->
        <el-card class="mb-5" shadow="never">
            <template #header><span class="font-semibold">可用通知渠道</span></template>
            <el-row :gutter="12">
                <el-col :span="8" v-for="ch in channels" :key="ch.channel">
                    <div class="channel-card p-3 rounded-lg" :class="ch.verified ? 'bg-blue-50 border border-blue-200' : 'bg-gray-50 border border-gray-200'">
                        <div class="font-semibold">{{ channelLabels[ch.channel] || ch.channel }}</div>
                        <div class="text-sm text-gray-500 mt-1">{{ ch.description }}</div>
                        <el-tag v-if="ch.verified" type="success" size="small" class="mt-1">已验证</el-tag>
                        <el-tag v-else type="warning" size="small" class="mt-1">未验证</el-tag>
                    </div>
                </el-col>
            </el-row>
        </el-card>

        <!-- 免打扰 & 摘要设置 (M3-29) -->
        <el-card class="mb-5" shadow="never">
            <template #header><span class="font-semibold">⏰ 免打扰与时区设置</span></template>
            <el-row :gutter="24" class="items-end">
                <el-col :span="6">
                    <el-form-item label="免打扰开始时间">
                        <el-time-picker v-model="general.quiet_hours_start" format="HH:mm" value-format="HH:mm"
                            placeholder="不限制" style="width:100%" is-range />
                    </el-form-item>
                </el-col>
                <el-col :span="6">
                    <el-form-item label="免打扰结束时间">
                        <el-time-picker v-model="general.quiet_hours_end" format="HH:mm" value-format="HH:mm"
                            placeholder="不限制" style="width:100%" is-range />
                    </el-form-item>
                </el-col>
                <el-col :span="6">
                    <el-form-item label="时区">
                        <el-select v-model="general.timezone" style="width:100%">
                            <el-option v-for="tz in timezoneOptions" :key="tz.value" :label="tz.label" :value="tz.value" />
                        </el-select>
                    </el-form-item>
                </el-col>
                <el-col :span="6">
                    <el-form-item label="摘要频率">
                        <el-select v-model="general.digest_frequency" style="width:100%">
                            <el-option v-for="(label, val) in digestLabels" :key="val" :label="label" :value="val" />
                        </el-select>
                    </el-form-item>
                </el-col>
            </el-row>
            <div class="mt-2 flex items-center gap-4">
                <el-tag v-if="general.in_quiet_hours" type="warning" size="small">🔕 当前在免打扰时段</el-tag>
                <el-tag v-else type="success" size="small">🔔 通知正常发送</el-tag>
                <el-button size="small" type="primary" plain @click="saveGeneralSettings" :loading="saving">保存通用设置</el-button>
            </div>
        </el-card>

        <!-- 操作栏 -->
        <div class="flex items-center justify-between mb-4">
            <el-radio-group v-model="activeTab" size="small">
                <el-radio-button value="all">全部</el-radio-button>
                <el-radio-button v-for="g in groupedByCategory" :key="g.category" :value="g.category">
                    {{ g.label }}
                </el-radio-button>
            </el-radio-group>
            <div class="flex gap-2">
                <el-button size="small" @click="handleInitialize">恢复默认</el-button>
                <el-button type="primary" size="small" @click="savePreferences" :loading="saving">保存设置</el-button>
            </div>
        </div>

        <!-- 通知偏好矩阵 -->
        <div v-loading="loading">
            <el-card v-for="group in filteredGroups" :key="group.category" class="mb-4" shadow="never">
                <template #header>
                    <div class="flex items-center justify-between">
                        <span class="font-semibold">{{ group.label }}</span>
                        <div class="flex gap-2 items-center">
                            <span class="text-sm text-gray-400">全选:</span>
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
                    <el-table-column label="通知渠道" width="180">
                        <template #default="{ row }">
                            <div class="flex items-center gap-2">
                                <span>{{ channelLabels[row.channel] || row.channel }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="通知类型" min-width="200">
                        <template #default="{ row }">
                            <span>{{ row.label || categoryLabels[row.category] || row.category }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="启用" width="120" align="center">
                        <template #default="{ row }">
                            <el-switch v-model="row.enabled" @click.stop />
                        </template>
                    </el-table-column>
                    <el-table-column label="说明" min-width="200">
                        <template #default="{ row }">
                            <span class="text-gray-400 text-sm">
                                <template v-if="row.channel === 'mail'">发送到注册邮箱</template>
                                <template v-else-if="row.channel === 'sms'">发送到注册手机</template>
                                <template v-else>平台消息中心可查看</template>
                            </span>
                        </template>
                    </el-table-column>
                </el-table>
            </el-card>

            <el-empty v-if="!loading && groupedByCategory.length === 0" description="暂无通知分类" />
        </div>

        <!-- 简略概览表 -->
        <el-card class="mb-4" shadow="never" v-if="categorySummaries.length">
            <template #header><span class="font-semibold">概览</span></template>
            <el-table :data="categorySummaries" stripe>
                <el-table-column prop="label" label="通知类型" min-width="160" />
                <el-table-column label="邮件" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.email" type="success" size="small">✓</el-tag>
                        <el-tag v-else type="info" size="small">✗</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="短信" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.sms" type="success" size="small">✓</el-tag>
                        <el-tag v-else type="info" size="small">✗</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="站内信" width="100" align="center">
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
