<script>
export default {
    name: 'PageHeading',
    props: {
        eyebrow: { type: String, default: '' },
        title: { type: String, default: '' },
        description: { type: String, default: '' }
    }
};
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