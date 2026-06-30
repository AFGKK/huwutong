<template>
    <div class="bot-manager-page">
        <div class="page-header">
            <h2>🤖 Bot 机器人管理</h2>
            <el-button type="primary" @click="showRegisterDialog = true">
                <el-icon><Plus /></el-icon> 注册新 Bot
            </el-button>
        </div>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- 我的 Bot -->
            <el-tab-pane label="🤖 我的 Bot" name="my">
                <div class="tab-content">
                    <el-table :data="myBots" v-loading="loadingMy" stripe size="small" style="width:100%">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column prop="name" label="名称" min-width="120" />
                        <el-table-column prop="description" label="描述" min-width="200" show-overflow-tooltip />
                        <el-table-column label="状态" width="90">
                            <template #default="{row}">
                                <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
                                    {{ row.is_active ? '活跃' : '已停用' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="公开" width="70">
                            <template #default="{row}">
                                <el-tag :type="row.is_public ? 'success' : 'info'" size="small">{{ row.is_public ? '是' : '否' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="Token" min-width="200">
                            <template #default="{row}">
                                <div class="token-display">
                                    <code>{{ maskToken(row.token) }}</code>
                                    <el-button text size="small" @click="copyToken(row.token)" title="复制">
                                        <el-icon><CopyDocument /></el-icon>
                                    </el-button>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="创建时间" width="150">
                            <template #default="{row}">{{ row.created_at }}</template>
                        </el-table-column>
                        <el-table-column label="操作" width="180" fixed="right">
                            <template #default="{row}">
                                <el-button size="small" text @click="refreshBotToken(row)">刷新Token</el-button>
                                <el-button size="small" text @click="showCommands(row)">命令</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div v-if="!myBots.length && !loadingMy" class="empty-state">
                        <el-empty description="暂无 Bot，点击右上角注册一个" :image-size="60" />
                    </div>
                </div>
            </el-tab-pane>

            <!-- Bot 市场 -->
            <el-tab-pane label="🛒 Bot 市场" name="market">
                <div class="tab-content">
                    <div class="toolbar">
                        <el-input v-model="marketQuery" placeholder="搜索公开 Bot..." size="small" clearable
                            style="width:300px" @keydown.enter="loadMarketplace" />
                        <el-button size="small" type="primary" @click="loadMarketplace">搜索</el-button>
                    </div>
                    <el-row :gutter="16">
                        <el-col v-for="bot in marketBots" :key="bot.id" :span="8" style="margin-bottom:16px">
                            <el-card shadow="hover" class="market-card">
                                <div class="market-card-header">
                                    <div class="market-avatar">{{ bot.name.charAt(0) }}</div>
                                    <div class="market-info">
                                        <div class="market-name">{{ bot.name }}</div>
                                        <div class="market-author">by {{ bot.user?.name || '匿名' }}</div>
                                    </div>
                                </div>
                                <div class="market-desc">{{ bot.description || '暂无描述' }}</div>
                                <div v-if="bot.commands?.length" class="market-commands">
                                    <el-tag v-for="cmd in bot.commands.slice(0, 3)" :key="cmd.command" size="small" style="margin:2px">
                                        /{{ cmd.command }}
                                    </el-tag>
                                    <span v-if="bot.commands.length > 3" class="text-muted">+{{ bot.commands.length - 3 }}</span>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>
                    <div v-if="!marketBots.length && !loadingMarket" class="empty-state">
                        <el-empty description="市场中暂无公开 Bot" :image-size="60" />
                    </div>
                </div>
            </el-tab-pane>
        </el-tabs>

        <!-- 注册对话框 -->
        <el-dialog v-model="showRegisterDialog" title="注册新 Bot" width="520px">
            <el-form :model="form" label-width="100px" :rules="rules" ref="formRef">
                <el-form-item label="名称" prop="name">
                    <el-input v-model="form.name" placeholder="唯一名称，如 my-bot" maxlength="100" />
                </el-form-item>
                <el-form-item label="描述" prop="description">
                    <el-input v-model="form.description" type="textarea" :rows="2" placeholder="简单描述 Bot 功能" maxlength="500" />
                </el-form-item>
                <el-form-item label="Webhook URL" prop="webhook_url">
                    <el-input v-model="form.webhook_url" placeholder="https://example.com/bot-webhook" />
                    <div class="form-hint">Bot 收到消息时，会将消息 POST 到此 URL</div>
                </el-form-item>
                <el-form-item label="命令列表">
                    <div v-for="(cmd, i) in form.commands" :key="i" class="cmd-row">
                        <el-input v-model="cmd.command" placeholder="命令名" size="small" style="width:140px" />
                        <el-input v-model="cmd.description" placeholder="描述" size="small" style="width:200px" />
                        <el-button text size="small" type="danger" @click="form.commands.splice(i, 1)">
                            <el-icon><Delete /></el-icon>
                        </el-button>
                    </div>
                    <el-button size="small" text @click="form.commands.push({ command: '', description: '' })">
                        <el-icon><Plus /></el-icon> 添加命令
                    </el-button>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showRegisterDialog = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="registerBot">注册</el-button>
            </template>
        </el-dialog>

        <!-- 命令查看对话框 -->
        <el-dialog v-model="showCommandsDialog" title="Bot 命令列表" width="420px">
            <div v-if="selectedBot?.commands?.length">
                <div v-for="cmd in selectedBot.commands" :key="cmd.command" class="cmd-display-row">
                    <el-tag type="primary">/{{ cmd.command }}</el-tag>
                    <span>{{ cmd.description }}</span>
                </div>
            </div>
            <div v-else style="text-align:center;padding:20px;color:#909399">无自定义命令</div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, CopyDocument, Delete } from '@element-plus/icons-vue'
import apiClient from '@/utils/request'

const activeTab = ref('my')
const loadingMy = ref(false)
const loadingMarket = ref(false)
const myBots = ref([])
const marketBots = ref([])
const marketQuery = ref('')
const showRegisterDialog = ref(false)
const saving = ref(false)
const formRef = ref(null)
const showCommandsDialog = ref(false)
const selectedBot = ref(null)

const form = reactive({
    name: '',
    description: '',
    webhook_url: '',
    commands: [],
})

const rules = {
    name: [{ required: true, message: '请输入 Bot 名称', trigger: 'blur' }],
}

async function loadMyBots() {
    loadingMy.value = true
    try {
        const res = await apiClient.get('/bots/my')
        myBots.value = res.data?.data || []
    } catch { myBots.value = [] }
    finally { loadingMy.value = false }
}

async function loadMarketplace() {
    loadingMarket.value = true
    try {
        const params = {}
        if (marketQuery.value) params.q = marketQuery.value
        const res = await apiClient.get('/bots/marketplace', { params })
        marketBots.value = res.data?.data?.data || res.data?.data || []
    } catch { marketBots.value = [] }
    finally { loadingMarket.value = false }
}

async function registerBot() {
    if (!formRef.value) return
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return

    saving.value = true
    try {
        const payload = {
            name: form.name,
            description: form.description,
            webhook_url: form.webhook_url,
            commands: form.commands.filter(c => c.command.trim()),
        }
        const res = await apiClient.post('/bots/register', payload)
        const bot = res.data?.data
        if (bot) {
            ElMessage.success(`Bot「${bot.name}」注册成功！Token: ${bot.token}`)
            myBots.value.unshift(bot)
            showRegisterDialog.value = false
            resetForm()
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '注册失败')
    } finally { saving.value = false }
}

async function refreshBotToken(bot) {
    try {
        await ElMessageBox.confirm(`确定刷新「${bot.name}」的 Token？旧的 Token 将立即失效。`, '确认刷新')
        const res = await apiClient.post(`/bots/${bot.id}/refresh-token`)
        bot.token = res.data?.data?.token || bot.token
        ElMessage.success('Token 已刷新')
    } catch { /* 取消 */ }
}

function copyToken(token) {
    navigator.clipboard.writeText(token).then(() => ElMessage.success('已复制')).catch(() => ElMessage.warning('复制失败'))
}

function maskToken(token) {
    if (!token || token.length < 12) return token || ''
    return token.substring(0, 8) + '••••' + token.substring(token.length - 4)
}

function showCommands(bot) {
    selectedBot.value = bot
    showCommandsDialog.value = true
}

function resetForm() {
    form.name = ''
    form.description = ''
    form.webhook_url = ''
    form.commands = []
}

onMounted(() => {
    loadMyBots()
    loadMarketplace()
})
</script>

<style scoped>
.bot-manager-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 22px; }
.tab-content { padding: 16px 0; min-height: 300px; }
.toolbar { display: flex; gap: 8px; margin-bottom: 16px; }
.empty-state { padding: 40px 0; }
.token-display { display: flex; align-items: center; gap: 4px; }
.token-display code { font-size: 12px; background: #f5f7fa; padding: 2px 6px; border-radius: 3px; }
.form-hint { font-size: 11px; color: #909399; margin-top: 4px; }
.cmd-row { display: flex; gap: 6px; margin-bottom: 6px; align-items: center; }
.cmd-display-row { display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
.cmd-display-row:last-child { border-bottom: none; }
.market-card { cursor: default; }
.market-card-header { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.market-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #409eff, #66b1ff); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700; flex-shrink: 0; }
.market-info { flex: 1; min-width: 0; }
.market-name { font-size: 15px; font-weight: 600; }
.market-author { font-size: 12px; color: #909399; }
.market-desc { font-size: 13px; color: #606266; margin-bottom: 8px; line-height: 1.5; }
.market-commands { display: flex; flex-wrap: wrap; gap: 2px; align-items: center; }
.text-muted { font-size: 12px; color: #909399; margin-left: 4px; }
</style>
