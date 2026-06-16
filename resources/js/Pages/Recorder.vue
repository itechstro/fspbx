<template>
    <MainLayout>
        <div class="m-3">
            <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
                <template #title>Recorder</template>

                <template #action>
                    <a v-if="permissions.analytics_view" :href="routes.analytics_page"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Analytics
                    </a>

                    <button v-if="!showGlobal && permissions.all_cdr_view" type="button"
                        @click.prevent="handleShowGlobal()"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Show global
                    </button>

                    <button v-if="showGlobal && permissions.all_cdr_view" type="button"
                        @click.prevent="handleShowLocal()"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Show local
                    </button>
                </template>

                <template #filters>
                    <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                        </div>
                        <input type="search" v-model="filterData.search" name="mobile-search-recorder"
                            class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                            placeholder="Search caller or dialed number" @keydown.enter="handleSearchButtonClick" />
                        <input type="search" v-model="filterData.search" name="desktop-search-recorder"
                            class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                            placeholder="Search caller or dialed number" @keydown.enter="handleSearchButtonClick" />
                    </div>

                    <div class="relative z-10 min-w-64 -mt-0.5 mb-2 scale-y-95 shrink-0 sm:mr-4">
                        <DatePicker :dateRange="filterData.dateRange" :timezone="timezone" :clearable="false"
                            @update:date-range="handleUpdateDateRange" />
                    </div>

                    <div v-if="permissions.search_sentiment && permissions.transcription_summary"
                        class="relative min-w-36 mb-2 shrink-0 sm:mr-4">
                        <multiselect v-model="filterData.sentiment" :options="sentimentOptions" :searchable="false"
                            :close-on-select="true" track-by="value" label="name" :show-labels="false"
                            placeholder="Sentiment" aria-label="pick a value">
                        </multiselect>
                    </div>
                </template>

                <template #navigation>
                    <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                        :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page"
                        :links="data.links" @pagination-change-page="renderRequestedPage" />
                </template>

                <template #table-header>
                    <TableColumnHeader v-if="showGlobal" header="Domain"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                        <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('caller_id_name')">
                            <span class="mr-2">Caller ID Name</span>
                            <ChevronUpIcon v-if="sortData.name === 'caller_id_name' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'caller_id_name' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                        </div>
                    </TableColumnHeader>
                    <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                        <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('caller_id_number')">
                            <span class="mr-2">Caller ID Number</span>
                            <ChevronUpIcon v-if="sortData.name === 'caller_id_number' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'caller_id_number' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                        </div>
                    </TableColumnHeader>
                    <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                        <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('caller_destination')">
                            <span class="mr-2">Dialed Number</span>
                            <ChevronUpIcon v-if="sortData.name === 'caller_destination' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'caller_destination' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                        </div>
                    </TableColumnHeader>
                    <TableColumnHeader header="Dialed Name"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                        <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('start_epoch')">
                            <span class="mr-2">Date</span>
                            <ChevronUpIcon v-if="sortData.name === 'start_epoch' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'start_epoch' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                        </div>
                    </TableColumnHeader>
                    <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                        <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('start_epoch')">
                            <span class="mr-2">Time</span>
                        </div>
                    </TableColumnHeader>
                    <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                        <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('duration')">
                            <span class="mr-2">Duration</span>
                            <ChevronUpIcon v-if="sortData.name === 'duration' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'duration' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                        </div>
                    </TableColumnHeader>
                    <TableColumnHeader header="Rec" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Actions"
                        class="w-16 px-1 py-3.5 text-sm font-semibold text-center text-gray-900" />
                </template>

                <template #table-body>
                    <tr v-for="row in data.data" :key="row.xml_cdr_uuid">
                        <TableField v-if="showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.domain?.domain_description">
                            <ejs-tooltip :content="row.domain?.domain_name" position="TopLeft"
                                target="#recorder_domain_tooltip_target">
                                <div id="recorder_domain_tooltip_target">
                                    {{ row.domain?.domain_description }}
                                </div>
                            </ejs-tooltip>
                        </TableField>

                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.caller_id_name_formatted ?? row.caller_id_name" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.caller_id_number_formatted ?? row.caller_id_number" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.caller_destination_formatted ?? row.caller_destination" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.caller_destination_name_formatted" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.start_date" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.start_time" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.duration_formatted" />

                        <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                            <template v-if="row.has_recording" #action-buttons>
                                #action-buttons>
                                <PlayCircleIcon v-if="permissions.call_recording_play"
                                    @click="handleCallRecordingButtonClick(row.xml_cdr_uuid)"
                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                            </template>
                        </TableField>

                        <TableField class="w-16 whitespace-nowrap px-1 py-1 text-sm text-gray-500">
                            <template #action-buttons>
                                <ejs-tooltip v-if="page.props.auth.can.cdr_view_details" :content="'View details'"
                                    position="TopCenter" target="#recorder_view_tooltip_target">
                                    <div id="recorder_view_tooltip_target">
                                        <MagnifyingGlassIcon @click="handleViewRequest(row.xml_cdr_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>
                            </template>
                        </TableField>
                    </tr>
                </template>

                <template #empty>
                    <div v-if="data.data.length === 0" class="text-center my-5 ">
                        <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">No recorder calls found</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Adjust your search and try again.
                        </p>
                    </div>
                </template>

                <template #loading>
                    <Loading :show="loading" />
                </template>

                <template #footer>
                    <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                        :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page"
                        :links="data.links" @pagination-change-page="renderRequestedPage" />
                </template>
            </DataTable>
        </div>
    </MainLayout>

    <CallDetailsModal :show="showDetailsModal" :item="itemOptions?.item" :loading="loadingModal"
        customClass="sm:max-w-4xl" @close="handleModalClose" @success="showNotification" @error="handleErrorResponse" />

    <CallRecordingModal :show="showCallRecordingModal" :cdr_uuid="selectedUuid" :routes="routes"
        @close="showCallRecordingModal = false" @error="handleErrorResponse" @success="showNotification" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import MainLayout from '../Layouts/MainLayout.vue';
