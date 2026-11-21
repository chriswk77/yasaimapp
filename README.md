# YASAIMAPP

**Yet Another Stupid AI Mushroom App** — a map for one's own find spots:
place them, label them, sort them into lists, find them again in the woods
without reception.

It is one person's project, grown out of mapping fungi, and it is written
together with an AI — the name says so. The interface is German; an English
catalog covers part of it.

## What it does

- **Finds on a map** — points with a title, a find number, fields per list,
  pictures, measurements; lists gathered in folders („Mappen").
- **Offline** — finds come into being on the device and travel to the
  server later; map tiles and name lists are downloaded only when asked for.
- **Names** — scientific names first, from the reference lists the
  installation loads (fungi, lichens, plants, animals).
- **Pictures** — voucher photos at the find, and the full recordings in
  the user's own cloud storage.
- **Sharing** — a list is shared with friends and working groups, each
  with its own rights and filter; a find worked on together has co-owners.
- **At the desk** — the Fundbrowser: plates, measuring in micrographs,
  editing pictures, a find report.

## What it is made of

PHP without a framework, JavaScript as modules without a build step,
Leaflet and MapLibre for the map, MySQL or MariaDB behind it. There is no
compile stage: what lies in `assets/` goes to the browser as it is.

| | |
|---|---|
| `index.php` | the map |
| `pages/` | everything else the browser calls directly |
| `api/v1/` | JSON for the browser and the recorder app |
| `includes/` | server logic |
| `assets/js/` | the browser's modules |
| `migrations/` | the schema, as numbered `.sql` files |
| `modules/` | optional parts that are not in this repository — see `modules/README.md` |
| `tests/` | `php tests/x.php` or `node tests/x.mjs`; green ends with `All good.` |

`CLAUDE.md` is the project's guide: the rules that cut across everything,
and the traps that have already caught someone. The reasons for a decision
stand as comments where the decision applies.

## Setting it up

1. Put the folder under a web server with PHP 8 (it is developed on 8.4).
2. Create an empty database:
   `CREATE DATABASE karte CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
3. `cp config.sample.php config.php` and fill in the database — the sample
   says what each entry is for and where the file may live.
4. Open `pages/setup.php`: it applies the migrations and creates the first
   account as administrator, then locks itself.
5. For a look around with something in it: `php admin/demodata.php --new`
   builds three demo accounts with lists, finds and a share.

Later migrations are applied in the administration. The workflow under
`.github/` is the maintainer's own deployment and needs his server.

## Licence

GNU Affero General Public License, version 3 — see `LICENSE` and
`COPYRIGHT`. The libraries under `assets/vendor/` are their authors' and
keep their own licences.
