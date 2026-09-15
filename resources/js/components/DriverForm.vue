<template>
    <div>
        <!-- Personal -->
        <h6 class="text-uppercase text-muted small fw-bold mb-3">Personal details</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">First name</label>
                <input v-model="form.first_name" type="text" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Last name</label>
                <input v-model="form.last_name" type="text" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input v-model="form.phone" type="tel" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input v-model="form.email" type="email" class="form-control">
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
        </div>

        <!-- CDL -->
        <h6 class="text-uppercase text-muted small fw-bold mb-3">CDL and certificates</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">CDL class</label>
                <select v-model="form.cdl_class" class="form-select">
                    <option :value="null">—</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">CDL state</label>
                <select v-model="form.cdl_state" class="form-select">
                    <option :value="null">—</option>
                    <option v-for="state in states" :key="state" :value="state">{{ state }}</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">CDL issued</label>
                <input v-model="form.cdl_issued_at" type="date" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">CDL expires</label>
                <input v-model="form.cdl_expires_at" type="date" class="form-control">
            </div>
            <div class="col-md-8">
                <label class="form-label">Endorsements</label>
                <div class="d-flex flex-wrap gap-3">
                    <div v-for="item in endorsements" :key="item.value" class="form-check">
                        <input :id="`end-${item.value}`" v-model="form.endorsements" :value="item.value"
                               type="checkbox" class="form-check-input">
                        <label class="form-check-label" :for="`end-${item.value}`">{{ item.label }}</label>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Medical card expires</label>
                <input v-model="form.medical_card_expires_at" type="date" class="form-control">
            </div>
        </div>

        <!-- Experience -->
        <h6 class="text-uppercase text-muted small fw-bold mb-3">Experience and stability</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">Experience (years)</label>
                <input v-model.number="form.years_experience" type="number" step="0.5" min="0" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Driver type</label>
                <select v-model="form.driver_type" class="form-select">
                    <option v-for="item in driverTypes" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Preferred route</label>
                <select v-model="form.preferred_route" class="form-select">
                    <option :value="null">—</option>
                    <option v-for="item in routeTypes" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Jobs in last 3 years <span class="text-muted">(job hopping)</span></label>
                <input v-model.number="form.jobs_last_3_years" type="number" min="0" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Longest tenure (months)</label>
                <input v-model.number="form.longest_tenure_months" type="number" min="0" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Unemployment gap (months)</label>
                <input v-model.number="form.unemployment_gap_months" type="number" min="0" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Equipment experience</label>
                <div class="d-flex flex-wrap gap-3">
                    <div v-for="item in equipment" :key="item.value" class="form-check">
                        <input :id="`eq-${item.value}`" v-model="form.equipment_experience" :value="item.value"
                               type="checkbox" class="form-check-input">
                        <label class="form-check-label" :for="`eq-${item.value}`">{{ item.label }}</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Safety -->
        <h6 class="text-uppercase text-muted small fw-bold mb-3">Safety / MVR</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">Accidents (3 yrs)</label>
                <input v-model.number="form.accidents_3y" type="number" min="0" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Of those, preventable</label>
                <input v-model.number="form.preventable_accidents_3y" type="number" min="0" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Moving violations (3 yrs)</label>
                <input v-model.number="form.moving_violations_3y" type="number" min="0" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">SAP status</label>
                <select v-model="form.sap_status" class="form-select">
                    <option v-for="item in sapStatuses" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input id="dui" v-model="form.dui_ever" type="checkbox" class="form-check-input">
                    <label class="form-check-label" for="dui">Has a DUI</label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input id="susp" v-model="form.license_suspended_ever" type="checkbox" class="form-check-input">
                    <label class="form-check-label" for="susp">Licence has been suspended</label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input id="failed" v-model="form.failed_drug_test_ever" type="checkbox" class="form-check-input">
                    <label class="form-check-label" for="failed">Failed a drug test</label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input id="canpass" v-model="form.can_pass_drug_test" type="checkbox" class="form-check-input">
                    <label class="form-check-label" for="canpass">Can pass a drug test</label>
                </div>
            </div>
        </div>

        <!-- Work preferences -->
        <h6 class="text-uppercase text-muted small fw-bold mb-3">Work preferences</h6>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Work authorisation</label>
                <select v-model="form.work_authorization" class="form-select">
                    <option :value="null">—</option>
                    <option v-for="item in workAuth" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Available from</label>
                <input v-model="form.available_from" type="date" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Desired pay</label>
                <div class="input-group">
                    <input v-model.number="payAmount" type="number" step="0.01" min="0" class="form-control">
                    <select v-model="form.desired_pay_unit" class="form-select">
                        <option :value="null">—</option>
                        <option value="per_mile">$/mile</option>
                        <option value="per_week">$/week</option>
                        <option value="percentage">%</option>
                    </select>
                </div>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input id="relocate" v-model="form.willing_to_relocate" type="checkbox" class="form-check-input">
                    <label class="form-check-label" for="relocate">Willing to relocate</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea v-model="form.notes" rows="3" class="form-control"></textarea>
            </div>
        </div>
    </div>
</template>

<script>
import {
    US_STATES, ENDORSEMENTS, EQUIPMENT, ROUTE_TYPES, DRIVER_TYPES, WORK_AUTH, SAP_STATUSES,
} from '../constants';

export default {
    name: 'DriverForm',

    props: {
        modelValue: { type: Object, required: true },
    },

    emits: ['update:modelValue'],

    data() {
        return {
            states: US_STATES,
            endorsements: ENDORSEMENTS,
            equipment: EQUIPMENT,
            routeTypes: ROUTE_TYPES,
            driverTypes: DRIVER_TYPES,
            workAuth: WORK_AUTH,
            sapStatuses: SAP_STATUSES,
        };
    },

    computed: {
        form() {
            return this.modelValue;
        },

        /** Stored in cents on the server, shown in dollars in the form. */
        payAmount: {
            get() {
                return this.form.desired_pay_cents ? this.form.desired_pay_cents / 100 : null;
            },
            set(value) {
                this.form.desired_pay_cents = value ? Math.round(value * 100) : null;
            },
        },
    },
};
</script>
