<template>
  <div class="ai-feature-page">
    <el-page-header :content="t('test_generator_page.title')" @back="$router.push('/ai')" />
    <p class="text-muted" style="margin:8px 0 20px">{{ t('test_generator_page.desc') }}</p>

    <el-card shadow="hover" style="margin-bottom:20px">
      <el-form :inline="true">
        <el-form-item :label="t('test_generator_page.language')">
          <el-select v-model="language" style="width:140px" @change="onLanguageChange">
            <el-option v-for="f in frameworks" :key="f.language" :label="f.language" :value="f.language" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('test_generator_page.framework')">
          <el-select v-model="framework" style="width:140px">
            <el-option v-for="fw in currentFrameworks" :key="fw" :label="fw" :value="fw" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :loading="loading" @click="generateAll">
            <el-icon><MagicStick /></el-icon> {{ t('test_generator_page.generate') }}
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card v-if="result" shadow="hover">
      <template #header>
        {{ t('test_generator_page.result_header', { n: result.total }) }}
        <el-tag size="small" style="margin-left:8px">{{ result.language }}/{{ result.framework }}</el-tag>
      </template>
      <el-tabs v-if="result.test_files?.length">
        <el-tab-pane v-for="(file, i) in result.test_files" :key="i" :label="file.test_file_name || t('test_generator_page.test_n', { n: i + 1 })">
          <pre class="code-pre">{{ file.test_code }}</pre>
          <div class="mt-2">
            <el-tag v-if="file.llm_generated" type="success" size="small">{{ t('test_generator_page.ai_generated') }}</el-tag>
            <el-tag v-else type="info" size="small">{{ t('test_generator_page.template_generated') }}</el-tag>
            <span v-if="file.coverage_notes" class="text-muted" style="margin-left:8px">{{ file.coverage_notes }}</span>
          </div>
          <el-button size="small" style="margin-top:8px" @click="copyText(file.test_code)">{{ t('test_generator_page.copy') }}</el-button>
        </el-tab-pane>
      </el-tabs>
      <el-empty v-else :description="t('test_generator_page.no_files')" />
    </el-card>

    <el-empty v-if="!loading && !result" :description="t('test_generator_page.empty')" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { MagicStick } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { generateAllTests, getTestFrameworks } from '@/api/aiIntelligence'

const { t } = useI18n()
const loading = ref(false)
const frameworks = ref([])
const result = ref(null)
const language = ref('php')
const framework = ref('pest')

const currentFrameworks = computed(() => {
  const f = frameworks.value.find(item => item.language === language.value)
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
    ElMessage.success(t('test_generator_page.messages.generated', { n: res.data?.total || 0 }))
  } catch (_) { /* ignore */ }
  finally { loading.value = false }
}

function copyText(text) {
  navigator.clipboard.writeText(text).then(() => ElMessage.success(t('test_generator_page.messages.copied')))
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
