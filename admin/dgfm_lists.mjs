/**
 * The DGfM's four lists as one CSV — data/dgfm/marks.csv.
 *
 *   node admin/dgfm_lists.mjs            fetch the current lists and rewrite the CSV
 *   node admin/dgfm_lists.mjs --offline  rebuild from the files already in the scratch folder
 *
 * 17.09.2026: „Die DGfM hat auf ihrer Seite einige Listen —
 * Verantwortungsarten und drei Listen Speisepilze, uneinheitlich bewertet
 * und Giftpilze. Diese vier Listen würde ich gerne auch ausfiltern." The
 * three food-value lists exist only as PDFs, the responsibility species as
 * a table on a web page. None of them carries a taxon number, only names,
 * and the names come in the shapes a PDF written for people has: genus
 * headings without a species („Agaricus — Champignons"), claims about a
 * whole genus („Cortinarius spp."), aggregates („Galerina marginata agg."),
 * a species wrapped over two lines, a German name continued after a blank
 * line.
 *
 * THIS SCRIPT ONLY READS AND WRITES; IT DOES NOT JUDGE. Every line of the
 * source becomes one CSV row with the name AS WRITTEN, plus what kind of
 * row it is: a species, a genus claim, or a heading to skip. Matching the
 * names against our fungus reference (map_tax_dgfm) happens in the
 * administration (includes/dgfm_marks_content.php), where the unmatched
 * ones can be shown to a person — a PDF changes a spelling now and then,
 * and a script that silently guessed would hide exactly that.
 *
 * WHY A CSV IN THE REPOSITORY and not a parser on the server: the lists
 * change once or twice a year, pdftotext is not on the web host, and a
 * file under version control shows in its diff what the DGfM changed.
 * The CSV is the ledger; this script is how it is renewed.
 *
 * Needs pdftotext (poppler) on the path; nothing else beyond Node.
 */

import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync, readFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT    = join(dirname(fileURLToPath(import.meta.url)), '..');
const OUT     = join(ROOT, 'data', 'dgfm');
const SCRATCH = join(OUT, 'source');   // the fetched originals, kept beside the CSV
const SITE    = 'https://www.dgfm-ev.de/';
const OFFLINE = process.argv.includes('--offline');

/* Where the lists are announced. The PDF file names carry their date and
   change with every issue, so the pages are read for the current link
   instead of pinning one. */
const PAGES = {
    edible:         { page: 'pilzesammeln-und-vergiftungen/speisepilze/wildpilze', file: /positivliste[_-]speisepilze/i },
    inconsistent:   { page: 'pilzesammeln-und-vergiftungen/speisepilze/wildpilze', file: /uneinheitl/i },
    poisonous:      { page: 'de/pilze-sammeln/giftpilze-vergiftungen',             file: /giftpilze-nach-taxa/i },
    responsibility: { page: 'naturschutz-und-kartierung/verantwortungsarten' },
};

async function fetchText(url) {
    const answer = await fetch(url, { redirect: 'follow' });
    if (!answer.ok) throw new Error(`${url}: ${answer.status}`);
    return answer.text();
}
async function fetchBytes(url) {
    const answer = await fetch(url, { redirect: 'follow' });
    if (!answer.ok) throw new Error(`${url}: ${answer.status}`);
    return Buffer.from(await answer.arrayBuffer());
}

/**
 * The newest matching PDF path on a page — the file names sort by date.
 * Two spellings of a link on the site: the plain path, and the attachment
 * form „?d=attachment&f=…&p=dokumente%2FPSV%2F….pdf" (the poisonous list
 * is announced only that way).
 */
