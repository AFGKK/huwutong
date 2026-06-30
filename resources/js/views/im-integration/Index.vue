<template>
    <div class="im-integration-page">
        <div class="page-header">
            <h2>IM 通知集成</h2>
        </div>

        <el-row :gutter="16">
            <!-- Slack -->
            <el-col :span="12" class="mb-4">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><ChatDotSquare /></el-icon> Slack</span>
                            <el-tag type="success" size="small">推荐</el-tag>
                        </div>
                    </template>
                    <div class="channel-body">
                        <p class="channel-desc">通过 Slack Incoming Webhook 将通知推送到指定频道。</p>
                        <el-form :model="slackForm" label-position="top" size="small">
                            <el-form-item label="Webhook URL">
                                <el-input v-model="slackForm.webhook_url" placeholder="https://hooks.slack.com/services/..." />
                            </el-form-item>
                            <el-form-item>
                                <el-button @click="testSlack" :loading="testing.slack" type="primary">测试连接</el-button>
                                <el-button @click="sendSlackTestMsg" :loading="sending.slack">发送测试消息</el-button>
                            </el-form-item>
                        </el-form>
                        <div v-if="results.slack" :class="['result-box', results.slack.success ? 'success' : 'error']">
                            {{ results.slack.message }}
                        </div>
                        <div class="channel-help">
                            <a href="https://api.slack.com/messaging/webhooks" target="_blank">如何创建 Slack Webhook →</a>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <!-- 钉钉 -->
            <el-col :span="12" class="mb-4">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><ChatDotSquare /></el-icon> 钉钉</span>
                        </div>
                    </template>
                    <div class="channel-body">
                        <p class="channel-desc">通过钉钉自定义机器人 Webhook 发送群消息。</p>
                        <el-form :model="dingtalkForm" label-position="top" size="small">
                            <el-form-item label="Webhook URL">
                                <el-input v-model="dingtalkForm.webhook_url" placeholder="https://oapi.dingtalk.com/robot/send?access_token=..." />
                            </el-form-item>
                            <el-form-item>
                                <el-button @click="testDingTalk" :loading="testing.dingtalk" type="primary">测试连接</el-button>
                                <el-button @click="sendDingTalkTestMsg" :loading="sending.dingtalk">发送测试消息</el-button>
                            </el-form-item>
                        </el-form>
                        <div v-if="results.dingtalk" :class="['result-box', results.dingtalk.success ? 'success' : 'error']">
                            {{ results.dingtalk.message }}
                        </div>
                        <div class="channel-help">
                            <a href="https://open.dingtalk.com/document/robots/custom-robot-access" target="_blank">如何创建钉钉机器人 →</a>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <!-- 企业微信 -->
            <el-col :span="12" class="mb-4">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><ChatDotSquare /></el-icon> 企业微信</span>
                        </div>
                    </template>
                    <div class="channel-body">
                        <p class="channel-desc">通过企业微信群机器人 Webhook 发送 Markdown 消息。</p>
                        <el-form :model="wecomForm" label-position="top" size="small">
                            <el-form-item label="Webhook URL">
                                <el-input v-model="wecomForm.webhook_url" placeholder="https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=..." />
                            </el-form-item>
                            <el-form-item>
                                <el-button @click="testWeCom" :loading="testing.wecom" type="primary">测试连接</el-button>
                                <el-button @click="sendWeComTestMsg" :loading="sending.wecom">发送测试消息</el-button>
                            </el-form-item>
                        </el-form>
                        <div v-if="results.wecom" :class="['result-box', results.wecom.success ? 'success' : 'error']">
                            {{ results.wecom.message }}
                        </div>
                        <div class="channel-help">
                            <a href="https://developer.work.weixin.qq.com/document/path/91770" target="_blank">如何创建企微机器人 →</a>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <!-- 飞书 -->
            <el-col :span="12" class="mb-4">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><ChatDotSquare /></el-icon> 飞书</span>
                        </div>
                    </template>
                    <div class="channel-body">
                        <p class="channel-desc">通过飞书群机器人 Webhook 发送富文本卡片消息。</p>
                        <el-form :model="feishuForm" label-position="top" size="small">
                            <el-form-item label="Webhook URL">
                                <el-input v-model="feishuForm.webhook_url" placeholder="https://open.feishu.cn/open-apis/bot/v2/hook/..." />
                            </el-form-item>
                            <el-form-item>
                                <el-button @click="testFeishu" :loading="testing.feishu" type="primary">测试连接</el-button>
                                <el-button @click="sendFeishuTestMsg" :loading="sending.feishu">发送测试消息</el-button>
                            </el-form-item>
                        </el-form>
                        <div v-if="results.feishu" :class="['result-box', results.feishu.success ? 'success' : 'error']">
                            {{ results.feishu.message }}
                        </div>
                        <div class="channel-help">
                            <a href="https://open.feishu.cn/document/tools/custom-bot" target="_blank">如何创建飞书机器人 →</a>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 自动发送配置 -->
        <el-card shadow="never">
            <template #header>
                <span>自动发送事件配置</span>
            </template>
            <el-table :data="autoSendEvents" stripe>
                <el-table-column prop="event" label="事件" width="250" />
                <el-table-column label="推送渠道">
                    <template #default="{ row }">
                        <el-tag v-for="ch in row.channels" :key="ch" :type="tagType(ch)" size="small" class="mr-1">{{ channelName(ch) }}</el-tag>
                        <span v-if="!row.channels.length" class="text-muted">未配置</span>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- ═══════ Microsoft Teams 集成 ═══════ -->
        <el-divider />
        <h3 style="margin:16px 0 12px;display:flex;align-items:center;gap:8px">
            <el-icon><ChatDotSquare /></el-icon> Microsoft Teams 通知
            <el-tag size="small" type="info">独立管理</el-tag>
        </h3>

        <!-- Teams 统计 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4"><el-card shadow="hover"><div class="stat-value">{{ teamsDash.active }}</div><div class="stat-label">活跃 Webhook</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-value text-success">{{ teamsDash.today_success }}</div><div class="stat-label">今日成功</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-value text-danger">{{ teamsDash.today_failed }}</div><div class="stat-label">今日失败</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-value">{{ teamsDash.total }}</div><div class="stat-label">总计配置</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-value text-primary">{{ teamsDash.today_total }}</div><div class="stat-label">今日发送</div></el-card></el-col>
        </el-row>

        <el-tabs v-model="teamsTab">
            <!-- Webhook 配置 -->
            <el-tab-pane label="Webhook 配置" name="webhooks">
                <el-card shadow="never">
                    <template #header>
                        <el-space>
                            <span>Teams Webhook 列表</span>
                            <el-button size="small" type="primary" @click="openTeamsDialog">新建 Webhook</el-button>
                        </el-space>
                    </template>
                    <el-table :data="teamsWebhooks" stripe v-loading="teamsLoading">
                        <el-table-column prop="name" label="频道名称" width="140" />
                        <el-table-column prop="webhook_url" label="Webhook URL" min-width="280" show-overflow-tooltip />
                        <el-table-column label="通知类型" width="110">
                            <template #default="{ row }"><el-tag size="small">{{ teamsTypeLabel(row.notification_type) }}</el-tag></template>
                        </el-table-column>
                        <el-table-column label="状态" width="65">
                            <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag></template>
                        </el-table-column>
                        <el-table-column label="操作" width="280" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="editTeamsWebhook(row)">编辑</el-button>
                                <el-button size="small" type="success" @click="teamsTest(row)">测试</el-button>
                                <el-popconfirm title="确认删除?" @confirm="teamsDelete(row)">
                                    <template #reference><el-button size="small" type="danger">删除</el-button></template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>

                <!-- 手动发送 -->
                <el-card shadow="never" style="margin-top:16px">
                    <template #header><span>手动发送</span></template>
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-card shadow="never">
                                <template #header><el-space><span>发送激活通知</span><el-tag size="small">activation</el-tag></el-space></template>
                                <el-form size="small" label-position="top">
                                    <el-form-item label="License Key"><el-input v-model="activationForm.license_key" placeholder="LIC-XXXX" /></el-form-item>
                                    <el-form-item label="产品名称"><el-input v-model="activationForm.product_name" placeholder="标准版" /></el-form-item>
                                    <el-form-item label="客户名称"><el-input v-model="activationForm.customer_name" placeholder="张三" /></el-form-item>
                                    <el-button type="primary" size="small" :loading="sendingActivation" @click="handleSendActivation">发送</el-button>
                                </el-form>
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card shadow="never">
                                <template #header><el-space><span>发送告警通知</span><el-tag size="small">alert</el-tag></el-space></template>
                                <el-form size="small" label-position="top">
                                    <el-form-item label="标题"><el-input v-model="alertForm.title" placeholder="系统异常告警" /></el-form-item>
                                    <el-form-item label="消息内容"><el-input v-model="alertForm.message" type="textarea" :rows="2" placeholder="详细描述" /></el-form-item>
                                    <el-form-item label="严重程度">
                                        <el-select v-model="alertForm.severity" style="width:120px">
                                            <el-option label="信息" value="info" /><el-option label="警告" value="warning" /><el-option label="严重" value="critical" />
                                        </el-select>
                                    </el-form-item>
                                    <el-button type="danger" size="small" :loading="sendingAlert" @click="handleSendAlert">发送</el-button>
                                </el-form>
                            </el-card>
                        </el-col>
                    </el-row>
                </el-card>
            </el-tab-pane>

            <!-- 发送日志 -->
            <el-tab-pane label="发送日志" name="logs">
                <el-card shadow="never">
                    <template #header>
                        <el-space>
                            <span>通知发送日志</span>
                            <el-select v-model="teamsLogStatusFilter" placeholder="状态" clearable size="small" style="width:120px" @change="loadTeamsLogs">
                                <el-option label="全部" value="" /><el-option label="成功" value="success" /><el-option label="失败" value="failed" />
                            </el-select>
                            <el-select v-model="teamsLogTypeFilter" placeholder="类型" clearable size="small" style="width:140px" @change="loadTeamsLogs">
                                <el-option label="全部" value="" /><el-option label="激活通知" value="activation" /><el-option label="告警通知" value="alert" />
                                <el-option label="过期提醒" value="expiry" /><el-option label="测试" value="test" />
                            </el-select>
                        </el-space>
                    </template>
                    <el-table :data="teamsLogs" stripe v-loading="teamsLogsLoading">
                        <el-table-column prop="created_at" label="时间" width="150" />
                        <el-table-column label="类型" width="80"><template #default="{ row }">{{ teamsTypeLabel(row.notification_type) }}</template></el-table-column>
                        <el-table-column label="状态" width="65">
                            <template #default="{ row }"><el-tag :type="row.status === 'success' ? 'success' : 'danger'" size="small">{{ row.status === 'success' ? '成功' : '失败' }}</el-tag></template>
                        </el-table-column>
                        <el-table-column prop="title" label="标题" min-width="200" show-overflow-tooltip />
                        <el-table-column prop="message" label="消息" min-width="200" show-overflow-tooltip />
                        <el-table-column prop="error_message" label="错误" min-width="180" show-overflow-tooltip />
                    </el-table>
                    <el-pagination v-if="teamsLogTotal > teamsLogPerPage" v-model:current-page="teamsLogPage" :page-size="teamsLogPerPage" :total="teamsLogTotal" layout="prev, pager, next" @current-change="loadTeamsLogs" class="pagination" />
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- Teams 新建/编辑对话框 -->
        <el-dialog v-model="teamsDlgVisible" :title="teamsIsEditing ? '编辑 Webhook' : '新建 Teams Webhook'" width="550px">
            <el-form label-position="top" size="small" :model="teamsForm">
                <el-form-item label="频道名称" required><el-input v-model="teamsForm.name" placeholder="如：销售团队告警频道" /></el-form-item>
                <el-form-item label="Webhook URL" required>
                    <el-input v-model="teamsForm.webhook_url" placeholder="https://your-org.webhook.office.com/webhookb2/..." />
                    <div style="font-size:12px;color:#909399;margin-top:4px">在 Teams 频道 → 连接器 → Incoming Webhook 获取 URL</div>
                </el-form-item>
                <el-form-item label="通知类型" required>
                    <el-select v-model="teamsForm.notification_type" style="width:100%">
                        <el-option v-for="nt in teamsNotificationTypes" :key="nt.key" :label="nt.label" :value="nt.key" />
                    </el-select>
                </el-form-item>
                <el-form-item label="启用"><el-switch v-model="teamsForm.is_active" /></el-form-item>
                <el-form-item label="描述"><el-input v-model="teamsForm.description" type="textarea" :rows="2" placeholder="可选" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="teamsDlgVisible = false">取消</el-button>
                <el-button type="primary" :loading="teamsSaving" @click="saveTeamsWebhook">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { ChatDotSquare } from '@element-plus/icons-vue';
