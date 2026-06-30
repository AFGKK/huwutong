<template>
    <div class="smtp-fallback-page">
        <div class="page-header">
            <div class="header-left">
                <h2>SMTP 降级配置</h2>
                <span class="header-subtitle">配置 SMTP 多渠道降级策略和故障转移</span>
            </div>
            <div class="header-right">
                <el-button @click="handleTest" :loading="testing">测试降级链</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">保存配置</el-button>
            </div>
        </div>

        <el-row :gutter="16">
            <el-col :span="8">
                <el-card shadow="never">
                    <template #header><span>当前状态</span></template>
                    <div class="status-list">
                        <div class="status-item">
                            <span class="label">主 SMTP</span>
                            <el-tag :type="status.primary_healthy ? 'success' : 'danger'" size="small">
                                {{ status.primary_healthy ? '正常' : '异常' }}
                            </el-tag>
                        </div>
                        <div class="status-item">
                            <span class="label">备用 SMTP</span>
                            <el-tag :type="status.backup_healthy ? 'success' : 'warning'" size="small">
                                {{ status.backup_healthy ? '正常' : '异常' }}
                            </el-tag>
                        </div>
                        <div class="status-item">
                            <span class="label">当前使用</span>
                            <el-tag :type="status.currently_using === 'primary' ? 'success' : 'warning'" size="small">
                                {{ status.currently_using === 'primary' ? '主 SMTP' : status.currently_using === 'backup' ? '备用 SMTP' : '无可用' }}
                            </el-tag>
                        </div>
                        <div class="status-item">
                            <span class="label">上次降级</span>
                            <span class="value">{{ status.last_fallback_at || '—' }}</span>
                        </div>
                        <div class="status-item">
                            <span class="label">上次恢复</span>
                            <span class="value">{{ status.last_recovery_at || '—' }}</span>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <el-col :span="16">
                <el-card shadow="never">
                    <template #header><span>降级策略配置</span></template>
                    <el-form :model="form" label-width="200px" label-position="left">
                        <el-form-item label="失败阈值（次数）">
                            <el-input-number v-model="form.failure_threshold" :min="1" :max="20" />
                            <span class="ml-2 text-muted">连续失败 N 次后切换到备用 SMTP</span>
                        </el-form-item>
                        <el-form-item label="恢复检查间隔（分钟）">
                            <el-input-number v-model="form.recovery_interval" :min="5" :max="1440" :step="5" />
                            <span class="ml-2 text-muted">每 N 分钟检查主 SMTP 是否恢复</span>
                        </el-form-item>
                        <el-form-item label="自动恢复">
                            <el-switch v-model="form.auto_recover" />
                            <span class="ml-2 text-muted">主 SMTP 恢复后自动切回</span>
                        </el-form-item>
                        <el-divider content-position="left">通知配置</el-divider>
                        <el-form-item label="降级时通知">
                            <el-switch v-model="form.notify_on_fallback" />
                        </el-form-item>
                        <el-form-item label="恢复时通知">
                            <el-switch v-model="form.notify_on_recovery" />
                        </el-form-item>
                        <el-form-item label="通知邮箱">
                            <el-select v-model="form.notify_emails" multiple filterable allow-create default-first-option style="width:100%">
                                <el-option v-for="email in form.notify_emails" :key="email" :value="email" />
                            </el-select>
                            <span class="ml-2 text-muted">输入邮箱后按回车添加</span>
                        </el-form-item>
                    </el-form>
                </el-card>

                <!-- 测试结果 -->
                <el-card v-if="testResults" shadow="never" style="margin-top:16px;">
                    <template #header>
                        <span>测试结果</span>
                        <el-tag :type="testResults.success ? 'success' : 'danger'" size="small">
                            {{ testResults.success ? '通过' : '失败' }}
                        </el-tag>
                    </template>
                    <p>{{ testResults.message }}</p>
                    <el-table :data="testResults.results" stripe size="small">
                        <el-table-column prop="host" label="主机" />
                        <el-table-column prop="provider" label="提供商" />
                        <el-table-column label="主/备" width="70">
                            <template #default="{ row }">{{ row.is_primary ? '主' : '备' }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="90">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'success' ? 'success' : 'danger'" size="small">
                                    {{ row.status === 'success' ? '正常' : '失败' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="latency_ms" label="延迟(ms)" width="90" />
                        <el-table-column prop="error" label="错误信息" show-overflow-tooltip />
                    </el-table>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import api from '@/api/smtpFallback';

const form = ref({
    failure_threshold: 3,
    recovery_interval: 30,
    notify_on_fallback: true,
    notify_on_recovery: true,
    notify_emails: [],
    auto_recover: true,
});
const status = ref({});
const testResults = ref(null);
const saving = ref(false);
const testing = ref(false);

function unwrap(res) {
    const body = res?.data ?? res;
    return body?.data ?? body;
}

async function loadConfig() {
    try {
        const res = await api.getConfig();
        const data = unwrap(res) || {};
        form.value.failure_threshold = data.failure_threshold ?? 3;
        form.value.recovery_interval = data.recovery_interval ?? 30;
        form.value.notify_on_fallback = data.notify_on_fallback ?? true;
        form.value.notify_on_recovery = data.notify_on_recovery ?? true;
        form.value.notify_emails = data.notify_emails || [];
        form.value.auto_recover = data.auto_recover ?? true;
        status.value = data.current_status || {};
    } catch (e) {
        ElMessage.error('加载配置失败');
    }
}

async function handleSave() {
    saving.value = true;
    try {
        await api.updateConfig(form.value);
        await loadConfig();
        ElMessage.success('配置已保存');
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败');
    } finally {
        saving.value = false;
    }
}

async function handleTest() {
    testing.value = true;
    testResults.value = null;
    try {
        const res = await api.test();
        testResults.value = unwrap(res);
    } catch (e) {
        ElMessage.error('测试失败');
    } finally {
        testing.value = false;
    }
}

onMounted(() => {
    loadConfig();
});
</script>

<style scoped>
.smtp-fallback-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.header-left h2 { margin: 0; font-size: 20px; display: inline; }
.header-subtitle { font-size: 13px; color: #999; margin-left: 8px; }
.status-list { display: flex; flex-direction: column; gap: 12px; }
.status-item { display: flex; justify-content: space-between; align-items: center; }
.status-item .label { color: #666; font-size: 13px; }
.status-item .value { color: #333; font-size: 13px; }
.text-muted { color: #999; font-size: 12px; }
.ml-2 { margin-left: 8px; }
</style>
