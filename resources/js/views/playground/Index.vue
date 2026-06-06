<template>
    <div class="playground-page">
        <div class="page-header">
            <h2>API 交互式 Playground</h2>
            <p class="header-subtitle">选择一个 API 端点，填写参数，实时调用并查看响应。自动生成多种语言的代码片段。</p>
        </div>

        <el-row :gutter="20">
            <!-- 左侧：端点选择 -->
            <el-col :span="6">
                <el-card shadow="never" class="sidebar-card">
                    <template #header>
                        <div class="sidebar-header">
                            <span>API 端点</span>
                            <el-input
                                v-model="endpointSearch"
                                placeholder="搜索..."
                                size="small"
                                clearable
                                class="search-input"
                            />
                        </div>
                    </template>

                    <div v-loading="endpointsLoading" class="endpoint-list">
                        <div v-for="(eps, group) in groupedEndpoints" :key="group" class="endpoint-group">
                            <div class="group-label">{{ group }}</div>
                            <div
                                v-for="ep in eps"
                                :key="ep.id"
                                class="endpoint-item"
                                :class="{ active: selectedEndpoint?.id === ep.id }"
                                @click="selectEndpoint(ep)"
                            >
                                <span class="ep-method" :class="methodClass(ep.method)">{{ ep.method }}</span>
                                <span class="ep-title">{{ ep.title }}</span>
                            </div>
                        </div>
                        <el-empty v-if="!endpointsLoading && Object.keys(groupedEndpoints).length === 0" description="无匹配端点" />
                    </div>
                </el-card>
            </el-col>

            <!-- 右侧：主区域 -->
            <el-col :span="18">
                <div v-if="!selectedEndpoint" class="no-endpoint">
                    <el-empty description="从左侧选择一个 API 端点开始" />
                </div>

                <template v-else>
                    <!-- 端点信息 -->
                    <el-card shadow="never" class="endpoint-info">
                        <div class="info-header">
                            <el-tag :type="methodTag(selectedEndpoint.method)" effect="dark" class="method-tag">
                                {{ selectedEndpoint.method }}
                            </el-tag>
                            <code class="path-display">{{ selectedEndpoint.path }}</code>
                            <span class="info-title">{{ selectedEndpoint.title }}</span>
                        </div>
                        <p class="info-desc">{{ selectedEndpoint.description }}</p>
                    </el-card>

                    <!-- 认证信息 -->
                    <el-alert
                        v-if="selectedEndpoint.auth"
                        title="此端点需要认证，请确保已登录"
                        type="warning"
                        show-icon
                        :closable="false"
                        class="auth-alert"
                    />

                    <!-- 参数输入 -->
                    <el-card shadow="never" class="params-card">
                        <template #header>
                            <span>请求参数</span>
                        </template>

                        <!-- Query 参数 -->
                        <div v-if="selectedEndpoint.query_params?.length" class="param-section">
                            <h4 class="param-section-title">Query 参数</h4>
                            <el-form label-width="140px" size="small">
                                <el-form-item
                                    v-for="param in selectedEndpoint.query_params"
                                    :key="param.name"
                                    :label="param.name"
                                    :required="param.required"
                                >
                                    <el-input
                                        v-model="queryParams[param.name]"
                                        :placeholder="param.description"
                                        clearable
                                    />
                                    <div class="param-desc">{{ param.description }}</div>
                                </el-form-item>
                            </el-form>
                        </div>

                        <!-- Body 参数 -->
                        <div v-if="selectedEndpoint.request_body?.length" class="param-section">
                            <h4 class="param-section-title">请求体 <span class="section-subtitle">JSON</span></h4>
                            <div class="body-editor">
                                <el-input
                                    v-model="requestBodyRaw"
                                    type="textarea"
                                    :rows="8"
                                    placeholder="{ &quot;key&quot;: &quot;value&quot; }"
                                    class="json-input"
                                    style="font-family: 'SF Mono', 'Fira Code', monospace; font-size: 13px;"
                                />
                                <el-button
                                    text
                                    size="small"
                                    type="primary"
                                    @click="formatJson"
                                    class="format-btn"
                                >
                                    格式化 JSON
                                </el-button>
                            </div>
                        </div>

                        <div v-if="!selectedEndpoint.query_params?.length && !selectedEndpoint.request_body?.length" class="no-params">
                            <el-empty description="此端点无需参数" :image-size="60" />
                        </div>
                    </el-card>

                    <!-- 操作按钮 -->
                    <div class="action-bar">
                        <el-button
                            type="primary"
                            size="large"
                            :loading="executing"
                            :disabled="!selectedEndpoint"
                            @click="executeRequest"
                        >
                            <el-icon><CaretRight /></el-icon>
                            {{ executing ? '请求中...' : '发送请求' }}
                        </el-button>
                        <el-button
                            size="large"
                            @click="showCodePanel = !showCodePanel"
                            :type="showCodePanel ? 'warning' : 'default'"
                        >
                            <el-icon><Document /></el-icon>
                            查看代码
                        </el-button>
                        <el-button size="large" @click="resetParams">重置</el-button>
                    </div>

                    <!-- 代码面板 -->
                    <el-collapse-transition>
                        <div v-if="showCodePanel" class="code-panel">
                            <el-card shadow="never">
                                <template #header>
                                    <div class="code-header">
                                        <span>代码生成</span>
                                        <el-radio-group v-model="codeLanguage" size="small">
                                            <el-radio-button value="curl">cURL</el-radio-button>
                                            <el-radio-button value="php">PHP</el-radio-button>
                                            <el-radio-button value="node">Node.js</el-radio-button>
                                            <el-radio-button value="python">Python</el-radio-button>
                                            <el-radio-button value="java">Java</el-radio-button>
                                            <el-radio-button value="go">Go</el-radio-button>
                                        </el-radio-group>
                                    </div>
                                </template>

                                <div v-loading="codeGenerating" class="code-output">
                                    <div class="code-toolbar">
                                        <el-button text size="small" @click="copyCode">
                                            <el-icon><CopyDocument /></el-icon> 复制
                                        </el-button>
                                    </div>
                                    <pre><code>{{ generatedCode }}</code></pre>
                                </div>
                            </el-card>
                        </div>
                    </el-collapse-transition>

                    <!-- 响应展示 -->
                    <el-card v-if="responseData" shadow="never" class="response-card">
                        <template #header>
                            <div class="response-header">
                                <span>响应</span>
                                <div class="response-meta">
                                    <el-tag :type="responseSuccess ? 'success' : 'danger'" size="small">
                                        HTTP {{ responseData.status }}
                                    </el-tag>
                                    <el-tag v-if="responseData.duration_ms" size="small" type="info">
                                        {{ (responseData.duration_ms / 1000).toFixed(2) }}ms
                                    </el-tag>
                                    <el-button text size="small" @click="copyResponse">
                                        <el-icon><CopyDocument /></el-icon> 复制
                                    </el-button>
                                </div>
                            </div>
                        </template>

                        <div class="response-body-wrapper">
                            <pre class="response-body" :class="{ 'response-error': !responseSuccess }"><code>{{ responseBodyFormatted }}</code></pre>
                        </div>
                    </el-card>
                </template>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { ElMessage } from 'element-plus';
