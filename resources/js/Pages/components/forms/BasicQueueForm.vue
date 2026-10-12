<template>
    <div v-if="inline" v-show="show">
        <BasicQueueFormFields ref="fieldsRef" :options="options" :loading="loading" :mode="mode"
            :extended-fields="extendedFields" @close="emit('close')" @error="(...args) => emit('error', ...args)"
            @success="(...args) => emit('success', ...args)" @refresh-data="emit('refresh-data')" />
    </div>

    <TransitionRoot v-else as="div" :show="show">
        <Dialog as="div" class="relative z-10" @close="emit('close')">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-5xl sm:p-6">
                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>

                            <button type="button"
                                class="absolute right-4 top-4 rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                @click="emit('close')">
                                <span class="sr-only">{{ $t("Close") }}</span>
                                <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                            </button>

                            <BasicQueueFormFields ref="fieldsRef" :options="options" :loading="loading" :mode="mode"
                                :extended-fields="extendedFields" @close="emit('close')" @error="(...args) => emit('error', ...args)"
                                @success="(...args) => emit('success', ...args)" @refresh-data="emit('refresh-data')" />
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>

    </TransitionRoot>
</template>

<script setup>
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import { XMarkIcon } from "@heroicons/vue/24/solid";
import BasicQueueFormFields from "./BasicQueueFormFields.vue";

const formLocale = useVueformLocale();

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: String,
    inline: {
        type: Boolean,
        default: false,
    },
    extendedFields: {
        type: Boolean,
        default: false,
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);
const form$ = ref(null);
const availableGreetings = ref(null);
const currentAudio = ref(null);
const currentAudioGreeting = ref(null);
const greetingLabel = ref(null);
const isAudioPlaying = ref(false);
const isDownloading = ref(false);
const isGreetingUpdating = ref(false);
const showEditModal = ref(false);
const showGreetingDeleteConfirmationModal = ref(false);
const showNewGreetingModal = ref(false);

const buttonIconClassOverrides = {
    ButtonElement: {
        button_secondary: ["form-bg-btn-secondary"],
        button: ["form-border-width-btn"],
        button_enabled: ["focus:form-ring"],
        button_md: ["form-p-btn"],
    },
};

const strategyOptions = computed(() => [
    { value: "ring-all", label: trans("Ring All") },
    { value: "longest-idle-agent", label: trans("Longest Idle Agent") },
    { value: "round-robin", label: trans("Round Robin") },
    { value: "top-down", label: trans("Top Down") },
    { value: "agent-with-least-talk-time", label: trans("Least Talk Time") },
    { value: "agent-with-fewest-calls", label: trans("Fewest Calls") },
    { value: "sequentially-by-agent-order", label: trans("Sequential Agent Order") },
    { value: "random", label: trans("Random") },
]);

const tierOptions = Array.from({ length: 10 }, (_, i) => {
    const value = String(i + 1);
    return { value, label: value };
});

const defaultValues = computed(() => ({
    queue_name: props.options?.item?.queue_name ?? null,
    queue_extension: props.options?.item?.queue_extension ?? null,
    queue_strategy: props.options?.item?.queue_strategy ?? "ring-all",
    queue_greeting: props.options?.item?.queue_greeting || "disabled",
    queue_moh_sound: props.options?.item?.queue_moh_sound ?? "local_stream://default",
    queue_max_wait_time: props.options?.item?.queue_max_wait_time ?? 0,
    queue_max_wait_time_with_no_agent: props.options?.item?.queue_max_wait_time_with_no_agent ?? 90,
    queue_tier_rules_apply: props.options?.item?.queue_tier_rules_apply ?? "false",
    queue_cid_prefix: props.options?.item?.queue_cid_prefix ?? null,
    timeout_action: props.options?.item?.timeout_action ?? null,
    timeout_target: {
        value: props.options?.item?.timeout_target_uuid ?? null,
        extension: props.options?.item?.timeout_target_extension ?? null,
        name: props.options?.item?.timeout_target_name ?? null,
    },
    queue_description: props.options?.item?.queue_description ?? null,
    tiers: (props.options?.tiers ?? []).map((tier) => ({
        call_center_agent_uuid: tier.call_center_agent_uuid,
        agent_label: tier.agent_label || tier.agent_name || null,
        tier_level: String(tier.tier_level ?? 1),
        tier_position: String(tier.tier_position ?? 1),
    })),
}));

