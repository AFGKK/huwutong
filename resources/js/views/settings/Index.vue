<template>
    <div class="settings-page">
        <div class="page-header">
            <h2>{{ t('settings_page.title') }}</h2>
            <div class="header-actions">
                <el-input
                    v-model="searchQuery"
                    :placeholder="t('settings_page.search_ph')"
                    clearable
                    prefix-icon="Search"
                    style="width: 280px;"
                    @input="onSearchInput"
                />
            </div>
        </div>

        <div v-loading="loading" class="settings-content">
            <!-- 分组导航 -->
            <div v-if="!searchQuery" class="group-nav" ref="groupNavRef">
                <el-tag
                    v-for="group in filteredGroups"
                    :key="group.group"
                    :type="expandedGroups.has(group.group) ? 'primary' : 'info'"
                    size="small"
                    effect="plain"
                    class="group-nav-tag"
                    @click="toggleGroup(group.group)"
                >
                    <el-icon style="margin-right: 4px; vertical-align: middle;"><component :is="groupIcon(group.group)" /></el-icon>
                    {{ group.label }}
                </el-tag>
                <el-button size="small" text @click="expandAll">{{ t('settings_page.expand_all') }}</el-button>
                <el-button size="small" text @click="collapseAll">{{ t('settings_page.collapse_all') }}</el-button>
            </div>

            <el-form
                ref="formRef"
                :model="formData"
                label-width="140px"
                label-position="right"
            >
                <el-card
                    v-for="group in filteredGroups"
                    :key="group.group"
                    shadow="never"
                    class="setting-group"
                    :body-style="expandedGroups.has(group.group) || searchQuery ? {} : { display: 'none' }"
                >
                    <template #header>
                        <div class="group-header" @click="toggleGroup(group.group)">
                            <span class="group-title">
                                <el-icon style="margin-right: 6px;"><component :is="groupIcon(group.group)" /></el-icon>
                                {{ group.label }}
                            </span>
                            <span class="group-meta">
                                <el-tag size="small" type="info" effect="plain">{{ t('settings_page.item_count', { n: group.settings.length }) }}</el-tag>
                                <el-icon class="group-collapse-icon" :class="{ rotated: expandedGroups.has(group.group) }">
                                    <ArrowDown />
                                </el-icon>
                            </span>
                        </div>
                    </template>

                    <el-form-item
                        v-for="setting in group.settings"
                        :key="setting.key"
                        :label="setting.description || setting.key"
                        :prop="setting.key"
                        v-show="isSettingVisible(setting)"
                    >
                        <!-- 文本输入 -->
                        <el-input
                            v-if="setting.type === 'text'"
                            v-model="formData[setting.key]"
                            :placeholder="setting.description"
                            clearable
                            style="max-width: 500px;"
                        />
                        <!-- 多行文本 -->
                        <el-input
                            v-else-if="setting.type === 'textarea'"
                            v-model="formData[setting.key]"
                            type="textarea"
                            :rows="3"
                            :placeholder="setting.description"
                            style="max-width: 500px;"
                        />
                        <!-- 颜色选择 -->
                        <el-color-picker
                            v-else-if="setting.type === 'color'"
                            v-model="formData[setting.key]"
                            :predefine="predefineColors"
                        />
                        <!-- 密码/密钥 -->
                        <el-input
                            v-else-if="setting.type === 'password'"
                            v-model="formData[setting.key]"
                            type="password"
                            show-password
                            :placeholder="setting.description"
                            style="max-width: 500px;"
                            clearable
                        />
                        <!-- 开关 -->
                        <el-switch
                            v-else-if="setting.type === 'switch'"
                            v-model="formData[setting.key]"
                        />
                        <!-- 图片上传 -->
                        <div v-else-if="setting.type === 'image'" class="image-setting">
                            <div class="image-upload-row">
                                <el-input
                                    v-model="formData[setting.key]"
                                    :placeholder="t('settings_page.image_url_ph')"
                                    style="max-width: 400px;"
                                />
                                <el-upload
                                    :show-file-list="false"
                                    :before-upload="(file) => handleUpload(setting.key, file)"
                                    accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml"
                                    class="ml-2"
                                >
                                    <el-button type="primary" :loading="uploadingKey === setting.key">
                                        <el-icon><Upload /></el-icon> {{ t('actions.upload') }}
                                    </el-button>
                                </el-upload>
                            </div>
                            <div v-if="formData[setting.key]" class="image-preview">
                                <el-image
                                    :src="formData[setting.key]"
                                    style="width: 60px; height: 60px; border-radius: 4px;"
                                    fit="contain"
                                />
                            </div>
                        </div>
                        <!-- 选择 -->
                        <el-select
                            v-else-if="setting.type === 'select'"
                            v-model="formData[setting.key]"
                            style="max-width: 300px;"
                        >
                            <el-option
                                v-for="opt in parseSelectOptions(setting.options)"
                                :key="opt.value"
                                :label="opt.label"
                                :value="opt.value"
                            />
                        </el-select>
                        <!-- 默认文本 -->
                        <el-input
                            v-else
                            v-model="formData[setting.key]"
                            style="max-width: 500px;"
                        />

                        <div v-if="setting.is_public" class="setting-badge">
                            <el-tag size="small" type="info" effect="plain">{{ t('settings_page.public_badge') }}</el-tag>
                        </div>
                    </el-form-item>
                </el-card>
            </el-form>
        </div>

        <!-- 底部操作栏 -->
        <div class="sticky-footer">
            <div class="footer-inner">
                <span class="footer-hint">{{ t('settings_page.footer_summary', { groups: allGroupsCount, settings: allSettingsCount }) }}</span>
                <div>
                    <el-button @click="resetForm">{{ t('actions.reset') }}</el-button>
                    <el-button type="primary" :loading="submitting" @click="submitForm" size="large">
                        {{ t('settings_page.save_settings') }}
                    </el-button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Upload, Search, ArrowDown, Setting, TrendCharts, Connection, Wallet, Monitor, Lock, Tools, EditPen, Clock, Bell, DataBoard, Document, Grid, ChatDotSquare, Link, Promotion, Service, Tickets, Iphone, Cpu } from '@element-plus/icons-vue';