import DataTable from './components/general/DataTable.vue';
import TableColumnHeader from './components/general/TableColumnHeader.vue';
import TableField from './components/general/TableField.vue';
import Paginator from './components/general/Paginator.vue';
import DatePicker from './components/general/DatePicker.vue';
import CallDetailsModal from './components/modal/CallDetailsModal.vue';
import CallRecordingModal from './components/modal/CallRecordingModal.vue';
import Notification from './components/notifications/Notification.vue';
import Loading from './components/general/Loading.vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import { TooltipComponent as EjsTooltip } from '@syncfusion/ej2-vue-popups';
import { registerLicense } from '@syncfusion/ej2-base';
import moment from 'moment-timezone';
import {
    ChevronDownIcon,
    ChevronUpIcon,
    PlayCircleIcon,
    MagnifyingGlassIcon,
} from '@heroicons/vue/24/solid';

const page = usePage();
const loading = ref(false);
const showDetailsModal = ref(false);
const showCallRecordingModal = ref(false);
const loadingModal = ref(false);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const selectedUuid = ref(null);
const itemOptions = ref({});

const data = ref({
    data: [],
    prev_page_url: null,
    next_page_url: null,
    from: 0,
    to: 0,
    total: 0,
    current_page: 1,
    last_page: 1,
    links: [],
});

const props = defineProps({
    showGlobal: {
        type: Boolean,
        default: false,
    },
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    routes: Object,
    permissions: Object,
});

const startLocal = moment.utc(props.startPeriod).tz(props.timezone);
const endLocal = moment.utc(props.endPeriod).tz(props.timezone);

const filterData = ref({
    search: null,
    showGlobal: props.showGlobal,
    sentiment: null,
    dateRange: [
        startLocal.clone().startOf('day').toISOString(),
        endLocal.clone().endOf('day').toISOString(),
    ],
});

const sortData = ref({
    name: 'start_epoch',
    order: 'desc',
});

const sentimentOptions = [
    { name: 'Neutral', value: 'neutral' },
    { name: 'Positive', value: 'positive' },
    { name: 'Negative', value: 'negative' },
];

const showGlobal = ref(props.showGlobal);

onMounted(() => {
    getData();
});

const getData = (pageNumber = 1) => {
    loading.value = true;

    let sort = sortData.value.name;
    if (sortData.value.order === 'desc') {
        sort = `-${sort}`;
    }

    axios.get(props.routes.data_route, {
        params: {
            filter: filterData.value,
            page: pageNumber,
            sort,
        },
    })
        .then((response) => {
            data.value = response.data;
        })
        .catch(handleErrorResponse)
        .finally(() => {
            loading.value = false;
        });
};

const handleSearchButtonClick = () => {
    getData();
};

const handleSortRequest = (column) => {
    if (sortData.value.name === column) {
        sortData.value.order = sortData.value.order === 'asc' ? 'desc' : 'asc';
    } else {
        sortData.value.name = column;
        sortData.value.order = column === 'start_epoch' ? 'desc' : 'asc';
    }

    getData(1);
};

const handleFiltersReset = () => {
    filterData.value.dateRange = [
        startLocal.clone().startOf('day').toISOString(),
        endLocal.clone().endOf('day').toISOString(),
    ];
    filterData.value.search = null;
    filterData.value.sentiment = null;
    handleSearchButtonClick();
};

const renderRequestedPage = (url) => {
    const urlObj = new URL(url, window.location.origin);
    const pageParam = urlObj.searchParams.get('page') ?? 1;
    getData(pageParam);
};

const handleUpdateDateRange = (newDateRange) => {
    filterData.value.dateRange = newDateRange;
};

const handleShowGlobal = () => {
    filterData.value.showGlobal = true;
    showGlobal.value = true;
    handleSearchButtonClick();
};

const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    showGlobal.value = false;
    handleSearchButtonClick();
};

const handleCallRecordingButtonClick = (uuid) => {
    showCallRecordingModal.value = true;
    selectedUuid.value = uuid;
};

const handleViewRequest = (itemUuid) => {
    showDetailsModal.value = true;
    loadingModal.value = true;

    axios.post(props.routes.item_options, { item_uuid: itemUuid })
        .then((response) => {
            itemOptions.value = response.data;
        })
        .catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        })
        .finally(() => {
            loadingModal.value = false;
        });
};

const handleModalClose = () => {
    showDetailsModal.value = false;
    itemOptions.value = {};
};

const showNotification = (type, messages) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
};

const hideNotification = () => {
    notificationShow.value = false;
};

const handleErrorResponse = (error) => {
    if (error?.response?.data?.errors) {
        const messages = Object.values(error.response.data.errors).flat();
        showNotification('error', messages);
        return;
    }

    showNotification('error', ['Request failed. Please try again.']);
};

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');
</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>
