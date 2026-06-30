<template>
    <div class="embedded-widget-page">
        <div class="page-header">
            <h2>嵌入式 Widget</h2>
            <el-button type="primary" @click="showGenerateDialog = true">
                <el-icon><Plus /></el-icon> 生成嵌入令牌
            </el-button>
        </div>

        <!-- 使用说明 -->
        <el-card class="mb-4">
            <template #header>
                <span>📖 使用说明</span>
            </template>
            <div class="guide-content">
                <p>将 License 管理功能嵌入到您自己的产品后台，客户无需切换系统即可查看和管理授权。</p>
                <el-steps :active="3" align-center class="guide-steps">
                    <el-step title="生成令牌" description="为客户生成 JWT 签名的嵌入令牌" />
                    <el-step title="嵌入代码" description="复制一段 JavaScript 代码到您的页面" />
                    <el-step title="客户使用" description="客户在您的产品内直接管理 License" />
                </el-steps>
            </div>
        </el-card>

        <el-row :gutter="16">
            <!-- 代码生成 -->
            <el-col :span="14">
                <el-card class="mb-4">
                    <template #header>
                        <span>🔗 嵌入代码</span>
                    </template>
                    <el-form label-width="100px">
                        <el-form-item label="客户">
                            <el-select v-model="selectedCustomer" filterable placeholder="选择客户" style="width:100%">
                                <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="权限">
                            <el-checkbox-group v-model="selectedPermissions">
                                <el-checkbox label="licenses:read">查看 License</el-checkbox>
                                <el-checkbox label="licenses:write">管理 License</el-checkbox>
                                <el-checkbox label="devices:read">查看设备</el-checkbox>
                            </el-checkbox-group>
                        </el-form-item>
                        <el-form-item label="有效期">
                            <el-select v-model="expiresIn">
                                <el-option label="1 小时" :value="3600" />
                                <el-option label="6 小时" :value="21600" />
                                <el-option label="24 小时" :value="86400" />
                                <el-option label="7 天" :value="604800" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="主题色">
                            <el-color-picker v-model="brandColor" show-alpha :predefine="['#1a73e8','#409eff','#67c23a','#e6a23c','#f56c6c']" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="generateToken" :loading="generating">
                                生成令牌
                            </el-button>
                        </el-form-item>
                    </el-form>

                    <!-- 生成的代码 -->
                    <div v-if="generatedCode" class="generated-code">
                        <div class="code-header">
                            <span>✅ 嵌入代码已生成</span>
                            <el-button size="small" text @click="copyCode">
                                <el-icon><CopyDocument /></el-icon> 复制
                            </el-button>
                        </div>
                        <pre><code v-text="generatedCode"></code></pre>

                        <el-alert
                            title="安全提示"
                            type="warning"
                            :closable="false"
                            show-icon
                            class="mt-2"
                        >
                            <template #default>
                                令牌包含敏感权限，请勿在公开页面中暴露。<br>
                                建议在服务端生成令牌并通过 API 传递给前端。
                            </template>
                        </el-alert>

                        <div class="preview-section mt-3">
                            <span class="preview-label">实时预览</span>
                            <div class="widget-preview">
                                <iframe
                                    :src="previewUrl"
                                    style="width:100%;border:none;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1)"
                                    :style="{ height: previewHeight + 'px' }"
                                    title="Widget 预览"
                                />
                            </div>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <!-- 使用统计 -->
            <el-col :span="10">
                <el-card class="mb-4">
                    <template #header>
                        <span>📊 使用统计</span>
                    </template>
                    <el-descriptions :column="1" border>
                        <el-descriptions-item label="已生成令牌">--</el-descriptions-item>
                        <el-descriptions-item label="活跃令牌">--</el-descriptions-item>
                        <el-descriptions-item label="本月加载次数">--</el-descriptions-item>
                    </el-descriptions>
                </el-card>

                <!-- 快速开始 -->
                <el-card>
                    <template #header>
                        <span>🚀 快速开始</span>
                    </template>
                    <div class="quickstart">
                        <h4>原生 JS</h4>
                        <pre><code>&lt;script src="/js/widget-sdk/hwt-widget.js"&gt;&lt;/script&gt;
&lt;script&gt;
  HWTWidget.init({
    token: 'YOUR_TOKEN',
    container: '#widget-container',
    color: '#1a73e8',
  });
