<template>
    <div class="offline-page">
        <div class="page-header">
            <div class="header-left">
                <h2>离线 License 管理</h2>
                <span class="header-subtitle">管理离线环境下的 License 签名、验证、吊销和密钥轮换</span>
            </div>
            <div class="header-right">
                <el-button @click="loadPublicKey">
                    <el-icon><Refresh /></el-icon>
                    刷新
                </el-button>
            </div>
        </div>

        <el-row :gutter="16">
            <!-- 签名密钥 -->
            <el-col :span="8">
                <el-card shadow="never" class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>签名密钥</span>
                            <el-button size="small" type="warning" @click="handleInitKeys" :loading="initKeying">
                                <el-icon><Refresh /></el-icon> 轮换密钥
                            </el-button>
                        </div>
                    </template>
                    <div v-loading="loadingKey">
                        <div v-if="publicKeyInfo">
                            <el-descriptions :column="1" border size="small">
                                <el-descriptions-item label="密钥版本">{{ publicKeyInfo.key_version }}</el-descriptions-item>
                                <el-descriptions-item label="算法">{{ publicKeyInfo.algorithm }}</el-descriptions-item>
                                <el-descriptions-item label="创建时间">{{ formatTime(publicKeyInfo.created_at) }}</el-descriptions-item>
                                <el-descriptions-item label="过期时间">{{ formatTime(publicKeyInfo.expires_at) }}</el-descriptions-item>
                                <el-descriptions-item label="公钥">
                                    <code class="key-text">{{ publicKeyInfo.public_key?.substring(0, 48) }}...</code>
                                    <el-button text size="small" @click="copyText(publicKeyInfo.public_key)">
                                        <el-icon><CopyDocument /></el-icon>
                                    </el-button>
                                </el-descriptions-item>
                            </el-descriptions>
                        </div>
                        <el-empty v-else-if="!loadingKey" :image-size="50" description="暂无签名密钥" />
                    </div>
                </el-card>

                <!-- 吊销 License -->
                <el-card shadow="never">
                    <template #header>
                        <span>吊销 / 恢复 License</span>
                    </template>
                    <el-form :model="revokeForm" label-width="80px" size="small">
                        <el-form-item label="License Key">
                            <el-input v-model="revokeForm.license_key" placeholder="输入 License Key" />
                        </el-form-item>
                        <el-form-item label="吊销原因" v-if="showRevokeInput">
                            <el-input v-model="revokeForm.reason" placeholder="如：客户退款" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="danger" size="small" @click="handleRevoke" :loading="revoking" :disabled="!revokeForm.license_key">
                                <el-icon><Close /></el-icon> 吊销
                            </el-button>
                            <el-button type="success" size="small" @click="handleRestore" :loading="restoring" :disabled="!revokeForm.license_key" class="ml-2">
                                <el-icon><CircleCheck /></el-icon> 恢复
                            </el-button>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-col>

            <!-- 生成离线 License -->
            <el-col :span="16">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>生成离线 License 文件</span>
                            <el-button size="small" type="primary" @click="showGenerateDialog = true">
                                <el-icon><Plus /></el-icon> 批量生成
                            </el-button>
                        </div>
                    </template>

                    <el-alert
                        title="说明"
                        type="info"
                        :closable="false"
                        show-icon
                        class="mb-4"
                        description="输入 License ID 生成可离线验证的 .license 文件。离线 License 使用 Ed25519 签名，可在无网络连接的环境中使用。"
                    />

                    <!-- 单 License 生成 -->
                    <el-form :model="generateForm" label-width="120px" :inline="true">
                        <el-form-item label="License ID">
                            <el-input-number v-model="generateForm.license_id" :min="1" style="width: 200px" placeholder="输入 License ID" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="handleGenerate" :loading="generating">生成离线文件</el-button>
                        </el-form-item>
                    </el-form>

                    <!-- 生成结果 -->
                    <div v-if="generatedFile" class="generated-result">
                        <el-alert type="success" :closable="true" show-icon @close="generatedFile = null">
                            <template #title>
                                <span>离线 License 文件生成成功</span>
                            </template>
                            <div class="file-detail">
                                <div class="file-detail-row">
                                    <span class="file-label">License Key:</span>
                                    <code>{{ generatedFile.license_key }}</code>
                                </div>
                                <div class="file-detail-row">
                                    <span class="file-label">签名算法:</span>
                                    <span>{{ generatedFile.algorithm }}</span>
                                </div>
                                <div class="file-detail-row" v-if="generatedFile.expires_at">
                                    <span class="file-label">过期时间:</span>
                                    <span>{{ formatTime(generatedFile.expires_at) }}</span>
                                </div>
                                <div class="file-detail-row">
                                    <span class="file-label">文件内容:</span>
                                    <el-button text size="small" type="primary" @click="copyText(generatedFile.license_file)">
                                        <el-icon><CopyDocument /></el-icon> 复制文件内容
                                    </el-button>
                                </div>
                            </div>
                            <pre class="file-preview">{{ generatedFile.license_file?.substring(0, 200) }}...</pre>
                        </el-alert>
                    </div>
                </el-card>

                <!-- 最近离线文件记录 -->
                <el-card shadow="never" class="mt-4">
                    <template #header>
                        <span>最近生成的离线文件</span>
                    </template>
                    <el-empty :image-size="50" description="暂无生成记录" />
                </el-card>
            </el-col>
        </el-row>

        <!-- 批量生成 Dialog -->
        <el-dialog v-model="showGenerateDialog" title="批量生成离线 License" width="520px">
            <el-form :model="batchForm" label-width="100px">
                <el-form-item label="License IDs">
                    <el-input
                        v-model="batchForm.license_ids"
                        type="textarea"
                        :rows="4"
                        placeholder="每行一个 License ID，如：&#10;123&#10;456&#10;789"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleBatchGenerate" :loading="batchGenerating">
                        批量生成
                    </el-button>
                </el-form-item>
            </el-form>

            <div v-if="batchResults.length" class="batch-results">
                <el-divider />
                <h4>生成结果</h4>
                <div v-for="(r, i) in batchResults" :key="i" class="batch-item">
                    <span class="batch-index">#{{ i + 1 }}</span>
                    <code>{{ r.license_key }}</code>
                    <el-tag v-if="!r.error" type="success" size="small">成功</el-tag>
                    <el-tag v-else type="danger" size="small">{{ r.error }}</el-tag>
                    <el-button v-if="r.license_file" text size="small" @click="copyText(r.license_file)">
                        复制
                    </el-button>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, Close, CircleCheck, CopyDocument } from '@element-plus/icons-vue';
