<template>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h4 class="page-title mb-3">Company profile</h4>

            <AlertBox :message="error" :errors="errors" />
            <AlertBox :message="notice" variant="success" />

            <div v-if="loading" class="empty-state">Loading…</div>

            <div v-else class="card">
                <div class="card-body p-4">
                    <form @submit.prevent="save">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Company name</label>
                                <input v-model="form.company_name" type="text" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Fleet size</label>
                                <input v-model.number="form.fleet_size" type="number" min="0" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">MC number</label>
                                <input v-model="form.mc_number" type="text" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">DOT number</label>
                                <input v-model="form.dot_number" type="text" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact person</label>
                                <input v-model="form.contact_name" type="text" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact phone</label>
                                <input v-model="form.contact_phone" type="tel" class="form-control">
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
                                <label class="form-label">ZIP</label>
                                <input v-model="form.zip" type="text" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Website</label>
                                <input v-model="form.website" type="text" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">About the company</label>
                                <textarea v-model="form.about" rows="4" class="form-control"
                                          placeholder="A short description drivers will see"></textarea>
                            </div>
                        </div>

                        <button class="btn btn-primary mt-4" :disabled="saving">
                            {{ saving ? 'Saving…' : 'Save' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../../api';
import AlertBox from '../../components/AlertBox.vue';
import { US_STATES } from '../../constants';
import { useAuthStore } from '../../stores/auth';

export default {
    name: 'CarrierCompanyPage',

    components: { AlertBox },

    setup() {
        return { auth: useAuthStore() };
    },

    data() {
        return {
            form: {},
            states: US_STATES,
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
                const { data } = await api.get('/carrier/profile');
                this.form = { ...data.carrier };
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        async save() {
            this.saving = true;
            this.error = null;
            this.notice = null;
            this.errors = {};

            const { id, user_id, created_at, updated_at, scoring_overrides, has_active_subscription,
                subscription_plan, subscription_status, subscription_expires_at, ...payload } = this.form;

            try {
                const { data } = await api.put('/carrier/profile', payload);
                this.form = { ...data.carrier };
                await this.auth.refresh();
                this.notice = 'Saved.';
            } catch (e) {
                this.error = e.friendly;
                this.errors = e.errors || {};
            } finally {
                this.saving = false;
            }
        },
    },
};
</script>
