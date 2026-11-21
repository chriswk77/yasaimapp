/**
 * Tests the geoid conversion against known values.
 *
 *   node tests/geoid.mjs        (from the project directory)
 *
 * The target values do not come from the same source as the grid but from
 * published EGM96 figures: the global extremes (-107 m south of India,
 * +85 m near New Guinea) and the order of magnitude in central Europe
 * (+45 to +50 m). If the conversion did not hit these, the grid would be
 * unpacked wrongly — the most likely mistake with a floating-point
 * predictor.
 */
import { execFileSync } from 'node:child_process';

const ask = (lat, lon) => {
    const out = execFileSync('php', ['-r', `
        define('PROJECT_PATH', __DIR__);
        require 'includes/geoid.php';
        $n = geoid_undulation(${lat}, ${lon});
        echo $n === null ? 'null' : number_format($n, 3, '.', '');
    `], { encoding: 'utf8' });
    return out === 'null' ? null : parseFloat(out);
};

let failed = 0;
const check = (b, what) => {
    if (!b) { failed++; console.log('  FAILED:', what); } else console.log('  ok   ', what);
};
const between = (v, lo, hi) => v !== null && v >= lo && v <= hi;

console.log('\nKnown spots');
for (const [lat, lon, what, lo, hi] of [
    [50.0,   8.0,  'Taunus',                  45, 50],
    [52.52, 13.40, 'Berlin',                  37, 43],
    [47.0,  11.0,  'Tyrol',                   46, 53],
    [ 4.7,  77.5,  'geoid low near India',  -106, -98],
    // Here lies the global maximum of EGM96 at roughly +85 m — so the
    // bound must reach up to just below it, not end at 80.
    [-8.0, 147.0,  'New Guinea, geoid high',  60, 86],
    [ 0.0,   0.0,  'Gulf of Guinea',          15, 20],
]) {
    const n = ask(lat, lon);
    check(between(n, lo, hi), `${what}: ${n} m (expected ${lo} to ${hi})`);
}

console.log('\nIntermediate values');
const a = ask(50.0, 8.0), b = ask(50.25, 8.0), m = ask(50.125, 8.0);
check(m !== null && Math.abs(m - (a + b) / 2) < 0.02,
    `the midpoint between two grid points is averaged (${a} / ${m} / ${b})`);

console.log('\nEdges');
check(ask(90, 0) !== null,  'the North Pole yields a value');
check(ask(-90, 0) !== null, 'the South Pole yields a value (no reading past the end of the grid)');
check(ask(0, 180) !== null, 'date line east');
check(ask(0, -180) !== null, 'date line west');
check(Math.abs(ask(0, 179.999) - ask(0, -180)) < 0.5, 'east and west fit together');

console.log('\nNonsense');
check(ask(91, 0) === null,   '91 degrees north is rejected');
check(ask(0, 181) === null,  '181 degrees east is rejected');

console.log('\nConversion');
const nhn = execFileSync('php', ['-r', `
    define('PROJECT_PATH', __DIR__);
    require 'includes/geoid.php';
    echo number_format(geoid_to_nhn(300.0, 50.0, 8.0), 2, '.', '');
`], { encoding: 'utf8' });
check(Math.abs(parseFloat(nhn) - (300 - a)) < 0.01,
    `300 m ellipsoidal in the Taunus is ${nhn} m above NHN`);

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
