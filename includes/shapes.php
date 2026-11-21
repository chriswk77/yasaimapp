<?php
/**
 * The lists' marker shapes — the geometry once for the whole project.
 *
 * WHY NOT IN icon.php: these shapes are CONTENT, not a control. The user
 * picks one per list, they carry their own rules (white border only on the
 * outside, paint-order) and they grow with the demands on the mapping, not
 * with those on the interface. The header of icon.php says the same
 * thing from the other side: what does not belong there belongs here.
 *
 * WHY NO LONGER IN map.js: they lived there as long as only the map
 * needed them. Since the containers in the admin area show their mounted
 * lists with the lists' own symbol, PHP needs them too — and a second copy
 * of the path data would be exactly the state the icon library set out
 * against (there it was two clocks that had already drifted apart before
 * anyone noticed). The way back into the script goes via shape_stock(),
 * as with the icons: assets/js/shapes.js takes them from the <template>
 * block instead of carrying them a second time.
 *
 * DRAWING HAPPENS IN A FIELD OF 24 BY 24 UNITS. All shapes sit centered,
 * only the little flag does not: its tip stands bottom left, because a flag
 * points at its point instead of covering it. Where the point actually lies
 * is recorded as FORM_ANKER in map.js — that is Leaflet's arithmetic and
 * belongs to the map, not to the drawing.
 *
 * A SHAPE IS ONE ELEMENT. It may consist of several pieces, but all must
 * stand in the same d="": within ONE <path>, SVG draws all strokes first
 * and then all fills; across several elements it does not. Two overlapping
 * <circle>s get a white arc right through the figure — measured, not
 * assumed.
 */

declare(strict_types=1);

/**
 * The drawings. Key is the name from map_lists.symbol, value the inside of
 * the <svg> — no color, no size, no border: what becomes of it is decided
 * only by the place that draws it. On the map it gets a white fringe and an
 * anchor, in a list row neither of the two.
 *
 * On an aerial photo, color alone is often not enough — green on forest,
 * yellow on meadow. With a second distinction via shape, lists remain
 * tellable apart even then, and with color blindness at all.
 */
