<?php
/**
 * The help — one page, in the order one meets things.
 *
 * WHY THE NUMBERS ARE NOT IN THE PICTURE. A screenshot with numbers burnt
 * into it is a dead end: every reworded caption means opening an image
 * editor, and the next change of a button turns the whole picture into a
 * lie one cannot correct in passing. Here the picture is a plain PNG and
 * the numbers are elements over it, placed in per cent (help_shot). One
 * can move a number, rename it, translate it — without ever touching the
 * image. And they stay sharp at every zoom, which a rasterized circle
 * does not.
 *
 * WHY IT SURVIVES MISSING PICTURES. Whoever adds a section writes the text
 * first and the screenshot later, and both should already work on their
 * own: help_shot() prints NOTHING if the file is not there, and the
 * legend keeps standing as an ordinary list. The page is thus never
 * broken, only sometimes still without pictures.
 *
 * THE SCREENSHOTS LIVE IN assets/help/. They are the only images in this
 * project that are deployed, so they stay small — a wide screenshot at
 * 1200 pixels is enough to read a button on, and nobody prints this.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
// page_header/page_footer and icon() — bootstrap brings neither, every
// page names what it needs (see administration.php).
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/icons.php';

$me = login_required();

/**
 * A screenshot with numbers over it.
 *
 * @param string $file  name in assets/help/, without a path
 * @param string $alt   what the picture shows, for whoever cannot see it
 * @param array  $marks [number => [x per cent, y per cent, section?]] — the
 *                      CENTRE of the mark, measured on the picture
 *                      (.help-mark translates itself back by half its
 *                      size); with a section id the number is a LINK into
 *                      that section (15.09.2026: „die Nummern
 *                      anklickbar machen, dass wir gleich ins Kapitel
 *                      springen können")
 *
 * THE PICTURES ARE SHOT BY admin/help_shots.mjs, not by hand — the same
 * seven views on every run, from the demodata fixture. Whoever moves a
 * button reruns it and checks the marks here against the new picture.
 */
function help_shot(string $file, string $alt, array $marks = []): void
{
    $path = __DIR__ . '/../assets/help/' . $file;
    if (!is_file($path)) {
        // Deliberately silent: the text of a section stands on its own,
        // and a broken image icon would say "defective" where in truth
        // only a picture is still missing.
        return;
    }
    ?>
    <figure class="help-shot">
        <img src="<?= h(url('assets/help/' . $file)) ?>?v=<?= (int) filemtime($path) ?>"
             alt="<?= h($alt) ?>" loading="lazy">
        <?php foreach ($marks as $number => $mark): [$x, $y] = $mark; $to = $mark[2] ?? null; ?>
            <?php if ($to !== null): global $numbers; ?>
            <a class="help-mark" href="#<?= h($to) ?>" style="--x: <?= (float) $x ?>%; --y: <?= (float) $y ?>%"
               title="<?= h(t('Zu Abschnitt %s', $numbers[$to] ?? $to)) ?>"
               aria-label="<?= h(t('Zu Abschnitt %s', $numbers[$to] ?? $to)) ?>"><?= h((string) $number) ?></a>
            <?php else: ?>
            <span class="help-mark" style="--x: <?= (float) $x ?>%; --y: <?= (float) $y ?>%"
                  aria-hidden="true"><?= h((string) $number) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </figure>
    <?php
}

/**
 * THE OUTLINE — ONE LIST, TWO READERS (15.09.2026: „ein bisschen
 * zusammenhängend machen: Karte ist 1, Sidebar ist 1.1, Listen und
 * Freigaben sowie Exkursionen in der Sidebar 1.1.1 und so weiter"). The
 * table of contents and the headings both read from here, so a section
 * moved in this list renumbers everywhere at once, and a heading can never
 * carry a number the contents page does not know. Level 1 is an h2, level
 * 2 an h3, level 3 an h4 — the page's own h1 is its title.
 *
 * Cross references in the texts („siehe 2.3") are written out by hand and
 * checked against this list below — a number that drifts is caught at
 * build, not by a reader.
 */
$outline = [
    ['karte',        t('Die Karte'), 1],
    ['zoomen',       t('Zoomen und Verschieben'), 2],
    ['gps',          t('GPS — der Standort'), 2],
    ['setzen',       t('Punkt setzen'), 2],
    ['layer',        t('Die Kartenlayer'), 2],
    ['chat',         t('Der Chat'), 2],
    ['leiste',       t('Die Seitenleiste (links)'), 2],
    ['kopfzeile',    t('Die Kopfzeile'), 3],
    ['reiter',       t('Die Reiter'), 3],
    ['listen',       t('Listen'), 4],
    ['geteilt',      t('Geteilt'), 5],
    ['exkursionen-leiste', t('Exkursionen'), 5],
    ['punkte',       t('Punkte'), 4],
    ['filter',       t('Filter'), 5],
    ['fenster',      t('Punktfenster (Seitenleiste rechts)'), 2],
    ['fenster-text',    t('Text'), 3],
    ['fenster-photos',  t('Belege'), 3],
    ['fenster-place',   t('Karte'), 3],
    ['fenster-foreign', t('Ein fremder Fund'), 3],
    ['verwaltung',   t('Die Verwaltung'), 1],
    ['einstellungen', t('Einstellungen'), 2],
    ['konto',        t('Konto'), 3],
    ['oberflaeche',  t('Oberfläche'), 3],
    ['arten',        t('Arten'), 3],
    ['fundnummer',   t('Fundnummer'), 3],
    ['messen',       t('Fotos'), 3],
    ['geraete',      t('Geräte'), 3],
    // THE ORDER OF THE TABS (02.10.2026): Einstellungen,
    // Kontakte, Offline, Gemeinsam, Organisieren, Cloud, Import, Papierkorb.
    // Kontakte split off Gemeinsam the same day; „Direkt geteilt" is the
    // photo session's new sub-tab — both chapters stand here as heads for
    // the help's own rework, which writes them out.
    ['kontakte',     t('Kontakte'), 2],
    ['freunde',      t('Freunde'), 3],
    ['gruppen',      t('Arbeitsgruppen'), 3],
    ['offline',      t('Offline'), 2],
    ['gebiete',      t('Offline-Gebiete'), 3],
    ['daten',        t('Offline-Daten'), 3],
    ['gemeinsam',    t('Gemeinsam'), 2],
    ['freigaben',    t('Freigaben'), 3],
    ['direkt',       t('Direkt geteilt'), 3],
    ['exkursionen',  t('Exkursionen'), 3],
    ['organisieren', t('Organisieren'), 2],
    ['cloud',        t('Cloud'), 2],
    ['import',       t('Import'), 2],
    ['papierkorb',   t('Papierkorb'), 2],
];

/** id => number („1.1.3") — computed once, over the outline order. */
$numbers = [];
$counter = [0, 0, 0, 0, 0];
foreach ($outline as [$id, , $level]) {
    $counter[$level - 1]++;
    for ($k = $level; $k < 5; $k++) {
        $counter[$k] = 0;
    }
    $numbers[$id] = implode('.', array_slice($counter, 0, $level));
}
$titles = array_column($outline, 1, 0);
$levels = array_column($outline, 2, 0);

/**
 * A section heading that the table of contents can jump to — and the
 * section around it.
 *
 * NESTED SECTIONS, SO THE TEXT STEPS IN UNDER ITS HEADING
 * (15.09.2026: „den Text unterhalb der Überschriften etwas einrücken, so
 * dass wir wirklich einen hierarchischen Aufbau haben"). Each call opens
 * a <section> of its level and first closes every open section of the
 * same or a deeper level, so 1.1.3 lies inside 1.1 inside 1 — and the
 * stylesheet indents a section's body, which puts a subsection's heading
 * at its parent's text and its own text one step further. help_end()
 * closes what is still open before the footer.
 */
$openSections = [];
function help_section(string $id): void
{
    global $numbers, $titles, $levels, $openSections;
    if (!isset($numbers[$id])) {
        throw new LogicException('help section not in the outline: ' . $id);
    }
    $level = $levels[$id];
    while ($openSections !== [] && end($openSections) >= $level) {
        echo "</section>\n";
        array_pop($openSections);
    }
    $openSections[] = $level;
    $tag = 'h' . ($level + 1);
    printf('<section class="help-section help-level-%d">' . "\n", $level);
    printf('<%s id="%s"><span class="help-number">%s</span> %s</%s>' . "\n",
        $tag, h($id), h($numbers[$id]), h($titles[$id]), $tag);
}
function help_end(): void
{
    global $openSections;
    while ($openSections !== []) {
        echo "</section>\n";
        array_pop($openSections);
    }
}

page_header(t('Hilfe'), ['wide' => true]);
?>

<?php /* THE CONTENTS STAND BESIDE THE TEXT (15.09.2026: „das
     Register selbst würde ich als Sidebar einblenden, so dass man nicht
     immer ganz nach oben scrollen muss"). On the desk a sticky column on
     the left that scrolls with the reader and marks the section under the
     eye (assets/js/help.js); on the phone it stays a block above the text. */ ?>
<div class="help-layout">
<nav class="help-toc" aria-label="<?= h(t('Inhalt')) ?>">
<?php
    // Nested lists out of the flat outline: open one <ol> per level step
    // down, close per step up.
    // The mantra first, unnumbered: it is not a chapter, it is what the
    // chapters rest on (15.09.2026: „bitte auch als Punkt in
    // der Sidebar").
    printf('<ol class="help-toc-mantra"><li><a href="#mantra">%s</a></li><li><a href="#app-browser">%s</a></li></ol>',
        h(t('So ist die App gedacht')), h(t('App oder Browser?')));
    $depth = 0;
    foreach ($outline as [$id, $title, $level]) {
        while ($depth < $level) { echo '<ol>'; $depth++; }
        while ($depth > $level) { echo '</ol></li>'; $depth--; }
        printf('<li><a href="#%s"><span class="help-number">%s</span> %s</a>', h($id), h($numbers[$id]), h($title));
    }
    while ($depth > 0) { echo '</li></ol>'; $depth--; }
?>
</nav>
<div class="help-body">

<section class="help-mantra" id="mantra">
    <h2><?= h(t('So ist die App gedacht')) ?></h2>
    <p><?= t('YASAIMAPP kartiert <strong>Arten</strong>. Jeder Fund kommt in eine <strong>Grundliste seines Reichs</strong> — Pilze, Flechten, Pflanzen —, und zwar immer in dieselbe. Nicht nach Orten sortieren, nicht nach Gemeinden, nicht nach Wäldern oder Revieren: Wo ein Fund liegt, weiß die Karte von selbst, und Ort, Gemeinde, Messtischblatt oder Gebiet findest du jederzeit über die Filter und über Autolisten wieder.') ?></p>
    <?php /* The cross reference goes to 1.6.2.1 Listen, where the pencil's
         window — sublists and all — is explained (16.09.2026). */ ?>
    <p><?= t('Nach dem Anmelden hast du bereits die <strong>Mappe „Meine Funde"</strong> mit drei Listen: Flechten, Pflanzen und Pilze, jede mit eigener Farbe und eigenem Zeichen. Weitere Bereiche legst du genauso an, und was du nicht brauchst, löschst du — wie das geht, steht unter <a href="#listen">%s</a>.', h($numbers['listen'])) ?></p>
    <p><?= t('Bevor du losgehst, überlege dir, was du heute hauptsächlich kartierst — zum Beispiel Pilze — und schalte <strong>nur diese eine Liste</strong> ein. Beim Setzen eines Punktes ist dann genau sie schon angehakt, und jeder Fund ist ein einziger Tipp auf „Weiter“. Findest du zwischendurch eine Flechte, wählst du kurz die Pilzliste ab und die Flechtenliste an. Sind Pilze und Flechten dauernd eingeblendet, sind bei jedem Punkt beide angehakt, und du musst jedes Mal einen Haken entfernen. Deshalb: immer nur <strong>eine Liste</strong> an, und für den Fund außer der Reihe die passende Liste wählen. So einfach ist das Konzept, und alles Weitere baut darauf auf.') ?></p>