import { testSlack as apiTestSlack, testDingTalk as apiTestDingTalk, testWeCom as apiTestWeCom, testFeishu as apiTestFeishu, sendImMessage } from '@/api/imIntegration';
import teamsNotifier from '@/api/teamsNotifier';
import imConfig from '@/config/im-integration';

const slackForm = reactive({ webhook_url: '' });
const dingtalkForm = reactive({ webhook_url: '' });
const wecomForm = reactive({ webhook_url: '' });
const feishuForm = reactive({ webhook_url: '' });

const testing = reactive({ slack: false, dingtalk: false, wecom: false, feishu: false });
const sending = reactive({ slack: false, dingtalk: false, wecom: false, feishu: false });
const results = reactive({ slack: null, dingtalk: null, wecom: null, feishu: null });

const autoSendEvents = ref([]);

// ── Microsoft Teams ──
const teamsTab = ref('webhooks');
const teamsLoading = ref(false);
const teamsLogsLoading = ref(false);
const teamsSaving = ref(false);
const sendingActivation = ref(false);
const sendingAlert = ref(false);
const teamsDlgVisible = ref(false);
const teamsIsEditing = ref(false);
const teamsEditingId = ref(null);
const teamsLogStatusFilter = ref('');
const teamsLogTypeFilter = ref('');
const teamsLogPage = ref(1);
const teamsLogPerPage = ref(20);
const teamsLogTotal = ref(0);

