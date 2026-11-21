/**
 * Looks for calls that point into a void.
 *
 *   node tests/module.mjs
 *
 * WHY THIS HAS TO EXIST. This project has no build step: what sits in
 * assets/ goes to the browser as is. So there is also nothing that
 * notices a call to a function that no longer exists — no bundler, no
 * type check, no linter. The mistake only strikes the user.
 *
 * AND IT HAS ALREADY HAPPENED. When the server time sync was dismantled,
 * the definition of foundForSaving() went with it, the call site in
 * the save button stayed. Result: saving EVERY point was silently broken
 * — without an error message, because the call sits before the send and
 * the exception aborted the whole body. For days, across several
 * deployments, and nobody found it except the user.
 *
 * HOW IT TESTS — with strings, not with a syntax tree. A proper parser
 * would be more precise but would mean a dependency, and this project
 * does not want one. The sums still work out because the question is
 * simple: a name that gets called somewhere in the module must also be
 * introduced somewhere in it — as a function, as a constant, as an
 * import. Scopes are deliberately NOT distinguished; that yields too few
 * reports rather than too many, and a false alarm is more expensive here
 * than a gap.
 *
 * STRINGS AND COMMENTS ARE THROWN OUT FIRST. Without that, every comment
 * that mentions a function ("siehe faktenZeile(teile)") reports an error
 * — and a test you learn to look past is none.
 */

import { readFileSync, readdirSync, writeFileSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join, basename } from 'node:path';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const FOLDER = join(ROOT, 'assets', 'js');

/* EVERY SCRIPT THE BROWSER IS HANDED: the core's, and the ones a module
   brings (modules/<name>/assets/js, 21.11.2026). A module's script is held
   to the same three questions — it moved out of FOLDER, not out of reach. */
const SCRIPTS = readdirSync(FOLDER).filter(f => f.endsWith('.js')).sort().map(f => join(FOLDER, f));
if (existsSync(join(ROOT, 'modules'))) {
    for (const name of readdirSync(join(ROOT, 'modules')).sort()) {
        const folder = join(ROOT, 'modules', name, 'assets', 'js');
        if (!existsSync(folder)) continue;
        SCRIPTS.push(...readdirSync(folder).filter(f => f.endsWith('.js')).sort().map(f => join(folder, f)));
    }
}

/* What the browser brings along. Deliberately kept tight: what is missing
   here speaks up on the first run and then gets added — better that way
   round than a list that waves everything through. */
const GLOBALS = new Set([
    // Language
    'Array', 'BigInt', 'Boolean', 'Date', 'Error', 'Function', 'Intl', 'JSON',
    'Map', 'Math', 'Number', 'Object', 'Promise', 'Proxy', 'Reflect', 'RegExp',
    'Set', 'String', 'Symbol', 'TypeError', 'RangeError', 'WeakMap', 'WeakSet',
    'parseFloat', 'parseInt', 'isNaN', 'isFinite', 'encodeURIComponent',
    'decodeURIComponent', 'encodeURI', 'decodeURI', 'structuredClone', 'queueMicrotask',
    // Browser
    'AbortController', 'Blob', 'CustomEvent', 'DOMParser', 'Event', 'File',
    'FileReader', 'FormData', 'Headers', 'Image', 'IntersectionObserver',
    'MutationObserver', 'Notification', 'Request', 'Response', 'ResizeObserver',
    'TextDecoder', 'TextEncoder', 'URL', 'URLSearchParams', 'WebSocket', 'Worker',
    'alert', 'atob', 'btoa', 'cancelAnimationFrame', 'clearInterval', 'clearTimeout',
    'confirm', 'console', 'document', 'fetch', 'getComputedStyle', 'indexedDB',
    'localStorage', 'matchMedia', 'navigator', 'prompt', 'requestAnimationFrame',
    'requestIdleCallback', 'screen', 'sessionStorage', 'setInterval', 'setTimeout',
    'window', 'caches', 'createImageBitmap', 'OffscreenCanvas', 'DataView',
    'ArrayBuffer', 'Uint8Array', 'Uint8ClampedArray', 'Int8Array', 'Uint16Array',
    'Int16Array', 'Uint32Array', 'Int32Array', 'Float32Array', 'Float64Array',
    'Canvas', 'Audio', 'AbortSignal', 'ImageData', 'Option',
    // Leaflet is included as a script, not imported.
    'L',
    // Keywords that look like a call.
    'if', 'for', 'while', 'switch', 'catch', 'return', 'typeof', 'void', 'do',
    'function', 'super', 'import', 'await', 'yield', 'delete', 'new', 'in', 'of',
    'else', 'case', 'throw', 'instanceof', 'async', 'get', 'set', 'static',
]);

