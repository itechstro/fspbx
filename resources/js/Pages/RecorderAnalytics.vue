<template>
    <MainLayout>
        <div class="m-3 space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Recorder Analytics</h1>
                    <p class="text-sm text-gray-500">Summary stats and per-call summaries for recorded calls.</p>
                </div>
                <a :href="routes.recorder_page"
                    class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Back to Recorder
                </a>
            </div>

            <div class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="relative min-w-64 flex-1">
                        <label class="block text-xs font-medium text-gray-500">Search</label>
                        <input
                            v-model="filterData.search"
                            type="search"
                            class="mt-1 block w-full rounded-md border-0 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                            placeholder="Caller, dialed number, or summary text"
                            @keydown.enter="loadReport"
                        />
                    </div>
                    <div class="relative z-10 min-w-64">
                        <DatePicker :dateRange="filterData.dateRange" :timezone="timezone" :clearable="false"
                            @update:date-range="handleUpdateDateRange" />
                    </div>
                    <button type="button" @click="loadReport"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        Update Report
                        <Spinner class="ml-2" :show="loading" />
                    </button>
                    <button type="button" @click="exportCsv" :disabled="exporting || !report"
                        class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50">
                        Export CSV
                        <Spinner class="ml-2" :show="exporting" />
                    </button>
                    <button type="button" @click="generateExecutiveSummary"
                        :disabled="generatingExecutiveSummary || !report || !canGenerateExecutiveSummary"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50">
                        Generate Summary
                        <Spinner class="ml-2" :show="generatingExecutiveSummary" />
                    </button>
                </div>
                <p v-if="report?.period_label" class="mt-3 text-sm text-gray-500">{{ report.period_label }}</p>
                <p v-if="filterData.search" class="mt-1 text-sm text-gray-500">
                    Search filter applies to the report, CSV export, executive summary, and emailed reports.
                </p>
                <p v-if="report && !executiveSummaryAvailable" class="mt-2 text-sm text-amber-700">
                    AI executive summary requires an OpenAI API key in server settings.
                </p>
                <p v-else-if="report && !canGenerateExecutiveSummary" class="mt-2 text-sm text-gray-500">
                    Generate Summary needs at least one summarized call in this period.
                </p>
            </div>

            <div v-if="loading && !report" class="rounded-lg bg-white p-12 text-center text-sm text-gray-500 ring-1 ring-gray-200">
                Loading analytics...
            </div>

            <template v-else-if="report">
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                    <StatCard label="Total Calls" :value="report.summary.total_calls" />
                    <StatCard label="Total Duration" :value="report.summary.total_duration" />
                    <StatCard label="Average Duration" :value="report.summary.average_duration" />
                    <StatCard label="Transcribed" :value="report.summary.transcribed_count" />
                    <StatCard label="Summarized" :value="report.summary.summarized_count" />
                </div>

                <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">AI Executive Summary</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Leadership brief across the selected period. Use Generate Summary above, or include it in emailed reports below.
                        </p>
                    </div>

                    <p v-if="!executiveSummaryAvailable" class="mt-4 text-sm text-amber-700">
                        OpenAI API key is not configured on this server.
                    </p>

                    <p v-else-if="!canGenerateExecutiveSummary" class="mt-4 text-sm text-gray-500">
                        No summarized calls are available for this period.
                    </p>

                    <p v-else-if="!executiveSummary" class="mt-4 text-sm text-gray-500">
                        Click Generate Summary to create the brief for this date range.
                    </p>

                    <div v-if="executiveSummary" class="mt-5 space-y-5 text-sm text-gray-700">
                        <div v-if="executiveSummary.overview">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Overview</h3>
                            <p class="whitespace-pre-line">{{ executiveSummary.overview }}</p>
                        </div>

                        <div v-if="executiveSummary.highlights?.length">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Highlights</h3>
                            <ul class="list-disc space-y-1 pl-5">
                                <li v-for="item in executiveSummary.highlights" :key="item">{{ item }}</li>
                            </ul>
                        </div>

                        <div v-if="executiveSummary.concerns?.length">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Concerns</h3>
                            <ul class="list-disc space-y-1 pl-5">
                                <li v-for="item in executiveSummary.concerns" :key="item">{{ item }}</li>
                            </ul>
                        </div>

                        <div v-if="executiveSummary.recommendations?.length">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Recommendations</h3>
                            <ul class="list-disc space-y-1 pl-5">
                                <li v-for="item in executiveSummary.recommendations" :key="item">{{ item }}</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200 lg:col-span-2">
                        <h2 class="mb-4 text-sm font-semibold text-gray-900">Calls Per Day</h2>
                        <div v-if="!report.calls_by_day?.length" class="py-10 text-center text-sm text-gray-500">
                            No calls in this period.
                        </div>
                        <div v-else class="h-56">
                            <Bar :data="callsByDayChartData" :options="callsByDayOptions" />
                        </div>
                    </div>

                    <AnalyticsDoughnutCard
                        title="Transcription Status"
                        :breakdown="report.transcription_status_breakdown"
                        :colors="transcriptionStatusColors"
                        empty-text="No transcription data for this period."
                    />
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <AnalyticsDoughnutCard
                        title="Summary Status"
                        :breakdown="report.summary_status_breakdown"
                        :colors="summaryStatusColors"
                        empty-text="No summary data for this period."
                    />

                    <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200">
                        <h2 class="mb-4 text-sm font-semibold text-gray-900">Sentiment</h2>
                        <div v-if="sentimentTotal === 0" class="py-10 text-center text-sm text-gray-500">
                            No sentiment data for this period.
                        </div>
                        <div v-else class="flex flex-col items-center gap-4">
                            <div class="h-44 w-44">
                                <Doughnut :data="sentimentChartData" :options="doughnutOptions" />
                            </div>
                            <div class="w-full space-y-1">
                                <div v-for="item in sentimentBreakdown" :key="item.label"
                                    class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block h-2.5 w-2.5 rounded-full"
                                            :style="{ backgroundColor: item.color }"></span>
                                        <span class="text-gray-700">{{ item.label }}</span>
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ item.count }}
                                        <span class="ml-1 text-gray-400">{{ percent(item.count, sentimentTotal) }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200">
                        <h2 class="mb-4 text-sm font-semibold text-gray-900">Top Topics</h2>
                        <div v-if="!report.top_topics?.length" class="py-10 text-center text-sm text-gray-500">
                            No summary topics for this period.
                        </div>
                        <ol v-else class="space-y-2 text-sm">
                            <li v-for="(topic, index) in report.top_topics" :key="topic.label"
                                class="flex items-start justify-between gap-3 border-b border-gray-100 pb-2 last:border-0">
                                <div class="flex items-start gap-2 text-gray-700">
                                    <span class="mt-0.5 text-xs font-semibold text-gray-400">{{ index + 1 }}.</span>
                                    <span>{{ topic.label }}</span>
                                </div>
                                <span class="shrink-0 font-medium text-gray-900">{{ topic.count }}</span>
                            </li>
                        </ol>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200">
                    <h2 class="mb-4 text-sm font-semibold text-gray-900">Recorded Calls</h2>
                    <div v-if="report.calls.length === 0" class="py-10 text-center text-sm text-gray-500">
                        No recorder calls found for this period<span v-if="filterData.search"> matching your search</span>.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                                    <th class="px-2 py-2">Date</th>
                                    <th class="px-2 py-2">Caller</th>
                                    <th class="px-2 py-2">Dialed</th>
                                    <th class="px-2 py-2">Duration</th>
                                    <th class="px-2 py-2">Sentiment</th>
                                    <th class="px-2 py-2">Summary</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="call in report.calls" :key="call.xml_cdr_uuid" class="border-b border-gray-100 align-top">
                                    <td class="px-2 py-3 whitespace-nowrap">
                                        <div>{{ call.date }}</div>
                                        <div class="text-xs text-gray-500">{{ call.time }}</div>
                                    </td>
                                    <td class="px-2 py-3">{{ call.caller || '—' }}</td>
                                    <td class="px-2 py-3">{{ call.dialed || '—' }}</td>
                                    <td class="px-2 py-3 whitespace-nowrap">{{ call.duration }}</td>
                                    <td class="px-2 py-3 whitespace-nowrap">{{ call.sentiment || '—' }}</td>
                                    <td class="px-2 py-3 text-gray-700">{{ call.summary || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <div v-if="permissions.schedule" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200">
                    <h2 class="mb-1 text-sm font-semibold text-gray-900">Email Report Now</h2>
                    <p class="mb-4 text-sm text-gray-500">Send the current date range and search filter to one or more recipients. Emails include a CSV attachment.</p>
                    <label class="block text-sm font-medium text-gray-700">Recipients</label>
                    <textarea v-model="sendEmailsText" rows="3"
                        class="mt-1 block w-full rounded-md border-0 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                        placeholder="email1@example.com, email2@example.com"></textarea>
                    <label v-if="executiveSummaryAvailable" class="mt-4 inline-flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="sendIncludeExecutiveSummary" type="checkbox"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                        Include AI executive summary
                    </label>
                    <button type="button" @click="sendReportNow" :disabled="sending"
                        class="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50">
                        Send Report
                        <Spinner class="ml-2" :show="sending" />
                    </button>
                </div>

                <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200">
                    <h2 class="mb-1 text-sm font-semibold text-gray-900">Scheduled Email</h2>
                    <p class="mb-4 text-sm text-gray-500">
                        Daily uses yesterday. Weekly uses the last 7 days ending yesterday. Monthly uses the previous calendar month.
                        The saved search filter below applies to each scheduled report.
                    </p>

                    <div class="space-y-4">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="schedule.enabled" type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                            Enable scheduled report
                        </label>

                        <label v-if="executiveSummaryAvailable" class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="schedule.include_executive_summary" type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                            Include AI executive summary
                        </label>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Recipients</label>
                            <textarea v-model="scheduleEmailsText" rows="3"
                                class="mt-1 block w-full rounded-md border-0 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                                placeholder="email1@example.com, email2@example.com"></textarea>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Frequency</label>
                                <select v-model="schedule.frequency"
                                    class="mt-1 block w-full rounded-md border-0 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Send Time</label>
                                <input v-model="schedule.send_time" type="time"
                                    class="mt-1 block w-full rounded-md border-0 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600" />
                            </div>
                        </div>

                        <div v-if="schedule.frequency === 'weekly'">
                            <label class="block text-sm font-medium text-gray-700">Day of Week</label>
                            <select v-model.number="schedule.day_of_week"
                                class="mt-1 block w-full rounded-md border-0 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600">
                                <option v-for="day in weekDays" :key="day.value" :value="day.value">{{ day.label }}</option>
                            </select>
                        </div>

                        <div v-if="schedule.frequency === 'monthly'">
                            <label class="block text-sm font-medium text-gray-700">Day of Month</label>
                            <select v-model.number="schedule.day_of_month"
                                class="mt-1 block w-full rounded-md border-0 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600">
                                <option v-for="day in 28" :key="day" :value="day">{{ day }}</option>
                            </select>
                        </div>

                        <button type="button" @click="saveSchedule" :disabled="savingSchedule"
                            class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50">
                            Save Schedule
                            <Spinner class="ml-2" :show="savingSchedule" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
            @update:show="hideNotification" />
    </MainLayout>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from 'vue';
import moment from 'moment-timezone';
import { Doughnut, Bar } from 'vue-chartjs';
import { ArcElement, BarElement, CategoryScale, Chart as ChartJS, Legend, LinearScale, Tooltip } from 'chart.js';
import MainLayout from '../Layouts/MainLayout.vue';
import DatePicker from './components/general/DatePicker.vue';
import AnalyticsDoughnutCard from './components/general/AnalyticsDoughnutCard.vue';
import Spinner from './components/general/Spinner.vue';
import Notification from './components/notifications/Notification.vue';

ChartJS.register(ArcElement, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const StatCard = defineComponent({
    props: {
        label: String,
        value: [String, Number],
    },
    setup(props) {
        return () => h('div', { class: 'rounded-lg bg-white p-4 ring-1 ring-gray-200' }, [
            h('div', { class: 'text-xs uppercase tracking-wide text-gray-500' }, props.label),
            h('div', { class: 'mt-2 text-2xl font-semibold text-gray-900' }, props.value ?? '—'),
        ]);
    },
});

const props = defineProps({
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    routes: Object,
    permissions: Object,
    executiveSummaryAvailable: Boolean,
});

const loading = ref(false);
const exporting = ref(false);
const generatingExecutiveSummary = ref(false);
const sending = ref(false);
const savingSchedule = ref(false);
const report = ref(null);
const executiveSummary = ref(null);
const notificationShow = ref(false);
const notificationType = ref(null);
const notificationMessages = ref('');

const startLocal = moment.utc(props.startPeriod).tz(props.timezone);
const endLocal = moment.utc(props.endPeriod).tz(props.timezone);

const filterData = ref({
    dateRange: [
        startLocal.clone().startOf('day').toISOString(),
        endLocal.clone().endOf('day').toISOString(),
    ],
    search: '',
});

const schedule = ref({
    enabled: false,
    include_executive_summary: false,
    emails: [],
    frequency: 'weekly',
    send_time: '08:00',
    day_of_week: 1,
    day_of_month: 1,
});

const sendEmailsText = ref('');
const scheduleEmailsText = ref('');
const sendIncludeExecutiveSummary = ref(false);

const weekDays = [
    { value: 0, label: 'Sunday' },
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
];

const sentimentColors = {
    positive: '#16a34a',
    neutral: '#6b7280',
    negative: '#dc2626',
    unknown: '#d1d5db',
};

const transcriptionStatusColors = {
    none: '#d1d5db',
    pending: '#f59e0b',
    queued: '#0ea5e9',
    in_progress: '#6366f1',
    completed: '#16a34a',
    failed: '#dc2626',
    other: '#6b7280',
};

const summaryStatusColors = {
    summarized: '#16a34a',
    not_summarized: '#d1d5db',
};

const formatChartDate = (date) => moment(date, 'YYYY-MM-DD').format('MMM D');

const callsByDayChartData = computed(() => ({
    labels: (report.value?.calls_by_day ?? []).map((item) => formatChartDate(item.date)),
    datasets: [{
        label: 'Calls',
        data: (report.value?.calls_by_day ?? []).map((item) => item.count),
        backgroundColor: '#4f46e5',
        borderRadius: 4,
        maxBarThickness: 32,
    }],
}));

const callsByDayOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
    },
    scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, ticks: { precision: 0 } },
    },
};

