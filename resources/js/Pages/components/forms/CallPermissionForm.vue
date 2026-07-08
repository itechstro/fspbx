<template>
    <AddEditItemModal :show="show" :header="header" :loading="loading" @close="emit('close')">
        <template #modal-body>
            <Vueform
                ref="form$"
                :endpoint="submitForm"
                :display-errors="false"
                :default="defaultValues"
                @success="handleSuccess"
                @error="handleError"
                @response="handleResponse"
            >
                <TextElement
                    name="name"
                    label="Name"
                    placeholder="e.g. International Staff"
                    :floating="false"
                    :rules="['required', 'max:255']"
                />

                <TextareaElement
                    name="description"
                    label="Description"
                    :floating="false"
                    :rows="2"
                    :rules="['max:1000']"
                />

                <TextElement
                    name="toll_allow"
                    label="Toll Allow"
                    placeholder="local,domestic,international"
                    description="Comma-separated tokens matched by outbound routes."
                    :floating="false"
                    :rules="['max:1000']"
                />

                <SelectElement
                    name="default_action"
                    label="Default Action"
                    :items="defaultActionOptions"
                    :native="false"
                    :search="false"
                    description="Used when no destination rule matches."
                    :floating="false"
                    :rules="['required']"
                />

                <ToggleElement
                    name="enabled"
                    text="Enabled"
                    true-value="true"
                    false-value="false"
                    :labels="{ on: 'On', off: 'Off' }"
                    label="&nbsp;"
                />

                <StaticElement name="destinations_heading" tag="h3" content="Destination Rules" />

                <StaticElement
                    name="destinations_help"
                    tag="p"
                    content="Match dialed numbers by prefix. Use * as a wildcard suffix. First matching rule wins."
                />

                <ListElement
                    name="destinations"
                    size="sm"
                    :initial="0"
                    :add-classes="{ ListElement: { listItem: 'bg-gray-50 p-4 mb-4 rounded-lg border border-gray-200' } }"
                >
                    <template #default="{ index }">
                        <ObjectElement :name="index">
                            <HiddenElement name="call_permission_destination_uuid" />

                            <TextElement
                                name="destination_prefix"
                                label="Number Prefix"
                                placeholder="e.g. 011* or 1900"
                                :floating="false"
                                :rules="['max:255']"
                            />

                            <SelectElement
                                name="destination_action"
                                label="Action"
                                :items="destinationActionOptions"
                                :native="false"
                                :search="false"
                                :floating="false"
                            />

                            <TextElement
                                name="destination_description"
                                label="Description"
                                :floating="false"
                                :rules="['max:255']"
                            />

                            <ToggleElement
                                name="enabled"
                                text="Enabled"
                                true-value="true"
                                false-value="false"
                                :labels="{ on: 'On', off: 'Off' }"
                                label="&nbsp;"
                            />
                        </ObjectElement>
                    </template>
                </ListElement>

                <GroupElement name="button_container" />

                <ButtonElement name="submit" button-label="Save" :submits="true" align="right" />
            </Vueform>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { computed, ref } from "vue";
import AddEditItemModal from "../modal/AddEditItemModal.vue";

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: {
        type: String,
        default: "Call Permissions",
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);

const form$ = ref(null);

const defaultActionOptions = [
    { value: "allow", label: "Allow" },
    { value: "deny", label: "Deny" },
];

const destinationActionOptions = [
    { value: "allow", label: "Allow" },
    { value: "deny", label: "Deny" },
];

const defaultValues = computed(() => ({
    name: props.options?.item?.name ?? null,
    description: props.options?.item?.description ?? null,
    toll_allow: props.options?.item?.toll_allow ?? null,
    default_action: props.options?.item?.default_action ?? "allow",
    enabled: props.options?.item?.enabled ?? "true",
    destinations: (props.options?.item?.destinations ?? []).map((destination) => ({
        call_permission_destination_uuid: destination.call_permission_destination_uuid ?? null,
        destination_prefix: destination.destination_prefix ?? null,
        destination_action: destination.destination_action ?? "deny",
        destination_description: destination.destination_description ?? null,
        enabled: destination.enabled ?? "true",
    })),
}));

const submitForm = async (FormData, form$) => {
    const route = props.mode === "create"
        ? props.options.routes.store_route
        : props.options.routes.update_route;

    if (props.mode === "create") {
        return await form$.$vueform.services.axios.post(route, form$.requestData);
    }

    return await form$.$vueform.services.axios.put(route, form$.requestData);
};

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear();

    if (el$.children$) {
        Object.values(el$.children$).forEach((childEl$) => {
            clearErrorsRecursive(childEl$);
        });
    }
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach((el$) => {
        clearErrorsRecursive(el$);
    });

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

const handleError = (error) => {
    emit("error", error);
};
</script>
