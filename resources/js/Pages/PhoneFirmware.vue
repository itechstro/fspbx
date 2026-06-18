<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="fetchData" @reset-filters="resetFilters">
            <template #title>Phone Firmware</template>

            <template #subtitle>
                Upload firmware files for phone auto-upgrade. Files are served from
                <code class="rounded bg-gray-100 px-1 py-0.5 text-xs">/firmware/</code>
                without authentication.
            </template>

            <template #filters>
                <div class="relative mb-2 min-w-64 focus-within:z-10 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input
                        v-model="filterData.search"
                        type="text"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                        placeholder="Filter current folder"
                        @keydown.enter="fetchData"
                    />
                </div>
            </template>

            <template #action>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                        title="Refresh"
                        :disabled="loading"
                        @click="fetchData"
                    >
                        <ArrowPathIcon class="h-4 w-4 text-gray-500" :class="{ 'animate-spin': loading }" />
                        Refresh
                    </button>

                    <button
                        v-if="publicUrl"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        @click="copyPublicUrl"
                    >
                        <ClipboardDocumentIcon class="h-4 w-4 text-gray-500" />
                        Copy URL
                    </button>

                    <button
                        v-if="canApplyDefaultSettings"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        @click="confirmApplyProvision('default')"
                    >
                        Apply to Default Settings
                    </button>

                    <button
                        v-if="canApplyDomainSettings"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        @click="confirmApplyProvision('domain')"
                    >
                        Apply to Domain Settings
                    </button>

                    <button
                        v-if="permissions.upload"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        @click="showFolderModal = true"
                    >
                        <FolderPlusIcon class="h-4 w-4 text-gray-500" />
                        New Folder
                    </button>

                    <button
                        v-if="permissions.upload"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                        @click="openUploadModal"
                    >
                        <ArrowUpTrayIcon class="h-4 w-4" />
                        Upload
                    </button>
                </div>
            </template>

            <template #navigation>
                <div class="flex flex-wrap items-center gap-1 px-1 py-2 text-sm text-gray-600">
                    <template v-for="(crumb, index) in breadcrumbs" :key="crumb.path || 'root'">
                        <button
                            type="button"
                            class="rounded px-1 py-0.5 hover:bg-gray-100 hover:text-gray-900"
                            :class="{ 'font-semibold text-gray-900': index === breadcrumbs.length - 1 }"
                            @click="openPath(crumb.path)"
                        >
                            {{ crumb.name }}
                        </button>
                        <span v-if="index < breadcrumbs.length - 1" class="text-gray-400">/</span>
                    </template>
                </div>
            </template>

            <template #table-header>
                <TableColumnHeader header="Name" class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Type" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Size" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Modified" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader v-if="hasRowActions" header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template #table-body>
                <tr v-if="loading">
                    <td :colspan="columnCount" class="px-4 py-8 text-center text-sm text-gray-500">
                        Loading firmware files...
                    </td>
                </tr>
                <tr v-else-if="filteredItems.length === 0">
                    <td :colspan="columnCount" class="px-4 py-8 text-center text-sm text-gray-500">
                        This folder is empty.
                    </td>
                </tr>
                <tr
                    v-for="item in filteredItems"
                    :key="item.path"
                    class="border-b border-gray-100 hover:bg-gray-50"
                >
                    <td class="px-4 py-3 text-sm text-gray-900">
                        <button
                            v-if="item.type === 'directory'"
                            type="button"
                            class="inline-flex items-center gap-2 font-medium text-indigo-600 hover:text-indigo-500"
                            @click="openPath(item.path)"
                        >
                            <FolderIcon class="h-4 w-4 text-amber-500" />
                            {{ item.name }}
                        </button>
                        <span v-else class="inline-flex items-center gap-2">
                            <DocumentIcon class="h-4 w-4 text-gray-400" />
                            {{ item.name }}
                        </span>
                    </td>
                    <td class="px-2 py-3 text-sm capitalize text-gray-600">{{ item.type }}</td>
                    <td class="px-2 py-3 text-sm text-gray-600">{{ formatSize(item.size) }}</td>
                    <td class="px-2 py-3 text-sm text-gray-600">{{ item.modified_at || '—' }}</td>
                    <td v-if="hasRowActions" class="px-2 py-3 text-right text-sm">
                        <div class="inline-flex items-center gap-2">
                            <button
                                v-if="item.type === 'file'"
                                type="button"
                                class="text-indigo-600 hover:text-indigo-500"
                                @click="downloadItem(item)"
                            >
                                Download
                            </button>
                            <button
                                v-if="permissions.delete"
                                type="button"
                                class="text-red-600 hover:text-red-500"
                                @click="confirmDelete(item)"
                            >
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>
            </template>
        </DataTable>

        <div
            v-if="provision.supported"
            class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm text-gray-700"
        >
            <p class="font-medium text-gray-900">
                {{ provision.label }} auto-upgrade settings
            </p>
            <p class="mt-1 text-gray-600">
                Use the buttons above to write these values into Default Settings or the current domain override.
            </p>
            <ul class="mt-3 space-y-2">
                <li
                    v-for="setting in provision.settings"
                    :key="setting.subcategory"
                    class="rounded-md bg-white px-3 py-2 ring-1 ring-inset ring-indigo-100"
                >
                    <div class="font-medium text-gray-900">{{ setting.subcategory }}</div>
                    <div class="mt-1 break-all font-mono text-xs text-gray-600">{{ setting.value }}</div>
                </li>
            </ul>
            <div class="mt-3 flex flex-wrap gap-3 text-sm">
                <a
                    v-if="routes.default_settings"
                    :href="routes.default_settings"
                    class="text-indigo-700 hover:text-indigo-500"
                >
                    Open Default Settings
                </a>
                <a
                    v-if="routes.domain_settings"
                    :href="routes.domain_settings"
                    class="text-indigo-700 hover:text-indigo-500"
                >
                    Open Domain Settings
                </a>
            </div>
        </div>

        <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
            <p class="font-medium text-gray-900">After uploading</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>
                    Fanvil and Intrade phones need a UTF-8 manifest
                    (<code class="rounded bg-white px-1">vendor_model_hwv1_0.txt</code>) plus the firmware file in this folder.
                </li>
                <li>
                    Open a vendor folder such as <code class="rounded bg-white px-1">intrade</code>,
                    <code class="rounded bg-white px-1">fanvil</code>, or
                    <code class="rounded bg-white px-1">grandstream</code> to apply the matching provision settings automatically.
                </li>
                <li>Allowed upload types: {{ allowedExtensionsLabel }} (max {{ maxUploadMb }} MB).</li>
            </ul>
        </div>
    </div>

    <AddEditItemModal
        :show="showUploadModal"
        :loading="formSubmitting"
        header="Upload Firmware"
        custom-class="sm:max-w-2xl"
        @close="closeUploadModal"
    >
        <template #modal-body>
            <div
                class="mb-4 rounded-md border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-600"
            >
                Uploading to <span class="font-medium text-gray-900">{{ currentPathLabel }}</span>
            </div>

            <div
                class="cursor-pointer rounded-lg border-2 border-dashed p-6 text-center"
                :class="isDragging ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300'"
                @click="browseFile"
                @dragenter.prevent="isDragging = true"
                @dragover.prevent
                @dragleave="isDragging = false"
                @drop.prevent="dropFile"
            >
                <input ref="fileInput" type="file" class="hidden" :accept="acceptAttribute" @change="handleFileSelect" />
                <p v-if="!selectedFile" class="text-gray-600">
                    Drag and drop a firmware file here, or <span class="text-indigo-600 underline">browse</span>.
                </p>
                <p v-else class="text-gray-700">
                    Selected: <span class="font-medium">{{ selectedFile.name }}</span>
                    ({{ formatSize(selectedFile.size) }})
                </p>
            </div>

            <p v-if="uploadError" class="mt-2 text-sm text-red-600">{{ uploadError }}</p>

            <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                    @click="closeUploadModal"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="!selectedFile || formSubmitting"
                    @click="submitUpload"
                >
                    Upload
                </button>
            </div>
        </template>
    </AddEditItemModal>

    <AddEditItemModal
        :show="showFolderModal"
        :loading="formSubmitting"
        header="New Folder"
        custom-class="sm:max-w-lg"
        @close="closeFolderModal"
    >
        <template #modal-body>
            <label class="block text-sm font-medium text-gray-700" for="folder-name">Folder name</label>
            <input
                id="folder-name"
                v-model="folderName"
                type="text"
                class="mt-2 block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                placeholder="v2.4.1.1234"
                @keydown.enter="submitFolder"
            />
            <p v-if="folderError" class="mt-2 text-sm text-red-600">{{ folderError }}</p>

            <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                    @click="closeFolderModal"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="!folderName || formSubmitting"
                    @click="submitFolder"
                >
                    Create
                </button>
            </div>
        </template>
    </AddEditItemModal>

    <ConfirmationModal
        :show="showApplyModal"
        :loading="formSubmitting"
        header="Apply Provision Settings"
        confirm-button-label="Apply"
        @close="closeApplyModal"
        @confirm="submitApplyProvision"
    >
        <div class="space-y-3 text-sm text-gray-600">
            <p>
                Apply <span class="font-medium text-gray-900">{{ provision.label }}</span> firmware settings to
                <span class="font-medium text-gray-900">{{ applyScopeLabel }}</span>?
            </p>
            <ul class="space-y-2">
                <li
                    v-for="setting in provision.settings"
                    :key="setting.subcategory"
                    class="rounded-md bg-gray-50 px-3 py-2"
                >
                    <div class="font-medium text-gray-900">{{ setting.subcategory }}</div>
                    <div class="mt-1 break-all font-mono text-xs">{{ setting.value }}</div>
                </li>
            </ul>
        </div>
    </ConfirmationModal>

    <ConfirmationModal
        :show="showDeleteModal"
        :loading="formSubmitting"
        header="Confirm Deletion"
        confirm-button-label="Delete"
        @close="showDeleteModal = false"
        @confirm="deleteItem"
    >
        <p class="text-sm text-gray-500">
            Delete <span class="font-medium text-gray-900">{{ deleteTarget?.name }}</span>?
            This cannot be undone.
        </p>
    </ConfirmationModal>

    <Notification
        :show="notificationShow"
        :type="notificationType"
        :messages="notificationMessages"
        @update:show="hideNotification"
    />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import {
    ArrowPathIcon,
    ArrowUpTrayIcon,
    ClipboardDocumentIcon,
    DocumentIcon,
    FolderIcon,
    FolderPlusIcon,
    MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline';
import MainLayout from '../Layouts/MainLayout.vue';
import DataTable from './components/general/DataTable.vue';
import TableColumnHeader from './components/general/TableColumnHeader.vue';
import AddEditItemModal from './components/modal/AddEditItemModal.vue';
import ConfirmationModal from './components/modal/ConfirmationModal.vue';
import Notification from './components/notifications/Notification.vue';

const props = defineProps({
    routes: {
        type: Object,
        required: true,
    },
    permissions: {
        type: Object,
        required: true,
    },
    public_base_url: {
        type: String,
        required: true,
    },
    allowed_extensions: {
        type: Array,
        default: () => [],
    },
    max_upload_mb: {
        type: Number,
        default: 200,
    },
    domain_name: {
        type: String,
        default: '',
    },
});

const loading = ref(false);
const formSubmitting = ref(false);
const currentPath = ref('');
const publicUrl = ref('');
const breadcrumbs = ref([{ name: 'firmware', path: '' }]);
const items = ref([]);
const provision = ref({
    supported: false,
    vendor: null,
    label: null,
    public_url: null,
    settings: [],
});
const filterData = ref({ search: '' });

const showUploadModal = ref(false);
const showFolderModal = ref(false);
const showDeleteModal = ref(false);
const showApplyModal = ref(false);
const applyScope = ref('default');
const selectedFile = ref(null);
const uploadError = ref('');
const folderName = ref('');
const folderError = ref('');
const deleteTarget = ref(null);
const isDragging = ref(false);
const fileInput = ref(null);

const notificationShow = ref(false);
const notificationType = ref('success');
const notificationMessages = ref({});

const hasRowActions = computed(() => true);
const columnCount = computed(() => (hasRowActions.value ? 5 : 4));
const currentPathLabel = computed(() => (currentPath.value ? `firmware/${currentPath.value}` : 'firmware'));
const allowedExtensionsLabel = computed(() => props.allowed_extensions.map((ext) => `.${ext}`).join(', '));
const acceptAttribute = computed(() => props.allowed_extensions.map((ext) => `.${ext}`).join(','));
const maxUploadMb = computed(() => props.max_upload_mb);
const filteredItems = computed(() => {
    const query = filterData.value.search.trim().toLowerCase();
    if (!query) {
        return items.value;
    }

    return items.value.filter((item) => item.name.toLowerCase().includes(query));
});
const canApplyDefaultSettings = computed(() => provision.value.supported && props.permissions.default_settings);
const canApplyDomainSettings = computed(() => provision.value.supported && props.permissions.domain_settings);
const applyScopeLabel = computed(() => {
    if (applyScope.value === 'domain') {
        return props.domain_name ? `domain settings (${props.domain_name})` : 'domain settings';
    }

    return 'default settings';
});

const showNotification = (type, messages) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
};

