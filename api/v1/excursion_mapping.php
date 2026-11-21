<?php
/**
 * Start and stop mapping for an excursion.
 *
 *   POST   excursion_mapping.php  {excursion: <id>}   start — the map's
 *          badge (13.09.2026: „jetzt teilnehmen"), for a
 *          leader or a participant already on the list, while the window
 *          is open (excursion_map_along). Answers with the same 'mapping'
 *          the start payload carries, so the badge can be drawn without
 *          a reload.
 *   DELETE excursion_mapping.php                      stop
 *
 * The DELETE is the counterpart to the tap at the door
 * (pages/excursion.php, 'join_as_user'): the session forgets the
 * excursion, and from the next find on nothing carries its reference any
 * more. What was already collected stays with it — it belongs to the
 * excursion, and the badge was never a claim on the past.
 *
 * ONLY THE SESSION IS TOUCHED, no row anywhere: the guest entry stays,
 * because it is what ties the finds to the person later. Whoever taps
 * the QR code — or the offer on the map — again is back in a second.
 */

declare(strict_types=1);

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/excursions.php';

try {
    if ($method === 'POST') {
        $e = excursion_map_along($pdo, (int) $me['id'], (int) ($input['excursion'] ?? 0));
        json_response(['mapping' => excursion_mapping_payload($pdo, $e)]);
    }
    if ($method === 'DELETE') {
        unset($_SESSION['excursion_mapping']);
        json_response(['mapping' => null]);
    }
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 422);
}
json_error(t('Methode nicht unterstützt.'), 405);
