<template>
    <div>
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h4 class="page-title">Driver pool</h4>
                <p class="page-lede">Filter on your criteria — results come back scored and sorted.</p>
            </div>
            <button class="btn btn-primary" @click="openAddForm">Add a driver</button>
        </div>

        <AlertBox :message="error" :errors="errors" />

        <div class="row g-4">
            <div class="col-lg-3">
                <div class="card filter-panel">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Filters</h6>
                            <button class="btn btn-sm btn-link p-0" @click="reset">Reset</button>
                        </div>

                        <div class="mb-3">
                            <input v-model="filters.q" type="search" class="form-control form-control-sm"
                                   placeholder="Name, phone or city" @keyup.enter="load(1)">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small mb-1">State</label>
                            <select v-model="filters.state" class="form-select form-select-sm" @change="load(1)">
                                <option :value="null">All</option>
                                <option v-for="state in states" :key="state" :value="state">{{ state }}</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small mb-1">CDL class</label>
                            <select v-model="filters.cdl_class" class="form-select form-select-sm" @change="load(1)">
                                <option :value="null">All</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small mb-1">Min experience (yrs)</label>
                            <input v-model.number="filters.min_experience" type="number" step="0.5" min="0"
                                   class="form-control form-control-sm" @change="load(1)">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small mb-1">Max accidents (3y)</label>
                            <input v-model.number="filters.max_accidents" type="number" min="0"
                                   class="form-control form-control-sm" @change="load(1)">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small mb-1">Max violations (3y)</label>
                            <input v-model.number="filters.max_violations" type="number" min="0"
                                   class="form-control form-control-sm" @change="load(1)">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small mb-1">Max jobs (3y)</label>
                            <input v-model.number="filters.max_jobs_3y" type="number" min="0"
                                   class="form-control form-control-sm" @change="load(1)">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small mb-1">Endorsement</label>
                            <select v-model="filters.endorsement" class="form-select form-select-sm" @change="load(1)">
                                <option :value="null">All</option>
                                <option v-for="item in endorsements" :key="item.value" :value="item.value">{{ item.label }}</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small mb-1">Equipment</label>
                            <select v-model="filters.equipment" class="form-select form-select-sm" @change="load(1)">
                                <option :value="null">All</option>
                                <option v-for="item in equipment" :key="item.value" :value="item.value">{{ item.label }}</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small mb-1">Driver type</label>
                            <select v-model="filters.driver_type" class="form-select form-select-sm" @change="load(1)">
                                <option :value="null">All</option>
                                <option v-for="item in driverTypes" :key="item.value" :value="item.value">{{ item.label }}</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small mb-1">Min score</label>
                            <input v-model.number="filters.min_score" type="number" min="0" max="100"
                                   class="form-control form-control-sm" @change="load(1)">
                        </div>

                        <div class="form-check mb-2">
                            <input id="no-dui" v-model="filters.no_dui" type="checkbox" class="form-check-input"
                                   @change="load(1)">
                            <label class="form-check-label small" for="no-dui">No DUI</label>
                        </div>

                        <div class="form-check">
                            <input id="hide-dq" v-model="filters.hide_disqualified" type="checkbox"
                                   class="form-check-input" @change="load(1)">
                            <label class="form-check-label small" for="hide-dq">Hide knocked out</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted small">
                        <strong>{{ meta.total || 0 }}</strong> drivers
                        <span v-if="meta.qualified !== undefined"> · {{ meta.qualified }} qualified</span>
                        <span v-if="meta.capped_by_plan" class="text-warning">
                            · capped at {{ meta.plan_limit }} by your plan
                        </span>
                    </div>
                    <div style="width: 230px">
                        <select v-model="filters.sort" class="form-select form-select-sm" @change="load(1)">
                            <option value="score">Score, highest first</option>
                            <option value="experience">Experience</option>
                            <option value="safety">Safety, cleanest first</option>
                            <option value="date">Recently added</option>
                            <option value="name">Name</option>
                        </select>
                    </div>
                </div>

                <div v-if="loading" class="empty-state">Loading…</div>

                <div v-else-if="!rows.length" class="empty-state">
                    No drivers match these filters.
                </div>

                <div v-else class="card">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr class="text-muted small">
                                    <th style="width: 90px">Score</th>
                                    <th>Driver</th>
                                    <th>Experience</th>
                                    <th>Safety</th>
                                    <th>Stability</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in rows" :key="row.driver.id" class="driver-row" @click="selected = row">
                                    <td>
                                        <ScorePill :score="row.score" :tier="row.tier" :disqualified="row.disqualified" />
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ row.driver.full_name }}</div>
                                        <div class="text-muted small">
                                            {{ [row.driver.city, row.driver.state].filter(Boolean).join(', ') || '—' }}
                                            · {{ row.driver.phone || '—' }}
                                        </div>
                                    </td>
                                    <td>
                                        {{ row.driver.years_experience }} yrs
                                        <div class="text-muted small">CDL {{ row.driver.cdl_class || '—' }}</div>
                                    </td>
                                    <td>
                                        <span :class="row.driver.accidents_3y ? 'text-danger' : 'text-success'">
                                            {{ row.driver.accidents_3y }} accidents
                                        </span>
                                        <div class="text-muted small">{{ row.driver.moving_violations_3y }} violations</div>
                                    </td>
                                    <td class="text-muted small">
                                        {{ row.driver.jobs_last_3_years }} jobs / 3 yrs
                                        <div>{{ row.driver.longest_tenure_months }} mo longest</div>
                                    </td>
                                    <td @click.stop>
                                        <select :value="row.driver.status" class="form-select form-select-sm mb-1"
                                                @change="updateStatus(row, $event.target.value)">
                                            <option v-for="item in driverStatuses" :key="item.value" :value="item.value">
                                                {{ item.label }}
                                            </option>
                                        </select>
                                        <button v-if="row.driver.user_id" class="btn btn-sm btn-outline-primary w-100"
                                                :disabled="contacting === row.driver.id" @click="contact(row)">
                                            {{ contacting === row.driver.id ? '…' : 'Message' }}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <nav v-if="meta.last_page > 1" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <li class="page-item" :class="{ disabled: meta.current_page === 1 }">
                            <button class="page-link" @click="load(meta.current_page - 1)">Previous</button>
                        </li>
                        <li class="page-item disabled">
                            <span class="page-link">{{ meta.current_page }} / {{ meta.last_page }}</span>
                        </li>
                        <li class="page-item" :class="{ disabled: meta.current_page === meta.last_page }">
                            <button class="page-link" @click="load(meta.current_page + 1)">Next</button>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

        <!-- Driver detail -->
        <div v-if="selected" class="modal d-block" tabindex="-1" style="background: rgba(15,23,42,.5)"
             @click.self="selected = null">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-0">{{ selected.driver.full_name }}</h5>
                            <div class="text-muted small">
                                {{ selected.driver.phone || '—' }} · {{ selected.driver.email || '—' }}
                            </div>
                        </div>
                        <button type="button" class="btn-close" @click="selected = null"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-4">
                            <ScorePill :score="selected.score" :tier="selected.tier"
                                       :disqualified="selected.disqualified" />
                        </div>

                        <DriverSummary :driver="selected.driver" class="mb-4" />

                        <h6>Score breakdown</h6>
                        <ScoreBreakdown :rows="selected.breakdown" :knockouts="selected.knockouts" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual driver entry -->
        <div v-if="showAddForm" class="modal d-block" tabindex="-1" style="background: rgba(15,23,42,.5)">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add a driver</h5>
                        <button type="button" class="btn-close" @click="showAddForm = false"></button>
                    </div>
                    <form @submit.prevent="saveDriver">
                        <div class="modal-body">
                            <AlertBox :message="formError" :errors="errors" />
                            <DriverForm v-model="newDriver" />
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" @click="showAddForm = false">Cancel</button>
                            <button class="btn btn-primary" :disabled="saving">
                                {{ saving ? 'Saving…' : 'Add driver' }}
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
import DriverForm from '../../components/DriverForm.vue';
import DriverSummary from '../../components/DriverSummary.vue';
import ScorePill from '../../components/ScorePill.vue';
import ScoreBreakdown from '../../components/ScoreBreakdown.vue';
import {
    US_STATES, ENDORSEMENTS, EQUIPMENT, DRIVER_TYPES, DRIVER_STATUSES,
} from '../../constants';

