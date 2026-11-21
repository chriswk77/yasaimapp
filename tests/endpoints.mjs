/**
 * Holds the browser and the interface to the same names.
 *
 *   node tests/endpoints.mjs
 *
 * WHY THIS HAS TO EXIST. tests/module.mjs already looks for calls that
 * point into a void — but only WITHIN a module. The call api.senden('fotos')
 * points past the edge of JavaScript, at a file in api/v1/. No test looked
 * there, and nothing else can: there is no build step that resolves the
 * name, and the browser only finds out in the woods.
 *
 * AND IT HAS ALREADY HAPPENED, twice in one commit. The English rename
 * moved api/v1/fotos.php to photos.php and changed the fields the endpoint
 * reads from 'bild'/'vorschau' to 'image'/'preview' — photos.js kept
 * sending the old names to the old address. The upload got the hoster's
 * HTML 404 for weeks. The voucher photo stayed under its hourglass, and
 * with the old rule about final rejections it was deleted from the store
 * on top of that. The camera route is exactly the one that gets used far
 * from any laptop, so nobody sees it early.
 *
 * TWO QUESTIONS, both answerable with strings:
 *   1. Does every section the browser calls exist as api/v1/<name>.php?
 *   2. Does every file that photos.js appends arrive under a name the
 *      endpoint actually reads out of $_FILES?
 *
 * The second is deliberately narrow. Multipart is the only place where a
 * misspelt field name yields neither an error nor a warning — PHP simply
 * hands over an empty array. Ordinary JSON fields stay out of it; they
 * would need a parser, and a test with false alarms is worse than none.
 */

import { readFileSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const JS   = join(ROOT, 'assets', 'js');
const API  = join(ROOT, 'api', 'v1');

let failed = 0;
function fail(text) { failed++; console.log('  FAILED: ' + text); }

/* Strings and comments stay in for once: the names being looked for ARE
   string literals. What a comment mentions in passing is caught by the
   pattern only when it looks like a real call — that is rare enough. */
const sections = new Set(readdirSync(API)
    .filter(n => n.endsWith('.php'))
    .map(n => n.slice(0, -4)));

console.log('Sections the browser calls');
const CALLS = [
    // api.get('points'), api.senden('photos', …), api.url('species_list')
    /\bapi\.(?:get|post|patch|remove|senden|url)\s*\(\s*'([a-z_]+)'/g,
    // request('cloud.php?id=…') — the cloud upload goes past the queue and
    // therefore names its file itself.
    /\brequest\s*\(\s*[`']([a-z_]+)\.php/g,
];
let found = 0;
for (const file of readdirSync(JS).filter(n => n.endsWith('.js'))) {
    const source = readFileSync(join(JS, file), 'utf8');
    for (const pattern of CALLS) {
        for (const m of source.matchAll(pattern)) {
            if (sections.has(m[1])) continue;
            const line = source.slice(0, m.index).split('\n').length;
            fail(`assets/js/${file}:${line} calls api/v1/${m[1]}.php — that file does not exist`);
            found++;
        }
    }
}
if (found === 0) console.log(`  ok    every called section exists (${sections.size} in api/v1/)`);

console.log('\nFile fields of the photo upload');
const photosJs  = readFileSync(join(JS, 'photos.js'), 'utf8');
const photosPhp = readFileSync(join(API, 'photos.php'), 'utf8');

/* Three arguments to append() means a file: name, blob, file name. */
const sent = [...photosJs.matchAll(/\bf\.append\(\s*'([a-z_]+)'\s*,[^;]*?,\s*'[^']*'\s*\)/g)]
    .map(m => m[1]);
const read = new Set([...photosPhp.matchAll(/\$_FILES\[\s*'([a-z_]+)'\s*\]/g)].map(m => m[1]));

if (sent.length === 0) fail('photos.js appends no file at all — has the upload moved?');
for (const name of sent) {
    if (read.has(name)) continue;
    fail(`photos.js sends the file '${name}', api/v1/photos.php reads only: ${[...read].join(', ') || '(nothing)'}`);
}
if (failed === 0) console.log(`  ok    ${sent.join(', ')} — read on both sides`);

console.log('\nWhat the browser reads out of an answer');

/* THE SAME MISTAKE ONE LEVEL DEEPER. The address can be right, the field
   names of the upload can be right, and the answer still be read under a
   name nobody sends: cloudupload.js and map.js asked for answer.dateien
   while the endpoint had long been returning 'files'. The recording landed
   at pCloud, the browser then showed an empty list — and again nothing
   anywhere complained, because reading a missing property yields undefined
   and `|| []` swallows it.

   HOW IT BINDS THE TWO SIDES. Only a variable that demonstrably comes out
   of request('<name>.php…') counts, and only for the next few lines after
   its assignment. That is deliberately narrow: cloudupload.js also has a
   variable called 'answer' holding pCloud's reply, and pCloud sends
   completely different names. A test that mixed the two would report a
   mistake that is none — and one learns to look past those. */
const WINDOW = 20;

/* WHAT AN ENDPOINT PASSES THROUGH FROM A FUNCTION.
 *
 * The scan below reads the endpoint FILE and collects every 'key' => it
 * finds there. That is deliberately coarse and misses one shape: an
 * endpoint that hands a function's return value straight on —
 * json_response(excursion_guest_update(...)) — carries no key of its own,
 * and every field of that answer was reported as missing.
 *
 * Three false alarms stood here for days because of it, and a test that
 * cries wolf is worse than none: one learns to look past it, and then the
 * real one goes past too.
 *
 * A RECORD, NOT A LOOSENING. Following the call would mean reading the
 * includes as well, and then the test would find every key in the project
 * and never report anything again. Named here instead — with the source,
 * so the next hand can check it. Whoever adds a line says WHY. */
const PASSED_THROUGH = {
    // includes/excursions.php, excursion_guest_update():
    //     return ['name' => $name, 'renamed' => $renamed];
    excursion_guest: ['name', 'renamed'],
    // includes/data.php, point_load() → point_prepare(): POST answers with
    // the created point, 'id' first among its keys (collectionui.js reads it
    // after „Art hinzufügen", 15.10.2026).
    points: ['id'],
};
const ASSIGN = /\bconst\s+([A-Za-z_$][\w$]*)\s*=\s*await\s+request\s*\(\s*[`']([a-z_]+)\.php/g;

let checked = 0;
for (const file of readdirSync(JS).filter(n => n.endsWith('.js'))) {
    const source = readFileSync(join(JS, file), 'utf8');
    for (const m of source.matchAll(ASSIGN)) {
        const [, variable, section] = m;
        const endpoint = join(API, `${section}.php`);
        if (!sections.has(section)) continue;   // already reported above

        /* Every key the endpoint puts into a json_response([...]). Read
           coarsely on purpose: what is not in there does not exist. */
        const php = readFileSync(endpoint, 'utf8');
        const sent = new Set([
            ...[...php.matchAll(/'([a-z_]+)'\s*=>/g)].map(x => x[1]),
            ...(PASSED_THROUGH[section] ?? []),
        ]);

        const from = source.slice(0, m.index).split('\n').length;
        const lines = source.split('\n').slice(from - 1, from - 1 + WINDOW);
        const read = new RegExp(`\\b${variable}\\.([a-z_]+)\\b`, 'g');

        for (const [offset, line] of lines.entries()) {
            for (const hit of line.matchAll(read)) {
                checked++;
                if (sent.has(hit[1])) continue;
                fail(`assets/js/${file}:${from + offset} reads ${variable}.${hit[1]} — `
                    + `api/v1/${section}.php never sends that name`);
            }
        }
    }
}
console.log(`  ok    ${checked} read(s) against what the endpoints send`);

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
