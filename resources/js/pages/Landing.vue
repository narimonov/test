<template>
    <div>
        <div class="hero p-5 mb-4">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="label-mono mb-2" style="color: rgba(255,255,255,.6)">CDL driver recruiting</div>
                    <h1 class="display-6 fw-bold mb-3">Hire on criteria, not on a hunch.</h1>
                    <p class="lead mb-4" style="color: rgba(255,255,255,.78)">
                        Every driver is scored against the rules you set. Knockouts drop out on their own;
                        the rest arrive sorted, strongest first. Companies are checked against FMCSA before
                        they can post a thing.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <router-link :to="{ name: 'register', query: { role: 'carrier' } }" class="btn btn-primary btn-lg">
                            Start as a carrier
                        </router-link>
                        <router-link :to="{ name: 'register', query: { role: 'driver' } }" class="btn btn-outline-light btn-lg">
                            Sign up as a driver
                        </router-link>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="label-mono mb-2">For drivers</div>
                        <h5 class="card-title">Fill it in once</h5>
                        <p class="card-text text-muted">
                            Build your profile, mask what you do not want shared on your CDL and medical
                            card, then apply to open jobs in one click.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="label-mono mb-2">For carriers</div>
                        <h5 class="card-title">See the good ones first</h5>
                        <p class="card-text text-muted">
                            Post a job with real requirements. Applicants come back ranked, and matching
                            drivers who have not applied yet come back too.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="label-mono mb-2">Verified both ways</div>
                        <h5 class="card-title">No anonymous accounts</h5>
                        <p class="card-text text-muted">
                            Carriers verify through FMCSA. Reviews need proof of a real working
                            relationship and are checked before they go live.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <router-link :to="{ name: 'login' }" class="btn btn-link">Already have an account? Sign in</router-link>
        </div>
    </div>
</template>

<script>
export default { name: 'LandingPage' };
</script>
