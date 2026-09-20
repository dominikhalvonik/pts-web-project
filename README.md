# TestLab Shop
## Výučbová aplikácia pre predmet Pokročilé testovanie softvéru

Verzia 1.0.0 · PHP + Vue 3 + MariaDB · rozhranie v slovenčine

**Účel:** spoločný objekt GUI, API, databázového, akceptačného a neskôr mobilného testovania. Ide o simulovaný obchod, nie o hotový produkčný e-shop. Nie sú implementované skutočné platby, odosielanie e-mailov, registrácia ani administrácia predaja.

**Rozsah overenia pred odovzdaním:** 47 testov PHP výpočtov/validácie a 26 offline kontrol Vue rozhrania s náhradným API prešlo. Živá integrácia s MariaDB, kontajnery a konkrétny hosting v autorskom prostredí neboli spustené. Podrobnosti a reprodukovateľné skripty sú v `docs/VERIFICATION.md`.

## Čo aplikácia obsahuje

Katalóg ôsmich produktov, vyhľadávanie podľa názvu/SKU, kategórie a dostupnosti; prihlásenie dvoch demonštračných účtov; košík s množstvami; zľavový kód; dopravu; serverový prepočet; odoslanie objednávky; zoznam a detail vlastných objednávok; zrušenie s vrátením zásob. Košík sa ukladá oddelene pre jednotlivé účty do lokálneho úložiska prehliadača. Po prihlásení sa načíta košík daného účtu, nie anonymný košík.

Pre výučbu sú pripravené stabilné `data-testid`, sumy v atribútoch `data-cents`, SQL kontrolné query, izolované testovacie prostredia, voliteľné serverové oneskorenie a štyri riadené chyby. Mobilná aplikácia zatiaľ súčasťou nie je; môže neskôr použiť rovnaké API a bearer token.

## Technológie a požiadavky

PHP 8.2+ v 64-bitovom prostredí, rozšírenia `PDO`, `pdo_mysql`, `json`, `session`; MariaDB s InnoDB, návrhový cieľ 10.11 alebo 11.4; moderný prehliadač s ES modulmi. Pre nové nasadenie použite podporovanú opravenú verziu PHP, napríklad vetvu 8.4, a aktuálne opravné vydanie zvolenej vetvy MariaDB.

Frontend používa **Vue 3 Composition API a samostatné JavaScript moduly bez build kroku**. Nie sú potrebné Node.js, npm, Composer ani prístup k CDN počas behu. Vue je priložené lokálne vo fixovanej verzii 3.5.13; nie je tým deklarované použitie najnovšieho vydania. Nejde o projekt so súbormi `.vue`; komponenty majú `setup()` a statický `template` v `.js`. Závislosť je uvedená v `THIRD_PARTY_NOTICES.md`.

Vue runtime kompilátor vyžaduje v tejto variante CSP `unsafe-eval`. Kompilujú sa iba šablóny z kódu aplikácie, nikdy používateľské vstupy. Pri budúcej migrácii na predkompilované SFC je možné túto výnimku odstrániť.

## A. Rýchle lokálne spustenie bez Dockeru

### 1. Vytvorte prázdnu databázu

V MariaDB alebo v administrácii hostingu vytvorte databázu `testlab_shop` s `utf8mb4` a používateľa s prístupom k nej. Príklad pre lokálneho administrátora je v `database/create-local-database.sql`; pred spustením zmeňte heslo. Nepoužívajte existujúcu databázu s inými tabuľkami.

### 2. Nastavte súkromnú konfiguráciu

PowerShell, z koreňového priečinka projektu:

```powershell
Copy-Item app/config.example.php app/config.local.php
```

Linux/macOS:

```bash
cp app/config.example.php app/config.local.php
```

V `app/config.local.php` upravte `db.host`, `db.port`, `db.database`, `db.username` a `db.password`. Hodnoty sa nesmú nachádzať vo frontende. Pri lokálnom spustení ponechajte `app_env=local`, `enable_test_api=false` a `fault_mode=none`.

### 3. Vytvorte schému a počiatočné dáta

```bash
php bin/install.php
php bin/seed.php
php bin/check.php
php tests/domain.php
```

`install.php` vyžaduje prázdnu databázu a nikdy nevykonáva DROP. `seed.php` neprerába existujúce dáta. Schéma nie je verzovaný migrátor; pri ďalšom vývoji používajte samostatné explicitné migrácie a zálohy. Ak import schémy zlyhá uprostred, odstráňte iba túto novú prázdnu výučbovú databázu, odstráňte príčinu a inštaláciu zopakujte.

