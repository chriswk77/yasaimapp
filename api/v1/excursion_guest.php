<?php
/**
 * The guest's own entry: name and, if he wants it after all, his e-mail.
 *
 *   GET   excursion_guest.php        what stands there now
 *   PATCH excursion_guest.php        first_name, last_name, email
 *
 * WHO MAY: only the guest himself, and only while his excursion runs —
 * excursion_actor() answers both at once, and the door closes with the
 * window like every other guest right (see _start.php).
 *
 * WHY IT EXISTS (24.08.2026): a name is mistyped in the
 * car park, and whoever kept the e-mail at the door may change his mind
 * once he sees what he has collected — that is exactly when the later
 * attribution becomes worth something to him.
 */

declare(strict_types=1);

const EXCURSION_GUESTS_ALLOWED = true;

require __DIR__ . '/_start.php';

// A logged-in person has an account for this; here only the guest acts.
if ($actor === null) {
    json_error(t('Nur für Exkursionsgäste.'), 403);
}

try {
    switch ($method) {
        case 'GET':
            // The split columns arrive with migration 0076 — code before
            // migration, so the display name has to do until then.
            $split = excursion_guest_names_ready($pdo);
            $stmt = $pdo->prepare('SELECT name, email'
                . ($split ? ', first_name, last_name' : '')
                . ' FROM map_excursion_guests WHERE id = ?');
            $stmt->execute([$actor['guest_id']]);
            $row = $stmt->fetch() ?: [];
            $parts = explode(' ', (string) ($row['name'] ?? ''));
            $last  = count($parts) > 1 ? array_pop($parts) : '';
            json_response([
                'first_name' => (string) ($row['first_name'] ?? implode(' ', $parts)),
                'last_name'  => (string) ($row['last_name'] ?? $last),
                'email'      => (string) ($row['email'] ?? ''),
            ]);
            // json_response terminates

        case 'PATCH':
        case 'PUT':
            json_response(excursion_guest_update($pdo, $actor,
                (string) ($input['first_name'] ?? ''),
                (string) ($input['last_name'] ?? ''),
                (string) ($input['email'] ?? '')));

        default:
            json_error(t('Methode nicht unterstützt.'), 405);
    }
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 422);
}
