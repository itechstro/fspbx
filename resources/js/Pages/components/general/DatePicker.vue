<template>
    <VueDatePicker v-model="dateRange" :range="true" :multi-calendars="{ static: false }" :preset-dates="presetDates"
        :enable-time-picker="false" :week-start="weekStart" :format="displayFormat" :locale="displayLocale"
        auto-apply @update:model-value="handleDate" :timezone="timezone">
        <template #preset-date-range-button="{ label, value, presetDate }">
            <span role="button" :tabindex="0" @click="presetDate(value)" @keyup.enter.prevent="presetDate(value)"
                @keyup.space.prevent="presetDate(value)">
                {{ label }}
            </span>
        </template>
    </VueDatePicker>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import VueDatePicker from '@vuepic/vue-datepicker';
import {
    startOfDay, endOfDay,
    startOfWeek, endOfWeek,
    subDays,
    startOfMonth, endOfMonth,
    subMonths
} from 'date-fns';
import {
    enUS,
    enGB,
    enAU,
    enCA,
    enIN,
    enNZ,
    enIE,
    enZA,
    de,
    deAT,
    fr,
    frCA,
    es,
    it,
    nl,
    nlBE,
    pt,
    ptBR,
    pl,
    sv,
    nb,
    da,
    fi,
    ja,
    ko,
    zhCN,
    zhTW,
    th,
    vi,
    id,
    he,
} from 'date-fns/locale';

/** BCP47 tags from DomainPresentationService; fall back when date-fns has no regional pack. */
const dateFnsLocales = {
    'en-US': enUS,
    'en-GB': enGB,
    'en-AU': enAU,
    'en-CA': enCA,
    'en-SG': enGB,
    'en-MY': enGB,
    'en-IN': enIN,
    'en-NZ': enNZ,
    'en-IE': enIE,
    'en-PH': enUS,
    'en-HK': enGB,
    'en-ZA': enZA,
    'en-AE': enGB,
    'de-DE': de,
    'de-AT': deAT,
    'de-CH': de,
    'fr-FR': fr,
    'fr-CA': frCA,
    'es-ES': es,
    'es-MX': es,
    'it-IT': it,
    'nl-NL': nl,
    'nl-BE': nlBE,
    'pt-PT': pt,
    'pt-BR': ptBR,
    'pl-PL': pl,
    'sv-SE': sv,
    'nb-NO': nb,
    'da-DK': da,
    'fi-FI': fi,
    'ja-JP': ja,
    'ko-KR': ko,
    'zh-CN': zhCN,
    'zh-TW': zhTW,
    'th-TH': th,
    'vi-VN': vi,
    'id-ID': id,
    'he-IL': he,
};

const props = defineProps({
    dateRange: Array,
    timezone: String,
});

const page = usePage();

const displayFormat = computed(() => page.props.presentation?.datepicker_format ?? 'MM/dd/yyyy');
const displayLocale = computed(() => {
    const key = page.props.presentation?.locale ?? 'en-US';
    if (dateFnsLocales[key]) {
        return dateFnsLocales[key];
    }
    if (key.startsWith('en-')) {
        return enGB;
    }
    return enUS;
});
const weekStart = computed(() => (page.props.presentation?.country === 'US' ? 0 : 1));

// Initial date range
const dateRange = ref();
dateRange.value = props.dateRange;
// Watch for changes in the dateRange prop and update the local dateRange state
watch(() => props.dateRange, (newDateRange) => {
    dateRange.value = [...newDateRange]; // Create a new array to ensure reactivity
});

const presetDates = computed(() => {
    const weekStartsOn = weekStart.value;
    const today = new Date();

    return [
    { label: 'Today', value: [startOfDay(today), endOfDay(today)] },
    { label: 'This Week', value: [startOfWeek(startOfDay(today), { weekStartsOn }), endOfWeek(endOfDay(today), { weekStartsOn })] },
    { label: 'Past 7 Days', value: [subDays(startOfDay(today), 6), endOfDay(today)] },
    { label: 'Past 30 Days', value: [subDays(startOfDay(today), 29), endOfDay(today)] },
    { label: 'This Month', value: [startOfMonth(startOfDay(today)), endOfMonth(endOfDay(today))] },
    { label: 'Last Month', value: [startOfMonth(subMonths(startOfDay(today), 1)), endOfMonth(subMonths(endOfDay(today), 1))] }
    ];
});

const emit = defineEmits(['update:dateRange']);

const handleDate = (modelData) => {
    emit('update:dateRange', dateRange.value);
}

</script>

<style>
@import '@vuepic/vue-datepicker/dist/main.css';
</style>
