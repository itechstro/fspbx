<template>
    <MainLayout />

    <div class="m-3 space-y-4">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Mobile Apps</template>

            <template #action>
                <button type="button" @click.prevent="handleSettingsButtonClick()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    Admin API Settings
                </button>
            </template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <input type="text" v-model="filterData.search" @keydown.enter="handleSearchButtonClick"
                        class="block w-full rounded-md border-0 py-1.5 pl-3 text-sm text-gray-900 ring-1 ring-inset ring-gray-300"
                        placeholder="Search" />
                </div>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>

            <template #table-header>
                <TableColumnHeader class="px-4 py-1.5 text-left text-sm font-semibold text-gray-900">Tenant</TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">Tenant Domain</TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">CloudPLAY Customer</TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">Profile</TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">Status</TableColumnHeader>
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.domain_uuid">
                    <TableField class="px-4 py-2 text-sm text-gray-500">
                        {{ row.domain_description || row.domain_name }}
                    </TableField>
                    <TableField class="px-2 py-2 text-sm text-gray-500" :text="row.domain_name" />
                    <TableField class="px-2 py-2 text-sm text-gray-500">
                        <span v-if="row.ringotel_status == 'true'">
                            {{ row.cloudplay_cust_username || 'Connected' }}
                            <span v-if="row.cloudplay_cust_id" class="text-xs text-gray-400">(ID {{ row.cloudplay_cust_id }})</span>
                        </span>
                        <span v-else class="text-gray-400">—</span>
                    </TableField>
                    <TableField class="px-2 py-2 text-sm text-gray-500">
                        <span v-if="row.cloudplay_profile_id">ID {{ row.cloudplay_profile_id }}</span>
                        <span v-else class="text-amber-600">Not set</span>
                    </TableField>
                    <TableField class="px-2 py-2 text-sm text-gray-500">
                        <Badge v-if="row.ringotel_status == 'true'" text="Activated" backgroundColor="bg-green-50"
                            textColor="text-green-700" ringColor="ring-green-600/20" />
                        <Badge v-else text="Inactive" backgroundColor="bg-rose-50" textColor="text-rose-700"
                            ringColor="ring-rose-600/20" />
                    </TableField>
                    <TableField class="px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center justify-end gap-1">
                                <PowerIcon v-if="row.ringotel_status == 'false'" @click="handleActivateButtonClick(row.domain_uuid)"
                                    class="h-9 w-9 py-2 rounded-full text-gray-400 hover:bg-gray-200 cursor-pointer" title="Activate tenant" />
                                <Cog6ToothIcon v-if="row.ringotel_status == 'true'" @click="handleEditCustomerButtonClick(row.domain_uuid)"
                                    class="h-9 w-9 py-2 rounded-full text-gray-400 hover:bg-gray-200 cursor-pointer" title="Edit tenant connection" />
                                <XCircleIcon v-if="row.ringotel_status == 'true'" @click="handleDeactivateButtonClick(row.domain_uuid)"
                                    class="h-9 w-9 py-2 rounded-full text-gray-400 hover:bg-gray-200 cursor-pointer" title="Disconnect tenant" />
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="data.data.length === 0" class="text-center my-5 text-sm text-gray-500">No results found</div>
            </template>

            <template #loading>
                <Loading :show="loading" />
            </template>
        </DataTable>
    </div>

    <AddEditItemModal :show="showActivateModal" header="Activate CloudPLAY Customer" :loading="loadingModal"
        @close="handleModalClose">
        <template #modal-body>
            <CreateCloudPlayCustomerForm :options="itemOptions" :errors="formErrors" :is-submitting="activateFormSubmiting"
                @submit="handleCreateRequest" @cancel="handleModalClose" />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :show="showPairModal" header="Connect Existing CloudPLAY Customer" :loading="loadingModal"
        @close="handleModalClose">
        <template #modal-body>
            <PairCloudPlayCustomerForm :customers="cloudPlayCustomers" :profiles="cloudPlayProfiles"
                :selected-account="selectedAccount" :errors="formErrors" :is-submitting="pairFormSubmiting"
                @submit="handlePairRequest" @cancel="handleModalClose" />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :show="showEditCustomerModal" header="Tenant CloudPLAY Connection" :loading="loadingModal"
        @close="handleModalClose">
        <template #modal-body>
            <UpdateCloudPlayCustomerForm :customer="cloudPlayCustomer" :profiles="cloudPlayProfiles" :errors="formErrors"
                :is-submitting="editCustomerFormSubmiting" @submit="handleUpdateCustomerRequest" @cancel="handleModalClose" />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :show="showSettingsModal" header="CloudPLAY Admin API Settings" :loading="loadingModal"
        @close="handleModalClose">
        <template #modal-body>
            <UpdateCloudPlaySettingsForm :settings="cloudPlaySettings" :errors="formErrors"
                :is-submitting="settingsFormSubmiting" @submit="handleSettingsRequest" @cancel="handleModalClose" />
        </template>
    </AddEditItemModal>

    <ConfirmationModal :show="showConfirmationModal" @close="showConfirmationModal = false" @confirm="confirmDeleteAction"
        header="Confirm Action" text="Disconnect CloudPLAY for this tenant?"
        confirm-button-label="Disconnect" cancel-button-label="Cancel" :loading="showDeactivateSpinner" />

    <ConfirmationModal :show="showSetupConfirmationModal" @close="cancelSetupAction" @confirm="confirmSetupAction"
        header="Set up CloudPLAY customer"
        text="Create a new CloudPLAY customer or connect an existing one?"
        confirm-button-label="Create New Customer" cancel-button-label="Connect Existing"
        :loading="showConnectSpinner || showCreateSpinner" :color="'blue'" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import MainLayout from '../Layouts/MainLayout.vue';
