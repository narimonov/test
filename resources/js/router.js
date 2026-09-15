import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';

const routes = [
    { path: '/', name: 'landing', component: () => import('./pages/Landing.vue'), meta: { guest: true } },
    { path: '/login', name: 'login', component: () => import('./pages/Login.vue'), meta: { guest: true } },
    { path: '/register', name: 'register', component: () => import('./pages/Register.vue'), meta: { guest: true } },
    { path: '/verify', name: 'verify', component: () => import('./pages/Verify.vue'), meta: { auth: true } },

    // Driver tomoni
    { path: '/driver/jobs', name: 'driver.jobs', component: () => import('./pages/driver/Jobs.vue'), meta: { auth: true, role: 'driver' } },
    { path: '/driver/jobs/:id', name: 'driver.job', component: () => import('./pages/driver/JobDetail.vue'), meta: { auth: true, role: 'driver' } },
    { path: '/driver/profile', name: 'driver.profile', component: () => import('./pages/driver/Profile.vue'), meta: { auth: true, role: 'driver' } },
    { path: '/driver/documents', name: 'driver.documents', component: () => import('./pages/driver/Documents.vue'), meta: { auth: true, role: 'driver' } },
    { path: '/driver/applications', name: 'driver.applications', component: () => import('./pages/driver/Applications.vue'), meta: { auth: true, role: 'driver' } },

    // Blacklist apelyatsiyasi — driver ham, kompaniya ham foydalanadi.
    { path: '/appeal', name: 'appeal', component: () => import('./pages/Appeal.vue'), meta: { auth: true } },

    // Carrier tomoni
    { path: '/carrier/verify', name: 'carrier.verify', component: () => import('./pages/carrier/VerifyFmcsa.vue'), meta: { auth: true, role: 'carrier' } },
    { path: '/carrier', name: 'carrier.dashboard', component: () => import('./pages/carrier/Dashboard.vue'), meta: { auth: true, role: 'carrier' } },
    { path: '/carrier/jobs', name: 'carrier.jobs', component: () => import('./pages/carrier/Jobs.vue'), meta: { auth: true, role: 'carrier' } },
    { path: '/carrier/jobs/:id/applicants', name: 'carrier.applicants', component: () => import('./pages/carrier/Applicants.vue'), meta: { auth: true, role: 'carrier' } },
    { path: '/carrier/drivers', name: 'carrier.drivers', component: () => import('./pages/carrier/TalentPool.vue'), meta: { auth: true, role: 'carrier' } },
    { path: '/carrier/criteria', name: 'carrier.criteria', component: () => import('./pages/carrier/Criteria.vue'), meta: { auth: true, role: 'carrier' } },
    { path: '/carrier/company', name: 'carrier.company', component: () => import('./pages/carrier/Company.vue'), meta: { auth: true, role: 'carrier' } },
    { path: '/carrier/billing', name: 'carrier.billing', component: () => import('./pages/carrier/Billing.vue'), meta: { auth: true, role: 'carrier' } },

    // Admin tomoni
    { path: '/admin', name: 'admin.overview', component: () => import('./pages/admin/Overview.vue'), meta: { auth: true, role: 'admin' } },
    { path: '/admin/users', name: 'admin.users', component: () => import('./pages/admin/Users.vue'), meta: { auth: true, role: 'admin' } },
    { path: '/admin/appeals', name: 'admin.appeals', component: () => import('./pages/admin/Appeals.vue'), meta: { auth: true, role: 'admin' } },

    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('./pages/NotFound.vue') },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    await auth.boot();

    if (to.meta.auth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guest && auth.isAuthenticated && to.name !== 'landing') {
        return auth.homeRoute;
    }

    if (to.meta.role === 'driver' && !auth.isDriver) {
        return auth.homeRoute;
    }

    if (to.meta.role === 'carrier' && !auth.isCarrier) {
        return auth.homeRoute;
    }

    if (to.meta.role === 'admin' && !auth.isAdmin) {
        return auth.homeRoute;
    }

    /*
     * Kompaniya FMCSA kodi bilan tasdiqlanmaguncha faqat tasdiqlash sahifasiga
     * kira oladi — backend ham shu qoidani majburlaydi.
     */
    if (auth.isCarrier && !auth.isFmcsaVerified && to.name !== 'carrier.verify' && to.meta.role === 'carrier') {
        return { name: 'carrier.verify' };
    }

    return true;
});

export default router;
