<template>
    <div class="hsm-page">
        <div class="page-header">
            <h2>HSM 硬件安全模块</h2>
            <el-button type="primary" @click="refresh">
                <el-icon><Refresh /></el-icon> 刷新
            </el-button>
        </div>

        <el-row :gutter="16">
            <!-- 状态卡片 -->
            <el-col :span="8">
                <el-card class="mb-4">
                    <template #header><span>🔐 HSM 状态</span></template>
                    <div class="status-items">
                        <div class="status-item">
                            <span>启用状态</span>
                            <el-tag :type="health.healthy ? 'success' : 'danger'" size="small">
                                {{ health.healthy ? '已启用' : '未启用' }}
                            </el-tag>
                        </div>
                        <div class="status-item">
                            <span>提供者</span>
                            <code>{{ health.provider }}</code>
                        </div>
                        <div class="status-item">
                            <span>健康状态</span>
                            <el-tag :type="health.healthy ? 'success' : 'warning'" size="small">
                                {{ health.healthy ? '正常' : '异常' }}
                            </el-tag>
                        </div>
                        <div class="status-item">
                            <span>消息</span>
                            <span class="text-secondary">{{ health.message }}</span>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <!-- 统计卡片 -->
            <el-col :span="8">
                <el-card class="mb-4">
                    <template #header><span>📊 签名统计</span></template>
                    <div class="status-items">
                        <div class="status-item">
                            <span>密钥总数</span>
                            <strong>{{ stats.total_keys }}</strong>
                        </div>
                        <div class="status-item">
                            <span>活跃密钥</span>
                            <strong>{{ stats.active_keys }}</strong>
                        </div>
                        <div class="status-item">
                            <span>总签名次数</span>
                            <strong>{{ stats.total_signatures }}</strong>
                        </div>
                        <div class="status-item">
                            <span>算法</span>
                            <el-tag size="small">Ed25519</el-tag>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <!-- 操作 -->
            <el-col :span="8">
                <el-card class="mb-4">
                    <template #header><span>⚡ 快速操作</span></template>
                    <div class="quick-actions">
                        <el-button type="primary" @click="showInitDialog = true" class="action-btn">
                            初始化密钥
                        </el-button>
                        <el-button type="warning" @click="handleRotate" class="action-btn">
                            轮换密钥
                        </el-button>
                        <el-button @click="showSignDialog = true" class="action-btn">
                            测试签名
                        </el-button>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 密钥列表 -->
        <el-card>
            <template #header><span>🗝️ HSM 密钥列表</span></template>
            <el-table :data="keys" stripe v-loading="loading">
                <el-table-column prop="id" label="ID" width="60" />
                <el-table-column prop="key_label" label="标签" width="140">
                    <template #default="{ row }">
                        <code>{{ row.key_label }}</code>
                    </template>
                </el-table-column>
                <el-table-column prop="algorithm" label="算法" width="100" />
                <el-table-column prop="provider" label="提供者" width="120" />
                <el-table-column label="公钥" min-width="200">
                    <template #default="{ row }">
                        <code class="key-truncate">{{ row.public_key?.substring(0, 40) }}...</code>
                    </template>
                </el-table-column>
                <el-table-column prop="sign_count" label="签名次数" width="100" align="right" />
                <el-table-column label="活跃" width="70" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                            {{ row.is_active ? '是' : '否' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="170" />
            </el-table>
        </el-card>

        <!-- 初始化对话框 -->
        <el-dialog v-model="showInitDialog" title="初始化 HSM 密钥" width="400px">
            <el-form label-width="100px">
                <el-form-item label="密钥标签">
                    <el-input v-model="initForm.label" placeholder="license-v1" />
                </el-form-item>
                <el-form-item label="算法">
                    <el-select v-model="initForm.algorithm">
                        <el-option label="Ed25519（推荐）" value="Ed25519" />
                        <el-option label="RSA-2048（兼容）" value="RSA" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showInitDialog = false">取消</el-button>
                <el-button type="primary" @click="handleInit" :loading="initLoading">创建</el-button>
            </template>
        </el-dialog>

        <!-- 签名对话框 -->
        <el-dialog v-model="showSignDialog" title="测试 HSM 签名" width="500px">
            <el-form label-width="100px">
                <el-form-item label="License Key">
                    <el-input v-model="signForm.licenseKey" placeholder="HWT-ENT-xxxx" />
                </el-form-item>
                <el-form-item label="密钥">
                    <el-select v-model="signForm.keyId" filterable>
                        <el-option v-for="k in keys" :key="k.id" :label="`#${k.id} ${k.key_label}`" :value="k.id" />
                    </el-select>
                </el-form-item>
                <div v-if="signResult" class="sign-result">
                    <p class="result-label">签名结果：</p>
                    <code class="result-value">{{ signResult }}</code>
                </div>
            </el-form>
            <template #footer>
                <el-button @click="showSignDialog = false">关闭</el-button>
                <el-button type="primary" @click="handleTestSign" :loading="signLoading">签名</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Refresh } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import apiClient from '@/api/client';

const loading = ref(false);
const keys = ref([]);
const health = ref({});
const stats = ref({});
const showInitDialog = ref(false);
const showSignDialog = ref(false);
const initLoading = ref(false);
const signLoading = ref(false);
const signResult = ref('');

const initForm = ref({ label: 'license-v1', algorithm: 'Ed25519' });
const signForm = ref({ licenseKey: '', keyId: null });

async function refresh() {
    loading.value = true;
    try {
        const [healthRes, statsRes, keysRes] = await Promise.all([
            apiClient.get('/hsm/health'),
            apiClient.get('/hsm/stats'),
            apiClient.get('/hsm/keys'),
        ]);
        health.value = healthRes.data?.data || {};
        stats.value = statsRes.data?.data || {};
        keys.value = keysRes.data?.data || [];
    } catch {
        health.value = { healthy: false, message: 'API 不可用' };
    } finally {
        loading.value = false;
    }
}

async function handleInit() {
    initLoading.value = true;
    try {
        await apiClient.post('/hsm/init', initForm.value);
        ElMessage.success('密钥创建成功');
        showInitDialog.value = false;
        refresh();
    } catch (e) {
        ElMessage.error('创建失败: ' + (e.response?.data?.message || e.message));
    } finally {
        initLoading.value = false;
    }
}

async function handleRotate() {
    try {
        await ElMessageBox.confirm('确认轮换密钥？旧密钥将被停用，新密钥将用于后续签名。', '确认轮换');
        await apiClient.post('/hsm/rotate', { label: 'license-v1' });
        ElMessage.success('密钥轮换成功');
        refresh();
    } catch { /* 取消 */ }
}

async function handleTestSign() {
    if (!signForm.value.licenseKey || !signForm.value.keyId) {
        ElMessage.warning('请填写 License Key 并选择密钥');
        return;
    }
    signLoading.value = true;
    try {
        const { data } = await apiClient.post('/hsm/sign', signForm.value);
        signResult.value = data?.data?.signature || '';
        ElMessage.success('签名成功');
    } catch (e) {
        ElMessage.error('签名失败: ' + (e.response?.data?.message || e.message));
    } finally {
        signLoading.value = false;
    }
}

onMounted(refresh);
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