**Alternatíva bez SSH/PHP CLI:** v phpMyAdmin vyberte prázdnu databázu a postupne importujte `database/schema.sql` a `database/seed.sql`. Nepoužívajte zároveň import SQL aj `install.php`/`seed.php`.

### 4. Spustite web

```bash
php -S 127.0.0.1:8080 -t public
```

Otvorte `http://127.0.0.1:8080/`. Server musí obsluhovať **iba priečinok `public/`**, nie celý repozitár. Vstavaný PHP server používajte na lokálny vývoj, nie ako verejný webserver. Nepotrebujete rewrite pravidlá; frontend má hash navigáciu a API sa smeruje parametrom `route`.

### 5. Prihláste sa

| Účet | Lokálne demonštračné heslo |
|---|---|
| `student@example.test` | `Student123!` |
| `student2@example.test` | `Student123!` |

Účty zdieľajú demonštračný sklad, ale každý vidí iba vlastné objednávky. Pre paralelné automatizované testy používajte izolované fixtures, nie tieto spoločné účty. Pred sprístupnením cez internet nepoužívajte demonštračné heslá. CLI seed umožňuje nastaviť `DEMO_PASSWORD`; v prostredí `production` odmieta predvolené heslo. SQL seed je určený výhradne na lokálne/obmedzené výučbové nasadenie.

## B. Voliteľné spustenie cez Docker Compose

Docker nie je podmienkou hostingu. Táto konfigurácia je doplnková a pred odovzdaním nebola spustená.

```bash
# Windows: Copy-Item .env.example .env
cp .env.example .env
# Upravte obidve databázové heslá v .env.
docker compose up -d --build
docker compose exec app php bin/install.php
docker compose exec app php bin/seed.php
docker compose exec app php bin/check.php
```

Aplikácia má adresu `http://127.0.0.1:8080/`. Port databázy nie je publikovaný na hostiteľovi. Databáza pretrváva v pomenovanom volume; opätovný štart nerobí reset. `docker compose down` dáta nemaže; príkaz s `--volumes` by ich zmazal, preto sa v návode automaticky nepoužíva. Zmena hesiel v `.env` sama nemení už existujúce účty v inicializovanom MariaDB volume.

PHP samo súbor `.env` nečíta. Mimo Dockeru používajte `app/config.local.php` alebo skutočné environment premenné.

## C. Nasadenie na bežný PHP hosting

Najčistejšie je nastaviť document root domény na `<projekt>/public`. Súkromné `app/`, `database/`, `bin/`, `tests/` a `docs/` musia zostať mimo document root.

Pri pevnom `public_html` možno rozloženie urobiť takto:

```text
/home/account/
  app/                  # súkromný backend a config.local.php
  database/             # SQL, mimo webu
  bin/                  # CLI, mimo webu
  public_html/          # sem skopírovať iba OBSAH public/
    index.php
    api/index.php
    assets/
```

V tomto rozložení existujúca relatívna cesta v `public/api/index.php` funguje. Pri inom umiestnení zmeňte v tomto jednom súbore cestu k `app/bootstrap.php` na súkromný absolútny path. `app/bootstrap.php` potom ostatné súbory načítava relatívne k sebe. Frontend používa relatívne cesty a hash navigáciu; nie je potrebné prepisovať build pri zmene domény.

Použite HTTPS a `secure_cookies=true`, najmä pri HTTPS ukončenom na reverznom proxy. Nedovoľte verejný prístup k súkromným adresárom a nespoliehajte sa iba na `.htaccess`. Ten je len doplnkovou ochranou pre Apache. Pod Nginx sa `.htaccess` ignoruje; správny document root a obsluha PHP musia byť nastavené v konfigurácii servera.

Na CGI/FastCGI hostingu overte, že sa hlavička `Authorization` dostane k PHP. Pre Apache je v `public/.htaccess` pripravený `SetEnvIf`. Pri chybe HTTP 500 po nahratí skontrolujte serverový log a povolené direktívy `.htaccess`; niektorí poskytovatelia nepovoľujú `Options` alebo vlastné `Require`.

## Referenčná objednávka

Po prihlásení pridajte **2× KB-001 a 1× MS-001**, použite **STUDENT10** a zvoľte **kuriéra**.

| Hodnota | Očakávaná suma | SQL/API v centoch |
|---|---:|---:|
| Medzisúčet | 89,70 € | 8970 |
| Zľava | 8,97 € | 897 |
| Doprava | 4,90 € | 490 |
| Celkom | **85,63 €** | **8563** |