const teamsDash = reactive({ total: 0, active: 0, today_total: 0, today_success: 0, today_failed: 0 });
const teamsWebhooks = ref([]);
const teamsLogs = ref([]);
const teamsNotificationTypes = ref([]);

const teamsForm = reactive({
    name: '', webhook_url: '', notification_type: 'all', is_active: true, description: '',
});
const activationForm = reactive({ license_key: '', product_name: '', customer_name: '' });
const alertForm = reactive({ title: '', message: '', severity: 'warning' });

function teamsTypeLabel(type) {
    const map = { all: '全部', activation: '激活通知', alert: '告警通知', expiry: '过期提醒', test: '测试' };
    return map[type] || type;
}

async function loadTeamsConfig() {
    try { const res = await teamsNotifier.config(); teamsNotificationTypes.value = res.data.data.notification_types; } catch {}
}
async function loadTeamsDash() {
    try { const res = await teamsNotifier.dashboard(); Object.assign(teamsDash, res.data.data); } catch {}
}
async function loadTeamsWebhooks() {
    teamsLoading.value = true;
    try { const res = await teamsNotifier.list(); teamsWebhooks.value = res.data.data || []; } catch {} finally { teamsLoading.value = false; }
}
async function loadTeamsLogs() {
    teamsLogsLoading.value = true;
    try {
        const res = await teamsNotifier.logs({ page: teamsLogPage.value, per_page: teamsLogPerPage.value, status: teamsLogStatusFilter.value || undefined, type: teamsLogTypeFilter.value || undefined });
        teamsLogs.value = res.data.data?.data || res.data.data || [];
        teamsLogTotal.value = res.data.meta?.total || 0;
    } catch {} finally { teamsLogsLoading.value = false; }
}
function openTeamsDialog() {
    teamsIsEditing.value = false;
    teamsEditingId.value = null;
    teamsForm.name = ''; teamsForm.webhook_url = ''; teamsForm.notification_type = 'all'; teamsForm.is_active = true; teamsForm.description = '';
    teamsDlgVisible.value = true;
}
function editTeamsWebhook(row) {
    teamsIsEditing.value = true;
    teamsEditingId.value = row.id;
    teamsForm.name = row.name; teamsForm.webhook_url = row.webhook_url; teamsForm.notification_type = row.notification_type; teamsForm.is_active = row.is_active; teamsForm.description = row.description || '';
    teamsDlgVisible.value = true;
}
async function saveTeamsWebhook() {
    if (!teamsForm.name || !teamsForm.webhook_url) { ElMessage.warning('请填写完整信息'); return; }
    teamsSaving.value = true;
    try {
        if (teamsIsEditing.value) { await teamsNotifier.update(teamsEditingId.value, teamsForm); ElMessage.success('已更新'); }
        else { await teamsNotifier.create(teamsForm); ElMessage.success('已创建'); }
        teamsDlgVisible.value = false;
        await loadTeamsWebhooks();
    } catch (e) { ElMessage.error(e.response?.data?.message || '保存失败'); } finally { teamsSaving.value = false; }
}
async function teamsTest(row) {
    try { await teamsNotifier.test(row.id); ElMessage.success('测试成功'); } catch (e) { ElMessage.error(e.response?.data?.message || '测试失败'); }
}
async function teamsDelete(row) {
    try { await teamsNotifier.delete(row.id); ElMessage.success('已删除'); await loadTeamsWebhooks(); } catch (e) { ElMessage.error(e.response?.data?.message || '删除失败'); }
}
async function handleSendActivation() {
    if (!activationForm.license_key) { ElMessage.warning('请输入 License Key'); return; }
    sendingActivation.value = true;
    try { await teamsNotifier.sendActivation(activationForm); ElMessage.success('激活通知已发送'); } catch (e) { ElMessage.error(e.response?.data?.message || '发送失败'); } finally { sendingActivation.value = false; }
}
async function handleSendAlert() {
    if (!alertForm.title) { ElMessage.warning('请输入标题'); return; }
    sendingAlert.value = true;
    try { await teamsNotifier.sendAlert(alertForm); ElMessage.success('告警通知已发送'); } catch (e) { ElMessage.error(e.response?.data?.message || '发送失败'); } finally { sendingAlert.value = false; }
}

