<template>
    <div class="mfa-page">
        <div class="page-header">
            <h2>{{ t('mfa_page.title') }}</h2>
        </div>

        <el-row :gutter="20">
            <!-- 状态卡片 -->
            <el-col :span="8">
                <el-card>
                    <template #header>{{ t('profile_page.mfa_status') }}</template>
                    <div class="status-info">
                        <el-icon :size="48" :color="mfaEnabled ? '#67c23a' : '#909399'">
                            <Lock />
                        </el-icon>
                        <div class="status-text">
                            <div class="status-value">{{ mfaEnabled ? statusLabels.enabled : statusLabels.disabled }}</div>
                            <div class="status-desc">
                                {{ mfaEnabled ? t('mfa_page.status_desc_enabled') : t('mfa_page.status_desc_disabled') }}
                            </div>
                        </div>
                    </div>
                    <el-button
                        :type="mfaEnabled ? 'danger' : 'primary'"
                        class="w-full mt-4"
                        @click="mfaEnabled ? showDisable() : showSetup()"
                    >
                        {{ mfaEnabled ? t('mfa_page.disable_btn') : t('mfa_page.enable_btn') }}
                    </el-button>
                </el-card>

                <!-- 恢复码 -->
                <el-card class="mt-4">
                    <template #header>{{ t('mfa_page.recovery_codes') }}</template>
                    <div class="mb-2">
                        <div class="stat-value">{{ remainingCodes }}</div>
                        <div class="stat-label">{{ t('mfa_page.remaining_codes') }}</div>
                    </div>
                    <el-button class="w-full" @click="regenerateCodes" :disabled="!mfaEnabled">
                        {{ t('mfa_page.regenerate') }}
                    </el-button>
                </el-card>
            </el-col>

            <!-- 设备列表 -->
            <el-col :span="16">
                <el-card>
                    <template #header>{{ t('mfa_page.devices_title') }}</template>
                    <el-table :data="devices" stripe>
                        <el-table-column prop="name" :label="colLabels.name" min-width="150" />
                        <el-table-column prop="type" :label="colLabels.type" width="100">
                            <template #default="{ row }">
                                <el-tag size="small">{{ row.type }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="last_used_at" :label="colLabels.last_used" width="150" />
                        <el-table-column prop="confirmed_at" :label="colLabels.bound_at" width="120" />
                        <el-table-column :label="colLabels.actions" width="150" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" @click="showRename(row)">
                                    {{ t('mfa_page.rename') }}
                                </el-button>
                                <el-button text type="danger" size="small" @click="handleDelete(row)">
                                    {{ t('mfa_page.unbind') }}
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!devices.length" :description="t('mfa_page.empty_devices')" />
                </el-card>
            </el-col>
        </el-row>

        <!-- 启用 MFA 对话框 -->
        <el-dialog v-model="setupVisible" :title="t('mfa_page.setup_title')" width="500px">
            <div v-if="!setupConfirmed">
                <p>{{ t('mfa_page.setup_instructions') }}</p>
                <div class="setup-info">
                    <div class="secret-box">
                        <code>{{ setupData.secret }}</code>
                        <el-button text @click="copySecret">{{ t('actions.copy') }}</el-button>
                    </div>
                    <div class="uri-box">
                        <a :href="setupData.uri" target="_blank">
                            {{ setupData.uri.slice(0, 60) }}...
                        </a>
                    </div>
                </div>
                <el-form label-position="top" class="mt-4">
                    <el-form-item :label="t('mfa_page.verify_label')">
                        <el-input v-model="verifyCode" :placeholder="t('mfa_page.verify_placeholder')" maxlength="6" />
                    </el-form-item>
                </el-form>
                <el-button type="primary" class="w-full" @click="confirmSetup" :loading="confirming">
                    {{ t('mfa_page.confirm_enable') }}
                </el-button>
            </div>
            <div v-else>
                <el-alert :title="t('mfa_page.enabled_alert')" type="success" show-icon class="mb-4" />
                <p class="mb-2">{{ t('mfa_page.save_codes_hint') }}</p>
                <el-card shadow="never" class="codes-card">
                    <div v-for="(code, i) in recoveryCodes" :key="i" class="recovery-code">
                        <code>{{ code }}</code>
                        <el-button text size="small" @click="copyCode(code)">{{ t('actions.copy') }}</el-button>
                    </div>
                </el-card>
            </div>
        </el-dialog>

        <!-- 重命名对话框 -->
        <el-dialog v-model="renameVisible" :title="t('mfa_page.rename_title')" width="400px">
            <el-form>
                <el-form-item :label="t('mfa_page.device_name_label')">
                    <el-input v-model="renameName" :placeholder="t('mfa_page.device_name_ph')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="renameVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="confirmRename">{{ t('actions.confirm') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import mfaApi from '@/api/mfa';
import { Lock } from '@element-plus/icons-vue';

const { t } = useI18n();

const statusLabels = computed(() => ({
    enabled: t('security_page.checks.status.enabled'),
    disabled: t('security_page.checks.status.not_enabled'),
}));

const colLabels = computed(() => ({
    name: t('mfa_page.cols.name'),
    type: t('mfa_page.cols.type'),
    last_used: t('mfa_page.cols.last_used'),
    bound_at: t('mfa_page.cols.bound_at'),
    actions: t('mfa_page.cols.actions'),
}));

const mfaEnabled = ref(false);
const devices = ref([]);
const remainingCodes = ref(0);

// Setup dialog
const setupVisible = ref(false);
const setupConfirmed = ref(false);
const setupData = ref({});
const verifyCode = ref('');
const confirming = ref(false);
const recoveryCodes = ref([]);

// Rename
const renameVisible = ref(false);
const renameName = ref('');
const renameDeviceId = ref(null);

async function fetchData() {
    try {
        const { data: res } = await mfaApi.list();
        devices.value = res.data || [];
        mfaEnabled.value = devices.value.length > 0;
    } catch { /* ignore */ }

    try {
        const { data: res } = await mfaApi.recoveryStatus();
        remainingCodes.value = res.data?.remaining || 0;
    } catch { /* ignore */ }
}

async function showSetup() {
    setupConfirmed.value = false;
    verifyCode.value = '';
    const { data: res } = await mfaApi.setup();
    setupData.value = res.data;
    setupVisible.value = true;
}

async function confirmSetup() {
    if (verifyCode.value.length !== 6) {
        ElMessage.warning(t('mfa_page.messages.code_required'));
        return;
    }
    confirming.value = true;
    try {
        const { data: res } = await mfaApi.confirm({
            secret: setupData.value.secret,
            code: verifyCode.value,
            device_name: t('mfa_page.default_device_name', { date: new Date().toLocaleDateString() }),
        });
        recoveryCodes.value = res.data.recovery_codes;
        setupConfirmed.value = true;
        mfaEnabled.value = true;
    } catch {
        ElMessage.error(t('mfa_page.messages.verify_failed'));
    } finally {
        confirming.value = false;
    }
    await fetchData();
}

function showDisable() {
    ElMessageBox.prompt(t('mfa_page.messages.disable_prompt'), t('mfa_page.messages.disable_title'), {
        confirmButtonText: t('mfa_page.messages.confirm_disable'),
        cancelButtonText: t('actions.cancel'),
        inputPattern: /.{1,}/,
        inputErrorMessage: t('mfa_page.messages.code_required_short'),
    }).then(async ({ value }) => {
        await mfaApi.disable({ code: value });
        ElMessage.success(t('mfa_page.messages.disabled_ok'));
        await fetchData();
    }).catch(() => {});
}

function showRename(device) {
    renameDeviceId.value = device.id;
    renameName.value = device.name;
    renameVisible.value = true;
}

async function confirmRename() {
    if (!renameName.value) return;
    try {
        await mfaApi.renameDevice(renameDeviceId.value, renameName.value);
        ElMessage.success(t('mfa_page.messages.renamed_ok'));
        renameVisible.value = false;
        await fetchData();
    } catch {
        ElMessage.error(t('mfa_page.messages.rename_failed'));
    }
}

async function handleDelete(device) {
    try {
        await ElMessageBox.confirm(
            t('mfa_page.messages.unbind_confirm', { name: device.name }),
            t('actions.confirm'),
        );
        await mfaApi.deleteDevice(device.id);
        ElMessage.success(t('mfa_page.messages.unbind_ok'));
        await fetchData();
    } catch { /* ignore */ }
}

async function regenerateCodes() {
    try {
        await ElMessageBox.confirm(t('mfa_page.messages.regenerate_confirm'), t('actions.confirm'));
        await mfaApi.regenerateCodes();
        ElMessage.success(t('mfa_page.messages.regenerate_ok'));
        await fetchData();
    } catch { /* ignore */ }
}

function copySecret() {
    navigator.clipboard.writeText(setupData.value.secret);
    ElMessage.success(t('mfa_page.messages.secret_copied'));
}

function copyCode(code) {
    navigator.clipboard.writeText(code);
    ElMessage.success(t('mfa_page.messages.code_copied'));
}

onMounted(fetchData);
</script>

<style scoped>
.page-header { margin-bottom: 20px; }
.page-header h2 { margin: 0; }
.status-info {
    display: flex;
    align-items: center;
    gap: 16px;
}
.status-text { flex: 1; }
.status-value { font-size: 18px; font-weight: 600; }
.status-desc { font-size: 13px; color: #909399; margin-top: 4px; }
.mt-4 { margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }
.mb-2 { margin-bottom: 8px; }
.w-full { width: 100%; }
.setup-info {
    background: #f5f7fa;
    padding: 16px;
    border-radius: 8px;
    margin: 16px 0;
}
.secret-box, .uri-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
    word-break: break-all;
}
.codes-card {
    max-height: 300px;
    overflow-y: auto;
}
.recovery-code {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #ebeef5;
    font-family: monospace;
    font-size: 14px;
}
.stat-value { font-size: 28px; font-weight: 700; }
.stat-label { font-size: 13px; color: #909399; }
</style>
