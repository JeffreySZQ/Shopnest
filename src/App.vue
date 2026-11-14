<script setup>
import { onMounted } from 'vue';
import { useStore } from '@/stores/shop.js';

const shop = useStore();

onMounted(() => shop.init());
</script>

<template>
    <v-app>
        <RouterView />
    </v-app>
</template>
