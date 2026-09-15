<template>
    <div>
        <h4 class="page-title mb-1">Admin</h4>
        <p class="page-lede mb-4">Platform-wide state</p>

        <AlertBox :message="error" />

        <div v-if="loading" class="empty-state">Loading…</div>

        <template v-else>
            <div class="row g-3 mb-4">
                <div v-for="tile in tiles" :key="tile.label" class="col-6 col-lg-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="stat-value" :class="tile.variant">{{ tile.value }}</div>
                            <div class="stat-label">{{ tile.label }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div v-for="group in groups" :key="group.title" class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="mb-3">{{ group.title }}</h6>
                            <div v-for="row in group.rows" :key="row.label"
                                 class="d-flex justify-content-between py-1 border-bottom">
                                <span>{{ row.label }}</span>
                                <span class="fw-semibold">{{ row.value }}</span>
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

export default {
    name: 'AdminOverviewPage',

    components: { AlertBox },

    data() {
        return { data: null, loading: true, error: null };
    },

    computed: {
        tiles() {
            if (!this.data) return [];

            return [
                { label: 'Users', value: this.data.users.total },
                { label: 'Verified carriers', value: this.data.carriers.fmcsa_verified },
                { label: 'Open jobs', value: this.data.activity.open_jobs },
                { label: 'Pending appeals', value: this.data.activity.pending_appeals, variant: 'text-warning' },
            ];
        },

        groups() {
            if (!this.data) return [];

            return [
                {
                    title: 'Users',
                    rows: [
                        { label: 'Drivers', value: this.data.users.drivers },
                        { label: 'Carriers', value: this.data.users.carriers },
                        { label: 'Blocked', value: this.data.users.blocked },
                        { label: 'Joined this week', value: this.data.users.new_this_week },
                    ],
                },
                {
                    title: 'Carriers',
                    rows: [
                        { label: 'Total', value: this.data.carriers.total },
                        { label: 'FMCSA verified', value: this.data.carriers.fmcsa_verified },
                        { label: 'Subscribed', value: this.data.carriers.subscribed },
                        { label: 'Blacklisted', value: this.data.carriers.blacklisted },
                    ],
                },
                {
                    title: 'Drivers',
                    rows: [
                        { label: 'Profiles', value: this.data.drivers.total },
                        { label: 'Hired', value: this.data.drivers.hired },
                        { label: 'Blacklisted', value: this.data.drivers.blacklisted },
                    ],
                },
                {
                    title: 'Activity',
                    rows: [
                        { label: 'Applications', value: this.data.activity.applications },
                        { label: 'Published reviews', value: this.data.activity.reviews },
                        { label: 'Unsatisfactory', value: this.data.activity.negative_reviews },
                    ],
                },
            ];
        },
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            try {
                const { data } = await api.get('/admin/overview');
                this.data = data;
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>
