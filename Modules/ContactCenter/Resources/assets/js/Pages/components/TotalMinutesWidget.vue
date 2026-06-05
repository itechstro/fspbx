<template>
    <div class="card">
      <div class="card-body pt-2">
        <div class="d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #f1f3fa;">
          <h6 class="m-0" style="color: #6c757d; font-size:20px">Total minutes</h6>
          <i class="mdi mdi-timer-outline text-info" style="font-size: 2rem;"></i>
        </div>
        <div class="mt-3">
          <div class="d-flex justify-content-between align-items-center">
            <h2 class="my-2 text-info">{{ totalMinutes }}</h2>
            <div>
              <div class="mb-0 text-muted">
                <span class="me-1">{{ inboundMinutes }}</span>
                <span class="text-nowrap">Inbound (answered)</span>
              </div>
              <div v-if="!multipleQueuesSelected" class="mb-0 text-muted">
                <span class="me-1">{{ outboundMinutes }}</span>
                <span class="text-nowrap">Outbound (connected)</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script setup>
  import { defineProps } from 'vue';
  
  const props = defineProps({
    totalMinutes: Number,
    inboundMinutes: Number,
    outboundMinutes: Number,
    multipleQueuesSelected: Boolean
  });
  </script>
  
  