</section>

<?php /* APP OR BROWSER, BEFORE ANYTHING ELSE (27.09.2026, after a
     350 MB offline area and the question in which storage it had landed):
     the app and every browser keep their own storage, so where one works
     decides where the offline maps lie. His rules, in his order: the app
     first, without it Chrome — it gets on better with the phone's GPS than
     Firefox —, and never both side by side. Unnumbered and dressed like
     the mantra, because it is about how to open YASAIMAPP, not about a
     part of it — and a numbered chapter in front would have shifted every
     number the texts quote. No word on where the app comes from: it is
     not in a store yet. */ ?>
<section class="help-mantra" id="app-browser">
    <h2><?= h(t('App oder Browser?')) ?></h2>
    <p><?= h(t('YASAIMAPP läuft auf zwei Wegen: als App für Android oder im Browser, zum Beispiel in Chrome oder Firefox.')) ?></p>
    <p><?= t('<strong>Jeder Weg hat seinen eigenen Speicher.</strong> Was du in der App auf das Handy lädst, liegt nur in der App. Was du in Chrome lädst, liegt nur in Chrome, und Firefox hat wieder seinen eigenen.') ?></p>
    <?php /* THE EXAMPLE, AND WHAT ELSE STAYS BEHIND (27.09.2026:
         „macht das mal noch an einem Beispiel klar … sämtliche
         Einstellungen sind danach anders"). The list names only what the
         settings bundle really keeps per device — theme and accent live
         there too, measured in appearance.js —, then the copies every
         way makes by itself (the point buckets, the species name lists,
         viewed voucher photos, passing tiles: his „Buckets, also
         Artenlisten, Punkte … kann auch viel Platz wegnehmen"), and last
         what does NOT split, so nobody fears for his finds. */ ?>
    <p><?= t('<strong>Ein Beispiel:</strong> Du lädst in Chrome ein Offline-Gebiet mit 350 MB herunter. Öffnest du im Wald die App, sind die Karten dort nicht da. Du müsstest das Gebiet in der App ein zweites Mal laden. Dann liegen 700 MB auf dem Handy, zweimal dieselben Karten.') ?></p>
    <p><?= t('<strong>Auch deine Einstellungen merkt sich jeder Weg für sich:</strong> das Aussehen unter Oberfläche, welche Listen eingeschaltet und welche zugeklappt sind, deine Filter, die Grundkarte, die Stelle, an der die Karte zuletzt stand, und die laufende Tour. Wechselst du von Chrome in die App, sieht dort vieles anders aus. Anmelden musst du dich in jedem Weg einmal.') ?></p>
    <p><?= t('<strong>Jeder Weg legt außerdem von selbst seine eigene Kopie für unterwegs an:</strong> deine Funde, die Namenslisten der Arten, Belegfotos, die du dir angesehen hast, und Kartenteile, die beim Umsehen mitgeladen werden. Wer zwei oder drei Wege benutzt, hat all das zwei- oder dreimal auf dem Handy. Das kostet unnötig Platz.') ?></p>
    <p><?= t('Punkte, die du ohne Empfang gesetzt hast, warten dort auf die Übertragung, wo du sie gesetzt hast. <strong>Deine Funde und Listen selbst sind überall dieselben:</strong> Sie liegen auf dem Server, sobald sie übertragen sind.') ?></p>
    <p><?= t('<strong>Darum unsere Empfehlung:</strong>') ?></p>
    <ol>
        <li><?= t('<strong>Installiere die App.</strong> Mit ihr kannst du am meisten machen, und sie ist am bequemsten. Sie zeichnet zum Beispiel deinen Weg auf, auch wenn der Bildschirm aus ist.') ?></li>
        <li><?= t('<strong>Ohne App nimm am Handy Chrome.</strong> Chrome arbeitet mit dem GPS des Handys besser zusammen als Firefox.') ?></li>
    </ol>
    <p><?= h(t('Hast du die App, dann benutze nur noch sie und nicht zusätzlich den Browser. Wer ständig wechselt, hat nur Nachteile: alles doppelt auf dem Handy, andere Einstellungen und Punkte, die an zwei Stellen auf Empfang warten. Willst du nur im Browser arbeiten, dann in Chrome.')) ?></p>
</section>

<p class="hint-text">
    <?= h(t('Zuerst, wie die Karte aufgebaut ist und wie Listen und Punkte zusammenhängen — dann die Dinge in der Reihenfolge, in der man ihnen begegnet. Wer etwas Bestimmtes sucht, springt über das Verzeichnis.')) ?>
</p>

<?php help_section('karte'); ?>
<p>
    <?= t('Die App besteht aus <strong>drei Teilen</strong>: links die Seitenleiste, in der Mitte die Karte, rechts das Punktfenster. Die Karte ist die Hauptsache — auf ihr liegen deine Funde als Marker, und auf ihr sitzen ein paar Knöpfe, die hier zuerst erklärt werden. Die beiden Leisten kommen danach; was in ihnen steckt, haben eigene Kapitel.') ?>
</p>
<?php help_shot('karte.png', t('Die drei Teile: Seitenleiste links, Karte, Punktfenster rechts'), [
    1 => [65.8, 91.5, 'zoomen'], 2 => [43.75, 86, 'gps'], 3 => [55, 86, 'setzen'], 4 => [64.5, 4.5, 'layer'],
    5 => [36.5, 4.5, 'chat'], 6 => [37, 95.5, 'zoomen'], 7 => [15, 76, 'leiste'], 8 => [97, 27, 'fenster'],
]); ?>
<ol class="help-legend">
    <li><?= t('Plus und Minus: die Karte vergrößern und verkleinern (<a href="#zoomen">%s</a>).', h($numbers['zoomen'])) ?></li>
    <li><?= t('Standort: schaltet das GPS ein (<a href="#gps">%s</a>).', h($numbers['gps'])) ?></li>
    <li><?= t('Punkt setzen: einen neuen Fund eintragen (<a href="#setzen">%s</a>).', h($numbers['setzen'])) ?></li>
    <li><?= t('Die Kartenlayer: Grundkarte und Ebenen wählen (<a href="#layer">%s</a>).', h($numbers['layer'])) ?></li>
    <li><?= t('Die Sprechblase: der Chat mit deinen Freunden (<a href="#chat">%s</a>).', h($numbers['chat'])) ?></li>
    <li><?= h(t('Der Maßstabsbalken: wie lang ein Stück Karte in Metern ist.')) ?></li>
    <li><?= t('Die Seitenleiste links: die Verwaltungszentrale (<a href="#leiste">%s</a>).', h($numbers['leiste'])) ?></li>
    <li><?= t('Die Seitenleiste rechts: das Punktfenster (<a href="#fenster">%s</a>).', h($numbers['fenster'])) ?></li>
</ol>

<?php help_section('zoomen'); ?>
<p><?= t('<strong>Am Rechner:</strong> Das Mausrad vergrößert und verkleinert die Karte an der Stelle, über der die Maus steht; die Knöpfe <strong>+</strong> und <strong>−</strong> rechts unten tun dasselbe in der Kartenmitte. Verschieben: die linke Maustaste gedrückt halten und ziehen. Ein Doppelklick zoomt eine Stufe hinein.') ?></p>
<p><?= t('<strong>Am Handy:</strong> Zwei Finger auseinander ziehen vergrößert, zusammenziehen verkleinert. Ein Finger verschiebt die Karte. Zweimal tippen zoomt eine Stufe hinein.') ?></p>
<p class="hint-text"><?= h(t('Der Maßstabsbalken links unten sagt dir, wie viel Meter ein Stück Karte gerade sind — beim Hineinzoomen wird die Zahl darauf kleiner.')) ?></p>

<?php help_section('gps'); ?>
<p class="help-see"><?= t('Was die Ortung beim Setzen eines Punktes bewirkt, steht unter <a href="#setzen">%s</a>.', h($numbers['setzen'])) ?></p>
<p><?= t('Der linke der beiden Knöpfe unten, <strong>Standort</strong>, schaltet das GPS ein. Beim ersten Mal fragt der Browser, ob die App deinen Standort kennen darf — das musst du erlauben, sonst bleibt der Knopf stumm. Dann springt die Karte zu dir und zeigt dich als <strong>blauen Punkt</strong>, und die Karte folgt dir beim Gehen.') ?></p>
<p><?= t('Um den blauen Punkt liegt ein <strong>zweiter, größerer Kreis</strong>. Er zeigt, wie genau das GPS dich gerade sieht: Irgendwo in diesem Kreis bist du wirklich. Je größer der Kreis, desto ungenauer die Ortung — unter Bäumen oder zwischen Häusern wird er groß, unter freiem Himmel schrumpft er nach ein paar Sekunden auf wenige Meter. Ein Punkt, den du setzt, während der Kreis noch groß ist, kann entsprechend danebenliegen.') ?></p>
<p><?= t('Sobald du die Karte selbst verschiebst, hört sie auf, dir zu folgen; der blaue Punkt bleibt. Ein Tipp auf den Knopf lässt die Karte wieder mitlaufen, ein weiterer schaltet den Standort aus.') ?></p>
<p class="hint-text"><?= h(t('Freier Himmel hilft: weg von Hauswänden, raus aus dem Auto. Am Rechner ohne GPS ortet der Browser nur grob über das Netz.')) ?></p>

<?php help_section('setzen'); ?>
<p><?= t('Der rechte der beiden Knöpfe unten, <strong>Punkt setzen</strong>, trägt einen neuen Fund ein. Wo der Punkt landet, hängt vom GPS ab:') ?></p>
<ul>
    <li><?= t('<strong>Folgt dir der blaue Punkt</strong> (<a href="#gps">%s</a>), setzt ein Tipp auf „Punkt setzen" den Fund genau an deinen Standort — du musst nichts auf der Karte treffen.', h($numbers['gps'])) ?></li>
    <li><?= t('<strong>Ist das GPS aus, oder liegt dein Standort außerhalb des Kartenausschnitts</strong>, wirst du aufgefordert, die Stelle selbst zu wählen: am Rechner mit einem Klick in die Karte, am Handy mit einem Tipp. Nochmal auf „Punkt setzen" tippen, bevor du die Stelle wählst, bricht ab.') ?></li>
</ul>
<p><?= h(t('Danach öffnet sich zuerst dieses Fenster:')) ?></p>
<?php help_shot('tour.png', t('Das Fenster nach „Punkt setzen": Tour, Neue Tour, Listen'), [
    1 => [66, 15.3], 2 => [83, 7.3], 3 => [45, 36.7, 'leiste'],
]); ?>
<ol class="help-legend">
    <li><?= t('<strong>Tour:</strong> der Name des heutigen Ausflugs. Vorgeschlagen wird „Tagestour"; du kannst ihn ändern. Jeder Fund, den du heute setzt, bekommt diesen Namen mit — so findest du den ganzen Ausflug später mit einem Griff wieder.') ?></li>
    <li><?= t('<strong>Neue Tour:</strong> beendet die laufende Tour und beginnt eine zweite am selben Tag — etwa nach dem Ortswechsel am Nachmittag. Der Vorschlag zählt dann hoch: „Tagestour #2".') ?></li>
    <li><?= t('<strong>In welche Listen?</strong> Vorangehakt ist genau das, was du dir gerade auf der Karte anzeigen lässt — die Listen, die in der Seitenleiste eingeblendet sind (<a href="#leiste">%s</a>). Zu sehen sind zuerst nur diese Listen. Findest du etwas außer der Reihe, zeigt dir <strong>Listen ändern</strong> alle deine Listen. Haken kannst du setzen und entfernen, mehrere sind möglich. Ohne Haken landet der Fund unter „Ohne Liste".', h($numbers['leiste'])) ?>
        <p class="hint-text"><?= t('<strong>Das entscheidet, ob dich die App nervt oder ob du sie richtig bedienst.</strong> Überlege dir vor dem Losgehen, was du heute hauptsächlich kartierst — zum Beispiel Pilze — und schalte nur diese Liste ein: Dann ist hier bei jedem Fund genau sie angehakt, und du tippst nur noch „Weiter“. Für eine Flechte zwischendurch wählst du kurz die Pilzliste ab und die Flechtenliste an. Sind beide dauernd eingeblendet, musst du bei jedem Punkt einen Haken entfernen — deshalb immer nur eine Liste, und für den Fund außer der Reihe die passende Liste.') ?></p>
        <p class="hint-text"><?= t('Die Ablage dafür: die Mappe „Meine Funde", die du nach dem Anmelden schon hast, und darin je eine Liste pro Reich — wie du sie anlegst, steht unter <a href="#listen">%s</a>. Im Fenster stehen die Listen einer Mappe eingerückt darunter, ein Klick auf den Mappennamen klappt sie auf und zu. Wer seine Funde trotzdem nach Revieren sehen will, legt dafür eine <strong>Autoliste</strong> an (<a href="#organisieren">%s</a>): eine Liste, die sich selbst aus einem Filter füllt, ohne dass beim Setzen etwas anzuhaken wäre.', h($numbers['listen']), h($numbers['organisieren'])) ?></p>
    </li>
