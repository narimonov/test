<template>
    <div class="app-shell">
        <nav v-if="auth.isAuthenticated" class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <router-link class="navbar-brand" :to="auth.homeRoute">DriverHub</router-link>

                <button class="navbar-toggler" type="button" @click="menuOpen = !menuOpen">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" :class="{ show: menuOpen }">
                    <ul class="navbar-nav me-auto" @click="menuOpen = false">
                        <template v-if="auth.isDriver">
                            <li class="nav-item">
                                <router-link class="nav-link" :to="{ name: 'driver.jobs' }">Vakansiyalar</router-link>
                            </li>
                            <li class="nav-item">
                                <router-link class="nav-link" :to="{ name: 'driver.applications' }">Arizalarim</router-link>
                            </li>
                            <li class="nav-item">
                                <router-link class="nav-link" :to="{ name: 'driver.profile' }">Profilim</router-link>
                            </li>
                            <li class="nav-item">
                                <router-link class="nav-link" :to="{ name: 'driver.documents' }">Hujjatlarim</router-link>
                            </li>
                        </template>

                        <template v-if="auth.isCarrier && auth.isFmcsaVerified">
                            <li class="nav-item">
                                <router-link class="nav-link" :to="{ name: 'carrier.dashboard' }">Dashboard</router-link>
                            </li>
                            <li class="nav-item">
                                <router-link class="nav-link" :to="{ name: 'carrier.jobs' }">Vakansiyalar</router-link>
                            </li>
                            <li class="nav-item">
                                <router-link class="nav-link" :to="{ name: 'carrier.drivers' }">Driver bazasi</router-link>
                            </li>
                            <li class="nav-item">
                                <router-link class="nav-link" :to="{ name: 'carrier.criteria' }">Kriteriyalar</router-link>
                            </li>
                        </template>
                        <template v-if="auth.isAdmin">
                            <li class="nav-item">
                                <router-link class="nav-link" :to="{ name: 'admin.overview' }">Umumiy</router-link>
                            </li>
                            <li class="nav-item">
                                <router-link class="nav-link" :to="{ name: 'admin.users' }">Foydalanuvchilar</router-link>
                            </li>
                            <li class="nav-item">
                                <router-link class="nav-link" :to="{ name: 'admin.appeals' }">Apelyatsiyalar</router-link>
                            </li>
                        </template>
                    </ul>

                    <ul class="navbar-nav" @click="menuOpen = false">
                        <li v-if="auth.isCarrier && auth.isFmcsaVerified" class="nav-item">
                            <router-link class="nav-link" :to="{ name: 'carrier.billing' }">
                                <span v-if="auth.hasSubscription" class="badge bg-success">Obuna aktiv</span>
                                <span v-else class="badge bg-warning text-dark">Obuna kerak</span>
                            </router-link>
                        </li>
                        <li v-if="auth.isCarrier && auth.isFmcsaVerified" class="nav-item">
                            <router-link class="nav-link" :to="{ name: 'carrier.company' }">Kompaniya</router-link>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" @click.prevent="logout">Chiqish</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div v-if="auth.isCarrier && !auth.isFmcsaVerified" class="alert alert-warning rounded-0 mb-0 text-center">
            Kompaniyangiz hali tasdiqlanmagan.
            <router-link :to="{ name: 'carrier.verify' }" class="alert-link">FMCSA kodi bilan tasdiqlang</router-link>
            — ilova shundan keyin ochiladi.
        </div>

        <div v-else-if="auth.isAuthenticated && !auth.isAdmin && !auth.isVerified"
             class="alert alert-warning rounded-0 mb-0 text-center">
            Akkauntingiz tasdiqlanmagan.
            <router-link :to="{ name: 'verify' }" class="alert-link">Telefon yoki emailni tasdiqlang</router-link>
            — ariza berish va driver bazasi shundan keyin ochiladi.
        </div>

        <div v-if="blacklisted" class="alert alert-danger rounded-0 mb-0 text-center">
            Akkauntingiz blacklist'da.
            <router-link :to="{ name: 'appeal' }" class="alert-link">Apelyatsiya bering</router-link>
        </div>

        <main class="flex-grow-1 py-4">
            <div class="container">
                <router-view v-slot="{ Component }">
                    <component :is="Component" />
                </router-view>
            </div>
        </main>

        <footer class="py-3 text-center text-muted small border-top bg-white">
            DriverHub — driver recruiting platformasi
        </footer>
    </div>
</template>

<script>
import { useAuthStore } from './stores/auth';

export default {
    name: 'App',

    data() {
        return {
            menuOpen: false,
        };
    },

    setup() {
        return { auth: useAuthStore() };
    },

    computed: {
        blacklisted() {
            return !!this.auth.user?.is_blacklisted;
        },
    },

    methods: {
        async logout() {
            await this.auth.logout();
            this.$router.push({ name: 'login' });
        },
    },
};
</script>
