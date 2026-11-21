/**
 * Tests the settings bundle against a second page (assets/js/settings.js).
 *
 *   node tests/settingsstore.mjs
 *
 * WHAT IS TESTED HERE, and why exactly that: the maintainer's microscope of
 * 13.11.2026. He set it up on his second laptop, took pictures, and the
 * next day the button was gone — the setup lives in the ONE entry all
 * settings share, every page read that entry once and wrote all of it
 * back at each change, and so a page opened before the setup wrote its
 * old bundle over it. Here the „other page" is a write straight into the
 * storage between this page's reading and its next change: what the
 * other page stored must survive, what this page changed must win, and
 * what the browser reports from the other page must be taken over.
 */

const storage = new Map([['map.settings', JSON.stringify({ baseMap: 'Luftbild', brush: 10 })]]);
globalThis.localStorage = {
    getItem: (k) => storage.get(k) ?? null,
    setItem: (k, v) => storage.set(k, String(v)),
    removeItem: (k) => storage.delete(k),
};
// A window that only collects its listeners — to play the browser's reports to this page.
const listeners = {};
globalThis.window = { addEventListener: (kind, fn) => { (listeners[kind] ??= []).push(fn); } };
const report = (kind, event) => (listeners[kind] ?? []).forEach((fn) => fn(event));

const { settings } = await import('../assets/js/settings.js');

let failed = 0;
function check(b, what) {
    if (b) { console.log(`  ok    ${what}`); } else { failed++; console.log(`  FAILED: ${what}`); }
}
const stored = () => JSON.parse(storage.get('map.settings') ?? '{}');
/** The other page writes — it knows what is stored and adds or changes its own. */
const otherPage = (change) => storage.set('map.settings', JSON.stringify({ ...stored(), ...change }));

console.log('\nThe settings bundle and a second page\n');

// The other page sets up the microscope; this page, loaded before, remembers a brush size.
otherPage({ microscopeSource: 'program' });
settings.set('brush', 25);
settings.flush();
check(stored().microscopeSource === 'program', 'what another page stored survives this page\'s next write (the microscope\'s setup)');
check(stored().brush === 25 && stored().baseMap === 'Luftbild', 'this page\'s change is written, the rest stays');
check(settings.get('microscopeSource') === 'program', 'and after writing, this page knows what the other stored');

// Both change the same value: the one who writes last wins — this page, with what it changed.
otherPage({ brush: 3, theme: 'dark' });
settings.set('brush', 40);
settings.flush();
check(stored().brush === 40 && stored().theme === 'dark', 'a value both changed is this page\'s, the other page\'s other values stay');

// Forgetting: only the forgotten name goes.
otherPage({ microscopeWebcam: { deviceId: 'x' } });
settings.forget('brush');
settings.flush();
check(!('brush' in stored()) && stored().microscopeWebcam?.deviceId === 'x' && stored().microscopeSource === 'program',
    'a forgotten value goes, and nothing else with it');

// The browser reports the other page's write: taken over at once, without a write of this page's.
otherPage({ baseMap: 'OpenStreetMap' });
report('storage', { key: 'map.settings' });
check(settings.get('baseMap') === 'OpenStreetMap', 'what the browser reports from another page is taken over');

// A change not written yet stays this page's through such a report.
settings.set('baseMap', 'Topo');
otherPage({ baseMap: 'Luftbild', lang: 'en' });
report('storage', { key: 'map.settings' });
check(settings.get('baseMap') === 'Topo' && settings.get('lang') === 'en', 'a change of this page not written yet stays its own through the report');
settings.flush();
check(stored().baseMap === 'Topo' && stored().lang === 'en', 'and is written over the other page\'s value');

// Coming back out of the browser's back-forward cache: the page as it was left, the storage as it is now.
otherPage({ microscopeSource: 'webcam' });
report('pageshow', { persisted: true });
check(settings.get('microscopeSource') === 'webcam', 'a page shown again out of the browser\'s cache reads the storage anew');
settings.set('brush', 12);
settings.flush();
check(stored().microscopeSource === 'webcam' && stored().brush === 12, 'and its next write keeps what was stored meanwhile');

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
