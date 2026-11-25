# YASAIMAPP

**Yet Another Stupid AI Mushroom App** — the christening of
24.08.2026; the display name everywhere, while the TECHNICAL names keep
their history (`map_` table prefix, the `/map/` path, this repository):
renaming a live database buys risk and nothing else.

A map for one's own find spots: place them, label them, sort them into
lists, find them again offline. PHP without a framework, JavaScript as
modules without a build step, Leaflet for the map, MySQL behind it. There is
no compile stage — what sits in `assets/` goes to the browser as-is; `?v=`
on the paths guards against stale caches.

This document collects only what **cuts across everything**. Everything else
lives in the code.

## How the code sounds

The why sits as a comment at the spot where it applies — not in a document
lying next to it and going stale. Comments explain the decision and the
discarded alternative, not the syntax:

> `/* The arrow is ALSO there where there is nothing to fold — empty then.
> Otherwise lists without sublists would shift left by its width, and the
> indent that is meant to show the level would suddenly mean two things. */`

Whoever finds a trap writes it down where the next person would step into
it. Important paragraphs open with a line in ALL CAPS. Commit messages are
long and justify the same reasoning once more, for those who never open the
code.

**NO NAME IN A COMMENT** (decided 20.11.2026, on the way to a public
repository). A comment says what was decided, when and why —
`(20.11.2026: „unten wird gespeichert")` — and never who. The quote stays
where it is the reason, in its German; the name in front of it goes. Where
a sentence needs somebody, it is „the maintainer". Commit messages follow
the same rule. All 3,500 places that carried the name were swept that day;
what is left are example names in a few tests and comments.

**A COMMENT IN A TEMPLATE IS A PHP COMMENT** (decided 20.11.2026):
`<?php /* … */ ?>`, never `<!-- … -->`. An HTML comment is part of the
answer — measured that day, the map sent 115 of its 425 KB as comments, and
whoever opened the page source read the reasoning of the whole project. The
PHP comment stands at the same spot and stays on the server.
`tests/comments.php` is the tripwire.

**English for the code, German for the user** (the calls of 15. and
16.08.2026; before that everything was German, and the git history still
speaks it). Identifiers, file names, CSS classes, comments and the output
of the test scripts are English. Everything the user reads — page texts,
dialogs, placeholders — is German and stays German, and so is the user's
own content that tests use as fixtures. Since 0063 the DATABASE SCHEMA is
English too; result-array keys straight from a fetch stay column-true, and
one name runs end to end — column, fetch key, API field, JS property — with
no translation table between them. ONE DELIBERATE EXCEPTION: proper names
stay themselves (`meinepilze`, `takeout`, `mtb`, `dgfm`), and the file
names of the applied migrations keep their German — they are the ledger.

## Layout