import settingApi from '@/api/setting';

const { t } = useI18n();

const loading = ref(false);
const submitting = ref(false);
const groups = ref([]);
const formRef = ref(null);
const formData = reactive({});
const searchQuery = ref('');
const expandedGroups = ref(new Set());

function parseSelectOptions(options) {
    if (!options) return [];
    // 如果是 JSON 字符串，先解析
    var opts = options;
    if (typeof opts === 'string') {
        try { opts = JSON.parse(opts); } catch(e) { return []; }
    }
    if (Array.isArray(opts)) {
        return opts.map(function(o) { return { label: o, value: o }; });
    }
    // 对象格式 { value: label }
    return Object.keys(opts).map(function(k) {
        return { label: opts[k], value: k };
    });
}

const uploadingKey = ref('');

const allGroupsCount = computed(() => groups.value.length);
const allSettingsCount = computed(() => {
    let n = 0;
    for (const g of groups.value) n += g.settings.length;
    return n;
});

const filteredGroups = computed(() => {
    if (!searchQuery.value) return groups.value;
    const q = searchQuery.value.toLowerCase();
    return groups.value.map(g => ({
        ...g,
        settings: g.settings.filter(s =>
            (s.description || '').toLowerCase().includes(q) ||
            s.key.toLowerCase().includes(q)
        ),
    })).filter(g => g.settings.length > 0);
});

function groupIcon(group) {
    const map = {
        general: Setting, brand: EditPen, contact: ChatDotSquare,
        mail: Connection, payment: Wallet, storage: DataBoard,
        sms: Iphone, ai: Cpu,
        security: Lock, maintenance: Tools, registration: EditPen,
        localization: Clock, notification: Bell, backup: DataBoard,
        logging: Document, interface: Monitor, oauth: Link,
        seo: TrendCharts, tracking: Promotion, verification: Tickets,
        social: Service, api: Connection, service: Service, legal: Document,
        wechat: Iphone,
    };
    return map[group] || Setting;
}

function toggleGroup(group) {
    if (searchQuery.value) return;
    const s = new Set(expandedGroups.value);
    if (s.has(group)) s.delete(group); else s.add(group);
    expandedGroups.value = s;
}

function expandAll() {
    expandedGroups.value = newSet(groups.value.map(g => g.group));
}
function collapseAll() {
    expandedGroups.value = new Set();
}
function newSet(arr) { return new Set(arr); }

function onSearchInput() {
    if (searchQuery.value) {
        expandedGroups.value = new Set(groups.value.map(g => g.group));
    }
}

function resetForm() {
    loadSettings();
    ElMessage.info(t('settings_page.reset_to_saved'));
}

const predefineColors = [
    '#0f172a', '#67C23A', '#E6A23C', '#F56C6C', '#909399',
    '#1d1e1f', '#303133', '#606266', '#C0C4CC', '#DCDFE6',
];

async function handleUpload(key, file) {
    uploadingKey.value = key;
    try {
        const { data: res } = await settingApi.uploadImage(key, file);
        if (res.success) {
            formData[key] = res.data.url || res.data.value;
            ElMessage.success(t('settings_page.upload_ok'));
        }
    } catch {
        ElMessage.error(t('settings_page.upload_fail'));
    } finally {
        uploadingKey.value = false;
    }
    return false; // 阻止默认上传
}

