<template>
    <div class="card">
      <div class="card-body pt-2">
        <div class="d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #f1f3fa;">
          <h6 class="m-0" style="color: #6c757d; font-size:20px">Service Level</h6>
          <i class="mdi mdi-emoticon-happy-outline text-info" style="font-size: 2rem;"></i>
        </div>
        <div class="mt-3">
          <div class="d-flex justify-content-between align-items-center">
            <h2 :class="serviceLevelColor" class="my-2">{{ serviceLevel.toFixed(0) }}%</h2>
            <div class="mb-0 text-muted ms-3">
              <span>% of calls answered within 60s of entering the queue</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>

<script setup>
import {  computed } from 'vue';

const props = defineProps({
    serviceLevel: Number,
});

// Assuming you have logic to determine the color based on the service level
const serviceLevelColor = computed(() => {
  if (props.serviceLevel >= 90) return 'text-success';
  if (props.serviceLevel >= 80) return 'text-warning';
  return 'text-danger';
});
</script>
