<template>
    <div>
        <div class="mb-4">
            <h4 class="page-title">Driver travel</h4>
            <p class="page-lede">
                Get a hired driver to orientation. Book here, or record a flight you bought
                elsewhere — either way the travel step of their onboarding closes.
            </p>
        </div>

        <AlertBox :message="error" :errors="errors" />
        <AlertBox :message="notice" variant="success" />

        <div class="card mb-4">
            <div class="card-body">
                <h6 class="mb-3">Find a flight</h6>

                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Driver</label>
                        <select v-model="form.driver_profile_id" class="form-select">
                            <option :value="null">Pick a hired driver</option>
                            <option v-for="driver in hired" :key="driver.id" :value="driver.id">
                                {{ driver.full_name }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">From</label>
                        <input v-model="form.origin" type="text" maxlength="3" class="form-control text-uppercase"
                               placeholder="MDW">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">To</label>
                        <input v-model="form.destination" type="text" maxlength="3" class="form-control text-uppercase"
                               placeholder="DFW">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Departing</label>
                        <input v-model="form.depart_on" type="date" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" :disabled="searching || !canSearch" @click="search">
                            {{ searching ? 'Searching…' : 'Search' }}
                        </button>
                    </div>
                </div>
                <div class="form-text mt-2">Airport codes, three letters.</div>
            </div>
        </div>

        <div v-if="offers.length" class="card mb-4">
            <div class="card-header py-2">{{ offers.length }} options</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Flight</th>
                            <th>Departs</th>
                            <th>Arrives</th>
                            <th>Stops</th>
                            <th>Price</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="offer in offers" :key="offer.id">
                            <td>
                                <div class="fw-semibold">{{ offer.airline }}</div>
                                <div class="label-mono">{{ offer.flight_number }}</div>
                            </td>
                            <td>{{ time(offer.departs_at) }}</td>
                            <td>{{ time(offer.arrives_at) }}</td>
                            <td>{{ offer.stops === 0 ? 'Non-stop' : offer.stops }}</td>
                            <td class="fw-semibold">${{ (offer.amount_cents / 100).toFixed(2) }}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-primary" :disabled="booking === offer.id"
                                        @click="book(offer)">
                                    {{ booking === offer.id ? 'Booking…' : 'Book' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-1">Booked somewhere else?</h6>
                        <p class="text-muted small mb-3">
                            Record it so onboarding knows the driver can get there.
                        </p>
                        <form @submit.prevent="record">
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label small mb-1">Driver</label>
                                    <select v-model="external.driver_profile_id" class="form-select form-select-sm" required>
                                        <option :value="null">Pick a hired driver</option>
                                        <option v-for="driver in hired" :key="driver.id" :value="driver.id">
                                            {{ driver.full_name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Airline</label>
                                    <input v-model="external.airline" type="text" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Flight</label>
                                    <input v-model="external.flight_number" type="text" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Confirmation</label>
                                    <input v-model="external.booking_reference" type="text" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">Departing</label>
                                    <input v-model="external.depart_on" type="date" class="form-control form-control-sm">
                                </div>
                            </div>
                            <button class="btn btn-outline-secondary btn-sm mt-3" :disabled="recording">
                                {{ recording ? 'Saving…' : 'Record booking' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header py-2">Booked travel</div>
                    <div v-if="!bookings.length" class="empty-state py-4">Nothing booked yet.</div>
                    <div v-else class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Driver</th>
                                    <th>Route</th>
                                    <th>Flight</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="booking in bookings" :key="booking.id">
                                    <td>
                                        {{ booking.driver_profile
                                            ? `${booking.driver_profile.first_name} ${booking.driver_profile.last_name}`
                                            : '—' }}
                                    </td>
                                    <td class="label-mono">
                                        {{ booking.origin || '—' }} → {{ booking.destination || '—' }}
                                        <div>{{ booking.depart_on || '' }}</div>
                                    </td>
                                    <td>
                                        {{ booking.carrier_name }} {{ booking.flight_number }}
                                        <div class="label-mono">{{ booking.booking_reference }}</div>
                                    </td>
                                    <td>
                                        <span class="badge" :class="booking.status === 'booked' ? 'bg-success' : 'bg-secondary'">
                                            {{ booking.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../../api';
import AlertBox from '../../components/AlertBox.vue';

export default {
    name: 'CarrierTravelPage',

    components: { AlertBox },

    data() {
        return {
            hired: [],
            bookings: [],
            offers: [],
            form: { driver_profile_id: null, origin: '', destination: '', depart_on: '' },
            external: {
                driver_profile_id: null, airline: '', flight_number: '',
                booking_reference: '', depart_on: '',
            },
            searching: false,
            booking: null,
            recording: false,
            error: null,
            notice: null,
            errors: {},
        };
    },

    computed: {
        canSearch() {
            return this.form.driver_profile_id
                && this.form.origin.length === 3
                && this.form.destination.length === 3
                && this.form.depart_on;
        },
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            try {
                const [bookings, drivers] = await Promise.all([
                    api.get('/carrier/travel'),
                    api.get('/carrier/drivers?include_hired=1&per_page=100'),
                ]);

                this.bookings = bookings.data.bookings;
                // Travel is only for drivers this carrier actually hired.
                this.hired = drivers.data.data
                    .map((row) => row.driver)
                    .filter((driver) => driver.is_hired);
            } catch (e) {
                this.error = e.friendly;
            }
        },

        async search() {
            this.searching = true;
            this.error = null;
            this.offers = [];

            try {
                const { data } = await api.post('/carrier/travel/search', {
                    origin: this.form.origin.toUpperCase(),
                    destination: this.form.destination.toUpperCase(),
                    depart_on: this.form.depart_on,
                });

                this.offers = data.offers;

                if (!this.offers.length) {
                    this.error = 'No flights came back for that route and date.';
                }
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.searching = false;
            }
        },

        async book(offer) {
            this.booking = offer.id;
            this.error = null;
            this.notice = null;

            try {
                const { data } = await api.post(`/carrier/drivers/${this.form.driver_profile_id}/travel`, {
                    offer_id: offer.id,
                    origin: offer.origin,
                    destination: offer.destination,
                    depart_on: offer.departs_at.slice(0, 10),
                    airline: offer.airline,
                    flight_number: offer.flight_number,
                    departs_at: offer.departs_at,
                    arrives_at: offer.arrives_at,
                    amount_cents: offer.amount_cents,
                });

                this.notice = data.message;
                this.offers = [];
                await this.load();
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.booking = null;
            }
        },

        async record() {
            this.recording = true;
            this.error = null;
            this.notice = null;
            this.errors = {};

            const { driver_profile_id: driverId, ...payload } = this.external;

            try {
                const { data } = await api.post(`/carrier/drivers/${driverId}/travel/record`, payload);
                this.notice = data.message;
                this.external = {
                    driver_profile_id: null, airline: '', flight_number: '',
                    booking_reference: '', depart_on: '',
                };
                await this.load();
            } catch (e) {
                this.error = e.friendly;
                this.errors = e.errors || {};
            } finally {
                this.recording = false;
            }
        },

        time(value) {
            return value ? new Date(value).toLocaleString('en-US', {
                month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
            }) : '—';
        },
    },
};
</script>
