<script setup>
defineProps({
    value: {
        type: String,
        default: 'unknown'
    }
});
</script>

<template>
    <span
        class="status-badge"
        :class="
            /paid|delivered|published|success|shipped/.test(value.toLowerCase())
                ? 'status-good'
                : /cancel|refund|delete|failed/.test(value.toLowerCase())
                    ? 'status-bad'
                    : 'status-neutral'
        "
    >
        {{ value }}
    </span>
</template>