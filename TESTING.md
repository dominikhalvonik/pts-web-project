# Testovanie a príprava výučby

## Odporúčaný postup

Najskôr nasadiť normálnu verziu (`fault_mode=none`), vykonať referenčnú objednávku a spustiť živý integračný test. Až overenú inštaláciu používať ako základ jednotlivých hodín. K aplikácii je priložená špecifikácia `ACCEPTANCE.md`; test nemá iba prepisovať výsledok implementácie.

## Stabilné selektory

Aplikácia používa `data-testid`. Odporúčaný zápis v Selenium: `By.cssSelector("[data-testid='login-email']")`. Nepoužívajte poradové XPath ani triedy určené na vzhľad. Identifikátor SKU je stabilný v každom pracovnom priestore; interné číselné DB ID nie sú globálne pevné.

| Časť | data-testid |
|---|---|
| Prihlásenie | login-email, login-password, login-submit, login-error |
| Navigácia | nav-catalog, nav-cart, nav-orders, nav-login, logout-button |
| Stav účtu/košíka | current-user, cart-count, notification |
| Katalóg | search-input, search-submit, category-filter, in-stock-filter, catalog-ready, catalog-loading |
| Produkt KB-001 | product-card-KB-001, add-to-cart-KB-001, price-KB-001, stock-KB-001 |
| Košík | quantity-KB-001, remove-KB-001, coupon-input, coupon-apply, delivery-method |
| Ponuka | quote-subtotal, quote-discount, quote-shipping, quote-total, quote-error |
| Odoslanie | proceed-checkout, customer-name, customer-email, customer-address, customer-city, customer-postal-code, order-note, accept-terms, place-order |
| Detail | order-id, order-number, order-status, order-total, order-quantity-KB-001 |
| Zrušenie | cancel-order, confirm-cancel, keep-order |

Peňažné elementy majú aj číselný atribút `data-cents`; napríklad `order-total` má v referenčnom scenári `8563`. Stav je v `data-status`, sklad v `data-stock`. Test GUI by mal podľa potreby overiť aj formátovaný viditeľný text, nielen atribút. Čakajte explicitne na stav pripravenosti, nie pomocou pevného `sleep`.

## Izolované fixtures

Jeden `run-*` priestor má vlastný účet a produkty. Rovnaké SKU v rôznych priestoroch neznamená zdieľaný sklad. CLI vytvorenie: `php bin/fixtures.php create`; údaje odovzdá vyučujúci skupine alebo testovaciemu procesu. Účet sa prihlasuje bežným spôsobom, neexistuje parameter URL, ktorým by sa zákazník mohol prepnúť do cudzieho priestoru.

Pre automatické vytvorenie cez HTTP nastavte v súkromnom configu:

```php
'app_env' => 'testing',
'enable_test_api' => true,
// Tu použiť výsledok random_bytes generátora, nie túto ukážku.
 'test_api_key' => 'SEM_NAHODNY_TAJNY_KLUC_ASPON_32_ZNAKOV',
'fault_mode' => 'none',
```

Generovanie kľúča:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

`POST route=/testing/fixtures`, telo `{}`, hlavička `X-Test-Key` vráti vlastný účet a priestor. Na konci `DELETE route=/testing/fixtures/run-...` s tým istým kľúčom. Endpoint neprijíma `demo` a nemaže ostatné priestory. Reset/mazanie nevykonávajte počas aktívnych testov príslušného priestoru. Mazanie samo nevypína už otvorené prehliadače; po zmazaní je ich pôvodná identita neplatná.

Kľúč má prístup ku všetkým run-* priestorom, preto patrí len vyučujúcemu alebo dôveryhodnej CI. Pre nedôveryhodných SQL klientov použite samostatnú databázu pre každú skupinu. Samotné `workspace_id` nie je databázová bezpečnostná hranica.

## Spustenie kontrol

Čisté PHP testy bez databázy:

```bash
php tests/domain.php
```

Živé HTTP testy nad pripravenou aplikáciou a MariaDB:

```powershell
$env:TEST_API_KEY = "VAŠ_SKUTOČNÝ_TAJNÝ_KĽÚČ"
python tests/integration.py --url http://127.0.0.1:8080/
```

Linux/macOS:

```bash
export TEST_API_KEY='VAS_SKUTOCNY_TAJNY_KLUC'
python3 tests/integration.py --url http://127.0.0.1:8080/
```