const agentOptions = computed(() => props.options?.agent_options ?? []);
const routingTypes = computed(() => props.options?.routing_types ?? []);
const musicOnHoldOptions = computed(() => props.options?.music_on_hold_options ?? []);
const greetingTranscription = computed(() => {
    const selectedId = form$.value?.data?.queue_greeting ?? null;

    if (!selectedId || !availableGreetings.value) {
        return null;
    }

    const selectedItem = availableGreetings.value.find(
        (item) => String(item.value) === String(selectedId),
    );

    return selectedItem?.description || null;
});

const availableAgentOptions = computed(() => {
    const tiersField = form$.value?.el$("tiers");
    const currentTiers = tiersField?.value || defaultValues.value.tiers || [];
    const selectedAgentUuids = currentTiers.map((tier) => tier.call_center_agent_uuid).filter(Boolean);

    return [
        {
            label: trans("Agents"),
            items: agentOptions.value.filter((agent) => !selectedAgentUuids.includes(agent.value)),
        },
    ];
});

const handleAgentSelect = (option) => {
    const currentTiers = form$.value?.el$("tiers")?.value || [];

    form$.value.update({
        tiers: [
            ...currentTiers,
            {
                call_center_agent_uuid: option.value,
                agent_label: option.label,
                tier_level: "1",
                tier_position: "1",
            },
        ],
    });

    form$.value.el$("selected_agents").update([]);
};

const getAgentLabel = (agentUuid, fallback = null) => {
    const agent = agentOptions.value.find((option) => option.value === agentUuid);

    return agent?.label || fallback || agentUuid || trans("Agent");
};

const fetchGreetings = async () => {
    const route = props.options?.routes?.greeting_route;

    if (!route) {
        availableGreetings.value = [{ value: "disabled", label: trans("No greeting") }];
        return availableGreetings.value;
    }

    try {
        const response = await axios.get(route);
        availableGreetings.value = [
            { value: "disabled", label: trans("No greeting") },
            ...(response.data || []),
        ];
        return availableGreetings.value;
    } catch (error) {
        emit("error", error);
        availableGreetings.value = [{ value: "disabled", label: trans("No greeting") }];
        return availableGreetings.value;
    }
};

const getSelectedGreetingFileName = () => {
    return form$.value?.data?.queue_greeting ?? null;
};

const hasPlayableGreeting = (form$) => {
    const val = form$?.el$("queue_greeting")?.value ?? null;

    return val !== "disabled" && val !== "0" && val !== "-1" && val !== null && val !== "";
};

const showNotification = (type, messages = null) => {
    emit("success", type, messages);
};

const showNotificationFromChild = (type, messages = null) => {
    if (typeof type === "string") {
        showNotification(type, messages);
        return;
    }

    showNotification("success", type);
};

const handleNewGreetingButtonClick = () => {
    stopGreetingAudio();
    showNewGreetingModal.value = true;
};

const playGreeting = () => {
    const greeting = getSelectedGreetingFileName();

    if (!greeting || !props.options?.routes?.serve_greeting_route) {
        return;
    }

    if (currentAudio.value && currentAudioGreeting.value === greeting) {
        if (currentAudio.value.paused) {
            currentAudio.value.play();
            isAudioPlaying.value = true;
        }
        return;
    }

    stopGreetingAudio();

    const fileUrl = props.options.routes.serve_greeting_route.replace(":file_name", encodeURIComponent(greeting));

    currentAudio.value = new Audio(fileUrl);
    currentAudioGreeting.value = greeting;
    isAudioPlaying.value = true;

    currentAudio.value.play().catch(() => {
        isAudioPlaying.value = false;
        showNotification("error", { request: [trans("Audio playback failed")] });
    });

    currentAudio.value.addEventListener("ended", () => {
        isAudioPlaying.value = false;
    });

    currentAudio.value.addEventListener("error", () => {
        isAudioPlaying.value = false;
        showNotification("error", { request: [trans("File not found or failed to load audio")] });
    });
};

const pauseGreeting = () => {
    if (currentAudio.value) {
        currentAudio.value.pause();
        isAudioPlaying.value = false;
    }
};

const stopGreetingAudio = () => {
    if (currentAudio.value) {
        currentAudio.value.pause();
        currentAudio.value.currentTime = 0;
        currentAudio.value = null;
    }

    isAudioPlaying.value = false;
    currentAudioGreeting.value = null;
};

