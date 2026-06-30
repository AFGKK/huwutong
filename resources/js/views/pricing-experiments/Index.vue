<template>
    <div class="pricing-experiments-page">
        <div class="page-header">
            <h2>价格实验 / A/B 定价系统</h2>
            <p class="text-muted">对不同区域/渠道展示不同价格，通过数据驱动定价优化</p>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
                <el-button @click="showRecsPanel" :loading="loadingRecs">
                    <el-icon><DataAnalysis /></el-icon> 优化建议
                </el-button>
                <el-button type="primary" @click="showCreateDialog = true">
                    <el-icon><Plus /></el-icon> 新建实验
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-label">总实验</div>
                    <div class="stat-value">{{ stats.total }}</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover" class="stat-success">
                    <div class="stat-label">运行中</div>
                    <div class="stat-value">{{ stats.running }}</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover" class="stat-info">
                    <div class="stat-label">草稿</div>
                    <div class="stat-value">{{ stats.draft }}</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover" class="stat-warning">
                    <div class="stat-label">已暂停</div>
                    <div class="stat-value">{{ stats.paused || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover">
                    <div class="stat-label">已完成</div>
                    <div class="stat-value">{{ stats.completed }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 过滤器 -->
        <el-card class="mb-4">
            <el-form :model="filters" inline size="small">
                <el-form-item label="状态">
                    <el-select v-model="filters.status" placeholder="全部" clearable style="width:140px">
                        <el-option label="草稿" value="draft" />
                        <el-option label="运行中" value="running" />
                        <el-option label="已暂停" value="paused" />
                        <el-option label="已完成" value="completed" />
                        <el-option label="已取消" value="cancelled" />
                    </el-select>
                </el-form-item>
                <el-form-item label="类型">
                    <el-select v-model="filters.experiment_type" placeholder="全部" clearable style="width:140px">
                        <el-option label="定价" value="pricing" />
                        <el-option label="折扣" value="discount" />
                        <el-option label="捆绑" value="bundle" />
                        <el-option label="阶梯" value="tier" />
                        <el-option label="促销" value="promotion" />
                    </el-select>
                </el-form-item>
                <el-form-item label="搜索">
                    <el-input v-model="filters.search" placeholder="名称" style="width:200px" clearable />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList">搜索</el-button>
                    <el-button @click="resetFilters">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 实验列表 -->
        <el-card>
            <el-table :data="experiments" stripe v-loading="loading">
                <el-table-column label="名称" min-width="200">
                    <template #default="{ row }">
                        <div class="exp-name">{{ row.name }}</div>
                        <small class="text-muted">{{ row.slug }}</small>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="类型" width="80">
                    <template #default="{ row }">
                        <el-tag effect="plain" size="small">{{ row.experiment_type }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="流量分配" width="80" align="center">
                    <template #default="{ row }">{{ row.traffic_split ?? 50 }}%</template>
                </el-table-column>
                <el-table-column label="参与者" width="80" align="center">
                    <template #default="{ row }">{{ row.participants_count || 0 }}</template>
                </el-table-column>
                <el-table-column label="目标指标" width="100" prop="target_metric" />
                <el-table-column label="显著" width="70" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.results?.significant" type="success" size="small">是</el-tag>
                        <el-tag v-else type="info" size="small">否</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="创建人" width="100" prop="creator?.name" />
                <el-table-column label="时间" width="160">
                    <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="260" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewDetail(row)">详情</el-button>
                        <el-button v-if="row.status === 'draft'" size="small" type="success" @click="startExp(row)">启动</el-button>
                        <el-button v-if="row.status === 'running'" size="small" type="warning" @click="pauseExp(row)">暂停</el-button>
                        <el-button v-if="row.status === 'paused'" size="small" type="success" @click="startExp(row)">恢复</el-button>
                        <el-button v-if="row.status === 'running'" size="small" type="danger" @click="completeExp(row)">结束</el-button>
                        <el-button v-if="row.status === 'completed'" size="small" @click="calculateExp(row)">重算</el-button>
                        <el-button v-if="row.status === 'draft' || row.status === 'cancelled'"
                            size="small" type="danger" @click="deleteExp(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrapper">
                <el-pagination v-if="pagination.total > 0"
                    :current-page="pagination.current_page" :total="pagination.total"
                    :page-size="pagination.per_page" layout="total, prev, pager, next"
                    @current-change="onPageChange" />
            </div>
        </el-card>

        <!-- 新建实验弹窗 -->
        <el-dialog v-model="showCreateDialog" title="新建定价实验" width="700px">
            <el-form :model="createForm" label-position="top">
                <el-row :gutter="16">
                    <el-col :span="16">
                        <el-form-item label="实验名称" :rules="[{ required: true }]">
                            <el-input v-model="createForm.name" placeholder="例如：华东区月付价格测试" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="类型">
                            <el-select v-model="createForm.experiment_type" style="width:100%">
                                <el-option label="定价" value="pricing" />
                                <el-option label="折扣" value="discount" />
                                <el-option label="捆绑" value="bundle" />
                                <el-option label="阶梯" value="tier" />
                                <el-option label="促销" value="promotion" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="描述">
                    <el-input v-model="createForm.description" type="textarea" :rows="2" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="目标指标">
                            <el-select v-model="createForm.target_metric" style="width:100%">
                                <el-option label="转化率" value="conversion" />
                                <el-option label="收入" value="revenue" />
                                <el-option label="留存" value="retention" />
                                <el-option label="利润" value="profit" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="流量分配(%)">
                            <el-input-number v-model="createForm.traffic_split" :min="1" :max="99" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="最小样本量">
                            <el-input-number v-model="createForm.minimum_sample_size" :min="10" :step="100" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <!-- 客户分群筛选 (M3-26 增强) -->
                <el-divider>客户分群筛选（可选 — 仅对匹配客户生效）</el-divider>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="地理区域">
                            <el-select v-model="createForm.segment_filters.region" multiple clearable placeholder="全部区域" style="width:100%">
                                <el-option label="中国大陆" value="china" />
                                <el-option label="港澳台" value="hk_mo_tw" />
                                <el-option label="北美" value="north_america" />
                                <el-option label="欧洲" value="europe" />
                                <el-option label="东南亚" value="southeast_asia" />
                                <el-option label="日韩" value="jp_kr" />
                                <el-option label="其他" value="other" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="渠道来源">
                            <el-select v-model="createForm.segment_filters.channel" multiple clearable placeholder="全部渠道" style="width:100%">
                                <el-option label="官网直访" value="direct" />
                                <el-option label="SEO" value="seo" />
                                <el-option label="SEM" value="sem" />
                                <el-option label="社交媒体" value="social" />
                                <el-option label="邮件营销" value="email" />
                                <el-option label="代理商" value="agent" />
                                <el-option label="联盟推广" value="affiliate" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="客户等级">
                            <el-select v-model="createForm.segment_filters.customer_tier" multiple clearable placeholder="全等级" style="width:100%">
                                <el-option label="免费" value="free" />
                                <el-option label="基础" value="basic" />
                                <el-option label="专业" value="pro" />
                                <el-option label="企业" value="enterprise" />
                                <el-option label="VIP" value="vip" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="行业">
                            <el-select v-model="createForm.segment_filters.industry" multiple clearable placeholder="全部行业" style="width:100%">
                                <el-option label="科技/SaaS" value="tech" />
                                <el-option label="金融" value="finance" />
                                <el-option label="教育" value="education" />
                                <el-option label="医疗" value="healthcare" />
                                <el-option label="制造业" value="manufacturing" />
                                <el-option label="游戏" value="gaming" />
                                <el-option label="电商" value="ecommerce" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="新客vs老客">
                            <el-select v-model="createForm.segment_filters.new_vs_returning" multiple clearable placeholder="全部" style="width:100%">
                                <el-option label="新客(30天内)" value="new" />
                                <el-option label="老客" value="returning" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="设备类型">
                            <el-select v-model="createForm.segment_filters.device_type" multiple clearable placeholder="全部" style="width:100%">
                                <el-option label="Windows" value="windows" />
                                <el-option label="macOS" value="macos" />
                                <el-option label="Linux" value="linux" />
                                <el-option label="iOS" value="ios" />
                                <el-option label="Android" value="android" />
                                <el-option label="Web" value="web" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-divider>实验组配置</el-divider>
                <el-form-item label="对照组配置 (JSON)">
                    <el-input v-model="createForm.controlConfigStr" type="textarea" :rows="2"
                        placeholder='{"price_monthly": 99, "price_yearly": 990}' />
                </el-form-item>
                <el-form-item label="实验组配置 (JSON)">
                    <el-input v-model="createForm.treatmentConfigStr" type="textarea" :rows="2"
                        placeholder='{"adjustment_type": "percentage", "adjustment_value": -10}' />
                </el-form-item>
                <el-divider>时间范围</el-divider>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="开始时间">
                            <el-date-picker v-model="createForm.starts_at" type="datetime" placeholder="立即" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="结束时间">
                            <el-date-picker v-model="createForm.ends_at" type="datetime" placeholder="无期限" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="showCreateDialog = false">取消</el-button>
                <el-button type="primary" @click="doCreate" :loading="creating">保存草稿</el-button>
            </template>
        </el-dialog>

        <!-- 详情弹窗 -->
        <el-dialog v-model="showDetailDialog" title="实验详情" width="700px">
            <template v-if="detailData">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="名称" :span="2">{{ detailData.name }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="statusType(detailData.status)">{{ statusLabel(detailData.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="类型">{{ detailData.experiment_type }}</el-descriptions-item>
                    <el-descriptions-item label="目标指标">{{ detailData.target_metric }}</el-descriptions-item>
                    <el-descriptions-item label="流量分配">{{ detailData.traffic_split }}%</el-descriptions-item>
                    <el-descriptions-item label="最小样本量">{{ detailData.minimum_sample_size }}</el-descriptions-item>
                    <el-descriptions-item label="当前样本量">{{ detailData.sample_size || 0 }}</el-descriptions-item>
                    <el-descriptions-item label="开始时间">{{ formatTime(detailData.starts_at) || '立即' }}</el-descriptions-item>
                    <el-descriptions-item label="结束时间">{{ formatTime(detailData.ends_at) || '无期限' }}</el-descriptions-item>
                    <el-descriptions-item label="创建人">{{ detailData.creator?.name }}</el-descriptions-item>
                    <el-descriptions-item label="置信水平">{{ detailData.confidence_level || 95 }}%</el-descriptions-item>
                </el-descriptions>

                <template v-if="detailData.results">
                    <el-divider />
                    <div class="d-flex justify-between align-center mb-2">
                        <h4>实验结果</h4>
                        <div v-if="detailData.status === 'completed'" class="header-actions">
                            <el-button size="small" type="primary" @click="applyWinner(detailData)">
                                <el-icon><Check /></el-icon> 应用优胜方案
                            </el-button>
                            <el-button size="small" @click="calculateExp(detailData)">重算结果</el-button>
                        </div>
                    </div>

                    <!-- 结果核心指标卡 -->
                    <el-row :gutter="12" class="mb-3">
                        <el-col :span="6">
                            <el-card shadow="never" class="result-card control-card">
                                <div class="result-label">对照组</div>
                                <div class="result-value">{{ detailData.results.control?.count ?? 0 }}</div>
                                <div class="result-sub">参与者</div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never" class="result-card treatment-card">
                                <div class="result-label">实验组</div>
                                <div class="result-value">{{ detailData.results.treatment?.count ?? 0 }}</div>
                                <div class="result-sub">参与者</div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never" class="result-card"
                                :class="(detailData.results.improvement?.conversion_rate ?? 0) >= 0 ? 'result-positive' : 'result-negative'">
                                <div class="result-label">转化率变化</div>
                                <div class="result-value">
                                    {{ formatPercent(detailData.results.improvement?.conversion_rate) }}
                                </div>
                                <div class="result-sub">实验组 vs 对照组</div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never" class="result-card"
                                :class="(detailData.results.significance?.significant) ? 'result-positive' : ''">
                                <div class="result-label">统计显著</div>
                                <div class="result-value">
                                    <el-tag :type="detailData.results.significance?.significant ? 'success' : 'info'" size="large" effect="dark">
                                        {{ detailData.results.significance?.significant ? '✅ 显著' : '❌ 不显著' }}
                                    </el-tag>
                                </div>
                                <div class="result-sub">P={{ detailData.results.significance?.p_value ?? '-' }}</div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <!-- 对比详情 -->
                    <el-descriptions :column="4" border size="small">
                        <el-descriptions-item label="对照组转化数">{{ detailData.results.control?.converted ?? 0 }}</el-descriptions-item>
                        <el-descriptions-item label="实验组转化数">{{ detailData.results.treatment?.converted ?? 0 }}</el-descriptions-item>
                        <el-descriptions-item label="对照组转化率">{{ detailData.results.control?.conversion_rate ?? 0 }}%</el-descriptions-item>
                        <el-descriptions-item label="实验组转化率">{{ detailData.results.treatment?.conversion_rate ?? 0 }}%</el-descriptions-item>
                    </el-descriptions>
                    <el-descriptions :column="4" border size="small" class="mt-2">
                        <el-descriptions-item label="对照组平均收入">{{ formatMoney(detailData.results.control?.avg_revenue) }}</el-descriptions-item>
                        <el-descriptions-item label="实验组平均收入">{{ formatMoney(detailData.results.treatment?.avg_revenue) }}</el-descriptions-item>
                        <el-descriptions-item label="对照组流失率">{{ detailData.results.control?.churn_rate ?? 0 }}%</el-descriptions-item>
                        <el-descriptions-item label="实验组流失率">{{ detailData.results.treatment?.churn_rate ?? 0 }}%</el-descriptions-item>
                    </el-descriptions>
                    <el-descriptions :column="3" border size="small" class="mt-2">
                        <el-descriptions-item label="Z值">{{ detailData.results.significance?.z_score ?? '-' }}</el-descriptions-item>
                        <el-descriptions-item label="P值">{{ detailData.results.significance?.p_value ?? '-' }}</el-descriptions-item>
                        <el-descriptions-item label="置信水平">{{ detailData.confidence_level || 95 }}%</el-descriptions-item>
                    </el-descriptions>

                    <!-- 优胜方案推荐 -->
                    <template v-if="detailData.metadata?.winning_recommendation">
                        <el-divider />
                        <el-alert :title="'🎯 优胜方案已生成'" :description="detailData.metadata.winning_recommendation.reason"
                            type="success" show-icon :closable="false" class="mb-2" />
                        <div v-if="detailData.metadata.winning_applied_at" class="text-success">
                            ✅ 已于 {{ formatTime(detailData.metadata.winning_applied_at) }} 应用
                        </div>
                        <el-descriptions :column="1" border size="small" class="mt-2">
                            <el-descriptions-item label="推荐配置">
                                <pre class="json-block">{{ JSON.stringify(detailData.metadata.winning_recommendation.winning_config, null, 2) }}</pre>
                            </el-descriptions-item>
                            <el-descriptions-item label="推荐理由">{{ detailData.metadata.winning_recommendation.reason }}</el-descriptions-item>
                        </el-descriptions>
                    </template>
                </template>

                <template v-if="detailData.control_config || detailData.treatment_config">
                    <el-divider />
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <h4>对照组配置</h4>
                            <pre class="json-block">{{ JSON.stringify(detailData.control_config, null, 2) }}</pre>
                        </el-col>
                        <el-col :span="12">
                            <h4>实验组配置</h4>
                            <pre class="json-block">{{ JSON.stringify(detailData.treatment_config, null, 2) }}</pre>
                        </el-col>
                    </el-row>
                </template>

                <template v-if="detailData.participants?.length">
                    <el-divider />
                    <h4>参与者 ({{ detailData.participants.length }})</h4>
                    <el-table :data="detailData.participants" size="small" max-height="200">
                        <el-table-column prop="customer_id" label="客户ID" width="80" />
                        <el-table-column prop="group" label="分组" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.group === 'treatment' ? 'warning' : 'info'" size="small">
                                    {{ row.group === 'treatment' ? '实验组' : '对照组' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="original_price" label="原价" width="100" />
                        <el-table-column prop="experiment_price" label="实验价" width="100" />
                        <el-table-column prop="revenue_impact" label="收入影响" width="100" />
                        <el-table-column prop="assigned_at" label="分配时间" width="160">
                            <template #default="{ row }">{{ formatTime(row.assigned_at) }}</template>
                        </el-table-column>
                    </el-table>
                </template>
            </template>
        </el-dialog>

        <!-- 优化建议弹窗 (M3-26) -->
        <el-dialog v-model="showRecommendations" title="📊 数据驱动定价优化建议" width="800px">
            <template v-if="recommendations.length === 0">
                <el-empty description="暂无可用的优化建议，请先完成一些定价实验" />
            </template>
            <template v-else>
                <el-alert type="info" :closable="false" class="mb-2">
                    <template #title>
                        基于 {{ recommendations.length }} 个已完成实验的分析
                        （{{ recommendations.filter(r => r.is_significant).length }} 个具有统计显著性）
                    </template>
                </el-alert>
                <el-table :data="recommendations" stripe size="small">
                    <el-table-column label="实验名称" min-width="160">
                        <template #default="{ row }">
                            <div class="exp-name">{{ row.experiment_name }}</div>
                            <small class="text-muted">{{ row.experiment_type }}</small>
                        </template>
                    </el-table-column>
                    <el-table-column label="显著性" width="90" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.is_significant ? 'success' : 'info'" size="small">
                                {{ row.is_significant ? '显著' : '不显著' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="转化率变化" width="130" align="center">
                        <template #default="{ row }">
                            <span :class="row.improvement_rate > 0 ? 'text-success' : (row.improvement_rate < 0 ? 'text-danger' : '')">
                                {{ formatPercent(row.improvement_rate) }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column label="收入影响" width="130" align="center">
                        <template #default="{ row }">
                            <span :class="row.revenue_impact > 0 ? 'text-success' : (row.revenue_impact < 0 ? 'text-danger' : '')">
                                {{ formatMoney(row.revenue_impact) }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column label="优先级" width="80" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.priority === 'high' ? 'danger' : (row.priority === 'medium' ? 'warning' : 'info')" size="small">
                                {{ { high: '高', medium: '中', low: '低', need_more_data: '待定' }[row.priority] || row.priority }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作建议" min-width="220">
                        <template #default="{ row }">
                            <small>{{ row.suggestion }}</small>
                        </template>
                    </el-table-column>
                </el-table>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Plus, DataAnalysis, Check } from '@element-plus/icons-vue';
import dynamicPricingApi from '@/api/dynamicPricing';

const loading = ref(false);
const creating = ref(false);
const showCreateDialog = ref(false);
const showDetailDialog = ref(false);

const experiments = ref([]);
const detailData = ref(null);

const stats = reactive({
    total: 0, running: 0, completed: 0, draft: 0, paused: 0, total_participants: 0,
});

const filters = reactive({
    status: '', experiment_type: '', search: '',
});

const pagination = reactive({
    current_page: 1, total: 0, per_page: 20,
});

const createForm = ref({
    name: '', description: '', experiment_type: 'pricing', target_metric: 'conversion',
    traffic_split: 50, minimum_sample_size: 100, confidence_level: 95,
    controlConfigStr: '', treatmentConfigStr: '', starts_at: null, ends_at: null,
    segment_filters: {
        region: [], channel: [], customer_tier: [], industry: [],
        new_vs_returning: [], device_type: [],
    },
});

const recommendations = ref([]);
const showRecommendations = ref(false);
const loadingRecs = ref(false);

function statusLabel(s) {
    const map = { draft: '草稿', running: '运行中', paused: '已暂停', completed: '已完成', cancelled: '已取消' };
    return map[s] || s;
}

function statusType(s) {
    const map = { draft: 'info', running: 'success', paused: 'warning', completed: '', cancelled: 'info' };
    return map[s] || 'info';
}

function formatTime(t) {
    return t ? new Date(t).toLocaleString('zh-CN') : '-';
}

async function loadAll() {
    loading.value = true;
    try {
        const [listRes, statsRes] = await Promise.all([
            dynamicPricingApi.getExperiments({ ...filters, page: pagination.current_page, per_page: pagination.per_page }),
            dynamicPricingApi.getExperimentStats(),
        ]);
        const d = listRes.data;
        const listData = d?.data || {};
        experiments.value = listData.data || (Array.isArray(listData) ? listData : []);
        pagination.current_page = listData.current_page || 1;
        pagination.total = listData.total || experiments.value.length;
        Object.assign(stats, statsRes.data?.data || {});
    } catch (err) {
        console.error(err);
    } finally {
        loading.value = false;
    }
}

async function loadList() {
    pagination.current_page = 1;
    await loadAll();
}

function resetFilters() {
    filters.status = '';
    filters.experiment_type = '';
    filters.search = '';
    loadList();
}

function onPageChange(page) {
    pagination.current_page = page;
    loadAll();
}

async function doCreate() {
    if (!createForm.value.name) {
        ElMessage.warning('请输入实验名称');
        return;
    }
    creating.value = true;
    try {
        const payload = {
            name: createForm.value.name,
            description: createForm.value.description,
            experiment_type: createForm.value.experiment_type,
            target_metric: createForm.value.target_metric,
            traffic_split: createForm.value.traffic_split,
            minimum_sample_size: createForm.value.minimum_sample_size,
            confidence_level: createForm.value.confidence_level,
            starts_at: createForm.value.starts_at,
            ends_at: createForm.value.ends_at,
        };
        // 带分群筛选条件
        const sf = createForm.value.segment_filters;
        const hasSegments = Object.values(sf).some(v => v && v.length > 0);
        if (hasSegments) {
            payload.segment_filters = {};
            Object.entries(sf).forEach(([key, val]) => {
                if (val && val.length > 0) payload.segment_filters[key] = val;
            });
        }
        if (createForm.value.controlConfigStr) {
            try { payload.control_config = JSON.parse(createForm.value.controlConfigStr); } catch { ElMessage.warning('对照组 JSON 格式错误'); creating.value = false; return; }
        }
        if (createForm.value.treatmentConfigStr) {
            try { payload.treatment_config = JSON.parse(createForm.value.treatmentConfigStr); } catch { ElMessage.warning('实验组 JSON 格式错误'); creating.value = false; return; }
        }
        await dynamicPricingApi.createExperiment(payload);
        ElMessage.success('实验草稿已创建');
        showCreateDialog.value = false;
        createForm.value = { name: '', description: '', experiment_type: 'pricing', target_metric: 'conversion', traffic_split: 50, minimum_sample_size: 100, confidence_level: 95, controlConfigStr: '', treatmentConfigStr: '', starts_at: null, ends_at: null, segment_filters: { region: [], channel: [], customer_tier: [], industry: [], new_vs_returning: [], device_type: [] } };
        await loadAll();
    } catch (err) {
        console.error(err);
    } finally {
        creating.value = false;
    }
}

async function viewDetail(row) {
    showDetailDialog.value = true;
    detailData.value = null;
    try {
        const res = await dynamicPricingApi.getExperiment(row.id);
        detailData.value = res.data?.data;
    } catch (err) {
        console.error(err);
    }
}

async function startExp(row) {
    try {
        await dynamicPricingApi.startExperiment(row.id);
        ElMessage.success('实验已启动');
        await loadAll();
    } catch (err) {
        console.error(err);
    }
}

async function pauseExp(row) {
    try {
        await dynamicPricingApi.pauseExperiment(row.id);
        ElMessage.success('实验已暂停');
        await loadAll();
    } catch (err) {
        console.error(err);
    }
}

async function completeExp(row) {
    try {
        await ElMessageBox.confirm(`确定结束实验 "${row.name}"？`, '确认', { type: 'warning' });
        await dynamicPricingApi.completeExperiment(row.id);
        ElMessage.success('实验已结束');
        await loadAll();
    } catch (err) {}
}

async function calculateExp(row) {
    try {
        await dynamicPricingApi.calculateResults(row.id);
        ElMessage.success('结果已重算');
        await viewDetail(row);
        await loadAll();
    } catch (err) {
        console.error(err);
    }
}

async function deleteExp(row) {
    try {
        await ElMessageBox.confirm(`确定删除实验 "${row.name}"？`, '确认', { type: 'warning' });
        await dynamicPricingApi.deleteExperiment(row.id);
        ElMessage.success('实验已删除');
        await loadAll();
    } catch (err) {}
}

// M3-26 增强功能

function formatPercent(val) {
    if (val === null || val === undefined || val === 0) return '0%';
    return (val > 0 ? '+' : '') + val.toFixed(2) + '%';
}

function formatMoney(val) {
    if (val === null || val === undefined) return '¥0.00';
    return '¥' + Number(val).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function applyWinner(row) {
    try {
        await ElMessageBox.confirm(
            `确定将实验 "${row.name}" 的优胜方案应用为推荐定价？\n\n系统将基于统计结果生成推荐配置。`,
            '应用优胜方案', { type: 'info', confirmButtonText: '确定应用', cancelButtonText: '取消' }
        );
        const res = await dynamicPricingApi.applyWinningTreatment(row.id);
        ElMessage.success('优胜方案已生成，可在详情中查看');
        await viewDetail(row);
        await loadAll();
    } catch (err) {
        if (err !== 'cancel') console.error(err);
    }
}

async function loadRecommendations() {
    loadingRecs.value = true;
    try {
        const res = await dynamicPricingApi.getRecommendations();
        recommendations.value = res.data?.data?.recommendations || [];
        showRecommendations.value = true;
    } catch (err) {
        console.error(err);
        ElMessage.error('加载建议失败');
    } finally {
        loadingRecs.value = false;
    }
}

async function showRecsPanel() {
    await loadRecommendations();
}

onMounted(loadAll);
</script>

<style scoped>
.pricing-experiments-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 8px; flex-direction: column; }
.page-header h2 { margin: 0; font-size: 20px; }
.page-header .text-muted { margin: 4px 0 0; color: #909399; font-size: 13px; }
.header-actions { display: flex; gap: 8px; margin-top: 8px; }

.mb-4 { margin-bottom: 16px; }
.mt-2 { margin-top: 8px; }

.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 700; }
.stat-success .stat-value { color: #67c23a; }
.stat-info .stat-value { color: #409eff; }
.stat-warning .stat-value { color: #e6a23c; }

.pagination-wrapper { display: flex; justify-content: flex-end; padding: 16px 0; }

.text-muted { color: #909399; font-size: 12px; }
.text-success { color: #67c23a; font-weight: 600; }
.exp-name { font-weight: 500; }

.json-block { background: #f5f7fa; padding: 12px; border-radius: 4px; font-size: 12px; overflow-x: auto; max-height: 200px; }

.d-flex { display: flex; }
.justify-between { justify-content: space-between; }
.align-center { align-items: center; }
.mb-2 { margin-bottom: 8px; }
.mb-3 { margin-bottom: 12px; }

.result-card { text-align: center; padding: 4px 0; border-radius: 8px; }
.result-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.result-value { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
.result-sub { font-size: 11px; color: #c0c4cc; }
.control-card { border-left: 3px solid #409eff; }
.treatment-card { border-left: 3px solid #e6a23c; }
.result-positive { border-left: 3px solid #67c23a; }
.result-negative { border-left: 3px solid #f56c6c; }
.text-danger { color: #f56c6c; font-weight: 600; }
</style>
