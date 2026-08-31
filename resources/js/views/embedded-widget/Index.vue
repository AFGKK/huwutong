<template>
    <div class="embedded-widget-page">
        <div class="page-header">
            <h2>{{ t('embedded_widget_page.title') }}</h2>
            <el-button type="primary">
                <el-icon><Plus /></el-icon> {{ t('embedded_widget_page.generate_token_btn') }}
            </el-button>
        </div>

        <!-- 使用说明 -->
        <el-card class="mb-4">
            <template #header>
                <span>{{ t('embedded_widget_page.guide.title') }}</span>
            </template>
            <div class="guide-content">
                <p>{{ t('embedded_widget_page.guide.intro') }}</p>
                <el-steps :active="3" align-center class="guide-steps">
                    <el-step :title="t('embedded_widget_page.guide.steps.token.title')" :description="t('embedded_widget_page.guide.steps.token.desc')" />
                    <el-step :title="t('embedded_widget_page.guide.steps.embed.title')" :description="t('embedded_widget_page.guide.steps.embed.desc')" />
                    <el-step :title="t('embedded_widget_page.guide.steps.use.title')" :description="t('embedded_widget_page.guide.steps.use.desc')" />
                </el-steps>
            </div>
        </el-card>

        <el-row :gutter="16">
            <!-- 代码生成 -->
            <el-col :span="14">
                <el-card class="mb-4">
                    <template #header>
                        <span>{{ t('embedded_widget_page.embed_code.title') }}</span>
                    </template>
                    <el-form label-width="100px">
                        <el-form-item :label="t('embedded_widget_page.embed_code.customer')">
                            <el-select v-model="selectedCustomer" filterable :placeholder="t('licenses_page.select_customer')" style="width:100%">
                                <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t('embedded_widget_page.embed_code.permissions')">
                            <el-checkbox-group v-model="selectedPermissions">
                                <el-checkbox label="licenses:read">{{ t('embedded_widget_page.permissions.licenses_read') }}</el-checkbox>
                                <el-checkbox label="licenses:write">{{ t('embedded_widget_page.permissions.licenses_write') }}</el-checkbox>
                                <el-checkbox label="devices:read">{{ t('embedded_widget_page.permissions.devices_read') }}</el-checkbox>
                            </el-checkbox-group>
                        </el-form-item>
                        <el-form-item :label="t('embedded_widget_page.embed_code.expires')">
                            <el-select v-model="expiresIn">
                                <el-option v-for="opt in expiresOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t('embedded_widget_page.embed_code.brand_color')">
                            <el-color-picker v-model="brandColor" show-alpha :predefine="['#1a73e8','#0f172a','#67c23a','#e6a23c','#f56c6c']" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="generateToken" :loading="generating">
                                {{ t('embedded_widget_page.embed_code.generate') }}
                            </el-button>
                        </el-form-item>
                    </el-form>

                    <!-- 生成的代码 -->
                    <div v-if="generatedCode" class="generated-code">
                        <div class="code-header">
                            <span>{{ t('embedded_widget_page.embed_code.generated') }}</span>
                            <el-button size="small" text @click="copyCode">
                                <el-icon><CopyDocument /></el-icon> {{ t('actions.copy') }}
                            </el-button>
                        </div>
                        <pre><code v-text="generatedCode"></code></pre>

                        <el-alert
                            :title="t('embedded_widget_page.security.title')"
                            type="warning"
                            :closable="false"
                            show-icon
                            class="mt-2"
                        >
                            <template #default>
                                {{ t('embedded_widget_page.security.line1') }}<br>
                                {{ t('embedded_widget_page.security.line2') }}
                            </template>
                        </el-alert>

                        <div class="preview-section mt-3">
                            <span class="preview-label">{{ t('embedded_widget_page.embed_code.preview') }}</span>
                            <div class="widget-preview">
                                <iframe
                                    :src="previewUrl"
                                    style="width:100%;border:none;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1)"
                                    :style="{ height: previewHeight + 'px' }"
                                    :title="t('embedded_widget_page.embed_code.preview_title')"
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
                        <span>{{ t('embedded_widget_page.stats.title') }}</span>
                    </template>
                    <el-descriptions :column="1" border>
                        <el-descriptions-item :label="t('embedded_widget_page.stats.tokens_generated')">--</el-descriptions-item>
                        <el-descriptions-item :label="t('embedded_widget_page.stats.active_tokens')">--</el-descriptions-item>
                        <el-descriptions-item :label="t('embedded_widget_page.stats.loads_this_month')">--</el-descriptions-item>
                    </el-descriptions>
                </el-card>

                <!-- 快速开始 -->
                <el-card>
                    <template #header>
                        <span>{{ t('embedded_widget_page.quickstart.title') }}</span>
                    </template>
                    <div class="quickstart">
                        <h4>{{ t('embedded_widget_page.quickstart.native_js') }}</h4>
                        <pre><code>&lt;script src="/js/widget-sdk/hwt-widget.js"&gt;&lt;/script&gt;
&lt;script&gt;
  HWTWidget.init({
    token: 'YOUR_TOKEN',
    container: '#widget-container',
    color: '#1a73e8',
  });
&lt;/script&gt;</code></pre>

                        <h4>{{ t('embedded_widget_page.quickstart.react') }}</h4>
                        <pre><code>import { HWTWidget } from 'hwt-widget';

function App() {
  return &lt;HWTWidget.ReactComponent
    token="YOUR_TOKEN"
    color="#1a73e8"
  /&gt;;
}</code></pre>

                        <h4>{{ t('embedded_widget_page.quickstart.vue') }}</h4>
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
        <el-dialog v-model="showResultDialog" :title="t('embedded_widget_page.dialog.title')" width="500px">
            <div class="token-result">
                <el-alert :title="t('embedded_widget_page.dialog.warning')" type="warning" :closable="false" show-icon />
                <div class="token-value">
                    <code>{{ generatedToken }}</code>
                </div>
                <el-button type="primary" @click="copyToken" class="mt-2">
                    <el-icon><CopyDocument /></el-icon> {{ t('embedded_widget_page.dialog.copy_token') }}
                </el-button>
            </div>
            <template #footer>
                <el-button @click="showResultDialog = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Plus, CopyDocument } from '@element-plus/icons-vue';
import embeddedWidgetApi from '@/api/embeddedWidget';

const { t } = useI18n();
const ns = 'embedded_widget_page';

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

const expiresOptions = computed(() => [
    { value: 3600, label: t(`${ns}.expires.h1`) },
    { value: 21600, label: t(`${ns}.expires.h6`) },
    { value: 86400, label: t(`${ns}.expires.h24`) },
    { value: 604800, label: t(`${ns}.expires.d7`) },
]);

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
        ElMessage.warning(t(`${ns}.messages.select_customer`));
        return;
    }
    generating.value = true;
    try {
        const { data } = await embeddedWidgetApi.generateToken({
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
        ElMessage.success(t(`${ns}.messages.token_generated`));
    } catch (e) {
        ElMessage.error(t(`${ns}.messages.generate_failed`, { error: e.response?.data?.message || e.message }));
    } finally {
        generating.value = false;
    }
}

function copyCode() {
    navigator.clipboard.writeText(generatedCode.value);
    ElMessage.success(t(`${ns}.messages.code_copied`));
}

function copyToken() {
    navigator.clipboard.writeText(generatedToken.value);
    ElMessage.success(t(`${ns}.messages.token_copied`));
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