</ol>
<p><?= t('„Weiter" führt rechts ins Punktfenster (<a href="#fenster">%s</a>), in dem du den Fund beschreibst.', h($numbers['fenster'])) ?></p>

<?php help_section('layer'); ?>
<p class="help-see"><?= t('Der Filter (<a href="#filter">%s</a>) siebt auch die Wegstrecken: eine Strecke bleibt, solange ein passender Fund auf ihr liegt.', h($numbers['filter'])) ?></p>
<p><?= t('Der Knopf <strong>oben rechts</strong> mit den gestapelten Ebenen öffnet die Kartenwahl:') ?></p>
<?php help_shot('layer.png', t('Die Kartenwahl: oben die Grundkarten, unten die Zusatzlayer'), [
    1 => [90, 22], 2 => [90, 70],
]); ?>
<ol class="help-legend">
    <li><?= t('<strong>Die Karten:</strong> Luftbild und OpenStreetMap. Genau eine davon ist immer an.') ?></li>
    <li><?= t('<strong>Die Zusatzlayer:</strong> Ebenen, die du über die Karte legst — Höhenlinien, Geländeschummerung, Gesteinstyp, Baumarten, Schutzgebiete — und die Wegstrecken, die es nur gibt, wenn du deine Gänge mit der <strong>Android-App</strong> aufzeichnest (dazu ein eigenes Kapitel). Beliebig viele zugleich; was du wählst, merkt sich das Gerät.') ?></li>
</ol>
<p><?= t('Ein eingeschalteter Zusatzlayer zeigt <strong>unten links</strong> seine Legende — hier Baumarten, Schutzgebiete und Gesteinstyp:') ?></p>
<?php help_shot('legende.png', t('Die Legenden der eingeschalteten Zusatzlayer'), [
    1 => [93, 46], 2 => [88, 58],
]); ?>
<ol class="help-legend">
    <li><?= t('Jeder Layer hat seinen Kasten; die Überschrift klappt ihn zu und wieder auf.') ?></li>
    <li><?= t('Die Zeilen darin sind die Unterebenen des Layers, und <strong>ein Klick auf eine Zeile schaltet sie aus oder wieder ein</strong> — bei den Baumarten etwa lässt du nur die Buche stehen und blendest alle anderen Arten aus.') ?></li>
</ol>

<?php help_section('chat'); ?>
<p><?= t('Die <strong>Sprechblase oben links</strong> öffnet die Chats mit deinen Freunden. Sie funktioniert nur, wenn du Freunde hast — deshalb ist sie erst da, wenn du welche hast, und trägt einen grünen Punkt, wenn Nachrichten ungelesen sind. Wie du Freunde einlädst oder anfragst, um mit ihnen chatten zu können, steht in der Verwaltung unter <a href="#freunde">%s</a>.', h($numbers['freunde'])) ?></p>

<?php help_section('leiste'); ?>
<p><?= t('Die Seitenleiste links ist die <strong>Verwaltungszentrale</strong>. Sie hat drei Teile: oben die Kopfzeile, darunter die zwei Reiter, unten die Fußzeile. Von hier aus verlässt du die App, rufst die Hilfe auf, suchst einen Ort, gehst in die Verwaltung — und bestimmst, <strong>welche deiner Punkte die Karte zeigt</strong>. Am Handy legt sich die Leiste über die Karte; das Menüzeichen ☰ oben links öffnet sie.') ?></p>
<div class="help-shots">
<div>
<p class="help-caption"><?= h(t('Desktop-Version')) ?></p>
<?php help_shot('leiste.png', t('Die Seitenleiste am Rechner, mit dem Griff an der Kante'), [
    1 => [38, 2.8, 'kopfzeile'], 2 => [38, 8.3, 'reiter'], 3 => [77.5, 44],
]); ?>
</div>
<div>
<p class="help-caption"><?= h(t('Handy-Version')) ?></p>
<?php help_shot('leiste-handy.png', t('Die Seitenleiste am Handy, mit „Schließen" in der Fußzeile'), [
    1 => [50, 3.2, 'kopfzeile'], 2 => [50, 9.2, 'reiter'], 3 => [85, 92.5],
]); ?>
</div>
</div>
<ol class="help-legend">
    <li><?= t('Die Kopfzeile: zur Karte, Abmelden, Hilfe, Suche, Verwaltung (<a href="#kopfzeile">%s</a>).', h($numbers['kopfzeile'])) ?></li>
    <li><?= t('Die Reiter „Listen" und „Punkte" (<a href="#reiter">%s</a>).', h($numbers['reiter'])) ?></li>
    <li><?= t('<strong>Die Leiste schließen:</strong> am Rechner ein Klick auf den Griff an der Kante — der Streifen am linken Rand holt sie zurück. Der Griff kann noch etwas: Ziehst du ihn, statt zu klicken, wird die Leiste breiter oder schmaler. Am Handy „Schließen" in der Fußzeile — das Menüzeichen ☰ oben links öffnet sie wieder.') ?></li>
</ol>

<?php help_section('kopfzeile'); ?>
<?php help_shot('kopfzeile.png', t('Die Kopfzeile der Seitenleiste'), [
    1 => [44, 32], 2 => [66.5, 80], 3 => [75.5, 80], 4 => [84, 80], 5 => [92.5, 80],
]); ?>
<ol class="help-legend">
    <li><?= t('<strong>Zur Karte</strong> (der Name der App ganz links): führt von der Verwaltung und von der Hilfe zurück zur Karte.') ?></li>
    <li><?= t('<strong>Abmelden</strong> (die Tür): beendet die Anmeldung auf diesem Gerät.') ?></li>
    <li><?= t('<strong>Hilfe</strong> (das Fragezeichen): diese Seite.') ?></li>
    <li><?= t('<strong>Suche</strong> (die Lupe): klappt ein Feld auf, das vieles versteht — einen Ortsnamen, eine Koordinate in Grad, Plus Code oder UTM, ein Messtischblatt wie „4625/2", oder einen Kartenlink aus einer anderen Anwendung. Die Karte springt dorthin; es wird kein Punkt gesetzt.') ?>
        <p><?= t('<strong>Die Suche</strong> im Bild: links das aufgeklappte Feld mit den Treffern zu einem Ortsnamen, rechts die Karte nach dem Sprung — ein Fadenkreuz markiert die gefundene Stelle, bis du die Suche leerst.') ?></p>
        <div class="help-shots">
        <?php help_shot('suche.png', t('Die Suche mit ihren Treffern'), [
            '4.1' => [70, 41], '4.2' => [60, 68],
        ]); ?>
        <?php help_shot('gefunden.png', t('Die gefundene Stelle mit dem Fadenkreuz'), [
            '4.3' => [59, 40],
        ]); ?>
        </div>
        <ol class="help-legend" style="--help-legend-prefix: '4.'">
            <li><?= h(t('Das Suchfeld — ein Ortsname, eine Koordinate, ein Messtischblatt, ein Kartenlink.')) ?></li>
            <li><?= h(t('Die Treffer zum Ortsnamen; ein Tipp springt hin. Eine Koordinate braucht keine Liste — die Karte springt sofort.')) ?></li>
            <li><?= h(t('Das Fadenkreuz auf der gefundenen Stelle. Es setzt keinen Punkt — das tust du, wenn du willst, mit „Punkt setzen".')) ?></li>
        </ol>
    </li>
    <li><?= t('<strong>Verwaltung</strong> (das Zahnrad): die zweite Seite der App mit allem, was nicht ins Gelände gehört — siehe <a href="#verwaltung">%s</a>.', h($numbers['verwaltung'])) ?></li>
</ol>

<?php help_section('reiter'); ?>
<p><?= t('Zwei Reiter, eine Sache von zwei Seiten: Unter <strong>Listen</strong> wählst du, welche Listen die Karte zeigt; unter <strong>Punkte</strong> stehen genau die Punkte dieser Listen. Die Zahl an jedem Reiter sagt, wie viele es gerade sind.') ?></p>