const channelName = (ch) => ({ slack: 'Slack', dingtalk: '钉钉', wecom: '企微', feishu: '飞书' }[ch] || ch);
const tagType = (ch) => ({ slack: 'success', dingtalk: 'warning', wecom: 'primary', feishu: 'info' }[ch] || 'info');

const testSlack = async () => {
    if (!slackForm.webhook_url) { ElMessage.warning('请输入 Webhook URL'); return; }
    testing.slack = true;
    try {
        const res = await apiTestSlack(slackForm.webhook_url);
        results.slack = { success: true, message: '✅ ' + (res.data.message || '连接成功') };
    } catch (e) {
        results.slack = { success: false, message: '❌ ' + (e.response?.data?.message || '连接失败') };
    } finally { testing.slack = false; }
};

const testDingTalk = async () => {
    if (!dingtalkForm.webhook_url) { ElMessage.warning('请输入 Webhook URL'); return; }
    testing.dingtalk = true;
    try {
        const res = await apiTestDingTalk(dingtalkForm.webhook_url);
        results.dingtalk = { success: true, message: '✅ ' + (res.data.message || '连接成功') };
    } catch (e) {
        results.dingtalk = { success: false, message: '❌ ' + (e.response?.data?.message || '连接失败') };
    } finally { testing.dingtalk = false; }
};

