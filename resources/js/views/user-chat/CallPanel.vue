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
                    <video ref="remoteVideoRef" autoplay playsinline class="remote-video-el"></video>
                    <div v-if="!hasRemoteMedia" class="remote-video-fallback">
                        <div class="remote-avatar-large">{{ callPartner?.name?.charAt(0) || '?' }}</div>
                        <div class="remote-name">{{ callPartner?.name || '对方' }}</div>
                    </div>
                    <video ref="localVideoRef" autoplay playsinline muted class="local-video-el"></video>
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
        <audio ref="remoteAudioRef" autoplay playsinline></audio>
    </div>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Phone, Microphone, Mute, Bell, MuteNotification, Minus, Close } from '@element-plus/icons-vue'
import callsApi from '@/api/calls'

const props = defineProps({
    modelValue: { type: String, default: 'idle' },
    callType: { type: String, default: 'audio' },
    callPartner: { type: Object, default: () => null },
    conversationId: { type: Number, default: null },
    myName: { type: String, default: '' },
    myId: { type: Number, default: 0 },
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
const hasRemoteMedia = ref(false)
const localVideoRef = ref(null)
const remoteVideoRef = ref(null)
const remoteAudioRef = ref(null)

let timerInterval = null
let statusPollInterval = null
let signalPollInterval = null
let currentCallId = ref(null)
let callRole = null
let peerConnection = null
let localStream = null
let remoteDescriptionSet = false

const ICE_SERVERS = [{ urls: 'stun:stun.l.google.com:19302' }]

const callStatusText = computed(() => {
    switch (callState.value) {
        case 'calling': return '正在呼叫...'
        case 'ringing': return '来电...'
        case 'connected': return '通话中'
        case 'ended': return '已结束'
        default: return ''
    }
})

async function startCall(calleeId, type, convId) {
    try {
        const res = await callsApi.call({
            callee_id: calleeId,
            call_type: type,
            conversation_id: convId,
        })
        const data = res.data?.data || {}
        currentCallId.value = data.call_id
        callRole = 'caller'
        callState.value = 'calling'
        startStatusPolling(data.call_id)
        await setupWebRTC('caller')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '呼叫失败')
        callState.value = 'idle'
    }
}

async function answerCall() {
    if (!currentCallId.value) return
    try {
        await callsApi.respond(currentCallId.value, { action: 'accept' })
        callState.value = 'connected'
        startTimer()
        stopStatusPolling()
        callRole = 'callee'
        await setupWebRTC('callee')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '接听失败')
    }
}

async function endCall() {
    stopStatusPolling()
    stopSignalPolling()
    stopTimer()
    cleanupWebRTC()

    if (currentCallId.value) {
        try {
            if (callState.value === 'ringing') {
                await callsApi.respond(currentCallId.value, { action: 'reject' })
            } else {
                await callsApi.end(currentCallId.value)
            }
        } catch { /* ignore */ }
    }

    callState.value = 'ended'
    setTimeout(() => {
        callState.value = 'idle'
        currentCallId.value = null
        callRole = null
        minimized.value = false
        hasRemoteMedia.value = false
        emit('call-ended')
    }, 1500)
}

function startStatusPolling(callId) {
    statusPollInterval = setInterval(async () => {
        try {
            const res = await callsApi.status(callId)
            const status = res.data?.data?.status
            if (status === 'connected') {
                callState.value = 'connected'
                startTimer()
                stopStatusPolling()
            } else if (status === 'rejected') {
                ElMessage.info('对方拒绝了通话')
                await endCall()
            } else if (status === 'ended') {
                ElMessage.info('通话已结束')
                await endCall()
            }
        } catch { stopStatusPolling() }
    }, 2000)
}

function stopStatusPolling() {
    if (statusPollInterval) { clearInterval(statusPollInterval); statusPollInterval = null }
}

function startSignalPolling(types) {
    stopSignalPolling()
    signalPollInterval = setInterval(async () => {
        if (!currentCallId.value) return
        for (const type of types) {
            try {
                const res = await callsApi.signalPoll(currentCallId.value, type)
                const payload = res.data?.data
                if (payload?.data) {
                    await handleRemoteSignal(type, payload.data)
                }
            } catch { /* ignore */ }
        }
    }, 1500)
}

function stopSignalPolling() {
    if (signalPollInterval) { clearInterval(signalPollInterval); signalPollInterval = null }
}

