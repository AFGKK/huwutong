<template>
    <div class="sandbox-page">
        <!-- 页头 -->
        <div class="page-header">
            <div>
                <h2>💻 代码沙箱</h2>
                <p class="text-gray-500 text-sm">安全的在线代码执行环境，支持 PHP / Python / Node.js / SQL</p>
            </div>
            <div class="flex gap-2">
                <el-button @click="loadLanguages" :loading="loadingLangs">
                    <el-icon><Refresh /></el-icon> 检测环境
                </el-button>
                <el-button @click="clearOutput">
                    <el-icon><Delete /></el-icon> 清空输出
                </el-button>
            </div>
        </div>

        <el-row :gutter="16">
            <!-- 编辑区 -->
            <el-col :span="14">
                <el-card shadow="never">
                    <template #header>
                        <div class="sandbox-header">
                            <div class="flex gap-2 items-center">
                                <el-select v-model="language" style="width:130px" @change="onLanguageChange">
                                    <el-option label="PHP" value="php" />
                                    <el-option label="Python" value="python" />
                                    <el-option label="Node.js" value="node" />
                                    <el-option label="SQL" value="sql" />
                                    <el-option label="Bash" value="bash" :disabled="true" />
                                </el-select>
                                <span class="lang-version" v-if="langVersions[language]">v{{ langVersions[language] }}</span>
                            </div>
                            <div class="flex gap-2">
                                <el-button text size="small" @click="loadTemplate">📋 示例代码</el-button>
                                <el-button type="primary" :loading="running" @click="runCode">
                                    <el-icon><CaretRight /></el-icon> 运行
                                </el-button>
                            </div>
                        </div>
                    </template>
                    <div class="editor-wrap">
                        <textarea ref="editorRef" v-model="code" class="code-editor" spellcheck="false"
                            :placeholder="`在此输入 ${languageLabel} 代码...`" @keydown="handleKeydown"></textarea>
                    </div>
                    <div class="editor-info">
                        <span>{{ code.length }} / {{ maxLength }} 字符</span>
                        <span v-if="code.length > maxLength" class="text-danger">已超出限制！</span>
                    </div>
                </el-card>
            </el-col>

            <!-- 输出区 -->
            <el-col :span="10">
                <el-card shadow="never">
                    <template #header>
                        <div class="sandbox-header">
                            <span>📤 输出结果</span>
                            <el-tag v-if="lastResult" :type="lastResult.success ? 'success' : 'danger'" size="small">
                                {{ lastResult.success ? '执行成功' : '执行失败' }}
                            </el-tag>
                        </div>
                    </template>
                    <div class="output-wrap">
                        <div v-if="!lastResult" class="output-placeholder">
                            <el-icon :size="40" color="#dcdfe6"><Upload /></el-icon>
                            <p>点击「运行」执行代码</p>
                        </div>
                        <div v-else class="output-content">
                            <!-- 输出 -->
                            <div v-if="lastResult.output" class="output-section">
                                <div class="output-label">标准输出</div>
                                <pre class="output-text">{{ lastResult.output }}</pre>
                            </div>
                            <!-- 错误 -->
                            <div v-if="lastResult.error" class="output-section">
                                <div class="output-label output-error-label">错误信息</div>
                                <pre class="output-text output-error">{{ lastResult.error }}</pre>
                            </div>
                            <!-- 执行信息 -->
                            <div class="output-meta">
                                <span>执行时间：{{ lastResult.execution_time }}ms</span>
                                <span v-if="lastResult.exit_code !== undefined">退出码：{{ lastResult.exit_code }}</span>
                                <span v-if="lastResult.rows !== undefined">行数：{{ lastResult.rows }}</span>
                                <span>{{ lastResult.code_length }} 字符</span>
                            </div>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import apiClient from '@/utils/request'

const code = ref('')
const language = ref('php')
const running = ref(false)
const lastResult = ref(null)
const loadingLangs = ref(false)
const langVersions = ref({})

const maxLength = 5000

