<?php
/**
 * The icon library — every symbol of the interface exactly once.
 *
 * WHY IT EXISTS: the map sheet stood three times in the project (sidebar
 * header, administration header, a point's facts row), the X twice, the
 * clock twice — and the two clocks had already drifted apart: one hand
 * started at 7.2, the other at 7.4. That is precisely the price of copied
 * drawings, and you only see it once both lie side by side. An icon drawn
 * differently in two places is two icons.
 *
 * WHAT BELONGS HERE: icons of the interface — what the user clicks or
 * reads as information. What does NOT belong here are the lists' marker
 * shapes (FORMEN in map.js): those are content, not controls — the user
 * picks them per list, they carry rules of their own (white border,
 * paint-order) and grow with the demands on the survey, not with those on
 * the interface.
 *
 * NO ICON CARRIES ITS OWN SIZE. It comes from the stylesheet and there
 * from the font of the line the icon stands in — if the font grows, the
 * icon grows with it. The default is 1em (.icon in app.css); deviating
 * spots set it via their own class.
 *
 * That this was necessary, the sidebar header showed: four icons stood
 * there with width="18" in the markup — and were drawn 16 pixels anyway,
 * because ".symbol-button svg" in the stylesheet overruled the attribute.
 * A value that does nothing is worse than none: you change it and wonder
 * why nothing happens.
 *
 * THE ONE EXCEPTION are the map's markers (map.js): their size feeds
 * into Leaflet's iconSize and iconAnchor, with which the marker is aligned
 * over its coordinate. Came it from the stylesheet, it would run away from
 * the anchor computation, and the marker would sit next to the spot it
 * points at.
 *
 * ONLY THE GEOMETRY LIVES HERE, not the <svg> around it: only the call
 * decides the stroke width, for the same map sheet stands once at 20
 * pixels in the header and once at 11.5 in a facts row — and a stroke that
 * is elegant at 20 vanishes at 11.5.
 *
 * THE COORDINATE FIELD IS ALWAYS 24×24. Whoever adds an icon draws it into
 * this field; everything else takes its cue from that.
 *
 * FOR JAVASCRIPT: icon_stock() places the needed icons as <template>
 * into the document, assets/js/icon.js fetches them from there. That
 * way the path data exists only once even when both sides need it.
 */

declare(strict_types=1);

/**
 * The drawings. Key is the name, value the inside of the <svg>.
 *
 * 'stroke' deviates from the default 1.8 where the drawing needs it;
 * 'filled' says the icon is a surface and not lines.
 */
