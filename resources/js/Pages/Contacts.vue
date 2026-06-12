<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>{{ speedDialMode ? 'Speed Dial' : 'Contacts' }}</template>

            <template #subtitle>
                <span v-if="speedDialMode">
                    Edit a row to change the speed-dial code on the Phones tab, or assigned users on the Visibility tab.
                </span>
                <span v-else>
                    Manage the domain phonebook on the legacy contact records used by provisioning and speed dial.
                </span>
            </template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-contacts"
                        id="mobile-search-contacts"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                    <input type="text" v-model="filterData.search" name="desktop-search-contacts"
                        id="desktop-search-contacts"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                </div>
            </template>

            <template #action>
                <a v-if="speedDialMode" :href="routes.contacts"
                    class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    All Contacts
                </a>
                <a v-else :href="routes.speed_dial"
                    class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Speed Dial
                </a>

                <template v-if="speedDialMode">
                    <button v-if="permissions.upload" type="button" @click.prevent="openUploadModal('speed-dial')"
                        class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        <DocumentArrowUpIcon class="h-5 w-5" aria-hidden="true" />
                        Upload CSV
                    </button>

                    <button type="button" @click.prevent="handleExportSpeedDial"
                        class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        <DocumentArrowDownIcon class="h-5 w-5" aria-hidden="true" />
                        Export
                    </button>
                </template>

                <template v-else>
                    <button v-if="permissions.upload" type="button" @click.prevent="openUploadModal('csv')"
                        class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        <DocumentArrowUpIcon class="h-5 w-5" aria-hidden="true" />
                        Upload CSV
                    </button>

                    <button v-if="permissions.upload" type="button" @click.prevent="openUploadModal('vcard')"
                        class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        <DocumentArrowUpIcon class="h-5 w-5" aria-hidden="true" />
                        Upload vCard
                    </button>

                    <button type="button" @click.prevent="handleExportCsv"
                        class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        <DocumentArrowDownIcon class="h-5 w-5" aria-hidden="true" />
                        Export CSV
                    </button>

                    <button type="button" @click.prevent="handleExportVcard"
                        class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        <DocumentArrowDownIcon class="h-5 w-5" aria-hidden="true" />
                        Export vCard
                    </button>
                </template>

                <button v-if="permissions.sync_connect || permissions.sync_run" type="button"
                    @click.prevent="showSyncModal = true"
                    class="ml-2 sm:ml-4 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Sync
                </button>

                <button v-if="permissions.create" type="button" @click.prevent="handleCreateButtonClick"
                    class="ml-2 sm:ml-4 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Create
                </button>

                <button v-if="!speedDialMode && filterData.showGlobal && permissions.view_global" type="button"
                    @click.prevent="handleShowLocal"
                    class="ml-2 sm:ml-4 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Show local
                </button>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>

            <template #table-header>
                <template v-if="speedDialMode">
                    <TableColumnHeader
                        class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                        <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                        <div class="pl-4 flex items-center cursor-pointer select-none"
                            @click="handleSortRequest('contact_organization')">
                            <span class="mr-2">Speed Dial Name</span>
                            <ChevronUpIcon v-if="sortData.name === 'contact_organization' && sortData.order === 'asc'"
                                class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'contact_organization' && sortData.order === 'desc'"
                                class="h-4 w-4 text-gray-500" />
                        </div>
                    </TableColumnHeader>

                    <TableColumnHeader header="Destination Number"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Speed Dial Code"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Assigned User"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
                </template>

                <template v-else>
                    <TableColumnHeader
                        class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                        <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                        <div class="pl-4 flex items-center cursor-pointer select-none"
                            @click="handleSortRequest('contact_name_given')">
                            <span class="mr-2">Name</span>
                            <ChevronUpIcon v-if="sortData.name === 'contact_name_given' && sortData.order === 'asc'"
                                class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'contact_name_given' && sortData.order === 'desc'"
                                class="h-4 w-4 text-gray-500" />
                        </div>
                    </TableColumnHeader>

                    <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                        <div class="flex items-center cursor-pointer select-none"
                            @click="handleSortRequest('contact_organization')">
                            <span class="mr-2">Organization</span>
                            <ChevronUpIcon v-if="sortData.name === 'contact_organization' && sortData.order === 'asc'"
                                class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'contact_organization' && sortData.order === 'desc'"
                                class="h-4 w-4 text-gray-500" />
                        </div>
                    </TableColumnHeader>

                    <TableColumnHeader header="Type" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Title" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Category" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Primary Phone" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
                </template>
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td :colspan="speedDialMode ? 6 : 7">
                    <div class="text-sm text-center m-2">
                        <span class="font-semibold">{{ selectedItems.length }}</span> items are selected.
                        <button v-if="!selectAll && selectedItems.length !== data.total"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200 hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleSelectAll">
                            Select all {{ data.total }} items
                        </button>
                        <button v-if="selectAll"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200 hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleClearSelection">
                            Clear selection
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.contact_uuid">
                    <template v-if="speedDialMode">
                        <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                            <div class="flex items-center">
                                <input v-model="selectedItems" type="checkbox" name="action_box[]"
                                    :value="row.contact_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                                <div class="ml-4"
                                    :class="{ 'cursor-pointer hover:text-gray-900': permissions.update }"
                                    @click="permissions.update && handleEditButtonClick(row.contact_uuid)">
                                    {{ speedDialName(row) }}
                                </div>
                            </div>
                        </TableField>

                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                            <span v-if="row.primary_phone">
                                {{ row.primary_phone.phone_number_formatted || row.primary_phone.phone_number }}
                            </span>
                        </TableField>

                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                            <span v-if="row.primary_phone">{{ row.primary_phone.phone_speed_dial }}</span>
                        </TableField>

                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                            <div v-if="row.contact_users?.length" class="flex flex-wrap gap-1">
                                <Badge v-for="assignment in row.contact_users" :key="assignment.contact_user_uuid"
                                    :text="assignedUserLabel(assignment)" backgroundColor="bg-gray-100"
                                    textColor="text-gray-700" ringColor="ring-gray-400/20"
                                    class="px-2 py-1 text-xs font-semibold" />
                            </div>
                        </TableField>
                    </template>

                    <template v-else>
                        <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                            <div class="flex items-center">
                                <input v-model="selectedItems" type="checkbox" name="action_box[]"
                                    :value="row.contact_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                                <div class="ml-4"
                                    :class="{ 'cursor-pointer hover:text-gray-900': permissions.update }"
                                    @click="permissions.update && handleEditButtonClick(row.contact_uuid)">
                                    {{ displayName(row) }}
                                </div>
                            </div>
                        </TableField>

                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.contact_organization" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500 capitalize"
                            :text="row.contact_type" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.contact_title" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.contact_category" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                            <span v-if="row.primary_phone">
                                <span v-if="row.primary_phone.phone_label" class="text-gray-400 mr-1">
                                    {{ row.primary_phone.phone_label }}:
                                </span>
                                {{ row.primary_phone.phone_number }}
                            </span>
                        </TableField>
                    </template>

                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap justify-end">
                                <PencilSquareIcon v-if="permissions.update"
                                    @click="handleEditButtonClick(row.contact_uuid)"
                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                    title="Edit" />

                                <TrashIcon v-if="permissions.destroy"
                                    @click="handleSingleItemDeleteRequest(row.contact_uuid)"
                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                    title="Delete" />
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="data.data.length === 0" class="text-center my-5">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                    <p class="mt-1 text-sm text-gray-500">Adjust your search and try again.</p>
                </div>
            </template>

            <template #loading>
                <Loading :show="loading" />
            </template>

            <template #footer>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>
        </DataTable>
    </div>

    <ConfirmationModal :show="confirmationModalTrigger" @close="confirmationModalTrigger = false"
        @confirm="confirmAction" :header="confirmationHeader" :text="confirmationText"
        :confirm-button-label="confirmationButtonLabel" cancel-button-label="Cancel" />

    <ContactForm :show="showForm" :options="itemOptions" :mode="formMode" :loading="loadingForm"
        :header="formHeader" :initial-tab="speedDialMode ? 'phones' : 'profile'" @close="handleFormClose"
        @error="handleErrorResponse" @success="showNotification" @refresh-data="refreshCurrentPage" />

    <UploadModal :show="showUploadModal" :header="uploadModalHeader"
        :show-template-download="uploadFormat === 'csv' || uploadFormat === 'speed-dial'"
        :template-label="uploadFormat === 'speed-dial' ? 'Download speed dial template' : 'Download CSV template'"
        @close="closeUploadModal" @upload="uploadFile"
        @download-template="uploadFormat === 'speed-dial' ? downloadSpeedDialTemplate : downloadCsvTemplate"
        :is-submitting="isUploadingFile" :errors="uploadErrors" />

    <ContactSyncModal :show="showSyncModal" :routes="routes" :permissions="permissions" @close="showSyncModal = false"
        @success="showNotification" @error="handleErrorResponse" @synced="refreshCurrentPage" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import axios from "axios";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import Notification from "./components/notifications/Notification.vue";