import { CaretRight, CopyDocument } from '@element-plus/icons-vue';
import playgroundApi from '@/api/playground';

const endpointsLoading = ref(false);
const endpoints = ref([]);
const endpointSearch = ref('');
const selectedEndpoint = ref(null);
const executing = ref(false);
const showCodePanel = ref(false);
const codeGenerating = ref(false);
const codeLanguage = ref('curl');
const generatedCode = ref('');
const responseData = ref(null);
const queryParams = reactive({});
const requestBodyRaw = ref('');
const lastExecutedParams = ref({});

const groupedEndpoints = computed(() => {
    const list = endpoints.value;
    const search = endpointSearch.value.toLowerCase();
    const filtered = search
        ? list.filter(ep =>
            ep.title.toLowerCase().includes(search) ||
            ep.path.toLowerCase().includes(search) ||
            ep.group.toLowerCase().includes(search)
          )
        : list;

    const groups = {};
    for (const ep of filtered) {
        if (!groups[ep.group]) groups[ep.group] = [];
        groups[ep.group].push(ep);
    }
    return groups;
});

const responseSuccess = computed(() => {
    return responseData.value?.status >= 200 && responseData.value?.status < 300;
});

const responseBodyFormatted = computed(() => {
    if (!responseData.value) return '';
    try {
        return JSON.stringify(responseData.value.body, null, 2);
    } catch {
        return String(responseData.value.body);
    }
});

