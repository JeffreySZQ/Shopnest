<<<<<<< HEAD
<script setup>
defineProps({
    value: {
        type: String,
        default: 'unknown'
    }
});
=======
<script>
export default {
    name: 'StatusBadge',
    props: {
        value: { type: String, default: 'unknown' }
    },
    computed: {
        statusClass() {
            const value = String(this.value || '').toLowerCase();
            if (/paid|delivered|published|success|shipped/.test(value)) return 'status-good';
            if (/cancel|refund|delete|failed/.test(value)) return 'status-bad';
            return 'status-neutral';
        }
    }
};
>>>>>>> origin/main
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