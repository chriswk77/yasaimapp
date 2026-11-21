/**
 * Test of the queue for write operations without network.
 *
 * The case that triggered this file: a point is created without network
 * and gets a provisional negative id. When the network comes back and the
 * server REJECTS, the entry flies out of the queue — but the point in the
 * application kept its 'pending' and was brought out again on every
 * reload. A ghost that exists only on this device, that nothing can
 * remove, and that stands there twice as soon as somebody creates the
 * same point again.
 *
 *   node tests/queue.mjs
 */

// Stand in for localStorage before the module is loaded.
const storage = new Map();
globalThis.localStorage = {
    getItem: (k) => storage.get(k) ?? null,
    setItem: (k, v) => storage.set(k, String(v)),
    removeItem: (k) => storage.delete(k),
};
// navigator already exists in Node and is write-protected — onLine must
// be true though, otherwise drain() starts right up again.
Object.defineProperty(globalThis.navigator, 'onLine', { value: true, configurable: true });
globalThis.window = { addEventListener() {} };

const w = await import('../assets/js/queue.js');

let failed = 0;
function check(name, condition, seen = '') {
    if (condition) {
        console.log('  ok    ' + name);
    } else {
        console.log('  FAILED ' + name + (seen ? '  ->  ' + seen : ''));
        failed++;
    }
}

function empty() {
    storage.clear();
    // The module reads the queue on load; for clean runs, work it off
    // until nothing is left in it.
    return w.drain(async () => ({ id: 1 }));
}

console.log('\nProvisional ids');

const a = w.provisionalKey();
const b = w.provisionalKey();
check('are negative', a < 0 && b < 0, `${a}, ${b}`);
check('are different', a !== b, `${a}, ${b}`);

console.log('\nOpen ids');

w.enqueue({ type: 'create', section: 'points', data: { title: 'A' }, provisional: a });
w.enqueue({ type: 'update', section: 'points', id: 42, data: { title: 'B' } });

let open = w.openKeys();
check('knows the provisional id', open.has(a), [...open].join(','));
check('knows the real id', open.has(42), [...open].join(','));
check('invents nothing extra', open.size === 2, String(open.size));

console.log('\nRejected entry');

// The server answers and rejects — not a network error.
await w.drain(async (entry) => {
    if (entry.provisional === a) throw new Error('Server meldet 422.');
    return { id: entry.id };
});

open = w.openKeys();
check('the rejected entry is out', !open.has(a), [...open].join(','));
check('and it does not block the rest', w.openCount() === 0, String(w.openCount()));

// This is the core: the application asks this to decide whether to carry
// a waiting point across the reload. If the queue says "nothing pending
// any more", it must not come back.
check('the ghost is not revived', !w.openKeys().has(a));

console.log('\nTransient versus final');

const rejected = (status) => { const f = new Error('x'); f.status = status; return f; };

check('an expired session is transient', w.isTransient(rejected(401)));
check('a stale form token is transient', w.isTransient(rejected(419)));
check('a server error is transient', w.isTransient(rejected(503)));
check('invalid input is final', !w.isTransient(rejected(422)));
check('not found is final', !w.isTransient(rejected(404)));

await empty();
w.forgetDiscarded();

// An entry that fails on an expired session must STAY PUT. Throwing it
// away would mean losing the input exactly when the queue would have to
// fulfil its purpose.
const k = w.provisionalKey();
w.enqueue({ type: 'create', section: 'points', data: { title: 'Wichtig' }, provisional: k });
await w.drain(async () => { throw rejected(401); });

check('it stays put on an expired session', w.openCount() === 1, String(w.openCount()));
check('and is not remembered as discarded', w.discarded().length === 0, String(w.discarded().length));
check('the hurdle can be queried', w.hurdle()?.status === 401, String(w.hurdle()?.status));

// After logging in it goes through — without anyone retyping anything.
await w.drain(async () => ({ id: 123 }), () => {});
check('after the second attempt it is through', w.openCount() === 0, String(w.openCount()));