function shapes_all(): array
{
    $shapes = [
        'circle'     => '<circle cx="12" cy="12" r="8"/>',
        /* THE POLE HAS A WIDTH (24.09.2026: „die Fahne hat bei
           der Symbolauswahl keine Stange, nur in der Karte sehe ich eine").
           It used to be a line of no width — M6 21.5 V3.2 … H6 z runs down
           and up the same x — so it had no area: the picker and the lists,
           which fill without an edge, showed the cloth floating in the air,
           and only the map drew a pole, because its white edge strokes even
           a line of no width. Now a bar 1.6 wide, its foot where the anchor
           was (map.js, 6/21.5), the cloth hung on its right side. */
        'flag' => '<path d="M5.2 2.4h1.6v19.1H5.2z M6.8 3.2h10.8L14.4 8l3.2 4.8H6.8z"/>',
        'cross'     => '<path d="m5.5 8 2.5-2.5 4 4 4-4L18.5 8l-4 4 4 4-2.5 2.5-4-4-4 4L5.5 16l4-4z"/>',

        /* FOUR SIGNS FOR PLACES, NOT FOR ORGANISMS (23.09.2026:
           the area lists, the burn sites, a car park). Drawn filled like
           every shape here; holes by winding (banned) or even-odd (parking)
           so the white fringe on the map runs round the figure, not
           through it.

           BANNED — a struck-through ring for the eutrophied, spoiled area:
           outer circle clockwise, the two halves of the hole either side
           of the bar counter-clockwise, all one path. */
        'banned'    => '<path d="M12 3a9 9 0 1 1 0 18a9 9 0 1 1 0-18z'
                     . 'M9.04 6.78L17.22 14.96A6 6 0 0 0 9.04 6.78z'
                     . 'M14.96 17.22L6.78 9.04A6 6 0 0 0 14.96 17.22z"/>',
        // CHECK — the promising area: a thick tick in the cross's weight.
        'check'     => '<path d="M3.8 12.6 6.5 9.9l3.6 3.6 7.4-7.4 2.7 2.7L10.1 18.9z"/>',
        // FIRE — a flame for the burn sites; it stands on its base (anchor).
        'fire'      => '<path fill-rule="evenodd" d="M12 2c.4 3.2 3.6 5 5.2 8 1.6 3 1.2 6.4-.9 8.5-1.4 1.4-3.2 2.1-4.3 2.1'
                     . 's-2.9-.7-4.3-2.1C5.6 16.4 5.4 13.3 7 10.6c.5 1.4 1.4 2.3 2.5 2.7C9 9 10.8 5.3 12 2z'
                     . 'M12 12.4c.9 1.6 2.3 2.6 2.3 4.4a2.3 2.3 0 0 1-4.6 0c0-1.8 1.4-2.8 2.3-4.4z"/>',
        // PARKING — a rounded square with the P cut out, its bowl filled again.
        'parking'   => '<path fill-rule="evenodd" d="M6.5 3.5h11a3 3 0 0 1 3 3v11a3 3 0 0 1-3 3h-11a3 3 0 0 1-3-3v-11a3 3 0 0 1 3-3z'
                     . 'M9 7h4.2a3.1 3.1 0 0 1 0 6.2h-2V17H9z'
                     . 'M11.2 9h2a1.1 1.1 0 0 1 0 2.2h-2z"/>',

        /* CAMPINGPLATZ (24.09.2026): the triangle left the
           picker — worn only by Finn's forest fires, which took the flame,
           and by the maintainer's own campsites, which wanted „ein eigenes
           Campingplatz-Symbol". A tent with its door flap thrown back and
           a small conifer beside it, on a line of ground; the tent keeps
           something of the triangle it replaces. The door is a hole, the
           flap an element of its own so its white edge parts it from the
           tent. It stands on its ground (anchor in map.js). */
        'campsite'  => '<path d="M10.4 6.4L18.6 20.6L2.2 20.6Z M1.4 20.65L22.8 20.65L22.8 21.55L1.4 21.55Z'
                     . ' M20.3 16.6L20.3 20.6L19.3 20.6L19.3 16.6Z M19.8 7.4L22.6 13L21.2 12.8L23 17.2L16.6'
                     . ' 17.2L18.4 12.8L17 13Z M10.4 20.62L13 20.62L10.4 10.8Z"/>'
                     . '<path d="M10 20.62L7.4 20.62L10.4 10.8Z"/>',

        /* ACCOMMODATION beside the campsite (24.09.2026: „für die
           Unterkünfte neben dem Zeltplatz bräuchte man noch die Pension und
           das Hotel"). Three signs, all taken. PENSION: a small gabled
           house — door, two windows, a round window in the gable, a
           chimney. HOTEL: a tall block with a flat roof, a grid of windows
           and the entrance. ÜBERNACHTUNG: the bed of the road sign, someone
           asleep under the blanket, for any place to sleep. The pointed
           roof and the flat one tell house and block apart before any
           window does. An „H" was not drawn on purpose: in Germany it
           marks a bus stop. Windows and doors are holes; the sleeper's head
           and blanket are elements of their own. All stand on their ground
           (anchors in map.js). */
        'guesthouse' => '<path d="M5 11L19 11L19 21.5L5 21.5Z M12 3.2L21.6 11.9L2.4 11.9Z M15.6 4.6L17.4'
                     . ' 4.6L17.4 9.4L15.6 9.4Z M10.7 21.52L13.3 21.52L13.3 15.2L10.7 15.2Z M6.6 15.8L9 15.8L9'
                     . ' 13.4L6.6 13.4Z M15 15.8L17.4 15.8L17.4 13.4L15 13.4Z M12.99 8.12L12.69 7.74L12.24'
                     . ' 7.53L11.76 7.53L11.31 7.74L11.01 8.12L10.9 8.6L11.01 9.08L11.31 9.46L11.76 9.67L12.24'
                     . ' 9.67L12.69 9.46L12.99 9.08L13.1 8.6Z"/>',
        'hotel'     => '<path d="M5.4 4.4L18.6 4.4L18.6 21.5L5.4 21.5Z M4.6 3.2L19.4 3.2L19.4 4.6L4.6 4.6Z M7'
                     . ' 8L9.4 8L9.4 6.2L7 6.2Z M10.8 8L13.2 8L13.2 6.2L10.8 6.2Z M14.6 8L17 8L17 6.2L14.6 6.2Z'
                     . ' M7 11L9.4 11L9.4 9.2L7 9.2Z M10.8 11L13.2 11L13.2 9.2L10.8 9.2Z M14.6 11L17 11L17'
                     . ' 9.2L14.6 9.2Z M7 14L9.4 14L9.4 12.2L7 12.2Z M10.8 14L13.2 14L13.2 12.2L10.8 12.2Z'
                     . ' M14.6 14L17 14L17 12.2L14.6 12.2Z M7 17L9.4 17L9.4 15.2L7 15.2Z M10.8 17L13.2 17L13.2'
                     . ' 15.2L10.8 15.2Z M14.6 17L17 17L17 15.2L14.6 15.2Z M10.5 21.52L13.5 21.52L13.5 18L10.5'
                     . ' 18Z"/>',
        'lodging'   => '<path d="M2.6 8.6L4.6 8.6L4.6 20.8L2.6 20.8Z M2.6 15L21.4 15L21.4 17.8L2.6 17.8Z M19.8'
                     . ' 12.8L21.4 12.8L21.4 17.8L19.8 17.8Z M5.2 14.6L5.23 13.99L5.32 13.5L5.46 13.11L5.68'
                     . ' 12.83L5.95 12.62L6.3 12.49L6.71 12.42L7.2 12.4L7.75 12.42L8.22 12.49L8.6 12.62L8.9'
                     . ' 12.82L9.13 13.11L9.28 13.5L9.37 13.99L9.4 14.6Z M2.6 17.6L4.2 17.6L4.2 20.8L2.6 20.8Z'
                     . ' M19.8 17.6L21.4 17.6L21.4 20.8L19.8 20.8Z"/>'
                     . '<path d="M8.9 10.6L8.8 11.18L8.5 11.69L8.05 12.07L7.5 12.27L6.9 12.27L6.35 12.07L5.9'
                     . ' 11.69L5.6 11.18L5.5 10.6L5.6 10.02L5.9 9.51L6.35 9.13L6.9 8.93L7.5 8.93L8.05 9.13L8.5'
                     . ' 9.51L8.8 10.02Z"/>'
                     . '<path d="M9.8 15L9.98 13.91L10.37 13.03L10.93 12.35L11.65 11.85L12.5 11.52L13.46'
                     . ' 11.34L14.5 11.31L15.6 11.4L16.46 11.57L17.24 11.79L17.92 12.08L18.5 12.45L18.97'
                     . ' 12.91L19.31 13.48L19.53 14.17L19.6 15Z"/>',

        /* RASTPLATZ (24.09.2026: „was brauchen wir noch? Ein
           Rastplatz, so einen Picknicktisch"): the picnic table end-on, as
           on the road sign — the top, the legs in an A, the crossbar with a
           bench on either side. A version with a conifer beside it matched
           the campsite but gave the table less of the field; he took the
           table alone. It stands on its legs (anchor in map.js). */
        'rest_area' => '<path d="M5.6 7.4L18.4 7.4L18.4 9.4L5.6 9.4Z M1.8 14.6L22.2 14.6L22.2 15.9L1.8 15.9Z'
                     . ' M9.51 9.44L5.51 21.24L4.09 20.76L8.09 8.96Z M15.91 8.96L19.91 20.76L18.49 21.24L14.49'
                     . ' 9.44Z M1.8 12.9L6.4 12.9L6.4 14.7L1.8 14.7Z M17.6 12.9L22.2 12.9L22.2 14.7L17.6'
                     . ' 14.7Z"/>',

        /* PILZ — TWO MUSHROOMS SINCE 24.09.2026, the maintainer's pick of five
           (today's with a seam, a bolete, a fly agaric, a gilled one, the
           pair). It is the most frequent marker on the map — every new
           account starts with „Pilze" — so it got five drafts, not one.

           A SEAM BETWEEN CAP AND STEM, his wish: the stems are drawn
           first, the caps after them, and a cap's white edge cuts the
           seam across the top of its stem. Until now cap and stem were
           one path, a leftover from before paint-order, and the mushroom
           read as a single blob. Three of the other drafts followed as
           shapes of their own — see below.

           It stands on the ground between the two stems (anchor 12/21 in
           map.js). */
        'mushroom'  => '<path d="M12.4 11.4H16V18.4C16 20 15.2 21 14.2 21C13.2 21 12.4 20 12.4 18.4Z'
                     . 'M5 16.4H7.4V19.7C7.4 20.5 6.9 21 6.2 21C5.5 21 5 20.5 5 19.7Z"/>'
                     . '<path d="M14.2 4C10.6 4 7.8 6.5 7.8 9.6C7.8 10.1 8.2 10.5 8.7 10.5H19.7C20.2 10.5'
                     . ' 20.6 10.1 20.6 9.6C20.6 6.5 17.8 4 14.2 4Z'
                     . 'M6.2 11.3C4 11.3 2.3 12.9 2.3 14.9C2.3 15.3 2.6 15.6 3 15.6H9.4C9.8 15.6 10.1 15.3'
                     . ' 10.1 14.9C10.1 12.9 8.4 11.3 6.2 11.3Z"/>',

        /* THREE MORE MUSHROOMS, from the same five drafts
           (24.09.2026: „warum behalten wir nicht den Fliegenpilz? Der war
           ja ganz toll. Den Steinpilz. Und den einzelnen Pilz mit der
           Trennlinie. Aber Standard ist der Doppelpilz"). Each one draws
           its stem first and its cap after, so the cap's white edge cuts
           the seam. The fly agaric's spots are holes: on the map the white
           edge runs round them and fills them — white dots on the cap, as
           in life. It was declined as the SIGN OF EVERY FUNGUS LIST, for
           saying „poisonous"; as a choice of one's own it says exactly
           what the person choosing it means.

           The picker names the two by what they STAND FOR, not by the
           species drawn (24.09.2026): the bolete is
           „Speisepilz", the fly agaric „Giftpilz". The keys keep the
           drawing's name — they are stored in map_lists.symbol, a label
           is not. */
        'single_mushroom' => '<path d="M9.6 12.5H14.4V18.1C14.4 19.8 13.4 21 12 21C10.6 21 9.6 19.8 9.6 18.1Z"/>'
                     . '<path d="M12 3C7.3 3 3.5 6.4 3.5 10.6C3.5 11.1 3.9 11.5 4.4 11.5H19.6C20.1 11.5 20.5 11.1'
                     . ' 20.5 10.6C20.5 6.4 16.7 3 12 3Z"/>',
        'bolete'    => '<path d="M9.7 12.8H14.3C15.3 14.3 16.4 16.2 16.4 18.1C16.4 19.9 14.6 21.1 12 21.1C9.4 21.1'
                     . ' 7.6 19.9 7.6 18.1C7.6 16.2 8.7 14.3 9.7 12.8Z"/>'
                     . '<path d="M12 3.2C6.9 3.2 3 6.2 3 10C3 11.2 4 11.8 5.6 11.8H18.4C20 11.8 21 11.2 21 10C21 6.2'
                     . ' 17.1 3.2 12 3.2Z"/>',
        'fly_agaric' => '<path d="M10.3 12.4H13.7L13.65 13.9C14.6 14.1 15.1 14.5 15 14.9C14.9 15.2 14.3 15.3 13.6 15.3'
                     . 'L13.8 18.6C14.8 18.9 15.3 19.6 15.1 20.2C14.9 20.9 13.7 21.2 12 21.2C10.3 21.2 9.1 20.9 8.9 20.2'
                     . 'C8.7 19.6 9.2 18.9 10.2 18.6L10.4 15.3C9.7 15.3 9.1 15.2 9 14.9C8.9 14.5 9.4 14.1 10.35 13.9Z"/>'
                     . '<path fill-rule="evenodd" d="M12 3C7.4 3 3.6 6.2 3.3 10.4C3.2 11.1 3.7 11.6 4.4 11.6H19.6'
                     . 'C20.3 11.6 20.8 11.1 20.7 10.4C20.4 6.2 16.6 3 12 3Z'
                     . 'M6.95 7.6A1.15 1.15 0 1 0 9.25 7.6A1.15 1.15 0 1 0 6.95 7.6Z'
                     . 'M11.45 5.4A1.05 1.05 0 1 0 13.55 5.4A1.05 1.05 0 1 0 11.45 5.4Z'
                     . 'M14.7 8.3A1.2 1.2 0 1 0 17.1 8.3A1.2 1.2 0 1 0 14.7 8.3Z'
                     . 'M10.85 9.4A0.75 0.75 0 1 0 12.35 9.4A0.75 0.75 0 1 0 10.85 9.4Z'
                     . 'M5.1 10.2A0.6 0.6 0 1 0 6.3 10.2A0.6 0.6 0 1 0 5.1 10.2Z'
                     . 'M17.8 10.3A0.6 0.6 0 1 0 19 10.3A0.6 0.6 0 1 0 17.8 10.3Z"/>',

        /*
         * Designed and reviewed at 11 px (sidebar), 20 px (picker) and
         * 24 px (map, with border, on forest as on field). What failed in
         * the process stands here as a warning, because it will suggest
         * itself again with the next symbol:
         *
         *   A DECIDUOUS TREE — round crown on a trunk — is, at 11 px, the
         *   mushroom. Both are a blob above a stalk. Hence the conifer:
         *   the jags carry down to the smallest size.
         *
         *   A tapering insect body becomes a leaf, and wings that nestle
         *   into the outline become bumps. The animal stays recognizable
         *   only through the deep notch between the wings.
         *
         *   The lichen's cup must SINK IN distinctly. If it bulges even
         *   slightly, it is a cap again — the difference from the mushroom
         *   is exactly that one curvature.
         *
         * These three lessons still hold; they come from the size, not
         * from the technique. A fourth, by contrast, is obsolete: until
         * paint-order arrived, every shape had to be ONE closed contour,
         * because the white border ran around every subpath. The stag is
         * still drawn to that rule — it is no longer necessary. (The
         * conifer this block was written for gave way to the seedling on
         * 24.09.2026; its lesson about the deciduous tree stands.)
         */

        /* PFLANZE — A SEEDLING (24.09.2026). The plant lists wore
           the conifer, and „das passt vielleicht zum Pilz als
           Mykorrhiza-Partner, aber eigentlich gibt es ja mehr
           Blütenpflanzen": a plant list holds herbs, flowers, grasses,
           ferns. Of a deciduous tree, a flower, a leaf, a fern, a clover
           and five seedlings he chose this one — two pointed leaves, the
           right one bigger as they grow, a midrib cut out of each, on a
           stem out of a little mound of soil. It REPLACED the conifer
           ('tree'): every list that wore it wears the seedling (migration
           0122, and LIST_SYMBOL_ALIAS in data.php for devices that still
           hold the old name). Trees may return as shapes of their own,
           under new names. It stands on the mound (anchor 12/21.5 in
           map.js). */
        'plant'     => '<path d="M11.35 20.2L11.4 14.99L11.55 10.79L12.65 10.81L12.6 15.01L12.65 20.2Z M6.6 21.5L7.16'
                     . ' 21.04L7.9 20.67L8.79 20.38L9.8 20.17L10.88 20.04L12 20L13.12 20.04L14.2 20.17L15.21'
                     . ' 20.38L16.1 20.67L16.84 21.04L17.4 21.5Z M11.7 11.6L10.88 11.86L10.08 11.97L9.31'
                     . ' 11.94L8.57 11.79L7.88 11.51L7.23 11.13L6.62 10.65L6.08 10.09L5.6 9.44L5.18 8.73L4.84'
                     . ' 7.96L4.58 7.15L5.42 7.03L6.26 7L7.08 7.06L7.87 7.21L8.62 7.45L9.32 7.78L9.95 8.2L10.5'
                     . ' 8.71L10.96 9.3L11.32 9.98L11.57 10.75Z M12.4 10.6L12.45 9.65L12.65 8.78L12.98 7.99L13.43'
                     . ' 7.28L13.99 6.66L14.64 6.12L15.37 5.67L16.17 5.31L17.03 5.04L17.93 4.87L18.86 4.79L19.81'
                     . ' 4.81L19.6 5.74L19.3 6.62L18.92 7.45L18.45 8.22L17.91 8.91L17.29 9.51L16.61 10.01L15.87'
                     . ' 10.4L15.07 10.67L14.22 10.8L13.33 10.78Z M10.97 10.88L6.18 7.98L6.02 8.23L10.73 11.26Z'
                     . ' M13.33 10.16L18.4 6.1L18.22 5.86L13.05 9.81Z"/>',

        /* TWO TREES AGAIN, drawn anew and chosen (24.09.2026,
           after the conifer had given its place to the seedling: „wir können
           gerne noch eine Tanne und einen Laubbaum dazufügen, dann aber mit
           neuen Symbolen, die wir erstmal auswählen"). Of a fir, a spruce, a
           pine, a lobed broadleaf, an oak and a birch he took the SPRUCE and
           the OAK — named for what they stand for, „Nadelbaum" and
           „Laubbaum". The spruce: narrow, a jagged edge of seven layers, the
           trunk behind it. The oak: a broad crown of seven overlapping
           clusters on a thick trunk with a root flare — wider than high,
           because a round crown on a thin stalk is at 11 px a mushroom (the
           lesson above; the pine among the drafts fell into it). Both stand
           on the trunk (anchor 12/21.5 in map.js). */
        'conifer'   => '<path d="M11.15 17.2L12.85 17.2L12.85 21.5L11.15 21.5Z"/>'
                     . '<path d="M12.88 3.65L13.7 4.2L13.26 5.7L14.42 6.25L13.63 7.75L15.14 8.3L14.01 9.8L15.86'
                     . ' 10.35L14.38 11.85L16.58 12.4L14.76 13.9L17.3 14.45L15.13 15.95L18.02 16.5L13.2 17.9L10.8'
                     . ' 17.9L5.98 16.5L8.87 15.95L6.7 14.45L9.24 13.9L7.42 12.4L9.62 11.85L8.14 10.35L9.99 9.8L8.86'
                     . ' 8.3L10.37 7.75L9.58 6.25L10.74 5.7L10.3 4.2L11.12 3.65L12 1.8Z"/>',
        'broadleaf' => '<path d="M10.8 13.4L13.2 13.4L13.5 19.1L15.3 21.5L8.7 21.5L10.5 19.1Z"/>'
                     . '<path d="M9.2 10.5L9.12 11.19L8.89 11.85L8.52 12.43L8.03 12.92L7.45 13.29L6.79 13.52L6.1'
                     . ' 13.6L5.41 13.52L4.75 13.29L4.17 12.92L3.68 12.43L3.31 11.85L3.08 11.19L3 10.5L3.08 9.81L3.31'
                     . ' 9.15L3.68 8.57L4.17 8.08L4.75 7.71L5.41 7.48L6.1 7.4L6.79 7.48L7.45 7.71L8.03 8.08L8.52'
                     . ' 8.57L8.89 9.15L9.12 9.81Z M13 7.3L12.91 8.08L12.65 8.82L12.24 9.48L11.68 10.04L11.02'
                     . ' 10.45L10.28 10.71L9.5 10.8L8.72 10.71L7.98 10.45L7.32 10.04L6.76 9.48L6.35 8.82L6.09 8.08L6'
                     . ' 7.3L6.09 6.52L6.35 5.78L6.76 5.12L7.32 4.56L7.98 4.15L8.72 3.89L9.5 3.8L10.28 3.89L11.02'
                     . ' 4.15L11.68 4.56L12.24 5.12L12.65 5.78L12.91 6.52Z M18.2 7.1L18.11 7.92L17.83 8.71L17.39'
                     . ' 9.41L16.81 9.99L16.11 10.43L15.32 10.71L14.5 10.8L13.68 10.71L12.89 10.43L12.19 9.99L11.61'
                     . ' 9.41L11.17 8.71L10.89 7.92L10.8 7.1L10.89 6.28L11.17 5.49L11.61 4.79L12.19 4.21L12.89'
                     . ' 3.77L13.68 3.49L14.5 3.4L15.32 3.49L16.11 3.77L16.81 4.21L17.39 4.79L17.83 5.49L18.11 6.28Z'
                     . ' M21 10.3L20.92 10.99L20.69 11.65L20.32 12.23L19.83 12.72L19.25 13.09L18.59 13.32L17.9'
                     . ' 13.4L17.21 13.32L16.55 13.09L15.97 12.72L15.48 12.23L15.11 11.65L14.88 10.99L14.8 10.3L14.88'
                     . ' 9.61L15.11 8.95L15.48 8.37L15.97 7.88L16.55 7.51L17.21 7.28L17.9 7.2L18.59 7.28L19.25'
                     . ' 7.51L19.83 7.88L20.32 8.37L20.69 8.95L20.92 9.61Z M15.9 10.4L15.8 11.27L15.51 12.09L15.05'
                     . ' 12.83L14.43 13.45L13.69 13.91L12.87 14.2L12 14.3L11.13 14.2L10.31 13.91L9.57 13.45L8.95'
                     . ' 12.83L8.49 12.09L8.2 11.27L8.1 10.4L8.2 9.53L8.49 8.71L8.95 7.97L9.57 7.35L10.31 6.89L11.13'
                     . ' 6.6L12 6.5L12.87 6.6L13.69 6.89L14.43 7.35L15.05 7.97L15.51 8.71L15.8 9.53Z M11.1 12.3L11.03'
                     . ' 12.88L10.84 13.43L10.53 13.92L10.12 14.33L9.63 14.64L9.08 14.83L8.5 14.9L7.92 14.83L7.37'
                     . ' 14.64L6.88 14.33L6.47 13.92L6.16 13.43L5.97 12.88L5.9 12.3L5.97 11.72L6.16 11.17L6.47'
                     . ' 10.68L6.88 10.27L7.37 9.96L7.92 9.77L8.5 9.7L9.08 9.77L9.63 9.96L10.12 10.27L10.53'
                     . ' 10.68L10.84 11.17L11.03 11.72Z M18.1 12.3L18.03 12.88L17.84 13.43L17.53 13.92L17.12'
                     . ' 14.33L16.63 14.64L16.08 14.83L15.5 14.9L14.92 14.83L14.37 14.64L13.88 14.33L13.47'
                     . ' 13.92L13.16 13.43L12.97 12.88L12.9 12.3L12.97 11.72L13.16 11.17L13.47 10.68L13.88'
                     . ' 10.27L14.37 9.96L14.92 9.77L15.5 9.7L16.08 9.77L16.63 9.96L17.12 10.27L17.53 10.68L17.84'
                     . ' 11.17L18.03 11.72Z"/>',

        /* A FLOWER AND TWO PLANTS OF THE WET GROUND (24.09.2026).
           Of six flowers — five round petals, a tulip, a daisy, a
           bellflower, three meadow flowers, a blossom seen from above — he
           took the TULIP as „Blütenpflanze": the cup with its three points
           between two long leaves is a silhouette no other shape has, while
           every round flower shrank to a dot on a stalk at 11 px. Of five
           grasses he took two, both as signs of a PLACE: the cattail marks
           the wet biotope, and the lone tuft became „Binsen" — the rushes
           drawn as rushes lost their side tufts at every size a list
           shows, and the tuft already „sieht fast wie eine Binse aus".

           A SINGLE TUFT IS A WETLAND. That was his reading of every
           one-plant grass: „wenn man das einzeln zeigt, sieht es immer
           nach Feuchtgebiet aus". A meadow needs several tufts of different
           height side by side — and few enough that 11 px does not turn
           them into a smear. Whoever draws grassland starts from there.

           THE CATTAIL WAS DRAWN TWICE. After the border had been thinned
           it still showed nothing on the map („unbrauchbar — entweder wir
           zeichnen ihn anders oder wir nehmen es ganz raus"): stalks and
           spikes one unit wide and a leaf running out thin left two small
           rods. Of four bold drafts he took two heads, 3.4 and 3 units
           thick, on stalks of 1.7 and 1.5, and one leaf 2.6 wide at its
           foot — nothing left for the map to lose. The leaf's foot dips
           below the stalks, and it has to: lifted onto their line it looked
           like a branch off the stalk, and he kept the draft — „das Blatt
           entspringt nicht am Stiel, sondern viel weiter unten".

           The tulip's cup and the cattail's heads are elements of their
           own, so on the map their white edge parts them from the stalk.
           All three stand on their base (anchors in map.js). */
        'flower'    => '<path d="M11.4 21.5L11.43 16L11.45 10.8L12.55 10.8L12.57 16L12.6 21.5Z M11.6 21.2L10.79'
                     . ' 20.82L10.08 20.3L9.46 19.67L8.93 18.93L8.5 18.11L8.16 17.23L7.93 16.3L7.79 15.36L7.76'
                     . ' 14.41L7.83 13.47L8.61 13.99L9.34 14.6L10 15.29L10.59 16.04L11.08 16.85L11.46'
                     . ' 17.7L11.71 18.57L11.83 19.45L11.8 20.33Z M12.4 21.2L12.23 20.43L12.21 19.66L12.32'
                     . ' 18.9L12.56 18.15L12.91 17.44L13.36 16.76L13.89 16.14L14.5 15.57L15.16 15.08L15.87'
                     . ' 14.67L15.93 15.49L15.89 16.31L15.76 17.13L15.54 17.92L15.23 18.67L14.84 19.36L14.35'
                     . ' 19.98L13.78 20.5L13.13 20.91Z"/>'
                     . '<path d="M12 11.4L11.03 11.27L10.22 10.97L9.57 10.51L9.05 9.91L8.66 9.19L8.4 8.35L8.25'
                     . ' 7.41L8.2 6.4L8.3 3.6L10.1 5.7L12 2.4L13.9 5.7L15.7 3.6L15.8 6.4L15.75 7.41L15.6'
                     . ' 8.35L15.34 9.19L14.95 9.91L14.43 10.51L13.78 10.97L12.97 11.27Z"/>',
        'rushes'    => '<path d="M11.39 21.71L11.06 20.66L10.71 19.64L10.32 18.66L9.9 17.71L9.44 16.79L8.95'
                     . ' 15.89L8.42 15.03L7.86 14.2L7.27 13.4L6.64 12.63L5.97 11.89L5.27 11.18L4.53 10.51L3.75'
                     . ' 9.86L3.85 9.74L4.68 10.33L5.48 10.95L6.25 11.61L6.99 12.31L7.7 13.04L8.37 13.81L9.02'
                     . ' 14.62L9.63 15.46L10.21 16.34L10.76 17.26L11.27 18.21L11.75 19.21L12.2 20.23L12.61'
                     . ' 21.29Z M11.32 21.65L11.13 20.57L10.92 19.49L10.69 18.4L10.44 17.3L10.16 16.19L9.87'
                     . ' 15.07L9.55 13.95L9.21 12.81L8.85 11.67L8.47 10.52L8.07 9.36L7.65 8.19L7.2 7.01L6.73'
                     . ' 5.83L6.87 5.77L7.42 6.92L7.95 8.07L8.46 9.21L8.95 10.34L9.41 11.47L9.86 12.59L10.29'
                     . ' 13.7L10.69 14.81L11.08 15.92L11.44 17.02L11.78 18.11L12.11 19.2L12.41 20.28L12.68'
                     . ' 21.35Z M11.25 21.56L11.21 20.47L11.16 19.34L11.1 18.18L11.04 16.98L10.96 15.75L10.88'
                     . ' 14.48L10.79 13.17L10.69 11.83L10.58 10.45L10.47 9.03L10.35 7.58L10.21 6.09L10.07'
                     . ' 4.57L9.93 3.01L10.07 2.99L10.32 4.54L10.55 6.05L10.78 7.53L11 8.97L11.21 10.37L11.42'
                     . ' 11.75L11.61 13.08L11.8 14.38L11.98 15.65L12.15 16.87L12.31 18.07L12.46 19.23L12.61'
                     . ' 20.35L12.75 21.44Z M11.25 21.45L11.37 20.36L11.5 19.22L11.63 18.04L11.77 16.82L11.92'
                     . ' 15.55L12.07 14.24L12.23 12.89L12.39 11.49L12.57 10.05L12.75 8.57L12.93 7.04L13.12'
                     . ' 5.47L13.32 3.85L13.53 2.19L13.67 2.21L13.57 3.88L13.46 5.5L13.37 7.08L13.28 8.62L13.2'
                     . ' 10.11L13.12 11.56L13.05 12.96L12.99 14.32L12.93 15.63L12.88 16.91L12.84 18.13L12.8'
                     . ' 19.32L12.77 20.45L12.75 21.55Z M11.31 21.37L11.56 20.29L11.83 19.2L12.12 18.09L12.43'
                     . ' 16.97L12.76 15.83L13.1 14.68L13.47 13.52L13.85 12.34L14.25 11.15L14.67 9.94L15.11'
                     . ' 8.72L15.56 7.49L16.04 6.24L16.53 4.98L16.67 5.02L16.26 6.32L15.87 7.59L15.5 8.85L15.15'
                     . ' 10.1L14.82 11.32L14.51 12.53L14.21 13.73L13.94 14.91L13.68 16.07L13.45 17.21L13.23'
                     . ' 18.34L13.03 19.45L12.85 20.55L12.69 21.63Z M11.38 21.3L11.78 20.24L12.2 19.2L12.66'
                     . ' 18.18L13.15 17.18L13.67 16.21L14.23 15.26L14.81 14.34L15.42 13.44L16.07 12.56L16.74'
                     . ' 11.71L17.45 10.89L18.19 10.08L18.95 9.3L19.75 8.55L19.85 8.65L19.12 9.46L18.42'
                     . ' 10.29L17.76 11.14L17.13 12L16.53 12.89L15.96 13.79L15.43 14.71L14.93 15.65L14.47'
                     . ' 16.61L14.03 17.59L13.63 18.59L13.26 19.6L12.93 20.64L12.62 21.7Z M11.4 21.51L11.43'
                     . ' 20.44L11.46 19.38L11.48 18.32L11.51 17.28L11.53 16.24L11.56 15.2L11.58 14.18L11.61'
                     . ' 13.16L11.63 12.15L11.65 11.15L11.67 10.15L11.69 9.16L11.71 8.18L11.73 7.2L11.87'
                     . ' 7.2L11.93 8.17L11.99 9.15L12.04 10.14L12.1 11.14L12.15 12.14L12.21 13.15L12.26'
                     . ' 14.17L12.31 15.2L12.36 16.23L12.41 17.27L12.46 18.31L12.51 19.37L12.55 20.43L12.6'
                     . ' 21.49Z"/>',
        'cattail'   => '<path d="M9.35 21.5L9.45 12.4L10.95 12.4L11.05 21.5Z M14.45 21.5L14.55 14.2L15.85'
                     . ' 14.2L15.95 21.5Z M9.65 5L9.85 1.6L10.55 1.6L10.75 5Z M10.55 1.6L10.48 1.81L10.31'
                     . ' 1.93L10.09 1.93L9.92 1.81L9.85 1.6L9.92 1.39L10.09 1.27L10.31 1.27L10.48 1.39Z M14.7'
                     . ' 8.4L14.88 5.4L15.52 5.4L15.7 8.4Z M15.52 5.4L15.46 5.59L15.3 5.71L15.1 5.71L14.94'
                     . ' 5.59L14.88 5.4L14.94 5.21L15.1 5.09L15.3 5.09L15.46 5.21Z M8.48 22.16L7.77 20.66L7.08'
                     . ' 19.09L6.43 17.48L5.82 15.81L5.25 14.09L4.71 12.32L4.2 10.5L3.73 8.62L3.87 8.58L4.63'
                     . ' 10.34L5.42 12.04L6.23 13.68L7.08 15.24L7.95 16.74L8.84 18.17L9.76 19.53L10.72'
                     . ' 20.84Z"/>'
                     . '<path d="M8.5 12.2L8.5 5.4L11.9 5.4L11.9 12.2Z M11.9 12.2L11.67 13.05L11.05 13.67L10.2'
                     . ' 13.9L9.35 13.67L8.73 13.05L8.5 12.2L8.73 11.35L9.35 10.73L10.2 10.5L11.05 10.73L11.67'
                     . ' 11.35Z M11.9 5.4L11.67 6.25L11.05 6.87L10.2 7.1L9.35 6.87L8.73 6.25L8.5 5.4L8.73'
                     . ' 4.55L9.35 3.93L10.2 3.7L11.05 3.93L11.67 4.55Z M13.7 14.2L13.7 8.6L16.7 8.6L16.7 14.2Z'
                     . ' M16.7 14.2L16.5 14.95L15.95 15.5L15.2 15.7L14.45 15.5L13.9 14.95L13.7 14.2L13.9'
                     . ' 13.45L14.45 12.9L15.2 12.7L15.95 12.9L16.5 13.45Z M16.7 8.6L16.5 9.35L15.95 9.9L15.2'
                     . ' 10.1L14.45 9.9L13.9 9.35L13.7 8.6L13.9 7.85L14.45 7.3L15.2 7.1L15.95 7.3L16.5 7.85Z"/>',

        /* TWO GRASSLANDS — a meadow is not a single tuft
           (24.09.2026: „eine Wiese ist ja viel dynamischer, mit mehreren
           größeren, kleineren Grasbüscheln nebeneinander … aber alles
           nebeneinander wird natürlich auch Chaos; man muss da einen
           Mittelweg finden"). The middle way is three tufts of different
           height, no more. Of three drafts each he took these two.

           MAGERRASEN: the three tufts on a line of ground and one small
           flower above them. The line makes the tufts ONE place instead of
           three plants; the flower stands for the herbs such grassland is
           rich in. KALKTROCKENRASEN: the sun over a block of limestone
           and two tufts beside it — dry and lime in one sign. The crack is
           what makes the block a stone; without it the first draft was a
           mound. The stone without the sun went live first, but the sun was
           what he had meant, and under the old 1 px border it would have
           drowned like every other detail (map.js, symbolFor).

           A blade is drawn with six segments here, not the fourteen of the
           drafts: the same outline at every size a marker has, and half
           the path data in every page's shape stock. The flower is its own
           element, so its white edge parts it from the tall tuft. Both
           stand on their ground (anchors in map.js). */
        'poor_grassland' => '<path d="M3 20.45L21 20.45L21 21.55L3 21.55Z M3.55 21L3.48 21.27L3.27 21.48L3'
                     . ' 21.55L2.73 21.48L2.52 21.27L2.45 21L2.52 20.73L2.72 20.52L3 20.45L3.27 20.52L3.48'
                     . ' 20.73Z M21.55 21L21.48 21.27L21.27 21.48L21 21.55L20.73 21.48L20.52 21.27L20.45'
                     . ' 21L20.52 20.73L20.73 20.52L21 20.45L21.27 20.52L21.48 20.73Z M5.39 20.94L5.34'
                     . ' 20.28L5.23 19.71L5.05 19.18L4.8 18.69L4.46 18.24L4.03 17.83L4.12 17.71L4.67 17.98L5.18'
                     . ' 18.35L5.64 18.8L6.03 19.35L6.36 19.98L6.61 20.66Z M5.38 20.85L5.4 19.98L5.4 19.17L5.36'
                     . ' 18.41L5.3 17.69L5.2 17.02L5.07 16.39L5.21 16.34L5.52 16.92L5.8 17.57L6.05 18.28L6.27'
                     . ' 19.05L6.46 19.88L6.62 20.75Z M5.38 20.75L5.54 19.88L5.73 19.05L5.95 18.28L6.2'
                     . ' 17.57L6.48 16.92L6.79 16.34L6.93 16.39L6.8 17.02L6.7 17.69L6.64 18.41L6.6 19.17L6.6'
                     . ' 19.98L6.62 20.85Z M5.39 20.66L5.64 19.98L5.97 19.35L6.36 18.8L6.82 18.35L7.33'
                     . ' 17.98L7.88 17.71L7.97 17.83L7.54 18.24L7.2 18.69L6.95 19.18L6.77 19.71L6.66 20.28L6.61'
                     . ' 20.94Z M10.38 20.89L10.26 19.49L10.04 18.2L9.73 17L9.32 15.89L8.8 14.87L8.18'
                     . ' 13.94L8.29 13.85L9.08 14.68L9.78 15.65L10.38 16.74L10.89 17.95L11.31 19.28L11.62'
                     . ' 20.71Z M10.38 20.85L10.34 19.12L10.24 17.51L10.08 15.99L9.85 14.58L9.57 13.26L9.22'
                     . ' 12.04L9.37 11.99L9.89 13.16L10.35 14.45L10.76 15.86L11.11 17.38L11.4 19.02L11.62'
                     . ' 20.75Z M10.38 20.8L10.47 18.76L10.56 16.83L10.65 15.02L10.74 13.33L10.83 11.76L10.93'
                     . ' 10.3L11.07 10.3L11.17 11.76L11.26 13.33L11.35 15.02L11.44 16.83L11.53 18.76L11.62'
                     . ' 20.8Z M10.38 20.75L10.6 19.02L10.89 17.38L11.24 15.86L11.65 14.45L12.11 13.16L12.63'
                     . ' 11.99L12.78 12.04L12.43 13.26L12.15 14.58L11.92 15.99L11.76 17.51L11.66 19.12L11.62'
                     . ' 20.85Z M10.38 20.71L10.69 19.28L11.11 17.95L11.62 16.74L12.22 15.65L12.92 14.68L13.71'
                     . ' 13.85L13.82 13.94L13.2 14.87L12.68 15.89L12.27 17L11.96 18.2L11.74 19.49L11.62 20.89Z'
                     . ' M16.59 20.92L16.5 19.99L16.33 19.16L16.09 18.39L15.75 17.69L15.32 17.04L14.79'
                     . ' 16.45L14.89 16.34L15.56 16.81L16.17 17.38L16.7 18.06L17.16 18.85L17.53 19.73L17.81'
                     . ' 20.68Z M16.58 20.84L16.59 19.62L16.56 18.48L16.5 17.41L16.4 16.41L16.26 15.47L16.08'
                     . ' 14.59L16.23 14.55L16.58 15.38L16.9 16.3L17.19 17.3L17.44 18.37L17.65 19.53L17.82'
                     . ' 20.76Z M16.58 20.76L16.75 19.53L16.96 18.37L17.21 17.3L17.5 16.3L17.82 15.38L18.17'
                     . ' 14.55L18.32 14.59L18.14 15.47L18 16.41L17.9 17.41L17.84 18.48L17.81 19.62L17.82 20.84Z'
                     . ' M16.59 20.68L16.87 19.73L17.24 18.85L17.7 18.06L18.23 17.38L18.84 16.81L19.51'
                     . ' 16.34L19.61 16.45L19.08 17.04L18.65 17.69L18.31 18.39L18.07 19.16L17.9 19.99L17.81'
                     . ' 20.92Z M13.65 20.78L13.98 13.98L14.3 8.98L14.9 9.02L14.62 14.02L14.35 20.82Z"/>'
                     . '<path d="M15.7 6.33L15.55 6.88L15.15 7.29L14.6 7.44L14.05 7.29L13.65 6.88L13.5'
                     . ' 6.33L13.65 5.79L14.05 5.38L14.6 5.23L15.15 5.38L15.55 5.78Z M16.9 7.21L16.76'
                     . ' 7.76L16.35 8.16L15.8 8.31L15.25 8.16L14.85 7.76L14.7 7.21L14.85 6.66L15.25 6.26L15.8'
                     . ' 6.11L16.35 6.26L16.76 6.66Z M16.44 8.62L16.3 9.17L15.89 9.58L15.34 9.72L14.79'
                     . ' 9.58L14.39 9.17L14.24 8.62L14.39 8.07L14.79 7.67L15.34 7.52L15.89 7.67L16.3 8.07Z'
                     . ' M14.96 8.62L14.81 9.17L14.41 9.58L13.86 9.72L13.31 9.58L12.9 9.17L12.76 8.62L12.9'
                     . ' 8.07L13.31 7.67L13.86 7.52L14.41 7.67L14.81 8.07Z M14.5 7.21L14.35 7.76L13.95'
                     . ' 8.16L13.4 8.31L12.85 8.16L12.44 7.76L12.3 7.21L12.44 6.66L12.85 6.26L13.4 6.11L13.95'
                     . ' 6.26L14.35 6.66Z"/>',
        'calcareous_grassland' => '<path d="M3 21.3L3.6 17.8L6.2 15.8L9.4 16.4L11 18.8L11.4 21.3Z M6.36 16.34L5.98'
                     . ' 18.02L6.72 19.42L6.36 20.76L6.64 20.84L7.08 19.38L6.42 17.98L6.84 16.46Z"/>'
                     . '<path d="M14.58 21.3L14.49 20.18L14.31 19.16L14.05 18.22L13.71 17.34L13.26 16.54L12.72'
                     . ' 15.8L12.83 15.7L13.53 16.33L14.15 17.08L14.69 17.93L15.16 18.89L15.53 19.96L15.82'
                     . ' 21.1Z M14.58 21.25L14.55 19.87L14.48 18.59L14.35 17.38L14.16 16.25L13.93 15.2L13.63'
                     . ' 14.22L13.77 14.16L14.24 15.09L14.66 16.11L15.03 17.23L15.35 18.45L15.61 19.76L15.82'
                     . ' 21.15Z M14.57 21.2L14.67 19.57L14.76 18.03L14.85 16.58L14.94 15.23L15.03 13.97L15.12'
                     . ' 12.8L15.27 12.8L15.37 13.97L15.46 15.23L15.55 16.58L15.64 18.03L15.73 19.57L15.82'
                     . ' 21.2Z M14.58 21.15L14.79 19.76L15.05 18.45L15.37 17.23L15.74 16.11L16.16 15.09L16.63'
                     . ' 14.16L16.77 14.22L16.47 15.2L16.24 16.25L16.05 17.38L15.92 18.59L15.85 19.87L15.82'
                     . ' 21.25Z M14.58 21.1L14.87 19.96L15.24 18.89L15.71 17.93L16.25 17.08L16.87 16.33L17.57'
                     . ' 15.7L17.68 15.8L17.14 16.54L16.69 17.34L16.35 18.22L16.09 19.16L15.91 20.18L15.82'
                     . ' 21.3Z M19.29 21.31L19.25 20.76L19.16 20.27L19.02 19.82L18.83 19.4L18.57 19.02L18.24'
                     . ' 18.66L18.33 18.55L18.77 18.8L19.18 19.12L19.54 19.52L19.86 19.98L20.12 20.52L20.31'
                     . ' 21.09Z M19.28 21.2L19.35 20.38L19.43 19.61L19.5 18.89L19.58 18.21L19.65 17.58L19.73'
                     . ' 17L19.88 17L19.95 17.58L20.03 18.21L20.1 18.89L20.18 19.61L20.25 20.38L20.32 21.2Z'
                     . ' M19.29 21.09L19.48 20.52L19.74 19.98L20.06 19.52L20.42 19.12L20.83 18.8L21.27'
                     . ' 18.55L21.36 18.66L21.03 19.02L20.77 19.4L20.58 19.82L20.44 20.27L20.35 20.76L20.31'
                     . ' 21.31Z"/>'
                     . '<path d="M10.5 6.6L10.4 7.25L10.1 7.83L9.63 8.3L9.05 8.6L8.4 8.7L7.75 8.6L7.17 8.3L6.7'
                     . ' 7.83L6.4 7.25L6.3 6.6L6.4 5.95L6.7 5.37L7.17 4.9L7.75 4.6L8.4 4.5L9.05 4.6L9.63'
                     . ' 4.9L10.1 5.37L10.4 5.95Z M7.95 3.7L7.95 2.5L8.85 2.5L8.85 3.7Z M10.13 4.23L10.98'
                     . ' 3.38L11.62 4.02L10.77 4.87Z M11.3 6.15L12.5 6.15L12.5 7.05L11.3 7.05Z M10.77'
                     . ' 8.33L11.62 9.18L10.98 9.82L10.13 8.97Z M8.85 9.5L8.85 10.7L7.95 10.7L7.95 9.5Z M6.67'
                     . ' 8.97L5.82 9.82L5.18 9.18L6.03 8.33Z M5.5 7.05L4.3 7.05L4.3 6.15L5.5 6.15Z M6.03'
                     . ' 4.87L5.18 4.02L5.82 3.38L6.67 4.23Z"/>',

        /* SPHAGNUMSUMPF — PEAT MOSS STANDING IN WATER
           (24.09.2026). The first drafts drew the bog's companions and a
           hummock: he asked whether the cotton grass's round heads were
           flowering sphagnum, and the star heads on bare stalks were, in
           his words, a liverwort's — Marchantia's umbrellas. What makes a
           Sphagnum plant is its build: a dense, rounded head (the
           capitulum) and, below it at every node, branches that stand out
           and hang down. Three plants of different height, as a lawn
           grows, over a line of water: the water says bog, the hanging
           branches say peat moss. A spore capsule was tried above the
           heads and read as a pin stuck in them.

           Heads and water are elements of their own, so on the map their
           white edges part them from the stems. Fewer points than the
           draft where nobody sees them — the bumps of a head lie mostly
           inside the union — 5.7 instead of 8.4 KB, measured the same. It
           stands on its water (anchor in map.js). */
        'sphagnum_bog' => '<path d="M6 19.4L6.08 9.2L6.72 9.2L6.8 19.4Z M6.38 11.95L5.78 11.83L5.33 12.06L4.9'
                     . ' 12.75L4.57 13.92L4.43 13.88L4.63 12.66L5 11.79L5.64 11.28L6.42 11.25Z M6.38 11.25L7.16'
                     . ' 11.28L7.8 11.79L8.17 12.66L8.37 13.88L8.23 13.92L7.9 12.75L7.47 12.06L7.02 11.83L6.42'
                     . ' 11.95Z M6.38 14.55L5.78 14.43L5.33 14.66L4.9 15.35L4.57 16.52L4.43 16.48L4.63 15.26L5'
                     . ' 14.39L5.64 13.88L6.42 13.85Z M6.38 13.85L7.16 13.88L7.8 14.39L8.17 15.26L8.37'
                     . ' 16.48L8.23 16.52L7.9 15.35L7.47 14.66L7.02 14.43L6.42 14.55Z M6.38 17.15L5.78'
                     . ' 17.03L5.33 17.26L4.9 17.95L4.57 19.12L4.43 19.08L4.63 17.86L5 16.99L5.64 16.48L6.42'
                     . ' 16.45Z M6.38 16.45L7.16 16.48L7.8 16.99L8.17 17.86L8.37 19.08L8.23 19.12L7.9'
                     . ' 17.95L7.47 17.26L7.02 17.03L6.42 17.15Z M11.6 19.4L11.68 4.4L12.32 4.4L12.4 19.4Z'
                     . ' M11.98 7.35L11.38 7.23L10.93 7.46L10.5 8.15L10.17 9.32L10.03 9.28L10.23 8.06L10.6'
                     . ' 7.19L11.24 6.68L12.02 6.65Z M11.98 6.65L12.76 6.68L13.4 7.19L13.77 8.06L13.97'
                     . ' 9.28L13.83 9.32L13.5 8.15L13.07 7.46L12.62 7.23L12.02 7.35Z M11.98 9.95L11.38'
                     . ' 9.83L10.93 10.06L10.5 10.75L10.17 11.92L10.03 11.88L10.23 10.66L10.6 9.79L11.24'
                     . ' 9.28L12.02 9.25Z M11.98 9.25L12.76 9.28L13.4 9.79L13.77 10.66L13.97 11.88L13.83'
                     . ' 11.92L13.5 10.75L13.07 10.06L12.62 9.83L12.02 9.95Z M11.98 12.55L11.38 12.43L10.93'
                     . ' 12.66L10.5 13.35L10.17 14.52L10.03 14.48L10.23 13.26L10.6 12.39L11.24 11.88L12.02'
                     . ' 11.85Z M11.98 11.85L12.76 11.88L13.4 12.39L13.77 13.26L13.97 14.48L13.83 14.52L13.5'
                     . ' 13.35L13.07 12.66L12.62 12.43L12.02 12.55Z M11.98 15.15L11.38 15.03L10.93 15.26L10.5'
                     . ' 15.95L10.17 17.12L10.03 17.08L10.23 15.86L10.6 14.99L11.24 14.48L12.02 14.45Z M11.98'
                     . ' 14.45L12.76 14.48L13.4 14.99L13.77 15.86L13.97 17.08L13.83 17.12L13.5 15.95L13.07'
                     . ' 15.26L12.62 15.03L12.02 15.15Z M17.2 19.4L17.28 7.4L17.92 7.4L18 19.4Z M17.58'
                     . ' 10.15L16.98 10.03L16.53 10.26L16.1 10.95L15.77 12.12L15.63 12.08L15.83 10.86L16.2'
                     . ' 9.99L16.84 9.48L17.62 9.45Z M17.58 9.45L18.36 9.48L19 9.99L19.37 10.86L19.57'
                     . ' 12.08L19.43 12.12L19.1 10.95L18.67 10.26L18.22 10.03L17.62 10.15Z M17.58 12.75L16.98'
                     . ' 12.63L16.53 12.86L16.1 13.55L15.77 14.72L15.63 14.68L15.83 13.46L16.2 12.59L16.84'
                     . ' 12.08L17.62 12.05Z M17.58 12.05L18.36 12.08L19 12.59L19.37 13.46L19.57 14.68L19.43'
                     . ' 14.72L19.1 13.55L18.67 12.86L18.22 12.63L17.62 12.75Z M17.58 15.35L16.98 15.23L16.53'
                     . ' 15.46L16.1 16.15L15.77 17.32L15.63 17.28L15.83 16.06L16.2 15.19L16.84 14.68L17.62'
                     . ' 14.65Z M17.58 14.65L18.36 14.68L19 15.19L19.37 16.06L19.57 17.28L19.43 17.32L19.1'
                     . ' 16.15L18.67 15.46L18.22 15.23L17.62 15.35Z"/>'
                     . '<path d="M7.85 9.2L7.71 9.83L7.3 10.33L6.72 10.61L6.08 10.61L5.5 10.33L5.09 9.83L4.95'
                     . ' 9.2L5.09 8.57L5.5 8.07L6.08 7.79L6.72 7.79L7.3 8.07L7.71 8.57Z M5.93 9.67L5.69'
                     . ' 10.25L5.11 10.49L4.53 10.25L4.29 9.67L4.53 9.09L5.11 8.85L5.69 9.09Z M5.87 8.93L5.63'
                     . ' 9.51L5.05 9.75L4.47 9.51L4.23 8.93L4.47 8.35L5.05 8.11L5.63 8.35Z M6.21 8.26L5.97'
                     . ' 8.84L5.39 9.08L4.81 8.84L4.57 8.26L4.81 7.68L5.39 7.44L5.97 7.68Z M6.85 7.87L6.61'
                     . ' 8.45L6.03 8.69L5.45 8.45L5.21 7.87L5.45 7.29L6.03 7.05L6.61 7.29Z M7.59 7.87L7.35'
                     . ' 8.45L6.77 8.69L6.19 8.45L5.95 7.87L6.19 7.29L6.77 7.05L7.35 7.29Z M8.23 8.26L7.99'
                     . ' 8.84L7.41 9.08L6.83 8.84L6.59 8.26L6.83 7.68L7.41 7.44L7.99 7.68Z M8.57 8.93L8.33'
                     . ' 9.51L7.75 9.75L7.17 9.51L6.93 8.93L7.17 8.35L7.75 8.11L8.33 8.35Z M8.51 9.67L8.27'
                     . ' 10.25L7.69 10.49L7.11 10.25L6.87 9.67L7.11 9.09L7.69 8.85L8.27 9.09Z M13.45 4.4L13.31'
                     . ' 5.03L12.9 5.53L12.32 5.81L11.68 5.81L11.1 5.53L10.69 5.03L10.55 4.4L10.69 3.77L11.1'
                     . ' 3.27L11.68 2.99L12.32 2.99L12.9 3.27L13.31 3.77Z M11.53 4.87L11.29 5.45L10.71'
                     . ' 5.69L10.13 5.45L9.89 4.87L10.13 4.29L10.71 4.05L11.29 4.29Z M11.47 4.13L11.23'
                     . ' 4.71L10.65 4.95L10.07 4.71L9.83 4.13L10.07 3.55L10.65 3.31L11.23 3.55Z M11.81'
                     . ' 3.46L11.57 4.04L10.99 4.28L10.41 4.04L10.17 3.46L10.41 2.88L10.99 2.64L11.57 2.88Z'
                     . ' M12.45 3.07L12.21 3.65L11.63 3.89L11.05 3.65L10.81 3.07L11.05 2.49L11.63 2.25L12.21'
                     . ' 2.49Z M13.19 3.07L12.95 3.65L12.37 3.89L11.79 3.65L11.55 3.07L11.79 2.49L12.37'
                     . ' 2.25L12.95 2.49Z M13.83 3.46L13.59 4.04L13.01 4.28L12.43 4.04L12.19 3.46L12.43'
                     . ' 2.88L13.01 2.64L13.59 2.88Z M14.17 4.13L13.93 4.71L13.35 4.95L12.77 4.71L12.53'
                     . ' 4.13L12.77 3.55L13.35 3.31L13.93 3.55Z M14.11 4.87L13.87 5.45L13.29 5.69L12.71'
                     . ' 5.45L12.47 4.87L12.71 4.29L13.29 4.05L13.87 4.29Z M19.05 7.4L18.91 8.03L18.5'
                     . ' 8.53L17.92 8.81L17.28 8.81L16.7 8.53L16.29 8.03L16.15 7.4L16.29 6.77L16.7 6.27L17.28'
                     . ' 5.99L17.92 5.99L18.5 6.27L18.91 6.77Z M17.13 7.87L16.89 8.45L16.31 8.69L15.73'
                     . ' 8.45L15.49 7.87L15.73 7.29L16.31 7.05L16.89 7.29Z M17.07 7.13L16.83 7.71L16.25'
                     . ' 7.95L15.67 7.71L15.43 7.13L15.67 6.55L16.25 6.31L16.83 6.55Z M17.41 6.46L17.17'
                     . ' 7.04L16.59 7.28L16.01 7.04L15.77 6.46L16.01 5.88L16.59 5.64L17.17 5.88Z M18.05'
                     . ' 6.07L17.81 6.65L17.23 6.89L16.65 6.65L16.41 6.07L16.65 5.49L17.23 5.25L17.81 5.49Z'
                     . ' M18.79 6.07L18.55 6.65L17.97 6.89L17.39 6.65L17.15 6.07L17.39 5.49L17.97 5.25L18.55'
                     . ' 5.49Z M19.43 6.46L19.19 7.04L18.61 7.28L18.03 7.04L17.79 6.46L18.03 5.88L18.61'
                     . ' 5.64L19.19 5.88Z M19.77 7.13L19.53 7.71L18.95 7.95L18.37 7.71L18.13 7.13L18.37'
                     . ' 6.55L18.95 6.31L19.53 6.55Z M19.71 7.87L19.47 8.45L18.89 8.69L18.31 8.45L18.07'
                     . ' 7.87L18.31 7.29L18.89 7.05L19.47 7.29Z"/>'
                     . '<path d="M3.27 20.22L4 20.68L4.58 20.54L5.46 19.92L6.66 19.62L7.77 20.15L8.54 20.66L9.1'
                     . ' 20.59L9.94 19.99L11.12 19.6L12.27 20.07L13.07 20.63L13.63 20.63L14.43 20.07L15.58'
                     . ' 19.6L16.76 19.99L17.6 20.59L18.16 20.66L18.93 20.15L20.04 19.62L21.15 19.88L20.85'
                     . ' 20.94L20.16 20.71L19.47 21.1L18.44 21.72L17.2 21.61L16.24 20.97L15.62 20.7L14.97'
                     . ' 21.03L13.97 21.67L12.73 21.67L11.73 21.03L11.08 20.7L10.46 20.97L9.5 21.61L8.26'
                     . ' 21.72L7.23 21.1L6.54 20.71L5.94 20.91L5.02 21.55L3.8 21.76L2.73 21.18Z M3.55 20.7L3.44'
                     . ' 21.02L3.17 21.22L2.83 21.22L2.56 21.02L2.45 20.7L2.56 20.38L2.83 20.18L3.17 20.18L3.44'
                     . ' 20.38Z M21.55 20.41L21.44 20.74L21.17 20.94L20.83 20.94L20.56 20.74L20.45 20.41L20.56'
                     . ' 20.09L20.83 19.89L21.17 19.89L21.44 20.09Z"/>',

        /* INSEKT — A BUTTERFLY SINCE 24.09.2026 („Schmetterling
           gefällt mir, weil das ist auch etwas, was mich interessiert").
           The beetles drawn beside it could be taken for the spider; a
           butterfly is an insect beyond doubt. Drawn as fine as the stag
           allows — clubbed antennae, an eyespot cut out of every wing —
           because the white border lies outside only and eats nothing.

           The hindwings carry swallowtail tails, pointing slightly
           OUTWARD as they do in life: turned inward they read as hooks
           (his first objection), straight down as drips. Wings first,
           body second: the body's white edge draws the seam between them.
           The key stays 'insect' — every list that wore the old insect
           wears the butterfly. It flies, so it stays centred. */
        'insect'    => '<path fill-rule="evenodd" d="'
                     . 'M10.88 8.26C9.26 5.23 6.23 2.86 3.64 2.64C2.13 2.53 1.37 3.5 1.59 5.02C1.8 6.74 2.45'
                     . ' 8.47 3.64 9.77C5.37 11.5 8.18 12.14 10.88 11.71ZM3.8 5.99A1.13 1.13 0 1 0 6.07 5.99A1.13'
                     . ' 1.13 0 1 0 3.8 5.99ZM13.12 8.26 C14.74 5.23 17.77 2.86 20.36 2.64 C21.87 2.53 22.63 3.5'
                     . ' 22.41 5.02 C22.2 6.74 21.55 8.47 20.36 9.77 C18.63 11.5 15.82 12.14 13.12 11.71 ZM20.2'
                     . ' 5.99 A1.13 1.13 0 1 1 17.93 5.99 A1.13 1.13 0 1 1 20.2 5.99 ZM10.88 12.66C8.05 12.45'
                     . ' 5.32 13.39 4.58 15.6C4.06 17.18 4.58 18.86 5.84 19.48L5.11 21.9C4.95 22.43 5.69 22.74'
                     . ' 5.95 22.22L7.1 19.8C8.89 19.38 10.46 17.38 10.88 14.65ZM6.21 16.54A0.79 0.79 0 1 0 7.79'
                     . ' 16.54A0.79 0.79 0 1 0 6.21 16.54ZM13.12 12.66 C15.95 12.45 18.68 13.39 19.42 15.6 C19.94'
                     . ' 17.18 19.42 18.86 18.16 19.48 L18.89 21.9 C19.05 22.43 18.31 22.74 18.05 22.22 L16.9'
                     . ' 19.8 C15.11 19.38 13.54 17.38 13.12 14.65 ZM17.79 16.54 A0.79 0.79 0 1 1 16.21 16.54'
                     . ' A0.79 0.79 0 1 1 17.79 16.54 Z'
                     . '"/>'
                     . '<path d="' . 'M12 6.4C12.6 6.4 12.9 6.9 12.9 7.5L12.9 18.3C12.9 19.1 12.5 19.7 12 19.7C11.5 19.7 11.1'
                     . ' 19.1 11.1 18.3L11.1 7.5C11.1 6.9 11.4 6.4 12 6.4ZM10.75 5.3A1.25 1.25 0 1 0 13.25'
                     . ' 5.3A1.25 1.25 0 1 0 10.75 5.3ZM11.74 4.22L10.4 2.41L9.26 1.51L8.94 1.89L10 2.79L11.26'
                     . ' 4.58ZM12.26 4.22 L13.6 2.41 L14.74 1.51 L15.06 1.89 L14 2.79 L12.74 4.58 ZM8.25 1.7A0.75'
                     . ' 0.75 0 1 0 9.75 1.7A0.75 0.75 0 1 0 8.25 1.7ZM15.75 1.7 A0.75 0.75 0 1 1 14.25 1.7 A0.75'
                     . ' 0.75 0 1 1 15.75 1.7 Z' . '"/>',

        /* THREE MORE INSECTS, from the same six drafts
           (24.09.2026: „Insekt A, den Käfer, E, den Marienkäfer, und F, die
           Libelle"). The butterfly stays THE insect, the sign an insect
           list gets unasked; these are choices of one's own. All three
           seen from above and centred — they walk and fly, none stands on
           a foot. Drawn in several elements ON PURPOSE, not one path: legs
           and body, wings and abdomen, the two eyes overlap, and within
           ONE path two overlapping parts of opposite winding cancel into
           a hole; across elements each part fills alone, and the later
           one's white edge draws the seam between them — the beetle's
           neck, the dragonfly's segments. The ladybird's spots and eyes
           are holes (evenodd): on the map the white edge fills them. */
        'beetle'    => '<path d="M10.03 7.66L7.81 6.61L6.68 4.18L6.12 4.42L7.19 7.19L9.57 8.54Z M13.97 7.66L16.19'
                     . ' 6.61L17.32 4.18L17.88 4.42L16.81 7.19L14.43 8.54Z M9.03 12.12L5.77 11.35L3.88 13.49L4.32'
                     . ' 13.91L6.03 12.25L8.77 13.08Z M14.97 12.12L18.23 11.35L20.12 13.49L19.68 13.91L17.97'
                     . ' 12.25L15.23 13.08Z M8.65 15.77L5.86 17.53L4.91 20.91L5.49 21.09L6.54 18.07L9.15 16.63Z'
                     . ' M15.35 15.77L18.14 17.53L19.09 20.91L18.51 21.09L17.46 18.07L14.85 16.63Z M11.49 3.93L10.09'
                     . ' 2.53L8.4 1.87L8.2 2.33L9.71 3.07L10.91 4.47Z M12.51 3.93L13.91 2.53L15.6 1.87L15.8'
                     . ' 2.33L14.29 3.07L13.09 4.47Z"/>'
                     . '<path d="M10.2 5.3A1.8 1.6 0 1 0 13.8 5.3A1.8 1.6 0 1 0 10.2 5.3Z M9 8.4A3 1.9 0 1 0 15'
                     . ' 8.4A3 1.9 0 1 0 9 8.4Z"/>'
                     . '<path d="M11.7 10.8L11.7 21.5C10 21.3 8.6 20.1 8.3 17.7C8 15.5 8.1 12.6 8.8 11.6C9.3 11 10.3'
                     . ' 10.8 11.7 10.8Z M12.3 10.8L12.3 21.5C14 21.3 15.4 20.1 15.7 17.7C16 15.5 15.9 12.6 15.2'
                     . ' 11.6C14.7 11 13.7 10.8 12.3 10.8Z"/>',
        'ladybird'  => '<path d="M7.57 9.78L5.25 8.91L4.13 7.26L3.67 7.54L4.75 9.49L7.23 10.62Z M16.43 9.78L18.75'
                     . ' 8.91L19.87 7.26L20.33 7.54L19.25 9.49L16.77 10.62Z M5.65 13.95L2.82 13.71L1.67 15.65L2.13'
                     . ' 15.95L3.18 14.49L5.55 14.85Z M18.35 13.95L21.18 13.71L22.33 15.65L21.87 15.95L20.82'
                     . ' 14.49L18.45 14.85Z M6.52 18.05L4.47 19.8L4.13 22.15L4.67 22.25L5.13 20.2L7.08 18.75Z M17.48'
                     . ' 18.05L19.53 19.8L19.87 22.15L19.33 22.25L18.87 20.2L16.92 18.75Z M10.88 4.39L9.59 2.76L8.1'
                     . ' 2.17L7.9 2.63L9.21 3.24L10.32 4.81Z M13.12 4.39L14.41 2.76L15.9 2.17L16.1 2.63L14.79'
                     . ' 3.24L13.68 4.81Z"/>'
                     . '<path fill-rule="evenodd" d="M9.5 6A2.5 2.5 0 1 0 14.5 6A2.5 2.5 0 1 0 9.5 6Z M10.35'
                     . ' 5.3A0.55 0.55 0 1 0 11.45 5.3A0.55 0.55 0 1 0 10.35 5.3Z M12.55 5.3A0.55 0.55 0 1 0 13.65'
                     . ' 5.3A0.55 0.55 0 1 0 12.55 5.3Z"/>'
                     . '<path fill-rule="evenodd" d="M11.65 6.9A7 7 0 0 0 11.65 20.9Z M7.8 11.3A1.2 1.2 0 1 0 10.2'
                     . ' 11.3A1.2 1.2 0 1 0 7.8 11.3Z M7 15.6A1.3 1.3 0 1 0 9.6 15.6A1.3 1.3 0 1 0 7 15.6Z M9.3'
                     . ' 18.8A1 1 0 1 0 11.3 18.8A1 1 0 1 0 9.3 18.8Z M12.35 6.9A7 7 0 0 1 12.35 20.9Z M13.8 11.3A1.2'
                     . ' 1.2 0 1 0 16.2 11.3A1.2 1.2 0 1 0 13.8 11.3Z M14.4 15.6A1.3 1.3 0 1 0 17 15.6A1.3 1.3 0 1 0'
                     . ' 14.4 15.6Z M12.7 18.8A1 1 0 1 0 14.7 18.8A1 1 0 1 0 12.7 18.8Z"/>',
        'dragonfly' => '<path d="M10.9 7.1C7.6 5.6 4 5 2.2 5.6C1.4 5.9 1.5 6.9 2.4 7.3C4.4 8.2 7.8 8.4 10.9 8.3Z'
                     . ' M13.1 7.1C16.4 5.6 20 5 21.8 5.6C22.6 5.9 22.5 6.9 21.6 7.3C19.6 8.2 16.2 8.4 13.1 8.3Z'
                     . ' M10.9 9.3C7.6 9.3 4.2 9.9 2.5 11C1.8 11.5 2 12.4 2.9 12.5C5.2 12.7 8.4 11.5 10.9 10.4Z M13.1'
                     . ' 9.3C16.4 9.3 19.8 9.9 21.5 11C22.2 11.5 22 12.4 21.1 12.5C18.8 12.7 15.6 11.5 13.1 10.4Z"/>'
                     . '<path d="M11.25 10.6L11.4 16L11.55 22.3L12.45 22.3L12.6 16L12.75 10.6Z"/>'
                     . '<path d="M10.5 8.7A1.5 2.2 0 1 0 13.5 8.7A1.5 2.2 0 1 0 10.5 8.7Z"/>'
                     . '<path d="M9.65 4.9A1.35 1.35 0 1 0 12.35 4.9A1.35 1.35 0 1 0 9.65 4.9Z M11.65 4.9A1.35 1.35'
                     . ' 0 1 0 14.35 4.9A1.35 1.35 0 1 0 11.65 4.9Z"/>',

        /* THE MAMMALS, DRAWN BY US (24.09.2026: „mich stört ein
           bisschen, dass ich für das Hirschsymbol ein Copyright angeben
           muss"). The stag used to be Caro Asercion's (game-icons.net,
           CC BY 3.0) and needed its credit in the map's attribution line;
           this one is our own, and the credit went with it. Four more stand
           beside it — roe deer, wild boar, cow, sheep — all in profile, all
           looking right like the old stag and the fish, all on their hooves
           at the bottom edge (anchors in map.js, measured: the feet lie
           left of centre, under the body, not under the head).

           DRAWN FOR THE THIN BORDER (map.js, symbolFor): legs at least 1.1
           units wide and a unit apart, antler tines a unit apart — anything
           closer, the white edge fills. The holes are the cow's spots and
           the boar's eye; the ground shows through them.

           HIRSCH: a heavy body with a mane, a lower head, and antlers of two
           beams spreading like a lyre — the near one back, the far one up
           and forward, each with its tines and a crown. Two parallel beams,
           the first try, touched on the map. */
        'deer'      => '<path d="M15.4 12.4L15.66 12.77L15.85 13.18L15.95 13.63L15.97 14.1L15.91 14.57L15.77'
                     . ' 15.02L15.53 15.43L15.2 15.8L14.7 16.04L14.09 16.17L13.41 16.23L12.65 16.23L11.84'
                     . ' 16.2L10.98 16.17L10.1 16.16L9.2 16.2L8.5 16.26L7.77 16.29L7.04 16.28L6.32 16.2L5.66'
                     . ' 16.05L5.07 15.81L4.57 15.46L4.2 15L4.04 14.39L4.01 13.79L4.09 13.22L4.3 12.7L4.62'
                     . ' 12.25L5.04 11.91L5.57 11.68L6.2 11.6L7.12 11.59L8.2 11.58L9.39 11.59L10.65 11.62L11.93'
                     . ' 11.71L13.18 11.85L14.35 12.08L15.4 12.4Z M12.6 12.4L12.86 12.21L13.16 12.01L13.51'
                     . ' 11.8L13.87 11.56L14.25 11.29L14.62 10.98L14.98 10.62L15.3 10.2L15.55 10L15.83'
                     . ' 9.87L16.14 9.79L16.47 9.78L16.82 9.83L17.16 9.95L17.49 10.14L17.8 10.4L17.58'
                     . ' 10.79L17.35 11.19L17.12 11.63L16.9 12.1L16.67 12.61L16.45 13.16L16.22 13.75L16 14.4Z'
                     . ' M16.5 11.2L17.5 10.9L16.9 11.9L17.5 12L16.8 13L17.4 13.2L16.3 14.2Z M15.35 15.75L15.45'
                     . ' 18.55L15.55 21.46L14.45 21.54L14.15 18.65L13.85 15.85Z M13.3 16.08L12.91 18.66L12.62'
                     . ' 21.54L11.58 21.46L11.69 18.54L11.9 15.92Z M8.53 16.54L7.27 18.88L7.74 21.38L6.66'
                     . ' 21.62L5.93 18.72L7.07 15.86Z M6.29 16.09L5.13 18.5L5.22 21.47L4.18 21.53L3.87'
                     . ' 18.3L4.91 15.51Z M4.45 12.4L3.66 13.55L3.14 13.25L3.75 12Z"/>'
                     . '<path d="M15.5 10.2L15.68 9.81L15.9 9.47L16.17 9.19L16.48 8.96L16.81 8.8L17.16'
                     . ' 8.7L17.53 8.66L17.9 8.7L18.28 8.85L18.67 9.06L19.07 9.32L19.45 9.61L19.81 9.93L20.15'
                     . ' 10.26L20.45 10.59L20.7 10.9L20.74 11.08L20.71 11.25L20.63 11.4L20.5 11.53L20.33'
                     . ' 11.62L20.14 11.69L19.92 11.71L19.7 11.7L19.16 11.62L18.59 11.52L18.02 11.4L17.45'
                     . ' 11.25L16.9 11.06L16.38 10.83L15.91 10.54L15.5 10.2Z M16.05 8.98L15.79 9.09L15.55'
                     . ' 9.14L15.31 9.12L15.08 9.06L14.87 8.94L14.67 8.77L14.5 8.57L14.35 8.32L14.23 8.05L14.5'
                     . ' 7.91L14.77 7.82L15.04 7.78L15.29 7.77L15.53 7.82L15.75 7.91L15.94 8.06L16.1 8.25L16.22'
                     . ' 8.5L16.3 8.8Z M16.53 8.97L15.8 6.82L14.3 4.68L12.38 3.08L12.82 2.52L14.9 4.12L16.6'
                     . ' 6.38L17.47 8.63Z M16.71 7.71L17.31 7.66L17.89 7.55L18.47 7.38L19.06 7.13L19.14'
                     . ' 7.27L18.61 7.66L18.06 8L17.48 8.28L16.89 8.49Z M15.55 5.77L15.82 5.26L16 4.75L16.11'
                     . ' 4.2L16.13 3.61L16.27 3.59L16.41 4.19L16.45 4.8L16.39 5.43L16.25 6.03Z M12.35'
                     . ' 2.95L12.34 2.43L12.28 1.95L12.14 1.49L11.94 1.04L12.06 0.96L12.4 1.35L12.67 1.8L12.89'
                     . ' 2.31L13.05 2.85Z M12.78 3.35L12.22 3.24L11.67 3.05L11.15 2.79L10.67 2.47L10.73'
                     . ' 2.33L11.26 2.53L11.78 2.65L12.3 2.68L12.82 2.65Z M15.79 8.9L16.45 6.42L18.15'
                     . ' 4.34L19.91 3.13L20.29 3.67L18.65 4.86L17.15 6.78L16.61 9.1Z M17.98 4.77L17.93'
                     . ' 4.21L17.86 3.65L17.76 3.09L17.63 2.52L17.77 2.48L18.02 3.01L18.24 3.55L18.44'
                     . ' 4.09L18.62 4.63Z M19.72 3.39L19.94 2.97L20.12 2.54L20.24 2.08L20.33 1.6L20.47'
                     . ' 1.6L20.51 2.11L20.48 2.61L20.41 3.12L20.28 3.61Z"/>',

        /* REH: slender, big ears, no antlers — that is what tells it from
           the stag. Its first neck was „zu lang, das sieht mehr nach einem
           Lama aus"; the head now sits lower and nearer the body. */
        'roe_deer'  => '<path d="M15 10.6L15.2 10.93L15.33 11.29L15.41 11.69L15.42 12.1L15.37 12.51L15.25'
                     . ' 12.91L15.06 13.27L14.8 13.6L14.31 13.84L13.73 13.97L13.08 14.03L12.38 14.03L11.62'
                     . ' 14L10.83 13.97L10.02 13.96L9.2 14L8.58 14.06L7.95 14.1L7.32 14.09L6.7 14.02L6.12'
                     . ' 13.9L5.6 13.69L5.15 13.4L4.8 13L4.63 12.39L4.57 11.79L4.62 11.22L4.78 10.7L5.03'
                     . ' 10.25L5.39 9.91L5.85 9.68L6.4 9.6L7.27 9.66L8.28 9.7L9.38 9.74L10.55 9.8L11.73'
                     . ' 9.9L12.9 10.05L14 10.28L15 10.6Z M13 10.8L13.16 10.49L13.35 10.15L13.56 9.78L13.78'
                     . ' 9.4L14 9L14.24 8.58L14.47 8.14L14.7 7.7L14.87 7.5L15.07 7.36L15.3 7.28L15.55'
                     . ' 7.26L15.82 7.3L16.11 7.4L16.4 7.57L16.7 7.8L16.55 8.22L16.38 8.66L16.22 9.13L16.05'
                     . ' 9.61L15.88 10.12L15.72 10.65L15.55 11.21L15.4 11.8Z M14.85 13.17L14.97 17.37L15.2'
                     . ' 21.46L14.2 21.54L13.83 17.43L13.55 13.23Z M13 13.44L12.64 17.43L12.37 21.52L11.43'
                     . ' 21.48L11.56 17.37L11.8 13.36Z M8.65 13.85L7.2 17.25L7.89 21.41L6.91 21.59L6 17.15L7.35'
                     . ' 13.35Z M6.62 13.41L5.36 16.86L5.57 21.47L4.63 21.53L4.24 16.74L5.38 12.99Z"/>'
                     . '<path d="M14.9 7.5L15.08 7.08L15.3 6.72L15.57 6.41L15.88 6.17L16.21 6L16.56 5.9L16.93'
                     . ' 5.86L17.3 5.9L17.68 6.05L18.08 6.26L18.47 6.52L18.85 6.81L19.21 7.13L19.55 7.46L19.85'
                     . ' 7.79L20.1 8.1L20.14 8.28L20.11 8.45L20.03 8.6L19.9 8.72L19.73 8.82L19.54 8.89L19.32'
                     . ' 8.91L19.1 8.9L18.56 8.82L17.99 8.74L17.42 8.63L16.85 8.5L16.3 8.33L15.78 8.11L15.31'
                     . ' 7.84L14.9 7.5Z M15.68 5.95L15.32 5.83L15.03 5.65L14.8 5.42L14.63 5.15L14.52 4.83L14.48'
                     . ' 4.49L14.49 4.12L14.56 3.74L14.69 3.35L15.09 3.46L15.44 3.62L15.75 3.81L16.01'
                     . ' 4.04L16.21 4.3L16.35 4.6L16.41 4.92L16.4 5.26L16.3 5.62L16.1 6Z M15.15 6.58L14.81'
                     . ' 6.68L14.49 6.69L14.19 6.63L13.92 6.51L13.67 6.32L13.46 6.08L13.27 5.79L13.13'
                     . ' 5.45L13.03 5.09L13.39 4.97L13.75 4.9L14.09 4.89L14.41 4.93L14.7 5.03L14.96 5.18L15.18'
                     . ' 5.4L15.34 5.67L15.45 6.01L15.5 6.4Z"/>',

        /* WILDSCHWEIN: a wedge — high withers, a bristle ridge, a long
           snout with a tusk, a tufted tail, the eye a hole. Its legs are
           short, thick above and slender below, the front pair set a little
           forward and the hind pair bent at the hock: straight pillars
           („irgendetwas stört mich an den Füßen") were the first draft. */
        'wild_boar' => '<path d="M16 9.7L16.69 10.11L17.41 10.58L18.14 11.09L18.88 11.62L19.6 12.17L20.3'
                     . ' 12.71L20.97 13.22L21.6 13.7L21.7 13.83L21.79 13.99L21.85 14.18L21.89 14.39L21.9'
                     . ' 14.61L21.9 14.84L21.86 15.07L21.8 15.3L21.62 15.38L21.4 15.45L21.14 15.54L20.85'
                     . ' 15.62L20.54 15.72L20.23 15.83L19.91 15.96L19.6 16.1L19.28 16.26L18.92 16.43L18.53'
                     . ' 16.6L18.1 16.78L17.63 16.94L17.12 17.08L16.58 17.21L16 17.3L15.06 17.34L14.07'
                     . ' 17.32L13.02 17.26L11.93 17.18L10.81 17.08L9.67 16.99L8.53 16.93L7.4 16.9L6.61'
                     . ' 16.85L5.91 16.71L5.29 16.48L4.78 16.15L4.36 15.73L4.05 15.21L3.86 14.6L3.8 13.9L3.89'
                     . ' 13.14L4.15 12.49L4.57 11.93L5.13 11.45L5.8 11.03L6.58 10.64L7.46 10.27L8.4 9.9L9.39'
                     . ' 9.55L10.4 9.25L11.42 9.03L12.43 8.9L13.4 8.88L14.33 9L15.21 9.27L16 9.7Z M13.95'
                     . ' 9.3L14.5 7.8L15.15 9.3Z M12.15 9.3L12.7 7.8L13.35 9.3Z M10.35 9.7L10.9 8.2L11.55 9.7Z'
                     . ' M8.55 10.3L9.1 8.8L9.75 10.3Z M14.8 9.7L15.6 7.7L16.4 10.1Z M17.09 15.47L17.26'
                     . ' 18.51L17.32 21.45L16.28 21.55L15.74 18.69L15.11 15.73Z M14.74 15.7L14.82 18.54L14.8'
                     . ' 21.47L13.8 21.53L13.38 18.66L12.86 15.9Z M9.06 15.89L8.11 18.24L8.22 21.44L7.18'
                     . ' 21.56L6.49 18.16L6.94 15.31Z M6.72 15.45L5.77 18.05L5.8 21.46L4.8 21.54L4.23'
                     . ' 17.95L4.68 14.95Z M4.25 12.62L3.34 14.37L3.22 16.11L2.78 16.09L2.86 14.23L3.75 12.38Z'
                     . ' M2.6 16.23L2.85 15.94L3.15 15.94L3.4 16.23L3.5 16.7L3.4 17.17L3.15 17.46L2.85'
                     . ' 17.46L2.6 17.17L2.5 16.7Z M17.35 12.3L17.44 12.56L17.66 12.73L17.94 12.73L18.16'
                     . ' 12.56L18.25 12.3L18.16 12.04L17.94 11.87L17.66 11.87L17.44 12.04Z"/>'
                     . '<path d="M19.25 14.96L19.55 14.42L19.89 13.97L20.26 13.64L20.65 13.44L20.75 13.56L20.47'
                     . ' 13.86L20.26 14.23L20.09 14.68L19.95 15.24Z"/>',

        /* KUH: a deep, round belly with the udder under it — the first was
           „etwas mager" — horns, an ear, a tail with its tuft; the spots
           are holes. */
        'cow'       => '<path d="M17 9.4L17.33 9.99L17.56 10.68L17.71 11.44L17.76 12.25L17.73 13.08L17.6'
                     . ' 13.9L17.4 14.68L17.1 15.4L16.67 15.81L16.12 16.16L15.48 16.44L14.75 16.66L13.95'
                     . ' 16.83L13.08 16.96L12.16 17.05L11.2 17.1L10.28 17.11L9.35 17.05L8.42 16.93L7.5'
                     . ' 16.76L6.62 16.54L5.8 16.27L5.05 15.96L4.4 15.6L4.08 14.83L3.87 13.92L3.75 12.92L3.74'
                     . ' 11.9L3.81 10.92L3.96 10.03L4.2 9.31L4.5 8.8L4.81 8.57L5.15 8.39L5.51 8.28L5.88'
                     . ' 8.22L6.25 8.24L6.64 8.32L7.02 8.47L7.4 8.7L8.44 8.78L9.57 8.82L10.79 8.84L12.05'
                     . ' 8.86L13.33 8.91L14.6 9L15.83 9.15L17 9.4Z M17.05 15.6L16.9 21.5L15.3 21.5L15.15 15.6Z'
                     . ' M14.35 15.6L14.2 21.5L12.6 21.5L12.45 15.6Z M8.95 15.6L8.8 21.5L7.2 21.5L7.05 15.6Z'
                     . ' M6.15 15.6L6 21.5L4.4 21.5L4.25 15.6Z M8.46 16.77L8.9 16.42L9.54 16.23L10.26'
                     . ' 16.23L10.9 16.42L11.34 16.77L11.5 17.2L11.34 17.63L10.9 17.98L10.26 18.17L9.54'
                     . ' 18.17L8.9 17.98L8.46 17.63L8.3 17.2Z M4.09 9.47L3.37 12.44L3.15 16.01L2.65 15.99L2.83'
                     . ' 12.36L3.51 9.33Z M2.46 16.07L2.73 15.74L3.07 15.74L3.34 16.07L3.45 16.6L3.34'
                     . ' 17.13L3.07 17.46L2.73 17.46L2.46 17.13L2.35 16.6Z M13.8 10.3L13.32 10.09L12.8'
                     . ' 9.98L12.27 9.97L11.77 10.06L11.34 10.25L11.02 10.54L10.82 10.92L10.8 11.4L10.94'
                     . ' 11.87L11.21 12.23L11.56 12.48L11.96 12.64L12.39 12.69L12.81 12.65L13.19 12.52L13.5'
                     . ' 12.3L13.74 12.03L13.93 11.76L14.06 11.49L14.14 11.22L14.15 10.97L14.1 10.73L13.99'
                     . ' 10.5L13.8 10.3Z M8.4 11.6L7.9 11.39L7.4 11.28L6.92 11.28L6.49 11.39L6.12 11.6L5.85'
                     . ' 11.92L5.71 12.36L5.7 12.9L5.84 13.37L6.11 13.73L6.46 13.97L6.86 14.11L7.29 14.14L7.71'
                     . ' 14.07L8.09 13.89L8.4 13.6L8.63 13.27L8.79 12.96L8.89 12.68L8.93 12.41L8.89 12.17L8.79'
                     . ' 11.95L8.63 11.76L8.4 11.6Z"/>'
                     . '<path d="M16.6 10.4L16.85 9.99L17.15 9.65L17.49 9.38L17.85 9.19L18.23 9.06L18.62'
                     . ' 9.01L19.02 9.02L19.4 9.1L19.77 9.33L20.11 9.68L20.43 10.15L20.73 10.69L20.99'
                     . ' 11.28L21.23 11.9L21.43 12.51L21.6 13.1L21.62 13.42L21.54 13.71L21.38 13.95L21.15'
                     . ' 14.14L20.86 14.27L20.53 14.32L20.17 14.3L19.8 14.2L19.41 13.96L18.99 13.69L18.55'
                     . ' 13.42L18.1 13.15L17.65 12.9L17.21 12.68L16.79 12.51L16.4 12.4Z M17.45 10.23L17.25'
                     . ' 10.5L17.03 10.69L16.78 10.82L16.5 10.88L16.22 10.89L15.92 10.83L15.62 10.72L15.33'
                     . ' 10.56L15.04 10.35L15.24 10.06L15.46 9.8L15.7 9.6L15.96 9.45L16.23 9.35L16.51'
                     . ' 9.32L16.79 9.35L17.07 9.46L17.34 9.64L17.6 9.9Z M18.1 9.18L18.21 8.72L18.22 8.32L18.13'
                     . ' 7.91L17.91 7.49L17.55 7.06L17.65 6.94L18.14 7.27L18.55 7.66L18.85 8.12L19.04 8.67L19.1'
                     . ' 9.22Z M19.17 9.27L19.38 8.81L19.51 8.39L19.55 7.95L19.49 7.5L19.33 7.03L19.47'
                     . ' 6.97L19.77 7.39L19.99 7.87L20.11 8.4L20.12 8.96L20.03 9.53Z"/>',

        /* SCHAF: a fleece of bumps, smaller than first drawn („zu viel
           Wolle") and on short legs, with a narrow head in front — drawn
           after the fleece, so its white edge parts the two. */
        'sheep'     => '<path d="M4.91 12.44L5.52 11.55L6.49 10.78L7.75 10.2L9.22 9.83L10.8 9.7L12.38'
                     . ' 9.83L13.85 10.2L15.11 10.78L16.08 11.55L16.69 12.44L16.9 13.4L16.69 14.36L16.08'
                     . ' 15.25L15.11 16.02L13.85 16.6L12.38 16.97L10.8 17.1L9.22 16.97L7.75 16.6L6.49'
                     . ' 16.02L5.52 15.25L4.91 14.36L4.7 13.4Z M3.81 12.75L4.36 12.35L5.04 12.35L5.59 12.75L5.8'
                     . ' 13.4L5.59 14.05L5.04 14.45L4.36 14.45L3.81 14.05L3.6 13.4Z M4.22 14.09L4.77 13.69L5.45'
                     . ' 13.69L6 14.09L6.21 14.74L6 15.38L5.45 15.78L4.77 15.78L4.22 15.38L4.01 14.74Z M5.4'
                     . ' 15.25L5.95 14.85L6.63 14.85L7.18 15.25L7.39 15.89L7.18 16.54L6.63 16.94L5.95 16.94L5.4'
                     . ' 16.54L5.19 15.89Z M7.19 16.07L7.74 15.67L8.42 15.67L8.97 16.07L9.18 16.71L8.97'
                     . ' 17.36L8.42 17.76L7.74 17.76L7.19 17.36L6.98 16.71Z M9.35 16.44L9.9 16.04L10.58'
                     . ' 16.04L11.13 16.44L11.34 17.08L11.13 17.73L10.58 18.13L9.9 18.13L9.35 17.73L9.14 17.08Z'
                     . ' M11.58 16.31L12.13 15.91L12.81 15.91L13.36 16.31L13.57 16.96L13.36 17.61L12.81'
                     . ' 18L12.13 18L11.58 17.61L11.37 16.96Z M13.59 15.71L14.14 15.31L14.82 15.31L15.37'
                     . ' 15.71L15.58 16.35L15.37 17L14.82 17.4L14.14 17.4L13.59 17L13.38 16.35Z M15.1'
                     . ' 14.7L15.65 14.3L16.33 14.3L16.88 14.7L17.09 15.35L16.88 15.99L16.33 16.39L15.65'
                     . ' 16.39L15.1 15.99L14.89 15.35Z M15.91 13.43L16.46 13.03L17.14 13.03L17.69 13.43L17.9'
                     . ' 14.08L17.69 14.73L17.14 15.13L16.46 15.13L15.91 14.73L15.7 14.08Z M15.91 12.07L16.46'
                     . ' 11.67L17.14 11.67L17.69 12.07L17.9 12.72L17.69 13.37L17.14 13.77L16.46 13.77L15.91'
                     . ' 13.37L15.7 12.72Z M15.1 10.81L15.65 10.41L16.33 10.41L16.88 10.81L17.09 11.45L16.88'
                     . ' 12.1L16.33 12.5L15.65 12.5L15.1 12.1L14.89 11.45Z M13.59 9.8L14.14 9.4L14.82 9.4L15.37'
                     . ' 9.8L15.58 10.45L15.37 11.09L14.82 11.49L14.14 11.49L13.59 11.09L13.38 10.45Z M11.58'
                     . ' 9.19L12.13 8.8L12.81 8.8L13.36 9.19L13.57 9.84L13.36 10.49L12.81 10.89L12.13'
                     . ' 10.89L11.58 10.49L11.37 9.84Z M9.35 9.07L9.9 8.67L10.58 8.67L11.13 9.07L11.34'
                     . ' 9.72L11.13 10.36L10.58 10.76L9.9 10.76L9.35 10.36L9.14 9.72Z M7.19 9.44L7.74 9.04L8.42'
                     . ' 9.04L8.97 9.44L9.18 10.09L8.97 10.73L8.42 11.13L7.74 11.13L7.19 10.73L6.98 10.09Z M5.4'
                     . ' 10.26L5.95 9.86L6.63 9.86L7.18 10.26L7.39 10.91L7.18 11.55L6.63 11.95L5.95 11.95L5.4'
                     . ' 11.55L5.19 10.91Z M4.22 11.42L4.77 11.02L5.45 11.02L6 11.42L6.21 12.06L6 12.71L5.45'
                     . ' 13.11L4.77 13.11L4.22 12.71L4.01 12.06Z M15.43 16L15.35 21.5L14.25 21.5L14.18 16Z'
                     . ' M13.03 16L12.95 21.5L11.85 21.5L11.78 16Z M9.43 16L9.35 21.5L8.25 21.5L8.18 16Z M7.02'
                     . ' 16L6.95 21.5L5.85 21.5L5.77 16Z"/>'
                     . '<path d="M16.18 12.2L15.96 11.46L15.95 10.77L16.14 10.18L16.51 9.77L17.02 9.56L17.64'
                     . ' 9.59L18.28 9.84L18.9 10.29L19.43 10.9L19.82 11.6L20.04 12.34L20.05 13.03L19.86'
                     . ' 13.62L19.49 14.03L18.98 14.24L18.36 14.21L17.72 13.96L17.1 13.51L16.57 12.9Z M17.02'
                     . ' 10L17.18 9.76L17.38 9.58L17.6 9.45L17.85 9.37L18.11 9.34L18.38 9.35L18.66 9.42L18.94'
                     . ' 9.53L19.22 9.68L19.06 9.95L18.87 10.18L18.66 10.38L18.43 10.53L18.19 10.64L17.93'
                     . ' 10.69L17.67 10.69L17.41 10.63L17.15 10.5L16.9 10.3Z"/>',

        /* THE ARACHNIDS (24.09.2026: „schau doch mal, was wir in
           der Artenliste bei Spinnen haben"). The group 'arachnids' in
           species_packages.php is the whole class Arachnida: in the German
           list 145 species, 44 of them spiders, 5 ticks, 5 scorpions (one
           a pseudoscorpion); Fauna Europaea adds harvestmen, mites and
           pseudoscorpions — 24,016 names. He took all five signs drawn for
           them. All are drawn for the thin border (map.js, symbolFor): legs
           about a unit wide and a unit apart where they come close.

           SPINNE — drawn anew with the others and replacing the first one:
           from above, a front body and a larger abdomen, a pair of palps,
           four legs a side bent at the knee. The first drawing's lesson
           stands: a spider of straight strokes looks like a star. */
        'spider'    => '<path d="M14.3 9.6L14.22 10.27L13.99 10.9L13.63 11.44L13.15 11.85L12.6 12.11L12'
                     . ' 12.2L11.4 12.11L10.85 11.85L10.37 11.44L10.01 10.9L9.78 10.27L9.7 9.6L9.78 8.93L10.01'
                     . ' 8.3L10.37 7.76L10.85 7.35L11.4 7.09L12 7L12.6 7.09L13.15 7.35L13.63 7.76L13.99'
                     . ' 8.3L14.22 8.93Z M10.04 8.81L6.83 5.88L5.26 2.36L5.94 2.04L7.57 5.32L10.76 7.99Z M13.24'
                     . ' 7.99L16.43 5.32L18.06 2.04L18.74 2.36L17.17 5.88L13.96 8.81Z M9.83 9.92L5.39 8.41L2.37'
                     . ' 5.89L2.83 5.31L5.81 7.59L10.17 8.88Z M13.83 8.88L18.19 7.59L21.17 5.31L21.63'
                     . ' 5.89L18.61 8.41L14.17 9.92Z M10.14 11.13L5.64 12.2L2.88 15.25L2.32 14.75L5.16'
                     . ' 11.4L9.86 10.07Z M14.14 10.07L18.84 11.4L21.68 14.75L21.12 15.25L18.36 12.2L13.86'
                     . ' 11.13Z M10.78 12L7.39 15.05L5.55 19.73L4.85 19.47L6.61 14.55L10.02 11.2Z M13.98'
                     . ' 11.2L17.39 14.55L19.15 19.47L18.45 19.73L16.61 15.05L13.22 12Z M10.83 7.76L10.3'
                     . ' 6.33L10.9 6.07L11.57 7.44Z M12.43 7.44L13.1 6.07L13.7 6.33L13.17 7.76Z"/>'
                     . '<path d="M15.2 15.4L15.12 16.27L14.88 17.09L14.5 17.83L14 18.45L13.39 18.91L12.71'
                     . ' 19.2L12 19.3L11.29 19.2L10.61 18.91L10 18.45L9.5 17.83L9.12 17.09L8.88 16.27L8.8'
                     . ' 15.4L8.88 14.53L9.12 13.71L9.5 12.97L10 12.35L10.61 11.89L11.29 11.6L12 11.5L12.71'
                     . ' 11.6L13.39 11.89L14 12.35L14.5 12.97L14.88 13.71L15.12 14.53Z"/>',

        /* SPINNE AM FADEN — his own picture („eine Spinne, die am Faden
           hängt, also der Faden nach oben, und dann unten die Spinne"). It
           hangs from its spinnerets as a real one does: the abdomen on the
           thread, the head below, the legs spread. The thread is 0.8 wide;
           thinner vanishes under the white edge. It hangs by the thread's
           top (anchor in map.js): the point is where the thread is fixed. */
        'hanging_spider' => '<path d="M12.4 0.4L12.4 9.4L11.6 9.4L11.6 0.4Z M14.8 11.8L14.73 12.53L14.52 13.23L14.19'
                     . ' 13.86L13.75 14.38L13.21 14.77L12.62 15.02L12 15.1L11.38 15.02L10.79 14.77L10.25'
                     . ' 14.38L9.81 13.86L9.48 13.23L9.27 12.53L9.2 11.8L9.27 11.07L9.48 10.37L9.81 9.74L10.25'
                     . ' 9.22L10.79 8.83L11.38 8.58L12 8.5L12.62 8.58L13.21 8.83L13.75 9.22L14.19 9.74L14.52'
                     . ' 10.37L14.73 11.07Z"/>'
                     . '<path d="M14.1 16L14.01 16.65L13.77 17.24L13.38 17.74L12.87 18.09L12.3 18.28L11.7'
                     . ' 18.28L11.13 18.09L10.62 17.74L10.23 17.24L9.99 16.65L9.9 16L9.99 15.35L10.23'
                     . ' 14.76L10.62 14.26L11.13 13.91L11.7 13.72L12.3 13.72L12.87 13.91L13.38 14.26L13.77'
                     . ' 14.76L14.01 15.35Z M10.73 17.38L7.74 19.86L6.32 22.94L5.68 22.66L7.06 19.34L10.07'
                     . ' 16.62Z M13.93 16.62L16.94 19.34L18.32 22.66L17.68 22.94L16.26 19.86L13.27 17.38Z'
                     . ' M10.16 16.67L6 17.97L3.23 20.27L2.77 19.73L5.6 17.23L9.84 15.73Z M14.16 15.73L18.4'
                     . ' 17.23L21.23 19.73L20.77 20.27L18 17.97L13.84 16.67Z M9.82 15.67L5.57 13.96L2.94'
                     . ' 11.04L3.46 10.56L6.03 13.24L10.18 14.73Z M13.82 14.73L17.97 13.24L20.54 10.56L21.06'
                     . ' 11.04L18.43 13.96L14.18 15.67Z M10.23 14.74L7.44 11.62L6.27 7.91L6.93 7.69L8.16'
                     . ' 11.18L10.97 14.06Z M13.03 14.06L15.84 11.18L17.07 7.69L17.73 7.91L16.56 11.62L13.77'
                     . ' 14.74Z"/>',

        /* WEBERKNECHT from the side: a small round body slung low between
           long legs arched high at the knee. Seen from above — one small
           body, eight long legs radiating — it was a star; he had both live
           side by side, and the side view won „ganz klar". The trial key
           'harvestman_side' lives on as an alias (data.php) for any list
           that picked it meanwhile. It stands on its feet (anchor in
           map.js). */
        'harvestman' => '<path d="M10.7 12.36L8.9 5.48L2.93 21.62L2.27 21.38L8.3 4.92L11.6 12.04Z M11'
                     . ' 12.35L10.16 7.6L6.54 21.59L5.86 21.41L9.44 7.2L11.9 12.05Z M12.04 12.08L14.32'
                     . ' 5.95L20.13 21.38L19.47 21.62L13.68 6.45L12.96 12.32Z M12.45 12.05L15.89 3.91L22.53'
                     . ' 21.38L21.87 21.62L15.31 4.49L13.35 12.35Z M10.24 12.69L6.41 8.11L1.7 18.73L1.1'
                     . ' 18.47L5.99 7.49L10.86 12.11Z M13.13 12.12L17.98 7.07L23.09 17.46L22.51 17.74L17.62'
                     . ' 7.73L13.77 12.68Z"/>'
                     . '<path d="M14.4 12.6L14.3 13.11L14.02 13.57L13.57 13.96L13 14.24L12.34 14.38L11.66'
                     . ' 14.38L11 14.24L10.43 13.96L9.98 13.57L9.7 13.11L9.6 12.6L9.7 12.09L9.98 11.63L10.43'
                     . ' 11.24L11 10.96L11.66 10.82L12.34 10.82L13 10.96L13.57 11.24L14.02 11.63L14.3 12.09Z"/>',

        /* ZECKE from above: a teardrop body, the shield an element of its
           own so its white edge draws the rim, the mouthparts in front,
           eight short legs reaching forward. */
        'tick'      => '<path d="M12 7.8L13.13 7.96L14.13 8.41L14.98 9.11L15.67 10L16.21 11.04L16.58'
                     . ' 12.19L16.78 13.39L16.8 14.6L16.64 15.97L16.33 17.2L15.88 18.29L15.3 19.23L14.61'
                     . ' 19.98L13.82 20.53L12.94 20.88L12 21L11.06 20.88L10.18 20.53L9.39 19.98L8.7 19.22L8.12'
                     . ' 18.29L7.67 17.2L7.36 15.97L7.2 14.6L7.22 13.39L7.42 12.19L7.79 11.04L8.32 10L9.02'
                     . ' 9.11L9.87 8.41L10.87 7.96L12 7.8Z M11 8.2L10.95 7.67L10.96 7.13L11.02 6.61L11.12'
                     . ' 6.12L11.28 5.68L11.48 5.3L11.72 5L12 4.8L12.28 5L12.52 5.3L12.72 5.68L12.88 6.12L12.98'
                     . ' 6.61L13.04 7.13L13.05 7.67L13 8.2Z M8.75 10.04L5.48 8.1L4.45 5.33L5.15 5.07L6.12'
                     . ' 7.5L9.25 9.16Z M14.75 9.16L17.88 7.5L18.85 5.07L19.55 5.33L18.52 8.1L15.25 10.04Z'
                     . ' M8.32 11.49L4.6 10.79L2.91 8.63L3.49 8.17L5 10.01L8.48 10.51Z M15.52 10.51L19'
                     . ' 10.01L20.51 8.17L21.09 8.63L19.4 10.79L15.68 11.49Z M8.31 12.89L4.58 13.64L2.8'
                     . ' 12.52L3.2 11.88L4.62 12.76L8.09 11.91Z M15.91 11.91L19.38 12.76L20.8 11.88L21.2'
                     . ' 12.52L19.42 13.64L15.69 12.89Z M8.68 14.21L5.5 16.32L4.53 17.98L3.87 17.62L4.9'
                     . ' 15.68L8.12 13.39Z M15.88 13.39L19.1 15.68L20.13 17.62L19.47 17.98L18.5 16.32L15.32'
                     . ' 14.21Z"/>'
                     . '<path d="M12 8.9L12.76 8.97L13.39 9.16L13.89 9.46L14.27 9.85L14.53 10.32L14.67'
                     . ' 10.84L14.69 11.41L14.6 12L14.36 12.49L14.08 12.9L13.78 13.25L13.45 13.53L13.1'
                     . ' 13.74L12.74 13.88L12.37 13.97L12 14L11.63 13.97L11.26 13.88L10.9 13.74L10.55'
                     . ' 13.53L10.22 13.25L9.92 12.9L9.64 12.49L9.4 12L9.31 11.41L9.33 10.84L9.47 10.32L9.72'
                     . ' 9.85L10.11 9.46L10.61 9.16L11.24 8.97L12 8.9Z"/>',

        /* SKORPION from the side, looking right like the mammals: a low
           body, two claws — the far one higher, drawn first, so the near
           one's white edge lies over it — four legs fanned, the front pair
           forward and the hind pair back, and a slim tail arched over the
           back with its bulb and a hooked sting. The first side view, one
           claw and four legs straight down like the teeth of a comb, did
           not please him („gefällt mir noch nicht"); from above, the tail,
           the pincers and eight legs covered each other into a string of
           beads, and a second top view lost to this one. The tail's joints
           are cuts the white edge fills; it stands on its legs (anchor in
           map.js). */
        'scorpion'  => '<path d="M15.2 13.7L16.4 12.14L17 11L17.8 11.4L17.2 12.66L16 14.3Z M19.23 9.33L19.38'
                     . ' 9.66L19.34 10.07L19.13 10.5L18.78 10.89L18.34 11.17L17.88 11.3L17.47 11.27L17.17'
                     . ' 11.07L17.02 10.74L17.06 10.33L17.27 9.9L17.62 9.51L18.06 9.23L18.52 9.1L18.93 9.13Z'
                     . ' M18.64 9.01L19.05 8.54L19.49 8.17L19.94 7.94L20.37 7.83L20.43 7.97L20.07 8.18L19.76'
                     . ' 8.48L19.46 8.87L19.16 9.39Z M19.33 9.55L19.8 9.3L20.16 9.03L20.43 8.73L20.63'
                     . ' 8.37L20.77 8.43L20.67 8.85L20.44 9.27L20.1 9.68L19.67 10.05Z"/>'
                     . '<path d="M6.6 15L7.07 14.45L7.83 13.99L8.84 13.63L10.03 13.38L11.33 13.22L12.7'
                     . ' 13.17L14.08 13.23L15.4 13.4L15.95 13.54L16.41 13.73L16.78 13.96L17.06 14.24L17.25'
                     . ' 14.54L17.35 14.88L17.37 15.23L17.3 15.6L16.92 15.95L16.2 16.24L15.21 16.47L14.01'
                     . ' 16.64L12.67 16.75L11.24 16.8L9.8 16.78L8.4 16.7L7.97 16.61L7.58 16.48L7.25 16.31L6.98'
                     . ' 16.11L6.77 15.88L6.63 15.62L6.57 15.32L6.6 15Z M15.42 15.92L16.8 18.13L17.66'
                     . ' 21.4L16.94 21.6L16 18.47L14.58 16.48Z M13.57 16.24L14.23 18.41L14.57 21.45L13.83'
                     . ' 21.55L13.37 18.59L12.63 16.56Z M11.69 16.62L11.13 18.58L10.67 21.55L9.93 21.45L10.27'
                     . ' 18.42L10.71 16.38Z M9.73 16.56L8.51 18.46L7.56 21.6L6.84 21.4L7.69 18.14L8.87 16.04Z'
                     . ' M16.13 14.49L17.62 13.74L18.61 13.09L19.19 13.91L18.18 14.66L16.67 15.51Z M21.49'
                     . ' 12.47L21.49 12.85L21.3 13.22L20.93 13.56L20.44 13.82L19.89 13.97L19.34 13.99L18.85'
                     . ' 13.88L18.5 13.65L18.31 13.33L18.31 12.95L18.5 12.58L18.87 12.24L19.36 11.98L19.91'
                     . ' 11.83L20.46 11.81L20.95 11.92L21.3 12.15Z M20.96 11.85L21.58 11.68L22.16 11.65L22.66'
                     . ' 11.77L23.03 12.03L22.97 12.17L22.58 12.06L22.19 12.1L21.76 12.26L21.24 12.55Z M21.23'
                     . ' 12.85L21.72 12.97L22.13 12.99L22.5 12.92L22.85 12.74L22.95 12.86L22.64 13.18L22.22'
                     . ' 13.41L21.72 13.53L21.17 13.55Z"/>'
                     . '<path d="M6.74 15.05L5.85 14.47L5.05 13.68L4.43 12.77L3.98 11.79L3.71 10.75L3.6'
                     . ' 9.69L3.65 8.65L3.88 7.65L4.28 6.71L4.86 5.89L5.63 5.22L6.55 4.77L7.49 4.51L8.38'
                     . ' 4.39L9.22 4.39L9.99 4.51L10.6 4.73L10.2 5.87L9.71 5.71L9.16 5.65L8.5 5.68L7.78'
                     . ' 5.81L7.05 6.03L6.42 6.37L5.94 6.83L5.56 7.4L5.31 8.08L5.17 8.83L5.15 9.63L5.26'
                     . ' 10.44L5.5 11.22L5.86 11.95L6.32 12.58L6.89 13.11L7.66 13.55Z M12.57 6.33L12.36 6.64L12'
                     . ' 6.83L11.55 6.89L11.08 6.79L10.65 6.56L10.34 6.23L10.19 5.85L10.23 5.47L10.44 5.16L10.8'
                     . ' 4.97L11.25 4.91L11.72 5.01L12.15 5.24L12.46 5.57L12.61 5.95Z M12.51 5.96L12.93'
                     . ' 6.29L13.23 6.77L13.33 7.3L13.27 7.88L13.07 8.52L12.93 8.48L12.99 7.85L12.93 7.36L12.75'
                     . ' 7.02L12.49 6.8L12.09 6.64Z M4.27 13.05L6.17 11.95L6.01 11.67L4.11 12.77Z M3.28'
                     . ' 9.87L5.48 9.78L5.47 9.46L3.27 9.55Z M3.88 6.68L5.82 7.72L5.97 7.44L4.03 6.4Z M6.25'
                     . ' 4.44L7.05 6.48L7.35 6.36L6.55 4.32Z"/>',

        /* THE MOLLUSCS (24.09.2026: „was haben wir denn bei
           Schnecken?"). The group 'molluscs' in species_packages.php: in
           the German list 528 snails and slugs — 235 names end in
           „-schnecke", 30 are slugs (Wegschnecken, Schnegel) — 68 mussels,
           a few cephalopods, chitons and tusk shells; Fauna Europaea adds
           13,498 snails and 231 mussels. He took all four signs drawn for
           them, all looking right like the other animals.

           SCHNECKE, drawn anew and replacing the ring on an arc: a shell
           with a spiral groove (a cut the white edge fills), seated on the
           foot, the body leaving its mouth in front, eye stalks with knobs
           and two short lower feelers. Seated LOW and large — set higher,
           it was „eine Nacktschnecke mit einer Kugel oben drauf". */
        'snail'     => '<path d="M3 21L3.46 20.72L3.93 20.47L4.42 20.26L4.92 20.07L5.44 19.92L5.95 19.79L6.48'
                     . ' 19.68L7 19.6L8.17 19.53L9.42 19.47L10.68 19.4L11.92 19.3L13.1 19.17L14.17 18.98L15.09'
                     . ' 18.73L15.8 18.4L16.32 17.93L16.82 17.42L17.3 16.92L17.77 16.44L18.24 16.01L18.7'
                     . ' 15.66L19.15 15.41L19.6 15.3L20.03 15.32L20.4 15.43L20.72 15.63L20.98 15.9L21.17'
                     . ' 16.23L21.29 16.59L21.33 16.99L21.3 17.4L21.2 17.76L21.05 18.08L20.85 18.37L20.62'
                     . ' 18.62L20.37 18.86L20.09 19.06L19.8 19.24L19.5 19.4L19.07 19.71L18.65 20.04L18.25'
                     . ' 20.36L17.84 20.66L17.4 20.93L16.93 21.16L16.4 21.32L15.8 21.4L13.99 21.43L12.2'
                     . ' 21.45L10.43 21.45L8.72 21.43L7.1 21.37L5.59 21.29L4.21 21.16L3 21Z M19.15 15.57L20.8'
                     . ' 11.19L21.4 11.41L19.85 15.83Z M21.72 11.3L21.64 11.61L21.41 11.84L21.1 11.92L20.79'
                     . ' 11.84L20.56 11.61L20.48 11.3L20.56 10.99L20.79 10.76L21.1 10.68L21.41 10.76L21.64'
                     . ' 10.99Z M18.53 15.93L18.18 11.53L18.82 11.47L19.27 15.87Z M19.12 11.5L19.04 11.81L18.81'
                     . ' 12.04L18.5 12.12L18.19 12.04L17.96 11.81L17.88 11.5L17.96 11.19L18.19 10.96L18.5'
                     . ' 10.88L18.81 10.96L19.04 11.19Z M21.01 17.32L22.79 18.07L22.61 18.53L20.79 17.88Z'
                     . ' M20.86 18.07L22.43 19.21L22.17 19.59L20.54 18.53Z"/>'
                     . '<path d="M16.6 14L16.55 14.81L16.39 15.6L16.13 16.37L15.77 17.1L15.32 17.77L14.78'
                     . ' 18.38L14.17 18.92L13.5 19.37L12.77 19.73L12 19.99L11.21 20.15L10.4 20.2L9.59 20.15L8.8'
                     . ' 19.99L8.03 19.73L7.3 19.37L6.63 18.92L6.02 18.38L5.48 17.77L5.03 17.1L4.67 16.37L4.41'
                     . ' 15.6L4.25 14.81L4.2 14L4.25 13.19L4.41 12.4L4.67 11.63L5.03 10.9L5.48 10.23L6.02'
                     . ' 9.62L6.63 9.08L7.3 8.63L8.03 8.27L8.8 8.01L9.59 7.85L10.4 7.8L11.21 7.85L12 8.01L12.77'
                     . ' 8.27L13.5 8.63L14.17 9.08L14.78 9.62L15.32 10.23L15.77 10.9L16.13 11.63L16.39'
                     . ' 12.4L16.55 13.19Z M10.1 13.79L10.02 13.68L9.98 13.57L9.96 13.44L9.97 13.29L10.02'
                     . ' 13.14L10.1 12.98L10.22 12.84L10.38 12.71L10.57 12.62L10.79 12.55L11.04 12.54L11.29'
                     . ' 12.57L11.55 12.65L11.8 12.79L12.03 12.98L12.22 13.23L12.38 13.52L12.47 13.84L12.51'
                     . ' 14.2L12.48 14.57L12.37 14.93L12.2 15.29L11.95 15.62L11.63 15.9L11.25 16.13L10.83'
                     . ' 16.28L10.37 16.36L9.88 16.35L9.4 16.25L8.93 16.05L8.49 15.76L8.1 15.39L7.79 14.93L7.55'
                     . ' 14.42L7.41 13.86L7.38 13.26L7.46 12.66L7.65 12.07L7.96 11.51L8.37 11.01L8.88'
                     . ' 10.58L9.47 10.24L10.13 10.02L10.83 9.92L11.55 9.95L12.26 10.12L12.95 10.42L13.58'
                     . ' 10.85L14.13 11.4L14.58 12.05L14.91 12.79L15.1 13.58L15.14 14.42L15.03 15.26L14.76'
                     . ' 16.08L14.33 16.85L13.76 17.53L13.07 18.11L12.27 18.56L11.36 18.87L11.51 19.3L12.45'
                     . ' 18.98L13.33 18.48L14.08 17.85L14.7 17.1L15.17 16.26L15.47 15.36L15.59 14.44L15.55'
                     . ' 13.52L15.34 12.64L14.98 11.83L14.48 11.11L13.87 10.5L13.17 10.02L12.41 9.69L11.61'
                     . ' 9.51L10.8 9.47L10.02 9.58L9.29 9.83L8.62 10.21L8.05 10.69L7.58 11.26L7.24 11.89L7.02'
                     . ' 12.56L6.93 13.25L6.97 13.92L7.13 14.57L7.4 15.16L7.76 15.68L8.21 16.11L8.72 16.45L9.26'
                     . ' 16.68L9.83 16.8L10.4 16.81L10.94 16.72L11.45 16.53L11.9 16.26L12.28 15.92L12.58'
                     . ' 15.53L12.79 15.1L12.92 14.65L12.96 14.19L12.92 13.76L12.79 13.35L12.6 12.98L12.35'
                     . ' 12.67L12.05 12.42L11.73 12.24L11.39 12.13L11.05 12.09L10.72 12.11L10.41 12.2L10.14'
                     . ' 12.34L9.91 12.52L9.73 12.73L9.6 12.97L9.53 13.21L9.51 13.45L9.55 13.68L9.62 13.88L9.72'
                     . ' 14.03Z"/>',

        /* NACKTSCHNECKE: long and low, the mantle shield in front as a ring
           (a cut) with its breathing hole, ridges on the back behind it.
           The body keeps its height nearly to the end and only there runs
           out („die kenne ich konstant länger"): a tail that tapered from
           the middle made a wedge of it. */
        'slug'      => '<path d="M1.4 21L1.65 20.48L1.96 19.96L2.33 19.47L2.75 19L3.23 18.57L3.76 18.19L4.35'
                     . ' 17.86L5 17.6L5.92 17.38L6.85 17.18L7.78 16.97L8.7 16.75L9.58 16.51L10.4 16.25L11.15'
                     . ' 15.95L11.8 15.6L12.35 15.11L12.95 14.71L13.58 14.38L14.22 14.15L14.88 14.01L15.54'
                     . ' 13.97L16.18 14.03L16.8 14.2L17.17 14.35L17.55 14.5L17.93 14.63L18.3 14.76L18.68'
                     . ' 14.88L19.05 14.97L19.43 15.05L19.8 15.1L20.19 15.18L20.54 15.33L20.84 15.55L21.09'
                     . ' 15.82L21.27 16.15L21.39 16.51L21.43 16.89L21.4 17.3L21.3 17.69L21.15 18.04L20.95'
                     . ' 18.34L20.72 18.61L20.47 18.85L20.19 19.06L19.9 19.24L19.6 19.4L19.17 19.71L18.75'
                     . ' 20.04L18.34 20.36L17.93 20.66L17.48 20.93L16.98 21.16L16.43 21.32L15.8 21.4L13.93'
                     . ' 21.43L12.06 21.45L10.21 21.45L8.38 21.43L6.57 21.37L4.8 21.29L3.08 21.16L1.4 21Z'
                     . ' M19.45 15.46L21.2 11.09L21.8 11.31L20.15 15.74Z M22.12 11.2L22.04 11.51L21.81'
                     . ' 11.74L21.5 11.82L21.19 11.74L20.96 11.51L20.88 11.2L20.96 10.89L21.19 10.66L21.5'
                     . ' 10.58L21.81 10.66L22.04 10.89Z M18.83 15.82L18.68 11.41L19.32 11.39L19.57 15.78Z'
                     . ' M19.62 11.4L19.54 11.71L19.31 11.94L19 12.02L18.69 11.94L18.46 11.71L18.38 11.4L18.46'
                     . ' 11.09L18.69 10.86L19 10.78L19.31 10.86L19.54 11.09Z M21.09 17.32L22.88 17.96L22.72'
                     . ' 18.44L20.91 17.88Z M20.95 18.07L22.52 19.11L22.28 19.49L20.65 18.53Z M17.09'
                     . ' 16.57L17.06 16.85L16.97 17.09L16.82 17.33L16.61 17.56L16.34 17.77L16.01 17.95L15.65'
                     . ' 18.09L15.25 18.2L14.83 18.27L14.4 18.29L13.97 18.27L13.55 18.2L13.15 18.09L12.79'
                     . ' 17.95L12.46 17.77L12.19 17.56L11.98 17.33L11.83 17.09L11.74 16.85L11.71 16.6L11.74'
                     . ' 16.35L11.83 16.11L11.98 15.87L12.19 15.64L12.46 15.43L12.79 15.25L13.15 15.11L13.55'
                     . ' 15L13.97 14.93L14.4 14.91L14.83 14.93L15.25 15L15.65 15.11L16.01 15.25L16.34'
                     . ' 15.43L16.61 15.64L16.82 15.87L16.97 16.11L17.06 16.35L17.09 16.63L17.51 16.57L17.47'
                     . ' 16.25L17.35 15.92L17.15 15.61L16.89 15.33L16.57 15.08L16.19 14.87L15.78 14.71L15.34'
                     . ' 14.59L14.88 14.51L14.4 14.49L13.92 14.51L13.46 14.59L13.02 14.71L12.61 14.87L12.23'
                     . ' 15.08L11.91 15.33L11.65 15.61L11.45 15.92L11.33 16.25L11.29 16.6L11.33 16.95L11.45'
                     . ' 17.28L11.65 17.59L11.91 17.87L12.23 18.12L12.61 18.33L13.02 18.49L13.46 18.61L13.92'
                     . ' 18.69L14.4 18.71L14.88 18.69L15.34 18.61L15.78 18.49L16.19 18.33L16.57 18.12L16.89'
                     . ' 17.87L17.15 17.59L17.35 17.28L17.47 16.95L17.51 16.63Z M16.8 17.21L16.55 17.02L16.25'
                     . ' 17.02L16 17.21L15.9 17.5L16 17.79L16.25 17.98L16.55 17.98L16.8 17.79L16.9 17.5Z M4.36'
                     . ' 20.3L5.36 18.7L5.04 18.5L4.04 20.1Z M6.36 20.3L7.36 18.7L7.04 18.5L6.04 20.1Z M8.36'
                     . ' 20.3L9.36 18.7L9.04 18.5L8.04 20.1Z M10.36 20.3L11.36 18.7L11.04 18.5L10.04 20.1Z"/>',

        /* SCHLIESSMUNDSCHNECKE: a slender spindle, turning left like the
           door snails on the trunks of every German wood, the mouth low on
           the left, the sutures cuts. A second draft of stacked, rounded
           whorls lost to this first one. It stands on its lower tip. */
        'door_snail' => '<path d="M10.7 1.94L10.99 2.63L11.27 3.32L11.55 4.01L11.83 4.7L12.11 5.39L12.39'
                     . ' 6.08L12.67 6.77L12.95 7.46L13.23 8.15L13.51 8.84L13.79 9.53L14.08 10.22L14.36'
                     . ' 10.91L14.64 11.6L14.92 12.29L15.2 12.98L15.48 13.67L15.59 14.4L15.66 15.13L15.73'
                     . ' 15.87L15.8 16.6L15.87 17.34L15.94 18.07L16.01 18.81L16.06 19.34L15.72 19.82L15.12'
                     . ' 20.2L14.36 20.46L13.56 20.57L12.83 20.49L12.28 20.19L12.03 19.65L11.79 18.95L11.56'
                     . ' 18.25L11.33 17.55L11.09 16.85L10.86 16.15L10.62 15.45L10.43 14.75L10.41 14L10.38'
                     . ' 13.26L10.36 12.51L10.33 11.77L10.31 11.02L10.29 10.28L10.26 9.53L10.24 8.79L10.21'
                     . ' 8.04L10.19 7.3L10.16 6.55L10.14 5.81L10.12 5.07L10.09 4.32L10.07 3.58L10.04 2.83L10.02'
                     . ' 2.09Z M10.82 5.62L11.47 4.46L11.15 4.28L10.51 5.44Z M10.86 8.1L12.35 6.76L12.11'
                     . ' 6.49L10.62 7.83Z M10.92 10.55L13.25 9.04L13.05 8.73L10.72 10.25Z M10.98 13L14.15'
                     . ' 11.3L13.98 10.98L10.81 12.68Z M11.06 15.44L15.06 13.57L14.9 13.24L10.91 15.11Z M11.56'
                     . ' 17.79L15.56 15.91L15.4 15.59L11.41 17.46Z M11.89 17.3L11.51 16.81L11.03 16.52L10.54'
                     . ' 16.49L10.1 16.72L9.78 17.17L9.63 17.79L9.68 18.46L9.91 19.1L10.29 19.59L10.77'
                     . ' 19.88L11.26 19.91L11.7 19.68L12.02 19.23L12.17 18.61L12.12 17.94Z"/>',

        /* MUSCHEL: a freshwater mussel from the side — the small beak
           above, the long rear end, two growth lines along the lower margin
           only; drawn round and round, they made a cinnamon roll of it. It
           lies on its lower margin. */
        'mussel'    => '<path d="M8 7.2L8.96 7.01L10.01 6.91L11.14 6.9L12.32 6.97L13.53 7.12L14.74 7.36L15.94'
                     . ' 7.67L17.09 8.04L18.19 8.49L19.2 9L19.8 9.45L20.28 9.95L20.65 10.48L20.91 11.04L21.05'
                     . ' 11.63L21.09 12.21L21.02 12.79L20.84 13.36L20.57 13.9L20.2 14.4L19.31 15.2L18.21'
                     . ' 15.92L16.95 16.55L15.56 17.08L14.07 17.5L12.54 17.8L10.99 17.97L9.45 18L7.98 17.88L6.6'
                     . ' 17.6L5.71 17.24L4.94 16.78L4.3 16.23L3.79 15.61L3.4 14.93L3.13 14.2L2.98 13.45L2.94'
                     . ' 12.69L3.01 11.94L3.2 11.2L3.53 10.46L3.91 9.8L4.34 9.23L4.8 8.73L5.3 8.3L5.82'
                     . ' 7.95L6.36 7.66L6.91 7.44L7.46 7.29L8 7.2Z M9.3 7.5L9.18 7.85L8.85 8.13L8.37 8.28L7.83'
                     . ' 8.28L7.35 8.13L7.02 7.85L6.9 7.5L7.02 7.15L7.35 6.87L7.83 6.72L8.37 6.72L8.85'
                     . ' 6.87L9.18 7.15Z M17.43 10.15L17.54 10.56L17.56 10.95L17.52 11.35L17.4 11.73L17.21'
                     . ' 12.1L16.96 12.44L16.32 13.01L15.53 13.53L14.62 13.99L13.61 14.37L12.53 14.68L11.41'
                     . ' 14.9L10.28 15.02L9.16 15.04L8.09 14.96L7.1 14.76L6.47 14.5L5.93 14.18L5.49 13.79L5.13'
                     . ' 13.36L4.85 12.88L4.67 12.38L4.56 11.84L4.16 11.92L4.28 12.49L4.49 13.05L4.8 13.59L5.2'
                     . ' 14.07L5.7 14.5L6.29 14.86L6.99 15.14L8.04 15.35L9.15 15.44L10.3 15.42L11.47'
                     . ' 15.29L12.62 15.07L13.73 14.75L14.78 14.36L15.73 13.88L16.57 13.33L17.25 12.72L17.55'
                     . ' 12.31L17.77 11.88L17.91 11.43L17.96 10.97L17.93 10.5L17.82 10.05Z M14.41 9.27L14.48'
                     . ' 9.54L14.49 9.8L14.46 10.06L14.39 10.3L14.27 10.54L14.11 10.76L13.68 11.14L13.16'
                     . ' 11.49L12.54 11.79L11.87 12.05L11.14 12.26L10.39 12.4L9.63 12.48L8.88 12.5L8.17'
                     . ' 12.44L7.5 12.31L7.09 12.14L6.74 11.93L6.44 11.68L6.21 11.39L6.03 11.08L5.91 10.75L5.83'
                     . ' 10.39L5.44 10.47L5.52 10.86L5.67 11.25L5.88 11.62L6.16 11.96L6.5 12.25L6.91 12.5L7.4'
                     . ' 12.69L8.11 12.84L8.87 12.9L9.66 12.88L10.45 12.8L11.23 12.64L11.99 12.43L12.7'
                     . ' 12.16L13.35 11.84L13.92 11.46L14.39 11.04L14.61 10.75L14.76 10.45L14.85 10.14L14.89'
                     . ' 9.81L14.87 9.48L14.8 9.17Z"/>',


        /* THE BIRDS (24.09.2026: „ich glaube, wir rutschen ab in
           zu sehr die Detailverliebtheit … bei den Pilzen sind wir ja auch
           nicht bis ins Detail gegangen") — a few groups, no species, and
           nothing that does not live in Germany. He asked for a waterbird,
           a songbird and a bird of prey; asked what else was missing, I
           named owls, woodpeckers and waders, and he took them too. All
           look right like the other animals.

           SINGVOGEL IS THE BIRD SIGN ITSELF: the key 'bird' draws it, so
           every list that wore the old sign — a bird of prey seen from
           below, after the silhouette on window stickers — now shows the
           songbird („wenn, dann sollen sie zum Singvogel werden"), and bird
           lists start with it. Plump, perched on a twig, a short pointed
           beak, the tail slanting down; the eye and the edge of the wing
           are cuts. It sits on its twig (anchor in map.js). */
        'bird'      => '<path d="M2.37 18.25L21.57 17.3L21.63 18.3L2.43 19.35Z"/>'
                     . '<path d="M15.36 10.84L15.54 11.57L15.53 12.35L15.32 13.14L14.92 13.9L14.35 14.61L13.63'
                     . ' 15.23L12.8 15.74L11.89 16.1L10.94 16.32L9.99 16.37L9.09 16.26L8.27 15.99L7.57'
                     . ' 15.57L7.02 15.02L6.64 14.36L6.46 13.63L6.47 12.85L6.68 12.06L7.08 11.3L7.65 10.59L8.37'
                     . ' 9.97L9.2 9.46L10.11 9.1L11.06 8.88L12.01 8.83L12.91 8.94L13.73 9.21L14.43 9.63L14.98'
                     . ' 10.18Z M17.8 8.6L17.71 9.25L17.47 9.85L17.07 10.37L16.55 10.77L15.95 11.01L15.3'
                     . ' 11.1L14.65 11.01L14.05 10.77L13.53 10.37L13.13 9.85L12.89 9.25L12.8 8.6L12.89'
                     . ' 7.95L13.13 7.35L13.53 6.83L14.05 6.43L14.65 6.19L15.3 6.1L15.95 6.19L16.55 6.43L17.07'
                     . ' 6.83L17.47 7.35L17.71 7.95Z M17.5 7.9L20 8.9L17.4 9.6Z M8.4 15.2L3.3 16.9L2.8 15.6L7.8'
                     . ' 13Z M10.97 15.63L10.75 18.42L10.05 18.38L10.23 15.57Z M12.77 15.18L12.95 18.28L12.25'
                     . ' 18.32L12.03 15.22Z M16.31 7.78L16.12 7.64L15.88 7.64L15.69 7.78L15.62 8L15.69'
                     . ' 8.22L15.88 8.36L16.12 8.36L16.31 8.22L16.38 8Z M6.91 11.78L7.48 11.41L8.09 11.15L8.75'
                     . ' 10.97L9.44 10.88L10.14 10.88L10.82 10.97L11.47 11.14L12.07 11.4L12.6 11.73L13.06'
                     . ' 12.15L13.34 11.85L12.86 11.4L12.27 11.02L11.61 10.74L10.9 10.56L10.16 10.47L9.41'
                     . ' 10.47L8.67 10.56L7.95 10.75L7.28 11.04L6.69 11.42Z"/>',

        /* WASSERVOGEL: a duck swimming — a boat-shaped body with the tail
           raised, a round head, the broad bill an element of its own, a
           wave below. It floats on its water (anchor in map.js). */
        'waterbird' => '<path d="M3 11.2L3.79 11.65L4.67 11.98L5.62 12.22L6.62 12.38L7.68 12.49L8.77 12.58L9.88'
                     . ' 12.67L11 12.8L11.86 12.89L12.65 12.95L13.36 12.95L14 12.91L14.56 12.82L15.05'
                     . ' 12.67L15.46 12.47L15.8 12.2L16.37 12.41L16.87 12.75L17.27 13.2L17.57 13.75L17.76'
                     . ' 14.4L17.8 15.12L17.68 15.93L17.4 16.8L16.69 17.35L15.53 17.79L14.05 18.14L12.35'
                     . ' 18.38L10.56 18.51L8.79 18.54L7.17 18.47L5.8 18.3L5.23 18.03L4.72 17.61L4.27 17.07L3.9'
                     . ' 16.44L3.6 15.75L3.38 15.02L3.25 14.3L3.2 13.6L3.15 13.23L3.1 12.89L3.04 12.56L2.99'
                     . ' 12.25L2.95 11.96L2.93 11.69L2.95 11.43L3 11.2Z M14.32 12.8L14.82 10.17L15.25'
                     . ' 9.08L17.35 9.72L17.18 10.63L16.88 13.2Z M18.95 8.4L18.88 8.99L18.67 9.54L18.33'
                     . ' 10.02L17.89 10.42L17.37 10.69L16.8 10.83L16.2 10.83L15.63 10.69L15.11 10.42L14.67'
                     . ' 10.02L14.33 9.54L14.12 8.99L14.05 8.4L14.12 7.81L14.33 7.26L14.67 6.78L15.11'
                     . ' 6.38L15.63 6.11L16.2 5.97L16.8 5.97L17.37 6.11L17.89 6.38L18.33 6.78L18.67 7.26L18.88'
                     . ' 7.81Z M6.46 14.4L7.14 14.19L7.86 14.03L8.61 13.92L9.39 13.86L10.17 13.86L10.94'
                     . ' 13.92L11.69 14.04L12.41 14.22L13.08 14.47L13.7 14.79L13.9 14.41L13.25 14.08L12.54'
                     . ' 13.82L11.78 13.63L10.99 13.5L10.18 13.44L9.37 13.44L8.57 13.5L7.78 13.61L7.03'
                     . ' 13.78L6.34 14Z M17.52 7.56L17.32 7.42L17.08 7.42L16.88 7.56L16.8 7.8L16.88 8.04L17.08'
                     . ' 8.18L17.32 8.18L17.52 8.04L17.6 7.8Z"/>'
                     . '<path d="M21.98 9.36L21.83 9.61L21.47 9.8L20.95 9.9L20.32 9.91L19.67 9.81L19.07'
                     . ' 9.64L18.59 9.39L18.29 9.12L18.22 8.84L18.37 8.59L18.73 8.4L19.25 8.3L19.88 8.29L20.53'
                     . ' 8.39L21.13 8.56L21.61 8.81L21.91 9.08Z"/>'
                     . '<path d="M2.69 20.43L3.35 20.86L3.82 20.83L4.52 20.35L5.49 19.84L6.61 19.99L7.48'
                     . ' 20.57L8.07 20.89L8.56 20.75L9.33 20.2L10.38 19.8L11.45 20.13L12.25 20.7L12.78'
                     . ' 20.9L13.33 20.64L14.16 20.06L15.27 19.82L16.28 20.28L17.01 20.79L17.49 20.88L18.12'
                     . ' 20.5L19.02 19.93L20.15 19.88L21.09 20.43L21.89 20.92L21.31 21.86L20.51 21.37L19.85'
                     . ' 20.94L19.38 20.97L18.68 21.45L17.71 21.96L16.59 21.81L15.72 21.23L15.13 20.91L14.64'
                     . ' 21.05L13.87 21.6L12.82 22L11.75 21.67L10.95 21.1L10.42 20.9L9.87 21.16L9.04 21.74L7.93'
                     . ' 21.98L6.92 21.52L6.19 21.01L5.71 20.92L5.08 21.3L4.18 21.87L3.05 21.92L2.11 21.37Z'
                     . ' M2.95 20.9L2.84 21.22L2.57 21.42L2.23 21.42L1.96 21.22L1.85 20.9L1.96 20.58L2.23'
                     . ' 20.38L2.57 20.38L2.84 20.58Z M22.15 21.39L22.04 21.71L21.77 21.91L21.43 21.91L21.16'
                     . ' 21.71L21.05 21.39L21.16 21.07L21.43 20.87L21.77 20.87L22.04 21.07Z"/>',

        /* GREIFVOGEL: a buzzard soaring, seen from below — broad wings with
           three spread fingers at each tip, a fanned tail, a small head.
           The old bird sign was this idea with narrow wings. It flies, so
           it stays centred. */
        'bird_of_prey' => '<path d="M13.2 7.8L17 7.2L20.2 7.6L21.4 8L22.9 7.9L23.4 8.5L21.9 9L23.3 9.6L23.5'
                     . ' 10.3L21.8 10.4L22.8 11.2L22.7 11.9L21.2 11.6L18.8 12.9L15.8 12.6L13.2 12.4Z M10.8'
                     . ' 12.4L8.2 12.6L5.2 12.9L2.8 11.6L1.3 11.9L1.2 11.2L2.2 10.4L0.5 10.3L0.7 9.6L2.1 9L0.6'
                     . ' 8.5L1.1 7.9L2.6 8L3.8 7.6L7 7.2L10.8 7.8Z M12 6.2L12.45 6.38L12.81 6.78L13.09'
                     . ' 7.35L13.29 8.1L13.41 9L13.45 10.03L13.41 11.17L13.3 12.4L13.11 12.92L12.81 13.3L12.42'
                     . ' 13.53L12 13.6L11.58 13.53L11.19 13.3L10.89 12.93L10.7 12.4L10.59 11.17L10.55'
                     . ' 10.03L10.59 9L10.71 8.1L10.91 7.35L11.19 6.78L11.55 6.38L12 6.2Z M13.55 5.4L13.46'
                     . ' 5.93L13.19 6.4L12.78 6.74L12.27 6.93L11.73 6.93L11.22 6.74L10.81 6.4L10.54 5.93L10.45'
                     . ' 5.4L10.54 4.87L10.81 4.4L11.22 4.06L11.73 3.87L12.27 3.87L12.77 4.06L13.19 4.4L13.46'
                     . ' 4.87Z M10.6 13L10.97 12.97L11.32 12.94L11.66 12.93L12 12.93L12.34 12.93L12.68'
                     . ' 12.94L13.03 12.97L13.4 13L13.57 13.77L13.78 14.55L14.01 15.34L14.23 16.12L14.41'
                     . ' 16.88L14.55 17.58L14.62 18.23L14.6 18.8L14.08 19.06L13.45 19.25L12.74 19.36L12'
                     . ' 19.4L11.26 19.36L10.55 19.25L9.92 19.06L9.4 18.8L9.38 18.23L9.45 17.58L9.59 16.88L9.78'
                     . ' 16.12L9.99 15.34L10.22 14.55L10.43 13.77L10.6 13Z"/>',

        /* EULE from the front: one egg of head and body, ear tufts, two
           large eye rings (holes round the pupils), a small beak, folded
           wings, on a branch — a silhouette no other bird has. It sits on
           its branch (anchor in map.js). */
        'owl'       => '<path d="M3.19 20L20.79 19.65L20.81 20.75L3.21 21.2Z"/>'
                     . '<path d="M12 4.4L12.94 4.47L13.82 4.66L14.61 4.99L15.3 5.45L15.88 6.04L16.33 6.76L16.64'
                     . ' 7.62L16.8 8.6L16.99 9.92L17.09 11.29L17.1 12.68L17 14.05L16.79 15.37L16.46 16.59L16'
                     . ' 17.68L15.4 18.6L14.76 18.99L13.94 19.28L13 19.44L12 19.5L11 19.44L10.06 19.28L9.24'
                     . ' 18.99L8.6 18.6L8 17.68L7.54 16.59L7.21 15.37L7 14.05L6.9 12.68L6.91 11.29L7.01'
                     . ' 9.92L7.2 8.6L7.36 7.62L7.67 6.76L8.12 6.04L8.7 5.45L9.39 4.99L10.18 4.66L11.06 4.47L12'
                     . ' 4.4Z M7.6 6.6L7.6 2.6L10 5Z M14 5L16.4 2.6L16.4 6.6Z M10.8 8.8L10.7 9.23L10.42'
                     . ' 9.58L10.02 9.77L9.58 9.77L9.18 9.58L8.9 9.23L8.8 8.8L8.9 8.37L9.18 8.02L9.58'
                     . ' 7.83L10.02 7.83L10.42 8.02L10.7 8.37Z M15.2 8.8L15.1 9.23L14.82 9.58L14.42 9.77L13.98'
                     . ' 9.77L13.58 9.58L13.3 9.23L13.2 8.8L13.3 8.37L13.58 8.02L13.98 7.83L14.42 7.83L14.82'
                     . ' 8.02L15.1 8.37Z M11.15 19.8L11.01 20.24L10.63 20.51L10.17 20.51L9.79 20.24L9.65'
                     . ' 19.8L9.79 19.36L10.17 19.09L10.63 19.09L11.01 19.36Z M14.35 19.8L14.21 20.24L13.83'
                     . ' 20.51L13.37 20.51L12.99 20.24L12.85 19.8L12.99 19.36L13.37 19.09L13.83 19.09L14.21'
                     . ' 19.36Z M11.75 8.17L11.46 7.6L11 7.14L10.43 6.85L9.8 6.75L9.17 6.85L8.6 7.14L8.14'
                     . ' 7.6L7.85 8.17L7.75 8.8L7.85 9.43L8.14 10L8.6 10.46L9.17 10.75L9.8 10.85L10.43 10.75L11'
                     . ' 10.46L11.46 10L11.75 9.43L11.85 8.8Z M16.15 8.17L15.86 7.6L15.4 7.14L14.83 6.85L14.2'
                     . ' 6.75L13.57 6.85L13 7.14L12.54 7.6L12.25 8.17L12.15 8.8L12.25 9.43L12.54 10L13'
                     . ' 10.46L13.57 10.75L14.2 10.85L14.83 10.75L15.4 10.46L15.86 10L16.15 9.43L16.25 8.8Z'
                     . ' M7.59 11.38L7.51 12.27L7.49 13.13L7.54 13.95L7.66 14.73L7.85 15.46L8.1 16.15L8.4'
                     . ' 16.78L8.77 17.36L9.19 17.89L9.65 18.35L9.95 18.05L9.5 17.61L9.11 17.12L8.77 16.58L8.48'
                     . ' 15.98L8.25 15.34L8.08 14.64L7.96 13.9L7.91 13.12L7.93 12.29L8.01 11.42Z M15.99'
                     . ' 11.42L16.07 12.29L16.09 13.12L16.04 13.9L15.92 14.64L15.75 15.34L15.52 15.98L15.23'
                     . ' 16.58L14.89 17.12L14.5 17.61L14.05 18.05L14.35 18.35L14.81 17.89L15.23 17.36L15.6'
                     . ' 16.78L15.9 16.15L16.15 15.46L16.34 14.73L16.46 13.95L16.51 13.13L16.49 12.27L16.41'
                     . ' 11.38Z M12 12.4L12.7 10.6L11.3 10.6Z"/>',

        /* SPECHT clinging to a trunk: the chisel bill at the wood, the stiff
           tail propped against the bark, three notches in the bark. It
           stays centred — the trunk runs the full height and has no
           ground to stand on. */
        'woodpecker' => '<path d="M20.3 0.4L20.3 23.6L16.9 23.6L16.9 0.4Z M17.85 2.51L19.05 4.11L19.35'
                     . ' 3.89L18.15 2.29Z M17.85 9.71L19.05 11.31L19.35 11.09L18.15 9.49Z M17.85 17.71L19.05'
                     . ' 19.31L19.35 19.09L18.15 17.49Z"/>'
                     . '<path d="M15.49 13.37L15.13 14.38L14.65 15.3L14.08 16.08L13.44 16.69L12.78 17.09L12.11'
                     . ' 17.27L11.48 17.21L10.91 16.93L10.44 16.42L10.09 15.73L9.87 14.87L9.8 13.91L9.88'
                     . ' 12.87L10.11 11.83L10.47 10.82L10.95 9.9L11.52 9.12L12.16 8.51L12.82 8.11L13.49'
                     . ' 7.93L14.12 7.99L14.69 8.27L15.16 8.78L15.51 9.47L15.73 10.33L15.8 11.29L15.72 12.33Z'
                     . ' M16.5 6.6L16.41 7.25L16.13 7.84L15.71 8.34L15.16 8.69L14.53 8.88L13.87 8.88L13.24'
                     . ' 8.69L12.69 8.34L12.27 7.84L11.99 7.25L11.9 6.6L11.99 5.95L12.27 5.36L12.69 4.86L13.24'
                     . ' 4.51L13.87 4.32L14.53 4.32L15.16 4.51L15.71 4.86L16.13 5.36L16.41 5.95Z M16.2 5.9L18.4'
                     . ' 6.5L16.2 7.3Z M11.2 16.2L13.6 15.6L16.4 20.8L15.6 21.4Z M14.29 14.21L16.51 13.66L16.69'
                     . ' 14.34L14.51 14.99Z M15.21 5.78L15.02 5.64L14.78 5.64L14.59 5.78L14.52 6L14.59'
                     . ' 6.22L14.78 6.36L15.02 6.36L15.21 6.22L15.28 6Z M10.39 9.98L10.3 10.76L10.27 11.53L10.3'
                     . ' 12.28L10.39 13.01L10.55 13.71L10.76 14.37L11.04 14.99L11.39 15.56L11.79 16.09L12.25'
                     . ' 16.55L12.55 16.25L12.11 15.81L11.73 15.33L11.42 14.79L11.16 14.22L10.95 13.59L10.81'
                     . ' 12.94L10.72 12.25L10.69 11.53L10.72 10.79L10.81 10.02Z"/>',

        /* STELZVOGEL: a stork for all the waders — herons and cranes too:
           long thin legs, a long neck, a long pointed bill (an element of
           its own). It stands on its feet (anchor in map.js). */
        'wader'     => '<path d="M15.15 9.93L15.12 10.48L14.89 11.04L14.46 11.57L13.85 12.07L13.09 12.5L12.21'
                     . ' 12.84L11.26 13.09L10.26 13.23L9.28 13.26L8.34 13.16L7.49 12.96L6.77 12.65L6.2'
                     . ' 12.26L5.83 11.79L5.65 11.27L5.68 10.72L5.91 10.16L6.34 9.63L6.95 9.13L7.71 8.7L8.59'
                     . ' 8.36L9.54 8.11L10.54 7.97L11.52 7.94L12.46 8.04L13.31 8.24L14.03 8.55L14.6 8.94L14.97'
                     . ' 9.41Z M13.09 8.9L13.45 8.58L13.71 8.27L13.96 7.89L14.17 7.46L14.36 6.98L14.53'
                     . ' 6.47L14.68 5.92L14.83 5.37L14.97 4.81L15.11 4.26L16.49 4.54L16.4 5.1L16.32 5.67L16.23'
                     . ' 6.26L16.12 6.86L15.99 7.47L15.82 8.08L15.59 8.69L15.3 9.28L14.92 9.84L14.51 10.3Z'
                     . ' M18.13 4.07L17.93 4.5L17.53 4.83L16.99 4.99L16.39 4.98L15.82 4.79L15.37 4.45L15.11'
                     . ' 4L15.07 3.53L15.27 3.1L15.67 2.77L16.21 2.61L16.81 2.62L17.38 2.81L17.83 3.15L18.09'
                     . ' 3.6Z M6.2 12.2L3.4 12.4L5.8 10.8Z M10.04 12.32L10.82 16.79L10.2 21.55L9.4 21.45L9.98'
                     . ' 16.81L9.16 12.48Z M12.24 12.3L13.22 16.76L13 21.52L12.2 21.48L12.38 16.84L11.36 12.5Z'
                     . ' M6.48 9.39L7.1 9.13L7.76 8.93L8.45 8.78L9.17 8.7L9.9 8.68L10.62 8.73L11.34 8.84L12.03'
                     . ' 9.02L12.69 9.27L13.3 9.59L13.5 9.21L12.86 8.88L12.16 8.62L11.43 8.43L10.67 8.31L9.9'
                     . ' 8.27L9.14 8.28L8.38 8.37L7.65 8.52L6.96 8.73L6.32 9.01Z M17.16 3.31L17 3.2L16.8'
                     . ' 3.2L16.64 3.31L16.58 3.5L16.64 3.69L16.8 3.8L17 3.8L17.16 3.69L17.22 3.5Z"/>'
                     . '<path d="M18.05 3.5L20.37 5.14L22.88 6.97L22.72 7.23L20.03 5.66L17.55 4.3Z"/>',






        /* REPTILES AND AMPHIBIANS (24.09.2026: „was ist denn
           mit den Lurchen gemeint? … Reptilien und Lurche ist doch
           eigentlich was anderes"). They are two classes — 242 reptiles and
           119 amphibians in the German list, 306 and 131 in Fauna Europaea
           — and since the same day two groups in species_packages.php:
           'reptiles' starts with the lizard, 'amphibians' with the frog.
           Three signs instead of one ambiguous „Lurch". The old sign, a
           four-legged animal with a tail seen from
           above, read as a lizard as much as a newt. Its key 'amphibian'
           is retired to an alias of the lizard that replaced it (data.php,
           migration 0124): a key 'amphibian' drawing a reptile would lie.

           FROSCH from above, legs splayed — the sign everybody reads as an
           amphibian: a body with two eye bumps, the front legs bent
           forward, the hind legs folded in a Z, toes on every foot. A
           frog sitting side-on lost to it. Amphibian lists start with it;
           seen from above it stays centred. */
        'frog'      => '<path d="M15.9 12.8L15.81 13.8L15.56 14.75L15.16 15.62L14.61 16.37L13.95 16.96L13.21'
                     . ' 17.37L12.41 17.57L11.59 17.57L10.79 17.37L10.05 16.96L9.39 16.37L8.84 15.62L8.44'
                     . ' 14.75L8.19 13.8L8.1 12.8L8.19 11.8L8.44 10.85L8.84 9.98L9.39 9.23L10.05 8.64L10.79'
                     . ' 8.23L11.59 8.03L12.41 8.03L13.21 8.23L13.95 8.64L14.61 9.23L15.16 9.98L15.56'
                     . ' 10.85L15.81 11.8Z M14.8 8.6L14.66 9.28L14.27 9.89L13.65 10.38L12.87 10.69L12'
                     . ' 10.8L11.13 10.69L10.35 10.38L9.73 9.89L9.34 9.28L9.2 8.6L9.34 7.92L9.73 7.31L10.35'
                     . ' 6.82L11.13 6.51L12 6.4L12.87 6.51L13.65 6.82L14.27 7.31L14.66 7.92Z M11.5 8.2L11.38'
                     . ' 8.81L11.03 9.33L10.51 9.68L9.9 9.8L9.29 9.68L8.77 9.33L8.42 8.81L8.3 8.2L8.42'
                     . ' 7.59L8.77 7.07L9.29 6.72L9.9 6.6L10.51 6.72L11.03 7.07L11.38 7.59Z M15.7 8.2L15.58'
                     . ' 8.81L15.23 9.33L14.71 9.68L14.1 9.8L13.49 9.68L12.97 9.33L12.62 8.81L12.5 8.2L12.62'
                     . ' 7.59L12.97 7.07L13.49 6.72L14.1 6.6L14.71 6.72L15.23 7.07L15.58 7.59Z M9.31 11.77L6.18'
                     . ' 13.13L5.12 9.94L6.08 9.66L6.62 12.07L8.69 10.63Z M5.45 10.06L4.35 9.36L4.59 8.94L5.75'
                     . ' 9.54Z M5.32 9.9L4.93 8.66L5.38 8.5L5.88 9.7Z M5.32 9.7L5.82 8.5L6.27 8.66L5.88 9.9Z'
                     . ' M9.29 17.09L5.03 16.04L7.63 19.69L3.83 21.84L3.37 20.96L6.37 19.51L3.77 15.16L9.51'
                     . ' 15.31Z M3.71 21.71L2.28 22.16L2.1 21.67L3.49 21.09Z M3.49 21.71L2.1 21.13L2.28'
                     . ' 20.64L3.71 21.09Z M3.32 21.56L2.62 20.23L3.08 19.97L3.88 21.24Z M15.31 10.63L17.38'
                     . ' 12.07L17.92 9.66L18.88 9.94L17.82 13.13L14.69 11.77Z M18.25 9.54L19.41 8.94L19.65'
                     . ' 9.36L18.55 10.06Z M18.12 9.7L18.62 8.5L19.07 8.66L18.68 9.9Z M18.12 9.9L17.73'
                     . ' 8.66L18.18 8.5L18.68 9.7Z M14.49 15.31L20.23 15.16L17.63 19.51L20.63 20.96L20.17'
                     . ' 21.84L16.37 19.69L18.97 16.04L14.71 17.09Z M20.51 21.09L21.9 21.67L21.72 22.16L20.29'
                     . ' 21.71Z M20.29 21.09L21.72 20.64L21.9 21.13L20.51 21.71Z M20.12 21.24L20.92 19.97L21.38'
                     . ' 20.23L20.68 21.56Z"/>',

        /* EIDECHSE from above: an S-curved body, a long tail running out,
           four legs splayed with elbows and toes, the eyes holes. It
           replaced the old symmetric animal (19.08.2026) in every list that
           wore it, and reptile lists start with it. Centred. */
        'lizard'    => '<path d="M15.29 1.89L15.44 2.19L15.58 2.57L15.7 3.02L15.63 3.46L15.44 3.9L15.25'
                     . ' 4.37L15.05 4.84L14.85 5.32L14.72 5.83L14.57 6.36L14.4 6.89L14.24 7.42L14.09 7.93L13.96'
                     . ' 8.43L13.86 8.9L13.8 9.36L13.75 9.82L13.64 10.3L13.54 10.8L13.46 11.29L13.4 11.77L13.36'
                     . ' 12.24L13.34 12.68L13.33 13.09L13.35 13.46L13.39 13.81L13.4 14.17L13.44 14.53L13.5'
                     . ' 14.9L13.58 15.27L13.67 15.65L13.78 15.99L13.91 16.28L14.09 16.57L14.33 16.88L14.61'
                     . ' 17.22L14.88 17.59L15.14 17.99L15.33 18.44L15.42 18.94L15.38 19.41L15.27 19.86L15.1'
                     . ' 20.3L14.88 20.73L14.61 21.13L14.31 21.5L13.98 21.83L13.6 22.1L13.16 22.3L12.65'
                     . ' 22.44L12.11 22.54L11.55 22.6L11.01 22.64L10.52 22.67L10.11 22.68L9.82 22.7L9.78'
                     . ' 22.5L10.07 22.42L10.48 22.34L10.96 22.25L11.48 22.15L12 22.02L12.49 21.88L12.9'
                     . ' 21.7L13.2 21.5L13.43 21.26L13.65 20.97L13.84 20.64L14 20.29L14.12 19.94L14.19'
                     . ' 19.6L14.21 19.3L14.18 19.06L14.11 18.9L13.98 18.71L13.77 18.49L13.49 18.23L13.18'
                     . ' 17.94L12.84 17.61L12.51 17.23L12.22 16.81L11.99 16.42L11.75 16.06L11.51 15.68L11.28'
                     . ' 15.27L11.07 14.83L10.88 14.36L10.75 13.85L10.67 13.31L10.61 12.76L10.59 12.19L10.59'
                     . ' 11.61L10.62 11.01L10.66 10.41L10.73 9.81L10.82 9.22L11 8.64L11.23 8.07L11.5 7.5L11.79'
                     . ' 6.97L12.09 6.46L12.38 5.97L12.66 5.52L12.92 5.09L13.15 4.68L13.27 4.24L13.38'
                     . ' 3.77L13.48 3.3L13.57 2.84L13.75 2.44L14.09 2.14L14.41 1.89L14.71 1.71Z M11.52 7.94L8.5'
                     . ' 7.39L7.79 4.91L8.61 4.69L9.1 6.61L11.68 6.86Z M8.06 5.04L7.05 4.39L7.27 4.01L8.34'
                     . ' 4.56Z M7.94 4.89L7.58 3.75L8 3.6L8.46 4.71Z M7.94 4.71L8.4 3.6L8.82 3.75L8.46 4.89Z'
                     . ' M14.33 7.67L16.35 8.38L16.79 6.3L17.61 6.5L16.85 9.22L14.07 8.73Z M16.94 6.49L16.58'
                     . ' 5.35L17 5.2L17.46 6.31Z M16.94 6.31L17.4 5.2L17.82 5.35L17.46 6.49Z M17.06 6.16L18.13'
                     . ' 5.61L18.35 5.99L17.34 6.64Z M11.12 13.94L8.57 14.31L8.42 17.03L7.58 16.97L7.83'
                     . ' 13.69L10.88 12.86Z M8.14 17.24L7.07 17.79L6.85 17.41L7.86 16.76Z M8.26 17.09L7.8'
                     . ' 18.2L7.38 18.05L7.74 16.91Z M8.26 16.91L8.62 18.05L8.2 18.2L7.74 17.09Z M13.19'
                     . ' 13.69L15.92 12.73L17.73 14.93L17.07 15.47L15.68 13.67L13.61 14.71Z M17.66 15.29L17.2'
                     . ' 16.4L16.78 16.25L17.14 15.11Z M17.66 15.11L18.02 16.25L17.6 16.4L17.14 15.29Z M17.54'
                     . ' 14.96L18.55 15.61L18.33 15.99L17.26 15.44Z M14.37 3.33L14.1 3.22L13.83 3.33L13.72'
                     . ' 3.6L13.83 3.87L14.1 3.98L14.37 3.87L14.48 3.6Z M15.67 3.63L15.4 3.52L15.13 3.63L15.02'
                     . ' 3.9L15.13 4.17L15.4 4.28L15.67 4.17L15.78 3.9Z"/>',

        /* SCHLANGE from above: S-windings from the pointed tail to a broad
           head, the adder's zigzag down the back as a cut the white edge
           fills, the eye a hole. Centred. */
        'snake'     => '<path d="M3.02 20.85L3.39 20.83L3.89 20.85L4.47 20.88L5.11 20.92L5.76 20.93L6.41'
                     . ' 20.91L6.99 20.85L7.49 20.73L7.97 20.56L8.49 20.34L9 20.1L9.48 19.82L9.9 19.53L10.25'
                     . ' 19.23L10.52 18.97L10.68 18.76L10.8 18.52L10.9 18.2L10.97 17.81L11 17.38L10.98'
                     . ' 16.95L10.92 16.54L10.83 16.2L10.74 15.99L10.65 15.88L10.44 15.73L10.07 15.53L9.59'
                     . ' 15.3L9.04 15.04L8.46 14.73L7.86 14.32L7.35 13.77L7 13.2L6.73 12.64L6.52 12.06L6.37'
                     . ' 11.45L6.3 10.83L6.3 10.19L6.38 9.55L6.57 8.92L6.87 8.34L7.25 7.81L7.68 7.33L8.16'
                     . ' 6.89L8.68 6.49L9.23 6.14L9.81 5.84L10.44 5.6L11.14 5.47L11.83 5.46L12.48 5.52L13.08'
                     . ' 5.62L13.63 5.72L14.12 5.8L14.52 5.84L14.83 5.83L15.13 5.76L15.47 5.66L15.82 5.51L16.18'
                     . ' 5.34L16.56 5.15L16.96 4.96L17.37 4.77L17.79 4.61L18.19 4.46L18.52 4.2L18.85 3.96L19.16'
                     . ' 3.73L19.49 3.65L19.8 3.67L20.06 3.7L20.26 3.75L20.94 5.85L20.78 6.01L20.58 6.19L20.34'
                     . ' 6.38L20.04 6.49L19.68 6.48L19.3 6.47L18.92 6.48L18.61 6.59L18.32 6.74L18 6.93L17.65'
                     . ' 7.15L17.25 7.39L16.8 7.62L16.31 7.84L15.77 8.03L15.17 8.17L14.51 8.23L13.87 8.19L13.26'
                     . ' 8.12L12.69 8.03L12.18 7.96L11.74 7.93L11.41 7.94L11.16 8L10.88 8.13L10.53 8.32L10.17'
                     . ' 8.56L9.84 8.84L9.54 9.13L9.29 9.42L9.12 9.68L9.03 9.88L8.98 10.07L8.96 10.32L8.98'
                     . ' 10.63L9.03 10.97L9.1 11.33L9.2 11.68L9.34 12L9.45 12.23L9.57 12.38L9.82 12.56L10.21'
                     . ' 12.79L10.71 13.05L11.26 13.34L11.84 13.7L12.41 14.18L12.86 14.81L13.12 15.48L13.26'
                     . ' 16.13L13.33 16.8L13.33 17.47L13.25 18.15L13.1 18.81L12.86 19.45L12.52 20.04L12.06'
                     . ' 20.56L11.51 20.97L10.91 21.28L10.27 21.53L9.63 21.73L8.97 21.89L8.34 22L7.71'
                     . ' 22.07L7.05 22.07L6.36 22L5.67 21.89L4.99 21.73L4.36 21.57L3.79 21.41L3.33 21.26L2.98'
                     . ' 21.15Z M5.06 22.05L7.01 21.11L8.96 21.83L10.23 20.1L12.21 19.83L11.76 17.93L12.83'
                     . ' 16.2L11.28 15.28L10.5 13.51L8.49 13.61L8.67 11.88L7.29 10.75L8.5 9.67L8.36 7.98L10.26'
                     . ' 7.88L11.24 6.35L12.78 7.57L14.52 6.68L16.15 7.45L17.02 5.75L16.66 5.58L16.02'
                     . ' 7.07L14.51 6.28L12.82 7.17L11.19 5.96L10.06 7.53L8.07 7.71L8.13 9.53L6.89 10.78L8.3'
                     . ' 12.04L8.22 13.91L10.29 13.85L10.98 15.55L12.44 16.27L11.37 17.86L11.89 19.6L10.03'
                     . ' 19.75L8.84 21.45L6.98 20.71L4.89 21.69Z M20.14 4.15L19.93 4L19.67 4L19.46 4.15L19.38'
                     . ' 4.4L19.46 4.65L19.67 4.8L19.93 4.8L20.14 4.65L20.22 4.4Z"/>',





        /* THE FISH (24.09.2026), by way of life like the birds: a
           trout „für klare sauerstoffreiche Fließgewässer", a peaceful fish
           and a predatory one. All from the side, looking right like the
           other animals; they swim, so they stay centred.

           FRIEDFISCH IS THE FISH SIGN ITSELF — a carp, and it replaced the
           old plain fish under the key 'fish' („der aktuelle Fisch wird der
           Friedfisch, der Karpfen"), so every fish list shows it and fish
           lists start with it: a high back, the long dorsal fin, a forked
           tail, barbels at the small mouth. Scale arcs were tried and made
           a smiling face of it. */
        'fish'      => '<path d="M6.2 11L6.86 10.14L7.64 9.35L8.51 8.66L9.45 8.05L10.45 7.55L11.48 7.16L12.54'
                     . ' 6.88L13.59 6.72L14.61 6.69L15.6 6.8L16.48 7.02L17.3 7.32L18.07 7.68L18.78 8.1L19.43'
                     . ' 8.58L20.01 9.09L20.52 9.64L20.96 10.21L21.32 10.8L21.6 11.4L21.61 11.95L21.5'
                     . ' 12.52L21.29 13.08L20.98 13.65L20.55 14.2L20.02 14.73L19.37 15.22L18.62 15.67L17.77'
                     . ' 16.07L16.8 16.4L15.59 16.61L14.37 16.66L13.16 16.57L11.97 16.35L10.83 16.02L9.73'
                     . ' 15.61L8.7 15.12L7.76 14.58L6.92 14L6.2 13.4L6.15 13.16L6.1 12.92L6.07 12.68L6.06'
                     . ' 12.44L6.05 12.2L6.06 11.96L6.07 11.72L6.1 11.48L6.15 11.24Z M8.6 8.6L9.4 6.8L13.2'
                     . ' 4.6L14.6 4.2L15.4 6.9Z M6.8 13.6L2.6 17.4L1.8 16.6L3.8 12.2L1.8 7.8L2.6 7L6.8 10.8Z'
                     . ' M13.6 17L11 19L12.4 16.8Z M10.4 15.8L8 17.6L9.2 15.4Z M21.26 13.1L21.91 14.92L21.49'
                     . ' 15.08L20.74 13.3Z M20.47 13.58L20.52 15.39L20.08 15.41L19.93 13.62Z M19.54 10.09L19.31'
                     . ' 9.86L19 9.78L18.69 9.86L18.46 10.09L18.38 10.4L18.46 10.71L18.69 10.94L19 11.02L19.31'
                     . ' 10.94L19.54 10.71L19.62 10.4Z M17.02 8.54L16.75 9.32L16.56 10.16L16.45 11.03L16.41'
                     . ' 11.93L16.45 12.83L16.56 13.72L16.75 14.61L17.02 15.46L17.38 15.34L17.12 14.51L16.94'
                     . ' 13.66L16.83 12.79L16.79 11.93L16.83 11.07L16.94 10.23L17.12 9.43L17.38 8.66Z M21.53'
                     . ' 12.42L20.53 12.82L20.67 13.18L21.67 12.78Z"/>',

        /* FORELLE: slender and spotted, with the small adipose fin between
           dorsal fin and tail that marks the salmonids — the fish of clear,
           cold streams rich in oxygen. The spots are holes. */
        'trout'     => '<path d="M6.2 11.2L6.97 10.73L7.84 10.3L8.78 9.9L9.79 9.55L10.85 9.25L11.95 9.01L13.06'
                     . ' 8.84L14.19 8.74L15.3 8.73L16.4 8.8L17.28 8.95L18.1 9.15L18.86 9.4L19.56 9.7L20.2'
                     . ' 10.02L20.76 10.38L21.25 10.77L21.65 11.17L21.97 11.58L22.2 12L22.03 12.42L21.77'
                     . ' 12.83L21.42 13.23L20.98 13.62L20.45 13.98L19.84 14.3L19.15 14.6L18.37 14.85L17.52'
                     . ' 15.05L16.6 15.2L15.45 15.28L14.29 15.28L13.13 15.21L11.99 15.07L10.88 14.88L9.8'
                     . ' 14.62L8.79 14.32L7.84 13.98L6.97 13.61L6.2 13.2L6.15 13.01L6.1 12.82L6.07 12.62L6.06'
                     . ' 12.41L6.05 12.2L6.06 11.99L6.07 11.78L6.1 11.58L6.15 11.39Z M6.8 13.4L2.6 16.4L1.8'
                     . ' 15.6L3.3 12.2L1.8 8.8L2.6 8L6.8 11Z M11 9.3L12.4 6.4L14.8 8.8Z M9.4 9.9L9.3 10.23L9.02'
                     . ' 10.49L8.62 10.63L8.18 10.63L7.78 10.49L7.5 10.23L7.4 9.9L7.5 9.57L7.78 9.31L8.18'
                     . ' 9.17L8.62 9.17L9.02 9.31L9.3 9.57Z M13 15.3L10.6 17.2L11.8 15.1Z M10.2 14.6L7.8 16.2L9'
                     . ' 14.3Z M17.8 14.2L15 16L17 13.8Z M20.22 10.9L20 10.68L19.7 10.6L19.4 10.68L19.18'
                     . ' 10.9L19.1 11.2L19.18 11.5L19.4 11.72L19.7 11.8L20 11.72L20.22 11.5L20.3 11.2Z M17.63'
                     . ' 9.52L17.36 10.13L17.16 10.75L17.04 11.36L16.99 11.97L17 12.57L17.08 13.15L17.22'
                     . ' 13.72L17.42 14.27L17.78 14.13L17.59 13.61L17.45 13.08L17.38 12.54L17.36 11.98L17.41'
                     . ' 11.42L17.53 10.84L17.71 10.27L17.97 9.68Z M21.97 12.31L20.57 12.51L20.63 12.89L22.03'
                     . ' 12.69Z M10.74 10.75L10.53 10.6L10.27 10.6L10.06 10.75L9.98 11L10.06 11.25L10.27'
                     . ' 11.4L10.53 11.4L10.74 11.25L10.82 11Z M12.74 9.95L12.53 9.8L12.27 9.8L12.06 9.95L11.98'
                     . ' 10.2L12.06 10.45L12.27 10.6L12.53 10.6L12.74 10.45L12.82 10.2Z M14.74 10.95L14.53'
                     . ' 10.8L14.27 10.8L14.06 10.95L13.98 11.2L14.06 11.45L14.27 11.6L14.53 11.6L14.74'
                     . ' 11.45L14.82 11.2Z M12.54 12.65L12.33 12.5L12.07 12.5L11.86 12.65L11.78 12.9L11.86'
                     . ' 13.15L12.07 13.3L12.33 13.3L12.54 13.15L12.62 12.9Z M9.34 12.05L9.13 11.9L8.87'
                     . ' 11.9L8.66 12.05L8.58 12.3L8.66 12.55L8.87 12.7L9.13 12.7L9.34 12.55L9.42 12.3Z M15.74'
                     . ' 12.95L15.53 12.8L15.27 12.8L15.06 12.95L14.98 13.2L15.06 13.45L15.27 13.6L15.53'
                     . ' 13.6L15.74 13.45L15.82 13.2Z"/>',

        /* RAUBFISCH — a perch, chosen over a pike: a humped back, the first
           dorsal fin spiny, the second soft, dark bars across (cuts the
           white edge fills). */
        'predatory_fish' => '<path d="M6.2 11.4L6.91 10.6L7.71 9.89L8.59 9.27L9.52 8.74L10.5 8.3L11.5 7.96L12.51'
                     . ' 7.72L13.5 7.57L14.47 7.53L15.4 7.6L16.23 7.76L17.02 8L17.78 8.3L18.49 8.66L19.15'
                     . ' 9.07L19.75 9.53L20.28 10.02L20.74 10.53L21.11 11.06L21.4 11.6L21.41 12.08L21.31'
                     . ' 12.57L21.1 13.06L20.79 13.53L20.38 13.97L19.86 14.39L19.24 14.77L18.53 15.11L17.71'
                     . ' 15.39L16.8 15.6L15.6 15.73L14.39 15.75L13.2 15.69L12.03 15.53L10.9 15.3L9.82 15L8.79'
                     . ' 14.63L7.84 14.2L6.97 13.72L6.2 13.2L6.15 13.02L6.1 12.84L6.07 12.66L6.06 12.48L6.05'
                     . ' 12.3L6.06 12.12L6.07 11.94L6.1 11.76L6.15 11.58Z M10.2 8L10.4 5.8L11.1 7L12 4.6L12.9'
                     . ' 6.6L13.9 4.2L14.8 6.8L15.8 4.6L16.2 7.8Z M7.2 10L7.4 7.4L9.4 6.4L9.8 8.2Z M6.8'
                     . ' 13.4L2.6 16.8L1.8 16L3.4 12.3L1.8 8.6L2.6 7.8L6.8 11.2Z M14.4 16L12 18L13.2 15.8Z'
                     . ' M10.6 15.2L8.2 17L9.4 14.8Z M19.81 10.45L19.55 10.19L19.2 10.1L18.85 10.19L18.59'
                     . ' 10.45L18.5 10.8L18.59 11.15L18.85 11.41L19.2 11.5L19.55 11.41L19.81 11.15L19.9 10.8Z'
                     . ' M17.22 8.55L17.02 9.32L16.87 10.1L16.79 10.9L16.76 11.7L16.79 12.5L16.87 13.3L17.02'
                     . ' 14.08L17.22 14.85L17.58 14.75L17.39 14L17.25 13.24L17.17 12.47L17.14 11.7L17.17'
                     . ' 10.93L17.25 10.16L17.39 9.4L17.58 8.65Z M21.35 12.12L20.15 12.42L20.25 12.78L21.45'
                     . ' 12.48Z M9.45 8.9L8.85 14.57L9.35 14.63L9.95 8.96Z M11.85 8.78L11.25 14.57L11.75'
                     . ' 14.63L12.35 8.84Z M14.25 8.66L13.65 14.57L14.15 14.63L14.75 8.72Z"/>',

        /* ÜBRIGE TIERE — A CENTIPEDE, drawn anew (24.09.2026:
           „das alte ist nicht gut"; it was a chain of rings). The group is
           a catch-all of some 30,700 names in about fifty classes —
           nematodes, flatworms and parasites, millipedes and centipedes,
           crustaceans, rotifers, the animals of the German coasts — and a
           segmented creature with many legs is still the smallest picture
           they share. From above and S-curved: one pair of legs per
           segment, angled back, feelers in front, the long trailing legs
           behind; the segments are cuts. The key stays 'worm', so every
           list keeps its sign. Centred. */
        'worm'      => '<path d="M14.5 3.11L14.47 3.29L14.42 3.52L14.37 3.78L14.3 4.07L14.21 4.38L14.1'
                     . ' 4.71L13.98 5.04L13.87 5.38L13.75 5.73L13.64 6.06L13.54 6.39L13.44 6.71L13.34'
                     . ' 7.04L13.23 7.38L13.11 7.71L13 8.04L12.89 8.37L12.79 8.68L12.7 9L12.62 9.3L12.55'
                     . ' 9.59L12.5 9.87L12.47 10.14L12.46 10.4L12.47 10.65L12.5 10.92L12.54 11.2L12.61'
                     . ' 11.5L12.69 11.8L12.78 12.12L12.88 12.44L12.98 12.77L13.09 13.1L13.2 13.44L13.31'
                     . ' 13.79L13.4 14.12L13.48 14.41L13.57 14.7L13.67 14.99L13.78 15.29L13.89 15.59L14'
                     . ' 15.91L14.11 16.23L14.21 16.57L14.3 16.91L14.37 17.27L14.41 17.65L14.41 18.04L14.37'
                     . ' 18.44L14.29 18.83L14.19 19.2L14.06 19.57L13.92 19.91L13.77 20.24L13.58 20.53L13.4'
                     . ' 20.79L13.23 21.02L13.07 21.21L12.95 21.36L12.84 21.47L11.56 20.93L11.58 20.74L11.64'
                     . ' 20.5L11.71 20.25L11.79 19.98L11.87 19.7L11.95 19.41L12.07 19.14L12.18 18.86L12.27'
                     . ' 18.6L12.33 18.36L12.37 18.15L12.39 17.96L12.38 17.78L12.36 17.58L12.31 17.36L12.25'
                     . ' 17.12L12.17 16.86L12.07 16.59L11.96 16.3L11.85 16L11.73 15.69L11.61 15.36L11.5'
                     . ' 15.02L11.4 14.68L11.31 14.37L11.22 14.07L11.11 13.75L11 13.43L10.89 13.08L10.77'
                     . ' 12.73L10.66 12.37L10.56 12L10.47 11.61L10.4 11.22L10.36 10.81L10.34 10.4L10.35'
                     . ' 9.99L10.39 9.58L10.46 9.19L10.54 8.81L10.63 8.43L10.73 8.07L10.84 7.71L10.96'
                     . ' 7.36L11.07 7.03L11.17 6.71L11.27 6.4L11.36 6.09L11.46 5.76L11.56 5.41L11.68 5.05L11.79'
                     . ' 4.69L11.91 4.34L12.02 3.99L12.14 3.66L12.27 3.36L12.39 3.09L12.51 2.85L12.61 2.65L12.7'
                     . ' 2.49Z M12.34 4.4L10.24 4.57L10.19 4.08L12.27 3.78Z M14.04 4.39L15.5 5.9L15.15'
                     . ' 6.26L13.6 4.83Z M11.78 6.15L9.69 6.4L9.62 5.9L11.69 5.53Z M13.47 6.08L14.99 7.54L14.65'
                     . ' 7.91L13.05 6.54Z M11.26 7.76L9.16 7.96L9.1 7.46L11.18 7.14Z M12.95 7.73L14.43'
                     . ' 9.23L14.08 9.59L12.52 8.18Z M10.8 9.54L8.75 10.01L8.62 9.53L10.64 8.94Z M12.47'
                     . ' 9.29L14.14 10.58L13.84 10.98L12.11 9.79Z M10.83 11.44L9.04 12.55L8.77 12.13L10.49'
                     . ' 10.93Z M12.33 10.66L14.32 11.34L14.18 11.82L12.15 11.25Z M11.33 13.23L9.76 14.63L9.42'
                     . ' 14.27L10.91 12.78Z M12.68 12.21L14.76 12.54L14.7 13.03L12.6 12.82Z M11.83 14.85L10.21'
                     . ' 16.19L9.88 15.81L11.43 14.39Z M13.22 13.88L15.28 14.28L15.2 14.78L13.12 14.49Z M12.4'
                     . ' 16.44L10.88 17.9L10.53 17.55L11.96 16.01Z M13.71 15.36L15.8 15.61L15.75 16.11L13.65'
                     . ' 15.98Z M12.74 17.81L10.97 18.94L10.69 18.52L12.4 17.29Z M14.24 17.01L16.24 17.67L16.09'
                     . ' 18.15L14.06 17.6Z M12.51 18.97L10.42 19.2L10.35 18.71L12.42 18.36Z M14.2 18.91L15.7'
                     . ' 20.39L15.36 20.75L13.78 19.37Z M11.87 20.34L9.77 20.24L9.78 19.74L11.88 19.72Z M13.55'
                     . ' 20.55L14.81 22.24L14.41 22.55L13.07 20.93Z M12.96 3.14L12.61 2.53L12.16 2.09L11.63'
                     . ' 1.78L11.05 1.63L10.47 1.63L9.87 1.81L9.73 1.39L10.39 1.17L11.11 1.15L11.82 1.32L12.47'
                     . ' 1.67L13.03 2.2L13.44 2.86Z M14.03 2.98L14.6 2.54L15.27 2.23L15.97 2.08L16.67'
                     . ' 2.09L17.34 2.27L17.91 2.61L17.69 2.99L17.17 2.7L16.61 2.57L16.03 2.57L15.44 2.72L14.89'
                     . ' 2.99L14.37 3.42Z M11.93 21.21L9.94 22.95L9.64 22.62L11.55 20.8Z M12.94 21.39L12.96'
                     . ' 24.03L12.51 24.04L12.39 21.4Z M13.59 5.14L12.17 4.65L12.07 4.94L13.49 5.42Z M13.06'
                     . ' 6.79L11.63 6.36L11.54 6.64L12.98 7.08Z M12.52 8.47L11.1 8L11 8.28L12.43 8.75Z M12.18'
                     . ' 10.06L10.7 9.78L10.65 10.07L12.12 10.35Z M12.31 11.49L10.82 11.71L10.86 12L12.35'
                     . ' 11.79Z M12.77 13.05L11.34 13.52L11.44 13.8L12.86 13.34Z M13.27 14.68L11.83 15.09L11.91'
                     . ' 15.38L13.35 14.97Z M13.79 16.15L12.38 16.67L12.49 16.95L13.89 16.43Z M14.12'
                     . ' 17.74L12.64 17.97L12.68 18.26L14.16 18.03Z M13.76 19.61L12.32 19.16L12.23 19.44L13.67'
                     . ' 19.89Z M13.09 21.06L11.75 20.39L11.62 20.66L12.96 21.32Z"/>',

        /* KREBS — a crayfish from above, the one part of the catch-all with
           a place in the field and in conservation (the noble crayfish
           against the American signal crayfish): the carapace with its
           rostrum, two claws as real pincers — two fingers with the
           opening between them; drawn as round paddles they read as
           spoons — long feelers, three walking legs a side, the segmented
           tail with its fan. Centred. */
        'crayfish'  => '<path d="M12 5L12.52 5.28L13.03 5.67L13.5 6.15L13.93 6.72L14.28 7.38L14.56 8.12L14.74'
                     . ' 8.93L14.8 9.8L14.75 10.59L14.62 11.32L14.39 11.97L14.08 12.52L13.68 12.98L13.2'
                     . ' 13.32L12.64 13.53L12 13.6L11.36 13.53L10.8 13.32L10.32 12.98L9.92 12.53L9.61'
                     . ' 11.97L9.38 11.32L9.25 10.59L9.2 9.8L9.26 8.93L9.44 8.12L9.72 7.38L10.07 6.72L10.5'
                     . ' 6.15L10.97 5.67L11.48 5.28L12 5Z M14.4 13.9L14.26 14.18L13.84 14.43L13.2 14.61L12.42'
                     . ' 14.71L11.58 14.71L10.8 14.61L10.16 14.43L9.74 14.18L9.6 13.9L9.74 13.62L10.16'
                     . ' 13.37L10.8 13.19L11.58 13.09L12.42 13.09L13.2 13.19L13.84 13.37L14.26 13.62Z M14.22'
                     . ' 15.15L14.09 15.43L13.7 15.68L13.11 15.86L12.39 15.96L11.61 15.96L10.89 15.86L10.3'
                     . ' 15.68L9.91 15.43L9.78 15.15L9.91 14.87L10.3 14.62L10.89 14.44L11.61 14.34L12.39'
                     . ' 14.34L13.11 14.44L13.7 14.62L14.09 14.87Z M14.04 16.4L13.92 16.68L13.56 16.93L13.02'
                     . ' 17.11L12.35 17.21L11.65 17.21L10.98 17.11L10.44 16.93L10.08 16.68L9.96 16.4L10.08'
                     . ' 16.12L10.44 15.87L10.98 15.69L11.65 15.59L12.35 15.59L13.02 15.69L13.56 15.87L13.92'
                     . ' 16.12Z M13.86 17.65L13.75 17.93L13.42 18.18L12.93 18.36L12.32 18.46L11.68 18.46L11.07'
                     . ' 18.36L10.58 18.18L10.25 17.93L10.14 17.65L10.25 17.37L10.58 17.12L11.07 16.94L11.68'
                     . ' 16.84L12.32 16.84L12.93 16.94L13.42 17.12L13.75 17.37Z M13.68 18.9L13.58 19.18L13.29'
                     . ' 19.43L12.84 19.61L12.29 19.71L11.71 19.71L11.16 19.61L10.71 19.43L10.42 19.18L10.32'
                     . ' 18.9L10.42 18.62L10.71 18.37L11.16 18.19L11.71 18.09L12.29 18.09L12.84 18.19L13.29'
                     . ' 18.37L13.58 18.62Z M12.95 21.2L12.86 21.94L12.59 22.53L12.21 22.86L11.79 22.86L11.41'
                     . ' 22.53L11.14 21.94L11.05 21.2L11.14 20.46L11.41 19.87L11.79 19.54L12.21 19.54L12.59'
                     . ' 19.87L12.86 20.46Z M11.09 20.38L11.34 21.03L11.38 21.64L11.21 22.08L10.86 22.27L10.39'
                     . ' 22.17L9.91 21.79L9.51 21.22L9.26 20.57L9.22 19.96L9.39 19.52L9.74 19.33L10.21'
                     . ' 19.43L10.69 19.81Z M14.49 21.22L14.09 21.79L13.61 22.17L13.14 22.27L12.79 22.08L12.62'
                     . ' 21.64L12.66 21.03L12.91 20.38L13.31 19.81L13.79 19.43L14.26 19.33L14.61 19.52L14.78'
                     . ' 19.96L14.74 20.57Z M14.5 9.09L16.37 9.77L17.12 11.18L16.68 11.42L16.03 10.23L14.3'
                     . ' 9.71Z M14.55 10.51L16.39 11.49L17.12 12.99L16.68 13.21L16.01 11.91L14.25 11.09Z M14.58'
                     . ' 11.93L16.41 13.2L17.13 14.79L16.67 15.01L15.99 13.6L14.22 12.47Z M9.7 9.71L7.97'
                     . ' 10.23L7.32 11.42L6.88 11.18L7.63 9.77L9.5 9.09Z M9.75 11.09L7.99 11.91L7.32 13.21L6.88'
                     . ' 12.99L7.61 11.49L9.45 10.51Z M9.78 12.47L8.01 13.6L7.33 15.01L6.87 14.79L7.59'
                     . ' 13.2L9.42 11.93Z M10.97 5.54L10.43 4.66L9.99 3.84L9.64 3.08L9.35 2.4L9.08 1.81L8.83'
                     . ' 1.3L8.56 0.89L8.24 0.56L8.56 0.24L8.92 0.6L9.24 1.06L9.52 1.59L9.8 2.2L10.11'
                     . ' 2.87L10.46 3.6L10.89 4.39L11.43 5.26Z M12.57 5.26L13.11 4.39L13.54 3.6L13.89 2.87L14.2'
                     . ' 2.2L14.48 1.59L14.76 1.06L15.08 0.6L15.44 0.24L15.76 0.56L15.44 0.89L15.17 1.3L14.92'
                     . ' 1.81L14.65 2.4L14.36 3.08L14.01 3.84L13.57 4.66L13.03 5.54Z M13.98 6.55L15.94'
                     . ' 5.6L16.88 4.68L17.52 5.32L16.46 6.4L14.42 7.45Z M18.95 3.34L19.05 3.99L18.99 4.6L18.78'
                     . ' 5.1L18.43 5.44L18 5.56L17.53 5.46L17.08 5.15L16.7 4.66L16.45 4.06L16.35 3.41L16.41'
                     . ' 2.8L16.62 2.3L16.97 1.96L17.4 1.84L17.87 1.94L18.32 2.25L18.7 2.74Z M17.94 2.28L18.33'
                     . ' 1.24L17.96 0.43L18.44 0.17L19.07 1.16L18.86 2.52Z M16.58 2.38L16.47 1.24L17.09'
                     . ' 0.37L17.51 0.63L17.13 1.36L17.42 2.22Z M9.58 7.45L7.54 6.4L6.48 5.32L7.12 4.68L8.06'
                     . ' 5.6L10.02 6.55Z M7.55 4.06L7.3 4.66L6.92 5.15L6.47 5.46L6 5.56L5.57 5.44L5.22 5.1L5.01'
                     . ' 4.6L4.95 3.99L5.05 3.34L5.3 2.74L5.68 2.25L6.13 1.94L6.6 1.84L7.03 1.96L7.38 2.3L7.59'
                     . ' 2.8L7.65 3.41Z M5.14 2.52L4.93 1.16L5.56 0.17L6.04 0.43L5.67 1.24L6.06 2.28Z M6.58'
                     . ' 2.22L6.87 1.36L6.49 0.63L6.91 0.37L7.53 1.24L7.42 2.38Z M9.9 14.65L14.1 14.65L14.1'
                     . ' 14.35L9.9 14.35Z M10.08 15.9L13.92 15.9L13.92 15.6L10.08 15.6Z M10.26 17.15L13.74'
                     . ' 17.15L13.74 16.85L10.26 16.85Z M10.44 18.4L13.56 18.4L13.56 18.1L10.44 18.1Z"/>',


        /* FLECHTE — A CLADONIA WITH TIERED CUPS (24.09.2026).
           The first drawing meant a Cladonia's podetia and was „nicht
           schön": two funnels on a wavy line. Lichens barely have a picture
           of their own — Ramalina and Evernia look like coral and are taken
           for fungi, and a flat thallus with cups reads as a liverwort. What
           no fungus and no moss does is a cup growing out of the middle of a
           cup, as in Cladonia verticillata: a slender stalk flaring sharply
           into a goblet, the opening an ellipse seen from a little above (a
           hole the white edge fills, so the inside of the cup shows white),
           a second goblet out of its centre, one more beside it, all rising
           from a cushion of basal scales. The cushion is drawn last, so its
           white edge lays the stalks behind it. It stands on the cushion
           (anchor in map.js).

           IT IS THE SIGN LICHEN LISTS START WITH — the starter lists in
           auth.php and the lichen package in species_packages.php. The
           foliose lichen held that place for an afternoon; then the maintainer
           found the Cladonia „sogar gerade markanter" and it came back. */
        'lichen'    => '<path d="M9.95 20.4L9.98 16L9.88 15.19L9.67 14.48L9.36 13.87L8.99 13.33L8.6 12.87L8.22'
                     . ' 12.47L7.87 12.12L7.6 11.8L7.68 11.6L7.9 11.41L8.25 11.24L8.73 11.1L9.3 10.99L9.93'
                     . ' 10.92L10.6 10.9L11.27 10.92L11.9 10.99L12.47 11.1L12.95 11.24L13.3 11.41L13.52'
                     . ' 11.6L13.6 11.8L13.33 12.12L12.98 12.47L12.6 12.87L12.21 13.33L11.84 13.87L11.53'
                     . ' 14.48L11.32 15.19L11.22 16L11.25 20.4Z M10.02 11.9L10.05 7.4L9.97 6.79L9.79 6.27L9.54'
                     . ' 5.83L9.24 5.44L8.92 5.1L8.61 4.79L8.33 4.5L8.1 4.2L8.16 4L8.35 3.81L8.65 3.64L9.04'
                     . ' 3.5L9.52 3.39L10.04 3.32L10.6 3.3L11.16 3.32L11.68 3.39L12.16 3.5L12.55 3.64L12.85'
                     . ' 3.81L13.04 4L13.1 4.2L12.87 4.5L12.59 4.79L12.28 5.1L11.96 5.44L11.66 5.83L11.41'
                     . ' 6.27L11.23 6.79L11.15 7.4L11.18 11.9Z M16.55 20.4L16.58 16.8L16.51 15.99L16.36'
                     . ' 15.28L16.14 14.67L15.89 14.13L15.62 13.67L15.35 13.27L15.1 12.92L14.9 12.6L14.96'
                     . ' 12.4L15.13 12.21L15.4 12.04L15.77 11.9L16.2 11.79L16.69 11.72L17.2 11.7L17.71'
                     . ' 11.72L18.2 11.79L18.63 11.9L19 12.04L19.27 12.21L19.44 12.4L19.5 12.6L19.3 12.92L19.05'
                     . ' 13.27L18.78 13.67L18.51 14.13L18.26 14.67L18.04 15.28L17.89 15.99L17.82 16.8L17.85'
                     . ' 20.4Z M12.8 11.62L12.28 11.48L11.51 11.39L10.6 11.36L9.69 11.39L8.92 11.48L8.4'
                     . ' 11.62L8.22 11.78L8.4 11.94L8.92 12.08L9.69 12.17L10.6 12.2L11.51 12.17L12.28'
                     . ' 12.08L12.8 11.94L12.98 11.78Z M12.34 4.02L11.93 3.88L11.32 3.79L10.6 3.76L9.88'
                     . ' 3.79L9.27 3.88L8.86 4.02L8.72 4.18L8.86 4.34L9.27 4.48L9.88 4.57L10.6 4.6L11.32'
                     . ' 4.57L11.93 4.48L12.34 4.34L12.48 4.18Z M18.75 12.42L18.39 12.28L17.84 12.19L17.2'
                     . ' 12.16L16.56 12.19L16.01 12.28L15.65 12.42L15.52 12.58L15.65 12.74L16.01 12.88L16.56'
                     . ' 12.97L17.2 13L17.84 12.97L18.39 12.88L18.75 12.74L18.88 12.58Z"/>'
                     . '<path d="M5.55 20.7L5.37 21.12L4.88 21.44L4.2 21.55L3.53 21.44L3.03 21.12L2.85'
                     . ' 20.7L3.03 20.27L3.52 19.96L4.2 19.85L4.88 19.96L5.37 20.27Z M7.5 20.4L7.32 20.82L6.83'
                     . ' 21.14L6.15 21.25L5.48 21.14L4.98 20.82L4.8 20.4L4.98 19.97L5.47 19.66L6.15 19.55L6.83'
                     . ' 19.66L7.32 19.97Z M9.45 20.7L9.27 21.12L8.78 21.44L8.1 21.55L7.43 21.44L6.93'
                     . ' 21.12L6.75 20.7L6.93 20.27L7.43 19.96L8.1 19.85L8.78 19.96L9.27 20.27Z M11.4'
                     . ' 20.4L11.22 20.82L10.73 21.14L10.05 21.25L9.38 21.14L8.88 20.82L8.7 20.4L8.88'
                     . ' 19.97L9.38 19.66L10.05 19.55L10.73 19.66L11.22 19.97Z M13.35 20.7L13.17 21.12L12.68'
                     . ' 21.44L12 21.55L11.32 21.44L10.83 21.12L10.65 20.7L10.83 20.27L11.32 19.96L12'
                     . ' 19.85L12.68 19.96L13.17 20.27Z M15.3 20.4L15.12 20.82L14.62 21.14L13.95 21.25L13.27'
                     . ' 21.14L12.78 20.82L12.6 20.4L12.78 19.97L13.27 19.66L13.95 19.55L14.62 19.66L15.12'
                     . ' 19.97Z M17.25 20.7L17.07 21.12L16.58 21.44L15.9 21.55L15.23 21.44L14.73 21.12L14.55'
                     . ' 20.7L14.73 20.27L15.23 19.96L15.9 19.85L16.58 19.96L17.07 20.27Z M19.2 20.4L19.02'
                     . ' 20.82L18.53 21.14L17.85 21.25L17.18 21.14L16.68 20.82L16.5 20.4L16.68 19.97L17.18'
                     . ' 19.66L17.85 19.55L18.53 19.66L19.02 19.97Z M21.15 20.7L20.97 21.12L20.48 21.44L19.8'
                     . ' 21.55L19.12 21.44L18.63 21.12L18.45 20.7L18.63 20.27L19.12 19.96L19.8 19.85L20.48'
                     . ' 19.96L20.97 20.27Z M4.2 20.7L19.8 20.7L19.8 21.4L4.2 21.4Z"/>',

        /* BLATTFLECHTE — A LUNG LICHEN FROM ABOVE (the same day:
           „eine Lungenflechte, eine Lobaria, mit Apothezien drauf, also eher
           so wie eine Wolke mit kleinen Kreisen drin"). Six broad lobes with
           slightly notched ends and four apothecia as HOLES: on the map the
           white edge lines each one with a ring and the ground shows
           through inside it, in the list the page does — so the discs take
           another colour than the thallus without the shape needing a
           second one („nicht mit der Hintergrundfarbe füllen,
           sondern transparent machen"; on the aerial image they turn dark,
           like the red-brown apothecia of a real Lobaria). It is
           meant to be that cloud: a second draft with seven lobes, deeper
           bays and five discs, drawn so it would look less like one, was
           „schon falsch" — he kept the first.

           THE DISCS ARE UNEQUAL ON PURPOSE. Four of one size sat in the
           outer lobes like a grid, and one cut the edge; three were still
           symmetric; one moved to the middle, and different sizes settled
           it („unterschiedliche Größen ist cool"): two large ones off the
           centre, a medium one, one that is almost a dot — then all four
           shrunk by the same factor of 0.8, because the
           large ones were too large. Each hole keeps at least 0.9 units of
           thallus to the edge and to its neighbour.

           The outline is ONE polygon so the holes cut cleanly — over two
           overlapping pieces a hole only takes one of them away. Seen from
           above it lies flat and stays centred (no anchor). */
        'foliose_lichen' => '<path d="M20.45 12.2L20.84 12.6L21.14 13.02L21.36 13.47L21.51 13.93L21.6 14.39L21.63'
                     . ' 14.86L21.53 15.3L21.24 15.67L20.86 15.99L20.73 16.41L20.75 16.91L20.61 17.34L20.3'
                     . ' 17.68L19.92 17.96L19.51 18.19L19.05 18.36L18.56 18.47L18.03 18.51L17.46 18.45L16.83'
                     . ' 18.26L15.3 16.74L15.67 17.76L15.66 18.33L15.56 18.81L15.38 19.23L15.16 19.6L14.9'
                     . ' 19.93L14.6 20.21L14.28 20.45L13.92 20.61L13.53 20.61L13.12 20.46L12.74 20.45L12.38'
                     . ' 20.68L12 20.82L11.61 20.78L11.24 20.64L10.88 20.44L10.55 20.19L10.24 19.89L9.98'
                     . ' 19.53L9.75 19.11L9.6 18.6L9.57 17.89L8.84 18.76L8.26 19.15L7.72 19.36L7.21 19.46L6.72'
                     . ' 19.47L6.24 19.42L5.79 19.31L5.36 19.14L4.98 18.91L4.7 18.58L4.58 18.11L4.46 17.68L4.08'
                     . ' 17.42L3.68 17.17L3.45 16.8L3.35 16.37L3.32 15.91L3.35 15.45L3.45 14.98L3.62 14.51L3.87'
                     . ' 14.05L4.24 13.61L4.79 13.18L6.27 12.72L5.65 12.49L5.24 12.2L4.93 11.88L4.69 11.54L4.51'
                     . ' 11.19L4.39 10.82L4.32 10.45L4.3 10.07L4.37 9.72L4.61 9.43L4.91 9.17L5.01 8.83L5'
                     . ' 8.43L5.11 8.09L5.36 7.82L5.66 7.59L6 7.41L6.36 7.27L6.75 7.18L7.18 7.15L7.63 7.2L8.14'
                     . ' 7.36L8.09 6.81L7.65 5.61L7.66 4.93L7.78 4.36L7.99 3.86L8.25 3.42L8.56 3.03L8.91'
                     . ' 2.7L9.3 2.42L9.72 2.23L10.19 2.22L10.67 2.4L11.12 2.41L11.55 2.15L12 1.98L12.46'
                     . ' 2.02L12.9 2.19L13.32 2.43L13.72 2.72L14.08 3.08L14.4 3.5L14.66 4L14.85 4.61L14.88'
                     . ' 5.45L14.93 6.12L15.47 5.75L15.97 5.56L16.44 5.47L16.9 5.45L17.34 5.5L17.76 5.6L18.16'
                     . ' 5.76L18.51 5.97L18.77 6.28L18.88 6.71L19 7.11L19.34 7.35L19.72 7.59L19.94 7.93L20.03'
                     . ' 8.33L20.06 8.76L20.03 9.19L19.93 9.62L19.78 10.05L19.54 10.48L19.2 10.89L18.69'
                     . ' 11.29L19.17 11.56L19.94 11.84Z M11.18 10.06L10.88 9.48L10.42 9.02L9.84 8.72L9.2'
                     . ' 8.62L8.56 8.72L7.98 9.02L7.52 9.48L7.22 10.06L7.12 10.7L7.22 11.34L7.52 11.92L7.98'
                     . ' 12.38L8.56 12.68L9.2 12.78L9.84 12.68L10.42 12.38L10.88 11.92L11.18 11.34L11.28 10.7Z'
                     . ' M17.5 12.68L17.22 12.12L16.78 11.68L16.22 11.4L15.6 11.3L14.98 11.4L14.42 11.68L13.98'
                     . ' 12.12L13.7 12.68L13.6 13.3L13.7 13.92L13.98 14.48L14.42 14.92L14.98 15.2L15.6'
                     . ' 15.3L16.22 15.2L16.78 14.92L17.22 14.48L17.5 13.92L17.6 13.3Z M17.38 7.81L17.11'
                     . ' 7.39L16.69 7.12L16.2 7.02L15.71 7.12L15.29 7.39L15.02 7.81L14.92 8.3L15.02 8.79L15.29'
                     . ' 9.21L15.71 9.48L16.2 9.58L16.69 9.48L17.11 9.21L17.38 8.79L17.48 8.3Z M8.92 15.62L8.6'
                     . ' 15.39L8.2 15.39L7.88 15.62L7.76 16L7.88 16.38L8.2 16.61L8.6 16.61L8.92 16.38L9.04'
                     . ' 16Z"/>',
    ];


    return $shapes;
}

