<?php
/**
 * The find number — minted at creation, shaped by the user (migration 0070).
 *
 * HOW THE PATTERN SPEAKS. One string, free text with placeholders:
 *
 *   JJJJ  the find year, four digits          TT  the find day, two digits
 *   JJ    the find year, two digits           N…  the running number — as
 *   MM    the find month, two digits              many digits as N stand
 *                                                 in a row ("NNNN" = 0001)
 *
 * "CW-JJJJ-NNNN" thus yields "CW-2026-0001". The date is the FIND time,
 * not the moment of saving: whoever hands in yesterday's tour in the
 * evening numbers yesterday's finds under yesterday. Only the longest run
 * of N is the counter; everything else stands as written. The counter
 * grows past its digits without cutting ("NN" and find 100 → "100") —
 * a truncated number would collide with an earlier one.
 *
 * WITHOUT A PATTERN NO NUMBER IS MINTED — the database id is the number
 * then, and the display reads it straight off the point. Minting only
 * happens for points born into a SPECIES-LIST branch: a waypoint is not a
 * find, and a parking spot must not use up a number of the collection.
 *
 * THE PLACEHOLDERS ARE GERMAN ON PURPOSE (JJJJ, TT — not YYYY, DD): they
 * are user-facing vocabulary, typed by the user into a user-visible
 * setting, exactly like a date format in any German-speaking program.
 */

declare(strict_types=1);

/** Are the columns there yet? Migration 0070 — code goes live first. */
function find_number_ready(PDO $pdo): bool
{
    static $there = null;
    return $there ??= column_exists($pdo, 'map_points', 'find_number')
        && column_exists($pdo, 'map_users', 'find_number_pattern');
}

