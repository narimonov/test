<template>
    <div>
        <div class="hero p-5 mb-4">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="display-6 fw-bold mb-3">Driverni tuyg'u bilan emas, kriteriya bilan tanlang</h1>
                    <p class="lead mb-4 opacity-75">
                        Har bir driver sizning kriteriyalaringiz bo'yicha avtomatik ball oladi.
                        Knockout shartlariga tushganlari darrov ajratiladi, qolganlari
                        yuqori balldan pastga qarab tartiblanadi.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <router-link :to="{ name: 'register', query: { role: 'carrier' } }" class="btn btn-light btn-lg">
                            Kompaniya sifatida boshlash
                        </router-link>
                        <router-link :to="{ name: 'register', query: { role: 'driver' } }" class="btn btn-outline-light btn-lg">
                            Driver sifatida ro'yxatdan o'tish
                        </router-link>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Driver uchun</h5>
                        <p class="card-text text-muted">
                            Ro'yxatdan o'ting, profilingizni to'ldiring va ochiq vakansiyalarga
                            bir bosishda ariza bering. Profil bir marta to'ldiriladi.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Kompaniya uchun</h5>
                        <p class="card-text text-muted">
                            Vakansiya joylang, arizalarni ball bo'yicha saralangan holda ko'ring
                            va butun driver bazasidan kerakligini filtrlab toping.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Kriteriyalar sizniki</h5>
                        <p class="card-text text-muted">
                            Tajriba, avariya, job hopping, endorsement — har biriga o'z vaznini
                            berasiz. Kriteriya o'zgarsa, ballar avtomatik qayta hisoblanadi.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <router-link :to="{ name: 'login' }" class="btn btn-link">Akkauntingiz bormi? Kirish</router-link>
        </div>
    </div>
</template>

<script>
export default { name: 'LandingPage' };
</script>
