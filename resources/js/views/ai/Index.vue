<template>
  <div class="ai-dashboard-page">
    <div class="page-header">
      <h2>AI 智能套件</h2>
      <p class="text-muted">收入预测 · 流失预警 · 自适应安全 · 定价建议 · SDK 生成 · 测试生成</p>
    </div>

    <el-row :gutter="20" style="margin-bottom:20px">
      <el-col :span="8">
        <el-card shadow="hover" class="nav-card" @click="$router.push('/ai/revenue-forecast')">
          <div class="nav-icon" style="background:#e6f7ff;color:#1890ff">📈</div>
          <div class="nav-info">
            <strong>AI 收入预测</strong>
            <p>ML时间序列预测月度/季度MRR/ARR</p>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover" class="nav-card" @click="$router.push('/ai/churn-prediction')">
          <div class="nav-icon" style="background:#fff7e6;color:#fa8c16">⚠️</div>
          <div class="nav-info">
            <strong>AI 客户流失预警</strong>
            <p>识别高风险客户并建议干预措施</p>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover" class="nav-card" @click="$router.push('/ai/adaptive-security')">
          <div class="nav-icon" style="background:#fff1f0;color:#f5222d">🛡️</div>
          <div class="nav-info">
            <strong>AI 自适应安全</strong>
            <p>动态调整限流/信任分/熔断阈值</p>
          </div>
        </el-card>
      </el-col>
    </el-row>
    <el-row :gutter="20">
      <el-col :span="8">
        <el-card shadow="hover" class="nav-card" @click="$router.push('/ai/pricing-optimizer')">
          <div class="nav-icon" style="background:#f6ffed;color:#52c41a">💰</div>
          <div class="nav-info">
            <strong>AI 定价建议</strong>
            <p>最优定价/折扣策略/套餐组合</p>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover" class="nav-card" @click="$router.push('/ai/sdk-generator')">
          <div class="nav-icon" style="background:#f9f0ff;color:#722ed1">📦</div>
          <div class="nav-info">
            <strong>AI SDK 生成器</strong>
            <p>自动生成集成代码和依赖配置</p>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover" class="nav-card" @click="$router.push('/ai/test-generator')">
          <div class="nav-icon" style="background:#e6fffb;color:#13c2c2">🧪</div>
          <div class="nav-info">
            <strong>AI 测试生成器</strong>
            <p>基于OpenAPI Schema自动生成测试用例</p>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
</script>

<style scoped>
.ai-dashboard-page { padding: 20px; }
.page-header { margin-bottom: 24px; }
.page-header h2 { margin: 0 0 4px; font-size: 22px; }
.nav-card { cursor: pointer; transition: transform .2s; }
.nav-card:hover { transform: translateY(-2px); }
.nav-card :deep(.el-card__body) { display: flex; align-items: center; gap: 16px; }
.nav-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; }
.nav-info strong { display: block; margin-bottom: 4px; }
.nav-info p { margin: 0; font-size: 12px; color: #909399; }
.text-muted { color: #909399; font-size: 14px; }
</style>
