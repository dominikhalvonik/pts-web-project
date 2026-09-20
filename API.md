# HTTP/JSON API

## Adresa a formát

Základný súbor: `<adresa-aplikácie>/api/index.php`. Operácia sa volí cez query parameter `route`, napríklad:

```text
GET http://127.0.0.1:8080/api/index.php?route=/products
GET http://127.0.0.1:8080/api/index.php?route=/orders&page=1
```

Zápis `/products` v tabuľkách nižšie označuje hodnotu `route`, nie samostatný URL path. Rewrite ani PATH_INFO nie sú potrebné. Vo frameworku REST Assured použite `.queryParam("route", "/products")` a volajte súbor `api/index.php`.

JSON úspechu: `{"data": ..., "meta": {}}`. JSON chyby: `{"error":{"code":"...","message":"...","details":{},"request_id":"..."}}`. Klient sa má rozhodovať podľa HTTP statusu a `error.code`, nie podľa prekladu správy.

Pre POST použite `Content-Type: application/json` a objekt JSON, pri prázdnom tele `{}`. Maximálna veľkosť tela je 32768 bajtov. Koreňové pole namiesto objektu je neplatné. API nevracia verejné stack trace ani obsah konfigurácie. Neznáma cesta alebo nepodporovaná kombinácia cesty/metódy vracia 404.

## Autentifikácia

### Webová session

1. `GET route=/auth/me` vráti `data.user` (alebo null) a `data.csrf_token`; zachovajte cookie.
2. `POST route=/auth/login`, telo `{"email":"student@example.test","password":"Student123!"}` a hlavička `X-CSRF-Token`.
3. Pri úspechu sa obnoví identifikátor session a CSRF token. Ďalšie POST operácie `/orders`, `/orders/{uuid}/cancel`, `/auth/logout` vyžadujú nový CSRF token aj rovnakú session cookie.

Frontend toto rieši automaticky. Session cookie je HttpOnly a SameSite=Lax; Secure závisí od nastavenia HTTPS.

### Bearer token pre API testy a mobilného klienta

```bash
curl -X POST "http://127.0.0.1:8080/api/index.php?route=/auth/token" \
  -H "Content-Type: application/json" \
  -d '{"email":"student@example.test","password":"Student123!"}'
```

Vráti sa `data.token`, `token_type=Bearer` a UTC `expires_at`. Token potom posielajte v hlavičke `Authorization: Bearer <token>`. Neposielajte ho v URL. Bearer token nevyžaduje CSRF. `POST /auth/logout` s bearer tokenom zruší práve tento token. Pri použití PowerShellu možno namiesto aliasu `curl` použiť `curl.exe` alebo `Invoke-RestMethod`.

## Operácie

| Metóda | route | Prístup | Účinok / data |
|---|---|---|---|
| GET | `/health` | verejný | dostupnosť DB spojenia, verzia aplikácie |
| GET | `/auth/me` | verejný | `user`, `csrf_token` |
| POST | `/auth/login` | CSRF + prihlasovacie údaje | session login, `user`, nový `csrf_token` |
| POST | `/auth/token` | prihlasovacie údaje | nový bearer token, 201 |
| POST | `/auth/logout` | prihlásený; pri session aj CSRF | odhlásenie |
| GET | `/products` | verejný/prihlásený | `products` zo správneho pracovného priestoru |
| POST | `/cart/quote` | verejný/prihlásený | ceny a dostupnosť bez rezervácie skladu |
| GET | `/orders` | prihlásený | `orders`, `page`, `page_size=20`, `total` |
| POST | `/orders` | prihlásený + idempotency; pri session aj CSRF | vytvorenie 201 alebo replay 200 |
| GET | `/orders/{uuid}` | vlastník | detail a položky |
| POST | `/orders/{uuid}/cancel` | vlastník; pri session aj CSRF | zrušenie, vracia detail |
| POST | `/testing/fixtures` | povolený testovací režim + X-Test-Key | nový izolovaný priestor a prístupové údaje, 201 |
| DELETE | `/testing/fixtures/{workspace_code}` | povolený testovací režim + X-Test-Key | odstránenie konkrétneho run-* priestoru |

