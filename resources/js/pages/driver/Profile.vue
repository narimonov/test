<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="page-title mb-1">My profile</h4>
                <p class="text-muted small mb-0">
                    The fuller your profile, the sooner carriers find you.
                </p>
            </div>
            <div v-if="completeness" class="text-end">
                <div class="fw-bold">{{ completeness.percent }}%</div>
                <div class="label-mono">complete</div>
            </div>
        </div>

        <div v-if="completeness && completeness.percent < 100" class="progress mb-4" style="height: 6px">
            <div class="progress-bar" :style="{ width: completeness.percent + '%' }"></div>
        </div>

        <AlertBox :message="error" :errors="errors" />
        <AlertBox :message="notice" variant="success" />

        <div v-if="loading" class="empty-state">Loading…</div>

        <div v-else class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body p-4">
                        <form @submit.prevent="save">
                            <DriverForm v-model="form" />

                            <hr class="my-4">

                            <div class="form-check mb-3">
                                <input id="searchable" v-model="form.is_searchable" type="checkbox" class="form-check-input">
                                <label class="form-check-label" for="searchable">
                                    Let carriers find me in the driver pool
                                </label>
                            </div>

                            <button class="btn btn-primary" :disabled="saving">
                                {{ saving ? 'Saving…' : 'Save' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card filter-panel">
                    <div class="card-body">
                        <h6 class="mb-3">How you score</h6>

                        <div v-if="selfScore" class="d-flex align-items-center gap-3 mb-3">
                            <ScorePill :score="selfScore.score" :tier="selfScore.tier"
                                       :disqualified="selfScore.disqualified" />
                            <div class="small text-muted">
                                An estimate against the default criteria. Each carrier can
                                weight things differently.
                            </div>
                        </div>

                        <ScoreBreakdown v-if="selfScore" :rows="selfScore.breakdown"
                                        :knockouts="selfScore.knockouts" />

                        <div v-if="completeness?.missing?.length" class="mt-3">
                            <div class="label-mono mb-1">Still missing</div>
                            <ul class="small text-muted mb-0 ps-3">
                                <li v-for="field in completeness.missing" :key="field">{{ fieldLabel(field) }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../../api';
import AlertBox from '../../components/AlertBox.vue';
import DriverForm from '../../components/DriverForm.vue';
import ScorePill from '../../components/ScorePill.vue';
import ScoreBreakdown from '../../components/ScoreBreakdown.vue';

const FIELD_LABELS = {
    first_name: 'First name',
    last_name: 'Last name',
    phone: 'Phone',
    city: 'City',
    state: 'State',
    cdl_class: 'CDL class',
    cdl_expires_at: 'CDL expiry',
    years_experience: 'Experience',
    equipment_experience: 'Equipment experience',
    driver_type: 'Driver type',
    preferred_route: 'Route type',
    work_authorization: 'Work authorisation',
    available_from: 'Available from',
};

export default {
    name: 'DriverProfilePage',

    components: { AlertBox, DriverForm, ScorePill, ScoreBreakdown },

    data() {
        return {
            form: {},
            selfScore: null,
            completeness: null,
            loading: true,
            saving: false,
            error: null,
            notice: null,
            errors: {},
        };
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            try {
                const { data } = await api.get('/driver/profile');
                this.apply(data);
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        apply(data) {
            this.form = {
                ...data.profile,
                endorsements: data.profile.endorsements || [],
                equipment_experience: data.profile.equipment_experience || [],
            };
            this.selfScore = data.self_score;
            this.completeness = data.completeness;
        },

        async save() {
            this.saving = true;
            this.error = null;
            this.notice = null;
            this.errors = {};

            try {
                const { data } = await api.put('/driver/profile', this.payload());
                this.apply(data);
                this.notice = 'Profile saved.';
            } catch (e) {
                this.error = e.friendly;
                this.errors = e.errors || {};
            } finally {
                this.saving = false;
            }
        },

        /** Strip fields the server does not accept (id, timestamps). */
        payload() {
            const { id, user_id, created_by_user_id, source, status, full_name,
                created_at, updated_at, resume_path, ...rest } = this.form;

            return rest;
        },

        fieldLabel(field) {
            return FIELD_LABELS[field] || field;
        },
    },
};
</script>