const testWeCom = async () => {
    if (!wecomForm.webhook_url) { ElMessage.warning('请输入 Webhook URL'); return; }
    testing.wecom = true;
    try {
        const res = await apiTestWeCom(wecomForm.webhook_url);
        results.wecom = { success: true, message: '✅ ' + (res.data.message || '连接成功') };
    } catch (e) {
        results.wecom = { success: false, message: '❌ ' + (e.response?.data?.message || '连接失败') };
    } finally { testing.wecom = false; }
};

const testFeishu = async () => {
    if (!feishuForm.webhook_url) { ElMessage.warning('请输入 Webhook URL'); return; }
    testing.feishu = true;
    try {
        const res = await apiTestFeishu(feishuForm.webhook_url);
        results.feishu = { success: true, message: '✅ ' + (res.data.message || '连接成功') };
    } catch (e) {
        results.feishu = { success: false, message: '❌ ' + (e.response?.data?.message || '连接失败') };
    } finally { testing.feishu = false; }
};

const sendTestMsg = async (channel, webhookUrl, formKey) => {
    if (!webhookUrl) { ElMessage.warning('请输入 Webhook URL'); return; }
    sending[formKey] = true;
    try {
        const res = await sendImMessage({
            channel,
            webhook_url: webhookUrl,
            title: '🔄 IM 集成测试消息',
            content: `这是一条来自互物通的测试消息。\n时间: ${new Date().toLocaleString()}\n状态: 发送成功 ✅`,
            severity: 'low',
        });
        ElMessage.success('测试消息已发送');
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '发送失败');
    } finally { sending[formKey] = false; }
};

