# Funkčná špecifikácia a akceptačné kritériá v1.0

Táto špecifikácia je zdrojom očakávaných výsledkov. Správnosť testu sa nemá odvodzovať iba z toho, čo aktuálne vráti aplikácia. Predvolený režim je `fault_mode=none`.

## Identita a prístup

Katalóg je dostupný aj anonymne, vtedy zobrazuje priestor `demo`. Prihlásenie je potrebné na vytvorenie, zobrazenie histórie/detailu a zrušenie objednávky. Vlastník môže čítať iba svoje objednávky; cudzí alebo neexistujúci identifikátor vráti 404. Druhý demonštračný účet používa rovnaký sklad; samostatné fixtures používajú oddelené sklady.

Session aj bearer token majú predvolenú maximálnu platnosť 7200 sekúnd. Webová session používa CSRF; bearer autentifikácia nevyžaduje CSRF, ale musí mať platný token. Odhlásenie bearer klienta ruší použitý token, nie všetky ostatné tokeny používateľa.

## Košík a cena

Košík obsahuje 1 až 30 rôznych aktívnych SKU. Každá položka má celé množstvo 1 až 20. Duplicitné SKU, zlomkové množstvo a textové množstvo sú neplatné. Pri API je celé číslo JSON `2`, nie reťazec `"2"`. Neaktívny/nejestvujúci produkt sa odmietne ako 404; nedostatočný sklad ako 409. Nulový sklad neumožňuje objednať ani jeden kus.

Jednotkové ceny pochádzajú výhradne z databázy. Klientská cena v požiadavke sa ignoruje. Všetky sumy v API/SQL sú celé čísla v eurocentoch. Ceny sú modelové konečné sumy; samostatný daňový výpočet ani účtovné doklady nie sú predmetom aplikácie.

Medzisúčet je súčet `unit_price_cents × quantity`. Jediný platný neprázdny kupón je `STUDENT10`, po odstránení okrajových medzier a prevode na veľké písmená. Zľava je 10 % z celého medzisúčtu, zaokrúhlená **jedenkrát na konci, half-up na celé centy**. Pri 995 centoch je zľava 100 centov. Dva kusy po 995 centov majú zľavu 199 centov, nie 200.

Osobný odber stojí 0 centov. Kuriér stojí 490 centov, ak suma produktov **po zľave** je menšia ako 10000 centov. Pri presne 10000 centoch je kuriér zdarma. Celková suma je `subtotal_cents - discount_cents + shipping_cents`. Kupón neznižuje samostatne cenu dopravy.

Cenová ponuka nerezervuje zásoby. Pri odoslaní sa znovu kontroluje aktuálna cena a sklad. `expected_total_cents` vyjadruje sumu, ktorú klient očakáva; pri rozdiele sa objednávka neuloží a vráti sa 409 `PRICE_CHANGED`. Skutočnú cenu neurčuje tento parameter.

## Objednávka

Povinné sú meno, platný e-mail, spôsob doručenia a boolean `accept_terms=true`. Pri kuriérovi aj adresa, mesto a slovenské päťmiestne PSČ; prijíma sa aj medzera po prvých troch čísliciach. Osobný odber adresu nevyžaduje a do objednávky ju neukladá. Poznámka má najviac 500 znakov a zobrazuje sa ako text, nie HTML.

Úspešné vytvorenie vracia 201 a stav `created`. Objednávka má UUID a číslo `TL-` + 32 hexadecimálnych znakov. Obsahuje snímku názvov a cien položiek v čase nákupu. Zmena názvu/ceny produktu neskôr nemení staré položky.

Objednávka a odpočítanie skladu sú súčasťou jednej transakcie. Nedostatok zásob nesmie zanechať čiastočnú objednávku. Server vyžaduje hlavičku `Idempotency-Key` s 8 až 128 povolenými ASCII znakmi. Rovnaký účet + kľúč + rovnaký normalizovaný obsah vráti tú istú objednávku, HTTP 200 a `meta.replayed=true`; nesmie sa znovu odpočítať sklad. Rovnaký kľúč s iným obsahom vráti 409. Po zrušení objednávky opakovanie pôvodného požiadavku s týmto kľúčom stále vracia tú istú už zrušenú objednávku, nevytvára novú.

Zrušenie mení `created` na `cancelled` a vracia položky na sklad práve raz. Druhé zrušenie tej istej objednávky je bezpečné a sklad znovu nezvyšuje. Údaje zostávajú v histórii. Nie je implementované platenie, odoslanie zásielky ani stav `paid` či `shipped`.

## Referenčné scenáre

| Scenár | Obsah | Očakávaný výsledok |
|---|---|---|
| Základný | KB-001 × 2, MS-001 × 1, STUDENT10, kuriér | subtotal 8970; discount 897; shipping 490; total 8563 |
| Bez zľavy | Rovnaké produkty, bez kupónu, kuriér | total 9460 |
| Osobný odber | Rovnaké produkty, STUDENT10, pickup | total 8073 |
| Presná hranica | BAG-001 × 4, bez kupónu, kuriér | subtotal/total 10000; shipping 0 |
| Hranica po zľave | BAG-001 × 4, STUDENT10, kuriér | subtotal 10000; discount 1000; shipping 490; total 9490 |
| Zaokrúhľovanie | PAD-001 × 1, STUDENT10, pickup | subtotal 995; discount 100; total 895 |
| Vypredaný produkt | HUB-001 × 1 | quote/order odmietnuté, 409 OUT_OF_STOCK |
| Súbeh | Dve objednávky MON-001 × 15 pri počiatočnom sklade 20 | iba jedna vznikne; zostane 5 ks |

Časy sa ukladajú ako UTC `DATETIME`. Web ich zobrazuje v časovom pásme Europe/Bratislava. Testy nemajú tvrdo predpokladať aktuálny dátum ani konkrétne UUID.
