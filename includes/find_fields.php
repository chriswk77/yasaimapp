<?php
/**
 * The find fields — the fixed field set of every species list.
 *
 * WHAT BELONGS TO A FIND MAPPING belongs to every one: Finder, Bestimmer,
 * Bestimmungssicherheit, Menge, Substrat, Begleitvegetation, Geruch,
 * Geschmack, Sporenpulverfarbe, Belegnummer, whether a voucher is kept,
 * and a general remark.
 * If these fields were created per list individually, the same thing would
 * soon have three names ("Bestimmer", "bestimmt von", "Det.") — and any
 * evaluation across them would be none. That is why the set lives EXACTLY
 * ONCE, here, and automatically applies to every list with the species-list
 * checkmark, inherited by its whole branch like the list's own root fields.
 *
 * THE MAP STAYS NEUTRAL: Whoever creates no species list sees none of
 * these fields. Only the checkmark turns a list into a find mapping.
 *
 * THE VALUES live, like all field values, in the point's extra block,
 * under the field name. There is no second storage location — field times,
 * sharing, search and import treat find fields like any other field.
 *
 * Custom fields with the same name are filtered out when a list is saved
 * (list_fields_set) and suppressed in the display — the find field
 * wins, otherwise the uniformity the set exists for would be gone.
 */

declare(strict_types=1);

/**
 * The set, in display order. The same shape as the custom fields from
 * lists_load(), plus 'fixed' as a marker for the interface.
 *
 * @return array[]
 */
function find_fields(): array
{
    return [
        ['name' => 'Finder',    'type' => 'text', 'is_species' => false, 'options' => null, 'fixed' => true],
        ['name' => 'Bestimmer', 'type' => 'text', 'is_species' => false, 'options' => null, 'fixed' => true],
        ['name' => 'Bestimmungssicherheit', 'type' => 'choice', 'is_species' => false,
         // A scale, not an alphabet: macroscopically certain, microscopically
         // certain, genetically certain, uncertain, undetermined — in this
         // order. Written out since 0069 (23.08.2026) — the
         // options are STORED VALUES, so the migration renamed the stock
         // along. „genetisch sicher" joined on 25.09.2026 for a sequenced
         // find — not „ITS": ITS is one marker of several (LSU, TEF1,
         // RPB2 …), and the level says HOW it was determined, not with
         // which marker; that stands at the sequence itself.
         'options' => ['makroskopisch sicher', 'mikroskopisch sicher', 'genetisch sicher', 'unsicher', 'unbestimmt'],
         'fixed' => true],
        // 'inputmode' steers which keyboard the phone opens FIRST
        // (23.08.2026): 'numeric' brings the digit pad for a
        // field one almost always answers with a count. The field itself
        // stays free text — "ca. 20" and "viele" remain writable, the
        // attribute never validates, it only sorts the keys.
        ['name' => 'Anzahl / Menge',    'type' => 'text',       'is_species' => false, 'options' => null, 'fixed' => true,
         'inputmode' => 'numeric'],
        /* THEIR OWN GROUPS, NOT THE LIST'S (12.11.2026): a host
           is a plant, an insect or a fungus — „die können auch mal Wirt
           sein" —, the companions are plants alone. Fixed, not a choice in
           the list's window; the device keeps these packages wherever a
           species list is (map.js, speciesNeedsSync). */
        ['name' => 'Substrat / Wirt',   'type' => 'text',       'is_species' => true,  'options' => null, 'fixed' => true,
         'species_groups' => ['plants', 'insects', 'fungi']],
        ['name' => 'Begleitvegetation', 'type' => 'multiline', 'is_species' => true,  'options' => null, 'fixed' => true,
         'species_groups' => ['plants']],
        /* ODOUR, TASTE, SPORE PRINT (09.11.2026: „mit festen
           Labeln könnte man dann später wieder Statistiken auswerten").
           Text fields like the rest — export, import, search and sharing
           need nothing new —, with a 'picker' the windows add beside them:
           terms by group to click, or the spore print's colour field
           (assets/js/findtraits.js, which also says how the text is
           written). A word typed by hand stays beside the picked ones. */
        ['name' => 'Geruch',            'type' => 'text',       'is_species' => false, 'options' => null, 'fixed' => true,
         'picker' => 'odour'],
        ['name' => 'Geschmack',         'type' => 'text',       'is_species' => false, 'options' => null, 'fixed' => true,
         'picker' => 'taste'],
        ['name' => 'Sporenpulverfarbe', 'type' => 'text',       'is_species' => false, 'options' => null, 'fixed' => true,
         'picker' => 'spore_print'],
        /* THE VOUCHER KEPT — a tick (26.09.2026). He means to
           write the find number on his vouchers rather than keep a voucher
           system of his own; the DGfM delivery then carries the find number
           as the voucher number, and this tick says that a voucher exists
           („Beleg: ja" in MykIS — mykis_row). A CHOICE WITH ONE ANSWER, not
           a new kind of field: stored is „ja" or nothing, and everything
           that reads fields — export, import, search, the server's checks
           of a choice — knows that already. Only the windows draw a choice
           with a single answer as a tick (map.js, organize.js).

           THE TICK FIRST, THE NUMBER ONLY BEHIND IT (26.09.2026:
           „nur den Haken und erst wenn der Haken gesetzt ist, das Feld
           Belegnummer. Außer es ist ausgefüllt. Dann sollte aber auch der
           Haken gesetzt sein" — filled by hand, not by the find number the
           DGfM delivery puts in its place). 'reveals' names the field the
           tick uncovers: the windows show it only while the tick is set,
           a number entered sets the tick, and unticking empties it; the
           import sets the tick where a file brings a number. */
        /* UNDER THE FIND NUMBER, not among the find fields
           (09.11.2026: „Beleg aufbewahrt bitte mal unterhalb Fundnummer und
           über Fundzeit"): the voucher belongs to the number one writes on
           it. 'after' => 'number' moves a field there in the point window
           (pointwindow.js, extraAfter); everything else reads the set in
           this order as before. Its number goes along — it is the tick's. */
        ['name' => 'Beleg aufbewahrt',  'type' => 'choice',     'is_species' => false, 'options' => ['ja'], 'fixed' => true,
         'reveals' => 'Beleg-Nr.', 'after' => 'number'],
        ['name' => 'Beleg-Nr.',         'type' => 'text',       'is_species' => false, 'options' => null, 'fixed' => true,
         'after' => 'number'],
        /* THE GENERAL REMARK (09.11.2026): what the identification
           taught across the books, and what no key asks — „die Schlüsselfrage
           mit schleimigem Hut konnte ich nur so beantworten, weil … davor 3
           Tage 30 Grad … ich konnte das Merkmal nicht am Fund selbst
           überprüfen". Here because free words belong to the FIND, not to a
           plate (the plate's free text went the same day): a plate shows
           it as a detail like any other field. Beside the Notiz, which
           stays the find's own jotting. The remarks PER BOOK belong to the
           identification module and come from the taxonomy session. */
        ['name' => 'Bemerkung',         'type' => 'multiline',  'is_species' => false, 'options' => null, 'fixed' => true],
    ];
}

/** The names of the set, lowercased — for filtering out duplicates. */
function find_field_names(): array
{
    return array_map(static fn(array $f): string => mb_strtolower($f['name']), find_fields());
}

/** Only the find fields that carry species names — for the species table. */
function find_field_species_names(): array
{
    return array_column(
        array_filter(find_fields(), static fn(array $f): bool => $f['is_species']),
        'name'
    );
}
