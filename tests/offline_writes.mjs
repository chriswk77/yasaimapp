/**
 * Test of the boundary between "may wait in the queue" and "really rejected".
 *
 * THE CASE THAT TRIGGERED THIS FILE cost the maintainer the titles of a whole
 * tour (20.08.2026). api.patch() enqueued only on a NETWORK error — where
 * no answer came at all. If the server answered, the call counted as
 * rejected and was thrown on, and in the point window revertSave caught it:
 * the stock went back, the typed text stood in the fields again, unsaved,
 * and was gone the moment the window closed.
 *
 * But the likeliest failures of a tour all answer: a session expired after
 * a day without network (401), a stale form token (419), a sign-in portal
 * in a foreign WLAN that sends HTML instead of JSON (notJson), a proxy
 * that says 502 on a weak line. All of them settle with a second attempt.
 *
 * What must NOT wait is a real rejection — invalid input, missing
 * permission. Such an entry would try again for all eternity.
 *
 *   node tests/offline_writes.mjs
 */

const storage = new Map();
globalThis.localStorage = {
    getItem: (k) => storage.get(k) ?? null,
    setItem: (k, v) => storage.set(k, String(v)),
    removeItem: (k) => storage.delete(k),
};
Object.defineProperty(globalThis.navigator, 'onLine', { value: true, configurable: true });
globalThis.window = { addEventListener() {}, START_DATA: { baseUrl: '/', csrf: 'x' } };
globalThis.crypto ??= {};
globalThis.crypto.randomUUID ??= () => 'test-uuid';

// The answer the stubbed server gives — set per case below.
let answer = { status: 200, json: {} };
globalThis.fetch = async () => ({
    ok: answer.status >= 200 && answer.status < 300,
    status: answer.status,
    json: async () => {
        if (answer.json === null) throw new Error('kein JSON');
        return answer.json;
    },
});

const { api } = await import('../assets/js/api.js');
const queue = await import('../assets/js/queue.js');

let failed = 0;
function check(name, condition, seen = '') {
    if (condition) {
        console.log('  ok    ' + name);
    } else {
        console.log('  FAILED ' + name + (seen ? '  ->  ' + seen : ''));
        failed++;
    }
}

const TITLE = 'Ein Titel, der nicht verlorengehen darf';

/**
 * One save attempt against a server that answers with $status.
 *
 * COUNTED AS A DIFFERENCE, not as a total. Emptying the storage does not
 * empty the queue — the module holds its list in memory and writes it out
 * on the next enqueue. A test that clears the storage and then expects
 * exactly one entry therefore passes only on the first case, and the
 * rejection cases would pass for the wrong reason: nothing was written, so
 * the storage stayed empty whether the entry was rejected or not.
 */
async function attempt(status, json = { fehler: 'test' }) {
    const before = queue.openCount();
    answer = { status, json };
    let threw = null;
    try {
        await api.patch('points', 7, { title: TITLE });
    } catch (e) {
        threw = e;
    }
    const waiting = JSON.parse(storage.get('map.queue') || '[]');
    return { threw, added: queue.openCount() - before, last: waiting.at(-1) };
}


console.log('\nWhat may wait — the answer came, but it passes by itself:\n');

for (const [status, label] of [[401, 'session expired'], [419, 'stale form token'],
                               [408, 'server took too long'], [429, 'too many requests'],
                               [500, 'server had a bad moment'], [502, 'proxy on a weak line']]) {
    const { threw, added, last } = await attempt(status);
    check(`${status} (${label}) waits, with its data`,
        threw === null && added === 1 && last?.data?.title === TITLE,
        `threw=${threw?.message ?? 'no'} added=${added} title=${last?.data?.title}`);
}

// The captive portal: any status, but no JSON — the application never
// answered. This is what cost the voucher photos once, see api.js.
{
    const { threw, added } = await attempt(404, null);
    check('404 without JSON (sign-in portal) waits',
        threw === null && added === 1,
        `threw=${threw?.message ?? 'no'} added=${added}`);
}

console.log('\nWhat must NOT wait — the application answered and refused:\n');

for (const [status, label] of [[400, 'malformed'], [403, 'not allowed'],
                               [404, 'point is gone'], [422, 'invalid input']]) {
    const { threw, added } = await attempt(status);
    check(`${status} (${label}) is thrown on, nothing waits`,
        threw !== null && added === 0,
        `threw=${threw?.message ?? 'no'} added=${added}`);
}

/* WRITTEN BEFORE IT IS SENT — the promise the queue makes since
   21.08.2026, and the one whose absence cost the titles.
   
   Until then an entry only reached the queue in the CATCH of a call. That
   holds as long as failing means being refused. In the woods it does not:
   the request goes out, a few bytes leave, and the line dies without a
   word. Nothing throws, so nothing is caught, so nothing is enqueued — and
   the input lives solely in RAM. A reload, a closed window, a phone that
   switches itself off, and it is gone.
   
   These two cases therefore do not ask what happens AFTER the answer. They
   ask what stands in the storage WHILE nobody is answering. */
console.log('\nWritten before it is sent — while the line is still silent:\n');

{
    let release;
    const hanging = new Promise((resolve) => { release = resolve; });
    globalThis.fetch = () => hanging.then(() => ({
        ok: true, status: 200, json: async () => ({ id: 7, title: TITLE }),
    }));

    const before = queue.openCount();
    const call = api.patch('points', 7, { title: TITLE });

    // Not a single tick has passed in which an answer could have come.
    const waiting = JSON.parse(storage.get('map.queue') || '[]');
    check('the entry lies in the storage while the line is silent',
        queue.openCount() === before + 1 && waiting.at(-1)?.data?.title === TITLE,
        `queue=${queue.openCount() - before} title=${waiting.at(-1)?.data?.title}`);
    check('and is marked as held, so nothing sends it twice',
        waiting.at(-1)?.sending === true, String(waiting.at(-1)?.sending));

    release();
    await call;
    check('once it has arrived, nothing is left over',
        queue.openCount() === before, String(queue.openCount() - before));
}

/* A NUMBER THE SERVER HAS NEVER HEARD OF MUST NOT GO ON THE LINE.
   A point created without network carries a provisional negative id. A
   change saved on it would be answered "Keine Kennung angegeben" — and
   that is a refusal, and a refusal is final: the change would be put
   aside and the input lost. It belongs beside its create in the queue,
   where it gets the real number when the create goes through. */
{
    let touched = false;
    globalThis.fetch = () => { touched = true; throw new Error('the line must not be touched'); };

    const before = queue.openCount();
    const answer = await api.patch('points', -17, { title: TITLE });

    check('a provisional id does not touch the line at all', !touched);
    check('but the change waits, with its text',
        queue.openCount() === before + 1
        && JSON.parse(storage.get('map.queue') || '[]').at(-1)?.data?.title === TITLE);
    check('and the caller gets a usable answer back',
        answer?.id === -17 && answer?.pending === true,
        JSON.stringify(answer));
}

console.log(failed === 0 ? '\nAll good.' : `\n${failed} failed.`);
process.exit(failed === 0 ? 0 : 1);