/**
 * Replace comments and strings with whitespace, keep the lines.
 *
 * TEMPLATES INSIDE TEMPLATES ARE READ AS WHAT THEY ARE (15.10.2026). The
 * reader used to take a backtick string flat, up to the next backtick —
 * and `a ${b ? `c` : ''} d` has its next backtick INSIDE the expression.
 * From there everything ran out of step: the text after the inner string
 * read as code, an apostrophe in it opened a quote that swallowed half a
 * file, and one script reported nine functions as „introduced nowhere" that
 * were plainly there. Now `${ … }` in a template is code again, read by the
 * same rules — strings, comments, nested templates — up to its own closing
 * brace; a call inside it counts like any other.
 */
function strip(text) {
    return stripFrom(text, 0, false).out;
}

/**
 * The reader itself. inExpression: the code of a template's `${ … }`, which
 * ends at the brace that closes it — the result says where.
 */
function stripFrom(text, start, inExpression) {
    let out = '';
    let i = start;
    const n = text.length;
    let depth = 0;

    while (i < n) {
        const c = text[i];
        const two = text.slice(i, i + 2);

        if (two === '//') {
            while (i < n && text[i] !== '\n') { out += ' '; i++; }
            continue;
        }
        if (two === '/*') {
            while (i < n && text.slice(i, i + 2) !== '*/') { out += text[i] === '\n' ? '\n' : ' '; i++; }
            out += '  '; i += 2;
            continue;
        }
        // REGEX LITERALS MUST GO TOO. coordinates.js contains
        // /\bO\b|O(?=\s*\d)/ — and the "O(" in it looks like a call.
        // Whether a "/" begins a literal or is a division is decided by
        // the preceding character: after a value only division can
        // follow, after a bracket, a comma or an operator only a pattern.
        if (c === '/') {
            const before = out.replace(/\s+$/, '').slice(-1);
            const patternPossible = before === '' || '(,=:[!&|?{};+-*%~^<>'.includes(before)
                || /\b(return|typeof|case|in|of|do|else)$/.test(out.replace(/\s+$/, ''));
            if (patternPossible) {
                out += ' '; i++;
                let inClass = false;
                while (i < n && (inClass || text[i] !== '/')) {
                    if (text[i] === '\\') { out += '  '; i += 2; continue; }
                    if (text[i] === '[') inClass = true;
                    if (text[i] === ']') inClass = false;
                    if (text[i] === '\n') break;   // a literal never runs past the line
                    out += ' '; i++;
                }
                out += ' '; i++;
                continue;
            }
        }
        if (c === '`') {
            out += ' '; i++;
            while (i < n && text[i] !== '`') {
                if (text[i] === '\\') { out += '  '; i += 2; continue; }
                if (text[i] === '$' && text[i + 1] === '{') {
                    out += '  ';
                    const inner = stripFrom(text, i + 2, true);
                    out += inner.out + ' ';
                    i = inner.end + 1;
                    continue;
                }
                out += text[i] === '\n' ? '\n' : ' ';
                i++;
            }
            out += ' '; i++;
            continue;
        }
        if (c === '"' || c === "'") {
            const end = c;
            out += ' '; i++;
            while (i < n && text[i] !== end) {
                if (text[i] === '\\') { out += '  '; i += 2; continue; }
                out += text[i] === '\n' ? '\n' : ' ';
                i++;
            }
            out += ' '; i++;
            continue;
        }
        if (inExpression) {
            if (c === '{') depth++;
            if (c === '}') {
                if (depth === 0) return { out, end: i };
                depth--;
            }
        }
        out += c; i++;
    }
    return { out, end: i };
}