import DataTable from './components/general/DataTable.vue';
import TableColumnHeader from './components/general/TableColumnHeader.vue';
import TableField from './components/general/TableField.vue';
import Paginator from './components/general/Paginator.vue';
import AddEditItemModal from './components/modal/AddEditItemModal.vue';
import ConfirmationModal from './components/modal/ConfirmationModal.vue';
import Loading from './components/general/Loading.vue';
import Notification from './components/notifications/Notification.vue';
import Badge from '@generalComponents/Badge.vue';
import CreateCloudPlayCustomerForm from './components/forms/CreateCloudPlayCustomerForm.vue';
import PairCloudPlayCustomerForm from './components/forms/PairCloudPlayCustomerForm.vue';
import UpdateCloudPlaySettingsForm from './components/forms/UpdateCloudPlaySettingsForm.vue';
import UpdateCloudPlayCustomerForm from './components/forms/UpdateCloudPlayCustomerForm.vue';
import { Cog6ToothIcon, PowerIcon, XCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    data: Object,
    routes: Object,
    pagination: Object,
});

const loading = ref(false);
const loadingModal = ref(false);
const showActivateModal = ref(false);
const showPairModal = ref(false);
const showEditCustomerModal = ref(false);
const showSettingsModal = ref(false);
const showConfirmationModal = ref(false);
const showSetupConfirmationModal = ref(false);
const activateFormSubmiting = ref(false);
const pairFormSubmiting = ref(false);
const settingsFormSubmiting = ref(false);
const editCustomerFormSubmiting = ref(false);
const showDeactivateSpinner = ref(false);
const showConnectSpinner = ref(false);
const showCreateSpinner = ref(false);
const confirmDeleteAction = ref(null);
const confirmSetupAction = ref(null);
const cancelSetupAction = ref(null);
const formErrors = ref(null);
const notificationShow = ref(false);
const notificationType = ref(null);
const notificationMessages = ref(null);
const itemOptions = ref({});
const cloudPlayCustomers = ref([]);
const cloudPlaySettings = ref({});
const cloudPlayCustomer = ref({});
const cloudPlayProfiles = ref([]);
const selectedAccount = ref(null);

const filterData = ref({ search: null });
const sortData = ref({ name: 'domain_name', order: 'asc' });

const handleActivateButtonClick = (itemUuid) => {
    showSetupConfirmationModal.value = true;
    confirmSetupAction.value = () => executeCreateCustomerAction(itemUuid);
    cancelSetupAction.value = () => executePairCustomerAction(itemUuid);
};

const executeCreateCustomerAction = (itemUuid) => {
    showSetupConfirmationModal.value = false;
    formErrors.value = null;
    showActivateModal.value = true;
    loadingModal.value = true;
    getItemOptions(itemUuid);
};

const executePairCustomerAction = (itemUuid) => {
    showSetupConfirmationModal.value = false;
    showPairModal.value = true;
    loadingModal.value = true;
    selectedAccount.value = itemUuid;
    cloudPlayProfiles.value = [];
    getCloudPlayCustomers();
};

const handleDeactivateButtonClick = (uuid) => {
    showConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeDisconnect(uuid);
};

const executeDisconnect = (uuid) => {
    showDeactivateSpinner.value = true;
    axios.post(props.routes.destroy_customer, { domain_uuid: uuid })
        .then((response) => {
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
            handleModalClose();
        })
        .catch(handleFormErrorResponse)
        .finally(() => {
            showDeactivateSpinner.value = false;
        });
};

