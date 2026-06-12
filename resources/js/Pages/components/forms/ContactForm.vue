<template>
    <TransitionRoot as="div" :show="show">
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
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-6xl sm:p-6">
                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full h-full py-10">
                                <div class="flex justify-center items-center space-x-3">
                                    <svg class="animate-spin h-10 w-10 text-blue-600"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <div class="text-lg text-blue-600 m-auto">Loading...</div>
                                </div>
                            </div>

                            <Vueform v-if="!loading" :key="formInstanceKey" ref="form$" :endpoint="submitForm"
                                @success="handleSuccess" @error="handleError" @response="handleResponse"
                                :display-errors="false" :default="defaultValues">
                                <template #empty>
                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="profile" label="Profile" :elements="profileTabElements" />
                                                <FormTab name="phones" label="Phones" :elements="phonesTabElements" />
                                                <FormTab name="emails" label="Emails" :elements="emailsTabElements" />
                                                <FormTab name="addresses" label="Addresses" :elements="addressesTabElements" />
                                                <FormTab name="notes" label="Notes" :elements="notesTabElements" />
                                                <FormTab name="urls" label="URLs" :elements="urlsTabElements" />
                                                <FormTab name="times" label="Times" :elements="timesTabElements" />
                                                <FormTab name="relations" label="Relations" :elements="relationsTabElements" />
                                                <FormTab v-if="showVisibilityTab" name="visibility" label="Visibility"
                                                    :elements="visibilityTabElements" />
                                                <FormTab v-if="mode === 'update'" name="attachments" label="Attachments"
                                                    :elements="attachmentsTabElements" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>
                                                <!-- Profile -->
                                                <StaticElement name="profile_header" tag="h4" content="Profile"
                                                    description="Name and organization shown in the phonebook." />

                                                <StaticElement name="contact_uuid_clean"
                                                    :conditions="[() => props.options?.item?.contact_uuid]">
                                                    <div class="mb-1">
                                                        <div class="text-sm font-medium text-gray-600 mb-1">Contact ID</div>
                                                        <div class="flex items-center group">
                                                            <span class="text-sm text-gray-900 select-all font-normal">
                                                                {{ props.options?.item?.contact_uuid }}
                                                            </span>
                                                            <button type="button"
                                                                @click="handleCopyToClipboard(props.options?.item?.contact_uuid)"
                                                                class="ml-2 p-1 rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-50"
                                                                title="Copy to clipboard">
                                                                <ClipboardDocumentIcon class="h-4 w-4" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </StaticElement>

                                                <RadiogroupElement name="contact_type" label="Contact Type" view="tabs"
                                                    :items="contactTypeItems" />

                                                <TextElement name="contact_name_given" label="First Name"
                                                    placeholder="Given name" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />
                                                <TextElement name="contact_name_family" label="Last Name"
                                                    placeholder="Family name" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />
                                                <TextElement name="contact_organization" label="Organization"
                                                    placeholder="Company or department" :floating="false" />

                                                <TextElement name="contact_title" label="Title" placeholder="Job title"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />
                                                <TextElement name="contact_role" label="Role" placeholder="Role"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />
                                                <TextElement name="contact_category" label="Category"
                                                    placeholder="Optional grouping label" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />
                                                <SelectElement name="contact_time_zone" label="Time Zone" :groups="true"
                                                    :items="timezones" :search="true" :native="false"
                                                    input-type="search" :floating="false" :strict="false" allow-absent
                                                    placeholder="Select time zone" :columns="{ sm: { container: 6 } }" />
                                                <TextElement name="contact_url" label="Website"
                                                    placeholder="https://example.com" input-type="url"
                                                    :floating="false" />
                                                <TextareaElement name="contact_note" label="Summary Note" :rows="3"
                                                    placeholder="Primary note for this contact" />

                                                <GroupElement name="profile_button_container" />
                                                <ButtonElement name="profile_submit" button-label="Save" :submits="true"
                                                    align="right" />

                                                <!-- Phones -->
                                                <StaticElement name="phones_header" tag="h4" content="Phone Numbers"
                                                    description="Voice, mobile, fax, and speed-dial codes for this contact." />

                                                <ListElement name="phones" :sort="false" size="sm"
                                                    :controls="{ add: permissions.phone, remove: permissions.phone }"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-sm ring-1 ring-gray-200' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <HiddenElement name="contact_phone_uuid" :meta="true" />
                                                            <SelectElement name="phone_label" label="Label"
                                                                :items="phoneLabels" :search="true" :native="false"
                                                                :floating="false" :columns="{ sm: { container: 4 } }" />
                                                            <TextElement name="phone_number" label="Number"
                                                                placeholder="Phone number" :floating="false"
                                                                :columns="{ sm: { container: 4 } }" />
                                                            <TextElement name="phone_extension" label="Extension"
                                                                :floating="false" :columns="{ sm: { container: 4 } }" />
                                                            <TextElement name="phone_speed_dial" label="Speed Dial"
                                                                :floating="false" :columns="{ sm: { container: 4 } }" />
                                                            <ToggleElement name="phone_primary" text="Primary"
                                                                true-value="1" false-value="0"
                                                                :labels="{ on: 'Yes', off: 'No' }"
                                                                :columns="{ sm: { container: 4 } }" label="&nbsp;" />
                                                            <ToggleElement name="phone_type_voice" text="Voice"
                                                                true-value="1" false-value="0"
                                                                :labels="{ on: 'Yes', off: 'No' }"
                                                                :columns="{ sm: { container: 2 } }" label="&nbsp;" />
                                                            <ToggleElement name="phone_type_fax" text="Fax"
                                                                true-value="1" false-value="0"
                                                                :labels="{ on: 'Yes', off: 'No' }"
                                                                :columns="{ sm: { container: 2 } }" label="&nbsp;" />
                                                            <ToggleElement name="phone_type_text" text="Text"
                                                                true-value="1" false-value="0"
                                                                :labels="{ on: 'Yes', off: 'No' }"
                                                                :columns="{ sm: { container: 2 } }" label="&nbsp;" />
                                                            <TextElement name="phone_description" label="Description"
                                                                :floating="false" />
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <GroupElement name="phones_button_container" />
                                                <ButtonElement name="phones_submit" button-label="Save" :submits="true"
                                                    align="right" />

                                                <!-- Emails -->
                                                <StaticElement name="emails_header" tag="h4" content="Email Addresses" />

                                                <ListElement name="emails" :sort="false" size="sm"
                                                    :controls="{ add: permissions.email, remove: permissions.email }"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-sm ring-1 ring-gray-200' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <HiddenElement name="contact_email_uuid" :meta="true" />
                                                            <SelectElement name="email_label" label="Label"
                                                                :items="emailLabels" :search="true" :native="false"
                                                                :floating="false" :columns="{ sm: { container: 4 } }" />
                                                            <TextElement name="email_address" label="Email"
                                                                input-type="email" :floating="false"
                                                                :columns="{ sm: { container: 8 } }" />
                                                            <ToggleElement name="email_primary" text="Primary"
                                                                true-value="1" false-value="0"
                                                                :labels="{ on: 'Yes', off: 'No' }"
                                                                :columns="{ sm: { container: 4 } }" label="&nbsp;" />
                                                            <TextElement name="email_description" label="Description"
                                                                :floating="false" />
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <GroupElement name="emails_button_container" />
                                                <ButtonElement name="emails_submit" button-label="Save" :submits="true"
                                                    align="right" />

                                                <!-- Addresses -->
                                                <StaticElement name="addresses_header" tag="h4" content="Addresses" />

                                                <ListElement name="addresses" :sort="false" size="sm"
                                                    :controls="{ add: permissions.address, remove: permissions.address }"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-sm ring-1 ring-gray-200' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <HiddenElement name="contact_address_uuid" :meta="true" />
                                                            <SelectElement name="address_label" label="Label"
                                                                :items="addressLabels" :search="true" :native="false"
                                                                :floating="false" :columns="{ sm: { container: 4 } }" />
                                                            <ToggleElement name="address_primary" text="Primary"
                                                                true-value="1" false-value="0"
                                                                :labels="{ on: 'Yes', off: 'No' }"
                                                                :columns="{ sm: { container: 4 } }" label="&nbsp;" />
                                                            <TextElement name="address_street" label="Street"
                                                                :floating="false" />
                                                            <TextElement name="address_extended" label="Extended"
                                                                :floating="false" :columns="{ sm: { container: 6 } }" />
                                                            <TextElement name="address_locality" label="City"
                                                                :floating="false" :columns="{ sm: { container: 6 } }" />
                                                            <TextElement name="address_region" label="State/Region"
                                                                :floating="false" :columns="{ sm: { container: 4 } }" />
                                                            <TextElement name="address_postal_code" label="Postal Code"
                                                                :floating="false" :columns="{ sm: { container: 4 } }" />
                                                            <TextElement name="address_country" label="Country"
                                                                :floating="false" :columns="{ sm: { container: 4 } }" />
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <GroupElement name="addresses_button_container" />
                                                <ButtonElement name="addresses_submit" button-label="Save" :submits="true"
                                                    align="right" />

                                                <!-- Extra notes -->
                                                <StaticElement name="notes_header" tag="h4" content="Additional Notes"
                                                    description="Extra note records separate from the summary note on Profile." />

                                                <ListElement name="notes" :sort="false" size="sm"
                                                    :controls="{ add: permissions.note, remove: permissions.note }"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-sm ring-1 ring-gray-200' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <HiddenElement name="contact_note_uuid" :meta="true" />
                                                            <TextareaElement name="contact_note" label="Note" :rows="3" />
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <GroupElement name="notes_button_container" />
                                                <ButtonElement name="notes_submit" button-label="Save" :submits="true"
                                                    align="right" />

                                                <!-- URLs -->
                                                <StaticElement name="urls_header" tag="h4" content="URLs"
                                                    description="Additional website and social links." />

                                                <ListElement name="urls" :sort="false" size="sm"
                                                    :controls="{ add: permissions.url, remove: permissions.url }"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-sm ring-1 ring-gray-200' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <HiddenElement name="contact_url_uuid" :meta="true" />
                                                            <SelectElement name="url_type" label="Type" :items="urlTypes"
                                                                :search="true" :native="false" :floating="false"
                                                                :columns="{ sm: { container: 4 } }" />
                                                            <TextElement name="url_label" label="Label" :floating="false"
                                                                :columns="{ sm: { container: 4 } }" />
                                                            <TextElement name="url_address" label="URL"
                                                                input-type="url" :floating="false" />
                                                            <ToggleElement name="url_primary" text="Primary"
                                                                true-value="1" false-value="0"
                                                                :labels="{ on: 'Yes', off: 'No' }"
                                                                :columns="{ sm: { container: 4 } }" label="&nbsp;" />
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <GroupElement name="urls_button_container" />
                                                <ButtonElement name="urls_submit" button-label="Save" :submits="true"
                                                    align="right" />

                                                <!-- Times -->
                                                <StaticElement name="times_header" tag="h4" content="Availability Times" />

                                                <ListElement name="times" :sort="false" size="sm"
                                                    :controls="{ add: permissions.time, remove: permissions.time }"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-sm ring-1 ring-gray-200' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <HiddenElement name="contact_time_uuid" :meta="true" />
                                                            <DateElement name="time_start" label="Start"
                                                                :time="true" :seconds="true" :hour24="true"
                                                                value-format="YYYY-MM-DD HH:mm:ss"
                                                                load-format="YYYY-MM-DD HH:mm:ss"
                                                                display-format="YYYY-MM-DD HH:mm:ss"
                                                                :columns="{ sm: { container: 6 } }" />
                                                            <DateElement name="time_stop" label="Stop"
                                                                :time="true" :seconds="true" :hour24="true"
                                                                value-format="YYYY-MM-DD HH:mm:ss"
                                                                load-format="YYYY-MM-DD HH:mm:ss"
                                                                display-format="YYYY-MM-DD HH:mm:ss"
                                                                :columns="{ sm: { container: 6 } }" />
                                                            <TextElement name="time_description" label="Description"
                                                                :floating="false" />
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <GroupElement name="times_button_container" />
                                                <ButtonElement name="times_submit" button-label="Save" :submits="true"
                                                    align="right" />

                                                <!-- Relations -->
                                                <StaticElement name="relations_header" tag="h4" content="Related Contacts" />

                                                <ListElement name="relations" :sort="false" size="sm"
                                                    :controls="{ add: permissions.relation, remove: permissions.relation }"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-sm ring-1 ring-gray-200' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <HiddenElement name="contact_relation_uuid" :meta="true" />
                                                            <TextElement name="relation_label" label="Relationship"
                                                                placeholder="Manager, assistant, etc." :floating="false"
                                                                :columns="{ sm: { container: 6 } }" />
                                                            <SelectElement name="relation_contact_uuid" label="Contact"
                                                                :items="contactOptions" :search="true" :native="false"
                                                                input-type="search" :floating="false"
                                                                :columns="{ sm: { container: 6 } }" />
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <GroupElement name="relations_button_container" />
                                                <ButtonElement name="relations_submit" button-label="Save" :submits="true"
                                                    align="right" />

                                                <!-- Visibility -->
                                                <StaticElement name="visibility_header" tag="h4" content="Visibility"
                                                    description="Control which portal users and groups can see this contact when contact permissions are enabled." />

                                                <StaticElement name="visibility_extensions_note"
                                                    :conditions="[() => visibility.provision_contact_extensions]">
                                                    <div class="mb-4 rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-sm text-blue-900">
                                                        Extension directory entries are included separately in phonebook XML
                                                        when enabled in domain provisioning settings
                                                        ({{ visibility.directory_extension_count }} extension(s) currently visible).
                                                    </div>
                                                </StaticElement>

                                                <TagsElement v-if="permissions.user_view" name="contact_users"
                                                    :search="true" :items="userOptions" label="Assigned Users"
                                                    input-type="search" autocomplete="off"
                                                    placeholder="Choose assigned user(s)" :floating="false"
                                                    :strict="false" :object="true" :close-on-select="false"
                                                    :format-load="formatContactUsersLoad"
                                                    :disabled="[() => !permissions.user_edit]"
                                                    description="Portal users who can see this contact in their phonebook." />

                                                <TagsElement v-if="permissions.group_view" name="contact_groups"
                                                    :search="true" :items="groupOptions" label="Assigned Groups"
                                                    input-type="search" autocomplete="off"
                                                    placeholder="Choose assigned group(s)" :floating="false"
                                                    :strict="false" :object="true" :close-on-select="false"
                                                    :disabled="[() => !permissions.group_edit]"
                                                    description="Group members who can see this contact in their phonebook." />

                                                <GroupElement name="visibility_button_container" />
                                                <ButtonElement name="visibility_submit" button-label="Save" :submits="true"
                                                    align="right" />

                                                <!-- Attachments -->
                                                <StaticElement name="attachments_header" tag="h4" content="Attachments"
                                                    description="Upload files linked to this contact. Save the contact before uploading on create." />

                                                <ListElement name="attachments" :sort="false" size="sm"
                                                    :controls="{ add: false, remove: false }"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-sm ring-1 ring-gray-200' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <HiddenElement name="contact_attachment_uuid" :meta="true" />
                                                            <StaticElement :name="`attachment_name_${index}`">
                                                                <div class="text-sm font-medium text-gray-900 mb-2">
                                                                    {{ attachmentFilename(index) }}
                                                                </div>
                                                            </StaticElement>
                                                            <ToggleElement name="attachment_primary" text="Primary"
                                                                true-value="1" false-value="0"
                                                                :labels="{ on: 'Yes', off: 'No' }"
                                                                :columns="{ sm: { container: 4 } }" label="&nbsp;" />
                                                            <TextElement name="attachment_description" label="Description"
                                                                :floating="false" />
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <StaticElement name="attachment_upload_panel"
                                                    :conditions="[() => permissions.attachment_add && options?.routes?.attachment_store]">
                                                    <div class="mt-4 rounded-lg border border-dashed border-gray-300 bg-white p-4">
                                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                                            Upload attachment
                                                        </label>
                                                        <input ref="attachmentInput" type="file" class="block w-full text-sm text-gray-600"
                                                            @change="handleAttachmentSelected" />
                                                        <div class="mt-3 flex justify-end gap-2">
                                                            <button type="button"
                                                                class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                                                                :disabled="uploadingAttachment || !selectedAttachmentFile"
                                                                @click="uploadAttachment">
                                                                {{ uploadingAttachment ? 'Uploading...' : 'Upload' }}
                                                            </button>
                                                        </div>
                                                    </div>
                                                </StaticElement>

                                                <GroupElement name="attachments_button_container" />
                                                <ButtonElement name="attachments_submit" button-label="Save" :submits="true"
                                                    align="right" />
                                            </FormElements>
                                        </div>
                                    </div>
                                </template>
                            </Vueform>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed, ref } from "vue";
