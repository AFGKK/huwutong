<template>
    <div class="security-headers-page">
        <div class="page-header">
            <div class="header-left">
                <h2>安全响应头管理</h2>
                <span class="header-subtitle">配置 HSTS/X-Frame-Options/Referrer-Policy 等 HTTP 安全响应头</span>
            </div>
            <div class="header-right">
                <el-button @click="handleReset">恢复默认</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">保存配置</el-button>
            </div>
        </div>

        <el-row :gutter="16">
            <el-col :span="16">
                <el-card shadow="never">
                    <template #header><span>响应头配置</span></template>
                    <el-form :model="form" label-width="200px" label-position="left">
                        <!-- HSTS -->
                        <el-divider content-position="left">Strict-Transport-Security (HSTS)</el-divider>
                        <el-form-item label="启用 HSTS">
                            <el-switch v-model="form.hsts" />
                        </el-form-item>
                        <el-form-item v-if="form.hsts" label="max-age (秒)">
                            <el-input-number v-model="form.hsts_max_age" :min="0" :max="31536000" :step="86400" />
                            <span class="ml-2 text-muted">默认 31536000（1年）</span>
                        </el-form-item>
                        <el-form-item v-if="form.hsts" label="includeSubDomains">
                            <el-switch v-model="form.hsts_include_subdomains" />
                        </el-form-item>

                        <!-- X-Frame-Options -->
                        <el-divider content-position="left">X-Frame-Options（防点击劫持）</el-divider>
                        <el-form-item label="策略">
                            <el-select v-model="form.x_frame_options" style="width:200px">
                                <el-option label="DENY（禁止）" value="DENY" />
                                <el-option label="SAMEORIGIN（同源）" value="SAMEORIGIN" />
                                <el-option label="ALLOW-FROM（指定源）" value="ALLOW-FROM" />
                                <el-option label="关闭" value="off" />
                            </el-select>
                        </el-form-item>
                        <el-form-item v-if="form.x_frame_options === 'ALLOW-FROM'" label="允许来源">
                            <el-input v-model="form.x_frame_options_origin" placeholder="https://example.com" />
                        </el-form-item>

                        <!-- X-Content-Type-Options -->
                        <el-divider content-position="left">X-Content-Type-Options（防 MIME 嗅探）</el-divider>
                        <el-form-item label="策略">
                            <el-select v-model="form.x_content_type_options" style="width:200px">
                                <el-option label="nosniff" value="nosniff" />
                                <el-option label="关闭" value="off" />
                            </el-select>
                        </el-form-item>

                        <!-- Referrer-Policy -->
                        <el-divider content-position="left">Referrer-Policy</el-divider>
                        <el-form-item label="策略">
                            <el-select v-model="form.referrer_policy" style="width:300px">
                                <el-option label="strict-origin-when-cross-origin" value="strict-origin-when-cross-origin" />
                                <el-option label="no-referrer" value="no-referrer" />
                                <el-option label="same-origin" value="same-origin" />
                                <el-option label="origin" value="origin" />
                                <el-option label="strict-origin" value="strict-origin" />
                                <el-option label="unsafe-url" value="unsafe-url" />
                                <el-option label="关闭" value="off" />
                            </el-select>
                        </el-form-item>

                        <!-- Permissions-Policy -->
                        <el-divider content-position="left">Permissions-Policy（浏览器 API 权限）</el-divider>
                        <el-form-item label="启用">
                            <el-switch v-model="form.permissions_policy_enabled" />
                        </el-form-item>
                        <el-form-item v-if="form.permissions_policy_enabled" label="策略值">
                            <el-input v-model="form.permissions_policy" type="textarea" :rows="2" />
                        </el-form-item>

                        <!-- X-XSS-Protection -->
                        <el-divider content-position="left">X-XSS-Protection</el-divider>
                        <el-form-item label="策略">
                            <el-select v-model="form.x_xss_protection" style="width:200px">
                                <el-option label="1; mode=block" value="1; mode=block" />
                                <el-option label="1（启用）" value="1" />
                                <el-option label="0（禁用）" value="0" />
                                <el-option label="关闭" value="off" />
                            </el-select>
                        </el-form-item>

                        <!-- Cache-Control -->
                        <el-divider content-position="left">Cache-Control</el-divider>
                        <el-form-item label="启用">
                            <el-switch v-model="form.cache_control_enabled" />
                        </el-form-item>
                        <el-form-item v-if="form.cache_control_enabled" label="策略值">
                            <el-input v-model="form.cache_control" />
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-col>

            <el-col :span="8">
                <el-card shadow="never">
                    <template #header>
                        <span>生效预览</span>
                        <el-button size="small" @click="loadPreview">刷新</el-button>
                    </template>
                    <div v-if="previewHeaders">
                        <div v-for="(val, key) in previewHeaders" :key="key" class="header-item">
                            <div class="header-key">{{ key }}</div>
                            <div class="header-value">{{ val }}</div>
                        </div>
                    </div>
                    <el-empty v-else description="点击刷新查看" />
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import api from '@/api/securityHeaders';

const form = ref({});
const previewHeaders = ref(null);
const saving = ref(false);

function unwrap(res) {
    const body = res?.data ?? res;
    return body?.data ?? body;
}

async function loadConfig() {
    try {
        const res = await api.getConfig();
        form.value = unwrap(res) || {};
    } catch (e) {
        ElMessage.error('加载配置失败');
    }
}

async function loadPreview() {
    try {
        const res = await api.preview();
        const data = unwrap(res);
        previewHeaders.value = data?.headers || null;
    } catch (e) {
        ElMessage.error('加载预览失败');
    }
}

async function handleSave() {
    saving.value = true;
    try {
        await api.updateConfig(form.value);
        ElMessage.success('配置已保存');
        await loadPreview();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败');
    } finally {
        saving.value = false;
    }
}

async function handleReset() {
    try {
        await api.reset();
        await loadConfig();
        await loadPreview();
        ElMessage.success('已恢复默认配置');
    } catch (e) {
        ElMessage.error('重置失败');
    }
}

onMounted(() => {
    loadConfig();
    loadPreview();
});
</script>

<style scoped>
.security-headers-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.header-left h2 { margin: 0; font-size: 20px; display: inline; }
.header-subtitle { font-size: 13px; color: #999; margin-left: 8px; }
.header-item { padding: 8px; margin-bottom: 4px; background: #f5f7fa; border-radius: 4px; word-break: break-all; }
.header-key { font-weight: bold; font-size: 12px; color: #409eff; margin-bottom: 2px; }
.header-value { font-size: 11px; color: #666; font-family: monospace; }
.text-muted { color: #999; font-size: 12px; }
.ml-2 { margin-left: 8px; }
</style>
