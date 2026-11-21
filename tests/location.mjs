/**
 * Tests the location state machine from map.js — without Leaflet,
 * without a browser. Apart from L, map, status, settings, document
 * and navigator the block is self-contained; all of those are provided
 * here.
 *
 *   node tests/location.mjs        (from the project directory)
 *
 * Why at all: the map page demands a login and a real satellite receiver
 * — by hand the automaton can only be tested outdoors and only
 * laboriously. On its first run it immediately found a mistake that would
 * otherwise only have been noticed while walking: a guard that was meant
 * to distinguish the map's own following from a move by the user for
 * 700 ms would have swallowed almost every move while walking — the map
 * could not have been held in place. It is gone without replacement,
 * because Leaflet's 'dragstart' only ever comes from dragging (looked up
 * in leaflet.js: it fires exclusively from the Draggable, panTo does not
 * trigger it).
 */
import { readFileSync } from 'node:fs';

const source = readFileSync('assets/js/map.js', 'utf8');
// THESE MARKERS MIRROR COMMENT TEXT IN map.js. Whoever rewords those two
// section headers must change them here too — otherwise indexOf returns -1,
// the slice grabs the wrong block, and the build below dies on a name that
// is not in it. Exactly that happened when the comments switched to English.
const from   = source.indexOf('// Location\n// ---');
const until  = source.indexOf('// Reloading and status messages');
const block  = source.slice(source.lastIndexOf('// ---', from), until);

let failed = 0;
const check = (condition, what) => {
    if (!condition) { failed++; console.log('  FAILED:', what); }
    else console.log('  ok   ', what);
};

// --- Dummies --------------------------------------------------------------
const classes = new Set();
const button = {
    title: '',
    classList: {
        toggle: (n, on) => on ? classes.add(n) : classes.delete(n),
        contains: n => classes.has(n),
    },
    addEventListener: (_, f) => { button.click = f; },
};

const layers = new Set();
const makeLayer = () => {
    const e = {
        addTo: k => { layers.add(e); return e; },
        setLatLng: () => e, setRadius: () => e, bindTooltip: () => e,
        getLatLng: () => ({ lat: 50, lng: 8 }), setTooltipContent: () => e,
        // The wake fix pokes the marker's DOM element to restart the pulse
        // and flags the circle as stale via a class toggle.
        getElement: () => ({ querySelector: () => null, classList: { toggle: () => {} } }),
    };
    return e;
};
const L = { divIcon: () => ({}), marker: makeLayer, circle: makeLayer };

let panned = 0;
let inView = true;                 // does the marker lie in the viewport?
const mapHooks = {};
const map = {
    on: (n, f) => { for (const e of n.split(' ')) mapHooks[e] = f; },
    removeLayer: e => layers.delete(e),
    panTo: () => { panned++; },
    getBounds: () => ({ contains: () => inView }),
};

let lastMessage = '';
const status = t => { lastMessage = t; };

const stored = {};
const settings = {
    get: (n, v) => (n in stored ? stored[n] : v),
    set: (n, w) => { stored[n] = w; },
};

let watches = 0, ticks = 0;
const provided = { getCurrentPosition: () => {}, watchPosition: () => ++watches, clearWatch: () => { watches--; } };
const navigator = { geolocation: provided, userAgent: 'Mozilla/5.0 (Linux; Android 14) Chrome/126 Mobile' };
const document = { getElementById: () => button, addEventListener: (n, f) => { document[n] = f; }, hidden: false };
/* The wake fix (1ba8224) put a pageshow handler into the location block —
   back from the bfcache the watch is gone and gets taken up again. Without
   this mock the block dies at window.addEventListener, and the suite had
   been silently red since that commit. */
const windowHooks = {};
const window = { addEventListener: (n, f) => { windowHooks[n] = f; },
                 // The compass is let go when the screen goes dark (releaseHeading).
                 removeEventListener: (n) => { delete windowHooks[n]; } };

const fix = (accuracy, lat = 50.08, lon = 8.24, altitude = 214.7) =>
    ({ coords: { latitude: lat, longitude: lon, accuracy, altitude }, timestamp: 1 });

// The place button lives in another section of map.js. All that counts
// here is that the location section nudges it to redraw.
let redrawn = 0;
const drawPointButton = () => { redrawn++; };

// --- Run the block --------------------------------------------------------
/* THE NAMES IN THESE STRINGS MIRROR map.js INTERNALS — the parameter
   list and the appended return are code built from strings, so no rename
   pass sees them. Whoever renames map, locationActive, locationFollows,
   acceptLocation or locationForPoint over there changes them here in the
   same breath, otherwise the block dies on an undefined name. */