/** The user's pattern — null when none is set (then the id speaks). */
function find_number_pattern(PDO $pdo, int $userId): ?string
{
    if (!find_number_ready($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT find_number_pattern FROM map_users WHERE id = ?');
    $stmt->execute([$userId]);
    $pattern = trim((string) $stmt->fetchColumn());
    return $pattern === '' ? null : $pattern;
}

/** The next running number — for the settings display. */
function find_number_next(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT find_counter FROM map_users WHERE id = ?');
    $stmt->execute([$userId]);
    return max(1, (int) $stmt->fetchColumn());
}

/**
 * Store the pattern from the settings form.
 *
 * A NON-EMPTY PATTERN MUST CARRY AN N-RUN: without the counter every find
 * would get the same number, and a "number" that does not number is a
 * mistake one should hear about at saving, not discover in the woods.
 *
 * THE COUNTER IS SET INSIDE THE PATTERN, not in a field of its own
 * (25.09.2026: „das Feld nächste laufende Nummer … ist einfach
 * doppelt gemoppelt, kann weg" — the pattern takes one's last number
 * written out, and the preview shows what comes next). So a pattern with
 * N's leaves the counter where it is, and only a written-out number moves
 * it (below). $next stays for the tests, which set a counter directly.
 *
 * @throws InvalidArgumentException with a message fit for the notice
 */
/**
 * THE NUMBER A FIND HAS WITHOUT A PATTERN: its database id, marked „Y-" —
 * „Y-100" (25.09.2026). The bare id could meet a number of the
 * user's own pattern: with „NNN" the counter mints „100" some day, and two
 * finds read the same. With the mark the two kinds cannot meet: no pattern
 * may begin with it (find_number_settings_save), and an imported number
 * that does is dropped (point_create) — whoever re-imports an export gets
 * the new find's own mark, not the old one's.
 *
 * SHOWN AND EXPORTED, NEVER STORED: the column stays empty, so every old
 * find wears the mark at once, and a later change of it needs no
 * migration either — the move below needed none. The browser's twin is
 * findNumberFallback() in assets/js/findnumber.js.
 *
 * A HYPHEN, NOT THE COLON IT WAS UNTIL 04.10.2026 („statt dem
 * Doppelpunkt machen wir generell ein Minus, in der Anzeige, dann braucht
 * man sich nicht umgewöhnen"). The number is about to stand in the cloud's
 * folder names, and a colon may not: Windows refuses it, a Mac shows it as
 * a slash. A mark that read „Y:100" on the map and „Y-100" on the disk
 * would be two spellings of one number. The colon was chosen because
 * nobody's own numbers carry one; the hyphen is common in them („wk-00554")
 * — but only a pattern BEGINNING with „Y-" collides, and that one is
 * refused, as „Y:" was.
 */
const FIND_NUMBER_FALLBACK_PREFIX = 'Y-';

/**
 * Every spelling the mark ever had — „Y:" stands in exports and notes
 * written before 04.10.2026, and re-imported they still name the find they
 * came from, not a number of the user's own.
 */
const FIND_NUMBER_RESERVED_PREFIXES = ['Y-', 'Y:'];

function find_number_fallback(int $pointId): string
{
    return FIND_NUMBER_FALLBACK_PREFIX . $pointId;
}

/** Does a pattern or a number begin with the mark? Case and blanks do not hide it. */
function find_number_reserved(string $value): bool
{
    foreach (FIND_NUMBER_RESERVED_PREFIXES as $prefix) {
        if (stripos(ltrim($value), $prefix) === 0) {
            return true;
        }
    }
    return false;
}

function find_number_settings_save(PDO $pdo, int $userId, string $pattern, ?string $next = null,
                                   bool $restart = false): void
{
    if (!find_number_ready($pdo)) {
        throw new InvalidArgumentException(t('Die Fundnummern brauchen die Migration 0070 — sie ist noch nicht eingespielt.'));
    }
    $wasRestart = find_number_restart($pdo, $userId);

    $pattern = trim($pattern);
    if (find_number_reserved($pattern)) {
        throw new InvalidArgumentException(t('Ein Muster darf nicht mit „Y-" anfangen. So beginnen die Nummern, die YASAIMAPP selbst vergibt.'));
    }
    if (mb_strlen($pattern) > 60) {
        throw new InvalidArgumentException(t('Das Muster ist zu lang. Es darf höchstens 60 Zeichen haben.'));
    }

    /* A WRITTEN-OUT NUMBER COUNTS ON FROM ITSELF (23.08.2026):
       whoever continues an existing collection types its LAST GIVEN
       number straight into the pattern — "wk-JJJJ-00000543" — and that
       digit run becomes the counter: as many places as digits, and the
       NEXT find counts on past it (544). The typed one is the number that
       already exists out there; starting AT it would mint it a second
       time. The run turns into N's for storage, so afterwards the pattern
       reads like any other. Only when NO N stands in the pattern: with
       N's present, digits are literal text (a "Revier7-NNNN" keeps its
       7). The longest run wins, like with the N's — whoever writes the
       year out as 2026 instead of JJJJ keeps it as frozen text, and the
       preview shows exactly that.

       THE SAME RULES A SECOND TIME IN THE BROWSER: the live preview
       (assets/js/findnumber.js) mirrors this normalization — whoever
       changes it here changes it there, tests/find_number.php and
       tests/findnumber.mjs hold the two together. */
    if ($pattern !== '' && !preg_match('/N/', $pattern)) {
        $runs = [];
        preg_match_all('/[0-9]+/', $pattern, $runs, PREG_OFFSET_CAPTURE);
        $longest = null;
        foreach ($runs[0] as $run) {
            if ($longest === null || strlen($run[0]) > strlen($longest[0])) {
                $longest = $run;
            }
        }
        if ($longest === null) {
            throw new InvalidArgumentException(
                t('Im Muster fehlt die laufende Nummer. Schreib zum Beispiel NNNN — oder deine letzte Nummer, zum Beispiel 00543.'));
        }
        $next    = (string) ((int) $longest[0] + 1);
        $pattern = substr_replace($pattern, str_repeat('N', strlen($longest[0])),
            $longest[1], strlen($longest[0]));
    }

    if ($next !== null) {
        $nextNumber = (int) $next;
        if ($nextNumber < 1 || $nextNumber > 99999999) {
            throw new InvalidArgumentException(t('Die nächste Nummer muss zwischen 1 und 99999999 liegen.'));
        }
        $pdo->prepare('UPDATE map_users SET find_number_pattern = ?, find_counter = ? WHERE id = ?')
            ->execute([$pattern === '' ? null : $pattern, $nextNumber, $userId]);
    } else {
        $pdo->prepare('UPDATE map_users SET find_number_pattern = ? WHERE id = ?')
            ->execute([$pattern === '' ? null : $pattern, $userId]);
    }

    /* THE RESTART FLAG, columns from 0072 — the code is live first. The
       flag only sticks when the pattern carries a date part: without one
       there is no section, and a stored "on" would lie in the checkbox.

       THE SECTION THE COUNTER COUNTS IN is set to TODAY'S only where the
       counter was just stated — whoever types 543 today means 544 to
       follow TODAY, not a fresh 1 — or where the restart is switched on
       just now, so the finds of this year go on counting and the next
       year begins at 1. A restart that was on already KEEPS its section:
       a pattern saved again on the second of January must not stamp the
       old year's count onto the new one, or the year's first find would
       draw 58 where the mint promised 1. The first find of the next
       section restarts by itself.

       BUT A SECTION WITHOUT A FIND YET BEGINS AT 1 AT ONCE (28.11.2026:
       a finder ticked „jeden Tag neu" and his first find of that day drew
       37 — the rule above had stamped today onto the running count, and
       the restart would only have shown the next morning). Counting on
       is right where today already has numbers in the pattern's shape;
       where it has none, nothing would collide, and whoever ticks the
       box in the morning means the 01 today. So the section stays
       unstamped (NULL) and the first find draws 1 and stamps it. */
    if (column_exists($pdo, 'map_users', 'find_number_restart')) {
        $period = $restart ? find_number_period($pattern === '' ? null : $pattern, null) : null;
        if ($period === null) {
            $pdo->prepare('UPDATE map_users SET find_number_restart = 0, find_counter_period = NULL WHERE id = ?')
                ->execute([$userId]);
        } elseif ($next === null && !$wasRestart
                  && find_number_highest($pdo, $userId, $pattern, $period, null) === 0) {
            $pdo->prepare('UPDATE map_users SET find_number_restart = 1, find_counter_period = NULL WHERE id = ?')
                ->execute([$userId]);
        } elseif ($next !== null || !$wasRestart) {
            $pdo->prepare('UPDATE map_users SET find_number_restart = 1, find_counter_period = ? WHERE id = ?')
                ->execute([$period['key'], $userId]);
        } else {
            $pdo->prepare('UPDATE map_users SET find_number_restart = 1 WHERE id = ?')
                ->execute([$userId]);
        }
    }
}

/**
 * The date section a pattern's numbers live in — or null.
 *
 * THE CHAIN MUST REACH DOWN FROM THE YEAR (23.08.2026 — his
 * question was the test case): a TT alone does not identify a day.
 * "05-001" of September and of October would read the same, and a daily
 * restart would mint that collision on purpose. Only what the written
 * date really distinguishes may reset the counter: Tag needs JJ+MM+TT,
 * Monat needs JJ+MM, Jahr needs JJ (JJJJ contains it). A TT without MM
 * stays decoration, and the section falls back to what the chain still
 * covers — with no year at all there is no section, however many day and
 * month parts stand around. Without a section the mint ignores the flag:
 * a restart there would mint the same number twice.
 *
 * The check sees the same tokens the renderer replaces — a TT inside a
 * word ("OTTO") counts here exactly because it counts there.
 *
 * @return array{unit: string, key: string}|null unit is 'Tag'|'Monat'|'Jahr'
 *         (user vocabulary, shown in the settings label), key the section
 *         of $foundAt: '2026-08-23', '2026-08' or '2026'
 */
function find_number_period(?string $pattern, ?string $foundAt): ?array
{
    if ($pattern === null || !str_contains($pattern, 'JJ')) {
        return null;
    }
    $when = null;
    if ($foundAt !== null) {
        $when = DateTime::createFromFormat('Y-m-d H:i:s', $foundAt) ?: null;
    }
    $when ??= new DateTime();

    if (str_contains($pattern, 'MM') && str_contains($pattern, 'TT')) {
        return ['unit' => 'Tag', 'key' => $when->format('Y-m-d')];
    }
    if (str_contains($pattern, 'MM')) {
        return ['unit' => 'Monat', 'key' => $when->format('Y-m')];
    }
    return ['unit' => 'Jahr', 'key' => $when->format('Y')];
}

/** Is the per-section restart switched on? Columns arrive with 0072. */
function find_number_restart(PDO $pdo, int $userId): bool
{
    if (!column_exists($pdo, 'map_users', 'find_number_restart')) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT find_number_restart FROM map_users WHERE id = ?');
    $stmt->execute([$userId]);
    return (bool) $stmt->fetchColumn();
}

/** The section the counter counts in (0072) — null when none is stored. */
function find_number_counter_period(PDO $pdo, int $userId): ?string
{
    if (!column_exists($pdo, 'map_users', 'find_counter_period')) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT find_counter_period FROM map_users WHERE id = ?');
    $stmt->execute([$userId]);
    $period = $stmt->fetchColumn();
    return $period === false || $period === null ? null : (string) $period;
}

/**
 * The number the next find of TODAY would draw under $pattern — the
 * mint's own choice without consuming it: the highest number already
 * given in today's section (or in the whole series, without a restart)
 * plus one, at least the counter — and the counter counts only where the
 * restart has not opened a new section today. Since the field for the next number went (25.09.2026) the
 * settings' preview is the one place that says what comes — and after a
 * new year, or every morning under a daily restart, it used to promise
 * the old count while the mint began at 1. The browser twin is the
 * preview in administration.js.
 */
function find_number_upcoming(PDO $pdo, int $userId, ?string $pattern): int
{
    $next = find_number_next($pdo, $userId);
    if ($pattern === null) {
        return $next;
    }
    $period = find_number_restart($pdo, $userId) ? find_number_period($pattern, null) : null;
    $floor = $period === null || $period['key'] === find_number_counter_period($pdo, $userId) ? $next : 1;
    return max($floor, find_number_highest($pdo, $userId, $pattern, $period, null) + 1);
}

/**
 * Render one number from the pattern.
 *
 * @param ?string $foundAt find time 'Y-m-d H:i:s' — null falls back to
 *        today: a point deliberately without a find time still needs its
 *        date parts filled with SOMETHING, and the day it was recorded is
 *        the honest stand-in.
 */
function find_number_render(string $pattern, ?string $foundAt, int $n): string
{
    $when = null;
    if ($foundAt !== null) {
        $when = DateTime::createFromFormat('Y-m-d H:i:s', $foundAt) ?: null;
    }
    $when ??= new DateTime();

    /* THE N-RUN FIRST, and via callback rather than str_replace: the date
       replacements below insert digits, and a pattern like "JJJJNNNN"
       must not have its year eaten by a later N-hungry pass. Only the
       FIRST longest run counts as the counter — a second "NN" elsewhere
       stays literal, which the preview makes visible. */
    $runs = [];
    preg_match_all('/N+/', $pattern, $runs, PREG_OFFSET_CAPTURE);
    $longest = null;
    foreach ($runs[0] as $run) {
        if ($longest === null || strlen($run[0]) > strlen($longest[0])) {
            $longest = $run;
        }
    }
    if ($longest !== null) {
        $digits = str_pad((string) $n, strlen($longest[0]), '0', STR_PAD_LEFT);
        $pattern = substr_replace($pattern, $digits, $longest[1], strlen($longest[0]));
    }

    return strtr($pattern, [
        'JJJJ' => $when->format('Y'),
        'JJ'   => $when->format('y'),
        'MM'   => $when->format('m'),
        'TT'   => $when->format('d'),
    ]);
}

/**
 * Is at least one of these lists a species list?
 *
 * („branch" is the tree's name: the flag once sat on a branch's root and
 * this walked up to it. Since the Mappen, 0168, every list carries it.)
 */
function find_number_species_branch(PDO $pdo, int $userId, array $listIds): bool
{
    $ids = array_values(array_filter(array_map('intval', $listIds), static fn(int $id): bool => $id > 0));
    if ($ids === []) {
        return false;
    }
    $space = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT 1 FROM map_lists WHERE user_id = ? AND is_species = 1 AND id IN ($space) LIMIT 1");
    $stmt->execute([$userId, ...$ids]);
    return $stmt->fetchColumn() !== false;
}

/**
 * Mint the number for a new point — or null when none is due.
 *
 * THE NEXT NUMBER IS THE HIGHEST ONE ALREADY GIVEN, PLUS ONE — at least
 * the counter (25.09.2026). The counter alone was not enough,
 * measured the same evening: with the restart it remembered only the
 * section of the LAST find, so a find of an older section minted in
 * between — yesterday's offline finds handed in after this morning's, or
 * one find from an old photo in the middle of the year — reset it, and
 * the next find of today drew 260925-01 a second time. Now each section
 * counts from what it already holds (find_number_highest), and so does
 * a pattern without a restart: numbers that came in by import or by hand
 * — the finds of a second phone mapped with another app, „wk-00556" and
 * „wk-00557" — make the counter go on at „wk-00558" by itself. A rule
 * that such numbers must stay below the counter was weighed and dropped:
 * whoever maps a day with another app NEEDS the higher numbers, and the
 * way around it (raise the counter, map a point, import into the gap) is
 * one no user would ever find.
 *
 * THE COUNTER STAYS THE FLOOR: the number one wrote into the pattern
 * („wk-JJJJ-00000543" → 544) counts where no find of the series exists
 * yet — the old numbers are on paper — and it keeps the numbers of finds
 * deleted for good from being given again. It belongs to its section:
 * a find of an older section draws from what that section holds and
 * leaves the counter of the current one alone.
 *
 * ONE MINT PER ACCOUNT AT A TIME. point_create() mints inside its
 * transaction; the account's row is taken FOR UPDATE, so a second device
 * handing in at the same moment waits until the first number is stored,
 * and the numbers are read with a locking read, which sees that commit
 * where a plain read would still see the transaction's older snapshot.
 */
function find_number_mint(PDO $pdo, int $userId, array $listIds, ?string $foundAt): ?string
{
    if (!find_number_ready($pdo)) {
        return null;
    }
    $pattern = find_number_pattern($pdo, $userId);
    if ($pattern === null) {
        return null;
    }
    if (!find_number_species_branch($pdo, $userId, $listIds)) {
        return null;
    }
    return find_number_draw($pdo, $userId, $pattern, $foundAt);
}

/**
 * The step itself: draw the next number under $pattern for a find of
 * $foundAt, as find_number_mint() describes. Called by the mint behind its
 * gates, and by an import for a find whose user asked for „die nächste
 * freie Nummer" — there nobody asks for the species branch, the user did.
 * Inside the caller's transaction, so the lock holds until the number is
 * stored.
 */
function find_number_draw(PDO $pdo, int $userId, string $pattern, ?string $foundAt): string
{
    $restartReady = column_exists($pdo, 'map_users', 'find_counter_period');
    $stmt = $pdo->prepare('SELECT find_counter'
        . ($restartReady ? ', find_number_restart, find_counter_period' : '')
        . ' FROM map_users WHERE id = ? FOR UPDATE');
    $stmt->execute([$userId]);
    $row = $stmt->fetch() ?: [];
    $counter = max(1, (int) ($row['find_counter'] ?? 1));
    $stored  = isset($row['find_counter_period']) ? (string) $row['find_counter_period'] : null;
    // Without a section in the pattern the flag means nothing — a restart
    // there would mint the same number twice.
    $period = !empty($row['find_number_restart']) ? find_number_period($pattern, $foundAt) : null;

    $floor = $period === null || $period['key'] === $stored ? $counter : 1;
    $n = max($floor, find_number_highest($pdo, $userId, $pattern, $period, $foundAt) + 1);

    if ($period === null) {
        $pdo->prepare('UPDATE map_users SET find_counter = ? WHERE id = ?')->execute([$n + 1, $userId]);
    } elseif ($stored === null || strcmp($period['key'], $stored) >= 0) {
        $pdo->prepare('UPDATE map_users SET find_counter = ?, find_counter_period = ? WHERE id = ?')
            ->execute([$n + 1, $period['key'], $userId]);
    }
    // An older section: the stored counter belongs to a later one and
    // stays — the next find of today counts on from it.

    return mb_substr(find_number_render($pattern, $foundAt, $n), 0, 80);
}

/**
 * The highest running number already given in the pattern's shape — 0
 * when there is none. With a section (a restart), only the numbers of the
 * section $foundAt lies in count; without one, every number of the shape,
 * since the counter runs on across years.
 *
 * EVERY FIND OF THE ACCOUNT COUNTS, the trash included (a find put back
 * keeps its number), and so does a number in other case or with fewer
 * leading zeros: „WK-600" is no string twin of „wk-00600", but a person
 * reads the same number. A counter part beyond eight digits lies past
 * anything the counter will reach and is left out — one mistyped import
 * must not throw the series into the millions.
 */
function find_number_highest(PDO $pdo, int $userId, string $pattern, ?array $period, ?string $foundAt): int
{
    $when = null;
    if ($foundAt !== null) {
        $when = DateTime::createFromFormat('Y-m-d H:i:s', $foundAt) ?: null;
    }
    $when ??= new DateTime();
    $fixed = match ($period['unit'] ?? null) {
        'Tag'   => ['JJJJ', 'JJ', 'MM', 'TT'],
        'Monat' => ['JJJJ', 'JJ', 'MM'],
        'Jahr'  => ['JJJJ', 'JJ'],
        default => [],
    };
    [$regex, $prefix] = find_number_matcher($pattern, $fixed, $when);
    if ($regex === null) {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT find_number FROM map_points
                            WHERE user_id = ? AND find_number LIKE ? LOCK IN SHARE MODE');
    $stmt->execute([$userId, addcslashes($prefix, '\\%_') . '%']);
    $highest = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $number) {
        if (preg_match($regex, trim((string) $number), $m) === 1) {
            $digits = ltrim($m[1], '0');
            if (strlen($digits) <= 8) {
                $highest = max($highest, (int) $digits);
            }
        }
    }
    return $highest;
}

/**
 * The pattern split into what it is made of, read as the renderer writes
 * (find_number_render): the counter is the FIRST longest N-run, the date
 * parts are found left to right, the longest first — strtr's order — and
 * everything else is literal text. The matcher and the normalizer below
 * build on it, and so does the browser's twin (findnumber.js).
 *
 * @return array<array{0: string, 1: string|int}>  ['lit', text],
 *         ['date', 'JJJJ'|'JJ'|'MM'|'TT'] or ['counter', its width]
 */
function find_number_tokens(string $pattern): array
{
    $runs = [];
    preg_match_all('/N+/', $pattern, $runs, PREG_OFFSET_CAPTURE);
    $longest = null;
    foreach ($runs[0] as $run) {
        if ($longest === null || strlen($run[0]) > strlen($longest[0])) {
            $longest = $run;
        }
    }
    $tokens = [];
    $scan = static function (string $part) use (&$tokens): void {
        $literal = '';
        for ($i = 0, $len = strlen($part); $i < $len;) {
            foreach (['JJJJ', 'JJ', 'MM', 'TT'] as $key) {
                if (substr($part, $i, strlen($key)) === $key) {
                    if ($literal !== '') {
                        $tokens[] = ['lit', $literal];
                        $literal = '';
                    }
                    $tokens[] = ['date', $key];
                    $i += strlen($key);
                    continue 2;
                }
            }
            $literal .= $part[$i];
            $i++;
        }
        if ($literal !== '') {
            $tokens[] = ['lit', $literal];
        }
    };
    if ($longest === null) {
        $scan($pattern);
        return $tokens;
    }
    $scan(substr($pattern, 0, $longest[1]));
    $tokens[] = ['counter', strlen($longest[0])];
    $scan(substr($pattern, $longest[1] + strlen($longest[0])));
    return $tokens;
}

/**
 * The pattern turned round: a matcher for the numbers it gives — the
 * other half of find_number_render(). The running number becomes a
 * group, the date parts digits, everything else stands as written; the
 * date parts named in $fixed take $when's own values, so only one
 * section matches.
 *
 * @param string[] $fixed  of 'JJJJ', 'JJ', 'MM', 'TT'
 * @return array{0: ?string, 1: string}  the regex (null without a counter)
 *         and the literal start every match shares, to narrow the query
 */
function find_number_matcher(string $pattern, array $fixed, DateTimeInterface $when): array
{
    $tokens = find_number_tokens($pattern);
    if (!in_array('counter', array_column($tokens, 0), true)) {
        return [null, ''];
    }
    $values = ['JJJJ' => $when->format('Y'), 'JJ' => $when->format('y'),
               'MM' => $when->format('m'), 'TT' => $when->format('d')];
    $regex = '';
    $prefix = '';
    $open = true;   // still inside the literal start
    foreach ($tokens as [$kind, $value]) {
        if ($kind === 'lit') {
            $regex .= preg_quote($value, '/');
            $prefix .= $open ? $value : '';
        } elseif ($kind === 'date' && in_array($value, $fixed, true)) {
            $regex .= $values[$value];
            $prefix .= $open ? $values[$value] : '';
        } elseif ($kind === 'date') {
            $regex .= '\d{' . ($value === 'JJJJ' ? 4 : 2) . '}';
            $open = false;
        } else {
            $regex .= '(\d+)';
            $open = false;
        }
    }
    return ['/^' . $regex . '$/iu', $prefix];
}

/**
 * A number typed by hand, in the series' own spelling — when it has the
 * pattern's shape (26.09.2026: „wk-01 darf zu wk-00001
 * werden"). The literal text as the pattern writes it, the date digits
 * as typed, the running number padded to the N-run's width and never
 * cut. Anything of another shape is a series of its own and stays as
 * typed. The browser's twin is normalizeFindNumber() in findnumber.js.
 */
function find_number_normalize(string $pattern, string $value): string
{
    return find_number_read($pattern, $value) ?? $value;
}

/**
 * The same reading, saying whether it matched: the number in the
 * series' spelling, or null when it lacks the pattern's shape — the
 * twin of readFindNumber() in findnumber.js, reduced to the spelling.
 * A bulk numbering needs the difference: a start of the series' own
 * shape counts in the series, anything else is a series of its own
 * (find_number_start).
 */
function find_number_read(string $pattern, string $value): ?string
{
    $tokens = find_number_tokens($pattern);
    if (!in_array('counter', array_column($tokens, 0), true)) {
        return null;
    }
    $regex = '';
    foreach ($tokens as [$kind, $part]) {
        $regex .= match ($kind) {
            'lit'  => preg_quote((string) $part, '/'),
            'date' => '(\d{' . ($part === 'JJJJ' ? 4 : 2) . '})',
            default => '(\d+)',
        };
    }
    if (preg_match('/^' . $regex . '$/iu', trim($value), $m) !== 1) {
        return null;
    }
    $out = '';
    $group = 1;
    foreach ($tokens as [$kind, $part]) {
        if ($kind === 'lit') {
            $out .= $part;
        } elseif ($kind === 'date') {
            $out .= $m[$group++];
        } else {
            $digits = ltrim($m[$group++], '0');
            $out .= str_pad($digits === '' ? '0' : $digits, (int) $part, '0', STR_PAD_LEFT);
        }
    }
    return $out;
}

/**
 * THE KEY A NUMBER IS COMPARED BY: its spelling in the series where it has
 * the series' shape, lower-cased — „WK-1", „wk-01" and „wk-00001" are one
 * number, and a person reads them as one. Every check for a number given
 * twice goes by this key (the import's screening, the point window's line,
 * the numbering window): comparing the letters let „wk-1" from a file sit
 * beside „wk-00001" without a word (found in the review of 26.09.2026).
 * The browser's twin is findNumberKey() in findnumber.js.
 */
function find_number_key(?string $pattern, string $number): string
{
    $number = trim($number);
    if ($pattern !== null) {
        $number = find_number_read($pattern, $number) ?? $number;
    }
    return mb_strtolower($number);
}

/**
 * The numbers the trash holds — a find put back keeps its number, so its
 * number is not free (find_number_highest counts the trash too). For the
 * point window's line and the numbering window, whose pages carry only
 * the living finds.
 *
 * @return array<int, array{number: string, title: string}>
 */
function find_number_trash(PDO $pdo, int $userId): array
{
    if (!find_number_ready($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare("SELECT title, find_number FROM map_points
                            WHERE user_id = ? AND deleted_at IS NOT NULL AND find_number IS NOT NULL AND find_number <> ''");
    $stmt->execute([$userId]);
    return array_map(static fn(array $p): array => ['number' => (string) $p['find_number'], 'title' => (string) $p['title']],
        $stmt->fetchAll());
}

/**
 * The series as the point window needs it on the device: the pattern and
 * where the counter stands — so the number field can say, offline too,
 * which number of the series one types, whether it is free and which is
 * next (findnumber.js, nextFreeNumber). Null without a pattern.
 *
 * @return array{pattern: string, counter: int, restart: bool, period: ?string}|null
 */
function find_number_series(PDO $pdo, int $userId): ?array
{
    $pattern = find_number_pattern($pdo, $userId);
    if ($pattern === null) {
        return null;
    }
    return ['pattern' => $pattern, 'counter' => find_number_next($pdo, $userId),
            'restart' => find_number_restart($pdo, $userId), 'period' => find_number_counter_period($pdo, $userId)];
}

/* ---------------------------------------------------------------------
   MANY NUMBERS AT ONCE — Organisieren → Fundnummern
   (26.09.2026: „das mehrfach umbenennen der Fundnummern"). The window
   shows every number before anything is saved, so the arithmetic lives
   twice: here, where it decides, and in assets/js/numbering.js, where it
   previews. tests/find_number.php and tests/numbering.mjs hold both to
   the same cases — a preview that promises other numbers than the
   server writes would be worse than none.
   --------------------------------------------------------------------- */

/** Does the pattern name a date — JJJJ, JJ, MM or TT anywhere? */
function find_number_dated(string $pattern): bool
{
    return in_array('date', array_column(find_number_tokens($pattern), 0), true);
}

/**
 * The next free number among $numbers — find_number_draw()'s choice over a
 * list instead of the database: the highest number of the series given,
 * plus one, at least the counter, and with a restart only within the
 * section of $foundAt. The twin of nextFreeNumber() in findnumber.js.
 *
 * @param array{pattern: string, counter: int, restart: bool, period: ?string} $series
 * @param string[] $numbers the numbers already given, in any case
 * @return array{n: int, text: string, key: ?string} key: the section, null without a restart
 */
function find_number_next_free(array $series, array $numbers, ?string $foundAt): array
{
    $pattern = $series['pattern'];
    $period = !empty($series['restart']) ? find_number_period($pattern, $foundAt) : null;
    $floor = $period === null || $period['key'] === ($series['period'] ?? null) ? max(1, (int) $series['counter']) : 1;
    $when = $foundAt !== null ? (DateTime::createFromFormat('Y-m-d H:i:s', $foundAt) ?: new DateTime()) : new DateTime();
    $fixed = match ($period['unit'] ?? null) {
        'Tag'   => ['JJJJ', 'JJ', 'MM', 'TT'],
        'Monat' => ['JJJJ', 'JJ', 'MM'],
        'Jahr'  => ['JJJJ', 'JJ'],
        default => [],
    };
    [$regex] = find_number_matcher($pattern, $fixed, $when);
    $highest = 0;
    foreach ($regex === null ? [] : $numbers as $number) {
        if (preg_match($regex, trim((string) $number), $m) === 1) {
            $digits = ltrim($m[1], '0');
            if (strlen($digits) <= 8) {
                $highest = max($highest, (int) $digits);
            }
        }
    }
    $n = max($floor, $highest + 1);
    return ['n' => $n, 'text' => mb_substr(find_number_render($pattern, $foundAt, $n), 0, 80),
            'key' => $period['key'] ?? null];
}

/**
 * „Die nächsten freien" for many finds, one after the other — each draws
 * as find_number_draw() would at that moment, and the counter moves on as
 * it would: past the number in its own section or without a restart, and
 * untouched by a find of an older section.
 *
 * @param (string|null)[] $foundAts in the order the finds are numbered
 * @return array{0: string[], 1: int, 2: ?string} the numbers, then counter and section afterwards
 */
function find_number_draw_many(array $series, array $numbers, array $foundAts): array
{
    $counter = max(1, (int) $series['counter']);
    $period = $series['period'] ?? null;
    $out = [];
    foreach ($foundAts as $foundAt) {
        $next = find_number_next_free(['pattern' => $series['pattern'], 'counter' => $counter,
            'restart' => !empty($series['restart']), 'period' => $period], $numbers, $foundAt);
        if ($next['key'] === null) {
            $counter = $next['n'] + 1;
        } elseif ($period === null || strcmp($next['key'], $period) >= 0) {
            $counter = $next['n'] + 1;
            $period = $next['key'];
        }
        $numbers[] = $next['text'];
        $out[] = $next['text'];
    }
    return [$out, $counter, $period];
}

/**
 * What was typed after „Fortlaufend ab" — the twin of readStart() in
 * numbering.js. Either a FIRST NUMBER, whose last run of digits counts up
 * while everything else stays as typed („neu-01" → neu-02 …; one of the
 * series' own shape in the series' spelling, „wk-01" → wk-00001), or a
 * PATTERN as the settings read one, where JJJJ, JJ, MM and TT become each
 * find's own date and the N-run the running number from 1
 * (26.09.2026: „könnte man bei fortlaufend tatsächlich ein neues Muster
 * eingeben?"). A pattern without an N-run of two starts its count at the
 * last number written in it: „CW-JJJJ-0100" counts from 0100.
 *
 * @return array|null null for nothing typed; ['error' => text] for what
 *         cannot count; otherwise kind 'number' (first, width, head, tail,
 *         own) or 'pattern' (pattern, first, width, dated, unit)
 */
function find_number_start(?string $seriesPattern, string $typed): ?array
{
    $text = trim($typed);
    if ($text === '') {
        return null;
    }
    if (find_number_reserved($text)) {
        return ['error' => t('Eine Fundnummer darf nicht mit „Y-" anfangen. So beginnen die Nummern, die YASAIMAPP selbst vergibt.')];
    }
    $own = $seriesPattern !== null ? find_number_read($seriesPattern, $text) : null;
    if ($own === null && preg_match('/JJ|MM|TT|NN/', $text) === 1) {
        preg_match_all('/N+/', $text, $nRuns);
        $longestN = max(0, ...array_map('strlen', $nRuns[0] ?: ['']));
        preg_match_all('/\d+/', $text, $runs, PREG_OFFSET_CAPTURE);
        $pattern = $text;
        $first = 1;
        if ($longestN < 2 && $runs[0] !== []) {
            $run = end($runs[0]);
            if (strlen($run[0]) > 15) {
                return ['error' => t('Die Zahl in der ersten Nummer ist zu lang.')];
            }
            $pattern = substr($text, 0, $run[1]) . str_repeat('N', max(strlen($run[0]), $longestN + 1))
                . substr($text, $run[1] + strlen($run[0]));
            $first = (int) $run[0];
        } elseif ($longestN === 0) {
            return ['error' => t('Im Muster fehlt die laufende Nummer: NNN oder eine Zahl.')];
        }
        $tokens = find_number_tokens($pattern);
        $width = 1;
        foreach ($tokens as [$kind, $part]) {
            if ($kind === 'counter') {
                $width = (int) $part;
            }
        }
        return ['kind' => 'pattern', 'pattern' => $pattern, 'first' => $first, 'width' => $width,
                'dated' => find_number_dated($pattern), 'unit' => find_number_period($pattern, null)['unit'] ?? null];
    }
    $spelled = $own ?? $text;
    preg_match_all('/\d+/', $spelled, $runs, PREG_OFFSET_CAPTURE);
    if ($runs[0] === []) {
        return ['error' => t('In der ersten Nummer fehlt eine Zahl, die hochzählen kann.')];
    }
    $run = end($runs[0]);
    if (strlen($run[0]) > 15) {
        return ['error' => t('Die Zahl in der ersten Nummer ist zu lang.')];
    }
    return ['kind' => 'number', 'first' => (int) $run[0], 'width' => strlen($run[0]),
            'head' => substr($spelled, 0, $run[1]), 'tail' => substr($spelled, $run[1] + strlen($run[0])),
            'own' => $own !== null];
}

/**
 * The plan: what every chosen find is to carry — the twin of planNumbers()
 * in numbering.js, and the one the server writes by.
 *
 *   'next'    the next free numbers of the series, drawn one after the
 *             other as the mint would (find_number_draw_many)
 *   'from'    counted in the given order from the start (find_number_start),
 *             one count per day, month or year where a dated pattern
 *             begins anew
 *   'remove'  the own number goes; the find wears its „Y-" mark again
 *
 * The scope says who moves: 'all', or 'missing' — only the finds without
 * an own number. A find that keeps its number HOLDS ITS PLACE in a count
 * only when it already carries the very number that place would give:
 * „wk-00003" third in a count from „wk-00001" stays where the notebook has
 * it, and the count walks past any other number — amid „neu-01" a
 * „wk-00003" is none of its business (26.09.2026: „erkennt
 * meine bisherige Nummer wk-003 und zählt danach mit neu-04 weiter").
 *
 * NO NUMBER TWICE, whatever the order: a new number meeting one that
 * stays — outside the choice ($taken), kept, or left without a date — is
 * not given, and the find stays as it is ('taken', with the carrier's
 * title). Leaving a find as it is keeps ITS number, which may in turn
 * meet a new one given before it; so the check runs until nothing moves.
 *
 * @param array<int, array{id: int, title: string, number: ?string, found_at: ?string}> $finds in order
 * @param array<string, string> $taken find_number_key() => title, of own finds outside $finds
 * @return array{rows: array<int, array{id: int, state: string, number: ?string, conflict: ?string}>,
 *               counter: ?int, period: ?string}
 */
function find_number_plan(?array $series, array $finds, array $taken, string $mode, ?array $start,
                          bool $restart, string $scope): array
{
    // Compared by the number in the series, not by its letters
    // (find_number_key) — $taken must be keyed the same way.
    $lower = static fn(?string $s): string => find_number_key($series['pattern'] ?? null, (string) $s);
    $has = static fn(array $f): bool => trim((string) ($f['number'] ?? '')) !== '';
    // Written through point_update(), which spells a number of the
    // account's own shape the series' way — the plan spells it the same,
    // so what the window shows is what gets stored.
    $spell = static fn(string $text): string => $series !== null ? find_number_normalize($series['pattern'], $text) : $text;
    $finds = array_values($finds);

    $pattern = $mode === 'next' ? ($series['pattern'] ?? null)
        : (($start['kind'] ?? '') === 'pattern' ? $start['pattern'] : null);
    $dated = $mode !== 'remove' && $pattern !== null && find_number_dated($pattern);

    $rows = [];
    $moving = [];
    $carriers = $taken;
    foreach ($finds as $i => $f) {
        $moving[$i] = $mode === 'remove' ? $has($f) : ($scope === 'all' || !$has($f));
        $undated = $moving[$i] && $dated && ($f['found_at'] ?? null) === null;
        $rows[$i] = ['id' => (int) $f['id'], 'state' => $undated ? 'undated' : 'kept', 'number' => null, 'conflict' => null];
        if ((!$moving[$i] || $undated) && $has($f)) {
            $carriers[$lower($f['number'])] = (string) ($f['title'] ?? '');
        }
    }
    $counter = null;
    $period = null;

    if ($mode === 'remove') {
        foreach ($finds as $i => $f) {
            if ($moving[$i]) {
                $rows[$i]['state'] = 'new';
            }
        }
        return ['rows' => $rows, 'counter' => null, 'period' => null];
    }

    if ($mode === 'next' && $series !== null) {
        $batch = array_keys(array_filter($rows, static fn(array $r): bool => $r['state'] === 'kept'));
        $batch = array_values(array_filter($batch, static fn(int $i): bool => $moving[$i]));
        [$texts, $counter, $period] = find_number_draw_many($series, array_keys($carriers),
            array_map(static fn(int $i): ?string => $finds[$i]['found_at'] ?? null, $batch));
        foreach ($batch as $k => $i) {
            $rows[$i]['state'] = 'new';
            $rows[$i]['number'] = $spell($texts[$k]);
        }
    } elseif ($mode === 'from' && $start !== null && !isset($start['error'])) {
        $counts = [];
        foreach ($finds as $i => $f) {
            $foundAt = $f['found_at'] ?? null;
            if ($dated && $foundAt === null) {
                continue;
            }
            $section = $pattern !== null && $restart && ($start['unit'] ?? null) !== null
                ? (string) (find_number_period($pattern, $foundAt)['key'] ?? '') : '';
            $n = $counts[$section] ?? (int) $start['first'];
            $text = $spell(mb_substr($pattern !== null
                ? find_number_render($pattern, $foundAt, $n)
                : $start['head'] . str_pad((string) $n, (int) $start['width'], '0', STR_PAD_LEFT) . $start['tail'], 0, 80));
            if (!$moving[$i]) {
                if ($lower($f['number'] ?? null) === $lower($text)) {
                    $counts[$section] = $n + 1;
                }
                continue;
            }
            $counts[$section] = $n + 1;
            $rows[$i]['state'] = 'new';
            $rows[$i]['number'] = $text;
        }
    }

    do {
        $moved = false;
        $stays = $carriers;
        foreach ($rows as $i => $r) {
            if (($r['state'] === 'taken' || $r['state'] === 'kept') && $moving[$i] && $has($finds[$i])) {
                $stays[$lower($finds[$i]['number'])] = (string) ($finds[$i]['title'] ?? '');
            }
        }
        $given = [];
        foreach ($rows as $i => $r) {
            if ($r['state'] !== 'new') {
                continue;
            }
            $key = $lower($r['number']);
            if ($key === $lower($finds[$i]['number'] ?? null)) {
                $rows[$i]['state'] = 'kept';
                $rows[$i]['number'] = null;
                $moved = true;
            } elseif (isset($stays[$key]) || isset($given[$key])) {
                $rows[$i]['state'] = 'taken';
                $rows[$i]['conflict'] = $stays[$key] ?? $given[$key];
                $moved = true;
            } else {
                $given[$key] = (string) ($finds[$i]['title'] ?? '');
            }
        }
    } while ($moved);

    return ['rows' => $rows, 'counter' => $counter, 'period' => $period];
}
