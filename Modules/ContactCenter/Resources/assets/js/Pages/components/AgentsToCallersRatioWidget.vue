<template>
    <div class="card">
      <div class="card-body pt-2">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #f1f3fa;">
          <h6 class="m-0" style="color: #6c757d; font-size:20px">Agents to callers ratio</h6>
          <i class="mdi mdi-account-group text-info" style="font-size: 2rem;"></i>
        </div>
  
        <!-- Content -->
        <div class="mt-3">
          <div class="d-flex justify-content-between align-items-center">
            <template v-if="!multipleQueuesSelected">
              <h2 class="my-2 text-info">{{ availableAgentCount }}:{{ totalProgress + totalQueued }}</h2>
              <div>{{ totalQueued }} Queue length</div>
            </template>
            <template v-else>
              <div>
                Ratio is not available for multiple contact centers. To view this data, change your filters to 1 contact center.
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>
  </template>

  <script setup>
  import { ref } from 'vue';

  const props = defineProps({
    availableAgentCount: Number,
    totalQueued: Number,
    totalProgress: Number,
    multipleQueuesSelected: Boolean,
});

  </script>
  