console.log('\nWhat is finally rejected gets kept');

await empty();
w.forgetDiscarded();
w.enqueue({ type: 'create', section: 'points',
    data: { title: 'Steinpilzstelle', lat: 50.23, lon: 8.44 }, provisional: w.provisionalKey() });
await w.drain(async () => { throw rejected(422); });

const kept = w.discarded();
check('it is kept, not gone', kept.length === 1, String(kept.length));
check('with the content', kept[0]?.entry?.data?.title === 'Steinpilzstelle', JSON.stringify(kept[0]?.entry?.data));
check('and with the reason', typeof kept[0]?.reason === 'string');
w.forgetDiscarded();
check('forgetting only happens on request', w.discarded().length === 0);

console.log('\nNetwork error');

w.enqueue({ type: 'create', section: 'points', data: { title: 'C' }, provisional: w.provisionalKey() });
await w.drain(async () => {
    const f = new Error('Keine Verbindung.');
    f.isNetworkError = true;
    throw f;
});
check('with the network missing the entry stays put', w.openCount() === 1, String(w.openCount()));

console.log('\nReplacing an id');

await empty();
const v = w.provisionalKey();
w.enqueue({ type: 'create', section: 'lists', data: { name: 'Neu' }, provisional: v });
// A point meant to go INTO the list that is not yet created.
w.enqueue({ type: 'create', section: 'points', data: { title: 'P', lists: [v] }, provisional: w.provisionalKey() });

const seen = [];
await w.drain(async (entry) => {
    seen.push(JSON.parse(JSON.stringify(entry.data)));
    return { id: entry.section === 'lists' ? 77 : 88 };
}, () => {});

check('the list gets its real id',
    seen[1]?.lists?.[0] === 77, JSON.stringify(seen[1]));
check('afterwards the queue is empty', w.openCount() === 0, String(w.openCount()));

// ---------------------------------------------------------------------------
// Replay
//
// THE CASE THAT TRIGGERED THIS: four points placed in the forest, no
// network, phone off. On the next open the map builds its inventory from
// the buckets — and there stands only what the server delivered. The
// four were not on the map. They were not lost, but whoever takes them
// for lost places them again.
// ---------------------------------------------------------------------------

console.log('\nReplay');

await empty();
const serverState = [{ id: 5, title: 'Vom Server', lat: 50, lon: 9 }];

check('with nothing waiting the server state stays untouched',
    w.replay('points', serverState) === serverState);

const n1 = w.provisionalKey();
w.enqueue({ type: 'create', section: 'points', provisional: n1,
              data: { title: 'Im Wald', lat: 49.9, lon: 9.1 } });

let out = w.replay('points', serverState);
check('the point from the dead spot is included', out.length === 2, String(out.length));
check('with its provisional id', out.some(p => p.id === n1),
    out.map(p => p.id).join(','));
check('and marked as waiting', out.find(p => p.id === n1)?.pending === true);
check('the server state remains standing beside it', out.some(p => p.id === 5));

// First create, then rename — the order has to be right.
w.enqueue({ type: 'update', section: 'points', id: n1, data: { title: 'Doch anders' } });
out = w.replay('points', serverState);
check('a later change lays itself on top',
    out.find(p => p.id === n1)?.title === 'Doch anders',
    out.find(p => p.id === n1)?.title);
check('and the point stays one', out.length === 2, String(out.length));

// A change to a point that exists on the server.
w.enqueue({ type: 'update', section: 'points', id: 5, data: { title: 'Umbenannt' } });
out = w.replay('points', serverState);
check('a waiting change to a real point is visible too',
    out.find(p => p.id === 5)?.title === 'Umbenannt', out.find(p => p.id === 5)?.title);
check('and leaves the remaining fields in place', out.find(p => p.id === 5)?.lat === 50);
check('the server state itself is not touched', serverState[0].title === 'Vom Server');

// Other sections are none of the points' business.
w.enqueue({ type: 'create', section: 'lists', data: { name: 'Fremd' },
              provisional: w.provisionalKey() });
