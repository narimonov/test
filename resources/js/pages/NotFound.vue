<template>
    <div class="empty-state">
        <h3 class="mb-2">404</h3>
        <p class="mb-3">Bunday sahifa topilmadi.</p>
        <router-link :to="auth.homeRoute" class="btn btn-primary">Bosh sahifaga</router-link>
    </div>
</template>

<script>
import { useAuthStore } from '../stores/auth';

export default {
    name: 'NotFoundPage',

    setup() {
        return { auth: useAuthStore() };
    },
};
</script>
