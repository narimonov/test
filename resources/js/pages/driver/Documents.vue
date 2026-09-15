<template>
    <div>
        <h4 class="page-title mb-1">My documents</h4>
        <p class="text-muted small mb-4">
            Photograph your CDL and medical card. Mark the parts you want hidden and they are
            destroyed, not blurred, then stamped with a <code>{{ watermarkText }}</code> watermark
            and saved as a PDF. Carriers only ever see that PDF.
        </p>

        <AlertBox :message="error" :errors="errors" />
        <AlertBox :message="notice" variant="success" />

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body p-4">
                        <h6 class="mb-3">New document</h6>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Document type</label>
                                <select v-model="form.type" class="form-select">
                                    <option v-for="(label, value) in types" :key="value" :value="value">
                                        {{ label }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Expiry date</label>
                                <input v-model="form.document_expires_at" type="date" class="form-control">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Photo</label>
                            <input ref="file" type="file" class="form-control"
                                   accept="image/jpeg,image/png,image/webp" capture="environment"
                                   @change="pick">
                            <div class="form-text">Your phone camera is fine. JPG, PNG or WEBP.</div>
                        </div>

                        <RedactionCanvas v-if="preview" v-model="form.redactions" :src="preview" class="mb-3" />

                        <button class="btn btn-primary" :disabled="!preview || uploading" @click="upload">
                            {{ uploading ? 'Uploading…' : 'Upload and redact' }}
                        </button>
                        <button v-if="preview" class="btn btn-link" @click="reset">Cancel</button>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-3">Uploaded</h6>

                        <div v-if="loading" class="text-muted small">Loading…</div>

                        <div v-else-if="!documents.length" class="text-muted small">
                            Nothing uploaded yet.
                        </div>

                        <div v-for="document in documents" :key="document.id"
                             class="d-flex justify-content-between align-items-start py-2 border-bottom">
                            <div>
                                <div class="fw-semibold">{{ document.type_label }}</div>
                                <div class="text-muted small">
                                    {{ document.redactions ? document.redactions.length : 0 }} area(s) redacted
                                    <template v-if="document.document_expires_at">
                                        · expires {{ document.document_expires_at }}
                                        <span v-if="document.is_expired" class="badge bg-danger ms-1">expired</span>
                                    </template>
                                </div>
                                <div v-if="document.status !== 'ready'" class="small text-danger">
                                    {{ document.status }} {{ document.failure_reason }}
                                </div>
                            </div>
                            <div class="text-end">
                                <button v-if="document.status === 'ready'" class="btn btn-sm btn-outline-primary mb-1"
                                        :disabled="opening === document.id" @click="openPdf(document)">
                                    {{ opening === document.id ? '…' : 'PDF' }}
                                </button>
                                <button class="btn btn-sm btn-outline-danger" @click="remove(document)">Delete</button>
                            </div>
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
import RedactionCanvas from '../../components/RedactionCanvas.vue';

export default {
    name: 'DriverDocumentsPage',

    components: { AlertBox, RedactionCanvas },

    data() {
        return {
            documents: [],
            types: {},
            watermarkText: 'recruiting',
            form: { type: 'cdl', document_expires_at: '', redactions: [] },
            file: null,
            preview: null,
            loading: true,
            uploading: false,
            opening: null,
            error: null,
            notice: null,
            errors: {},
        };
    },

    created() {
        this.load();
    },

    beforeUnmount() {
        this.releasePreview();
    },

    methods: {
        async load() {
            try {
                const { data } = await api.get('/driver/documents');
                this.documents = data.documents;
                this.types = data.types;
                this.watermarkText = data.watermark_text;

                if (!this.form.type) {
                    this.form.type = Object.keys(this.types)[0];
                }
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        pick(event) {
            const file = event.target.files[0];

            this.releasePreview();
            this.form.redactions = [];

            if (!file) {
                this.file = null;
                this.preview = null;

                return;
            }

            this.file = file;
            this.preview = URL.createObjectURL(file);
        },

        releasePreview() {
            if (this.preview) {
                URL.revokeObjectURL(this.preview);
                this.preview = null;
            }
        },

        reset() {
            this.releasePreview();
            this.file = null;
            this.form.redactions = [];

            if (this.$refs.file) {
                this.$refs.file.value = '';
            }
        },

        async upload() {
            this.uploading = true;
            this.error = null;
            this.notice = null;
            this.errors = {};

            const payload = new FormData();
            payload.append('type', this.form.type);
            payload.append('file', this.file);

            if (this.form.document_expires_at) {
                payload.append('document_expires_at', this.form.document_expires_at);
            }

            this.form.redactions.forEach((box, index) => {
                Object.entries(box).forEach(([key, value]) => {
                    payload.append(`redactions[${index}][${key}]`, value);
                });
            });

            try {
                await api.post('/driver/documents', payload);
                this.notice = 'Saved with the marked areas redacted.';
                this.reset();
                await this.load();
            } catch (e) {
                this.error = e.friendly;
                this.errors = e.errors || {};
            } finally {
                this.uploading = false;
            }
        },

        async remove(document) {
            if (!window.confirm(`Delete this ${document.type_label}?`)) return;

            try {
                await api.delete(`/driver/documents/${document.id}`);
                await this.load();
            } catch (e) {
                this.error = e.friendly;
            }
        },

        /**
         * The PDF sits behind a permission check. We do not put the token in the
         * URL — it would end up in browser history and server logs — so we fetch
         * it as a blob and open that instead.
         */
        async openPdf(document) {
            this.opening = document.id;
            this.error = null;

            try {
                const response = await api.get(`/documents/${document.id}/pdf`, { responseType: 'blob' });
                const url = URL.createObjectURL(response.data);

                window.open(url, '_blank', 'noopener');

                // Give the browser time to read it before revoking.
                setTimeout(() => URL.revokeObjectURL(url), 60000);
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.opening = null;
            }
        },
    },
};
</script>
