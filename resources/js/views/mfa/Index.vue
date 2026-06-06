<template>
    <div class="mfa-page">
        <div class="page-header">
            <h2>MFA 设置</h2>
        </div>

        <el-row :gutter="20">
            <!-- 状态卡片 -->
            <el-col :span="8">
                <el-card>
                    <template #header>MFA 状态</template>
                    <div class="status-info">
                        <el-icon :size="48" :color="mfaEnabled ? '#67c23a' : '#909399'">
                            <Lock />
                        </el-icon>
                        <div class="status-text">
                            <div class="status-value">{{ mfaEnabled ? '已启用' : '未启用' }}</div>
                            <div class="status-desc">
                                {{ mfaEnabled ? '您的账户已受 MFA 保护' : '建议启用 MFA 增强账户安全' }}
                            </div>
                        </div>
                    </div>
                    <el-button
                        :type="mfaEnabled ? 'danger' : 'primary'"
                        class="w-full mt-4"
                        @click="mfaEnabled ? showDisable() : showSetup()"
                    >
                        {{ mfaEnabled ? '禁用 MFA' : '启用 MFA' }}
                    </el-button>
                </el-card>

                <!-- 恢复码 -->
                <el-card class="mt-4">
                    <template #header>备用恢复码</template>
                    <div class="mb-2">
                        <div class="stat-value">{{ remainingCodes }}</div>
                        <div class="stat-label">剩余恢复码</div>
                    </div>
                    <el-button class="w-full" @click="regenerateCodes" :disabled="!mfaEnabled">
                        重新生成
                    </el-button>
                </el-card>
            </el-col>

            <!-- 设备列表 -->
            <el-col :span="16">
                <el-card>
                    <template #header>已绑定设备</template>
                    <el-table :data="devices" stripe>
                        <el-table-column prop="name" label="设备名称" min-width="150" />
                        <el-table-column prop="type" label="类型" width="100">
                            <template #default="{ row }">
                                <el-tag size="small">{{ row.type }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="last_used_at" label="最后使用" width="150" />
                        <el-table-column prop="confirmed_at" label="绑定时间" width="120" />
                        <el-table-column label="操作" width="150" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" @click="showRename(row)">
                                    重命名
                                </el-button>
                                <el-button text type="danger" size="small" @click="handleDelete(row)">
                                    解绑
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!devices.length" description="还没有绑定设备" />
                </el-card>
            </el-col>
        </el-row>

        <!-- 启用 MFA 对话框 -->
        <el-dialog v-model="setupVisible" title="启用 MFA" width="500px">
            <div v-if="!setupConfirmed">
                <p>请使用身份验证器应用（如 Google Authenticator、Authy）扫描以下二维码或手动输入密钥：</p>
                <div class="setup-info">
                    <div class="secret-box">
                        <code>{{ setupData.secret }}</code>
                        <el-button text @click="copySecret">复制</el-button>
                    </div>
                    <div class="uri-box">
                        <a :href="setupData.uri" target="_blank">
                            {{ setupData.uri.slice(0, 60) }}...
                        </a>
                    </div>
                </div>
                <el-form label-position="top" class="mt-4">
                    <el-form-item label="输入验证码确认">
                        <el-input v-model="verifyCode" placeholder="输入 6 位验证码" maxlength="6" />
                    </el-form-item>
                </el-form>
                <el-button type="primary" class="w-full" @click="confirmSetup" :loading="confirming">
                    确认并启用
                </el-button>
            </div>
            <div v-else>
                <el-alert title="MFA 已启用" type="success" show-icon class="mb-4" />
                <p class="mb-2">请立即保存以下恢复码，关闭后将无法再次查看：</p>
                <el-card shadow="never" class="codes-card">
                    <div v-for="(code, i) in recoveryCodes" :key="i" class="recovery-code">
                        <code>{{ code }}</code>
                        <el-button text size="small" @click="copyCode(code)">复制</el-button>
                    </div>
                </el-card>
            </div>
        </el-dialog>

        <!-- 重命名对话框 -->
        <el-dialog v-model="renameVisible" title="重命名设备" width="400px">
            <el-form>
                <el-form-item label="设备名称">
                    <el-input v-model="renameName" placeholder="输入新名称" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="renameVisible = false">取消</el-button>
                <el-button type="primary" @click="confirmRename">确认</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import mfaApi from '@/api/mfa';
import { Lock } from '@element-plus/icons-vue';

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
        ElMessage.warning('请输入 6 位验证码');
        return;
    }
    confirming.value = true;
    try {
        const { data: res } = await mfaApi.confirm({
            secret: setupData.value.secret,
            code: verifyCode.value,
            device_name: '管理后台设备 (' + new Date().toLocaleDateString() + ')',
        });
        recoveryCodes.value = res.data.recovery_codes;
        setupConfirmed.value = true;
        mfaEnabled.value = true;
    } catch {
        ElMessage.error('验证失败，请重试');
    } finally {
        confirming.value = false;
    }
    await fetchData();
}

function showDisable() {
    ElMessageBox.prompt('请输入您的 MFA 验证码或恢复码：', '禁用 MFA', {
        confirmButtonText: '确认禁用',
        cancelButtonText: '取消',
        inputPattern: /.{1,}/,
        inputErrorMessage: '请输入验证码',
    }).then(async ({ value }) => {
        await mfaApi.disable({ code: value });
        ElMessage.success('MFA 已禁用');
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
        ElMessage.success('设备已重命名');
        renameVisible.value = false;
        await fetchData();
    } catch {
        ElMessage.error('重命名失败');
    }
}

async function handleDelete(device) {
    try {
        await ElMessageBox.confirm(`确定要解绑设备 "${device.name}" 吗？`, '确认');
        await mfaApi.deleteDevice(device.id);
        ElMessage.success('设备已解绑');
        await fetchData();
    } catch { /* ignore */ }
}

async function regenerateCodes() {
    try {
        await ElMessageBox.confirm('重新生成恢复码将使旧的恢复码立即失效，确定继续吗？', '确认');
        const { data: res } = await mfaApi.regenerateCodes();
        ElMessage.success('恢复码已重新生成');
        await fetchData();
    } catch { /* ignore */ }
}

function copySecret() {
    navigator.clipboard.writeText(setupData.value.secret);
    ElMessage.success('密钥已复制');
}

function copyCode(code) {
    navigator.clipboard.writeText(code);
    ElMessage.success('恢复码已复制');
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