const handleCreateRequest = (form) => {
    if (!form.domain_uuid) {
        showNotification('error', { server: ['Tenant details are still loading. Wait a moment and try again.'] });
        return;
    }

    formErrors.value = null;
    activateFormSubmiting.value = true;
    axios.post(props.routes.create_customer, form)
        .then((response) => {
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
            handleModalClose();
        })
        .catch(handleFormErrorResponse)
        .finally(() => {
            activateFormSubmiting.value = false;
        });
};

const handlePairRequest = (form) => {
    formErrors.value = null;
    pairFormSubmiting.value = true;
    axios.post(props.routes.pair_customer, form)
        .then((response) => {
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
            handleModalClose();
        })
        .catch(handleFormErrorResponse)
        .finally(() => {
            pairFormSubmiting.value = false;
        });
};

const handleEditCustomerButtonClick = (domainUuid) => {
    formErrors.value = null;
    showEditCustomerModal.value = true;
    loadingModal.value = true;
    cloudPlayProfiles.value = [];
    getCloudPlayCustomer(domainUuid);
    getCloudPlayProfiles(domainUuid);
};

const handleUpdateCustomerRequest = (form) => {
    formErrors.value = null;
    editCustomerFormSubmiting.value = true;
    axios.post(props.routes.update_customer, form)
        .then((response) => {
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
            handleModalClose();
        })
        .catch(handleFormErrorResponse)
        .finally(() => {
            editCustomerFormSubmiting.value = false;
        });
};

const handleSettingsButtonClick = () => {
    showSettingsModal.value = true;
    loadingModal.value = true;
    getCloudPlaySettings();
};

const handleSettingsRequest = (form) => {
    settingsFormSubmiting.value = true;
    axios.post(props.routes.update_settings, form)
        .then((response) => {
            showNotification('success', response.data.messages);
            handleModalClose();
        })
        .catch(handleFormErrorResponse)
        .finally(() => {
            settingsFormSubmiting.value = false;
        });
};

const getItemOptions = (itemUuid) => {
    axios.post(props.routes.item_options, { item_uuid: itemUuid })
        .then((response) => {
            itemOptions.value = response.data;
        })
        .catch(handleFormErrorResponse)
        .finally(() => {
            loadingModal.value = false;
        });
};

const getCloudPlayCustomers = () => {
    axios.post(props.routes.get_all_customers)
        .then((response) => {
            cloudPlayCustomers.value = response.data.customers;
        })
        .catch(handleFormErrorResponse)
        .finally(() => {
            loadingModal.value = false;
        });
};

const getCloudPlayProfiles = (domainUuid) => {
    axios.post(props.routes.get_profiles, { domain_uuid: domainUuid })
        .then((response) => {
            cloudPlayProfiles.value = response.data.profiles;
        })
        .catch(handleFormErrorResponse);
};

const getCloudPlayCustomer = (domainUuid) => {
    axios.post(props.routes.get_customer, { domain_uuid: domainUuid })
        .then((response) => {
            cloudPlayCustomer.value = response.data;
        })
        .catch(handleFormErrorResponse)
        .finally(() => {
            loadingModal.value = false;
        });
};

const getCloudPlaySettings = () => {
    axios.post(props.routes.get_settings)
        .then((response) => {
            cloudPlaySettings.value = response.data;
        })
        .catch(handleFormErrorResponse)
        .finally(() => {
            loadingModal.value = false;
        });
};

const handleSearchButtonClick = () => {
    loading.value = true;
    router.visit(props.routes.current_page, {
        data: { filterData: filterData.value, sortField: sortData.value.name, sortOrder: sortData.value.order },
        preserveScroll: true,
        preserveState: true,
        only: ['data'],
        onSuccess: () => {
            loading.value = false;
        },
    });
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    handleSearchButtonClick();
};

const renderRequestedPage = (page) => {
    loading.value = true;
    router.visit(page, {
        preserveScroll: true,
        preserveState: true,
        only: ['data'],
        onSuccess: () => {
            loading.value = false;
        },
    });
};

const handleModalClose = () => {
    showActivateModal.value = false;
    showPairModal.value = false;
    showEditCustomerModal.value = false;
    showSettingsModal.value = false;
    formErrors.value = null;
};

const handleFormErrorResponse = (error) => {
    if (error?.response?.status === 419) {
        showNotification('error', { request: ['Session expired. Reload the page and try again.'] });
        return;
    }

    if (error?.response) {
        const errors = error.response.data?.errors ?? { server: [error.message || 'Request failed.'] };
        formErrors.value = errors;
        showNotification('error', errors);
        return;
    }

    if (error?.request) {
        showNotification('error', { request: ['No response from server. Check your connection and try again.'] });
        return;
    }

    showNotification('error', { request: [error?.message || 'Request failed.'] });
};

const showNotification = (type, messages) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
};

const hideNotification = () => {
    notificationShow.value = false;
};

</script>
