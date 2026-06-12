<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-20" @close="emit('close')">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-20 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:p-6">
                            <DialogTitle as="h3" class="text-base font-semibold leading-6 text-gray-900">
                                Contact Sync
                            </DialogTitle>
                            <p class="mt-2 text-sm text-gray-600">
                                Connect Google or Microsoft 365, then sync contacts into the domain phonebook.
                                OAuth app credentials are configured under Default Settings or Domain Settings
                                (category Contact).
                            </p>

                            <div v-if="loading" class="py-8 text-center text-sm text-gray-500">Loading...</div>

                            <div v-else class="mt-6 space-y-4">
                                <div v-for="provider in providers" :key="provider.key"
                                    class="rounded-lg border border-gray-200 p-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <h4 class="text-sm font-semibold text-gray-900">{{ provider.label }}</h4>
                                            <p class="mt-1 text-sm text-gray-500">
                                                <span v-if="!provider.status.configured">OAuth credentials not configured.</span>
                                                <span v-else-if="provider.status.connected">
                                                    Connected as {{ provider.status.account_email || 'unknown account' }}.
                                                </span>
                                                <span v-else>Not connected.</span>
                                            </p>
                                            <p v-if="provider.status.last_sync_at" class="mt-1 text-xs text-gray-400">
                                                Last sync: {{ formatDate(provider.status.last_sync_at) }}
                                                <span v-if="provider.status.last_sync_message"> — {{ provider.status.last_sync_message }}</span>
                                            </p>
                                        </div>
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <a v-if="permissions.connect && provider.status.configured && !provider.status.connected"
                                                :href="provider.connectRoute"
                                                class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">
                                                Connect
                                            </a>
                                            <button v-if="permissions.sync && provider.status.connected" type="button"
                                                :disabled="provider.busy"
                                                class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                                                @click="runSync(provider.key)">
                                                Sync now
                                            </button>
                                            <button v-if="permissions.connect && provider.status.connected" type="button"
                                                :disabled="provider.busy"
                                                class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                                                @click="disconnect(provider.key)">
                                                Disconnect
                                            </button>
                                        </div>
                                    </div>
                                    <label v-if="permissions.connect && provider.status.connected"
                                        class="mt-3 flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" :checked="provider.status.sync_enabled"
                                            :disabled="provider.busy"
                                            @change="toggleSync(provider.key, $event.target.checked)">
                                        Enable scheduled sync
                                    </label>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <button type="button"
                                    class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                    @click="emit('close')">
                                    Close
                                </button>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import axios from "axios";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";

const props = defineProps({
    show: Boolean,
    routes: Object,
    permissions: Object,
});

const emit = defineEmits(["close", "success", "error", "synced"]);

const loading = ref(false);
const status = ref({
    google: {},
    microsoft: {},
});
const syncPermissions = ref({
    connect: false,
    sync: false,
});
const busyProviders = ref({
    google: false,
    microsoft: false,
});

const permissions = computed(() => ({
    connect: props.permissions?.sync_connect || syncPermissions.value.connect,
    sync: props.permissions?.sync_run || syncPermissions.value.sync,
}));

const providers = computed(() => [
    {
        key: "google",
        label: "Google Contacts",
        connectRoute: props.routes?.connect_google,
        status: status.value.google || {},
        busy: busyProviders.value.google,
    },
    {
        key: "microsoft",
        label: "Microsoft 365",
        connectRoute: props.routes?.connect_microsoft,
        status: status.value.microsoft || {},
        busy: busyProviders.value.microsoft,
    },
]);

watch(() => props.show, (visible) => {
    if (visible) {
        loadStatus();
    }
});

const loadStatus = () => {
    loading.value = true;

    axios.get(props.routes.sync_status)
        .then((response) => {
            status.value = response.data.providers;
            syncPermissions.value = response.data.permissions;
        })
        .catch((error) => emit("error", error))
        .finally(() => {
            loading.value = false;
        });
};

const routeFor = (template, provider) => template.replace(":provider", provider);

const runSync = (provider) => {
    busyProviders.value[provider] = true;

    axios.post(routeFor(props.routes.sync_run, provider))
        .then((response) => {
            if (response.data.providers?.[provider]) {
                status.value[provider] = response.data.providers[provider];
            }
            emit("success", response.data.messages);
            emit("synced");
        })
        .catch((error) => emit("error", error))
        .finally(() => {
            busyProviders.value[provider] = false;
        });
};

const disconnect = (provider) => {
    busyProviders.value[provider] = true;

    axios.post(routeFor(props.routes.sync_disconnect, provider))
        .then((response) => {
            loadStatus();
            emit("success", response.data.messages);
        })
        .catch((error) => emit("error", error))
        .finally(() => {
            busyProviders.value[provider] = false;
        });
};

const toggleSync = (provider, enabled) => {
    busyProviders.value[provider] = true;

    axios.post(routeFor(props.routes.sync_toggle, provider), { enabled })
        .then((response) => {
            if (response.data.providers?.[provider]) {
                status.value[provider] = response.data.providers[provider];
            }
            emit("success", response.data.messages);
        })
        .catch((error) => emit("error", error))
        .finally(() => {
            busyProviders.value[provider] = false;
        });
};

const formatDate = (value) => {
    if (!value) {
        return "";
    }

    return new Date(value).toLocaleString();
};
</script>
