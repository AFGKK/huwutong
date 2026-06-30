<template>
  <div class="personalized-section">
    <!-- 个性化推荐 -->
    <el-card v-if="recommendations.length" class="mb-4" shadow="hover">
      <template #header>
        <div class="card-header">
          <span>推荐</span>
          <el-button text size="small" @click="homepageData.recommendations = []">关闭</el-button>
        </div>
      </template>
      <el-row :gutter="16">
        <el-col :span="8" v-for="(rec, i) in recommendations" :key="i">
          <el-card shadow="never" class="rec-card" @click="handleClick(rec)">
            <div class="rec-type">
              <el-tag :type="recTypeTag(rec.recommendation_type)" size="small" effect="plain">
                {{ recTypeLabel(rec.recommendation_type) }}
              </el-tag>
            </div>
            <div class="rec-reason">{{ rec.reason }}</div>
            <div class="rec-source">{{ sourceLabel(rec.source) }} · 评分 {{ rec.score }}</div>
          </el-card>
        </el-col>
      </el-row>
    </el-card>

    <!-- 快捷操作 -->
    <el-card class="mb-4" shadow="hover">
      <template #header>
        <span>快捷操作</span>
      </template>
      <el-row :gutter="16">
        <el-col :span="6" v-for="action in quickActions" :key="action.key">
          <el-button text class="quick-action-btn" @click="$router.push(action.route)">
            <el-icon :size="20"><component :is="action.icon" /></el-icon>
            <span>{{ action.label }}</span>
          </el-button>
        </el-col>
      </el-row>
    </el-card>

    <!-- 热门功能 -->
    <el-card v-if="popularFeatures.length" shadow="hover">
      <template #header>
        <span>热门功能</span>
      </template>
      <el-row :gutter="12">
        <el-col :span="6" v-for="(feat, i) in popularFeatures" :key="i">
          <div class="feature-item">
            <div class="feature-action">{{ feat.event_action }}</div>
            <div class="feature-count">{{ feat.cnt }} 次使用</div>
          </div>
        </el-col>
      </el-row>
    </el-card>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getPersonalizedHomepage, clickRecommendation, dismissRecommendation } from '../../api/personalization'

const emit = defineEmits(['loaded'])
const homepageData = ref({ recommendations: [], quick_actions: [], popular_features: [], stats: {} })

const recommendations = computed(() => homepageData.value.recommendations || [])
const quickActions = computed(() => homepageData.value.quick_actions || [])
const popularFeatures = computed(() => homepageData.value.popular_features || [])

function recTypeTag(t) {
  const map = { license: 'success', feature: 'primary', addon: 'warning', article: 'info', product: 'danger' }
  return map[t] || ''
}
function recTypeLabel(t) {
  const map = { license: '套餐', feature: '功能', addon: '增值', article: '文章', product: '产品' }
  return map[t] || t
}
function sourceLabel(s) {
  const map = { rule: '规则', rfm: 'RFM', behavior: '行为', llm: 'AI' }
  return map[s] || s
}

function handleClick(rec) {
  clickRecommendation(rec.id).catch(() => {})
}

async function loadPersonalization() {
  try {
    const { data } = await getPersonalizedHomepage()
    homepageData.value = data || {}
    emit('loaded', data)
  } catch (e) {
    // 静默失败，个性化功能不影响主页面
  }
}

onMounted(loadPersonalization)
</script>

<style scoped>
.personalized-section { min-height: 100px; }
.mb-4 { margin-bottom: 16px; }
.rec-card { cursor: pointer; transition: all 0.2s; }
.rec-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.rec-type { margin-bottom: 8px; }
.rec-reason { font-size: 14px; color: #303133; margin-bottom: 6px; line-height: 1.4; }
.rec-source { font-size: 12px; color: #909399; }
.quick-action-btn {
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  width: 100%; height: 80px; border: 1px solid #ebeef5; border-radius: 8px;
  transition: all 0.2s;
}
.quick-action-btn:hover { border-color: #409eff; background: #ecf5ff; }
.feature-item {
  padding: 12px; background: #f5f7fa; border-radius: 6px; margin-bottom: 8px;
  text-align: center;
}
.feature-action { font-size: 13px; color: #303133; margin-bottom: 4px; }
.feature-count { font-size: 12px; color: #909399; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
</style>
