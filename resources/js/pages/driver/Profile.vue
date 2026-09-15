<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-1">Driver profilim</h4>
                <p class="text-muted small mb-0">
                    Profil to'liq bo'lsa kompaniyalar sizni tezroq topadi.
                </p>
            </div>
            <div v-if="completeness" class="text-end">
                <div class="fw-bold">{{ completeness.percent }}%</div>
                <div class="small text-muted">to'ldirilgan</div>
            </div>
        </div>

        <div v-if="completeness && completeness.percent < 100" class="progress mb-4" style="height: 6px">
            <div class="progress-bar" :style="{ width: completeness.percent + '%' }"></div>
        </div>

        <AlertBox :message="error" :errors="errors" />
        <AlertBox :message="notice" variant="success" />

        <div v-if="loading" class="empty-state">Yuklanmoqda…</div>

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
                                    Kompaniyalar meni driver bazasidan topa olsin
                                </label>
                            </div>

                            <button class="btn btn-primary" :disabled="saving">
                                {{ saving ? 'Saqlanmoqda…' : 'Saqlash' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card filter-panel">
                    <div class="card-body">
                        <h6 class="mb-3">Profilingiz bahosi</h6>

                        <div v-if="selfScore" class="d-flex align-items-center gap-3 mb-3">
                            <ScorePill :score="selfScore.score" :tier="selfScore.tier"
                                       :disqualified="selfScore.disqualified" />
                            <div class="small text-muted">
                                Bu — standart kriteriyalar bo'yicha taxminiy baho.
                                Har bir kompaniya o'z vaznlarini qo'yishi mumkin.
                            </div>
                        </div>

                        <ScoreBreakdown v-if="selfScore" :rows="selfScore.breakdown"
                                        :knockouts="selfScore.knockouts" />

                        <div v-if="completeness?.missing?.length" class="mt-3">
                            <div class="small fw-semibold mb-1">To'ldirilmagan maydonlar</div>
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
    first_name: 'Ism',
    last_name: 'Familiya',
    phone: 'Telefon',
    city: 'Shahar',
    state: 'Shtat',
    cdl_class: 'CDL klass',
    cdl_expires_at: 'CDL muddati',
    years_experience: 'Tajriba',
    equipment_experience: 'Equipment tajribasi',
    driver_type: 'Driver turi',
    preferred_route: 'Route turi',
    work_authorization: 'Ishlash huquqi',
    available_from: 'Ishga chiqa oladigan sana',
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
                this.notice = 'Profil saqlandi.';
            } catch (e) {
                this.error = e.friendly;
                this.errors = e.errors || {};
            } finally {
                this.saving = false;
            }
        },

        /** Server qabul qilmaydigan maydonlarni (id, timestamps) yubormaymiz. */
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