| | |
|---|---|
| `index.php` | the map, a single document holding every `<dialog>` |
| `pages/` | everything else the browser calls directly: administration, photo delivery, manifest, setup, location probe. Only the map, the login and `sw.js` stay in the root — the last one MUST sit there, its location defines its scope |
| `api/v1/` | JSON for browser and PWA |
| `includes/` | server logic; `*_content.php` is view **and** processing |
| `assets/js/` | modules, `map.js` wires them up |
| `admin/` | tools; most of them redirect to the administration these days |
| `modules/` | what is not part of the core: a folder per module with a `module.php` that RETURNS what it offers. The core asks through the hooks of `includes/modules.php` — a header button, a key tool for a find, a section under the friends — and NEVER calls a module by name: an installation without the folder must work as before, and `tests/modules.php` is the tripwire. A module lives WHOLLY in its folder (21.11.2026): its pages, API, tools, scripts and styles, its `migrations/` (one numbering and one ledger with the core's — a module mints its number from the same row), its `lang/` and its `tests/`. AND IT IS A REPOSITORY OF ITS OWN: this one ignores `modules/*/` and carries only `modules/README.md`; a module is cloned into its folder, committed and pushed THERE, and deploys itself (its own workflow, its own memo file in its folder). A worktree of this repository has no modules — the application is complete without |
| `cronjobs/` | the unattended twins of the administration's chunk buttons — each job calls the SAME `*_run()` loop its tab does, so nothing can drift apart. FIVE FILES: access, lock and time budget in `_frame.php`, every job as a function in `_jobs.php`, and one bundle per cadence, named by it — `every_5_minutes.php`, `hourly.php`, `daily.php` (CRON_GROUPS) —, each one cron entry with ONE shared 100-second budget, because manitu allows five entries, calls a script by its path with nothing after it and cuts each call at 120 seconds. A new job joins the bundle of its cadence; only a new cadence becomes a new file (01.10.2026) |
| `migrations/` | numbered `.sql`, run in name order — file names are the applied-migration ledger and NEVER rename |
| `tests/` | `php tests/x.php` or `node tests/x.mjs`; green means the last line is `All good.` |

**AT THE DESK AN AREA'S PAGE RUNS INSIDE A FRAME** (25.11.2026:
„mach die Rahmenseite zum normalen Eingang"). The map, the Fundbrowser, a
module's area and the administration are still pages of their own, with
their own addresses — the phone opens them as such. At the desk (a wide
window AND a mouse) whoever asks for one lands in `pages/shell.php`, which
holds them side by side in frames, so a change of area is a change of view
and full screen holds. `includes/shell.php` says how one gets in,
`assets/js/shell.js` holds the frames, `assets/js/shellframe.js` is the
part every page carries. What follows for all code: a page must work as
the WINDOW and as a FRAME alike. `location.href = …` to another area still
arrives (the frame page puts it right), `shellOpen()` is the way without a
reload; what must take the whole window — a provider's sign-in, a foreign
site — says `target="_top"`. And a test or a look at ONE page alone says
`sessionStorage['shell.off'] = '1'` in its tab first.

## How much to test

**ONLY WHAT WAS ACTUALLY TOUCHED, and for a small change nothing at all.**
The call of 19.08.2026, and the reason is the maintainer's time: a full round of
tests after a reworded label costs minutes and answers a question nobody
asked. Whoever runs everything every time is not being careful, they are
being expensive.

The scale:

| the change | what it takes |
|---|---|
| text, a label, a color, a spacing | `php -l` on the file, or `node --check` — nothing more |
| markup and styles of one component | that, plus a look at the thing itself |
| logic inside one file | the test that belongs to that file, if there is one |
| a contract — API fields, SQL, the service worker, migrations | the tests that cover it, and a measurement against reality |

**A test belongs to what it guards, not to the moment.** `tests/columns.php`
matters when an SQL string was touched, `tests/serviceworker.mjs` when
caching rules were, `tests/sizes.php` when a size was written out. None of
them has anything to say about a renamed button.

**ONE TEST NEEDS A BROWSER, and it is the only one.**
`node tests/neighbours.mjs` measures what stands side by side in a row and
insists it is the same height — the question is not what a stylesheet says
but what the browser draws, and that cannot be read out of a file. It
drives the Chrome on the machine through the DevTools protocol over the
WebSocket Node brings itself: nothing to install, no package.json. In
exchange it wants more than the others — the local server answering, Chrome
in one of the usual places, and the demodata fixture, because it logs in as
anna. It names whichever of the three is missing. Run it after touching
sizes, spacing or the shape of a form row; `tests/sizes.php` stays the fast
static twin and catches a size written out by hand.

**Measuring beats running the suite** where a claim is at stake: what the
provider really answers, what the column really holds, what the browser
really draws. That is worth the minutes; a green line that was green before
the change is not.

**Measure against `admin/demodata.php`, not against a hand-made fixture.**
`php admin/demodata.php --new` builds a permanent local set — three
accounts (`anna@demo.local`, password `Demo-2026`), two Mappen with
species lists in them and a loose list beside, points including one in the trash and one
without a list, a friendship, a group, an AREA WITH AN OUTLINE and, above
all, SOMEONE ELSE'S LIST SHARED WITH ANNA — since 09.10.2026 in all three
kinds of „Listen teilen": Ben's list for her with ticks, her „Waldbrände"
worked in together with Ben, her „Pilze" (in the Mappe „Meine Funde")
for her group through a filter (before migration 0160 the fixture falls back to the old boxes).
The shared list is why the file exists: a throwaway user without a shared
list never reaches the one line that draws a hung-in list's shape
server-side, and on 20.08.2026
exactly that line took the whole administration down — the page broke off
before the scripts, so no tab reacted and the area list stayed on its
placeholder. It looked like a hang and was a crash. Whatever the fixture
lacks, no test can see. The protected-area polygons are the exception:
they live in the browser's Cache Storage and belong to a device, so they
are fetched in the "Offline" tab and cannot be seeded.

## Design

**Colors come from `:root`** in `app.css` — `--ground`, `--ground-2/3`,
`--border`, `--text`, `--text-quiet`, `--accent`, `--accent-light`,
`--ok`, `--warn`, `--error`, plus `--radius` and `--font`. The last
three are meaning colors — green says yes/done, orange warns, red means
error or danger — and never take the accent's place: the accent is the
user's chosen dress, a meaning color is a statement. A literal color value in
a rule is only right when it depicts something real (a tree species, a rock
type), never a surface.

**A message about a DEED is a toast, always** (22.08.2026).
Whatever answers "how did my click go" — saved, moved, did not work, is
gone — floats bottom right through `toast()` from `assets/js/toast.js`
and disappears by itself. Never into the document flow: a block there
pushes the page down under the reading eye and stays until the next load,
so a finished action goes on looking like an open one. Server-side the
same thing: `notice($kind, $text, float: true)` marks a block, and
`toast.js` lifts it out at load. Every `*_process()` result is marked.

What STAYS in the flow is what describes a state — „Freunde brauchen die
Migration 0041". It has to stand until somebody acts on it, because it is
the reason a whole tab looks empty. The line does not run along the kind:
„Diese Gruppe gibt es nicht mehr." is an error and still floats.

The map keeps its own bar (`status()` in `map.js`): it lies over the map,
dodges the sidebar and knows where a message came from. Two surfaces, two
mechanisms — a shared one would have to know about both.

**The toasts are the exception to the colour rule.** `.toast` and its
three kinds carry written-out values instead of tokens
(22.08.2026): a message floating OVER the page is not a surface of it, it
has to read on paper-white and coal-black alike, and green must mean
"done" in all seven themes — through a token the amber theme would hand a
confirmation the warning colour. Everything else keeps taking its
surfaces from `:root`.

