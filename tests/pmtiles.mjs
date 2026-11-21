/**
 * Tests the PMTiles reader (assets/js/pmtiles.js).
 *
 *   node tests/pmtiles.mjs
 *
 * AGAINST A REAL ARCHIVE, because the two things that can go wrong are
 * the ones a hand-made fixture would share with the reader: the Hilbert
 * numbering and the directory's varints. Mapterhorn serves the same tiles
 * twice — whole on tiles.mapterhorn.com, and inside the archives on
 * download.mapterhorn.com that area downloads read by range. A tile the
 * reader finds in the archive must be byte for byte the one the tile
 * server hands out, at several levels and in a leaf directory's depth.
 *
 * Needs the network; without it the checks are skipped, not failed.
 */

import { PMTiles, tileId } from '../assets/js/pmtiles.js';

let failed = 0;
let skipped = 0;
const check = (ok, what) => {
    if (ok) console.log(`  ok    ${what}`);
    else { failed++; console.log(`  FAILED: ${what}`); }
};

console.log('Tile numbers (spec examples):');
check(tileId(0, 0, 0) === 0, '0/0/0 is 0');
check(tileId(1, 0, 0) === 1, '1/0/0 is 1');
check(tileId(1, 0, 1) === 2, '1/0/1 is 2');
check(tileId(1, 1, 1) === 3, '1/1/1 is 3');
check(tileId(1, 1, 0) === 4, '1/1/0 is 4');
check(tileId(2, 0, 0) === 5, '2/0/0 is 5');
check(tileId(12, 3423, 1763) === 19078479, '12/3423/1763 is 19078479');

const column = (lon, z) => Math.floor((lon + 180) / 360 * 2 ** z);
const row = (lat, z) => Math.floor((1 - Math.asinh(Math.tan(lat * Math.PI / 180)) / Math.PI) / 2 * 2 ** z);

// Vogelsberg: the z13+ archive of its z6 tile, and the planet file below 13.
const SPOTS = [
    { archive: '6-33-21.pmtiles', z: 16, lon: 9.21, lat: 50.51 },
    { archive: '6-33-21.pmtiles', z: 14, lon: 9.21, lat: 50.51 },
    { archive: '6-33-21.pmtiles', z: 13, lon: 8.48, lat: 50.21 },
    { archive: 'planet.pmtiles', z: 10, lon: 9.21, lat: 50.51 },
];

console.log('Tiles from the archive equal the tile server\'s:');
const archives = new Map();
for (const spot of SPOTS) {
    const x = column(spot.lon, spot.z);
    const y = row(spot.lat, spot.z);
    const name = `${spot.archive} ${spot.z}/${x}/${y}`;
    try {
        if (!archives.has(spot.archive)) {
            archives.set(spot.archive, PMTiles.fromUrl(`https://download.mapterhorn.com/${spot.archive}`));
        }
        const reader = archives.get(spot.archive);
        const [fromArchive, served] = await Promise.all([
            reader.tile(spot.z, x, y),
            fetch(`https://tiles.mapterhorn.com/${spot.z}/${x}/${y}.webp`).then((r) => (r.ok ? r.arrayBuffer() : null)),
        ]);
        if (!served) { skipped++; console.log(`  ----  skipped (tile server has none): ${name}`); continue; }
        const a = new Uint8Array(fromArchive ?? new ArrayBuffer(0));
        const b = new Uint8Array(served);
        check(a.length === b.length && a.every((v, i) => v === b[i]),
            `${name}: ${a.length} bytes, the same as served`);
    } catch (e) {
        if (/fetch failed|ENOTFOUND|ECONN/.test(String(e?.cause ?? e))) {
            skipped++;
            console.log(`  ----  skipped (no network): ${name}`);
        } else {
            check(false, `${name}: ${e.message}`);
        }
    }
}

const reader = archives.get('6-33-21.pmtiles');
if (reader?.header) {
    console.log('The header:');
    check(reader.header.minZoom === 13, `the regional archive starts at level 13 (${reader.header.minZoom})`);
    check(reader.header.tileCompression === 1 || reader.header.tileCompression === 0,
        'terrain tiles lie uncompressed (webp is its own)');
    check(await reader.tile(9, 268, 172) === null, 'a level the archive does not hold answers null');
}

console.log(failed ? `\n${failed} check(s) failed.` : `\nAll good.${skipped ? ` (${skipped} skipped)` : ''}`);
process.exitCode = failed ? 1 : 0;
