<script setup>
defineProps({
    eyebrow: String,
    title: String,
    description: String
});
</script>

<template>
    <div class="page-heading">
        <p
            v-if="eyebrow"
            class="eyebrow"
        >
            {{ eyebrow }}
        </p>

        <h1>{{ title }}</h1>

        <p
            v-if="description"
            class="muted lead-copy"
        >
            {{ description }}
        </p>

        <slot />
    </div>
</template>