const sentimentBreakdown = computed(() => {
    const sentiment = report.value?.summary?.sentiment ?? {};
    return [
        { label: 'Positive', value: 'positive', count: sentiment.positive ?? 0, color: sentimentColors.positive },
        { label: 'Neutral', value: 'neutral', count: sentiment.neutral ?? 0, color: sentimentColors.neutral },
        { label: 'Negative', value: 'negative', count: sentiment.negative ?? 0, color: sentimentColors.negative },
        { label: 'Unknown', value: 'unknown', count: sentiment.unknown ?? 0, color: sentimentColors.unknown },
    ];
});

const sentimentTotal = computed(() => sentimentBreakdown.value.reduce((sum, item) => sum + item.count, 0));

const canGenerateExecutiveSummary = computed(() => {
    return props.executiveSummaryAvailable && (report.value?.summary?.summarized_count ?? 0) > 0;
});

const sentimentChartData = computed(() => ({
    labels: sentimentBreakdown.value.map((item) => item.label),
    datasets: [{
        data: sentimentBreakdown.value.map((item) => item.count),
        backgroundColor: sentimentBreakdown.value.map((item) => item.color),
        borderWidth: 0,
    }],
}));

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
    },
};

const percent = (count, total) => {
    if (!total) {
        return '0';
    }

    return Math.round((count / total) * 100);
};

