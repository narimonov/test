<template>
    <div>
        <div class="mb-4"><h4 class="page-title">Open jobs</h4>
            <p class="page-lede">Apply in one click — your profile goes with it.</p></div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input v-model="filters.q" type="search" class="form-control"
                               placeholder="Title, city or keyword" @keyup.enter="load">
                    </div>
                    <div class="col-md-2">
                        <select v-model="filters.state" class="form-select" @change="load">
                            <option :value="null">All states</option>
                            <option v-for="state in states" :key="state" :value="state">{{ state }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select v-model="filters.route_type" class="form-select" @change="load">
                            <option :value="null">All route types</option>
                            <option v-for="item in routeTypes" :key="item.value" :value="item.value">{{ item.label }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select v-model="filters.driver_type" class="form-select" @change="load">
                            <option :value="null">All driver types</option>
                            <option v-for="item in driverTypes" :key="item.value" :value="item.value">{{ item.label }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <AlertBox :message="error" />

        <div v-if="loading" class="empty-state">Loading…</div>

        <div v-else-if="!jobs.length" class="empty-state">
            Nothing matches right now. Try widening the filters.
        </div>

        <div v-else class="row g-3">
            <div v-for="job in jobs" :key="job.id" class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="mb-1">{{ job.title }}</h5>
                                <div class="text-muted small">{{ job.carrier?.company_name }}</div>
                            </div>
                            <span v-if="job.already_applied" class="badge bg-success">Applied</span>
                        </div>

                        <div class="text-muted small mt-2">
                            <span v-if="job.city || job.state">{{ [job.city, job.state].filter(Boolean).join(', ') }}</span>
                            <span v-if="job.route_type"> · {{ label(routeTypes, job.route_type) }}</span>
                            <span v-if="job.equipment"> · {{ job.equipment }}</span>
                        </div>

                        <div v-if="payRange(job)" class="fw-semibold mt-2">{{ payRange(job) }}</div>

                        <p class="text-muted small mt-2 mb-3">{{ excerpt(job.description) }}</p>

                        <router-link :to="{ name: 'driver.job', params: { id: job.id } }" class="btn btn-sm btn-outline-primary">
                            View
                        </router-link>
                    </div>
                </div>
            </div>
        </div>

        <nav v-if="meta.last_page > 1" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item" :class="{ disabled: meta.current_page === 1 }">
                    <button class="page-link" @click="go(meta.current_page - 1)">Previous</button>
                </li>
                <li class="page-item disabled">
                    <span class="page-link">{{ meta.current_page }} / {{ meta.last_page }}</span>
                </li>
                <li class="page-item" :class="{ disabled: meta.current_page === meta.last_page }">
                    <button class="page-link" @click="go(meta.current_page + 1)">Next</button>
                </li>
            </ul>
        </nav>
    </div>
</template>

<script>
import api from '../../api';
import AlertBox from '../../components/AlertBox.vue';
import { US_STATES, ROUTE_TYPES, DRIVER_TYPES, labelFor, formatPay } from '../../constants';

export default {
    name: 'DriverJobsPage',

    components: { AlertBox },

    data() {
        return {
            jobs: [],
            meta: { current_page: 1, last_page: 1 },
            filters: { q: '', state: null, route_type: null, driver_type: null },
            states: US_STATES,
            routeTypes: ROUTE_TYPES,
            driverTypes: DRIVER_TYPES,
            loading: true,
            error: null,
        };
    },

    created() {
        this.load();
    },

    methods: {
        async load(page = 1) {
            this.loading = true;
            this.error = null;

            try {
                const { data } = await api.get('/driver/jobs', { params: { ...this.filters, page } });
                this.jobs = data.data;
                this.meta = { current_page: data.current_page, last_page: data.last_page };
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        go(page) {
            if (page >= 1 && page <= this.meta.last_page) this.load(page);
        },

        label(list, value) {
            return labelFor(list, value);
        },

        payRange(job) {
            const min = formatPay(job.pay_min_cents, job.pay_unit);
            const max = formatPay(job.pay_max_cents, job.pay_unit);

            if (min && max) return `${min} – ${max}`;

            return min || max || null;
        },

        excerpt(text) {
            if (!text) return '';

            return text.length > 130 ? `${text.slice(0, 130)}…` : text;
        },
    },
};
</script>