import axios from "axios";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";
import { XMarkIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: {
        type: String,
        default: "Contact",
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);

const form$ = ref(null);
const attachmentInput = ref(null);
const selectedAttachmentFile = ref(null);
const uploadingAttachment = ref(false);

const permissions = computed(() => props.options?.permissions ?? {});
const formInstanceKey = computed(() => props.options?.item?.contact_uuid || props.mode || "new");
const visibility = computed(() => props.options?.visibility ?? {});
const userOptions = computed(() => props.options?.user_options ?? []);
const groupOptions = computed(() => props.options?.group_options ?? []);
const showVisibilityTab = computed(() => visibility.value.permissions_enabled
    && (permissions.value.user_view || permissions.value.group_view));
const timezones = computed(() => props.options?.timezones ?? []);
const phoneLabels = computed(() => props.options?.phone_labels ?? []);
const emailLabels = computed(() => props.options?.email_labels ?? []);
const addressLabels = computed(() => props.options?.address_labels ?? []);
const urlTypes = computed(() => props.options?.url_types ?? []);
const contactOptions = computed(() => props.options?.contact_options ?? []);

const contactTypeItems = computed(() => {
    const types = props.options?.contact_types ?? [];

    if (types.length) {
        return Object.fromEntries(types.map((type) => [type.value, type.label]));
    }

    return { individual: "Individual", organization: "Organization" };
});

const profileTabElements = [
    "profile_header", "contact_uuid_clean", "contact_type", "contact_name_given", "contact_name_family",
    "contact_organization", "contact_title", "contact_role", "contact_category", "contact_time_zone",
    "contact_url", "contact_note", "profile_button_container", "profile_submit",
];

const phonesTabElements = ["phones_header", "phones", "phones_button_container", "phones_submit"];
const emailsTabElements = ["emails_header", "emails", "emails_button_container", "emails_submit"];
const addressesTabElements = ["addresses_header", "addresses", "addresses_button_container", "addresses_submit"];
const notesTabElements = ["notes_header", "notes", "notes_button_container", "notes_submit"];
const urlsTabElements = ["urls_header", "urls", "urls_button_container", "urls_submit"];
const timesTabElements = ["times_header", "times", "times_button_container", "times_submit"];
const relationsTabElements = ["relations_header", "relations", "relations_button_container", "relations_submit"];
const visibilityTabElements = [
    "visibility_header", "visibility_extensions_note", "contact_users", "contact_groups",
    "visibility_button_container", "visibility_submit",
];
const attachmentsTabElements = [
    "attachments_header", "attachments", "attachment_upload_panel",
    "attachments_button_container", "attachments_submit",
];

const flagValue = (value) => (value ? "1" : "0");

const mapPhones = (rows = []) => rows.map((row) => ({
    contact_phone_uuid: row.contact_phone_uuid ?? null,
    phone_label: row.phone_label ?? null,
    phone_number: row.phone_number ?? null,
    phone_extension: row.phone_extension ?? null,
    phone_speed_dial: row.phone_speed_dial ?? null,
    phone_primary: flagValue(row.phone_primary),
    phone_type_voice: flagValue(row.phone_type_voice),
    phone_type_fax: flagValue(row.phone_type_fax),
    phone_type_text: flagValue(row.phone_type_text),
    phone_description: row.phone_description ?? null,
}));

const mapEmails = (rows = []) => rows.map((row) => ({
    contact_email_uuid: row.contact_email_uuid ?? null,
    email_label: row.email_label ?? null,
    email_address: row.email_address ?? null,
    email_primary: flagValue(row.email_primary),
    email_description: row.email_description ?? null,
}));

const mapAddresses = (rows = []) => rows.map((row) => ({
    contact_address_uuid: row.contact_address_uuid ?? null,
    address_label: row.address_label ?? null,
    address_street: row.address_street ?? null,
    address_extended: row.address_extended ?? null,
    address_locality: row.address_locality ?? null,
    address_region: row.address_region ?? null,
    address_postal_code: row.address_postal_code ?? null,
    address_country: row.address_country ?? null,
    address_primary: flagValue(row.address_primary),
    address_description: row.address_description ?? null,
}));

const mapNotes = (rows = []) => rows.map((row) => ({
    contact_note_uuid: row.contact_note_uuid ?? null,
    contact_note: row.contact_note ?? null,
}));

const mapUrls = (rows = []) => rows.map((row) => ({
    contact_url_uuid: row.contact_url_uuid ?? null,
    url_type: row.url_type ?? null,
    url_label: row.url_label ?? null,
    url_address: row.url_address ?? null,
    url_primary: flagValue(row.url_primary),
    url_description: row.url_description ?? null,
}));

const mapTimes = (rows = []) => rows.map((row) => ({
    contact_time_uuid: row.contact_time_uuid ?? null,
    time_start: row.time_start ?? null,
    time_stop: row.time_stop ?? null,
    time_description: row.time_description ?? null,
}));

const mapRelations = (rows = []) => rows.map((row) => ({
    contact_relation_uuid: row.contact_relation_uuid ?? null,
    relation_label: row.relation_label ?? null,
    relation_contact_uuid: row.relation_contact_uuid ?? null,
}));

const mapAttachments = (rows = []) => rows.map((row) => ({
    contact_attachment_uuid: row.contact_attachment_uuid ?? null,
    attachment_primary: flagValue(row.attachment_primary),
    attachment_description: row.attachment_description ?? null,
    attachment_filename: row.attachment_filename ?? null,
}));

const assignedUserLabel = (row, options = []) => {
    const value = row.value ?? row.user_uuid ?? row.user?.user_uuid;
    const optionLabel = options.find((option) => option.value === value)?.label;

    return row.label || row.user?.name_formatted || optionLabel || row.user?.username || value;
};

const mapAssignedUsers = (rows = [], options = []) => rows.map((row) => {
    const value = row.value ?? row.user_uuid ?? row.user?.user_uuid;

    return {
        value,
        label: assignedUserLabel(row, options),
    };
});

const formatContactUsersLoad = (value) => {
    if (!Array.isArray(value)) {
        return [];
    }

    return mapAssignedUsers(value, userOptions.value);
};

const mapAssignedGroups = (rows = []) => rows
    .filter((row) => row.group?.group_name)
    .map((row) => ({
        value: row.group_uuid,
        label: row.group?.group_name ?? row.group_uuid,
    }));

const defaultValues = computed(() => ({
    contact_type: props.options?.item?.contact_type ?? "individual",
    contact_organization: props.options?.item?.contact_organization ?? null,
    contact_name_given: props.options?.item?.contact_name_given ?? null,
    contact_name_family: props.options?.item?.contact_name_family ?? null,
    contact_title: props.options?.item?.contact_title ?? null,
    contact_role: props.options?.item?.contact_role ?? null,
    contact_category: props.options?.item?.contact_category ?? null,
    contact_time_zone: props.options?.item?.contact_time_zone ?? null,
    contact_url: props.options?.item?.contact_url ?? null,
    contact_note: props.options?.item?.contact_note ?? null,
    phones: mapPhones(props.options?.item?.phones ?? []),
    emails: mapEmails(props.options?.item?.emails ?? []),
    addresses: mapAddresses(props.options?.item?.addresses ?? []),
    notes: mapNotes(props.options?.item?.notes ?? []),
    urls: mapUrls(props.options?.item?.urls ?? []),
    times: mapTimes(props.options?.item?.times ?? []),
    relations: mapRelations(props.options?.item?.relations ?? []),
    attachments: mapAttachments(props.options?.item?.attachments ?? []),
    contact_users: mapAssignedUsers(props.options?.item?.contact_users ?? [], props.options?.user_options ?? []),
    contact_groups: mapAssignedGroups(props.options?.item?.contact_groups ?? []),
}));

const attachmentFilename = (index) => {
    const row = props.options?.item?.attachments?.[index];

    return row?.attachment_filename || "Attachment";
};

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emit("success", "success", { message: ["Copied to clipboard."] });
    }).catch(() => {
        emit("error", { response: { data: { errors: { request: ["Failed to copy to clipboard."] } } } });
    });
};