<?php help_section('listen'); ?>
<p class="help-see"><?= t('Warum die Listen so aufgebaut sind — eine Liste je Reich —, steht ganz oben unter <a href="#mantra">„So ist die App gedacht"</a> und bei <a href="#setzen">%s</a>.', h($numbers['setzen'])) ?></p>
<p><?= t('Unter <strong>Meine Listen</strong> stehen deine Listen. Mehrere Listen kannst du in einer <strong>Mappe</strong> zusammenfassen. Eine Mappe ist nur ein Name, unter dem die Listen beieinander stehen. Sie hat keine eigenen Punkte und keine eigenen Einstellungen. Daneben gibt es Listen ohne Mappe, und ganz unten die Rückfallliste <strong>Ohne Liste</strong> für Punkte, die in keiner Liste liegen. Eine neue Mappe legst du unten in der Seitenleiste an, mit dem Knopf <strong>Neue Mappe</strong> neben „Neue Liste".') ?></p>
<?php help_shot('listen.png', t('Meine Listen: eine aufgeklappte Mappe, eine zugeklappte mit einer Markierung darin, eine Liste ohne Mappe, und „Ohne Liste"'), [
    1 => [60, 34], 2 => [60, 73], 3 => [55, 83], 4 => [55, 93], 5 => [49, 34], 6 => [31, 78], 7 => [55, 22],
]); ?>
<ol class="help-legend">
    <li><?= t('<strong>Eine Mappe</strong>, aufgeklappt: Das Dreieck klappt sie zu und wieder auf. Die Listen stehen eingerückt darunter. Der Haken vor der Mappe blendet alle ihre Listen auf einmal ein oder aus. Ein Klick auf eine Liste blendet nur ihre Punkte ein oder aus.') ?></li>
    <li><?= t('<strong>Eine zugeklappte Mappe</strong>: Ist darin eine Liste eingeblendet, leuchtet die Mappe. So siehst du, dass etwas darunter läuft, ohne aufzuklappen.') ?></li>
    <li><?= t('<strong>Eine Liste ohne Mappe</strong>: Form und Farbe davor.') ?></li>
    <li><?= t('<strong>Ohne Liste</strong>: Punkte, die in keiner Liste liegen — etwa nach dem Löschen ihrer Liste. Sie lassen sich wie alle anderen ein- und ausblenden.') ?></li>
    <li><?= t('<strong>Der Stift</strong> steht an jeder Liste und an jeder Mappe. An einer Liste öffnet er das Fenster der Liste. An einer Mappe kannst du sie nur umbenennen oder löschen. Löschst du eine Mappe, bleiben ihre Listen mit allen Punkten erhalten und stehen danach ohne Mappe da. So sieht das Fenster einer Liste aus, hier für „Pilze":') ?>
        <?php help_shot('liste.png', t('Das Fenster der Liste „Pilze"'), [
            '5.1' => [21, 5.2], '5.2' => [25, 12.1], '5.3' => [20, 23.7], '5.4' => [47, 59.4], '5.5' => [79, 66.3],
            '5.6' => [34, 68], '5.7' => [21, 74.6], '5.8' => [20, 97.2],
        ]); ?>
        <ol class="help-legend" style="--help-legend-prefix: '5.'">
            <li><?= t('<strong>Der Name</strong> der Liste.') ?></li>
            <li><?= t('<strong>Mappe:</strong> Hier legst du die Liste in eine Mappe oder nimmst sie wieder heraus. Die Auswahl erscheint, sobald du eine Mappe hast. Die Liste behält dabei alle Punkte und Einstellungen.') ?></li>
            <li><?= t('<strong>Farbe</strong> und <strong>Form</strong> der Markierung: Beides gilt für die Marker dieser Liste auf der Karte.') ?></li>
            <li><?= t('<strong>Artenliste:</strong> mit dem Haken schlägt das Titelfeld eines Punktes wissenschaftliche Namen vor, und die Fundfelder darunter kommen dazu. Für die Listen der Reiche ist er gesetzt. Darunter fragt das Fenster: <strong>Was enthält diese Liste?</strong> Du hakst die Gruppen an — Pilze, Flechten, Pflanzen, die Tiergruppen. Bei „Art" kommen nur Namen aus diesen Gruppen, und ihre Namen kommen von selbst aufs Gerät, damit die Vorschläge auch ohne Empfang gehen (<a href="#arten">%s</a>).', h($numbers['arten'])) ?></li>
            <li><?= t('<strong>Die Fundfelder</strong> einer Artenliste — Finder, Bestimmer, Bestimmungssicherheit, Menge, Substrat, Begleitvegetation, Geruch, Geschmack, Sporenpulverfarbe, Beleg-Nummer, Fundbeschreibung — stehen hier zur Ansicht.') ?></li>
            <li><?= t('<strong>Eigene Felder:</strong> „Feld hinzufügen" legt ein weiteres Feld an, das jeder Punkt dieser Liste bekommt — einzeilig oder mehrzeilig, mit Name und Vorgabe.') ?></li>
            <li><?= t('<strong>Teilen:</strong> Hier wählst du, welche Freunde und Arbeitsgruppen die Liste sehen und was jeder damit darf. Das stellst du für jede Liste einzeln ein.') ?></li>
            <li><?= t('<strong>Speichern</strong> unten rechts; „Liste löschen" steht daneben, sobald die Liste keine Artenliste mehr ist — eine Artenliste ist gegen Löschen geschützt.') ?></li>
        </ol>
    </li>
    <li><?= t('<strong>Der Zähler</strong> an einer zugeklappten Mappe: „1/2" heißt, eine von zwei Listen ist eingeblendet. Er leuchtet erst ab einem Haken; „0/2" sagt, dass darunter gerade nichts an ist.') ?></li>
    <li><?= t('<strong>Der Bereich „Meine Listen"</strong> lässt sich als Ganzes zuklappen — das Dreieck vor der Überschrift —, genauso der Bereich „Geteilt" darunter. Zugeklappt trägt auch er den Zähler, wie viele seiner Listen an sind.') ?></li>
</ol>

<?php help_section('geteilt'); ?>
<p><?= t('Unter <strong>Geteilt</strong> stehen die <strong>Freigaben</strong>, in denen du Mitglied bist — deine eigenen und die deiner Freunde —, jede mit allen Listen, die darin hängen: die der anderen und deine eigenen, die du hineingehängt hast. Eine Freigabe legst du in der Verwaltung an, dort lädst du Freunde und Arbeitsgruppen ein und hängst Listen hinein: <a href="#freigaben">%s</a>. Hier in der Leiste blendest du ein und aus, was davon auf die Karte soll.', h($numbers['freigaben'])) ?></p>
<?php help_shot('geteilt.png', t('Der Bereich Geteilt mit einer eigenen und einer fremden Freigabe'), [
    1 => [40, 10.7], 2 => [91, 33], 3 => [49, 19], 4 => [60, 19], 5 => [52, 69], 6 => [64, 87.5],
]); ?>
<ol class="help-legend">
    <li><?= t('<strong>Der Bereich „Geteilt"</strong> — das Dreieck klappt ihn als Ganzes zu, wie „Meine Listen" darüber; zugeklappt trägt er den Zähler mit den eingeblendeten Listen.') ?></li>
    <li><?= t('<strong>Eine Freigabe</strong> — das Dreieck davor klappt ihre Listen auf und zu, ein Klick auf die Zeile ebenso. Die Listen darunter blendest du wie deine eigenen ein und aus; sie tragen den Namen dessen, dem sie gehören.') ?></li>
    <li><?= t('<strong>Der Stift an deiner eigenen Freigabe</strong> öffnet ihr Fenster:') ?>
        <?php help_shot('freigabe.png', t('Das Fenster der eigenen Freigabe „Gemeinsame Funde"'), [
            '3.1' => [80, 12.6], '3.2' => [60, 27.5], '3.3' => [45, 33.4], '3.4' => [50, 45], '3.5' => [70, 95.5],
        ]); ?>
        <ol class="help-legend" style="--help-legend-prefix: '3.'">
            <li><?= t('<strong>Der Name</strong> — alle Mitglieder sehen ihn.') ?></li>
            <li><?= t('<strong>Besitzer und Mitglieder:</strong> wer die Freigabe angelegt hat und wer dabei ist. Einladen — Freunde und Arbeitsgruppen — geht in der Verwaltung (<a href="#freigaben">%s</a>).', h($numbers['freigaben'])) ?></li>
            <li><?= t('<strong>Der Filter</strong>, der der Freigabe zugrunde liegt: welche Punkte der eingehängten Listen sie überhaupt zeigt — alle, oder nur die aus einem Zeitraum oder Gebiet. Er wird beim Anlegen festgelegt und lässt sich <strong>nachträglich nicht ändern</strong>: Worauf sich die Mitglieder einmal eingelassen haben, darauf können sie sich verlassen.') ?></li>
            <li><?= t('<strong>Eigene Listen einhängen:</strong> der Baum deiner Listen — ein Haken hängt eine Liste in die Freigabe, die Mitglieder sehen dann ihre Punkte; der Haken weg nimmt sie wieder heraus. Das geht hier, jederzeit, auch nachträglich.') ?></li>
            <li><?= t('<strong>Speichern.</strong>') ?></li>
        </ol>
    </li>
    <li><?= t('<strong>Die Sprechblase</strong> öffnet den Chat dieser Freigabe — alle Mitglieder lesen mit. Ein grüner Punkt daran heißt: ungelesen.') ?></li>
    <li><?= t('<strong>Der Stift an einer fremden Freigabe</strong> öffnet dasselbe Fenster, aber es ändert nichts beim Besitzer:') ?>
        <?php help_shot('freigabe-fremd.png', t('Das Fenster der fremden Freigabe „Pilzfreunde Nord"'), [
            '5.1' => [80, 12.2], '5.2' => [38, 31.5], '5.3' => [45, 35.6], '5.4' => [50, 46.8],
        ]); ?>
        <ol class="help-legend" style="--help-legend-prefix: '5.'">
            <li><?= t('<strong>Wie du ihn nennst:</strong> ein Name nur für dich — die anderen sehen weiter den Namen des Erstellers. Leer heißt: wie beim Ersteller.') ?></li>
            <li><?= t('<strong>Die Tür hinter deinem Namen</strong> ist dein Austritt: Du verlässt die Freigabe, ihre Listen verschwinden von deiner Karte, und deine eingehängten Listen kommen wieder heraus. Der Besitzer kann dich neu einladen.') ?></li>
            <li><?= t('<strong>Der Filter</strong> des Besitzers — auch hier fest, du siehst, worauf du dich verlässt.') ?></li>
            <li><?= t('<strong>Eigene Listen einhängen</strong> geht auch in einer fremden Freigabe: Was du hier anhakst, sehen die anderen Mitglieder.') ?></li>
        </ol>
    </li>
    <li><?= t('<strong>Der Stift an einer fremden Liste</strong> öffnet ein kleines Fenster, das nichts beim Besitzer ändert:') ?>
        <?php help_shot('fremde-liste.png', t('Das Fenster einer fremden Liste'), [
            '6.1' => [50, 15], '6.2' => [84, 21], '6.3' => [85, 42], '6.4' => [46, 74], '6.5' => [48, 91],
        ]); ?>
        <ol class="help-legend" style="--help-legend-prefix: '6.'">
            <li><?= t('<strong>Die Überschrift</strong> nennt die Liste, die Freigabe, in der sie hängt, und wem die Freigabe gehört — dieselbe Liste kann in mehreren Freigaben hängen.') ?></li>
            <li><?= t('<strong>Deine Rechte:</strong> was dir der Besitzer der Liste an ihren Punkten erlaubt — nur ansehen, oder auch Titel und Notizen ändern, Punkte verschieben, löschen, Bilder löschen, bestimmte Felder ausfüllen. Das legt jeder für seine eigene Liste fest, wenn er sie in eine Freigabe hängt (<a href="#freigaben">%s</a>); du siehst hier, was er dir eingeräumt hat. Im Punktfenster steht dann alles andere ausgegraut.', h($numbers['freigaben'])) ?></li>
            <li><?= t('<strong>Farbe auf diesem Gerät:</strong> die Marker der fremden Liste in einer Farbe deiner Wahl — nur auf deiner Karte, damit sie sich von deinen eigenen unterscheiden. Der Besitzer merkt davon nichts.') ?></li>
            <li><?= t('<strong>Form auf diesem Gerät:</strong> ebenso die Form der Marker.') ?></li>
            <li><?= t('<strong>Zurücksetzen</strong> nimmt Farbe und Form wieder so, wie der Besitzer sie gewählt hat.') ?></li>
        </ol>
    </li>
</ol>

<?php help_section('exkursionen-leiste'); ?>
<p><?= t('Unter <strong>Exkursionen</strong> stehen die gemeinsamen Ausflüge, die du leitest oder mitleitest, und die, an denen du teilgenommen hast. Eingeblendet zeigt eine Exkursion die Funde <strong>aller</strong> Beteiligten — deine, die der Gäste, die der Mitleitenden. Anlegen, den Einladungs-QR-Code zeigen und leiten tust du in der Verwaltung: <a href="#exkursionen">%s</a>.', h($numbers['exkursionen'])) ?></p>
<?php help_shot('exkursionen.png', t('Der Bereich Exkursionen in der Seitenleiste'), [
    1 => [50, 14], 2 => [50, 40], 3 => [50, 62.5], 4 => [91, 85],
]); ?>
<ol class="help-legend">
    <li><?= t('<strong>Der Bereich „Exkursionen"</strong> — wie die beiden über ihm als Ganzes zuklappbar, zugeklappt mit dem Zähler.') ?></li>
    <li><?= t('<strong>Alle Exkursionen</strong> meint jede — auch die noch nicht aufgeklappten und die der nächsten Saison. Wählst du danach eine einzelne, geht „Alle" von selbst wieder aus, und umgekehrt.') ?></li>
    <li><?= t('<strong>Eine Exkursion</strong> mit ihrem Namen; bei einer fremden steht dahinter, wer sie leitet. Ein Klick blendet ihre Funde ein und aus. Die zehn jüngsten stehen ausgeschrieben, „Weitere zeigen" klappt den Rest auf — wann sie stattfand, ist eine Frage an den Zeitfilter.') ?></li>
    <li><?= t('<strong>Die Zahl</strong> der Funde auf ihr — deine eigenen mitgezählt.') ?></li>
</ol>

