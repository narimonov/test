<template>
    <div v-if="!auth.isAuthenticated" class="page container py-4">
        <router-view />
        <SupportWidget v-if="auth.isAuthenticated" />
    </div>

    <div v-else class="shell">
        <div v-if="sidebarOpen" class="sidebar-backdrop d-lg-none" @click="sidebarOpen = false"></div>

        <aside class="sidebar" :class="{ 'is-open': sidebarOpen }">
            <div class="sidebar-brand">
                <span class="brand-mark">DH</span>
                <span>
                    <span class="brand-name">DriverHub</span>
                    <span class="brand-role">{{ roleLabel }}</span>
                </span>
            </div>

            <nav class="sidebar-nav" @click="sidebarOpen = false">
                <template v-for="section in sections" :key="section.title">
                    <div class="nav-section">{{ section.title }}</div>
                    <router-link v-for="item in section.items" :key="item.name" :to="{ name: item.name }">
                        <span>{{ item.label }}</span>
                        <span v-if="item.badge" class="badge bg-danger">{{ item.badge }}</span>
                    </router-link>
                </template>
            </nav>

            <div class="sidebar-foot">
                <div class="who">{{ auth.user.name }}</div>
                <div class="who-sub">{{ auth.user.email }}</div>
                <button class="btn btn-sm btn-outline-light w-100 mt-2" @click="logout">Sign out</button>
            </div>
        </aside>

        <div class="content">
            <header class="topbar">
                <button class="btn btn-sm btn-outline-secondary d-lg-none" @click="sidebarOpen = !sidebarOpen">
                    Menu
                </button>

                <div class="ms-auto d-flex align-items-center gap-2">
                    <router-link v-if="auth.isCarrier && auth.isFmcsaVerified" :to="{ name: 'carrier.billing' }"
                                 class="text-decoration-none">
                        <span v-if="auth.hasSubscription" class="badge bg-success">{{ planName }}</span>
                        <span v-else class="badge bg-warning text-dark">No active plan</span>
                    </router-link>
                </div>
            </header>

            <div v-if="banners.length">
                <div v-for="banner in banners" :key="banner.text" class="alert rounded-0 mb-0 text-center"
                     :class="`alert-${banner.variant}`">
                    {{ banner.text }}
                    <router-link v-if="banner.to" :to="banner.to" class="alert-link">{{ banner.action }}</router-link>
                </div>
            </div>

            <main class="page">
                <router-view />
            </main>
        </div>

        <SupportWidget />
    </div>
</template>

<script>
import SupportWidget from './components/SupportWidget.vue';
import { useAuthStore } from './stores/auth';

export default {
    name: 'App',

    components: { SupportWidget },

    setup() {
        return { auth: useAuthStore() };
    },

    data() {
        return { sidebarOpen: false };
    },

    computed: {
        roleLabel() {
            if (this.auth.isAdmin) return 'Administrator';

            return this.auth.isCarrier ? 'Carrier' : 'Driver';
        },

        planName() {
            return this.auth.user?.carrier?.subscription_plan
                ? this.auth.user.carrier.subscription_plan.toUpperCase()
                : 'ACTIVE';
        },

        sections() {
            if (this.auth.isAdmin) {
                return [{
                    title: 'Administration',
                    items: [
                        { name: 'admin.overview', label: 'Overview' },
                        { name: 'admin.users', label: 'Users' },
                        { name: 'admin.support', label: 'Support queue' },
                        { name: 'admin.reviews', label: 'Review queue' },
                        { name: 'admin.appeals', label: 'Appeals' },
                    ],
                }];
            }

            if (this.auth.isCarrier) {
                if (!this.auth.isFmcsaVerified) {
                    return [{
                        title: 'Getting started',
                        items: [{ name: 'carrier.verify', label: 'Verify company' }],
                    }];
                }

                return [
                    {
                        title: 'Hiring',
                        items: [
                            { name: 'carrier.dashboard', label: 'Dashboard' },
                            { name: 'carrier.jobs', label: 'Job posts' },
                            { name: 'carrier.drivers', label: 'Driver pool' },
                            { name: 'conversations', label: 'Messages' },
                            { name: 'onboarding', label: 'Onboarding' },
                            { name: 'carrier.travel', label: 'Driver travel' },
                        ],
                    },
                    {
                        title: 'Setup',
                        items: [
                            { name: 'carrier.criteria', label: 'Criteria' },
                            { name: 'carrier.recruiting', label: 'Personal recruiting' },
                            { name: 'carrier.company', label: 'Company' },
                            { name: 'carrier.billing', label: 'Billing' },
                        ],
                    },
                    {
                        title: 'Account',
                        items: [
                            { name: 'appeal', label: 'Standing & appeals' },
                            { name: 'privacy', label: 'Privacy' },
                        ],
                    },
                ];
            }

            return [
                {
                    title: 'Work',
                    items: [
                        { name: 'driver.jobs', label: 'Find jobs' },
                        { name: 'driver.applications', label: 'My applications' },
                        { name: 'conversations', label: 'Messages' },
                        { name: 'onboarding', label: 'Onboarding' },
                    ],
                },
                {
                    title: 'My file',
                    items: [
                        { name: 'driver.profile', label: 'Profile' },
                        { name: 'driver.documents', label: 'Documents' },
                    ],
                },
                {
                    title: 'Account',
                    items: [
                        { name: 'appeal', label: 'Standing & appeals' },
                        { name: 'privacy', label: 'Privacy' },
                    ],
                },
            ];
        },

        banners() {
            const list = [];

            if (this.auth.isCarrier && !this.auth.isFmcsaVerified) {
                list.push({
                    variant: 'warning',
                    text: 'Your company is not verified yet.',
                    action: 'Verify with the FMCSA code',
                    to: { name: 'carrier.verify' },
                });
            } else if (!this.auth.isAdmin && !this.auth.isVerified) {
                list.push({
                    variant: 'warning',
                    text: 'Your account is not verified.',
                    action: 'Confirm your phone or email',
                    to: { name: 'verify' },
                });
            }

            if (!this.auth.hasPrivacyConsent) {
                list.push({
                    variant: 'info',
                    text: 'Our privacy notice has been updated.',
                    action: 'Review and accept',
                    to: { name: 'privacy' },
                });
            }

            if (this.auth.isBlacklisted) {
                list.push({
                    variant: 'danger',
                    text: 'This account is blacklisted.',
                    action: 'Submit an appeal',
                    to: { name: 'appeal' },
                });
            }

            return list;
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