&lt;/script&gt;</code></pre>

                        <h4>React</h4>
                        <pre><code>import { HWTWidget } from 'hwt-widget';

function App() {
  return &lt;HWTWidget.ReactComponent
    token="YOUR_TOKEN"
    color="#1a73e8"
  /&gt;;
}</code></pre>

                        <h4>Vue</h4>
                        <pre><code>&lt;template&gt;
  &lt;HwtWidget
    token="YOUR_TOKEN"
    color="#1a73e8"
  /&gt;
&lt;/template&gt;</code></pre>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 生成对话框 -->
        <el-dialog v-model="showResultDialog" title="令牌已生成" width="500px">
            <div class="token-result">
                <el-alert title="请立即复制令牌，关闭后不再显示" type="warning" :closable="false" show-icon />
                <div class="token-value">
                    <code>{{ generatedToken }}</code>
                </div>
                <el-button type="primary" @click="copyToken" class="mt-2">
                    <el-icon><CopyDocument /></el-icon> 复制令牌
                </el-button>
            </div>
            <template #footer>
                <el-button @click="showResultDialog = false">关闭</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus, CopyDocument } from '@element-plus/icons-vue';
import apiClient from '@/api/client';

const customers = ref([]);
const selectedCustomer = ref(null);
const selectedPermissions = ref(['licenses:read', 'devices:read']);
const expiresIn = ref(3600);
const brandColor = ref('#1a73e8');
const generating = ref(false);
const generatedToken = ref('');
const generatedCode = ref('');
const showResultDialog = ref(false);
const previewHeight = ref(400);

const previewUrl = computed(() => {
    if (!generatedToken.value) return '';
    const params = new URLSearchParams({
        token: generatedToken.value,
        color: brandColor.value.replace('#', ''),
    });
    return `/widget/embed?${params.toString()}`;
});

async function generateToken() {
    if (!selectedCustomer.value) {
        ElMessage.warning('请选择客户');
        return;
    }
    generating.value = true;
    try {
        const { data } = await apiClient.post('/widget/token', {
            customer_id: selectedCustomer.value,
            permissions: selectedPermissions.value,
            expires_in: expiresIn.value,
        });
        const result = data.data;
        generatedToken.value = result.token;

        // 生成嵌入代码
        const host = window.location.origin;
        generatedCode.value = `<script src="${host}/js/widget-sdk/hwt-widget.js"><\/script>\n<script>\nHWTWidget.init({\n  token: '${result.token}',\n  container: '#license-widget',\n  color: '${brandColor.value}',\n});\n<\/script>`;

        showResultDialog.value = true;
        ElMessage.success('令牌已生成');
    } catch (e) {
        ElMessage.error('生成失败: ' + (e.response?.data?.message || e.message));
    } finally {
        generating.value = false;
    }
}

function copyCode() {
    navigator.clipboard.writeText(generatedCode.value);
    ElMessage.success('代码已复制');
}

function copyToken() {
    navigator.clipboard.writeText(generatedToken.value);
    ElMessage.success('令牌已复制');
}
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.guide-content { padding: 8px 0; }
.guide-steps { margin-top: 16px; }
.generated-code { margin-top: 16px; }
.code-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.generated-code pre {
    background: #f5f7fa;
    border: 1px solid #e4e7ed;
    border-radius: 4px;
    padding: 12px;
    overflow-x: auto;
    font-size: 12px;
    line-height: 1.6;
}
.generated-code code { font-family: 'SF Mono', 'Cascadia Code', monospace; }
.preview-section { margin-top: 16px; }
.preview-label { font-size: 14px; font-weight: 600; margin-bottom: 8px; display: block; }
.widget-preview { border: 1px solid #e4e7ed; border-radius: 8px; overflow: hidden; }
.token-result { text-align: center; padding: 16px; }
.token-value {
    margin: 16px 0;
    padding: 12px;
    background: #f5f7fa;
    border-radius: 4px;
    word-break: break-all;
}
.token-value code { font-size: 12px; }
.quickstart h4 { margin: 12px 0 4px; font-size: 13px; }
.quickstart pre {
    background: #f5f7fa;
    border-radius: 4px;
    padding: 8px;
    font-size: 11px;
    overflow-x: auto;
}
.mt-2 { margin-top: 8px; }
.mt-3 { margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }
</style>