/** Everything that introduces a name in this module. */
function introduced(source) {
    const names = new Set();
    const add = (pattern, group = 1) => {
        for (const t of source.matchAll(pattern)) {
            for (const name of String(t[group]).split(',')) {
                // What arrives here is one entry of a list: "a", "a as b",
                // "a: b", "a = 1". WHAT IS INTRODUCED IS ALWAYS THE LAST
                // PART — the left of ':' is the foreign key, the right is
                // the name this module works with. Without the colon the
                // checker reported every renamed destructuring as a call
                // to a function that exists nowhere.
                const clean = name.trim()
                    .split(/\s+as\s+/).pop()
                    .split(':').pop()
                    .split('=')[0]
                    .trim().replace(/[{}]/g, '');
                if (clean) names.add(clean);
            }
        }
    };

    add(/\bfunction\s*\*?\s*([A-Za-z_$][\w$]*)/g);
    add(/\bclass\s+([A-Za-z_$][\w$]*)/g);
    add(/\b(?:const|let|var)\s+([A-Za-z_$][\w$]*)/g);
    // Imports: both { a, b as c } and the default name.
    add(/\bimport\s+\{([^}]*)\}/g);
    add(/\bimport\s+(?:\*\s+as\s+)?([A-Za-z_$][\w$]*)\s+from/g);
    // Destructuring assignments — rough, but they only must not report wrongly.
    add(/\b(?:const|let|var)\s*\{([^}]*)\}\s*=/g);
    add(/\b(?:const|let|var)\s*\[([^\]]*)\]\s*=/g);
    // Named parameters and catch variables.
    add(/\bcatch\s*\(\s*([A-Za-z_$][\w$]*)/g);

    // METHOD SHORTHAND IS AN INTRODUCTION, NOT A CALL. In an object
    // literal or a class stands "patch(path, id) {" — only the curly
    // brace behind it distinguishes it from a call. Without this line
    // the test reports every second method of the project. A class's
    // STATIC method is one too ("static fromUrl(url) {", pmtiles.js) —
    // and 'static async' stacks, hence the repetition.
    add(/^[ \t]*(?:(?:static|async|get|set)[ \t]+)*\*?[ \t]*([A-Za-z_$][\w$]*)[ \t]*\([^()]*\)[ \t]*\{/gm);
    // Getters in the middle of an object literal do not stand at the start of a line.
    add(/\b(?:get|set)[ \t]+([A-Za-z_$][\w$]*)[ \t]*\(/g);

    return names;
}

let failed = 0;

/* PHASE ZERO: A REAL ESM PARSE OF EVERY MODULE — in ONE child process.
   `node --check` on a .js file parses it as COMMONJS and can wave
   through what the browser refuses. Measured 29.08.2026: cloudupload.js
   carried two missing closing parens after the language sweep, node
   --check said fine, and Chromium answered `await import()` with
   "missing ) after argument list" — every upload was dead while every
   check was green. vm.SourceTextModule parses with the module grammar
   the browser speaks; constructing it is parsing, nothing runs. */
console.log('\nEvery module parses as a module\n');
{
    const runner = join(tmpdir(), 'yasaimapp-esm-check.mjs');
    writeFileSync(runner, `
        import { readFileSync } from 'node:fs';
        const vm = await import('node:vm');
        for (const f of process.argv.slice(2)) {
            try {
                new vm.SourceTextModule(readFileSync(f, 'utf8'), { identifier: f });
                console.log('ok ' + f);
            } catch (e) {
                console.log('FAIL ' + f + ' :: ' + e.message);
            }
        }`);
    const files = SCRIPTS;
    const out = execFileSync(process.execPath,
        ['--experimental-vm-modules', runner, ...files],
        { stdio: 'pipe' }).toString();
    for (const line of out.split('\n')) {
        if (line.startsWith('ok ')) {
            console.log(`  ok    ${line.slice(3).split('/').pop()}`);
        } else if (line.startsWith('FAIL ')) {
            failed++;
            const [file, why] = line.slice(5).split(' :: ');
            console.log(`  FAILED: ${file.split('/').pop()} — ${why}`);
        }
    }
}

console.log('\nCalls that point into a void\n');


for (const path of SCRIPTS) {
    const file   = basename(path);
    const raw    = readFileSync(path, 'utf8');
    const source = strip(raw);
    const known  = introduced(source);

    // Parameter names count as introduced: they stand between brackets
    // and would otherwise be false alarms by the row.
    /* ONE LEVEL OF BRACKETS MAY STAND INSIDE (26.09.2026). A default that
       is itself a function — `ownerOf = () => null`, `extra = () => 0` —
       brought its own pair, the list no longer matched, and EVERY
       parameter of that function went unknown: collectTours' ownerOf
       stood here as a known false alarm since 24.09.2026, importrows'
       extra joined it, and collectDays' dayOfPoint followed the day its
       list got an arrow default too. The two alternatives cannot overlap
       (a bracket or none), so the pattern stays linear. */
    for (const t of source.matchAll(/\(((?:[^()]|\([^()]*\))*)\)\s*(?:=>|\{)/g)) {
        // Brackets off first: "{ angehakt, beiAenderung = null }" would
        // otherwise become " { angehakt" when splitting, and the name is
        // lost — exactly that once made the checker report
        // drawListSelection() wrongly.
        for (const part of t[1].replace(/[{}[\]]/g, ' ').split(',')) {
            const name = part.trim().split(/[=:\s]/)[0].replace(/\./g, '');
            if (/^[A-Za-z_$][\w$]*$/.test(name)) known.add(name);
        }
    }

    const open = new Map();
    // A call: a name followed by "(", not as .methode() and not after
    // "function"/"new" (that is an introduction or a constructor).
    for (const t of source.matchAll(/(^|[^\w$.])([A-Za-z_$][\w$]*)\s*\(/g)) {
        const name = t[2];
        if (known.has(name) || GLOBALS.has(name)) continue;
        // "new Foo(" is a constructor. If Foo is not introduced here, it
        // comes from the browser or from Leaflet — and that is not a
        // finding.
        if (/\bnew\s+$/.test(source.slice(Math.max(0, t.index - 8), t.index + t[1].length))) continue;
        if (!open.has(name)) {
            open.set(name, source.slice(0, t.index).split('\n').length);
        }
    }

    if (open.size === 0) {
        console.log(`  ok    ${file}`);
        continue;
    }
    for (const [name, row] of open) {
        failed++;
        console.log(`  FAILED: ${file}:${row} calls ${name}() — introduced nowhere`);
    }
}


/* ---------------------------------------------------------------------------
 * And the second question: does anyone access a let/const before it is there?
 *
 * THIS HAS ALREADY HAPPENED TWICE HERE — flaechenMoeglich and
 * ansichtWarVorgabe, both in map.js, both by the same pattern: a
 * function is called near the top of the file but stands further down and
 * there touches a declaration that comes even further down. JavaScript
 * hoists the FUNCTION, but not the let — it stays untouchable up to its
 * line.
 *
 * THE INSIDIOUS PART IS WHEN IT STRIKES. With ansichtWarVorgabe the
 * assignment sat in the LAST branch: only reachable when there is neither
 * a remembered view nor a point — so exclusively on a fresh login in a
 * fresh browser. Every machine that has already been worked on turns back
 * earlier. No trying-out finds this; the first foreign machine finds it
 * immediately.
 *
 * ONE LEVEL DEEP. If the called function calls on in turn, that is not
 * followed — that would need a call graph. Both real cases lay one level
 * deep, and a test that carries ninety percent and gets by without a
 * dependency is better than none.
 */
console.log('\nAccesses before their own declaration\n');

for (const path of SCRIPTS) {
    const file   = basename(path);
    const source = strip(readFileSync(path, 'utf8'));
    const lineOf = (i) => source.slice(0, i).split('\n').length;

    // Declarations at top level: at the start of a line, without indentation.
    const later = new Map();
    for (const t of source.matchAll(/^(?:const|let)\s+([A-Za-z_$][\w$]*)/gm)) {
        later.set(t[1], lineOf(t.index));
    }

    // Collect function bodies, with their line.
    const body = new Map();
    for (const t of source.matchAll(/^function\s+([A-Za-z_$][\w$]*)\s*\([^)]*\)\s*\{/gm)) {
        let i = source.indexOf('{', t.index), depth = 0, end = i;
        for (; end < source.length; end++) {
            if (source[end] === '{') depth++;
            else if (source[end] === '}' && --depth === 0) break;
        }
        body.set(t[1], source.slice(i, end));
    }

    // Calls at top level — not indented, hence no callback.
    let found = 0;
    for (const t of source.matchAll(/^(?:(?:const|let|var)\s+[\w$]+\s*=\s*)?([A-Za-z_$][\w$]*)\s*\(/gm)) {
        const name = t[1];
        if (!body.has(name)) continue;
        const atLine = lineOf(t.index);

        for (const [decl, declLine] of later) {
            if (declLine <= atLine) continue;
            if (!new RegExp(`(^|[^\\w$.])${decl}([^\\w$]|$)`).test(body.get(name))) continue;
            failed++; found++;
            console.log(`  FAILED: ${file}:${atLine} calls ${name}(), which touches ${decl} — `
                + `declared only at line ${declLine}`);
        }
    }
    if (found === 0) console.log(`  ok    ${file}`);
}

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