const showNotification = (type, messages = null) => {
    notificationType.value = type;
    notificationMessages.value = messages ?? {};
    notificationShow.value = true;
};

const hideNotification = () => {
    notificationShow.value = false;
};

const handleErrorResponse = (error) => {
    if (error?.response?.data?.errors) {
        showNotification('error', error.response.data.errors);
        return;
    }

    showNotification('error', { error: ['Request failed. Please try again.'] });
};

const parseEmails = (value) => {
    return String(value || '')
        .split(/[\s,;]+/)
        .map((email) => email.trim())
        .filter(Boolean);
};

const loadReport = () => {
    loading.value = true;
    executiveSummary.value = null;

    axios.get(props.routes.report_route, {
        params: {
            filter: filterData.value,
        },
    })
        .then((response) => {
            report.value = response.data;
        })
        .catch(handleErrorResponse)
        .finally(() => {
            loading.value = false;
        });
};

const generateExecutiveSummary = () => {
    if (!props.executiveSummaryAvailable) {
        showNotification('error', { executive_summary: ['OpenAI API key is not configured on this server.'] });
        return;
    }

    if (!canGenerateExecutiveSummary.value) {
        showNotification('error', { executive_summary: ['No summarized calls are available for this period.'] });
        return;
    }

    generatingExecutiveSummary.value = true;

    axios.post(props.routes.executive_summary_route, {
        filter: filterData.value,
    })
        .then((response) => {
            executiveSummary.value = response.data.executive_summary ?? null;
        })
        .catch(handleErrorResponse)
        .finally(() => {
            generatingExecutiveSummary.value = false;
        });
};

