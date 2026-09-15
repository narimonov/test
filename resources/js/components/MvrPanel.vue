<template>
    <div>
        <div v-if="loading" class="text-muted small">Checking…</div>

        <template v-else>
            <div v-if="!quote.driver_authorised" class="alert alert-warning small mb-2">
                This driver has not authorised a record check. The FCRA and the DPPA both require
                written permission before a driving record is pulled, so we cannot order one yet.
            </div>

            <div v-else-if="!quote.has_licence_number" class="alert alert-warning small mb-2">
                We need the licence number on their profile before a record can be ordered.
            </div>

            <div v-else class="d-flex align-items-center flex-wrap gap-2 mb-2">
                <span class="label-mono">{{ quote.state }}</span>
                <span v-if="quote.reusable" class="badge bg-success">
                    Reusable record on file — no fee
                </span>
                <span v-else class="badge bg-light text-dark">
                    ${{ (quote.cost_cents / 100).toFixed(2) }} state fee
                </span>
                <button class="btn btn-sm btn-primary ms-auto" :disabled="ordering" @click="order">
                    {{ ordering ? 'Working…' : (quote.reusable ? 'Use it' : 'Order record') }}
                </button>
            </div>

            <AlertBox :message="error" />
            <AlertBox :message="notice" variant="success" />

            <div v-if="reports.length" class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Pulled</th>
                            <th>State</th>
                            <th>Licence</th>
                            <th>Findings</th>
                            <th>Reusable until</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="report in reports" :key="report.id">
                            <td class="small">{{ date(report.completed_at || report.ordered_at) }}</td>
                            <td>{{ report.state }}</td>
                            <td>
                                <span class="badge" :class="report.licence_status === 'valid' ? 'bg-success' : 'bg-danger'">
                                    {{ report.licence_status || report.status }}
                                </span>
                            </td>
                            <td class="small">
                                <span v-if="report.is_clean" class="text-success">Clean</span>
                                <span v-else class="text-danger">
                                    {{ report.violations_count }} violations ·
                                    {{ report.accidents_count }} accidents ·
                                    {{ report.suspensions_count }} suspensions
                                </span>
                            </td>
                            <td class="small text-muted">{{ report.expires_on || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="text-muted small">No record on file for this driver yet.</div>
        </template>
    </div>
</template>

<script>
import api from '../api';
import AlertBox from './AlertBox.vue';

/**
 * Motor vehicle records for one driver. A record pulled within the reuse
 * window is shared rather than bought again, which is what the badge shows.
 */
export default {
    name: 'MvrPanel',

    components: { AlertBox },

    props: {
        driverId: { type: Number, required: true },
    },

    data() {
        return {
            quote: {},
            reports: [],
            loading: true,
            ordering: false,
            error: null,
            notice: null,
        };
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            try {
                const [quote, reports] = await Promise.all([
                    api.get(`/carrier/drivers/${this.driverId}/mvr/quote`),
                    api.get(`/carrier/drivers/${this.driverId}/mvr`),
                ]);

                this.quote = quote.data;
                this.reports = reports.data.reports;
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        async order() {
            this.ordering = true;
            this.error = null;
            this.notice = null;

            try {
                const { data } = await api.post(`/carrier/drivers/${this.driverId}/mvr`, {});
                this.notice = data.message;
                await this.load();
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.ordering = false;
            }
        },

        date(value) {
            return value ? new Date(value).toLocaleDateString('en-US') : '—';
        },
    },
};
</script>