async function setupWebRTC(role) {
    if (!currentCallId.value || typeof RTCPeerConnection === 'undefined') return

    try {
        localStream = await navigator.mediaDevices.getUserMedia({
            audio: true,
            video: props.callType === 'video',
        })
        if (props.callType === 'video' && localVideoRef.value) {
            localVideoRef.value.srcObject = localStream
        }
    } catch (e) {
        console.warn('[Call] getUserMedia failed:', e.message)
    }

    peerConnection = new RTCPeerConnection({ iceServers: ICE_SERVERS })
    remoteDescriptionSet = false

    if (localStream) {
        localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream))
    }

    peerConnection.ontrack = (event) => {
        const stream = event.streams?.[0]
        if (!stream) return
        hasRemoteMedia.value = true
        if (props.callType === 'video' && remoteVideoRef.value) {
            remoteVideoRef.value.srcObject = stream
        } else if (remoteAudioRef.value) {
            remoteAudioRef.value.srcObject = stream
        }
    }

    peerConnection.onicecandidate = (event) => {
        if (event.candidate && currentCallId.value) {
            callsApi.signal(currentCallId.value, {
                type: 'ice_candidate',
                data: event.candidate.toJSON(),
            }).catch(() => {})
        }
    }

    if (role === 'caller') {
        const offer = await peerConnection.createOffer()
        await peerConnection.setLocalDescription(offer)
        await callsApi.signal(currentCallId.value, { type: 'offer', data: offer })
        startSignalPolling(['answer', 'ice_candidate'])
    } else {
        startSignalPolling(['offer', 'ice_candidate'])
    }
}

async function handleRemoteSignal(type, data) {
    if (!peerConnection) return

    if (type === 'offer') {
        if (remoteDescriptionSet) return
        await peerConnection.setRemoteDescription(new RTCSessionDescription(data))
        remoteDescriptionSet = true
        const answer = await peerConnection.createAnswer()
        await peerConnection.setLocalDescription(answer)
        await callsApi.signal(currentCallId.value, { type: 'answer', data: answer })
    } else if (type === 'answer') {
        if (remoteDescriptionSet) return
        await peerConnection.setRemoteDescription(new RTCSessionDescription(data))
        remoteDescriptionSet = true
    } else if (type === 'ice_candidate') {
        try {
            await peerConnection.addIceCandidate(new RTCIceCandidate(data))
        } catch { /* ignore duplicate/stale candidates */ }
    }
}

function cleanupWebRTC() {
    stopSignalPolling()
    if (peerConnection) {
        peerConnection.close()
        peerConnection = null
    }
    if (localStream) {
        localStream.getTracks().forEach(track => track.stop())
        localStream = null
    }
    if (localVideoRef.value) localVideoRef.value.srcObject = null
    if (remoteVideoRef.value) remoteVideoRef.value.srcObject = null
    if (remoteAudioRef.value) remoteAudioRef.value.srcObject = null
    remoteDescriptionSet = false
}

function startTimer() {
    const callStartTime = Date.now()
    timerInterval = setInterval(() => {
        const sec = Math.floor((Date.now() - callStartTime) / 1000)
        const m = Math.floor(sec / 60)
        const s = sec % 60
        callTimer.value = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
    }, 1000)
}

function stopTimer() {
    if (timerInterval) { clearInterval(timerInterval); timerInterval = null }
    callTimer.value = ''
}

function toggleMute() {
    muted.value = !muted.value
    localStream?.getAudioTracks().forEach(track => { track.enabled = !muted.value })
}

function handleIncomingCall(callId, caller, type) {
    currentCallId.value = callId
    callRole = 'callee'
    callState.value = 'ringing'
    startStatusPolling(callId)
}

defineExpose({ startCall, handleIncomingCall })

watch(() => props.callType, () => {
    hasRemoteMedia.value = false
})

onUnmounted(() => {
    stopStatusPolling()
    stopSignalPolling()
    stopTimer()
    cleanupWebRTC()
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
.call-video-area { position: relative; height: 240px; background: #1a1a2e; overflow: hidden; }
.remote-video-el { width: 100%; height: 100%; object-fit: cover; background: #1a1a2e; }
.remote-video-fallback { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; pointer-events: none; }
.remote-avatar-large { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #409eff, #66b1ff); display: flex; align-items: center; justify-content: center; font-size: 36px; font-weight: 700; margin-bottom: 12px; }
.remote-name { font-size: 16px; }
.local-video-el { position: absolute; bottom: 12px; right: 12px; width: 80px; height: 120px; object-fit: cover; border-radius: 8px; border: 2px solid #409eff; background: #2a2a4a; z-index: 2; }
.call-audio-area { text-align: center; padding: 32px 20px; }
.audio-avatar-wrap { margin-bottom: 12px; }
.audio-avatar-large { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, #409eff, #66b1ff); display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 700; color: #fff; margin: 0 auto; }
.audio-name { font-size: 18px; font-weight: 600; margin-bottom: 4px; }
.audio-status { font-size: 13px; color: #909399; margin-bottom: 4px; }
.audio-timer { font-size: 14px; color: #606266; font-weight: 600; font-variant-numeric: tabular-nums; }
.call-controls { display: flex; justify-content: center; gap: 20px; padding: 16px; border-top: 1px solid #f0f0f0; }
</style>
