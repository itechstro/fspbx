<template>
    <MainLayout />

    <div class="m-3 space-y-4">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Mobile Apps</template>

            <template #action>
                <button type="button" @click.prevent="handleSettingsButtonClick()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    CloudPLAY Admin API
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
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">Mobile App</TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">Settings</TableColumnHeader>
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.domain_uuid">
                    <TableField class="px-4 py-2 text-sm text-gray-500">
                        {{ row.domain_description || row.domain_name }}
                    </TableField>
                    <TableField class="px-2 py-2 text-sm text-gray-500" :text="row.domain_name" />
                    <TableField class="px-2 py-2 text-sm text-gray-500">
                        <select :value="row.mobile_app_provider" @change="updateTenantProvider(row, $event.target.value)"
                            class="rounded-md border-gray-300 text-sm">
                            <option value="cloudplay">CloudPLAY Softphone</option>
                            <option value="ringotel">Ringotel</option>
                            <option value="talkphone">TalkPhone</option>
                        </select>
                    </TableField>
                    <TableField class="px-2 py-2 text-sm text-gray-500">
                        <span v-if="row.mobile_app_provider === 'talkphone'">
                            {{ row.talkphone?.transport || 'WSS' }} · {{ row.talkphone?.codecs || 'Default codecs' }} · {{ row.talkphone?.voicemail_number || '*97' }}
                        </span>
                        <span v-else-if="row.mobile_app_provider === 'ringotel'">
                            <Badge v-if="row.ringotel_status == 'true'" text="Activated" backgroundColor="bg-green-50"
                                textColor="text-green-700" ringColor="ring-green-600/20" />
                            <Badge v-else text="Inactive" backgroundColor="bg-rose-50" textColor="text-rose-700"
                                ringColor="ring-rose-600/20" />
                        </span>
                        <span v-else>
                            <span v-if="row.ringotel_status == 'true'">{{ row.cloudplay_cust_username || 'Connected' }}</span>
                            <span v-else class="text-gray-400">Not connected</span>
                            <span v-if="row.cloudplay_profile_id"> · Profile {{ row.cloudplay_profile_id }}</span>
                        </span>
                    </TableField>
                    <TableField class="px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center justify-end gap-1">
                                <Cog6ToothIcon v-if="row.mobile_app_provider === 'talkphone'" @click="openTalkPhoneSettings(row)"
                                    class="h-9 w-9 py-2 rounded-full text-gray-400 hover:bg-gray-200 cursor-pointer" title="TalkPhone settings" />
                                <PowerIcon v-if="row.mobile_app_provider === 'cloudplay' && row.ringotel_status == 'false'" @click="handleActivateButtonClick(row.domain_uuid)"
                                    class="h-9 w-9 py-2 rounded-full text-gray-400 hover:bg-gray-200 cursor-pointer" title="Activate tenant" />
                                <BookOpenIcon v-if="row.mobile_app_provider === 'cloudplay' && row.ringotel_status == 'true'"
                                    @click="handleSyncEnterprisePhonebookClick(row.domain_uuid)"
                                    class="h-9 w-9 py-2 rounded-full text-gray-400 hover:bg-gray-200 cursor-pointer"
                                    :class="{ 'opacity-50 pointer-events-none': syncingPhonebookDomainUuid === row.domain_uuid }"
                                    title="Sync enterprise phonebook" />
                                <Cog6ToothIcon v-if="row.mobile_app_provider === 'cloudplay' && row.ringotel_status == 'true'" @click="handleEditCustomerButtonClick(row.domain_uuid)"
                                    class="h-9 w-9 py-2 rounded-full text-gray-400 hover:bg-gray-200 cursor-pointer" title="Edit tenant connection" />
                                <XCircleIcon v-if="row.mobile_app_provider === 'cloudplay' && row.ringotel_status == 'true'" @click="handleDeactivateButtonClick(row.domain_uuid)"
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

    <AddEditItemModal :show="showTalkPhoneModal" header="TalkPhone tenant settings" :loading="loadingModal"
        @close="handleModalClose">
        <template #modal-body>
            <form class="space-y-4" @submit.prevent="saveTalkPhoneTenantSettings">
                <p class="text-sm text-gray-600">These defaults apply to every TalkPhone on this tenant. An extension can override them on its Mobile App tab. Leave a field blank to use the TalkPhone default.</p>
                <label class="block text-sm">Transport
                    <select v-model="talkphoneForm.transport" class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="">Default (WSS)</option>
                        <option>WSS</option>
                        <option>WS</option>
                        <option>TLS</option>
                        <option>TCP</option>
                    </select>
                </label>
                <label class="block text-sm">Port
                    <input v-model="talkphoneForm.port" class="mt-1 block w-full rounded-md border-gray-300" />
                </label>
                <label class="block text-sm">WebSocket path
                    <input v-model="talkphoneForm.ws_path" class="mt-1 block w-full rounded-md border-gray-300" />
                </label>
                <label class="block text-sm">Media
                    <select v-model="talkphoneForm.media_transport" class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="">Default (SRTP)</option>
                        <option>SRTP</option>
                        <option>RTP</option>
                    </select>
                </label>
                <label class="block text-sm">Voicemail number
                    <input v-model="talkphoneForm.voicemail_number" class="mt-1 block w-full rounded-md border-gray-300" placeholder="*97" />
                </label>
                <label class="block text-sm">Codecs
                    <input v-model="talkphoneForm.codecs" class="mt-1 block w-full rounded-md border-gray-300" placeholder="opus,G722,PCMU,PCMA" />
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="talkphoneForm.allow_bad_certificate" />
                    Allow self-signed certificate
                </label>
                <label class="block text-sm">Register expiry
                    <input v-model="talkphoneForm.register_expires" class="mt-1 block w-full rounded-md border-gray-300" placeholder="600" />
                </label>
                <div class="flex justify-end gap-3">
                    <button type="button" class="text-sm font-semibold text-gray-700" @click="handleModalClose">Cancel</button>
                    <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white" :disabled="talkphoneSaving">Save</button>
                </div>
            </form>
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
import { BookOpenIcon, Cog6ToothIcon, PowerIcon, XCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    data: Object,
    routes: Object,
    pagination: Object,
    mobileAppProvider: String,
});