/* THE BLOCK GREW NEIGHBOURS (29./30.09.2026): the direction arrow is
   made in it (createDirection, direction.js), the heading cone reads the
   compass through headingOf (heading.js), and on the vector map a compass
   button turns the map (VECTOR, requestAnimationFrame and its cancel);
   since 04.10.2026 the compass reading is turned to true north
   (declination, declination.js).
   None of them is what this test is about — they get the quietest
   stand-ins, the Leaflet
   map (VECTOR false) keeps the turning out, and the suite had been red
   since 66c4ecc without them. */
const direction = { redraw: () => {}, remembered: () => null, start: () => {} };
const build = new Function('L', 'map', 'status', 'settings', 'document', 'navigator', 'window',
    'drawPointButton', 'setInterval', 'clearInterval', 'setTimeout', 'clearTimeout', 't',
    'createDirection', 'headingOf', 'VECTOR', 'requestAnimationFrame', 'cancelAnimationFrame', 'declination',
    block + '\n; return { active: () => locationActive, follows: () => locationFollows,'
          + ' accept: acceptLocation, forPoint: locationForPoint };');

const tick = () => { ticks++; return ticks; };
// t() like the German app's: the text is its own key, but the
// placeholders still fill — the block under test speaks t() since the
// language sweep (28.08.2026), and its messages carry %d.
const tStub = (text, ...args) => {
    let i = 0;
    return args.length === 0 ? text
        : text.replace(/%(?:\d+\$)?[sd]/g, () => String(args[i++]));
};
const api = build(L, map, status, settings, document, navigator, window,
    drawPointButton, tick, () => {}, tick, () => {}, tStub,
    () => direction, () => null, false, () => 0, () => {}, () => 0);

console.log('\nStarting position');
check(!api.active(), 'off');
check(!classes.has('active'), 'button without a state class');

console.log('\nSwitching on');
button.click();
check(api.active() && api.follows(), 'on and following');
check(classes.has('active') && classes.has('following'), 'button shows both');
check(watches === 1, 'exactly one watch registered');
check(stored.location === true, 'state remembered');

console.log('\nFirst fix, coarse');
api.accept(fix(48));
check(layers.size === 2, 'marker and circle drawn');
check(!classes.has('settled'), 'still pulsing (not settled)');
check(/48 m/.test(lastMessage), 'accuracy reported: ' + lastMessage);

console.log('\nUnusable fix');
const before = panned;
api.accept(fix(400));
check(panned === before, '400 m discarded, map not panned');

console.log('\nBetter fix');
api.accept(fix(6));
check(classes.has('settled'), 'pulsing stops');
check(panned > before, 'map followed along');

console.log('\nWorse fix while walking');
const g2 = panned;
api.accept(fix(20, 50.081, 8.241));
check(panned > g2, 'shown anyway — not only improvements');

console.log('\nUser moves the map');
mapHooks.dragstart();
check(api.active() && !api.follows(), 'display stays, following off');
check(classes.has('active') && !classes.has('following'), 'button shows the difference');

console.log('\nButton: follow again');
button.click();
check(api.active() && api.follows(), 'following');

console.log('\nLocation as the coordinate for a new point');
const p = api.forPoint();
check(p !== null, 'offered as long as the marker is in view');
check(p && p.accuracy_m === 20, 'takes the LAST accuracy (20), not the best (6)');
check(p && p.elevation_ellipsoid_m === 215, 'altitude rounded and taken as ELLIPSOID height');
check(p && !('elevation_m' in p),
    'no sea-level height claimed — the server computes that from the geoid grid');

console.log('\nMap pushed away, marker out of view');
inView = false;
check(api.forPoint() === null, 'no location any more — the point belongs where the tap lands');
inView = true;

console.log('\nFollowing off, marker still in view');
mapHooks.dragstart();
check(!api.follows(), 'following is off');
check(api.forPoint() !== null, 'location still offered');
button.click();

console.log('\nBack from the back-forward cache');
check(typeof windowHooks.pageshow === 'function', 'pageshow handle is registered');
const w1 = watches;
windowHooks.pageshow({ persisted: true });
check(watches >= w1, 'the watch survives waking up');

console.log('\nScreen off');
document.hidden = true;
document.visibilitychange();
check(watches === 0, 'watch released — no consumer in the background');
check(api.active(), 'display stays switched on');

console.log('\nScreen on again');
document.hidden = false;
document.visibilitychange();
check(watches === 1, 'watch taken up again');

console.log('\nSwitching off');
button.click();
check(!api.active() && watches === 0, 'off, no watch');
check(layers.size === 0, 'marker and circle removed');
check(stored.location === false, 'state remembered');

console.log(failed === 0 ? '\nAll good.\n' : `\n${failed} FAILED\n`);
process.exit(failed === 0 ? 0 : 1);
