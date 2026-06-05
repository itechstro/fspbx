<template>
    <MainLayout>
        <main class="mx-auto max-w-8xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Contact Center Settings</h1>
                    <p class="mt-1 text-sm text-gray-500">Manage queues, agents, routing, tier rules, and announcements.</p>
                </div>
                <a :href="routes.dashboard"
                    class="text-sm font-medium text-cyan-700 hover:text-cyan-900">Back to Dashboard</a>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-12">
                <section class="rounded-lg bg-white p-4 shadow ring-1 ring-gray-200 lg:col-span-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-900">Contact Centers</h2>
                        <a :href="routes.create"
                            class="inline-flex items-center rounded-md bg-cyan-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-cyan-700">
                            Add
                        </a>
                    </div>

                    <ul class="mt-4 space-y-1">
                        <li v-for="queue in queues" :key="queue.call_center_queue_uuid">
                            <a :href="settingsUrl(queue.call_center_queue_uuid)"
                                class="block rounded-md px-3 py-2 text-sm"
                                :class="selectedQueueUuid === queue.call_center_queue_uuid
                                    ? 'bg-cyan-50 font-medium text-cyan-800'
                                    : 'text-gray-700 hover:bg-gray-50'">
                                <span>{{ queue.queue_name }}</span>
                                <span class="ml-2 text-gray-400">{{ queue.queue_extension }}</span>
                            </a>
                        </li>
                        <li v-if="!queues.length" class="px-3 py-6 text-sm text-gray-500">
                            No contact centers yet. Click Add to create one.
                        </li>
                    </ul>
                </section>

                <section class="rounded-lg bg-white p-4 shadow ring-1 ring-gray-200 lg:col-span-8">
                    <div v-if="!selectedQueueUuid" class="py-16 text-center text-sm text-gray-500">
                        Select a contact center on the left, or create a new one.
                    </div>

                    <template v-else>
                        <div v-if="loadingOptions" class="py-16 text-center text-sm text-gray-500">
                            Loading contact center settings...
                        </div>

                        <BasicQueueForm v-else-if="itemOptions" :show="true" :inline="true" :extended-fields="true"
                            mode="update" :options="itemOptions" :loading="false" header="Contact Center Settings"
                            @success="handleSaveSuccess" @error="handleFormError" @refresh-data="loadItemOptions" />

                        <div class="mt-6 flex justify-end border-t border-gray-200 pt-6">
                            <button type="button"
                                class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                                :disabled="deleteLoading" @click="deleteQueue">
                                Delete Contact Center
                            </button>
                        </div>
                    </template>
                </section>
            </div>

            <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
                @update:show="notificationShow = false" />
        </main>
    </MainLayout>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import axios from 'axios';
import MainLayout from '@layouts/MainLayout.vue';
import Notification from '../../../../../../resources/js/Pages/components/notifications/Notification.vue';
import BasicQueueForm from '../../../../../../resources/js/Pages/components/forms/BasicQueueForm.vue';

const props = defineProps({
    queues: {
        type: Array,
        default: () => [],
    },
    selectedQueueUuid: {
        type: String,
        default: null,
    },
    routes: {
        type: Object,
        required: true,
    },
});

const itemOptions = ref(null);
const loadingOptions = ref(!!props.selectedQueueUuid);
const deleteLoading = ref(false);
const notificationShow = ref(false);
const notificationType = ref(null);
const notificationMessages = ref(null);

const settingsUrl = (queueUuid) => props.routes.settings_show.replace('__QUEUE__', queueUuid);

const showNotification = (type, messages) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
};

const loadItemOptions = async () => {
    if (!props.selectedQueueUuid || !props.routes.queue_item_options) {
        itemOptions.value = null;
        return;
    }

    loadingOptions.value = true;

    try {
        const response = await axios.post(props.routes.queue_item_options, {
            itemUuid: props.selectedQueueUuid,
        });
        itemOptions.value = response.data;
    } catch (error) {
        showNotification('error', error?.response?.data?.messages ?? {
            error: ['Failed to load contact center settings.'],
        });
        itemOptions.value = null;
    } finally {
        loadingOptions.value = false;
    }
};

onMounted(() => {
    loadItemOptions();
});

watch(() => props.selectedQueueUuid, () => {
    loadItemOptions();
});

const handleSaveSuccess = (type, messages) => {
    showNotification(type, messages);
};

const handleFormError = (error) => {
    const method = error?.response?.config?.method?.toLowerCase();
    const url = error?.response?.config?.url || '';

    // Ignore background load failures from greeting/routing helpers on page refresh.
    if (method !== 'put' && !url.includes('/contact-center/settings/')) {
        return;
    }

    const responseErrors = error?.response?.data?.errors;
    const messages = error?.response?.data?.messages;

    if (messages) {
        showNotification('error', messages);
        return;
    }

    if (responseErrors) {
        showNotification('error', {
            error: Object.values(responseErrors).flat(),
        });
        return;
    }

    showNotification('error', {
        error: [error?.response?.data?.message || 'Failed to save contact center.'],
    });
};

const deleteQueue = async () => {
    if (!props.routes.destroy || !window.confirm('Delete this contact center?')) {
        return;
    }

    deleteLoading.value = true;

    try {
        const response = await axios.delete(props.routes.destroy);
        const redirectUrl = response?.data?.redirect_url ?? props.routes.settings_list;
        window.location.href = redirectUrl;
    } catch (error) {
        showNotification('error', error?.response?.data?.errors ?? { error: ['Failed to delete contact center.'] });
        deleteLoading.value = false;
    }
};
</script>