const languageLabel = computed(() => {
    const map = { php: 'PHP', python: 'Python', node: 'Node.js', sql: 'SQL', bash: 'Bash' }
    return map[language.value] || language.value
})

function handleKeydown(e) {
    // Ctrl+Enter 运行
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault()
        runCode()
    }
    // Tab 缩进
    if (e.key === 'Tab') {
        e.preventDefault()
        const start = e.target.selectionStart
        const end = e.target.selectionEnd
        code.value = code.value.substring(0, start) + '    ' + code.value.substring(end)
        e.target.selectionStart = e.target.selectionEnd = start + 4
    }
}

async function loadLanguages() {
    loadingLangs.value = true
    try {
        const res = await apiClient.get('/code-sandbox/languages')
        const langs = res.data?.data?.languages || {}
        langVersions.value = {}
        Object.entries(langs).forEach(([k, v]) => {
            if (v.version) {
                // 提取版本号
                const m = v.version.match(/(\d+\.\d+[\.\d]*)/)
                langVersions.value[k] = m ? m[1] : v.version
            }
        })
        ElMessage.success('环境检测完成')
    } catch {
        ElMessage.error('环境检测失败')
    } finally {
        loadingLangs.value = false
    }
}

async function loadTemplate() {
    try {
        const res = await apiClient.get('/code-sandbox/templates')
        const templates = res.data?.data?.templates || {}
        const tmpl = templates[language.value]
        if (tmpl) {
            code.value = tmpl.code
        }
    } catch {
        ElMessage.error('加载模板失败')
    }
}

async function runCode() {
    if (!code.value.trim()) { ElMessage.warning('请输入代码'); return }
    if (code.value.length > maxLength) { ElMessage.warning('代码超过长度限制'); return }
    running.value = true
    lastResult.value = null
    try {
        const res = await apiClient.post('/code-sandbox/execute', {
            code: code.value,
            language: language.value,
        })
        lastResult.value = res.data?.data
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '执行失败')
    } finally {
        running.value = false
    }
}

function clearOutput() {
    lastResult.value = null
}

function onLanguageChange() {
    lastResult.value = null
}

onMounted(() => {
    loadLanguages()
    loadTemplate()
})
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.text-gray-500 { color: #909399; }
.text-sm { font-size: 13px; }
.text-danger { color: #f56c6c; }
.flex { display: flex; }
.gap-2 { gap: 8px; }
.items-center { align-items: center; }

.sandbox-header { display: flex; justify-content: space-between; align-items: center; }
.lang-version { font-size: 12px; color: #909399; font-family: monospace; }

.editor-wrap { position: relative; }
.code-editor {
    width: 100%;
    height: 480px;
    font-family: 'Consolas', 'Courier New', monospace;
    font-size: 14px;
    line-height: 1.6;
    padding: 16px;
    background: #1e1e1e;
    color: #d4d4d4;
    border: 1px solid #333;
    border-radius: 6px;
    resize: vertical;
    tab-size: 4;
    outline: none;
    white-space: pre;
    overflow: auto;
}
.code-editor::placeholder { color: #666; }
.code-editor:focus { border-color: #409eff; }

.editor-info { display: flex; gap: 12px; font-size: 12px; color: #909399; margin-top: 6px; justify-content: flex-end; }

.output-wrap { min-height: 480px; max-height: 520px; overflow-y: auto; }
.output-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 400px; color: #909399; gap: 12px; }
.output-placeholder p { font-size: 14px; margin: 0; }

.output-content { padding: 0; }
.output-section { margin-bottom: 12px; }
.output-label { font-size: 12px; font-weight: 600; color: #67c23a; margin-bottom: 4px; }
.output-error-label { color: #f56c6c; }
.output-text {
    background: #f5f7fa;
    border-radius: 4px;
    padding: 12px;
    font-family: 'Consolas', 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-all;
    max-height: 300px;
    overflow: auto;
    margin: 0;
}
.output-error { background: #fef0f0; color: #f56c6c; }
.output-meta { display: flex; gap: 16px; font-size: 11px; color: #909399; padding-top: 8px; border-top: 1px solid #eee; flex-wrap: wrap; }
</style>