/**
 * The geometry of a shape, with fallback to the circle.
 *
 * FALL BACK SILENTLY INSTEAD OF THROWING — unlike with the icons: an
 * unknown icon name is a typo in the code and should stand out, an unknown
 * shape name by contrast stands in the DATA and can come from an older
 * device that knows a shape which does not (yet) exist here. An exception
 * would then tear open the whole page instead of drawing one row more
 * plainly.
 */
function shape_geometry(?string $form): string
{
    // A retired name draws its successor (LIST_SYMBOL_ALIAS, data.php).
    if (defined('LIST_SYMBOL_ALIAS') && $form !== null) {
        $form = LIST_SYMBOL_ALIAS[$form] ?? $form;
    }
    /* THE FALLBACK MUST NOT ITSELF BE ABLE TO FAIL. $all['kreis'] stood
       here, and the renaming of the shapes into English turned it into
       nothing: the key was gone. As long as the database already carried
       English values nobody noticed — the fallback is only reached for an
       UNKNOWN name. That is exactly what the live server was before
       migration 0059: the German values still stood there, every call ran
       into the fallback, found nothing and returned null — and because the
       function promises string, it was a TypeError in the middle of
       building the page. Everything below it was missing, the scripts at
       the foot of the page included: no tab reacted any more, and the area
       list stayed on „wird geholt …" for ever. It looked like a hanging
       load and was a crash.

       Hence an empty string at the end and no second lookup in the table.
       A marker without a drawing is a blemish; half a page is not. */
    $all = shapes_all();
    return $all[(string) $form] ?? $all['circle'] ?? '';
}

