<template>
    <div class="portal-notification-preferences">
        <div class="page-header">
            <h2>通知偏好设置</h2>
            <p class="text-muted">选择您希望接收哪些类型的通知以及通过什么渠道接收。</p>
        </div>

        <el-row :gutter="16">
            <el-col :span="16">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>通知分类</span>
                            <el-button text size="small" @click="setAll('all')">全部开启</el-button>
                            <el-button text size="small" @click="setAll('none')">全部关闭</el-button>
                        </div>
                    </template>

                    <div v-if="preferences.length" class="preference-list">
                        <div
                            v-for="group in groupedPreferences"
                            :key="group.group"
                            class="preference-group"
                        >
                            <div class="group-header">
                                <h4 class="group-title">{{ groupGroupLabel(group.group) }}</h4>
                            </div>
                            <div
                                v-for="pref in group.items"
                                :key="pref.id || pref.key"
                                class="preference-item"
                            >
                                <div class="pref-info">
                                    <div class="pref-name">{{ pref.label }}</div>
                                    <div class="pref-desc">{{ pref.description }}</div>
                                </div>
                                <div class="pref-channels">
                                    <el-checkbox
                                        v-if="pref.channels?.includes('email')"
                                        v-model="pref.email_enabled"
                                        @change="() => updatePreference(pref)"
                                    >
                                        邮件
                                    </el-checkbox>
                                    <el-checkbox
                                        v-if="pref.channels?.includes('sms')"
                                        v-model="pref.sms_enabled"
                                        @change="() => updatePreference(pref)"
                                    >
                                        短信
                                    </el-checkbox>
                                    <el-checkbox
                                        v-if="pref.channels?.includes('in_app')"
                                        v-model="pref.in_app_enabled"
                                        @change="() => updatePreference(pref)"
                                    >
                                        站内信
                                    </el-checkbox>
                                </div>
                            </div>
                        </div>
                    </div>
                    <el-empty v-else description="暂无通知偏好设置" :image-size="60" />
                </el-card>
            </el-col>

            <el-col :span="8">
                <!-- 全局设置 -->
                <el-card class="mb-4">
                    <template #header>
                        <span>全局设置</span>
                    </template>
                    <div class="global-settings">
                        <div class="global-item">
                            <div class="global-info">
                                <div class="global-label">通知摘要</div>
                                <div class="global-desc">将非紧急通知合并为每日摘要发送</div>
                            </div>
                            <el-switch v-model="digestEnabled" @change="updateDigest" />
                        </div>
                        <el-divider />
                        <div class="global-item">
                            <div class="global-info">
                                <div class="global-label">勿扰模式</div>
                                <div class="global-desc">在指定时间内不发送通知</div>
                            </div>
                            <el-switch v-model="quietHoursEnabled" @change="() => {}" />
                        </div>
                        <div v-if="quietHoursEnabled" class="quiet-hours-config">
                            <el-time-picker
                                v-model="quietStart"
                                placeholder="开始"
                                format="HH:mm"
                                style="width: 130px"
                                @change="updateQuietHours"
                            />
                            <span class="quiet-separator">至</span>
                            <el-time-picker
                                v-model="quietEnd"
                                placeholder="结束"
                                format="HH:mm"
                                style="width: 130px"
                                @change="updateQuietHours"
                            />
                        </div>
                    </div>
                </el-card>

                <!-- 当前联系方式 -->
                <el-card>
                    <template #header>
                        <span>当前联系方式</span>
                    </template>
                    <div class="contact-info">
                        <div class="contact-item">
                            <el-icon><Message /></el-icon>
                            <div>
                                <div class="contact-label">邮箱</div>
                                <div class="contact-value">{{ authStore.userEmail || '未设置' }}</div>
                            </div>
                        </div>
                        <div class="contact-item">
                            <el-icon><Phone /></el-icon>
                            <div>
                                <div class="contact-label">手机</div>
                                <div class="contact-value">{{ userPhone || '未设置' }}</div>
                            </div>
                        </div>
                    </div>
                    <el-button text type="primary" class="w-full" @click="$router.push('/portal/settings')">
                        前往个人设置修改
                    </el-button>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import apiClient from '@/api/client';
import { ElMessage } from 'element-plus';
import { Message, Phone } from '@element-plus/icons-vue';

const authStore = useAuthStore();
const preferences = ref([]);
const digestEnabled = ref(false);
const quietHoursEnabled = ref(false);
const quietStart = ref(null);
const quietEnd = ref(null);
const userPhone = ref('');

const groupedPreferences = computed(() => {
    const groups = {};
    for (const pref of preferences.value) {
        const g = pref.group || 'other';
        if (!groups[g]) groups[g] = { group: g, items: [] };
        groups[g].items.push(pref);
    }
    return Object.values(groups);
});

function groupGroupLabel(group) {
    const map = {
        license: 'License 相关',
        billing: '计费与账单',
        security: '安全相关',
        system: '系统通知',
        marketing: '营销推广',
        other: '其他',
    };
    return map[group] || group;
}

async function fetchPreferences() {
    try {
        const { data: res } = await apiClient.get('/notifications/preferences');
        const data = res.data || {};
        preferences.value = data.items || [];

        if (Array.isArray(preferences.value)) {
            preferences.value.forEach(p => {
                if (!p.channels) p.channels = ['email', 'in_app'];
                p.email_enabled = p.email_enabled ?? p.channels.includes('email');
                p.sms_enabled = p.sms_enabled ?? p.channels.includes('sms');
                p.in_app_enabled = p.in_app_enabled ?? p.channels.includes('in_app');
            });
        }

        digestEnabled.value = data.digest_enabled ?? false;
        quietHoursEnabled.value = data.quiet_hours_enabled ?? false;
        if (data.quiet_start) quietStart.value = data.quiet_start;
        if (data.quiet_end) quietEnd.value = data.quiet_end;
    } catch {
        // Fallback defaults
        preferences.value = getDefaultPreferences();
    }
}

