<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ChatBox from '@/Components/ChatBox.vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});

function handleImageError() {
    document.getElementById('screenshot-container')?.classList.add('!hidden');
    document.getElementById('docs-card')?.classList.add('!row-span-1');
    document.getElementById('docs-card-content')?.classList.add('!flex-row');
    document.getElementById('background')?.classList.add('!hidden');
}
</script>

<template>
    <Head title="Welcome" />
    <header>
        <h3 class="d-flex text-center md:text-5xl lg:text-6xl text-[#00ff00] font-[Courier]">Battle at Chiclay's</h3>
    </header>
    <main>
        <ChatBox></ChatBox>
    </main>
</template>