/**
 * The small shape symbol for a row — the list's shape in its color.
 *
 * WITHOUT FIXED DIMENSIONS: the size hangs on the stylesheet (.color-point)
 * and there on the font of the row the symbol stands in. The counterpart in
 * the browser is shapePoint() in assets/js/shapes.js — both build the same
 * markup, and both take the geometry from the same table up above.
 *
 * No white fringe and no anchor: both belong to the map, where the shape
 * stands on an aerial photo and hangs over a coordinate. In a list row it
 * stands on the application's surface and needs neither.
 */
function shape_point(?string $form, ?string $color): string
{
    // The color comes from map_lists.color and thus from the data — h() is
    // no formality here: without it a quotation mark from the column would
    // stand in the middle of the attribute.
    $color = $color !== null && $color !== '' ? $color : '#8a97a4';

    return '<span class="color-point"><svg viewBox="0 0 24 24" aria-hidden="true">'
         . '<g fill="' . h($color) . '">' . shape_geometry($form) . '</g></svg></span>';
}

/**
 * The stock for the script — like icon_stock(), for the same reason.
 *
 * The shapes stand in the <template> each inside a complete <svg>: bare
 * <path> and <circle> tags would be read by the HTML parser as unknown HTML
 * elements and nested when handed back. Inside an <svg> they land in the
 * right namespace, and assets/js/shapes.js takes its innerHTML from there.
 */
function shape_stock(): string
{
    $out = '<div id="shape-stock" hidden>';
    $all = shapes_all();
    foreach ($all as $name => $geometrie) {
        $out .= '<template data-form="' . h($name) . '">'
               . '<svg viewBox="0 0 24 24">' . $geometrie . '</svg></template>';
    }
    /* THE RETIRED NAMES TOO, drawn as their successor: a phone that still
       holds a list under an old name (it was offline when migration 0122
       ran) draws the seedling instead of falling back to a circle. They
       are not offered — the picker reads SHAPE_NAMES, not this stock. */
    foreach (defined('LIST_SYMBOL_ALIAS') ? LIST_SYMBOL_ALIAS : [] as $old => $new) {
        if (isset($all[$new]) && !isset($all[$old])) {
            $out .= '<template data-form="' . h($old) . '">'
                   . '<svg viewBox="0 0 24 24">' . $all[$new] . '</svg></template>';
        }
    }
    return $out . '</div>';
}
