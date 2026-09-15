# DriverHub — driver recruiting SPA

Indeed uslubidagi ikki tomonlama platforma: **driver** o'zi ro'yxatdan o'tib profil
to'ldiradi va vakansiyalarga ariza beradi, **carrier (kompaniya)** esa obuna to'lab
arizachilarni va butun driver bazasini o'z kriteriyalari bo'yicha avtomatik
ball qo'yilgan holda ko'radi.

- **Frontend:** Vue 3 SPA (vue-router + Pinia, Laravel Mix bilan build qilinadi)
- **Backend:** Laravel API (`routes/api.php`), Sanctum token auth
- **Saralash:** `config/driver_scoring.php` — kriteriyalar kodda emas, konfiguratsiyada
- **Kompaniya tekshiruvi:** FMCSA (MC/DOT) + rasmiy kontaktga yuboriladigan kod
- **Hujjatlar:** CDL / medical card rasmi berkitilib, watermark bilan PDF qilinadi

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
| Admin | `admin@example.com` | `password` |
| Kompaniya | `carrier@example.com` | `password` |
| Driver | `driver@example.com` | `password` |

Testlar: `./vendor/bin/phpunit`

---

## Kompaniya ro'yxatdan o'tishi (FMCSA)

Kompaniya MC yoki DOT raqamini kiritadi. Tizim FMCSA bazasidan tekshiradi:

1. **Topilmasa** yoki **allowed-to-operate emas** bo'lsa — ro'yxatdan o'tkazilmaydi
   va hech qanday yozuv saqlanmaydi.
2. Topilsa — kompaniya nomi FMCSA'dagi rasmiy nomdan olinadi (foydalanuvchi
   yozganidan emas).
3. Tasdiqlash kodi **FMCSA'da ro'yxatdan o'tgan telefon yoki emailga** yuboriladi.
   Foydalanuvchiga faqat maskalangan ko'rinishi ko'rsatiladi (`****4567`).
4. Kod tasdiqlangunicha kompaniya ilovadan foydalana olmaydi
   (`carrier.fmcsa` middleware, `403 fmcsa_verification_required`).
5. Keyinchalik authority to'xtatilsa kirish yopiladi (`403 fmcsa_not_active`).
   Admin `POST /api/admin/carriers/{carrier}/recheck` bilan qayta tekshiradi.

Sozlash (`.env`):

```env
FMCSA_DRIVER=fake              # lokal ishlab chiqish uchun, tashqi so'rov yo'q
# FMCSA_DRIVER=qcmobile        # production
# FMCSA_WEB_KEY=...            # QCDevsite'da bepul olinadi
# FMCSA_CENSUS_DATASET=...     # telefon/email uchun (QCMobile ularni bermaydi)
# FMCSA_REQUIRE_ACTIVE=true
```

`FMCSA_DRIVER=fake` da qoida: DOT `0` bilan boshlansa — topilmadi,
`9` bilan boshlansa — nofaol, qolgani — faol.

---

## Hujjatlar: berkitish va watermark

Driver CDL / medical card'ni rasmga oladi va maxfiy joylarni sichqoncha bilan
belgilaydi. Tizim:

1. EXIF bo'yicha buradi va kichraytiradi
2. Belgilangan joylarni **qaytarib bo'lmaydigan** qilib berkitadi
3. Butun rasm bo'ylab qiya watermark yozadi (rasmning o'ziga, PDF ustiga emas)
4. PDF qilib private diskda saqlaydi

Asl rasm hech qachon tarqatilmaydi. PDF'ni faqat driverning o'zi, unga ariza
kelgan kompaniya va admin ko'ra oladi (`GET /api/documents/{id}/pdf`).

```env
DOCUMENTS_WATERMARK=recruiting   # production'da almashtiriladi
DOCUMENTS_REDACTION=pixelate     # yoki blackout
DOCUMENTS_DISK=local
```

> Oddiy blur ishlatilmaydi — blur qilingan matnni tiklash mumkin. `pixelate`
> sohani juda kichik o'lchamga siqib qaytadan cho'zadi, ya'ni asl piksellar
> butunlay yo'qoladi.

---

## Ish munosabati qoidalari

- **CDL sanasi va tajriba**: ko'rsatilgan tajriba CDL olingan sanadan oshib
  ketsa profil saqlanmaydi (`ExperienceMatchesCdlIssueDate`, 0.5 yil tolerans).
- **Eksklyuzivlik**: bitta kompaniya driverni `hired` qilsa, driver o'sha
  kompaniyaga biriktiriladi, boshqa ochiq arizalari yopiladi va boshqa
  kompaniya uni ishga ola olmaydi.