function icon_stock_all(): array
{
    return [
        // --- Paths and tools ---------------------------------------------
        //
        // A folded map sheet. The previous 🗺️ was a font glyph: drawn
        // differently on every system, sometimes colorful, sometimes flat,
        // and never in the interface's color.
        'map' => [
            'path'   => '<path d="M9 3.5 3.5 5.6v14.2L9 17.7l6 2.8 5.5-2.1V4.2L15 6.3 9 3.5Z"/>'
                      . '<path d="M9 3.5v14.2M15 6.3v14.2"/>',
            'stroke' => 1.6,
        ],

        /* FULL SCREEN and its way back (fullscreen.js): four corners
           pointing out, and the same corners pointing in. */
        'fullscreen' => [
            'path' => '<path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5"/>',
        ],
        'fullscreen-exit' => [
            'path' => '<path d="M9 4v5H4M20 9h-5V4M15 20v-5h5M4 15h5v5"/>',
        ],
        'magnifier' => [
            'path' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.4 15.4 4.1 4.1"/>',
        ],

        // Crosshair: "show" means "exactly here", not "somewhere on a
        // map" — the folded map sheet stood here first, but said only
        // "map" and nothing about the spot. The lines reach beyond the
        // circle, otherwise it would read as a clock.
        'crosshair' => [
            'path' => '<circle cx="12" cy="12" r="6.5"/><path d="M12 2v5M12 17v5M2 12h5M17 12h5"/>',
        ],

        // An "i" in a circle — information instead of action. The dot of
        // the i is a stroke of length zero: with stroke-linecap="round"
        // (the default of every icon here) it draws itself as a round dot,
        // entirely without a second, filled shape next to the stroked one.
        'info' => [
            'path' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.4v.01"/>',
        ],

        // A triangle with an exclamation mark — the warning before the one
        // switch that changes whom a list belongs to (22.09.2026).
        'warning' => [
            'path' => '<path d="M12 3.5 2.8 19.5h18.4L12 3.5z"/><path d="M12 9.5v4.5M12 16.6v.01"/>',
        ],

        // A gear, and a toothed one at that. Before, a circle with eight
        // rays stood here — that reads as a sun or an asterisk, not as a
        // setting. The teeth are computed, not eyeballed: eight of them,
        // tip 24°, gap 22°, tip circle 9.4, root circle 6.9. Arc points
        // placed by hand never sit quite evenly on something like this,
        // and on a gear of all things you can see it.
        'gear' => [
            'path'   => '<path d="M10.05 2.81 A9.4 9.4 0 0 1 13.95 2.81 L13.38 5.24 A6.9 6.9 0 0 1 15.81 6.25'
                      . ' L17.12 4.12 A9.4 9.4 0 0 1 19.88 6.88 L17.75 8.19 A6.9 6.9 0 0 1 18.76 10.62'
                      . ' L21.19 10.05 A9.4 9.4 0 0 1 21.19 13.95 L18.76 13.38 A6.9 6.9 0 0 1 17.75 15.81'
                      . ' L19.88 17.12 A9.4 9.4 0 0 1 17.12 19.88 L15.81 17.75 A6.9 6.9 0 0 1 13.38 18.76'
                      . ' L13.95 21.19 A9.4 9.4 0 0 1 10.05 21.19 L10.62 18.76 A6.9 6.9 0 0 1 8.19 17.75'
                      . ' L6.88 19.88 A9.4 9.4 0 0 1 4.12 17.12 L6.25 15.81 A6.9 6.9 0 0 1 5.24 13.38'
                      . ' L2.81 13.95 A9.4 9.4 0 0 1 2.81 10.05 L5.24 10.62 A6.9 6.9 0 0 1 6.25 8.19'
                      . ' L4.12 6.88 A9.4 9.4 0 0 1 6.88 4.12 L8.19 6.25 A6.9 6.9 0 0 1 10.62 5.24 L10.05 2.81 Z"/>'
                      . '<circle cx="12" cy="12" r="3.2"/>',
            'stroke' => 1.7,
        ],

        'sign-out' => [
            'path' => '<path d="M14 4.5H6.5A1.5 1.5 0 0 0 5 6v12a1.5 1.5 0 0 0 1.5 1.5H14"/>'
                    . '<path d="M16.5 8.5 20 12l-3.5 3.5M19.5 12H10"/>',
        ],

        // ONE X FOR ALL CASES — closing a window, collapsing a bar,
        // clearing a search field. There were two: one from 6 to 18, one
        // from 7 to 17. The difference was not intended but grown, and on
        // buttons lying side by side such a thing shows.
        //
        // NOT A FONT GLYPH: "×" and "✕" every system draws differently,
        // sometimes as a narrow cross, sometimes almost square, and their
        // stroke width follows the font instead of the icons next to them.
        'close' => [
            'path' => '<path d="M6 6l12 12M18 6 6 18"/>',
        ],

        // Scissors: cutting a recorded track in two (12.09.2026). Two rings
        // for the handles, two blades that cross — the sign everybody
        // reads as „cut", and nothing else in the library says it.
        'scissors' => [
            'path'   => '<circle cx="6" cy="6" r="2.6"/><circle cx="6" cy="18" r="2.6"/>'
                      . '<path d="M8.2 7.9 20 19.5M8.2 16.1 20 4.5M11.6 12l-1.1 1.1"/>',
            'stroke' => 2,
        ],

        // The funnel for filtering. A sieve or slider would be equally up
        // for choice — but the slider ('sliders') is already taken (map
        // layers), and a funnel says exactly what happens here: everything
        // goes in at the top, less comes out at the bottom.
        'filter' => [
            'path'   => '<path d="M3.5 5h17l-6.5 7.5V20l-4-2.2v-5.3z"/>',
            'stroke' => 1.8,
        ],

        // The checkmark — the counterpart to the X, and in its measures:
        // both sit in the filter as the two states of ONE row ("enthält" /
        // "enthält nicht") directly above one another as soon as you open
        // the menu. A checkmark sitting smaller or higher than its X would
        // make the row jump on toggling.
        'check' => [
            'path'   => '<path d="M5 12.5 10 17.5 19 7"/>',
            'stroke' => 2,
        ],

        // THE RED TRASH BIN is the icon for deleting a RECORD, across the
        // whole project. Closing windows, clearing fields and lifting a
        // selection stay with the X: nothing is lost there, and a bin that
        // throws nothing away would devalue the red promise. It turns red
        // via the button class (.symbol-button.delete in app.css), not
        // here — the drawing follows currentColor like every other.
        'delete' => [
            'path'   => '<path d="M4 7h16M9.5 7V5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v2'
                      . 'M6.2 7l.8 12.3a2 2 0 0 0 2 1.7h6a2 2 0 0 0 2-1.7L17.8 7M10 11v5.5M14 11v5.5"/>',
            'stroke' => 2,
        ],

        // THE FOURTH FAREWELL (23.11.2026): blocking a friend. The ring
        // with the bar is the sign every phone uses for „blocked", and
        // nothing in this library says it otherwise. It ends the friendship
        // like the bin did and keeps the other from asking again —
        // CLAUDE.md, „Three symbols for three farewells", names it.
        'block' => [
            'path'   => '<circle cx="12" cy="12" r="8"/><path d="M6.4 6.4l11.2 11.2"/>',
            'stroke' => 2,
        ],

        // A flag on its pole: reporting a person to the administrators
        // (23.11.2026). The flag is what one raises to call attention, and
        // it carries no farewell — reporting ends nothing.
        'flag' => [
            'path'   => '<path d="M6 21V4M6 4.5h11l-2.2 4 2.2 4H6"/>',
            'stroke' => 1.8,
        ],

        // --- Lists and sorting -------------------------------------------
        //
        // The triangle that shows expanded and collapsed sections. Filled,
        // because an outline flickers at this size.
        'fold-triangle' => [
            'path'     => '<path d="M8.5 4.6 18.4 12 8.5 19.4z"/>',
            'filled' => true,
        ],

        // THE CLOCK says "by time", and does so without translation: an
        // arrow with two strokes beside it is the generic icon for "sort"
        // and does not say BY WHAT. It also stands in a point's facts
        // row — the same icon, the same meaning.
        'clock' => [
            'path' => '<circle cx="12" cy="12" r="8.2"/><path d="M12 7.4V12l3.4 2"/>',
        ],

        // A OVER Z with an arrow is the familiar icon for alphabetical.
        // That grouping happens along the way it does not say — that is in
        // the title and shows itself on the first press. An icon that
        // wanted to explain both would no longer be one at 16 pixels.
        'az' => [
            'path'   => '<path d="M5.5 4v15m0 0-2.6-2.8M5.5 19l2.6-2.8"/>'
                      . '<path d="M11.4 10.4 13.9 4l2.5 6.4M12.3 8.6h3.2"/>'
                      . '<path d="M11.6 13.6h4.6l-4.6 6h4.6"/>',
            'stroke' => 1.7,
        ],

        // --- Facts of a point --------------------------------------------
        //
        // Viewed side by side at 11.5, 16 and 64 pixels before building
        // in: what is pretty at 64 can be a blot at 11.5. At most four
        // strokes per icon — every line not needed only contributes gray.
        'calendar' => [
            'path'   => '<rect x="3.5" y="5.5" width="17" height="15" rx="2.2"/>'
                      . '<path d="M3.5 10.2h17M8.5 3v4.6M15.5 3v4.6"/>',
            'stroke' => 2,
        ],

        'mountain' => [
            'path'   => '<path d="M2.5 19.5 9.5 7l4.2 6.8 2.8-4 5 9.7Z"/>',
            'stroke' => 2,
        ],

        // THE ACCURACY: the dot with its halo, as every phone map draws
        // one's own position — „±5 m" IS the halo around the spot. A
        // circle with its radius drawn in was tried first and read as a
        // „G" at the size of the line. Not the crosshair either: that one
        // already means „show exactly here".
        'accuracy' => [
            'path'   => '<circle cx="12" cy="12" r="8.5" fill="currentColor" fill-opacity=".2"/>'
                      . '<circle cx="12" cy="12" r="2.8" fill="currentColor" stroke="none"/>',
            'stroke' => 2,
        ],

        // THE AREA IS FILLED, the remaining facts are strokes. As an
        // outline it was just a shape at this size — only the fill says
        // "area", and it picks up how the areas look on the map. Three
        // drafts compared; this was the only one that still means
        // something small.
        'area' => [
            'path'   => '<path d="M4 8.5 11 3.5l8.5 5.5-2 9.5-9.5 1.5Z" fill="currentColor" fill-opacity=".28"/>',
            'stroke' => 2,
        ],

        /* The floppy disk — the save sign, and it stays one although the
           thing itself is long gone: no drawing at this size says
           „speichern" more plainly, and everyone born before the cloud
           reads it without thinking. The notch top right and the label
           below are what make it a floppy and not a square. */
        'save' => [
            'path'   => '<path d="M5 4h10.5L20 8.5V19a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z"/>'
                      . '<path d="M8 4v5h6.5V4"/><path d="M7.5 20v-6h9v6"/>',
            'stroke' => 2,
        ],

        /* Taking an import back: the arrow that curves backwards — the
           sign every program uses for „undo". Not the bucket: nothing is
           destroyed here, the points go into the trash and the lists of
           that one import disappear; a bucket would promise the end of
           something and mean a step back. */
        'undo' => [
            'path'   => '<path d="M4 9.5h9.5a5.5 5.5 0 1 1 0 11H8"/><path d="M8 5 3.5 9.5 8 14"/>',
            'stroke' => 2,
        ],

        /* A plain arrow to the right — „take this over there". The time
           filter's small button between „von" and „bis" (timerange.js),
           18.09.2026: „einfach nur ein kleiner Pfeil, der
           rüber zeigt". */
        'arrow-right' => [
            'path'   => '<path d="M4 12h15"/><path d="m13 6 6 6-6 6"/>',
            'stroke' => 2,
        ],

        /* The arrow's other way — „back" to the Fundbrowser's tiles and
           one find before (10.10.2026). Its own drawing and not the
           right one mirrored by the stylesheet: a mirrored copy would be
           one more rule per place that wants it. */
        'arrow-left' => [
            'path'   => '<path d="M20 12H5"/><path d="m11 6-6 6 6 6"/>',
            'stroke' => 2,
        ],
        /* Up and down — a layer of „Ebenen" moved over or under its
           neighbour (blend.js, layerMove; 11.11.2026). Drawings
           of their own, as with the left arrow. */
        'arrow-up' => [
            'path'   => '<path d="M12 20V5"/><path d="m6 11 6-6 6 6"/>',
            'stroke' => 2,
        ],
        'arrow-down' => [
            'path'   => '<path d="M12 4v15"/><path d="m6 13 6 6 6-6"/>',
            'stroke' => 2,
        ],
        /* A layer lying flat at the bottom and an arrow from above onto
           its middle — „als Grundlage nehmen", the layer goes under all
           (blend.js, groundTake; 13.11.2026, drew it in words). */
        'to-ground' => [
            'path'   => '<path d="M12 14.5 3 18l9 3.5 9-3.5z"/><path d="M12 2.5v9.5"/><path d="m8 8.5 4 4 4-4"/>',
            'stroke' => 2,
        ],

        /* Four arrows into the four corners — where a plate's scale bar
           and medium stand (plates.js, cornerControl; 08.11.2026:
           „4 Schaltflächen mit jeweils 1 Pfeil ins Eck"). Four drawings and
           not one turned by the stylesheet, as with the left arrow above. */
        'arrow-up-left' => [
            'path'   => '<path d="M17 17 7 7"/><path d="M7 14V7h7"/>',
            'stroke' => 2,
        ],
        'arrow-up-right' => [
            'path'   => '<path d="M7 17 17 7"/><path d="M10 7h7v7"/>',
            'stroke' => 2,
        ],
        'arrow-down-left' => [
            'path'   => '<path d="M17 7 7 17"/><path d="M7 10v7h7"/>',
            'stroke' => 2,
        ],
        'arrow-down-right' => [
            'path'   => '<path d="M7 7 17 17"/><path d="M17 10v7h-7"/>',
            'stroke' => 2,
        ],

        /* Four corners — „the whole thing into the frame". The photo
           window's „Einpassen" (04.10.2026: „hierzu gibt es ja
           auch ein Symbol"): the sign every viewer uses for fitting. */
        'fit' => [
            'path'   => '<path d="M4 9V5.5A1.5 1.5 0 0 1 5.5 4H9"/><path d="M15 4h3.5A1.5 1.5 0 0 1 20 5.5V9"/>'
                      . '<path d="M20 15v3.5a1.5 1.5 0 0 1-1.5 1.5H15"/><path d="M9 20H5.5A1.5 1.5 0 0 1 4 18.5V15"/>',
            'stroke' => 2,
        ],

        /* A double arrow lying flat — „pull this way or that". Not shown
           as an icon anywhere: it is the photo window's resize CURSOR
           (photowindow.js, drawnCursor), turned to the exact angle a
           rectangle's side runs on (04.10.2026: the browser's
           own four resize arrows came within 22 degrees, not onto it). */
        'resize' => [
            'path'   => '<path d="M3.5 12h17"/><path d="m7.5 8-4 4 4 4"/><path d="m16.5 8 4 4-4 4"/>',
            'stroke' => 2,
        ],

        /* A chain link — „this leads somewhere else". Used at the
           pairing, where one of three ways hands the key to an app on
           the SAME device instead of to a camera. */
        'link' => [
            'path'   => '<path d="M10 13.5a4 4 0 0 0 5.66 0l3-3a4 4 0 1 0-5.66-5.66l-1.5 1.5"/>'
                      . '<path d="M14 10.5a4 4 0 0 0-5.66 0l-3 3a4 4 0 1 0 5.66 5.66l1.5-1.5"/>',
            'stroke' => 2,
        ],

        /* Two sheets, one behind the other — the sign every program uses
           for „copy". Not a clipboard: that one means paste in half the
           applications out there and would have to be learned. */
        'copy' => [
            'path'   => '<rect x="9" y="9" width="11" height="11" rx="2"/>'
                      . '<path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/>',
            'stroke' => 2,
        ],

        /* A question mark in a circle — the one sign for „help" that
           needs no word beside it. Not the lifebelt some programs use:
           that promises rescue from trouble, and this page is meant to be
           read BEFORE anything goes wrong. */
        /* A bare question mark, without the circle of „help": the middle
           light of the keys' verdicts (09.10.2026: „diese Ampel
           — Grün, Gelb, Rot … nur noch Symbole"), drawn like the check and
           the cross beside it so the three read as one set. */
        'question' => [
            'path'   => '<path d="M8.4 8.4a3.6 3.6 0 0 1 7.2.5c0 2.6-3.6 3.1-3.6 5.7"/><path d="M12 19v.01"/>',
            'stroke' => 2,
        ],

        'help' => [
            'path'   => '<circle cx="12" cy="12" r="9"/>'
                      . '<path d="M9.2 9.3a2.9 2.9 0 0 1 5.6 1c0 1.9-2.8 2.5-2.8 4.2"/>'
                      . '<path d="M12 17.6v.01"/>',
            'stroke' => 2,
        ],

        'photo' => [
            'path'   => '<rect x="3" y="7.5" width="18" height="12" rx="2.4"/>'
                      . '<circle cx="12" cy="13.5" r="3.4"/><path d="M8.8 7.5 10 5.2h4l1.2 2.3"/>',
            'stroke' => 2,
        ],

        // The microscope: the „bearbeitet" mark on a find — cloud
        // recordings exist, so there are micrographs or stacking shots
        // beyond the plain phone photo. Foot, curved arm, tube with
        // eyepiece — simple enough to survive the 11.5 px facts row.
        'microscope' => [
            'path'   => '<path d="M4 21h16"/><path d="M7 17.5h6.5"/>'
                      . '<path d="M13.5 21a6.5 6.5 0 0 0 0-13"/>'
                      . '<path d="M8 12.5a2 2 0 0 1-2-2V6.5h6v4a2 2 0 0 1-2 2Z"/>'
                      . '<path d="M11 6.5V4H7v2.5"/>',
            'stroke' => 2,
        ],

        // The municipality: a house, because at eleven pixels it is the
        // only icon that still says "place". A town sign would be a
        // rectangle at this size, a church a blot with a stroke.
        'place' => [
            'path'   => '<path d="M3.5 11 12 4l8.5 7"/><path d="M6 9.7V20h12V9.7"/>',
            'stroke' => 2,
        ],

        // Several find spots: filled dots, because those of all things
        // still remain dots even at eleven pixels.
        'points' => [
            'path'   => '<circle cx="7.5" cy="8" r="2.6" fill="currentColor" stroke="none"/>'
                      . '<circle cx="16.5" cy="10.5" r="2.6" fill="currentColor" stroke="none"/>'
                      . '<circle cx="10.5" cy="17" r="2.6" fill="currentColor" stroke="none"/>',
            'stroke' => 2,
        ],

        /* --- Picture names (Motivnamen) --------------------------------
           One drawing per stock name, for the rails that list a find's
           pictures by name (subjecticons.js maps the words onto these;
           13.11.2026: „für die ganzen Labels eindeutige Symbole
           … dass ich links nur noch eine Symbolleiste habe"). Each draws
           THE THING ITSELF, so two of them never differ by a mark alone:
           the four cystidia share one bottle and differ by where it
           stands — at the gill's edge, on its faces, on the cap, on the
           stem. Photo, magnifier, spore, bars, ruler and label are the
           library's own and serve as they are. */
        // Hauptmotiv: one mushroom in the focus brackets
        'subject-main' => ['path' => '<path d="M3 7V3h4M17 3h4v4M21 17v4h-4M7 21H3v-4"/><path d="M5.5 12a6.5 5.5 0 0 1 13 0Z"/><path d="M10.3 12v6.5h3.4V12"/>'],
        // Kollektion: several fruit bodies on the ground
        'subject-collection' => ['path' => '<path d="M2 20.5h20"/><path d="M2.5 11a4.5 4 0 0 1 9 0Z"/><path d="M5.8 11v9.5M8.2 11v9.5"/><path d="M13 14a3.5 3 0 0 1 7 0Z"/><path d="M15.7 14v6.5M17.3 14v6.5"/>'],
        // Studio: a mushroom under a lamp on its stand, the light falling on it
        'subject-studio' => ['path' => '<path d="M2 20.5h10"/><path d="M2.5 14a4.5 4 0 0 1 9 0Z"/><path d="M5.8 14v6.5M8.2 14v6.5"/><path d="M13 6.5l3.5 4.5 4.5-4.5-1.8-2.5Z"/><path d="M20 6.8v13.7M17.5 20.5h5"/><path d="M11.3 10.2l-1.3.9M13.6 12.6l-.9 1.2"/>'],
        // Größenvergleich: a mushroom beside a ruler
        'subject-size' => ['path' => '<path d="M2.5 11a5 4.5 0 0 1 10 0Z"/><path d="M6.1 11v9h2.8v-9"/><rect x="15" y="3" width="5.5" height="18" rx="1"/><path d="M15 7h2.6M15 11h1.6M15 15h2.6M15 19h1.6"/>'],
        // Habitat: trees on the ground
        'subject-habitat' => ['path' => '<path d="M2 20h20"/><path d="M7.5 3.5 3.5 15h8Z"/><path d="M7.5 15v5"/><path d="M16.5 8 13 16.5h7Z"/><path d="M16.5 16.5V20"/>'],
        // Standort: the map pin
        'subject-site' => ['path' => '<path d="M12 21s6.5-6.2 6.5-11a6.5 6.5 0 0 0-13 0c0 4.8 6.5 11 6.5 11Z"/><circle cx="12" cy="10" r="2.3"/>'],
        // Hutoberseite: the cap from above, umbo and scales
        'subject-cap-top' => ['path' => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="1.8"/><path d="m7.6 8.3.9.8M15.7 7.9l-.6 1M17 14.4l-1-.2M9.2 16.3l.7-.8M12.4 5.8v1.2M6.4 13.2l1.1-.3"/>'],
        // Unterseite: the cap from below, gills round the stem
        'subject-cap-under' => ['path' => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="2.2"/><path d="M14.2 12L20.5 12M13.91 13.1L19.36 16.25M13.1 13.91L16.25 19.36M12 14.2L12 20.5M10.9 13.91L7.75 19.36M10.09 13.1L4.64 16.25M9.8 12L3.5 12M10.09 10.9L4.64 7.75M10.9 10.09L7.75 4.64M12 9.8L12 3.5M13.1 10.09L16.25 4.64M13.91 10.9L19.36 7.75"/>'],
        // Schnittbild: the mushroom cut lengthwise
        'subject-section' => ['path' => '<path d="M11.2 5.6v14H9.6v-8H4.5a6.7 6 0 0 1 6.7-6Z"/><path d="M12.8 5.6v14h1.6v-8h5.1a6.7 6 0 0 0-6.7-6Z"/>'],
        // Stielbasis: the bulb in its volva
        'subject-stem-base' => ['path' => '<path d="M10.5 3v10.5M13.5 3v10.5"/><path d="M10.5 13.5c-2 .6-3.2 2.1-3.2 3.7 0 1.7 2.1 2.8 4.7 2.8s4.7-1.1 4.7-2.8c0-1.6-1.2-3.1-3.2-3.7"/><path d="M7.6 16.2c-1.2-.4-2.1-1.4-2.4-2.6M16.4 16.2c1.2-.4 2.1-1.4 2.4-2.6"/>'],
        // Velum: the ring on the stem — macro and micro alike
        'subject-velum' => ['path' => '<path d="M4.5 9a7.5 5.5 0 0 1 15 0Z"/><path d="M10.6 9v11.5M13.4 9v11.5"/><path d="M10.6 12.3 8.3 15.6h7.4l-2.3-3.3"/>'],
        // Makrochemie: a pipette and its drop
        'subject-chemistry' => ['path' => '<path d="M9.8 7V4.3a2.2 2.2 0 0 1 4.4 0V7"/><path d="M8.8 7h6.4"/><path d="M10.6 7v6.5l1.4 2 1.4-2V7"/><path d="M12 18.2c-1.1 1.4-1.6 2.1-1.6 2.7a1.6 1.6 0 0 0 3.2 0c0-.6-.5-1.3-1.6-2.7Z"/>'],
        // Sporenpulver: a print on paper
        'subject-spore-print' => ['path' => '<rect x="3.5" y="3.5" width="17" height="17" rx="1.5"/><circle cx="12" cy="12" r="5.5" fill="currentColor" fill-opacity=".35"/><circle cx="12" cy="12" r="1.3"/>'],
        // Makrochemie SPP (Sporenpulver): the print with a drop
        'subject-spore-print-chemistry' => ['path' => '<rect x="2.5" y="6.5" width="15" height="15" rx="1.5"/><circle cx="10" cy="14" r="4.3" fill="currentColor" fill-opacity=".35"/><path d="M19.5 2.3c1.3 1.7 2.3 2.9 2.3 4.1a2.3 2.3 0 0 1-4.6 0c0-1.2 1-2.4 2.3-4.1Z"/>'],
        // Exsikkat: the voucher box with its label
        'subject-exsiccate' => ['path' => '<path d="M4.5 8.5h15V20h-15z"/><path d="M3 5h18v3.5H3z"/><path d="M9 12.5h6v3H9z"/>'],
        // Übersichtsschnitt: a whole fruit body in section, at low power
        'subject-overview' => ['path' => '<path d="M2.5 5.5c.8 6.5 4.5 10 9.5 10s8.7-3.5 9.5-10"/><path d="M6.3 5.5c.8 4 3 6.2 5.7 6.2s4.9-2.2 5.7-6.2"/><path d="M2.5 5.5h3.8M17.7 5.5h3.8"/><path d="M10.4 15.5v5h3.2v-5"/><path d="M8.5 20.5h7"/>'],
        // Hymenium: a palisade of clubs
        'subject-hymenium' => ['path' => '<path d="M3 20.5h18"/><path d="M3.8 20v-8a1.3 1.3 0 0 1 2.6 0v8M7.6 20v-10a1.3 1.3 0 0 1 2.6 0v10M11.4 20v-7a1.3 1.3 0 0 1 2.6 0v7M15.2 20v-9.5a1.3 1.3 0 0 1 2.6 0v9.5M18.6 20v-7.5a1.3 1.3 0 0 1 2.6 0v7.5"/>'],
        // Basidien: a club with four sterigmata
        /* HEAD DOWN (22.11.2026: „Oberseite nach unten … sie zeigt
           momentan nach oben und sie soll nach unten zeigen") — the same
           drawing mirrored top to bottom, the sterigmata pointing down. */
        'subject-basidia' => ['path' => '<path d="M10 3v7.5C8.2 11.4 7.5 13 7.5 14.5a4.5 4.5 0 0 0 9 0c0-1.5-.7-3.1-2.5-4V3"/><path d="M8.6 17.8 7.3 20.5M11 18.9l-.4 2.8M13 18.9l.4 2.8M15.4 17.8l1.3 2.7"/>'],
        // Cheilozystiden: a gill, the cystidia at its edge
        'subject-cheilocystidia' => ['path' => '<path d="M3 3.5h18"/><path d="M9 3.5 12 16l3-12.5"/><path transform="translate(12 16) rotate(180) scale(0.85)" d="M-1.7 0c0-2.3.8-3.1 1-4.5.1-.9 0-1.6.2-2.1.2-.6.8-.6 1 0 .2.5.1 1.2.2 2.1.2 1.4 1 2.2 1 4.5"/><path transform="translate(11.4 14.6) rotate(-140) scale(0.7)" d="M-1.7 0c0-2.3.8-3.1 1-4.5.1-.9 0-1.6.2-2.1.2-.6.8-.6 1 0 .2.5.1 1.2.2 2.1.2 1.4 1 2.2 1 4.5"/><path transform="translate(12.6 14.6) rotate(140) scale(0.7)" d="M-1.7 0c0-2.3.8-3.1 1-4.5.1-.9 0-1.6.2-2.1.2-.6.8-.6 1 0 .2.5.1 1.2.2 2.1.2 1.4 1 2.2 1 4.5"/>'],
        // Pleurozystiden: a gill, the cystidia on its faces
        'subject-pleurocystidia' => ['path' => '<path d="M3 3.5h18"/><path d="M9 3.5 12 21l3-17.5"/><path transform="translate(9.6 7.5) rotate(-100) scale(0.8)" d="M-1.7 0c0-2.3.8-3.1 1-4.5.1-.9 0-1.6.2-2.1.2-.6.8-.6 1 0 .2.5.1 1.2.2 2.1.2 1.4 1 2.2 1 4.5"/><path transform="translate(10.6 14) rotate(-100) scale(0.8)" d="M-1.7 0c0-2.3.8-3.1 1-4.5.1-.9 0-1.6.2-2.1.2-.6.8-.6 1 0 .2.5.1 1.2.2 2.1.2 1.4 1 2.2 1 4.5"/><path transform="translate(14.4 7.5) rotate(100) scale(0.8)" d="M-1.7 0c0-2.3.8-3.1 1-4.5.1-.9 0-1.6.2-2.1.2-.6.8-.6 1 0 .2.5.1 1.2.2 2.1.2 1.4 1 2.2 1 4.5"/><path transform="translate(13.4 14) rotate(100) scale(0.8)" d="M-1.7 0c0-2.3.8-3.1 1-4.5.1-.9 0-1.6.2-2.1.2-.6.8-.6 1 0 .2.5.1 1.2.2 2.1.2 1.4 1 2.2 1 4.5"/>'],
        // Pileozystiden: the cap, the cystidia on top
        'subject-pileocystidia' => ['path' => '<path d="M3 15a9 7 0 0 1 18 0Z"/><path d="M10.6 15v6M13.4 15v6"/><path transform="translate(12 8) rotate(0) scale(0.8)" d="M-1.7 0c0-2.3.8-3.1 1-4.5.1-.9 0-1.6.2-2.1.2-.6.8-.6 1 0 .2.5.1 1.2.2 2.1.2 1.4 1 2.2 1 4.5"/><path transform="translate(7.5 8.95) rotate(-35) scale(0.8)" d="M-1.7 0c0-2.3.8-3.1 1-4.5.1-.9 0-1.6.2-2.1.2-.6.8-.6 1 0 .2.5.1 1.2.2 2.1.2 1.4 1 2.2 1 4.5"/><path transform="translate(16.5 8.95) rotate(35) scale(0.8)" d="M-1.7 0c0-2.3.8-3.1 1-4.5.1-.9 0-1.6.2-2.1.2-.6.8-.6 1 0 .2.5.1 1.2.2 2.1.2 1.4 1 2.2 1 4.5"/>'],
        // Kaulozystiden: the stem, the cystidia at its side
        'subject-caulocystidia' => ['path' => '<path d="M4 7.5a8 4.5 0 0 1 16 0Z"/><path d="M10.4 7.5V21M13.6 7.5V21"/><path transform="translate(13.6 11.5) rotate(90) scale(0.75)" d="M-1.7 0c0-2.3.8-3.1 1-4.5.1-.9 0-1.6.2-2.1.2-.6.8-.6 1 0 .2.5.1 1.2.2 2.1.2 1.4 1 2.2 1 4.5"/><path transform="translate(10.4 14.5) rotate(-90) scale(0.75)" d="M-1.7 0c0-2.3.8-3.1 1-4.5.1-.9 0-1.6.2-2.1.2-.6.8-.6 1 0 .2.5.1 1.2.2 2.1.2 1.4 1 2.2 1 4.5"/><path transform="translate(13.6 17.5) rotate(90) scale(0.75)" d="M-1.7 0c0-2.3.8-3.1 1-4.5.1-.9 0-1.6.2-2.1.2-.6.8-.6 1 0 .2.5.1 1.2.2 2.1.2 1.4 1 2.2 1 4.5"/>'],
        // HDS: the cap in section, its uppermost layer
        'subject-cuticle' => ['path' => '<path d="M2.5 19h19"/><path d="M2.5 19a9.5 9.5 0 0 1 19 0"/><path d="M5 19a7 7 0 0 1 14 0"/><path d="M5.24 17.19L2.82 16.54M6.27 14.98L4.22 13.55M7.98 13.27L6.55 11.22M10.19 12.24L9.54 9.82M12.61 12.03L12.83 9.54M14.96 12.66L16.01 10.39M16.95 14.05L18.72 12.28M18.34 16.04L20.61 14.99"/>'],
        // Asci: a tube with its spores in a row
        'subject-asci' => ['path' => '<path d="M9 21V6a3 3 0 0 1 6 0v15"/><path d="M10.6 4.4h2.8"/><circle cx="12" cy="7.6" r="1.25"/><circle cx="12" cy="11" r="1.25"/><circle cx="12" cy="14.4" r="1.25"/><circle cx="12" cy="17.8" r="1.25"/>'],
        // Paraphysen: thin threads with clavate tips
        'subject-paraphyses' => ['path' => '<path d="M6.5 21V9.4M12 21V7.4M17.5 21V10.4"/><ellipse cx="6.5" cy="7.6" rx="1.4" ry="1.9"/><ellipse cx="12" cy="5.6" rx="1.4" ry="1.9"/><ellipse cx="17.5" cy="8.6" rx="1.4" ry="1.9"/><path d="M6.5 15h0M12 13h0M17.5 15.5h0" stroke-width="3"/>'],
        // Randhaare: the cup with hairs at its rim
        'subject-marginal-hairs' => ['path' => '<path d="M4 7c0 7 3.6 12 8 12s8-5 8-12"/><path d="M4 7h16"/><path d="M4 7 2.6 4.4M4.2 9.6l-2.6-.9M5 12.4l-2.4.6M20 7l1.4-2.6M19.8 9.6l2.6-.9M19 12.4l2.4.6"/>'],
        // Excipulum: the cup in section, cells in its wall
        'subject-excipulum' => ['path' => '<path d="M3 7c0 7 4 12 9 12s9-5 9-12"/><path d="M7.4 7c0 4.6 2.1 8 4.6 8s4.6-3.4 4.6-8"/><path d="M3 7h4.4M16.6 7H21"/><circle cx="5.3" cy="10.8" r="1"/><circle cx="7.7" cy="14.6" r="1"/><circle cx="12" cy="16.9" r="1"/><circle cx="16.3" cy="14.6" r="1"/><circle cx="18.7" cy="10.8" r="1"/>'],
        // Schnallen: a hypha, its septum, the clamp over it
        'subject-clamps' => ['path' => '<path d="M2 11h20M2 15h20"/><path d="M12 11v4"/><path d="M9 11c0-2.8 1.5-4.2 3.5-4.2s3.2 1.5 3.2 3.4"/>'],
        // Generative Hyphen: thin-walled, with septa
        'subject-generative-hyphae' => ['path' => '<path d="M2 10h20M2 14h20"/><path d="M7 10v4M12 10v4M17 10v4"/>'],
        // Skeletthyphen: thick-walled, no septa
        'subject-skeletal-hyphae' => ['path' => '<path d="M2 9.2h20M2 14.8h20"/><path d="M2 11.2h20M2 12.8h20" stroke-width="1"/>'],
        // Bindehyphen: narrow and much branched
        'subject-binding-hyphae' => ['path' => '<path d="M3 19c3-1 4-4 6-6s5-2 7-5 3-4 5-4"/><path d="M9 13c-1.2-2-1.2-4.2 0-6.5M15.2 8.8c2 .5 3.9 2.1 4.8 4.6M6 16.3c-1.2 1.7-1.3 3.2-.3 4.7"/>'],
        // Capillitium: a puffball, the threads inside
        'subject-capillitium' => ['path' => '<path d="M4 12.5a8 8 0 0 1 16 0c0 4.2-3.6 7.5-8 7.5s-8-3.3-8-7.5Z"/><path d="M7 11.5c2-2 3 2 5 0s3 2 5 0M7.3 15c2-1.6 3.4 1.6 5.4 0s2.6 1.3 3.8 0"/>'],
        // Sklerotium: a lumpy tuber, cells hinted in it, a thin fruit body rising from it — macro and micro alike
        'subject-sclerotium' => ['path' => '<path d="M5 16.5c-.6-2.6 1-4.9 3.3-5.4 1-.2 1.6-1.2 2.9-1.4 1.6-.3 2.6.7 3.9.6 2.6-.2 4.6 1.6 4.6 4 0 1.6-.8 2.6-.6 3.6.2 1.4-1.4 2.6-3.6 2.4-1.4-.1-2.2.6-3.8.6-1.8 0-2.7-.8-4.1-.9C6.3 19.8 5.3 18 5 16.5Z"/><path d="M11.6 9.9c-.1-1.7-.4-3.2-.7-4.7"/><path d="M8.5 5.3a2.4 1.7 0 0 1 4.8 0Z"/><path d="M9 15.6h0M12.4 14.3h0M15.6 15.8h0M11.6 18h0" stroke-width="1.9"/>'],
        // Konidien: a conidiophore, a chain of conidia on each of its three tips
        'subject-conidia' => ['path' => '<path d="M12 21v-8.5"/><path d="M12 12.5 8.9 10.1M12 12.5l3.1-2.4M12 12.5v-1.1"/><circle cx="8.1" cy="8.7" r="1.2"/><circle cx="7.4" cy="6" r="1.2"/><circle cx="6.7" cy="3.3" r="1.2"/><circle cx="12" cy="9.9" r="1.2"/><circle cx="12" cy="7.2" r="1.2"/><circle cx="12" cy="4.5" r="1.2"/><circle cx="15.9" cy="8.7" r="1.2"/><circle cx="16.6" cy="6" r="1.2"/><circle cx="17.3" cy="3.3" r="1.2"/>'],
        // Text: the find details, a page of lines
        'subject-text' => ['path' => '<rect x="5" y="3.5" width="14" height="17" rx="1.5"/><path d="M8 8h8M8 11.5h8M8 15h5"/>'],
        // --- Tabs of the administration ----------------------------------
        //
        // EVERY TAB CARRIES ONE, and they must differ FROM ONE ANOTHER —
        // not merely mean something each on its own. They stand side by
        // side in one row, partly in the same bar, and what resembles
        // another there is worthless: whoever tells "Freunde" from
        // "Arbeitsgruppen" only after looking twice ends up reading the
        // text anyway. That is why the three people tabs carry three
        // different pictures (one person, two persons, three persons)
        // instead of the same thing three times in nuances.
        //
        // Four tabs need no icon of their own: settings takes the gear,
        // the trash the bin, elevations the mountain, sheets the map sheet
        // (a Messtischblatt IS one) and municipalities the house. A second
        // icon for the same thing would be exactly what this file
        // prevents.

        // Mein Bereich: one person.
        'person' => [
            'path' => '<circle cx="12" cy="8" r="3.6"/>'
                    . '<path d="M4.8 20.5c0-3.9 3.2-6.2 7.2-6.2s7.2 2.3 7.2 6.2"/>',
        ],

        /* The container chat. A rounded rectangle with a tail at the
           bottom left — the shape everybody reads as "somebody said
           something", and the one drawing in this library that needs no
           explanation.

           NO DOTS INSIDE IT. Three dots would mean "is typing" in every
           other application, and this bubble means "there is a
           conversation here". At sixteen pixels they would also be three
           grey specks that only make the shape restless. The mark for
           unread messages sits OUTSIDE, at the corner of the button
           (.chat-button.has-unread) — that way it can appear and vanish
           without the icon changing. */
        'speech-bubble' => [
            'path' => '<path d="M4.5 5.5h15a1.5 1.5 0 0 1 1.5 1.5v8a1.5 1.5 0 0 1-1.5 1.5H9.5L5.5 20v-3.5H4.5A1.5 1.5 0 0 1 3 15V7a1.5 1.5 0 0 1 1.5-1.5Z"/>',
        ],
        /* THE SAME BUBBLE WITH TWO LINES OF TEXT IN IT — „there is a
           conversation here" in the chat list, beside the empty bubble
           for a friend one has not written with yet (27.11.2026). Lines,
           not dots: three dots say „is typing" (see above). */
        'speech-bubble-text' => [
            'path' => '<path d="M4.5 5.5h15a1.5 1.5 0 0 1 1.5 1.5v8a1.5 1.5 0 0 1-1.5 1.5H9.5L5.5 20v-3.5H4.5A1.5 1.5 0 0 1 3 15V7a1.5 1.5 0 0 1 1.5-1.5Z"/>'
                    . '<path d="M7.5 9.5h9M7.5 12.5h6"/>',
        ],

        // Freunde: two — the second stands cropped behind, otherwise both
        // would be half the size of the single person next door.
        'people' => [
            'path' => '<circle cx="9.5" cy="8.5" r="3.2"/>'
                    . '<path d="M3 20.5c0-3.5 2.9-5.6 6.5-5.6s6.5 2.1 6.5 5.6"/>'
                    . '<path d="M16 5.8a3.2 3.2 0 0 1 0 5.6M17.8 15.3c1.9.7 3.2 2.4 3.2 5.2"/>',
        ],

        // Arbeitsgruppen: three heads, one in front. More than three would
        // be a row of blots at sixteen pixels.
        'group' => [
            'path' => '<circle cx="12" cy="7.5" r="2.9"/>'
                    . '<circle cx="4.8" cy="10" r="2.2"/><circle cx="19.2" cy="10" r="2.2"/>'
                    . '<path d="M6.8 20.5c0-3.1 2.3-5 5.2-5s5.2 1.9 5.2 5"/>',
        ],

        // Gemeinsam (the roof over friends, groups and containers): not a
        // fourth pile of people, but the connectedness itself — three
        // nodes that hang together.
        'network' => [
            'path' => '<circle cx="12" cy="5" r="2.4"/>'
                    . '<circle cx="5" cy="18.5" r="2.4"/><circle cx="19" cy="18.5" r="2.4"/>'
                    . '<path d="M10.8 7.1 6.2 16.4M13.2 7.1l4.6 9.3M7.4 18.5h9.2"/>',
        ],

        // Container: a crate several people put things into.
        'container' => [
            'path' => '<path d="M3.5 7.5 12 3.5l8.5 4v9L12 20.5 3.5 16.5z"/>'
                    . '<path d="M3.5 7.5 12 11.5l8.5-4M12 11.5v9"/>',
        ],

        // Offline: the device itself — this is about what sits on THIS
        // phone. A crossed-out cloud says "no network", and that is the
        // occasion, not the thing.
        'device' => [
            'path' => '<rect x="6.5" y="2.5" width="11" height="19" rx="2.2"/>'
                    . '<path d="M10.5 5.5h3"/>',
        ],

        // The same device, a red cross over it — "remove from device", not
        // "delete record" (the bin stands for that). The cross carries its
        // red fixed in the icon (var(--error), no hex) because here it
        // belongs to the MEANING like the fill does with 'area' —
        // unlike the bin, whose red comes from the calling site, BECAUSE
        // it also stands where red does not (yet) fit (see its comment).
        // A second device icon, because the cross would otherwise also lie
        // where "Offline" means only the tab.
        'device-remove' => [
            'path' => '<rect x="6.5" y="2.5" width="11" height="19" rx="2.2"/>'
                    . '<path d="M10.5 5.5h3"/>'
                    // Longer than the device is wide (juts out left/right)
                    // and thicker than its stroke — otherwise the cross
                    // drowns in the outline instead of lying over it.
                    . '<path d="M4 5.5 20 19.5M20 5.5 4 19.5" stroke="var(--error)" stroke-width="2.6"/>',
        ],

        // Offline areas: the outline. The same idea as a point's area, but
        // WITHOUT fill — there the area is the thing, here it is the
        // boundary you draw corner by corner.
        'outline' => [
            'path' => '<path d="M4 10.5 11.5 4l8 5-2.5 9.5-10.5 1z"/>',
        ],

        // Create new — in front of "Neues Gebiet", but could stand in
        // front of any "new" button. No circle around it: that would come
        // from the button's own surface, a second one here would be a
        // repetition.
        'plus' => [
            'path' => '<path d="M12 5v14M5 12h14"/>',
        ],
        /* The valid one among several edits of a picture
           (11.11.2026: „Wir markieren sie mit einem Stern"). Filled where it
           marks (.photo-versions), an outline wherever else it may go. */
        'star' => [
            'path' => '<path d="M12 3.5l2.6 5.4 5.9.8-4.3 4.1 1 5.8L12 16.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.8z" stroke-linejoin="round"/>',
        ],

        // The plus without its upright — the third state of a tick box:
        // SOME of the branch below is on (the sidebar's tick column,
        // 25.09.2026). The arm of the plus, so the two read as one family
        // wherever they meet.
        'minus' => [
            'path' => '<path d="M5 12h14"/>',
        ],

        // Offline data: a store. The name lists sit on the device as a
        // stock, not as a map and not as a point.
        'storage' => [
            'path' => '<ellipse cx="12" cy="6" rx="7.5" ry="2.8"/>'
                    . '<path d="M4.5 6v12c0 1.6 3.4 2.8 7.5 2.8s7.5-1.2 7.5-2.8V6"/>'
                    . '<path d="M4.5 12c0 1.6 3.4 2.8 7.5 2.8s7.5-1.2 7.5-2.8"/>',
        ],

        // Cloud: one's own storage at the provider. A cloud, and a whole
        // one — the crossed-out one next to it would mean "no network",
        // and 'device' already stands for that. The arrow in it points UP:
        // here something goes out, away from the server, into the user's
        // storage.
        'cloud' => [
            'path' => '<path d="M6.8 19.5a4.3 4.3 0 0 1-.5-8.6 5.6 5.6 0 0 1 10.8-1.4 4.1 4.1 0 0 1 .6 8.1"/>'
                    . '<path d="M12 20.5v-8M9 15.2l3-3 3 3"/>',
        ],

        /* The cloud with an exclamation mark — an edit NOT YET in the cloud
           (14.11.2026, assets/js/editsaves.js): the same cloud as above,
           closed below, the mark where the arrow was. */
        'cloud-unsaved' => [
            'path' => '<path d="M6.8 19.5a4.3 4.3 0 0 1-.5-8.6 5.6 5.6 0 0 1 10.8-1.4 4.1 4.1 0 0 1 .6 8.1z"/>'
                    . '<path d="M12 10.5v4.2M12 17.2v.01"/>',
        ],

        /* A folder and a sheet — the find's other files (05.10.2026,
           Belege → Dateien): a folder dropped in stays a folder in the
           list, a file is a sheet with its corner turned. Getting one onto
           the device is 'import', the arrow down into the tray. */
        'folder' => [
            'path' => '<path d="M3.5 7.5A1.5 1.5 0 0 1 5 6h4.2l2 2.2H19a1.5 1.5 0 0 1 1.5 1.5v8.3A1.5 1.5 0 0 1 19 19.5H5A1.5 1.5 0 0 1 3.5 18Z"/>',
        ],
        'file' => [
            'path' => '<path d="M6.5 3.5h7l4 4V19a1.5 1.5 0 0 1-1.5 1.5H6.5A1.5 1.5 0 0 1 5 19V5a1.5 1.5 0 0 1 1.5-1.5Z"/>'
                    . '<path d="M13.5 3.5v4h4"/>',
        ],

        // Organisieren: lines growing shorter — ordered is what has an order.
        // Three strokes, more the size does not carry.
        // A hiker mid-stride, stick in hand — the tour
        // (23.08.2026: "bei Tour bitte einen Wanderer"). Not a route line:
        // the tour is the outing, and the outing is a person walking.
        'hiker' => [
            'path' => '<circle cx="12.6" cy="4" r="1.9"/>'
                    . '<path d="M12.2 7.3 11.2 13.2 14 16 14 21.2"/>'
                    . '<path d="M11.2 13.2 9.2 17 7.2 20.6"/>'
                    . '<path d="M12 8.8 16 11.2"/>'
                    . '<path d="M17 8.4 15.6 21.2"/>',
        ],

        'list' => [
            'path' => '<path d="M4 6.5h16M4 12h11M4 17.5h6"/>',
        ],

        // Import: the arrow points down INTO the tray. The database dump
        // has the same arrow the other way round — that is intended, the
        // two are the way there and the way back.
        'import' => [
            'path' => '<path d="M12 3.5v10m0 0 4-4m-4 4-4-4"/>'
                    . '<path d="M4.5 15v4a1.5 1.5 0 0 0 1.5 1.5h12a1.5 1.5 0 0 0 1.5-1.5v-4"/>',
        ],

        /* Fetch anew: the circular arrow, and it belongs beside 'import'
           because it says the same thing once more — "onto the device",
           only for something already lying there. Two thirds of a circle
           with one arrowhead, not two: a double arrow reads as "exchange"
           or "sync in both directions", and nothing here goes back up. */
        'refresh' => [
            'path' => '<path d="M20 12a8 8 0 1 1-2.34-5.66"/>'
                    . '<path d="M20 4.5V10h-5.5"/>',
        ],

        // Database dump: the same arrow outwards (see 'import').
        'dump' => [
            'path' => '<path d="M12 20.5v-10m0-.5 4 4m-4-4-4 4"/>'
                    . '<path d="M4.5 9V5A1.5 1.5 0 0 1 6 3.5h12A1.5 1.5 0 0 1 19.5 5v4"/>',
        ],

        // Administration: sliders. The gear is taken by the settings, and
        // two gears side by side would not be two things.
        'sliders' => [
            'path' => '<path d="M3.5 8.5h17M3.5 16h17"/>'
                    . '<circle cx="9" cy="8.5" r="2.4"/><circle cx="15" cy="16" r="2.4"/>',
        ],

        // User administration: the ID card — not people again, but what is
        // administered here: accounts with role and quota.
        // A ruler, diagonal with tick marks — the measuring settings
        // (28.08.2026). Diagonal like the calibration line it stands for,
        // not like a toolbar ruler lying flat.
        /* The two measuring gestures of the photo window
           (29.08.2026: icon buttons, no words): a diagonal line with its
           two endpoint dots — the distance measure as one draws it —
           and the four-tap rectangle. */
        'measure-path' => [
            'path' => '<path d="M4 18l4.5-8 4 5L19 6"/>'
                    . '<circle cx="4" cy="18" r="1.6"/><circle cx="19" cy="6" r="1.6"/>',
        ],
        'annotate' => [
            'path' => '<path d="M6 6h12M12 6v13"/>',
        ],
        /* The arrow that points at something in the picture
           (05.10.2026: „ein extra Tool, das Pfeile zeichnet") — slanted, as
           one draws it onto a photo, so it does not read as „next" like
           arrow-right. */
        'annotate-arrow' => [
            'path' => '<path d="M5 19 18 6"/><path d="M10 6h8v8"/>',
        ],
        /* The box of a fixed size (06.10.2026): a square over a
           dimension line — a frame that is told how big it is, not drawn
           to it. */
        'annotate-box' => [
            'path' => '<rect x="6" y="3.5" width="12" height="12" rx=".5"/>'
                    . '<path d="M6 20h12M6 18.5v3M18 18.5v3"/>',
        ],
        /* THE POINTER, and it is the tool that does NOTHING
           (08.09.2026: „bei einem Touch Gerät nirgends den Messmodus
           beenden. Man kann immer nur umschalten"). At the desk the right
           click disarms; a finger has no right click, and pressing the
           armed tool a second time is only findable for whoever already
           knows it. So the old drawing-program arrow joins the row: the
           state in which a tap means nothing but looking. */
        'pointer' => [
            'path' => '<path d="M6 3.4 18.6 13.1 12.5 13.8 15.8 20 13.3 21.3 10 15.1 6 19.2Z"/>',
        ],
        /* A HAND THAT PICKS SOMETHING UP — the pickers beside Geruch,
           Geschmack and Sporenpulverfarbe (findtraits.js;
           12.11.2026: „eine Hand, die was aufhebt"). His own drawing, made
           on 512 and set onto this grid: the back of the hand, two fingers
           spread to the left, index finger and thumb running together in a
           loop. A finer line than the rest („die Strichstärke etwas
           dünner"): at this size the fingers lie close, and the usual
           stroke filled the gaps between them. */
        'pick' => [
            'path' => '<path d="M12.8 6.14H10.31A2.81 2.81 0 0 0 7.5 8.95V15.7A1.69 1.69 0 0 0 10.88 15.7V12.42A1.5 1.5 0 0 1 13.88 12.42'
                    . 'C13.88 13.36 10.88 15.23 10.88 16.17A1.69 1.69 0 0 0 13.83 17.02L16.17 14.67H18.05A1.88 1.88 0 0 0 19.45 14.06'
                    . 'L21.19 12.33V6.14H14.39"/>'
                    . '<path d="M9.38 6.14H4.5A1.69 1.69 0 0 0 4.5 9.52H7.5"/>'
                    . '<path d="M8.34 7.45 4.88 10.92"/>'
                    . '<path d="M4.12 11.86 3.28 12.7A1.5 1.5 0 0 0 5.39 14.81L7.5 12.7"/>',
            'stroke' => 1.3,
        ],
        'measure-line' => [
            'path' => '<path d="M5 19 19 5"/>'
                    . '<circle cx="5" cy="19" r="1.8"/><circle cx="19" cy="5" r="1.8"/>',
        ],
        'measure-rect' => [
            'path' => '<rect x="4" y="7" width="16" height="10" rx="1"/>',
        ],
        // The core area on the microscope's live picture: a dashed circle, as it is drawn there.
        'core-area' => [
            'path' => '<circle cx="12" cy="12" r="8.5" stroke-dasharray="3.2 2.7"/>',
        ],
        /* Mirroring a spore's tile in the statistics
           (04.10.2026): two shapes facing each other across a dashed axis
           — left and right swapped, or top and bottom. */
        'flip-h' => [
            'path' => '<path d="M12 3v18" stroke-dasharray="2 2.2"/>'
                    . '<path d="M9.5 6.5v11H3.5z"/><path d="M14.5 6.5v11h6z"/>',
        ],
        'flip-v' => [
            'path' => '<path d="M3 12h18" stroke-dasharray="2 2.2"/>'
                    . '<path d="M6.5 9.5h11V3.5z"/><path d="M6.5 14.5h11v6z"/>',
        ],
        /* The cutout with a measure inside (04.10.2026): the
           frame's four corners and, within, the bent line one traces —
           the ascus, the allantoid spore. Its own tool beside „Länge ×
           Breite" since then. */
        'measure-cutout' => [
            'path' => '<path d="M4 8V4h4M16 4h4v4M20 16v4h-4M8 20H4v-4"/>'
                    . '<path d="M7.5 15.5c1.5-4 5.5-6.5 9-7"/>',
        ],
        /* THE CYSTIDIUM MEASURED IN ITS PARTS (05.11.2026): the frame's
           corners around a cystidium with a head and a belly, three short
           strokes across where A, M and B — or head, neck and belly — are
           taken. */
        'measure-cystid' => [
            'path' => '<path d="M4 8V4h4M16 4h4v4M20 16v4h-4M8 20H4v-4"/>'
                    . '<path d="M12 6.2a1.8 1.8 0 1 1 0 .1M12 8v2.5c-2.6 1.5-2.6 6.5 0 7.5 2.6-1 2.6-6 0-7.5"/>'
                    . '<path d="M9 14.5h6"/>',
        ],
        /* THE SECTOR (18.11.2026): a cap seen a little askew —
           an ellipse — with a slice cut from its middle, the piece one
           counts lamellae in. */
        'measure-sector' => [
            'path' => '<ellipse cx="12" cy="12" rx="9" ry="6.5"/>'
                    . '<path d="M12 12 21 12M12 12 16.5 6.4"/>',
        ],
        /* THE PLATE (06.10.2026): an A4 sheet cut into rectangles — what a
           plate is, as Matthias Reul laid them out. And the editor's own
           three tools: a cell split side by side, split one above the
           other, and the four-way cross that carries a cell elsewhere. */
        'plate' => [
            'path' => '<rect x="5" y="3" width="14" height="18" rx="1.2"/>'
                    . '<path d="M5 10h14M12 10v11M12 15.5h7"/>',
        ],
        // Two corners crossing — the photo window's cutout tool (the plate's
        // „Ausschnitt ändern" wears the four arrows, `move`, since 08.10.2026:
        // the corners read as Photoshop's crop).
        'crop' => [
            'path' => '<path d="M7 3v13a1 1 0 0 0 1 1h13"/><path d="M3 7h13a1 1 0 0 1 1 1v13"/>',
        ],
        'split-columns' => [
            'path' => '<rect x="3.5" y="5" width="17" height="14" rx="1.5"/><path d="M12 5v14"/>',
        ],
        'split-rows' => [
            'path' => '<rect x="3.5" y="5" width="17" height="14" rx="1.5"/><path d="M3.5 12h17"/>',
        ],
        // An odour: three wavy rising lines (findtraits.js, the plate's details).
        'scent' => [
            'path' => '<path d="M8 20c-1.6-2.2 1.6-3.8 0-6s1.6-3.8 0-6M12 20c-1.6-2.2 1.6-3.8 0-6s1.6-3.8 0-6'
                    . 'M16 20c-1.6-2.2 1.6-3.8 0-6s1.6-3.8 0-6"/>',
        ],
        // A taste: a drop.
        'drop' => [
            'path' => '<path d="M12 3.5c3.2 4.2 6 7.4 6 10.6a6 6 0 0 1-12 0c0-3.2 2.8-6.4 6-10.6Z"/>',
        ],
        // A grip to drag a row of a list into another place (plates.js,
        // sortable ticks): two columns of three dots, filled — at this size
        // a ring is a smudge.
        'grip' => [
            'path' => '<g fill="currentColor" stroke="none">'
                    . '<circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/>'
                    . '<circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>'
                    . '<circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></g>',
        ],
        'move' => [
            'path' => '<path d="M12 3v18M3 12h18"/>'
                    . '<path d="m9.5 5.5 2.5-2.5 2.5 2.5M9.5 18.5l2.5 2.5 2.5-2.5'
                    . 'M5.5 9.5 3 12l2.5 2.5M18.5 9.5 21 12l-2.5 2.5"/>',
        ],
        /* STRAIGHTENING (09.11.2026, for „Ausrichten" in the
           photo window: „in Photoshop gibt es da auch ein Symbol für"):
           Photoshop's straighten tool is a spirit level, and so is this —
           a level lying slightly askew, its vial in the middle, over the
           dashed line it is to be set straight along. */
        'straighten' => [
            'path' => '<path d="M3 19.5h18" stroke-dasharray="2 2.2"/>'
                    . '<g transform="rotate(-14 12 10.5)"><rect x="3" y="7.5" width="18" height="6" rx="1.5"/>'
                    . '<rect x="9.5" y="9" width="5" height="3" rx="1.5"/></g>',
        ],
        'ruler' => [
            'path' => '<rect x="2.8" y="13.2" width="22" height="6.5" rx="1.4"'
                    . ' transform="rotate(-38 4 16.5)"/>'
                    . '<path d="m8.2 12.4 1.7 2.2M11.6 9.8l1.7 2.2M15 7.2l1.7 2.2"/>',
        ],
        'badge' => [
            'path' => '<rect x="3" y="5" width="18" height="14" rx="2.2"/>'
                    . '<circle cx="9.5" cy="10.8" r="2.2"/>'
                    . '<path d="M6.2 16c0-1.9 1.5-3 3.3-3s3.3 1.1 3.3 3M15.5 11h3"/>',
        ],

        // The find number: the number sign, slightly slanted like a
        // handwritten one. It stands in the point list before the minted
        // (or plain database) number — a bare "AM-2026-0001" between date
        // and municipality would read as one more mystery code, the hash
        // says "this is its number".
        'hash' => [
            'path' => '<path d="M9.8 4 7.6 20M16.4 4l-2.2 16M4.5 9h16M3.5 15h16"/>',
            'stroke' => 2,
        ],

        // „Ausrichten" of the layers tool (blend.js, the head „Ebenen",
        // 14.11.2026): two pictures of one thing lying askew — one whole,
        // one dashed and shifted — and the arrow that brings the dashed
        // one home onto the other.
        'align-layers' => [
            'path' => '<rect x="3.5" y="3.5" width="12" height="12" rx="1.5"/>'
                    . '<rect x="8.5" y="8.5" width="12" height="12" rx="1.5" stroke-dasharray="2.2 2"/>'
                    . '<path d="M20.5 3.5 17 7M20.5 3.5h-3M20.5 3.5v3"/>',
        ],

        // Migrations: layers building on one another — that is exactly
        // what they are, and they run in this order.
        'stack' => [
            'path' => '<path d="M12 3.5 3.5 7.5 12 11.5l8.5-4z"/>'
                    . '<path d="M3.5 12 12 16l8.5-4M3.5 16.5 12 20.5l8.5-4"/>',
        ],

        // Backfilling: the pencil. Here, what a point is missing gets
        // filled in.
        'pencil' => [
            'path' => '<path d="M16.3 3.6 20.4 7.7 8.4 19.7l-5 .9.9-5z"/>'
                    . '<path d="M13.8 6.1 17.9 10.2"/>',
        ],
        // The dead-end road sign (Zeichen 357): a sign whose road ends in a
        // bar — the T. For a couplet where no answer fits
        // (09.10.2026: „das X-Symbol passt nicht gut … das Straßenschild für
        // Sackgasse sieht doch aus wie ein T").
        'dead-end' => [
            'path'   => '<rect x="3.5" y="3" width="17" height="18" rx="2.5"/>'
                      . '<path d="M10 21v-10.5M14 21v-10.5"/>'
                      . '<rect x="7" y="6.5" width="10" height="3" rx=".6"/>',
            'stroke' => 1.6,
        ],
        // A sticky note with its corner folded — the note on a statement of
        // an identification key (09.10.2026: „ein Post-it-Symbol").
        'sticky-note' => [
            'path' => '<path d="M4 4h16v10l-6 6H4z"/>'
                    . '<path d="M14 20v-6h6"/>',
        ],

        // Looking without touching: the eye. The phone's point peek shows
        // it where a shared find grants no writing right — the counterpart
        // of the pencil beside it.
        /* INVERTED (photowindow.js, masksPanelDraw; 09.11.2026): a filled
           field with an oval cut out of it — a mask that acts outside. */
        'invert' => [
            'path' => '<path fill="currentColor" stroke="none" fill-rule="evenodd" d="M5 5h14a1.5 1.5 0 0 1 1.5 1.5v11a1.5 1.5 0 0 1-1.5 1.5H5a1.5 1.5 0 0 1-1.5-1.5v-11A1.5 1.5 0 0 1 5 5Z'
                    . 'M7.5 12a4.5 3.6 0 1 0 9 0a4.5 3.6 0 1 0-9 0Z"/>',
        ],
        // The eye shut: a mask switched off (photowindow.js, masksPanelDraw; 09.11.2026).
        'eye-off' => [
            'path' => '<path d="M2.5 12S6 5.8 12 5.8 21.5 12 21.5 12 18 18.2 12 18.2 2.5 12 2.5 12Z"/>'
                    . '<circle cx="12" cy="12" r="3.1"/><path d="M4 20 20 4"/>',
        ],
        'eye' => [
            'path' => '<path d="M2.5 12S6 5.8 12 5.8 21.5 12 21.5 12 18 18.2 12 18.2 2.5 12 2.5 12Z"/>'
                    . '<circle cx="12" cy="12" r="3.1"/>',
        ],

        // Taxonomy: the branching tree. It IS a hierarchy, and that is the
        // only thing an icon can show of it.
        'tree' => [
            'path' => '<circle cx="12" cy="4.8" r="2.2"/>'
                    . '<circle cx="5.5" cy="19.2" r="2.2"/><circle cx="18.5" cy="19.2" r="2.2"/>'
                    . '<path d="M12 7v4.5M5.5 17V11.5h13V17"/>',
        ],

        // Species: the label. NO mushroom and no leaf — the map is
        // neutral, the topic arises from what the user creates.
        'label' => [
            'path' => '<path d="M11.5 3.5H5a1.5 1.5 0 0 0-1.5 1.5v6.5l9 9 8-8z"/>'
                    . '<circle cx="7.8" cy="7.8" r="1.4"/>',
        ],

        // Statistics: three bars on a baseline.
        'bars' => [
            'path' => '<path d="M3.5 20.5h17"/><path d="M7 17V10.5M12 17V4.5M17 17v-9"/>',
        ],

        // Account: the key. Here stand name, login address and password —
        // the access, not the person.
        'key' => [
            'path' => '<circle cx="7.5" cy="12" r="3.6"/>'
                    . '<path d="M11.1 12h9.4M17.4 12v3.4M20.5 12v2.6"/>',
        ],

        // A spore, for the overview tables of spores in a key (Læssøe's
        // coprinoids, 18.10.2026: „vielleicht sogar mit einem
        // Sporensymbol"): the ellipse leaning as under the microscope, the
        // germ pore flattened at its top and the small apiculus below.
        'spore' => [
            'path' => '<g transform="rotate(-28 12 12)">'
                    . '<path d="M10.6 4.1h2.8c2.6.9 4.4 3.9 4.4 7.6 0 4.3-2.6 7.4-5.8 7.4s-5.8-3.1-5.8-7.4c0-3.7 1.8-6.7 4.4-7.6Z"/>'
                    . '<path d="M11.4 19.1 11 21"/></g>',
            'stroke' => 2.2,
        ],
        /* THE PICTURE NAME „SPOREN" HEAD DOWN (22.11.2026: „Auch das
           Sporenbild gleich mit umdrehen, also Oberseite nach unten"), as the
           basidia beside it: the spore above turned half round (−28° + 180°),
           so it leans as before, the germ pore below and the apiculus on top. A drawing of its own, because
           `spore` stays as it is in the keys' tables and the plate's details. */
        'subject-spores' => [
            'path' => '<g transform="rotate(152 12 12)">'
                    . '<path d="M10.6 4.1h2.8c2.6.9 4.4 3.9 4.4 7.6 0 4.3-2.6 7.4-5.8 7.4s-5.8-3.1-5.8-7.4c0-3.7 1.8-6.7 4.4-7.6Z"/>'
                    . '<path d="M11.4 19.1 11 21"/></g>',
            'stroke' => 2.2,
        ],

        // Appearance: the half-filled circle — light against dark is
        // exactly what gets chosen there.
        'contrast' => [
            'path' => '<circle cx="12" cy="12" r="8.5"/>'
                    . '<path d="M12 3.5a8.5 8.5 0 0 1 0 17z" fill="currentColor"/>',
        ],

        // Oberfläche → Farben: the painter's palette with three dabs —
        // theme and accent are both colours, and contrast is the level
        // above (25.09.2026).
        // „Bearbeiten" → Einstellungen → Licht (13.11.2026, the
        // groups became signs on a rail): the sun — a disc and eight short
        // rays, open enough to stay a sun at the rail's size.
        'sun' => [
            'path' => '<circle cx="12" cy="12" r="4"/>'
                    . '<path d="M12 3v2.2M12 18.8V21M3 12h2.2M18.8 12H21M5.6 5.6l1.6 1.6M16.8 16.8l1.6 1.6M5.6 18.4l1.6-1.6M16.8 7.2l1.6-1.6"/>',
        ],
        // … and Details: a loupe over fine grain — clarity, dehaze and
        // sharpening all work on the small things one looks at closely.
        'details' => [
            'path' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.4 15.4 4.1 4.1"/>'
                    . '<circle cx="8.3" cy="8.6" r=".9" fill="currentColor" stroke="none"/>'
                    . '<circle cx="12.4" cy="8.6" r=".9" fill="currentColor" stroke="none"/>'
                    . '<circle cx="8.3" cy="12.6" r=".9" fill="currentColor" stroke="none"/>'
                    . '<circle cx="12.4" cy="12.6" r=".9" fill="currentColor" stroke="none"/>',
        ],

        // „Ausbessern" → Entfernen (13.11.2026, the kinds on a rail): a wand
        // with three sparks — the AI that invents what lay behind.
        'wand' => [
            'path' => '<path d="m4.5 19.5 10-10"/><path d="m13 8 3 3"/>'
                    . '<path d="M17.5 3v3M16 4.5h3M20 9.5v2M19 10.5h2M10 3.5v2M9 4.5h2"/>',
        ],
        // „Masken" on the rail: the ellipse with its inner, steering line …
        // (No inner ring: an ellipse within an ellipse read as an eye — the
        // eye that shows and hides a mask stands in the same sidebar.)
        'ellipse' => [
            'path' => '<ellipse cx="12" cy="12" rx="9" ry="6.2" transform="rotate(-30 12 12)"/>'
                    . '<path d="M12 10.3v3.4M10.3 12h3.4"/>',
        ],
        // … the gradient: a square whose bands thin out from full to nothing …
        'gradient' => [
            'path' => '<rect x="3.5" y="3.5" width="17" height="17" rx="2"/>'
                    . '<path d="M3.5 8.5h17"/><path d="M3.5 13h17" stroke-dasharray="3 2"/><path d="M3.5 17h17" stroke-dasharray="1 2.5"/>',
        ],
        // … and the brush: a round brush with its stroke.
        'brush' => [
            'path' => '<path d="M20.5 3.5 11 13"/><path d="M9.5 12.5c-2 0-3.5 1.6-3.5 3.6 0 1.6-.9 2.8-2.5 3.4 1 .5 2.4.8 3.8.8 2.8 0 4.7-1.8 4.7-4.3 0-1.4-1.1-3.5-2.5-3.5Z"/>',
        ],

        // The mask tools of „Ebenen" on their rail (blend.js, 14.11.2026):
        // „Radieren" — an eraser block lying askew, its worn end below, and
        // the line it has rubbed out beside it …
        'eraser' => [
            'path' => '<path d="M9.5 19.5h10"/>'
                    . '<path d="m4.9 14.4 9.2-9.2a1.6 1.6 0 0 1 2.2 0l3.5 3.5a1.6 1.6 0 0 1 0 2.2l-8.8 8.6H7.6l-2.7-2.9a1.6 1.6 0 0 1 0-2.2Z"/>'
                    . '<path d="m9.3 10 5.7 5.7"/>',
        ],
        // … and „Füllen": a paint bucket tipped over, the paint running out
        // of its rim as a drop.
        'bucket' => [
            'path' => '<path d="m11 3.5 8.5 8.5-6.6 6.6a2 2 0 0 1-2.8 0l-5.7-5.7a2 2 0 0 1 0-2.8Z"/>'
                    . '<path d="M4.5 12.5h14"/><path d="M8.5 6 6 3.5"/>'
                    . '<path d="M21.5 18.2c0 1-.7 1.8-1.5 1.8s-1.5-.8-1.5-1.8c0-1.2 1.5-3.2 1.5-3.2s1.5 2 1.5 3.2Z"/>',
        ],

        'palette' => [
            'path' => '<path d="M12 3.5c-4.9 0-8.5 3.6-8.5 8.3 0 4.6 3.8 8.7 8.3 8.7 1.1 0 1.8-.7 1.8-1.6 0-.5-.2-.9-.5-1.2-.3-.4-.5-.8-.5-1.3 0-1 .8-1.7 1.8-1.7h2.1c2.6 0 4.5-2 4.5-4.5 0-3.9-4-6.7-9-6.7Z"/>'
                    . '<circle cx="7.9" cy="11.2" r="1.15" fill="currentColor"/>'
                    . '<circle cx="10.6" cy="7.5" r="1.15" fill="currentColor"/>'
                    . '<circle cx="15.1" cy="7.8" r="1.15" fill="currentColor"/>',
        ],

        // „Bearbeiten" → Farbmischer (22.11.2026): three circles overlapping
        // like coloured lights — the colours taken one by one.
        'colour-mix' => [
            'path' => '<circle cx="12" cy="8.6" r="5"/><circle cx="8.8" cy="14.2" r="5"/><circle cx="15.2" cy="14.2" r="5"/>',
        ],

        // „Farbstich" → Pipette (22.11.2026): an eyedropper lying askew, its
        // bulb up right, its glass tip down left where it takes the colour.
        'eyedropper' => [
            'path' => '<path d="M15.2 4.3a2.4 2.4 0 0 1 3.4 0l1.1 1.1a2.4 2.4 0 0 1 0 3.4l-2.3 2.3-4.5-4.5Z"/>'
                    . '<path d="m11.8 7.5 4.7 4.7"/><path d="m13.6 9.3-7.9 7.9-.9 2.9 2.9-.9 7.9-7.9"/>',
        ],

        // „Bearbeiten" → Gradationskurve (22.11.2026): the square of the curve
        // and a soft S through it — dark tones down, bright ones up.
        'curve' => [
            'path' => '<rect x="3.5" y="3.5" width="17" height="17" rx="2"/><path d="M6 18c5 0 4-6 6-6s1-6 6-6"/>',
        ],

        // „Bearbeiten" → Farbkorrektur (22.11.2026): a disc cut into three —
        // the bright, the middle and the dark tones, each turned on its own.
        'colour-grade' => [
            'path' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 12V3.5M12 12l7.4 4.3M12 12l-7.4 4.3"/>',
        ],

        // „Bearbeiten" → Masken (10.11.2026, the tabs became
        // signs, as in Lightroom): a whole circle and a dashed one over it —
        // the part of the picture that is changed, and the line around it.
        'mask' => [
            'path' => '<circle cx="9.5" cy="12" r="6"/>'
                    . '<circle cx="14.5" cy="12" r="6" stroke-dasharray="1.9 1.9"/>',
        ],

        // „Bearbeiten" → Ausbessern: the sticking plaster — it mends a spot.
        'bandage' => [
            'path' => '<g transform="rotate(-45 12 12)"><rect x="2.5" y="8" width="19" height="8" rx="4"/>'
                    . '<path d="M8.5 8v8M15.5 8v8"/></g>'
                    . '<circle cx="11" cy="11" r=".6" fill="currentColor"/><circle cx="13" cy="13" r=".6" fill="currentColor"/>'
                    . '<circle cx="11" cy="13" r=".6" fill="currentColor"/><circle cx="13" cy="11" r=".6" fill="currentColor"/>',
        ],

        // Oberfläche → Sprache: the globe, the sign for a language choice
        // everywhere — the speech bubble already means the chat.
        'globe' => [
            'path' => '<circle cx="12" cy="12" r="8.5"/>'
                    . '<path d="M3.5 12h17M12 3.5c2.3 2.3 3.5 5.2 3.5 8.5s-1.2 6.2-3.5 8.5c-2.3-2.3-3.5-5.2-3.5-8.5s1.2-6.2 3.5-8.5Z"/>',
        ],
    ];
}

/**
 * An icon as a finished <svg>.
 *
 * Options, all optional:
 *   'class'   Additional CSS class. '.icon' every one carries by
 *              itself — the base size hangs on it.
 *   'stroke'   Stroke width, when the icon's default does not fit.
 *   'title'    Makes the icon visible to screen readers (as <title>).
 *              WITHOUT a title it is aria-hidden — the normal case, for
 *              almost always the button around it already carries an
 *              aria-label, and then a screen reader would read the same
 *              thing out twice.
 *
 * THERE IS NO SIZE HERE, on purpose — see the head of this file.
 */
function icon(string $name, array $options = []): string
{
    $all = icon_stock_all();
    if (!isset($all[$name])) {
        // No silent nothing: a missing icon is a typo in the name, and it
        // should stand out while it is still cheap to find.
        throw new InvalidArgumentException('Unknown icon: ' . $name);
    }
    $entry = $all[$name];

    $classes = trim('icon ' . (string) ($options['class'] ?? ''));

    // Filled icons carry no strokes and vice versa — in between there is
    // nothing that would be worth it.
    $style = !empty($entry['filled'])
        ? ' fill="currentColor"'
        : ' fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"'
          . ' stroke-width="' . (float) ($options['stroke'] ?? $entry['stroke'] ?? 1.8) . '"';

    $opening = '<svg viewBox="0 0 24 24" class="' . h($classes) . '"' . $style;

    // WITH A TITLE the mute icon becomes a speaking one: aria-hidden must
    // go in the process, otherwise it overrules the title and the screen
    // reader stays silent.
    $title = (string) ($options['title'] ?? '');
    return $title !== ''
        ? $opening . ' role="img"><title>' . h($title) . '</title>' . $entry['path'] . '</svg>'
        : $opening . ' aria-hidden="true">' . $entry['path'] . '</svg>';
}

/**
 * The icons JavaScript needs, as a <template> block for the document.
 *
 * WHY VIA THE DOCUMENT and not as a second copy in the module: otherwise
 * the path data would exist twice again — exactly the state this file set
 * out against. The pattern existed once before for the bin alone
 * (<template id="symbol-delete">); here it is generalized.
 *
 * Without an argument, ALL come along. That is around two and a half
 * kilobytes — for a document that carries the start data anyway, no
 * weight, and maintaining the selection by hand would be a list that goes
 * stale at the next new icon.
 */
function icon_stock(?array $names = null): string
{
    $all = icon_stock_all();
    $out = '<div id="icon-stock" hidden>';
    foreach ($names ?? array_keys($all) as $name) {
        $out .= '<template data-icon="' . h($name) . '">' . icon($name) . '</template>';
    }
    return $out . '</div>';
}
