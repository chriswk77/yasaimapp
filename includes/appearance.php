<?php
/**
 * Theme and accent — the tiles, ONCE, for everyone who may choose.
 *
 * They used to live inside the settings tab, which was right as long as
 * that was the only place. Since the excursion guest may choose too
 * (24.08.2026) there are two, and two copies of eight themes
 * and six accents would drift apart at the first new colour — the tile
 * would then show something other than what one gets.
 *
 * THE VALUES ARE PREVIEW COLOURS ONLY. The real effect lives in app.css
 * ([data-theme="…"], [data-accent="…"]) and MUST keep step with these
 * lines. The wiring is the browser's twin of this file:
 * assets/js/appearance.js.
 */

declare(strict_types=1);

/* TWO ROWS, DARK AND LIGHT (22.08.2026). Eight tiles in one
   run made a wall in which one had to read every name; sorted by family,
   the eye picks the half it wants first and only then a tone.

   THE DEFAULT LEADS ITS ROW: Petrol among the themes, Lime among the
   accents. It is what everybody sees before they choose anything, so it
   is the tile one compares against — and it belongs where one starts
   reading, not in the middle. */
function appearance_themes(): array
{
    return [
        'Dunkel' => [
            ['value' => 'petrol',    'name' => 'Petrol',    'ground' => '#02100f', 'ground2' => '#071c1a', 'border' => '#1c4a45'],
            ['value' => 'charcoal',  'name' => 'Kohle',     'ground' => '#14181c', 'ground2' => '#1c2229', 'border' => '#333d48'],
            ['value' => 'amber',     'name' => 'Bernstein', 'ground' => '#040200', 'ground2' => '#0c0704', 'border' => '#3a2610'],
        ],
        'Hell' => [
            ['value' => 'warmlight', 'name' => 'Warmlicht', 'ground' => '#f9f8f6', 'ground2' => '#fdfcfb', 'border' => '#d8d6d1'],
            ['value' => 'slate',     'name' => 'Schiefer',  'ground' => '#eef2f1', 'ground2' => '#f7faf9', 'border' => '#c5cdca'],
            ['value' => 'taupe',     'name' => 'Taupe',     'ground' => '#efece9', 'ground2' => '#f8f6f4', 'border' => '#c7bfb5'],
        ],
    ];
}

/* THE NAME IS THE COLOUR, so a swapped colour is a swapped key
   (22.08.2026: Gold → Orange, Pink → Lila, Moosgrün →
   Stahlblau, Jade → Rot). 'gold' with an orange value would be the sort
   of half-truth this project keeps stumbling over — and the old keys
   still lie on the devices, so they are carried over in the preamble of
   index.php and page.php, and healed in appearance.js. */
function appearance_accents(): array
{
    return [
        ['value' => 'lime',      'name' => 'Lime',      'color' => '#96ff50'],
        ['value' => 'steelblue', 'name' => 'Stahlblau', 'color' => '#307ba5'],
        ['value' => 'red',       'name' => 'Rot',       'color' => '#c9372a'],
        ['value' => 'mint',      'name' => 'Minze',     'color' => '#00c48d'],
        ['value' => 'orange',    'name' => 'Orange',    'color' => '#e07b1f'],
        ['value' => 'purple',    'name' => 'Lila',      'color' => '#5d22b3'],
    ];
}

/**
 * Both pickers, with the ids appearance.js binds to. Only one of the two
 * places renders per page, so the ids stay unique.
 *
 * $heading tells whether the column headings come along: the settings tab
 * wants them, the guest's window carries its own title and would then
 * repeat itself.
 *
 * PLAIN NAMES (25.09.2026, in the plain-language pass): the
 * choice that was „Thema" is „Hintergrund" — it says what changes —, and
 * „Akzentfarbe" is „Markierungsfarbe": the colour marks what is chosen or
 * important, not only buttons, and „Signalfarbe" would have sounded like
 * the warning colours this project keeps apart from it.
 *
 * SIDE BY SIDE IN THE SETTINGS (25.09.2026: „Thema und
 * Akzentfarbe nebeneinander … da passt nebendran noch locker die
 * Akzentfarbe, und dann gehört das alles mehr zusammen"): the two choices
 * stand as a .column-pair — one header bar, a line between, stacked again
 * on the phone. The guest's window is narrow and keeps them one above the
 * other.
 */
function appearance_picker(bool $heading = true): void
{
    ?>
    <?php if ($heading): ?>
    <div class="column-pair appearance-pair">
    <div>
        <div class="column-header"><h3><?= h(t('Hintergrund')) ?></h3></div>
    <?php else: ?>
        <p class="choice-family"><?= h(t('Hintergrund')) ?></p>
    <?php endif; ?>
    <p class="hint-text">
        <?= h(t('Das gilt nur für dieses Gerät. So kannst du zum Beispiel am Handy hell und am Computer dunkel wählen.')) ?>
    </p>
    <div id="theme-selection">
        <?php foreach (appearance_themes() as $family => $tiles): ?>
        <?php /* The family names ('Dunkel', 'Hell') and the tile names are
                 display only — the stored value is 'value', never the name,
                 so t() is safe here. */ ?>
        <p class="choice-family"><?= h(t($family)) ?></p>
        <div class="theme-selection">
            <?php foreach ($tiles as $t): ?>
            <label class="theme-card">
                <input type="radio" name="theme" value="<?= h($t['value']) ?>">
                <span class="theme-swatch" aria-hidden="true"
                      style="--sw-ground: <?= h($t['ground']) ?>; --sw-ground2: <?= h($t['ground2']) ?>; --sw-border: <?= h($t['border']) ?>;">
                    <span></span>
                </span>
                <?= h(t($t['name'])) ?>
            </label>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($heading): ?>
    </div>
    <div>
        <div class="column-header"><h3><?= h(t('Markierungsfarbe')) ?></h3></div>
    <?php else: ?>
        <p class="choice-family"><?= h(t('Markierungsfarbe')) ?></p>
    <?php endif; ?>
    <p class="hint-text">
        <?= h(t('Mit dieser Farbe zeigt YASAIMAPP, was gewählt oder wichtig ist: Knöpfe, gesetzte Haken, der offene Reiter. Du kannst jede Farbe mit jedem Hintergrund mischen. Manche Mischungen sind aber schlechter zu erkennen. Nimm die, die du am besten siehst. Auch das gilt nur für dieses Gerät.')) ?>
    </p>
    <div class="accent-selection" id="accent-selection">
        <?php foreach (appearance_accents() as $a): ?>
        <label class="accent-card">
            <input type="radio" name="accent" value="<?= h($a['value']) ?>">
            <span class="accent-swatch" aria-hidden="true" style="--sw-color: <?= h($a['color']) ?>;"></span>
            <?= h(t($a['name'])) ?>
        </label>
        <?php endforeach; ?>
    </div>
    <?php if ($heading): ?>
    </div>
    </div>
    <?php endif; ?>
    <?php
}
