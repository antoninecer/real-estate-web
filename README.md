# Real Estate Web

Jednoduchý webový dashboard pro prohlížení a filtrování realitních nabídek.

Projekt slouží jako **vizualizační vrstva nad databází**, kterou plní samostatný systém *real-estate-agent* (scraping, scoring a AI analýza).

## Co projekt dělá

Web umožňuje:

* zobrazit nalezené byty
* řadit podle ceny, plochy nebo score
* filtrovat podle parametrů
* rychle otevřít detail inzerátu
* sledovat kvalitu nabídky podle interního scoringu

Data se čtou z PostgreSQL view:

```
v_estates_hard_score
```

---

# Architektura

Systém je rozdělen na dvě části.

```
real-estate-agent
    scraping
    AI analýza
    scoring
    PostgreSQL

real-estate-web
    PHP dashboard
    filtry
    přehled bytů
```

Web **neobsahuje scraping ani AI logiku**, pouze zobrazuje data z databáze.

---

# Struktura projektu

```
real-estate-web/

inc/
    connect.example.php
    helpers.php
    menu.php

index.php
vypis.php
aiprompt.php
api.php

styles.css
```

---

# Instalace

1. naklonovat repo

```
git clone https://github.com/antoninecer/real-estate-web.git
```

2. vytvořit DB konfiguraci

```
cp inc/connect.example.php inc/connect.php
```

3. upravit připojení k databázi

```
$dsn = "pgsql:host=127.0.0.1;port=5432;dbname=realestate";
$user = "realestate";
$password = "CHANGE_ME";
```

4. otevřít v browseru

```
http://server/real-estate-web/vypis.php
```

---

# Hlavní stránky

## vypis.php

Přehled všech bytů.

Funkce:

* řazení sloupců
* filtry
* score
* otevření detailu

## aiprompt.php

Experimentální nástroj pro AI hodnocení inzerátů.

## api.php

API endpoint pro napojení na AI nebo automatizaci.

---

# Scoring

Score je počítáno v databázi ve view:

```
v_estates_hard_score
```

Zohledňuje například:

* cenu
* cenu za m²
* vzdálenost od metra
* velikost bytu
* patro
* stav budovy
* bonusové vlastnosti (balkon, garáž apod.)

Konfigurace scoringu je uložena v tabulce:

```
estate_scoring_profiles
```

---

# Licence

Projekt je experimentální a slouží pro výzkum realitního trhu.
