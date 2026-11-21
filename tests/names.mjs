/**
 * Test of the short names.
 *
 * A pure function with a rule that is easy to get almost right: as many
 * letters of the surname as it takes to be unambiguous, and not one more.
 * The interesting cases are all at the edges — no surname, the same name
 * twice, one surname a prefix of the other.
 *
 *   node tests/names.mjs
 */

const { shortName, shortNames } = await import('../assets/js/names.js');

let failed = 0;
function check(name, seen, wanted) {
    if (seen === wanted) {
        console.log('  ok    ' + name);
    } else {
        console.log(`  FAILED ${name}  ->  "${seen}" instead of "${wanted}"`);
        failed++;
    }
}

console.log('\nOne name is never ambiguous:\n');
check('alone, without company', shortName('Finn Horn'), 'Finn');
check('alone, with itself in the company', shortName('Finn Horn', ['Finn Horn']), 'Finn');

console.log('\nDifferent first names — the surname stays out of it:\n');
{
    const c = ['Finn Horn', 'Tobias Lenz', 'Marlene Bergner'];
    check('Finn among strangers', shortName('Finn Horn', c), 'Finn');
    check('Tobias among strangers', shortName('Tobias Lenz', c), 'Tobias');
}

console.log('\nThe same first name — as many letters as it takes:\n');
check('one letter is enough',
    shortName('Finn Horn', ['Finn Horn', 'Finn Bauer']), 'Finn H.');
check('two letters needed',
    shortName('Finn Horn', ['Finn Horn', 'Finn Hansen']), 'Finn Ho.');
check('three letters needed',
    shortName('Finn Horn', ['Finn Horn', 'Finn Hoffmann']), 'Finn Hor.');
check('the other side of the same pair',
    shortName('Finn Hoffmann', ['Finn Horn', 'Finn Hoffmann']), 'Finn Hof.');
check('three of a kind',
    shortName('Finn Hansen', ['Finn Horn', 'Finn Hansen', 'Finn Bauer']), 'Finn Ha.');

console.log('\nThe edges:\n');
// NO DOT HERE: 'Ho' is the whole surname. A dot would promise letters
// that do not exist.
check('a surname that IS the prefix of another stays whole, and without a dot',
    shortName('Finn Ho', ['Finn Ho', 'Finn Hoffmann']), 'Finn Ho');
check('the longer one of such a pair is cut, so it gets the dot',
    shortName('Finn Hoffmann', ['Finn Ho', 'Finn Hoffmann']), 'Finn Hof.');
check('the same name twice — nothing can be told apart, so it stays whole',
    shortName('Finn Horn', ['Finn Horn', 'Finn Horn']), 'Finn');
check('no surname at all, and a rival',
    shortName('Finn', ['Finn', 'Finn Horn']), 'Finn');
check('double first name counts as first name plus surname',
    shortName('Anna Maria Schmidt', ['Anna Maria Schmidt']), 'Anna');
check('empty stays empty', shortName('', ['Finn Horn']), '');
check('nothing at all stays nothing', shortName(null, []), '');

console.log('\nThe whole company at once:\n');
{
    const map = shortNames(['Finn Horn', 'Finn Hansen', 'Tobias Lenz']);
    check('Finn Horn in the company', map.get('Finn Horn'), 'Finn Ho.');
    check('Finn Hansen in the company', map.get('Finn Hansen'), 'Finn Ha.');
    check('Tobias keeps his first name', map.get('Tobias Lenz'), 'Tobias');
}

console.log(failed === 0 ? '\nAll good.' : `\n${failed} failed.`);
process.exit(failed === 0 ? 0 : 1);
