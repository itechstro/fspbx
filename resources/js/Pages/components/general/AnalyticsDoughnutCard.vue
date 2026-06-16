<template>
    <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200">
        <h2 class="mb-4 text-sm font-semibold text-gray-900">{{ title }}</h2>
        <div v-if="total === 0" class="py-10 text-center text-sm text-gray-500">
            {{ emptyText }}
        </div>
        <div v-else class="flex flex-col items-center gap-4">
            <div class="h-44 w-44">
                <Doughnut :data="chartData" :options="chartOptions" />
            </div>
            <div class="w-full space-y-1">
                <div v-for="item in items" :key="item.status"
                    class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <span class="inline-block h-2.5 w-2.5 rounded-full"
                            :style="{ backgroundColor: item.color }"></span>
                        <span class="text-gray-700">{{ item.label }}</span>
                    </div>
                    <div class="font-medium text-gray-900">
                        {{ item.count }}
                        <span class="ml-1 text-gray-400">{{ percent(item.count, total) }}%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import {
    ArcElement,
    Chart as ChartJS,
    Tooltip,
} from 'chart.js';

ChartJS.register(ArcElement, Tooltip);

const props = defineProps({
    title: { type: String, required: true },
    breakdown: { type: Array, default: () => [] },
    colors: { type: Object, default: () => ({}) },
    fallbackColors: { type: Array, default: () => ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#6b7280'] },
    emptyText: { type: String, default: 'No data for this period.' },
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
    },
};

const items = computed(() => (props.breakdown ?? []).map((row, index) => ({
    status: row.status,
    label: row.label,
    count: row.count ?? 0,
    color: props.colors[row.status] ?? props.fallbackColors[index % props.fallbackColors.length],
})));

const total = computed(() => items.value.reduce((sum, item) => sum + item.count, 0));

const chartData = computed(() => ({
    labels: items.value.map((item) => item.label),
    datasets: [{
        data: items.value.map((item) => item.count),
        backgroundColor: items.value.map((item) => item.color),
        borderWidth: 0,
    }],
}));

function percent(count, totalCount) {
    if (!totalCount) {
        return '0';
    }

    return Math.round((count / totalCount) * 100);
}
</script>