const exportCsv = () => {
    exporting.value = true;

    axios.get(props.routes.export_route, {
        params: {
            filter: filterData.value,
        },
        responseType: 'blob',
    })
        .then((response) => {
            const disposition = response.headers['content-disposition'] || '';
            const match = disposition.match(/filename="(.+)"/);
            const filename = match?.[1] || 'recorder-analytics.csv';
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', filename);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        })
        .catch(handleErrorResponse)
        .finally(() => {
            exporting.value = false;
        });
};

const loadSchedule = () => {
    if (!props.permissions?.schedule) {
        return;
    }

    axios.get(props.routes.schedule_route)
        .then((response) => {
            const row = response.data.schedule ?? {};
            schedule.value = {
                enabled: !!row.enabled,
                include_executive_summary: !!row.include_executive_summary,
                emails: row.emails ?? [],
                frequency: row.frequency ?? 'weekly',
                send_time: row.send_time ?? '08:00',
                day_of_week: row.day_of_week ?? 1,
                day_of_month: row.day_of_month ?? 1,
            };
            if (row.search) {
                filterData.value.search = row.search;
            }
            scheduleEmailsText.value = (row.emails ?? []).join(', ');
            if (!sendEmailsText.value) {
                sendEmailsText.value = scheduleEmailsText.value;
            }
        })
        .catch(handleErrorResponse);
};

const sendReportNow = () => {
    const emails = parseEmails(sendEmailsText.value);
    if (!emails.length) {
        showNotification('error', { emails: ['Add at least one email address.'] });
        return;
    }

    sending.value = true;

    axios.post(props.routes.send_route, {
        emails,
        include_executive_summary: sendIncludeExecutiveSummary.value,
        filter: filterData.value,
    })
        .then((response) => {
            showNotification('success', response.data.messages ?? { success: ['Report email queued.'] });
        })
        .catch(handleErrorResponse)
        .finally(() => {
            sending.value = false;
        });
};

const saveSchedule = () => {
    savingSchedule.value = true;

    axios.put(props.routes.schedule_route, {
        ...schedule.value,
        emails: parseEmails(scheduleEmailsText.value),
        search: filterData.value.search || null,
    })
        .then((response) => {
            showNotification('success', response.data.messages ?? { success: ['Scheduled report settings saved.'] });
            const row = response.data.schedule ?? {};
            scheduleEmailsText.value = (row.emails ?? []).join(', ');
        })
        .catch(handleErrorResponse)
        .finally(() => {
            savingSchedule.value = false;
        });
};

const handleUpdateDateRange = (newDateRange) => {
    filterData.value.dateRange = newDateRange;
};

onMounted(() => {
    loadReport();
    loadSchedule();
});
</script>
