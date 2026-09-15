<template>
    <div class="row g-3">
        <div class="col-md-6">
            <h6 class="text-uppercase text-muted small fw-bold">CDL va tajriba</h6>
            <dl class="row mb-0 small">
                <dt class="col-6 fw-normal text-muted">CDL klass</dt>
                <dd class="col-6">{{ driver.cdl_class || '—' }} ({{ driver.cdl_state || '—' }})</dd>

                <dt class="col-6 fw-normal text-muted">CDL muddati</dt>
                <dd class="col-6">{{ date(driver.cdl_expires_at) }}</dd>

                <dt class="col-6 fw-normal text-muted">Tajriba</dt>
                <dd class="col-6">{{ driver.years_experience }} yil</dd>

                <dt class="col-6 fw-normal text-muted">Driver turi</dt>
                <dd class="col-6">{{ label(driverTypes, driver.driver_type) }}</dd>

                <dt class="col-6 fw-normal text-muted">Route</dt>
                <dd class="col-6">{{ label(routeTypes, driver.preferred_route) }}</dd>

                <dt class="col-6 fw-normal text-muted">Endorsement</dt>
                <dd class="col-6">{{ list(driver.endorsements) }}</dd>

                <dt class="col-6 fw-normal text-muted">Equipment</dt>
                <dd class="col-6">{{ list(driver.equipment_experience) }}</dd>
            </dl>
        </div>

        <div class="col-md-6">
            <h6 class="text-uppercase text-muted small fw-bold">Safety va barqarorlik</h6>
            <dl class="row mb-0 small">
                <dt class="col-6 fw-normal text-muted">Avariyalar (3y)</dt>
                <dd class="col-6">{{ driver.accidents_3y }} ({{ driver.preventable_accidents_3y }} aybdor)</dd>

                <dt class="col-6 fw-normal text-muted">Violations (3y)</dt>
                <dd class="col-6">{{ driver.moving_violations_3y }}</dd>

                <dt class="col-6 fw-normal text-muted">DUI</dt>
                <dd class="col-6">{{ driver.dui_ever ? 'Ha' : 'Yo\'q' }}</dd>

                <dt class="col-6 fw-normal text-muted">SAP</dt>
                <dd class="col-6">{{ label(sapStatuses, driver.sap_status) }}</dd>

                <dt class="col-6 fw-normal text-muted">3 yilda ish soni</dt>
                <dd class="col-6">{{ driver.jobs_last_3_years }}</dd>

                <dt class="col-6 fw-normal text-muted">Eng uzun staj</dt>
                <dd class="col-6">{{ driver.longest_tenure_months }} oy</dd>

                <dt class="col-6 fw-normal text-muted">Ishlash huquqi</dt>
                <dd class="col-6">{{ label(workAuth, driver.work_authorization) }}</dd>
            </dl>
        </div>

        <div v-if="driver.notes" class="col-12">
            <h6 class="text-uppercase text-muted small fw-bold">Izoh</h6>
            <p class="small mb-0" style="white-space: pre-line">{{ driver.notes }}</p>
        </div>
    </div>
</template>

<script>
import { DRIVER_TYPES, ROUTE_TYPES, SAP_STATUSES, WORK_AUTH, ENDORSEMENTS, EQUIPMENT, labelFor } from '../constants';

export default {
    name: 'DriverSummary',

    props: {
        driver: { type: Object, required: true },
    },

    data() {
        return {
            driverTypes: DRIVER_TYPES,
            routeTypes: ROUTE_TYPES,
            sapStatuses: SAP_STATUSES,
            workAuth: WORK_AUTH,
        };
    },

    methods: {
        label(list, value) {
            return labelFor(list, value);
        },

        list(values) {
            if (!values || !values.length) return '—';

            return values
                .map((value) => labelFor([...ENDORSEMENTS, ...EQUIPMENT], value))
                .join(', ');
        },

        date(value) {
            return value ? new Date(value).toLocaleDateString('uz-UZ') : '—';
        },
    },
};
</script>
