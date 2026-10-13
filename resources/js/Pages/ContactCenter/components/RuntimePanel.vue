<template>
    <div class="space-y-6">
        <div v-if="part === 'callbacks'">
            <p class="text-sm text-gray-500">{{ $t('Callers can leave the queue and receive a return call when an agent is free.') }}</p>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2" @keydown.enter.prevent="saveCallbacks">
                <label class="flex items-center gap-2 text-sm text-gray-700 sm:col-span-2">
                    <input v-model="callbacks.enabled" type="checkbox" class="rounded border-gray-300" />
                    {{ $t('Enable queue callbacks') }}
                </label>
                <label class="text-sm text-gray-700">
                    {{ $t('Greeting we play when calling back') }}
                    <input v-model="callbacks.introduction" type="text" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
                </label>
                <label class="text-sm text-gray-700">
                    {{ $t('How we ask them to accept') }}
                    <input v-model="callbacks.instruction" type="text" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
                </label>
                <label class="text-sm text-gray-700">
                    {{ $t('Offer a callback after') }}
                    <input v-model.number="callbacks.offer_after_seconds" type="number" min="10" max="3600" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
                </label>
                <label class="text-sm text-gray-700">
                    {{ $t('Key the caller presses to accept') }}
                    <input v-model="callbacks.offer_key" maxlength="1" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
                </label>
                <label class="text-sm text-gray-700">
                    {{ $t('Stop calling people back at') }}
                    <input v-model="callbacks.closing_time" type="time" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
                </label>
                <label class="text-sm text-gray-700">
                    {{ $t('Time zone') }}
                    <input v-model="callbacks.timezone" type="text" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
                </label>
                <label class="text-sm text-gray-700">
                    {{ $t('Try each callback up to') }}
                    <input v-model.number="callbacks.max_attempts" type="number" min="1" max="5" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
                </label>
                <label class="text-sm text-gray-700">
                    {{ $t('Wait between tries') }}
                    <input v-model.number="callbacks.retry_seconds" type="number" min="60" max="3600" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
                </label>
                <div class="flex justify-end sm:col-span-2">
                    <button type="button" class="inline-flex items-center justify-center rounded border border-indigo-500 bg-indigo-500 px-4 py-2 text-sm font-medium text-white hover:border-indigo-800 hover:bg-indigo-800 disabled:opacity-50" :disabled="saving" @click="saveCallbacks">
                        {{ $t('Save callback settings') }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="part === 'audio'">
            <p class="text-sm text-gray-500">{{ $t('Continuous stream keeps the queue music. Audio sequence starts at the first clip for each caller.') }}</p>
            <div class="mt-4 space-y-3" @keydown.enter.prevent="saveAudio">
                <label class="text-sm text-gray-700">
                    {{ $t('Mode') }}
                    <select v-model="audio.mode" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="continuous">{{ $t('Continuous stream') }}</option>
                        <option value="sequence">{{ $t('Audio sequence') }}</option>
                    </select>
                </label>
                <p class="text-sm text-gray-500">{{ $t('Status') }}: {{ audio.status || $t('Not prepared') }}<span v-if="audio.error"> — {{ audio.error }}</span></p>
                <div v-for="(step, index) in audio.steps" :key="index" class="flex flex-wrap items-end gap-2">
                    <label class="text-sm text-gray-700">
                        {{ $t('Step') }}
                        <select v-model="step.type" class="mt-1 block rounded-md border-gray-300 text-sm">
                            <option value="clip">{{ $t('Audio clip') }}</option>
                            <option value="callback">{{ $t('Callback offer') }}</option>
                            <option value="position">{{ $t('Queue position') }}</option>
                        </select>
                    </label>
                    <label v-if="step.type === 'clip'" class="min-w-48 flex-1 text-sm text-gray-700">
                        {{ $t('Recording') }}
                        <select v-model="step.recording" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option v-for="recording in recordings" :key="recording.value" :value="recording.value">{{ recording.label }}</option>
                        </select>
                    </label>
                    <label v-if="step.type === 'clip'" class="text-sm text-gray-700">
                        {{ $t('Duration limit') }}
                        <input v-model.number="step.duration_seconds" type="number" min="1" class="mt-1 block w-24 rounded-md border-gray-300 text-sm" />
                    </label>
                    <button type="button" class="text-sm text-red-600" @click="audio.steps.splice(index, 1)">{{ $t('Remove') }}</button>
                </div>
                <div class="flex gap-3">
                    <button type="button" class="text-sm font-medium text-indigo-600 hover:text-indigo-500" @click="audio.steps.push({ type: 'clip', recording: '', duration_seconds: null })">{{ $t('Add audio') }}</button>
                    <button type="button" class="text-sm font-medium text-indigo-600 hover:text-indigo-500" @click="audio.steps.push({ type: 'callback' })">{{ $t('Add callback offer') }}</button>
                    <button type="button" class="text-sm font-medium text-indigo-600 hover:text-indigo-500" @click="audio.steps.push({ type: 'position' })">{{ $t('Add queue position') }}</button>
                </div>
                <div class="flex justify-end">
                    <button type="button" class="inline-flex items-center justify-center rounded border border-indigo-500 bg-indigo-500 px-4 py-2 text-sm font-medium text-white hover:border-indigo-800 hover:bg-indigo-800 disabled:opacity-50" :disabled="saving" @click="saveAudio">
                        {{ $t('Save') }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="part === 'callbacks'">
            <h2 class="text-sm font-semibold text-gray-900">{{ $t('Callback history') }}</h2>
            <table class="mt-3 min-w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="py-1 pr-3">{{ $t('Number') }}</th>
                        <th class="py-1 pr-3">{{ $t('Status') }}</th>
                        <th class="py-1 pr-3">{{ $t('Tries') }}</th>
                        <th class="py-1 pr-3">{{ $t('Next action') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in history" :key="row.contact_center_callback_uuid" class="border-t border-gray-100">
                        <td class="py-2 pr-3">{{ row.number }}</td>
                        <td class="py-2 pr-3">{{ row.status }}</td>
                        <td class="py-2 pr-3">{{ row.attempts }}</td>
                        <td class="py-2 pr-3">{{ row.next_attempt_at || '—' }}</td>
                        <td class="py-2 text-right">
                            <button v-if="row.status === 'waiting' || row.status === 'dialing_agent'" type="button" class="text-red-600" @click="cancel(row)">
                                {{ $t('Cancel') }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!history.length">
                        <td colspan="5" class="py-3 text-gray-500">{{ $t('No callback requests yet.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    routes: { type: Object, required: true },
    greetingRoute: { type: String, default: '' },
    part: { type: String, default: 'callbacks' },
});

const emit = defineEmits(['notify']);

const saving = ref(false);
const recordings = ref([]);
const history = ref([]);
const callbacks = ref({
    enabled: false,
    introduction: '',
    instruction: '',
    offer_after_seconds: 90,
    offer_key: '9',
    timezone: 'UTC',
    closing_time: '17:00',
    max_attempts: 3,
    retry_seconds: 600,
});
const audio = ref({ mode: 'continuous', status: 'idle', error: null, steps: [] });

const load = async () => {
    const response = await axios.get(props.routes.runtime);
    callbacks.value = { ...callbacks.value, ...response.data.callbacks };
    audio.value = { ...audio.value, ...response.data.audio, steps: response.data.audio?.steps || [] };
    history.value = response.data.history || [];
};

const saveCallbacks = async () => {
    saving.value = true;
    try {
        const response = await axios.put(props.routes.runtime_callbacks, callbacks.value);
        emit('notify', 'success', response.data.messages);
        await load();
    } catch (error) {
        emit('notify', 'error', error?.response?.data?.errors || { error: [error.message] });
    } finally {
        saving.value = false;
    }
};

const saveAudio = async () => {
    saving.value = true;
    try {
        const response = await axios.put(props.routes.runtime_audio, {
            mode: audio.value.mode,
            steps: audio.value.steps,
        });
        emit('notify', 'success', response.data.messages);
        await load();
    } catch (error) {
        emit('notify', 'error', error?.response?.data?.errors || { error: [error.message] });
    } finally {
        saving.value = false;
    }
};

const cancel = async (row) => {
    try {
        const response = await axios.post(`${props.routes.runtime_callbacks}/${row.contact_center_callback_uuid}/cancel`);
        emit('notify', 'success', response.data.messages);
        await load();
    } catch (error) {
        emit('notify', 'error', error?.response?.data?.errors || { error: [error.message] });
    }
};

onMounted(async () => {
    if (props.part === 'audio' && props.greetingRoute) {
        const response = await axios.get(props.greetingRoute);
        recordings.value = (response.data?.data || response.data || []).filter((item) => item.value && item.value !== '0');
    }
    await load();
});
</script>