<?php help_section('punkte'); ?>
<p><?= t('Der Reiter <strong>Punkte</strong> zeigt genau die Punkte der eingeblendeten Listen — die neuesten zuerst. Jede Zeile trägt den Titel, darunter Fundzeit und Ort; bei einem fremden Punkt steht davor, von wem er ist.') ?></p>
<div class="help-shots">
<div>
<p class="help-caption"><?= h(t('Desktop-Version')) ?></p>
<?php help_shot('punkte.png', t('Der Reiter Punkte: Filter, Sortierung, ein markierter Fund'), [
    1 => [30, 13.75], 2 => [53, 19.75], 3 => [38, 33.75], 4 => [77.8, 44],
]); ?>
</div>
<div>
<p class="help-caption"><?= h(t('Handy-Version')) ?></p>
<?php help_shot('punkte-handy.png', t('Der Reiter Punkte am Handy, über der Karte'), [
    1 => [40, 15.8], 2 => [62, 23], 3 => [45, 38.8], 4 => [85, 92.5],
]); ?>
</div>
</div>
<ol class="help-legend">
    <li><?= t('<strong>Der Filter</strong> — klappt die Filtergruppen auf; die Zahl daran sagt, wie viele Filter gerade wirken.') ?></li>
    <li><?= t('<strong>Die zwei Sortierknöpfe</strong> — nach Zeit, die neuesten zuerst, oder nach Namen, dann stehen gleiche Arten beieinander.') ?></li>
    <li><?= t('<strong>Ein markierter Fund</strong> — die gewählte Zeile trägt den Akzent an ihrer Kante. Am Rechner öffnet ein Klick das Punktfenster und die Karte springt hin; am Handy zeigt er den Punkt erst auf der Karte, ein zweiter Tipp öffnet das Fenster.') ?></li>
    <li><?= t('<strong>Zurück zur Karte</strong> — am Rechner der Griff an der Kante der Leiste (ein Klick klappt sie ein, Ziehen macht sie breiter oder schmaler), am Handy der Knopf „Schließen“: Dort liegt die Leiste über der Karte.') ?></li>
</ol>

<?php help_section('filter'); ?>
<p><?= t('Filter halten zurück, was gerade nicht gemeint ist — auf der Karte und in der Punkteliste zugleich. Sie sind in acht Gruppen geordnet; der Knopf „Filter" klappt sie auf, die Zahl daran sagt, wie viele Filter wirken. <strong>Dieselben Filter</strong> stehen in der Verwaltung unter Organisieren (<a href="#organisieren">%s</a>) — dort wählen sie Punkte für die Tabelle aus, hier für die Karte.', h($numbers['organisieren'])) ?></p>
<?php help_shot('filter.png', t('Die acht Filtergruppen'), [
    1 => [29.5, 5.7], 2 => [28, 34.1], 3 => [27.4, 43.8], 4 => [30.6, 53.6], 5 => [46.3, 63.4], 6 => [44.3, 73.2], 7 => [46.8, 83], 8 => [44.5, 92.7],
]); ?>
<ol class="help-legend">
    <li><strong><?= h(t('Text')) ?></strong>
        <?php help_shot('filter-text.png', t('Der Textfilter')); ?>
        <p><?= t('Sucht in allem, was ein Punkt trägt: Titel, Notiz, Fundfelder, Messtischblatt, Gemeinde, Schutzgebiet.') ?></p>
        <p><?= t('<strong>Eine Zeile ist eine Bedingung.</strong> Jede Zeile sagt, ob ihr Text vorkommen soll („enthält") oder gerade nicht („enthält nicht") — das Zeichen vor dem Feld stellt um. <strong>Mehrere Zeilen gelten alle zugleich</strong> (und): Jede muss erfüllt sein. <strong>Kommas innerhalb einer Zeile heißen oder</strong>: Einer der Begriffe genügt — bei „enthält nicht": keiner darf vorkommen.') ?></p>
        <p class="help-example"><?= t('Beispiel 1 — nur zwei Arten') ?></p>
        <?php help_shot('filter-text-1.png', t('Beispiel 1: eine Zeile, zwei Arten mit Komma')); ?>
        <p><?= t('Du willst alle Funde von Boletus edulis und Boletus reticulatus sehen. Das ist <em>eine</em> Zeile mit Komma: <code>Boletus edulis, Boletus reticulatus</code>. Gelesen heißt die Zeile: <em class="help-read">enthält Boletus edulis oder Boletus reticulatus</em>.') ?></p>
        <p><?= t('Schreibst du dagegen Boletus edulis in die erste Zeile und Boletus reticulatus in die zweite, liest sich das: <em class="help-read">enthält Boletus edulis und enthält Boletus reticulatus</em> — beides in einem Fund, und das gibt es nicht: Die Karte bliebe leer.') ?></p>
        <p class="help-example"><?= t('Beispiel 2 — ohne zwei Gattungen') ?></p>
        <?php help_shot('filter-text-2.png', t('Beispiel 2: eine Zeile auf „enthält nicht" mit zwei Gattungen')); ?>
        <p><?= t('Du willst alles außer Morcheln und Verpeln. Eine Zeile auf „enthält nicht" stellen und <code>Morchella, Verpa</code> eintragen. Gelesen heißt die Zeile: <em class="help-read">enthält nicht Morchella oder Verpa</em>.') ?></p>
        <p><?= t('Hier ginge es auch zweizeilig — erste Zeile „enthält nicht" <code>Morchella</code>, zweite Zeile „enthält nicht" <code>Verpa</code> — und läse sich: <em class="help-read">enthält nicht Morchella und enthält nicht Verpa</em>. Beides darf ein Fund zugleich erfüllen, also kommt dasselbe heraus.') ?></p>
    </li>
    <li><strong><?= h(t('Zeit')) ?></strong>
        <?php help_shot('filter-time.png', t('Der Zeitfilter')); ?>
        <p><?= t('„Letzte Tour …" klappt die Liste deiner letzten Touren auf, die deiner Freunde mit „von …" gekennzeichnet; darunter ein Zeitraum von/bis — der kleine Pfeil dazwischen übernimmt das Datum von „von" nach „bis", für einen einzelnen Tag oder als Anfang eines Zeitraums. Der <strong>Jahresregler</strong> darunter hat zwei Anfasser auf Jahresgrenzen — das älteste Jahr deines Bestands links, das jüngste rechts; ein Jahr ist eine Stufe, ganz auseinander heißt alle Jahre. Was du ziehst, steht sofort in „von" und „bis" — zwei Griffe, und du siehst die Funde eines Jahres oder zweier. „ohne Fundzeit" zeigt die Punkte, die keine tragen — solche entstehen nur durch Importe; beim Kartieren mit der App bekommt jeder Punkt seine Fundzeit.') ?></p>
    </li>
    <li><strong><?= h(t('Ort')) ?></strong>
        <?php help_shot('filter-place.png', t('Der Ortsfilter'), ['3.1' => [20, 22]]); ?>
        <p><?= t('Bereiche, die du auf der Karte zeichnest (3.1); Bundesländer; Gemeinden; Messtischblätter („4625/2", Komma heißt oder). Jede Wahl verengt, was die anderen anbieten.') ?></p>
        <p class="help-example"><?= t('Bereich zeichnen') ?></p>
        <p><?= t('Ein Bereich ist ein Stück Karte, das du selbst umrandest — dein Revier, ein Waldstück, eine Wiese. Der Filter zeigt dann nur die Funde darin. Wer ein Gebiet bearbeitet, braucht diesen Filter am häufigsten. Ein Tipp auf „Bereich zeichnen …" (3.1) schiebt die Seitenleiste beiseite und macht die Karte zum Zeichenblatt:') ?></p>
        <?php help_shot('filter-area-drawing.png', t('Die Karte beim Zeichnen eines Bereichs'), [
            '3.2' => [32.5, 46], '3.3' => [48, 5.8], '3.4' => [92, 5.8],
        ]); ?>
        <?php /* The sub-numbers of entry 3, running on across both pictures
             (18.09.2026: „3.1, 3.2, 3.3 und so weiter — dass man
             sieht, dass das dazugehört und nicht wieder bei 1 beginnt");
             the button in the picture above is 3.1. */ ?>
        <ol class="help-legend" style="--help-legend-prefix: '3.'; counter-reset: help 1">
            <li><?= t('<strong>Ecke für Ecke antippen.</strong> Jede Ecke wird ein Punkt, und vom letzten folgt die Linie dem Zeiger bis zur nächsten. Drei Ecken sind das Mindeste, nach oben gibt es keine Grenze.') ?></li>
            <li><?= t('<strong>Ecke zurück</strong> nimmt die letzte Ecke wieder weg; <strong>Verwerfen</strong> die ganze Zeichnung.') ?></li>
            <li><?= t('<strong>Fertig</strong> schließt den Umriss — die Seitenleiste kommt zurück, und der Bereich filtert sofort.') ?></li>
        </ol>
        <?php help_shot('filter-area-done.png', t('Die Gruppe Ort mit einem gezeichneten Bereich'), [
            '3.5' => [41, 25.5], '3.6' => [85, 25.5], '3.7' => [44, 42],
        ]); ?>
        <ol class="help-legend" style="--help-legend-prefix: '3.'; counter-reset: help 4">
            <li><?= t('<strong>Der Bereich</strong> steht als „Bereich 1" mit seiner Fläche in der Gruppe Ort.') ?></li>
            <li><?= t('<strong>Das Kreuz</strong> nimmt ihn wieder aus dem Filter; die Zeichnung ist dann weg.') ?></li>
            <li><?= t('<strong>Weiterer Bereich …</strong> zeichnet einen zweiten. Mehrere Bereiche heißen: in einem davon genügt.') ?></li>
        </ol>
        <p><?= t('Die Bereiche merkt sich dein Gerät, auch über einen Neustart. In Organisieren (<a href="#organisieren">%s</a>) gelten dieselben Bereiche; gezeichnet werden sie nur hier auf der Karte.', h($numbers['organisieren'])) ?></p>
    </li>
    <li><strong><?= h(t('Höhe')) ?></strong>
        <?php help_shot('filter-elevation.png', t('Der Höhenfilter')); ?>
        <p><?= t('Ein Regler mit zwei Enden: die untere und die obere Höhe über dem Meer, ganz außen heißt „alle Höhen". Das obere Ende ist offen: Steht es ganz rechts, zählen auch Funde über 2500 m — beide Enden ganz rechts zeigt also alles ab 2500 m. Die Höhe eines Punktes trägt der Server nach.') ?></p>
    </li>
    <li><strong><?= h(t('Bestimmung')) ?></strong>
        <?php help_shot('filter-identification.png', t('Der Bestimmungsfilter')); ?>
        <p><?= t('Nur nicht bestimmte Funde; die Kürzel im Titel (sp., cf., aff., s. l., s. str., agg. — s. str. ist keine offene Bestimmung, sondern die enge, und fällt deshalb nicht unter „nur nicht bestimmte"); ob Material vorliegt: bearbeitet oder unbearbeitet; und das Feld Bestimmungssicherheit — makroskopisch sicher, mikroskopisch sicher, genetisch sicher, unsicher.') ?></p>
    </li>
    <li><strong><?= h(t('Systematik')) ?></strong>
        <?php help_shot('filter-taxonomy.png', t('Der Systematikfilter')); ?>
        <p><?= t('Reich, Stamm, Klasse, Ordnung, Familie, Gattung: Was du unten wählst, ergibt die Ränge darüber von selbst; angeboten wird nur, was im Bestand vorkommt. Wo wenige zur Wahl stehen, ist der Rang ein Klappmenü; wo viele, ein Textfeld, das beim Tippen vorschlägt — ein paar Buchstaben genügen, ein Tipp auf den Vorschlag füllt das Feld.') ?></p>
    </li>
    <li><strong><?= h(t('Gefährdung')) ?></strong>
        <?php help_shot('filter-red-list.png', t('Der Gefährdungsfilter')); ?>
        <p><?= t('Die Kategorien der Roten Liste; mehrere angehakt heißt: eine davon genügt. Darunter der Haken „nur Verantwortungsarten“: Arten, für deren Erhalt Deutschland eine besondere Verantwortung trägt — die Liste der DGfM. Er gilt zusätzlich zu den Kategorien.') ?></p>
    </li>
    <li><strong><?= h(t('Speisewert')) ?></strong>
        <?php help_shot('filter-edibility.png', t('Der Speisewertfilter')); ?>
        <p><?= t('Die drei Listen des DGfM-Fachausschusses Pilzverwertung und Toxikologie: Speisepilz, uneinheitlich beurteilt, Giftpilz — mehrere angehakt heißt: eine davon genügt. Eine Art kann mehrere Marken tragen; Morcheln stehen als Speisepilz mit Warnung, als uneinheitlich beurteilt und roh als Giftpilz. Sagt die Liste etwas über eine ganze Gattung, gilt es für jede ihrer Arten. Die Marken einer Art zeigt das Punktfenster (<a href="#fenster">%s</a>) in seiner Faktenzeile; die Bemerkung der DGfM erscheint, wenn du mit der Maus darüber gehst.', h($numbers['fenster'])) ?></p>
    </li>