import ContactForm from "./components/forms/ContactForm.vue";
import ContactSyncModal from "./components/modal/ContactSyncModal.vue";
import UploadModal from "./components/modal/UploadModal.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import { ChevronDownIcon, ChevronUpIcon, MagnifyingGlassIcon, PencilSquareIcon, TrashIcon } from "@heroicons/vue/24/solid";
import { DocumentArrowDownIcon, DocumentArrowUpIcon } from "@heroicons/vue/24/outline";
import Badge from "@generalComponents/Badge.vue";

const props = defineProps({
    routes: Object,
    permissions: Object,
    speedDialMode: {
        type: Boolean,
        default: false,
    },
    openSyncModal: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();

const speedDialMode = computed(() => props.speedDialMode);

const loading = ref(false);
const currentPage = ref(1);
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const confirmationModalTrigger = ref(false);
const confirmAction = ref(null);
const confirmationHeader = ref("Are you sure?");
const confirmationText = ref("");
const confirmationButtonLabel = ref("Continue");
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);
const showForm = ref(false);
const formMode = ref("create");
const loadingForm = ref(false);
const showUploadModal = ref(false);
const showSyncModal = ref(false);
const uploadFormat = ref("csv");
const isUploadingFile = ref(false);
const uploadErrors = ref(null);
const itemOptions = ref({
    item: {},
    contact_types: [],
    permissions: {},
    routes: {},
});

const routes = props.routes;
const permissions = props.permissions;

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

const filterData = ref({
    search: null,
    showGlobal: false,
    speedDial: props.speedDialMode,
});

const sortData = ref({
    name: "contact_organization",
    order: "asc",
});

const bulkActions = computed(() => {
    const actions = [];

    if (permissions.destroy) {
        actions.push({ id: "bulk_delete", label: "Delete", icon: "TrashIcon" });
    }

    return actions;
});

const uploadModalHeader = computed(() => {
    if (uploadFormat.value === "vcard") {
        return "Upload vCard";
    }

    if (uploadFormat.value === "speed-dial") {
        return "Upload Speed Dial CSV";
    }

    return "Upload CSV";
});

const formHeader = computed(() => {
    const label = speedDialMode.value ? "Speed Dial" : "Contact";

    if (formMode.value === "create") {
        return `Create ${label}`;
    }

    const item = itemOptions.value?.item;
    const name = speedDialMode.value ? speedDialName(item) : displayName(item);

    return `Update ${label}${name ? ` - ${name}` : ""}`;
});

onMounted(() => {
    getData();

    if (props.openSyncModal) {
        showSyncModal.value = true;
    }

    if (page.props.flash?.message) {
        showNotification("success", { success: [page.props.flash.message] });
    }

    if (page.props.flash?.error) {
        showNotification("error", { error: [page.props.flash.error] });
    }
});

const displayName = (row) => {
    if (!row) {
        return "";
    }

    if (row.display_name) {
        return row.display_name;
    }

    const name = [row.contact_name_given, row.contact_name_family].filter(Boolean).join(" ").trim();

    return name || row.contact_organization || "";
};

const speedDialName = (row) => {
    if (!row) {
        return "";
    }

    return row.contact_organization || displayName(row);
};

const assignedUserLabel = (assignment) => {
    if (!assignment) {
        return "";
    }

    return assignment.user?.name_formatted || assignment.user?.username || assignment.label || "";
};

const handleSortRequest = (column) => {
    if (sortData.value.name === column) {
        sortData.value.order = sortData.value.order === "asc" ? "desc" : "asc";
    } else {
        sortData.value.name = column;
        sortData.value.order = "asc";
    }

    getData(currentPage.value);
};

const getData = (page = 1) => {
    loading.value = true;
    currentPage.value = Number(page) || 1;

    let sort = sortData.value.name;
    if (sortData.value.order === "desc") {
        sort = `-${sort}`;
    }

    axios.get(routes.data_route, {
        params: {
            filter: filterData.value,
            page: currentPage.value,
            sort,
        },
    })
        .then((response) => {
            data.value = response.data;
            currentPage.value = response.data.current_page ?? currentPage.value;
        })
        .catch(handleErrorResponse)
        .finally(() => {
            loading.value = false;
        });
};

const handleSearchButtonClick = () => {
    getData(1);
};

const refreshCurrentPage = () => {
    getData(currentPage.value);
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    getData(1);
};

const renderRequestedPage = (url) => {
    if (!url) return;

    const urlObj = new URL(url, window.location.origin);
    const pageParam = urlObj.searchParams.get("page") ?? 1;
    getData(pageParam);
};

const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    getData(1);
};

