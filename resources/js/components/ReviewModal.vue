<template>
    <div class="modal d-block" tabindex="-1" style="background: rgba(14,20,27,.5)" @click.self="$emit('close')">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ title }}</h5>
                    <button type="button" class="btn-close" @click="$emit('close')"></button>
                </div>
                <form @submit.prevent="submit">
                    <div class="modal-body">
                        <AlertBox :message="error" :errors="errors" />

                        <label class="form-label">Rating</label>
                        <div class="d-flex gap-2 mb-3">
                            <button v-for="star in 5" :key="star" type="button"
                                    class="btn" :class="star <= rating ? 'btn-warning' : 'btn-outline-secondary'"
                                    @click="rating = star">
                                {{ star }}
                            </button>
                        </div>

                        <div v-if="isNegative" class="alert alert-warning small">
                            This counts as unsatisfactory. After {{ threshold }} published unsatisfactory
                            reviews the other side is blacklisted — which is why we check the proof first.
                        </div>

                        <label class="form-label">Comment <span class="text-muted small">(optional)</span></label>
                        <textarea v-model="body" rows="4" class="form-control mb-4"
                                  placeholder="What went well, what did not"></textarea>

                        <div class="border rounded p-3 bg-light">
                            <div class="fw-semibold mb-1">Proof you worked together</div>
                            <p class="text-muted small mb-3">
                                Attach something that shows the two of you actually worked together — a rate
                                confirmation, settlement, pay stub or employment letter. We check it and
                                contact the other side before anything is published. Reviews without proof
                                are not published.
                            </p>

                            <div class="row g-2 mb-2">
                                <div class="col-md-5">
                                    <label class="form-label small mb-1">Document type</label>
                                    <select v-model="kind" class="form-select form-select-sm">
                                        <option v-for="option in kinds" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label small mb-1">Files</label>
                                    <input ref="files" type="file" class="form-control form-control-sm"
                                           accept="application/pdf,image/jpeg,image/png,image/webp" multiple
                                           @change="pickFiles">
                                </div>
                            </div>

                            <label class="form-label small mb-1">Note for our team <span class="text-muted">(optional)</span></label>
                            <input v-model="proofNote" type="text" class="form-control form-control-sm"
                                   placeholder="e.g. loads run in March, dispatcher was Mike">

                            <div v-if="files.length" class="label-mono mt-2">{{ files.length }} file(s) attached</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" @click="$emit('close')">Cancel</button>
                        <button class="btn btn-primary" :disabled="saving || !files.length">
                            {{ saving ? 'Sending…' : 'Submit for review' }}
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
        title: { type: String, default: 'Leave a review' },
    },

    emits: ['close', 'saved'],

    data() {
        return {
            rating: 5,
            body: '',
            kind: 'rate_confirmation',
            kinds: [
                { value: 'rate_confirmation', label: 'Rate confirmation' },
                { value: 'employment_letter', label: 'Employment letter' },
                { value: 'settlement', label: 'Settlement' },
                { value: 'paystub', label: 'Pay stub' },
                { value: 'other', label: 'Other' },
            ],
            files: [],
            proofNote: '',
            saving: false,
            error: null,
            errors: {},
            // Mirrors the server threshold; used only for the warning.
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
        pickFiles(event) {
            this.files = Array.from(event.target.files || []).slice(0, 5);
        },

        async submit() {
            this.saving = true;
            this.error = null;
            this.errors = {};

            const payload = new FormData();
            payload.append('application_id', this.applicationId);
            payload.append('rating', this.rating);

            if (this.body.trim()) payload.append('body', this.body.trim());
            if (this.proofNote.trim()) payload.append('proof_note', this.proofNote.trim());

            this.files.forEach((file, index) => {
                payload.append('proofs[]', file);
                payload.append(`proof_kinds[${index}]`, this.kind);
            });

            try {
                const { data } = await api.post('/reviews', payload);
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
