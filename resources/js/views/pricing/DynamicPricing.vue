<template>
  <div class="dynamic-pricing-page">
    <!-- 统计/概览 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value">{{ stats.totalRules }}</div>
            <div class="stat-label">{{ t('dynamic_pricing_page.stats.total_rules') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value text-success">{{ stats.activeRules }}</div>
            <div class="stat-label">{{ t('dynamic_pricing_page.stats.active_rules') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value">{{ stats.totalTiers }}</div>
            <div class="stat-label">{{ t('dynamic_pricing_page.stats.total_tiers') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-item">
            <div class="stat-value">{{ stats.totalApplications }}</div>
            <div class="stat-label">{{ t('dynamic_pricing_page.stats.total_applications') }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-tabs v-model="activeTab" type="border-card">
      <!-- ──────── 标签1: 定价规则 ──────── -->
      <el-tab-pane :label="t('dynamic_pricing_page.tabs.rules')" name="rules">
        <div class="tab-toolbar">
          <el-form :inline="true" size="small">
            <el-form-item>
              <el-select v-model="ruleFilter.rule_type" :placeholder="t('dynamic_pricing_page.rules.filter_rule_type')" clearable @change="fetchRules" style="width:130px">
                <el-option v-for="rt in localizedRuleTypes" :key="rt.value" :label="rt.label" :value="rt.value" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-select v-model="ruleFilter.target_type" :placeholder="t('dynamic_pricing_page.rules.filter_target_type')" clearable @change="fetchRules" style="width:120px">
                <el-option v-for="tt in localizedTargetTypes" :key="tt.value" :label="tt.label" :value="tt.value" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-input v-model="ruleFilter.search" :placeholder="t('dynamic_pricing_page.rules.search_ph')" clearable @input="onSearchDebounce" style="width:200px" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="showCreateRuleDlg = true">
                <el-icon><Plus /></el-icon> {{ t('dynamic_pricing_page.rules.create_btn') }}
              </el-button>
            </el-form-item>
          </el-form>
        </div>

        <el-table :data="rules" v-loading="loading" stripe>
          <el-table-column prop="name" :label="t('dynamic_pricing_page.cols.rule_name')" min-width="180" show-overflow-tooltip />
          <el-table-column :label="t('dynamic_pricing_page.cols.type')" width="120">
            <template #default="{ row }">
              <el-tag :type="ruleTypeTag(row.rule_type)" size="small">{{ ruleTypeLabel(row.rule_type) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('dynamic_pricing_page.cols.adjustment')" width="120">
            <template #default="{ row }">
              <span v-if="row.adjustment_type === 'percentage'">{{ row.adjustment_value }}% {{ row.stack_mode === 'add' ? t('dynamic_pricing_page.adjustment.markup') : t('dynamic_pricing_page.adjustment.discount') }}</span>
              <span v-else-if="row.adjustment_type === 'fixed'">{{ row.adjustment_value }} {{ t('dynamic_pricing_page.adjustment.fixed') }}</span>
              <span v-else-if="row.adjustment_type === 'override'">{{ row.adjustment_value }} {{ t('dynamic_pricing_page.adjustment.override') }}</span>
              <span v-else>{{ row.adjustment_value }}</span>
            </template>
          </el-table-column>
          <el-table-column :label="t('dynamic_pricing_page.cols.target')" width="120">
            <template #default="{ row }">{{ targetLabel(row.target_type) }}</template>
          </el-table-column>
          <el-table-column :label="t('dynamic_pricing_page.cols.priority')" width="70" prop="priority" />
          <el-table-column :label="t('dynamic_pricing_page.cols.status')" width="80">
            <template #default="{ row }">
              <el-switch :modelValue="row.is_active" @click="toggleRule(row)" size="small" />
            </template>
          </el-table-column>
          <el-table-column :label="t('dynamic_pricing_page.cols.applied_count')" width="80" prop="applied_count" />
          <el-table-column :label="t('dynamic_pricing_page.cols.validity')" min-width="150">
            <template #default="{ row }">
              <small v-if="row.starts_at || row.ends_at">
                {{ row.starts_at ? formatDate(row.starts_at) : t('dynamic_pricing_page.validity.unlimited') }}
                ~ {{ row.ends_at ? formatDate(row.ends_at) : t('dynamic_pricing_page.validity.unlimited') }}
              </small>
              <small v-else class="text-muted">{{ t('dynamic_pricing_page.validity.long_term') }}</small>
            </template>
          </el-table-column>
          <el-table-column :label="t('dynamic_pricing_page.cols.actions')" width="160" fixed="right">
            <template #default="{ row }">
              <el-button size="small" link @click="editRule(row)">{{ t('actions.edit') }}</el-button>
              <el-button size="small" link @click="viewRuleLogs(row)">{{ t('dynamic_pricing_page.row_actions.logs') }}</el-button>
              <el-popconfirm :title="t('dynamic_pricing_page.rules.delete_confirm')" @confirm="deleteRule(row)">
                <template #reference>
                  <el-button size="small" type="danger" link>{{ t('actions.delete') }}</el-button>
                </template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ──────── 标签2: 阶梯定价 ──────── -->
      <el-tab-pane :label="t('dynamic_pricing_page.tabs.tiers')" name="tiers">
        <div class="tab-toolbar">
          <el-form :inline="true" size="small">
            <el-form-item>
              <el-select v-model="tierPlanId" :placeholder="t('dynamic_pricing_page.tiers.select_plan')" clearable style="width:200px">
                <el-option v-for="p in plans" :key="p.id" :label="p.name" :value="p.id" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :disabled="!tierPlanId" @click="fetchTiers">
                <el-icon><Search /></el-icon> {{ t('dynamic_pricing_page.tiers.query_btn') }}
              </el-button>
              <el-button type="success" :disabled="!tierPlanId" @click="showCreateTierDlg = true">
                <el-icon><Plus /></el-icon> {{ t('dynamic_pricing_page.tiers.add_btn') }}
              </el-button>
            </el-form-item>
          </el-form>
        </div>

        <el-table v-if="tiers.length" :data="tiers" stripe v-loading="tierLoading">
          <el-table-column prop="name" :label="t('dynamic_pricing_page.cols.tier_name')" min-width="150" />
          <el-table-column prop="from_quantity" :label="t('dynamic_pricing_page.cols.from_qty')" width="100" />
          <el-table-column :label="t('dynamic_pricing_page.cols.to_qty')" width="100">
            <template #default="{ row }">{{ row.to_quantity ?? '∞' }}</template>
          </el-table-column>
          <el-table-column prop="unit_price" :label="t('dynamic_pricing_page.cols.unit_price')" width="120">
            <template #default="{ row }">¥{{ row.unit_price }}</template>
          </el-table-column>
          <el-table-column prop="flat_fee" :label="t('dynamic_pricing_page.cols.flat_fee')" width="120">
            <template #default="{ row }">¥{{ row.flat_fee }}</template>
          </el-table-column>
          <el-table-column prop="sort_order" :label="t('dynamic_pricing_page.cols.sort_order')" width="70" />
          <el-table-column :label="t('dynamic_pricing_page.cols.status')" width="80">
            <template #default="{ row }">
              <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? t('dynamic_pricing_page.status.enabled') : t('dynamic_pricing_page.status.disabled') }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('dynamic_pricing_page.cols.actions')" width="120">
            <template #default="{ row }">
              <el-button size="small" link @click="editTier(row)">{{ t('actions.edit') }}</el-button>
              <el-popconfirm :title="t('messages.confirm_delete')" @confirm="deleteTier(row)">
                <template #reference>
                  <el-button size="small" type="danger" link>{{ t('actions.delete') }}</el-button>
                </template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-else-if="!tierLoading && tierPlanId" :description="t('dynamic_pricing_page.tiers.empty')" />

        <!-- 阶梯定价预览 -->
        <el-card v-if="tiers.length > 0" shadow="never" class="mt-3">
          <template #header>
            <span>{{ t('dynamic_pricing_page.tiers.sim_title') }}</span>
          </template>
          <el-form :inline="true" size="small">
            <el-form-item :label="t('dynamic_pricing_page.tiers.purchase_qty')">
              <el-input-number v-model="simQty" :min="1" :max="10000" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="simulateTierPrice">{{ t('dynamic_pricing_page.tiers.simulate_btn') }}</el-button>
            </el-form-item>
          </el-form>
          <div v-if="simResult">
            <el-descriptions :column="2" border size="small">
              <el-descriptions-item :label="t('dynamic_pricing_page.tiers.total_price')">¥{{ simResult.total_price }}</el-descriptions-item>
              <el-descriptions-item :label="t('dynamic_pricing_page.tiers.saving')">¥{{ simResult.saving }}</el-descriptions-item>
              <el-descriptions-item :label="t('dynamic_pricing_page.tiers.effective_unit_price')">¥{{ simResult.unit_price }}</el-descriptions-item>
              <el-descriptions-item :label="t('dynamic_pricing_page.tiers.saving_percent')">{{ simResult.saving_percent }}%</el-descriptions-item>
            </el-descriptions>
          </div>
        </el-card>
      </el-tab-pane>

      <!-- ──────── 标签3: 定价计算器 ──────── -->
      <el-tab-pane :label="t('dynamic_pricing_page.tabs.calculator')" name="calculator">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-card shadow="never">
              <template #header>{{ t('dynamic_pricing_page.calculator.title') }}</template>
              <el-form label-width="100px" size="small">
                <el-form-item :label="t('dynamic_pricing_page.forms.pricing_plan')">
                  <el-select v-model="calcForm.pricing_plan_id" :placeholder="t('dynamic_pricing_page.calculator.select_plan')" style="width:100%">
                    <el-option v-for="p in plans" :key="p.id" :label="p.name" :value="p.id" />
                  </el-select>
                </el-form-item>
                <el-form-item :label="t('dynamic_pricing_page.calculator.billing_period')">
                  <el-select v-model="calcForm.billing_period" style="width:100%">
                    <el-option v-for="bp in billingPeriodOptions" :key="bp.value" :label="bp.label" :value="bp.value" />
                  </el-select>
                </el-form-item>
                <el-form-item :label="t('dynamic_pricing_page.calculator.quantity')">
                  <el-input-number v-model="calcForm.quantity" :min="1" />
                </el-form-item>
                <el-form-item>
                  <el-button type="primary" :loading="calcLoading" @click="calculatePrice">{{ t('dynamic_pricing_page.calculator.calc_btn') }}</el-button>
                </el-form-item>
              </el-form>
            </el-card>
          </el-col>
          <el-col :span="12">
            <el-card shadow="never">
              <template #header>
                {{ t('dynamic_pricing_page.calculator.result_title') }}
                <el-tag v-if="calcResult.total_discount > 0" type="danger" size="small" class="ml-2">
                  {{ t('dynamic_pricing_page.calculator.discount_tag', { percent: calcResult.discount_percent }) }}
                </el-tag>
              </template>
              <template v-if="calcResult.original_price !== undefined">
                <el-descriptions :column="1" border size="small">
                  <el-descriptions-item :label="t('dynamic_pricing_page.calculator.original_price')">¥{{ calcResult.original_price }}</el-descriptions-item>
                  <el-descriptions-item :label="t('dynamic_pricing_page.calculator.final_price')">
                    <strong class="text-success">¥{{ calcResult.final_price }}</strong>
                  </el-descriptions-item>
                  <el-descriptions-item :label="t('dynamic_pricing_page.calculator.discount_amount')">¥{{ calcResult.total_discount }}</el-descriptions-item>
                </el-descriptions>

                <!-- 应用规则明细 -->
                <div v-if="calcResult.breakdown?.length" class="mt-2">
                  <h4>{{ t('dynamic_pricing_page.calculator.breakdown_title') }}</h4>
                  <el-table :data="calcResult.breakdown" size="small" border>
                    <el-table-column prop="rule_name" :label="t('dynamic_pricing_page.cols.rule')" min-width="140" />
                    <el-table-column prop="price_before" :label="t('dynamic_pricing_page.cols.price_before')" width="90">
                      <template #default="{ row }">¥{{ row.price_before }}</template>
                    </el-table-column>
                    <el-table-column prop="price_after" :label="t('dynamic_pricing_page.cols.price_after')" width="90">
                      <template #default="{ row }">¥{{ row.price_after }}</template>
                    </el-table-column>
                    <el-table-column prop="step_discount" :label="t('dynamic_pricing_page.cols.discount')" width="80">
                      <template #default="{ row }"><span class="text-danger">-¥{{ row.step_discount }}</span></template>
                    </el-table-column>
                  </el-table>
                </div>
              </template>
              <div v-else class="text-muted">{{ t('dynamic_pricing_page.calculator.empty_hint') }}</div>
            </el-card>
          </el-col>
        </el-row>
      </el-tab-pane>

      <!-- ──────── 标签4: LLM 定价优化 ──────── -->
      <el-tab-pane :label="t('dynamic_pricing_page.tabs.optimize')" name="optimize">
        <el-row :gutter="20">
          <el-col :span="8">
            <el-card shadow="never">
              <template #header>{{ t('dynamic_pricing_page.optimize.select_plan') }}</template>
              <el-form label-width="80px" size="small">
                <el-form-item :label="t('dynamic_pricing_page.optimize.plan_label')">
                  <el-select v-model="optimizePlanId" :placeholder="t('dynamic_pricing_page.placeholders.select_plan')" style="width:100%">
                    <el-option v-for="p in plans" :key="p.id" :label="p.name" :value="p.id" />
                  </el-select>
                </el-form-item>
                <el-form-item>
                  <el-button type="primary" :loading="optimizeLoading" :disabled="!optimizePlanId" @click="runOptimization">
                    <el-icon><MagicStick /></el-icon> {{ t('dynamic_pricing_page.optimize.analyze_btn') }}
                  </el-button>
                </el-form-item>
              </el-form>
            </el-card>
          </el-col>
          <el-col :span="16">
            <el-card shadow="never" v-if="optimizeResult">
              <template #header>
                <span>{{ t('dynamic_pricing_page.optimize.suggestions_title') }}</span>
                <el-tag v-if="optimizeResult.suggestions?.overall_score" type="warning" size="small" class="ml-2">
                  {{ t('dynamic_pricing_page.optimize.health_score', { score: optimizeResult.suggestions.overall_score }) }}
                </el-tag>
              </template>
              <div v-if="optimizeResult.suggestions">
                <div v-if="optimizeResult.suggestions.price_suggestions" class="mb-2">
                  <h4>{{ t('dynamic_pricing_page.optimize.price_suggestions') }}</h4>
                  <el-descriptions :column="2" border size="small">
                    <el-descriptions-item v-for="(val, key) in optimizeResult.suggestions.price_suggestions" :key="key" :label="key">
                      ¥{{ val }}
                    </el-descriptions-item>
                  </el-descriptions>
                </div>
                <div v-if="optimizeResult.suggestions.tier_suggestions" class="mt-2">
                  <h4>{{ t('dynamic_pricing_page.optimize.tier_suggestions') }}</h4>
                  <pre class="suggestion-pre">{{ formatJson(optimizeResult.suggestions.tier_suggestions) }}</pre>
                </div>
                <div v-if="optimizeResult.suggestions.bundling_opportunities" class="mt-2">
                  <h4>{{ t('dynamic_pricing_page.optimize.bundling') }}</h4>
                  <el-text type="info">{{ optimizeResult.suggestions.bundling_opportunities }}</el-text>
                </div>
                <div v-if="optimizeResult.suggestions.risk_warnings" class="mt-2">
                  <h4>{{ t('dynamic_pricing_page.optimize.risk_warnings') }}</h4>
                  <el-alert :title="optimizeResult.suggestions.risk_warnings" type="warning" :closable="false" show-icon />
                </div>
                <div v-if="optimizeResult.suggestions.seasonal_strategy" class="mt-2">
                  <h4>{{ t('dynamic_pricing_page.optimize.seasonal') }}</h4>
                  <el-text>{{ optimizeResult.suggestions.seasonal_strategy }}</el-text>
                </div>
              </div>
              <div v-else-if="optimizeResult.error" class="text-danger">{{ optimizeResult.error }}</div>
            </el-card>
            <el-card v-else shadow="never">
              <div class="text-muted" style="text-align:center;padding:40px">
                <el-icon :size="48" color="#c0c4cc"><MagicStick /></el-icon>
                <p class="mt-2">{{ t('dynamic_pricing_page.optimize.empty_title') }}</p>
                <p class="text-muted">{{ t('dynamic_pricing_page.optimize.empty_desc') }}</p>
              </div>
            </el-card>
          </el-col>
        </el-row>
      </el-tab-pane>

      <!-- ──────── 标签5: 应用日志 ──────── -->
      <el-tab-pane :label="t('dynamic_pricing_page.tabs.logs')" name="logs">
        <el-table :data="appLogs" v-loading="logLoading" stripe>
          <el-table-column :label="t('dynamic_pricing_page.cols.rule')" min-width="150">
            <template #default="{ row }">{{ row.rule_id }}</template>
          </el-table-column>
          <el-table-column :label="t('dynamic_pricing_page.cols.context')" width="120">
            <template #default="{ row }">{{ row.context_type }}</template>
          </el-table-column>
          <el-table-column :label="t('dynamic_pricing_page.cols.original_price')" width="100">
            <template #default="{ row }">¥{{ row.original_price }}</template>
          </el-table-column>
          <el-table-column :label="t('dynamic_pricing_page.cols.final_price')" width="100">
            <template #default="{ row }">¥{{ row.final_price }}</template>
          </el-table-column>
          <el-table-column :label="t('dynamic_pricing_page.cols.discount_amount')" width="100">
            <template #default="{ row }">-¥{{ row.discount_amount }}</template>
          </el-table-column>
          <el-table-column :label="t('dynamic_pricing_page.cols.applied_at')" width="170">
            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ──────── 标签6: 定价实验 (M3-26) ──────── -->
      <el-tab-pane :label="t('dynamic_pricing_page.tabs.experiments')" name="experiments">
        <div class="tab-toolbar">
          <el-button type="primary" @click="showCreateExperiment = true">
            <el-icon><Plus /></el-icon> {{ t('dynamic_pricing_page.experiments.create_btn') }}
          </el-button>
          <span style="margin-left:12px;color:var(--el-text-color-secondary);font-size:13px">
            {{ t('dynamic_pricing_page.experiments.stats_summary', { running: expStats.running, completed: expStats.completed, participants: expStats.total_participants }) }}
          </span>
        </div>

        <el-table :data="experiments" v-loading="expLoading" stripe>
          <el-table-column prop="name" :label="t('dynamic_pricing_page.cols.exp_name')" min-width="160" />
          <el-table-column prop="experiment_type" :label="t('dynamic_pricing_page.cols.exp_type')" width="100">
            <template #default="{ row }"><el-tag effect="plain" size="small">{{ row.experiment_type }}</el-tag></template>
          </el-table-column>
          <el-table-column prop="traffic_split" :label="t('dynamic_pricing_page.cols.traffic_split')" width="100">
            <template #default="{ row }">{{ row.traffic_split }}%</template>
          </el-table-column>
          <el-table-column :label="t('dynamic_pricing_page.cols.sample_size')" width="90">
            <template #default="{ row }">{{ row.sample_size }}/{{ row.minimum_sample_size }}</template>
          </el-table-column>
          <el-table-column :label="t('dynamic_pricing_page.cols.status')" width="100">
            <template #default="{ row }">
              <el-tag :type="expStatusType(row.status)" effect="plain" size="small">{{ expStatusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('dynamic_pricing_page.cols.exp_result')" min-width="200">
            <template #default="{ row }">
              <span v-if="row.results?.improvement" style="font-size:13px">
                <span :style="{color: (row.results.improvement.conversion_rate || 0) >= 0 ? '#67c23a' : '#f56c6c'}">
                  {{ t('dynamic_pricing_page.experiments.conversion_improvement', { rate: (row.results.improvement.conversion_rate >= 0 ? '+' : '') + row.results.improvement.conversion_rate }) }}
                </span>
                <span v-if="row.results.significance?.significant" style="margin-left:6px">
                  <el-tag effect="plain" size="small" type="success">{{ t('dynamic_pricing_page.experiments.significant') }}</el-tag>
                </span>
              </span>
              <span v-else class="no-data">-</span>
            </template>
          </el-table-column>
          <el-table-column prop="starts_at" :label="t('dynamic_pricing_page.cols.starts_at')" width="160" />
          <el-table-column :label="t('dynamic_pricing_page.cols.actions')" width="200" fixed="right">
            <template #default="{ row }">
              <el-button text size="small" type="primary" @click="viewExperiment(row)">{{ t('dynamic_pricing_page.row_actions.detail') }}</el-button>
              <el-button v-if="row.status === 'draft' || row.status === 'paused'" text size="small" type="success" @click="handleStartExp(row)">{{ t('dynamic_pricing_page.row_actions.start') }}</el-button>
              <el-button v-if="row.status === 'running'" text size="small" type="warning" @click="handlePauseExp(row)">{{ t('dynamic_pricing_page.row_actions.pause') }}</el-button>
              <el-button v-if="row.status === 'running' || row.status === 'paused'" text size="small" type="primary" @click="handleCompleteExp(row)">{{ t('dynamic_pricing_page.row_actions.complete') }}</el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="pagination-wrap">
          <el-pagination v-model:current-page="expPage" :page-size="20" :total="expTotal" layout="prev, pager, next" background small @current-change="fetchExperiments" />
        </div>
      </el-tab-pane>
    </el-tabs>

    <!-- ───── 新建/编辑规则 对话框 ───── -->
    <el-dialog v-model="showCreateRuleDlg" :title="editRuleId ? t('dynamic_pricing_page.dialogs.edit_rule') : t('dynamic_pricing_page.dialogs.create_rule')" width="700px">
      <el-form :model="ruleForm" :rules="ruleRules" ref="ruleFormRef" label-width="100px" size="small">
        <el-row :gutter="16">
          <el-col :span="16">
            <el-form-item :label="t('dynamic_pricing_page.forms.rule_name')" prop="name">
              <el-input v-model="ruleForm.name" maxlength="200" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item :label="t('dynamic_pricing_page.forms.priority')" prop="priority">
              <el-input-number v-model="ruleForm.priority" :min="0" :max="9999" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item :label="t('dynamic_pricing_page.forms.description')">
          <el-input v-model="ruleForm.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('dynamic_pricing_page.forms.rule_type')" prop="rule_type">
              <el-select v-model="ruleForm.rule_type" style="width:100%">
                <el-option v-for="rt in localizedRuleTypes" :key="rt.value" :label="rt.label" :value="rt.value" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('dynamic_pricing_page.forms.target_type')" prop="target_type">
              <el-select v-model="ruleForm.target_type" style="width:100%">
                <el-option v-for="tt in localizedTargetTypes" :key="tt.value" :label="tt.label" :value="tt.value" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item :label="t('dynamic_pricing_page.forms.adjustment_type')" prop="adjustment_type">
              <el-select v-model="ruleForm.adjustment_type" style="width:100%">
                <el-option v-for="at in localizedAdjustmentTypes" :key="at.value" :label="at.label" :value="at.value" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item :label="t('dynamic_pricing_page.forms.adjustment_value')" prop="adjustment_value">
              <el-input-number v-model="ruleForm.adjustment_value" :precision="2" :step="5" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item :label="t('dynamic_pricing_page.forms.stack_mode')" prop="stack_mode">
              <el-select v-model="ruleForm.stack_mode" style="width:100%">
                <el-option v-for="sm in localizedStackModes" :key="sm.value" :label="sm.label" :value="sm.value" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('dynamic_pricing_page.forms.min_price')">
              <el-input-number v-model="ruleForm.min_price" :min="0" :precision="2" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('dynamic_pricing_page.forms.max_price')">
              <el-input-number v-model="ruleForm.max_price" :min="0" :precision="2" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('dynamic_pricing_page.forms.starts_at')">
              <el-date-picker v-model="ruleForm.starts_at" type="datetime" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('dynamic_pricing_page.forms.ends_at')">
              <el-date-picker v-model="ruleForm.ends_at" type="datetime" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="showCreateRuleDlg = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="savingRule" @click="submitRule">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- ───── 新建阶梯定价 对话框 ───── -->
    <el-dialog v-model="showCreateTierDlg" :title="editTierId ? t('dynamic_pricing_page.dialogs.edit_tier') : t('dynamic_pricing_page.dialogs.create_tier')" width="500px">
      <el-form :model="tierForm" label-width="100px" size="small">
        <el-form-item :label="t('dynamic_pricing_page.forms.tier_name')" prop="name">
          <el-input v-model="tierForm.name" :placeholder="t('dynamic_pricing_page.tiers.name_ph')" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('dynamic_pricing_page.forms.from_qty')">
              <el-input-number v-model="tierForm.from_quantity" :min="1" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('dynamic_pricing_page.forms.to_qty')">
              <el-input-number v-model="tierForm.to_quantity" :min="0" :controls="false" style="width:100%" :placeholder="t('dynamic_pricing_page.tiers.to_qty_ph')" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('dynamic_pricing_page.forms.unit_price')">
              <el-input-number v-model="tierForm.unit_price" :precision="2" :min="0" :step="10" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('dynamic_pricing_page.forms.flat_fee')">
              <el-input-number v-model="tierForm.flat_fee" :precision="2" :min="0" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="showCreateTierDlg = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="savingTier" @click="submitTier">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- ─── 新建实验 对话框 ─── -->
    <el-dialog v-model="showCreateExperiment" :title="t('dynamic_pricing_page.dialogs.create_experiment')" width="600px">
      <el-form :model="expForm" ref="expFormRef" label-width="140px" size="small">
        <el-form-item :label="t('dynamic_pricing_page.forms.exp_name')" prop="name">
          <el-input v-model="expForm.name" maxlength="200" />
        </el-form-item>
        <el-form-item :label="t('dynamic_pricing_page.forms.description')">
          <el-input v-model="expForm.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('dynamic_pricing_page.forms.exp_type')">
              <el-select v-model="expForm.experiment_type" style="width:100%">
                <el-option v-for="et in experimentTypeOptions" :key="et.value" :label="et.label" :value="et.value" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('dynamic_pricing_page.forms.target_metric')">
              <el-select v-model="expForm.target_metric" style="width:100%">
                <el-option v-for="tm in targetMetricOptions" :key="tm.value" :label="tm.label" :value="tm.value" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('dynamic_pricing_page.forms.traffic_split')">
              <el-input-number v-model="expForm.traffic_split" :min="1" :max="99" style="width:100%" /> %
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('dynamic_pricing_page.forms.min_sample')">
              <el-input-number v-model="expForm.minimum_sample_size" :min="10" :step="50" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item :label="t('dynamic_pricing_page.forms.control_config')">
          <el-input v-model="expForm.controlConfigStr" type="textarea" :rows="2" :placeholder="t('dynamic_pricing_page.placeholders.control_config')" />
        </el-form-item>
        <el-form-item :label="t('dynamic_pricing_page.forms.treatment_config')">
          <el-input v-model="expForm.treatmentConfigStr" type="textarea" :rows="2" :placeholder="t('dynamic_pricing_page.placeholders.treatment_config')" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('dynamic_pricing_page.forms.starts_at')">
              <el-date-picker v-model="expForm.starts_at" type="datetime" :placeholder="t('dynamic_pricing_page.placeholders.start_now')" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('dynamic_pricing_page.forms.ends_at')">
              <el-date-picker v-model="expForm.ends_at" type="datetime" :placeholder="t('dynamic_pricing_page.placeholders.no_end_limit')" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="showCreateExperiment = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="savingExp" @click="submitExperiment">{{ t('dynamic_pricing_page.experiments.save_draft') }}</el-button>
      </template>
    </el-dialog>

    <!-- ─── 实验详情 对话框 ─── -->
    <el-dialog v-model="showExpDetail" :title="expDetail?.name || t('dynamic_pricing_page.experiments.detail_fallback')" width="700px">
      <el-descriptions v-if="expDetail" :column="2" border>
        <el-descriptions-item :label="t('dynamic_pricing_page.cols.status')" :span="2">
          <el-tag :type="expStatusType(expDetail.status)" effect="plain">{{ expStatusLabel(expDetail.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('dynamic_pricing_page.cols.exp_type')">{{ expDetail.experiment_type }}</el-descriptions-item>
        <el-descriptions-item :label="t('dynamic_pricing_page.forms.metric')">{{ expDetail.target_metric }}</el-descriptions-item>
        <el-descriptions-item :label="t('dynamic_pricing_page.cols.traffic_split')">{{ t('dynamic_pricing_page.experiments.traffic_treatment', { split: expDetail.traffic_split }) }}</el-descriptions-item>
        <el-descriptions-item :label="t('dynamic_pricing_page.cols.sample_size')">{{ expDetail.sample_size }}/{{ expDetail.minimum_sample_size }}</el-descriptions-item>
        <el-descriptions-item :label="t('dynamic_pricing_page.forms.start')">{{ expDetail.starts_at || '-' }}</el-descriptions-item>
        <el-descriptions-item :label="t('dynamic_pricing_page.forms.end')">{{ expDetail.ends_at || '-' }}</el-descriptions-item>
      </el-descriptions>

      <h4 style="margin-top:16px">{{ t('dynamic_pricing_page.experiments.results_title') }}</h4>
      <el-table v-if="expDetail?.results" :data="resultRows(expDetail.results)" stripe>
        <el-table-column prop="metric" :label="t('dynamic_pricing_page.cols.metric')" width="120" />
        <el-table-column prop="control" :label="t('dynamic_pricing_page.cols.control')" width="140" />
        <el-table-column prop="treatment" :label="t('dynamic_pricing_page.cols.treatment')" width="140" />
        <el-table-column prop="improvement" :label="t('dynamic_pricing_page.cols.improvement')" width="120">
          <template #default="{ row }">
            <span :style="{color: (row.improvementVal || 0) >= 0 ? '#67c23a' : '#f56c6c'}">
              {{ row.improvementVal >= 0 ? '+' : '' }}{{ row.improvementVal }}
            </span>
          </template>
        </el-table-column>
      </el-table>
      <div v-else style="padding:20px 0;text-align:center;color:var(--el-text-color-placeholder)">{{ t('dynamic_pricing_page.experiments.no_results') }}</div>

      <div v-if="expDetail?.results?.significance" style="margin-top:12px">
        <el-tag :type="expDetail.results.significance.significant ? 'success' : 'info'" effect="plain">
          {{ expDetail.results.significance.significant ? t('dynamic_pricing_page.experiments.stat_significant') : t('dynamic_pricing_page.experiments.stat_not_significant') }}
        </el-tag>
        <span style="margin-left:8px;font-size:12px;color:var(--el-text-color-secondary)">
          Z={{ expDetail.results.significance.z_score }}, p={{ expDetail.results.significance.p_value }}
        </span>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Plus, Search, Refresh, MagicStick } from '@element-plus/icons-vue';
import dynamicPricingApi from '../../api/dynamicPricing';
import billingApi from '../../api/billing';

export default {
  name: 'DynamicPricing',
  components: { Plus, Search, Refresh, MagicStick },
  setup() {
    const { t, locale } = useI18n();

    const billingPeriodKeys = ['monthly', 'quarterly', 'semi_annually', 'yearly'];
    const experimentTypeKeys = ['pricing', 'discount', 'bundle', 'tier', 'promotion'];
    const targetMetricKeys = ['conversion', 'revenue', 'retention', 'profit'];
    const resultMetricKeys = ['conversion_rate', 'avg_revenue', 'churn_rate'];

    const billingPeriodOptions = computed(() =>
      billingPeriodKeys.map((value) => ({
        value,
        label: t(`dynamic_pricing_page.billing_periods.${value}`),
      }))
    );

    const experimentTypeOptions = computed(() =>
      experimentTypeKeys.map((value) => ({
        value,
        label: t(`dynamic_pricing_page.experiment_types.${value}`),
      }))
    );

    const targetMetricOptions = computed(() =>
      targetMetricKeys.map((value) => ({
        value,
        label: t(`dynamic_pricing_page.target_metrics.${value}`),
      }))
    );

    function metaLabel(category, value, fallback) {
      const key = `dynamic_pricing_page.meta.${category}.${value}`;
      const label = t(key);
      return label !== key ? label : fallback;
    }

    function localizeMetaList(list, category) {
      return (list || []).map((item) => ({
        ...item,
        label: metaLabel(category, item.value, item.label),
      }));
    }

    const ruleRules = computed(() => ({
      name: [{ required: true, message: t('dynamic_pricing_page.validation.name_required') }],
      rule_type: [{ required: true, message: t('dynamic_pricing_page.validation.rule_type_required') }],
      target_type: [{ required: true, message: t('dynamic_pricing_page.validation.target_type_required') }],
      adjustment_type: [{ required: true, message: t('dynamic_pricing_page.validation.adjustment_type_required') }],
    }));

    const dateLocale = computed(() => (locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'));
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

    const localizedRuleTypes = computed(() => localizeMetaList(metadata.rule_types, 'rule_types'));
    const localizedTargetTypes = computed(() => localizeMetaList(metadata.target_types, 'target_types'));
    const localizedAdjustmentTypes = computed(() => localizeMetaList(metadata.adjustment_types, 'adjustment_types'));
    const localizedStackModes = computed(() => localizeMetaList(metadata.stack_modes, 'stack_modes'));

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
      } catch (e) { ElMessage.error(t('dynamic_pricing_page.messages.fetch_rules_failed')); }
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
          ElMessage.success(editRuleId.value ? t('dynamic_pricing_page.messages.rule_updated') : t('dynamic_pricing_page.messages.rule_created'));
          showCreateRuleDlg.value = false;
          resetRuleForm();
          fetchRules();
        }
      } catch (e) { ElMessage.error(e.response?.data?.message || t('messages.failed')); }
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
      } catch (e) { ElMessage.error(t('messages.failed')); }
    }

    async function deleteRule(row) {
      try {
        await dynamicPricingApi.deleteRule(row.id);
        ElMessage.success(t('dynamic_pricing_page.messages.rule_deleted'));
        fetchRules();
      } catch (e) { ElMessage.error(t('dynamic_pricing_page.messages.delete_failed')); }
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
      } catch (e) { ElMessage.error(t('dynamic_pricing_page.messages.fetch_tiers_failed')); }
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
          ElMessage.success(editTierId.value ? t('dynamic_pricing_page.messages.tier_updated') : t('dynamic_pricing_page.messages.tier_created'));
          showCreateTierDlg.value = false;
          editTierId.value = null;
          resetTierForm();
          fetchTiers();
        }
      } catch (e) { ElMessage.error(e.response?.data?.message || t('messages.failed')); }
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
        ElMessage.success(t('dynamic_pricing_page.messages.tier_deleted'));
        fetchTiers();
      } catch (e) { ElMessage.error(t('dynamic_pricing_page.messages.delete_failed')); }
    }

    async function simulateTierPrice() {
      try {
        const { data } = await dynamicPricingApi.calculatePrice({
          pricing_plan_id: tierPlanId.value,
          billing_period: 'monthly',
          quantity: simQty.value,
        });
        if (data.success) simResult.value = data.data;
      } catch (e) { ElMessage.error(t('dynamic_pricing_page.messages.simulate_failed')); }
    }

    // ─── 计算器 ───
    async function calculatePrice() {
      calcLoading.value = true;
      try {
        const { data } = await dynamicPricingApi.calculatePrice(calcForm);
        if (data.success) Object.assign(calcResult, data.data);
      } catch (e) { ElMessage.error(t('dynamic_pricing_page.messages.calc_failed')); }
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
      } catch (e) { ElMessage.error(t('dynamic_pricing_page.messages.optimize_failed')); }
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
      return item ? metaLabel('rule_types', type, item.label) : type;
    }

    function targetLabel(type) {
      const item = metadata.target_types.find(t => t.value === type);
      return item ? metaLabel('target_types', type, item.label) : type;
    }

    function formatTime(val) {
      if (!val) return '';
      return new Date(val).toLocaleString(dateLocale.value, { hour12: false });
    }

    function formatDate(d) {
      if (!d) return '';
      return new Date(d).toLocaleDateString(dateLocale.value);
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
      const key = `dynamic_pricing_page.exp_status.${s}`;
      const label = t(key);
      return label !== key ? label : s;
    }
    function resultRows(results) {
      if (!results?.control || !results?.treatment) return [];
      const c = results.control, tr = results.treatment, i = results.improvement || {};
      return resultMetricKeys.map((metric) => ({
        metric: t(`dynamic_pricing_page.result_metrics.${metric}`),
        control: metric === 'avg_revenue' ? `¥${c[metric]}` : `${c[metric]}%`,
        treatment: metric === 'avg_revenue' ? `¥${tr[metric]}` : `${tr[metric]}%`,
        improvementVal: metric === 'avg_revenue' ? `¥${i[metric]}` : i[metric],
      }));
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
          ElMessage.success(t('dynamic_pricing_page.messages.exp_created'));
          showCreateExperiment.value = false;
          expForm.value = { name: '', description: '', experiment_type: 'pricing', target_metric: 'conversion', traffic_split: 50, minimum_sample_size: 100, controlConfigStr: '', treatmentConfigStr: '', starts_at: null, ends_at: null };
          fetchExperiments();
          fetchExpStats();
        }
      } catch (e) {
        ElMessage.error(e.response?.data?.message || t('dynamic_pricing_page.messages.create_failed'));
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
        if (data.success) { ElMessage.success(t('dynamic_pricing_page.messages.exp_started')); fetchExperiments(); fetchExpStats(); }
      } catch (e) { ElMessage.error(e.response?.data?.message || t('dynamic_pricing_page.messages.start_failed')); }
    }
    async function handlePauseExp(row) {
      try {
        const { data } = await dynamicPricingApi.pauseExperiment(row.id);
        if (data.success) { ElMessage.success(t('dynamic_pricing_page.messages.exp_paused')); fetchExperiments(); }
      } catch (e) { ElMessage.error(e.response?.data?.message || t('dynamic_pricing_page.messages.pause_failed')); }
    }
    async function handleCompleteExp(row) {
      try {
        const { data } = await dynamicPricingApi.completeExperiment(row.id);
        if (data.success) { ElMessage.success(t('dynamic_pricing_page.messages.exp_completed')); fetchExperiments(); fetchExpStats(); }
      } catch (e) { ElMessage.error(e.response?.data?.message || t('dynamic_pricing_page.messages.complete_failed')); }
    }

    return {
      t,
      activeTab, loading, tierLoading, calcLoading, optimizeLoading, logLoading,
      savingRule, savingTier, stats, metadata, plans,
      localizedRuleTypes, localizedTargetTypes, localizedAdjustmentTypes, localizedStackModes,
      billingPeriodOptions, experimentTypeOptions, targetMetricOptions,
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
