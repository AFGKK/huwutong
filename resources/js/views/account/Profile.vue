<template>
    <div class="profile-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('profile_page.title') }}</h2>
                <span class="header-subtitle">{{ t('profile_page.subtitle') }}</span>
            </div>
        </div>

        <el-tabs v-model="activeTab" type="border-card">
            <el-tab-pane :label="tabLabels.profile" name="profile">
                <el-row :gutter="24">
                    <el-col :span="8">
                        <el-card shadow="never" class="avatar-card">
                            <div class="avatar-section">
                        <div class="avatar-wrapper" @mouseenter="showOverlay = true" @mouseleave="showOverlay = false">
                            <el-avatar :size="128" :src="avatarUrl" class="profile-avatar">
                                <div class="avatar-fallback">{{ initials }}</div>
                                <template #error>
                                    <div class="avatar-fallback">{{ initials }}</div>
                                </template>
                            </el-avatar>
                            <div v-show="showOverlay" class="avatar-overlay" @click="triggerUpload">
                                <el-icon :size="24"><Camera /></el-icon>
                                <span>{{ t('profile_page.avatar.change') }}</span>
                            </div>
                        </div>
                        <p class="avatar-hint">{{ t('profile_page.avatar.hint') }}</p>

                        <div class="avatar-actions">
                            <el-button size="small" @click="triggerUpload">
                                <el-icon><Upload /></el-icon> {{ t('profile_page.avatar.upload') }}
                            </el-button>
                            <el-button
                                v-if="pendingFile"
                                size="small"
                                type="primary"
                                @click="confirmUpload"
                                :loading="uploading"
                            >
                                <el-icon><Check /></el-icon> {{ t('profile_page.avatar.save') }}
                            </el-button>
                            <el-button
                                v-if="pendingFile"
                                size="small"
                                @click="cancelUpload"
                            >
                                {{ t('actions.cancel') }}
                            </el-button>
                            <el-button
                                v-if="hasAvatar && !pendingFile"
                                size="small"
                                type="danger"
                                plain
                                @click="handleDeleteAvatar"
                                :loading="deleting"
                            >
                                <el-icon><Delete /></el-icon> {{ t('profile_page.avatar.restore_default') }}
                            </el-button>
                        </div>

                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/jpeg,image/png,image/gif,image/webp"
                            style="display: none"
                            @change="handleFileChange"
                        />
                    </div>
                </el-card>
            </el-col>

            <el-col :span="16">
                <el-card shadow="never" class="info-card">
                    <template #header>
                        <span>{{ t('profile_page.basic_info') }}</span>
                    </template>

                    <el-form ref="formRef" :model="form" :rules="rules" label-width="100px" label-position="left">
                        <el-form-item :label="t('auth.email')">
                            <el-input :model-value="user.email" disabled>
                                <template #append>
                                    <el-tag v-if="user.email_verified_at" type="success" size="small">{{ t('portal.verified') }}</el-tag>
                                    <el-tag v-else type="warning" size="small">{{ t('portal.unverified') }}</el-tag>
                                </template>
                            </el-input>
                        </el-form-item>

                        <el-form-item :label="t('auth.name')" prop="name">
                            <el-input v-model="form.name" :placeholder="t('profile_page.name_ph')" maxlength="100" />
                        </el-form-item>

                        <el-form-item :label="t('auth.phone')" prop="phone">
                            <el-input v-model="form.phone" :placeholder="t('profile_page.phone_ph')" maxlength="20" />
                        </el-form-item>

                        <el-form-item :label="t('profile_page.role')">
                            <el-tag v-for="role in user.roles" :key="role.id" style="margin-right: 4px;">{{ role.name }}</el-tag>
                            <span v-if="!user.roles?.length" class="text-muted">—</span>
                        </el-form-item>

                        <el-form-item :label="t('portal.registered_at')">
                            <span>{{ user.created_at }}</span>
                        </el-form-item>

                        <el-form-item>
                            <el-button type="primary" @click="handleSave" :loading="saving">{{ t('profile_page.save_profile') }}</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>

                <el-card shadow="never" class="info-card" style="margin-top: 16px;">
                    <template #header>
                        <span>{{ t('profile_page.account_security') }}</span>
                    </template>
                    <el-descriptions :column="1" border>
                        <el-descriptions-item :label="t('profile_page.last_login')">{{ user.last_login_at || '—' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('profile_page.login_ip')">{{ user.last_login_ip || '—' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('profile_page.mfa_status')">
                            <el-tag :type="user.mfa_enabled ? 'success' : 'info'" size="small">
                                {{ user.mfa_enabled ? t('profile_page.mfa_on') : t('profile_page.mfa_off') }}
                            </el-tag>
                        </el-descriptions-item>
                    </el-descriptions>
                </el-card>

                <el-card shadow="never" class="info-card" style="margin-top: 16px;">
                    <template #header>
                        <span>{{ t('profile_page.security_score') }}</span>
                    </template>
                    <div v-if="secLoading" style="text-align:center;padding:20px"><el-icon class="is-loading" :size="20"><Loading /></el-icon></div>
                    <div v-else-if="secScore" class="security-score-panel">
                        <div class="sec-score-ring">
                            <div class="sec-score-circle" :class="'level-' + secScore.level">
                                <span class="sec-score-value">{{ secScore.score }}</span>
                                <span class="sec-score-max">/ {{ secScore.max_score }}</span>
                                <span class="sec-score-label">{{ secScore.level_label }}</span>
                            </div>
                        </div>
                        <div class="sec-items">
                            <div v-for="item in secScore.items" :key="item.key" class="sec-item" :class="{ passed: item.passed }">
                                <div class="sec-item-icon" :class="item.passed ? 'sec-icon-pass' : 'sec-icon-warn'"></div>
                                <div class="sec-item-body">
                                    <div class="sec-item-label">{{ item.label }}</div>
                                    <div class="sec-item-detail">{{ item.detail }}</div>
                                </div>
                                <div class="sec-item-score">+{{ item.score }}</div>
                                <div v-if="item.action" class="sec-item-action">
                                    <el-button size="small" type="primary" plain @click="gotoUrl(item.action_url)">{{ item.action }}</el-button>
                                </div>
                            </div>
                        </div>
                    </div>
                </el-card>

                <el-card shadow="never" class="info-card" style="margin-top: 16px;">
                    <template #header>
                        <span>{{ t('profile_page.preferences') }}</span>
                    </template>
                    <div v-if="prefLoading" style="text-align:center;padding:20px"><el-icon class="is-loading" :size="20"><Loading /></el-icon></div>
                    <div v-else class="pref-form">
                        <el-form label-width="100px" label-position="left" size="small">
                            <el-form-item :label="t('profile_page.ui_theme')">
                                <el-radio-group v-model="prefs.theme">
                                    <el-radio v-for="opt in themeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio>
                                </el-radio-group>
                            </el-form-item>
                            <el-form-item :label="t('profile_page.blog_font')">
                                <el-select v-model="prefs.blog_font" style="width:160px">
                                    <el-option v-for="opt in fontOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                </el-select>
                            </el-form-item>
                            <el-form-item :label="t('profile_page.blog_font_size')">
                                <el-radio-group v-model="prefs.blog_font_size">
                                    <el-radio v-for="opt in fontSizeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio>
                                </el-radio-group>
                            </el-form-item>
                            <el-divider />
                            <el-form-item :label="t('profile_page.notify_prefs')">
                                <div style="display:flex;flex-direction:column;gap:6px">
                                    <el-checkbox v-model="prefs.notify_new_article">{{ t('profile_page.notify.new_article') }}</el-checkbox>
                                    <el-checkbox v-model="prefs.notify_comment_reply">{{ t('profile_page.notify.comment_reply') }}</el-checkbox>
                                    <el-checkbox v-model="prefs.notify_follow_update">{{ t('profile_page.notify.follow_update') }}</el-checkbox>
                                </div>
                            </el-form-item>
                            <el-form-item :label="t('profile_page.email_digest')">
                                <el-select v-model="prefs.email_digest" style="width:160px">
                                    <el-option v-for="opt in digestOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                </el-select>
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="savePrefs" :loading="prefSaving">{{ t('profile_page.save_prefs') }}</el-button>
                                <span v-if="prefSaved" class="prefs-saved">{{ t('profile_page.prefs_saved') }}</span>
                            </el-form-item>
                        </el-form>
                    </div>
                </el-card>
            </el-col>
        </el-row>
            </el-tab-pane>

            <el-tab-pane :label="tabLabels.points" name="points">
                <PointsDaily />
            </el-tab-pane>

            <el-tab-pane :label="tabLabels.interactions" name="interactions">
                <UserInteractions />
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Loading } from '@element-plus/icons-vue';
import profileApi from '@/api/profile';
import { useAuthStore } from '@/stores/auth';
import { getSecurityScore, getPreferences, savePreferences } from '@/api/interaction';
import UserInteractions from './UserInteractions.vue';
import PointsDaily from './PointsDaily.vue';

const { t } = useI18n();
const route = useRoute();
const profileTabs = ['profile', 'points', 'interactions'];

const tabLabels = computed(() =>
    Object.fromEntries(profileTabs.map((key) => [key, t(`profile_page.tabs.${key}`)]))
);

const themeOptions = computed(() => [
    { label: t('profile_page.themes.system'), value: 'system' },
    { label: t('profile_page.themes.light'), value: 'light' },
    { label: t('profile_page.themes.dark'), value: 'dark' },
    { label: t('profile_page.themes.sepia'), value: 'sepia' },
]);

const fontOptions = computed(() => [
    { label: t('profile_page.fonts.default'), value: 'default' },
    { label: t('profile_page.fonts.serif'), value: 'serif' },
    { label: t('profile_page.fonts.monospace'), value: 'monospace' },
]);

const fontSizeOptions = computed(() => [
    { label: t('profile_page.font_sizes.small'), value: 'small' },
    { label: t('profile_page.font_sizes.medium'), value: 'medium' },
    { label: t('profile_page.font_sizes.large'), value: 'large' },
]);

const digestOptions = computed(() => [
    { label: t('portal.digest_none'), value: 'none' },
    { label: t('portal.digest_daily'), value: 'daily' },
    { label: t('portal.digest_weekly'), value: 'weekly' },
]);

const authStore = useAuthStore();

const user = ref({});
const activeTab = ref('profile');
const form = ref({ name: '', phone: '' });
const avatarUrl = ref('');
const hasAvatar = ref(false);
const showOverlay = ref(false);
const saving = ref(false);
const uploading = ref(false);
const deleting = ref(false);
const secLoading = ref(false);
const secScore = ref(null);
const prefLoading = ref(false);
const prefSaving = ref(false);
const prefSaved = ref(false);
const prefs = reactive({
  theme: 'system',
  blog_font: 'default',
  blog_font_size: 'medium',
  notify_new_article: true,
  notify_comment_reply: true,
  notify_follow_update: true,
  email_digest: 'weekly',
});
const pendingFile = ref(null);
const fileInput = ref(null);
const formRef = ref(null);

const initials = computed(() => {
    if (!user.value.name) return '?';
    return user.value.name.charAt(0).toUpperCase();
});

const rules = computed(() => ({
    name: [{ required: true, message: t('auth.name_required'), trigger: 'blur' }],
}));

function triggerUpload() {
    fileInput.value?.click();
}

async function handleFileChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (ev) => {
        avatarUrl.value = ev.target.result;
        pendingFile.value = file;
    };
    reader.readAsDataURL(file);

    e.target.value = '';
}

