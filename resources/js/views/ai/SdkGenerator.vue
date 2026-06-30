<template>
  <div class="ai-feature-page">
    <el-page-header :content="'AI SDK 配置生成器'" @back="$router.push('/ai')" />
    <p class="text-muted" style="margin:8px 0 20px">根据语言/框架自动生成开箱即用的集成代码和依赖配置</p>

    <el-card shadow="hover" style="margin-bottom:20px">
      <el-form :inline="true" :model="form">
        <el-form-item label="编程语言">
          <el-select v-model="form.language" style="width:160px" @change="onLanguageChange">
            <el-option v-for="opt in options" :key="opt.language" :label="opt.language" :value="opt.language" />
          </el-select>
        </el-form-item>
        <el-form-item label="框架">
          <el-select v-model="form.framework" style="width:160px">
            <el-option v-for="fw in currentFrameworks" :key="fw" :label="fw" :value="fw" />
          </el-select>
        </el-form-item>
        <el-form-item label="License Key">
          <el-input v-model="form.license_key" placeholder="可选" style="width:240px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :loading="generating" @click="generate">
            <el-icon><MagicStick /></el-icon> 生成配置
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card v-if="result" shadow="hover">
      <template #header>
        <span>生成结果</span>
        <el-tag size="small" style="margin-left:8px">{{ form.language }}/{{ form.framework }}</el-tag>
      </template>

      <el-tabs>
        <el-tab-pane label="安装命令" name="install">
          <pre class="code-pre" v-if="result.install_command">$ {{ result.install_command }}</pre>
          <el-empty v-else description="暂无" />
        </el-tab-pane>
        <el-tab-pane label="初始化代码" name="init">
          <pre class="code-pre">{{ result.init_code || result.setup_guide || '暂无' }}</pre>
          <el-button size="small" style="margin-top:8px" @click="copyText(result.init_code || result.setup_guide || '')">复制</el-button>
        </el-tab-pane>
        <el-tab-pane label="激活代码" name="activate">
          <pre class="code-pre">{{ result.activate_code || '暂无' }}</pre>
        </el-tab-pane>
        <el-tab-pane label="验证代码" name="validate">
          <pre class="code-pre">{{ result.validate_code || '暂无' }}</pre>
        </el-tab-pane>
        <el-tab-pane v-if="result.full_example" label="完整示例" name="full">
          <pre class="code-pre">{{ result.full_example }}</pre>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <el-empty v-if="!generating && !result" description="选择语言/框架后点击「生成配置」" />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { MagicStick } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { generateSdkConfig, getSdkOptions } from '@/api/aiIntelligence'

const generating = ref(false)
const options = ref([])
const result = ref(null)
const form = ref({ language: 'php', framework: 'laravel', license_key: '' })

const currentFrameworks = computed(() => {
  const opt = options.value.find(o => o.language === form.value.language)
  return opt?.frameworks || []
})

async function loadOptions() {
  try {
    const res = await getSdkOptions()
    options.value = res.data || []
  } catch (_) { /* ignore */ }
}

function onLanguageChange() {
  const fws = currentFrameworks.value
  if (fws.length && !fws.includes(form.value.framework)) {
    form.value.framework = fws[0]
  }
}

async function generate() {
  generating.value = true
  try {
    const res = await generateSdkConfig(form.value)
    result.value = res.data
    ElMessage.success('配置已生成')
  } catch (_) { /* ignore */ }
  finally { generating.value = false }
}

function copyText(text) {
  navigator.clipboard.writeText(text).then(() => ElMessage.success('已复制'))
}

loadOptions()
</script>
<style scoped>
.ai-feature-page { padding: 20px; }
.text-muted { color: #909399; font-size: 14px; }
.code-pre { background: #f5f7fa; padding: 12px; border-radius: 4px; overflow-x: auto; font-size: 13px; line-height: 1.5; max-height: 400px; overflow-y: auto; white-space: pre-wrap; }
</style>
