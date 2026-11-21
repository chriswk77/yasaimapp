<?php
/**
 * Directory of the name packages.
 *
 *   GET species_packages.php
 *
 * What exists, where it comes from, how big it is and which version is
 * currently in effect. The administration puts that side by side and
 * compares the version with what sits on this device; the search in the
 * browser learns here which packages it can know about at all.
 *
 * Only description, no names — the response is barely a kilobyte and may
 * therefore come fresh on every call.
 */

declare(strict_types=1);

/* GUESTS TOO (24.08.2026): without this directory the browser
   learns of no package, fetches none, and the title field stays without
   suggestions — measured on a guest's phone. species.php has been open
   for the same reason; here nothing user-bound is served either, only
   which name lists exist. The names themselves are reference data,
   identical for everyone. */
const EXCURSION_GUESTS_ALLOWED = true;

require __DIR__ . '/_start.php';
require_once __DIR__ . '/../../includes/species_packages.php';

if ($method !== 'GET') {
    json_error(t('Methode nicht unterstützt.'), 405);
}

$packages = [];
foreach (species_packages() as $key => $p) {
    $rev = species_package_rev($pdo, $key);
    $packages[] = [
        'identifier'     => $key,
        'name'        => $p['name'],
        'origin'      => $p['origin'],
        'text'        => $p['text'],
        'automatic'   => $p['automatic'],
        'version'     => $rev['version'],
        'rows'      => $rev['rows'],
        'bytes'       => $rev['bytes'],
    ];
}

json_response(['packages' => $packages]);