HTTP skript používa iba štandardnú knižnicu Pythonu 3.10+. Vytvorí dva vlastné priestory, overí ceny, zásoby, ochranu vlastníctva, session/CSRF, idempotenciu, zrušenie a súbeh objednávok; na konci odstráni iba vlastné priestory. Pri `FAULT_MODE` inom ako `none` má podľa typu chyby zlyhať. Neočakáva sa, že všetky testy prejdú na úmyselne chybnej aplikácii.

Priama nezávislá kontrola DB konkrétnej objednávky:

```bash
php tests/database.php UUID_OBJEDNAVKY 8563 created
```

Nastavenie DB prístupu berie z toho istého súkromného configu. Pre študentské JDBC testy použite samostatný read-only SQL účet, príklad v `database/readonly-user.example.sql`; neposkytujte aplikačné heslo. Skript `tests/database.php` navyše číta `users` pre kontrolu vlastníctva a je preto určený vyučujúcemu s príslušným oprávnením, nie tomuto obmedzenému read-only účtu.

Voliteľné vývojové kontroly UI, NIE hotové Selenium úlohy pre študentov:

```bash
python -m pip install playwright
python -m playwright install chromium
python tests/browser_mock.py --url http://127.0.0.1:8080/
# Alternatíva bez akejkoľvek navigácie na sieť: lokálne renderovanie kódu
python tests/offline_ui.py
```

Obidva UI skripty používajú náhradné API; offline varianta navyše náhradné úložisko a lokálne zabalenie ES modulov. Nepotvrdzujú správnosť PHP ani SQL. Verzia `browser_mock.py` načíta HTML a assety zo zadaného servera, ale API zachytí ako mock. Na Linuxe môže skript vybrať systémový `chromium`; parameter `--chromium` umožňuje zvoliť inú nainštalovanú spustiteľnú cestu.

## Riadené chyby pre vyučujúceho

Režim sa nastavuje len v súkromnom configu alebo environment premennej, nie v URL ani GUI. Vyžaduje `app_env=testing`. Nevyužívajte osobné údaje a nedávajte chybnú inštanciu na otvorený internet.

| fault_mode | Zámerná chyba | Ako ju odhaliť |
|---|---|---|
| none | žiadna zapnutá chyba | referenčná správna implementácia |
| shipping_boundary | doprava je zdarma až nad 100 €, nie od 100 € | BAG-001 × 4, bez kupónu, kuriér; má byť 0, aplikácia dá 490 |
| discount_rounding | zľava sa zaokrúhľuje nadol namiesto half-up | PAD-001 × 1; má byť discount 100, aplikácia dá 99 |
| stock_not_decremented | objednávka nezníži sklad; zrušenie ho tiež nemení | GUI/API objednávka vyzerá správne, ale porovnanie zásob odhalí rozdiel |
| order_total_response | API zoznam/detail objednávky vracia o 100 centov vyššiu sumu, DB zostáva správna | porovnanie požiadavky, GUI/API a priameho SQL |

`api_delay_ms=1200` pridá v testing prostredí deterministické oneskorenie API okrem health. Rozsah je maximálne 3000 ms. Nejde o náhodný flaky režim; slúži na úlohu s explicitným čakaním. Na PHP vstavanom serveri sa požiadavky bežne spracúvajú sériovo, preto presné súbežné záťažové merania robte na vhodnom viacprocesovom serveri.

**Dôležité:** režim nemeňte uprostred existujúcich testov. Každý režim skúšajte na novom pracovnom priestore. Predovšetkým zmena `stock_not_decremented` medzi vytvorením a zrušením objednávky by vedome narušila zásoby. Režimy nie sú persistované ku každej objednávke.

## Prepojenie s kurzom

GUI scenár: prihlásenie → výber → košík → cenová ponuka → objednávka. GUI+DB: prečítanie UUID → kontrola `orders`, `order_items` a zásob. Nahrávanie: tie isté kroky a explicitné overenie hodnoty/statusu. API: bearer token, ponuka, objednávka, fixtures. AI akceptačné testovanie: návrhy scenárov porovnať s `ACCEPTANCE.md`, nie iba s implementáciou. Databázové heslo a testovací API kľúč sa nesmú stať súčasťou zadania pre AI agenta.