const hideNotification = () => {
    notificationShow.value = false;
};

const handleError = (error) => {
    const messages = error.response?.data?.messages;
    if (messages) {
        showNotification('error', messages);
        return;
    }

    showNotification('error', { error: ['Request failed.'] });
};

const fetchData = async () => {
    loading.value = true;

    try {
        const response = await axios.get(props.routes.data_route, {
            params: { path: currentPath.value },
        });

        currentPath.value = response.data.path ?? '';
        publicUrl.value = response.data.public_url ?? '';
        breadcrumbs.value = response.data.breadcrumbs ?? [{ name: 'firmware', path: '' }];
        items.value = response.data.items ?? [];
        provision.value = response.data.provision ?? {
            supported: false,
            vendor: null,
            label: null,
            public_url: null,
            settings: [],
        };
    } catch (error) {
        handleError(error);
    } finally {
        loading.value = false;
    }
};

const resetFilters = () => {
    filterData.value.search = '';
};

const openPath = (path) => {
    currentPath.value = path;
    filterData.value.search = '';
    fetchData();
};

const formatSize = (bytes) => {
    if (bytes === null || bytes === undefined) {
        return '—';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1048576) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1048576).toFixed(1)} MB`;
};

const copyPublicUrl = async () => {
    if (!publicUrl.value) {
        return;
    }

    try {
        await navigator.clipboard.writeText(publicUrl.value);
        showNotification('success', { success: ['Public URL copied to clipboard.'] });
    } catch (error) {
        showNotification('error', { error: ['Could not copy URL.'] });
    }
};

const openUploadModal = () => {
    uploadError.value = '';
    selectedFile.value = null;
    showUploadModal.value = true;
};

const closeUploadModal = () => {
    showUploadModal.value = false;
    uploadError.value = '';
    selectedFile.value = null;
    isDragging.value = false;
};

const browseFile = () => {
    fileInput.value?.click();
};

const validateSelectedFile = (file) => {
    if (!file) {
        return 'Select a file to upload.';
    }

    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
    if (!props.allowed_extensions.includes(extension)) {
        return `Unsupported file type. Allowed: ${allowedExtensionsLabel.value}.`;
    }

    if (file.size > props.max_upload_mb * 1048576) {
        return `File exceeds the ${props.max_upload_mb} MB upload limit.`;
    }

    return '';
};

const handleFileSelect = (event) => {
    const file = event.target.files?.[0] ?? null;
    const error = validateSelectedFile(file);
    uploadError.value = error;
    selectedFile.value = error ? null : file;
    event.target.value = '';
};

const dropFile = (event) => {
    isDragging.value = false;
    const file = event.dataTransfer?.files?.[0] ?? null;
    const error = validateSelectedFile(file);
    uploadError.value = error;
    selectedFile.value = error ? null : file;
};

const submitUpload = async () => {
    const error = validateSelectedFile(selectedFile.value);
    uploadError.value = error;
    if (error) {
        return;
    }

    formSubmitting.value = true;
    const formData = new FormData();
    formData.append('path', currentPath.value);
    formData.append('file', selectedFile.value);

    try {
        const response = await axios.post(props.routes.upload, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        closeUploadModal();
        showNotification('success', response.data.messages);
        await fetchData();
    } catch (uploadFailure) {
        handleError(uploadFailure);
    } finally {
        formSubmitting.value = false;
    }
};

const closeFolderModal = () => {
    showFolderModal.value = false;
    folderName.value = '';
    folderError.value = '';
};

const submitFolder = async () => {
    folderError.value = '';
    formSubmitting.value = true;

    try {
        const response = await axios.post(props.routes.mkdir, {
            path: currentPath.value,
            name: folderName.value,
        });
        closeFolderModal();
        showNotification('success', response.data.messages);
        await fetchData();
    } catch (error) {
        folderError.value = error.response?.data?.messages?.error?.[0] ?? 'Could not create folder.';
    } finally {
        formSubmitting.value = false;
    }
};

const confirmDelete = (item) => {
    deleteTarget.value = item;
    showDeleteModal.value = true;
};

const deleteItem = async () => {
    if (!deleteTarget.value) {
        return;
    }

    formSubmitting.value = true;

    try {
        const response = await axios.post(props.routes.delete, {
            path: deleteTarget.value.path,
        });
        showDeleteModal.value = false;
        deleteTarget.value = null;
        showNotification('success', response.data.messages);
        await fetchData();
    } catch (error) {
        handleError(error);
    } finally {
        formSubmitting.value = false;
    }
};

const downloadItem = (item) => {
    const url = `${props.routes.download}?path=${encodeURIComponent(item.path)}`;
    window.location.href = url;
};

const confirmApplyProvision = (scope) => {
    applyScope.value = scope;
    showApplyModal.value = true;
};

const closeApplyModal = () => {
    showApplyModal.value = false;
    applyScope.value = 'default';
};

const submitApplyProvision = async () => {
    formSubmitting.value = true;

    try {
        const response = await axios.post(props.routes.apply_provision, {
            path: currentPath.value,
            scope: applyScope.value,
        });
        closeApplyModal();
        showNotification('success', response.data.messages);
    } catch (error) {
        handleError(error);
    } finally {
        formSubmitting.value = false;
    }
};

onMounted(() => {
    fetchData();
});
</script>
