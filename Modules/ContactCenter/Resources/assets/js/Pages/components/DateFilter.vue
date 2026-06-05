<template>
    <div class="d-flex">
        <VueDatePicker v-model="dateRange" :range="true" :multi-calendars="{ static: false }"
            @update:modelValue="onDateChange" :preset-dates="presetDates">
            <template #preset-date-range-button="{ label, value, presetDate }">
                <span role="button" :tabindex="0" @click="presetDate(value)" @keyup.enter.prevent="presetDate(value)"
                    @keyup.space.prevent="presetDate(value)">
                    {{ label }}
                </span>
            </template>
        </VueDatePicker>
    </div>
</template>
  
<script setup>
import { ref, defineEmits, onMounted } from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import {
    startOfDay, endOfDay,
    startOfWeek, endOfWeek,
    subDays,
    startOfMonth, endOfMonth,
    subMonths
} from 'date-fns';

const props = defineProps({
    filterData: Object // 
});

// Initial date range
const dateRange = ref();
dateRange.value = props.filterData.dateRange;

const today = new Date();

const presetDates = ref([
    { label: 'Today', value: [startOfDay(today), endOfDay(today)] },
    { label: 'This Week', value: [startOfWeek(today), endOfWeek(today)] },
    { label: 'Past 7 Days', value: [subDays(today, 6), today] },
    { label: 'Past 30 Days', value: [subDays(today, 29), today] },
    { label: 'This Month', value: [startOfMonth(today), endOfMonth(today)] },
    { label: 'Last Month', value: [startOfMonth(subMonths(today, 1)), endOfMonth(subMonths(today, 1))] }
]);

// console.log(dateRange);

const emit = defineEmits(['date-range-selected']);

// Method to handle date changes
const onDateChange = (newDateRange) => {
    emit('update:dateRange', newDateRange);
};
</script>
  
  