</ol>

<p><?= t('<strong>Auch die Wegstrecken</strong> (Zusatzlayer, <a href="#layer">%s</a>) gehorchen dem Filter: Eine Strecke bleibt, solange auf ihr ein Fund liegt, der den Filter besteht — und verschwindet, sobald alle ihre Funde ausgefiltert sind. Eine Strecke ohne Funde folgt nur dem Zeitraum und geht, sobald irgendein anderer Filter gesetzt ist.', h($numbers['layer'])) ?></p>

<?php help_section('fenster'); ?>
<p class="help-see"><?= t('Die Marken der DGfM-Listen in der Faktenzeile — Speisepilz, Giftpilz, Verantwortungsart — erklärt der Filter (<a href="#filter">%s</a>).', h($numbers['filter'])) ?></p>
<p class="help-see"><?= t('Wie ein Punkt entsteht und dieses Fenster erreicht, steht unter <a href="#setzen">%s</a>.', h($numbers['setzen'])) ?></p>
<p><?= t('Die Seitenleiste rechts ist das <strong>Punktfenster</strong>. Sie öffnet sich, wenn du einen Punkt setzt oder einen Marker antippst. Bei einem eigenen Fund ist sie der Editor: Name, Fotos, Fundzeit, Notiz, alles wird hier eingetragen und unten gespeichert. Bei einem fremden Fund — einem aus einer Freigabe — ist sie ein Anzeigefenster: du siehst alles, änderst aber nur, was der Besitzer dir erlaubt hat.') ?></p>

<?php /* THE FRAME FIRST, THE TABS AFTER (18.09.2026: „vor dem Text
     einen Screenshot, wo die Hauptsachen dieser Sidebar erklärt werden —
     die Überschrift, wo man den Fund löschen kann, die Reiter, die
     Fußzeile; danach gehen wir auf die einzelnen Reiter ein"). What
     stands on every tab — head, bucket, tab row, footer — is explained
     here once; 1.7.1 to 1.7.3 then hold only what their tab holds. */ ?>
<?php help_shot('punkt-rahmen.png', t('Das Punktfenster eines eigenen Fundes: Kopf, Reiter, Fußzeile'), [
    1 => [60, 3.9], 2 => [91, 17.6], 3 => [68, 23], 4 => [6, 95], 5 => [50, 90],
]); ?>
<ol class="help-legend">
    <li><?= t('<strong>Der Kopf:</strong> der Name des Fundes, darunter die <strong>Faktenzeile</strong> — alles, was der Server zum Ort nachträgt und niemand tippt: Fundnummer, Genauigkeit, Höhe, Gemeinde, Messtischblatt bis zum Vierundsechzigstel, Schutzgebiet, Rote Liste und die DGfM-Marke der Art. Sie steht über allen drei Reitern; ein Fingerzeig auf ein Stück davon sagt, was es ist.') ?></li>
    <li><?= t('<strong>Der Papierkorb</strong> legt den Fund in den Papierkorb (<a href="#papierkorb">%s</a>) — von dort kommt er zurück. Hat der Fund Mitbesitzer, steht hier eine Tür statt des Korbs: du gibst ihn ab, und er bleibt bei den anderen.', h($numbers['papierkorb'])) ?></li>
    <li><?= t('<strong>Die drei Reiter:</strong> Text für alles, was der Fund sagt — Titel, Felder, Notiz, Fundzeit, Koordinaten (<a href="#fenster-text">%s</a>); Belege für Fotos und Sequenzen (<a href="#fenster-photos">%s</a>); Karte für Liste und Umriss (<a href="#fenster-place">%s</a>). Die Zahl auf „Belege" sagt, wie viele Bilder und Sequenzen hängen — hinter einem Reiter sieht man das sonst nicht.', h($numbers['fenster-text']), h($numbers['fenster-photos']), h($numbers['fenster-place'])) ?></li>
    <li><?= t('<strong>Die Fußzeile:</strong> <strong>Schließen</strong> legt das Fenster zu; <strong>Speichern</strong> schreibt, was du in Text und Einordnung geändert hast, auf einmal. Fotos brauchen es nicht — sie hängen schon beim Aufnehmen am Fund. Das Fenster speichert nichts von selbst: schließt du es mit Änderungen, fragt es nach, was gespeichert werden soll — mit „Speichern" oder „Verwerfen" als Antwort.') ?></li>
    <li><?= t('<strong>Zeigen</strong> rückt den Fund in die Kartenmitte. <strong>Am Handy ist Zeigen der Weg, einen Marker zu verschieben:</strong> das Fenster tritt beiseite, und der Marker lässt sich mit dem Finger ziehen.') ?>
        <?php help_shot('punkt-handy-move.png', t('Das Handy nach „Zeigen": die Leiste „Marker verschieben" über der Karte'), [
            '5.1' => [80, 4.5], '5.2' => [62, 48.6], '5.3' => [8, 11],
        ]); ?>
        <ol class="help-legend" style="--help-legend-prefix: '5.'">
            <li><?= t('<strong>Die Leiste</strong> nennt die Koordinate und läuft beim Ziehen mit.') ?></li>
            <li><?= t('<strong>Der Marker</strong> mit dem Ring: ihn ziehst du an die richtige Stelle. Am Rechner geht das ebenso, sobald der Fund gewählt ist — dort steht das Fenster daneben und braucht nicht beiseitezutreten.') ?></li>
            <li><?= t('<strong>Fertig</strong> holt das Fenster zurück. Die neue Stelle steht jetzt im Koordinatenfeld, aber noch nicht in der Datenbank — erst <strong>Speichern</strong> macht sie fest. Schließt du stattdessen, fragt das Fenster: die neue Position speichern oder verwerfen?') ?></li>
        </ol>
    </li>
    <?php /* THE WAY IN ON THE PHONE as entry 6 with 6.1 to 6.4
         (18.09.2026: „das ist ja jetzt etwas versteckt … diesen Weg zeigen
         wir auf jeden Fall mit Screenshots", then „als Punkt 5, und alle
         deren Punkte als Unterpunkte 5.1, 5.2 und so weiter" — 6 since
         Zeigen took 5: „den Zeigen-Button eben auch mit fünf markieren"): a row in
         the sidebar does not open the window but shows the point on the
         map, and only the pencil on the peek line opens it. */ ?>
    <li><?= t('<strong>Der Weg hinein am Handy.</strong> Am Rechner genügt ein Klick — auf den Marker in der Karte oder auf eine Zeile im Reiter Punkte (<a href="#punkte">%s</a>) —, und das Fenster steht rechts neben der Karte. Am Handy öffnet der Marker das Fenster ebenso direkt; aus der Liste heraus ist der Weg aber einen Schritt länger, denn dort füllt das Fenster den ganzen Schirm und würde genau die Karte verdecken, zu der du gerade wolltest:', h($numbers['punkte'])) ?>
        <div class="help-shots help-shots-phones">
        <?php help_shot('punkte-handy.png', t('Der Reiter Punkte am Handy mit dem gewählten Fund'), ['6.1' => [88, 41]]); ?>
        <?php help_shot('punkt-handy-peek.png', t('Die Karte mit dem gezeigten Fund und der Zeile darunter'), ['6.2' => [62, 48.6], '6.3' => [18, 83.9]]); ?>
        <?php help_shot('punkt-handy.png', t('Das Punktfenster am Handy'), ['6.4' => [7, 96]]); ?>
        </div>
        <ol class="help-legend" style="--help-legend-prefix: '6.'">
            <li><?= t('<strong>Ein Tipp auf die Zeile</strong> im Reiter Punkte öffnet noch kein Fenster: die Seitenleiste geht zu, und die Karte fliegt zum Fund.') ?></li>
            <li><?= t('<strong>Der Fund</strong> steht mit einem Ring in der Kartenmitte — du siehst erst einmal, wo er liegt.') ?></li>
            <li><?= t('<strong>Die Zeile unten</strong> nennt ihn, und <strong>der Stift</strong> daran öffnet das Fenster. Bei einem fremden Fund, den du nur ansehen darfst, steht statt des Stifts ein Auge. Ein Tipp irgendwo in die Karte lässt die Zeile verschwinden — nichts geht auf.') ?></li>
            <li><?= t('<strong>Das Fenster</strong> füllt den Schirm; „Schließen" bringt die Karte zurück.') ?></li>
        </ol>
    </li>
</ol>

<?php help_section('fenster-text'); ?>
<?php help_shot('punkt.png', t('Der Reiter Text: Titel, die Felder der Liste, Notiz, Fundzeit, Koordinaten'), [
    1 => [75, 15], 2 => [75, 40], 3 => [75, 66], 4 => [75, 74], 5 => [75, 80], 6 => [35, 83.5],
]); ?>
<ol class="help-legend">
    <li><?= t('<strong>Die Art.</strong> Das erste Feld heißt „Art" — in jeder Liste. In einer Artenliste schlägt es beim Tippen wissenschaftliche Namen aus der Referenz vor, mit dem Status der Roten Liste dahinter. Aus welchen Artengruppen die Vorschläge kommen, steht bei der Liste, in der der Fund liegt — in der Pilzliste Pilze, in der Flechtenliste Flechten (<a href="#arten">%s</a>). In der Verwaltung unter Einstellungen → Arten schaltest du „Schon gefundene Arten zuerst vorschlagen" ein — dann stehen die Arten aus deinen und den mit dir geteilten Funden oben in der Liste.', h($numbers['arten'])) ?></li>
    <li><?= t('<strong>Die Felder der Liste:</strong> was die Liste, in der der Fund liegt, an Feldern mitbringt — hier die einer Artenliste: Finder, Bestimmer, Bestimmungssicherheit, Anzahl, Substrat, Begleitvegetation, Geruch, Geschmack, Sporenpulverfarbe, Beleg-Nr., Fundbeschreibung. Liegt der Fund in mehreren Listen, stehen die Felder aller hier, jedes einmal. Welche Felder eine Liste hat, legst du in ihrem Fenster fest (<a href="#listen">%s</a>). Substrat / Wirt schlägt beim Tippen Pflanzen, Insekten und Pilze vor, Begleitvegetation nur Pflanzen; eigene Felder, die als Artfeld markiert sind, schlagen dieselben Gruppen vor wie der Titel (<a href="#arten">%s</a>). In einem mehrzeiligen Feld zählt jede Zeile als eigener Name.', h($numbers['listen']), h($numbers['arten'])) ?></li>
    <li><?= t('<strong>Die Notiz</strong> — der Sammelplatz für alles, was in kein Feld passt. Sie wächst mit dem Text.') ?></li>
    <li><?= t('<strong>Fundzeit</strong> mit Uhrzeit: beim Setzen trägt die Uhr des Geräts sie ein; das Feld ist für den Fund von letzter Woche, den du nachträgst. Die Uhrzeit entscheidet mit, zu welcher Tour der Fund gehört (<a href="#setzen">%s</a>).', h($numbers['setzen'])) ?></li>
    <li><?= t('<strong>Koordinaten</strong> in Grad, sechs Nachkommastellen — zum Lesen und Kopieren, nicht zum Tippen: verschoben wird ein Fund in der Karte, mit der Maus oder nach „Zeigen" mit dem Finger (<a href="#fenster">%s</a>, Punkt 5). Der Stift öffnet das Feld für den einen Fall, dass eine Position von woanders kommt — ein Pin aus Google Maps, eine Angabe eines Freundes —, und das Kopierzeichen legt sie in die Zwischenablage.', h($numbers['fenster'])) ?></li>
    <li><?= t('<strong>Angelegt</strong> — wann der Punkt entstanden ist. Anders als die Fundzeit ändert sich das nie.') ?></li>