check('an entry from another section stays outside',
    w.replay('points', serverState).length === 2);
check('but shows up in its own',
    w.replay('lists', []).length === 1);
check('with the caller\'s defaults',
    w.replay('lists', [], { color: '#abc' })[0]?.color === '#abc');

// A waiting deletion takes the point off the map — otherwise it would
// stand there although long removed, and only go away on the next
// network.
w.enqueue({ type: 'delete', section: 'points', id: 5 });
out = w.replay('points', serverState);
check('a waiting delete takes the point away', !out.some(p => p.id === 5),
    out.map(p => p.id).join(','));

// A PATCH carries only fields. Inventing a record from it would mean
// drawing a point without a coordinate.
await empty();
w.enqueue({ type: 'update', section: 'points', id: 999, data: { title: 'Geist' } });
check('a change to something unknown invents nothing',
    w.replay('points', serverState).length === 1,
    JSON.stringify(w.replay('points', serverState)));

/* THE ENDLESS LOOP, and it was neither theory nor rare.
   
   drain() was handed sendEntry(), and sendEntry() called api.post/patch —
   the very wrappers whose job is to put a failed call INTO the queue. So
   an entry that came out of the queue and did not get through was never
   reported as failed: it was quietly enqueued a second time and the
   wrapper returned a made-up success for it.
   
   drain believed that, counted the entry as transmitted, took it off the
   front — and found the fresh copy at the back. The queue never got
   shorter, the loop never ended, and every pass was a REAL request on a
   line that was not working. Measured: 201 attempts without a pause, and
   drain's answer read {sent: 200}.
   
   The sender must therefore let a failure through. This case pins that
   down from the queue's side: whoever hands drain a sender that re-queues
   instead of throwing gets caught here, not in the woods. */
console.log('\nA sender that re-queues instead of failing');

await empty();
{
    const p = w.provisionalKey();
    w.enqueue({ type: 'create', section: 'points', data: { title: '' }, provisional: p });

    let tries = 0;
    const balance = await w.drain(async (entry) => {
        tries++;
        if (tries > 50) return { id: 1 };          // emergency brake for the test
        // exactly what api.post used to do on a network error
        w.enqueue({ type: entry.type, section: entry.section,
                    data: entry.data, provisional: w.provisionalKey() });
        return { ...entry.data, id: w.provisionalKey(), pending: true };
    });
    check('the loop ends instead of hammering the line', tries <= 4, `${tries} Versuche`);
    check('and drain comes back at all', typeof balance.sent === 'number', JSON.stringify(balance));
}

/* HELD ENTRIES — since 21.08.2026 a writing call reserves its place in
   here BEFORE it touches the line, and holds it while it tries. drain()
   must step aside for such an entry: not skip it, which would put what
   comes after in front, but stop in front of it. */
console.log('\nWhat somebody is holding right now');

await empty();
{
    const ticket = w.reserve({ type: 'update', section: 'points', id: 5, data: { title: 'A' } });
    w.enqueue({ type: 'update', section: 'points', id: 6, data: { title: 'B' } });

    let sent = [];
    await w.drain(async (e) => { sent.push(e.id); return { id: e.id }; });
    check('drain does not touch it', sent.length === 0, sent.join(','));
    check('and does not go past it either — order stays', !sent.includes(6), sent.join(','));

    w.release(ticket);
    sent = [];
    await w.drain(async (e) => { sent.push(e.id); return { id: e.id }; });
    check('released, both go, in order', sent.join(',') === '5,6', sent.join(','));
}

await empty();
{
    const ticket = w.reserve({ type: 'update', section: 'points', id: 8, data: { title: 'C' } });
    check('a reservation counts as waiting', w.openCount() === 1, String(w.openCount()));
    w.settle(ticket);
    check('settled, it is gone', w.openCount() === 0, String(w.openCount()));
}

console.log(failed === 0 ? '\nAll good.' : `\n${failed} check(s) failed.`);
process.exit(failed === 0 ? 0 : 1);
