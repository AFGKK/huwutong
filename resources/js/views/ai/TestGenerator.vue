<template>
  <div class="ai-feature-page">
    <el-page-header :content="'AI 测试用例生成器'" @back="$router.push('/ai')" />
    <p class="text-muted" style="margin:8px 0 20px">基于 OpenAPI Schema 自动生成单元测试和集成测试用例</p>

    <el-card shadow="hover" style="margin-bottom:20px">
      <el-form :inline="true">
        <el-form-item label="语言">
          <el-select v-model="language" style="width:140px">
            <el-option v-for="f in frameworks" :key="f.language" :label="f.language" :value="f.language" />
          </el-select>
        </el-form-item>
        <el-form-item label="框架">
          <el-select v-model="framework" style="width:140px">
            <el-option v-for="fw in currentFrameworks" :key="fw" :label="fw" :value="fw" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :loading="loading" @click="generateAll">
            <el-icon><MagicStick /></el-icon> 生成所有测试
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card v-if="result" shadow="hover">
      <template #header>
        测试文件（共 {{ result.total }} 个）
        <el-tag size="small" style="margin-left:8px">{{ result.language }}/{{ result.framework }}</el-tag>
      </template>
      <el-tabs v-if="result.test_files?.length">
        <el-tab-pane v-for="(file, i) in result.test_files" :key="i" :label="file.test_file_name || `测试 ${i+1}`">
          <pre class="code-pre">{{ file.test_code }}</pre>
          <div class="mt-2">
            <el-tag v-if="file.llm_generated" type="success" size="small">AI 生成</el-tag>
            <el-tag v-else type="info" size="small">模板生成</el-tag>
            <span v-if="file.coverage_notes" class="text-muted" style="margin-left:8px">{{ file.coverage_notes }}</span>
          </div>
          <el-button size="small" style="margin-top:8px" @click="copyText(file.test_code)">复制代码</el-button>
        </el-tab-pane>
      </el-tabs>
      <el-empty v-else description="暂无测试文件" />
    </el-card>

    <el-empty v-if="!loading && !result" description="选择语言/框架后点击「生成所有测试」" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { MagicStick } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { generateAllTests, getTestFrameworks } from '@/api/aiIntelligence'

const loading = ref(false)
const frameworks = ref([])
const result = ref(null)
const language = ref('php')
const framework = ref('pest')

const currentFrameworks = computed(() => {
  const f = frameworks.value.find(f => f.language === language.value)
  return f?.frameworks || []
})

function onLanguageChange() {
  const fws = currentFrameworks.value
  if (fws.length && !fws.includes(framework.value)) {
    framework.value = fws[0]
  }
}

async function generateAll() {
  loading.value = true
  try {
    const res = await generateAllTests({ language: language.value, framework: framework.value })
    result.value = res.data
    ElMessage.success(`已生成 ${res.data?.total || 0} 个测试文件`)
  } catch (_) { /* ignore */ }
  finally { loading.value = false }
}

function copyText(text) {
  navigator.clipboard.writeText(text).then(() => ElMessage.success('已复制'))
}

onMounted(async () => {
  try {
    const res = await getTestFrameworks()
    frameworks.value = res.data || []
  } catch (_) { /* ignore */ }
})
</script>
<style scoped>
.ai-feature-page { padding: 20px; }
.text-muted { color: #909399; font-size: 14px; }
.code-pre { background: #f5f7fa; padding: 12px; border-radius: 4px; overflow-x: auto; font-size: 13px; line-height: 1.5; max-height: 500px; overflow-y: auto; white-space: pre-wrap; }
.mt-2 { margin-top: 8px; }
</style>