</ol>

<?php help_section('fenster-photos'); ?>
<?php help_shot('punkt-fotos.png', t('Der Reiter Belege: die Galerie in Motivgruppen, darunter die Sequenzen'), [
    1 => [60, 11.6], 2 => [27, 17], 3 => [60, 17], 4 => [80, 43], 5 => [88, 73.5],
]); ?>
<ol class="help-legend">
    <li><?= t('<strong>Die Zahl am Reiter:</strong> wie viele Belege am Fund hängen, Bilder und Sequenzen — die Bilder mitgezählt, die noch auf Übertragung warten.') ?></li>
    <li><?= t('<strong>Aufnehmen</strong> öffnet die Kamera des Handys, mit allem, was sie kann. Vorher fragt die App nach dem Motivnamen. Das Bild wird auf 2048 Pixel verkleinert, auf diesem Server abgelegt und gegen deinen Speicherplatz gerechnet; ohne Netz wartet es auf dem Gerät und geht später von selbst.') ?></li>
    <li><?= t('<strong>Auswählen</strong> nimmt eine fertige Datei — ein Stack, ein Mikrofoto, eine Rohdatei von der richtigen Kamera — und legt sie in voller Größe in deine eigene Cloud, nicht auf diesen Server. Ohne verbundene Cloud (<a href="#cloud">%s</a>) ist der Knopf aus und sagt, warum.', h($numbers['cloud'])) ?></li>
    <li><?= t('<strong>Die Galerie</strong>, nach Motivnamen gruppiert — Hauptmotiv, Schnittbild, Sporen … in der Reihenfolge deiner Vorschlagsliste (<a href="#messen">%s</a>). Ein Tipp auf ein Bild öffnet es groß: dort bekommt es seinen Motivnamen, einen Maßstab und Messungen, und dort wird es gelöscht. Bilder, die andere an den Fund gehängt haben, tragen den Namen dessen, der sie gemacht hat.', h($numbers['messen'])) ?></li>
    <li><?= t('<strong>Sequenzen:</strong> der zweite Beleg — eine Gensequenz, als Text. „Sequenz hinzufügen" nimmt FASTA oder die nackten Basen, aus einer Datei oder eingefügt, dazu Marker (ITS, LSU …), Zugangsnummer, Probennummer, Labor und Primer; ein FASTA mit mehreren Einträgen ergibt mehrere Sequenzen. Was keine Basen sind, wird abgewiesen. Eine Sequenz gehört dem, der sie hochlädt, wie ein Bild dem Fotografen: an einem fremden Fund macht sie ihn zum Mitbesitzer, und nur er löscht sie wieder. Jede Zeile lässt sich als FASTA kopieren oder als Datei speichern, und der Fundbericht druckt sie mit.') ?></li>
</ol>

<?php help_section('fenster-place'); ?>
<?php help_shot('punkt-karte.png', t('Der Reiter Karte: Liste und Umriss'), [
    1 => [75, 43], 2 => [60, 80],
]); ?>
<ol class="help-legend">
    <li><?= t('<strong>Listen:</strong> deine Listen mit einem Haken bei der Liste, in der der Fund liegt. Die Listen einer Mappe stehen eingerückt unter ihr. Ein anderer Haken legt den Fund um, und mit ihm wechseln die Felder im Reiter Text.') ?></li>
    <li><?= t('<strong>Fläche:</strong> ein Fund kann eine Ausdehnung haben — eine Brandfläche, ein Bestand. „Umriss ziehen" macht die Karte zum Zeichenblatt, wie beim Bereich zeichnen im Filter (<a href="#filter">%s</a>); die Koordinate darüber zeigt dann auf einen Punkt in der Fläche.', h($numbers['filter'])) ?></li>
</ol>

<?php help_section('fenster-foreign'); ?>
<p><?= t('Ein Fund aus einer Freigabe (<a href="#geteilt">%s</a>) öffnet dasselbe Fenster — nur steht in ihm ausgegraut, was dir sein Besitzer nicht eingeräumt hat. Was er erlaubt, legt er fest, wenn er seine Liste in die Freigabe hängt; der Stift an der fremden Liste in der Seitenleiste sagt es dir in einem Satz.', h($numbers['geteilt'])) ?></p>
<?php help_shot('punkt-fremd.png', t('Das Punktfenster eines fremden Fundes ohne Schreibrechte'), [
    1 => [80, 22], 2 => [75, 45], 3 => [30, 95],
]); ?>
<ol class="help-legend">
    <li><?= t('<strong>Die Herkunft:</strong> wem der Fund gehört und über welche Freigabe und Liste er zu dir kommt. Diese Zeile gibt es nur bei fremden Funden, und unter ihr ist alles Weitere zu lesen: was grau ist, ist nicht kaputt — es gehört jemand anderem.') ?></li>
    <li><?= t('<strong>Was du nicht ändern darfst, steht als bloßer Text</strong> — ohne Rahmen, ohne Lösch-Kreuz, ohne Klappmenü; ein Feld ohne Inhalt fehlt ganz. Hier ist alles Text, weil der Besitzer nichts freigegeben hat. Mit Rechten stehen genau die Felder als Eingabe da, die er genannt hat: Titel, Notiz, einzelne Felder, oder die Koordinaten fürs Verschieben. Die Fundzeit ist immer nur Text, dafür gibt es kein Recht. Der Listenbaum fehlt: in welcher seiner Listen der Fund liegt, ist die Ordnung des Besitzers.') ?></li>
    <li><?= t('<strong>Die Fußzeile ohne Speichern:</strong> wo nichts zu ändern ist, gibt es nichts zu speichern. Auch der Papierkorb im Kopf fehlt, solange der Besitzer das Löschen nicht erlaubt hat — und der Reiter Fotos, solange am Fund keine Bilder hängen: ansehen darfst du sie immer, dazutun nur an einem Fund, den du mitbesitzt.') ?></li>
</ol>

<?php help_section('verwaltung'); ?>
<p>
    <?= t('Das Zahnrad in der Kopfzeile der Leiste führt hierher. Die Verwaltung ist in <strong>Bereiche</strong> geteilt, jeder mit eigenen Reitern; die Adresse merkt sich den Reiter, ein Lesezeichen führt also wieder genau dorthin.') ?>
</p>
<?php /* The marks sit at each tab's right edge, measured on ben's row at
         the tool's 1200 px (admin/help_shots.mjs verwaltung, 02.10.2026,
         after the split into Kontakte and Gemeinsam): the edge in per cent
         of the 1162 px bar, plus the 0.3 the old marks stood beside it. */ ?>
<?php help_shot('verwaltung.png', t('Die Reiter der Verwaltung'), [
    1 => [14.2, 50], 2 => [24.9, 50], 3 => [34.3, 50], 4 => [46.8, 50], 5 => [59.9, 50], 6 => [68.7, 50], 7 => [78.1, 50], 8 => [90, 50],
]); ?>
<ol class="help-legend">
    <li><?= h(t('Einstellungen')) ?></li>
    <li><?= h(t('Kontakte')) ?></li>
    <li><?= h(t('Offline')) ?></li>
    <li><?= h(t('Gemeinsam')) ?></li>
    <li><?= h(t('Organisieren')) ?></li>
    <li><?= h(t('Cloud')) ?></li>
    <li><?= h(t('Import')) ?></li>
    <li><?= h(t('Papierkorb')) ?></li>
</ol>

<?php help_section('einstellungen'); ?>
<ul>
    <li><?= t('<strong>Konto:</strong> Name, E-Mail-Adresse, Passwort.') ?></li>
    <li><?= t('<strong>Oberfläche:</strong> Hintergrund, Markierungsfarbe und Sprache.') ?></li>
    <li><?= t('<strong>Arten:</strong> welche Gruppen das Titelfeld vorschlägt.') ?></li>
    <li><?= t('<strong>Fundnummer:</strong> das Muster für deine laufende Nummer.') ?></li>
    <li><?= t('<strong>Geräte:</strong> ein Gerät koppeln, das ohne Passwort auf deine Funde darf — für die Aufzeichnungs-App.') ?></li>
</ul>


<?php help_section('konto'); ?>

<?php help_section('oberflaeche'); ?>

<?php help_section('arten'); ?>
<p><?= t('Welche <strong>Artengruppen</strong> das Feld „Art" im Punktfenster vorschlägt (<a href="#fenster-text">%s</a>, Punkt 1), legst du bei jeder Liste fest: im Fenster „Liste bearbeiten" unter „Was enthält diese Liste?". In der Pilzliste kommen dann nur Pilze, in der Flechtenliste nur Flechten. Substrat / Wirt schlägt Pflanzen, Insekten und Pilze vor, Begleitvegetation nur Pflanzen. Die Namen aller Gruppen, die deine Listen und die mit dir geteilten brauchen, kommen sofort aufs Gerät — so gehen die Vorschläge auch im Wald ohne Empfang. Braucht keine Liste eine Gruppe mehr, werden ihre Namen wieder entfernt. Hier unter Einstellungen → Arten steht, woher die Namen kommen.', h($numbers['fenster-text'])) ?></p>
<p><?= t('<strong>Schon gefundene Arten zuerst vorschlagen</strong> — auf diesem Gerät ein- und ausschaltbar, von Haus aus an: Arten aus deinen und den mit dir geteilten Funden stehen oben in der Vorschlagsliste, mit dem Grund dabei — zuerst die von der laufenden Tour, danach die häufig gefundenen vor den seltenen. Ein Fund aus dieser Jahreszeit (30 Tage vor und nach dem Funddatum, über alle Jahre) zählt dabei doppelt, ebenso einer aus 20 km Umkreis um den Fund. Darunter folgt die Referenz wie bisher.') ?></p>
<p class="help-see"><?= t('Ob das Titelfeld einer Liste Vorschläge bekommt, entscheidet der Haken „Artenliste" im Fenster der Liste — <a href="#listen">%s</a>, Punkt 5.5.', h($numbers['listen'])) ?></p>

<?php help_section('fundnummer'); ?>

<?php help_section('messen'); ?>

<?php help_section('geraete'); ?>

<?php help_section('kontakte'); ?>

<?php help_section('freunde'); ?>
<p class="help-see"><?= t('Mit Freunden chatten tust du auf der Karte über die Sprechblase — <a href="#chat">%s</a>.', h($numbers['chat'])) ?></p>

<?php help_section('gruppen'); ?>

<?php help_section('offline'); ?>
<p>
    <?= t('Im Wald gibt es keinen Empfang, und die App muss trotzdem helfen. Punkte entstehen sofort und reisen später zum Server. <strong>Was auf dem Gerät liegt, entscheidest du</strong> — nichts lädt ungefragt.') ?>
</p>
<p class="help-see"><?= t('Was du herunterlädst, liegt nur in der App oder in dem Browser, in dem du es geladen hast — siehe <a href="#app-browser">%s</a>.', h(t('App oder Browser?'))) ?></p>
<ul>
    <li><?= t('<strong>Offline-Gebiete:</strong> eine Fläche auf der Karte umranden, eine Zoomstufe wählen, herunterladen. Die Größe steht vorher da.') ?></li>
    <li><?= t('<strong>Offline-Daten:</strong> Namenslisten und Schutzgebiete, jedes einzeln zu holen und einzeln wieder zu entfernen.') ?></li>
