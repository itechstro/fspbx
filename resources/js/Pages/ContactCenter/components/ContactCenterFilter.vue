<template>
    <div class="d-flex">
      <div class="form-control p-0">
        <div class="dropdown">
          <button type="button" class="btn dropdown-toggle w-100 text-start" data-bs-toggle="dropdown" data-bs-auto-close="outside">
            Filter by Contact Center <span v-if="selectedQueues.length">({{ selectedQueues.length }})</span>
          </button>
          <div class="dropdown-menu">
            <div class="px-3 py-2">
              <div v-for="contactCenter in contactCenters" :key="contactCenter.call_center_queue_uuid" class="mb-3">
                <div class="form-check">
                  <label class="form-check-label">
                    <input type="checkbox" class="callcenter-check form-check-input" v-model="selectedQueues" :value="contactCenter.call_center_queue_uuid">
                    {{ contactCenter.queue_name }}
                  </label>
                </div>
              </div>
              <hr />
              <div class="mb-3">
                <label class="form-check-label">
                  <input id="selectAll" type="checkbox" class="form-check-input" v-model="isAllSelected" @change="selectAll($event.target.checked)">
                  Select All
                </label>
              </div>
              <button type="button" class="btn btn-primary" @click="applyFilter" :disabled="isApplyDisabled">Apply</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>

<script setup>
import { ref, onMounted,computed, watch  } from 'vue';

const props = defineProps({
    contactCenters: Array,
    filterData: Object // 
});

const emit = defineEmits(['update:selectedQueues'])
const selectedQueues = ref([]);

selectedQueues.value = props.filterData.queues;

const selectAll = (checked) => {
  if (checked) {
    selectedQueues.value = props.contactCenters.map(contactCenter => contactCenter.call_center_queue_uuid);
  } else {
    selectedQueues.value = [];
  }
};

const applyFilter = () => {
    emit('update:selectedQueues', selectedQueues.value);

};

// Computed property to determine if the Apply button should be disabled
const isApplyDisabled = computed(() => {
  return selectedQueues.value.length === 0;
});

// Watcher to update Select All checkbox based on individual queue selection changes
watch(selectedQueues, (newVal) => {
  if (newVal.length !== props.contactCenters.length) {
    // This ensures the Select All checkbox is unchecked if not all queues are selected
    document.getElementById("selectAll").checked = false;
  }
  if (newVal.length == props.contactCenters.length) {
    // This ensures the Select All checkbox is unchecked if not all queues are selected
    document.getElementById("selectAll").checked = true;
  }
});

// Computed property to manage the state of the Select All checkbox
const isAllSelected = computed(() => {
    return selectedQueues.value.length === props.contactCenters.length;
});

</script>