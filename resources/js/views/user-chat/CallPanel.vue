<template>
    <!-- 通话面板 -->
    <div v-if="callState !== 'idle'" class="call-overlay" :class="{ 'call-minimized': minimized }">
        <div class="call-panel">
            <!-- 最小化时只显示小条 -->
            <div v-if="minimized" class="call-minibar" @click="minimized = false">
                <div class="minibar-avatar">{{ callPartner?.name?.charAt(0) || '?' }}</div>
                <div class="minibar-info">
                    <div class="minibar-name">{{ callPartner?.name || '对方' }}</div>
                    <div class="minibar-status">{{ callStatusText }}</div>
                </div>
                <div class="minibar-timer" v-if="callTimer">{{ callTimer }}</div>
                <el-button circle size="small" type="danger" @click.stop="endCall" title="挂断">
                    <el-icon><Phone style="transform:rotate(135deg)" /></el-icon>
                </el-button>
            </div>

            <!-- 全尺寸通话面板 -->
            <template v-else>
                <div class="call-header">
                    <div class="call-header-left">
                        <el-button text size="small" @click="minimized = true" title="最小化">
                            <el-icon><Minus /></el-icon>
                        </el-button>
                    </div>
                    <div class="call-header-title">{{ callType === 'video' ? '📹 视频通话' : '📞 语音通话' }}</div>
                    <div class="call-header-right">
                        <el-button text size="small" @click="endCall" title="挂断">
                            <el-icon><Close /></el-icon>
                        </el-button>
                    </div>
                </div>

                <!-- 视频区域 -->
                <div v-if="callType === 'video'" class="call-video-area">
                    <div class="remote-video">
                        <div class="remote-avatar-large">{{ callPartner?.name?.charAt(0) || '?' }}</div>
                        <div class="remote-name">{{ callPartner?.name || '对方' }}</div>
                    </div>
                    <div class="local-video">
                        <div class="local-avatar-small">{{ myName?.charAt(0) || '我' }}</div>
                    </div>
                </div>

                <!-- 语音区域 -->
                <div v-else class="call-audio-area">
                    <div class="audio-avatar-wrap">
                        <div class="audio-avatar-large">{{ callPartner?.name?.charAt(0) || '?' }}</div>
                    </div>
                    <div class="audio-name">{{ callPartner?.name || '对方' }}</div>
                    <div class="audio-status">{{ callStatusText }}</div>
                    <div v-if="callTimer" class="audio-timer">{{ callTimer }}</div>
                </div>

                <!-- 通话控制栏 -->
                <div class="call-controls">
                    <el-button v-if="callState === 'calling'" circle type="danger" size="large" @click="endCall" title="挂断">
                        <el-icon :size="24"><Phone style="transform:rotate(135deg)" /></el-icon>
                    </el-button>
                    <template v-if="callState === 'ringing'">
                        <el-button circle type="success" size="large" @click="answerCall" title="接听">
                            <el-icon :size="24"><Phone /></el-icon>
                        </el-button>
                        <el-button circle type="danger" size="large" @click="endCall" title="拒绝">
                            <el-icon :size="24"><Phone style="transform:rotate(135deg)" /></el-icon>
                        </el-button>
                    </template>
                    <template v-if="callState === 'connected'">
                        <el-button circle :type="muted ? 'warning' : 'default'" size="large" @click="toggleMute" :title="muted ? '取消静音' : '静音'">
                            <el-icon :size="20"><Mute v-if="muted" /><Microphone v-else /></el-icon>
                        </el-button>
                        <el-button circle type="danger" size="large" @click="endCall" title="挂断">
                            <el-icon :size="24"><Phone style="transform:rotate(135deg)" /></el-icon>
                        </el-button>
                        <el-button circle :type="speakerOn ? 'primary' : 'default'" size="large" @click="speakerOn = !speakerOn" :title="speakerOn ? '听筒' : '扬声器'">
                            <el-icon :size="20"><MuteNotification v-if="speakerOn" /><Bell v-else /></el-icon>
                        </el-button>
                    </template>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Phone, Microphone, Mute, Bell, MuteNotification, Minus, Close } from '@element-plus/icons-vue'
import apiClient from '@/utils/request'

const props = defineProps({
    modelValue: { type: String, default: 'idle' }, // idle | calling | ringing | connected | ended
    callType: { type: String, default: 'audio' }, // audio | video
    callPartner: { type: Object, default: () => null },
    conversationId: { type: Number, default: null },
    myName: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue', 'call-ended'])

const callState = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v),
})

const minimized = ref(false)
const muted = ref(false)
const speakerOn = ref(false)
const callTimer = ref('')
let timerInterval = null
let callStartTime = null
let pollInterval = null
let currentCallId = ref(null)

const callStatusText = computed(() => {
    switch (callState.value) {
        case 'calling': return '正在呼叫...'
        case 'ringing': return '来电...'
        case 'connected': return '通话中'
        case 'ended': return '已结束'
        default: return ''
    }
})

