# DiceSim

DiceSim is a small PHP web app that runs Monte Carlo simulations of one-on-one melee
combat in a d100-based tabletop RPG. You enter the stats of two fighters, pick a combat
rule variant, and the app runs many battles to see how often each fighter wins, how long
fights last, and how much HP the winner usually has left.

It was written as a balancing tool for comparing house rules (in the app: *Codex* and its
variants). The UI is Hungarian only; the page title is **Kétparaszt**.

## Features

- Simulates any number of battles between two configurable fighters
- Six rule variants: active and passive defense, and different rules for how Defense wears down
- Optional rule modifiers: *Mestervágás* (master hit) and losing an attack when wounded
- Summary statistics: average number of rounds, wins per fighter (count and %), average remaining HP of the winner
- A round-by-round log of every battle, with each attack roll, HP loss and Defense loss

## Requirements

- PHP **8.1** or newer
- [Composer](https://getcomposer.org/)
- Internet access in the browser: Bootstrap 3.3.7 and jQuery 3.1.1 are loaded from public CDNs

## Installation

```bash
git clone git@github.com:dokker/dicesim.git
cd dicesim
composer install
```

## Running

PHP's built-in server is enough. Start it from the project root:

```bash
php -S localhost:8000
```

Then open <http://localhost:8000>. On Apache or nginx, point the document root at the
project directory and use `index.php` as the index file.

### With Docker

No local PHP or Composer needed:

```bash
docker compose up --build        # app on http://localhost:8000
docker compose run --rm app composer test   # run the test suite
```

The project directory is mounted into the container, so code changes show up without a
rebuild. After changing `composer.json`, run `docker compose run --rm app composer install`.

Managing the container:

```bash
docker compose up -d --build     # start in the background
docker compose logs -f           # follow the logs
docker compose stop              # stop (keeps the container)
docker compose down              # stop and remove the container
docker compose down -v           # also remove the vendor volume
docker compose ps                # show status
```

The app does **not** start automatically when the machine or Docker starts
(`restart: "no"` in `compose.yaml`); start it by hand with `docker compose up`.

## Usage

1. Fill in the stats of **Harcos 1** and **Harcos 2** (Fighter 1 and 2).
2. Under **Teszt beállításai** (test settings), set the number of battles, the passive
   Defense percentage, the modifiers and the rule variant.
3. Click **Teszt végrehajtása** (run test).

The summary appears below the form. Click a battle's heading to open its round-by-round log.

### Fighter stats

| Field         | Meaning                                         | Default  | Value used if left empty |
|---------------|-------------------------------------------------|----------|--------------------------|
| Név           | Name                                            | Mester / Tanítvány | Harcos 1 / Harcos 2 |
| Ké            | Initiative                                      | 10       | 1                        |
| Té            | Attack value (percent chance to hit, 0–100)     | 40       | 10                       |
| Vé            | Defense value (≥ 0)                             | 40       | 0                        |
| Ép            | Hit points (≥ 1)                                | 12       | 1                        |
| SFÉ           | Armor, subtracted from damage (≥ 0)             | 0        | 0                        |
| Támadások     | Number of attacks per round (≥ 1)               | 1        | 1                        |

Values that are not numbers fall back to the last column; values out of range are clamped.

### Test settings

| Setting        | Meaning |
|----------------|---------|
| # Csaták       | Number of battles to simulate (default 100, at most 10 000). |
| Passzív Vé %   | The percentage of Vé used as passive Defense in the passive-defense variants (0–100, default 40). Without Defense loss, values above 80 are not recommended. |
| Támadást veszít aki sebződik | A fighter who loses HP also loses one of their remaining attacks in that round. |
| Mestervágás    | Master hit: an attack roll at or below the tens digit of Té (e.g. ≤ 4 when Té is 45) always hits HP, whatever the target's Defense. |

## Combat mechanics

### Shared rules

- **Dice:** an attack roll uses two d10s read as a percentile number (tens digit and ones
  digit, where a 10 counts as 0, so the result is 0–99).
- **Hit:** an attack hits if the roll is **≤ Té**.
- **Damage:** the higher of the two d10s, minus the target's SFÉ (never below 0).
- **Initiative:** at the start of each round both fighters roll Ké + d10. A 10 explodes:
  roll again and add. The higher total acts first; on a tie both roll again.
- **Rounds:** the fighters take turns attacking until both have used all their attacks
  (Támadások) or one of them is dead. A fighter with no attacks left is skipped, so the
  other one can use all of theirs.
- **Battles** go on until one fighter's HP reaches 0. A battle still going after 500
  rounds (e.g. when neither fighter can hit) ends in a draw (*döntetlen*).
- HP, Vé and passive Defense are restored between battles.

### Rule variants

| # | Name in UI | How a successful attack is resolved |
|---|------------|-------------------------------------|
| 1 | **Codex** | If the roll is above the target's current Vé (or is a master hit), the damage goes to HP and Vé drops by the same amount. Otherwise the damage only lowers Vé. |
| 2 | **CX2** | Like Codex. In addition, a missed attack whose roll is still above the target's Vé lowers Vé by the *lower* of the two d10s. |
| 3 | **Passzív Védekezés Vé csökkenéssel** | Passive Defense = Vé × the passive %. A roll above it (or a master hit) deals HP damage; otherwise the damage lowers passive Defense. HP damage also lowers it. |
| 4 | **Passzív Vé Sebzésből** | No Defense check. Every hit deals damage − SFÉ − ⌊Vé / 10⌋ to HP (at least 0). No Defense loss, no master hit. |
| 5 | **Passzív Védekezés** | Passive Defense = Vé × the passive %. A roll above it (or a master hit) deals HP damage; otherwise nothing happens. No Defense loss. |
| 6 | **Aktív Védekezés** | The defender rolls d100 as well. If the defense roll is **≤ Vé** the attack is blocked; otherwise (or on a master hit) the damage goes to HP. No Defense loss. |

The numbers are the `system` values sent by the form and used in `Simulator::resolveAttack()`.

## Tests

The tests use PHPUnit:

```bash
composer test
```

Without a local PHP, run them in Docker:

```bash
docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit
```

`tests/RiggedDice.php` is a `Dice` that returns predetermined values, so most rules are
tested with exact rolls. One test also runs 400 battles with real dice in every system,
checking that every battle ends and that two equal fighters win about equally often.

## Project structure

```
index.php                 Entry point: loads Composer's autoloader and creates the Controller
includes/
  Controller.php          Reads the form, runs the Simulator, renders the page
  Simulator.php           Battles, rounds, initiative, rule variants and statistics
  Combatant.php           A fighter's state: HP, Defense, passive Defense, attacks left
  Dice.php                Dice roller (d10)
  Model.php               Form defaults and input validation
templates/
  base.html.twig          Page layout, test summary and battle log
  form.html.twig          Input form (extends base)
assets/style.css          Styles on top of Bootstrap 3
tests/                    PHPUnit tests
```

Classes are autoloaded via PSR-4 under the `diceSim\` namespace (mapped to `includes/`).