function getDefaultPreferences() {
    return [
        { key: 'license_expiry', group: 'license', label: 'License 到期提醒', description: 'License 即将到期或已到期时通知', channels: ['email', 'in_app'], email_enabled: true, in_app_enabled: true },
        { key: 'license_status_change', group: 'license', label: 'License 状态变更', description: 'License 被暂停、冻结、吊销等状态变化时通知', channels: ['email', 'in_app'], email_enabled: true, in_app_enabled: true },
        { key: 'device_activation', group: 'license', label: '新设备激活', description: '有新的设备激活 License 时通知', channels: ['email', 'in_app'], email_enabled: true, in_app_enabled: true },
        { key: 'billing_renewal', group: 'billing', label: '自动续费通知', description: '订阅自动续费成功或失败时通知', channels: ['email', 'sms', 'in_app'], email_enabled: true, sms_enabled: false, in_app_enabled: true },
        { key: 'invoice_available', group: 'billing', label: '新发票生成', description: '有新发票可供查看时通知', channels: ['email', 'in_app'], email_enabled: true, in_app_enabled: true },
        { key: 'payment_failed', group: 'billing', label: '支付失败提醒', description: '支付扣款失败时即时通知', channels: ['email', 'sms', 'in_app'], email_enabled: true, sms_enabled: true, in_app_enabled: true },
        { key: 'security_login', group: 'security', label: '新登录提醒', description: '有新的设备或位置登录您的账户时通知', channels: ['email', 'in_app'], email_enabled: true, in_app_enabled: true },
        { key: 'security_password', group: 'security', label: '密码变更提醒', description: '账户密码被修改时通知', channels: ['email', 'in_app'], email_enabled: true, in_app_enabled: true },
        { key: 'system_maintenance', group: 'system', label: '系统维护通知', description: '计划内系统维护或服务变更通知', channels: ['email', 'in_app'], email_enabled: true, in_app_enabled: true },
    ];
}

async function updatePreference(pref) {
    try {
        const payload = {
            key: pref.key || pref.id,
            email_enabled: !!pref.email_enabled,
            sms_enabled: !!pref.sms_enabled,
            in_app_enabled: !!pref.in_app_enabled,
        };
        await apiClient.put('/notifications/preferences', payload);
        // 不提示成功，保持交互轻量
    } catch {
        ElMessage.error('更新偏好设置失败');
    }
}

function setAll(action) {
    const value = action === 'all';
    for (const pref of preferences.value) {
        pref.email_enabled = pref.channels?.includes('email') ? value : false;
        pref.sms_enabled = pref.channels?.includes('sms') ? value : false;
        pref.in_app_enabled = pref.channels?.includes('in_app') ? value : false;
    }
    // 批量保存最后一个偏好
    if (preferences.value.length) {
        updatePreference(preferences.value[0]);
        ElMessage.success(action === 'all' ? '已开启全部通知' : '已关闭全部通知');
    }
}

async function updateDigest() {
    try {
        await apiClient.put('/notifications/preferences', { digest_enabled: digestEnabled.value });
        ElMessage.success(digestEnabled.value ? '通知摘要已开启' : '通知摘要已关闭');
    } catch {
        digestEnabled.value = !digestEnabled.value;
    }
}

async function updateQuietHours() {
    try {
        await apiClient.put('/notifications/preferences', {
            quiet_hours_enabled: quietHoursEnabled.value,
            quiet_start: quietStart.value,
            quiet_end: quietEnd.value,
        });
        ElMessage.success('勿扰时间已更新');
    } catch {
        // ignore
    }
}

onMounted(fetchPreferences);
</script>

<style scoped>
.page-header {
    margin-bottom: 20px;
}

.page-header h2 { margin: 0 0 4px; }

.text-muted {
    color: #909399;
    font-size: 14px;
    margin: 0;
}

.mb-4 { margin-bottom: 16px; }

.card-header {
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-header span { flex: 1; }

.preference-list {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.preference-group {
    display: flex;
    flex-direction: column;
}

.group-header {
    margin-bottom: 8px;
}

.group-title {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: #303133;
    padding-bottom: 8px;
    border-bottom: 1px solid #ebeef5;
}

.preference-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f5f7fa;
}

.preference-item:last-child {
    border-bottom: none;
}

.pref-info {
    flex: 1;
    min-width: 0;
}

.pref-name {
    font-size: 14px;
    font-weight: 500;
    color: #303133;
}

.pref-desc {
    font-size: 12px;
    color: #909399;
    margin-top: 2px;
}

.pref-channels {
    display: flex;
    gap: 16px;
    flex-shrink: 0;
}

.pref-channels .el-checkbox {
    margin-right: 0;
}

.global-settings {
    display: flex;
    flex-direction: column;
}

.global-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.global-label {
    font-size: 14px;
    font-weight: 500;
    color: #303133;
}

.global-desc {
    font-size: 12px;
    color: #909399;
    margin-top: 2px;
}

.quiet-hours-config {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    padding-left: 4px;
}

.quiet-separator {
    color: #909399;
    font-size: 13px;
}

.contact-info {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 12px;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.contact-label {
    font-size: 13px;
    color: #909399;
}

.contact-value {
    font-size: 14px;
    color: #303133;
}

.w-full { width: 100%; }
</style>