async function confirmUpload() {
    if (!pendingFile.value) return;
    uploading.value = true;
    try {
        const res = await profileApi.uploadAvatar(pendingFile.value);
        pendingFile.value = null;
        await loadUser();
        const data = res.data?.data || res.data;
        authStore.setUser({ avatar_url: data.avatar_url || avatarUrl.value });
        ElMessage.success(t('profile_page.messages.avatar_updated'));
    } catch (err) {
        await loadUser();
        ElMessage.error(err.response?.data?.message || t('profile_page.messages.upload_failed'));
    } finally {
        uploading.value = false;
    }
}

function cancelUpload() {
    pendingFile.value = null;
    loadUser();
}

async function handleDeleteAvatar() {
    try {
        await ElMessageBox.confirm(
            t('profile_page.confirm.restore_avatar_msg'),
            t('profile_page.confirm.restore_avatar_title'),
            {
                type: 'info',
                confirmButtonText: t('actions.confirm'),
                cancelButtonText: t('actions.cancel'),
            },
        );
        deleting.value = true;
        await profileApi.deleteAvatar();
        await loadUser();
        authStore.setUser({ avatar_url: '' });
        ElMessage.success(t('profile_page.messages.avatar_restored'));
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(t('messages.failed'));
        }
    } finally {
        deleting.value = false;
    }
}