const openUploadModal = (format) => {
    uploadFormat.value = format;
    uploadErrors.value = null;
    showUploadModal.value = true;
};

const closeUploadModal = () => {
    showUploadModal.value = false;
};

const uploadFile = (file) => {
    if (!file) {
        return;
    }

    isUploadingFile.value = true;
    uploadErrors.value = null;

    const formData = new FormData();
    formData.append("file", file);

    let route = routes.import_csv;
    if (uploadFormat.value === "vcard") {
        route = routes.import_vcard;
    } else if (uploadFormat.value === "speed-dial") {
        route = routes.import_speed_dial;
    }

    axios.post(route, formData)
        .then((response) => {
            showNotification("success", response.data.messages);
            closeUploadModal();
            refreshCurrentPage();
        })
        .catch((error) => {
            if (error.response?.data?.errors) {
                uploadErrors.value = error.response.data.errors;
                return;
            }

            handleErrorResponse(error);
        })
        .finally(() => {
            isUploadingFile.value = false;
        });
};

const downloadCsvTemplate = () => {
    axios.get(routes.download_csv_template, { responseType: "blob" })
        .then((response) => {
            const fileUrl = window.URL.createObjectURL(new Blob([response.data], { type: "text/csv" }));
            const link = document.createElement("a");
            link.href = fileUrl;
            link.setAttribute("download", "phonebook-contacts-template.csv");
            document.body.appendChild(link);
            link.click();
            link.remove();
        })
        .catch(handleErrorResponse);
};