function methodClass(method) {
    return method.toLowerCase();
}

function methodTag(method) {
    const map = { GET: 'success', POST: 'primary', PUT: 'warning', DELETE: 'danger' };
    return map[method] || 'info';
}

function selectEndpoint(ep) {
    selectedEndpoint.value = ep;
    resetParams();
    responseData.value = null;
    showCodePanel.value = false;
}

function resetParams() {
    // 清空 query params
    for (const key of Object.keys(queryParams)) {
        delete queryParams[key];
    }
    // 初始化 query params 默认值
    if (selectedEndpoint.value?.query_params) {
        for (const param of selectedEndpoint.value.query_params) {
            queryParams[param.name] = '';
        }
    }
    requestBodyRaw.value = '';
    if (selectedEndpoint.value?.request_body) {
        const defaultBody = {};
        for (const param of selectedEndpoint.value.request_body) {
            if (param.type === 'object') {
                defaultBody[param.name] = { hostname: 'my-server', os: 'linux' };
            } else if (param.required) {
                defaultBody[param.name] = param.type === 'integer' ? 1 : '';
            }
        }
        if (Object.keys(defaultBody).length > 0) {
            requestBodyRaw.value = JSON.stringify(defaultBody, null, 2);
        }
    }
}

function formatJson() {
    try {
        const obj = JSON.parse(requestBodyRaw.value);
        requestBodyRaw.value = JSON.stringify(obj, null, 2);
    } catch {
        ElMessage.warning('JSON 格式无效');
    }
}

function parseBody() {
    if (!requestBodyRaw.value.trim()) return undefined;
    try {
        return JSON.parse(requestBodyRaw.value);
    } catch {
        ElMessage.warning('请求体 JSON 格式无效，已作为字符串发送');
        return requestBodyRaw.value;
    }
}

function getQueryObject() {
    const q = {};
    for (const [key, val] of Object.entries(queryParams)) {
        if (val !== '' && val !== null && val !== undefined) {
            q[key] = val;
        }
    }
    return Object.keys(q).length > 0 ? q : undefined;
}

async function executeRequest() {
    if (!selectedEndpoint.value) return;

    executing.value = true;
    responseData.value = null;

    const body = parseBody();
    const query = getQueryObject();

    lastExecutedParams.value = {
        method: selectedEndpoint.value.method,
        path: selectedEndpoint.value.path,
        body,
        query,
    };

    try {
        const { data: res } = await playgroundApi.execute({
            method: selectedEndpoint.value.method,
            path: selectedEndpoint.value.path,
            body,
            query,
        });

        if (res.success) {
            responseData.value = res.data;
        }
    } catch {
        responseData.value = {
            status: 0,
            body: { error: '请求失败，请检查网络连接' },
            duration_ms: 0,
        };
    } finally {
        executing.value = false;
    }
}

async function generateCodeSnippet() {
    if (!selectedEndpoint.value) return;

    codeGenerating.value = true;
    try {
        const { data: res } = await playgroundApi.generateCode({
            language: codeLanguage.value,
            method: selectedEndpoint.value.method,
            path: selectedEndpoint.value.path,
            body: lastExecutedParams.value.body,
            query: lastExecutedParams.value.query,
        });

        if (res.success) {
            generatedCode.value = res.data.code;
        }
    } catch {
        generatedCode.value = '// 代码生成失败';
    } finally {
        codeGenerating.value = false;
    }
}

function copyCode() {
    if (!generatedCode.value) return;
    navigator.clipboard.writeText(generatedCode.value).then(() => {
        ElMessage.success('代码已复制');
    }).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = generatedCode.value;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        ElMessage.success('代码已复制');
    });
}

function copyResponse() {
    if (!responseBodyFormatted.value) return;
    navigator.clipboard.writeText(responseBodyFormatted.value).then(() => {
        ElMessage.success('响应已复制');
    });
}

watch(codeLanguage, () => {
    generateCodeSnippet();
});