function emptyFilters() {
    return {
        q: '',
        state: null,
        cdl_class: null,
        driver_type: null,
        endorsement: null,
        equipment: null,
        min_experience: null,
        max_accidents: null,
        max_violations: null,
        max_jobs_3y: null,
        min_score: null,
        no_dui: false,
        hide_disqualified: false,
        sort: 'score',
    };
}

function emptyDriver() {
    return {
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        city: '',
        state: null,
        zip: '',
        cdl_class: 'A',
        cdl_state: null,
        endorsements: [],
        equipment_experience: [],
        years_experience: 0,
        driver_type: 'company_driver',
        preferred_route: null,
        jobs_last_3_years: 0,
        longest_tenure_months: 0,
        unemployment_gap_months: 0,
        accidents_3y: 0,
        preventable_accidents_3y: 0,
        moving_violations_3y: 0,
        dui_ever: false,
        license_suspended_ever: false,
        failed_drug_test_ever: false,
        can_pass_drug_test: true,
        sap_status: 'none',
        work_authorization: null,
        willing_to_relocate: false,
        notes: '',
    };
}

export default {
    name: 'CarrierTalentPoolPage',

    components: { AlertBox, DriverForm, DriverSummary, ScorePill, ScoreBreakdown },

    data() {
        return {
            rows: [],
            meta: { current_page: 1, last_page: 1, total: 0 },
            filters: emptyFilters(),
            selected: null,
            showAddForm: false,
            newDriver: emptyDriver(),
            states: US_STATES,
            endorsements: ENDORSEMENTS,
            equipment: EQUIPMENT,
            driverTypes: DRIVER_TYPES,
            driverStatuses: DRIVER_STATUSES,
            loading: true,
            saving: false,
            contacting: null,
            error: null,
            formError: null,
            errors: {},
        };
    },

    created() {
        this.load();
    },

    methods: {
        async load(page = 1) {
            if (page < 1 || (this.meta.last_page && page > this.meta.last_page && page !== 1)) return;

            this.loading = true;
            this.error = null;

            // Empty filters are dropped; the server rejects nulls.
            const params = Object.fromEntries(
                Object.entries({ ...this.filters, page })
                    .filter(([, value]) => value !== null && value !== '' && value !== false)
            );

            try {
                const { data } = await api.get('/carrier/drivers', { params });
                this.rows = data.data;
                this.meta = data.meta;
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        reset() {
            this.filters = emptyFilters();
            this.load(1);
        },

        openAddForm() {
            this.newDriver = emptyDriver();
            this.formError = null;
            this.errors = {};
            this.showAddForm = true;
        },

        async saveDriver() {
            this.saving = true;
            this.formError = null;
            this.errors = {};

            try {
                await api.post('/carrier/drivers', this.newDriver);
                this.showAddForm = false;
                await this.load(1);
            } catch (e) {
                this.formError = e.friendly;
                this.errors = e.errors || {};
            } finally {
                this.saving = false;
            }
        },

        /** Open a thread with this driver and jump straight into it. */
        async contact(row) {
            this.contacting = row.driver.id;
            this.error = null;

            try {
                const { data } = await api.post('/carrier/conversations', {
                    driver_profile_id: row.driver.id,
                });

                this.$router.push({ name: 'conversations', query: { id: data.conversation.id } });
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.contacting = null;
            }
        },

        async updateStatus(row, status) {
            try {
                await api.put(`/carrier/drivers/${row.driver.id}/status`, { status });
                row.driver.status = status;
            } catch (e) {
                this.error = e.friendly;
            }
        },
    },
};
</script>