async function handleSave() {
    const valid = await formRef.value.validate().catch(() => false);
    if (!valid) return;

    saving.value = true;
    try {
        const res = await profileApi.updateProfile(form.value);
        const data = res.data?.data || res.data;
        authStore.setUser(data);
        ElMessage.success(t('profile_page.messages.profile_updated'));
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('auth.save_fail'));
    } finally {
        saving.value = false;
    }
}

async function loadUser() {
    try {
        const res = await profileApi.getUser();
        const data = res.data?.data || res.data;
        user.value = data;
        form.value.name = data.name || '';
        form.value.phone = data.phone || '';
        avatarUrl.value = data.avatar_url || '';
        hasAvatar.value = !!data.avatar;
    } catch (e) {
        ElMessage.error(t('profile_page.messages.load_user_failed'));
    }
}

async function loadSecurityScore() {
    secLoading.value = true;
    try {
        const res = await getSecurityScore();
        secScore.value = res.data?.data || null;
    } catch { /* ignore */ }
    finally { secLoading.value = false; }
}

async function loadPreferences() {
    prefLoading.value = true;
    try {
        const res = await getPreferences();
        const data = res.data?.data || {};
        Object.keys(data).forEach(key => { if (key in prefs) prefs[key] = data[key]; });
    } catch { /* ignore */ }
    finally { prefLoading.value = false; }
}