Po vytvorení majú byť v čerstvom izolovanom sklade KB-001 = 98 ks a MS-001 = 99 ks. Po zrušení sa vrátia na 100 ks. Vyhľadávajte objednávku podľa konkrétneho UUID, nikdy podľa „poslednej objednávky“ celej databázy.

Presná špecifikácia pravidiel: `docs/ACCEPTANCE.md`. API: `docs/API.md`. Testovacie rozšírenia: `docs/TESTING.md`. Priama SQL kontrola: `examples/verify-order.sql`.

## Izolované dáta pre skupiny a CI

Bez zapnutia HTTP testovacieho rozhrania môže vyučujúci vytvoriť pracovný priestor cez CLI:

```bash
php bin/fixtures.php create
```

Výsledný JSON obsahuje `workspace_code`, jedinečný e-mail a náhodné heslo. Po prihlásení daný účet vidí vlastných osem produktov s vlastnými zásobami. Účet možno použiť v Selenium aj v nahrávacom nástroji. Odstráňte iba vlastný priestor po ukončení jeho testov:

```bash
php bin/fixtures.php delete run-SEM_PATRÍ_24_HEX_ZNAKOV
```

HTTP variant pre CI vyžaduje `app_env=testing`, `enable_test_api=true` a minimálne 32-znakový súkromný kľúč. Podrobnosti sú v `docs/TESTING.md`. Univerzálny verejný endpoint „zmaž všetky dáta“ aplikácia nemá.

## Projektové súbory

```text
app/src/                 PHP validácia, autentifikácia, ceny, objednávky, fixtures
app/config.example.php   vzor súkromnej konfigurácie
public/                  jediný verejný adresár
public/assets/js/pages/  Vue stránky
public/assets/js/core/   API klient, stav aplikácie, navigácia
public/assets/vendor/    lokálna kópia Vue + licencia
database/                schéma, seed a vzory SQL účtov
bin/                     inštalácia, seed, diagnostika, fixtures, údržba
tests/                   jednotkové, offline UI, HTTP a SQL testy
examples/                referenčná objednávka a SQL overenie
docs/                    kontrakt, API, testovanie, overenie a zdroje
deploy/, compose.yaml    voliteľné kontajnerové prostredie
```

## Bezpečnostný a prevádzkový rámec

PHP používa parametrizované PDO dotazy, hashovanie hesiel, HttpOnly/SameSite cookie, CSRF kontrolu pri session mutáciách, expirovateľné bearer tokeny uložené v DB iba ako hash a obmedzenie pokusov o prihlásenie. Autorizácia obmedzuje objednávky na vlastníka. Zásoby sa pri vytvorení objednávky menia v transakcii. Tieto mechanizmy nie sú náhradou bezpečnostného auditu.

Výučbový server prevádzkujte v dôveryhodnej sieti/VPN alebo s obmedzeným prístupom, bez skutočných osobných dát. Testovací API kľúč nikdy nevkladajte do Vue ani do úložísk prístupných všetkým študentom. Priamym SQL používateľom prideľte len potrebné práva. Aplikačné oddelenie pracovných priestorov nie je databázové row-level security; SQL používateľ s prístupom ku všetkým tabuľkám môže čítať všetky priestory.

`php bin/cleanup.php` odstraňuje iba expirované API tokeny a staré záznamy limitovania prihlásení. Pri skupine so spoločnou NAT adresou treba rátať s limitom 120 prihlásení za 15 minút na IP a 10 pokusov na účet; pre veľkú triedu posúďte limit a autentifikované testy pripravte vopred.

## Diagnostika

`could not find driver` alebo `pdo_mysql is required`: zapnite `pdo_mysql` v tom PHP, ktoré skutočne obsluhuje web; CLI a web môžu používať iné `php.ini`.

`DATABASE_UNAVAILABLE`: skontrolujte server, port, meno databázy, oprávnenia, heslo a serverový log. `bin/check.php` ukáže stav spojenia a tabuliek.

`CSRF_FAILED`: načítajte `/auth/me`, zachovajte cookies a odošlite získaný `X-CSRF-Token`. Bežný frontend to robí automaticky. Pre natívneho klienta použite bearer token.

Prázdny katalóg pre testovací účet: skontrolujte, či nebol odstránený jeho pracovný priestor. Po zmene účtu sa mení aj izolovaný sklad a košík.

Licencia vlastného kódu: MIT. Táto verzia neobsahuje mobilného klienta, Jenkins pipeline ani AI agenta; poskytuje ich spoločný objekt testovania a API.
