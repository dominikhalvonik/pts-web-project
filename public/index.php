<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
// Runtime template compilation in this no-build Vue distribution requires unsafe-eval.
// Only application-owned templates are compiled; NEVER compile user-supplied strings.
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-eval'; style-src 'self'; img-src 'self' data:; connect-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
?><!doctype html>
<html lang="sk">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="TestLab Shop – výučbový objednávkový systém pre pokročilé testovanie softvéru.">
  <meta name="robots" content="noindex,nofollow">
  <title>TestLab Shop · Výučbová aplikácia</title>
  <link rel="icon" href="data:,">
  <link rel="stylesheet" href="./assets/css/app.css">
  <script src="./assets/vendor/vue.global.prod.js"></script>
  <script type="module" src="./assets/js/main.js"></script>
</head>
<body>
  <div id="app"><p class="boot-message">Načítavam TestLab Shop…</p></div>
  <noscript>Na používanie aplikácie je potrebné povoliť JavaScript.</noscript>
</body>
</html>