const loading = ref(false);
const loadingModal = ref(false);
const showActivateModal = ref(false);
const showPairModal = ref(false);
const showEditCustomerModal = ref(false);
const showSettingsModal = ref(false);
const showTalkPhoneModal = ref(false);
const talkphoneSaving = ref(false);
const talkphoneDomainUuid = ref(null);
const emptyTalkPhoneForm = () => ({
    transport: '',
    port: '',
    ws_path: '',
    media_transport: '',
    voicemail_number: '',
    codecs: '',
    allow_bad_certificate: false,
    register_expires: '',
});
const talkphoneForm = ref(emptyTalkPhoneForm());
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
const syncingPhonebookDomainUuid = ref(null);

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

const handleSyncEnterprisePhonebookClick = (domainUuid) => {
    syncingPhonebookDomainUuid.value = domainUuid;
    axios.post(props.routes.sync_enterprise_phonebook, { domain_uuid: domainUuid })
        .then((response) => {
            showNotification('success', response.data.messages);
        })
        .catch((error) => {
            const messages = error.response?.data?.messages;
            if (messages) {
                showNotification('error', messages);
                return;
            }
            handleFormErrorResponse(error);
        })
        .finally(() => {
            syncingPhonebookDomainUuid.value = null;
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

const updateTenantProvider = (row, provider) => {
    const previous = row.mobile_app_provider;
    row.mobile_app_provider = provider;
    axios.post(props.routes.update_tenant_provider, {
        domain_uuid: row.domain_uuid,
        provider,
    })
        .then((response) => {
            showNotification('success', response.data.messages);
        })
        .catch((error) => {
            row.mobile_app_provider = previous;
            handleFormErrorResponse(error);
        });
};

const openTalkPhoneSettings = (row) => {
    talkphoneDomainUuid.value = row.domain_uuid;
    formErrors.value = null;
    showTalkPhoneModal.value = true;
    loadingModal.value = true;
    axios.post(props.routes.get_talkphone_settings, { domain_uuid: row.domain_uuid })
        .then((response) => {
            const saved = response.data.settings || {};
            talkphoneForm.value = {
                transport: saved.transport || '',
                port: saved.port || '',
                ws_path: saved.ws_path || '',
                media_transport: saved.media_transport || '',
                voicemail_number: saved.voicemail_number || '',
                codecs: saved.codecs || '',
                allow_bad_certificate: ['1', 'true', 'yes', 'on'].includes(String(saved.allow_bad_certificate).toLowerCase()),
                register_expires: saved.register_expires || '',
            };
        })
        .catch(handleFormErrorResponse)
        .finally(() => {
            loadingModal.value = false;
        });
};

const saveTalkPhoneTenantSettings = () => {
    talkphoneSaving.value = true;
    const settings = { ...talkphoneForm.value };
    settings.allow_bad_certificate = settings.allow_bad_certificate ? 'true' : '';
    axios.post(props.routes.update_talkphone_settings, {
        domain_uuid: talkphoneDomainUuid.value,
        settings,
    })
        .then((response) => {
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
            handleModalClose();
        })
        .catch(handleFormErrorResponse)
        .finally(() => {
            talkphoneSaving.value = false;
        });
};

const handleModalClose = () => {
    showActivateModal.value = false;
    showPairModal.value = false;
    showEditCustomerModal.value = false;
    showSettingsModal.value = false;
    showTalkPhoneModal.value = false;
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
