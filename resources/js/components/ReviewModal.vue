<template>
    <div class="modal d-block" tabindex="-1" style="background: rgba(15,23,42,.5)" @click.self="$emit('close')">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ title }}</h5>
                    <button type="button" class="btn-close" @click="$emit('close')"></button>
                </div>
                <form @submit.prevent="submit">
                    <div class="modal-body">
                        <AlertBox :message="error" :errors="errors" />

                        <label class="form-label">Baho</label>
                        <div class="d-flex gap-2 mb-3">
                            <button v-for="star in 5" :key="star" type="button"
                                    class="btn" :class="star <= rating ? 'btn-warning' : 'btn-outline-secondary'"
                                    @click="rating = star">
                                {{ star }}
                            </button>
                        </div>

                        <div v-if="isNegative" class="alert alert-warning small">
                            Bu qoniqarsiz baho hisoblanadi. {{ threshold }} ta qoniqarsiz bahodan
                            keyin qarshi tomon blacklist'ga tushadi.
                        </div>

                        <label class="form-label">Izoh <span class="text-muted small">(ixtiyoriy)</span></label>
                        <textarea v-model="body" rows="4" class="form-control"
                                  placeholder="Nima yaxshi, nima yomon bo'ldi"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" @click="$emit('close')">Bekor</button>
                        <button class="btn btn-primary" :disabled="saving">
                            {{ saving ? 'Yuborilmoqda…' : 'Baho qoldirish' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../api';
import AlertBox from './AlertBox.vue';

export default {
    name: 'ReviewModal',

    components: { AlertBox },

    props: {
        applicationId: { type: Number, required: true },
        title: { type: String, default: 'Baho qoldirish' },
    },

    emits: ['close', 'saved'],

    data() {
        return {
            rating: 5,
            body: '',
            saving: false,
            error: null,
            errors: {},
            // Server bilan bir xil chegara; faqat ogohlantirish uchun.
            threshold: 3,
            negativeAt: 2,
        };
    },

    computed: {
        isNegative() {
            return this.rating <= this.negativeAt;
        },
    },

    methods: {
        async submit() {
            this.saving = true;
            this.error = null;
            this.errors = {};

            try {
                const { data } = await api.post('/reviews', {
                    application_id: this.applicationId,
                    rating: this.rating,
                    body: this.body || null,
                });

                this.$emit('saved', data);
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
