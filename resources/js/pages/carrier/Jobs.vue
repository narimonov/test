<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Vakansiyalar</h4>
            <button class="btn btn-primary" @click="openForm()">Yangi vakansiya</button>
        </div>

        <AlertBox :message="error" :errors="errors" />

        <div v-if="loading" class="empty-state">Yuklanmoqda…</div>

        <div v-else-if="!jobs.length" class="empty-state">
            Hali vakansiya joylamagansiz.
        </div>

        <div v-else class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr class="text-muted small">
                            <th>Lavozim</th>
                            <th>Joylashuv</th>
                            <th>Route</th>
                            <th class="text-center">Arizalar</th>
                            <th class="text-center">Holat</th>
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
                                    {{ job.is_open ? 'Ochiq' : 'Yopiq' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <router-link :to="{ name: 'carrier.applicants', params: { id: job.id } }"
                                             class="btn btn-sm btn-outline-primary me-1">
                                    Arizachilar
                                </router-link>
                                <button class="btn btn-sm btn-outline-secondary me-1" @click="openForm(job)">Tahrir</button>
                                <button class="btn btn-sm btn-outline-danger" @click="remove(job)">O'chirish</button>
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
                        <h5 class="modal-title">{{ form.id ? 'Vakansiyani tahrirlash' : 'Yangi vakansiya' }}</h5>
                        <button type="button" class="btn-close" @click="showForm = false"></button>
                    </div>
                    <form @submit.prevent="save">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Lavozim nomi</label>
                                    <input v-model="form.title" type="text" class="form-control" required
                                           placeholder="OTR CDL-A Driver">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Shahar</label>
                                    <input v-model="form.city" type="text" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Shtat</label>
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
                                    <label class="form-label">Route turi</label>
                                    <select v-model="form.route_type" class="form-select">
                                        <option :value="null">—</option>
                                        <option v-for="item in routeTypes" :key="item.value" :value="item.value">{{ item.label }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Driver turi</label>
                                    <select v-model="form.driver_type" class="form-select">
                                        <option v-for="item in driverTypes" :key="item.value" :value="item.value">{{ item.label }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Maosh (dan)</label>
                                    <input v-model.number="payMin" type="number" step="0.01" min="0" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Maosh (gacha)</label>
                                    <input v-model.number="payMax" type="number" step="0.01" min="0" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Birlik</label>
                                    <select v-model="form.pay_unit" class="form-select">
                                        <option :value="null">—</option>
                                        <option value="per_mile">$/mile</option>
                                        <option value="per_week">$/hafta</option>
                                        <option value="percentage">%</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Tavsif</label>
                                    <textarea v-model="form.description" rows="5" class="form-control"></textarea>
                                </div>

                                <div class="col-12">
                                    <div class="border rounded p-3 bg-light">
                                        <div class="fw-semibold mb-2">Shu vakansiya uchun minimal talab</div>
                                        <p class="text-muted small">
                                            Bu yerda kiritilgani umumiy knockout kriteriyalarni shu vakansiya
                                            uchun bekor qiladi.
                                        </p>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label small">Minimal tajriba (yil)</label>
                                                <input v-model.number="minExperience" type="number" step="0.5" min="0"
                                                       class="form-control form-control-sm">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small">CDL klass</label>
                                                <select v-model="requiredCdl" class="form-select form-select-sm">
                                                    <option :value="null">Standart (A)</option>
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
                                        <label class="form-check-label" for="is_open">Vakansiya ochiq</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" @click="showForm = false">Bekor</button>
                            <button class="btn btn-primary" :disabled="saving">
                                {{ saving ? 'Saqlanmoqda…' : 'Saqlash' }}
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

        /** requirements — knockout override'lari, UI'da ikkita sodda maydon. */
        minExperience: {
            get() {
                return this.ruleValue('years_experience');
            },
            set(value) {
                this.setRule('years_experience', 'gte', value, 'Tajriba talabga yetmaydi');
            },
        },
        requiredCdl: {
            get() {
                const value = this.ruleValue('cdl_class');

                return Array.isArray(value) ? value[0] : value;
            },
            set(value) {
                this.setRule('cdl_class', 'in', value ? [value] : null, 'CDL klassi mos emas');
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
            if (!window.confirm(`"${job.title}" vakansiyasi o'chirilsinmi? Unga kelgan arizalar ham o'chadi.`)) {
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