function newestPdf(html, pattern) {
    const paths = [...html.matchAll(/files\/dokumente\/[^"'&\s]*\.pdf/g)].map(m => m[0]);
    for (const m of html.matchAll(/[?&](?:amp;)?p=(dokumente(?:%2F|\/)[^"'&\s]*\.pdf)/g)) {
        paths.push('files/' + decodeURIComponent(m[1]));
    }
    const fitting = [...new Set(paths)].filter(p => pattern.test(p));
    if (fitting.length === 0) return null;
    return fitting.sort().at(-1);
}

/**
 * Where a page's columns begin — read off the rows, not the header. The
 * header words do not stand where the cells do: „Deutscher Name" sits one
 * character right of the names under it, „Bemerkungen" twenty characters
 * right of the remarks. So every run of text after two or more blanks is a
 * cell start, and the positions where many rows start a cell are the
 * columns. Position 0 is always the first.
 */
function columnStarts(lines) {
    const hits = new Map();
    for (const line of lines) {
        for (const m of line.matchAll(/(?:^\s*|\s{2,})(?=\S)/g)) {
            const at = m.index + m[0].length;
            if (at > 1) hits.set(at, (hits.get(at) ?? 0) + 1);
        }
    }
    // Cluster neighbouring positions (a cell may start a character early
    // or late), keep the clusters that a good share of the rows use.
    const clusters = [];
    for (const at of [...hits.keys()].sort((a, b) => a - b)) {
        const last = clusters.at(-1);
        if (last && at - last.to <= 3) { last.to = at; last.n += hits.get(at); }
        else clusters.push({ from: at, to: at, n: hits.get(at) });
    }
    const floor = Math.max(3, lines.length * 0.12);
    return [0, ...clusters.filter(c => c.n >= floor).map(c => c.from)];
}

/** Cut a line into the page's columns: each cell goes to the last column starting at or before it. */
function cells(line, starts) {
    const out = starts.map(() => '');
    // A page may indent its whole table by one blank — the first cell still starts the line.
    for (const m of line.matchAll(/(?:^\s*|\s{2,})(\S.*?)(?=\s{2,}|\s*$)/g)) {
        const at = m.index + m[0].length - m[1].length;
        let col = 0;
        for (let i = 0; i < starts.length; i++) if (starts[i] <= at + 1) col = i;
        out[col] = out[col] ? out[col] + ' ' + m[1] : m[1];
    }
    return out;
}

/**
 * The rows of a table PDF. A row begins where the first column holds a
 * capitalised word; every other line continues the row above — a
 * lowercase first cell is the wrapped rest of the name, the other cells
 * join their column. Blank lines end nothing: the German name of
 * Lactarius torminosus goes on after one. The table of a page starts
 * under its header line and ends at the page number, or where the
 * closing notes begin („Änderungen gegenüber …").
 */
function pdfRows(text) {
    const rows = [];
    let starts = null;
    for (const page of text.split('\f')) {
        const lines = page.split('\n');
        const head = lines.findIndex(l => /Deutscher Name/.test(l));
        if (head < 0) continue;
        let body = lines.slice(head + 1)
            .filter(l => !/^\s*$/.test(l) && !/^\s*\d+\s*$/.test(l))
            .filter(l => !/^\s*er Name\b/.test(l) && !/^\s+(BArt|SchV)\s*$/.test(l));
        const stop = body.findIndex(l => /^\s*Änderungen gegenüber/.test(l));
        if (stop >= 0) body = body.slice(0, stop);
        // A short last page (two rows under the header) gives the
        // clustering nothing to count; it keeps the columns of the page
        // before, which the same table laid out.
        const fresh = columnStarts(body);
        if (fresh.length > 1 || starts === null) starts = fresh;
        for (const line of body) {
            const c = cells(line, starts);
            const first = c[0];
            if (/^Syn\.?:?\s/.test(first)) continue;   // a synonym note under the row above — the reference knows synonyms itself
            if (first !== '' && /^[A-ZÄÖÜ]/.test(first)) {
                rows.push(c);
            } else if (rows.length > 0) {
                const row = rows.at(-1);
                for (let i = 0; i < c.length; i++) {
                    if (c[i] !== '') row[i] = (row[i] ? row[i] + ' ' : '') + c[i];
                }
            }
        }
    }
    return rows;
}

/** „Stand 01.05.2026" → 2026-05-01. */
function standOf(text) {
    const m = /Stand\s+(\d{2})\.(\d{2})\.(\d{4})/.exec(text);
    return m ? `${m[3]}-${m[2]}-${m[1]}` : '';
}

/**
 * What kind of row a name is. A single word is a heading (the PDFs group
 * their species under the genus, and the genus line says nothing). „spp."
 * claims the whole genus — sometimes two at once („Coprinellus,
 * Coprinopsis spp."). Everything else names a species; agg. and s. l.
 * are dropped, they widen a name the reference does not know widened.
 */
function classify(name) {
    const clean = name.replace(/\s+/g, ' ').replace(/,\s*$/, '').trim();
    if (/\bsubgen\.|\bsect\./i.test(clean)) return { kind: 'heading', name: clean };
    // „Cortinarius spp.", „Hebeloma spec.", and „Polyporus s.l." — a genus
    // taken broadly is still the whole genus.
    const spp = /^([A-Z][a-z]+(?:\s*,\s*[A-Z][a-z]+)*)\s+(spp?\.|spec\.|s\.\s?l\.?)$/.exec(clean);
    if (spp) {
        return { kind: 'genus', name: clean, genera: spp[1].split(/\s*,\s*/).filter(Boolean) };
    }
    // A species is a genus and a lowercase epithet; a line of capitalised
    // words („Inocybe, Inosperma") is a heading.
    const m = /^([A-Z][a-z]+) ([a-z][a-z-]+)(.*)$/.exec(clean);
    if (!m) return { kind: 'heading', name: clean };
    const species = [m[1] + ' ' + m[2]];
    // „Craterellus sinuosus, undulatus" — two species of one genus in one line.
    for (const more of m[3].matchAll(/,\s*([a-z][a-z-]+)\b/g)) species.push(m[1] + ' ' + more[1]);
    return { kind: 'species', name: clean, species };
}

/** One CSV field, quoted when it has to be. */
function field(v) {
    const s = String(v ?? '');
    return /[;"\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
}

async function main() {
    mkdirSync(SCRATCH, { recursive: true });
    const out = [];
    const today = new Date().toISOString().slice(0, 10);

    // --- the three PDFs
    for (const list of ['edible', 'inconsistent', 'poisonous']) {
        const { page, file } = PAGES[list];
        const pdfPath = join(SCRATCH, list + '.pdf');
        if (!OFFLINE) {
            const html = await fetchText(SITE + page);
            const rel  = newestPdf(html, file);
            if (!rel) throw new Error(`no PDF for ${list} on ${page}`);
            writeFileSync(pdfPath, await fetchBytes(SITE + rel));
            console.log(`  ${list}: ${rel}`);
        } else if (!existsSync(pdfPath)) {
            throw new Error(`${pdfPath} missing — run without --offline first`);
        }
        const text  = execFileSync('pdftotext', ['-layout', pdfPath, '-']).toString();
        const stand = standOf(text) || today;
        let n = 0;
        const rows = pdfRows(text).map(row => ({ row, c: classify(row[0]) }));
        for (const [i, { row, c }] of rows.entries()) {
            const [name, german, remark = '', fourth = ''] = row;
            if (c.kind === 'heading') continue;
            /* A GENUS ROW ABOVE ITS OWN SPECIES IS A HEADING, not a claim —
               the Positivliste writes „Polyporus s.l. — Stielporlinge" and
               lists the edible Polyporus below it, exactly as „Agaricus —
               Champignons" does without the s.l. The claims stand the
               other way round: „Russula spp." comes AFTER the named
               Russula, as the rest of the genus. Read off the order, not
               judged (18.09.2026: Russula nigricans stood as a
               poisonous mushroom). */
            const next = rows[i + 1]?.c;
            if (c.kind === 'genus' && next?.kind === 'species'
                && c.genera.some(g => next.species.some(sp => sp.startsWith(g + ' ')))) continue;
            // The poisonous list's fourth column is the syndrome; the other
            // two carry the protection and Red List marks there (or in the
            // remark) — kept as the remark's tail, they are words for people.
            const syndrome = list === 'poisonous' ? fourth : '';
            const note = list === 'poisonous' ? remark : [remark, fourth].filter(Boolean).join(', ');
            if (c.kind === 'genus') {
                for (const genus of c.genera) {
                    out.push([list, c.name, genus, '', german, '', note, syndrome, stand]);
                    n++;
                }
            } else {
                for (const species of c.species) {
                    out.push([list, c.name, '', species, german, '', note, syndrome, stand]);
                    n++;
                }
            }
        }
        console.log(`  ${list}: ${n} rows, Stand ${stand}`);
    }

    // --- the responsibility species: a table on the page
    {
        const htmlPath = join(SCRATCH, 'responsibility.html');
        if (!OFFLINE) writeFileSync(htmlPath, await fetchText(SITE + PAGES.responsibility.page));
        const html  = readFileSync(htmlPath, 'utf8');
        const table = /<table[\s\S]*?<\/table>/.exec(html)?.[0] ?? '';
        const strip = s => s.replace(/<[^>]+>/g, '').replace(/&nbsp;| /g, ' ')
            .replace(/&amp;/g, '&').replace(/\s+/g, ' ').trim();
        let n = 0;
        for (const tr of table.matchAll(/<tr[\s\S]*?<\/tr>/g)) {
            const tds = [...tr[0].matchAll(/<t[dh][^>]*>([\s\S]*?)<\/t[dh]>/g)].map(m => strip(m[1]));
            if (tds.length < 6) continue;
            const [name, german, , , , ver] = tds;
            const value = ver.trim();
            // „!" and „!!" are the statement; „?" and „(!)" are the authors
            // still thinking, and the legend lines have no mark at all.
            if (!/^!{1,2}$/.test(value)) continue;
            // Genus and epithet; the authors in between are dropped. A
            // variety stays only where it is a real one — „var. lazulinum"
            // — the nominate variety („Entoloma chalybaeum … var.
            // chalybaeum") IS the species, and the reference lists it so.
            const words = name.split(' ');
            const variety = /\svar\.\s+([a-z-]+)/.exec(name);
            const species = words.slice(0, 2).join(' ')
                + (variety && variety[1] !== words[1] ? ' var. ' + variety[1] : '');
            out.push(['responsibility', name, '', species, german, value, '', '', today]);
            n++;
        }
        console.log(`  responsibility: ${n} rows (page read ${today})`);
    }

    const header = ['list', 'name', 'genus', 'species', 'german', 'value', 'remark', 'syndrome', 'source_date'];
    const csv = [header, ...out].map(r => r.map(field).join(';')).join('\n') + '\n';
    writeFileSync(join(OUT, 'marks.csv'), csv);
    console.log(`\n${out.length} rows → data/dgfm/marks.csv`);
}

main().catch(e => { console.error(e.message); process.exit(1); });