// 支付网关配置项可见性：关闭的网关隐藏其配置字段
function isSettingVisible(setting) {
    const key = setting.key;
    // Live Chat widget removed; hide leftover site keys from admin UI
    if (key === 'service_chat_enabled' || key === 'chat_widget_enabled' || key.startsWith('chat_widget_')) return false;
    // 支付驱动选择器始终可见
    if (key === 'payment_driver') return true;
    // 各支付网关的启用开关始终可见
    if (key === 'alipay_enabled' || key === 'wechat_enabled' || key === 'stripe_enabled' || key === 'paypal_enabled' || key === 'yipay_enabled') return true;
    // 微信小程序配置始终可见（属于 wechat 分组，不是 payment 分组）
    if (key.startsWith('wechat_mini_program_') || key === 'wechat_mini_subscribe_template_id') return true;
    // alipay_* 配置在 alipay 开启时显示
    if (key.startsWith('alipay_')) return !!formData.alipay_enabled;
    // wechat_* 支付配置在 wechat 开启时显示（排除 wechat_mini_program_*）
    if (key.startsWith('wechat_')) return !!formData.wechat_enabled;
    // stripe_* 配置在 stripe 开启时显示
    if (key.startsWith('stripe_')) return !!formData.stripe_enabled;
    // paypal_* 配置在 paypal 开启时显示
    if (key.startsWith('paypal_')) return !!formData.paypal_enabled;
    // yipay_* 配置在 yipay 开启时显示
    if (key.startsWith('yipay_')) return !!formData.yipay_enabled;
    return true;
}

async function loadSettings() {
    loading.value = true;
    try {
        const { data: res } = await settingApi.grouped();
        if (res.success) {
            groups.value = res.data || [];
            // 初始化表单数据
            for (const group of groups.value) {
                for (const setting of group.settings) {
                    const val = setting.value;
                    if (setting.type === 'switch') {
                        formData[setting.key] = val === '1' || val === 'true' || val === true;
                    } else {
                        formData[setting.key] = val ?? '';
                    }
                }
            }
        }
    } catch {
        ElMessage.error(t('settings_page.load_fail'));
    } finally {
        loading.value = false;
    }
}

async function submitForm() {
    submitting.value = true;
    try {
        const settings = [];
        for (const group of groups.value) {
            for (const setting of group.settings) {
                let value = formData[setting.key];
                if (setting.type === 'switch') {
                    value = value ? '1' : '0';
                }
                settings.push({ key: setting.key, value: String(value ?? '') });
            }
        }
        await settingApi.update(settings);
        ElMessage.success(t('settings_page.save_ok'));
    } catch {
        // handled by interceptor
    } finally {
        submitting.value = false;
    }
}

onMounted(() => {
    loadSettings();
});
</script>

<style scoped>
.settings-page { padding: 20px; padding-bottom: 80px; }

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.page-header h2 { margin: 0; font-size: 20px; }
.header-actions { display: flex; gap: 12px; align-items: center; }

/* 分组导航 */
.group-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 20px;
    padding: 12px 16px;
    background: #fff;
    border-radius: 8px;
    border: 1px solid #ebeef5;
    align-items: center;
}
.group-nav-tag {
    cursor: pointer;
    transition: all 0.2s;
}
.group-nav-tag:hover {
    transform: scale(1.05);
}

/* 分组卡片 */
.setting-group {
    margin-bottom: 16px;
    transition: all 0.3s;
}
.group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    user-select: none;
}
.group-title {
    font-weight: 600;
    font-size: 15px;
    display: flex;
    align-items: center;
}
.group-meta {
    display: flex;
    align-items: center;
    gap: 8px;
}
.group-collapse-icon {
    transition: transform 0.3s;
    font-size: 14px;
}
.group-collapse-icon.rotated {
    transform: rotate(180deg);
}

.setting-badge {
    display: inline-block;
    margin-left: 8px;
}

.image-setting {
    display: flex;
    align-items: center;
    gap: 12px;
}

.image-upload-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* 底部固定操作栏 */
.sticky-footer {
    position: fixed;
    bottom: 0;
    left: 200px;
    right: 0;
    background: #fff;
    border-top: 1px solid #ebeef5;
    padding: 12px 24px;
    z-index: 100;
    box-shadow: 0 -2px 8px rgba(0,0,0,0.05);
    transition: left 0.3s;
}
.footer-inner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 800px;
}
.footer-hint {
    color: #909399;
    font-size: 13px;
}

:deep(.el-card__body) { padding: 16px 20px; }
:deep(.el-form-item) { margin-bottom: 18px; }
</style>