watch(showCodePanel, (val) => {
    if (val) {
        generateCodeSnippet();
    }
});

async function loadEndpoints() {
    endpointsLoading.value = true;
    try {
        const { data: res } = await playgroundApi.endpoints();
        if (res.success) {
            endpoints.value = res.data || [];
        }
    } catch {
        endpoints.value = [];
    } finally {
        endpointsLoading.value = false;
    }
}

onMounted(() => {
    loadEndpoints();
});
</script>

<style scoped>
.playground-page { padding: 20px; height: calc(100vh - 100px); }

.page-header { margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    color: var(--el-text-color-secondary);
    font-size: 13px;
    margin-top: 6px;
}

/* 侧边栏 */
.sidebar-card { height: calc(100vh - 200px); overflow: hidden; }
.sidebar-card :deep(.el-card__body) { padding: 8px; height: calc(100% - 60px); overflow-y: auto; }
.sidebar-header {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.search-input { width: 100%; }

.endpoint-list { }
.endpoint-group { margin-bottom: 8px; }
.group-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--el-text-color-secondary);
    text-transform: uppercase;
    padding: 4px 8px;
    letter-spacing: 0.5px;
}
.endpoint-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 8px;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.15s;
}
.endpoint-item:hover {
    background: var(--el-fill-color-light);
}
.endpoint-item.active {
    background: var(--el-color-primary-light-9);
}
.ep-method {
    font-size: 10px;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 3px;
    color: white;
    min-width: 40px;
    text-align: center;
    flex-shrink: 0;
}
.ep-method.get { background: #67C23A; }
.ep-method.post { background: #409EFF; }
.ep-method.put { background: #E6A23C; }
.ep-method.delete { background: #F56C6C; }
.ep-title {
    font-size: 13px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* 主区域 */
.no-endpoint {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 400px;
}

/* 端点信息 */
.endpoint-info { margin-bottom: 12px; }
.info-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}
.method-tag { font-weight: 700; }
.path-display {
    font-size: 15px;
    font-weight: 600;
    color: var(--el-text-color-primary);
}
.info-title { font-size: 14px; color: var(--el-text-color-secondary); }
.info-desc { margin: 0; color: var(--el-text-color-secondary); font-size: 13px; }

.auth-alert { margin-bottom: 12px; }

/* 参数 */
.params-card { margin-bottom: 12px; }
.param-section { margin-bottom: 16px; }
.param-section:last-child { margin-bottom: 0; }
.param-section-title {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--el-border-color-light);
}
.section-subtitle { font-weight: 400; font-size: 12px; color: var(--el-text-color-secondary); }
.param-desc { font-size: 11px; color: var(--el-text-color-secondary); margin-top: 2px; }

.body-editor { position: relative; }
.json-input { width: 100%; }
.format-btn { margin-top: 4px; }

.no-params { padding: 20px 0; }

/* 操作按钮 */
.action-bar {
    display: flex;
    gap: 12px;
    margin-bottom: 12px;
}

/* 代码面板 */
.code-panel { margin-bottom: 12px; }
.code-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.code-output {
    position: relative;
}
.code-toolbar {
    position: absolute;
    top: 8px;
    right: 8px;
    z-index: 10;
}
.code-output pre {
    margin: 0;
    padding: 16px;
    background: #1e1e1e;
    color: #d4d4d4;
    overflow-x: auto;
    font-size: 13px;
    line-height: 1.5;
    max-height: 500px;
    border-radius: 4px;
}
.code-output code { font-family: 'SF Mono', 'Fira Code', 'Cascadia Code', monospace; }

/* 响应 */
.response-card { }
.response-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.response-meta {
    display: flex;
    align-items: center;
    gap: 8px;
}
.response-body-wrapper { }
.response-body {
    margin: 0;
    padding: 16px;
    background: #1e1e1e;
    color: #d4d4d4;
    overflow-x: auto;
    font-size: 13px;
    line-height: 1.5;
    max-height: 500px;
    border-radius: 4px;
    font-family: 'SF Mono', 'Fira Code', 'Cascadia Code', monospace;
}
.response-error { color: #f48771; }

:deep(.el-card__body) { padding: 16px; }
</style>
