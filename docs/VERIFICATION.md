# Rozsah overenia verzie 1.0.0

Dátum prípravy: 20. september 2026.

## Skutočne vykonané kontroly

| Kontrola | Výsledok | Čo výsledok znamená |
|---|---|---|
| PHP syntax | 20 súborov bez syntaktických chýb | `php -l`; nie overenie DB dotazov |
| JavaScript syntax | 12 vlastných modulov bez syntaktických chýb | syntaktická kontrola; vendor knižnica do počtu nepatrí |
| Python syntax | 4 pomocné skripty | syntax, nie spustenie živých integračných testov |
| `php tests/domain.php` | **47 úspešných, 0 neúspešných** | výpočty cien, validácia a pravidlá bez databázy |
| `python tests/offline_ui.py` | **26 úspešných kontrol** | vykreslenie skutočného Vue kódu s náhradným API a úložiskom |
| PHP HTTP odpoveď pre `/` | HTTP 200, HTML UTF-8 | PHP poskytlo stránku, nie plne funkčný obchod |
| PHP HTTP odpoveď pre API health bez DB ovládača | HTTP 503, `DATABASE_UNAVAILABLE` | kontrolovaná chyba bez prezradenia prihlasovacích údajov |

Doménové testy obsahujú okrem iného referenčnú cenu 8563 centov, dopravu zdarma od 10000 centov po zľave, zaokrúhľovanie na polovici centu, neplatné množstvá, chýbajúci produkt, nedostupné zásoby a validáciu objednávky. Presné testy a výsledky sú priložené; ich úspech nie je dôkazom bezchybnosti všetkých možných vstupov.

Offline UI kontroly zahŕňajú katalóg a filtrovanie, neúspešné a úspešné prihlásenie, zmeny košíka, kupón, cenovú ponuku, odoslanie objednávky, escapovanie HTML poznámky, potvrdenie a odmietnutie zrušenia, históriu, odhlásenie, navigačné chyby a kontrolu šírky mobilného zobrazenia.

## Obmedzenia UI kontroly

Prehliadač v autorskom prostredí nepovoľoval navigáciu na adresy stránok. Preto sa **nemenili pravidlá prehliadača**: test použil lokálne HTML/CSS/JavaScript súbory, offline zostavenie vlastných ES modulov, náhradné API a náhradné úložisko. Skutočné Vue komponenty sa vykresľovali a ovládali v Chromium.

Týmto sa neoverilo sieťové načítanie ES modulov v prehliadači, skutočné cookies, reálne sessions, celá CSP pri sieťovom načítaní, komunikácia Vue → PHP ani perzistencia v MariaDB. Rovnaké rozhranie je doplnené samostatným `browser_mock.py`, ktorý na dostupnom počítači načíta HTML a JavaScript cez HTTP, no tiež používa náhradné API.

Priložené PNG sú **snímky skutočného rozhrania s náhradnými dátami**, nie dôkaz objednávky uloženej v reálnej databáze.

## Čo nebolo vykonané

V prostredí nebol dostupný server MariaDB ani PHP rozšírenie `pdo_mysql`. Preto neboli vykonané import schémy, živé SQL dotazy, autentifikácia cez databázu, integrácia API s DB, databázové transakcie, súbežné objednávky, `tests/integration.py` ani `tests/database.php`. Nebola spustená Docker Compose konfigurácia ani vykonané nasadenie na konkrétny hosting. Nejde o bezpečnostný audit ani záťažový test.

**Pred použitím na výučbe treba spustiť živú integračnú kontrolu na skutočnej inštalácii.** Táto povinnosť nie je nahradená uvedenými offline kontrolami.

## Reprodukcia

Bez MariaDB:

```bash
php tests/domain.php
# Voliteľné vývojové závislosti iba pre kontrolu rozhrania:
python -m pip install playwright
python -m playwright install chromium
python tests/offline_ui.py
```

Po inštalácii PHP + MariaDB podľa `README.md`:

```bash
php bin/check.php
# Zapnúť testing prostredie, test API a vlastný tajný kľúč podľa TESTING.md.
# Nastaviť TEST_API_KEY aj v prostredí testovacieho procesu.
python tests/integration.py --url http://127.0.0.1:8080/
# Manuálne vytvoriť referenčnú objednávku a dosadiť jej UUID:
php tests/database.php UUID_OBJEDNAVKY 8563 created
```

HTTP integračný skript má pripravené kontroly cien, zásob, vlastníctva objednávky, session/CSRF, idempotencie, zrušenia a súbehu. Skutočný test súbežných DB požiadaviek vyžaduje viacprocesový/multithreadový server; jednovláknový vstavaný PHP server môže požiadavky iba zoradiť za seba.

## Prostredie a uložené dôkazy

Pri skutočných lokálnych kontrolách: PHP 8.4.23; Node.js 22.16.0 použitý len na syntax, nie beh aplikácie; Python 3.13.5; Playwright 1.57.0; Chromium 144.0.7559.96. Tieto údaje opisujú prostredie kontroly, nie odporúčanie držať starú opravnú verziu.

`tests/results/domain-results.txt` obsahuje 47 PHP výsledkov. `browser-mock-results.json` obsahuje 26 offline UI výsledkov a explicitný rozsah. `verification-summary.json` obsahuje počty syntax kontrol, verzie, výsledky HTTP kontrol a SHA-256 priloženej Vue distribúcie. Náhľady: `catalog-desktop.png`, `catalog-mobile.png`, `order-desktop.png`.
