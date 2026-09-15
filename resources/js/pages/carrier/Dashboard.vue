<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">{{ carrier?.company_name || 'Dashboard' }}</h4>
                <p class="text-muted small mb-0">Recruiting jarayoni umumiy ko'rinishda</p>
            </div>
            <router-link :to="{ name: 'carrier.jobs' }" class="btn btn-primary">Vakansiya joylash</router-link>
        </div>

        <AlertBox :message="error" />

        <div v-if="!auth.hasSubscription" class="alert alert-warning">
            Obuna aktiv emas — arizachilar va driver bazasi yopiq.
            <router-link :to="{ name: 'carrier.billing' }" class="alert-link">Obunani ochish</router-link>
        </div>

        <div v-if="loading" class="empty-state">Yuklanmoqda…</div>

        <template v-else>
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="stat-value">{{ stats.open_jobs }}</div>
                            <div class="stat-label">Ochiq vakansiya</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="stat-value">{{ stats.applications }}</div>
                            <div class="stat-label">Jami ariza</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="stat-value">{{ stats.new_this_week }}</div>
                            <div class="stat-label">Shu haftada</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="stat-value text-success">{{ stats.by_tier?.A || 0 }}</div>
                            <div class="stat-label">A darajali driver</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="mb-3">Arizalar holati bo'yicha</h6>
                            <div v-for="status in statuses" :key="status.value"
                                 class="d-flex justify-content-between py-1 border-bottom">
                                <span>{{ status.label }}</span>
                                <span class="fw-semibold">{{ stats.by_status?.[status.value] || 0 }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="mb-3">Ball darajalari bo'yicha</h6>
                            <div v-for="tier in ['A', 'B', 'C', 'D']" :key="tier"
                                 class="d-flex justify-content-between py-1 border-bottom">
                                <span>{{ tier }} daraja</span>
                                <span class="fw-semibold">{{ stats.by_tier?.[tier] || 0 }}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 text-danger">
                                <span>Knockout (rad)</span>
                                <span class="fw-semibold">{{ stats.by_tier?.[''] || stats.by_tier?.null || 0 }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
import api from '../../api';
import AlertBox from '../../components/AlertBox.vue';
import { APPLICATION_STATUSES } from '../../constants';
import { useAuthStore } from '../../stores/auth';

export default {
    name: 'CarrierDashboardPage',

    components: { AlertBox },

    setup() {
        return { auth: useAuthStore() };
    },

    data() {
        return {
            carrier: null,
            stats: {},
            statuses: APPLICATION_STATUSES,
            loading: true,
            error: null,
        };
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            try {
                const { data } = await api.get('/carrier/dashboard');
                this.carrier = data.carrier;
                this.stats = data.stats;
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>
