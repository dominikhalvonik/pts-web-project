# Primárne technické zdroje

Overené pri príprave 20. septembra 2026. Nasledujúce zdroje dokumentujú použité technické mechanizmy, nie úspešnosť tejto konkrétnej implementácie.

- Vue: Quick Start; použitie bez build kroku a lokálneho skriptu: `https://vuejs.org/guide/quick-start.html`
- Vue: Ways of Using Vue: `https://vuejs.org/guide/extras/ways-of-using-vue.html`
- PHP: podporované vetvy: `https://www.php.net/supported-versions.php`
- PHP: parametrizované PDO dotazy: `https://www.php.net/manual/en/pdo.prepared-statements.php`
- PHP: password_hash: `https://www.php.net/manual/en/function.password-hash.php`
- MariaDB: FOR UPDATE a aktívna transakcia: `https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/for-update`
- MariaDB: údržba verzií: `https://mariadb.org/about/`
- Docker Compose: poradie štartu a service_healthy: `https://docs.docker.com/compose/how-tos/startup-order/`

Použitá lokálna Vue distribúcia má hlavičku `vue v3.5.13`. Je priložená kvôli opakovateľnému behu bez siete; nejde o tvrdenie o najnovšej verzii. Pri aktualizácii uložte konkrétnu verziu a znovu vykonajte UI aj integračné testy, nie dynamické načítanie `@latest` počas hodiny.