const handleAttachmentSelected = (event) => {
    selectedAttachmentFile.value = event.target.files?.[0] ?? null;
};

const uploadAttachment = async () => {
    if (!selectedAttachmentFile.value || !props.options?.routes?.attachment_store) {
        return;
    }

    uploadingAttachment.value = true;

    const formData = new FormData();
    formData.append("file", selectedAttachmentFile.value);

    try {
        await axios.post(props.options.routes.attachment_store, formData, {
            headers: { "Content-Type": "multipart/form-data" },
        });

        selectedAttachmentFile.value = null;
        if (attachmentInput.value) {
            attachmentInput.value.value = "";
        }

        emit("success", "success", { success: ["Attachment uploaded successfully."] });
        emit("refresh-data");
    } catch (error) {
        emit("error", error);
    } finally {
        uploadingAttachment.value = false;
    }
};

const contactSubmitFields = [
    "contact_type",
    "contact_organization",
    "contact_name_given",
    "contact_name_family",
    "contact_title",
    "contact_role",
    "contact_category",
    "contact_note",
    "contact_time_zone",
    "contact_url",
    "phones",
    "emails",
    "addresses",
    "notes",
    "urls",
    "times",
    "relations",
    "attachments",
    "contact_users",
    "contact_groups",
];

const includeVisibilityFields = () => showVisibilityTab.value;

const buildRequestData = (form$) => {
    const data = form$.data;

    return Object.fromEntries(
        contactSubmitFields
            .filter((field) => {
                if (field === "contact_users" || field === "contact_groups") {
                    return includeVisibilityFields();
                }

                return field in data;
            })
            .filter((field) => field in data)
            .map((field) => [field, data[field]])
    );
};

const submitForm = async (FormData, form$) => {
    // requestData excludes fields hidden by FormTab; form$.data includes all tabs.
    const requestData = buildRequestData(form$);
    const route = props.mode === "create"
        ? props.options.routes.store_route
        : props.options.routes.update_route;

    if (props.mode === "create") {
        return await form$.$vueform.services.axios.post(route, requestData);
    }

    return await form$.$vueform.services.axios.put(route, requestData);
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

    if (props.mode === "create" && response.data.contact_uuid) {
        emit("refresh-data");
    } else {
        emit("refresh-data");
    }

    emit("close");
};

const handleError = (error, details, form$) => {
    form$.messageBag.clear();

    if (details.type === "submit") {
        emit("error", error);
        return;
    }

    form$.messageBag.append("Could not submit form");
};
</script>
