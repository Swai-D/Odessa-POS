# Backups na kurejesha (Postgres)

Database ni mahali pekee data ya maduka yote ilipo. Backup ambayo haijawahi kurejeshwa haijathibitishwa, kwa hiyo kuna sehemu mbili: kuchukua, na kujaribu kurejesha.

## 1. Kuchukua backup

Tumia njia mbili kwa pamoja:

1. **Railway.** Kwenye Postgres service, angalia kama mpango wako una Backups za volume na uziwashe kwa ratiba ya kila siku. Hizi ni za haraka kurejesha, lakini ziko ndani ya Railway hiyo hiyo.
2. **Nakala yako mwenyewe nje ya Railway.** Endesha `scripts/backup-postgres.sh` kutoka kompyuta au server unayoimiliki, angalau mara moja kwa siku:

   ```sh
   DATABASE_URL="postgresql://USER:PASS@HOST:PORT/DB" scripts/backup-postgres.sh ~/odessa-backups 30
   ```

   Tumia **public/TCP proxy URL** ya Postgres kwenye Railway (si ile ya `railway.internal`, haifikiki kutoka nje). Script inatengeneza faili `odessa-pos-TAREHE.sql.gz`, inafuta za zamani kuliko siku 30, na inashindwa kwa sauti ikiwa faili ni dogo kupita kiasi.
   - Inahitaji `pg_dump` toleo 16 au zaidi (sawa na server).
   - Weka faili kwenye hifadhi nyingine (Google Drive, S3, diski ya nje). Faili lina data ya maduka yote: lichukulie kama nenosiri.
   - Ratiba: `cron` kwenye server yako, au Task Scheduler ya Windows.

## 2. Jaribu kurejesha (fanya hivi angalau kila baada ya miezi mitatu)

Usirejeshe juu ya database ya production. Tengeneza database tupu ya majaribio (Postgres ya local au service ya muda ya Railway), kisha:

```sh
createdb odessa_restore_test
gunzip -c odessa-pos-20261011-020000.sql.gz | psql odessa_restore_test
```

Kisha thibitisha:

1. `psql odessa_restore_test -c "select count(*) from tenants"` inarudisha idadi ya maduka unayotegemea.
2. Elekeza app ya local kwenye database hiyo (`DB_DATABASE=odessa_restore_test`), endesha `php artisan migrate:status` (zote "Ran") na ingia kama Owner wa duka moja ukaone mauzo ya jana.
3. Futa database ya majaribio.

Andika tarehe ya jaribio la mwisho mahali unapoweza kuiona.

## 3. Maafa halisi

1. Acha kuandika: weka `php artisan down` au simamisha web service ili hakuna mauzo mapya yanayoingia kwenye database iliyoharibika.
2. Tengeneza database mpya, rejesha backup ya karibuni zaidi kama hapo juu.
3. Elekeza `DB_URL` ya app kwenye database mpya na u-redeploy.
4. Mauzo yaliyofanyika baada ya backup ya mwisho yamepotea kwenye database; waambie wamiliki wa maduka waangalie risiti walizotoa kwa kipindi hicho (mauzo yana namba za kufuatana, kwa hiyo pengo linaonekana).

## 4. Ufuatiliaji

- `GET /health` inarudisha `{"status":"ok","database":"up"}` (200) au 503 ikiwa database haipatikani. Haihitaji kuingia wala kuonyesha chochote kingine.
- Weka uptime monitor (kwa mfano UptimeRobot au Better Stack) ichunguze `https://DOMAIN-YAKO/health` kila dakika 1 hadi 5 na ikutumie SMS au barua pepe ikishindwa.
- `/up` ndiyo inayotumiwa na Railway kwa health check. Haikagui database kwa makusudi, ili Railway isiwashe tena app tena na tena database ikiwa chini.