- **Baholar**: driver kompaniyaga, kompaniya driverga baho qoldiradi — faqat
  ariza `hired` yoki `rejected` bo'lgandan keyin, har hamkorlik uchun bir marta.
- **Blacklist**: 3 ta qoniqarsiz baho (1–2 yulduz) to'plagan tomon — driver
  bo'ladimi, kompaniya bo'ladimi — blacklist'ga tushadi.
- **Apelyatsiya**: blacklist'dagi tomon apelyatsiya beradi, admin qaror
  chiqaradi. Qabul qilinsa eski baholar qayta hisoblanmaydi (aks holda darrov
  qaytib tushardi), lekin baholarning o'zi ko'rinib turaveradi.

```env
REPUTATION_NEGATIVE_AT=2         # shu balldan past = qoniqarsiz
REPUTATION_BLACKLIST_AFTER=3     # nechta qoniqarsizdan keyin blacklist
```

---

## Admin

`role=admin` foydalanuvchi: platforma statistikasi, foydalanuvchilarni
bloklash/blokdan chiqarish (bloklanganda barcha tokenlari ham bekor qilinadi),
apelyatsiyalarni ko'rib chiqish, qo'lda blacklist qo'yish/olish, nomaqbul
review'ni olib tashlash, kompaniyani FMCSA bo'yicha qayta tekshirish.

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
| GET/POST/DELETE | `/api/driver/documents` | CDL / medical card |
| GET | `/api/documents/{id}/pdf` | berkitilgan PDF (huquq tekshiriladi) |
| GET | `/api/driver/jobs` | vakansiya qidirish |
| POST | `/api/driver/jobs/{job}/apply` | ariza (tasdiqlangan akkaunt talab qilinadi) |
| GET | `/api/driver/applications` | arizalar tarixi (ball ko'rsatilmaydi) |
| GET | `/api/carrier/dashboard` · `/api/carrier/profile` | kompaniya |
| POST/DELETE | `/api/carrier/subscription` | obuna |
| CRUD | `/api/carrier/jobs` | vakansiyalar |
| GET | `/api/carrier/jobs/{job}/applicants` | **ball bo'yicha saralangan arizachilar** |
| GET/POST | `/api/carrier/drivers` | **driver bazasi** + qo'lda kiritish |
| PUT | `/api/carrier/scoring/overrides` | kriteriya vaznlari |
| GET | `/api/auth/carrier/channels` | FMCSA kontaktlari (maskalangan) |
| POST | `/api/auth/carrier/send-code` · `/verify-code` | FMCSA tasdiqlash |
| GET/POST | `/api/reviews` | ikki tomonlama baho |
| GET/POST | `/api/appeals` | blacklist apelyatsiyasi |
| GET | `/api/admin/overview` · `/users` · `/appeals` | admin paneli |

Obuna talab qiladigan yo'llar (`applicants`, `drivers`) obunasiz `402` qaytaradi,
tasdiqlanmagan akkaunt `403` oladi.

---

## Hali ulanmagan (keyingi bosqich)

Quyidagilar strukturasi tayyor, faqat tashqi xizmat ulanishi kerak:

- **SMS/email yuborish** — hozir tasdiqlash kodi log'ga yoziladi va `APP_DEBUG=true`
  bo'lganda javobda qaytariladi (`AuthController::issueCode`,
  `CarrierVerificationService::issueCode`). Twilio/SES ulanganda faqat shu ikki
  metod o'zgaradi.
- **FMCSA production kaliti** — `FMCSA_WEB_KEY` va census dataset id.
- **To'lov (Stripe)** — obuna hozir `CarrierController::subscribe` da qo'lda
  aktivlashtiriladi.
- **MVR** — provayder tanlanishi kerak (SambaSafety'da sandbox bor). Oxirgi
  30 kun ichidagi MVR qayta ishlatilishi, state bo'yicha narxlar va yangi MVR
  buyurtma qilish shu integratsiyaga bog'liq.
- **Aviabilet** — Expedia Rapid API faqat mehmonxona beradi, reys bermaydi.
  Duffel yoki Amadeus Self-Service kerak bo'ladi.
- **Kompaniya reputatsiyasi** — MC/DOT bo'yicha internetdagi sharhlarni yig'ish
  (Google Places API ishlaydi; Indeed/Glassdoor'da ochiq API yo'q).
- **Onboarding qadamlari** — company driver uchun qadamlar ro'yxati.
- **CSV import** — `driver_profiles.source` da `csv` qiymati ko'zda tutilgan.
