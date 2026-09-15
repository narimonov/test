<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="page-title mb-0">Job posts</h4>
            <button class="btn btn-primary" @click="openForm()">New job post</button>
        </div>

        <AlertBox :message="error" :errors="errors" />

        <div v-if="loading" class="empty-state">Loading…</div>

        <div v-else-if="!jobs.length" class="empty-state">
            No job posts yet.
        </div>

        <div v-else class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr class="text-muted small">
                            <th>Title</th>
                            <th>Location</th>
                            <th>Route</th>
                            <th class="text-center">Applicants</th>
                            <th class="text-center">Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="job in jobs" :key="job.id">
                            <td class="fw-semibold">{{ job.title }}</td>
                            <td class="text-muted">{{ [job.city, job.state].filter(Boolean).join(', ') || '—' }}</td>
                            <td class="text-muted">{{ label(routeTypes, job.route_type) }}</td>
                            <td class="text-center">{{ job.applications_count }}</td>
                            <td class="text-center">
                                <span class="badge" :class="job.is_open ? 'bg-success' : 'bg-secondary'">
                                    {{ job.is_open ? 'Open' : 'Closed' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <router-link :to="{ name: 'carrier.applicants', params: { id: job.id } }"
                                             class="btn btn-sm btn-outline-primary me-1">
                                    Applicants
                                </router-link>
                                <router-link :to="{ name: 'carrier.matches', params: { id: job.id } }"
                                             class="btn btn-sm btn-outline-primary me-1">
                                    Matches
                                </router-link>
                                <button class="btn btn-sm btn-outline-secondary me-1" @click="openForm(job)">Edit</button>
                                <button class="btn btn-sm btn-outline-danger" @click="remove(job)">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Vakansiya formasi -->
        <div v-if="showForm" class="modal d-block" tabindex="-1" style="background: rgba(15,23,42,.5)">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ form.id ? 'Edit job post' : 'New job post' }}</h5>
                        <button type="button" class="btn-close" @click="showForm = false"></button>
                    </div>
                    <form @submit.prevent="save">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Job title</label>
                                    <input v-model="form.title" type="text" class="form-control" required
                                           placeholder="OTR CDL-A Driver">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">City</label>
                                    <input v-model="form.city" type="text" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">State</label>
                                    <select v-model="form.state" class="form-select">
                                        <option :value="null">—</option>
                                        <option v-for="state in states" :key="state" :value="state">{{ state }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Equipment</label>
                                    <input v-model="form.equipment" type="text" class="form-control" placeholder="Dry Van">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Route type</label>
                                    <select v-model="form.route_type" class="form-select">
                                        <option :value="null">—</option>
                                        <option v-for="item in routeTypes" :key="item.value" :value="item.value">{{ item.label }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Driver type</label>
                                    <select v-model="form.driver_type" class="form-select">
                                        <option v-for="item in driverTypes" :key="item.value" :value="item.value">{{ item.label }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Pay from</label>
                                    <input v-model.number="payMin" type="number" step="0.01" min="0" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Pay to</label>
                                    <input v-model.number="payMax" type="number" step="0.01" min="0" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Unit</label>
                                    <select v-model="form.pay_unit" class="form-select">
                                        <option :value="null">—</option>
                                        <option value="per_mile">$/mile</option>
                                        <option value="per_week">$/week</option>
                                        <option value="percentage">%</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea v-model="form.description" rows="5" class="form-control"></textarea>
                                </div>

                                <div class="col-12">
                                    <div class="border rounded p-3 bg-light">
                                        <div class="fw-semibold mb-2">Requirements for this job</div>
                                        <p class="text-muted small">
                                            These override the general knockout rules for this job only,
                                            and they drive the list of matching drivers.
                                        </p>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label small">Minimum experience (years)</label>
                                                <input v-model.number="minExperience" type="number" step="0.5" min="0"
                                                       class="form-control form-control-sm">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small">CDL class</label>
                                                <select v-model="requiredCdl" class="form-select form-select-sm">
                                                    <option :value="null">Default (A)</option>
                                                    <option value="A">A</option>
                                                    <option value="B">B</option>
                                                    <option value="C">C</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-check">
                                        <input id="is_open" v-model="form.is_open" type="checkbox" class="form-check-input">
                                        <label class="form-check-label" for="is_open">Job is open</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" @click="showForm = false">Cancel</button>
                            <button class="btn btn-primary" :disabled="saving">
                                {{ saving ? 'Saving…' : 'Save' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../../api';
import AlertBox from '../../components/AlertBox.vue';
import { US_STATES, ROUTE_TYPES, DRIVER_TYPES, labelFor } from '../../constants';

function emptyJob() {
    return {
        id: null,
        title: '',
        description: '',
        city: '',
        state: null,
        route_type: null,
        driver_type: 'company_driver',
        equipment: '',
        pay_min_cents: null,
        pay_max_cents: null,
        pay_unit: null,
        requirements: null,
        is_open: true,
    };
}

export default {
    name: 'CarrierJobsPage',

    components: { AlertBox },

    data() {
        return {
            jobs: [],
            form: emptyJob(),
            showForm: false,
            loading: true,
            saving: false,
            error: null,
            errors: {},
            states: US_STATES,
            routeTypes: ROUTE_TYPES,
            driverTypes: DRIVER_TYPES,
        };
    },

    computed: {
        payMin: {
            get() { return this.form.pay_min_cents ? this.form.pay_min_cents / 100 : null; },
            set(v) { this.form.pay_min_cents = v ? Math.round(v * 100) : null; },
        },
        payMax: {
            get() { return this.form.pay_max_cents ? this.form.pay_max_cents / 100 : null; },
            set(v) { this.form.pay_max_cents = v ? Math.round(v * 100) : null; },
        },

        /** requirements holds knockout overrides; the UI exposes two of them. */
        minExperience: {
            get() {
                return this.ruleValue('years_experience');
            },
            set(value) {
                this.setRule('years_experience', 'gte', value, 'Experience below the minimum');
            },
        },
        requiredCdl: {
            get() {
                const value = this.ruleValue('cdl_class');

                return Array.isArray(value) ? value[0] : value;
            },
            set(value) {
                this.setRule('cdl_class', 'in', value ? [value] : null, 'Wrong CDL class');
            },
        },
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            this.loading = true;

            try {
                const { data } = await api.get('/carrier/jobs');
                this.jobs = data.data;
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        label(list, value) {
            return labelFor(list, value);
        },

        ruleValue(key) {
            return this.form.requirements?.knockouts?.find((rule) => rule.key === key)?.value ?? null;
        },

        setRule(key, operator, value, reason) {
            const requirements = this.form.requirements || {};
            const knockouts = (requirements.knockouts || []).filter((rule) => rule.key !== key);

            if (value !== null && value !== '' && !(Array.isArray(value) && !value.length)) {
                knockouts.push({ key, operator, value, reason });
            }

            this.form.requirements = knockouts.length ? { ...requirements, knockouts } : null;
        },

        openForm(job = null) {
            this.errors = {};
            this.error = null;
            this.form = job ? { ...emptyJob(), ...job } : emptyJob();
            this.showForm = true;
        },

        async save() {
            this.saving = true;
            this.error = null;
            this.errors = {};

            const { id, applications_count, created_at, updated_at, carrier_id, ...payload } = this.form;

            try {
                if (id) {
                    await api.put(`/carrier/jobs/${id}`, payload);
                } else {
                    await api.post('/carrier/jobs', payload);
                }

                this.showForm = false;
                await this.load();
            } catch (e) {
                this.error = e.friendly;
                this.errors = e.errors || {};
            } finally {
                this.saving = false;
            }
        },

        async remove(job) {
            if (!window.confirm(`Delete "${job.title}"? Its applications go with it.`)) {
                return;
            }

            try {
                await api.delete(`/carrier/jobs/${job.id}`);
                await this.load();
            } catch (e) {
                this.error = e.friendly;
            }
        },
    },
};
</script>
