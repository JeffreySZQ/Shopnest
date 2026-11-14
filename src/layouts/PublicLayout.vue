<script setup>
import Header from '@/components/Header.vue';
import Footer from '@/components/Footer.vue';
</script>

<template>
    <div class="app-shell">
        <a
            class="skip-link"
            href="#main-content"
        >
            Skip to main content
        </a>

        <Header />

        <main
            id="main-content"
            class="flex-grow-1"
        >
            <RouterView />
        </main>

        <Footer />
    </div>
</template>