const sendSlackTestMsg = () => sendTestMsg('slack', slackForm.webhook_url, 'slack');
const sendDingTalkTestMsg = () => sendTestMsg('dingtalk', dingtalkForm.webhook_url, 'dingtalk');
const sendWeComTestMsg = () => sendTestMsg('wecom', wecomForm.webhook_url, 'wecom');
const sendFeishuTestMsg = () => sendTestMsg('feishu', feishuForm.webhook_url, 'feishu');

onMounted(() => {
    // 从 config 加载自动发送事件配置
    if (imConfig?.auto_send) {
        autoSendEvents.value = Object.entries(imConfig.auto_send).map(([event, channels]) => ({
            event: event.replace(/\./g, ' · '),
            channels,
        }));
    }
    loadTeamsConfig();
    loadTeamsDash();
    loadTeamsWebhooks();
});
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.card-header { display: flex; align-items: center; gap: 8px; }
.channel-body { min-height: 180px; }
.channel-desc { font-size: 13px; color: #909399; margin-bottom: 12px; }
.result-box { padding: 8px 12px; border-radius: 4px; font-size: 13px; margin-top: 8px; }
.result-box.success { background: #f0f9eb; color: #67c23a; }
.result-box.error { background: #fef0f0; color: #f56c6c; }
.channel-help { margin-top: 8px; font-size: 12px; }
.channel-help a { color: #409eff; text-decoration: none; }
.mr-1 { margin-right: 4px; }
.mb-4 { margin-bottom: 16px; }
.stat-value { font-size: 24px; font-weight: bold; text-align: center; }
.stat-label { font-size: 13px; color: #909399; text-align: center; margin-top: 4px; }
.pagination { margin-top: 16px; text-align: center; }
.text-muted { color: #909399; font-size: 13px; }
</style>