// 发起呼叫
async function startCall(calleeId, type, convId) {
    try {
        const res = await apiClient.post('/calls/call', {
            callee_id: calleeId,
            call_type: type,
            conversation_id: convId,
        })
        const data = res.data?.data || {}
        currentCallId.value = data.call_id
        callState.value = 'calling'
        callStartTime = Date.now()
        // 轮询对方是否接听
        startPolling(data.call_id)
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '呼叫失败')
        callState.value = 'idle'
    }
}

// 接听通话
async function answerCall() {
    if (!currentCallId.value) return
    try {
        await apiClient.post(`/calls/${currentCallId.value}/respond`, { action: 'accept' })
        callState.value = 'connected'
        callStartTime = Date.now()
        startTimer()
        stopPolling()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '接听失败')
    }
}

// 结束通话
async function endCall() {
    stopPolling()
    stopTimer()
    if (currentCallId.value) {
        try {
            await apiClient.post(`/calls/${currentCallId.value}/end`)
        } catch { /* ignore */ }
    }
    callState.value = 'ended'
    setTimeout(() => {
        callState.value = 'idle'
        currentCallId.value = null
        minimized.value = false
        emit('call-ended')
    }, 1500)
}

// 轮询呼叫状态（呼叫方用）
function startPolling(callId) {
    pollInterval = setInterval(async () => {
        try {
            const res = await apiClient.get(`/calls/${callId}/status`)
            const status = res.data?.data?.status
            if (status === 'connected') {
                callState.value = 'connected'
                startTimer()
                stopPolling()
            } else if (status === 'rejected' || status === 'ended') {
                ElMessage.info('对方拒绝了通话')
                endCall()
            }
        } catch { stopPolling() }
    }, 2000)
}
function stopPolling() {
    if (pollInterval) { clearInterval(pollInterval); pollInterval = null }
}

// 通话计时
function startTimer() {
    callStartTime = Date.now()
    timerInterval = setInterval(() => {
        const sec = Math.floor((Date.now() - callStartTime) / 1000)
        const m = Math.floor(sec / 60)
        const s = sec % 60
        callTimer.value = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
    }, 1000)
}
function stopTimer() {
    if (timerInterval) { clearInterval(timerInterval); timerInterval = null }
}

function toggleMute() { muted.value = !muted.value }

// 供父组件调用的方法
function handleIncomingCall(callId, caller, type) {
    currentCallId.value = callId
    callState.value = 'ringing'
    // 启动轮询检测对方取消
    startPolling(callId)
}

defineExpose({ startCall, handleIncomingCall })

onUnmounted(() => {
    stopPolling()
    stopTimer()
})
</script>

<style scoped>
.call-overlay { position: fixed; bottom: 20px; right: 20px; z-index: 2000; }
.call-panel { width: 340px; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.15); overflow: hidden; border: 1px solid #e4e7ed; }
.call-minimized .call-panel { width: 280px; }
.call-minibar { display: flex; align-items: center; gap: 8px; padding: 10px 14px; cursor: pointer; background: linear-gradient(135deg, #409eff, #337ecc); color: #fff; }
.minibar-avatar { width: 32px; height: 32px; border-radius: 50%; background: rgba(255,255,255,0.3); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; }
.minibar-info { flex: 1; min-width: 0; }
.minibar-name { font-size: 13px; font-weight: 600; }
.minibar-status { font-size: 11px; opacity: 0.8; }
.minibar-timer { font-size: 14px; font-weight: 600; font-variant-numeric: tabular-nums; }
.call-header { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border-bottom: 1px solid #f0f0f0; background: #fafafa; }
.call-header-title { font-size: 14px; font-weight: 600; }
.call-video-area { position: relative; height: 240px; background: #1a1a2e; display: flex; align-items: center; justify-content: center; }
.remote-video { text-align: center; color: #fff; }
.remote-avatar-large { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #409eff, #66b1ff); display: flex; align-items: center; justify-content: center; font-size: 36px; font-weight: 700; margin: 0 auto 12px; }
.remote-name { font-size: 16px; }
.local-video { position: absolute; bottom: 12px; right: 12px; width: 80px; height: 120px; background: #2a2a4a; border-radius: 8px; display: flex; align-items: center; justify-content: center; border: 2px solid #409eff; }
.local-avatar-small { width: 36px; height: 36px; border-radius: 50%; background: #67c23a; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 700; color: #fff; }
.call-audio-area { text-align: center; padding: 32px 20px; }
.audio-avatar-wrap { margin-bottom: 12px; }
.audio-avatar-large { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, #409eff, #66b1ff); display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 700; color: #fff; margin: 0 auto; }
.audio-name { font-size: 18px; font-weight: 600; margin-bottom: 4px; }
.audio-status { font-size: 13px; color: #909399; margin-bottom: 4px; }
.audio-timer { font-size: 14px; color: #606266; font-weight: 600; font-variant-numeric: tabular-nums; }
.call-controls { display: flex; justify-content: center; gap: 20px; padding: 16px; border-top: 1px solid #f0f0f0; }
</style>
