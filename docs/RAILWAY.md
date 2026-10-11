# Ku-host Odessa POS kwenye Railway

Mradi huu una-deploy kama Docker service ya Laravel 12/PHP 8.3. Railway itatengeneza Vite assets, itaendesha database migrations kabla ya deployment, na itathibitisha endpoint ya Laravel `/up`. App inahitaji PostgreSQL; usitumie SQLite production.

## 1. Weka project GitHub

Push project kwenye GitHub repository (private inapendekezwa). Usipush `.env`, credentials za production, au `APP_KEY`.

## 2. Tengeneza Railway services

1. Railway, tengeneza project na u-deploy GitHub repository kama service. Itatumia `railway.json` na `Dockerfile` zilizopo.
2. Ongeza PostgreSQL service ndani ya Railway project hiyo hiyo.
3. Kwenye Variables za app service, weka `DB_URL` kuwa Railway reference `${{Postgres.DATABASE_URL}}`. Badilisha `Postgres` iwe jina halisi la database service yako ikiwa ni tofauti.
4. Kwenye Networking ya app service, tengeneza public domain. Hostname yake ndiyo utaweka kwenye `APP_URL` na `APP_DOMAIN` hapa chini.

## 3. Weka environment variables

Ongeza variables hizi kwenye app service. Tengeneza key mpya kwa kuendesha `php artisan key:generate --show` kwenye kompyuta yako, kisha weka matokeo kama `APP_KEY` kwenye Railway.

| Variable | Thamani |
| --- | --- |
| `APP_NAME` | `Odessa POS` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Key mpya; iwe siri na usiibadilishe baada ya kuanza kutumia app |
| `APP_URL` | `https://your-domain.example` |
| `APP_DOMAIN` | `your-domain.example` (hostname pekee, bila scheme/path) |
| `DB_URL` | `${{Postgres.DATABASE_URL}}` |
| `LOG_CHANNEL` | `stderr` |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `FILESYSTEM_DISK` | `local` mwanzoni; soma sehemu ya uploads |
| `SUPER_ADMIN_EMAIL` | Email ya platform administrator wa kwanza |
| `SUPER_ADMIN_PASSWORD` | Password imara na ya kipekee |
| `DEMO_OWNER_EMAIL` | Email ya mmiliki wa demo shop |
| `DEMO_OWNER_PASSWORD` | Password imara na ya kipekee |

`APP_KEY` ikibadilishwa baada ya watu kuingia, cookies na sessions zilizofungwa kwa encryption zitavunjika. Production usiweke `APP_DEBUG=true`.

## 4. Deploy na initialize

Deploy app. Pre-deploy command itaendesha `php artisan migrate --force` kwenye Railway PostgreSQL. Baada ya deployment ya kwanza kufanikiwa, fungua shell ya app service (au tumia Railway CLI) na endesha hii mara moja kuunda super-admin na demo shop:

```sh
php artisan db:seed --force
```

Seeder inasoma `SUPER_ADMIN_*` na `DEMO_OWNER_*`; ziweke kabla ya kuendesha command. Inaunda tenant ya demo yenye slug `demo`. Seeder si sehemu ya deployment za kawaida. Ingia kama super-admin ili kusimamia tenants.

## 5. Weka domains za tenants

Kila shop inaweza kutumia hostname iliyohifadhiwa kwenye tenant record. Kwa subdomains kama `shop.example.com`, weka `APP_DOMAIN=example.com`, kisha sanidi DNS na wildcard domain/routing inayolingana kwenye Railway. Domain tupu `example.com` imetengwa kwa platform administration. Railway domain ya `*.up.railway.app` haiwezi kuchukua nafasi ya wildcard domain unayoimiliki. `X-Tenant` na session override zinapatikana local tu, si production.

Weka `APP_URL` kuwa URL rasmi ya app. Ukibadilisha domain variables, redeploy ili Laravel ijenge upya configuration cache.

## Uploads zisizopotea

Filesystem ya container ya Railway ni ya muda; picha na files zilizohifadhiwa kwenye local disk zinaweza kupotea wakati wa redeploy. Kabla ya kutumia uploads production, ama ambatisha Railway Volume kwenye `/var/www/html/storage/app` (bakiza replica moja tu ya web service), au sanidi object storage ya kudumu inayotumia S3 API. S3 disk ipo kwenye Laravel config, lakini project hii bado haina Flysystem S3 adapter kwenye Composer. Ongeza dependency hiyo kabla ya kuchagua `FILESYSTEM_DISK=s3`.

## Queue na uendeshaji

`QUEUE_CONNECTION=database` huhifadhi queued jobs kwenye PostgreSQL. App ikianza kutuma background jobs, ongeza Railway service ya pili kutoka repository na Dockerfile hiyohiyo, mpe app/database variables zilezile, kisha weka start command `php artisan queue:work --tries=3 --timeout=90`. Web service ibaki na Docker start command yake ya kawaida. Usiendeshe `db:seed` kila deploy.

Fanya PostgreSQL backups kivyake na ujaribu kurejesha backup. Angalia logs za Railway; container inaandika logs kwenda stderr. Health check ya `/up` inathibitisha Laravel inajibu, lakini haithibitishi business workflows, email provider, custom domains, au uploads persistence vimesanidiwa.