const downloadExport = (route, filename, mimeType) => {
    axios.get(route, {
        params: {
            filter: filterData.value,
        },
        responseType: "blob",
    })
        .then((response) => {
            const fileUrl = window.URL.createObjectURL(new Blob([response.data], { type: mimeType }));
            const link = document.createElement("a");
            link.href = fileUrl;
            link.setAttribute("download", filename);
            document.body.appendChild(link);
            link.click();
            link.remove();
        })
        .catch(handleErrorResponse);
};

const handleExportCsv = () => {
    downloadExport(routes.export_csv, "phonebook-contacts.csv", "text/csv");
};

const handleExportVcard = () => {
    downloadExport(routes.export_vcard, "phonebook-contacts.vcf", "text/vcard");
};

const handleExportSpeedDial = () => {
    downloadExport(routes.export_speed_dial, "speed-dial.csv", "text/csv");
};

const downloadSpeedDialTemplate = () => {
    axios.get(routes.download_speed_dial_template, { responseType: "blob" })
        .then((response) => {
            const fileUrl = window.URL.createObjectURL(new Blob([response.data], { type: "text/csv" }));
            const link = document.createElement("a");
            link.href = fileUrl;
            link.setAttribute("download", "speed-dial-template.csv");
            document.body.appendChild(link);
            link.click();
            link.remove();
        })
        .catch(handleErrorResponse);
};

