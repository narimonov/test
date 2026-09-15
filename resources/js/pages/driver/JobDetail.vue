<template>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <AlertBox :message="error" />
            <AlertBox :message="notice" variant="success" />

            <div v-if="loading" class="empty-state">Loading…</div>

            <div v-else-if="job" class="card">
                <div class="card-body p-4">
                    <h4 class="mb-1">{{ job.title }}</h4>
                    <div class="text-muted mb-3">
                        {{ job.carrier?.company_name }}
                        <span v-if="job.city || job.state">· {{ [job.city, job.state].filter(Boolean).join(', ') }}</span>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span v-if="job.route_type" class="badge bg-light text-dark">{{ label(routeTypes, job.route_type) }}</span>
                        <span v-if="job.driver_type" class="badge bg-light text-dark">{{ label(driverTypes, job.driver_type) }}</span>
                        <span v-if="job.equipment" class="badge bg-light text-dark">{{ job.equipment }}</span>
                    </div>

                    <div v-if="pay" class="fw-semibold mb-3">{{ pay }}</div>

                    <p class="mb-4" style="white-space: pre-line">{{ job.description }}</p>

                    <div v-if="job.carrier?.about" class="border-top pt-3 mb-4">
                        <h6>About the company</h6>
                        <p class="text-muted small mb-0" style="white-space: pre-line">{{ job.carrier.about }}</p>
                    </div>

                    <div v-if="job.carrier" class="border-top pt-3 mb-4">
                        <h6>What others say about them</h6>
                        <CarrierReputation :carrier-id="job.carrier.id" />
                    </div>

                    <div class="border-top pt-3">
                        <label class="form-label">Note to the carrier <span class="text-muted small">(optional)</span></label>
                        <textarea v-model="coverNote" rows="3" class="form-control mb-3"
                                  placeholder="A short note about yourself"></textarea>

                        <button class="btn btn-primary" :disabled="applying || applied" @click="apply">
                            <template v-if="applied">Application sent</template>
                            <template v-else>{{ applying ? 'Sending…' : 'Apply' }}</template>
                        </button>

                        <router-link :to="{ name: 'driver.jobs' }" class="btn btn-link">Back</router-link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../../api';
import AlertBox from '../../components/AlertBox.vue';
import CarrierReputation from '../../components/CarrierReputation.vue';
import { ROUTE_TYPES, DRIVER_TYPES, labelFor, formatPay } from '../../constants';

export default {
    name: 'DriverJobDetailPage',

    components: { AlertBox, CarrierReputation },

    data() {
        return {
            job: null,
            coverNote: '',
            loading: true,
            applying: false,
            applied: false,
            error: null,
            notice: null,
            routeTypes: ROUTE_TYPES,
            driverTypes: DRIVER_TYPES,
        };
    },

    computed: {
        pay() {
            if (!this.job) return null;

            const min = formatPay(this.job.pay_min_cents, this.job.pay_unit);
            const max = formatPay(this.job.pay_max_cents, this.job.pay_unit);

            if (min && max) return `${min} – ${max}`;

            return min || max || null;
        },
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            try {
                const { data } = await api.get(`/driver/jobs/${this.$route.params.id}`);
                this.job = data.job;
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        label(list, value) {
            return labelFor(list, value);
        },

        async apply() {
            this.applying = true;
            this.error = null;

            try {
                await api.post(`/driver/jobs/${this.job.id}/apply`, { cover_note: this.coverNote || null });
                this.applied = true;
                this.notice = 'Application sent. The carrier will get in touch.';
            } catch (e) {
                this.error = e.friendly;

                // An unverified account cannot apply.
                if (e.code === 'verification_required') {
                    this.$router.push({ name: 'verify' });
                }
            } finally {
                this.applying = false;
            }
        },
    },
};
</script>
