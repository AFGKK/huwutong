<template>
  <div class="dynamic-pricing-page">
    <!-- 统计/概览 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value">{{ stats.totalRules }}</div>
            <div class="stat-label">定价规则总数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value text-success">{{ stats.activeRules }}</div>
            <div class="stat-label">活跃规则</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value">{{ stats.totalTiers }}</div>
            <div class="stat-label">阶梯定价组</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value">{{ stats.totalApplications }}</div>
            <div class="stat-label">规则应用次数</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-tabs v-model="activeTab" type="border-card">
      <!-- ──────── 标签1: 定价规则 ──────── -->
      <el-tab-pane label="定价规则" name="rules">
        <div class="tab-toolbar">
          <el-form :inline="true" size="small">
            <el-form-item>
              <el-select v-model="ruleFilter.rule_type" placeholder="规则类型" clearable @change="fetchRules" style="width:130px">
                <el-option v-for="rt in metadata.rule_types" :key="rt.value" :label="rt.label" :value="rt.value" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-select v-model="ruleFilter.target_type" placeholder="目标类型" clearable @change="fetchRules" style="width:120px">
                <el-option v-for="tt in metadata.target_types" :key="tt.value" :label="tt.label" :value="tt.value" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-input v-model="ruleFilter.search" placeholder="搜索规则名称" clearable @input="onSearchDebounce" style="width:200px" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="showCreateRuleDlg = true">
                <el-icon><Plus /></el-icon> 新建规则
              </el-button>
            </el-form-item>
          </el-form>
        </div>

        <el-table :data="rules" v-loading="loading" stripe>
          <el-table-column prop="name" label="规则名称" min-width="180" show-overflow-tooltip />
          <el-table-column label="类型" width="120">
            <template #default="{ row }">
              <el-tag :type="ruleTypeTag(row.rule_type)" size="small">{{ ruleTypeLabel(row.rule_type) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="调整" width="120">
            <template #default="{ row }">
              <span v-if="row.adjustment_type === 'percentage'">{{ row.adjustment_value }}% {{ row.stack_mode === 'add' ? '加价' : '折扣' }}</span>
              <span v-else-if="row.adjustment_type === 'fixed'">{{ row.adjustment_value }} 固定</span>
              <span v-else-if="row.adjustment_type === 'override'">{{ row.adjustment_value }} 覆盖</span>
              <span v-else>{{ row.adjustment_value }}</span>
            </template>
          </el-table-column>
          <el-table-column label="目标" width="120">
            <template #default="{ row }">{{ targetLabel(row.target_type) }}</template>
          </el-table-column>
          <el-table-column label="优先级" width="70" prop="priority" />
          <el-table-column label="状态" width="80">
            <template #default="{ row }">
              <el-switch :modelValue="row.is_active" @click="toggleRule(row)" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="应用次数" width="80" prop="applied_count" />
          <el-table-column label="有效期" min-width="150">
            <template #default="{ row }">
              <small v-if="row.starts_at || row.ends_at">
                {{ row.starts_at ? formatDate(row.starts_at) : '不限' }}
                ~ {{ row.ends_at ? formatDate(row.ends_at) : '不限' }}
              </small>
              <small v-else class="text-muted">长期有效</small>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="160" fixed="right">
            <template #default="{ row }">
              <el-button size="small" link @click="editRule(row)">编辑</el-button>
              <el-button size="small" link @click="viewRuleLogs(row)">日志</el-button>
              <el-popconfirm title="确定删除此规则？" @confirm="deleteRule(row)">
                <template #reference>
                  <el-button size="small" type="danger" link>删除</el-button>
                </template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ──────── 标签2: 阶梯定价 ──────── -->
      <el-tab-pane label="阶梯定价" name="tiers">
        <div class="tab-toolbar">
          <el-form :inline="true" size="small">
            <el-form-item>
              <el-select v-model="tierPlanId" placeholder="选择定价方案" clearable style="width:200px">
                <el-option v-for="p in plans" :key="p.id" :label="p.name" :value="p.id" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :disabled="!tierPlanId" @click="fetchTiers">
                <el-icon><Search /></el-icon> 查询
              </el-button>
              <el-button type="success" :disabled="!tierPlanId" @click="showCreateTierDlg = true">
                <el-icon><Plus /></el-icon> 新增阶梯
              </el-button>
            </el-form-item>
          </el-form>
        </div>

        <el-table v-if="tiers.length" :data="tiers" stripe v-loading="tierLoading">
          <el-table-column prop="name" label="阶梯名称" min-width="150" />
          <el-table-column prop="from_quantity" label="起始数量" width="100" />
          <el-table-column label="结束数量" width="100">
            <template #default="{ row }">{{ row.to_quantity ?? '∞' }}</template>
          </el-table-column>
          <el-table-column prop="unit_price" label="单价" width="120">
            <template #default="{ row }">¥{{ row.unit_price }}</template>
          </el-table-column>
          <el-table-column prop="flat_fee" label="固定费用" width="120">
            <template #default="{ row }">¥{{ row.flat_fee }}</template>
          </el-table-column>
          <el-table-column prop="sort_order" label="排序" width="70" />
          <el-table-column label="状态" width="80">
            <template #default="{ row }">
              <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="120">
            <template #default="{ row }">
              <el-button size="small" link @click="editTier(row)">编辑</el-button>
              <el-popconfirm title="确认删除？" @confirm="deleteTier(row)">
                <template #reference>
                  <el-button size="small" type="danger" link>删除</el-button>
                </template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-else-if="!tierLoading && tierPlanId" description="暂无阶梯定价，请" />

        <!-- 阶梯定价预览 -->
        <el-card v-if="tiers.length > 0" shadow="never" class="mt-3">
          <template #header>
            <span>阶梯定价模拟</span>
          </template>
          <el-form :inline="true" size="small">
            <el-form-item label="购买数量">
              <el-input-number v-model="simQty" :min="1" :max="10000" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="simulateTierPrice">模拟计算</el-button>
            </el-form-item>
          </el-form>
          <div v-if="simResult">
            <el-descriptions :column="2" border size="small">
              <el-descriptions-item label="总价">¥{{ simResult.total_price }}</el-descriptions-item>
              <el-descriptions-item label="节省">¥{{ simResult.saving }}</el-descriptions-item>
              <el-descriptions-item label="有效单价">¥{{ simResult.unit_price }}</el-descriptions-item>
              <el-descriptions-item label="节省比例">{{ simResult.saving_percent }}%</el-descriptions-item>
            </el-descriptions>
          </div>
        </el-card>
      </el-tab-pane>

      <!-- ──────── 标签3: 定价计算器 ──────── -->
      <el-tab-pane label="定价计算器" name="calculator">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-card shadow="never">
              <template #header>价格计算</template>
              <el-form label-width="100px" size="small">
                <el-form-item label="定价方案">
                  <el-select v-model="calcForm.pricing_plan_id" placeholder="选择方案" style="width:100%">
                    <el-option v-for="p in plans" :key="p.id" :label="p.name" :value="p.id" />
                  </el-select>
                </el-form-item>
                <el-form-item label="计费周期">
                  <el-select v-model="calcForm.billing_period" style="width:100%">
                    <el-option label="月付" value="monthly" />
                    <el-option label="季付" value="quarterly" />
                    <el-option label="半年付" value="semi_annually" />
                    <el-option label="年付" value="yearly" />
                  </el-select>
                </el-form-item>
                <el-form-item label="数量">
                  <el-input-number v-model="calcForm.quantity" :min="1" />
                </el-form-item>
                <el-form-item>
                  <el-button type="primary" :loading="calcLoading" @click="calculatePrice">计算价格</el-button>
                </el-form-item>
              </el-form>
            </el-card>
          </el-col>
          <el-col :span="12">
            <el-card shadow="never">
              <template #header>
                计算结果
                <el-tag v-if="calcResult.total_discount > 0" type="danger" size="small" class="ml-2">
                  折扣 {{ calcResult.discount_percent }}%
                </el-tag>
              </template>
              <template v-if="calcResult.original_price !== undefined">
                <el-descriptions :column="1" border size="small">
                  <el-descriptions-item label="原始价格">¥{{ calcResult.original_price }}</el-descriptions-item>
                  <el-descriptions-item label="最终价格">
                    <strong class="text-success">¥{{ calcResult.final_price }}</strong>
                  </el-descriptions-item>
                  <el-descriptions-item label="折扣金额">¥{{ calcResult.total_discount }}</el-descriptions-item>
                </el-descriptions>

                <!-- 应用规则明细 -->
                <div v-if="calcResult.breakdown?.length" class="mt-2">
                  <h4>规则明细</h4>
                  <el-table :data="calcResult.breakdown" size="small" border>
                    <el-table-column prop="rule_name" label="规则" min-width="140" />
                    <el-table-column prop="price_before" label="调整前" width="90">
                      <template #default="{ row }">¥{{ row.price_before }}</template>
                    </el-table-column>
                    <el-table-column prop="price_after" label="调整后" width="90">
                      <template #default="{ row }">¥{{ row.price_after }}</template>
                    </el-table-column>
                    <el-table-column prop="step_discount" label="折扣" width="80">
                      <template #default="{ row }"><span class="text-danger">-¥{{ row.step_discount }}</span></template>
                    </el-table-column>
                  </el-table>
                </div>
              </template>
              <div v-else class="text-muted">点击"计算价格"查看结果</div>
            </el-card>
          </el-col>
        </el-row>
      </el-tab-pane>

      <!-- ──────── 标签4: LLM 定价优化 ──────── -->
      <el-tab-pane label="AI 定价优化" name="optimize">
        <el-row :gutter="20">
          <el-col :span="8">
            <el-card shadow="never">
              <template #header>选择方案</template>
              <el-form label-width="80px" size="small">
                <el-form-item label="方案">
                  <el-select v-model="optimizePlanId" placeholder="选择方案" style="width:100%">
                    <el-option v-for="p in plans" :key="p.id" :label="p.name" :value="p.id" />
                  </el-select>
                </el-form-item>
                <el-form-item>
                  <el-button type="primary" :loading="optimizeLoading" :disabled="!optimizePlanId" @click="runOptimization">
                    <el-icon><MagicStick /></el-icon> AI 分析优化
                  </el-button>
                </el-form-item>
              </el-form>
            </el-card>
          </el-col>
          <el-col :span="16">
            <el-card shadow="never" v-if="optimizeResult">
              <template #header>
                <span>优化建议</span>
                <el-tag v-if="optimizeResult.suggestions?.overall_score" type="warning" size="small" class="ml-2">
                  健康度: {{ optimizeResult.suggestions.overall_score }}/100
                </el-tag>
              </template>
              <div v-if="optimizeResult.suggestions">
                <div v-if="optimizeResult.suggestions.price_suggestions" class="mb-2">
                  <h4>建议价格</h4>
                  <el-descriptions :column="2" border size="small">
                    <el-descriptions-item v-for="(val, key) in optimizeResult.suggestions.price_suggestions" :key="key" :label="key">
                      ¥{{ val }}
                    </el-descriptions-item>
                  </el-descriptions>
                </div>
                <div v-if="optimizeResult.suggestions.tier_suggestions" class="mt-2">
                  <h4>阶梯定价建议</h4>
                  <pre class="suggestion-pre">{{ formatJson(optimizeResult.suggestions.tier_suggestions) }}</pre>
                </div>
                <div v-if="optimizeResult.suggestions.bundling_opportunities" class="mt-2">
                  <h4>打包机会</h4>
                  <el-text type="info">{{ optimizeResult.suggestions.bundling_opportunities }}</el-text>
                </div>
                <div v-if="optimizeResult.suggestions.risk_warnings" class="mt-2">
                  <h4>风险提示</h4>
                  <el-alert :title="optimizeResult.suggestions.risk_warnings" type="warning" :closable="false" show-icon />
                </div>
                <div v-if="optimizeResult.suggestions.seasonal_strategy" class="mt-2">
                  <h4>季节性策略</h4>
                  <el-text>{{ optimizeResult.suggestions.seasonal_strategy }}</el-text>
                </div>
              </div>
              <div v-else-if="optimizeResult.error" class="text-danger">{{ optimizeResult.error }}</div>
            </el-card>
            <el-card v-else shadow="never">
              <div class="text-muted" style="text-align:center;padding:40px">
                <el-icon :size="48" color="#c0c4cc"><MagicStick /></el-icon>
                <p class="mt-2">选择定价方案后点击"AI 分析优化"</p>
                <p class="text-muted">LLM 将分析当前价格结构并给出优化建议</p>
              </div>
            </el-card>
          </el-col>
        </el-row>
      </el-tab-pane>

      <!-- ──────── 标签5: 应用日志 ──────── -->
      <el-tab-pane label="规则应用日志" name="logs">
        <el-table :data="appLogs" v-loading="logLoading" stripe>
          <el-table-column label="规则" min-width="150">
            <template #default="{ row }">{{ row.rule_id }}</template>
          </el-table-column>
          <el-table-column label="场景" width="120">
            <template #default="{ row }">{{ row.context_type }}</template>
          </el-table-column>
          <el-table-column label="原始价格" width="100">
            <template #default="{ row }">¥{{ row.original_price }}</template>
          </el-table-column>
          <el-table-column label="最终价格" width="100">
            <template #default="{ row }">¥{{ row.final_price }}</template>
          </el-table-column>
          <el-table-column label="折扣金额" width="100">
            <template #default="{ row }">-¥{{ row.discount_amount }}</template>
          </el-table-column>
          <el-table-column label="应用时间" width="170">
            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ──────── 标签6: 定价实验 (M3-26) ──────── -->
      <el-tab-pane label="定价实验" name="experiments">
        <div class="tab-toolbar">
          <el-button type="primary" @click="showCreateExperiment = true">
            <el-icon><Plus /></el-icon> 新建实验
          </el-button>
          <span style="margin-left:12px;color:var(--el-text-color-secondary);font-size:13px">
            运行中: <strong>{{ expStats.running }}</strong> | 已完成: <strong>{{ expStats.completed }}</strong> | 总参与者: <strong>{{ expStats.total_participants }}</strong>
          </span>
        </div>

        <el-table :data="experiments" v-loading="expLoading" stripe>
          <el-table-column prop="name" label="实验名称" min-width="160" />
          <el-table-column prop="experiment_type" label="类型" width="100">
            <template #default="{ row }"><el-tag effect="plain" size="small">{{ row.experiment_type }}</el-tag></template>
          </el-table-column>
          <el-table-column prop="traffic_split" label="流量分配" width="100">
            <template #default="{ row }">{{ row.traffic_split }}%</template>
          </el-table-column>
          <el-table-column label="样本量" width="90">
            <template #default="{ row }">{{ row.sample_size }}/{{ row.minimum_sample_size }}</template>
          </el-table-column>
          <el-table-column label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="expStatusType(row.status)" effect="plain" size="small">{{ expStatusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="实验结果" min-width="200">
            <template #default="{ row }">
              <span v-if="row.results?.improvement" style="font-size:13px">
                <span :style="{color: (row.results.improvement.conversion_rate || 0) >= 0 ? '#67c23a' : '#f56c6c'}">
                  转化率 {{ row.results.improvement.conversion_rate >= 0 ? '+' : '' }}{{ row.results.improvement.conversion_rate }}%
                </span>
                <span v-if="row.results.significance?.significant" style="margin-left:6px">
                  <el-tag effect="plain" size="small" type="success">显著</el-tag>
                </span>
              </span>
              <span v-else class="no-data">-</span>
            </template>
          </el-table-column>
          <el-table-column prop="starts_at" label="开始时间" width="160" />
          <el-table-column label="操作" width="200" fixed="right">
            <template #default="{ row }">
              <el-button text size="small" type="primary" @click="viewExperiment(row)">详情</el-button>
              <el-button v-if="row.status === 'draft' || row.status === 'paused'" text size="small" type="success" @click="handleStartExp(row)">启动</el-button>
              <el-button v-if="row.status === 'running'" text size="small" type="warning" @click="handlePauseExp(row)">暂停</el-button>
              <el-button v-if="row.status === 'running' || row.status === 'paused'" text size="small" type="primary" @click="handleCompleteExp(row)">结束</el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="pagination-wrap">
          <el-pagination v-model:current-page="expPage" :page-size="20" :total="expTotal" layout="prev, pager, next" background small @current-change="fetchExperiments" />
        </div>
      </el-tab-pane>
    </el-tabs>

    <!-- ───── 新建/编辑规则 对话框 ───── -->
    <el-dialog v-model="showCreateRuleDlg" :title="editRuleId ? '编辑定价规则' : '新建定价规则'" width="700px">
      <el-form :model="ruleForm" :rules="ruleRules" ref="ruleFormRef" label-width="100px" size="small">
        <el-row :gutter="16">
          <el-col :span="16">
            <el-form-item label="规则名称" prop="name">
              <el-input v-model="ruleForm.name" maxlength="200" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="优先级" prop="priority">
              <el-input-number v-model="ruleForm.priority" :min="0" :max="9999" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="描述">
          <el-input v-model="ruleForm.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="规则类型" prop="rule_type">
              <el-select v-model="ruleForm.rule_type" style="width:100%">
                <el-option v-for="rt in metadata.rule_types" :key="rt.value" :label="rt.label" :value="rt.value" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="目标类型" prop="target_type">
              <el-select v-model="ruleForm.target_type" style="width:100%">
                <el-option v-for="tt in metadata.target_types" :key="tt.value" :label="tt.label" :value="tt.value" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item label="调整类型" prop="adjustment_type">
              <el-select v-model="ruleForm.adjustment_type" style="width:100%">
                <el-option v-for="at in metadata.adjustment_types" :key="at.value" :label="at.label" :value="at.value" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="调整值" prop="adjustment_value">
              <el-input-number v-model="ruleForm.adjustment_value" :precision="2" :step="5" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="叠加方式" prop="stack_mode">
              <el-select v-model="ruleForm.stack_mode" style="width:100%">
                <el-option v-for="sm in metadata.stack_modes" :key="sm.value" :label="sm.label" :value="sm.value" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="最低价格">
              <el-input-number v-model="ruleForm.min_price" :min="0" :precision="2" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="最高价格">
              <el-input-number v-model="ruleForm.max_price" :min="0" :precision="2" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="开始时间">
              <el-date-picker v-model="ruleForm.starts_at" type="datetime" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="结束时间">
              <el-date-picker v-model="ruleForm.ends_at" type="datetime" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="showCreateRuleDlg = false">取消</el-button>
        <el-button type="primary" :loading="savingRule" @click="submitRule">保存</el-button>
      </template>
    </el-dialog>

    <!-- ───── 新建阶梯定价 对话框 ───── -->
    <el-dialog v-model="showCreateTierDlg" :title="editTierId ? '编辑阶梯' : '新增阶梯'" width="500px">
      <el-form :model="tierForm" label-width="100px" size="small">
        <el-form-item label="阶梯名称" prop="name">
          <el-input v-model="tierForm.name" placeholder="如：标准价、批量价" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="起始数量">
              <el-input-number v-model="tierForm.from_quantity" :min="1" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="结束数量">
              <el-input-number v-model="tierForm.to_quantity" :min="0" :controls="false" style="width:100%" placeholder="留空=无限" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="单价">
              <el-input-number v-model="tierForm.unit_price" :precision="2" :min="0" :step="10" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="固定费用">
              <el-input-number v-model="tierForm.flat_fee" :precision="2" :min="0" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="showCreateTierDlg = false">取消</el-button>
        <el-button type="primary" :loading="savingTier" @click="submitTier">保存</el-button>
      </template>
    </el-dialog>

    <!-- ─── 新建实验 对话框 ─── -->
    <el-dialog v-model="showCreateExperiment" title="新建定价实验" width="600px">
      <el-form :model="expForm" ref="expFormRef" label-width="140px" size="small">
        <el-form-item label="实验名称" prop="name">
          <el-input v-model="expForm.name" maxlength="200" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="expForm.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="实验类型">
              <el-select v-model="expForm.experiment_type" style="width:100%">
                <el-option label="定价调整" value="pricing" />
                <el-option label="折扣" value="discount" />
                <el-option label="捆绑" value="bundle" />
                <el-option label="阶梯" value="tier" />
                <el-option label="促销" value="promotion" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="衡量指标">
              <el-select v-model="expForm.target_metric" style="width:100%">
                <el-option label="转化率" value="conversion" />
                <el-option label="收入" value="revenue" />
                <el-option label="留存" value="retention" />
                <el-option label="利润" value="profit" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="实验组流量">
              <el-input-number v-model="expForm.traffic_split" :min="1" :max="99" style="width:100%" /> %
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="最小样本量">
              <el-input-number v-model="expForm.minimum_sample_size" :min="10" :step="50" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="实验配置(Control)">
          <el-input v-model="expForm.controlConfigStr" type="textarea" :rows="2" placeholder='{"price_multiplier": 1.0}' />
        </el-form-item>
        <el-form-item label="实验配置(Treatment)">
          <el-input v-model="expForm.treatmentConfigStr" type="textarea" :rows="2" placeholder='{"adjustment_type":"percentage","adjustment_value":-10}' />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="开始时间">
              <el-date-picker v-model="expForm.starts_at" type="datetime" placeholder="立即开始" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="结束时间">
              <el-date-picker v-model="expForm.ends_at" type="datetime" placeholder="不设限制" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="showCreateExperiment = false">取消</el-button>
        <el-button type="primary" :loading="savingExp" @click="submitExperiment">保存草稿</el-button>
      </template>
    </el-dialog>

    <!-- ─── 实验详情 对话框 ─── -->
    <el-dialog v-model="showExpDetail" :title="expDetail?.name || '实验详情'" width="700px">
      <el-descriptions v-if="expDetail" :column="2" border>
        <el-descriptions-item label="状态" :span="2">
          <el-tag :type="expStatusType(expDetail.status)" effect="plain">{{ expStatusLabel(expDetail.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="类型">{{ expDetail.experiment_type }}</el-descriptions-item>
        <el-descriptions-item label="指标">{{ expDetail.target_metric }}</el-descriptions-item>
        <el-descriptions-item label="流量分配">{{ expDetail.traffic_split }}% 实验组</el-descriptions-item>
        <el-descriptions-item label="样本量">{{ expDetail.sample_size }}/{{ expDetail.minimum_sample_size }}</el-descriptions-item>
        <el-descriptions-item label="开始">{{ expDetail.starts_at || '-' }}</el-descriptions-item>
        <el-descriptions-item label="结束">{{ expDetail.ends_at || '-' }}</el-descriptions-item>
      </el-descriptions>

      <h4 style="margin-top:16px">实验结果</h4>
      <el-table v-if="expDetail?.results" :data="resultRows(expDetail.results)" stripe>
        <el-table-column prop="metric" label="指标" width="120" />
        <el-table-column prop="control" label="对照组" width="140" />
        <el-table-column prop="treatment" label="实验组" width="140" />
        <el-table-column prop="improvement" label="提升" width="120">
          <template #default="{ row }">
            <span :style="{color: (row.improvementVal || 0) >= 0 ? '#67c23a' : '#f56c6c'}">
              {{ row.improvementVal >= 0 ? '+' : '' }}{{ row.improvementVal }}
            </span>
          </template>
        </el-table-column>
      </el-table>
      <div v-else style="padding:20px 0;text-align:center;color:var(--el-text-color-placeholder)">实验尚未完成或暂无数据</div>

      <div v-if="expDetail?.results?.significance" style="margin-top:12px">
        <el-tag :type="expDetail.results.significance.significant ? 'success' : 'info'" effect="plain">
          {{ expDetail.results.significance.significant ? '统计显著 (p < 0.05)' : '未达到统计显著性' }}
        </el-tag>
        <span style="margin-left:8px;font-size:12px;color:var(--el-text-color-secondary)">
          Z={{ expDetail.results.significance.z_score }}, p={{ expDetail.results.significance.p_value }}
        </span>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus, Search, Refresh, MagicStick } from '@element-plus/icons-vue';
import dynamicPricingApi from '../../api/dynamicPricing';
import billingApi from '../../api/billing';

export default {
  name: 'DynamicPricing',
  components: { Plus, Search, Refresh, MagicStick },
  setup() {
    const activeTab = ref('rules');
    const loading = ref(false);
    const tierLoading = ref(false);
    const calcLoading = ref(false);
    const optimizeLoading = ref(false);
    const logLoading = ref(false);
    const savingRule = ref(false);
    const savingTier = ref(false);

    const stats = reactive({
      totalRules: 0, activeRules: 0, totalTiers: 0, totalApplications: 0,
    });

    const metadata = reactive({
      rule_types: [], adjustment_types: [], stack_modes: [], target_types: [], pricing_models: [],
    });

    const plans = ref([]);

    // 规则
    const rules = ref([]);
    const ruleFilter = reactive({ rule_type: '', target_type: '', search: '' });
    const showCreateRuleDlg = ref(false);
    const editRuleId = ref(null);
    const ruleFormRef = ref(null);
    const ruleForm = reactive({
      name: '', description: '', rule_type: 'promotion', target_type: 'plan',
      adjustment_type: 'percentage', adjustment_value: 10, stack_mode: 'multiply',
      priority: 100, min_price: null, max_price: null,
      starts_at: null, ends_at: null,
    });
    const ruleRules = {
      name: [{ required: true, message: '请输入规则名称' }],
      rule_type: [{ required: true, message: '请选择规则类型' }],
      target_type: [{ required: true, message: '请选择目标类型' }],
      adjustment_type: [{ required: true, message: '请选择调整类型' }],
    };

    // 阶梯
    const tierPlanId = ref(null);
    const tiers = ref([]);
    const showCreateTierDlg = ref(false);
    const editTierId = ref(null);
    const tierForm = reactive({
      pricing_plan_id: null, name: '', from_quantity: 1, to_quantity: null,
      unit_price: 0, flat_fee: 0,
    });
    const simQty = ref(10);
    const simResult = ref(null);

    // 计算器
    const calcForm = reactive({
      pricing_plan_id: null, billing_period: 'monthly', quantity: 1,
    });
    const calcResult = reactive({});

    // LLM 优化
    const optimizePlanId = ref(null);
    const optimizeResult = ref(null);

    // 日志
    const appLogs = ref([]);

    // ─── 数据加载 ───
    async function loadMetadata() {
      try {
        const { data } = await dynamicPricingApi.getMetadata();
        if (data.success) Object.assign(metadata, data.data);
      } catch (e) { /* ignore */ }
    }

    async function loadPlans() {
      try {
        const { data } = await billingApi.getPlans();
        if (data.success) plans.value = data.data.data || data.data;
      } catch (e) { /* ignore */ }
    }

    // ─── 规则 ───
    async function fetchRules() {
      loading.value = true;
      try {
        const params = { per_page: 100 };
        if (ruleFilter.rule_type) params.rule_type = ruleFilter.rule_type;
        if (ruleFilter.target_type) params.target_type = ruleFilter.target_type;
        if (ruleFilter.search) params.search = ruleFilter.search;
        const { data } = await dynamicPricingApi.getRules(params);
        if (data.success) {
          rules.value = data.data.data || data.data;
          stats.totalRules = data.data.total || 0;
          stats.activeRules = rules.value.filter(r => r.is_active).length;
        }
      } catch (e) { ElMessage.error('获取规则失败'); }
      finally { loading.value = false; }
    }

    async function submitRule() {
      const valid = await ruleFormRef.value?.validate().catch(() => false);
      if (!valid) return;
      savingRule.value = true;
      try {
        const payload = { ...ruleForm };
        const { data } = editRuleId.value
          ? await dynamicPricingApi.updateRule(editRuleId.value, payload)
          : await dynamicPricingApi.createRule(payload);
        if (data.success) {
          ElMessage.success(editRuleId.value ? '规则已更新' : '规则已创建');
          showCreateRuleDlg.value = false;
          resetRuleForm();
          fetchRules();
        }
      } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败'); }
      finally { savingRule.value = false; }
    }

    function editRule(row) {
      editRuleId.value = row.id;
      Object.assign(ruleForm, {
        name: row.name, description: row.description || '',
        rule_type: row.rule_type, target_type: row.target_type,
        adjustment_type: row.adjustment_type, adjustment_value: (row.adjustment_value ?? 0),
        stack_mode: row.stack_mode, priority: row.priority,
        min_price: row.min_price, max_price: row.max_price,
        starts_at: row.starts_at, ends_at: row.ends_at,
      });
      showCreateRuleDlg.value = true;
    }

    function resetRuleForm() {
      editRuleId.value = null;
      ruleForm.name = ''; ruleForm.description = '';
      ruleForm.rule_type = 'promotion'; ruleForm.target_type = 'plan';
      ruleForm.adjustment_type = 'percentage'; ruleForm.adjustment_value = 10;
      ruleForm.stack_mode = 'multiply'; ruleForm.priority = 100;
      ruleForm.min_price = null; ruleForm.max_price = null;
      ruleForm.starts_at = null; ruleForm.ends_at = null;
    }

    async function toggleRule(row) {
      try {
        const { data } = await dynamicPricingApi.toggleRule(row.id);
        if (data.success) {
          row.is_active = data.data.is_active;
          ElMessage.success(data.message);
        }
      } catch (e) { ElMessage.error('操作失败'); }
    }

    async function deleteRule(row) {
      try {
        await dynamicPricingApi.deleteRule(row.id);
        ElMessage.success('规则已删除');
        fetchRules();
      } catch (e) { ElMessage.error('删除失败'); }
    }

    // ─── 阶梯 ───
    async function fetchTiers() {
      if (!tierPlanId.value) return;
      tierLoading.value = true;
      try {
        const { data } = await dynamicPricingApi.getTiers(tierPlanId.value);
        if (data.success) {
          tiers.value = data.data;
          stats.totalTiers = data.data.length;
          tierForm.pricing_plan_id = tierPlanId.value;
        }
      } catch (e) { ElMessage.error('获取阶梯失败'); }
      finally { tierLoading.value = false; }
    }

    async function submitTier() {
      savingTier.value = true;
      try {
        const payload = { ...tierForm, pricing_plan_id: tierPlanId.value };
        const { data } = editTierId.value
          ? await dynamicPricingApi.updateTier(editTierId.value, payload)
          : await dynamicPricingApi.createTier(payload);
        if (data.success) {
          ElMessage.success(editTierId.value ? '阶梯已更新' : '阶梯已创建');
          showCreateTierDlg.value = false;
          editTierId.value = null;
          resetTierForm();
          fetchTiers();
        }
      } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败'); }
      finally { savingTier.value = false; }
    }

    function editTier(row) {
      editTierId.value = row.id;
      Object.assign(tierForm, {
        name: row.name, from_quantity: row.from_quantity,
        to_quantity: row.to_quantity, unit_price: (row.unit_price ?? 0),
        flat_fee: (row.flat_fee ?? 0),
      });
      showCreateTierDlg.value = true;
    }

    function resetTierForm() {
      editTierId.value = null;
      tierForm.name = ''; tierForm.from_quantity = 1;
      tierForm.to_quantity = null; tierForm.unit_price = 0; tierForm.flat_fee = 0;
    }

    async function deleteTier(row) {
      try {
        await dynamicPricingApi.deleteTier(row.id);
        ElMessage.success('阶梯已删除');
        fetchTiers();
      } catch (e) { ElMessage.error('删除失败'); }
    }

    async function simulateTierPrice() {
      try {
        const { data } = await dynamicPricingApi.calculatePrice({
          pricing_plan_id: tierPlanId.value,
          billing_period: 'monthly',
          quantity: simQty.value,
        });
        if (data.success) simResult.value = data.data;
      } catch (e) { ElMessage.error('模拟计算失败'); }
    }

    // ─── 计算器 ───
    async function calculatePrice() {
      calcLoading.value = true;
      try {
        const { data } = await dynamicPricingApi.calculatePrice(calcForm);
        if (data.success) Object.assign(calcResult, data.data);
      } catch (e) { ElMessage.error('计算失败'); }
      finally { calcLoading.value = false; }
    }

    // ─── LLM 优化 ───
    async function runOptimization() {
      if (!optimizePlanId.value) return;
      optimizeLoading.value = true;
      optimizeResult.value = null;
      try {
        const { data } = await dynamicPricingApi.optimizePricing(optimizePlanId.value);
        if (data.success) optimizeResult.value = data.data;
      } catch (e) { ElMessage.error('优化请求失败'); }
      finally { optimizeLoading.value = false; }
    }

    // ─── 日志 ───
    async function fetchLogs() {
      logLoading.value = true;
      try {
        const { data } = await dynamicPricingApi.getApplicationLogs({ per_page: 50 });
        if (data.success) appLogs.value = data.data.data || data.data;
      } catch (e) { /* ignore */ }
      finally { logLoading.value = false; }
    }

    function viewRuleLogs(row) {
      activeTab.value = 'logs';
      fetchLogs();
    }

    // ─── 工具 ───
    let searchTimer = null;
    function onSearchDebounce() {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(fetchRules, 300);
    }

    function ruleTypeTag(type) {
      const map = { volume: 'success', segment: 'primary', time_seasonal: 'warning', time_hourly: 'info', promotion: 'danger', llm_optimized: 'warning' };
      return map[type] || '';
    }

    function ruleTypeLabel(type) {
      const item = metadata.rule_types.find(r => r.value === type);
      return item ? item.label : type;
    }

    function targetLabel(type) {
      const item = metadata.target_types.find(t => t.value === type);
      return item ? item.label : type;
    }

    function formatTime(t) {
      if (!t) return '';
      return new Date(t).toLocaleString('zh-CN', { hour12: false });
    }

    function formatDate(d) {
      if (!d) return '';
      return new Date(d).toLocaleDateString('zh-CN');
    }

    function formatJson(obj) {
      try { return JSON.stringify(obj, null, 2); }
      catch { return String(obj); }
    }

    // ─── 初始化 ───
    onMounted(() => {
      loadMetadata();
      loadPlans();
      fetchRules();
      fetchLogs();
      fetchExperiments();
      fetchExpStats();
    });

    // ══════════ 定价实验 (M3-26) ══════════

    const showCreateExperiment = ref(false);
    const showExpDetail = ref(false);
    const expDetail = ref(null);
    const experiments = ref([]);
    const expLoading = ref(false);
    const expPage = ref(1);
    const expTotal = ref(0);
    const savingExp = ref(false);
    const expStats = ref({ total: 0, running: 0, completed: 0, draft: 0, total_participants: 0 });
    const expForm = ref({
      name: '', description: '', experiment_type: 'pricing', target_metric: 'conversion',
      traffic_split: 50, minimum_sample_size: 100,
      controlConfigStr: '', treatmentConfigStr: '',
      starts_at: null, ends_at: null,
    });

    function expStatusType(s) {
      return { draft: 'info', running: 'success', paused: 'warning', completed: 'primary', cancelled: 'danger' }[s] || 'info';
    }
    function expStatusLabel(s) {
      return { draft: '草稿', running: '运行中', paused: '已暂停', completed: '已完成', cancelled: '已取消' }[s] || s;
    }
    function resultRows(results) {
      if (!results?.control || !results?.treatment) return [];
      const c = results.control, t = results.treatment, i = results.improvement || {};
      return [
        { metric: '转化率', control: `${c.conversion_rate}%`, treatment: `${t.conversion_rate}%`, improvementVal: i.conversion_rate },
        { metric: '平均收入', control: `¥${c.avg_revenue}`, treatment: `¥${t.avg_revenue}`, improvementVal: `¥${i.avg_revenue}` },
        { metric: '流失率', control: `${c.churn_rate}%`, treatment: `${t.churn_rate}%`, improvementVal: i.churn_rate },
      ];
    }

    async function fetchExperiments() {
      expLoading.value = true;
      try {
        const { data } = await dynamicPricingApi.getExperiments({ page: expPage.value, per_page: 20 });
        if (data.success) {
          const d = data.data;
          experiments.value = d.data || d;
          expTotal.value = d.total || 0;
        }
      } catch (e) { /* ignore */ }
      finally { expLoading.value = false; }
    }
    async function fetchExpStats() {
      try {
        const { data } = await dynamicPricingApi.getExperimentStats();
        if (data.success) expStats.value = data.data || {};
      } catch (e) { /* ignore */ }
    }
    async function submitExperiment() {
      savingExp.value = true;
      try {
        const payload = {
          name: expForm.value.name,
          description: expForm.value.description,
          experiment_type: expForm.value.experiment_type,
          target_metric: expForm.value.target_metric,
          traffic_split: expForm.value.traffic_split,
          minimum_sample_size: expForm.value.minimum_sample_size,
          control_config: expForm.value.controlConfigStr ? JSON.parse(expForm.value.controlConfigStr) : {},
          treatment_config: expForm.value.treatmentConfigStr ? JSON.parse(expForm.value.treatmentConfigStr) : {},
          starts_at: expForm.value.starts_at,
          ends_at: expForm.value.ends_at,
        };
        const { data: res } = await dynamicPricingApi.createExperiment(payload);
        if (res.success) {
          ElMessage.success('实验已创建');
          showCreateExperiment.value = false;
          expForm.value = { name: '', description: '', experiment_type: 'pricing', target_metric: 'conversion', traffic_split: 50, minimum_sample_size: 100, controlConfigStr: '', treatmentConfigStr: '', starts_at: null, ends_at: null };
          fetchExperiments();
          fetchExpStats();
        }
      } catch (e) {
        ElMessage.error(e.response?.data?.message || '创建失败');
      }
      finally { savingExp.value = false; }
    }
    async function viewExperiment(row) {
      try {
        const { data } = await dynamicPricingApi.getExperiment(row.id);
        expDetail.value = data.success ? (data.data || data) : row;
      } catch (e) { expDetail.value = row; }
      showExpDetail.value = true;
    }
    async function handleStartExp(row) {
      try {
        const { data } = await dynamicPricingApi.startExperiment(row.id);
        if (data.success) { ElMessage.success('实验已启动'); fetchExperiments(); fetchExpStats(); }
      } catch (e) { ElMessage.error(e.response?.data?.message || '启动失败'); }
    }
    async function handlePauseExp(row) {
      try {
        const { data } = await dynamicPricingApi.pauseExperiment(row.id);
        if (data.success) { ElMessage.success('实验已暂停'); fetchExperiments(); }
      } catch (e) { ElMessage.error(e.response?.data?.message || '暂停失败'); }
    }
    async function handleCompleteExp(row) {
      try {
        const { data } = await dynamicPricingApi.completeExperiment(row.id);
        if (data.success) { ElMessage.success('实验已完成'); fetchExperiments(); fetchExpStats(); }
      } catch (e) { ElMessage.error(e.response?.data?.message || '结束失败'); }
    }

    return {
      activeTab, loading, tierLoading, calcLoading, optimizeLoading, logLoading,
      savingRule, savingTier, stats, metadata, plans,
      rules, ruleFilter, showCreateRuleDlg, editRuleId, ruleFormRef, ruleForm, ruleRules,
      tierPlanId, tiers, showCreateTierDlg, editTierId, tierForm,
      simQty, simResult, calcForm, calcResult,
      optimizePlanId, optimizeResult, appLogs,
      fetchRules, submitRule, editRule, toggleRule, deleteRule, onSearchDebounce,
      fetchTiers, submitTier, editTier, deleteTier, simulateTierPrice,
      calculatePrice, runOptimization, fetchLogs, viewRuleLogs,
      ruleTypeTag, ruleTypeLabel, targetLabel, formatTime, formatDate, formatJson,
      // 实验
      showCreateExperiment, showExpDetail, expDetail, experiments, expLoading, expPage, expTotal,
      savingExp, expStats, expForm,
      expStatusType, expStatusLabel, resultRows,
      fetchExperiments, submitExperiment, viewExperiment,
      handleStartExp, handlePauseExp, handleCompleteExp,
    };
  },
};
</script>

<style scoped>
.dynamic-pricing-page { padding: 16px; }
.stat-item { text-align: center; }
.stat-value { font-size: 24px; font-weight: 700; }
.stat-label { font-size: 12px; color: #909399; }
.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; }
.text-muted { color: #909399; }
.tab-toolbar { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mb-2 { margin-bottom: 8px; }
.mt-3 { margin-top: 12px; }
.mt-2 { margin-top: 8px; }
.ml-2 { margin-left: 8px; }
.suggestion-pre {
  background: #1e1e1e;
  color: #d4d4d4;
  padding: 12px;
  border-radius: 4px;
  font-size: 12px;
  line-height: 1.5;
  white-space: pre-wrap;
  max-height: 200px;
  overflow: auto;
}
</style>