const downloadGreeting = () => {
    isDownloading.value = true;
    const greeting = getSelectedGreetingFileName();

    if (!greeting || !props.options?.routes?.serve_greeting_route) {
        isDownloading.value = false;
        return;
    }

    const downloadUrl = props.options.routes.serve_greeting_route.replace(":file_name", encodeURIComponent(greeting))
        + `?download=true&v=${Date.now()}`;

    const link = document.createElement("a");
    link.href = downloadUrl;
    link.download = greeting || "greeting.wav";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    isDownloading.value = false;
};

const editGreeting = () => {
    const selectedId = getSelectedGreetingFileName();

    if (!selectedId || !availableGreetings.value) {
        return;
    }

    const selectedItem = availableGreetings.value.find(
        (item) => String(item.value) === String(selectedId),
    );

    if (selectedItem) {
        greetingLabel.value = selectedItem;
        showEditModal.value = true;
    }
};

const handleGreetingUpdate = async (updatedGreeting) => {
    const newName = updatedGreeting?.label?.trim();

    if (!newName) {
        showNotification("error", { request: [trans("Greeting name cannot be empty.")] });
        return;
    }

    isGreetingUpdating.value = true;

    try {
        const response = await axios.post(props.options.routes.update_greeting_route, {
            file_name: updatedGreeting.value,
            new_name: updatedGreeting.label,
        });

        if (response.data.success) {
            form$.value.el$("queue_greeting").clear();
            await form$.value.el$("queue_greeting").updateItems();
            form$.value.update({ queue_greeting: updatedGreeting.value });
            showNotification("success", response.data.messages);
        }
    } catch (error) {
        emit("error", error);
    } finally {
        isGreetingUpdating.value = false;
        showEditModal.value = false;
    }
};

const deleteGreeting = () => {
    stopGreetingAudio();
    showGreetingDeleteConfirmationModal.value = true;
};

const confirmGreetingDeleteAction = async () => {
    const fileName = getSelectedGreetingFileName();

    if (!fileName) {
        showGreetingDeleteConfirmationModal.value = false;
        return;
    }

    try {
        const response = await axios.post(props.options.routes.delete_greeting_route, { file_name: fileName });

        if (response.data.success) {
            stopGreetingAudio();

            if (availableGreetings.value) {
                availableGreetings.value = availableGreetings.value.filter(
                    (greeting) => String(greeting.value) !== String(fileName),
                );
            }

            form$.value.update({ queue_greeting: "disabled" });
            await form$.value.el$("queue_greeting").updateItems();
            showNotification("success", response.data.messages);
        }
    } catch (error) {
        emit("error", error);
    } finally {
        showGreetingDeleteConfirmationModal.value = false;
    }
};

const handleNewGreetingAdded = async (greetingId) => {
    await form$.value.el$("queue_greeting").updateItems();
    form$.value.update({ queue_greeting: greetingId });
    showNewGreetingModal.value = false;
};

const getRoutesForGreetingForm = computed(() => ({
    ...props.options?.routes,
    text_to_speech_route: props.options?.routes?.text_to_speech_route ?? null,
    upload_greeting_route: props.options?.routes?.upload_greeting_route ?? null,
}));

const formatTarget = (name, value) => {
    return { [name]: value?.bridge_uuid ?? value?.extension ?? null };
};

const submitForm = async (FormData, form$) => {
    form$.messageBag.clear();
    Object.values(form$.elements$).forEach(clearErrorsRecursive);
    const requestData = form$.requestData;
    const route = props.mode === "create"
        ? props.options.routes.store_route
        : props.options.routes.update_route;

    return props.mode === "create"
        ? await form$.$vueform.services.axios.post(route, requestData)
        : await form$.$vueform.services.axios.put(route, requestData);
};

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear();
    if (el$.children$) {
        Object.values(el$.children$).forEach((childEl$) => clearErrorsRecursive(childEl$));
    }
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach((el$) => clearErrorsRecursive(el$));

    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0]);
            }
        });
    }
};

const handleSuccess = (response) => {
    emit("success", "success", response.data.messages);
    emit("refresh-data");
    emit("close");
};

const handleError = (error, details, form$) => {
    form$.messageBag.clear();

    if (details.type === "submit") {
        emit("error", error);
        return;
    }

    form$.messageBag.append(trans("Could not submit form"));
};
</script>
