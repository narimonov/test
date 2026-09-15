<template>
    <div>
        <h4 class="mb-1">Admin paneli</h4>
        <p class="text-muted small mb-4">Butun platforma bo'yicha umumiy holat</p>

        <AlertBox :message="error" />

        <div v-if="loading" class="empty-state">Yuklanmoqda…</div>

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
                { label: 'Foydalanuvchi', value: this.data.users.total },
                { label: 'Tasdiqlangan kompaniya', value: this.data.carriers.fmcsa_verified },
                { label: 'Ochiq vakansiya', value: this.data.activity.open_jobs },
                { label: 'Kutilayotgan apelyatsiya', value: this.data.activity.pending_appeals, variant: 'text-warning' },
            ];
        },

        groups() {
            if (!this.data) return [];

            return [
                {
                    title: 'Foydalanuvchilar',
                    rows: [
                        { label: 'Driverlar', value: this.data.users.drivers },
                        { label: 'Kompaniyalar', value: this.data.users.carriers },
                        { label: 'Bloklangan', value: this.data.users.blocked },
                        { label: 'Shu haftada qo\'shilgan', value: this.data.users.new_this_week },
                    ],
                },
                {
                    title: 'Kompaniyalar',
                    rows: [
                        { label: 'Jami', value: this.data.carriers.total },
                        { label: 'FMCSA tasdiqlangan', value: this.data.carriers.fmcsa_verified },
                        { label: 'Obunali', value: this.data.carriers.subscribed },
                        { label: 'Blacklist', value: this.data.carriers.blacklisted },
                    ],
                },
                {
                    title: 'Driverlar',
                    rows: [
                        { label: 'Jami profil', value: this.data.drivers.total },
                        { label: 'Ishga olingan', value: this.data.drivers.hired },
                        { label: 'Blacklist', value: this.data.drivers.blacklisted },
                    ],
                },
                {
                    title: 'Faollik',
                    rows: [
                        { label: 'Arizalar', value: this.data.activity.applications },
                        { label: 'Baholar', value: this.data.activity.reviews },
                        { label: 'Qoniqarsiz baholar', value: this.data.activity.negative_reviews },
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
