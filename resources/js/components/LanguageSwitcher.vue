<template>
    <el-dropdown
        trigger="click"
        placement="bottom-end"
        @command="handleSwitch"
        :aria-label="$t('language.switch_language')"
    >
        <el-button text :aria-haspopup="true">
            <el-icon aria-hidden="true"><ChatRound /></el-icon>
            <span class="ml-1">{{ currentLabel }}</span>
            <el-icon class="el-icon--right" aria-hidden="true"><ArrowDown /></el-icon>
        </el-button>
        <template #dropdown>
            <el-dropdown-menu role="menu">
                <el-dropdown-item
                    v-for="loc in locales"
                    :key="loc.code"
                    :command="loc.code"
                    :class="{ 'is-active': loc.code === currentLocale }"
                    role="menuitemradio"
                    :aria-checked="loc.code === currentLocale"
                >
                    <el-icon
                        v-if="loc.code === currentLocale"
                        color="#0f172a"
                        aria-hidden="true"
                    >
                        <CircleCheck />
                    </el-icon>
                    <span>{{ loc.label }}</span>
                </el-dropdown-item>
            </el-dropdown-menu>
        </template>
    </el-dropdown>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { switchLocale as switchVueI18nLocale } from '@/i18n';
import { refreshDocumentTitle } from '@/utils/resolveDocumentTitle';

const { locale, t } = useI18n();
const router = useRouter();

const currentLocale = ref(locale.value);
const locales = ref([
    { code: 'zh_CN', label: t('language.zh_CN') },
    { code: 'en', label: t('language.en') },
]);

const currentLabel = computed(() => {
    const found = locales.value.find((l) => l.code === currentLocale.value);
    return found ? found.label : currentLocale.value;
});

async function handleSwitch(localeCode) {
    currentLocale.value = localeCode;
    switchVueI18nLocale(localeCode);
    refreshDocumentTitle(router.currentRoute.value);

    try {
        await axios.post('/api/locale/switch', { locale: localeCode });
    } catch (e) {
        // 静默失败，前端切换已生效
    }
}

onMounted(async () => {
    // 从后端获取支持的语言列表
    try {
        const res = await axios.get('/api/locale/supported');
        if (res.data?.locales) {
            locales.value = res.data.locales.map((l) => ({
                code: l.code,
                label: l.native || l.name || t(`language.${l.code}`, l.code),
            }));
        }
        if (res.data?.current) {
            currentLocale.value = res.data.current;
            switchVueI18nLocale(res.data.current);
        }
    } catch (e) {
        // 使用默认语言列表
    }
});
</script>