**The import conflicts are the second exception**
(26.09.2026: „dass wir da mal etwas bunt werden bei den Konflikten").
Each kind of conflict in the import preview — number twice, other
finder, you are the determiner, other number, perhaps renamed — wears
its own colour on its chip and its filter (`--kind-*` in `:root`, a
darker set for the light themes). They are categories to tell apart in
a long table and mean nothing beyond their kind, so they differ in
lightness as well as hue, and every chip carries its name too. The
meaning colours keep their meanings beside them: green comes in, grey
changes nothing, red is the one error to mend.

**States are named `is-`**: `is-active`, `is-open`, `is-collapsed`,
`is-unsaved`. Without exception.

**860 px is the phone breakpoint.** Other breakpoints exist, but each
belongs to exactly one component (a button row that wraps). Whoever means
"phone" takes 860.

**LISTS ARE FLAT; A MAPPE ONLY GATHERS THEM** (11.10.2026,
migration 0168, `includes/folders.php`). Every list carries ALL its
settings itself — colour, shape, species list, fields, who sees it and
what they may do. A Mappe is a name that gathers lists in the sidebar,
Organisieren and the point window's list choice, and nothing else: no
settings, no points, no share, no Mappe inside a Mappe („Die Mappe hat
überhaupt keine Funktion, außer optisch zusammengefasst"). It is a table
of its own, not a kind of list, so no place that reads lists has to learn
to skip it. Mappen and lists outside a Mappe stand in ONE row by
sort_order; a Mappe folds like the old headings did, its box switches all
its lists, its fold state lives in `collapsedLists` under `'f' + id`. It
replaced the list tree (sublists to any depth, headings that held nothing
and passed their settings down); the button „Mappen umstellen"
(`folders_convert_content.php`) turned every heading into a Mappe and gave
each list below a COPY of what it had inherited. Whoever finds a feature
that wants a setting „for the whole Mappe" builds it per list — the
sharing window has no „für alle Listen der Mappe" on purpose.

**Trees indent via `--depth`**, a number on the element, not a class per
level. The indent sits on the row so everything else stays in one flush
line.

**Three symbols for three farewells** (the maintainer asked what the bucket on
a foreign group does, 22.08.2026 — and whoever has to ask has been told
something wrong). The BUCKET means a record ceases to exist for everyone
(delete your own group, end a friendship). The DOOR (`sign-out`) means
you step out and it carries on without you (leave a group, leave a
container). The CROSS (`close`) dismisses something pending (reject or
withdraw a request) — the person stays and can be asked again tomorrow.
The colour is a separate question: all three stay red where the step
cannot be undone alone. A FOURTH since 23.11.2026, for the Play review:
the BLOCK sign (`block`) ends a friendship — it took the bucket's place
in the friend row, one button for both („weil beides dasselbe ist") —
and keeps the two from asking each other again until it is lifted.

**A FIND IS A SHARED RECORD, AND A PICTURE BELONGS TO WHOEVER TOOK IT**
(the model of 11.09.2026, built in six steps the same day and the
next). Whoever attaches a picture to somebody else's find becomes a
CO-OWNER (`map_point_owners`; measuring or editing a field does not —
„das ist die Arbeit"). The HOLDER stays one (`map_points.user_id`), so
every path that reads „my points" is untouched; co-owners reach the find
as a foreign point marked `co_owned`, in their own list („jeder mit seiner
eigenen Ablage"), and see EVERY picture of the circle with the
photographer's name on it. Every co-owner with a cloud holds the complete
picture set as his own COPY (`map_cloud_copies`, the worker in
`includes/cloud_copies_content.php`, tab Nachtragen → Kopien, cron twin) —
a picture is ONE record (title, scale, measures live once, on the origin
row), only its bytes are replicated. NO CLOUD, NO COPY: the server never
holds one; whoever connects a cloud later is backfilled, and so is a guest
whose rows are claimed. The photographer who deletes his picture deletes
it EVERYWHERE — against the never-lose-work rule, on purpose: the tidying
of a shared find has to run in sync, or every collaborator weeds the
guest's dozens of pictures himself.

LEAVING IS THE DOOR, NOT THE BUCKET. A co-owner who presses the trash
steps out into his own trash (reversible; „Endgültig" forgets him there
and removes his copies from his cloud, his own pictures stay). A holder
who presses it HANDS OVER to the most senior co-owner and steps out the
same way; only a holder without co-owners reaches the bucket. The rule
sits in `point_delete()` because every trash path runs through it, and
the window shows the door before anyone presses.

**SHARED IS SIGHT AND WORK, NEVER OWNERSHIP** (08./09.10.2026,
„Listen teilen", `includes/list_shares.php`, migration 0160). Whoever
shares, shares HIS OWN list — with the friends and own working groups he
ticks in the window „Liste bearbeiten", each with its own rights (a tick
per right and per field, no levels) and its own filter (the sidebar's
points filter, evaluated on the server so a point one may not see never
reaches one's device). Nobody hangs anything into somebody else's share.
Points and pictures stay their maker's; removing somebody, his leaving and
the end of a friendship change what he SEES, never what he owns — whoever
set points into a list takes them home with him, and a find shared by
co-ownership SPLITS into two when the friendship ends (point_owners_split).
„Gemeinsam bearbeiten" is the tick `own_points`; when a joint list's owner
goes, whoever has worked in it longest becomes its Listenadministrator.
Every new edge case is tested against this sentence first. Pictures are
pictures: a picture right reaches voucher photos and cloud recordings
alike.

**A JOINT LIST IS A SMALL WORKING LIST, NEVER A POOL OF FINDS**
(22.09.2026, after the three steps of „gemeinsam bearbeiten"
went live). Joint lists (`joint` on `map_container_lists`, since 0160
following the recipients' tick `own_points`,
`includes/joint_lists.php`) exist for what a group tends together:
areas with an outline, burn sites for the pyrophilous first settlers,
spots anyone may rate, move or refine — few points, every change a chat
line, the list handed on rather than deleted. They are NOT the way to
map together. Whoever maps a meadow with others keeps their finds in
their own main list and opens a share with an area filter on the
meadow: everyone sees exactly the other's finds from there, and every
list stays cleanly its finder's („da kann man immer noch sauber
trennen"). The help says so in its chapter Freigaben (pages/help.php,
#freigaben); whoever designs a feature around
joint lists designs it for the working list, not for 1,800 finds.

ONE DELIBERATE TWIN: `includes/measures.php` repeats the measurement
arithmetic the photo window and the plates share
(`assets/js/measurestats.js`: `measureUmPerPx`, `rectDims`, `frameValues`,
`measureRows`, `valueLines`) function for function, because the find
report is a page the server writes and cannot call the browser.
`tests/measures.php` holds it to hand-computed numbers down to the range
notation — whoever changes one side changes the other, and the test is
the tripwire.

A SECOND, SMALLER TWIN (25.09.2026): `mykis_open_marks()` in
`includes/mykis.php` reads the open marks of a title — cf., aff., sp.,
s. l., agg., and s. str. as their opposite — exactly as `openMarksIn()`
in `assets/js/identification.js` does, because the DGfM delivery runs on
the server and in a cron job. `tests/export.php` holds it to the cases
and traps of `tests/identification.mjs` (affinis, splendens, Linné's
„L."); a new spelling goes into both files and both tests.

**Dangerous buttons** carry `button danger` and ask via `data-confirm`
(see `dialogs.js`) — with a text that says what will be lost, not merely
"Wirklich?".

**Concluding buttons are right-aligned.** Save, close, cancel — everything
that sits below the content it concludes goes to the right
(`.button-row.right`; in dialogs `.dialog-footer` carries the same built
in). Not every `.button-row` concludes something: if it lines up peer
actions side by side, it stays left-aligned.

**SAVING IS THE LAST THING IN A WINDOW** (24.08.2026, after
finding it twice: „unten wird gespeichert, nicht mittendrin"). Every
modal concludes in its `.dialog-footer`, and NOTHING follows it —
whoever puts a save button in the middle invites saving before the
settings below it were even seen. Where the window carries further
forms of its own (the excursion's co-leaders, the guest's appearance),
the footer button reaches its form from outside with `form="…"` instead
of moving up: HTML knows no nested forms, but it does know that.
AND THE FOOTER STAYS IN SIGHT (12.11.2026: „dass die
Schaltelemente unten immer sichtbar sind, nur der Inhalt zwischendrin
dann anfängt zu scrollen"): a window taller than the screen scrolls
its content UNDER a footer that sticks to the bottom — the class
`pinned-footer` on the `<dialog>` (app.css; map.css for the list
windows' foot at the desk). A new modal with a footer gets the class.

**Every icon comes from `includes/icons.php`** — `icon('name')` in PHP,
`icon()` / `iconHtml()` from `assets/js/icons.js` in the browser (which
receives the drawings through the `<template>` block of `icon_stock()`
instead of carrying them a second time). Icon names are English like all
code (22.08.2026 — they are chrome, chosen by code and stored
nowhere). A new icon is registered there, not drawn at the call site: the map sheet once existed three times in the project and the clock
twice — and the two clocks had already drifted apart. `tests/icons.php`
hunts for `<svg>` outside the library and keeps the named exceptions on
record (marker shapes, Leaflet icons).

**The marker shapes of the lists are the second library**, in
`includes/shapes.php` — circle, mushroom, deer, lichen. They do not live
with the icons because they are content, not chrome: the user picks them
per list. Same path, same rule — `shape_point($symbol, $farbe)` in PHP,
`shapePoint()` from `assets/js/shapes.js` in the browser via
`shape_stock()`. Every list carries its own shape (since migration 0040,
and since the Mappen nothing inherits one). Whoever adds a new shape registers it in three places
— the drawing here, `LIST_SYMBOLS` in `data.php`, the label in
`SHAPE_NAMES` in `map.js` — and `tests/symbols.php` keeps the three in
step.

**No SVG carries its own size.** It lives in the stylesheet and there in
font size (`.icon`, 1em), so icons grow with the text; where something
else applies, the class of that spot says so. A size in the markup would
not only be redundant, it can be inert and still sit there — exactly that
was the case for four icons in the sidebar head.

**Buttons and fields have two sizes, and both live as tokens in `:root`**
(`--button-padding`, `--field-height` and their `-small` siblings). Normal
is 37 px — button and field form one line side by side; small is 31 px for
filter chips and the sidebar's date fields. Large is reserved for what a
thumb has to hit on the map (`.tool`). Whoever writes a size out instead
of taking the token gets caught by `tests/sizes.php`: `.button.small` once
sat in both stylesheets with different values, and the same button was
therefore 37 px tall on the map and 34 in the administration.

**A BUTTON LOOKS LIKE ONE, AND THE RULES BELOW HOLD EVERYWHERE**
(09.09.2026, after a whole afternoon of them: „einiges davon,
was ich gesagt habe, sind Grundsatzregeln für unser Design — das will ich
nicht nur bei Freunde, sondern überall"). They started at one plus that
was overlooked and ended as the grammar of every list in the
administration. The map and the sidebar follow only where he names the
buttons himself — asked outright on 09.09.2026 which of them should
already reach over there, he answered: none for now. Everything a page
renders through `app.css` follows all of it.

The chosen POINT row in the sidebar wears the same hairline, even on
all four sides (28.10.2026) — its old accent bar down the
left edge went, because under the frame it read as a thicker side.

- **A resting surface with a fine frame.** Not only under the pointer:
  a control that first has to be hovered to look like one is invisible to
  a thumb. Measured, the bare plus carried nine per cent of its own box in
  ink and no ground at all. THE EXCEPTION IS DENSITY, not repetition
  (the maintainer drew the line himself when asked, 09.09.2026): tightly set
  rows — the tree and the points table of Organisieren — keep their
  buttons flat, because a surface per row would turn the list into a
  keyboard and the row is what one is looking for anyway. Roomy rows,
  which often carry two lines of text, keep the surface.
- **The surface comes from the TEXT colour, not from `--ground-3`.**
  Measured twice: `--ground-3` IS the ground these controls stand on, in
  the dark theme and in the light one alike — the fill would have done
  nothing. Nine per cent of the text colour lightens on a dark ground and
  darkens on a light one, adapts to whatever surface a row happens to sit
  on, and needs no second rule for any of the seven themes. THE ACCENT IS
  NOT AN OPTION HERE: on a light theme its green stood on light and was a
  ghost. It may touch the frame under the pointer; never the ink.
- **A button with nothing but a symbol in it is square** — at whatever
  size that place calls for.
- **A button standing beside a filled area is as tall as that area.**
  For a word that is the whole answer; for a symbol it is a square AT that
  height, because a rectangle around a sign reads as a mistake.
- **A header bar is as tall as a button in it, and leaves air to its
  edge.** Never the button shrunk to fit the bar — on the phone it is 44
  and that is not up for negotiation; the bar grows instead.
- **In a row the buttons sit at the row's right end**, and they line up
  with the button in the header above them. Both edges on one line: a
  header that bleeds over a grid gap pulls its own padding back by exactly
  that much.
- **Lists stripe, they do not divide.** Zebra instead of a rule between
  rows, and BOTH tones lift off the ground — a stripe against bare page
  ground is a stripe and a hole. It holds for the person lists AND for
  the real tables (09.09.2026, asked how far it should go:
  „auch die echten Tabellen"); they all carry `table.list`, so one rule
  reaches trash, users, devices, migrations and the points table. Only
  the head keeps a line, because it closes the body off. Since
  23.09.2026 `.item-row` too (offline and protected areas), the last
  list that still divided. The stripes COUNT ONLY AMONG THE ROWS
  (`:nth-child(even of …)`) and per group, so the first row under any
  head is the quiet band — a head that counted along once put the loud
  band right under itself and stopped being a head.
- **Two lists side by side share ONE header bar**, and the divider between
  them inverts to the page ground at its height. The oldest of these
  rules (15.08.2026, for Organisieren) and the reason the rest
  were asked for.
- **A chosen row carries the surface plus a hairline of the accent**,
  damped to 55 per cent — at the groups. Chosen is the user's own doing,
  and the accent is his colour. THE SIDEBAR SAYS IT WITH A TICK BOX since
  24.09.2026 (after a draft in the browser): a box at the
  front of every list row — ticked, empty, or a dash where only part of a
  Mappe's lists are on, and a Mappe's box switches all its lists while its
  name still folds. The row only echoes the box with a half
  step of lightness and the bold name; the full step belongs to the
  heading lists there, the hairline went, and no count stands at the
  row's end any more. ORGANISIEREN TOOK THE SAME BOX on 28.09.2026 („auch
  die Hakenvariante"), for its lists and its tours — one drawing,
  `tickBox()` in `assets/js/tickbox.js`, styles in app.css — but kept
  its grounds, counts and owners; only the hairline went, and a chosen
  name turns bold.

**A PAGE IS SORTED BY WEIGHT** (23.09.2026, after reworking
Offline-Daten and the backup piece by piece: „was ist wichtig, was ist
unwichtig, was ist eine große Überschrift, wie gliedere ich
Informationen — das fehlt beim Erstellen der Seite komplett"). The pages
grew feature by feature, and every sentence and button got the same
voice, so the explanation, the decision and the footnote looked alike.
Before a block is added, it gets its place in this order:

- **The explanation first, in body size** — and whatever belongs to it
  stands WITH it: when something runs by itself, what it never does, the
  caveat that decides a choice (a protected area comes whole with ONE
  state). Such a sentence at the very end, in small print under the
  controls, is read by nobody, least of all by the one it was for.
- **Then the state, and the action that answers it right beneath** —
  the backup's table, then „Jetzt sichern".
- **Settings one changes rarely come last** — the backup folder's name.
- **Meta is a footnote**: the provider's vintage, the date of the data.
  Smaller and fainter than any sentence of the explanation
  (`.provider-currency`), never a third sentence in the same voice.
- **Loud only where there is something to do.** Sixteen accent buttons
  in a column all shout and say nothing: the standing offer is quiet
  (`subtle`), the accent belongs to the one action that is due —
  „Erneuern" when the provider really has newer data. A SAVE BUTTON IS
  QUIET UNTIL ITS FORM CHANGED (25.09.2026: „leuchten wie ein
  Weihnachtsbaum"): `data-quiet-until-changed` and `assets/js/quietsave.js`
  do it, everywhere in the administration. A window a script fills on
  opening (users, Organisieren) says `data-quiet-until-changed="open"`
  and takes its measure each time it opens. Code that sets a field
  without an event dispatches `change` on the form, or the button never
  hears of it.
- **What the page is opened for comes first.** In Offline-Daten that is
  „what do I carry?": the loaded states stand on top as their own group,
  the rest below — not hidden alphabetically among fourteen others.
- **A group's head is a surface**, dressed like `table.list thead th`
  (`.list-label`). Quiet capitals alone were tried first and overlooked
  („ist schon mal gut, aber fällt nicht auf"). A heading only where
  there is more than one block — over a single one it names it twice.
- **A thing speaks to its state.** A connected cloud tile says since
  when, not „Verbinden, zustimmen, fertig"; each provider carries a
  `hint` for before and a `connected_hint` for after.
- **What there is to choose stands in sight.** A few options are radios
  (`.choice-row` in a `fieldset.field`), never a dropdown; a settings
  tab shows its groups open under their heads, not in folds
  (25.09.2026: „nicht als Dropdown-Menü, sonst sieht man nicht die
  anderen Optionen" — and Konto lost its folds the same evening). A fold
  hides what it holds exactly as a dropdown hides its options.
- **A question is as large as its answers** (26.09.2026,
  first for the numbering window of Organisieren — „die Frage muss etwas
  markanter sein … mindestens in einer gleich großen Schrift. Und dann
  darunter die Antworten eingerückt" — then „die Fragen überall so").
  Where the user decides among answers, the question stands bold, in the
  text's size and colour, and the answers indented beneath it:
  `.field.question` for a fieldset, `.import-question` in the import's
  table, the field's name in the map's conflict window. A field's quiet
  caption stays for fields, which say what goes in. A question left open
  where it must be answered turns red INSIDE its window — a toast would
  lie behind a modal.

WHOEVER NOTICES A BREACH OUTSIDE THE TASK NAMES IT to the maintainer in a line
or two — many equally loud buttons, a key sentence at the end, a footnote
in body size — instead of passing by or redesigning unasked (the words:
„wenn dir selbst was auffällt, kannst du mich gerne darauf hinweisen").

**A FRAME MEANS „HERE YOU CAN WRITE", AND NOTHING ELSE**
(22.09.2026, after the coordinate field: „der Rahmen sagt: hier kann
geschrieben werden; ohne Rahmen sagt: kein Schreibrecht — das sollten
wir als grundsätzlichen Style beibehalten"). In the point window a field
wears its frame only while it can be typed into. Wherever the viewer
has no write right — a foreign find, a locked field, the coordinate
that is moved on the map and not typed — the value stands as plain
text: no frame, no ground, no clear cross, no dropdown arrow, no cursor,
in the text colour, and an empty one is not shown at all
(`detailReadingLook` and `.field.is-read` for foreign finds,
`.coord-row input[readonly]` for the coordinate). A control that only
looks like one is a promise the window cannot keep. Where an entry is
possible as an exception, a deliberate step opens the field — the
pencil beside the coordinate — and the frame comes with it.

**Single-line fields get a fixed height.** It would follow from padding and
line height on its own, but not identically for every field type: a date
field in Chrome brings its own innards and otherwise ends up two pixels
taller than the text field beside it.

**Multi-line fields grow with their content** and do not scroll — you
always see everything. `assets/js/autoheight.js` does that, and by itself:
it binds every `<textarea>` on load and watches the document for new ones,
so no call site has to register and none can forget. It is included once
per page — via `page_footer()` and via a side-effect import in `map.js`,
the same two paths as `dialogs.js`.

**A FOREIGN NAME IS DATA, NOT VOCABULARY.** Anything living on someone
else's server — a WFS path, a WMS layer id, a provider's field name —
looks like our words and is none. The move into English translated
`geodienste.bfn.de/ogc/wfs/schutzgebiet` into `…/protected_area` and
swisstopo's `pixelkarte-farbe` into `pixelkarte-color`; the first stopped
the protected areas from loading per state, the second left the Swiss
topographic map blank, and nothing in the project noticed for a day.
`tests/services.php` holds every external address of `map_layers()`
against a frozen record, in both directions — whoever changes one changes
the record too, and thereby states that the PROVIDER renamed something.

## Six traps that have already caught someone

1. **`[hidden]` is settled, and the trap now points the other way.** The
   browser hides `[hidden]` through its own stylesheet's `display:none`,
   the weakest rule there is — so one line at the head of `map.css` and of
   `app.css` makes ours the strongest instead:
   `[hidden] { display: none !important; }`. A `display` of ours therefore
   no longer lets an element escape its attribute, and NO ELEMENT NEEDS A
   COUNTER-RULE OF ITS OWN. Eighteen of them had grown up all the same,
   every one without effect; they went on 09.09.2026.

   WHAT STILL CATCHES SOMEONE IS THE OPPOSITE MOVE: whoever wants to SHOW
   something that carries `hidden` has to beat that line and needs
   `!important` too. `.sidebar-page[hidden] { display: flex !important; }`
   is the one place that does; the load window's progress line tried it
   without and was dead for months, unnoticed because the script takes the
   attribute off anyway.

   IT HOLDS ONLY WHERE THOSE STYLESHEETS ARE LOADED. `pages/report.php` is
   a document of its own with its own `<style>` and therefore keeps its
   counter-rule — and the line must stand in BOTH files, because the trash
   loads only `app.css` and its bulk-action bar once stood there for
   exactly that reason.
2. **`<dialog>` without `[open]`.** AND HERE NO LINE SAVES YOU — a closed
   `<dialog>` does not carry `hidden`, it is hidden by the browser
   stylesheet's `dialog:not([open]) { display: none }`, and that is again
   the weakest rule there is. A `display` of ours without the `[open]`
   condition therefore lays the closed window over the whole map.
   `.form-window` and `.list-window` sidestep it by governing their height
   through the `<form>` inside them; `.photo-window` and `.chat-window`
   hold no form and so spell the condition out.
3. **Height on the phone via `--view-height`, not `dvh`.** The keyboard
   slides over the bottom edge without the layout viewport getting any
   smaller. The number comes from `visualViewport` (`map.js`).
4. **No scroll area inside a scroll area.** A swiping finger catches
   sometimes the one, sometimes the other. Where a window already scrolls,
   the content inside it gets no height of its own.
5. **`?v=` reaches only what the page itself loads — so every page carries
   an import map.** A module imports its neighbours by their bare name
   (`'./layers.js'`), and there is no build step that could write a
   version in. `asset_import_map()` in helpers.php emits an IMPORT MAP that
   maps every file in `assets/js/` onto itself plus `?v=`, so the imports
   inherit the tag. Every file carries its own tag since 24.09.2026 — its
   modification time, through `asset_url()`, for the tags and the map
   alike — so a deployment re-fetches only the files that changed. The map
   had it alone until 30.09.2026; since then `page_header()` emits the
   same map in the head of every other page. Measured that day: map 41 of
   41 versioned, administration 42 of 42 (it was 30 of 44 bare), help 13
   of 13. Only documents of their own that load modules without imports
   (`pages/report.php`) go without.

   THE ONE RULE IS IDENTITY: the address a `<script>` tag names and the one
   the map hands out must be the SAME string, or the module is loaded twice,
   each copy with its own state. Before the administration had its map,
   exactly that ran there unnoticed — dialogs.js and toast.js once as the
   footer's versioned entry, once as a bare import. So every tag goes
   through `asset_url()`, and an inline `import` writes the BARE path
   (`url('assets/js/dialogs.js')`) and lets the map version it.

   `getAppFile()` in `sw.js` still decides by the address and not by the
   file type: WITH `?v=` cache-first, because such a URL points at one
   content for ever; WITHOUT it stale-while-revalidate, because a bare
   address may change under the same name (`tests/serviceworker.mjs`). Bare
   requests still come — a page still open from before a deployment, a
   document of its own. Whoever makes bare app files cache-first freezes
   every module on every phone, and no correction ever arrives.
6. **No „share" in an id, a class, a file name or an address the browser
   sees.** Content blockers carry cosmetic and network rules against social
   share buttons, and they reach far: on 20.08.2026 the Freigaben tab was
   display:none on the maintainer's desktop and nowhere else — half a day of
   searching (the note at the tab in pages/administration.php). „Listen
   teilen" therefore speaks of recipients and „given" in the browser
   (recipients.js, recipientfilter.js, api/v1/list_recipients.php, the
   tab `given`); server files that never reach a browser may keep the word.

## Server side

`page_header()` / `page_footer()` / `notice()` from `includes/page.php`
build every page. From `includes/helpers.php` come `h()`, which guards
every output, `url()`, which builds every path, and `csrf_field()` /
`csrf_check()` for every form.

A tool in the administration lives as `includes/<name>_content.php` with
`<name>_process()` **before** any output and `<name>_render()` after it.
Because on a page with tabs every submit reaches all of them, each form
carries a hidden `section` field by which the processing recognizes whether
it was the one meant.

## Database

Migrations are numbered `.sql` files in `migrations/` and run through the
administration (`admin/migrations.php` remains as the emergency exit in
case the administration itself trips over a missing column). MySQL cannot
roll back schema changes — every migration must have run locally first.

**The code goes live before the migration.** Deployment pushes files up;
someone applies the migration by hand afterwards. New code must therefore
survive the old schema, or it locks out the very page on which one would
apply the migration. For that there is
`column_exists($pdo, $tabelle, $spalte)`.

**MINT THE NUMBER AGAINST origin/main, NOT AGAINST THE WORKING COPY.**
Two sessions working in parallel both counted from their local ledger and
both minted 0068/0069 (excursions here, friend chat and certainty there) —
on 22.08.2026, the day several sessions ran at once. It stays harmless
exactly because runner and ledger go by the FULL FILE NAME: the order is
deterministic and every file has its own ledger row. But it is ugly and
confuses "which migration are we at", so: `git fetch` and look at
`origin/main`'s migrations before picking the next number. The duplicates
that exist keep their names — the ledger rule beats the numbering.

**The schema is English since migration 0063** — tables, columns, enum
values, and the identifier values inside rows (field keys in field_times /
original / overwritten, filter JSON keys, species source keys) moved in one
piece with the code that speaks them. The migration file names before 0063
stay German — they are the applied-migration ledger and NEVER rename. The
one 0063 could not take along: index and constraint names (no code says
them; renaming constraints means dropping and re-adding). The runner owns
its ledger (`map_migrations`) and creates it itself — 0063 deliberately
never touched the old `map_migrationen`, so the OLD tool could apply 0063
and still record it; 0064 dropped the German table afterwards.

**ONE GERMAN WORD FOUND MEANS SEARCHING FOR IT EVERYWHERE**
(22.08.2026). Whoever stumbles over a German identifier does not just fix
that spot: `git grep -in "<word>" -- . ':!migrations' ':!assets/vendor'`
across the whole project, immediately. It found a second, unnoticed
defect twice in one afternoon — "treffer" led from the friend list to the
silently broken offline species search, "gesamt" from there to
`minZoomGesamt`, which had the aerial layer querying at every zoom level.
A single find is never single: the same word was written everywhere at
the same time by the same hand.

A WRONG NAME IN AN SQL STRING STAYS INVISIBLE until the one request that
runs it: PHP sees a string, the database sees a column that is not there.
It cost the login for two days — `MAX(timestamp)` where the column was
called `zeitpunkt` (today attempted_at), in the throttle that only runs for
someone who is NOT yet logged in. `tests/columns.php` reads the columns out
of the database and holds every lowercase name in every SQL string against
them; since the schema move it guards renames and typos instead of a
language border.

## Offline

The application must keep helping in the woods, without reception. Points
come into being immediately with a provisional, negative ID and travel to
the server later via `queue.js`. What sits on the device — map tiles, name
lists — **is the user's decision**: nothing downloads unasked, and
whatever is there can be removed again piece by piece. Settings that
outlive a visit go through `settings.get/set` into a single bundle in
local storage. `assets/js/legacy.js` holds the one-time takeover of the
old German storage names and — since the schema move — of the old payload
keys, filter vocabulary and species group names inside the stored data;
pointstore.js carries its own version-2 upgrade for the bucket records. It
can all go once every device has visited once.

## Languages

**THE GERMAN SENTENCE IS THE KEY** (28.08.2026 — „damit wir
multilingual werden können"). `t()` from `includes/i18n.php` (PHP) and
`assets/js/i18n.js` (browser) wraps user-facing strings; the German text
STAYS in the code, exactly as the rule above wants it, and foreign
catalogs in `includes/lang/<code>.php` map German → target. A missing
translation falls back to German — a mixed page, never a broken one.
Placeholders are sprintf's (`%s`, `%d`); concatenated fragments cannot
be translated and must be joined into ONE sentence before wrapping. The
sweep is STAGED: en.php's section comments record which surfaces already
speak, and every new surface follows the same three moves — join
fragments, wrap in t(), add the sentences to the catalog. Language
resolution: account (`map_users.lang`, 0096) → session (`?lang=`, the
login page's switch) → Accept-Language → German.

## Three content rules

**The NAME carries the topic, the CONTENT stays neutral.** The app is
called YASAIMAPP (the call, 24.08.2026 — before that the
neutrality rule covered the name too and it was called "Karte"). Inside,
the old rule holds on: no topic in placeholders, field names or default
values — the topic emerges from what the user selects and creates. The
deliberate exceptions are all the maintainer's own dictations: the name
itself, the species-group preselection (fungi checked for whoever never
chose), and the mycological vocabulary he spelled out for the measuring
tool — the layer names (MEASURE_STRUCTURES) and the picture-name
suggestions (PHOTO_TITLE_SUGGESTIONS), both replaceable per account.

**Scientific names first**, German ones are garnish. "Deutsche Liste"
means: for Germany, not in German.

**PLAIN WORDS FOR BEGINNERS** (25.09.2026: „dass unser Text
wirklich altersgerecht ist und für absolute Anfänger gedacht, die nicht
verstehen, was du programmierst … immer eine ganz einfache Sprache").
Many in the fungus scene are older and not at home with computers, and
a sentence that lets them expect what the app does not do unsettles
them. So every text a user reads: short sentences, one thought each,
everyday words instead of the code's, and an EXAMPLE wherever behaviour
could be misread — what happens when one types „bol", and what does
NOT happen when one types nothing. The exact rule (±30 days, the find's
own day and place) belongs in the code's comment; the page says it the
way one would say it to a neighbour.

## The other half

**The recorder app lives in `~/Documents/yasaimapp-app`** — its own folder,
its own repository, deliberately OUTSIDE htdocs (a web root is no place for
a signing key, and nothing there is ever served). It exists for the one
thing this half cannot do: keep the GPS running while the screen is off.

It is a RECORDER, not a second YASAIMAPP — no map, no lists, no species
search. It speaks to `api/v1/tracks.php` with a bearer token from
Einstellungen → Geräte, and that token does not expire (auth.php,
token_create explains why).

Whoever changes `api/v1/tracks.php`, `includes/tracks.php` or the pairing
changes a CONTRACT: the app is a client that cannot be redeployed along
with a push. Its CLAUDE.md writes the contract down from the other side.

**THE PRIVACY STATEMENT IS PART OF THE UPKEEP** (20.11.2026).
`pages/privacy.php` shows `includes/privacy.de.txt` (Markdown inside, but
not `.md`: the deployment leaves every `.md` at home) — one text for the
website and the app, open without a login, and the address the Play Store
holds. Its SOURCE lies in the app's repository (`store/datenschutz.md`),
because it speaks for both halves. Whoever changes what it describes
changes the text too: an outside service the page or the server talks to,
where something is kept (this server or the user's cloud), how long
something is kept, what other people can see. The way: tell the app
session, it corrects the source, and the copy here is replaced word for
word from „Stand:" on. It was published without a lawyer's check, which
is still to come — so no sentence in it is rewritten here on the side.

## Deployment

A push to `main` triggers deployment via SFTP
(`.github/workflows/deploy.yml`); uploaded is the `git diff` against the
memo file `.ht-deployed-sha` on the server — the commit of the last
successful run, advanced only as the final command of a successful batch,
so an aborted run heals itself on the next one. The full `git ls-files`
upload remains as automatic fallback (missing or foreign memo) and as the
manual `full_run` trigger.
Before pushing, it pays to check whether a second session is working on the
same files right now — then stage individually instead of `git add -A`.
The deployment never deletes: a file renamed or removed in the repository
stays on the server and has to be removed there by hand.