async function savePrefs() {
    prefSaving.value = true;
    prefSaved.value = false;
    try {
        await savePreferences({ ...prefs });
        prefSaved.value = true;
        setTimeout(() => { prefSaved.value = false; }, 3000);
        ElMessage.success(t('profile_page.messages.prefs_saved'));
    } catch { ElMessage.error(t('auth.save_fail')); }
    finally { prefSaving.value = false; }
}

function gotoUrl(url) {
    if (url) window.location.href = url;
}

onMounted(() => {
    const tab = String(route.query.tab || '');
    if (profileTabs.includes(tab)) {
        activeTab.value = tab;
    }
    loadUser();
    loadSecurityScore();
    loadPreferences();
});
</script>

<style scoped>
.profile-page {
    padding: 20px;
}
.page-header {
    margin-bottom: 20px;
}
.header-left h2 {
    margin: 0;
    font-size: 20px;
    display: inline;
}
.header-subtitle {
    font-size: 13px;
    color: #999;
    margin-left: 8px;
}
.avatar-card, .info-card {
    min-height: 200px;
}

.security-score-panel { display: flex; gap: 24px; align-items: flex-start; }
.sec-score-ring { flex-shrink: 0; text-align: center; padding-top: 8px; }
.sec-score-circle {
  width: 100px; height: 100px; border-radius: 50%;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  border: 4px solid #e0e0e0; margin: 0 auto 8px;
}
.sec-score-circle.level-safe { border-color: #67c23a; }
.sec-score-circle.level-warning { border-color: #e6a23c; }
.sec-score-circle.level-danger { border-color: #f56c6c; }
.sec-score-value { font-size: 28px; font-weight: 700; color: #303133; line-height: 1; }
.sec-score-max { font-size: 11px; color: #909399; }
.sec-score-label { font-size: 11px; color: #909399; margin-top: 2px; }
.sec-items { flex: 1; display: flex; flex-direction: column; gap: 8px; }
.sec-item { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 6px; background: #f8f9fa; }
.sec-item.passed { background: #f0f9f0; }
.sec-item-icon { width: 16px; height: 16px; border-radius: 50%; flex-shrink: 0; }
.sec-icon-pass { background: #67c23a; }
.sec-icon-warn { background: #e6a23c; }
.sec-item-body { flex: 1; min-width: 0; }
.sec-item-label { font-size: 13px; font-weight: 500; color: #303133; }
.sec-item-detail { font-size: 11px; color: #909399; }
.sec-item-score { font-size: 12px; font-weight: 600; color: #67c23a; flex-shrink: 0; }
.sec-item-action { flex-shrink: 0; }

.pref-form { padding: 4px 0; }
.pref-form .el-form-item { margin-bottom: 6px; }
.pref-form .el-divider { margin: 12px 0; }
.pref-form .el-checkbox { margin-right: 0; }
.prefs-saved { color: #67c23a; font-size: 12px; margin-left: 8px; }

.avatar-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    padding: 20px 0;
}
.avatar-wrapper {
    position: relative;
    cursor: pointer;
    border-radius: 50%;
    overflow: hidden;
}
.profile-avatar {
    border: 3px solid #e8e8e8;
    display: block;
}
.avatar-fallback {
    width: 128px;
    height: 128px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    font-weight: bold;
    color: #fff;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
}
.avatar-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 12px;
    gap: 4px;
    border-radius: 50%;
    transition: opacity 0.2s;
}
.avatar-hint {
    font-size: 12px;
    color: #999;
    margin: 0;
}
.avatar-actions {
    display: flex;
    gap: 8px;
}
.text-muted {
    color: #999;
}
</style>
