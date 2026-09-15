# DriverHub — driver recruiting SPA

Indeed uslubidagi ikki tomonlama platforma: **driver** o'zi ro'yxatdan o'tib profil
to'ldiradi va vakansiyalarga ariza beradi, **carrier (kompaniya)** esa obuna to'lab
arizachilarni va butun driver bazasini o'z kriteriyalari bo'yicha avtomatik
ball qo'yilgan holda ko'radi.

- **Frontend:** Vue 3 SPA (vue-router + Pinia, Laravel Mix bilan build qilinadi)
- **Backend:** Laravel API (`routes/api.php`), Sanctum token auth
- **Saralash:** `config/driver_scoring.php` — kriteriyalar kodda emas, konfiguratsiyada

---

## Ishga tushirish

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# SQLite bilan tez boshlash uchun .env da:
#   DB_CONNECTION=sqlite
#   DB_DATABASE=/to'liq/yo'l/database/database.sqlite
touch database/database.sqlite

php artisan migrate:fresh --seed   # demo ma'lumot bilan
npm run dev                        # yoki: npm run watch
php artisan serve
```

Demo akkauntlar:

| Rol | Email | Parol |
|---|---|---|
| Kompaniya | `carrier@example.com` | `password` |
| Driver | `driver@example.com` | `password` |

Testlar: `./vendor/bin/phpunit`

---

## Kriteriyalarni o'zgartirish

Barcha saralash mantig'i **`config/driver_scoring.php`** faylida. Kodga tegmasdan
kriteriya qo'shish/olib tashlash mumkin.

### 1. Knockout — "bu bo'lsa darrov rad"

```php
'knockouts' => [
    ['key' => 'cdl_class', 'operator' => 'in', 'value' => ['A'], 'reason' => 'CDL Class A emas'],
    ['key' => 'years_experience', 'operator' => 'gte', 'value' => 1, 'reason' => 'Tajriba 1 yildan kam'],
],
```

Operatorlar: `gte`, `lte`, `gt`, `lt`, `eq`, `neq`, `in`, `not_in`, `is_true`,
`is_false`, `date_after_today`.

Knockout'ga tushgan driver ball olmaydi va ro'yxat oxirida `RAD` belgisi bilan turadi.

### 2. Ball beruvchi kriteriyalar

```php
[
    'key'    => 'accidents_3y',
    'label'  => 'Oxirgi 3 yildagi avariyalar',
    'group'  => 'safety',
    'type'   => 'bands',
    'weight' => 20,               // nisbiy og'irlik, yig'indisi 100 bo'lishi shart emas
    'bands'  => [
        ['max' => 0, 'points' => 100],
        ['max' => 1, 'points' => 55],
        ['max' => 2, 'points' => 20],
        ['points' => 0],
    ],
],
```

Tiplar:

| Tip | Nima uchun | Misol |
|---|---|---|
| `bands` | raqamli qiymat (tajriba, avariya soni) | `['min' => 5, 'points' => 100]` |
| `map` | matnli qiymat | `['us_citizen' => 100, '_default' => 50]` |
| `boolean` | ha/yo'q | `'true_points' => 0, 'false_points' => 100` |
| `set` | json massiv (endorsement, equipment) | `'valuable' => ['hazmat' => 40]` |

Yakuniy ball — barcha kriteriyalarning weighted average'i (0–100), keyin
`tiers` bo'yicha A/B/C/D darajaga ajratiladi.

### 3. Kompaniya va vakansiya darajasidagi override

- Kompaniya UI'dagi **Kriteriyalar** sahifasidan vaznlarni o'zgartiradi →
  `carriers.scoring_overrides` da saqlanadi.
- Har bir vakansiya o'z `requirements` json'i bilan knockout'ni yumshatishi mumkin
  (masalan shu ish uchun 1 yil tajriba yetarli).

Ustunlik tartibi: `config` → carrier override → job override.

---

## API

| Metod | Yo'l | Izoh |
|---|---|---|
| POST | `/api/auth/register` | `role`: `driver` yoki `carrier` |
| POST | `/api/auth/login` · `/api/auth/logout` · GET `/api/auth/me` | Sanctum token |
| POST | `/api/auth/send-code` · `/api/auth/verify-code` | telefon yoki email tasdiqlash |
| GET | `/api/scoring/criteria` | UI kriteriyalarni shu yerdan oladi |
| GET/PUT | `/api/driver/profile` | driver o'z profili + o'z-o'ziga baho |
| GET | `/api/driver/jobs` | vakansiya qidirish |
| POST | `/api/driver/jobs/{job}/apply` | ariza (tasdiqlangan akkaunt talab qilinadi) |
| GET | `/api/driver/applications` | arizalar tarixi (ball ko'rsatilmaydi) |
| GET | `/api/carrier/dashboard` · `/api/carrier/profile` | kompaniya |
| POST/DELETE | `/api/carrier/subscription` | obuna |
| CRUD | `/api/carrier/jobs` | vakansiyalar |
| GET | `/api/carrier/jobs/{job}/applicants` | **ball bo'yicha saralangan arizachilar** |
| GET/POST | `/api/carrier/drivers` | **driver bazasi** + qo'lda kiritish |
| PUT | `/api/carrier/scoring/overrides` | kriteriya vaznlari |

Obuna talab qiladigan yo'llar (`applicants`, `drivers`) obunasiz `402` qaytaradi,
tasdiqlanmagan akkaunt `403` oladi.

---

## Hali ulanmagan (keyingi bosqich)

Quyidagilar strukturasi tayyor, faqat tashqi xizmat ulanishi kerak:

- **SMS/email yuborish** — hozir tasdiqlash kodi log'ga yoziladi va `APP_DEBUG=true`
  bo'lganda javobda qaytariladi (`AuthController::issueCode`).
- **To'lov (Stripe)** — obuna hozir `CarrierController::subscribe` da qo'lda
  aktivlashtiriladi.
- **Resume/fayl yuklash** — `driver_profiles.resume_path` maydoni bor, upload yo'q.
- **CSV import** — `driver_profiles.source` da `csv` qiymati ko'zda tutilgan.
