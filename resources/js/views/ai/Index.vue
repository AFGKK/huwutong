<template>
  <div class="ai-dashboard-page">
    <div class="page-header">
      <h2>{{ t('ai_page.title') }}</h2>
      <p class="text-muted">{{ t('ai_page.subtitle') }}</p>
    </div>

    <el-row
      v-for="(row, rowIndex) in navCardRows"
      :key="rowIndex"
      :gutter="20"
      :style="rowIndex === 0 ? 'margin-bottom:20px' : undefined"
    >
      <el-col v-for="card in row" :key="card.key" :span="8">
        <el-card shadow="hover" class="nav-card" @click="$router.push(card.route)">
          <div class="nav-icon" :style="{ background: card.bg, color: card.color }">
            <el-icon :size="24"><component :is="card.icon" /></el-icon>
          </div>
          <div class="nav-info">
            <strong>{{ card.title }}</strong>
            <p>{{ card.desc }}</p>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { TrendCharts, Warning, Lock, Coin, Box, Document } from '@element-plus/icons-vue';

const { t } = useI18n();

const CARD_DEFS = [
  { key: 'revenue_forecast', route: '/ai/revenue-forecast', icon: TrendCharts, bg: '#e6f7ff', color: '#1890ff' },
  { key: 'churn_prediction', route: '/ai/churn-prediction', icon: Warning, bg: '#fff7e6', color: '#fa8c16' },
  { key: 'adaptive_security', route: '/ai/adaptive-security', icon: Lock, bg: '#fff1f0', color: '#f5222d' },
  { key: 'pricing_optimizer', route: '/ai/pricing-optimizer', icon: Coin, bg: '#f6ffed', color: '#52c41a' },
  { key: 'sdk_generator', route: '/ai/sdk-generator', icon: Box, bg: '#f9f0ff', color: '#722ed1' },
  { key: 'test_generator', route: '/ai/test-generator', icon: Document, bg: '#e6fffb', color: '#13c2c2' },
];

const navCards = computed(() =>
  CARD_DEFS.map((def) => ({
    ...def,
    title: t(`ai_page.cards.${def.key}.title`),
    desc: t(`ai_page.cards.${def.key}.desc`),
  }))
);

const navCardRows = computed(() => [
  navCards.value.slice(0, 3),
  navCards.value.slice(3, 6),
]);
</script>

<style scoped>
.ai-dashboard-page { padding: 20px; }
.page-header { margin-bottom: 24px; }
.page-header h2 { margin: 0 0 4px; font-size: 22px; }
.nav-card { cursor: pointer; transition: transform .2s; }
.nav-card:hover { transform: translateY(-2px); }
.nav-card :deep(.el-card__body) { display: flex; align-items: center; gap: 16px; }
.nav-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.nav-info strong { display: block; margin-bottom: 4px; }
.nav-info p { margin: 0; font-size: 12px; color: #909399; }
.text-muted { color: #909399; font-size: 14px; }
</style>