const handleCreateButtonClick = () => {
    showForm.value = true;
    formMode.value = "create";
    getItemOptions();
};

const handleEditButtonClick = (uuid) => {
    showForm.value = true;
    formMode.value = "update";
    getItemOptions(uuid);
};

const getItemOptions = (itemUuid = null) => {
    loadingForm.value = true;

    axios.post(routes.item_options, itemUuid ? { itemUuid } : {})
        .then((response) => {
            itemOptions.value = response.data;
        })
        .catch((error) => {
            handleFormClose();
            handleErrorResponse(error);
        })
        .finally(() => {
            loadingForm.value = false;
        });
};

const handleFormClose = () => {
    showForm.value = false;
};

const handleSelectPageItems = () => {
    if (selectPageItems.value) {
        selectedItems.value = data.value.data.map((row) => row.contact_uuid);
    } else {
        selectedItems.value = [];
        selectAll.value = false;
    }
};

const handleSelectAll = () => {
    axios.post(routes.select_all, { filter: filterData.value })
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
        })
        .catch(handleErrorResponse);
};

const handleClearSelection = () => {
    selectedItems.value = [];
    selectAll.value = false;
    selectPageItems.value = false;
};

const handleBulkActionRequest = (action) => {
    if (action === "bulk_delete") {
        confirmationHeader.value = "Delete selected contacts?";
        confirmationText.value = "This removes the contact and related phonebook data.";
        confirmationButtonLabel.value = "Delete";
        confirmAction.value = () => executeBulkDelete();
        confirmationModalTrigger.value = true;
    }
};

const handleSingleItemDeleteRequest = (uuid) => {
    confirmationHeader.value = "Delete this contact?";
    confirmationText.value = "This removes the contact and related phonebook data.";
    confirmationButtonLabel.value = "Delete";
    confirmAction.value = () => executeSingleDelete(uuid);
    confirmationModalTrigger.value = true;
};

const executeSingleDelete = (uuid) => {
    axios.delete(routes.destroy_template.replace(":uuid", uuid))
        .then((response) => {
            showNotification("success", response.data.messages);
            refreshCurrentPage();
            handleClearSelection();
        })
        .catch(handleErrorResponse)
        .finally(() => {
            confirmationModalTrigger.value = false;
        });
};

const executeBulkDelete = () => {
    axios.post(routes.bulk_delete, { items: selectedItems.value })
        .then((response) => {
            showNotification("success", response.data.messages);
            refreshCurrentPage();
            handleClearSelection();
        })
        .catch(handleErrorResponse)
        .finally(() => {
            confirmationModalTrigger.value = false;
        });
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
    const messages = error?.response?.data?.messages;

    if (messages) {
        const type = messages.error ? "error" : "success";
        showNotification(type, messages);
        return;
    }

    showNotification("error", { error: ["An unexpected error occurred."] });
};
</script>