`/products` podporuje parametre `q`, `category` a `in_stock=0|1`. Kategórie sú `Periférie`, `Príslušenstvo`, `Monitory`. SKU sa porovnáva presne; vyhľadávanie názvu/SKU používa databázovú koláciu. `q` je doslovný podreťazec, nie SQL vzor: `%` a `_` nie sú wildcardy. `/orders` podporuje `page` od 1.

## Cenová ponuka

```json
{
  "items": [{"sku":"KB-001","quantity":2},{"sku":"MS-001","quantity":1}],
  "coupon_code":"STUDENT10",
  "delivery_method":"courier"
}
```

Odpoveď `data` obsahuje `items` (`sku`, `name`, `unit_price_cents`, `quantity`, `line_total_cents`), `currency`, `subtotal_cents`, `discount_cents`, `shipping_cents`, `total_cents`, `coupon_code`, `delivery_method`.

## Vytvorenie objednávky

Použite telo `examples/reference-order.json` a hlavičku `Idempotency-Key`, ideálne nové UUID pre novú objednávku. Pri retry nezmenenej požiadavky zachovajte pôvodný kľúč. Povolené sú písmená ASCII, číslice, bodka, pomlčka, podčiarkovník a dvojbodka; dĺžka 8 až 128.

```bash
curl -X POST "http://127.0.0.1:8080/api/index.php?route=/orders" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEM_TOKEN" \
  -H "Idempotency-Key: SEM_NOVE_UUID" \
  --data-binary @examples/reference-order.json
```

Výsledok má UUID v `data.id`, čo je aj primárny kľúč `orders.id`. Referenčná suma je 8563 centov. Číslo objednávky je popisný identifikátor; pri priamom SQL overení použite UUID. `expected_total_cents` je povinné overenie očakávania klienta, nie zdroj ceny.

## Dôležité chyby

| HTTP | code | Význam |
|---:|---|---|
| 400 | INVALID_JSON / INVALID_ROUTE | chybný JSON alebo routovanie |
| 401 | UNAUTHENTICATED / INVALID_CREDENTIALS | chýbajúca identita alebo nesprávne údaje |
| 403 | CSRF_FAILED / TEST_API_FORBIDDEN | neplatný bezpečnostný token |
| 404 | ORDER_NOT_FOUND / PRODUCT_NOT_FOUND / NOT_FOUND | neprístupný alebo neexistujúci zdroj |
| 409 | OUT_OF_STOCK | nedostatok zásob |
| 409 | PRICE_CHANGED | aktuálna suma sa nezhoduje s očakávanou |
| 409 | IDEMPOTENCY_CONFLICT | kľúč už patril inému obsahu |
| 413 | BODY_TOO_LARGE | prekročený limit veľkosti |
| 415 | JSON_REQUIRED | nesprávny Content-Type |
| 422 | VALIDATION_ERROR / INVALID_COUPON / IDEMPOTENCY_KEY_REQUIRED | neplatné vstupné hodnoty |
| 429 | RATE_LIMITED | priveľa pokusov o prihlásenie |
| 503 | DATABASE_UNAVAILABLE / NOT_INITIALIZED | nedostupná alebo neinicializovaná databáza |

## Budúci mobilný klient

Natívny Android klient môže použiť `/auth/token`, `/products`, `/cart/quote` a `/orders`. Nepotrebuje priame SQL spojenie. Na Android zariadení `localhost` označuje samotné zariadenie, nie počítač s PHP serverom; adresu backendu treba nakonfigurovať pre konkrétny emulátor alebo LAN. V tomto balíku mobilný klient nie je.
