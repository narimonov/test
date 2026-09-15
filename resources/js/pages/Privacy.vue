<template>
    <div class="row justify-content-center">
        <div class="col-xl-9">
            <div class="mb-4">
                <h4 class="page-title">Privacy Notice</h4>
                <p class="page-lede">
                    Version {{ policy.version }} · effective {{ policy.effective_date }}
                </p>
            </div>

            <AlertBox :message="error" :errors="errors" />
            <AlertBox :message="notice" variant="success" />

            <div class="card mb-4">
                <div class="card-body p-4 privacy-body">
                    <p>
                        DriverHub connects CDL drivers with motor carriers. Recruiting in trucking is
                        regulated, so this notice sets out exactly what we collect, why each item is
                        needed, and which law makes it necessary. Where a rule requires your separate
                        permission, we ask for it separately rather than burying it here.
                    </p>

                    <h6 class="label-mono mt-4">1. What we collect and why</h6>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 22%">Data</th>
                                    <th style="width: 40%">Why we need it</th>
                                    <th>Legal basis / constraint</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-semibold">Mobile phone number</td>
                                    <td>
                                        To send the one-time code that confirms the account, and so a
                                        carrier can reach you about a job you applied to.
                                    </td>
                                    <td>
                                        The <strong>TCPA</strong> requires your prior express consent before
                                        anyone sends you an automated text. Verification codes are
                                        transactional, but consent is still required and we record it. Our
                                        sending numbers are registered under <strong>A2P 10DLC</strong>;
                                        since February 2025 US carriers block unregistered traffic.
                                        You can reply STOP at any time.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">CDL number, class, endorsements, issue and expiry dates</td>
                                    <td>
                                        A carrier is legally required to confirm that the person driving
                                        holds a valid commercial licence for the equipment. We also use the
                                        issue date to sanity-check claimed experience.
                                    </td>
                                    <td>
                                        A driver licence number is personal information under the
                                        <strong>Driver's Privacy Protection Act (18 U.S.C. § 2721)</strong>,
                                        which permits disclosure only for listed purposes — verifying a CDL
                                        holder's information for an employer is one of them. We do not sell
                                        or repurpose it.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Driving record (MVR)</td>
                                    <td>
                                        Carriers must review the driving record of anyone they hire.
                                        We only order one when a carrier is genuinely considering you.
                                    </td>
                                    <td>
                                        Both the <strong>FCRA</strong> and the <strong>DPPA</strong> apply.
                                        You get a standalone written disclosure and we must hold your
                                        written authorisation before a record is pulled. If something in
                                        that record counts against you, you are entitled to a copy and a
                                        chance to dispute it before a final decision.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">CDL and medical card images</td>
                                    <td>
                                        To let a carrier confirm your qualifications without you emailing
                                        documents around.
                                    </td>
                                    <td>
                                        You choose what to mask before uploading, and masked areas are
                                        destroyed rather than blurred. Only carriers you applied to can
                                        open the result. The original photograph is never shared.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Accidents, violations and employment history</td>
                                    <td>
                                        These are what carriers screen on, and what your score is built from.
                                    </td>
                                    <td>
                                        Self-reported by you. Carriers verify them separately through their
                                        own regulated checks; nothing here substitutes for that.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Company MC / USDOT number</td>
                                    <td>
                                        To confirm a company account belongs to a real, operating carrier.
                                    </td>
                                    <td>
                                        Checked against the public <strong>FMCSA</strong> register. The
                                        confirmation code is sent to the contact FMCSA holds for that
                                        carrier, so an outsider cannot open an account in a company's name.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h6 class="label-mono mt-4">2. Who sees your information</h6>
                    <ul>
                        <li>Carriers with an active subscription can search driver profiles. You control
                            this with the "let companies find me" switch on your profile.</li>
                        <li>A carrier you applied to additionally sees your redacted documents.</li>
                        <li>Our staff see what is needed to verify a review or work a support ticket.</li>
                        <li>Service providers who process data for us — payment gateways, the SMS and
                            email sender, and (once enabled) the MVR provider. They may use it only to
                            perform that service.</li>
                        <li>We do not sell personal information, and we do not share it for
                            cross-context behavioural advertising.</li>
                    </ul>

                    <h6 class="label-mono mt-4">3. How long we keep it</h6>
                    <p>
                        Profiles stay until you delete them. Where a carrier hires you, that carrier has
                        its own retention duties for the driver qualification file under
                        <strong>49 CFR Part 391</strong>, and its copy is governed by its policy, not
                        ours. Verification codes are discarded after 15 minutes. Consent records are kept
                        for as long as we may need to evidence them.
                    </p>

                    <h6 class="label-mono mt-4">4. Your rights</h6>
                    <p>
                        Depending on where you live — California's CCPA/CPRA and comparable state laws —
                        you can ask for a copy of your data, ask us to correct or delete it, and opt out
                        of sale or sharing. We do not sell data, so opting out changes nothing, but the
                        right stands. Exercising a right never costs you service. Write to
                        <a :href="`mailto:${policy.contact_email}`">{{ policy.contact_email }}</a>.
                    </p>

                    <h6 class="label-mono mt-4">5. Security</h6>
                    <p>
                        Documents are stored outside the public web root and served only after a
                        permission check. Passwords are hashed. Access to verification contacts and raw
                        FMCSA records is restricted to the server.
                    </p>

                    <h6 class="label-mono mt-4">6. Changes</h6>
                    <p>
                        When this notice changes materially we raise its version and ask you to accept it
                        again. Your acceptance is recorded against the version you actually saw.
                    </p>

                    <p class="text-muted small mt-4 mb-0">
                        This notice explains our own practices. It is not legal advice, and a carrier
                        using the platform remains responsible for its own FCRA, DPPA and FMCSA
                        obligations.
                    </p>
                </div>
            </div>

            <div v-if="auth.isAuthenticated" class="card">
                <div class="card-body p-4">
                    <h6 class="mb-3">Your choices</h6>

                    <div class="form-check mb-3">
                        <input id="accept" v-model="form.accept" type="checkbox" class="form-check-input">
                        <label class="form-check-label" for="accept">
                            I have read and accept this Privacy Notice (version {{ policy.version }}).
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input id="sms" v-model="form.sms_consent" type="checkbox" class="form-check-input">
                        <label class="form-check-label" for="sms">
                            I agree to receive text messages at my number, including verification codes and
                            messages about jobs I applied to. Message and data rates may apply; reply STOP
                            to opt out.
                            <span class="d-block text-muted small">Required by the TCPA before we can text you.</span>
                        </label>
                    </div>

                    <div v-if="auth.isDriver" class="form-check mb-4">
                        <input id="mvr" v-model="form.mvr_consent" type="checkbox" class="form-check-input">
                        <label class="form-check-label" for="mvr">
                            I authorise DriverHub and carriers I apply to to obtain my motor vehicle record
                            for employment purposes.
                            <span class="d-block text-muted small">
                                Written authorisation required by the FCRA and the DPPA. Optional — you can
                                use the platform without it, but most carriers will ask before hiring.
                            </span>
                        </label>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <button class="btn btn-primary" :disabled="!form.accept || saving" @click="accept">
                            {{ saving ? 'Saving…' : 'Save my choices' }}
                        </button>
                        <span v-if="auth.hasPrivacyConsent" class="label-mono text-success">
                            Accepted version {{ auth.user.privacy_version }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../api';
import AlertBox from '../components/AlertBox.vue';
import { useAuthStore } from '../stores/auth';

export default {
    name: 'PrivacyPage',

    components: { AlertBox },

    setup() {
        return { auth: useAuthStore() };
    },

    data() {
        return {
            policy: { version: '', effective_date: '', contact_email: '' },
            form: { accept: false, sms_consent: false, mvr_consent: false },
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
                const { data } = await api.get('/privacy');
                this.policy = data;

                const user = this.auth.user;

                if (user) {
                    this.form.accept = !!user.has_current_privacy_consent;
                    this.form.sms_consent = !!user.sms_consent_at;
                    this.form.mvr_consent = !!user.mvr_consent_at;
                }
            } catch (e) {
                this.error = e.friendly;
            }
        },

        async accept() {
            this.saving = true;
            this.error = null;
            this.notice = null;
            this.errors = {};

            try {
                const { data } = await api.post('/privacy/accept', {
                    version: this.policy.version,
                    sms_consent: this.form.sms_consent,
                    mvr_consent: this.form.mvr_consent,
                });

                this.notice = data.message;
                await this.auth.refresh();
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

<style scoped>
.privacy-body h6 { margin-top: 1.5rem; }
.privacy-body p { line-height: 1.65; }
</style>