</ul>
<?php help_shot('offline.png', t('Der Reiter Offline mit einem Gebiet'), [
    1 => [12, 83], 2 => [71.5, 87], 3 => [84.5, 87], 4 => [29, 10],
]); ?>
<ol class="help-legend">
    <li><?= h(t('Deine Gebiete, mit dem, was davon auf diesem Gerät liegt.')) ?></li>
    <li><?= h(t('Zeigen/Editieren: die Fläche umranden und die Zoomstufe wählen — je feiner, desto mehr Kacheln.')) ?></li>
    <li><?= h(t('Herunterladen, auffrischen, vom Gerät entfernen.')) ?></li>
    <li><?= h(t('Offline-Daten: Namenslisten und Schutzgebiete, einzeln zu holen.')) ?></li>
</ol>


<?php help_section('gebiete'); ?>

<?php help_section('daten'); ?>

<?php help_section('gemeinsam'); ?>

<?php help_section('freigaben'); ?>
<p class="help-see"><?= t('In der Seitenleiste erscheinen Freigaben unter „Geteilt" — beschrieben unter <a href="#geteilt">%s</a>.', h($numbers['geteilt'])) ?></p>
<p>
    <?= t('Eine Freigabe sammelt Listen mehrerer Leute. Jeder hängt eigene Listen hinein und nimmt sie wieder heraus; <strong>Punkte gehören immer dem, der sie gesetzt hat</strong>. Was darin hängt, sehen alle Mitglieder auf ihrer Karte.') ?>
</p>
<p class="hint-text">
    <?= h(t('Aufnehmen kannst du Freunde und Arbeitsgruppen — beides steht in Verwaltung → Kontakte. Ein Filter an der Freigabe teilt nur die Punkte, die passen, und lässt sich nach dem Anlegen nicht mehr ändern.')) ?>
</p>
<p>
    <?= t('<strong>Gemeinsam bearbeiten:</strong> Wer eine Liste eingehängt hat, kann sie unter „Rechte der anderen" auf <em>gemeinsam</em> stellen — der Schalter steht dort ganz oben, mit einer Warnung, und fragt beim Einschalten noch einmal nach. Dann hat jedes Mitglied alle Rechte an ihren Punkten und setzt selbst Punkte hinein — die Liste steht beim Punkt setzen unter „Gemeinsame Listen" zur Wahl. Ein Punkt gehört weiter dem, der ihn gesetzt hat; der Filter der Freigabe gilt für eine gemeinsame Liste nicht, sie zeigt alles. <strong>Jede Änderung steht im Chat der Freigabe</strong> — wer was geändert, herausgenommen oder zurückgeholt hat —, und Löschen heißt hier: aus der Liste in den Papierkorb der Liste, wo jedes Mitglied den Punkt zurückholt (<a href="#papierkorb">%s</a>). Die Liste selbst gehört der Gruppe: Löscht ihr Besitzer sie oder verlässt er die Freigabe, geht sie an ein anderes Mitglied über — an das, das am längsten in der Freigabe ist, samt allen Punkten; erst das letzte Mitglied löscht sie wirklich. Wer die Freigabe verlässt, nimmt seine eigenen Punkte aus den gemeinsamen Listen mit, und wer eine Liste aushängt oder „gemeinsam" ausschaltet, gibt den anderen ihre Punkte zurück, in eine eigene Liste „… (ehemals gemeinsam)" — alles steht im Chat.', h($numbers['papierkorb'])) ?>
</p>
<p>
    <?= t('<strong>Wofür gemeinsame Listen gedacht sind:</strong> für kleine Arbeitslisten, die eine Gruppe zusammen pflegt — Gebiete mit Umrandung, Brandflächen, Stellen, die jeder bewerten und nachschärfen kann. Nicht für Funde. Wer mit anderen eine Wiese kartiert, hängt weiter seine eigene Fundliste ein und macht dafür eine Freigabe mit Gebietsfilter auf die Wiese: Jeder sieht vom anderen genau die Funde von dort, und jede Liste bleibt sauber die ihres Finders.') ?>
</p>


<?php help_section('direkt'); ?>

<?php help_section('exkursionen'); ?>
<p class="help-see"><?= t('Die Exkursionen siehst du in der Seitenleiste unter „Exkursionen" — beschrieben unter <a href="#exkursionen-leiste">%s</a>.', h($numbers['exkursionen-leiste'])) ?></p>
<p>
    <?= t('Eine Exkursion ist ein gemeinsamer Ausflug: du legst sie an, zeigst den QR-Code, und die Gäste kartieren mit — <strong>ohne eigenes Konto</strong>. Sie geben Vorname und Nachname an, eine E-Mail-Adresse ist freiwillig.') ?>
</p>
<p class="hint-text">
    <?= h(t('Die Funde der Gäste erscheinen unter Organisieren → Touren bei dir. Für die Karte im Gelände kannst du beim Anlegen ein Offline-Gebiet mitgeben; die Gäste laden es mit einem Tippen, und die Größe steht auf dem Knopf.')) ?>
</p>
<p>
    <?= t('Gibt ein Gast seine E-Mail-Adresse an, werden ihm seine Funde <strong>zugeschrieben, sobald er sich mit dieser Adresse anmeldet</strong> — auch Wochen später, das Konto darf neu sein. Er sieht seine Exkursionen dann unter Organisieren → Touren und darf die eigenen Funde weiter pflegen.') ?>
</p>
<p>
    <?= t('<strong>Löschen der Exkursion:</strong> Die Funde der Gäste werden dabei endgültig gelöscht, samt Fotos — ohne Exkursion blieben sie sonst herrenlos zurück: gespeichert, aber für niemanden mehr erreichbar. Funde von Gästen, die ihre E-Mail-Adresse angegeben und sich angemeldet haben, ziehen stattdessen in deren Konto um. Funde von Leitern und angemeldeten Teilnehmern bleiben, wo sie sind, und verlieren nur den Bezug zur Exkursion. <strong>Wer Nachzüglern ihre Funde erhalten will, löscht die Exkursion also erst, wenn alle ihr Konto haben.</strong>') ?>
</p>


<?php help_section('organisieren'); ?>
<p class="help-see"><?= t('Warum Arten in eine Grundliste gehören und Reviere eine Autoliste sind, steht ganz oben unter <a href="#mantra">„So ist die App gedacht"</a> und bei <a href="#setzen">%s</a>.', h($numbers['setzen'])) ?></p>
<p class="help-see"><?= t('Die Filter hier sind dieselben wie in der Seitenleiste — erklärt unter <a href="#filter">%s</a>.', h($numbers['filter'])) ?></p>
<p>
    <?= h(t('Verwaltung → Organisieren zeigt dieselben Punkte als Tabelle: mehrere auf einmal auswählen, verschieben, umhängen, löschen. Unter „Touren" stehen die Ausflüge, unter „Autolisten" Listen, die sich selbst füllen — sie merken sich einen Filter statt einer Auswahl.')) ?>
</p>
<?php help_shot('organisieren.png', t('Der Reiter Organisieren mit der Punkttabelle'), [
    1 => [10.5, 8.6], 2 => [31.5, 25.6], 3 => [45, 18.3], 4 => [78.5, 98],
]); ?>
<ol class="help-legend">
    <li><?= h(t('Die zwei Spalten links: Listen und Touren — die aktive regiert die Tabelle.')) ?></li>
    <li><?= h(t('Die Mehrfachauswahl — das Kästchen im Kopf wählt alles Sichtbare.')) ?></li>
    <li><?= h(t('Filter und Spalten der Tabelle.')) ?></li>
    <li><?= h(t('Export und Fundbericht für die Auswahl.')) ?></li>
</ol>


<?php help_section('cloud'); ?>
<p>
    <?= t('Wer unter <em>Mein Bereich → Cloud</em> einen eigenen Speicher verbunden hat, bekommt dort auch seine Funde abgelegt: im Ordner <code>Datensicherung</code> (bei Google Drive und S3 in einem Ordner <code>YASAIMAPP</code>, dessen Namen du dort ändern kannst), eine Tabelle je Monat („<code>Funde 2026-08.csv</code>"). Das läuft <strong>einmal in der Nacht</strong> und von selbst — geschrieben wird nur, was sich seit dem letzten Mal geändert hat, ein alter Monat wandert also nicht jede Nacht aufs Neue hinauf. Der Knopf <em>Jetzt sichern</em> nimmt einen Durchgang vorweg.') ?>
</p>
<p>
    <?= t('Die Dateien öffnen sich in jedem Tabellenprogramm und gehen über <em>Verwaltung → Import</em> auch wieder herein. Es sind <strong>deine eigenen Funde</strong> — was dir über Freigaben oder Exkursionen nur angezeigt wird, gehört den anderen und wird in deren Cloud gesichert.') ?>
</p>
<p>
    <?= t('<strong>Das MTB in der Vorschau:</strong> Manche Tabellenanzeiger — auch die Schnellansicht mancher Cloud — halten <code>5815/431</code> für eine Rechnung, teilen und zeigen davon einen gekürzten Bruch: <code>27/2</code>. Andere Blätter sehen dann zwei- oder dreistellig aus. In der Datei steht der richtige Wert; nur die Anzeige rechnet.') ?>
</p>
<p>
    <?= t('<strong>Ein Spiegel, kein Archiv:</strong> Was du in der Karte löschst, fehlt beim nächsten Durchgang auch in der Sicherung. Davor schützt der Papierkorb in der Karte, nicht dieser Ordner. Umgekehrt wird oben nie etwas gelöscht — auch beim Trennen der Verbindung bleibt alles liegen, was schon dort ist.') ?>
</p>


<?php help_section('import'); ?>
<p>
    <?= t('Verwaltung → Import holt Punkte aus anderen Anwendungen. Datei auf die Fläche ziehen — mehrere auf einmal gehen auch —, dann zeigt die Vorschau, was ankäme. <strong>Jeder Import wird eine eigene Liste</strong> und lässt sich als Ganzes wieder zurücknehmen.') ?>
</p>
<?php help_shot('import.png', t('Die Ablegefläche des Imports'), [
    1 => [41, 55], 2 => [46, 22],
]); ?>
<ol class="help-legend">
    <li><?= h(t('Dateien hierherziehen oder klicken.')) ?></li>
    <li><?= h(t('Welche Formate gehen und wie du an die Daten kommst.')) ?></li>
</ol>


<?php help_section('papierkorb'); ?>
<p><?= t('Gelöschte Punkte liegen hier, bis du sie zurückholst oder endgültig entfernst — einzeln oder alle auf einmal. Was aus einer Exkursion stammt, steht bei seiner Exkursion; was du als Mitbesitzer verlassen hast, unter „Ausgestiegen".') ?></p>
<p><?= t('<strong>Gemeinsame Listen</strong> haben ihren eigenen Papierkorb, je Liste: Wer dort einen Punkt löscht, nimmt nur die Ablage des Punktes aus dieser Liste — der Punkt selbst bleibt beim Finder, und liegt er noch in einer anderen Liste, bleibt er dort. Der Abschnitt zeigt, wer den Punkt wann herausgenommen hat, und <strong>jedes Mitglied</strong> holt ihn zurück. „Endgültig" gibt es hier nicht, und „Papierkorb leeren" greift nicht hinein: Solange eine Ablage im Papierkorb einer gemeinsamen Liste liegt, kann auch der Finder den Punkt nicht endgültig entfernen. Liegt ein Punkt in keiner Liste mehr, ist er als Ganzes im Papierkorb — Zurückholen aus dem Papierkorb der Liste belebt ihn wieder.') ?></p>

<?php
help_end();
?>
</div>
</div>
<?php
page_footer(['scripts' => ['assets/js/help.js']]);