import offlineApi from '@/api/offline';

const loadingKey = ref(false);
const generating = ref(false);
const batchGenerating = ref(false);
const initKeying = ref(false);
const revoking = ref(false);
const restoring = ref(false);
const showGenerateDialog = ref(false);
const showRevokeInput = ref(false);

const publicKeyInfo = ref(null);
const generatedFile = ref(null);
const batchResults = ref([]);

const revokeForm = reactive({
    license_key: '',
    reason: '管理员吊销',
});

const generateForm = reactive({
    license_id: null,
});

const batchForm = reactive({
    license_ids: '',
});

function formatTime(time) {
    if (!time) return '—';
    return new Date(time).toLocaleString('zh-CN');
}

function copyText(text) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        ElMessage.success('已复制到剪贴板');
    }).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        ElMessage.success('已复制到剪贴板');
    });
}

async function loadPublicKey() {
    loadingKey.value = true;
    try {
        const { data: res } = await offlineApi.publicKey();
        if (res.success) {
            publicKeyInfo.value = res.data;
        }
    } catch {
        publicKeyInfo.value = null;
    } finally {
        loadingKey.value = false;
    }
}

async function handleGenerate() {
    if (!generateForm.license_id) {
        ElMessage.warning('请输入 License ID');
        return;
    }
    generating.value = true;
    try {
        const { data: res } = await offlineApi.generate(generateForm.license_id);
        if (res.success) {
            generatedFile.value = res.data;
            ElMessage.success('离线 License 文件生成成功');
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '生成失败');
    } finally {
        generating.value = false;
    }
}

async function handleBatchGenerate() {
    const ids = batchForm.license_ids
        .split('\n')
        .map(s => s.trim())
        .filter(s => s && !isNaN(Number(s)))
        .map(Number);

    if (ids.length === 0) {
        ElMessage.warning('请输入有效的 License ID');
        return;
    }

    batchGenerating.value = true;
    batchResults.value = [];
    try {
        const { data: res } = await offlineApi.generateBatch(ids);
        if (res.success) {
            batchResults.value = Array.isArray(res.data) ? res.data : [res.data];
            ElMessage.success(`已生成 ${batchResults.value.length} 个离线文件`);
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '批量生成失败');
    } finally {
        batchGenerating.value = false;
    }
}

async function handleInitKeys() {
    try {
        await ElMessageBox.confirm(
            '轮换签名密钥后，旧的离线 License 文件将失效！确认轮换？',
            '密钥轮换',
            { confirmButtonText: '确认轮换', cancelButtonText: '取消', type: 'warning' },
        );
        initKeying.value = true;
        const { data: res } = await offlineApi.initKeys();
        if (res.success) {
            ElMessage.success('签名密钥已轮换');
            loadPublicKey();
        }
    } catch {
        // cancelled
    } finally {
        initKeying.value = false;
    }
}

async function handleRevoke() {
    if (!revokeForm.license_key) return;
    revoking.value = true;
    try {
        const { data: res } = await offlineApi.revoke(revokeForm.license_key, revokeForm.reason);
        if (res.success) {
            ElMessage.success('License 已加入离线吊销列表');
            revokeForm.license_key = '';
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '吊销失败');
    } finally {
        revoking.value = false;
    }
}

async function handleRestore() {
    if (!revokeForm.license_key) return;
    restoring.value = true;
    try {
        const { data: res } = await offlineApi.restore(revokeForm.license_key);
        if (res.success) {
            ElMessage.success('License 已移出离线吊销列表');
            revokeForm.license_key = '';
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '恢复失败');
    } finally {
        restoring.value = false;
    }
}

onMounted(() => {
    loadPublicKey();
});
</script>

<style scoped>
.offline-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.ml-2 { margin-left: 8px; }

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
}

.key-text {
    font-size: 11px;
    word-break: break-all;
    user-select: all;
}

.generated-result {
    margin-top: 16px;
}
.file-detail {
    margin-top: 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.file-detail-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}
.file-label {
    font-weight: 600;
    color: var(--el-text-color-secondary);
    min-width: 100px;
}
.file-preview {
    margin-top: 8px;
    background: #f5f7fa;
    padding: 10px;
    border-radius: 4px;
    font-size: 11px;
    word-break: break-all;
    max-height: 100px;
    overflow: hidden;
}

.batch-results h4 {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 12px;
}
.batch-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
    border-bottom: 1px solid var(--el-border-color-light);
    font-size: 13px;
}
.batch-index {
    font-weight: 600;
    color: var(--el-text-color-placeholder);
    min-width: 24px;
}

:deep(.el-card__body) { padding: 16px; }
</style>
