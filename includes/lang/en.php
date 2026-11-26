<?php
/**
 * English — the full catalog after the sweep of 28.08.2026.
 *
 * THE KEY IS THE GERMAN SENTENCE, exactly as it stands in the code
 * (includes/i18n.php explains the whole architecture). A sentence
 * missing here shows up in German — annoying, never broken. The
 * placeholders (%s, %d) must survive translation; %1$s-style indices
 * reorder where English needs to. English values use straight quotes;
 * American spelling (Color) is the house pick.
 *
 * ONE GERMAN SENTENCE, ONE TRANSLATION. Before adding a sentence, look
 * whether it is already here: PHP keeps the later of two equal keys
 * without a word, and the second entry rewrites every place that says
 * the sentence („%s von %s" once meant „by" for the trash and turned the
 * cloud move's „12 MB of 40 MB" into „by"). Where the German says two
 * things with the same words, the CODE says two sentences.
 * tests/lang.php refuses a key standing twice.
 */

declare(strict_types=1);

return [
    '%d Aufnahme(n) abgelegt.'
        => '%d recording(s) filed.',
    '%d Aufnahme(n) abgelegt — %d Gattungsordner umgeräumt.'
        => '%d recording(s) filed — %d genus folders rearranged.',
    '%d Aufnahmen'
        => '%d recordings',
    '%d außerhalb Deutschlands'
        => '%d outside Germany',
    '%d Dateien lagen schon eingespielt vor und wurden übersprungen: „%s".'
        => '%d files had already been imported and were skipped: "%s".',
    '%d Dateien liegen bereit — eine nach der anderen, jede wird eine eigene Liste.'
        => '%d files are ready — one after the other, each becomes its own list.',
    '%d davon betrafen Punkte, die inzwischen im Papierkorb liegen — sie sind dort mit dabei.'
        => '%d of them concerned points that now lie in the trash — the changes went there with them.',
    '%d Ebenen'
        => '%d layers',
    '%d Ecken — „Fertig", wenn es passt'
        => '%d corners — "Done" when it fits',
    '%d Eintrag ohne brauchbare Koordinaten übergangen.'
        => '%d entry without usable coordinates passed over.',
    '%d Einträge ohne brauchbare Koordinaten übergangen.'
        => '%d entries without usable coordinates passed over.',
    '%d fehlgeschlagen (kommen beim nächsten Lauf wieder dran)'
        => '%d failed (they get another turn on the next run)',
    '%d Felder'
        => '%d fields',
    '%d Fotos'
        => '%d photos',
    '%d Fotos werden aufbereitet …'
        => 'Preparing %d photos …',
    '%d Fotos übernommen.'
        => '%d photos taken over.',
    '%d Funde mit Schutzgebiet vermerkt'
        => '%d finds noted with a protected area',
    '%d Funde würden angelegt'
        => '%d finds would be created',
    '%d Gebiete: Karten abgelaufen und entfernt. Die Umrandung bleibt.'
        => '%d areas: maps expired and removed. The outlines stay.',
    '%d Gebiete anderswo gelöscht — Kacheln entfernt.'
        => '%d areas deleted elsewhere — tiles removed.',
    '%d Gemeinden eingetragen'
        => '%d municipalities recorded',
    '%d gewählt'
        => '%d selected',
    '%d Höhen eingetragen'
        => '%d elevations recorded',
    '%d in keinem Höhenmodell erfasst'
        => '%d covered by no elevation model',
    '%d liegen in keinem'
        => '%d lie in none',
    '%d Linien und Flächen bleiben draussen — das sind Wege und Umrisse, keine Funde.'
        => '%d lines and areas stay out — those are paths and outlines, not finds.',
    '%d Listen'
        => '%d lists',
    '%d lokal'
        => '%d local',
    '%d Migration(en) einspielen'
        => 'Apply %d migration(s)',
    '%d Migration(en) noch nicht eingespielt.'
        => '%d migration(s) not yet applied.',
    '%d Mitglieder'
        => '%d members',
    '%d Monate gesichert (%s).'
        => '%d months backed up (%s).',
    '%d Monate fehlen noch. Die kommen heute Nacht von selbst.'
        => '%d months are still missing. They follow by themselves tonight.',
    'Ein Monat fehlt noch. Der kommt heute Nacht von selbst.'
        => 'One month is still missing. It follows by itself tonight.',
    '%d Monat gesichert (%s).'
        => '%d month backed up (%s).',
    '%d m ü. NHN'
        => '%d m a.s.l.',
    '%d Name eingetragen'
        => '%d name recorded',
    '%d Namen eingetragen'
        => '%d names recorded',
    '%d Namen ohne Treffer — unten einzeln abwählbar.'
        => '%d names without a hit — deselectable individually below.',
    '%d Name ohne Treffer — unten einzeln abwählbar.'
        => '%d name without a hit — deselectable individually below.',
    '%d neu'
        => '%d new',
    '%d ohne Gemeinde (Meer, Niemandsland)'
        => '%d without a municipality (sea, no man\'s land)',
    '%d ohne Position'
        => '%d without position',
    '%d Ortsmarken ohne brauchbare Koordinaten übergangen.'
        => '%d placemarks without usable coordinates passed over.',
    '%d Ortsmarken würden angelegt'
        => '%d placemarks would be created',
    '%d Ortsmarke ohne brauchbare Koordinaten übergangen.'
        => '%d placemark without usable coordinates passed over.',
    '%d Punkt'
        => '%d point',
    '%d Punkt(e) mit veraltetem Kürzel wieder als offen markiert — sie werden beim nächsten Nachtragen neu gefragt.'
        => '%d point(s) with an outdated code marked as open again — they will be asked anew on the next backfill.',
    '%d Punkt(e) tragen ein Kürzel, das es in der Tabelle ihres Landes nicht gibt'
        => '%d point(s) carry a code that does not exist in their country\'s table',
    '%d Punkt durchgesehen'
        => '%d point examined',
    '%d Punkte'
        => '%d points',
    '%d Punkte, %d Listen'
        => '%d points, %d lists',
    '%d Punkte durchgesehen'
        => '%d points examined',
    '%d Punkte endgültig entfernt.'
        => '%d points removed for good.',
    '%d Punkte gehören jetzt zur Tour „%s".'
        => '%d points now belong to the tour "%s".',
    '%d Punkte liegen im Papierkorb.'
        => '%d points are in the trash.',
    '%d Punkte mit Blatt versehen'
        => '%d points given a sheet',
    '%d Punkte ohne Blatt nachtragen'
        => 'Backfill %d points without a sheet',
    '%d Punkte stehen jetzt unter „Ohne Tour".'
        => '%d points now stand under "No tour".',
    '%d Punkte tragen jetzt „%s".'
        => '%d points now carry "%s".',
    '%d Punkte wandern in den Papierkorb, die Listen des Imports verschwinden. Punkte, die es schon vorher gab, bleiben unberührt.'
        => '%d points move to the trash, the import\'s lists disappear. Points that already existed before stay untouched.',
    '%d Punkte würden angelegt'
        => '%d points would be created',
    '%d Punkte zurückgeholt.'
        => '%d points brought back.',
    '%d Punkte · %d Name'
        => '%d points · %d name',
    '%d Punkte · %d Namen'
        => '%d points · %d names',
    '%d Punkt mit Blatt versehen'
        => '%d point given a sheet',
    '%d schon vorhanden'
        => '%d already present',
    '%d Titel-Verweis angepasst'
        => '%d title reference adjusted',
    '%d Titel-Verweise angepasst'
        => '%d title references adjusted',
    '%d Tracks und Routen bleiben draussen — das sind Wege, keine Funde.'
        => '%d tracks and routes stay out — those are paths, not finds.',
    '%d von %d Listen sichtbar'
        => '%d of %d lists visible',
    '%d von %d Unterlisten sichtbar'
        => '%d of %d sublists visible',
    '%d von %d — weiter rollen für mehr'
        => '%d of %d — keep scrolling for more',
    '%d warten weiter'
        => '%d still waiting',
    '%d Wegpunkte ohne brauchbare Koordinaten übergangen.'
        => '%d waypoints without usable coordinates passed over.',
    '%d Wegpunkte würden angelegt'
        => '%d waypoints would be created',
    '%d Wegpunkt ohne brauchbare Koordinaten übergangen.'
        => '%d waypoint without usable coordinates passed over.',
    '%d Zeilen ohne Artnamen übergangen.'
        => '%d rows without a species name passed over.',
    '%d Zeile ohne Artnamen übergangen.'
        => '%d row without a species name passed over.',
    '%d Änderungen %snoch nicht beim Server.'
        => '%d changes %snot yet at the server.',
    '%d Änderungen hat der Server abgelehnt — siehe „Ansehen"'
        => 'the server rejected %d changes — see "View"',
    '%d Änderungen konnten nicht gespeichert werden'
        => '%d changes could not be saved',
    '%d Änderungen nachgetragen'
        => '%d changes handed in',
    '%d Änderungen wurden nicht gespeichert'
        => '%d changes were not saved',
    '%d Änderungen wurden von jüngeren überschrieben'
        => '%d changes were overwritten by younger ones',
    '%s oder %s'
        => '%s or %s',
    '%s%s — Ziel wird bestimmt …'
        => '%s%s — determining target …',
    '%s (gelöscht)'
        => '%s (deleted)',
    '%s: %s (%s/%s) …'
        => '%s: %s (%s/%s) …',
    '%s: %s, %s'
        => '%s: %s, %s',
    '%s: wird abgelegt …'
        => '%s: storing …',
    '%s Adressen gaben nichts her.'
        => '%s addresses yielded nothing.',
    '%s als Mitleiter entfernen'
        => 'Remove %s as co-leader',
    '%s auf %s'
        => '%s on %s',
    '%s auf diesem Gerät'
        => '%s on this device',
    '%s aufklappen'
        => 'Unfold %s',
    '%s aus %s entfernen'
        => 'Remove %s from %s',
    '%s aus %s herausnehmen'
        => 'Take %s out of %s',
    '%s aushängen'
        => 'Unhook %s',
    '%s bearbeiten'
        => 'Edit %s',
    '%s Bedingungen gesetzt — zum Ansehen aufklappen'
        => '%s conditions set — unfold to view',
    '%s Bedingung gesetzt — zum Ansehen aufklappen'
        => '%s condition set — unfold to view',
    '%s bis %s'
        => '%s until %s',
    '%s Dateien: '
        => '%s files: ',
    ' %s davon sind fremde Funde aus einer Freigabe — sie kommen mit, mit dem Namen ihrer Finder.'
        => ' %s of them are other people\'s finds from a share — they come along, with their finders\' names.',
    ' %s davon sind fremde Funde — sie stehen mit dem Namen ihrer Finder darin.'
        => ' %s of them are other people\'s finds — they appear with their finders\' names.',
    '%s Ecken umrandet.'
        => '%s corners outlined.',
    '%s einklappen'
        => 'Fold %s',
    '%s erledigt dasselbe unbeaufsichtigt, jede Stunde — wie man es einrichtet, steht im Kopf der Datei.'
        => '%s does the same unattended, every hour — how to set it up stands at the head of the file.',
    '%s Felder über %d Zoomstufen'
        => '%s fields across %d zoom levels',
    '%s Funde kommen in den Bericht.'
        => '%s finds go into the report.',
    '%s Gebiete'
        => '%s areas',
    '%s gegen %s für alles andere.'
        => '%s against %s for everything else.',
    '%s geleert.'
        => '%s emptied.',
    '%s hat Kacheln bis Stufe %d. Darüber wird nur vergrößert, nicht schärfer.'
        => '%s has tiles up to level %d. Beyond that the image is only enlarged, not sharper.',
    '%s ist dann kein Mitleiter mehr — bearbeiten und den QR-Code zeigen kann nur noch, wer Leiter ist. Aufnehmen geht jederzeit wieder.'
        => '%s will no longer be a co-leader — only a leader can edit and show the QR code. Adding them again is possible any time.',
    '%s ist gelöscht, samt Punkten, Listen und Dateien.'
        => '%s is deleted, along with points, lists and files.',
    '%s ist jetzt dein Freund.'
        => '%s is now your friend.',
    '%s ist jetzt gesperrt.'
        => '%s is now locked.',
    '%s ist jetzt wieder frei.'
        => '%s is now free again.',
    '%s ist kein JPEG.'
        => '%s is not a JPEG.',
    '%s ist leer.'
        => '%s is empty.',
    '%s ist zu gross (%s).'
        => '%s is too large (%s).',
    '%s ist zu gross: %d×%d Pixel, erlaubt sind %d.'
        => '%s is too large: %d×%d pixels, allowed is %d.',
    '%s Kacheln'
        => '%s tiles',
    '%s Kacheln, Größe unbekannt'
        => '%s tiles, size unknown',
    '%s Kacheln von Diensten entfernt, die kein Vorabladen erlauben.'
        => '%s tiles removed from services that allow no prefetching.',
    '%s leeren?'
        => 'Empty %s?',
    '%s m'
        => '%s m',
    '%s m ü. NHN'
        => '%s m a.s.l. (NHN)',
    '%s Punkte, %s Listen, alle Gebiete und alle Dateien dieses Kontos verschwinden mit. Das lässt sich nicht zurückholen — auch nicht über den Papierkorb.'
        => '%s points, %s lists, all areas and all files of this account disappear with it. This cannot be brought back — not even via the trash.',
    '%s Punkte ausgewählt'
        => '%s points selected',
    '%s Punkte gehören danach zu „%s", und „%s" gibt es nicht mehr.'
        => '%s points then belong to "%s", and "%s" no longer exists.',
    '%s Punkte gehören danach zu „%s". Eine Tour, die sie bisher trugen, geben sie damit auf.'
        => '%s points then belong to "%s". Any tour they carried before is given up with that.',
    '%s Punkte tragen danach keine Tour mehr und stehen unter „Ohne Tour".'
        => '%s points then carry no tour any more and sit under "Without a tour".',
    '%s Punkte wandern in den Papierkorb — samt Listenzuordnung und Fotos. Endgültig gelöscht wird dort erst auf deinen Befehl.'
        => '%s points move to the trash — along with their list assignments and photos. They are only deleted for good there on your command.',
    '%s Punkte werden unwiderruflich gelöscht. Das lässt sich nicht rückgängig machen.'
        => '%s points will be deleted irrevocably. This cannot be undone.',
    '%s Punkte ziehen nach „%s", dann verschwindet „%s". Was schon im Ziel hängt, wird nicht doppelt zugeordnet.'
        => '%s points move to "%s", then "%s" disappears. What already hangs in the target is not assigned twice.',
    '%s von %s'
        => '%s of %s',
    '%s wählen'
        => 'Choose %s',
    '%s zur Auswahl'
        => '%s to choose from',
    '%s — %s. Noch nicht gespeichert.'
        => '%s — %s. Not saved yet.',
    '%s — ansprechen'
        => '%s — mention',
    '%s — antworten'
        => '%s — reply',
    'Passt zu den anderen Bedingungen nicht mehr'
        => 'No longer fits the other conditions',
    '%s — zum Umstellen anklicken'
        => '%s — click to switch',
    '%s · %s Umfang · %d Ecken'
        => '%s · %s perimeter · %d corners',
    '%s öffnen und mit dem Google-Konto anmelden, in dem die Karten-Listen liegen.'
        => 'Open %s and sign in with the Google account that holds the map lists.',
    ' (%s von anderen)'
        => ' (%s by others)',
    '(+%d zugeordnet)'
        => '(+%d assigned)',
    ' (auch aus %s)'
        => ' (also from %s)',
    ' (Gruppe)'
        => ' (group)',
    '(keine Fläche)'
        => '(no area)',
    '(leer)'
        => '(empty)',
    '(Liste gelöscht)'
        => '(list deleted)',
    '(lokal)'
        => '(local)',
    '(nicht mehr vorhanden)'
        => '(no longer there)',
    '1 Aufnahme'
        => '1 recording',
    '1 Ebene'
        => '1 layer',
    '1 Ecke — mindestens drei'
        => '1 corner — at least three',
    '1 Feld'
        => '1 field',
    '1 Foto'
        => '1 photo',
    '1 Foto übernommen.'
        => '1 photo taken over.',
    '1 Gebiet: Karten abgelaufen und entfernt. Die Umrandung bleibt.'
        => '1 area: maps expired and removed. The outline stays.',
    '1 Gebiet anderswo gelöscht — Kacheln entfernt.'
        => '1 area deleted elsewhere — tiles removed.',
    '1 Liste'
        => '1 list',
    '1 Mitglied'
        => '1 member',
    '1 Punkt'
        => '1 point',
    '1 Punkt ausgewählt'
        => '1 point selected',
    '1 wartet weiter'
        => '1 still waiting',
    '1 Änderung %snoch nicht beim Server.'
        => '1 change %snot yet at the server.',
    '1 Änderung hat der Server abgelehnt — siehe „Ansehen"'
        => 'the server rejected 1 change — see "View"',
    '1 Änderung konnte nicht gespeichert werden'
        => '1 change could not be saved',
    '1 Änderung nachgetragen'
        => '1 change handed in',
    '1 Änderung wurde von einer jüngeren überschrieben'
        => '1 change was overwritten by a younger one',
    '1. Datenbank'
        => '1. Database',
    '2 Ecken — noch eine'
        => '2 corners — one more',
    '2. Erstes Konto'
        => '2. First account',
    '15 — Überblick'
        => '15 — overview',
    '18 — empfohlen'
        => '18 — recommended',
    '19 — sehr fein'
        => '19 — very fine',
    '<strong>Am Fundort:</strong> der Standortknopf setzt den Punkt dorthin, wo das Gerät dich sieht — mit der Genauigkeit daneben, damit du weißt, wie ernst du sie nehmen darfst.'
        => '<strong>At the find spot:</strong> the location button places the point where the device sees you — with the accuracy next to it, so you know how seriously you may take it.',
    '<strong>Am Garmin-Gerät:</strong> per USB anschliessen — es meldet sich als Laufwerk. Im Ordner <code>Garmin</code> liegt <code>GPX/</code>, darin die Dateien. Die gewünschte kopieren und hier hochladen.'
        => '<strong>On the Garmin device:</strong> connect via USB — it shows up as a drive. The folder <code>Garmin</code> holds <code>GPX/</code>, and in it the files. Copy the one you want and upload it here.',
    '<strong>Arten:</strong> welche Gruppen das Titelfeld vorschlägt.'
        => '<strong>Species:</strong> which groups the title field suggests.',
    '<strong>Auf der Karte:</strong> „Punkt setzen" antippen, dann in die Karte tippen. Erneutes Antippen bricht ab.'
        => '<strong>On the map:</strong> tap "Set point", then tap into the map. Tapping again cancels.',
    'Das geht noch nicht. Zuerst muss der Verwalter dieser Seite YASAIMAPP bei %s anmelden. Dadurch sieht niemand etwas von deiner Cloud. Danach verbindest du hier dein eigenes %s.'
        => 'This does not work yet. First the administrator of this site has to register YASAIMAPP with %s. Nobody sees anything of your cloud through that. Then you connect your own %s here.',
    '<strong>Das MTB in der Vorschau:</strong> Manche Tabellenanzeiger — auch die Schnellansicht mancher Cloud — halten <code>5815/431</code> für eine Rechnung, teilen und zeigen davon einen gekürzten Bruch: <code>27/2</code>. Andere Blätter sehen dann zwei- oder dreistellig aus. In der Datei steht der richtige Wert; nur die Anzeige rechnet.'
        => '<strong>The MTB in the preview:</strong> some table viewers — including the quick view of some clouds — take <code>5815/431</code> for a calculation, divide, and show a reduced fraction of it: <code>27/2</code>. Other sheets then look two- or three-digit. The file holds the correct value; only the display calculates.',
    '<strong>Ein Spiegel, kein Archiv:</strong> Was du in der Karte löschst, fehlt beim nächsten Durchgang auch in der Sicherung. Davor schützt der Papierkorb in der Karte, nicht dieser Ordner. Umgekehrt wird oben nie etwas gelöscht — auch beim Trennen der Verbindung bleibt alles liegen, was schon dort ist.'
        => '<strong>A mirror, not an archive:</strong> what you delete in the map is also missing from the backup on the next pass. The trash in the map protects against that, not this folder. The other way round, nothing up there is ever deleted — even when disconnecting, everything that is already there stays.',
    '<strong>Es wird nicht neu gemessen</strong> — alle %s Meldungen tragen denselben Zeitstempel. Der Browser gibt eine einmal ermittelte Position immer wieder heraus, statt den Empfänger zu fragen. Warten hilft dagegen nicht.'
        => '<strong>No new measurements are taken</strong> — all %s reports carry the same timestamp. The browser keeps handing out a position it determined once instead of asking the receiver. Waiting will not help.',
    '<strong>Fundnummer:</strong> das Muster für deine laufende Nummer.'
        => '<strong>Find number:</strong> the pattern for your running number.',
    '<strong>geojson.io:</strong> oben „Save" → „GeoJSON". <strong>uMap:</strong> in den Karteneinstellungen der Bereich zum Teilen und Herunterladen bietet die Daten als GeoJSON an — auch je Ebene.'
        => '<strong>geojson.io:</strong> at the top "Save" → "GeoJSON". <strong>uMap:</strong> in the map settings, the sharing and download section offers the data as GeoJSON — per layer too.',
    '<strong>Geräte:</strong> ein Gerät koppeln, das ohne Passwort auf deine Funde darf — für die Aufzeichnungs-App.'
        => '<strong>Devices:</strong> pair a device that may access your finds without a password — for the recorder app.',
    '<strong>Google Earth im Browser:</strong> links unter „Projekte" das Projekt öffnen, im Menü „…" den Punkt zum Ausgeben als KML wählen.'
        => '<strong>Google Earth in the browser:</strong> open the project on the left under "Projects", in the "…" menu choose the entry for exporting as KML.',
    '<strong>Google Earth Pro (Rechner):</strong> links unter „Orte" mit der rechten Maustaste auf den Ordner, „Ort speichern unter&nbsp;…", als .kmz oder .kml sichern.'
        => '<strong>Google Earth Pro (computer):</strong> on the left under "Places", right-click the folder, "Save Place As&nbsp;…", save as .kmz or .kml.',
    '<strong>Google My Maps:</strong> die Karte öffnen, im Menü neben dem Kartennamen (drei Punkte) „In KML/KMZ exportieren", dort „Gesamte Karte" wählen und herunterladen. Ob .kml oder .kmz — beides geht hier.'
        => '<strong>Google My Maps:</strong> open the map, in the menu next to the map name (three dots) choose "Export to KML/KMZ", there select "Entire map" and download. Whether .kml or .kmz — both work here.',
    '<strong>In der App:</strong> der Weg heisst fast überall „Exportieren" oder „Teilen" — bei den Wegpunkten selbst oder beim Ordner, in dem sie liegen. Wo nach dem Format gefragt wird: GPX. Bei OsmAnd liegt er unter „Meine Orte", bei Locus unter „Punkte", bei OruxMaps unter den Wegpunkten.'
        => '<strong>In the app:</strong> the way is called "Export" or "Share" almost everywhere — on the waypoints themselves or on the folder they lie in. Where a format is asked for: GPX. In OsmAnd it sits under "My Places", in Locus under "Points", in OruxMaps under the waypoints.',
    '<strong>Jeden Tag heißt sie wieder „Tagestour"</strong>, und das ist kein Versehen: Zwei Touren mit demselben Namen bleiben zwei — sie werden nicht am Namen auseinandergehalten, sondern an einer eigenen Kennung. Nur wenn du an <em>einem</em> Tag eine zweite Tour beginnst, zählt der Vorschlag hoch: „Tagestour #2".'
        => '<strong>Every day it is called "Tagestour" again</strong>, and that is no accident: two tours with the same name remain two — they are told apart not by their name but by an identifier of their own. Only when you start a second tour on <em>one</em> day does the suggestion count up: "Day tour #2".',
    '<strong>Keine Satellitenortung</strong> — keine der %s Meldungen trägt eine Höhe. Das spricht für eine Ortung ausschließlich über WLAN und Funkzelle.'
        => '<strong>No satellite positioning</strong> — none of the %s reports carries an elevation. That points to positioning via Wi-Fi and cell tower only.',
    '<strong>Konto:</strong> Name, E-Mail-Adresse, Passwort.'
        => '<strong>Account:</strong> name, e-mail address, password.',
    '<strong>Laufende Satellitenmessung</strong> — %s von %s Meldungen tragen eine Höhe, und die Position bewegt sich. Bleibt die Genauigkeit trotzdem schlecht, liegt es am Empfang vor Ort, nicht am Browser.'
        => '<strong>Ongoing satellite measurement</strong> — %s of %s reports carry an elevation, and the position moves. If accuracy still stays poor, it is down to local reception, not the browser.',
    '<strong>Löschen der Exkursion:</strong> Die Funde der Gäste werden dabei endgültig gelöscht, samt Fotos — ohne Exkursion blieben sie sonst herrenlos zurück: gespeichert, aber für niemanden mehr erreichbar. Funde von Gästen, die ihre E-Mail-Adresse angegeben und sich angemeldet haben, ziehen stattdessen in deren Konto um. Funde von Leitern und angemeldeten Teilnehmern bleiben, wo sie sind, und verlieren nur den Bezug zur Exkursion. <strong>Wer Nachzüglern ihre Funde erhalten will, löscht die Exkursion also erst, wenn alle ihr Konto haben.</strong>'
        => '<strong>Deleting the excursion:</strong> the finds of the guests are permanently deleted with it, photos included — without the excursion they would otherwise be left behind ownerless: stored, but reachable for nobody anymore. Finds of guests who gave their e-mail address and signed in move into their account instead. Finds of leaders and signed-in participants stay where they are and only lose the link to the excursion. <strong>So whoever wants latecomers to keep their finds deletes the excursion only once everyone has their account.</strong>',
    '<strong>Oberfläche:</strong> Hintergrund, Markierungsfarbe und Sprache.'
        => '<strong>Oberfläche:</strong> background, marking colour and language.',
    '<strong>Offline-Daten:</strong> Namenslisten und Schutzgebiete, jedes einzeln zu holen und einzeln wieder zu entfernen.'
        => '<strong>Offline data:</strong> name lists and protected areas, each fetched individually and removed individually again.',
    '<strong>Offline-Gebiete:</strong> eine Fläche auf der Karte umranden, eine Zoomstufe wählen, herunterladen. Die Größe steht vorher da.'
        => '<strong>Offline areas:</strong> outline an area on the map, choose a zoom level, download. The size is shown beforehand.',
    '<strong>Ohne Breiten- und Längenspalte geht es nicht weiter</strong> — bitte oben zuordnen und anwenden. Steht die Lage in EINER Spalte („50.1, 8.2"), einfach dieselbe Spalte als Breiten- UND Längengrad wählen.'
        => '<strong>Without a latitude and a longitude column it cannot go on</strong> — please assign above and apply. If the position sits in ONE column ("50.1, 8.2"), simply choose the same column as latitude AND longitude.',
    '<strong>Ort:</strong> gezeichnete Gebiete, Bundesländer, Gemeinden und Messtischblätter. Jede Auswahl verengt, was die anderen anbieten.'
        => '<strong>Place:</strong> drawn areas, federal states, municipalities and map sheets. Each selection narrows what the others offer.',
    '<strong>Position steht fest</strong> — %s Meldungen, aber nur eine einzige Position, auf den Zentimeter gleich. Eine laufende Satellitenmessung zappelt immer ein wenig; das hier ist ein gespeicherter Wert.'
        => '<strong>Position is fixed</strong> — %s reports, but only one single position, identical to the centimetre. An ongoing satellite measurement always jitters a little; this is a stored value.',
    '<strong>QGIS:</strong> im Ebenenbereich mit der rechten Maustaste auf die Ebene, „Exportieren" → „Objekte speichern als&nbsp;…", als Format GeoJSON wählen.'
        => '<strong>QGIS:</strong> in the layers panel, right-click the layer, "Export" → "Save Features As&nbsp;…", choose GeoJSON as the format.',
    '<strong>Schnell:</strong> Rechtsklick am Rechner, langes Tippen am Handy — beides setzt sofort.'
        => '<strong>Quick:</strong> right-click on the computer, a long press on the phone — both place immediately.',
    '<strong>Sonst:</strong> fast jeder Umwandler im Netz gibt GeoJSON aus; wenn deine Anwendung nichts davon kennt, ist eine Tabelle der kürzere Weg (siehe unten).'
        => '<strong>Otherwise:</strong> almost every converter on the web outputs GeoJSON; if your application knows none of this, a table is the shorter way (see below).',
    '<strong>Text:</strong> Zeile für Zeile, jede entweder <em>enthält</em> oder <em>enthält nicht</em>. Alle müssen zutreffen. Kommas innerhalb einer Zeile gelten als <em>oder</em>.'
        => '<strong>Text:</strong> line by line, each one either <em>contains</em> or <em>does not contain</em>. All must apply. Commas within a line count as <em>or</em>.',
    '<strong>Zeitraum:</strong> von, bis, oder ein einzelner Tag.'
        => '<strong>Period:</strong> from, until, or a single day.',
    'Aargau'
        => 'Aargau',
    'ab %s'
        => 'from %s',
    'ab %s m'
        => 'from %s m',
    'Abbrechen'
        => 'Cancel',
    'Abgebrochen.'
        => 'Cancelled.',
    'Abgebrochen — die %s bereits geholten Kacheln wurden wieder entfernt.'
        => 'Cancelled — the %s tiles already fetched were removed again.',
    'Abgebrochen — es war noch nichts geholt.'
        => 'Cancelled — nothing had been fetched yet.',
    'Abgebrochen — das Foto wird nicht übernommen.'
        => 'Cancelled — the photo is not taken over.',
    'Abgebrochen — die Fotos werden nicht übernommen.'
        => 'Cancelled — the photos are not taken over.',
    'Abgelaufen'
        => 'Expired',
    'Ablehnen'
        => 'Decline',
    'Abmelden'
        => 'Sign out',
    'Abruzzen'
        => 'Abruzzo',
    'Abweichungen vom Original'
        => 'Deviations from the original',
    'Abzug herunterladen'
        => 'Download dump',
    ' Achtung: „%s" gehört zu einer Artenliste, das Ziel nicht — die Funde verlieren dort Fundfelder-Anzeige und Artenauswertung.'
        => ' Beware: "%s" belongs to a species list, the target does not — the finds lose their find-field display and species evaluation there.',
    'Admin'
        => 'Admin',
    'Administration'
        => 'Administration',
    'Admin — dazu die Serververwaltung'
        => 'Admin — plus the server administration',
    'Adresse'
        => 'Address',
    'Aktionen wirken auf alle %s gewählten.'
        => 'Actions act on all %s chosen.',
    'Aktionen wirken auf den gewählten Punkt.'
        => 'Actions act on the chosen point.',
    'aktueller Name zu „%s“'
        => 'current name for "%s"',
    'Dein jetziges Passwort'
        => 'Your current password',
    'Markierungsfarbe' => 'Marking colour',
    'alle'
        => 'all',
    'Alle %s Punkte der Tabelle kommen in die Datei.'
        => 'All %s points of the table go into the file.',
    'Alle, die nur über diese Gruppe Mitglied waren, verlieren die Freigabe — und ihre eingehängten Listen werden ausgehängt.'
        => 'Everyone who was a member only through this group loses the share — and their hung-in lists are unhooked.',
    'Alle auswählen'
        => 'Select all',
    'Alle Blätter neu berechnen?'
        => 'Recompute all sheets?',
    'Alle deine Freunde sind schon in dieser Gruppe.'
        => 'All your friends are already in this group.',
    'Alle deine Freunde und Gruppen sind schon in dieser Freigabe.'
        => 'All your friends and groups are already in this share.',
    'Alle deine Listen hängen schon in dieser Freigabe.'
        => 'All your lists are already mounted in this share.',
    'Alle Exkursionen'
        => 'All excursions',
    'Alle Filter löschen'
        => 'Clear all filters',
    'Alle Funde sind da.'
        => 'All finds are here.',
    'Bitte fülle alle fünf Felder aus.'
        => 'Please fill in all five fields.',
    'Alle Höhen'
        => 'All elevations',
    'Alle Messungen der Ebene „%s" dieses Bildes werden entfernt — auch aus der Statistik. Das lässt sich nicht rückgängig machen. Einzelne wählt ein Tipp im Bild aus — das × daneben löscht sie.'
        => 'All measurements of the layer "%s" of this image will be removed — from the statistics too. This cannot be undone. A tap in the image selects a single one — the × next to it deletes it.',
    'Alle Migrationen sind eingespielt.'
        => 'All migrations are applied.',
    'Alle neu berechnen'
        => 'Recompute all',
    'Alle Pilznamen der Welt samt Synonymen — für Funde, die in keiner Deutschlandliste stehen, und für alte Namen, unter denen man eine Art noch kennt. Ohne deutsche Namen und ohne Gefährdungseinstufung; die stehen in den Deutschlandlisten.'
        => 'All fungus names of the world including synonyms — for finds that stand in no Germany list, and for old names under which one still knows a species. Without German names and without threat status; those stand in the Germany lists.',
    'Alle Punkte'
        => 'All points',
    'Alles'
        => 'Everything',
    'alles andere'
        => 'everything else',
    'Alles andere: eine Tabelle mit Kopfzeile (.csv)'
        => 'Everything else: a table with a header row (.csv)',
    'Alles davon war schon eingespielt — nichts Neues dabei.'
        => 'All of it had already been imported — nothing new there.',
    'Alles gesichert. Seit dem letzten Mal hat sich nichts geändert.'
        => 'Everything is backed up. Nothing has changed since last time.',
    'Alle sichtbaren Punkte wählen'
        => 'Select all visible points',
    'Alle verwerfen'
        => 'Discard all',
    'Als Artenliste ist diese Liste gegen Löschen geschützt.'
        => 'As a species list, this list is protected against deletion.',
    'Als Ausschnitt nutzen — bis zu zwei Maße hineinzeichnen:'
        => 'Use it as a cutout — draw up to two measures inside:',
    'Als Mitleiter ausgetreten.'
        => 'Stepped out as co-leader.',
    'Als Mitleiter austreten'
        => 'Step out as co-leader',
    'Als Mitleiter austreten?'
        => 'Step out as co-leader?',
    'Als Mitleiter von %s austreten'
        => 'Step out as co-leader of %s',
    'Als was?'
        => 'As what?',
    'Am eigenen Konto geht weder Sperren noch Löschen noch Herabstufen: Wer der letzte Admin ist und sich die Rolle nimmt, sperrt die Serververwaltung für alle aus — und kann sie selbst nicht wieder aufschliessen. Dasselbe gilt für das Gründerkonto, also das erste dieser Anlage: Es ist der Anker, an dem man sich zurückhangeln kann, wenn sonst nichts mehr geht.'
        => 'Your own account can be neither locked nor deleted nor demoted: whoever is the last admin and takes the role from themselves locks everyone out of the server administration — and cannot unlock it again themselves. The same goes for the founder account, the first of this installation: it is the anchor to climb back along when nothing else works.',
    'Anbieter'
        => 'Providers',
    'Anfang und Ende der Struktur im Ausschnitt antippen.'
        => 'Tap the start and the end of the structure in the cutout.',
    'Anfrage an %s geschickt.'
        => 'Request sent to %s.',
    'Anfrage an %s zurückziehen'
        => 'Withdraw the request to %s',
    'Anfragen'
        => 'Requests',
    'Anfrage von %s abgelehnt.'
        => 'Request from %s declined.',
    'Anfrage von %s ablehnen'
        => 'Decline the request from %s',
    'Anfrage von %s annehmen'
        => 'Accept the request from %s',
    'An fremden Punkten lässt sich „%s“ nicht ändern.'
        => '"%s" cannot be changed on somebody else\'s points.',
    'Angemeldet bleiben'
        => 'Stay signed in',
    'Angenommen. Ihr seid jetzt Freunde.'
        => 'Accepted. You are friends now.',
    'Abgelehnt.'
        => 'Declined.',
    'Zurückgezogen.'
        => 'Withdrawn.',
    'Die Freundschaft mit %s ist beendet.'
        => 'The friendship with %s has ended.',
    'Angezeigt ist die gemessene Größe für dieses Gebiet, nicht für die ganze Ebene. Vorgewählt ist, was gerade auf der Karte liegt. Schutzgebiete holst du nicht hier, sondern unter Verwaltung → Offline → Offline-Daten, dort gleich für ein ganzes Bundesland.'
        => 'Shown is the measured size for this area, not for the whole layer. Preselected is what currently lies on the map. Protected areas are not fetched here but under Administration → Offline → Offline data, there right away for a whole federal state.',
    'Anlegen'
        => 'Create',
    'Anlegen fehlgeschlagen: %s'
        => 'Creating failed: %s',
    'Anmelden'
        => 'Sign in',
    'Annehmen'
        => 'Accept',
    'Ansehen'
        => 'View',
    'Anzahl / Menge'
        => 'Number / quantity',
    'Aostatal'
        => 'Aosta Valley',
    'Appenzell Ausserrhoden'
        => 'Appenzell Ausserrhoden',
    'Appenzell Innerrhoden'
        => 'Appenzell Innerrhoden',
    'Apulien'
        => 'Apulia',
    'Arbeitsgruppe'
        => 'Working group',
    'Arbeitsgruppen'
        => 'Work groups',
    'Arbeitsgruppen brauchen die Migration 0041 — sie ist noch nicht eingespielt.'
        => 'Working groups need migration 0041 — it has not been applied yet.',
    'Art'
        => 'Species',
    'Arten'
        => 'Species',
    'Arten aus allen Punkten neu ableiten'
        => 'Re-derive species from all points',
    'Artenliste — der Titel bekommt Vorschläge aus der Namensdatenbank'
        => 'Species list — the title gets suggestions from the name database',
    'Artnamen stehen am Punkt als Text — im Titel und in den Feldern, die als Artenfeld gekennzeichnet sind. Diese Tabelle stellt sie zusätzlich einzeln ab, mit Gattung und Verweis. Erst dadurch werden Fragen wie „alle Boletus" oder „wie viele Arten kenne ich" beantwortbar. Sie ist vollständig abgeleitet und lässt sich jederzeit neu bauen.'
        => 'Species names stand on the point as text — in the title and in the fields marked as species fields. This table additionally stores them one by one, with genus and reference. Only that makes questions like "all Boletus" or "how many species do I know" answerable. It is fully derived and can be rebuilt at any time.',
    'Auf der Karte'
        => 'On the map',
    'Auf der Karte umranden'
        => 'Outline on the map',
    'Auf der Karte zeigen — dort lässt sich die Umrandung auch ändern'
        => 'Show on the map — the outline can also be changed there',
    'noch nicht freigeschaltet'
        => 'not enabled yet',
    'Aufgenommen %s'
        => 'Taken %s',
    'Aufklappen'
        => 'Expand',
    'Aufnahme gelöscht.'
        => 'Recording deleted.',
    'Aufnahme löschen'
        => 'Delete recording',
    'Aufnahme löschen?'
        => 'Delete recording?',
    'Aufnahme nicht gefunden.'
        => 'Recording not found.',
    'Aufnahme und Maße werden gebraucht.'
        => 'Recording and dimensions are needed.',
    'Aufnahme vom %s'
        => 'Photo taken on %s',
    'Aufnehmen'
        => 'Capture',
    'Aufnehmen kannst du Freunde und Arbeitsgruppen — beides steht in Verwaltung → Kontakte. Ein Filter an der Freigabe teilt nur die Punkte, die passen, und lässt sich nach dem Anlegen nicht mehr ändern.'
        => 'You can add friends and work groups — both stand in Administration → Contacts. A filter on the share passes on only the points that match, and cannot be changed anymore after creating it.',
    'aus %s'
        => 'from %s',
    'aus allen deinen Punkten'
        => 'from all your points',
    'Aus der eigenen Freigabe tritt man nicht aus — man löscht sie.'
        => 'You do not leave your own share — you delete it.',
    'Ausfuhrdatei: <strong>.zip, .kml, .kmz, .gpx, .geojson oder .csv</strong> — aus Google Maps, Google Earth und My Maps, GPS-Geräten, Karten- und Fund-Apps, Web-Karten und jeder eigenen Tabelle. Woher die Datei stammt, erkennt der Import selbst. Jeder Import bekommt eine eigene Liste „Import %s" und lässt sich unten als Ganzes wieder zurücknehmen.'
        => 'Export file: <strong>.zip, .kml, .kmz, .gpx, .geojson or .csv</strong> — from Google Maps, Google Earth and My Maps, GPS devices, map and find apps, web maps and any table of your own. Where the file comes from, the import recognizes by itself. Every import gets its own list "Import %s" and can be taken back below as a whole.',
    'ausgestorben oder verschollen'
        => 'extinct or lost',
    'Ausgewählte endgültig entfernen'
        => 'Remove selected for good',
    'Ausgewählte zurückholen'
        => 'Bring back selected',
    'Aushängen'
        => 'Unhook',
    'Austreten'
        => 'Leave',
    'Austreten braucht die Migration 0067 — sie ist noch nicht eingespielt.'
        => 'Leaving needs migration 0067 — it has not been applied yet.',
    'Auswahl'
        => 'choice',
    'Auswahlwerte, durch Komma getrennt'
        => 'Choice values, separated by commas',
    'Auswählen'
        => 'Select',
    '„%s" geht, sobald der Punkt übertragen ist.'
        => '“%s” works once the point has been transferred.',
    '„%s" braucht eine verbundene Cloud — Mein Bereich → Cloud.'
        => '“%s” needs a connected cloud — My area → Cloud.',
    '„%s" geht mit deiner Cloud noch nicht. Fotos über „Aufnehmen" landen schon dort.'
        => '“%s” does not work with your cloud yet. Photos taken with “Capture” already go there.',
    'aus „%s"'
        => 'from "%s"',
    'Autoliste&nbsp;…'
        => 'Auto&nbsp;list&nbsp;…',
    'Autoliste anlegen'
        => 'Create auto list',
    'Autoliste „%s" angelegt — ihr Filter trifft gerade %d Punkte. Sie führt sich ab jetzt von selbst nach.'
        => 'Auto list "%s" created — its filter currently matches %d points. From now on it keeps itself up to date.',
    'Autoliste „%s" angelegt — ihr Filter trifft gerade einen Punkt. Sie führt sich ab jetzt von selbst nach.'
        => 'Auto list "%s" created — its filter currently matches one point. From now on it keeps itself up to date.',
    'Autoliste „%s" angelegt — ihr Filter trifft gerade keinen Punkt. Sie führt sich ab jetzt von selbst nach.'
        => 'Auto list "%s" created — its filter currently matches no point. From now on it keeps itself up to date.',
    'Automatisch (wie dein Gerät)'
        => 'Automatic (like your device)',
    'Baden-Württemberg'
        => 'Baden-Württemberg',
    'Bandwürmer'
        => 'Tapeworms',
    'Basel-Landschaft'
        => 'Basel-Landschaft',
    'Basel-Stadt'
        => 'Basel-Stadt',
    'Basilikata'
        => 'Basilicata',
    'Bauchhärlinge'
        => 'Gastrotrichs',
    'Baum'
        => 'Tree',
    'Baustein'
        => 'Building block',
    'Bayern'
        => 'Bavaria',
    'Bearbeiten'
        => 'Edit',
    'Bedeutung'
        => 'Meaning',
    'Bedingung: %s'
        => 'Condition: %s',
    'beendet'
        => 'ended',
    'bearbeitet'
        => 'worked up',
    'Beginnt mit diesem Punkt.'
        => 'Begins with this point.',
    'Begleitvegetation'
        => 'Accompanying vegetation',
    'Begriff im Titel'
        => 'Term in the title',
    'Bei %s Punkten: %s. Alles andere bleibt, wie es ist.'
        => 'For %s points: %s. Everything else stays as it is.',
    'bei allen %s gewählten'
        => 'for all %s chosen',
    'Beides geht auch nacheinander — und später jederzeit über das Ebenen-Symbol auf der Karte.'
        => 'Both also work one after the other — and any time later via the layers icon on the map.',
    '%d Hm'
        => '%d m elev.',
    'unter %d mm'
        => 'under %d mm',
    'über %d mm'
        => 'over %d mm',
    '%d–%d mm'
        => '%d–%d mm',
    'Regen vom %s bis %s'
        => 'Rain from %s to %s.',
    'Höhenlinien: eine Linie alle %d Höhenmeter, eine dicke Linie alle %d Höhenmeter.'
        => 'Contour lines: one line every %d metres of height, a thick line every %d metres.',
    'Das geht auch später jederzeit über das Ebenen-Symbol auf der Karte.'
        => 'That also works any time later via the layers icon on the map.',
    'Beides wird gebraucht: Client-ID und Client-Secret aus der %s-Registrierung.'
        => 'Both are needed: client ID and client secret from the %s registration.',
    'Bei diesen Zeilen fehlen brauchbare Koordinaten — sie werden nicht eingespielt, denn ein falsch gesetzter Punkt ist schlimmer als ein fehlender.'
        => 'These rows lack usable coordinates — they are not imported, because a wrongly placed point is worse than a missing one.',
    'Bei einem Fehler bricht der Vorgang ab, die betroffene Datei bleibt offen — MySQL kann Schemaänderungen nicht zurückrollen, deshalb muss jede Migration vorher lokal ausprobiert worden sein.'
        => 'On an error the process stops and the affected file stays open — MySQL cannot roll back schema changes, so every migration must have been tried out locally first.',
    'Bei einem Punkt: %s. Alles andere bleibt, wie es ist.'
        => 'For one point: %s. Everything else stays as it is.',
    'Bei Google öffnen'
        => 'Open at Google',
    'So geht es: Lege bei deinem Anbieter einen Eimer (Bucket) an. Erzeuge dann einen Zugriffsschlüssel. Lass ihn nur für diesen Eimer gelten, wenn dein Anbieter das kann. <strong>Wichtig:</strong> Der Schlüssel darf alles, was du ihm beim Erzeugen erlaubst. Die fünf Angaben findest du in der Übersicht deines Anbieters.'
        => 'Here is how: create a bucket at your provider. Then generate an access key. Let it apply to this bucket only, if your provider can do that. <strong>Important:</strong> the key may do everything you allow it when you create it. You find the five values in your provider\'s overview.',
    'beim gewählten Punkt'
        => 'for the chosen point',
    'Du bekommst nur Arten aus den Gruppen vorgeschlagen, die hier einen Haken haben. Das gilt mit und ohne Internet.'
        => 'You only get species suggested from the groups that have a tick here. That holds with and without internet.',
    'Nimmst du bei einer Gruppe den Haken weg, bekommst du ihre Arten nicht mehr vorgeschlagen. Ihre Namen werden dann auch von diesem Gerät entfernt. Das gilt, sobald du zur Karte zurückgehst. Kommen trotzdem noch Vorschläge aus dieser Gruppe? Dann lade die Seite einmal neu.'
        => 'If you take the tick away from a group, you no longer get its species suggested. Its names are then removed from this device too. That applies as soon as you go back to the map. Do suggestions from this group still come? Then reload the page once.',
    'Vorschläge gibt es nur in Listen, die als Artenliste angelegt sind. Deine Listen Pilze, Pflanzen und Flechten sind das schon von Anfang an. Wie du eine neue Liste zur Artenliste machst, steht in der Hilfe unter %s.'
        => 'Suggestions only come in lists set up as species lists. Your lists Pilze, Pflanzen and Flechten are that from the start. How to make a new list a species list is in the help under %s.',
    'Quelle: %s' => 'Source: %s',
    'Darin: %s' => 'Contains: %s',
    'Beim Tippen Namen aus der Artendatenbank vorschlagen'
        => 'Suggest names from the species database while typing',
    'Beleg-Nr.'
        => 'Voucher no.',
    'Belegbilder löschen'
        => 'Delete voucher photos',
    'Belegfoto'
        => 'Voucher photo',
    'Hier verbindest du YASAIMAPP mit deiner eigenen Cloud, also deinem Speicher im Internet. Das ist freiwillig, bringt dir aber viel:'
        => 'Here you connect YASAIMAPP to your own cloud, that is, your storage on the internet. It is optional, but it gives you a lot:',
    '<strong>Deine großen Bilder in voller Größe:</strong> Fotos aus der Kamera, auch gestackte, und Bilder durchs Mikroskop. Am Fund wählst du sie unter „Belege" mit „Auswählen" aus.'
        => '<strong>Your large pictures at full size:</strong> photos from the camera, stacked ones too, and pictures through the microscope. At the find you pick them under "Belege" with "Auswählen".',
    '<strong>Messen auf den Bildern,</strong> zum Beispiel Sporen unter dem Mikroskop.'
        => '<strong>Measuring on the pictures,</strong> for example spores under the microscope.',
    '<strong>Alles ordentlich abgelegt:</strong> ein Ordner je Gattung und Art, darin einer je Fund mit Tag, Ort und Fundnummer. Jedes Bild trägt Art, Tag, Ort, seine Nummer und seinen Motivnamen im Dateinamen. So findest du deine Bilder auch ohne YASAIMAPP wieder.'
        => '<strong>Everything neatly filed:</strong> a folder per genus and species, in it one per find with day, place and find number. Every picture carries species, day, place, its number and its subject name in its file name. So you find your pictures again without YASAIMAPP too.',
    '<strong>Eine Sicherung deiner Funde,</strong> jede Nacht von selbst.'
        => '<strong>A backup of your finds,</strong> every night by itself.',
    'Die Bilder gehen im Moment nur in %s. Die Sicherung geht bei jedem Anbieter unten.'
        => 'At the moment the pictures go only to %s. The backup works with every provider below.',
    'Und die Fotos, die ich am Fund mit „Aufnehmen" mache?'
        => 'And the photos I take at the find with "Aufnehmen"?',
    'Willst du ein Bild in voller Größe, nimm „Auswählen". Das lädt jedes Bild so hoch, wie es ist, auch ein Foto aus der Galerie deines Handys. „Auswählen" gibt es, sobald eine Cloud verbunden ist.'
        => 'If you want a picture at full size, use "Auswählen". It uploads every picture as it is, a photo from your phone\'s gallery too. "Auswählen" is there as soon as a cloud is connected.',
    'Benutzer'
        => 'User',
    'Benutzer bearbeiten'
        => 'Edit user',
    'Benutzerverwaltung'
        => 'User administration',
    'Benutzer — eigene Punkte, Listen und Fotos'
        => 'User — own points, lists and photos',
    'Bereich %d filtert jetzt — %s.'
        => 'Area %d is now filtering — %s.',
    'Bereich %s'
        => 'Area %s',
    'Bereich %s aus dem Filter nehmen'
        => 'Remove area %s from the filter',
    'Bereiche der Verwaltung'
        => 'Sections of the administration',
    'Bereiche zeichnest du auf der Karte.'
        => 'You draw areas on the map.',
    'Bereich zeichnen'
        => 'Draw area',
    'Bereich zeichnen: Ecke für Ecke antippen, dann „Fertig".'
        => 'Draw area: tap corner by corner, then "Done".',
    'Bereich zeichnen …'
        => 'Draw an area …',
    'Bereits eingespielt'
        => 'Already applied',
    'Bericht öffnen'
        => 'Open report',
    'Berlin'
        => 'Berlin',
    'Bern'
        => 'Bern',
    'Bernstein'
        => 'Amber',
    'Beschreibung'
        => 'Description',
    'Besitzer'
        => 'Owner',
    'Beste Genauigkeit ±%s m, %s verschiedene Zeitstempel und %s verschiedene Positionen bei %s Meldungen, Streuung %s m.'
        => 'Best accuracy ±%s m, %s distinct timestamps and %s distinct positions across %s reports, scatter %s m.',
    'Bestimmer'
        => 'Determiner',
    'Bestimmung'
        => 'Identification',
    'Bestimmungssicherheit'
        => 'Determination certainty',
    'Bestätigen'
        => 'Confirm',
    'Betrifft nur den Code, nicht die Mitgliedschaft: Wie lange er sich einlösen lässt. Ist er eingelöst, läuft nichts mehr ab.'
        => 'Concerns only the code, not the membership: how long it can be redeemed. Once it is redeemed, nothing expires any more.',
    'Bezeichnung des Feldes'
        => 'Name of the field',
    'Bildgröße weicht ab'
        => 'Picture size differs',
    'Das Eichbild von „%s" ist %d × %d px groß, dieses Bild %d × %d px. Es ist kleiner und anders beschnitten. YASAIMAPP rechnet so, als wäre es auf %d %% verkleinert. Das stimmt nur, wenn es mit derselben Kamera aufgenommen ist.'
        => 'The calibration image of “%s” is %d × %d px, this picture %d × %d px. It is smaller and cropped differently. YASAIMAPP calculates as if it were shrunk to %d %%. That is only right if it was taken with the same camera.',
    'Das Bild ist auf %d %% des Eichbilds verkleinert. Der Maßstab ist entsprechend umgerechnet.'
        => 'The picture is shrunk to %d %% of the calibration image. The scale has been converted accordingly.',
    'Bildbreite weicht ab'
        => 'Image width differs',
    'Bilder'
        => 'Images',
    'Bild'
        => 'Picture',
    'Bildtitel-Vorschläge'
        => 'Picture-name suggestions',
    'Bilder löschen'
        => 'delete photos',
    'Bild fehlt'
        => 'Image missing',
    'bis'
        => 'to',
    'Bis'
        => 'Until',
    'bis %s'
        => 'until %s',
    'bis %s m'
        => 'up to %s m',
    'Bisherige Importe'
        => 'Imports so far',
    'bis Zoom %s'
        => 'up to zoom %s',
    'Bis Zoomstufe'
        => 'Up to zoom level',
    'Bitte den Nachnamen angeben.'
        => 'Please enter your last name.',
    'Bitte den Vornamen angeben.'
        => 'Please enter your first name.',
    'Bitte eine gültige E-Mail-Adresse angeben.'
        => 'Please enter a valid e-mail address.',
    'Bitte Freund oder Gruppe auswählen.'
        => 'Please select a friend or group.',
    'Bitte Vorname und Nachname angeben.'
        => 'Please enter first and last name.',
    'Blatt %s · Viertel %s · Sechzehntel %s · Vierundsechzigstel %s  (1=NW 2=NO 3=SW 4=SO)'
        => 'Sheet %s · quarter %s · sixteenth %s · sixty-fourth %s  (1=NW 2=NE 3=SW 4=SE)',
    'Blatt, Viertel oder Sechzehntel — Komma heißt oder'
        => 'Sheet, quarter or sixteenth — a comma means or',
    'Blattmitte'
        => 'sheet center',
    'Blumentiere'
        => 'Anthozoans',
    'Blätter'
        => 'Sheets',
    'Brandenburg'
        => 'Brandenburg',
    'Breite'
        => 'Width',
    'Breitengrad'
        => 'Latitude',
    'Bremen'
        => 'Bremen',
    'Browser'
        => 'Browser',
    'Burgenland'
        => 'Burgenland',
    'Bärtierchen'
        => 'Tardigrades',
    'Bäume, Sträucher, Kräuter und Farne Deutschlands — mit deutschen Namen für 95 % der Arten.'
        => 'Trees, shrubs, herbs and ferns of Germany — with German names for 95 % of the species.',
    'Chat'
        => 'Chat',
    'Chat mit %s'
        => 'Chat with %s',
    'Chat mit %s — Neues da'
        => 'Chat with %s — something new',
    'Chat schließen'
        => 'Close chat',
    'Chats mit Freunden'
        => 'Chats with friends',
    'Chat von „%s"'
        => 'Chat of "%s"',
    'Chat von „%s" — Neues da'
        => 'Chat of "%s" — something new',
    'Client-ID'
        => 'Client ID',
    'Client-Secret'
        => 'Client secret',
    'Cloud'
        => 'Cloud',
    'Code'
        => 'Code',
    'Code erzeugen'
        => 'Generate code',
    'Code zurückziehen?'
        => 'Withdraw the code?',
    'CSV-Tabelle (beliebige Spalten)'
        => 'CSV table (arbitrary columns)',
    'CSV-Tabelle (Excel, LibreOffice)'
        => 'CSV table (Excel, LibreOffice)',
    'Dabei seit'
        => 'Member since',
    'Dafür hat dir der Besitzer kein Recht gegeben.'
        => 'The owner has not given you the right for that.',
    'Dafür muss erst ein Speicher verbunden sein.'
        => 'A storage has to be connected for that first.',
    'Dafür verbinde oben zuerst eine Cloud.'
        => 'For this, connect a cloud above first.',
    'Dafür musst du angemeldet sein.'
        => 'You need to be signed in for that.',
    'Es ist keine Cloud verbunden.'
        => 'No cloud is connected.',
    'Damit deine Funde dort landen, wo du stehst, braucht die Karte deinen Standort. Der Browser fragt dich gleich danach — bitte erlaube es. Die Position bleibt auf deinem Gerät und wandert nur mit den Funden mit, die du selbst anlegst.'
        => 'So your finds land where you are standing, the map needs your location. The browser will ask you for it in a moment — please allow it. The position stays on your device and only travels along with the finds you create yourself.',
    'Damit ist die Umrandung weg, und die Offline-Daten dieses Gebiets werden auf deinen Geräten mitgelöscht — jeweils beim nächsten Aufruf mit dem Gerät.'
        => 'This removes the outline, and the offline data of this area is deleted from your devices along with it — each on that device\'s next visit.',
    'Danach räumen sich die Karten auf diesem Gerät von selbst weg. Die Umrandung bleibt — nachladen ist ein Knopfdruck.'
        => 'After that the maps clear themselves off this device. The outline stays — reloading is one button press.',
    'Während des Ladens bleibt der Bildschirm von selbst an. Sperr das Handy nicht, sonst hält das Laden nach etwa einer Minute an. Entsperrst du es, geht es von selbst weiter. Bei großen Gebieten leg das Handy am besten ans Ladekabel.'
        => 'While loading, the screen stays on by itself. Do not lock the phone, or the loading pauses after about a minute. When you unlock it, it goes on by itself. For large areas, best put the phone on the charger.',
    'Danach steht örtlich auch %s auf dem Stand dieses Servers. Migrationen, die es nur örtlich schon gab, gelten dann wieder als offen und laufen beim nächsten Einspielen erneut.'
        => 'Afterwards %s locally also matches this server\'s state. Migrations that only existed locally then count as open again and run anew on the next apply.',
    'Danach öffnet sich das Fenster mit den Feldern. <strong>Gespeichert wird unten</strong>, und nichts steht darunter.'
        => 'Then the window with the fields opens. <strong>Saving happens at the bottom</strong>, and nothing stands below it.',
    'dann gibt es offline kein Kartenbild.'
        => 'then there is no map image offline.',
    'Darstellung'
        => 'Presentation',
    'Darstellung von „%s" auf diesem Gerät'
        => 'Appearance of "%s" on this device',
    'Dein jetziges Passwort stimmt nicht.'
        => 'Your current password is not right.',
    'Das Aufspielen fasst dieses Verzeichnis nicht an, die Dateien bleiben also liegen.'
        => 'Deployment does not touch this directory, so the files stay put.',
    'Das Bild'
        => 'The image',
    'Das Bild lässt sich nicht ablegen.'
        => 'The image cannot be stored.',
    'Das Bild lässt sich nicht umwandeln.'
        => 'The image cannot be converted.',
    'Das Bild war zu gross für diesen Server (Grenze: %s).'
        => 'The image was too large for this server (limit: %s).',
    'Das Bild war zu gross für diesen Server (Grenze: %s). Bitte beim Hoster anheben lassen.'
        => 'The image was too large for this server (limit: %s). Please ask the hoster to raise it.',
    'Das Bild wird sofort entfernt. Es gibt dafür keinen Papierkorb.'
        => 'The image is removed immediately. There is no trash for it.',
    'Das braucht die Migration 0091 — sie ist noch nicht eingespielt.'
        => 'This needs migration 0091 — it has not been applied yet.',
    'Das braucht die Migration 0093 — sie ist noch nicht eingespielt.'
        => 'This needs migration 0093 — it has not been applied yet.',
    'Das braucht die Migration 0096 — sie ist noch nicht eingespielt.'
        => 'This needs migration 0096 — it has not been applied yet.',
    'Das darf nur, wer geteilt hat — oder der Besitzer der Freigabe.'
        => 'Only whoever shared it may do that — or the owner of the share.',
    'Das darf nur der Besitzer der Freigabe.'
        => 'Only the owner of the share may do that.',
    'Das darf nur ein Verwalter.'
        => 'Only an administrator may do that.',
    'Das dauert.'
        => 'This takes a while.',
    'Das Eichbild liegt in deiner Cloud im Ordner „Kalibrierung".'
        => 'The calibration image is in your cloud, in the folder "Kalibrierung".',
    'Die Bilder ließen sich nicht laden: %s'
        => 'The pictures could not be loaded: %s',
    'Die Cloud nennt keine Dateikennung.'
        => 'The cloud names no file id.',
    'Die Cloud hat die Datei nicht angenommen (%s).'
        => 'The cloud did not accept the file (%s).',
    'Die Verbindung zur Cloud ist abgerissen.'
        => 'The connection to the cloud broke off.',
    'Der Dateiname fehlt.'
        => 'The file name is missing.',
    'Die Aufnahme ist in der Cloud nicht angekommen.'
        => 'The recording did not arrive in the cloud.',
    'Für Bilder direkt aus dem Browser braucht der Eimer noch eine CORS-Regel für %s. Dein Schlüssel darf sie nicht anlegen. Lege sie bei deinem Anbieter an: Methoden PUT, GET und HEAD, alle Kopfzeilen.'
        => 'For pictures straight from the browser the bucket still needs a CORS rule for %s. Your key may not create it. Create it at your provider: methods PUT, GET and HEAD, all headers.',
    'Das Eichbild ließ sich nicht in der Cloud ablegen. Die Vorlage ist nicht gespeichert. Versuch es noch einmal.'
        => 'The calibration image could not be filed in the cloud. The preset is not saved. Please try again.',
    'Das Eichbild von „%s" ist %d px breit, dieses Bild %d px. Ein unverkleinerter Beschnitt derselben Kamera — etwa vom Stacking — misst weiter richtig; ein verkleinertes Bild oder eine andere Kamera misst falsch.'
        => 'The calibration image of "%s" is %d px wide, this image %d px. An unscaled crop from the same camera — from stacking, say — still measures correctly; a downscaled image or a different camera measures wrong.',
    'Das eigene Konto lässt sich hier nicht sperren oder löschen.'
        => 'Your own account cannot be locked or deleted here.',
    'Das Entfernen ist fehlgeschlagen.'
        => 'Removing failed.',
    'Das erste Konto dieser Anlage'
        => 'The first account of this installation',
    'Das Fenster eines Punktes mit seinen Feldern'
        => 'The window of a point with its fields',
    'Das Format wurde nicht erkannt.'
        => 'The format was not recognized.',
    'Das Foto lässt sich nicht löschen.'
        => 'The photo cannot be deleted.',
    'Das Fotomaß ist eine Anzahl — oder bleibt leer für die Vorgabe.'
        => 'The photo measure is a number — or stays empty for the default.',
    'Das Foto verschwindet endgültig — es gibt keinen Papierkorb für Bilder.'
        => 'The photo disappears for good — there is no trash for images.',
    'Das Gastkonto fehlt — die Migration 0074 ist nicht eingespielt.'
        => 'The guest account is missing — migration 0074 has not been applied.',
    'Das Gebiet braucht mindestens drei Ecken.'
        => 'The area needs at least three corners.',
    'Das Gebiet gibt es nicht mehr.'
        => 'The area no longer exists.',
    'Das Gebiet ist nicht lesbar.'
        => 'The area is not readable.',
    'Das ging nicht.'
        => 'That did not work.',
    'Das hat nicht geklappt: %s'
        => 'That did not work: %s',
    'Das Hochladen ist fehlgeschlagen.'
        => 'The upload failed.',
    'Das ist das Gründerkonto dieser Anlage — es lässt sich weder sperren noch löschen.'
        => 'This is the founder account of this installation — it can be neither locked nor deleted.',
    'Das ist der letzte Admin — ohne ihn käme niemand mehr an die Serververwaltung. Erst jemand anderen zum Admin machen.'
        => 'This is the last admin — without them nobody could reach the server administration any more. Make somebody else an admin first.',
    'Dieses Bild gibt es nicht mehr.'
        => 'This picture no longer exists.',
    'Diesen Ordner kann ich gerade nicht öffnen. Die Aufnahme selbst ist davon nicht betroffen — du findest sie weiter bei deinem Fund.'
        => 'I cannot open this folder right now. The recording itself is not affected — you still find it at your find.',
    'Diese E-Mail-Adresse ist nicht vollständig. Eine Adresse sieht so aus: name@beispiel.de'
        => 'This e-mail address is not complete. An address looks like this: name@example.com',
    'Das Kontingent muss eine Zahl in Megabyte sein — oder leer für unbegrenzt.'
        => 'The quota must be a number in megabytes — or empty for unlimited.',
    'Das Laden ist abgebrochen: %s'
        => 'Loading was aborted: %s',
    'Das Messtischblatt (TK25) ist die Bezugsgrösse der deutschen Kartierung — 10 Bogenminuten breit, 6 hoch, rund 11 × 11 km. Neue Punkte bekommen es beim Anlegen, verschobene beim Speichern. Hier lässt es sich für ältere Punkte nachtragen.'
        => 'The Messtischblatt (TK25) is the reference unit of German mapping — 10 arc minutes wide, 6 high, around 11 × 11 km. New points get it on creation, moved ones on saving. Here it can be backfilled for older points.',
    'DAS MTB IN DER VORSCHAU'
        => 'THE MAP SHEET (MTB) IN THE PREVIEW',
    'Das neue Passwort ist zu kurz. Es braucht mindestens 10 Zeichen.'
        => 'The new password is too short. It needs at least 10 characters.',
    'Das Paket kam unvollständig an.'
        => 'The package arrived incomplete.',
    'Das Passwort muss mindestens 10 Zeichen haben.'
        => 'The password must have at least 10 characters.',
    'Das Passwort stimmt nicht.'
        => 'The password is not correct.',
    'Dasselbe Feld wurde auf zwei Geräten geändert — die jüngere Fassung hat gewonnen. Hier stehen beide nebeneinander, und je Feld lässt sich die andere zurückholen. Verloren ist keine.'
        => 'The same field was changed on two devices — the younger version won. Here both stand side by side, and per field the other one can be brought back. None is lost.',
    'Das Speichern ist fehlgeschlagen.'
        => 'Saving failed.',
    'Das Stiftsymbol öffnet Name, Farbe, Form und die Fundfelder.'
        => 'The pencil icon opens name, color, shape and the find fields.',
    'Das Token liess sich nicht verschlüsseln.'
        => 'The token could not be encrypted.',
    'Das Verzeichnis data/ lässt sich nicht anlegen.'
        => 'The data/ directory cannot be created.',
    'Das Vorschaubild'
        => 'The preview image',
    'Das Zeitfenster der Exkursion ist vorbei — es können keine Funde mehr dazukommen.'
        => 'The excursion\'s time window is over — no more finds can be added.',
    'Das Zeitfenster endet, bevor es beginnt.'
        => 'The time window ends before it begins.',
    'Das Zeitfenster ist gerade nicht offen.'
        => 'The time window is not open right now.',
    'Datei'
        => 'File',
    'Dateien aus %s, die noch nicht in %s stehen. Sie laufen in der Reihenfolge ihrer Namen.'
        => 'Files from %s that do not yet stand in %s. They run in the order of their names.',
    'Dateien fehlen.'
        => 'Files are missing.',
    'Dateien hierher ziehen'
        => 'Drag files here',
    'Dateien hierherziehen oder klicken.'
        => 'Drag files here or click.',
    'Daten auf dem Gerät'
        => 'Data on the device',
    'Datenbankabzug'
        => 'Database dump',
    'Datensicherung'
        => 'Backup',
    'Daten unzureichend'
        => 'data deficient',
    'dauerhaft'
        => 'permanent',
    'Dauerhaft behalten'
        => 'Keep for good',
    'davon gesichert'
        => 'of which backed up',
    'davon mit Verweis in die Namensliste'
        => 'of which with a reference into the name list',
    'Deine %d Fotos für diese Exkursion sind voll — lösch eines deiner Fotos, dann passt ein neues.'
        => 'Your %d photos for this excursion are used up — delete one of your photos and a new one will fit.',
    'Deine begonnene Eingabe von vorhin ließ sich nicht zurückholen.'
        => 'Your begun input from before could not be brought back.',
    'Deine eigenen Funde. Was dir über Freigaben oder Exkursionen nur angezeigt wird, gehört den anderen und wird in DEREN Cloud gesichert.'
        => 'Your own finds. What is merely shown to you through shares or excursions belongs to the others and is backed up in THEIR cloud.',
    'Deine Eingabe von vorhin steht wieder da — noch nicht gespeichert.'
        => 'Your input from before stands there again — not saved yet.',
    'Deine Freunde'
        => 'Your friends',
    'Deine Funde gehören ab jetzt nur noch dir.'
        => 'From now on your finds belong to you alone.',
    'Jede Nacht legt YASAIMAPP eine Kopie deiner Funde in deine Cloud, in den Ordner <code>%s</code>. Für jeden Monat gibt es eine Tabelle. Du öffnest sie mit jedem Tabellenprogramm, zum Beispiel Excel. Über „Import" holst du sie wieder herein.'
        => 'Every night YASAIMAPP puts a copy of your finds into your cloud, in the folder <code>%s</code>. There is one table for each month. You open it with any spreadsheet program, for example Excel. Via "Import" you bring it back in.',
    'Deine Gebiete, mit dem, was davon auf diesem Gerät liegt.'
        => 'Your areas, with what of them lies on this device.',
    'Deine Listen — anklicken blendet ihre Punkte ein und aus.'
        => 'Your lists — clicking shows and hides their points.',
    'Dem Formular „%s" fehlen die Felder %s. Es wurde nichts geändert — deine Auswahl steht noch.'
        => 'The form "%s" is missing the fields %s. Nothing was changed — your selection still stands.',
    'Den Eintrag gibt es auf dem Server nicht mehr — er wurde dort endgültig gelöscht. Ein erneuter Versuch ändert daran nichts.'
        => 'The entry no longer exists on the server — it was permanently deleted there. Trying again changes nothing about that.',
    'Den Punkt gibt es nicht mehr.'
        => 'The point no longer exists.',
    'Der belegte Platz wird bei jedem Aufruf auf der Platte nachgezählt, nicht mitgeschrieben — ein Zähler in einer Spalte läuft irgendwann auseinander, und dann stimmt er nie wieder.'
        => 'The used space is recounted on disk at every call, not tracked along — a counter in a column drifts apart eventually, and then it is never right again.',
    'Der Bereich liess sich nicht laden. Noch einmal öffnen versucht es erneut — hilft das nicht, ist die Anmeldung abgelaufen.'
        => 'The section could not be loaded. Opening it again retries — if that does not help, the sign-in has expired.',
    'Der Bildtitel braucht die Migration 0097 — sie ist noch nicht eingespielt.'
        => 'The picture name needs migration 0097 — it has not been applied yet.',
    'Der Bildtitel ließ sich nicht speichern.'
        => 'The picture name could not be saved.',
    'Der Code lässt sich danach nicht mehr einlösen.'
        => 'The code cannot be redeemed afterwards.',
    'Der Dienst hat nicht geantwortet.'
        => 'The service did not answer.',
    'Der eine Punkt der Tabelle kommt in die Datei.'
        => 'The table\'s one point goes into the file.',
    'Der eine Punkt von „%s" wandert dabei sofort in die neue Unterliste.'
        => 'The one point of "%s" immediately moves into the new sublist.',
    'Der Einladungscode wurde soeben von jemand anderem eingelöst.'
        => 'The invitation code was just redeemed by somebody else.',
    'Der Filter lässt keinen Punkt durch.'
        => 'The filter lets no point through.',
    'Der Freundes-Chat braucht die Migration 0068 — sie ist noch nicht eingespielt.'
        => 'The friend chat needs migration 0068 — it has not been applied yet.',
    'Der Fund landet im Papierkorb — du findest ihn in der Verwaltung unter Papierkorb bei seiner Exkursion und kannst ihn dort zurückholen.'
        => 'The find goes into the trash — you will find it in the administration under Trash at its excursion and can bring it back there.',
    'Der Fund liegt jetzt im Papierkorb seiner Exkursion.'
        => 'The find now lies in the trash of its excursion.',
    'Der Gastzugang braucht die Migration 0074 — sie ist noch nicht eingespielt.'
        => 'Guest access needs migration 0074 — it has not been applied yet.',
    'Der Gerätespeicher ist voll'
        => 'The device storage is full',
    'Der Gerätespeicher ist voll — Eingaben gehen beim nächsten Laden verloren. Bitte jetzt übertragen.'
        => 'The device storage is full — input will be lost on the next load. Please transfer now.',
    'Der gewählte Punkt kommt in die Datei.'
        => 'The chosen point goes into the file.',
    'Der gewählte Punkt wandert in den Papierkorb — samt Listenzuordnung und Fotos. Endgültig gelöscht wird dort erst auf deinen Befehl.'
        => 'The chosen point moves to the trash — along with its list assignments and photos. It is only deleted for good there on your command.',
    'Der Import braucht die Migration 0045 — sie ist noch nicht eingespielt.'
        => 'The import needs migration 0045 — it has not been applied yet.',
    'Der Import ist zurückgenommen — %d Punkte liegen im Papierkorb, seine Listen sind weg.'
        => 'The import has been taken back — %d points are in the trash, its lists are gone.',
    'Der Import ist zurückgenommen — seine Listen sind weg.'
        => 'The import has been taken back — its lists are gone.',
    'Der Import ist zurückgenommen — sein Punkt liegt im Papierkorb, seine Listen sind weg.'
        => 'The import has been taken back — its point is in the trash, its lists are gone.',
    'Der Import rät, welche Spalte Titel, Koordinaten und Fundzeit trägt; die Zuordnung lässt sich vor dem Einspielen prüfen und ändern. Alle übrigen Spalten wandern als „Spalte: Wert" in die Notiz.'
        => 'The import guesses which column carries title, coordinates and find time; the assignment can be checked and changed before applying. All remaining columns go into the note as "column: value".',
    'Der Leiter hat Fotos für diese Exkursion ausgeschaltet.'
        => 'The leader has switched photos off for this excursion.',
    'Der Listenbaum in der Seitenleiste'
        => 'The list tree in the sidebar',
    'Der Maßstab braucht die Migration 0088 — sie ist noch nicht eingespielt.'
        => 'The scale needs migration 0088 — it has not been applied yet.',
    'Der Maßstab für Aufnahmen braucht die Migration 0090 — sie ist noch nicht eingespielt.'
        => 'The scale for recordings needs migration 0090 — it has not been applied yet.',
    'Der Name steht als Finder an deinen Funden — auch an denen von vorhin, wenn du ihn hier änderst.'
        => 'The name stands as finder on your finds — including the earlier ones, if you change it here.',
    'Der Ordner darf gelöscht werden. Er entsteht dann von selbst neu, und alle Monate werden einmal frisch geschrieben.'
        => 'The folder may be deleted. It then comes back by itself, and every month is written fresh once.',
    'Der Ort — Gebiete zeichnest du auf der Karte.'
        => 'The place — you draw areas on the map.',
    'Der Papierkorb ist leer.'
        => 'The trash is empty.',
    'Der Punkt landet im Papierkorb von %s — zurückholen kann ihn nur %s.'
        => 'The point goes into the trash of %s — only %s can bring it back.',
    'Der Punkt liegt inzwischen im Papierkorb — die Änderung ist dort mit dabei.'
        => 'The point now lies in the trash — the change went there with it.',
    'Der Punkt liegt jetzt im Papierkorb von %s.'
        => 'The point now lies in the trash of %s.',
    'Der Rand je Ebene muss zwischen 100 und 300 Prozent liegen.'
        => 'The margin per layer must be between 100 and 300 percent.',
    'Der Reiter Offline mit einem Gebiet'
        => 'The Offline tab with an area',
    'Der Reiter Organisieren mit der Punkttabelle'
        => 'The Organize tab with the point table',
    'Der Schlüssel für die Cloud-Zugänge fehlt in der config.php — die gespeicherte Verbindung lässt sich ohne ihn nicht lesen.'
        => 'The key for the cloud connections is missing from config.php — the stored connection cannot be read without it.',
    'Der Server liefert keine Eimer mehr, meldet aber offene.'
        => 'The server delivers no more buckets but still reports open ones.',
    'Der Server liefert keine Exkursions-Eimer mehr, meldet aber offene.'
        => 'The server delivers no more excursion buckets but still reports open ones.',
    'Der Server liefert keine Freigabe-Eimer mehr, meldet aber offene.'
        => 'The server delivers no more share buckets but still reports open ones.',
    'Der Speicher nennt für „%s" keine Ordnerkennung.'
        => 'The storage names no folder id for "%s".',
    'Der Speicherplatz reicht nicht: %s werden gebraucht, frei sind %s.'
        => 'Not enough storage: %s needed, %s free.',
    'Der Text fehlt.'
        => 'The text is missing.',
    'Der Titel. In einer Artenliste schlägt er wissenschaftliche Namen vor.'
        => 'The title. In a species list it suggests scientific names.',
    'Der Titel lässt sich nicht leeren.'
        => 'The title cannot be cleared.',
    'Der Umriss einer Fläche — eine Zeile je Punkt kann nur einen Ort tragen, dann steht dort die Mitte. Und die Fotos: die liegen als Bilder in den Artordnern nebenan.'
        => 'The outline of an area — one line per point can carry only one place, so the centre stands there. And the photos: they lie as images in the species folders next door.',
    'Der Umriss taugt nicht.'
        => 'The outline is no good.',
    'Bitte trage deinen Vornamen ein.'
        => 'Please enter your first name.',
    'Der Zeitraum.'
        => 'The period.',
    'Der Zeitraum braucht Datumsangaben.'
        => 'The time range needs dates.',
    'Der Zeitraum endet, bevor er beginnt.'
        => 'The time range ends before it begins.',
    'Der Zugang braucht die Migration 0074.'
        => 'This access needs migration 0074.',
    'deutsches Geländemodell (±0,3 m)'
        => 'German terrain model (±0.3 m)',
    'Dezimalgrad'
        => 'Decimal degrees',
    'DI'
        => 'TU',
    'Die %d Punkte von „%s" wandern dabei sofort in die neue Unterliste.'
        => 'The %d points of "%s" immediately move into the new sublist.',
    'Die %s gewählten Punkte kommen in die Datei.'
        => 'The %s chosen points go into the file.',
    ' Die %s Unterlisten rücken eine Ebene auf, nichts darunter geht verloren.'
        => ' The %s sublists move up one level, nothing beneath it is lost.',
    'Die %s Zeilen in %s werden gelöscht. Sie wieder einzuspielen dauert je nach Tabelle Minuten bis Stunden.'
        => 'The %s rows in %s are deleted. Re-importing them takes minutes to hours depending on the table.',
    'Die Ablegefläche des Imports'
        => 'The drop surface of the import',
    'die anderen: %s'
        => 'the others: %s',
    'die anderen: nur ansehen'
        => 'the others: view only',
    'Die aufgeführten Tabellen werden dabei %s — was örtlich darin steht, ist danach weg.'
        => 'The listed tables are thereby %s — whatever they hold locally is gone afterwards.',
    'Die Aufnahme ließ sich nicht löschen.'
        => 'The recording could not be deleted.',
    'Die Auskunft kommt von Nominatim, dem Ortsdienst von OpenStreetMap — gestiftet, nicht gekauft. Er erlaubt eine Anfrage je Sekunde, und daran hält sich dieses Werkzeug: rund zehn Punkte je Häppchen von %d Sekunden. Der Lauf darf jederzeit unterbrochen und wieder gestartet werden.'
        => 'The answer comes from Nominatim, OpenStreetMap\'s place service — donated, not bought. It allows one request per second, and this tool keeps to that: around ten points per chunk of %d seconds. The run may be interrupted and restarted at any time.',
    'Die Auswahl enthält die Liste %s vollständig.'
        => 'The selection contains the list %s completely.',
    'Die Auswahl enthält die Listen %s vollständig.'
        => 'The selection contains the lists %s completely.',
    'Die Autoliste braucht einen Namen.'
        => 'The auto list needs a name.',
    'Die beiden Passwörter stimmen nicht überein.'
        => 'The two passwords do not match.',
    'Klicke Anfang und Ende einer Strecke an, deren Länge du kennst. Ein dritter Klick fängt neu an.'
        => 'Click the start and the end of a line whose length you know. A third click starts anew.',
    'Die Benutzerverwaltung braucht Migrationen, die auf diesem Server noch nicht eingespielt sind. Der Reiter „Migrationen" nebenan erledigt das.'
        => 'The user administration needs migrations that have not been applied on this server yet. The "Migrations" tab next door takes care of that.',
    'Die Bilddatei liegt nicht auf diesem Server.'
        => 'The image file does not lie on this server.',
    'Die Bildmaße brauchen die Migration 0095 — sie ist noch nicht eingespielt.'
        => 'The image dimensions need migration 0095 — it has not been applied yet.',
    'Die Größe des Bildes fehlt. Wähle das Bild bitte noch einmal.'
        => 'The size of the image is missing. Please choose the image again.',
    'Die Cloud-Tabelle fehlt noch — Migration 0053 einspielen.'
        => 'The cloud table is still missing — apply migration 0053.',
    'Die Cloud ist auf diesem Server noch nicht eingerichtet.'
        => 'The cloud is not set up on this server yet.',
    'Die Cloud ist gerade nicht erreichbar.'
        => 'The cloud cannot be reached right now.',
    'Die Datei data/cloud.php lässt sich nicht schreiben — dem Server fehlt das Recht dazu. Dann bleibt der Weg über die config.php.'
        => 'The file data/cloud.php cannot be written — the server lacks the right to. The way via config.php remains then.',
    'Die Dateien gehen in der Karte unter Verwaltung → Import wieder hinein, ohne Umweg: die Spaltennamen sind dieselben, die der Import erwartet. Genauso öffnen sie sich in jedem Tabellenprogramm (Semikolon als Trennzeichen, UTF-8).'
        => 'The files go back into the map under Administration → Import, no detour: the column names are the very ones the import expects. They open just as well in any spreadsheet program (semicolon as separator, UTF-8).',
    'Die Dateien öffnen sich in jedem Tabellenprogramm und gehen über <em>Verwaltung → Import</em> auch wieder herein. Es sind <strong>deine eigenen Funde</strong> — was dir über Freigaben oder Exkursionen nur angezeigt wird, gehört den anderen und wird in deren Cloud gesichert.'
        => 'The files open in any spreadsheet program and also come back in via <em>Administration → Import</em>. They are <strong>your own finds</strong> — what is merely shown to you via shares or excursions belongs to the others and is backed up in their cloud.',
    'Die Datei lässt sich nicht lesen: %s'
        => 'The file cannot be read: %s',
    'Die Datei war zu gross für diesen Server (Grenze: %s).'
        => 'The file was too big for this server (limit: %s).',
    'Die Datensicherung braucht Migration 0085 — die ist noch nicht eingespielt.'
        => 'The backup needs migration 0085 — it has not been applied yet.',
    'Die Datensicherung braucht Migration 0085 — die ist noch nicht eingespielt. Bis dahin wird nichts hochgeladen.'
        => 'The backup needs migration 0085 — it has not been applied yet. Until then nothing is uploaded.',
    'Die drei Ziffern hinter dem Schrägstrich sind Viertel, Sechzehntel und Vierundsechzigstel des Blattes, jeweils 1=NW 2=NO 3=SW 4=SO.'
        => 'The three digits after the slash are quarters, sixteenths and sixty-fourths of the sheet, each 1=NW 2=NE 3=SW 4=SE.',
    'Die E-Mail-Adresse ist nicht lesbar — oder lass sie ganz weg.'
        => 'The e-mail address is not readable — or leave it out entirely.',
    'Die E-Mail-Adresse ist nicht lesbar — oder lass sie weg.'
        => 'The e-mail address is not readable — or leave it out.',
    'Die Eichlinie braucht genau zwei Punkte.'
        => 'The calibration line needs exactly two points.',
    'Die Eichlinie hat keine Länge.'
        => 'The calibration line has no length.',
    'Die Eichlinie liegt nicht im Bild.'
        => 'The calibration line is not within the image.',
    'Die eigene Admin-Rolle lässt sich hier nicht abgeben — sonst käme niemand mehr an die Serververwaltung.'
        => 'You cannot give up your own admin role here — otherwise nobody could reach the server administration any more.',
    'Die Eimer kommen nicht zum Ende.'
        => 'The buckets never finish.',
    'Die Eimer stehen noch nicht bereit — Migration 0038 fehlt.'
        => 'The buckets are not ready yet — migration 0038 is missing.',
    'Die eingehängten Listen dieses Mitglieds werden ausgehängt.'
        => 'The hung-in lists of this member are unhooked.',
    'Die Einrichtung ist bereits abgeschlossen. Diese Seite kann gelöscht werden.'
        => 'Setup is already complete. This page can be deleted.',
    'Die Exkursion braucht einen Namen.'
        => 'The excursion needs a name.',
    'Die Exkursions-Eimer kommen nicht zum Ende.'
        => 'The excursion buckets never finish.',
    'Die Exkursionsleitung hat ein Gebiet hinterlegt: %s. Lade es jetzt herunter, solange du noch Empfang hast — im Wald zeigt die Karte dann auch ohne Netz, wo du bist.'
        => 'The excursion leaders have stored an area: %s. Download it now while you still have reception — in the woods the map will then show where you are even without a network.',
    'Die Filter schließen sich gegenseitig aus — zusammen bleibt kein Punkt übrig.'
        => 'The filters exclude one another — together no point remains.',
    'Die Filterzeile — was sie zurückhält, verschwindet auch aus der Liste.'
        => 'The filter row — what it holds back also disappears from the list.',
    'Die Fotoverwaltung ist auf diesem Server noch nicht eingerichtet.'
        => 'Photo management is not set up on this server yet.',
    'Die Freigabe %s verschwindet für alle Mitglieder. Die eingehängten Listen und ihre Punkte bleiben bei ihren Besitzern unverändert.'
        => 'The share %s disappears for all members. The hung-in lists and their points stay unchanged with their owners.',
    'Die Freigabe-Eimer kommen nicht zum Ende.'
        => 'The share buckets never finish.',
    'Die Freigabe braucht einen Namen.'
        => 'The share needs a name.',
    'Die Freigaben stehen noch nicht bereit — Migration 0042 fehlt.'
        => 'Shares are not ready yet — migration 0042 is missing.',
    'Die Freigabe „%s" verschwindet aus deiner Übersicht, und deine %d eingehängten Listen werden ausgehängt. Zurück kommst du nur, wenn %s dich wieder aufnimmt.'
        => 'The share "%1$s" disappears from your overview, and your %2$d hung-in lists are unhooked. You only get back in if %3$s admits you again.',
    'Die Freigabe „%s" verschwindet aus deiner Übersicht, und deine eingehängte Liste wird ausgehängt. Zurück kommst du nur, wenn %s dich wieder aufnimmt.'
        => 'The share "%s" disappears from your overview, and your hung-in list is unhooked. You only get back in if %s admits you again.',
    'Die Freigabe „%s" verschwindet aus deiner Übersicht. Zurück kommst du nur, wenn %s dich wieder aufnimmt.'
        => 'The share "%s" disappears from your overview. You only get back in if %s admits you again.',
    '%s und du seid danach keine Freunde mehr. Euer Chat wird gelöscht. Ihr kommt aus den Arbeitsgruppen und Freigaben des anderen heraus. Was ihr euch direkt geteilt habt, seht ihr nicht mehr.'
        => '%s and you are no longer friends afterwards. Your chat is deleted. You both leave each other\'s working groups and shares. What you shared directly with each other is gone for both of you.',
    'Die Funde aus %d Exkursionen konnten nicht geholt werden.'
        => 'The finds from %d excursions could not be fetched.',
    'Die Funde aus „%s" konnten nicht geholt werden.'
        => 'The finds from "%s" could not be fetched.',
    'Die Funde der Gäste erscheinen unter Organisieren → Touren bei dir. Für die Karte im Gelände kannst du beim Anlegen ein Offline-Gebiet mitgeben; die Gäste laden es mit einem Tippen, und die Größe steht auf dem Knopf.'
        => 'The finds of the guests appear with you under Organize → Tours. For the map in the field you can attach an offline area when creating; the guests download it with one tap, and the size stands on the button.',
    'Die Funde der Gäste werden endgültig gelöscht, samt Fotos. Funde von angemeldeten Teilnehmern und Leitern bleiben bei ihren Besitzern und verlieren nur den Bezug zur Exkursion „%s“.'
        => 'The guests\' finds are deleted for good, photos included. Finds of signed-in participants and leaders stay with their owners and only lose their reference to the excursion "%s".',
    'Die Fundfelder deiner Liste: Finder, Bestimmer, was du dort angelegt hast.'
        => 'The find fields of your list: finder, determiner, whatever you have created there.',
    'Die Fundnummern brauchen die Migration 0070 — sie ist noch nicht eingespielt.'
        => 'The find numbers need migration 0070 — it has not been applied yet.',
    'Die Fundzeit liegt außerhalb des Zeitfensters der Exkursion.'
        => 'The find time lies outside the excursion\'s time window.',
    'Die Fundzeit — vorbelegt mit jetzt, änderbar.'
        => 'The find time — prefilled with now, changeable.',
    'Die Gebiete lassen sich nicht holen: %s'
        => 'The areas cannot be fetched: %s',
    'Die geänderte Umrandung ist nicht gespeichert. Beim Schliessen fällt sie zurück auf den gespeicherten Stand.'
        => 'The changed outline is not saved. On closing it falls back to the saved state.',
    'Die geöffneten Filtergruppen'
        => 'The opened filter groups',
    'Die Gruppe %s verschwindet für alle Mitglieder.'
        => 'The group %s disappears for all members.',
    'Die Karte'
        => 'The map',
    'Die Karte mit der Seitenleiste links'
        => 'The map with the sidebar on the left',
    'Die Karten bleiben auf diesem Gerät, bis du sie über „Vom Gerät löschen" selbst entfernst.'
        => 'The maps stay on this device until you remove them yourself via "Delete from device".',
    'Die Koordinaten sind nicht lesbar. Erwartet wird "51.123456, 7.654321".'
        => 'The coordinates are not readable. Expected form: "51.123456, 7.654321".',
    'Die Lage, mit der gemessenen Genauigkeit.'
        => 'The position, with the measured accuracy.',
    'Die laufende Tour beenden und eine neue beginnen'
        => 'End the running tour and begin a new one',
    'Die Liste folgt dem eingestellten Filter: %s. Sie führt sich von selbst nach — von Hand füllen lässt sie sich nicht, in Freigaben einhängen schon.'
        => 'The list follows the configured filter: %s. It keeps itself up to date — it cannot be filled by hand, but it can be mounted in shares.',
    'Die Liste ist weg — ihre Punkte bleiben.'
        => 'The list is gone — its points stay.',
    'Die Listen eines Punktes sind angepasst.'
        => 'The lists of one point have been adjusted.',
    'Die Listen von %d Punkten sind angepasst.'
        => 'The lists of %d points have been adjusted.',
    'Die Liste verschwindet aus der Freigabe — an ihr selbst und ihren Punkten ändert sich nichts.'
        => 'The list disappears from the share — nothing changes about the list itself or its points.',
    'Die Länge der Eichlinie fehlt oder ist unbrauchbar.'
        => 'The length of the calibration line is missing or unusable.',
    'Die Mehrfachauswahl — das Kästchen im Kopf wählt alles Sichtbare.'
        => 'The multi-selection — the checkbox in the header selects everything visible.',
    'Die Messungen brauchen die Migration 0092 — sie ist noch nicht eingespielt.'
        => 'The measurements need migration 0092 — it has not been applied yet.',
    'Die Messungen ließen sich nicht speichern.'
        => 'The measurements could not be saved.',
    'Die Migration 0041 ist noch nicht eingespielt.'
        => 'Migration 0041 has not been applied yet.',
    'Die Migration 0042 ist noch nicht eingespielt.'
        => 'Migration 0042 has not been applied yet.',
    'Die neue Eichung ist unbrauchbar.'
        => 'The new calibration is unusable.',
    'Die neue Reihenfolge der Bilder ließ sich nicht speichern.'
        => 'The new order of the pictures could not be saved.',
    'Die Notiz wird %s geleert — ein leerer Wert und Leeren sind hier dasselbe.'
        => 'The note is cleared %s — an empty value and clearing are the same thing here.',
    'Die nächste Nummer muss zwischen 1 und 99999999 liegen.'
        => 'The next number must lie between 1 and 99999999.',
    'Die Positionen fehlen oder sind keine Liste.'
        => 'The positions are missing or are not a list.',
    'Die Punkte aus %d Freigaben konnten nicht geholt werden.'
        => 'The points from %d shares could not be fetched.',
    'Die Punkte aus „%s" konnten nicht geholt werden.'
        => 'The points from "%s" could not be fetched.',
    'Die Punkte darin bleiben erhalten. Punkte, die in keiner weiteren Liste stehen, findest du danach unter „Ohne Liste".'
        => 'The points in it are kept. Points that stand in no other list you will afterwards find under "Without list".',
    'Die Punkte der sichtbaren Listen, mit Titel und Fundzeit.'
        => 'The points of the visible lists, with title and find time.',
    'Die Punkte kommen <strong>ohne Fundzeit</strong> an — Google speichert nicht, wann ein Ort gesichert wurde. Im Zeitfilter liegen sie deshalb alle im Monat des Imports.'
        => 'The points arrive <strong>without a find time</strong> — Google does not store when a place was saved. In the time filter they therefore all sit in the month of the import.',
    'Die QR-Bibliothek fehlt auf dem Server — der Link darunter tut denselben Dienst.'
        => 'The QR library is missing on the server — the link below does the same job.',
    'Die QR-Bibliothek fehlt auf dem Server — nimm einen der beiden Wege daneben.'
        => 'The QR library is missing on the server — use one of the two ways beside it.',
    'Die Reiter: Punkte, Touren, Autolisten.'
        => 'The tabs: points, tours, autolists.',
    'Die restlichen %d zeigen'
        => 'Show the remaining %d',
    'Die Schriftgröße muss zwischen 8 und 36 liegen.'
        => 'The font size must be between 8 and 36.',
    'Die Schutzgebiete stehen in dieser Anlage nicht zur Verfügung.'
        => 'Protected areas are not available in this installation.',
    'Diese %d neu erfragen'
        => 'Ask these %d anew',
    'Diese Anlage bietet keine Schutzgebiete an.'
        => 'This installation offers no protected areas.',
    'Diese Dateien spiegeln den Stand der Karte. Wer einen Fund löscht, findet ihn beim nächsten Durchgang auch hier nicht mehr. Dafür ist der Papierkorb in der Karte da, nicht dieser Ordner.'
        => 'These files mirror the state of the map. Whoever deletes a find will not find it here after the next sweep either. The trash in the map is there for that, not this folder.',
    'Diese Datei überspringen'
        => 'Skip this file',
    'Diese E-Mail gehört bei dieser Exkursion schon zu jemand anderem.'
        => 'On this excursion, this e-mail already belongs to somebody else.',
    'Diese Exkursion gibt es nicht mehr.'
        => 'This excursion no longer exists.',
    'Diese Exkursion ist beendet — seit dem %s können keine Funde mehr dazukommen. Danke fürs Mitmachen!'
        => 'This excursion has ended — since %s no more finds can be added. Thanks for joining in!',
    'Diese Felder hat jemand anderes verändert. Dein zuletzt selbst eingegebener Stand bleibt als Anker liegen, bis du dich entscheidest — zurückholen oder übernehmen.'
        => 'Someone else changed these fields. Your last self-entered state stays put as an anchor until you decide — restore it or accept the change.',
    'Diese Freigabe gibt es nicht (mehr).'
        => 'This share does not exist (any more).',
    'Diese Freigabe gibt es nicht mehr.'
        => 'This share no longer exists.',
    'Die Seiten in der Reihenfolge, in der man ihnen begegnet. Wer etwas Bestimmtes sucht, springt über das Verzeichnis.'
        => 'The pages in the order you meet them. If you are looking for something specific, jump via the table of contents.',
    'Diese Kartenart lässt sich nicht auf Vorrat laden.'
        => 'This kind of map cannot be loaded in advance.',
    'Diese Liste gehört dir nicht (mehr).'
        => 'This list is not (or no longer) yours.',
    'Diese Liste gibt es nicht (mehr).'
        => 'This list does not exist (any more).',
    'Diese Messung löschen'
        => 'Delete this measurement',
    'Diese Methode kennt der Endpunkt nicht.'
        => 'The endpoint does not know this method.',
    'Diese Migrationen sind noch offen und werden der Reihe nach ausgeführt:'
        => 'These migrations are still open and will run in order:',
    'Diese Namen stehen in keiner Namensliste — vielleicht eine offene Bestimmung („agg.", „spec."), vielleicht ein Schreibfehler. Angekreuzt werden sie eingespielt, der Titel lässt sich später im Punktfenster nachtragen. Wegklicken, was gar nicht mitkommen soll.'
        => 'These names appear in no name list — perhaps an open determination ("agg.", "spec."), perhaps a typo. Ticked, they are imported; the title can be amended later in the point window. Untick whatever should not come along at all.',
    'Diesen Benutzer gibt es nicht.'
        => 'This user does not exist.',
    'Diesen Freund gibt es hier nicht mehr.'
        => 'This friend no longer exists here.',
    'Diesen Namen gibt es schon — das bleibt trotzdem eine eigene Tour.'
        => 'This name already exists — it still stays a tour of its own.',
    'Diesen Punkt gibt es auf dem Server nicht mehr — er wurde dort endgültig gelöscht, vermutlich von einem anderen Gerät aus. Die Änderung von hier hat damit kein Ziel mehr. Ein erneuter Versuch ändert daran nichts; ihr Inhalt steht oben, damit er nicht verloren geht.'
        => 'This point no longer exists on the server — it was permanently deleted there, probably from another device. The change from here therefore has no target any more. Trying again changes nothing about that; its content stands above so it does not get lost.',
    'Diesen Punkt gibt es nicht (mehr).'
        => 'This point does not exist (any more).',
    'Diese Orte tragen bei Google nur einen internen Verweis, aus dem sich keine Lage ableiten lässt — und raten wäre schlimmer als fehlen. Der Weg von Hand: Eintrag bei Google öffnen, mit Rechtsklick auf die Stelle die Koordinaten kopieren und hier über die Suche als neuen Punkt setzen.'
        => 'These places carry only an internal reference at Google, from which no position can be derived — and guessing would be worse than missing. The manual way: open the entry at Google, right-click the spot to copy the coordinates, and place it here via the search as a new point.',
    'Dieser Anbieter ist hier nicht eingerichtet.'
        => 'This provider is not set up here.',
    'Dieser Anbieter verbindet sich nicht über eine Zustimmungsseite.'
        => 'This provider does not connect through a consent page.',
    'dieser Browser'
        => 'this browser',
    'Dieser Browser kann nichts offline behalten.'
        => 'This browser cannot keep anything offline.',
    'Dieser Browser kennt keine Standortbestimmung.'
        => 'This browser knows no geolocation.',
    'Dieser Einladungscode ist unbekannt, abgelaufen oder schon eingelöst.'
        => 'This invitation code is unknown, expired or already redeemed.',
    'Dieser Punkt ist nicht mehr da — die Eingabe wurde nicht gespeichert. Bitte den Text herauskopieren, bevor du das Fenster schließt.'
        => 'This point is no longer there — your input was not saved. Please copy the text out before you close the window.',
    'Dieser Zugang führt zu keiner Exkursion. Vielleicht ist der QR-Code veraltet — frag die Person, die ihn dir gezeigt hat.'
        => 'This access does not lead to any excursion. Perhaps the QR code is outdated — ask the person who showed it to you.',
    'Dieser Zugang wurde mit libsodium verschlüsselt, das auf diesem Server fehlt.'
        => 'This connection was encrypted with libsodium, which is missing on this server.',
    'Dieser Zweig überschneidet sich mit einer Liste, die du hier schon teilst.'
        => 'This branch overlaps with a list you already share here.',
    'Dieses Gebiet gibt es nicht (mehr).'
        => 'This area does not exist (any more).',
    'Dieses Gerät gibt es nicht mehr.'
        => 'This device no longer exists.',
    'Diese Sprache gibt es hier nicht.'
        => 'This language does not exist here.',
    'Diese Vorlage gibt es nicht mehr.'
        => 'This preset no longer exists.',
    'Diese Wegstrecke gibt es nicht.'
        => 'This track does not exist.',
    'Diese Zeilen werden nicht eingespielt — ein falsch gesetzter Punkt ist schlimmer als ein fehlender. Die Zeilennummern zählen wie in der Tabellenkalkulation, samt Kopfzeile.'
        => 'These rows are not imported — a wrongly placed point is worse than a missing one. The row numbers count as in the spreadsheet, header row included.',
    'Diese Änderung gehört zu einem Eintrag, den dieses Gerät nur vorläufig kannte — seine endgültige Kennung ist hier nie angekommen. Sie lässt sich deshalb nicht mehr zuordnen. Der Eintrag selbst kann auf dem Server durchaus vorhanden sein.'
        => 'This change belongs to an entry this device only knew provisionally — its final id never arrived here. It can therefore no longer be matched up. The entry itself may well exist on the server.',
    'Die Sitzung ist abgelaufen. Bitte die Seite neu laden.'
        => 'The session has expired. Please reload the page.',
    'Die Spalten fehlen noch — erst Migration 0037 einspielen.'
        => 'The columns are still missing — apply migration 0037 first.',
    'Die Spalten für die Gemeinde fehlen noch. Erst Migration 0037 einspielen, dann steht dieses Werkzeug bereit.'
        => 'The columns for the municipality are still missing. Apply migration 0037 first, then this tool stands ready.',
    'In dieser Sprache zeigt dir YASAIMAPP Knöpfe, Menüs und Hinweise. Sie gilt auf allen deinen Geräten. „Automatisch" nimmt die Sprache deines Geräts.'
        => 'In this language YASAIMAPP shows you buttons, menus and hints. It applies on all your devices. "Automatisch" takes the language of your device.',
    'Was du selbst einträgst, wird nicht übersetzt: Arten, Substrat, Notizen, die Namen deiner Listen und alles andere. Schreibst du zum Beispiel „Steinpilz" als Art, steht dort auch in der englischen Ansicht „Steinpilz".'
        => 'What you enter yourself is not translated: species, substrate, notes, the names of your lists and everything else. If you write "Steinpilz" as the species, for example, "Steinpilz" stands there in the English view too.',
    'Die Standortbestimmung hat zu lange gedauert.'
        => 'Locating took too long.',
    'die Stelle liegt außerhalb'
        => 'the spot lies outside',
    'Die Suche geht gerade nicht. Versuch es gleich noch einmal.'
        => 'The search is not working right now. Try again in a moment.',
    'Die Tabelle für die Zugänge fehlt noch — Migration 0053 einspielen.'
        => 'The table for the connections is still missing — apply migration 0053.',
    'Die Tabelle für die Zugänge fehlt noch — Migration 0053 ist noch nicht eingespielt. Bis dahin lässt sich hier nichts verbinden.'
        => 'The table for the connections is still missing — migration 0053 has not been applied yet. Until then nothing can be connected here.',
    'Die Tabelle %s gibt es noch nicht — erst die Migrationen einspielen.'
        => 'The table %s does not exist yet — apply the migrations first.',
    'Die Taxonomie bleibt draußen'
        => 'The taxonomy stays out',
    'Die Taxonomietabellen rührt der Abzug nicht an, die bleiben stehen.'
        => 'The dump does not touch the taxonomy tables; those stay put.',
    'Die Tour braucht einen Namen.'
        => 'The tour needs a name.',
    'Die Touren brauchen die Migration 0071 — sie ist noch nicht eingespielt.'
        => 'The tours need migration 0071 — it has not been applied yet.',
    'Die Tour ist weg — ihre %d Punkte bleiben, sie tragen jetzt keine Tour mehr.'
        => 'The tour is gone — its %d points stay, they no longer carry a tour.',
    'Die Tour ist weg — ihr Punkt bleibt, er trägt jetzt keine Tour mehr.'
        => 'The tour is gone — its point stays, it no longer carries a tour.',
    'Die Tour „%s" steht bereit — noch ohne Punkte.'
        => 'The tour "%s" is ready — no points yet.',
    'Die Umrandung dieses Gebiets ist unlesbar.'
        => 'The outline of this area is unreadable.',
    ' Die Unterliste rückt eine Ebene auf, nichts darunter geht verloren.'
        => ' The sublist moves up one level, nothing beneath it is lost.',
    'Die Verbindung antwortet nicht.'
        => 'The connection is not responding.',
    'Die Verbindung klappt. In deiner Cloud sind %s von %s belegt.'
        => 'The connection works. %s of %s are used in your cloud.',
    'Die Verbindung klappt. Der Eimer „%s" bei %s antwortet.'
        => 'The connection works. The bucket "%s" at %s answers.',
    'Die Verbindung klappt nicht mehr. Trenne sie und verbinde dann neu.'
        => 'The connection no longer works. Disconnect it and then connect again.',
    'Die Verbindung zu %s wird gelöst. Deine Dateien dort bleiben unberührt — es geht nur der Zugang verloren, und du kannst ihn jederzeit neu herstellen.'
        => 'The connection to %s will be removed. Your files there stay untouched — only the access is lost, and you can re-establish it at any time.',
    'Die Verbindung zu pCloud ist abgerissen.'
        => 'The connection to pCloud broke off.',
    'Die Vorlagen brauchen die Migration 0089 — sie ist noch nicht eingespielt.'
        => 'Presets need migration 0089 — it has not been applied yet.',
    'Die Vorlage „%s" wird gelöscht. Fotos, die sie schon bekommen haben, behalten ihren Maßstab.'
        => 'The preset "%s" will be deleted. Photos that already got it keep their scale.',
    'Die wahre Länge der Strecke fehlt noch.'
        => 'The true length of the distance is still missing.',
    'Die wahre Länge der Strecke fehlt, oder sie ist keine Zahl.'
        => 'The true length of the line is missing, or it is not a number.',
    'Die Warteschlange konnte nicht mehr auf das Gerät geschrieben werden. Was seitdem eingegeben wurde, liegt nur im Arbeitsspeicher dieses Fensters.'
        => 'The queue could no longer be written to the device. What was entered since then lives only in the memory of this window.',
    'Die Werkzeuge: Standort und Punkt setzen.'
        => 'The tools: location and placing a point.',
    'Die Kartenwahl: Grundkarte, Ebenen, Wegstrecken.'
        => 'The map picker: base map, overlays, recorded tracks.',
    'Der Reiter „Punkte": die Punkte der sichtbaren Listen, mit Titel und Fundzeit — und darüber die Filter.'
        => 'The "Punkte" tab: the points of the visible lists, with title and find time — and the filters above them.',
    'Die Bestimmungssicherheit — vom Verdacht bis zum Mikroskop.'
        => 'The identification certainty — from a hunch to the microscope.',
    'Die Notiz.'
        => 'The note.',
    'Die Reiter: Fotos — und unter „Einordnung" Lage, Fundzeit, Listen und die Fundfelder deiner Liste.'
        => 'The tabs: photos — and under "Einordnung" position, find time, lists and the find fields of your list.',
    'Der Zeitraum — oder eine Tour aus der Liste.'
        => 'The time range — or a tour from the list.',
    'Die zwei Spalten links: Listen und Touren — die aktive regiert die Tabelle.'
        => 'The two panes on the left: lists and tours — the active one governs the table.',
    'Filter und Spalten der Tabelle.'
        => 'Filter and columns of the table.',
    'Export und Fundbericht für die Auswahl.'
        => 'Export and find report for the selection.',
    'Zeigen/Editieren: die Fläche umranden und die Zoomstufe wählen — je feiner, desto mehr Kacheln.'
        => 'Show/edit: outline the area and pick the zoom level — the finer, the more tiles.',
    'Offline-Daten: Namenslisten und Schutzgebiete, einzeln zu holen.'
        => 'Offline data: name lists and protected areas, fetched one by one.',
    'Die beiden neuen Passwörter sind nicht gleich. Bitte tippe sie noch einmal.'
        => 'The two new passwords are not the same. Please type them again.',
    'Die Zoomstufe der Offline-Karte liegt zwischen 13 und 20.'
        => 'The offline map\'s zoom level lies between 13 and 20.',
    'Die Zoomstufe — je feiner, desto mehr Kacheln.'
        => 'The zoom level — the finer, the more tiles.',
    'Die Änderung von %s wird durch dein Original ersetzt — auch auf deren Geräten.'
        => 'The change by %s is replaced with your original — on their devices too.',
    'Die überschriebenen Änderungen liegen auf dem Server — gerade nicht erreichbar.'
        => 'The overwritten changes lie on the server — not reachable right now.',
    'Direkter Link:'
        => 'Direct link:',
    'DO'
        => 'TH',
    'Doppelfüßer'
        => 'Millipedes',
    'Drei Ecken müssen bleiben.'
        => 'Three corners must remain.',
    'Drei Ecken müssen bleiben. Zum Auflösen „Umriss entfernen".'
        => 'Three corners must remain. To dissolve the area, use "Remove outline".',
    'Drei Wege führen zum selben Ergebnis, und welcher passt, hängt davon ab, wo du stehst:'
        => 'Three ways lead to the same result, and which one fits depends on where you are:',
    'Du bist in dieser Freigabe nicht dabei.'
        => 'You are not in this share.',
    'Du bist in dieser Freigabe nicht mehr dabei.'
        => 'You are no longer in this share.',
    'Du bist kein Mitglied dieser Freigabe.'
        => 'You are not a member of this share.',
    'Du bist schon der Leiter.'
        => 'You are the leader already.',
    'Du darfst %s.'
        => 'You may %s.',
    'Du darfst diese Punkte nur ansehen.'
        => 'You may only view these points.',
    'Du hast noch keine Freunde — erst anfragen, dann aufnehmen.'
        => 'You have no friends yet — request first, then admit.',
    'Du hast noch keine Listen.'
        => 'You have no lists yet.',
    'Du hast noch kein Gebiet. Eines legst du unter „Offline" an — dann kannst du es hier anbieten.'
        => 'You have no area yet. Create one under "Offline" — then you can offer it here.',
    'Du hast schon eine Freigabe mit diesem Namen.'
        => 'You already have a share with this name.',
    'Dunkel'
        => 'Dark',
    'Durch Komma Getrenntes gilt als oder.'
        => 'Comma-separated terms count as or.',
    'Du trittst als Mitleiter von „%s“ aus — bearbeiten und den QR-Code zeigen kann dann nur noch der Leiter. Zurück holt dich nur er.'
        => 'You step out as co-leader of "%s" — only the leader can then edit and show the QR code. Only he can bring you back.',
    'E-Mail (freiwillig)'
        => 'E-mail (optional)',
    'E-Mail-Adresse'
        => 'E-mail address',
    'E-Mail-Adresse (damit meldest du dich an)'
        => 'E-mail address (you sign in with it)',
    'Ebene und Rand (100–300) werden gebraucht.'
        => 'Layer and margin (100–300) are needed.',
    'Ecke eingefügt'
        => 'Corner inserted',
    'Ecke für Ecke antippen, dann „Fertig".'
        => 'Tap corner by corner, then "Done".',
    'Ecke für Ecke antippen, dann „Fertig" — es geht zurück in die Verwaltung.'
        => 'Tap corner by corner, then "Done" — you go back to the administration.',
    'Ecke für Ecke antippen, dann „Fertig" — es geht zurück ins Organisieren.'
        => 'Tap corner by corner, then "Done" — you go back to Organise.',
    'Ecke gelöscht'
        => 'Corner deleted',
    'Ecke löschen'
        => 'Delete corner',
    'Ecke verschoben'
        => 'Corner moved',
    'Ecke zurück'
        => 'Corner back',
    'Egel'
        => 'Leeches',
    'Eichbild'
        => 'Calibration image',
    'Eichbild wird in der Cloud abgelegt …'
        => 'Filing the calibration image in the cloud …',
    'Eigene'
        => 'Own',
    'eigene Eichung'
        => 'own calibration',
    'Eigene Exkursionen'
        => 'Own excursions',
    'Eigene Felder'
        => 'Custom fields',
    'Eigene Freigaben'
        => 'Own shares',
    'eigene Liste mit genau dieser Auswahl — sie wächst nur, wenn du selbst nachlegst'
        => 'its own list with exactly this selection — it only grows when you add to it yourself',
    'Deine Cloud'
        => 'Your cloud',
    'Eimer (Bucket)'
        => 'Bucket',
    'Ein Abzug der eigenen Tabellen als SQL — um örtlich denselben Stand zu haben wie hier. Die Datei wird unmittelbar heruntergeladen und liegt zu keinem Zeitpunkt auf dem Server.'
        => 'A dump of the own tables as SQL — to have the same state locally as here. The file is downloaded immediately and at no point lies on the server.',
    'Eine Arbeitsgruppe fasst Freunde zusammen, damit du mit allen auf einmal teilen kannst statt mit jedem einzeln.'
        => 'A working group bundles friends so you can share with all of them at once instead of one by one.',
    'Eine Autoliste führt ihren Inhalt über ihren Filter — zusammenführen lässt sie sich nicht.'
        => 'An auto list keeps its content through its filter — it cannot be merged.',
    'Eine davon betraf einen Punkt, der inzwischen im Papierkorb liegt — sie ist dort mit dabei.'
        => 'One of them concerned a point that now lies in the trash — the change went there with it.',
    'eine davon ohne messbare Größe'
        => 'one of them without a measurable size',
    'Eine der Farben ist keine.'
        => 'One of the colors is not a color.',
    'Eine Exkursion ist ein gemeinsamer Ausflug: du legst sie an, zeigst den QR-Code, und die Gäste kartieren mit — <strong>ohne eigenes Konto</strong>. Sie geben Vorname und Nachname an, eine E-Mail-Adresse ist freiwillig.'
        => 'An excursion is a joint outing: you create it, show the QR code, and the guests map along — <strong>without an account of their own</strong>. They give first and last name; an e-mail address is optional.',
    'Eine Exkursion sammelt Funde vieler Leute in einem Zeitfenster — auch von Gästen ohne Konto: Sie scannen den QR-Code, geben ihren Namen an und setzen los. Du bist Leiter und darfst hinterher alles bearbeiten, was auf ihr entstanden ist.'
        => 'An excursion collects finds of many people within a time window — including guests without an account: they scan the QR code, give their name and set off. You are the leader and may afterwards edit everything that came into being on it.',
    'Eine Freigabe sammelt Listen mehrerer Leute. Jeder hängt eigene Listen hinein und nimmt sie wieder heraus; <strong>Punkte gehören immer dem, der sie gesetzt hat</strong>. Was darin hängt, sehen alle Mitglieder auf ihrer Karte.'
        => 'A share collects lists of several people. Everyone hangs their own lists in and takes them out again; <strong>points always belong to whoever placed them</strong>. What hangs inside, all members see on their map.',
    'Eine Freigabe sammelt Listen mehrerer Leute. Jeder hängt eigene Listen hinein und nimmt sie wieder heraus — Punkte gehören immer dem, der sie gesetzt hat. Was hier hängt, sehen alle Mitglieder auf ihrer Karte.'
        => 'A share collects lists from several people. Everyone hangs in their own lists and takes them out again — points always belong to whoever set them. What hangs here is visible to all members on their map.',
    'Eine Linie bzw. Fläche bleibt draussen — das sind Wege und Umrisse, keine Funde.'
        => 'One line or area stays out — those are paths and outlines, not finds.',
    'Eine Liste, die Punkte hält: Form und Farbe stehen davor.'
        => 'A list that holds points: shape and color stand in front of it.',
    'Eine Liste ist ein Ordner für Punkte, und sie darf Unterlisten haben. <strong>Eine Liste mit Unterlisten ist eine Überschrift</strong> und hält selbst nichts — bekommt eine Liste ihre erste Unterliste, wandern ihre Punkte hinunter. Farbe und Form der Marker kommen deshalb immer von der untersten Ebene.'
        => 'A list is a folder for points, and it may have sublists. <strong>A list with sublists is a heading</strong> and holds nothing itself — when a list gets its first sublist, its points move down. Color and shape of the markers therefore always come from the lowest level.',
    'Eine Liste kann nicht in sich selbst aufgehen.'
        => 'A list cannot be absorbed into itself.',
    'Eine Messung hat die falsche Punktzahl.'
        => 'A measurement has the wrong number of points.',
    'Eine Messung hat keine Länge.'
        => 'A measurement has no length.',
    'Eine Messung ist keine.'
        => 'One of the measurements is not a measurement.',
    'Einen Marker kannst du <strong>ziehen</strong>, wenn sein Fenster offen ist. Die neue Lage steht dann im Koordinatenfeld und gilt erst mit „Speichern" — wer das Fenster schließt, findet den Punkt an seiner alten Stelle.'
        => 'You can <strong>drag</strong> a marker while its window is open. The new position then stands in the coordinate field and only takes effect with "Save" — whoever closes the window finds the point in its old place.',
    'Einen Punkt setzen'
        => 'Placing a point',
    'Eine Ortsmarke würde angelegt'
        => 'One placemark would be created',
    ' Einer davon ist ein fremder Fund aus einer Freigabe — er kommt mit, mit dem Namen seines Finders.'
        => ' One of them is somebody else\'s find from a share — it comes along, with its finder\'s name.',
    ' Einer davon ist ein fremder Fund — er steht mit dem Namen seines Finders darin.'
        => ' One of them is somebody else\'s find — it appears with its finder\'s name.',
    'Eine Tabelle mit %d Spalten und %d Zeilen. Welche Spalte was bedeutet, ist hier geraten — bitte prüfen und bei Bedarf umstellen; alles ohne Zuordnung wandert als „Spalte: Wert" in die Notiz.'
        => 'A table with %d columns and %d rows. What each column means is guessed here — please check and rearrange if needed; everything without an assignment goes into the note as "column: value".',
    'Eine Textzeile mit ihrem Zeichen für <em>enthält</em> / <em>enthält nicht</em>.'
        => 'A text line with its sign for <em>contains</em> / <em>does not contain</em>.',
    'Eine Tour ist ein Name, den jeder neue Punkt mitbekommt — kein eigener Datensatz, sondern ein Etikett. Vorgeschlagen wird „Tagestour"; wer sie umbenennt, benennt damit alle Punkte des Ausflugs.'
        => 'A tour is a name that every new point receives — not a record of its own, but a label. Suggested is "Tagestour"; whoever renames it thereby renames all points of the outing.',
    'Eine Vorgabe je Zeile — das Fotofenster schlägt sie beim Benennen der Bilder vor.'
        => 'One suggestion per line — the photo window offers them when naming pictures.',
    'Eine Vorlage braucht einen Namen.'
        => 'A preset needs a name.',
    'Eine Vorlage braucht einen Namen und eine brauchbare Eichung.'
        => 'A preset needs a name and a usable calibration.',
    'Eine Vorlage merkt sich, wie viele Mikrometer ein Pixel auf deinen Mikrofotos misst. Du legst eine für jedes Objektiv an, zum Beispiel „10x" und „40x". Gibst du einem Foto die passende Vorlage, misst du darin sofort in Mikrometern.'
        => 'A preset remembers how many micrometres one pixel measures on your micro photos. You make one for each objective, for example "10x" and "40x". Give a photo the matching preset, and you measure in micrometres in it straight away.',
    'Für eine neue Vorlage fotografierst du dein Objektmikrometer mit dem Objektiv, für das sie gelten soll. YASAIMAPP legt das Foto in deiner Cloud (%s) im Ordner „Kalibrierung" ab. Dann kannst du die Vorlage später am selben Bild nachmessen.'
        => 'For a new preset you photograph your stage micrometer with the objective it is meant for. YASAIMAPP puts the photo into your cloud (%s), in the folder "Kalibrierung". Then you can re-measure the preset on the same picture later.',
    'Neue Vorlage …'
        => 'New preset …',
    'Maßstab-Vorlagen kannst du nur mit einer verbundenen Cloud nutzen. Im Moment geht das mit %s. Verbinden kannst du sie im Reiter Cloud.'
        => 'You can use scale presets only with a connected cloud. Right now that works with %s. You can connect it in the Cloud tab.',
    'Maßstab-Vorlagen kannst du nur mit einer verbundenen Cloud nutzen. Verbinden kannst du sie im Reiter Cloud.'
        => 'You can use scale presets only with a connected cloud. You can connect it in the Cloud tab.',
    'Speicherst du bei einem Gerät eine Vorlage unter einem Namen, den es dort schon gibt, ersetzt sie die alte.'
        => 'If you save a preset on a device under a name that already exists there, it replaces the old one.',
    // Devices for the presets (migration 0183, 02.11.2026).
    'Hast du mehrere Mikroskope oder eine Stereolupe, legst du für jedes ein eigenes Gerät an. Jedes Gerät bekommt seine eigenen Vorlagen. Beim Messen wählst du dann zuerst das Gerät und danach das Objektiv.'
        => 'If you have several microscopes or a stereo microscope, make a device for each of them. Every device gets its own presets. When you measure, you first choose the device and then the objective.',
    'Gerät'
        => 'Device',
    'Objektiv löschen'
        => 'Delete objective',
    'Objektiv umbenennen'
        => 'Rename objective',
    'Wie soll das Objektiv heißen?'
        => 'What should the objective be called?',
    'Wie soll das Gerät heißen?'
        => 'What should the device be called?',
    '„%s" ist gelöscht.'
        => '“%s” is deleted.',
    'Das hat nicht geklappt.'
        => 'That did not work.',
    'Geräte und Objektive'
        => 'Devices and objectives',
    'Pilzgruppe' => 'Fungus group',
    'Basidiomyceten' => 'Basidiomycetes',
    'Hymenium' => 'Hymenium',
    'Excipulum und Rand' => 'Excipulum and margin',
    'Hut' => 'Cap',
    'Stiel' => 'Stem',
    'Hyphen' => 'Hyphae',
    'Porlinge und Rindenpilze' => 'Polypores and crust fungi',
    'Bauchpilze' => 'Gasteroid fungi',
    'Aufgenommen mit YASAIMAPP am %s (%d × %d Pixel).' => 'Taken with YASAIMAPP on %s (%d × %d pixels).',
    'Stapeln prüfen' => 'Checking the stacking',
    'Einzelbilder jedes Stapels als Datei speichern' => 'Save the single frames of every stack as a file',
    'Nach jedem Stapel lädt der Browser eine Datei „Fokusserie …" herunter. Sie enthält alle Einzelbilder und das Ergebnis.' => 'After every stack the browser downloads a file “Fokusserie …”. It holds all single frames and the result.',
    'Fokusserie %s' => 'Focus series %s',
    'Kamerabild spiegeln' => 'Mirror the camera picture',
    'Schiebst du den Objekttisch, soll das Bild in dieselbe Richtung wandern wie im Okular. Wandert es falsch herum, setze hier einen Haken.' => 'When you move the stage, the picture should travel the same way as in the eyepiece. If it travels the wrong way, tick a box here.',
    'Links und rechts tauschen' => 'Swap left and right',
    'Oben und unten tauschen' => 'Swap top and bottom',
    'Ansicht'
        => 'View',
    'Die Kamera läuft weiter.'
        => 'The camera keeps running.',
    'Zurück zum Mikroskop'
        => 'Back to the microscope',
    'Art des Fundes: %s'
        => 'Species of the find: %s',
    'Medium'
        => 'Medium',
    'Weniger'
        => 'Fewer',
    'Wasser'
        => 'Water',
    'Ammoniak'
        => 'Ammonia',
    'Kalilauge 3 %'
        => 'Potassium hydroxide 3 %',
    'Salzsäure 3 %'
        => 'Hydrochloric acid 3 %',
    'Lactoglycerol'
        => 'Lactoglycerol',
    'Melzers Reagenz'
        => 'Melzer’s reagent',
    'Lugolsche Lösung'
        => 'Lugol’s solution',
    'Baral-Lösung (Lugol nach Baral)'
        => 'Baral’s solution (Lugol after Baral)',
    'Baumwollblau'
        => 'Cotton blue',
    'Kongorot in Ammoniak'
        => 'Congo red in ammonia',
    'Kongorot in Wasser'
        => 'Congo red in water',
    'Brillantkresylblau'
        => 'Brilliant cresyl blue',
    'Chlorazolschwarz E'
        => 'Chlorazol black E',
    'Sudan IV'
        => 'Sudan IV',
    'Phloxin B'
        => 'Phloxine B',
    'Methylorange'
        => 'Methyl orange',
    'Toluidinblau'
        => 'Toluidine blue',
    'Methylenblau'
        => 'Methylene blue',
    'Methylviolett'
        => 'Methyl violet',
    'Patentblau'
        => 'Patent blue',
    'Carbolfuchsin'
        => 'Carbol fuchsin',
    'Malachitgrün'
        => 'Malachite green',
    'Trypanblau'
        => 'Trypan blue',
    'Erythrosin'
        => 'Erythrosine',
    'Karminessigsäure'
        => 'Acetocarmine',
    'China-Tusche'
        => 'India ink',
    'Eisenbeize'
        => 'Iron mordant',
    'Kalilauge 40 %'
        => 'Potassium hydroxide 40 %',
    'Natronlauge 40 %'
        => 'Sodium hydroxide 40 %',
    'Natriumhypochlorit 12,5 %'
        => 'Sodium hypochlorite 12.5 %',
    'Lactophenol'
        => 'Lactophenol',
    'Chloralhydrat'
        => 'Chloral hydrate',
    'L4 (nach Clémençon)'
        => 'L4 (after Clémençon)',
    'GSM (nach Clémençon)'
        => 'GSM (after Clémençon)',
    'Glamalc'
        => 'Glamalc',
    'Ethanolglycerin'
        => 'Ethanol glycerol',
    'Immersionsöl'
        => 'Immersion oil',
    'Sulfovanillin'
        => 'Sulphovanillin',
    'Kongorot in SDS'
        => 'Congo red in SDS',
    'Giemsa-Lösung'
        => 'Giemsa solution',
    'Färbt Lactiferen (Milchsafthyphen), etwa bei Lactarius, Lentinellus und Fistulina, und Pseudozystiden.'
        => 'Stains laticifers (latex hyphae), for example in Lactarius, Lentinellus and Fistulina, and pseudocystidia.',
    'Nur frisch anmischen: Es hält wenige Stunden.'
        => 'Mix it fresh only: it keeps a few hours.',
    'Färbt die Zystiden: Makrozystiden werden (blau)grau, das Eiweiß im Plasma kräftig rosa.'
        => 'Stains the cystidia: macrocystidia turn (blue-)grey, the protein in the cytoplasm a strong pink.',
    'Giftig und stark ätzend (Schwefelsäure).'
        => 'Toxic and strongly corrosive (sulphuric acid).',
    'Untersuchungsmedium und Aufheller, besonders beim Erwärmen.'
        => 'Mounting medium and clearing agent, especially when warmed.',
    'Giftig und krebserregend. Falsch dosiert lebensgefährlich.'
        => 'Toxic and carcinogenic. Wrongly dosed, life-threatening.',
    'Untersuchungsmedium.'
        => 'Mounting medium.',
    'Giftig, ätzend und erbgutverändernd.'
        => 'Toxic, corrosive and mutagenic.',
    'Untersuchungsmedium nach Clémençon. Weicht Exsikkate auf. Enthält KOH.'
        => 'Mounting medium after Clémençon. Softens dried material. Contains KOH.',
    'Giftig und ätzend. Greift Glas an – auch das Objektiv.'
        => 'Toxic and corrosive. Attacks glass – the objective too.',
    'Untersuchungsmedium nach Clémençon aus Glycerin, Natronlauge und Methylcellosolve. Weicht Exsikkate auf, ohne Strukturen zu zerstören.'
        => 'Mounting medium after Clémençon of glycerol, sodium hydroxide and methyl cellosolve. Softens dried material without destroying structures.',
    'Giftig und stark ätzend.'
        => 'Toxic and strongly corrosive.',
    'Untersuchungsmedium. Weicht Exsikkate auf, ohne Strukturen zu zerstören.'
        => 'Mounting medium. Softens dried material without destroying structures.',
    'Untersuchungsmedium. Weicht Exsikkate auf.'
        => 'Mounting medium. Softens dried material.',
    'Für das Ölobjektiv, meist 100×: ein Tropfen zwischen Deckglas und Objektiv.'
        => 'For the oil objective, mostly 100×: a drop between cover glass and objective.',
    'Auch als Medium für wasserabweisende Sporen, etwa bei Bovista und Lycoperdon.'
        => 'Also as a medium for water-repellent spores, for example in Bovista and Lycoperdon.',
    'Zeigt lösliche Wände bei Porlingen und Rindenpilzen (Aphyllophorales).'
        => 'Shows soluble walls in polypores and corticioid fungi (Aphyllophorales).',
    'Färbt die Spinulae einiger Hymenochaetaceae, etwa bei Phellinus und Onnia.'
        => 'Stains the spinulae of some Hymenochaetaceae, for example in Phellinus and Onnia.',
    'Weicht Exsikkate auf, kann aber Strukturen zerstören.'
        => 'Softens dried material, but can destroy structures.',
    'Aufheller: bleicht dunkles Gewebe.'
        => 'Clearing agent: bleaches dark tissue.',
    'Stark ätzend.'
        => 'Strongly corrosive.',
    'Fällt in KOH aus – nicht mischen.'
        => 'Precipitates in KOH – do not mix.',
    'Das neutrale Medium: Farben und Pigmente bleiben, wie sie sind.'
        => 'The neutral medium: colours and pigments stay as they are.',
    'Sporen misst man meist in Wasser oder in KOH 3 %.'
        => 'Spores are mostly measured in water or in KOH 3 %.',
    'Weicht Exsikkate auf und macht Gewebe durchsichtig. Frisches Gewebe lässt sich danach gut quetschen.'
        => 'Softens dried material and clears tissue. Fresh tissue then squashes well.',
    'Kann Feines zerstören, etwa die Kristallschöpfe an Zystiden.'
        => 'Can destroy fine structures, such as the crystal tufts on cystidia.',
    'Das Ornament der braunen Sporen tritt deutlicher hervor.'
        => 'The ornament of the brown spores stands out more clearly.',
    'Vorbehandlung für die Iodprobe: Wird die Ascusspitze erst nach KOH blau, ist sie hemiamyloid.'
        => 'Pretreatment for the iodine test: if the ascus tip only turns blue after KOH, it is hemiamyloid.',
    'Ätzend. Greift Glas an – auch das Objektiv.'
        => 'Corrosive. Attacks glass – the objective too.',
    'Starkes Iodreagenz mit Chloralhydrat. Amyloid heißt graublau bis schwarzviolett, dextrinoid braun bis weinrot.'
        => 'Strong iodine reagent with chloral hydrate. Amyloid means grey-blue to black-violet, dextrinoid brown to wine-red.',
    'Färbt das Sporenornament – Warzen, Grate, Netz –, so wie es die Schlüssel beschreiben. Ebenso bei Lactarius.'
        => 'Stains the spore ornament – warts, ridges, network – as the keys describe it. Likewise in Lactarius.',
    'Färbt Ascusspitze und Paraphysenspitzen.'
        => 'Stains the ascus tip and the paraphysis tips.',
    'Eine hemiamyloide Reaktion kann darin verborgen bleiben – dafür Lugol nehmen.'
        => 'A hemiamyloid reaction can stay hidden in it – use Lugol for that.',
    'Giftig und krebserregend (Chloralhydrat).'
        => 'Toxic and carcinogenic (chloral hydrate).',
    'Schwaches Iodreagenz. Amyloid heißt graublau bis schwarzviolett, dextrinoid braun bis weinrot.'
        => 'Weak iodine reagent. Amyloid means grey-blue to black-violet, dextrinoid brown to wine-red.',
    'Färbt das Sporenornament.'
        => 'Stains the spore ornament.',
    'Ohne KOH blau: euamyloid. Erst nach KOH blau: hemiamyloid.'
        => 'Blue without KOH: euamyloid. Blue only after KOH: hemiamyloid.',
    'Iodreagenz mittlerer Stärke. Amyloid heißt graublau bis schwarzviolett, dextrinoid braun bis weinrot.'
        => 'Iodine reagent of medium strength. Amyloid means grey-blue to black-violet, dextrinoid brown to wine-red.',
    'Färbt das Zellplasma, bei Erwärmen auch Wände und Ornamente.'
        => 'Stains the cytoplasm, when warmed also walls and ornaments.',
    'Cyanophile Sporen färben sich blau, etwa bei Boletus, Cystolepiota, Hygrophoropsis, Lepiota, Lepista und Rhodocybe.'
        => 'Cyanophilic spores turn blue, for example in Boletus, Cystolepiota, Hygrophoropsis, Lepiota, Lepista and Rhodocybe.',
    'Färbt die Gloeozystiden.'
        => 'Stains the gloeocystidia.',
    'Färbt die Zellwände rot: Hyphen, Schnallen, Basidien und Zystiden heben sich ab.'
        => 'Stains the cell walls red: hyphae, clamps, basidia and cystidia stand out.',
    'Hält etwa drei Monate. Mit destilliertem Wasser lässt es sich auffrischen.'
        => 'Keeps about three months. It can be refreshed with distilled water.',
    'Giftig und krebserregend.'
        => 'Toxic and carcinogenic.',
    'Mit Ammoniak lässt es sich auffrischen.'
        => 'It can be refreshed with ammonia.',
    'Weicht Exsikkate auf, 2- bis 3-prozentig verdünnt. Kann Strukturen zerstören.'
        => 'Softens dried material, diluted to 2 to 3 per cent. Can destroy structures.',
    'Färbt Chrysozystiden, etwa bei Hypholoma, Pholiota und Stropharia.'
        => 'Stains chrysocystidia, for example in Hypholoma, Pholiota and Stropharia.',
    'Ätzend. Die Dämpfe reizen die Atemwege.'
        => 'Corrosive. The fumes irritate the airways.',
    'Nach Carbolfuchsin: Die Säure entfärbt das Präparat, nur die Inkrustationen der Primordialhyphen bleiben rot.'
        => 'After carbol fuchsin: the acid decolours the mount, only the encrustations of the primordial hyphae stay red.',
    'Ätzend.'
        => 'Corrosive.',
    'Untersuchungsmedium, das kaum eintrocknet – gut für Präparate, die liegen bleiben.'
        => 'Mounting medium that hardly dries out – good for slides that are kept.',
    'Nicht mit Kongorot mischen: Es fällt aus.'
        => 'Do not mix with Congo red: it precipitates.',
    'Metachromatische Sporen färben sich rötlich violett, etwa bei Macrolepiota, Leucocoprinus und Leucoagaricus.'
        => 'Metachromatic spores turn reddish violet, for example in Macrolepiota, Leucocoprinus and Leucoagaricus.',
    'Färbt das Zellplasma.'
        => 'Stains the cytoplasm.',
    'In Lactoglycerin: Vakuolen der Paraphysen (Mollisia) und die Sporenkörperchen von Orbilia.'
        => 'In lactoglycerol: the vacuoles of the paraphyses (Mollisia) and the spore bodies of Orbilia.',
    'Färbt die Zellwände (Chitin) schwarz. So findet man Pilze auch in fremdem Gewebe, etwa in Pflanzen.'
        => 'Stains the cell walls (chitin) black. That way fungi are found in other tissue too, such as plants.',
    'Krebserregend.'
        => 'Carcinogenic.',
    'Färbt nur Fette: Öltropfen in Sporen und Hyphen werden rot.'
        => 'Stains only fats: oil drops in spores and hyphae turn red.',
    'Ätzend, erbgutverändernd.'
        => 'Corrosive, mutagenic.',
    'Färbt das Zellplasma rot. Hilft, reife von unreifen Sporen zu unterscheiden.'
        => 'Stains the cytoplasm red. Helps to tell ripe spores from unripe ones.',
    'Farbstoff, auch als pH-Anzeiger.'
        => 'Dye, also as a pH indicator.',
    'Giftig.'
        => 'Toxic.',
    'Färbt gelatinöses Gewebe, etwa bei Lactarius, und Oleiferen (ölhaltige Hyphen).'
        => 'Stains gelatinous tissue, for example in Lactarius, and oleiferous hyphae.',
    'Färbt die Zellkerne. So findet man Pilze auch in fremdem Gewebe.'
        => 'Stains the nuclei. That way fungi are found in other tissue too.',
    'Farbstoff für Wände und Plasma.'
        => 'Dye for walls and cytoplasm.',
    'Färbt Inkrustationen.'
        => 'Stains encrustations.',
    'Färbt die Primordialhyphen. Danach mit verdünnter Salzsäure entfärben: Nur ihre Inkrustationen bleiben rot.'
        => 'Stains the primordial hyphae. Then decolour with dilute hydrochloric acid: only their encrustations stay red.',
    'Ätzend und krebserregend.'
        => 'Corrosive and carcinogenic.',
    'Farbstoff.'
        => 'Dye.',
    'Färbt die Zellwände blau.'
        => 'Stains the cell walls blue.',
    'Färbt das Zellplasma rot.'
        => 'Stains the cytoplasm red.',
    'Färbt die Zellkerne.'
        => 'Stains the nuclei.',
    'Mit Eisenbeize: siderophile Körnchen in den Basidien, etwa bei Lyophyllum, Tephrocybe, Calocybe und Nyctalis.'
        => 'With iron mordant: siderophilous granules in the basidia, for example in Lyophyllum, Tephrocybe, Calocybe and Nyctalis.',
    'Mit Karminessigsäure: siderophile Körnchen in den Basidien, etwa bei Lyophyllum, Tephrocybe, Calocybe und Nyctalis. Aufwendig und für die Bestimmung nur von geringem Gewicht.'
        => 'With acetocarmine: siderophilous granules in the basidia, for example in Lyophyllum, Tephrocybe, Calocybe and Nyctalis. Laborious and of little weight for identification.',
    'Macht Schleimhüllen sichtbar: Der Hintergrund wird dunkel, die Hülle um Sporen oder Hyphen bleibt hell.'
        => 'Shows gelatinous sheaths: the background turns dark, the sheath round spores or hyphae stays light.',
    'Ascomyceten'
        => 'Ascomycetes',
    'Vorsicht'
        => 'Caution',
    'Das Bild ist zu hell. Dreh die Lampe etwas zurück.'
        => 'The picture is too bright. Turn the lamp down a little.',
    'Ein neues Gerät oder Objektiv legst du mit dem Maßstab-Zeichen neben „Objektiv" an.'
        => 'You create a new device or objective with the scale symbol beside “Objective”.',
    'Noch kein Gerät. Es entsteht beim ersten Eichen.'
        => 'No device yet. It is created with the first calibration.',
    'Noch keine Objektive für dieses Gerät.'
        => 'No objectives for this device yet.',
    'Die Eichung von „%s" am Gerät „%s" wird gelöscht. Fotos, die sie schon bekommen haben, behalten ihren Maßstab.'
        => 'The calibration of “%s” on the device “%s” will be deleted. Photos that already got it keep their scale.',
    'Die Eichung von „%s" wird gelöscht. Fotos, die sie schon bekommen haben, behalten ihren Maßstab.'
        => 'The calibration of “%s” will be deleted. Photos that already got it keep their scale.',
    'Das Gerät „%s" und sein Objektiv „%s" werden gelöscht. Fotos, die schon einen Maßstab davon bekommen haben, behalten ihn.'
        => 'The device “%s” and its objective “%s” will be deleted. Photos that already got a scale from it keep it.',
    'Das Gerät „%s" und seine %d Objektive werden gelöscht. Fotos, die schon einen Maßstab davon bekommen haben, behalten ihn.'
        => 'The device “%s” and its %d objectives will be deleted. Photos that already got a scale from them keep it.',
    'Für welches Gerät eichst du?'
        => 'Which device are you calibrating for?',
    'Name des neuen Geräts'
        => 'Name of the new device',
    'Gib dem neuen Gerät einen Namen.'
        => 'Give the new device a name.',
    'Das Gerät „%s" gibt es schon. Wähle es in der Liste aus.'
        => 'The device “%s” already exists. Choose it in the list.',
    'Mikroskop'
        => 'Microscope',
    'Neues Gerät …'
        => 'New device …',
    'Neues Gerät'
        => 'New device',
    'z. B. Reisemikroskop'
        => 'e.g. travel microscope',
    'Gerät umbenennen'
        => 'Rename device',
    'Gerät löschen'
        => 'Delete device',
    'Gerät löschen?'
        => 'Delete device?',
    'Das Gerät „%s" wird gelöscht.'
        => 'The device "%s" will be deleted.',
    'Das Gerät „%s" wird mit seinen %d Vorlagen gelöscht. Fotos, die eine davon schon bekommen haben, behalten ihren Maßstab.'
        => 'The device "%s" will be deleted with its %d presets. Photos that already got one of them keep their scale.',
    'Noch keine Vorlagen für dieses Gerät.'
        => 'No presets for this device yet.',
    'Neue Vorlage für „%s"'
        => 'New preset for "%s"',
    'Gerät angelegt.'
        => 'Device created.',
    'Gerät umbenannt.'
        => 'Device renamed.',
    'Gerät gelöscht. Fotos, die eine seiner Vorlagen schon bekommen haben, behalten ihren Maßstab.'
        => 'Device deleted. Photos that already got one of its presets keep their scale.',
    'Ein Gerät braucht einen Namen.'
        => 'A device needs a name.',
    'Ein Gerät mit diesem Namen gibt es schon.'
        => 'A device with this name already exists.',
    'Die Geräte brauchen die Migration 0183 — sie ist noch nicht eingespielt.'
        => 'Devices need migration 0183 — it has not been applied yet.',
    'Deine Vorlagen'
        => 'Your presets',
    'Bei „Eichbild" steht die Größe des Fotos, mit dem du geeicht hast. Hat ein Foto eine andere Größe, warnt dich YASAIMAPP. Es stammt dann vielleicht von einer anderen Kamera, und die Vorlage misst falsch.'
        => 'Under "Calibration image" stands the size of the photo you calibrated with. If a photo has a different size, YASAIMAPP warns you. It may come from another camera then, and the preset measures wrong.',
    'Eine Vorlage mit diesem Namen gibt es schon.'
        => 'A preset with this name already exists.',
    'Eine Änderung wurde nicht gespeichert'
        => 'One change was not saved',
    'Eine Überschrift mit ihren Unterlisten — das Dreieck klappt sie zu.'
        => 'A heading with its sublists — the triangle folds them away.',
    'Zu dieser Vorlage gibt es kein Eichbild. Du kannst nur den Namen ändern. Oder du wählst ein Eichbild und misst neu.'
        => 'There is no calibration image for this preset. You can only change the name. Or you choose a calibration image and measure anew.',
    'Eichbild wählen …'
        => 'Choose a calibration image …',
    'Anderes Bild …'
        => 'Another image …',
    'Ein Foto wurde abgelehnt: %s'
        => 'A photo was rejected: %s',
    'Ein Fund holt sein Schutzgebiet beim Anlegen selbst. Hier wird es für die nachgetragen, die daran vorbeikamen: importierte Funde und solche, die erst später in eine Artenliste einsortiert wurden. Gefragt wird beim Bundesamt für Naturschutz, in Häppchen von %d Sekunden. Ein Fund kostet drei Anfragen — je Gebietsart eine —, und zwischen allen liegt eine Pause: Der Dienst gehört jemand anderem, also gehen gut anderthalb Anfragen je Sekunde hinaus und nicht so viele wie möglich. Rechne mit etwa zwei Sekunden je Fund. Der Lauf darf jederzeit unterbrochen und wieder gestartet werden; zum Anhalten genügt es, die Seite zu verlassen.'
        => 'A find fetches its protected area itself on creation. Here it is backfilled for those that got past that: imported finds and ones only sorted into a species list later. The Federal Agency for Nature Conservation is asked, in chunks of %d seconds. A find costs three requests — one per area type —, and a pause lies between all of them: the service belongs to somebody else, so a good one and a half requests per second go out, not as many as possible. Expect about two seconds per find. The run may be interrupted and restarted at any time; to stop, leaving the page suffices.',
    'Ein Fund kommt in den Bericht.'
        => 'One find goes into the report.',
    'Ein Fund würde angelegt'
        => 'One find would be created',
    'Jedes Schutzgebiet zählt beim Bundesamt für Naturschutz zu genau einem Bundesland und kommt mit diesem vollständig, auch mit dem Teil jenseits der Grenze. Hessen bringt also seine Gebiete ganz mit, aber nicht die rheinland-pfälzischen, die nach Hessen hineinragen. Wer nahe einer Landesgrenze unterwegs ist, lädt deshalb beide Länder.'
        => 'At the Federal Agency for Nature Conservation every protected area counts towards exactly one federal state and comes complete with it, including the part beyond the border. Hesse therefore brings its own areas whole, but not the Rhineland-Palatinate ones that reach into Hesse. Whoever is out near a state border therefore loads both states.',
    'Eingehängt: „%s".'
        => 'Mounted: "%s".',
    'Eingehängte Listen'
        => 'Hung-in lists',
    'eingeladen von %s'
        => 'invited by %s',
    'eingespielt'
        => 'imported',
    'Eingespielt'
        => 'Applied',
    'Einheit'
        => 'Unit',
    'Übliche Längen'
        => 'Usual lengths',
    'Euro-Münze im Bild – die Strecke quer über die ganze Münze ziehen:'
        => 'A euro coin in the picture – draw the line across the whole coin:',
    'Euro-Münzen'
        => 'Euro coins',
    '%s mm Durchmesser'
        => '%s mm across',
    'Eigene Gegenstände:'
        => 'Your own things:',
    'Eigene Gegenstände'
        => 'Your own things',
    'Diese Länge merken'
        => 'Remember this length',
    '„%s" vergessen'
        => 'Forget “%s”',
    '„%s" vergessen?'
        => 'Forget “%s”?',
    'Der Knopf verschwindet. Maßstäbe, die du damit gesetzt hast, bleiben, wie sie sind.'
        => 'The button goes. Scales you set with it stay as they are.',
    'Vergessen'
        => 'Forget',
    'Erst die Länge eintragen, dann merken.'
        => 'Enter the length first, then remember it.',
    'Gegenstand merken'
        => 'Remember a thing',
    'Wie heißt der Gegenstand? Zum Beispiel „Feuerzeug".'
        => 'What is the thing called? For example “lighter”.',
    'Merken'
        => 'Remember',
    'Einhängen'
        => 'Hang in',
    'Einklappen'
        => 'Collapse',
    'Code und Link zeigen'
        => 'Show code and link',
    'Code und Link von %s zeigen'
        => 'Show code and link of %s',
    'Einladungslink'
        => 'Invitation link',
    'Code kopieren'
        => 'Copy code',
    'Einladungslink kopieren'
        => 'Copy invitation link',
    'Einladung'
        => 'Invitation',
    'Einladungen'
        => 'Invitations',
    'Einladungscode'
        => 'Invitation code',
    'Einmal zurücksetzen, dann holt der Lauf oben sie mit.'
        => 'Reset once, then the run above picks them up.',
    'Ein Messpunkt liegt nicht im Bild.'
        => 'A measure point is not within the image.',
    'Einpassen'
        => 'Fit',
    'In voller Größe, ein Bildpunkt je Bildschirmpunkt'
        => 'Full size, one image pixel per screen pixel',
    'Ein Punkt gehört danach zu „%s", und „%s" gibt es nicht mehr.'
        => 'One point then belongs to "%s", and "%s" no longer exists.',
    'Ein Punkt gehört danach zu „%s". Eine Tour, die er bisher trug, gibt er damit auf.'
        => 'One point then belongs to "%s". Any tour it carried before is given up with that.',
    'Ein Punkt gehört jetzt zur Tour „%s".'
        => 'One point now belongs to the tour "%s".',
    'Ein Punkt liegt im Papierkorb.'
        => 'One point is in the trash.',
    'Ein Punkt steht jetzt unter „Ohne Tour".'
        => 'One point now stands under "No tour".',
    'Ein Punkt trägt danach keine Tour mehr und steht unter „Ohne Tour".'
        => 'One point then carries no tour any more and sits under "Without a tour".',
    'Ein Punkt trägt jetzt „%s".'
        => 'One point now carries "%s".',
    'Ein Punkt würde angelegt'
        => 'One point would be created',
    'Ein Punkt zieht nach „%s", dann verschwindet „%s". Was schon im Ziel hängt, wird nicht doppelt zugeordnet.'
        => 'One point moves to "%s", then "%s" disappears. What already hangs in the target is not assigned twice.',
    'Einrichtung'
        => 'Setup',
    'Einspielen'
        => 'Import',
    'Einstellungen'
        => 'Settings',
    'Ein Track bzw. eine Route bleibt draussen — das sind Wege, keine Funde.'
        => 'One track or route stays out — those are paths, not finds.',
    'Eintrag'
        => 'Entry',
    'Einträge'
        => 'entries',
    'Ein Wegpunkt würde angelegt'
        => 'One waypoint would be created',
    'einzeilig'
        => 'single-line',
    'einzeln'
        => 'single',
    'Einzelne Bäume erahnbar'
        => 'Single trees guessable',
    'Einzelne Bäume unterscheidbar — reicht für die Suche'
        => 'Single trees distinguishable — enough for searching',
    'Emilia-Romagna'
        => 'Emilia-Romagna',
    'Endgültig'
        => 'For good',
    'Endgültig entfernen'
        => 'Remove for good',
    'Endgültig entfernen?'
        => 'Remove for good?',
    'Endgültig löschen'
        => 'Delete for good',
    'Endpunkt'
        => 'Endpoint',
    'Entfernen'
        => 'Remove',
    'Enthalten in Freigaben'
        => 'Contained in shares',
    'enthält'
        => 'contains',
    'enthält nicht'
        => 'does not contain',
    'enthält nicht „%s"'
        => 'does not contain "%s"',
    'enthält „%s"'
        => 'contains "%s"',
    'Entsperren'
        => 'Unlock',
    'Entweder Freigabe oder Freund — nicht beides.'
        => 'Either share or friend — not both.',
    'Ergebnis kopieren'
        => 'Copy result',
    'Ergibt sich aus der Wahl darunter'
        => 'Follows from the choice below',
    'Erkannt: %s'
        => 'Recognized: %s',
    'Er kommt danach wieder herein und findet alles vor, wie er es verlassen hat.'
        => 'They can then sign in again and will find everything as they left it.',
    'Er kommt dann nicht mehr herein, behält aber alles. Auch die angemeldeten Geräte werden abgemeldet. Rückgängig zu machen.'
        => 'They can then no longer sign in, but keep everything. Signed-in devices are signed out too. Can be undone.',
    'Er kommt dann nicht mehr herein, behält aber alles. Rückgängig zu machen.'
        => 'They no longer get in, but keep everything. Can be undone.',
    'Erst die Migrationen einspielen.'
        => 'Apply the migrations first.',
    'Klicke zuerst Anfang und Ende der Strecke im Bild an.'
        => 'First click the start and the end of the line in the image.',
    'Wähle zuerst ein Eichbild und klicke darin Anfang und Ende der Strecke an.'
        => 'First choose a calibration image and click the start and the end of the line in it.',
    'Erste Unterliste unter „%s"?'
        => 'First sublist under "%s"?',
    'erst das Original laden (oben links auf „Vorschaubild" klicken)'
        => 'load the original first (click “Preview” at the top left)',
    'Ordner in der Cloud öffnen'
        => 'Open the folder in the cloud',
    'Original'
        => 'Original',
    'erst „Volle Größe" laden'
        => 'load "Full size" first',
    'Es gibt keine Artenlisten — also auch keine Funde, für die das gälte.'
        => 'There are no species lists — hence no finds this would apply to.',
    'Es ist kein Bild angekommen.'
        => 'No image arrived.',
    'Es ist keine Datei angekommen.'
        => 'No file arrived.',
    '%s ist schon verbunden. Willst du wechseln, nimm „In andere Cloud umziehen …".'
        => '%s is already connected. To switch, use "Move to another cloud …".',
    'Es liegt keine hochgeladene Datei mehr bereit.'
        => 'There is no uploaded file waiting any more.',
    'Es ließ sich kein freier Code erzeugen. Bitte noch einmal versuchen.'
        => 'No free code could be generated. Please try again.',
    'etwa'
        => 'about',
    'Etwa %s je Kartenart — %s Kacheln.'
        => 'About %s per map type — %s tiles.',
    'Exkursion'
        => 'Excursion',
    'Exkursion %s bearbeiten'
        => 'Edit excursion %s',
    'Exkursion %s löschen'
        => 'Delete excursion %s',
    'Exkursion: %s'
        => 'Excursion: %s',
    'Ein Punkt gehört jetzt zur Exkursion „%s".'
        => 'One point now belongs to the excursion "%s".',
    '%d Punkte gehören jetzt zur Exkursion „%s".'
        => '%d points now belong to the excursion "%s".',
    'Ein Punkt gehört zu keiner Exkursion mehr.'
        => 'One point no longer belongs to any excursion.',
    '%d Punkte gehören zu keiner Exkursion mehr.'
        => '%d points no longer belong to any excursion.',
    'Einer liegt außerhalb ihres Zeitfensters.'
        => 'One lies outside its time window.',
    '%d liegen außerhalb ihres Zeitfensters.'
        => '%d lie outside its time window.',
    'Ein Punkt gehört danach zur Exkursion „%s" — auch wenn er außerhalb ihres Zeitfensters liegt.'
        => 'One point will then belong to the excursion "%s" — even if it lies outside its time window.',
    '%s Punkte gehören danach zur Exkursion „%s" — auch die außerhalb ihres Zeitfensters.'
        => '%s points will then belong to the excursion "%s" — those outside its time window too.',
    'Exkursion %s'
        => 'Excursion %s',
    'Exkursion angelegt.'
        => 'Excursion created.',
    'Exkursion angelegt — ein Mitleiter ist dabei.'
        => 'Excursion created — one co-leader is on board.',
    'Exkursion angelegt — %d Mitleiter sind dabei.'
        => 'Excursion created — %d co-leaders are on board.',
    'Exkursion bearbeiten'
        => 'Edit excursion',
    'Exkursionen'
        => 'Excursions',
    'Exkursionen brauchen die Migration 0068 — sie ist noch nicht eingespielt.'
        => 'Excursions need migration 0068 — it has not been applied yet.',
    'Exkursionen stehen noch nicht bereit — die Migration fehlt.'
        => 'Excursions are not ready yet — the migration is missing.',
    'Exkursion gelöscht.'
        => 'Excursion deleted.',
    'Exkursion gespeichert.'
        => 'Excursion saved.',
    'Exkursion löschen'
        => 'Delete excursion',
    'Exkursion löschen?'
        => 'Delete excursion?',
    'Exkursion „%s"'
        => 'Excursion "%s"',
    'Exkursion „%s“'
        => 'Excursion "%s"',
    'Exkursion „%s“ — als %s, läuft bis %s.'
        => 'Excursion "%s" — as %s, running until %s.',
    'Exkursion „%s“ — Leiter: %s'
        => 'Excursion "%s" — leader: %s',
    'Export'
        => 'Export',
    'Export&nbsp;…'
        => 'Export&nbsp;…',
    'extrem selten'
        => 'extremely rare',
    'Fadenwürmer'
        => 'Roundworms',
    'Familie'
        => 'Family',
    'Farbe'
        => 'Color',
    'Farbe auf diesem Gerät'
        => 'Color on this device',
    'Messlinien'
        => 'Measuring lines',
    'Randfarbe'
        => 'Edge color',
    'So sieht es auf einem Foto aus:'
        => 'This is how it looks on a photo:',
    'hell'
        => 'light',
    'grau'
        => 'grey',
    'dunkel'
        => 'dark',
    'Farbe und Form ändern'
        => 'Change color and shape',
    'Fehler (%s): %s [Code %s]'
        => 'Error (%s): %s [code %s]',
    'fehlt'
        => 'missing',
    'Feinste Stufe; vervierfacht die Menge gegenüber 18'
        => 'Finest level; quadruples the amount compared to 18',
    'Feld'
        => 'Field',
    'Feld entfernen'
        => 'Remove field',
    'Feld hinzufügen'
        => 'Add field',
    'Feld leeren'
        => 'Clear field',
    'Feld setzen'
        => 'Set field',
    'Feld setzen&nbsp;…'
        => 'Set&nbsp;field&nbsp;…',
    'Feld „%s"'
        => 'Field "%s"',
    'Feld „%s“ ausfüllen'
        => 'Fill in the "%s" field',
    'Fertig'
        => 'Done',
    'fertig.'
        => 'done.',
    'fileid und name werden gebraucht.'
        => 'fileid and name are needed.',
    'Filter'
        => 'Filter',
    'Filterbereich umranden'
        => 'Outline filter area',
    'Filter brauchen die Migration 0050 — sie ist noch nicht eingespielt.'
        => 'Filters need migration 0050 — it has not been applied yet.',
    'Filter gelöscht.'
        => 'Filters cleared.',
    'Filter halten zurück, was gerade nicht gemeint ist — auf der Karte und in der Liste zugleich. Sie sind in Gruppen geordnet, und geöffnet ist nur, was etwas enthält.'
        => 'Filters hold back what is not meant right now — on the map and in the list at once. They are ordered in groups, and only what contains something is open.',
    'Filter halten zurück, was nicht gemeint ist: Von jeder eingehängten Liste teilen sie nur die Punkte, die passen. Sie gelten ab dem Anlegen und lassen sich nicht mehr ändern.'
        => 'Filters hold back what is not meant: from every hung-in list they share only the points that fit. They apply from creation on and cannot be changed any more.',
    'Finder'
        => 'Finder',
    'Firefox ortet auf Android deutlich schlechter als Chrome — für genaue Punkte diese Seite dort öffnen.'
        => 'On Android, Firefox locates far worse than Chrome — for accurate points open this page there.',
    'Fische'
        => 'Fish',
    'Flechte'
        => 'Lichen',
    'Flechten'
        => 'Lichens',
    'Flechten, flechtenbewohnende und flechtenähnliche Pilze Deutschlands, mit Gefährdungseinstufung. Deutsche Namen gibt es nur für einen Teil der Arten — für Flechten sind kaum welche üblich.'
        => 'Lichens, lichenicolous and lichen-like fungi of Germany, with threat status. German names exist for only part of the species — few are in use for lichens.',
    'Flechten Deutschlands'
        => 'Lichens of Germany',
    'Fläche'
        => 'Area',
    'Fläche angelegt — %s.'
        => 'Area created — %s.',
    'Fläche ansehen'
        => 'View area',
    'Fläche mit %d Ecken'
        => 'Area with %d corners',
    'Fläche umranden'
        => 'Outline area',
    'Fläche umranden: Ecke für Ecke antippen, dann „Fertig".'
        => 'Outline area: tap corner by corner, then "Done".',
    'Form'
        => 'Shape',
    'Form auf diesem Gerät'
        => 'Shape on this device',
    'Form der Markierung'
        => 'Marker shape',
    'Foto %s von %s'
        => 'Photo %s of %s',
    'Foto endgültig löschen'
        => 'Delete photo for good',
    'Foto gelöscht.'
        => 'Photo deleted.',
    'Foto löschen'
        => 'Delete photo',
    'Foto löschen?'
        => 'Delete photo?',
    'Foto nicht gefunden.'
        => 'Photo not found.',
    'Fotos'
        => 'Photos',
    'Fotos, die du zu dieser Exkursion hochlädst, dürfen von der Exkursionsleitung frei verwendet und weitergegeben werden — etwa für Fundberichte und Belege.'
        => 'Photos you upload to this excursion may be freely used and passed on by the excursion leaders — for find reports and vouchers, for instance.',
    'Fotos, je Teilnehmer höchstens %d'
        => 'photos, at most %d per participant',
    'Fotos erlaubt'
        => 'Photos allowed',
    'Fotos je Teilnehmer, höchstens'
        => 'Photos per participant, at most',
    'Fotospeicher abgebrochen.'
        => 'Photo store aborted.',
    'Fotospeicher ist von einem anderen Tab belegt.'
        => 'The photo store is held by another tab.',
    'Foto wird aufbereitet …'
        => 'Preparing photo …',
    'FR'
        => 'FR',
    'möchte mit dir befreundet sein'
        => 'would like to be friends with you',
    'Freiburg'
        => 'Fribourg',
    'Freier Himmel hilft: weg von Hauswänden, raus aus dem Auto.'
        => 'Open sky helps: away from house walls, out of the car.',
    'Freigabe %s löschen'
        => 'Delete share %s',
    'Freigabe %s verlassen'
        => 'Leave share %s',
    'Freigabegebiet umranden'
        => 'Outline share area',
    'Freigabe löschen'
        => 'Delete share',
    'Freigabe löschen?'
        => 'Delete the share?',
    'Freigaben'
        => 'Shares',
    'Freigaben brauchen die Migration 0042 — sie ist noch nicht eingespielt.'
        => 'Shares need migration 0042 — it has not been applied yet.',
    'Freigaben von anderen'
        => 'Shares from others',
    'Freigabe verlassen'
        => 'Leave share',
    'Freigabe verlassen.'
        => 'Left the share.',
    'Freigabe verlassen?'
        => 'Leave the share?',
    'Freigeben'
        => 'Unlock',
    'Frei wählen …'
        => 'Choose freely …',
    'Frei — ohne Speichern'
        => 'Free — without saving',
    'Freund aufnehmen'
        => 'Admit a friend',
    'Freunde'
        => 'Friends',
    'Freunde brauchen die Migration 0041 — sie ist noch nicht eingespielt.'
        => 'Friends need migration 0041 — it has not been applied yet.',
    'Mit Freunden kannst du Listen teilen, Arbeitsgruppen bilden und auf der Karte miteinander schreiben.'
        => 'With friends you can share lists, form working groups and write to each other on the map.',
    'Ihr seid erst befreundet, wenn der andere deine Anfrage annimmt. Eine Anfrage schickst du mit „Neu".'
        => 'You are only friends once the other person accepts your request. You send a request with "Neu".',
    'Freunde und Gruppen brauchen die Migration 0041 — sie ist noch nicht eingespielt.'
        => 'Friends and groups need migration 0041 — it has not been applied yet.',
    'Freund in %s aufnehmen'
        => 'Admit a friend into %s',
    'Freundschaft anfragen'
        => 'Request a friendship',
    'Freundschaftsanfrage von %s'
        => 'Friend request from %s',
    'Friaul-Julisch Venetien'
        => 'Friuli-Venezia Giulia',
    'Fund'
        => 'Find',
    'Fund-Nr.'
        => 'Find no.',
    'Fund-Nr. %s'
        => 'Find no. %s',
    'Fund auswählen'
        => 'Select find',
    'Fundbericht'
        => 'Find report',
    'Fundbericht&nbsp;…'
        => 'Find&nbsp;report&nbsp;…',
    'Funde, die es hier schon gibt (gleicher Name, gleiche Stelle), werden nicht doppelt angelegt.'
        => 'Finds that already exist here (same name, same spot) are not created twice.',
    'Funde außerhalb (geprüft)'
        => 'finds outside (checked)',
    'Funde in einem Schutzgebiet'
        => 'finds in a protected area',
    'Funde noch offen'
        => 'finds still open',
    'Fundfelder — kommen mit der Artenliste, für den ganzen Ast'
        => 'Find fields — come with the species list, for the whole branch',
    'Fund NNNNN (JJJJ)'
        => 'Find NNNNN (JJJJ)',
    'Fundnummer'
        => 'Find number',
    'Fundnummer — nach deinem Muster aus den Einstellungen'
        => 'Find number — following your pattern from the settings',
    'Fundzeit'
        => 'Find time',
    'Fähnchen'
        => 'Flag',
    'Für alles, was oben nicht steht: Tabellen aus Excel, LibreOffice, Datenbanken und Fund-Apps. In Excel „Speichern unter" → <strong>CSV UTF-8</strong>, in LibreOffice „Speichern unter" → <strong>Text CSV</strong>. Wichtig ist nur eine Kopfzeile über den Spalten.'
        => 'For everything not listed above: tables from Excel, LibreOffice, databases and find apps. In Excel "Save As" → <strong>CSV UTF-8</strong>, in LibreOffice "Save As" → <strong>Text CSV</strong>. All that matters is a header row above the columns.',
    'Du brauchst einen Einladungscode. Deine Punkte siehst nur du, bis du eine Liste teilst.'
        => 'You need an invitation code. Only you see your points until you share a list.',
    'Für diese Aufnahme gibt es keine Vorschau — die Datei selbst liegt in der Cloud.'
        => 'There is no preview for this recording — the file itself lies in the cloud.',
    'Für diese E-Mail-Adresse gibt es bereits ein Konto.'
        => 'There is already an account for this e-mail address.',
    'Für diese E-Mail-Adresse gibt es schon ein Konto.'
        => 'There is already an account for this e-mail address.',
    'Für große Bestände erledigt %s dasselbe unbeaufsichtigt, im selben Takt — wie man es einrichtet, steht im Kopf der Datei.'
        => 'For large stocks %s does the same unattended, at the same pace — how to set it up stands at the head of the file.',
    'Für große Bestände erledigt %s dasselbe unbeaufsichtigt — wie man es einrichtet, steht im Kopf der Datei.'
        => 'For large stocks %s does the same unattended — how to set it up stands at the head of the file.',
    'Mit dieser Farbe zeigt YASAIMAPP, was gewählt oder wichtig ist: Knöpfe, gesetzte Haken, der offene Reiter. Du kannst jede Farbe mit jedem Hintergrund mischen. Manche Mischungen sind aber schlechter zu erkennen. Nimm die, die du am besten siehst. Auch das gilt nur für dieses Gerät.'
        => 'With this colour YASAIMAPP shows what is chosen or important: buttons, set ticks, the open tab. You can mix any colour with any background. Some mixes are harder to see, though. Take the one you see best. This too applies only to this device.',
    'Für wen? (nur als Gedächtnisstütze)'
        => 'For whom? (just as a memory aid)',
    'Gast'
        => 'Guest',
    'Gast — noch ohne eigene Bedeutung'
        => 'Guest — no meaning of its own yet',
    'Gattung'
        => 'Genus',
    'Gauß-Krüger, Streifen %s (%s°)'
        => 'Gauß-Krüger, zone %s (%s°)',
    'GBIF-Backbone, Stand 28.08.2023'
        => 'GBIF Backbone, as of 28.08.2023',
    'Gebiet'
        => 'Area',
    'Gebiet anlegen'
        => 'Create area',
    'Gebiet anlegen fehlgeschlagen: %s'
        => 'Creating the area failed: %s',
    'Gebiete'
        => 'Areas',
    'Gebiet löschen'
        => 'Delete area',
    'Gebiet nicht gefunden.'
        => 'Area not found.',
    'Gebiet nicht gefunden oder nichts zu ändern.'
        => 'Area not found or nothing to change.',
    'Gebiet umranden'
        => 'Outline area',
    'Gebiet „%s" löschen?'
        => 'Delete area "%s"?',
    'Gefundene Art'
        => 'Species found',
    'gefährdet'
        => 'vulnerable',
    'Gefährdung'
        => 'Threat status',
    'Gefährdung unbekannten Ausmaßes'
        => 'threatened to an unknown extent',
    'Gehört zu der Änderung darüber, die der Server abgelehnt hat — ohne sie gibt es den Eintrag nicht, auf den sie sich bezieht.'
        => 'Belongs to the change above, which the server rejected — without it the entry it refers to does not exist.',
    'gekoppelt %s'
        => 'paired %s',
    'geleert und neu angelegt'
        => 'emptied and recreated',
    'Gelöscht'
        => 'Deleted',
    'Gelöschte Punkte bleiben hier liegen, bis du sie endgültig entfernst. Ein Punkt ist zu mühsam erarbeitet, um ihn mit einem Fehlklick zu verlieren.'
        => 'Deleted points stay here until you remove them permanently. A point is too laboriously earned to lose it to a stray click.',
    'Gelöschter Punkt — nur angezeigt'
        => 'Deleted point — display only',
    'Gelöscht — wird übertragen, sobald Verbindung da ist.'
        => 'Deleted — will be transferred as soon as there is a connection.',
    'Gemeinde'
        => 'Municipality',
    'Gemeinden'
        => 'Municipalities',
    'Gemeinde …'
        => 'Municipality …',
    'Gemeinsam'
        => 'Shared',
    'Genauigkeit'
        => 'Accuracy',
    'Genauigkeit (m)'
        => 'Accuracy (m)',
    'Genauigkeit ±%s m'
        => 'Accuracy ±%s m',
    'Genf'
        => 'Geneva',
    'GeoJSON (QGIS, uMap, geojson.io)'
        => 'GeoJSON (QGIS, uMap, geojson.io)',
    'GeoJSON (Web-Karten und Konverter)'
        => 'GeoJSON (web maps and converters)',
    'geplant'
        => 'planned',
    'Geräte'
        => 'Devices',
    'Gerät trennen?'
        => 'Disconnect device?',
    'Gespeichert.'
        => 'Saved.',
    'Gespeichert. %s lässt sich jetzt verbinden.'
        => 'Saved. %s can now be connected.',
    'Gespeichert. Wird übertragen, sobald Verbindung da ist.'
        => 'Saved. Will be transferred as soon as there is a connection.',
    'Gespeichert wird nur die Umrandung — auf allen deinen Geräten. Karten dafür holst du dann auf dem Gerät, das mitkommt.'
        => 'Only the outline is saved — on all your devices. You then fetch maps for it on the device that comes along.',
    'Gespeichert — der Name steht jetzt auch an deinem bisherigen Fund.'
        => 'Saved — the name now also stands on your previous find.',
    'Gespeichert — der Name steht jetzt auch an deinen %d bisherigen Funden.'
        => 'Saved — the name now also stands on your %d previous finds.',
    'Gespeichert. Auf der Karte gilt es, sobald du sie neu lädst.'
        => 'Saved. On the map it applies once you reload it.',
    'Gespeichert. Ab jetzt siehst du YASAIMAPP in dieser Sprache.'
        => 'Saved. From now on you see YASAIMAPP in this language.',
    'Gespeichert — wird übertragen, sobald Verbindung da ist. Karten dafür holst du danach.'
        => 'Saved — will be transferred as soon as there is a connection. You fetch maps for it afterwards.',
    'gesperrt seit %s'
        => 'locked since %s',
    'gestern %s'
        => 'yesterday %s',
    'Geteilt'
        => 'Shared',
    'Geteilte Liste'
        => 'Shared list',
    'Geteilte Liste %s anpassen'
        => 'Adjust shared list %s',
    '%d von %d Listen gewählt'
        => '%d of %d lists chosen',
    'Geteilt von'
        => 'Shared by',
    'Geteilt wird nur, was passt: %s'
        => 'Only what fits is shared: %s',
    'geändert von %s'
        => 'changed by %s',
    'Geändert — %s'
        => 'Changed — %s',
    'Gib dem Gerät einen Namen, damit du es später wiedererkennst.'
        => 'Give the device a name so you recognize it later.',
    'Gibt ein Gast seine E-Mail-Adresse an, werden ihm seine Funde <strong>zugeschrieben, sobald er sich mit dieser Adresse anmeldet</strong> — auch Wochen später, das Konto darf neu sein. Er sieht seine Exkursionen dann unter Organisieren → Touren und darf die eigenen Funde weiter pflegen.'
        => 'If a guest gives their e-mail address, their finds are <strong>credited to them as soon as they sign in with that address</strong> — even weeks later, the account may be new. They then see their excursions under Organize → Tours and may keep maintaining their own finds.',
    'Das gilt nur für dieses Gerät. So kannst du zum Beispiel am Handy hell und am Computer dunkel wählen.'
        => 'This applies only to this device. So you can choose light on the phone and dark on the computer, for example.',
    'Ging gerade nicht — ohne Netz bleibt die Anfrage stehen.'
        => 'That did not work just now — without a connection the request stays put.',
    'Ging gerade nicht — ohne Netz bleibt es, wie es ist.'
        => 'That did not work just now — without a connection things stay as they are.',
    'Glarus'
        => 'Glarus',
    'Deutsche Namen darunter zeigen'
        => 'Show German names beneath',
    'Gleiche Arten zu einer Zeile zusammenfassen'
        => 'Merge identical species into one row',
    'Gleich Karten auf dieses Gerät laden'
        => 'Load maps onto this device right away',
    'Google Earth und My Maps: die Karte ausgeben (.kml/.kmz)'
        => 'Google Earth and My Maps: exporting the map (.kml/.kmz)',
    'Google Maps: So kommst du an die Takeout-Datei (.zip)'
        => 'Google Maps: How to get the Takeout file (.zip)',
    'GPS-Geräte und Karten-Apps: Garmin, Locus, OsmAnd, OruxMaps (.gpx)'
        => 'GPS devices and map apps: Garmin, Locus, OsmAnd, OruxMaps (.gpx)',
    'GPX (GPS-Geräte)'
        => 'GPX (GPS devices)',
    'GPX (GPS-Geräte und Karten-Apps)'
        => 'GPX (GPS devices and map apps)',
    'Grad/Dezimalminuten'
        => 'Degrees/decimal minutes',
    'Grad/Minuten/Sekunden'
        => 'Degrees/minutes/seconds',
    'Gradangabe'
        => 'Degree notation',
    'Graubünden'
        => 'Grisons',
    'groß (70 mm)'
        => 'large (70 mm)',
    'Groß-, Klein- und Schleimpilze Deutschlands, mit deutschen Namen und Gefährdungseinstufung.'
        => 'Macro-, microfungi and slime moulds of Germany, with German names and threat status.',
    'Grund: %s'
        => 'Reason: %s',
    'Gruppe %s löschen'
        => 'Delete group %s',
    'Gruppe löschen'
        => 'Delete group',
    'Gruppe löschen?'
        => 'Delete the group?',
    'Gruppen'
        => 'Groups',
    'Größe'
        => 'Size',
    'Gründer'
        => 'Founder',
    'Gültig bis'
        => 'Valid until',
    'Gültig für … Tage (leer = unbegrenzt)'
        => 'Valid for … days (empty = unlimited)',
    'Hakensaugwürmer'
        => 'Monogeneans',
    'Hamburg'
        => 'Hamburg',
    'Hell'
        => 'Light',
    'Herausnehmen'
        => 'Take out',
    'Herausnehmen?'
        => 'Take out?',
    'Herunterladen'
        => 'Download',
    'Herunterladen, auffrischen, vom Gerät entfernen.'
        => 'Download, refresh, remove from the device.',
    'Hessen'
        => 'Hesse',
    'Hier ist schon unter „%s" gemessen.'
        => 'Already measured here under "%s".',
    'Hier kannst du verschiedene Formate hochladen.'
        => 'You can upload various formats here.',
    'hier keine Daten'
        => 'no data here',
    'Hilfe'
        => 'Help',
    'Hinzufügen'
        => 'Add',
    'Hirsch'
        => 'Deer',
    'Hochgeladene Datei'
        => 'Uploaded file',
    'Hochladen und prüfen'
        => 'Upload and check',
    'HTTP %s für %s'
        => 'HTTP %s for %s',
    'Hundertfüßer'
        => 'Centipedes',
    'Hydrozoen'
        => 'Hydrozoans',
    'Häufigste Gattungen'
        => 'Most frequent genera',
    'Höchste Zoomstufe:'
        => 'Highest zoom level:',
    'Höhe'
        => 'Elevation',
    'Höhe (m)'
        => 'Elevation (m)',
    'Höhe aus dem Geländemodell DGM1 — auf etwa 0,3 m genau'
        => 'Elevation from the DGM1 terrain model — accurate to about 0.3 m',
    'Höhe aus dem weltweiten Geländemodell Copernicus — Raster 90 m'
        => 'Elevation from the worldwide Copernicus terrain model — 90 m grid',
    'Höhe vom Empfänger des Geräts — auf etwa 10 m genau'
        => 'Elevation from the device\'s receiver — accurate to about 10 m',
    'Höhen'
        => 'Elevations',
    'Höhere Krebse'
        => 'Malacostracans',
    'Höhe ±'
        => 'Elevation ±',
    'Ihr seid nicht befreundet.'
        => 'You are not friends.',
    'im Abzug'
        => 'in the dump',
    'im Gebiet „%s“'
        => 'in the area "%s"',
    'Im Papierkorb (dieses Konto)'
        => 'In the trash (this account)',
    'Import'
        => 'Import',
    'Import 1 von %d — jeder wird seine eigene Liste und lässt sich einzeln zurücknehmen.'
        => 'Import 1 of %d — each becomes its own list and can be taken back individually.',
    'Importname (frei wählbar — leer wird das Datum verwendet)'
        => 'Import name (your choice — empty uses the date)',
    'Import umbenennen'
        => 'Rename import',
    'Import zurücknehmen?'
        => 'Take back the import?',
    'im umrandeten Gebiet'
        => 'in the outlined area',
    'Im Wald gibt es keinen Empfang, und die App muss trotzdem helfen. Punkte entstehen sofort und reisen später zum Server. <strong>Was auf dem Gerät liegt, entscheidest du</strong> — nichts lädt ungefragt.'
        => 'In the woods there is no reception, and the app must help anyway. Points come into being immediately and travel to the server later. <strong>What lies on the device is your decision</strong> — nothing downloads unasked.',
    'In %d Chats wartet etwas'
        => 'Something is waiting in %d chats',
    'In den Papierkorb'
        => 'To the trash',
    'In den Papierkorb?'
        => 'Into the trash?',
    'In den Papierkorb gelegt.'
        => 'Put into the trash.',
    'In den Papierkorb legen?'
        => 'Put into the trash?',
    'In der App öffnen'
        => 'Open in the app',
    'In der config.php fehlt der Schlüssel für die Cloud-Zugänge (cloud.key). Ohne ihn wird kein Token gespeichert.'
        => 'The key for the cloud connections (cloud.key) is missing from config.php. Without it no token is stored.',
    'In der Liste nur <strong>„Gespeichert"</strong> ankreuzen. Dahinter stecken die Maps-Listen: Favoriten, „Möchte ich besuchen", eigene Listen.'
        => 'In the list, tick only <strong>"Saved"</strong>. That is where the Maps lists live: favorites, "Want to go", your own lists.',
    'In die Karte tippen, um den Punkt zu setzen.'
        => 'Tap the map to set the point.',
    'In die Karte tippen — Ecke für Ecke'
        => 'Tap the map — corner by corner',
    'In die Liste „%s", mit Fundzeit, Genauigkeit und Bemerkungen aus der App.'
        => 'Into the list "%s", with find time, accuracy and remarks from the app.',
    'In die Liste „%s", mit Name, Beschreibung, Höhe und dem Zeitpunkt der Markierung als Fundzeit.'
        => 'Into the list "%s", with name, description, elevation and the moment of marking as the find time.',
    'In die Liste „%s", mit Name, Beschreibung und — wo vorhanden — Zeitstempel und Höhe.'
        => 'Into the list "%s", with name, description and — where present — timestamp and elevation.',
    'In die Liste „%s", mit Titel, Beschreibung und allen weiteren Eigenschaften als Notiz.'
        => 'Into the list "%s", with title, description and all further properties as the note.',
    'In die Liste „%s".'
        => 'Into the list "%s".',
    'In diesem Ordner liegen deine Fundpunkte als Tabellen, eine Datei je Monat („Funde 2026-08.csv"). Sie werden automatisch geschrieben, immer dann, wenn sich in einem Monat etwas geändert hat. Ein Monat, in dem nichts passiert ist, wird nicht noch einmal hochgeladen.'
        => 'This folder holds your find spots as tables, one file per month ("Funde 2026-08.csv"). They are written automatically, whenever something changed in a month. A month in which nothing happened is not uploaded again.',
    'In eine Freigabe kannst du nur Freunde aufnehmen.'
        => 'You can only admit friends into a share.',
    'In einem Chat wartet etwas'
        => 'Something is waiting in one chat',
    'Inhalt'
        => 'Contents',
    'in keinem'
        => 'in none',
    'in keiner Liste'
        => 'in no list',
    'Insekt'
        => 'Insect',
    'Insekten'
        => 'Insects',
    'In welche Liste?'
        => 'Into which list?',
    'In welche Listen?'
        => 'Into which lists?',
    'In welches Format?'
        => 'Into which format?',
    'In welche Tour?'
        => 'Into which tour?',
    'In „%s“ aufnehmen'
        => 'Admit into "%s"',
    'In „%s“ einhängen'
        => 'Mount into "%s"',
    'ja'
        => 'yes',
    'Jahr'
        => 'year',
    'Für Fortgeschrittene: ein Speicher mit S3-Zugang, etwa bei Hetzner, Wasabi oder Backblaze. Hier fragt dich niemand nach Zustimmung. Du trägst fünf Angaben selbst ein.'
        => 'For advanced users: a storage with S3 access, for example at Hetzner, Wasabi or Backblaze. Nobody asks you for consent here. You enter five values yourself.',
    'Je Google-Liste entsteht darunter eine eigene. Punkte, die es hier schon gibt (gleicher Titel, gleiche Stelle), werden nicht doppelt angelegt, sondern nur zugeordnet.'
        => 'Each Google list becomes one of its own underneath. Points that already exist here (same title, same spot) are not created twice, only assigned.',
    'jetzige Fassung'
        => 'current version',
    'Jetzt sichern'
        => 'Back up now',
    'Jetzt versuchen'
        => 'Try now',
    'Jura'
        => 'Jura',
    'Kachel fehlt in beiden Quellen'
        => 'Tile missing in both sources',
    'Kacheln — Angaben untereinander'
        => 'Tiles — details stacked',
    'Kalabrien'
        => 'Calabria',
    'Kampanien'
        => 'Campania',
    'Karten auf dieses Gerät'
        => 'Maps onto this device',
    'Karten behalten bis'
        => 'Keep maps until',
    'Karten für unterwegs'
        => 'Maps to take along',
    'Kartenlink'
        => 'Map link',
    'Karte wieder mitlaufen lassen'
        => 'Let the map follow again',
    'Kein %s im Bestand — mit den anderen Bedingungen zusammen'
        => 'No %s in the stock — together with the other conditions',
    'Kein Benutzer angegeben.'
        => 'No user given.',
    'keine'
        => 'none',
    'Keine Aufnahme angegeben.'
        => 'No recording given.',
    'Keine brauchbaren Eimerschlüssel angegeben. Erwartet wird "2026-08".'
        => 'No usable bucket keys given. Expected is "2026-08".',
    'Keine brauchbare Position dabei.'
        => 'No usable position among them.',
    'Keine Cloud verbunden.'
        => 'No cloud connected.',
    'Keine der Dateien war brauchbar.'
        => 'None of the files was usable.',
    'Keine Ebene gewählt'
        => 'No layer selected',
    'keine Fotos'
        => 'no photos',
    'Keine Kennung angegeben.'
        => 'No identifier given.',
    'Keine Liste gewählt'
        => 'No list selected',
    'Keine Liste gewählt.'
        => 'No list selected.',
    'Gerade keine offenen Anfragen.'
        => 'No open requests right now.',
    'Keine offenen Einladungen — eingelöste stehen als Benutzer unten.'
        => 'No open invitations — redeemed ones stand as users below.',
    'Keine Punkte gewählt.'
        => 'No points chosen.',
    'keine Teilseite — vermutlich abgemeldet'
        => 'not a partial page — probably signed out',
    'Keine Tour und keine Exkursion passt dazu.'
        => 'No tour and no excursion matches.',
    'Keine Verbindung.'
        => 'No connection.',
    'Keine Verbindung — die Umrandung ist nicht gespeichert. Später noch einmal drücken.'
        => 'No connection — the outline is not saved. Press again later.',
    'Keine Verbindung — es steht, was auf dem Gerät liegt.'
        => 'No connection — what lies on the device is shown.',
    'Keine Änderung — nur angefasste Haken zählen.'
        => 'No change — only touched checkmarks count.',
    'Kein Feld gewählt.'
        => 'No field chosen.',
    'Kein Punkt angegeben.'
        => 'No point given.',
    'Kein Punkt ausgewählt.'
        => 'No point selected.',
    'Kein Punkt trägt ein veraltetes Kürzel.'
        => 'No point carries an outdated code.',
    'KEIN SCHUTZ VOR VERSEHENTLICHEM LÖSCHEN'
        => 'NO PROTECTION AGAINST DELETING BY MISTAKE',
    'Keins umrandet.'
        => 'None outlined.',
    'Kein Zugriff.'
        => 'No access.',
    'Kein Zugriff auf diese Exkursion.'
        => 'No access to this excursion.',
    'Kein Zugriff auf diese Freigabe.'
        => 'No access to this share.',
    'Kennung oder Passwort stimmt nicht.'
        => 'E-mail or password is not right.',
    'Kiemenfußkrebse'
        => 'Branchiopods',
    'Klasse'
        => 'Class',
    'KML (Google Earth)'
        => 'KML (Google Earth)',
    'Kohle'
        => 'Charcoal',
    'Kommt die Tabelle aus einer Fund-App wie „Meine Pilze", erkennt der Import sie an ihren Spalten und holt Fundzeit, Genauigkeit und Bemerkungen von selbst heraus; die wissenschaftlichen Namen werden gegen die Namensliste aufgelöst, damit Synonyme zusammenfinden.'
        => 'If the table comes from a find app like "Meine Pilze", the import recognizes it by its columns and pulls out find time, accuracy and remarks by itself; the scientific names are resolved against the name list so synonyms come together.',
    'Konten'
        => 'Accounts',
    'Konto'
        => 'Account',
    'Konto anlegen'
        => 'Create an account',
    'Konto endgültig löschen?'
        => 'Delete the account for good?',
    'Konto sperren?'
        => 'Lock the account?',
    'Koordinaten'
        => 'Coordinates',
    'Koordinaten kopieren'
        => 'Copy coordinates',
    'Koordinaten kopiert.'
        => 'Coordinates copied.',
    'Koordinate suchen'
        => 'Search coordinate',
    'Kopieren ging nicht — das Feld ist markiert, Strg+C genügt.'
        => 'Copying did not work — the field is selected, Ctrl+C is enough.',
    'Kopieren ging nicht — die Tabelle lässt sich auch von Hand markieren.'
        => 'Copying did not work — the table can also be selected by hand.',
    'Kopieren nicht erlaubt — Text ist markiert.'
        => 'Copying not allowed — the text is selected.',
    'Kopiert'
        => 'Copied',
    'Kopiert.'
        => 'Copied.',
    'Koppeln'
        => 'Pair',
    'Kreis'
        => 'Circle',
    'Kreuz'
        => 'Cross',
    'Kärnten'
        => 'Carinthia',
    'Kürzel im Titel'
        => 'Marks in the title',
    'Laden'
        => 'Load',
    'Land'
        => 'Country',
    'lange drücken für eine Fläche'
        => 'long-press for an area',
    'Latium'
        => 'Lazio',
    'Lebensraumtypen und Arten'
        => 'Habitat types and species',
    'leer, wird übersprungen'
        => 'empty, will be skipped',
    'leer = unbegrenzt'
        => 'empty = unlimited',
    'Leeren'
        => 'Empty',
    'Leere Touren brauchen die Migration 0073 — sie ist noch nicht eingespielt.'
        => 'Empty tours need migration 0073 — it has not been applied yet.',
    'Leiter: %s'
        => 'Leader: %s',
    'Letzte Tour'
        => 'Latest tour',
    'Letzte Tour …'
        => 'Latest tour …',
    'Ligurien'
        => 'Liguria',
    'Lila'
        => 'Purple',
    'Lime'
        => 'Lime',
    'Linie'
        => 'Line',
    'Links die Seitenleiste mit deinen Listen und Punkten, rechts die Karte. Die Leiste lässt sich einklappen — der Griff dafür sitzt in ihrer Kopfzeile, und auf dem Handy legt sie sich über die Karte statt daneben.'
        => 'On the left the sidebar with your lists and points, on the right the map. The sidebar can be collapsed — its handle sits in its header, and on the phone it lays itself over the map instead of beside it.',
    'Liste'
        => 'List',
    'Liste %s (gelöscht)'
        => 'List %s (deleted)',
    'Liste %s bearbeiten'
        => 'Edit list %s',
    'Liste angelegt.'
        => 'List created.',
    'Liste aus dem Takeout'
        => 'List from the Takeout',
    'Liste aushängen?'
        => 'Unhook the list?',
    'Liste bearbeiten'
        => 'Edit list',
    'Liste einhängen'
        => 'Hang in a list',
    'Liste gelöscht.'
        => 'List deleted.',
    'Liste gelöscht. %d Punkte stehen jetzt unter „Ohne Liste".'
        => 'List deleted. %d points now stand under "Without list".',
    'Liste gelöscht. 1 Punkt steht jetzt unter „Ohne Liste".'
        => 'List deleted. 1 point now stands under "Without list".',
    'Liste geändert.'
        => 'List changed.',
    'Liste in %s einhängen'
        => 'Hang a list into %s',
    'Liste löschen'
        => 'Delete list',
    'Liste löschen?'
        => 'Delete list?',
    'Listen'
        => 'Lists',
    'Listen&nbsp;…'
        => 'Lists&nbsp;…',
    'Listen der Auswahl'
        => 'Lists of the selection',
    'Listen einhängen'
        => 'Hang in lists',
    'Liste nicht gefunden.'
        => 'List not found.',
    'Liste nicht gefunden oder nichts zu ändern.'
        => 'List not found or nothing to change.',
    'Listen oder Punkte'
        => 'Lists or points',
    'Liste umbenennen'
        => 'Rename list',
    'Liste verschieben'
        => 'Move list',
    'Liste zusammenführen'
        => 'Merge list',
    'Liste — eine Zeile je Fund'
        => 'List — one row per find',
    'Liste „%s" löschen'
        => 'Delete list "%s"',
    'Liste „%s" löschen?'
        => 'Delete list "%s"?',
    'Lombardei'
        => 'Lombardy',
    'Los geht’s'
        => 'Let’s go',
    'Luzern'
        => 'Lucerne',
    'lädt hoch …'
        => 'uploading …',
    'lädt …'
        => 'loading …',
    'Länge'
        => 'Length',
    'Längengrad'
        => 'Longitude',
    'Breite µm'
        => 'Breadth µm',
    'Länge µm'
        => 'Length µm',
    'läuft'
        => 'running',
    'läuft ab %s'
        => 'expires %s',
    'Läuft bis %s.'
        => 'Runs until %s.',
    'Du bist angemeldet als %s — deine Funde bleiben in deinem eigenen Bestand, die Exkursionsleitung darf sie für den Fundbericht bearbeiten.'
        => 'You are signed in as %s — your finds stay in your own stock, the excursion leaders may edit them for the find report.',
    'Gib deinen Namen an — er steht als Finder an deinen Funden.'
        => 'Enter your name — it stands as finder on your finds.',
    'läuft nicht ab'
        => 'does not expire',
    'Läuft — Umbenennen gilt für alle Punkte dieser Tour.'
        => 'Running — renaming applies to all points of this tour.',
    'Löschen'
        => 'Delete',
    'löschen'
        => 'delete',
    'Löschen fehlgeschlagen.'
        => 'Deleting failed.',
    'Löschen fehlgeschlagen: %s'
        => 'Deleting failed: %s',
    'makroskopisch sicher'
        => 'macroscopically certain',
    'Manche Tabellenanzeiger — auch die Schnellansicht mancher Cloud — halten „5815/431" für eine Rechnung: sie teilen 5815 durch 431, bekommen 13,4919 und zeigen davon einen gekürzten Bruch, nämlich „27/2". Andere Blätter sehen dann zwei- oder dreistellig aus. In der Datei steht der richtige Wert — nur die Anzeige rechnet. Wer sichergehen will, öffnet sie in einem Tabellenprogramm oder sieht sie als reinen Text an.'
        => 'Some table viewers — the quick view of some clouds included — take "5815/431" for a calculation: they divide 5815 by 431, get 13.4919 and show a reduced fraction of it, namely "27/2". Other sheets then look two or three digits long. The file holds the correct value — only the display calculates. To be sure, open it in a spreadsheet program or view it as plain text.',
    'Marken'
        => 'Marche',
    'Marker verschieben'
        => 'Move marker',
    'Markiere hier Gebiete, die du gerne als Offline-Karten herunterladen willst. Herunterladen musst du sie dann auf das Gerät, das du in dem Gebiet benutzt — vermutlich dein Handy.'
        => 'Mark areas here that you would like to download as offline maps. You then have to download them on the device you use in that area — probably your phone.',
    'Material'
        => 'Material',
    'Maßstab'
        => 'Scale',
    'Maßstab-Vorlagen'
        => 'Scale presets',
    'Maßstab entfernen'
        => 'Remove scale',
    'Maßstab setzen'
        => 'Set scale',
    'Mecklenburg-Vorpommern'
        => 'Mecklenburg-Western Pomerania',
    'Mehr als %s Positionen — das ist keine Wegstrecke mehr.'
        => 'More than %s positions — that is no longer a track.',
    'Mehr als 200 Bildtitel-Vorgaben — das kann nicht stimmen.'
        => 'More than 200 picture-name suggestions — that cannot be right.',
    'Mehr als 500 Messungen an einem Bild — das kann nicht stimmen.'
        => 'More than 500 measurements on one image — that cannot be right.',
    'Mehr als zwei Maße in einem Ausschnitt — das kann nicht stimmen.'
        => 'More than two measures in one cutout — that cannot be right.',
    'Mehrere Begriffe, mit Komma getrennt, gelten als oder — Zeilen untereinander als und.'
        => 'Several terms separated by commas count as or — rows beneath one another as and.',
    'Mehrere möglich. Ohne Auswahl landet der Punkt unter „Ohne Liste“.'
        => 'Several are possible. Without a selection the point goes under "No list".',
    'mehrzeilig'
        => 'multi-line',
    'Mein Bereich'
        => 'My area',
    'meine'
        => 'mine',
    'Meine Listen'
        => 'My lists',
    'Messen'
        => 'Measuring',
    'Messtischblatt %s (%s)'
        => 'Messtischblatt %s (%s)',
    'Messung'
        => 'Measurement',
    'Messungen leeren'
        => 'Clear measurements',
    'Messungen löschen?'
        => 'Delete measurements?',
    'Messung läuft …'
        => 'Measuring …',
    'Messung starten'
        => 'Start measuring',
    'Methode nicht unterstützt.'
        => 'Method not supported.',
    'MI'
        => 'WE',
    'Migration 0033 ist noch nicht eingespielt.'
        => 'Migration 0033 has not been applied yet.',
    'Migration 0033 ist noch nicht eingespielt — bis dahin gibt es keine Blätter.'
        => 'Migration 0033 has not been applied yet — until then there are no sheets.',
    'Migration 0036 ist noch nicht eingespielt.'
        => 'Migration 0036 has not been applied yet.',
    'Migration 0036 ist noch nicht eingespielt — bis dahin gibt es keine Artentabelle.'
        => 'Migration 0036 has not been applied yet — until then there is no species table.',
    'Migrationen'
        => 'Migrations',
    'Migrationen einspielen'
        => 'Apply migrations',
    'Mikrometer je Pixel'
        => 'Micrometres per pixel',
    'mikroskopisch sicher'
        => 'microscopically certain',
    'mindestens'
        => 'at least',
    'Mindestens drei Ecken.'
        => 'At least three corners.',
    'Hier stehen alle bei YASAIMAPP, mit denen du noch nicht befreundet bist. Tipp ein paar Buchstaben vom Namen ein, zum Beispiel „Ben". Dann bleiben nur die passenden übrig.'
        => 'Everyone on YASAIMAPP you are not yet friends with is listed here. Type a few letters of the name, for example "Ben". Then only the matching ones remain.',
    'Tippst du auf eine Person, geht die Anfrage sofort los.'
        => 'When you tap a person, the request goes out at once.',
    'Minze'
        => 'Mint',
    'Mitarbeiten'
        => 'Collaborate',
    'Mit E-Mail bekommst du deine Funde später zugeschrieben, wenn du ein Konto anlegst. Nachtragen geht, solange die Exkursion läuft.'
        => 'With an email your finds are credited to you later when you create an account. Adding it works as long as the excursion is running.',
    'Mit E-Mail können dir deine Funde später zugeschrieben werden, wenn du irgendwann ein Konto anlegst. Ohne geht es auch — dann bleibt es beim Namen.'
        => 'With an e-mail, your finds can be attributed to you later if you ever create an account. It works without one too — then it stays with the name.',
    'Neue Vorlage'
        => 'New preset',
    'Vorlage „%s" bearbeiten'
        => 'Edit preset "%s"',
    'mit Gemeinde'
        => 'with a municipality',
    'Mitglied aufnehmen'
        => 'Admit a member',
    'Mitglieder'
        => 'Members',
    'Mitglied in %s aufnehmen'
        => 'Admit a member into %s',
    'Mitleiter aufgenommen.'
        => 'Co-leader added.',
    'Mitleiter brauchen die Migration 0069 — sie ist noch nicht eingespielt.'
        => 'Co-leaders need migration 0069 — it has not been applied yet.',
    'Mitleiter dürfen die Exkursion bearbeiten und den QR-Code zeigen. Löschen und Leiter verwalten bleibt bei dir.'
        => 'Co-leaders may edit the excursion and show the QR code. Deleting and managing leaders stays with you.',
    'Mitleiter entfernen'
        => 'Remove co-leader',
    'Mitleiter entfernen?'
        => 'Remove co-leader?',
    'Mitleiter entfernt.'
        => 'Co-leader removed.',
    'Mitleiter nimmst du aus deinen Freunden auf.'
        => 'Co-leaders are added from among your friends.',
    'Mitleiter nimmst du aus deinen Freunden auf — alle sind schon dabei.'
        => 'Co-leaders are added from among your friends — all of them are already on board.',
    'Mitleiter nimmst du aus deinen Freunden auf — du hast noch keine.'
        => 'Co-leaders are added from among your friends — you have none yet.',
    'Mitleiter wählen'
        => 'Choose co-leader',
    'Mitmachen'
        => 'Join in',
    'mittel (45 mm)'
        => 'medium (45 mm)',
    'MO'
        => 'MO',
    'Molise'
        => 'Molise',
    'Momentaufnahme'
        => 'Snapshot',
    'Monat'
        => 'month',
    'Monate mit Funden'
        => 'Months with finds',
    'MTB, z. B. 4625/2 …'
        => 'MTB, e.g. 4625/2 …',
    'Motivname'
        => 'Subject name',
    // „Richtung zeigen": an arrow at the edge towards a find (29.09.2026)
    'Richtung' => 'Direction',
    'Ein Pfeil am Bildschirmrand zeigt dir, in welcher Richtung der Fund liegt und wie weit er entfernt ist. Einen Weg sucht die Karte nicht.'
        => 'An arrow at the edge of the screen shows you in which direction the find lies and how far away it is. The map does not look for a route.',
    'Richtung zeigen' => 'Show direction',
    'Richtung beenden' => 'Stop showing the direction',
    'Karte mit der Blickrichtung drehen' => 'Turn the map with your view',
    'Lässt sich noch nicht laden' => 'Cannot be loaded yet',
    'Ohne Netz zeigt die Karte hier die einfachere Ansicht: Für diese Stelle liegen keine Kartendaten auf dem Gerät.'
        => 'Without a connection the map shows its simpler view here: the map data for this spot is not on the device.',
    // The subject window of a batch: count, and one name for all (28.09.2026)
    'Motivname für Foto %d von %d'
        => 'Subject name for photo %d of %d',
    'Diesen Namen für alle Fotos nehmen'
        => 'Use this name for all photos',
    'Diesen Namen für alle übrigen Fotos nehmen'
        => 'Use this name for all remaining photos',
    // The two sentences of the subject window that had no entry yet (28.09.2026)
    'Art des Fotos'
        => 'Kind of photo',
    'Eigener Name'
        => 'Custom name',
    'Makro'
        => 'Macro',
    'Mikro'
        => 'Micro',
    'Motivnamen'
        => 'Subject names',
    'Lädst du ein Foto hoch, wählst du zuerst: Makro oder Mikro. Danach schlägt dir YASAIMAPP Namen aus der passenden Liste vor, zum Beispiel „Hauptmotiv" oder „Sporen".'
        => 'When you upload a photo, you first choose: macro or micro. Then YASAIMAPP suggests names from the matching list, for example "Hauptmotiv" or "Sporen".',
    'Schreib einen Namen pro Zeile. In dieser Reihenfolge stehen die Fotos auch in der Galerie am Fund. Löschst du alle Zeilen einer Liste, schlägt YASAIMAPP dort nichts mehr vor.'
        => 'Write one name per line. The photos stand in this order in the find\'s gallery too. If you delete all lines of a list, YASAIMAPP suggests nothing there any more.',
    'Motivnamen für Makrofotos'
        => 'Subject names for macro photos',
    'Alles, was du am Fund mit Handy oder Kamera fotografierst.'
        => 'Everything you photograph at the find with a phone or a camera.',
    'Motivnamen für Mikrofotos'
        => 'Subject names for micro photos',
    'Alles, was du unter dem Mikroskop fotografierst.'
        => 'Everything you photograph under the microscope.',
    'Mikrofotos'
        => 'Micro photos',
    'Ein Mikrofoto braucht nicht die volle Größe deiner Kamera. YASAIMAPP verkleinert es auf 2048 Pixel an der langen Seite, auch das Eichbild einer neuen Vorlage. Das spart Platz und Zeit beim Hochladen. RAW-Dateien kann der Browser nicht verkleinern. Sie gehen so hoch, wie sie sind.'
        => 'A micrograph does not need the full size of your camera. YASAIMAPP shrinks it to 2048 pixels on the long side, the calibration image of a new preset too. That saves space and upload time. RAW files cannot be shrunk by the browser. They go up as they are.',
    'Deine Vorlagen messen trotzdem richtig, denn YASAIMAPP merkt sich die volle Größe und rechnet um. Du kannst den Haken also jederzeit setzen oder herausnehmen.'
        => 'Your presets still measure correctly, because YASAIMAPP remembers the full size and converts. So you can tick or untick the box at any time.',
    'Mikrofotos vor dem Hochladen verkleinern'
        => 'Shrink micro photos before uploading',
    'Muschelkrebse'
        => 'Ostracods',
    'Museum für Naturkunde Stuttgart (CC-BY 4.0) und Fauna Europaea, DOI 10.15468/ymk1bx (CC-BY-SA 4.0)'
        => 'Stuttgart State Museum of Natural History (CC-BY 4.0) and Fauna Europaea, DOI 10.15468/ymk1bx (CC-BY-SA 4.0)',
    'Museum für Naturkunde Stuttgart und Fauna Europaea'
        => 'Museum für Naturkunde Stuttgart and Fauna Europaea',
    'Muster'
        => 'Pattern',
    'mysql -u BENUTZER -p DATENBANK < karte-JJJJ-MM-TT-hhmm.sql'
        => 'mysql -u USER -p DATABASE < karte-YYYY-MM-DD-hhmm.sql',
    'Nach %s sortieren'
        => 'Sort by %s',
    'Nach dem Speichern ist es keine Artenliste mehr — dann lässt sie sich auch löschen.'
        => 'After saving it is no longer a species list — then it can also be deleted.',
    'Nach ein paar Minuten kommt eine E-Mail mit dem Download. Die .zip-Datei unverändert hier hochladen.'
        => 'After a few minutes an e-mail arrives with the download. Upload the .zip file here unchanged.',
    'Nach Gemeinde filtern'
        => 'Filter by municipality',
    'Nach Messtischblatt filtern'
        => 'Filter by map sheet',
    'Nachname'
        => 'Last name',
    'Nachname *'
        => 'Last name *',
    'Nach Namen — gleiche zusammengefasst'
        => 'By name — same names grouped',
    'Nach Namen — von A bis Z'
        => 'By name — from A to Z',
    'Nach oben'
        => 'Move up',
    'Nachricht'
        => 'Message',
    'Nachricht schreiben …'
        => 'Write a message …',
    'Nachtragen'
        => 'Backfill',
    'Nachtragen (%d offen)'
        => 'Backfill (%d open)',
    'Nachtragen gescheitert: %s'
        => 'Backfill failed: %s',
    'Nach unten'
        => 'Move down',
    'Nach Zeit — die neuesten zuerst'
        => 'By time — newest first',
    'Name'
        => 'Name',
    'Name (als Gedächtnisstütze)'
        => 'Name (as a memory aid)',
    'Name: %s'
        => 'Name: %s',
    'Name der Liste'
        => 'Name of the list',
    'Name der neuen Unterliste'
        => 'Name of the new sublist',
    'Name der Vorlage'
        => 'Name of the preset',
    'Name des Geräts'
        => 'Name of the device',
    'Name oder E-Mail-Adresse'
        => 'Name or e-mail address',
    'Name und E-Mail-Adresse'
        => 'Name and e-mail address',
    'Klicke auf der Karte ein Bundesland an, dann wird es geladen. Klickst du es noch einmal an, ist es wieder vom Gerät gelöscht.'
        => 'Click a state on the map to load it. Click it again and it is removed from the device.',
    '%s ließ sich nicht laden: %s'
        => '%s could not be loaded: %s',
    '%s — wird geladen …'
        => '%s — loading …',
    '%s — nicht auf diesem Gerät. Klicken lädt es.'
        => '%s — not on this device. Click to load it.',
    '%s — %s%s. Klicken löscht es vom Gerät.'
        => '%s — %s%s. Click to remove it from the device.',
    'Stand: %s'
        => 'Version: %s',
    '%s wird geladen …'
        => 'Loading %s …',
    '%s (danach noch: %s)'
        => '%s (then: %s)',
    'Umrisse: © GeoBasis-DE / BKG'
        => 'Outlines: © GeoBasis-DE / BKG',
    'Hier holst du die Schutzgebiete eines Bundeslands aufs Gerät: Nationalparke, Naturschutz- und FFH-Gebiete. Dann siehst du auch im Funkloch, ob du gerade in einem unterwegs bist — samt Namen und Steckbrief.'
        => 'Here you fetch the protected areas of a federal state onto your device: national parks, nature reserves and FFH areas. Then even without reception you see whether you are inside one right now — with its name and profile.',
    'nein — %s'
        => 'no — %s',
    'Neu'
        => 'New',
    'Neu berechnen'
        => 'Recompute',
    'Neu dabei:'
        => 'Newly in:',
    'Neue&nbsp;Liste&nbsp;…'
        => 'New&nbsp;list&nbsp;…',
    'Neue Arbeitsgruppe'
        => 'New working group',
    'Neue Einladung'
        => 'New invitation',
    'Neue Exkursion'
        => 'New excursion',
    'Neue Freigabe'
        => 'New share',
    'Neue Liste'
        => 'New list',
    'Neuenburg'
        => 'Neuchâtel',
    'Neue Punkte holen es beim Anlegen selbst; hier wird es für alle älteren nachgetragen.'
        => 'New points fetch it themselves when created; here it is backfilled for all older ones.',
    'Neue Punkte holen ihre Höhe beim Anlegen selbst. Hier werden sie für alle älteren nachgetragen — in Häppchen von %d Sekunden, mit einer kurzen Pause zwischen den Anfragen. Der Lauf darf jederzeit unterbrochen und wieder gestartet werden.'
        => 'New points fetch their elevation themselves when created. Here it is backfilled for all older ones — in chunks of %d seconds, with a short pause between the requests. The run may be interrupted and restarted at any time.',
    'Neuer Code:'
        => 'New code:',
    'Neues Gebiet'
        => 'New area',
    'Neues Passwort (mindestens 10 Zeichen)'
        => 'New password (at least 10 characters)',
    'Neues Passwort noch einmal'
        => 'New password once more',
    'Neue Tour'
        => 'New tour',
    'Neue Tour — beginnt mit diesem Punkt.'
        => 'New tour — begins with this point.',
    'Neue Liste …'
        => 'New list …',
    'Neue Tour …'
        => 'New tour …',
    'Nicht abgeschickt'
        => 'Not submitted',
    'nicht angekommen.'
        => 'did not arrive.',
    'Nicht angemeldet.'
        => 'Not signed in.',
    'nicht auf diesem Gerät'
        => 'not on this device',
    'nicht bewertet'
        => 'not evaluated',
    ' Nicht in den Filter kommen %s — eine Autoliste führt nur eigene Punkte aus gewöhnlichen Listen.'
        => ' Not part of the filter: %s — an auto list carries only your own points from ordinary lists.',
    'Nicht lesbar: %s'
        => 'Not readable: %s',
    'Nicht mehr für diese Exkursion kartieren'
        => 'Stop mapping for this excursion',
    'Nichts ausgewählt.'
        => 'Nothing selected.',
    'Nichts entfernt — die Punkte gibt es nicht mehr.'
        => 'Nothing removed — the points no longer exist.',
    'Nichts gefunden'
        => 'Nothing found',
    'Nichts gefunden.'
        => 'Nothing found.',
    'Du hast keine Gruppe gewählt. Dann bekommst du beim Tippen keine Vorschläge.'
        => 'You have chosen no group. Then you get no suggestions while typing.',
    'Nichts geändert.'
        => 'Nothing changed.',
    'Nichts liegt mehr bereit.'
        => 'Nothing is waiting any more.',
    'Nichts offen — alles eingespielt.'
        => 'Nothing open — everything applied.',
    'Nichts zurückgeholt — die Punkte gibt es nicht mehr.'
        => 'Nothing brought back — the points no longer exist.',
    'Nichts zu tun.'
        => 'Nothing to do.',
    'Nichts zu ändern.'
        => 'Nothing to change.',
    'Nichts zu ändern — der Wert stand schon überall so.'
        => 'Nothing to change — the value already stood like that everywhere.',
    'Nichts zu ändern — die Punkte standen schon so.'
        => 'Nothing to change — the points already stood like that.',
    'Nichts zu ändern — diese Tour trägt kein Punkt (mehr).'
        => 'Nothing to change — no point carries this tour (any more).',
    'Nichts zu ändern — die Zuordnung stimmte schon.'
        => 'Nothing to change — the assignment was already right.',
    'Nichts zu übertragen: %s'
        => 'Nothing to transfer: %s',
    'nicht verbunden'
        => 'not connected',
    'Nidwalden'
        => 'Nidwalden',
    'Niedersachsen'
        => 'Lower Saxony',
    'Niederösterreich'
        => 'Lower Austria',
    'Niemand gefunden. Hat die Person schon ein Konto bei YASAIMAPP?'
        => 'Nobody found. Does the person have a YASAIMAPP account yet?',
    'nirgends erfasst'
        => 'covered nowhere',
    'Noch %d Datei.'
        => '%d file left.',
    'Noch %d Dateien.'
        => '%d files left.',
    'Noch %d Dateien — die nächste steht bereit.'
        => '%d files left — the next one is ready.',
    'Noch %d Datei — die nächste steht bereit.'
        => '%d file left — the next one is ready.',
    'noch %d offen'
        => '%d still open',
    '%d fehlen noch, die kommen heute Nacht von selbst.'
        => '%d are still missing, they follow by themselves tonight.',
    '1 fehlt noch, der kommt heute Nacht von selbst.'
        => '1 is still missing, it follows by itself tonight.',
    'Noch bei keinem Punkt bestimmt.'
        => 'Not determined for any point yet.',
    'Noch drei Kleinigkeiten'
        => 'Three little things left',
    'Noch ein Maß in den Ausschnitt?'
        => 'Another measure into the cutout?',
    'Noch einmal prüfen'
        => 'Check once more',
    'Noch einmal versuchen'
        => 'Try again',
    'Du hast noch keine Freunde.'
        => 'You have no friends yet.',
    'Noch keine. Hier stehen die Freigaben, in die dich jemand aufgenommen hat — als Freund oder über eine Arbeitsgruppe.'
        => 'None yet. Here stand the shares somebody admitted you to — as a friend or through a working group.',
    'Noch keine. Über das Plus hängst du eigene Listen ein.'
        => 'None yet. The plus hangs in your own lists.',
    'Noch keine. Über den Knopf „Neu“ legst du eine an.'
        => 'None yet. The “New” button creates one.',
    'Noch keine angelegt. Über den Knopf „Neu“ legst du eine an.'
        => 'None created yet. The “New” button creates one.',
    'Noch keinen angelegt. Über den Knopf „Neu“ legst du einen an.'
        => 'None created yet. The “New” button creates one.',
    'Noch keine Vorlagen.'
        => 'No presets yet.',
    'noch keine — über das Plus aufnehmen'
        => 'none yet — admit via the plus',
    'Noch kein Gebiet angelegt.'
        => 'No area created yet.',
    'kommt später'
        => 'coming later',
    'noch nicht gefragt'
        => 'not yet asked',
    'Noch nichts.'
        => 'Nothing yet.',
    'Noch nichts gemessen.'
        => 'Nothing measured yet.',
    'Noch nicht übertragen'
        => 'Not transferred yet',
    'noch nie'
        => 'never yet',
    'noch nie benutzt'
        => 'never used',
    'noch nie da'
        => 'never been here',
    'Noch zwei Kleinigkeiten'
        => 'Two little things left',
    'Nordrhein-Westfalen'
        => 'North Rhine-Westphalia',
    'Notiz'
        => 'Note',
    'Notiz: %s'
        => 'Note: %s',
    'Notizen'
        => 'Notes',
    'Notizen bearbeiten'
        => 'Edit notes',
    'Nur ansehen'
        => 'View only',
    'nur aus dem Empfänger (±10 m)'
        => 'from the receiver only (±10 m)',
    'Du kannst nur den Namen ändern und speichern. Oder du klickst die Strecke neu an und eichst noch einmal.'
        => 'You can just change the name and save. Or you click the line anew and calibrate once more.',
    'nur der %s'
        => 'only %s',
    'Nur eigene Arbeitsgruppen lassen sich aufnehmen.'
        => 'Only your own working groups can be admitted.',
    'Nur für Exkursionsgäste.'
        => 'For excursion guests only.',
    'nur nicht bestimmte'
        => 'only unidentified ones',
    'Nur POST.'
        => 'POST only.',
    'Nur wer die Freigabe angelegt hat, kann sie umbenennen.'
        => 'Only whoever created the share can rename it.',
    'Nächstes Foto'
        => 'Next photo',
    'Obere Höhe'
        => 'Upper elevation',
    'Oberfläche'
        => 'Appearance',
    'Oberösterreich'
        => 'Upper Austria',
    'Obwalden'
        => 'Obwalden',
    'Oder als Ausschnitt nutzen — bis zu zwei Maße hineinzeichnen:'
        => 'Or use it as a cutout — draw up to two measures inside:',
    'oder klicken — mehrere auf einmal gehen auch'
        => 'or click — several at once work too',
    'Offen'
        => 'Open',
    'Offene Codes'
        => 'Open codes',
    'Offene Einladungen'
        => 'Open invitations',
    'Offline'
        => 'Offline',
    'Offline-Daten'
        => 'Offline data',
    'Offline-Gebiet'
        => 'Offline area',
    'Offline-Gebiete'
        => 'Offline areas',
    'Offline-Karte für die Gäste'
        => 'Offline map for the guests',
    'ohne'
        => 'none',
    'ohne Angabe'
        => 'no details given',
    'Ohne bestimmte Gattung lässt sich kein Ordner bilden.'
        => 'Without a determined genus no folder can be formed.',
    'ohne Blatt (meist außerhalb Deutschlands)'
        => 'without a sheet (mostly outside Germany)',
    'Ohne brauchbare Koordinaten — %d Zeilen'
        => 'Without usable coordinates — %d rows',
    'Ohne brauchbare Koordinaten — eine Zeile'
        => 'Without usable coordinates — one row',
    'Ohne client_id lässt sich die Strecke nicht wiedererkennen.'
        => 'Without client_id the track cannot be recognized again.',
    'Ohne Code kommt niemand herein. Ein Code gilt für eine Person, oder für so viele, wie du beim Erzeugen einträgst.'
        => 'Nobody gets in without a code. A code is for one person, or for as many as you enter when you create it.',
    'Für wie viele Personen?'
        => 'For how many people?',
    'Mehr als 1 heißt: Alle bekommen denselben Link, zum Beispiel in einem Rundbrief. Jede Anmeldung zählt einen Platz ab.'
        => 'More than 1 means: everybody gets the same link, in a circular letter for instance. Each registration takes one place.',
    'Offen, noch %d Plätze'
        => 'Open, %d places left',
    'Offen, noch ein Platz'
        => 'Open, one place left',
    'Ohne Empfang im Wald merkt sich die App deine Funde auf dem Gerät und schickt sie später ab. Damit der Browser diese Daten nicht bei Platzmangel wegräumt, braucht er deine Erlaubnis.'
        => 'Without reception in the woods, the app remembers your finds on the device and sends them later. So that the browser does not clear this data away when space runs low, it needs your permission.',
    'Ohne Freigabe oder Freund gibt es keinen Chat.'
        => 'Without a share or a friend there is no chat.',
    'ohne Fundzeit'
        => 'without a find time',
    'ohne Gemeinde (Meer, Niemandsland)'
        => 'without a municipality (sea, no man\'s land)',
    'ohne Höhe'
        => 'without elevation',
    'Ohne id lässt sich nichts löschen.'
        => 'Without an id nothing can be deleted.',
    'Ohne Liste'
        => 'Without a list',
    'ohne Namen'
        => 'unnamed',
    'Ohne Namen'
        => 'Without a name',
    'Ohne Netz — das Löschen wird nachgereicht.'
        => 'No connection — the deletion will be handed in later.',
    'Ohne Netz — die Änderung wird nachgereicht.'
        => 'No connection — the change will be handed in later.',
    'Ohne Position — %d Einträge'
        => 'Without position — %d entries',
    'Ohne Position — %d Funde'
        => 'Without position — %d finds',
    'Ohne Position — ein Eintrag'
        => 'Without position — one entry',
    'Ohne Position — ein Fund'
        => 'Without position — one find',
    'Ohne Titel'
        => 'Untitled',
    'Ohne Titel geht es nicht — ein Punkt ohne Namen ist ein Niemand.'
        => 'It does not work without a title — a point without a name is a nobody.',
    'Ohne Tour'
        => 'Without a tour',
    'Ohne Treffer in einer Namensliste — %d Funde'
        => 'Without a hit in any name list — %d finds',
    'Ohne Treffer in einer Namensliste — ein Fund'
        => 'Without a hit in any name list — one find',
    'Das Eichbild ist nicht in der Cloud angekommen. Die Vorlage ist nicht gespeichert.'
        => 'The calibration image did not reach the cloud. The preset is not saved.',
    'Ohne Wert bitte „Feld leeren" ankreuzen.'
        => 'Without a value, please tick "Clear field".',
    'Orange'
        => 'Orange',
    'Ordner aus der Datei'
        => 'Folder from the file',
    'Ordner in der Cloud'
        => 'Folder in the cloud',
    'Ordnung'
        => 'Order',
    'Organisieren'
        => 'Organize',
    'Original laden'
        => 'Load original',
    'Original laden — noch einmal versuchen'
        => 'Load original — try again',
    'Original laden · %s'
        => 'Load original · %s',
    'Originalliste einhängen'
        => 'Mount the original list',
    'Originallisten einhängen'
        => 'Mount the original lists',
    'Original wiederhergestellt — auf allen Geräten beim nächsten Abgleich.'
        => 'Original restored — on all devices at the next sync.',
    'Original wiederherstellen?'
        => 'Restore the original?',
    'Original wird geladen …'
        => 'Loading original …',
    'Ort'
        => 'Place',
    'Ort, Koordinate, Plus Code, UTM, Messtischblatt oder Kartenlink'
        => 'Place, coordinate, Plus Code, UTM, map sheet or map link',
    'Ort: %s, %s'
        => 'Place: %s, %s',
    'Ortsmarken'
        => 'Placemarks',
    'Ortsmarken, die es hier schon gibt (gleicher Name, gleiche Stelle), werden nicht doppelt angelegt.'
        => 'Placemarks that already exist here (same name, same spot) are not created twice.',
    'Ortssuche braucht Netz.'
        => 'Place search needs a connection.',
    'Papierkorb'
        => 'Trash',
    'Passwort'
        => 'Password',
    'Passwort (mindestens 10 Zeichen)'
        => 'Password (at least 10 characters)',
    'Dein Passwort ist geändert. Hier bleibst du angemeldet. Auf deinen anderen Geräten musst du dich neu anmelden. Gekoppelte Geräte (unter „Geräte") musst du neu koppeln.'
        => 'Your password is changed. Here you stay signed in. On your other devices you have to sign in again. Paired devices (under "Geräte") have to be paired again.',
    'Passwort wiederholen'
        => 'Repeat password',
    'Passwort ändern'
        => 'Change password',
    'pCloud antwortet nicht. Später noch einmal versuchen.'
        => 'pCloud does not answer. Try again later.',
    'pCloud antwortet unverständlich.'
        => 'pCloud answers unintelligibly.',
    'pCloud gibt für "%s" keine Ordnerkennung zurück.'
        => 'pCloud returns no folder id for "%s".',
    'pCloud gibt kein Token heraus.'
        => 'pCloud hands out no token.',
    'pCloud hat %d von %d Bytes angenommen.'
        => 'pCloud accepted %d of %d bytes.',
    'pCloud hat die Datei nicht angenommen.'
        => 'pCloud did not accept the file.',
    'pCloud ist auf diesem Server nicht eingerichtet.'
        => 'pCloud is not set up on this server.',
    'pCloud nennt keine Adresse für die Vorschau.'
        => 'pCloud names no address for the preview.',
    'pCloud nennt keine Dateikennung.'
        => 'pCloud names no file id.',
    'pCloud nimmt die Datei gerade nicht an.'
        => 'pCloud is not accepting the file right now.',
    'Petrol'
        => 'Petrol',
    'Pflanzen'
        => 'Plants',
    'Pflanzen Deutschlands'
        => 'Plants of Germany',
    'Piemont'
        => 'Piedmont',
    'Pilz'
        => 'Mushroom',
    'Pilze'
        => 'Fungi',
    'Pilze Deutschlands'
        => 'Fungi of Germany',
    'Pilznamen weltweit (GBIF)'
        => 'Fungus names worldwide (GBIF)',
    'Plattwürmer'
        => 'Flatworms',
    'Platz'
        => 'Space',
    'Plus Code %s+%s (%s)'
        => 'Plus Code %s+%s (%s)',
    'Polylinie'
        => 'Polyline',
    'Position'
        => 'Position',
    'Prüfen und verbinden'
        => 'Check and connect',
    'Punkt'
        => 'Point',
    'Punkt an deinem Standort angelegt.'
        => 'Point created at your location.',
    'Punkt an deinem Standort angelegt — ±%s m.'
        => 'Point created at your location — ±%s m.',
    'Punkt an deinem Standort anlegen'
        => 'Create a point at your location',
    'Punkt an der gesuchten Stelle angelegt.'
        => 'Point created at the searched spot.',
    'Punkt an der gesuchten Stelle anlegen'
        => 'Create a point at the searched spot',
    'Punkt angelegt.'
        => 'Point created.',
    'Punkt auswählen'
        => 'Select point',
    'Punkt bearbeiten'
        => 'Edit point',
    'Punkte'
        => 'Points',
    'Punkte %s'
        => '%s points',
    'Punkte, die es hier schon gibt (gleicher Name, gleiche Stelle), werden nicht doppelt angelegt.'
        => 'Points that already exist here (same name, same spot) are not created twice.',
    'Punkte außerhalb der Artenlisten'
        => 'points outside the species lists',
    'Punkte filtern'
        => 'Filter points',
    'Punkte filtern …'
        => 'Filter points …',
    'Punkte in einer Liste, deren Titel Arten sind'
        => 'points in a list whose titles are species',
    'Punkte insgesamt'
        => 'points in total',
    'Punkte liegen immer in der letzten Ebene.'
        => 'Points always live in the last level.',
    'Punkte liegen immer in der letzten Ebene. Bekommt eine Liste mit Punkten ihre erste Unterliste, wandern ihre Punkte dorthin.'
        => 'Points always sit in the last level. When a list with points gets its first sublist, the points move there.',
    'Punkte löschen — in DEINEN Papierkorb, nur du holst sie zurück'
        => 'Delete points — into YOUR trash, only you can bring them back',
    'Punkte mit Blatt'
        => 'points with a sheet',
    'Punkte mit mindestens einer Art'
        => 'points with at least one species',
    'Punkt endgültig entfernt.'
        => 'Point removed for good.',
    'Punkte verschieben'
        => 'Move points',
    'Punkt gespeichert.'
        => 'Point saved.',
    'Punkt hier'
        => 'Point here',
    'Punkt liegt nicht im Papierkorb.'
        => 'Point is not in the trash.',
    'Punkt löschen'
        => 'Delete point',
    'Punkt löschen?'
        => 'Delete point?',
    'Punkt nicht gefunden.'
        => 'Point not found.',
    'Punkt nicht im Papierkorb.'
        => 'Point not in the trash.',
    'Punkt Nr. %s (inzwischen gelöscht)'
        => 'Point no. %s (since deleted)',
    'Punkt setzen'
        => 'Set point',
    'Punkt setzen — danach in die Karte tippen'
        => 'Set point — then tap into the map',
    'Punkt setzen · lange drücken für eine Fläche'
        => 'Set point · long-press for an area',
    'Punktspeicher abgebrochen.'
        => 'Point store aborted.',
    'Punktspeicher ist von einem anderen Tab belegt.'
        => 'The point store is held by another tab.',
    'Punkt zurückgeholt.'
        => 'Point brought back.',
    'QR-Code für Gäste zeigen'
        => 'Show QR code for guests',
    'QR-Code von %s zeigen'
        => 'Show QR code of %s',
    'Quadrantmitte'
        => 'quadrant center',
    'Rand'
        => 'Margin',
    'Rechte brauchen die Migration 0043 — sie ist noch nicht eingespielt.'
        => 'Rights need migration 0043 — it has not been applied yet.',
    'Rechteck'
        => 'Rectangle',
    'Rechte der anderen'
        => 'Rights of the others',
    'Rechte gespeichert.'
        => 'Rights saved.',
    'Rechte speichern'
        => 'Save rights',
    'Rechte vergibt nur, wem die geteilte Liste gehört.'
        => 'Only the owner of the shared list grants rights.',
    'Referenzliste der Deutschen Gesellschaft für Mykologie'
        => 'Reference list of the German Mycological Society (DGfM)',
    'Referenzliste der DGfM und die weltweite GBIF-Liste'
        => 'Reference list of the DGfM and the worldwide GBIF list',
    'Regel hinzufügen'
        => 'Add a rule',
    'Region'
        => 'Region',
    'Reich'
        => 'Kingdom',
    'Reptilien'
        => 'Reptiles',
    'Amphibien'
        => 'Amphibians',
    'Rheinland-Pfalz'
        => 'Rhineland-Palatinate',
    'RL %s'
        => 'RL %s',
    'Rolle'
        => 'Role',
    'Rot'
        => 'Red',
    'Rote Liste'
        => 'Red list',
    'Rote Liste der Farn- und Blütenpflanzen Deutschlands (Metzing u. a. 2018), BfN'
        => 'Red List of the ferns and flowering plants of Germany (Metzing et al. 2018), BfN',
    'Rote Liste der Farn- und Blütenpflanzen Deutschlands, BfN'
        => 'Red List of the ferns and flowering plants of Germany, BfN',
    'Rote Liste der Flechten Deutschlands (Wirth u. a. 2011), BfN; deutsche Namen aus GBIF'
        => 'Red List of the lichens of Germany (Wirth et al. 2011), BfN; German names from GBIF',
    'Rote Liste der Flechten Deutschlands, BfN; deutsche Namen aus GBIF'
        => 'Red List of the lichens of Germany, BfN; German names from GBIF',
    'Rote Liste Deutschlands: %s'
        => 'Red List of Germany: %s',
    'Ruderfußkrebse'
        => 'Copepods',
    'Rädertierchen'
        => 'Rotifers',
    'Verbunden. Der Eimer „%s" bei %s antwortet.'
        => 'Connected. The bucket "%s" at %s answers.',
    'SA'
        => 'SA',
    'Saarland'
        => 'Saarland',
    'Sachsen'
        => 'Saxony',
    'Sachsen-Anhalt'
        => 'Saxony-Anhalt',
    'Sackkiefler'
        => 'Entognathans',
    'Salzburg'
        => 'Salzburg',
    'Sardinien'
        => 'Sardinia',
    'Saugwürmer'
        => 'Flukes',
    'Schaffhausen'
        => 'Schaffhausen',
    'Schiefer'
        => 'Slate',
    'Schirmquallen'
        => 'True jellyfish',
    'Schleswig-Holstein'
        => 'Schleswig-Holstein',
    'Schließen'
        => 'Close',
    'Schlüssel'
        => 'Key',
    'Schlüssel (Secret Key)'
        => 'Secret Key',
    'Schlüsselkennung (Access Key ID)'
        => 'Access Key ID',
    'Schlüssel kopieren'
        => 'Copy key',
    'Schnecke'
        => 'Snail',
    'Schriftgröße der Beschriftung, in Pixeln'
        => 'Size of the lettering, in pixels',
    'Schritt %d von %d'
        => 'Step %d of %d',
    'Schutzgebiet'
        => 'Protected area',
    'Schutzgebiete'
        => 'Protected areas',
    'Schutzgebiete an dieser Stelle — vom Bundesamt für Naturschutz'
        => 'Protected areas at this spot — from the Federal Agency for Nature Conservation',
    'Schutzgebiete werden erst ab Stufe %d geladen, gerade ist Stufe %d.'
        => 'Protected areas are only loaded from level %d on, current level is %d.',
    'Schwyz'
        => 'Schwyz',
    'Sechzehntelmitte'
        => 'sixteenth center',
    'Seeigel'
        => 'Sea urchins',
    'Seesterne'
        => 'Starfish',
    'Sein Punkt wandert in den Papierkorb, die Listen des Imports verschwinden. Punkte, die es schon vorher gab, bleiben unberührt.'
        => 'Its point moves to the trash, the import\'s lists disappear. Points that already existed before stay untouched.',
    'seit %d Minuten'
        => 'for %d minutes',
    'seit %d Stunden'
        => 'for %d hours',
    'seit %d Tagen'
        => 'for %d days',
    'seit 1 Stunde'
        => 'for 1 hour',
    'seit 1 Tag'
        => 'for 1 day',
    'Selbst für %s kartieren'
        => 'Map for %s yourself',
    'Selbst für diese Exkursion kartieren'
        => 'Map for this excursion yourself',
    'Senden'
        => 'Send',
    'Server meldet %s.'
        => 'Server reports %s.',
    'Sie entstand, während das Gerät keine Verbindung hatte. Beim späteren Übertragen hat der Server sie zurückgewiesen — gespeichert ist davon nichts.'
        => 'It was made while the device had no connection. On the later transfer the server rejected it — none of it is saved.',
    'Sie entstanden, während das Gerät keine Verbindung hatte. Beim späteren Übertragen hat der Server sie zurückgewiesen — gespeichert ist davon nichts.'
        => 'They were made while the device had no connection. On the later transfer the server rejected them — none of it is saved.',
    'Sie entsteht örtlich und wird von dort hochgespielt; sie zurückzuholen hieße, stundenlang abzuwarten, um zu bekommen, was man schon hat.'
        => 'It is built locally and uploaded from there; fetching it back would mean waiting for hours to get what you already have.',
    'Sie liegen bewusst nicht im Repository — 19 MB, die sich nie ändern, würden jeden Push und jedes Aufspielen belasten.'
        => 'They deliberately do not live in the repository — 19 MB that never change would weigh down every push and every deployment.',
    'Sie sehen jetzt auch, was du hier eingehängt hast — prüfe, ob deine Listen für diesen Kreis noch die richtigen sind.'
        => 'They now also see what you hung in here — check whether your lists are still the right ones for this circle.',
    'Sie sind auf diesem Gerät gespeichert und gehen von selbst durch, sobald wieder Verbindung da ist — es wird im Hintergrund immer wieder versucht. Du musst nichts tun.'
        => 'They are saved on this device and will go through by themselves as soon as there is a connection again — it keeps being retried in the background. You need to do nothing.',
    'sie stammen aus einer Zeit, in der es für dieses Land noch keine gab, und stehen deshalb mit dem blossen ISO-Rest da (%s statt %s).'
        => 'they stem from a time when there was none for this country yet, and therefore stand with the bare ISO remainder (%s instead of %s).',
    'Sizilien'
        => 'Sicily',
    'SO'
        => 'SU',
    'Sobald du wieder Empfang hast, überträgt sich alles und die Meldung verschwindet. Hilft das nicht, schafft Platz: unter Verwaltung → Offline lassen sich heruntergeladene Kartenausschnitte vom Gerät nehmen.'
        => 'As soon as you have reception again, everything transfers and the message disappears. If that does not help, free up space: under Administration → Offline, downloaded map areas can be removed from the device.',
    'Solange du diese Seite offen lässt, geht es beim nächsten Netz von selbst hoch. Lade sie bitte NICHT neu und schließe sie nicht — dabei ginge es verloren.'
        => 'As long as you leave this page open, it will go up by itself on the next connection. Please do NOT reload it and do not close it — that would lose it.',
    'So lassen'
        => 'Keep as is',
    'Solothurn'
        => 'Solothurn',
    'Sortierung aufheben'
        => 'Clear sorting',
    'Spalten'
        => 'Columns',
    'Spaltenbreite anpassen'
        => 'Adjust column width',
    'Spalten der Tabelle'
        => 'Columns of the table',
    'Spalte „%s" durchsuchen'
        => 'Search column "%s"',
    'Speichern'
        => 'Save',
    'Speichern.'
        => 'Save.',
    'Bitte dein Passwort'
        => 'Your password, please',
    'Speichern erlauben'
        => 'Allow storage',
    'Speichern fehlgeschlagen.'
        => 'Saving failed.',
    'Speichern fehlgeschlagen: %s'
        => 'Saving failed: %s',
    'Speichern nicht möglich: %s — die Eingabe steht noch im Fenster.'
        => 'Saving not possible: %s — your input is still in the window.',
    'Speicherplatz in MB'
        => 'Storage space in MB',
    'Sperren'
        => 'Lock',
    'Später kommt jeder Weitere über den Stift dazu.'
        => 'Anyone else joins later through the pencil.',
    'Spielt die Namensverzeichnisse in die Datenbank dieses Servers. Läuft in Häppchen von %d Sekunden und setzt nach einem Abbruch dort fort, wo die Tabelle aufhört — einfach wieder starten. Gebraucht wird das nur, wenn die Verzeichnisse erneuert wurden.'
        => 'Imports the name directories into this server\'s database. Runs in chunks of %d seconds and after an abort continues where the table ends — simply start again. This is only needed when the directories were renewed.',
    'spielt ein …'
        => 'applying …',
    'Spinne'
        => 'Spider',
    'Spinnentiere'
        => 'Arachnids',
    'Sprache'
        => 'Language',
    'Später'
        => 'Later',
    'St. Gallen'
        => 'St. Gallen',
    'Staat, Landesteil und Gemeinde zu jedem Punkt — dasselbe Schema, nach dem die Belegfotos auf der Festplatte liegen (%s).'
        => 'Country, region and municipality for every point — the same scheme by which the voucher photos lie on the disk (%s).',
    'Staat / Landesteil / Gemeinde — das Ablageschema der Belegfotos'
        => 'Country / region / municipality — the filing scheme of the voucher photos',
    'Stahlblau'
        => 'Steel Blue',
    'Stamm'
        => 'Phylum',
    'Stand beim Anbieter: %s'
        => 'Provider\'s version: %s',
    'Stand dieser Anlage, über alle Konten.'
        => 'State of this installation, across all accounts.',
    'Standort'
        => 'Location',
    'Standort-Probe'
        => 'Location probe',
    'Standortanzeige ausschalten'
        => 'Switch location display off',
    'Standort auf ±%d m genau.'
        => 'Location accurate to ±%d m.',
    'Standort auf ±%d m — wird noch schärfer …'
        => 'Location at ±%d m — still sharpening …',
    'Standort bleibt bei ±%d m.'
        => 'Location stays at ±%d m.',
    'Standort dauerhaft anzeigen'
        => 'Show location continuously',
    'Standort erlauben'
        => 'Allow location',
    'Standort ist gerade nicht zu ermitteln.'
        => 'The location cannot be determined right now.',
    'Standort nicht verfügbar.'
        => 'Location not available.',
    'Standort wird bestimmt …'
        => 'Determining location …',
    'Standort wird neu bestimmt — die Markierung ist noch die von vorhin.'
        => 'Redetermining location — the mark is still the one from before.',
    'Standortzugriff wurde abgelehnt.'
        => 'Location access was denied.',
    'Standort · ±%s m'
        => 'Location · ±%s m',
    'stark gefährdet'
        => 'endangered',
    'Statistik'
        => 'Statistics',
    'Statistik ein- und ausklappen'
        => 'Toggle statistics',
    'Status'
        => 'Status',
    'Steiermark'
        => 'Styria',
    'Stelle aus dem Papierkorb. Die Markierung ist nicht gespeichert.'
        => 'Spot from the trash. The mark is not saved.',
    'Stoppen'
        => 'Stop',
    'Strecke'
        => 'Distance',
    'Die Strecke ist %s Pixel lang. Stimmt die wahre Länge oben, speichere die Vorlage.'
        => 'The line is %s pixels long. If the true length above is right, save the preset.',
    'Strecke messen'
        => 'Measure distance',
    'Substrat / Wirt'
        => 'Substrate / host',
    'Suche leeren'
        => 'Clear search',
    'Suchen …'
        => 'Search …',
    'Suche …'
        => 'Search …',
    'Sucht in Name, Tag und Gemeinde'
        => 'Searches name, day and municipality',
    'Systematik'
        => 'Systematics',
    'Säugetiere'
        => 'Mammals',
    'Südtirol'
        => 'South Tyrol',
    'Tabelle'
        => 'Table',
    'Tag'
        => 'day',
    'Taupe'
        => 'Taupe',
    'Taxonomie einspielen'
        => 'Load taxonomy',
    'Tempo'
        => 'Speed',
    'Tessin'
        => 'Ticino',
    'Text'
        => 'Text',
    'Text auf dem Bild'
        => 'Text on the picture',
    'Text schreiben'
        => 'Write text',
    'Hintergrund' => 'Background',
    'Thurgau'
        => 'Thurgau',
    'Thüringen'
        => 'Thuringia',
    'Tirol'
        => 'Tyrol',
    'Titel'
        => 'Title',
    'Titel-Regeln — alle müssen zutreffen'
        => 'Title rules — all must match',
    'Titel: %s'
        => 'Title: %s',
    'Titel bearbeiten'
        => 'Edit the title',
    'Titel des Punktes'
        => 'Title of the point',
    'Titel enthält nicht „%s“'
        => 'title does not contain "%s"',
    'Titel enthält „%s“'
        => 'title contains "%s"',
    'Toskana'
        => 'Tuscany',
    'Tour'
        => 'Tour',
    'Tour %s bearbeiten'
        => 'Edit tour %s',
    'Tour&nbsp;…'
        => 'Tour&nbsp;…',
    'Tour bearbeiten'
        => 'Edit tour',
    'Touren'
        => 'Tours',
    'Touren und Exkursionen durchsuchen'
        => 'Search tours and excursions',
    'Tour löschen?'
        => 'Delete tour?',
    'Tour setzen'
        => 'Set tour',
    'Tour umbenannt — %d Punkte tragen jetzt „%s".'
        => 'Tour renamed — %d points now carry "%s".',
    'Tour umbenannt — 1 Punkt trägt jetzt „%s".'
        => 'Tour renamed — 1 point now carries "%s".',
    'Tour umbenennen'
        => 'Rename tour',
    'Tour zusammenführen'
        => 'Merge tour',
    'Tour „%s"'
        => 'Tour "%s"',
    'Zur Sicherheit: Gib dein Passwort ein. Dann werden Name und E-Mail-Adresse gespeichert.'
        => 'For safety: enter your password. Then name and e-mail address are saved.',
    'Trennen'
        => 'Disconnect',
    'Trentino'
        => 'Trentino',
    'Trentino-Südtirol'
        => 'Trentino-South Tyrol',
    'Trotzdem anwenden'
        => 'Apply anyway',
    'Umbenannt.'
        => 'Renamed.',
    'Umbenannt — auch die Liste des Imports heisst jetzt so.'
        => 'Renamed — the import\'s list now carries that name too.',
    'Umbenennen'
        => 'Rename',
    'Umbrien'
        => 'Umbria',
    'Umrandung gespeichert. Karten für neue Flächenteile holst du über „Herunterladen" — vorhandene Kacheln bleiben.'
        => 'Outline saved. You fetch maps for new parts of the area via "Download" — existing tiles stay.',
    'Umriss entfernen'
        => 'Remove outline',
    'Umriss entfernt — noch nicht gespeichert.'
        => 'Outline removed — not saved yet.',
    'Umriss gezogen — %s. Noch nicht gespeichert.'
        => 'Outline drawn — %s. Not saved yet.',
    'Umriss neu ziehen'
        => 'Redraw outline',
    'Umriss ziehen'
        => 'Draw outline',
    'unbegrenzt'
        => 'unlimited',
    'unbegrenzter Platz'
        => 'unlimited space',
    'Unbekannte Aktion.'
        => 'Unknown action.',
    'Unbekannte Aktion „%s" — das ist ein Fehler im Programm, nicht in deiner Eingabe. Es wurde nichts geändert.'
        => 'Unknown action "%s" — that is an error in the program, not in your input. Nothing was changed.',
    'Unbekannte Art.'
        => 'Unknown kind.',
    'Unbekannte Art: %s'
        => 'Unknown kind: %s',
    'Unbekannte Messart.'
        => 'Unknown measure type.',
    'Unbekannter Anbieter.'
        => 'Unknown provider.',
    'Unbekannte Regelart.'
        => 'Unknown rule type.',
    'unbearbeitet'
        => 'not worked up',
    'unbekannter Fehler'
        => 'unknown error',
    'unbekannter Grund'
        => 'unknown reason',
    'Unbekanntes Paket.'
        => 'Unknown package.',
    'Unbekanntes Paket: %s'
        => 'Unknown package: %s',
    'Unbekanntes Recht: %s'
        => 'Unknown right: %s',
    'Unbekannte Tabelle.'
        => 'Unknown table.',
    'Unbenanntes Bild'
        => 'Unnamed image',
    'unbestimmt'
        => 'undetermined',
    'Unbrauchbare Eichung.'
        => 'Unusable calibration.',
    'Unbrauchbare Messungen.'
        => 'Unusable measurements.',
    'und'
        => 'and',
    'und …'
        => 'and …',
    'ungefährdet'
        => 'least concern',
    'unsicher'
        => 'uncertain',
    'Untere Höhe'
        => 'Lower elevation',
    'Unterliste anlegen'
        => 'Create sublist',
    'Unterlisten'
        => 'Sublists',
    'Unterlisten einklappen'
        => 'Collapse sublists',
    'Unterlisten zeigen'
        => 'Show sublists',
    'Unterliste unter „%s" anlegen'
        => 'Create sublist under "%s"',
    'Unter „Neue Ausfuhr erstellen" erst <strong>„Auswahl aufheben"</strong> — sonst kommt das ganze Google-Leben als Archiv.'
        => 'Under "Create new export", first <strong>"Deselect all"</strong> — otherwise your whole Google life arrives as an archive.',
    'Uri'
        => 'Uri',
    'UTM Zone %s%s'
        => 'UTM zone %s%s',
    'Venetien'
        => 'Veneto',
    'Verbinden'
        => 'Connect',
    'Nach „Verbinden" fragt dich Google, ob YASAIMAPP an deinen Speicher darf. Sag ja. Dann kommst du hierher zurück, und alles ist fertig. YASAIMAPP sieht dort nur einen eigenen Ordner, nicht deine anderen Dateien.'
        => 'After "Connect", Google asks you whether YASAIMAPP may use your storage. Say yes. Then you come back here, and everything is done. There YASAIMAPP sees only a folder of its own, not your other files.',
    'Nach „Verbinden" fragt dich Microsoft, ob YASAIMAPP an deinen Speicher darf. Sag ja. Dann kommst du hierher zurück, und alles ist fertig. YASAIMAPP sieht dort nur einen eigenen Ordner unter „Apps", nicht deine anderen Dateien.'
        => 'After "Connect", Microsoft asks you whether YASAIMAPP may use your storage. Say yes. Then you come back here, and everything is done. There YASAIMAPP sees only a folder of its own under "Apps", not your other files.',
    'Nach „Verbinden" fragt dich pCloud, ob YASAIMAPP an deinen Speicher darf. Sag ja. Dann kommst du hierher zurück, und alles ist fertig.'
        => 'After "Connect", pCloud asks you whether YASAIMAPP may use your storage. Say yes. Then you come back here, and everything is done.',
    'Getrennt. Deine Dateien in der Cloud sind noch da.'
        => 'Disconnected. Your files in the cloud are still there.',
    'Verbindung prüfen'
        => 'Check connection',
    'Verbindung trennen?'
        => 'Disconnect?',
    'verbunden'
        => 'connected',
    'verbunden als %s'
        => 'connected as %s',
    'Vergrößern'
        => 'Zoom in',
    'Verkleinern'
        => 'Zoom out',
    'Verlassen'
        => 'Leave',
    'Verordnung'
        => 'Ordinance',
    'verschieben'
        => 'move',
    'Verschieben'
        => 'Move',
    'verschiedene Blätter'
        => 'distinct sheets',
    'verschiedene Gattungen'
        => 'distinct genera',
    'verschiedene Gemeinden'
        => 'distinct municipalities',
    'verschiedene Namen'
        => 'distinct names',
    'Verschoben.'
        => 'Moved.',
    'Verstanden'
        => 'Got it',
    'Verteilung'
        => 'Distribution',
    'Höhe der Verteilung ziehen – Doppelklick: volle Größe'
        => 'Drag the height of the distribution – double click: full size',
    'Höhe der Verteilung'
        => 'Height of the distribution',
    'Verwaltung'
        => 'Administration',
    'Verwaltung → Import holt Punkte aus anderen Anwendungen. Datei auf die Fläche ziehen — mehrere auf einmal gehen auch —, dann zeigt die Vorschau, was ankäme. <strong>Jeder Import wird eine eigene Liste</strong> und lässt sich als Ganzes wieder zurücknehmen.'
        => 'Administration → Import fetches points from other applications. Drag a file onto the surface — several at once work too —, then the preview shows what would arrive. <strong>Each import becomes a list of its own</strong> and can be taken back as a whole.',
    'Verwaltung → Organisieren zeigt dieselben Punkte als Tabelle: mehrere auf einmal auswählen, verschieben, umhängen, löschen. Unter „Touren" stehen die Ausflüge, unter „Autolisten" Listen, die sich selbst füllen — sie merken sich einen Filter statt einer Auswahl.'
        => 'Administration → Organize shows the same points as a table: select several at once, move, rehang, delete. Under "Tours" stand the outings, under "Autolists" lists that fill themselves — they remember a filter instead of a selection.',
    'Verwerfen'
        => 'Discard',
    'Verworfen — alle %d Dateien, nichts wurde eingespielt.'
        => 'Discarded — all %d files, nothing was imported.',
    'Verworfen — nichts wurde eingespielt.'
        => 'Discarded — nothing was imported.',
    'Vielborster'
        => 'Polychaetes',
    'Vielfüßer'
        => 'Millipede',
    'Vierundsechzigstelmitte'
        => 'sixty-fourth center',
    'volle Breite (180 mm)'
        => 'full width (180 mm)',
    'vom %s'
        => 'from %s',
    'vom Aussterben bedroht'
        => 'critically endangered',
    'Vom Gerät löschen'
        => 'Delete from device',
    'von'
        => 'from',
    'Von'
        => 'From',
    'von %s'
        => 'by %s',
    'von %s · %s → %s'
        => 'by %s · %s → %s',
    'Von anderen'
        => 'From others',
    'deine Anfrage, noch ohne Antwort'
        => 'your request, no answer yet',
    'Von Hand'
        => 'By hand',
    'Die Kopie ist kein Schutz vor versehentlichem Löschen. Dafür gibt es den Papierkorb: Dort bleibt jeder gelöschte Fund liegen, bis du ihn endgültig entfernst.'
        => 'The copy is no protection against deleting by mistake. That is what the trash is for: every deleted find stays there until you remove it for good.',
    'Von selbst weitermachen, bis alle erfasst sind'
        => 'Continue by itself until all are recorded',
    'Von selbst weitermachen, bis alle geprüft sind'
        => 'Continue by itself until all are checked',
    'Von selbst weitermachen, bis alles drin ist'
        => 'Continue by itself until everything is in',
    'Vor- und Nachname sind Pflicht.'
        => 'First and last name are required.',
    'Vor- und Nachname sind Pflicht: Der Name steht als Finder an deinen Funden.'
        => 'First and last name are required: the name stands as finder on your finds.',
    'Vor- und Nachname sind Pflicht — sie stehen später als Finder am Fund.'
        => 'First and last name are required — they will later stand as the finder on the find.',
    'Vor- und Nachname werden für deine Punkte als Finder eingetragen. Wenn du deine E-Mail-Adresse änderst, ist das dein neuer Login-Name. Bitte bedenke das.'
        => 'First and last name are recorded as the finder on your points. If you change your e-mail address, that is your new login name. Please keep that in mind.',
    'Vor- und Nachname — und, falls angegeben, die E-Mail-Adresse — werden bei uns gespeichert und dauerhaft als Finder mit deinen Funden verbunden. Fotos, die du hier hochlädst, dürfen von der Exkursionsleitung frei verwendet und weitergegeben werden, etwa für Fundberichte und Belege. Mit dem Mitmachen erklärst du dich mit beidem einverstanden.'
        => 'First and last name — and, if given, the e-mail address — are stored with us and permanently tied to your finds as finder. Photos you upload here may be freely used and passed on by the excursion leaders, for find reports and vouchers, for instance. By joining in you consent to both.',
    'Vorarlberg'
        => 'Vorarlberg',
    'Vorgaben'
        => 'Presets',
    'Vorhandene Blätter werden überschrieben und neu berechnet.'
        => 'Existing sheets are overwritten and recomputed.',
    'Voriges Foto'
        => 'Previous photo',
    'Vorlage bearbeiten'
        => 'Edit preset',
    'Vorlage gelöscht. Fotos, die sie schon bekommen haben, behalten ihren Maßstab.'
        => 'Preset deleted. Photos that already got it keep their scale.',
    'Vorlage gespeichert.'
        => 'Preset saved.',
    'Vorlage löschen'
        => 'Delete preset',
    'Vorlage löschen?'
        => 'Delete preset?',
    'Vorlage neu geeicht.'
        => 'Preset recalibrated.',
    'Vorlage speichern'
        => 'Save preset',
    'Vorlage umbenannt.'
        => 'Preset renamed.',
    'Vorletzte Tour'
        => 'Tour before last',
    'Vorname'
        => 'First name',
    'Vorname *'
        => 'First name *',
    'Vorschaubild'
        => 'Preview image',
    'Vorschaugröße (28 mm)'
        => 'Preview size (28 mm)',
    'Vorwarnliste'
        => 'near threatened',
    'Vögel'
        => 'Birds',
    'Waadt'
        => 'Vaud',
    'wachsend — auch was du dort später hinzufügst, sehen die Mitglieder'
        => 'growing — the members also see what you add there later',
    'Wahre Länge der Strecke'
        => 'True length of the line',
    'Wallis'
        => 'Valais',
    'Warmlicht'
        => 'Warm Light',
    'warten'
        => 'waiting',
    'Warten auf Übertragung'
        => 'Waiting for transfer',
    'wartet'
        => 'waiting',
    'WAS DRIN STEHT'
        => 'WHAT IS IN HERE',
    'Was mit der Auswahl geschehen soll.'
        => 'What should happen with the selection.',
    'WAS NICHT DRIN STEHT'
        => 'WHAT IS NOT IN HERE',
    'Aus welchen Gruppen kommen die Vorschläge?'
        => 'Which groups do the suggestions come from?',
    'Was tun?'
        => 'What to do?',
    'Web-Karten, QGIS und Konverter: uMap, geojson.io (.geojson)'
        => 'Web maps, QGIS and converters: uMap, geojson.io (.geojson)',
    'Weder Eichung noch Messungen noch Titel angegeben.'
        => 'Neither calibration nor measurements nor title given.',
    'Weg'
        => 'Method',
    'Wege und Schneisen'
        => 'Paths and firebreaks',
    'Wegpunkte, die es hier schon gibt (gleicher Name, gleiche Stelle), werden nicht doppelt angelegt.'
        => 'Waypoints that already exist here (same name, same spot) are not created twice.',
    'Wegstrecken brauchen die Migration 0084 — sie ist noch nicht eingespielt.'
        => 'Tracks need migration 0084 — it has not been applied yet.',
    'Weichtiere'
        => 'Molluscs',
    'Weiter'
        => 'Next',
    'weitere'
        => 'further',
    'Weitere %d zeigen'
        => 'Show %d more',
    'Weitere Leiter'
        => 'Further leaders',
    'Weiterer Bereich …'
        => 'Another area …',
    'Welche Anfrage?'
        => 'Which request?',
    'Welche Angaben?'
        => 'Which details?',
    'Welche Ebenen?'
        => 'Which layers?',
    'Welche Formate gehen und wie du an die Daten kommst.'
        => 'Which formats work and how you get at the data.',
    'Welche Formate gehen — und wie komme ich an die Daten?'
        => 'Which formats work — and how do I get the data?',
    'Welche Tour? Der Name fehlt.'
        => 'Which tour? The name is missing.',
    'weltweites Modell (±5 m)'
        => 'worldwide model (±5 m)',
    'Wenigborster'
        => 'Oligochaetes',
    'Wer'
        => 'Who',
    'Wer diesen Code scannt oder den Link öffnet, kann ohne Konto mitmachen — schon vorher, um sich die Karte aufs Handy zu laden. Funde gehen im Zeitfenster vom %s bis %s; danach führt der Link nur noch auf eine Abschlussseite.'
        => 'Whoever scans this code or opens the link can take part without an account — ahead of time too, to load the map onto the phone. Finds go in within the window from %s to %s; after that the link only leads to a closing page.',
    'Wer du bist'
        => 'Who you are',
    'Wer hier ist und was er darf. Herein kommt man nur über eine Einladung — die stellst du weiter oben aus, und dort wird auch schon festgelegt, mit welcher Rolle jemand ankommt.'
        => 'Who is here and what they may do. One only gets in via an invitation — you issue it further up, and that is also where the role somebody arrives with is set.',
    'Wer liest mit'
        => 'Who can read this',
    'Wer sie wirklich neu braucht, nimmt %s und den Reiter „Taxonomie einspielen".'
        => 'Whoever really needs it anew takes %s and the "Import taxonomy" tab.',
    'Werkzeug'
        => 'Tool',
    'Wert für alle gewählten'
        => 'Value for all chosen',
    'Wer unter <em>Mein Bereich → Cloud</em> einen eigenen Speicher verbunden hat, bekommt dort auch seine Funde abgelegt: im Ordner <code>Datensicherung</code> (bei Google Drive und S3 in einem Ordner <code>YASAIMAPP</code>, dessen Namen du dort ändern kannst), eine Tabelle je Monat („<code>Funde 2026-08.csv</code>"). Das läuft <strong>einmal in der Nacht</strong> und von selbst — geschrieben wird nur, was sich seit dem letzten Mal geändert hat, ein alter Monat wandert also nicht jede Nacht aufs Neue hinauf. Der Knopf <em>Jetzt sichern</em> nimmt einen Durchgang vorweg.'
        => 'Whoever has connected a storage of their own under <em>My area → Cloud</em> also gets their finds deposited there: in the folder <code>Datensicherung</code> (at Google Drive and S3 inside a folder <code>YASAIMAPP</code> whose name you can change there), one table per month ("<code>Funde 2026-08.csv</code>"). This runs <strong>once at night</strong> and by itself — only what has changed since last time is written, so an old month does not travel up anew every night. The button <em>Back up now</em> takes one pass in advance.',
    'WIEDER EINLESEN'
        => 'READING THEM BACK IN',
    'Wiederherstellen'
        => 'Restore',
    'Wie in die Freigabe?'
        => 'How into the share?',
    'Wie lang ist die gezogene Strecke wirklich?'
        => 'How long is the drawn line really?',
    'Hier stellst du ein, wie der Maßstab und deine Messlinien auf einem Foto aussehen.'
        => 'Here you set how the scale and your measuring lines look on a photo.',
    'Wien'
        => 'Vienna',
    'wird geholt …'
        => 'fetching …',
    'Wird geladen …'
        => 'Loading …',
    'wird gewogen …'
        => 'weighing …',
    'wird nachgesehen …'
        => 'checking …',
    'wird nicht gefragt'
        => 'is not asked',
    'Wird noch einmal versucht …'
        => 'Trying once more …',
    'Wird noch gespeichert — einen Augenblick.'
        => 'Still saving — one moment.',
    'wird noch gewogen'
        => 'still being weighed',
    'Wird übertragen, sobald Verbindung da ist'
        => 'Will be transferred as soon as there is a connection',
    'Wird übertragen, sobald Verbindung da ist.'
        => 'Will be transferred as soon as there is a connection.',
    'Wissenschaftliche Namen aus Europa, deutsche Namen wo es welche gibt.'
        => 'Scientific names from Europe, German names where they exist.',
    'Wohin?'
        => 'Where to?',
    'Wohin es geht, was zu erwarten ist — der Text steht auf der Beitrittsseite.'
        => 'Where it goes, what to expect — this text stands on the join page.',
    'YASAIMAPP einrichten'
        => 'Set up YASAIMAPP',
    'z. B. 10x'
        => 'e.g. 10x',
    'z. B. 100'
        => 'e.g. 100',
    'z. B. Altbestand vom alten Handy'
        => 'e.g. old records from the old phone',
    'z. B. CW-JJJJ-NNNN'
        => 'e.g. CW-JJJJ-NNNN',
    'z. B. Herbstexkursion Nordhang'
        => 'e.g. Autumn excursion north slope',
    'z. B. Kartierung Nordhang'
        => 'e.g. North slope survey',
    'z. B. Kartierung Nordhang 2026'
        => 'e.g. North slope survey 2026',
    'z. B. Ben aus dem Wanderverein'
        => 'e.g. Ben from the hiking club',
    'z. B. Urlaub Dolomiten'
        => 'e.g. Dolomites vacation',
    // The photo window's tools in groups, and the cutout as a tool of its own (photowindow.js; 04.10.2026)
    'gerade Länge'
        => 'straight length',
    'Länge × Breite'
        => 'Length × breadth',
    'z. B. Sporen'
        => 'e.g. spores',
    'Gebogene Länge'
        => 'Curved length',
    'z. B. gebogener Ascus, allantoide Spore'
        => 'e.g. a curved ascus, an allantoid spore',
    '+ Neue Beschriftungsebene …'
        => '+ New labelling layer …',
    'Neue Beschriftungsebene'
        => 'New labelling layer',
    'Wie soll die Ebene heißen? Auf jeder Ebene liegen eigene Texte, Pfeile und Rahmen. In der Tafel wählst du später, welche Ebene ein Bild zeigt.'
        => 'What should the layer be called? Each layer holds its own texts, arrows and boxes. On the plate you choose later which layer a picture shows.',
    'Beschriften'
        => 'Label',
    'Beschriften und Ausschneiden'
        => 'Label and cut out',
    'Die Ausschnitte stehen oben in ihren eigenen Reitern, einer für jeden Namen.'
        => 'The cutouts stand in their own tabs above, one for each name.',
    'Noch kein Ausschnitt. Wähle „Bildausschnitt" und setze vier Punkte.'
        => 'No cutout yet. Choose “Picture cutout” and set four points.',
    'Ausschnitte umbenennen'
        => 'Rename cutouts',
    'Wie sollen diese Ausschnitte heißen? Alle Ausschnitte mit diesem Namen bekommen den neuen Namen, auf allen Bildern des Funds und auf den Tafeln. Nimmst du einen Namen, den es schon gibt, stehen beide in einer Reihe.'
        => 'What should these cutouts be called? Every cutout with this name gets the new name, on all pictures of the find and on the plates. If you pick a name that already exists, both stand in one row.',
    'Wie sollen diese Ausschnitte heißen? Alle Ausschnitte mit diesem Namen bekommen den neuen Namen, auf allen Bildern des Funds und auf den Tafeln.'
        => 'What should these cutouts be called? Every cutout with this name gets the new name, on all pictures of the find and on the plates.',
    'Einige dieser Ausschnitte liegen auf Bildern, die du nicht ändern darfst. Deshalb wurde nichts umbenannt.'
        => 'Some of these cutouts lie on pictures you may not change. So nothing was renamed.',
    'Die Ausschnitte heißen jetzt „%s", aber die Tafeln ließen sich nicht anpassen. Lege die Reihe dort neu an.'
        => 'The cutouts are now called “%s”, but the plates could not be adjusted. Lay the row down there again.',
    'Umbenannt. Die Ausschnitte heißen jetzt „%s".'
        => 'Renamed. The cutouts are now called “%s”.',
    'Breite messen'
        => 'Measure breadth',
    'Reihenfolge'
        => 'Order',
    'Bildleiste ein- und ausklappen'
        => 'Toggle picture strip',
    'Ohne Motivnamen'
        => 'Without subject name',
    'Ebenen dieses Bildes'
        => 'Layers of this picture',
    'Vorlagen von %s'
        => 'Presets of %s',
    'Vorlagen des Fotografen'
        => 'The photographer\'s presets',
    'Nur zum Anschauen: Was du hier misst oder als Maßstab wählst, gilt nur in deinem Fenster und wird nicht gespeichert.'
        => 'Only to look at: what you measure here or choose as a scale applies only in your window and is not saved.',
    'Am %s aus %s umgezogen: %d Dateien, %d sind dort geblieben. In %s liegt noch alles wie vorher. Den Ordner dort kannst du selbst löschen.'
        => 'On %s moved from %s: %d files, %d stayed there. Everything in %s is still as before. You can delete the folder there yourself.',
    'Am %s aus %s umgezogen: %d Dateien. In %s liegt noch alles wie vorher. Den Ordner dort kannst du selbst löschen.'
        => 'On %s moved from %s: %d files. Everything in %s is still as before. You can delete the folder there yourself.',
    '1 weitere Datei'
        => '1 more file',
    '%d weitere Dateien'
        => '%d more files',
    'Alles, was YASAIMAPP in %s abgelegt hat, zieht in die neue Cloud um: %s, zusammen %s.'
        => 'Everything YASAIMAPP laid into %s moves to the new cloud: %s, %s in all.',
    'Alles, was YASAIMAPP in %s abgelegt hat, zieht in die neue Cloud um: %s.'
        => 'Everything YASAIMAPP laid into %s moves to the new cloud: %s.',
    'Umzug abgeschlossen. Alle Dateien liegen jetzt in %s.'
        => 'Move finished. All files are now in %s.',
    'Umzug abgeschlossen. %d Dateien sind in %s geblieben.'
        => 'Move finished. %d files stayed in %s.',
    '%d von 1 Datei ist umgezogen'
        => '%d of 1 file has moved',
    '%d von %d Dateien sind umgezogen'
        => '%d of %d files have moved',
    'Umzug fertig. Die Datei liegt jetzt in %s.'
        => 'Move done. The file is now in %s.',
    'Umzug fertig. Alle %d Dateien liegen jetzt in %s.'
        => 'Move done. All %d files are now in %s.',
    'Datei bei „%s"'
        => 'File at “%s”',
    'Datei bei einem Fund ohne Namen'
        => 'File at a find without a name',
    'Fotos, Sequenzen oder Dateien'
        => 'Photos, sequences or files',
    'Dateien'
        => 'Files',
    'Weitere Dateien zum Fund, zum Beispiel Notizen, PDFs oder RAW-Bilder. Sie liegen in deiner Cloud beim Fund und ziehen mit ihm um. Nur du siehst sie.'
        => 'More files for the find, for example notes, PDFs or RAW pictures. They lie in your cloud with the find and move along with it. Only you see them.',
    'Dateien oder Ordner hierher ziehen'
        => 'Drag files or folders here',
    'Dateien hochladen'
        => 'Upload files',
    'Ordner hochladen'
        => 'Upload folder',
    'Die Dateien brauchen die Migration 0153 — sie ist noch nicht eingespielt.'
        => 'The files need migration 0153 — it has not been applied yet.',
    'Die Datei gibt es hier nicht.'
        => 'This file does not exist here.',
    'Die Cloud dieser Datei ist nicht mehr verbunden.'
        => 'The cloud of this file is no longer connected.',
    'Die Datei ist in der Cloud nicht angekommen.'
        => 'The file did not arrive in the cloud.',
    'Diese Datei ist kein Text.'
        => 'This file is not text.',
    'Die Vorschau zeigt Texte bis %s — diese Datei ist größer. Lade sie herunter.'
        => 'The preview shows texts up to %s — this file is larger. Download it.',
    'Die Datei liegt nicht mehr in deiner Cloud.'
        => 'The file is no longer in your cloud.',
    '%d Dateien'
        => '%d files',
    '1 Datei'
        => '1 file',
    'Ordner löschen'
        => 'Delete folder',
    'Der Ordner „%s" und die %d Dateien darin werden aus deiner Cloud gelöscht.'
        => 'The folder “%s” and the %d files in it will be deleted from your cloud.',
    'In Google Drive öffnen'
        => 'Open in Google Drive',
    '„%s" wird aus deiner Cloud gelöscht.'
        => '“%s” will be deleted from your cloud.',
    'pCloud gibt die Datei nicht heraus.'
        => 'pCloud does not hand out the file.',
    'Die Cloud nennt keinen Weg zur Datei.'
        => 'The cloud names no way to the file.',
    'Löschen?'
        => 'Delete?',
    'Gelöscht.'
        => 'Deleted.',
    '%d Dateien gelöscht.'
        => '%d files deleted.',
    '%d von %d: %s'
        => '%d of %d: %s',
    '%d von %d Dateien gingen nicht hoch: %s'
        => '%d of %d files did not upload: %s',
    'Hochgeladen.'
        => 'Uploaded.',
    '%d Dateien hochgeladen.'
        => '%d files uploaded.',
    'Pfeil'
        => 'Arrow',
    'Pfeil zeichnen'
        => 'Draw an arrow',
    'direkt ins Bild schreiben'
        => 'write straight into the picture',
    'auf etwas zeigen'
        => 'point at something',
    'Größe %s'
        => 'Size %s',
    'Stärke %s'
        => 'Strength %s',
    'Rahmen'
        => 'Frame',
    'Rahmen in fester Größe'
        => 'Frame of a fixed size',
    'Ein Rahmen braucht Breite und Höhe.'
        => 'A frame needs a width and a height.',
    'gebogen'
        => 'bent',
    'klassisch'
        => 'classic',
    'Entlang der gezeichneten Linie gemessen.'
        => 'Measured along the drawn line.',
    'Von Ende zu Ende in gerader Linie — so messen es die meisten Bücher.'
        => 'From end to end in a straight line — the way most books measure it.',
    'Welche Ebene?'
        => 'Which layer?',
    'Länge klassisch gemessen, gerade von Ende zu Ende'
        => 'Length measured the classic way, straight from end to end',
    'Länge gebogen gemessen, entlang der Linie'
        => 'Length measured bent, along the line',
    'Merkmal:'
        => 'Mark:',
    'Merkmal dieser Messung:'
        => 'Mark of this measure:',
    '+ eigenes'
        => '+ own',
    'z. B. Makrozystide'
        => 'e.g. macrocystidium',
    'Eigenes Merkmal'
        => 'Own mark',
    'Alle %d'
        => 'All %d',
    'ohne Merkmal'
        => 'without mark',
    'ohne Merkmal %d'
        => 'without mark %d',
    'Die Linie misst:'
        => 'The line measures:',
    'Diese Linie misst:'
        => 'This line measures:',
    'nichts Bestimmtes'
        => 'nothing in particular',
    'z. B. Apikulus'
        => 'e.g. apiculus',
    'Eigenes Teilmaß'
        => 'Own part measure',
    'Teilmaße'
        => 'Part measures',
    'Jeder Punkt ist eine Messung. Die Kurve zeigt, wo die meisten liegen; die gestrichelte Linie ist der Durchschnitt.'
        => 'Each dot is one measure. The curve shows where most of them lie; the dashed line is the mean.',
    'Alle in einem Maßstab, nach Länge sortiert. Ausrichten geht im Bildbetrachter: „Alle drehen" und „Umdrehen".'
        => 'All at one scale, sorted by length. Aligning happens in the picture viewer: “Turn all” and “Turn round”.',
    'Umdrehen'
        => 'Turn round',
    'Bildausschnitt'
        => 'Picture cutout',
    'für eine Bildreihe auf der Tafel, ohne Messen'
        => 'for a row of pictures on the plate, without measuring',
    'Für die Tafel'
        => 'For the plate',
    'Vier Punkte: erst von unten nach oben (2) – in der Reihe steht der zweite Punkt oben –, dann die Breite (2).'
        => 'Four points: first from bottom to top (2) – in the row the second point stands at the top –, then the width (2).',
    'Der Ausschnitt heißt:'
        => 'The cutout is called:',
    'Dieser Ausschnitt heißt:'
        => 'This cutout is called:',
    'z. B. Ascusspitzen Jod'
        => 'e.g. ascus tips in iodine',
    '+ eigener Name'
        => '+ own name',
    'Ausschnitte'
        => 'Cutouts',
    'Die Ausschnitte stehen so, wie du sie im Bildbetrachter gesetzt hast: in dieser Reihenfolge, die Basis unten, die Spitze oben. Haben alle Bilder einen Maßstab, stehen sie im echten Größenverhältnis mit einem Balken, sonst alle gleich groß.'
        => 'The cutouts stand as you set them in the picture viewer: in that order, base at the bottom, tip at the top. If every picture has a scale they keep their true sizes with a bar, otherwise all the same size.',
    'Linien zwischen den Ausschnitten'
        => 'Lines between the cutouts',
    'Ohne Rand'
        => 'No margin',
    'Größe ändern – mit Umschalt bleibt das Seitenverhältnis'
        => 'Change the size – with Shift the proportions stay',
    'Einsatzbild'
        => 'Inset picture',
    'Einsatz: %s'
        => 'Inset: %s',
    'Ziehen verschiebt es. Die Punkte an den Ecken und Seiten ändern die Größe. Das rote Kreuz oben rechts entfernt es. Der Rahmen hat die Farbe und Stärke der Linien zwischen den Bildern.'
        => 'Dragging moves it. The points at the corners and sides change its size. The red cross at the top right removes it. Its frame has the colour and width of the lines between the pictures.',
    'Einsatz entfernen'
        => 'Remove the inset',
    'Noch kein Ausschnitt mit diesem Namen. Wähle „Bildausschnitt" und setze vier Punkte.'
        => 'No cutout with this name yet. Choose “Picture cutout” and set four points.',
    'Diese Ausschnitte stehen auf der Tafel als eine Reihe, genau so wie hier.'
        => 'On the plate these cutouts stand as one row, just as here.',
    'Alle drehen'
        => 'Turn all',
    'Alle Kacheln um 90° drehen'
        => 'Turn all tiles by 90°',
    'Messen beenden. Dann eine Messung anklicken, um sie zu ändern.'
        => 'Stop measuring. Then click a measure to change it.',
    'für Gebogenes, entlang der Mittellinie'
        => 'for bent things, along the midline',
    'Länge × Breite, gebogen'
        => 'Length × breadth, bent',
    'Kernbereich zeigen' => 'Show core area',
    'Kernbereich ausblenden' => 'Hide core area',
    'Statistik zeigen'
        => 'Show statistics',
    'Statistik ausblenden'
        => 'Hide statistics',
    'Messungen löschen'
        => 'Delete measurements',
    'Klicke auf den Anfang, dann auf das Ende.'
        => 'Click the start, then the end.',
    'Vier Punkte: erst die Längsachse (2), dann die Breite (2) – je den äußersten Punkt links und rechts der Achse.'
        => 'Four points: first the long axis (2), then the breadth (2) – the outermost point left and right of the axis.',
    'Zuerst der Ausschnitt: vier Punkte, erst die Längsachse (2), dann die Breite (2) – je den äußersten Punkt links und rechts der Achse.'
        => 'First the cutout: four points, first the long axis (2), then the breadth (2) – the outermost point left and right of the axis.',
    'Klicke in kleinen Schritten entlang der gedachten Mittellinie. Ende: letzten Punkt nochmal klicken oder Enter.'
        => 'Click in small steps along the imagined midline. To finish: click the last point again, or Enter.',
    'Klicke dorthin, wo der Text stehen soll.'
        => 'Click where the text should go.',
    'Klicke dorthin, wo der Pfeil hinzeigen soll. Dann klicke dorthin, wo er beginnt.'
        => 'Click where the arrow should point. Then click where it starts.',
    'Klicke auf Anfang und Ende einer Strecke, deren echte Länge du kennst – zum Beispiel auf den Maßstab im Bild. Du kannst dabei vergrößern.'
        => 'Click the start and the end of a distance whose real length you know – for example the scale bar in the picture. You can zoom in meanwhile.',
    'Jetzt klicke auf das Ende.'
        => 'Now click the end.',
    'Größer oder kleiner: am Quadrat ziehen. Drehen: am Kreis ziehen. Ändern: noch einmal auf den Text klicken.'
        => 'Bigger or smaller: drag the square. Turn: drag the circle. Change: click the text once more.',
    'Für den Rahmen braucht das Bild zuerst einen Maßstab.'
        => 'For the frame the picture first needs a scale.',
    // The frame pulled open and counted in (photowindow.js, boxHintSync, statsCountView).
    'aufziehen, dann darin zählen' => 'pull open, then count in it',
    'Quadrat' => 'Square',
    '%s · %d gezählt' => '%s · %d counted',
    'Klicke in den Rahmen, um zu zählen; ein Klick auf einen Punkt nimmt ihn zurück. Drehen am Kreis, verschieben durch Ziehen.'
        => 'Click into the frame to count; a click on a point takes it back. Turn at the circle, move by dragging.',
    'Ziehe den Rahmen auf. Er rastet in runden Maßen ein.' => 'Pull the frame open. It snaps to round sizes.',
    'Quadrat %s · Anzahl %d' => 'Square %s · count %d',
    'Rechteck %s · Anzahl %d' => 'Rectangle %s · count %d',
    'je cm²' => 'per cm²',
    'je mm²' => 'per mm²',
    'je 10 × 10 µm' => 'per 10 × 10 µm',
    '≈ %s %s' => '≈ %s %s',
    // The sector: a cap as an ellipse, a slice of it counted and taken round (photowindow.js, statsCountView, sectorHintSync).
    'Hut als Ellipse, dann den ganzen Hut oder ein Teilstück zählen'
        => 'the cap as an ellipse, then count the whole cap or a part of it',
    'Drei Punkte: die Mitte des Hutes, dann der Hutrand in der langen Richtung, dann der Rand in der kurzen.'
        => 'Three points: the middle of the cap, then its rim in the long direction, then the rim in the short one.',
    '%s: Ø ≈ %d (%d–%d), N = %d' => '%s: Ø ≈ %d (%d–%d), N = %d',
    '%s: ≈ %d, N = 1' => '%s: ≈ %d, N = 1',
    'L/l ≈ 1 : %s' => 'L/l ≈ 1 : %s',
    'Lamellen zählen' => 'Count gills',
    'Poren zählen' => 'Count pores',
    'Ganzen Hut zählen' => 'Count the whole cap',
    'Teilstück zählen' => 'Count a part',
    'Stiel zentral' => 'Stem central',
    'Stiel exzentrisch' => 'Stem eccentric',
    'Klicke auf den Stielansatz, dort wo die Lamellen zusammenlaufen.' => 'Click where the stem sits, where the gills run together.',
    'Klicke auf den Hutrand, wo die Lamellen anfangen.' => 'Click the rim of the cap where the gills begin.',
    'Fahre am Rand entlang bis dorthin, wo die Lamellen aufhören, und klicke.' => 'Move along the rim to where the gills end, and click.',
    'Klicke in den Hut, um zu zählen; ein Klick auf einen Punkt nimmt ihn zurück. Drehen am Kreis, verschieben durch Ziehen in der Ellipse.'
        => 'Click into the cap to count; a click on a point takes it back. Turn at the circle, move by dragging inside the ellipse.',
    // The full pictures kept at the desk (administration.php Offline-Daten, administration.js drawOriginals).
    'Volle Bilder' => 'Full pictures',
    'Öffnest du am Computer ein Bild, holt YASAIMAPP es in voller Größe. Damit es beim nächsten Öffnen nicht wieder über das Internet kommen muss, bleibt es auf diesem Gerät liegen. Wird der Platz knapp, fallen die Bilder heraus, die du am längsten nicht mehr geöffnet hast. Auf dem Handy bleibt nichts liegen.'
        => 'When you open a picture on a computer, YASAIMAPP fetches it in full size. So that it does not have to come over the internet again next time, it stays on this device. When space runs short, the pictures you have not opened for longest drop out. Nothing is kept on a phone.',
    'Auf diesem Gerät bleiben keine vollen Bilder liegen. Das geht nur am Computer.' => 'No full pictures are kept on this device. That only works on a computer.',
    '%s auf diesem Gerät · %d Bilder' => '%s on this device · %d pictures',
    'nichts auf diesem Gerät' => 'nothing on this device',
    '%s auf diesem Gerät · 1 Bild' => '%s on this device · 1 picture',
    'weniger als 1 MB' => 'less than 1 MB',
    'Die vollen Bilder sind von diesem Gerät gelöscht.' => 'The full pictures have been deleted from this device.',
    'Volle Bilder auf diesem Gerät behalten' => 'Keep full pictures on this device',
    'Wie viel Platz dürfen sie höchstens belegen?' => 'How much space may they take at most?',
    '%s GB' => '%s GB',
    'Zählungen' => 'Counts',
    'Jeder Rahmen und jede Ellipse so, wie das Foto ausgerichtet ist, mit den gezählten Punkten. Haben alle Bilder einen Maßstab, stehen sie im echten Größenverhältnis mit einem Balken.'
        => 'Every frame and every ellipse as the photo lies, with the counted points. Where every picture has a scale they stand in their true proportions with a bar.',
    'Ein Hut liegt sehr schräg, sein Wert ist ungenau.'
        => 'One cap lies very askew, its value is rough.',
    '%d Hüte liegen sehr schräg, ihre Werte sind ungenau.'
        => '%d caps lie very askew, their values are rough.',
    'Lamelletten' => 'Lamellulae',
    'Klicke auf den Hutrand, wo das Teilstück beginnt.'
        => 'Click the rim of the cap where the part begins.',
    'Fahre am Rand entlang bis zum Ende des Teilstücks und klicke.'
        => 'Move along the rim to the end of the part and click.',
    'Prüfe die Ellipse am Hutrand und verschiebe die Punkte, wenn nötig; mit gedrückter Umschalttaste wächst oder schrumpft die ganze Ellipse. Drehen am Kreis, verschieben durch Ziehen in der Ellipse.'
        => 'Check the ellipse against the rim of the cap and move the points if needed; holding Shift grows or shrinks the whole ellipse. Turn at the circle, move by dragging inside the ellipse.',
    'Klicke in das Teilstück, um zu zählen; ein Klick auf einen Punkt nimmt ihn zurück. Die Kanten des Teilstücks lassen sich an ihren Punkten ziehen. Drehen am Kreis, verschieben durch Ziehen neben dem Teilstück. Ein Klick auf den Stielansatz setzt das Teilstück neu.'
        => 'Click into the part to count; a click on a point takes it back. The part\'s edges can be dragged at their points. Turn at the circle, move by dragging beside the part. A click on the stem point sets the part anew.',
    'Lamellen' => 'Lamellae',
    'Poren' => 'Pores',
    'Das gilt nur für dich und wird nicht gespeichert.'
        => 'This is only for you and is not saved.',
    'Dafür braucht das Bild zuerst einen Maßstab.'
        => 'For this the picture first needs a scale.',
    'Frei messen: Hier wird nichts gespeichert und nichts gezählt.'
        => 'Free measuring: nothing is saved or counted here.',
    'Unter „%s" ist noch nichts gemessen. Miss mit „Länge × Breite". Gezählt werden alle Bilder dieses Fundes.'
        => 'Nothing measured under “%s” yet. Measure with “Length × breadth”. All pictures of this find count.',
    'Noch kein „%s" gemessen. Wähle die Strecke und klicke auf Anfang und Ende.'
        => 'No “%s” measured yet. Choose the line and click its start and end.',
    'Länge × Breite messen'
        => 'Measure length × breadth',
    'Gebogene Länge messen'
        => 'Measure bent length',
    'Schreibe jetzt. Mit Enter beginnt eine neue Zeile.'
        => 'Write now. Enter starts a new line.',
    'Hier fehlt noch die Breite.'
        => 'The breadth is still missing here.',
    'Jetzt die Länge: Klicke in kleinen Schritten entlang der gedachten Mittellinie. Ende: letzten Punkt nochmal klicken oder Enter.'
        => 'Now the length: click in small steps along the imagined midline. To finish: click the last point again, or Enter.',
    'Jetzt die Breite: Klicke an der dicksten Stelle auf beide Ränder.'
        => 'Now the breadth: click both edges at the thickest place.',
    'Zeiger'
        => 'Pointer',
    'Zeiger — beendet das Messen'
        => 'Pointer — ends measuring',
    'Zeigen'
        => 'Show',
    'Zeigen/Editieren'
        => 'Show/Edit',
    'Zeigt roh an, was die Standortbestimmung dieses Browsers meldet — auf welchem Weg gemessen wurde, verrät die Höhenangabe. Draußen ausführen, nicht am Fenster: das Signal braucht freien Himmel.'
        => 'Shows, raw, what this browser’s geolocation reports — the elevation value reveals how the measurement was taken. Run it outside, not at the window: the signal needs open sky.',
    'Zeile %d'
        => 'Row %d',
    'Zeilen'
        => 'Rows',
    'Zeit'
        => 'Time',
    'Zeitfenster'
        => 'Time window',
    'Zeitfilter aufheben'
        => 'Clear time filter',
    'Zeitraum'
        => 'Time range',
    'Zeitraum der Funde'
        => 'Time range of the finds',
    'Ziehen — Doppelklick setzt zurück'
        => 'Drag — double click resets',
    'Zug'
        => 'Zug',
    'zuletzt %s'
        => 'last seen %s',
    'letzte Sicherung'
        => 'last backup',
    'zuletzt gesehen %s'
        => 'last seen %s',
    'Zuletzt vor %s · ±%s m'
        => 'Last seen %s ago · ±%s m',
    'Zu meinem Standort'
        => 'To my location',
    'Zuordnung anwenden'
        => 'Apply assignment',
    'Zur Anmeldung'
        => 'Go to sign-in',
    'Zur Karte'
        => 'To the map',
    'Zurückgeholt und gespeichert.'
        => 'Brought back and saved.',
    'Zurückholen'
        => 'Bring back',
    'Zurücknehmen'
        => 'Take back',
    'Zurücksetzen'
        => 'Reset',
    'Zurückziehen'
        => 'Withdraw',
    'Zurück zur Karte'
        => 'Back to the map',
    'Zusammenführen'
        => 'Merge',
    'Zusammenführen geht nur von Blatt zu Blatt — erst die Unterlisten zusammenführen, dann den leeren Überbau löschen.'
        => 'Merging only works leaf to leaf — merge the sublists first, then delete the empty superstructure.',
    'Zusammengeführt — %d Punkte sind umgezogen, die Liste ist weg.'
        => 'Merged — %d points have moved, the list is gone.',
    'Zusammengeführt — ein Punkt ist umgezogen, die Liste ist weg.'
        => 'Merged — one point has moved, the list is gone.',
    'Zu viele Fehlversuche. Bitte in %d Minuten noch einmal versuchen.'
        => 'Too many failed attempts. Please try again in %d minutes.',
    'Zu viele Punkte für diesen Ausschnitt — auf der Karte stehen %d von %d. Näher heranzoomen zeigt alle.'
        => 'Too many points for this view — the map shows %d of %d. Zoom in closer to see all of them.',
    'Zwei Dinge kann auch der Import nicht heilen: Google speichert <strong>nicht, wann</strong> ein Ort gesichert wurde — die Punkte kommen ohne Fundzeit an. Und bei Orten aus Googles eigenem Verzeichnis stehen <strong>keine Koordinaten</strong> in der Ausfuhr; solche Einträge werden einzeln aufgelistet, statt ihre Lage zu raten.'
        => 'Two things even the import cannot heal: Google does <strong>not store when</strong> a place was saved — the points arrive without a find time. And for places from Google\'s own directory the export holds <strong>no coordinates</strong>; such entries are listed individually instead of guessing their position.',
    'Zwei Schritte: Datenbank aufbauen, dann das erste Konto anlegen. Dieses Konto wird Admin und kann später Einladungscodes ausgeben.'
        => 'Two steps: build the database, then create the first account. This account becomes admin and can issue invitation codes later.',
    'Zürich'
        => 'Zurich',
    '– keine –'
        => '– none –',
    '– leer –'
        => '– empty –',
    ' — aktuell'
        => ' — up to date',
    '— alle —'
        => '— all —',
    ' — aus %s fallen sie nicht, die folgen ihrem Filter'
        => ' — they do not fall out of %s, which follow their filters',
    ' — aus %s fallen sie nicht, die folgt ihrem Filter'
        => ' — they do not fall out of %s, which follows its filter',
    ' — beim Anbieter liegt Neueres'
        => ' — the provider has something newer',
    '— keine Tour —'
        => '— no tour —',
    '— keine —'
        => '— none —',
    '— nichts im Bestand —'
        => '— nothing in the stock —',
    '— oberste Ebene —'
        => '— top level —',
    '„%s" (Freigabe-Sicht)'
        => '"%s" (share view)',
    '„%s" (fremde Liste)'
        => '"%s" (somebody else\'s list)',
    '„%s" (selbst eine Autoliste)'
        => '"%s" (itself an auto list)',
    '„%s": %s'
        => '"%s": %s',
    '„%s": Das Format wurde nicht erkannt. Bekannt sind: %s.'
        => '"%s": The format was not recognized. Known formats: %s.',
    '„%s": nicht angekommen.'
        => '"%s": did not arrive.',
    '„%s" antwortet gerade nicht — der Dienst dahinter ist wohl gestört.'
        => '"%s" is not responding right now — the service behind it seems to be down.',
    '„%s" aus dem Filter nehmen'
        => 'Remove "%s" from the filter',
    '„%s" eingespielt — %d Punkte in „%s".'
        => '"%s" imported — %d points into "%s".',
    '„%s" eingespielt — ein Punkt in „%s".'
        => '"%s" imported — one point into "%s".',
    '„%s" endgültig löschen?'
        => 'Delete "%s" for good?',
    '„%s" ist auf diesem Gerät verfügbar: %s Kacheln'
        => '"%s" is available on this device: %s tiles',
    // Offline areas, the download that counts itself (27.09.2026)
    'Weiterladen' => 'Continue loading',
    'Kachelzahl unbekannt' => 'number of tiles unknown',
    'Nicht fertig' => 'Not finished',
    'Nicht fertig: Speicher voll' => 'Not finished: storage full',
    'Nicht fertig: Speichern ging nicht' => 'Not finished: saving failed',
    'Nicht fertig: %s von %s geladen' => 'Not finished: %s of %s loaded',
    'Nicht fertig: %s Kacheln ohne Verbindung' => 'Not finished: %s tiles without a connection',
    'Der Speicher auf diesem Gerät ist voll. %s Kacheln sind gespeichert. Mach Platz frei und tippe dann unter Offline-Gebiete auf „Weiterladen".'
        => 'The storage on this device is full. %s tiles are saved. Free some space, then tap "Continue loading" under Offline areas.',
    'Die Kacheln lassen sich auf diesem Gerät nicht mehr speichern. %s Kacheln sind gespeichert.'
        => 'Tiles can no longer be saved on this device. %s tiles are saved.',
    '„%s": %s Kacheln sind auf diesem Gerät, %s kamen ohne Verbindung nicht an. Tippe unter Offline-Gebiete auf „Weiterladen", sobald du wieder Empfang hast.'
        => '"%s": %s tiles are on this device, %s did not arrive for lack of a connection. Tap "Continue loading" under Offline areas as soon as you have reception again.',
    '„%s" ist bei %d Punkten angepasst.'
        => '"%s" has been adjusted on %d points.',
    '„%s" ist bei einem Punkt angepasst.'
        => '"%s" has been adjusted on one point.',
    '„%s" ist grösser als 50 MB — keine der bekannten Ausfuhren ist so gross. Beim Google-Takeout hilft: nur „Gespeichert" auswählen.'
        => '"%s" is bigger than 50 MB — none of the known exports is that big. For the Google Takeout it helps to select only "Saved".',
    '„%s" konnte nicht geladen werden: keine einzige Kachel angekommen (%d Versuche). Siehe Konsole.'
        => '"%s" could not be loaded: not a single tile arrived (%d attempts). See console.',
    '„%s" lag schon eingespielt vor und wurde übersprungen.'
        => '"%s" had already been imported and was skipped.',
    '„%s" liess sich nicht ablegen.'
        => '"%s" could not be stored.',
    '„%s" sperren?'
        => 'Lock "%s"?',
    '„%s" verschwindet. Der Punkt bleibt — er steht danach unter „Ohne Tour".'
        => '"%s" disappears. The point stays — it then sits under "Without a tour".',
    '„%s" verschwindet. Die %s Punkte bleiben — sie stehen danach unter „Ohne Tour".'
        => '"%s" disappears. The %s points stay — they then sit under "Without a tour".',
    '„%s" verschwindet. Ihre Punkte bleiben — was NUR hier stand, findest du danach unter „Ohne Liste".'
        => '"%s" disappears. Its points stay — what stood ONLY here you will then find under "Without a list".',
    '„%s" wandert in den Papierkorb und lässt sich von dort zurückholen.'
        => '"%s" goes into the trash and can be brought back from there.',
    '„%s" wieder freigeben?'
        => 'Unlock "%s" again?',
    '„%s" wird %s auf „%s" gesetzt.'
        => '"%1$s" is set to "%3$s" %2$s.',
    '„%s" wird %s geleert.'
        => '"%s" is cleared %s.',
    '„%s" wird geladen — bleib solange in Empfang.'
        => '"%s" is loading — stay in reception meanwhile.',
    '„%s" wird in der Cloud gelöscht. %s legt sie in seinen Papierkorb, dort lässt sie sich noch zurückholen — hier ist sie sofort weg.'
        => '"%s" will be deleted in the cloud. %s puts it into its trash, from where it can still be brought back — here it is gone immediately.',
    '„%s" wird in deinem S3-Speicher gelöscht. Dort gibt es keinen Papierkorb — sie ist danach für immer weg, dort und hier.'
        => '"%s" will be deleted in your S3 storage. There is no trash there — afterwards it is gone for good, there and here.',
    '„%s" wird unwiderruflich gelöscht. Das lässt sich nicht rückgängig machen.'
        => '"%s" will be deleted irrevocably. This cannot be undone.',
    '„%s" zieht mitsamt ihrem Ast um — Punkte und Unterlisten bleiben beieinander.'
        => '"%s" moves along with its branch — points and sublists stay together.',
    '„%s" übersprungen.'
        => '"%s" skipped.',
    '„%s“: %s'
        => '"%s": %s',
    '„%s“ braucht Datum und Uhrzeit.'
        => '"%s" needs a date and a time.',
    '„Nächster Schritt", dann so lassen, wie es steht (einmal exportieren, als .zip) und „Ausfuhr erstellen".'
        => '"Next step", then leave everything as it stands (export once, as .zip) and "Create export".',
    '„Ohne Liste"'
        => '"Without a list"',
    '… %s weitere'
        => '… %s more',
    '… und %d weitere'
        => '… and %d more',
    ' · Anfrage läuft schon'
        => ' · request already pending',
    '· automatisch'
        => '· automatic',
    '· automatisch — wächst mit ihrem Filter'
        => '· automatic — grows with its filter',
    ' · läuft'
        => ' · running',
    ' · schon befreundet'
        => ' · already friends',
    ' · teilgenommen'
        => ' · took part',
    ' · von '
        => ' · by ',
    'Ältere Funde werden geholt … (%d von %d Zeiträumen)'
        => 'Fetching older finds … (%d of %d periods)',
    'Ändern'
        => 'Change',
    'Änderung verwerfen?'
        => 'Discard change?',
    'Örtlich einspielen'
        => 'Import locally',
    'Örtlich mit %s erzeugen und einmalig nach %s auf diesen Server put.'
        => 'Generate locally with %s and put once into %s on this server.',
    'Über Mitternacht läuft eine Tour weiter, solange der letzte Punkt keine sechs Stunden zurückliegt — für den Leuchtabend am Licht, der um eins noch derselbe Abend ist.'
        => 'Past midnight a tour keeps running as long as the last point is no more than six hours back — for the moth night at the lamp, which at one in the morning is still the same evening.',
    'Übernehmen'
        => 'Apply',
    'Übernommen werden die <strong>Ortsmarken</strong> mit Name, Beschreibung und — wo vorhanden — Zeitstempel und Höhe; Ordner werden eigene Listen. Linien und Flächen bleiben draussen: Das sind Wege und Umrisse, keine Funde.'
        => 'Taken over are the <strong>placemarks</strong> with name, description and — where present — timestamp and elevation; folders become their own lists. Lines and areas stay out: those are paths and outlines, not finds.',
    'Übernommen werden die <strong>Punkte</strong>; Titel, Beschreibung und Zeitstempel werden unter ihren üblichen Namen in den Eigenschaften gesucht, alle weiteren Eigenschaften wandern als „Feld: Wert" in die Notiz. Linien und Flächen bleiben draussen.'
        => 'Taken over are the <strong>points</strong>; title, description and timestamp are looked up under their usual names in the properties, all further properties go into the note as "field: value". Lines and areas stay out.',
    'Übernommen werden die <strong>Wegpunkte</strong> mit Name, Beschreibung, Höhe und dem Zeitpunkt der Markierung als Fundzeit. Tracks und Routen bleiben draussen: Das sind Wege, keine Funde.'
        => 'Taken over are the <strong>waypoints</strong> with name, description, elevation and the moment of marking as the find time. Tracks and routes stay out: those are paths, not finds.',
    'Übernommen — der jetzige Stand ist das neue Original.'
        => 'Accepted — the current state is the new original.',
    'Überschriebene Änderungen'
        => 'Overwritten changes',
    'Überschrift mit Unterlisten — der Punkt gehört in die letzte Ebene'
        => 'Heading with sublists — the point belongs in the last level',
    'Übersichtskarte voranstellen'
        => 'Lead with an overview map',
    'Übersicht — Wege erkennbar'
        => 'Overview — paths recognisable',
    'Übersprungen.'
        => 'Skipped.',
    'Übrige Tiere'
        => 'Other animals',
    'überschriebene Fassung'
        => 'overwritten version',

    /* --- Cloud copies (Administration → Kopien, 11.09.2026) --- */
    'Kopien'
        => 'Copies',
    'Jeder Mitbesitzer mit Cloud bekommt den vollständigen Bildsatz eines Fundes als eigene Kopie. Dieser Lauf trägt die Dateien von Cloud zu Cloud — in Häppchen, mit Wiederholung; unbeaufsichtigt macht dasselbe cronjobs/hourly.php.'
        => 'Every co-owner with a cloud receives the complete picture set of a find as his own copy. This run carries the files from cloud to cloud — in chunks, with retries; cronjobs/hourly.php does the same unattended.',
    'Ein Häppchen kopieren'
        => 'Copy one chunk',
    'Wartend'
        => 'Waiting',
    'Zum Entfernen'
        => 'To be removed',
    'Aufgegeben'
        => 'Given up',
    '%d Versuche'
        => '%d attempts',
    '%d Kopien angelegt'
        => '%d copies made',
    '%d Kopien entfernt'
        => '%d copies removed',
    '%d aufgegeben'
        => '%d given up',
    'Aufgegebene Kopien werden wieder versucht.'
        => 'Given-up copies will be tried again.',
    'Der Kopierlauf braucht die Migration 0098 — sie ist noch nicht eingespielt.'
        => 'The copy run needs migration 0098 — it is not applied yet.',
    'Die Cloud des Fotografen ist nicht verbunden.'
        => 'The photographer\'s cloud is not connected.',
    'Die Cloud des Empfängers ist nicht verbunden.'
        => 'The recipient\'s cloud is not connected.',
    'Die Datei ließ sich nicht aus der Cloud des Fotografen laden.'
        => 'The file could not be fetched from the photographer\'s cloud.',
    'Die Datei ist zu groß zum Kopieren (%s).'
        => 'The file is too large to copy (%s).',
    'Die Datei ließ sich nicht aus der Cloud laden.'
        => 'The file could not be loaded from the cloud.',
    'Unbekannte Herkunft: %s'
        => 'Unknown origin: %s',

    /* --- Co-ownership (11.09.2026) --- */
    'Ablage gespeichert.'
        => 'Filed.',
    'Der Mitbesitz braucht die Migration 0098 — sie ist noch nicht eingespielt.'
        => 'Co-ownership needs migration 0098 — it is not applied yet.',

    /* --- Co-ownership, the door (11.09.2026) --- */
    'Fund abgeben?'
        => 'Hand the find over?',
    '„%s" geht an %s weiter — du steigst aus und kannst aus deinem Papierkorb zurück.'
        => '“%s” passes to %s — you step out and can come back from your trash.',
    'Abgeben'
        => 'Hand over',
    'Abgegeben an %s — der Fund liegt in deinem Papierkorb.'
        => 'Handed over to %s — the find is in your trash.',
    'Aussteigen?'
        => 'Step out?',
    'Du steigst aus „%s" aus — der Fund bleibt bei %s. Aus deinem Papierkorb kannst du zurück.'
        => 'You step out of “%s” — the find stays with %s. You can come back from your trash.',
    'Aussteigen'
        => 'Step out',
    'Ausgestiegen — der Fund liegt in deinem Papierkorb.'
        => 'Stepped out — the find is in your trash.',
    'Aussteigen fehlgeschlagen: %s'
        => 'Stepping out failed: %s',
    'Ausgestiegen'
        => 'Stepped out',
    'Diese Funde leben bei den anderen weiter. Zurückholen macht dich wieder zum Mitbesitzer; Endgültig entfernt deine Kopien ihrer Bilder aus deiner Cloud.'
        => 'These finds live on with the others. Restore makes you a co-owner again; Permanently removes your copies of their pictures from your cloud.',
    'Bei'
        => 'With',
    'Liegt auch beim Halter im Papierkorb.'
        => 'Also in the holder\'s trash.',
    'Für immer aussteigen?'
        => 'Step out for good?',
    'Du wirst bei „%s" vergessen, und deine Kopien der Bilder der anderen verschwinden aus deiner Cloud. Der Fund selbst bleibt bei den anderen; deine eigenen Bilder bleiben daran.'
        => 'You will be forgotten at “%s”, and your copies of the others\' pictures leave your cloud. The find itself stays with the others; your own pictures stay on it.',
    'Für immer aussteigen'
        => 'Step out for good',
    'Bei einem Fund wieder Mitbesitzer.'
        => 'Co-owner of one find again.',
    'Bei %d Funden wieder Mitbesitzer.'
        => 'Co-owner of %d finds again.',
    'Bei einem Fund für immer ausgestiegen — die Kopien werden aus deiner Cloud entfernt.'
        => 'Stepped out of one find for good — the copies will be removed from your cloud.',
    'Bei %d Funden für immer ausgestiegen — die Kopien werden aus deiner Cloud entfernt.'
        => 'Stepped out of %d finds for good — the copies will be removed from your cloud.',
    'Foto: %s'
        => 'Photo: %s',

    // --- The track buckets and their block in the Offline tab (11.09.2026) ---
    'Geteilt — zwei Strecken. Tippe die, die weg soll.'
        => 'Cut — two tracks. Tap the one that should go.',
    'Hier teilen'
        => 'Cut here',
    'Ohne Verbindung lässt sich nichts teilen.'
        => 'Without a connection nothing can be cut.',
    'Zum Teilen braucht es eine Stelle zwischen Anfang und Ende.'
        => 'A cut needs a spot between start and end.',
    '„%s" vom %s wird endgültig gelöscht — eine Strecke ist eine Aufzeichnung, kein Fund, und hat keinen Papierkorb.'
        => '„%s" of %s will be deleted for good — a track is a recording, not a find, and has no trash.',
    'Die Zuordnung braucht die Migration 0099 — sie ist noch nicht eingespielt.'
        => 'Assigning needs migration 0099 — it has not been applied yet.',
    'Diese Tour-Kennung ist nicht lesbar.'
        => 'This tour id cannot be read.',
    'Zu dieser Exkursion gehörst du nicht.'
        => 'You are not on this excursion.',
    'Nichts zuzuordnen — weder Tour noch Exkursion angegeben.'
        => 'Nothing to assign — neither tour nor excursion given.',
    'Tour: %s'
        => 'Tour: %s',
    'keiner Tour zugeordnet'
        => 'not filed under an outing',
    'Zuordnen'
        => 'Assign',
    'Tour vom %s'
        => 'Tour of %s',
    'Exkursion ab %s'
        => 'Excursion from %s',
    'Keine Zuordnung'
        => 'No assignment',
    'Tour und Exkursion lösen'
        => 'Detach tour and excursion',
    'Wegstrecke zuordnen'
        => 'Assign track',
    'Zu welcher Tour oder Exkursion gehört „%s"?'
        => 'Which tour or excursion does „%s" belong to?',
    'Ohne Verbindung lässt sich nichts zuordnen.'
        => 'Without a connection nothing can be assigned.',
    'Zugeordnet.'
        => 'Assigned.',
    'Wegstrecke gelöscht.'
        => 'Track deleted.',
    'Wegstrecke löschen?'
        => 'Delete track?',
    'Wegstrecken'
        => 'Tracks',
    'Der Speicher für Wegstrecken ist von einem anderen Tab belegt.'
        => 'The track store is held by another tab.',
    'Der Speicher für Wegstrecken wurde abgebrochen.'
        => 'The track store was aborted.',
    'Wegstrecke'
        => 'Track',
    'Was die Aufzeichnungs-App mitgeschrieben hat. Liegt auf dem Gerät und zeichnet auch ohne Netz.'
        => 'What the recorder app wrote down. Lies on the device and draws without a network too.',

    // --- The track popup: profile and the cut by spot (13.09.2026) ---
    'Teilen'
        => 'Cut',
    'An dieser Stelle teilen'
        => 'Cut at this spot',
    'Höhenprofil, %s bis %s'
        => 'Elevation profile, %s to %s',

    // --- Mapping along from the map, and the door ahead of the window (13.09.2026) ---
    '„%s" läuft'
        => '„%s" is running',
    'Teilnehmen'
        => 'Take part',
    'Nicht teilnehmen'
        => 'Not taking part',
    'Nicht mehr für die Exkursion kartieren?'
        => 'Stop mapping for the excursion?',
    'Deine nächsten Funde gehören dann nur noch dir, nicht mehr zu „%s". Zurück geht es über „Teilnehmen", solange die Exkursion läuft — bis %s.'
        => 'Your next finds will then be yours alone, no longer part of „%s". „Take part" brings you back as long as the excursion runs — until %s.',
    'Nicht mehr kartieren'
        => 'Stop mapping',
    'Du kartierst jetzt für „%s".'
        => 'You are now mapping for „%s".',
    'Ging gerade nicht — ohne Netz lässt sich nicht teilnehmen.'
        => 'Did not work just now — without a network you cannot take part.',
    'Diese Exkursion ist beendet.'
        => 'This excursion has ended.',
    'Beginnt am %s. Mach jetzt schon mit — dann kannst du dir die Karte des Gebiets aufs Handy laden, solange du Empfang hast. Funde eintragen geht ab dem Start.'
        => 'Starts on %s. Join now — then you can load the map of the area onto your phone while you have reception. Entering finds begins with the start.',
    'Beginnt am %s. Mach jetzt schon mit — Funde eintragen geht ab dem Start.'
        => 'Starts on %s. Join now — entering finds begins with the start.',
    'Öffne diesen Link dafür auf dem Handy, das du im Gelände dabeihast: Die Karte bleibt auf dem Gerät, auf dem du sie lädst.'
        => 'Open this link on the phone you will carry in the field: the map stays on the device that loads it.',
    'Die Karte bleibt auf dem Gerät, auf dem du sie lädst — also am besten hier auf dem Handy, das du im Gelände dabeihast.'
        => 'The map stays on the device that loads it — so best here on the phone you will carry in the field.',
    'Exkursion „%s“ — als %s, beginnt am %s.'
        => 'Excursion „%s“ — as %s, starts on %s.',
    'Die Exkursion beginnt am %s — bis dahin lässt sich hier nichts eintragen.'
        => 'The excursion starts on %s — nothing can be entered here until then.',
    'Exkursion: %s · ab %s'
        => 'Excursion: %s · from %s',

    'Link zum Mitmachen'
        => 'Link for joining',
    'Link kopieren'
        => 'Copy link',

    // --- The closing page's species list (13.09.2026) ---
    'Artenliste — eine Art aus %d Funden'
        => 'Species list — one species from %d finds',
    'Artenliste — %d Arten aus %d Funden'
        => 'Species list — %d species from %d finds',
    'RL: Rote Liste Deutschlands — %s.'
        => 'RL: Red List of Germany — %s.',
    'Karte der Funde dieser Exkursion'
        => 'Map of the finds of this excursion',
    '%s hat deine Anfrage angenommen — ihr seid jetzt Freunde.'
        => '%s accepted your request — you are friends now.',
    'Gelesen'
        => 'Read',

    // --- The species tab counts what each list holds (13.09.2026) ---
    '%s wissenschaftliche Namen'
        => '%s scientific names',
    '%s Synonyme'
        => '%s synonyms',
    '%s deutsche Namen'
        => '%s German names',
    'Für diese Gruppe gibt es noch keine Namen.'
        => 'There are no names for this group yet.',

    // The help, rebuilt as an outline (15.09.2026)
    'Zuerst, wie die Karte aufgebaut ist und wie Listen und Punkte zusammenhängen — dann die Dinge in der Reihenfolge, in der man ihnen begegnet. Wer etwas Bestimmtes sucht, springt über das Verzeichnis.'
        => 'First how the map is built and how lists and points belong together — then the things in the order one meets them. Whoever looks for something specific jumps via the contents.',
    'Die Seitenleiste'
        => 'The sidebar',
    'Die Kopfzeile'
        => 'The header',
    'Listen und Punkte — das Prinzip'
        => 'Lists and points — the principle',
    'Der Reiter Listen'
        => 'The Listen tab',
    'Geteilt: Freigaben und Exkursionen'
        => 'Shared: shares and excursions',
    'Der Reiter Punkte'
        => 'The Punkte tab',
    'Werkzeuge, Ebenen, Chat'
        => 'Tools, layers, chat',
    'Das Punktfenster'
        => 'The point window',
    'Die Verwaltung'
        => 'The administration',
    'Die Karte ist <strong>eine</strong> Seite, und alles, was du im Gelände brauchst, liegt auf ihr: links die Seitenleiste, rechts die Karte, dazwischen ein Griff. Die Verwaltung — Import, Freigaben, Einstellungen — ist eine zweite Seite und erreichbar über das Zahnrad in der Kopfzeile der Leiste; sie ist der Schreibtisch, die Karte ist das Feld.'
        => 'The map is <strong>one</strong> page, and everything you need in the field lies on it: the sidebar on the left, the map on the right, a grip between them. The administration — import, shares, settings — is a second page, reached through the gear in the sidebar\'s header; it is the desk, the map is the field.',
    'Der Reiter „Listen": deine Ablage. Anklicken blendet die Punkte einer Liste ein und aus.'
        => 'The "Listen" tab: your filing. A click shows or hides a list\'s points.',
    'Der Reiter „Punkte": genau die Punkte der eingeblendeten Listen, mit Titel und Fundzeit — und darüber die Filter.'
        => 'The "Punkte" tab: exactly the points of the shown lists, with title and find time — and the filters above them.',
    'Der Griff: ein Streifen über die ganze Höhe, der die Leiste ein- und ausklappt.'
        => 'The grip: a strip over the whole height that folds the sidebar in and out.',
    '<strong>Der Griff</strong> sitzt an der Kante zwischen Leiste und Karte und ist immer da, egal wie weit du in der Liste gescrollt hast. Eingeklappt rückt er an den linken Rand und die Karte bekommt die ganze Breite; ein Klick holt die Leiste zurück. <strong>Auf dem Handy</strong> gibt es keinen Griff: dort legt sich die Leiste über die Karte, das Menüzeichen ☰ oben links öffnet sie, „Schließen" in ihrer Fußzeile schließt sie.'
        => '<strong>The grip</strong> sits on the edge between sidebar and map and is always there, however far you have scrolled the list. Folded in, it moves to the left edge and the map gets the whole width; a click brings the sidebar back. <strong>On the phone</strong> there is no grip: the sidebar lies over the map there, the menu sign ☰ top left opens it, "Schließen" in its footer closes it.',
    'Die Leiste ist deine Ablage und dein Verzeichnis in einem: oben die Kopfzeile mit den Wegen hinaus, darunter zwei Reiter — <strong>Listen</strong>, wo du wählst, und <strong>Punkte</strong>, wo steht, was die Wahl ergibt. Die Filter gehören zu den Punkten und stehen über ihnen.'
        => 'The sidebar is your filing and your index in one: the header with the ways out on top, two tabs below — <strong>Listen</strong>, where you choose, and <strong>Punkte</strong>, where what the choice yields is listed. The filters belong to the points and stand above them.',
    'Über den Reitern steht eine schmale Zeile mit dem Namen der App und vier Zeichen. Sie führen aus der Karte hinaus oder ins Suchen — alles, was du am Punkt selbst tust, liegt darunter.'
        => 'Above the tabs stands a narrow line with the app\'s name and four signs. They lead out of the map or into searching — everything you do to a point itself lies below.',
    '<strong>Abmelden</strong> (die Tür): beendet die Anmeldung auf diesem Gerät. Was noch nicht übertragen war, wartet im Gerät und reist beim nächsten Anmelden — siehe Offline.'
        => '<strong>Sign out</strong> (the door): ends the login on this device. Whatever was not transferred yet waits on the device and travels at the next sign-in — see Offline.',
    '<strong>Hilfe</strong> (das Fragezeichen): diese Seite.'
        => '<strong>Help</strong> (the question mark): this page.',
    '<strong>Suche</strong> (die Lupe): klappt ein Feld auf, das vieles versteht — einen Ortsnamen, eine Koordinate in Grad, Plus Code oder UTM, ein Messtischblatt wie „4625/2", oder einen Kartenlink aus einer anderen Anwendung. Die Karte springt dorthin; es wird kein Punkt gesetzt.'
        => '<strong>Search</strong> (the magnifier): unfolds a field that understands a lot — a place name, a coordinate in degrees, Plus Code or UTM, a map sheet like "4625/2", or a map link from another application. The map jumps there; no point is placed.',
    '<strong>Verwaltung</strong> (das Zahnrad): die zweite Seite mit allem, was nicht ins Gelände gehört — Einstellungen, Offline-Gebiete, Cloud, Freunde und Freigaben, Organisieren, Import, Papierkorb. Ihr Aufbau steht unter 2; ein Klick auf den Namen der App oben links bringt dich von dort zur Karte zurück.'
        => '<strong>Administration</strong> (the gear): the second page with everything that does not belong in the field — settings, offline areas, cloud, friends and shares, Organisieren, import, trash. Its layout is described under 2; a click on the app\'s name top left brings you back to the map.',
    'Die Leiste hat zwei Reiter, und die beiden sind <strong>eine Sache von zwei Seiten</strong>. Unter „Listen" wählst du aus, was du sehen willst; unter „Punkte" steht, was diese Wahl ergibt. Es gibt keine dritte Stelle, an der Punkte auftauchen: Was auf der Karte liegt, steht auch in der Punkteliste, und was die Punkteliste zeigt, liegt auch auf der Karte.'
        => 'The sidebar has two tabs, and they are <strong>one thing seen from two sides</strong>. Under "Listen" you choose what you want to see; under "Punkte" stands what that choice yields. There is no third place where points turn up: what lies on the map is in the point list, and what the point list shows lies on the map.',
    '<strong>Eine Liste ist eine Ablage</strong> — ein Ordner für Punkte mit eigener Farbe und Form für die Marker. Ein Punkt kann in mehreren Listen liegen.'
        => '<strong>A list is a filing</strong> — a folder for points with its own colour and shape for the markers. A point can lie in several lists.',
    '<strong>Ein Klick auf eine Liste blendet ihre Punkte ein oder aus.</strong> Eingeblendete Listen sind hervorgehoben; die Zahl am Reiter „Listen" sagt, wie viele es gerade sind, die Zahl am Reiter „Punkte", wie viele Punkte das ergibt.'
        => '<strong>A click on a list shows or hides its points.</strong> Shown lists are highlighted; the number on the "Listen" tab says how many there are right now, the number on the "Punkte" tab how many points that yields.',
    '<strong>Filter kommen obendrauf.</strong> Sie stehen über der Punkteliste und halten von den eingeblendeten Punkten zurück, was nicht passt — auf der Karte und in der Liste zugleich. Eine Liste einblenden und dann filtern ist also der Weg zu „nur die Röhrlinge vom letzten Wochenende".'
        => '<strong>Filters come on top.</strong> They stand above the point list and hold back what does not fit among the shown points — on the map and in the list alike. Showing a list and then filtering is the way to "only the boletes of last weekend".',
    '<strong>Die Wahl bleibt.</strong> Welche Listen eingeblendet sind, merkt sich das Gerät; beim nächsten Öffnen steht die Karte so da wie verlassen.'
        => '<strong>The choice stays.</strong> The device remembers which lists are shown; the next opening finds the map as it was left.',
    'Oben deine eigenen Listen, darunter unter „Geteilt", was andere mit dir teilen. Eine Liste darf Unterlisten haben — dann ist sie eine <strong>Überschrift</strong> und hält selbst nichts: Bekommt eine Liste ihre erste Unterliste, wandern ihre Punkte hinunter. Farbe und Form der Marker kommen deshalb immer von der untersten Ebene.'
        => 'Your own lists on top, below them under "Geteilt" what others share with you. A list may have sublists — then it is a <strong>heading</strong> and holds nothing itself: when a list gets its first sublist, its points move down. Colour and shape of the markers therefore always come from the lowest level.',
    'Eine Überschrift mit ihren Unterlisten — ein Klick auf die Zeile klappt sie zu und wieder auf.'
        => 'A heading with its sublists — a click on the row folds it shut and open again.',
    'Eine Liste, die Punkte hält: Form und Farbe stehen davor, die Zahl dahinter. Ein Klick blendet ihre Punkte ein oder aus.'
        => 'A list that holds points: shape and colour in front, the count behind. A click shows or hides its points.',
    'Der Stift öffnet die Liste selbst: Name, Farbe, Form, Fundfelder, Löschen.'
        => 'The pencil opens the list itself: name, colour, shape, find fields, delete.',
    '„Geteilt": was andere mit dir teilen — siehe den nächsten Abschnitt.'
        => '"Geteilt": what others share with you — see the next section.',
    '<strong>„Ohne Liste"</strong> sammelt Punkte, die in keiner Liste liegen — etwa aus einem Import, dessen Liste du gelöscht hast. Sie lassen sich wie alle anderen ein- und ausblenden.'
        => '<strong>"Ohne Liste"</strong> collects points that lie in no list — from an import whose list you deleted, say. They can be shown and hidden like all others.',
    '<strong>„Neue Liste"</strong> in der Fußzeile legt eine Liste an. Ob sie eine Artenliste ist — mit Vorschlägen aus den Namenslisten und den Fundfeldern —, entscheidest du dort.'
        => '<strong>"Neue Liste"</strong> in the footer creates a list. Whether it is a species list — with suggestions from the name lists and the find fields — you decide there.',
    'Unter „Geteilt" steht, was nicht deins ist und trotzdem auf deine Karte gehört: die <strong>Freigaben</strong> deiner Freunde und Arbeitsgruppen, jede mit den Listen, die darin hängen — und darunter deine <strong>Exkursionen</strong>, die du leitest oder an denen du teilnimmst, mit den Funden aller Beteiligten. Beides wird wie eine eigene Liste ein- und ausgeblendet; die Zeilen stehen im leisen Ton, damit man sie von den eigenen unterscheidet.'
        => 'Under "Geteilt" stands what is not yours and still belongs on your map: the <strong>shares</strong> of your friends and working groups, each with the lists hung into it — and below them your <strong>excursions</strong>, the ones you lead or take part in, with the finds of everybody involved. Both are shown and hidden like a list of your own; the rows stand in the quiet tone so they can be told from your own.',
    '<strong>Eine Freigabe</strong> ist ein Container mit Listen mehrerer Leute (siehe 2.3). Der Stift daran öffnet ihre Einstellungen, die Sprechblase ihren Chat. Die Punkte in einer fremden Liste gehören dem, der sie gesetzt hat; was du daran ändern darfst, bestimmt er in der Freigabe — der Rest steht im Fenster ausgegraut.'
        => '<strong>A share</strong> is a container with the lists of several people (see 2.3). Its pencil opens its settings, the speech bubble its chat. The points in a foreign list belong to whoever placed them; what you may change is decided by them in the share — the rest stands greyed out in the window.',
    '<strong>Eine Exkursion</strong> zeigt eingeblendet die Funde aller — deine, die der Gäste, die der Mitleitenden. „Alle Exkursionen" ganz oben meint jede, auch die noch zusammengefalteten und die der nächsten Saison; die zehn jüngsten stehen ausgeschrieben, „weitere laden" klappt den Rest auf. Anlegen und leiten tust du in der Verwaltung (2.4).'
        => '<strong>An excursion</strong>, when shown, brings everybody\'s finds — yours, the guests\', the co-leaders\'. "Alle Exkursionen" at the very top means every one, the still folded ones and next season\'s included; the ten youngest are written out, "weitere laden" unfolds the rest. Creating and leading happens in the administration (2.4).',
    '<strong>Wegstrecken</strong> der anderen kommen denselben Weg: Die Aufzeichnung eines Freundes erscheint auf deiner Karte, wenn seine Funde des Tages in einer geteilten Liste liegen — in der Gegenfarbe deines Akzents, damit sie nicht wie deine aussieht.'
        => 'Other people\'s <strong>tracks</strong> come the same way: a friend\'s recording appears on your map when his finds of that day lie in a shared list — in the opposite colour of your accent, so it does not look like yours.',
    'Die Punkte der eingeblendeten Listen, <strong>die neuesten zuerst</strong>. Jede Zeile trägt den Titel, darunter Fundzeit und Ort; bei einem fremden Punkt steht davor, von wem er ist. Die zwei Knöpfe rechts über den Zeilen schalten die Ordnung um: nach Zeit, oder nach Namen — dann stehen gleiche Arten beieinander.'
        => 'The points of the shown lists, <strong>the newest first</strong>. Each row carries the title, find time and place below it; a foreign point says whose it is in front. The two buttons on the right above the rows switch the order: by time, or by name — then the same species stand together.',
    'Der Reiter Punkte mit der Filterzeile und den Zeilen der Punkte'
        => 'The Punkte tab with the filter row and the rows of the points',
    'Der Filterknopf klappt die Filtergruppen auf; die Zahl daran sagt, wie viele Filter gerade wirken.'
        => 'The filter button unfolds the filter groups; the number on it says how many filters are in effect.',
    'Ein Punkt: Titel, Fundzeit, Ort. Am Rechner öffnet ein Klick sein Fenster und die Karte springt hin; auf dem Handy zeigt er den Punkt erst auf der Karte, das Fenster öffnet ein zweiter Tipp.'
        => 'A point: title, find time, place. At the desk a click opens its window and the map jumps there; on the phone it first shows the point on the map, a second tap opens the window.',
    'Die Ordnung: nach Zeit — die neuesten zuerst — oder nach Namen.'
        => 'The order: by time — the newest first — or by name.',
    '<strong>Zeit:</strong> eine Tour aus der Liste — deine und die deiner Freunde, die mit „von …" gekennzeichnet sind — oder ein Zeitraum von/bis.'
        => '<strong>Time:</strong> a tour from the list — yours and your friends\', which are marked "von …" — or a range from/to.',
    '<strong>Auf der Karte:</strong> „Punkt setzen" antippen, dann in die Karte tippen. Erneutes Antippen bricht ab. Langes Drücken auf „Punkt setzen" beginnt eine Fläche statt eines Punktes.'
        => '<strong>On the map:</strong> tap "Punkt setzen", then tap into the map. Tapping again cancels. A long press on "Punkt setzen" begins an area instead of a point.',
    'Der neue Punkt fragt zuerst nach seiner Liste — und am ersten Punkt des Tages nach dem Namen der Tour, den alle weiteren Punkte des Tages dann mitbekommen (siehe Touren). Danach öffnet sich sein Fenster.'
        => 'The new point first asks for its list — and at the first point of the day for the name of the tour, which every further point of the day then carries (see Touren). Then its window opens.',
    'Ein Punkt hat ein Fenster mit drei Reitern, und <strong>gespeichert wird unten</strong> — nichts steht darunter. Was du in einem fremden Punkt nicht ändern darfst, steht ausgegraut da, damit du es trotzdem lesen kannst.'
        => 'A point has a window with three tabs, and <strong>saving happens at the bottom</strong> — nothing stands below it. What you may not change in a foreign point stands greyed out, so you can still read it.',
    'Der Titel. In einer Artenliste schlägt er wissenschaftliche Namen vor — und auf dem Handy auch ohne Empfang, wenn die Namensliste geladen ist.'
        => 'The title. In a species list it suggests scientific names — on the phone even without reception, once the name list is loaded.',
    'Die Reiter: „Fotos" für Aufnehmen und Auswählen, „Einordnung" für Listen, Koordinaten, Fläche, Fundzeit und die Fundfelder deiner Liste.'
        => 'The tabs: "Fotos" for taking and choosing pictures, "Einordnung" for lists, coordinates, area, find time and the find fields of your list.',
    'Speichern. „Zeigen" daneben rückt den Punkt in die Kartenmitte, der Papierkorb oben rechts löscht ihn — in den Papierkorb der Verwaltung, nicht endgültig.'
        => 'Save. "Zeigen" beside it moves the point to the centre of the map, the bin top right deletes it — into the administration\'s trash, not for good.',
    '<strong>Fotos:</strong> „Aufnehmen" öffnet die Kamera des Handys, das Bild kommt verkleinert auf den Server und wandert von dort in deine Cloud, wenn du eine verbunden hast. „Auswählen" nimmt eine fertige Datei — Mikrofoto, Stack, Rohdatei — in voller Größe direkt in die Cloud. Beides landet in derselben Galerie; ein Klick auf ein Bild öffnet es groß, mit Messwerkzeug und Maßstab.'
        => '<strong>Photos:</strong> "Aufnehmen" opens the phone\'s camera, the picture reaches the server reduced and moves on into your cloud if you have one connected. "Auswählen" takes a finished file — micrograph, stack, raw file — at full size straight into the cloud. Both land in the same gallery; a click on a picture opens it large, with measuring tool and scale.',
    '<strong>Einordnung:</strong> die Listen, in denen der Punkt liegt; die Koordinaten zum Kopieren; ein Umriss, wenn der Fund eine Fläche ist; die Fundzeit, vorbelegt mit jetzt; und die Fundfelder deiner Liste — Finder, Bestimmer, Substrat, was du dort angelegt hast.'
        => '<strong>Einordnung:</strong> the lists the point lies in; the coordinates for copying; an outline if the find is an area; the find time, preset to now; and the find fields of your list — finder, determiner, substrate, whatever you created there.',
    '<strong>Ziehen:</strong> Einen Marker kannst du verschieben, solange sein Fenster offen ist. Die neue Lage steht dann im Koordinatenfeld und gilt erst mit „Speichern" — wer das Fenster schließt, findet den Punkt an seiner alten Stelle.'
        => '<strong>Dragging:</strong> you can move a marker while its window is open. The new position then stands in the coordinate field and counts only with "Speichern" — whoever closes the window finds the point at its old place.',
    '<strong>Standort</strong> zeigt, wo das Gerät dich sieht, und folgt dir, bis du die Karte selbst verschiebst. Ein zweiter Tipp setzt einen Punkt dorthin.'
        => '<strong>Standort</strong> shows where the device sees you and follows you until you move the map yourself. A second tap places a point there.',
    '<strong>Punkt setzen</strong> — siehe oben. Der Knopf leuchtet, solange die Karte auf den Tipp wartet.'
        => '<strong>Punkt setzen</strong> — see above. The button glows while the map waits for the tap.',
    '<strong>Die Kartenwahl</strong> oben rechts: die Grundkarte (Luftbild, Topografie, OpenStreetMap), darunter Ebenen zum Zuschalten — Schummerung, Höhenlinien, Gesteinstyp, Baumarten, Schutzgebiete — und die <strong>Wegstrecken</strong> der Aufzeichnungs-App. Eine Ebene bleibt an, bis du sie ausschaltest; jedes Gerät merkt sich seine eigene Wahl.'
        => '<strong>The map picker</strong> top right: the base map (aerial, topographic, OpenStreetMap), below it overlays to switch on — hillshade, contour lines, rock type, tree species, protected areas — and the <strong>tracks</strong> of the recorder app. An overlay stays on until you switch it off; every device remembers its own choice.',
    '<strong>Die Sprechblase</strong> oben links öffnet die Chats mit deinen Freunden. Sie erscheint erst, wenn du Freunde hast (Verwaltung → Gemeinsam → Freunde), und trägt eine Zahl, wenn etwas ungelesen ist.'
        => '<strong>The speech bubble</strong> top left opens the chats with your friends. It appears only once you have friends (Verwaltung → Gemeinsam → Freunde) and carries a number when something is unread.',
    '<strong>Meldungen</strong> unter der Kopfzeile der Karte — eine Freundschaftsanfrage, eine laufende Exkursion, die dich zum Mitkartieren einlädt — sind Knöpfe: antippen heißt annehmen oder ansehen, das Kreuz heißt später.'
        => '<strong>Notices</strong> under the map\'s top edge — a friend request, a running excursion inviting you to map along — are buttons: tapping means accept or look, the cross means later.',
    '<strong>Ohne Empfang</strong> arbeitet die Karte weiter: Punkte entstehen sofort und reisen, sobald wieder Netz da ist; eine Zeile unten sagt, wie viele noch warten, und „Jetzt versuchen" stößt es an.'
        => '<strong>Without reception</strong> the map keeps working: points come into being at once and travel as soon as there is a network again; a line at the bottom says how many are still waiting, and "Jetzt versuchen" gives it a push.',
    'Das Zahnrad in der Kopfzeile der Leiste führt hierher. Die Verwaltung ist in <strong>Bereiche</strong> geteilt, jeder mit eigenen Reitern; die Adresse merkt sich den Reiter, ein Lesezeichen führt also wieder genau dorthin.'
        => 'The gear in the sidebar\'s header leads here. The administration is divided into <strong>areas</strong>, each with its own tabs; the address remembers the tab, so a bookmark leads back exactly there.',
    'Die Bereiche der Verwaltung und die Reiter von „Mein Bereich"'
        => 'The areas of the administration and the tabs of "Mein Bereich"',
    'Die Bereiche: „Mein Bereich" für jeden — und für den Betreiber „Administration" und „Statistik".'
        => 'The areas: "Mein Bereich" for everyone — and for the operator "Administration" and "Statistik".',
    'Die Reiter von „Mein Bereich"; einige haben darunter noch einmal Reiter.'
        => 'The tabs of "Mein Bereich"; some carry tabs of their own below.',
    '<strong>Einstellungen:</strong> Konto, Oberfläche, Arten, Fundnummer, Geräte — siehe 2.7.'
        => '<strong>Einstellungen:</strong> account, interface, species, find number, devices — see 2.7.',
    '<strong>Offline:</strong> Gebiete und Daten fürs Gelände — siehe 2.2.'
        => '<strong>Offline:</strong> areas and data for the field — see 2.2.',
    '<strong>Cloud:</strong> dein eigener Speicher für Fotos und die Datensicherung — siehe 2.6.'
        => '<strong>Cloud:</strong> your own storage for photos and the backup — see 2.6.',
    '<strong>Gemeinsam:</strong> Freunde, Arbeitsgruppen, Freigaben (2.3), Exkursionen (2.4) — alles, was mehrere Leute betrifft.'
        => '<strong>Gemeinsam:</strong> friends, working groups, shares (2.3), excursions (2.4) — everything that concerns several people.',
    '<strong>Organisieren:</strong> die Punkte als Tabelle, dazu Touren und Autolisten — siehe 2.1.'
        => '<strong>Organisieren:</strong> the points as a table, plus tours and auto lists — see 2.1.',
    '<strong>Import</strong> (2.5) und <strong>Papierkorb</strong>: Gelöschtes liegt dort, bis du es zurückholst oder endgültig löschst.'
        => '<strong>Import</strong> (2.5) and <strong>Papierkorb</strong>: deleted things lie there until you bring them back or delete them for good.',
    '<strong>Administration</strong> sieht nur der Betreiber: Benutzer, Migrationen, Datenbankabzug, Taxonomie — und „Nachtragen", wo der Server für vorhandene Punkte Höhen, Messtischblätter, Gemeinden, Schutzgebiete und Bildkopien nachträgt. Das läuft nachts von selbst; die Knöpfe nehmen einen Durchgang vorweg.'
        => '<strong>Administration</strong> is seen by the operator alone: users, migrations, database dump, taxonomy — and "Nachtragen", where the server backfills elevations, map sheets, municipalities, protected areas and picture copies for existing points. That runs at night by itself; the buttons take a round in advance.',

    // Help, chapter 1 as the maintainer dictated it (15.09.2026)
    'Zoomen und Verschieben'
        => 'Zooming and panning',
    'GPS — der Standort'
        => 'GPS — the location',
    'Die Kartenlayer'
        => 'The map layers',
    'Der Chat'
        => 'The chat',
    'Die Seitenleiste (links)'
        => 'The sidebar (left)',
    'Punktfenster (Seitenleiste rechts)'
        => 'Point window (sidebar on the right)',
    'Die App besteht aus <strong>drei Teilen</strong>: links die Seitenleiste, in der Mitte die Karte, rechts das Punktfenster. Die Karte ist die Hauptsache — auf ihr liegen deine Funde als Marker, und auf ihr sitzen ein paar Knöpfe, die hier zuerst erklärt werden. Die beiden Leisten kommen danach; was in ihnen steckt, haben eigene Kapitel.'
        => 'The app has <strong>three parts</strong>: the sidebar on the left, the map in the middle, the point window on the right. The map is the main thing — your finds lie on it as markers, and a few buttons sit on it that are explained first. The two sidebars come after; what is inside them has chapters of its own.',
    'Die drei Teile: Seitenleiste links, Karte, Punktfenster rechts'
        => 'The three parts: sidebar left, map, point window right',
    'Plus und Minus: die Karte vergrößern und verkleinern (<a href="#zoomen">%s</a>).'
        => 'Plus and minus: zoom the map in and out (<a href="#zoomen">%s</a>).',
    'Standort: schaltet das GPS ein (<a href="#gps">%s</a>).'
        => 'Location: switches the GPS on (<a href="#gps">%s</a>).',
    'Punkt setzen: einen neuen Fund eintragen (<a href="#setzen">%s</a>).'
        => 'Place a point: enter a new find (<a href="#setzen">%s</a>).',
    'Die Kartenlayer: Grundkarte und Ebenen wählen (<a href="#layer">%s</a>).'
        => 'The map layers: choose base map and overlays (<a href="#layer">%s</a>).',
    'Die Sprechblase: der Chat mit deinen Freunden (<a href="#chat">%s</a>).'
        => 'The speech bubble: the chat with your friends (<a href="#chat">%s</a>).',
    'Der Maßstabsbalken: wie lang ein Stück Karte in Metern ist.'
        => 'The scale bar: how long a piece of map is in metres.',
    'Die Seitenleiste links: die Verwaltungszentrale (<a href="#leiste">%s</a>).'
        => 'The sidebar on the left: the control centre (<a href="#leiste">%s</a>).',
    'Die Seitenleiste rechts: das Punktfenster (<a href="#fenster">%s</a>).'
        => 'The sidebar on the right: the point window (<a href="#fenster">%s</a>).',
    '<strong>Am Rechner:</strong> Das Mausrad vergrößert und verkleinert die Karte an der Stelle, über der die Maus steht; die Knöpfe <strong>+</strong> und <strong>−</strong> rechts unten tun dasselbe in der Kartenmitte. Verschieben: die linke Maustaste gedrückt halten und ziehen. Ein Doppelklick zoomt eine Stufe hinein.'
        => '<strong>At the desk:</strong> the mouse wheel zooms the map in and out at the spot under the mouse; the buttons <strong>+</strong> and <strong>−</strong> bottom right do the same at the map\'s centre. Panning: hold the left mouse button and drag. A double click zooms in one step.',
    '<strong>Am Handy:</strong> Zwei Finger auseinander ziehen vergrößert, zusammenziehen verkleinert. Ein Finger verschiebt die Karte. Zweimal tippen zoomt eine Stufe hinein.'
        => '<strong>On the phone:</strong> spreading two fingers zooms in, pinching zooms out. One finger pans the map. A double tap zooms in one step.',
    'Der Maßstabsbalken links unten sagt dir, wie viel Meter ein Stück Karte gerade sind — beim Hineinzoomen wird die Zahl darauf kleiner.'
        => 'The scale bar bottom left tells you how many metres a piece of map is right now — the number on it shrinks as you zoom in.',
    'Freier Himmel hilft: weg von Hauswänden, raus aus dem Auto. Am Rechner ohne GPS ortet der Browser nur grob über das Netz.'
        => 'Open sky helps: away from house walls, out of the car. At a desk without GPS the browser locates only roughly via the network.',
    'Die Seitenleiste rechts ist das <strong>Punktfenster</strong>. Sie öffnet sich, wenn du einen Punkt setzt oder einen Marker antippst. Bei einem eigenen Fund ist sie der Editor: Name, Fotos, Fundzeit, Notiz, alles wird hier eingetragen und unten gespeichert. Bei einem fremden Fund — einem aus einer Freigabe — ist sie ein Anzeigefenster: du siehst alles, änderst aber nur, was der Besitzer dir erlaubt hat.'
        => 'The sidebar on the right is the <strong>point window</strong>. It opens when you place a point or tap a marker. For a find of your own it is the editor: name, photos, find time, note, everything is entered here and saved at the bottom. For a foreign find — one from a share — it is a display window: you see everything, but change only what its owner allowed you to.',
    'Der linke der beiden Knöpfe unten, <strong>Standort</strong>, schaltet das GPS ein. Beim ersten Mal fragt der Browser, ob die App deinen Standort kennen darf — das musst du erlauben, sonst bleibt der Knopf stumm. Dann springt die Karte zu dir und zeigt dich als <strong>blauen Punkt</strong>, und die Karte folgt dir beim Gehen.'
        => 'The left of the two buttons at the bottom, <strong>Standort</strong>, switches the GPS on. The first time, the browser asks whether the app may know your location — you have to allow it, or the button stays silent. Then the map jumps to you and shows you as a <strong>blue dot</strong>, and the map follows you as you walk.',
    'Um den blauen Punkt liegt ein <strong>zweiter, größerer Kreis</strong>. Er zeigt, wie genau das GPS dich gerade sieht: Irgendwo in diesem Kreis bist du wirklich. Je größer der Kreis, desto ungenauer die Ortung — unter Bäumen oder zwischen Häusern wird er groß, unter freiem Himmel schrumpft er nach ein paar Sekunden auf wenige Meter. Ein Punkt, den du setzt, während der Kreis noch groß ist, kann entsprechend danebenliegen.'
        => 'Around the blue dot lies a <strong>second, larger circle</strong>. It shows how accurately the GPS sees you right now: somewhere inside this circle is where you really are. The larger the circle, the less accurate the fix — under trees or between houses it grows, under open sky it shrinks to a few metres after some seconds. A point placed while the circle is still large may lie off accordingly.',
    'Sobald du die Karte selbst verschiebst, hört sie auf, dir zu folgen; der blaue Punkt bleibt. Ein Tipp auf den Knopf lässt die Karte wieder mitlaufen, ein weiterer schaltet den Standort aus.'
        => 'As soon as you move the map yourself it stops following you; the blue dot stays. A tap on the button makes the map follow again, another switches the location off.',
    'Der rechte der beiden Knöpfe unten, <strong>Punkt setzen</strong>, trägt einen neuen Fund ein. Wo der Punkt landet, hängt vom GPS ab:'
        => 'The right of the two buttons at the bottom, <strong>Punkt setzen</strong>, enters a new find. Where the point lands depends on the GPS:',
    '<strong>Folgt dir der blaue Punkt</strong> (<a href="#gps">%s</a>), setzt ein Tipp auf „Punkt setzen" den Fund genau an deinen Standort — du musst nichts auf der Karte treffen.'
        => '<strong>If the blue dot follows you</strong> (<a href="#gps">%s</a>), a tap on "Punkt setzen" places the find exactly at your location — nothing on the map has to be hit.',
    '<strong>Ist das GPS aus, oder liegt dein Standort außerhalb des Kartenausschnitts</strong>, wirst du aufgefordert, die Stelle selbst zu wählen: am Rechner mit einem Klick in die Karte, am Handy mit einem Tipp. Nochmal auf „Punkt setzen" tippen, bevor du die Stelle wählst, bricht ab.'
        => '<strong>If the GPS is off, or your location lies outside the visible map</strong>, you are asked to choose the spot yourself: at the desk with a click on the map, on the phone with a tap. Tapping "Punkt setzen" again before choosing cancels.',
    'Danach öffnet sich zuerst dieses Fenster:'
        => 'Then this window opens first:',
    'Das Fenster nach „Punkt setzen": Tour, Neue Tour, Listen'
        => 'The window after "Punkt setzen": tour, new tour, lists',
    '<strong>Tour:</strong> der Name des heutigen Ausflugs. Vorgeschlagen wird „Tagestour"; du kannst ihn ändern. Jeder Fund, den du heute setzt, bekommt diesen Namen mit — so findest du den ganzen Ausflug später mit einem Griff wieder.'
        => '<strong>Tour:</strong> the name of today\'s outing. "Tagestour" is proposed; you can change it. Every find you place today carries this name — so you find the whole outing again later in one go.',
    '<strong>Neue Tour:</strong> beendet die laufende Tour und beginnt eine zweite am selben Tag — etwa nach dem Ortswechsel am Nachmittag. Der Vorschlag zählt dann hoch: „Tagestour #2".'
        => '<strong>Neue Tour:</strong> ends the running tour and begins a second one on the same day — after moving to another place in the afternoon, say. The proposal then counts up: "Tagestour #2".',
    '<strong>In welche Listen?</strong> Vorangehakt ist genau das, was du dir gerade auf der Karte anzeigen lässt — die Listen, die in der Seitenleiste eingeblendet sind (<a href="#leiste">%s</a>). Zu sehen sind zuerst nur diese Listen. Findest du etwas außer der Reihe, zeigt dir <strong>Listen ändern</strong> alle deine Listen. Haken kannst du setzen und entfernen, mehrere sind möglich. Ohne Haken landet der Fund unter „Ohne Liste".'
        => '<strong>In welche Listen?</strong> Pre-ticked is exactly what you are showing on the map right now — the lists shown in the sidebar (<a href="#leiste">%s</a>). At first only these lists are shown. If you find something out of turn, <strong>Listen ändern</strong> shows all your lists. You can tick and untick, several are possible. Without a tick the find lands under "Ohne Liste".',
    '„Weiter" führt rechts ins Punktfenster (<a href="#fenster">%s</a>), in dem du den Fund beschreibst.'
        => '"Weiter" leads to the point window on the right (<a href="#fenster">%s</a>), where you describe the find.',
    'So ist die App gedacht'
        => 'How the app is meant',
    // App or browser — each keeps its own storage (help, 27.09.2026)
    'App oder Browser?' => 'App or browser?',
    'YASAIMAPP läuft auf zwei Wegen: als App für Android oder im Browser, zum Beispiel in Chrome oder Firefox.'
        => 'YASAIMAPP runs in two ways: as an app for Android, or in the browser, for example in Chrome or Firefox.',
    '<strong>Jeder Weg hat seinen eigenen Speicher.</strong> Was du in der App auf das Handy lädst, liegt nur in der App. Was du in Chrome lädst, liegt nur in Chrome, und Firefox hat wieder seinen eigenen.'
        => '<strong>Each way has its own storage.</strong> What you load onto the phone in the app lies only in the app. What you load in Chrome lies only in Chrome, and Firefox has its own again.',
    '<strong>Ein Beispiel:</strong> Du lädst in Chrome ein Offline-Gebiet mit 350 MB herunter. Öffnest du im Wald die App, sind die Karten dort nicht da. Du müsstest das Gebiet in der App ein zweites Mal laden. Dann liegen 700 MB auf dem Handy, zweimal dieselben Karten.'
        => '<strong>An example:</strong> you download an offline area of 350 MB in Chrome. If you open the app in the woods, the maps are not there. You would have to load the area a second time in the app. Then 700 MB lie on the phone, the same maps twice.',
    '<strong>Auch deine Einstellungen merkt sich jeder Weg für sich:</strong> das Aussehen unter Oberfläche, welche Listen eingeschaltet und welche zugeklappt sind, deine Filter, die Grundkarte, die Stelle, an der die Karte zuletzt stand, und die laufende Tour. Wechselst du von Chrome in die App, sieht dort vieles anders aus. Anmelden musst du dich in jedem Weg einmal.'
        => '<strong>Each way also keeps your settings to itself:</strong> the look under Oberfläche, which lists are switched on and which are folded, your filters, the base map, where the map last stood, and the running tour. If you switch from Chrome to the app, much looks different there. You sign in once in each way.',
    '<strong>Jeder Weg legt außerdem von selbst seine eigene Kopie für unterwegs an:</strong> deine Funde, die Namenslisten der Arten, Belegfotos, die du dir angesehen hast, und Kartenteile, die beim Umsehen mitgeladen werden. Wer zwei oder drei Wege benutzt, hat all das zwei- oder dreimal auf dem Handy. Das kostet unnötig Platz.'
        => '<strong>Each way also makes its own copy for the road, by itself:</strong> your finds, the species name lists, voucher photos you have looked at, and map pieces loaded while you look around. Whoever uses two or three ways has all of that two or three times on the phone. That takes space for nothing.',
    'Punkte, die du ohne Empfang gesetzt hast, warten dort auf die Übertragung, wo du sie gesetzt hast. <strong>Deine Funde und Listen selbst sind überall dieselben:</strong> Sie liegen auf dem Server, sobald sie übertragen sind.'
        => 'Points you set without reception wait to be sent where you set them. <strong>Your finds and lists themselves are the same everywhere:</strong> they lie on the server as soon as they have been sent.',
    'Hast du die App, dann benutze nur noch sie und nicht zusätzlich den Browser. Wer ständig wechselt, hat nur Nachteile: alles doppelt auf dem Handy, andere Einstellungen und Punkte, die an zwei Stellen auf Empfang warten. Willst du nur im Browser arbeiten, dann in Chrome.'
        => 'Once you have the app, use only the app and not the browser as well. Switching back and forth brings only drawbacks: everything twice on the phone, different settings, and points waiting for reception in two places. If you want to work in the browser only, use Chrome.',
    '<strong>Darum unsere Empfehlung:</strong>' => '<strong>So our recommendation:</strong>',
    '<strong>Installiere die App.</strong> Mit ihr kannst du am meisten machen, und sie ist am bequemsten. Sie zeichnet zum Beispiel deinen Weg auf, auch wenn der Bildschirm aus ist.'
        => '<strong>Install the app.</strong> It lets you do the most, and it is the most comfortable. For example, it records your route even while the screen is off.',
    '<strong>Ohne App nimm am Handy Chrome.</strong> Chrome arbeitet mit dem GPS des Handys besser zusammen als Firefox.'
        => '<strong>Without the app, use Chrome on the phone.</strong> Chrome works better with the phone\'s GPS than Firefox.',
    'Was du herunterlädst, liegt nur in der App oder in dem Browser, in dem du es geladen hast — siehe <a href="#app-browser">%s</a>.'
        => 'What you download lies only in the app or in the browser you loaded it in — see <a href="#app-browser">%s</a>.',
    'YASAIMAPP kartiert <strong>Arten</strong>. Jeder Fund kommt in eine <strong>Grundliste seines Reichs</strong> — Pilze, Flechten, Pflanzen —, und zwar immer in dieselbe. Nicht nach Orten sortieren, nicht nach Gemeinden, nicht nach Wäldern oder Revieren: Wo ein Fund liegt, weiß die Karte von selbst, und Ort, Gemeinde, Messtischblatt oder Gebiet findest du jederzeit über die Filter und über Autolisten wieder.'
        => 'YASAIMAPP maps <strong>species</strong>. Every find goes into a <strong>base list of its kingdom</strong> — fungi, lichens, plants — and always into the same one. Do not sort by place, not by municipality, not by wood or territory: where a find lies the map knows by itself, and place, municipality, map sheet or area you find again any time through the filters and through auto lists.',
    'Zu Abschnitt %s'
        => 'To section %s',
    'Bevor du losgehst, überlege dir, was du heute hauptsächlich kartierst — zum Beispiel Pilze — und schalte <strong>nur diese eine Liste</strong> ein. Beim Setzen eines Punktes ist dann genau sie schon angehakt, und jeder Fund ist ein einziger Tipp auf „Weiter“. Findest du zwischendurch eine Flechte, wählst du kurz die Pilzliste ab und die Flechtenliste an. Sind Pilze und Flechten dauernd eingeblendet, sind bei jedem Punkt beide angehakt, und du musst jedes Mal einen Haken entfernen. Deshalb: immer nur <strong>eine Liste</strong> an, und für den Fund außer der Reihe die passende Liste wählen. So einfach ist das Konzept, und alles Weitere baut darauf auf.'
        => 'Before you set off, think about what you will mainly map today — fungi, say — and switch on <strong>only that one list</strong>. When you place a point, exactly it is already ticked, and every find is a single tap on “Weiter”. If you come across a lichen in between, briefly untick the fungi list and tick the lichen list. If fungi and lichens are shown all the time, both are ticked at every point, and every time you have to remove a tick. So: always just <strong>one list</strong> on, and the matching list for the find out of turn. That simple is the concept, and everything else builds on it.',
    '<strong>Das entscheidet, ob dich die App nervt oder ob du sie richtig bedienst.</strong> Überlege dir vor dem Losgehen, was du heute hauptsächlich kartierst — zum Beispiel Pilze — und schalte nur diese Liste ein: Dann ist hier bei jedem Fund genau sie angehakt, und du tippst nur noch „Weiter“. Für eine Flechte zwischendurch wählst du kurz die Pilzliste ab und die Flechtenliste an. Sind beide dauernd eingeblendet, musst du bei jedem Punkt einen Haken entfernen — deshalb immer nur eine Liste, und für den Fund außer der Reihe die passende Liste.'
        => '<strong>This decides whether the app annoys you or you use it right.</strong> Before setting off, think about what you will mainly map today — fungi, say — and switch on only that list: then exactly it is ticked here at every find, and all you tap is “Weiter”. For a lichen in between, briefly untick the fungi list and tick the lichen list. If both are shown all the time, you have to remove a tick at every point — so always just one list, and the matching list for the find out of turn.',
    'Nach dem Anmelden hast du bereits die <strong>Mappe „Meine Funde"</strong> mit drei Listen: Flechten, Pflanzen und Pilze, jede mit eigener Farbe und eigenem Zeichen. Weitere Bereiche legst du genauso an, und was du nicht brauchst, löschst du — wie das geht, steht unter <a href="#listen">%s</a>.'
        => 'After signing in you already have the <strong>folder “Meine Funde”</strong> with three lists: lichens, plants and fungi, each with its own colour and its own sign. You create further areas the same way, and what you do not need you delete — how that works is under <a href="#listen">%s</a>.',
    'Die Ablage dafür: die Mappe „Meine Funde", die du nach dem Anmelden schon hast, und darin je eine Liste pro Reich — wie du sie anlegst, steht unter <a href="#listen">%s</a>. Im Fenster stehen die Listen einer Mappe eingerückt darunter, ein Klick auf den Mappennamen klappt sie auf und zu. Wer seine Funde trotzdem nach Revieren sehen will, legt dafür eine <strong>Autoliste</strong> an (<a href="#organisieren">%s</a>): eine Liste, die sich selbst aus einem Filter füllt, ohne dass beim Setzen etwas anzuhaken wäre.'
        => 'The filing for it: the folder “Meine Funde” you already have after signing in, and in it one list per kingdom — how to create them is under <a href="#listen">%s</a>. In the window the lists of a folder stand indented below it, and a click on the folder name folds them open and shut. Whoever still wants to see finds by territory creates an <strong>auto list</strong> for it (<a href="#organisieren">%s</a>): a list that fills itself from a filter, with nothing to tick when placing.',
    'Der Knopf <strong>oben rechts</strong> mit den gestapelten Ebenen öffnet die Kartenwahl:'
        => 'The button <strong>top right</strong> with the stacked layers opens the map picker:',
    'Die Kartenwahl: oben die Grundkarten, unten die Zusatzlayer'
        => 'The map picker: base maps on top, overlays below',
    '<strong>Die Karten:</strong> Luftbild und OpenStreetMap. Genau eine davon ist immer an.'
        => '<strong>The maps:</strong> aerial, topographic map, OpenStreetMap. Exactly one of them is always on.',
    '<strong>Die Zusatzlayer:</strong> Ebenen, die du über die Karte legst — Höhenlinien, Geländeschummerung, Gesteinstyp, Baumarten, Schutzgebiete — und die Wegstrecken, die es nur gibt, wenn du deine Gänge mit der <strong>Android-App</strong> aufzeichnest (dazu ein eigenes Kapitel). Beliebig viele zugleich; was du wählst, merkt sich das Gerät.'
        => '<strong>The overlays:</strong> layers you lay over the map — contour lines, hillshade, rock type, tree species, protected areas — and the tracks, which exist only if you record your walks with the <strong>Android app</strong> (a chapter of its own). Any number at once; the device remembers what you choose.',
    'Ein eingeschalteter Zusatzlayer zeigt <strong>unten links</strong> seine Legende — hier Baumarten, Schutzgebiete und Gesteinstyp:'
        => 'A switched-on overlay shows its legend <strong>bottom left</strong> — here tree species, protected areas and rock type:',
    'Die Legenden der eingeschalteten Zusatzlayer'
        => 'The legends of the switched-on overlays',
    'Jeder Layer hat seinen Kasten; die Überschrift klappt ihn zu und wieder auf.'
        => 'Every layer has its box; the heading folds it shut and open again.',
    'Die Zeilen darin sind die Unterebenen des Layers, und <strong>ein Klick auf eine Zeile schaltet sie aus oder wieder ein</strong> — bei den Baumarten etwa lässt du nur die Buche stehen und blendest alle anderen Arten aus.'
        => 'The rows in it are the layer\'s sub-layers, and <strong>a click on a row switches it off or on again</strong> — with the tree species, say, you leave only the beech standing and hide all other species.',
    'Die <strong>Sprechblase oben links</strong> öffnet die Chats mit deinen Freunden. Sie funktioniert nur, wenn du Freunde hast — deshalb ist sie erst da, wenn du welche hast, und trägt einen grünen Punkt, wenn Nachrichten ungelesen sind. Wie du Freunde einlädst oder anfragst, um mit ihnen chatten zu können, steht in der Verwaltung unter <a href="#freunde">%s</a>.'
        => 'The <strong>speech bubble top left</strong> opens the chats with your friends. It only works if you have friends — so it is there only once you have some, and carries a green dot when messages are unread. How to invite friends, or ask them, to be able to chat with them is in the administration under <a href="#freunde">%s</a>.',
    'Die Reiter'
        => 'The tabs',
    'Die Seitenleiste links ist die <strong>Verwaltungszentrale</strong>. Sie hat drei Teile: oben die Kopfzeile, darunter die zwei Reiter, unten die Fußzeile. Von hier aus verlässt du die App, rufst die Hilfe auf, suchst einen Ort, gehst in die Verwaltung — und bestimmst, <strong>welche deiner Punkte die Karte zeigt</strong>. Am Handy legt sich die Leiste über die Karte; das Menüzeichen ☰ oben links öffnet sie.'
        => 'The sidebar on the left is the <strong>control centre</strong>. It has three parts: the header on top, the two tabs below it, the footer at the bottom. From here you leave the app, open the help, search for a place, go to the administration — and decide <strong>which of your points the map shows</strong>. On the phone the sidebar lies over the map; the menu sign ☰ top left opens it.',
    '<strong>Zur Karte</strong> (der Name der App ganz links): führt von der Verwaltung und von der Hilfe zurück zur Karte.'
        => '<strong>To the map</strong> (the app\'s name at the far left): leads back to the map from the administration and from the help.',
    '<strong>Abmelden</strong> (die Tür): beendet die Anmeldung auf diesem Gerät.'
        => '<strong>Sign out</strong> (the door): ends the login on this device.',
    '<strong>Verwaltung</strong> (das Zahnrad): die zweite Seite der App mit allem, was nicht ins Gelände gehört — siehe <a href="#verwaltung">%s</a>.'
        => '<strong>Administration</strong> (the gear): the app\'s second page with everything that does not belong in the field — see <a href="#verwaltung">%s</a>.',
    'Die Kopfzeile: zur Karte, Abmelden, Hilfe, Suche, Verwaltung (<a href="#kopfzeile">%s</a>).'
        => 'The header: to the map, sign out, help, search, administration (<a href="#kopfzeile">%s</a>).',
    'Die Reiter „Listen" und „Punkte" (<a href="#reiter">%s</a>).'
        => 'The "Listen" and "Punkte" tabs (<a href="#reiter">%s</a>).',
    'Die Kopfzeile der Seitenleiste'
        => 'The sidebar\'s header',
    'Die Seitenleiste am Rechner, mit dem Griff an der Kante'
        => 'The sidebar at the desk, with the grip on its edge',
    'Die Seitenleiste am Handy, mit „Schließen" in der Fußzeile'
        => 'The sidebar on the phone, with "Schließen" in the footer',
    '<strong>Die Leiste schließen:</strong> am Rechner ein Klick auf den Griff an der Kante — der Streifen am linken Rand holt sie zurück. Der Griff kann noch etwas: Ziehst du ihn, statt zu klicken, wird die Leiste breiter oder schmaler. Am Handy „Schließen" in der Fußzeile — das Menüzeichen ☰ oben links öffnet sie wieder.'
        => '<strong>Closing the sidebar:</strong> at the desk a click on the grip on its edge — the strip at the left edge brings it back. The grip can do one more thing: drag it instead of clicking, and the sidebar gets wider or narrower. On the phone "Schließen" in the footer — the menu sign ☰ top left opens it again.',
    '<strong>Die Suche</strong> im Bild: links das aufgeklappte Feld mit den Treffern zu einem Ortsnamen, rechts die Karte nach dem Sprung — ein Fadenkreuz markiert die gefundene Stelle, bis du die Suche leerst.'
        => '<strong>The search</strong> in pictures: on the left the unfolded field with the hits for a place name, on the right the map after the jump — a crosshair marks the spot found, until you clear the search.',
    'Die Suche mit ihren Treffern'
        => 'The search with its hits',
    'Die gefundene Stelle mit dem Fadenkreuz'
        => 'The spot found, with the crosshair',
    'Das Suchfeld — ein Ortsname, eine Koordinate, ein Messtischblatt, ein Kartenlink.'
        => 'The search field — a place name, a coordinate, a map sheet, a map link.',
    'Die Treffer zum Ortsnamen; ein Tipp springt hin. Eine Koordinate braucht keine Liste — die Karte springt sofort.'
        => 'The hits for the place name; a tap jumps there. A coordinate needs no list — the map jumps at once.',
    'Das Fadenkreuz auf der gefundenen Stelle. Es setzt keinen Punkt — das tust du, wenn du willst, mit „Punkt setzen".'
        => 'The crosshair on the spot found. It places no point — you do that, if you want, with "Punkt setzen".',
    'Desktop-Version'
        => 'Desktop version',
    'Handy-Version'
        => 'Phone version',
    'Zwei Reiter, eine Sache von zwei Seiten: Unter <strong>Listen</strong> wählst du, welche Listen die Karte zeigt; unter <strong>Punkte</strong> stehen genau die Punkte dieser Listen. Die Zahl an jedem Reiter sagt, wie viele es gerade sind.'
        => 'Two tabs, one thing seen from two sides: under <strong>Listen</strong> you choose which lists the map shows; under <strong>Punkte</strong> stand exactly the points of those lists. The number on each tab says how many there are right now.',
    'Unter <strong>Meine Listen</strong> stehen deine Listen. Mehrere Listen kannst du in einer <strong>Mappe</strong> zusammenfassen. Eine Mappe ist nur ein Name, unter dem die Listen beieinander stehen. Sie hat keine eigenen Punkte und keine eigenen Einstellungen. Daneben gibt es Listen ohne Mappe, und ganz unten die Rückfallliste <strong>Ohne Liste</strong> für Punkte, die in keiner Liste liegen. Eine neue Mappe legst du unten in der Seitenleiste an, mit dem Knopf <strong>Neue Mappe</strong> neben „Neue Liste".'
        => 'Under <strong>Meine Listen</strong> stand your lists. You can gather several lists in one <strong>folder</strong>. A folder is only a name under which the lists stand together. It has no points of its own and no settings of its own. Besides that there are lists without a folder, and at the very bottom the fallback list <strong>Ohne Liste</strong> for points that lie in no list. You create a new folder at the bottom of the sidebar with the button <strong>Neue Mappe</strong> beside “Neue Liste”.',
    'Meine Listen: eine aufgeklappte Mappe, eine zugeklappte mit einer Markierung darin, eine Liste ohne Mappe, und „Ohne Liste"'
        => 'My lists: an open folder, a folded one with a mark inside it, a list without a folder, and “Ohne Liste”',
    '<strong>Eine Mappe</strong>, aufgeklappt: Das Dreieck klappt sie zu und wieder auf. Die Listen stehen eingerückt darunter. Der Haken vor der Mappe blendet alle ihre Listen auf einmal ein oder aus. Ein Klick auf eine Liste blendet nur ihre Punkte ein oder aus.'
        => '<strong>A folder</strong>, open: the triangle folds it shut and open again. The lists stand indented below it. The tick in front of the folder shows or hides all its lists at once. A click on one list shows or hides only its points.',
    '<strong>Ohne Liste</strong>: Punkte, die in keiner Liste liegen — etwa nach dem Löschen ihrer Liste. Sie lassen sich wie alle anderen ein- und ausblenden.'
        => '<strong>Ohne Liste</strong>: points that lie in no list — after deleting their list, say. They can be shown and hidden like all others.',
    '<strong>Eine zugeklappte Mappe</strong>: Ist darin eine Liste eingeblendet, leuchtet die Mappe. So siehst du, dass etwas darunter läuft, ohne aufzuklappen.'
        => '<strong>A folded folder</strong>: if a list inside it is shown, the folder lights up. So you see that something below it is on without unfolding.',
    '<strong>Eine Liste ohne Mappe</strong>: Form und Farbe davor.'
        => '<strong>A list without a folder</strong>: shape and colour in front of it.',
    '<strong>Der Zähler</strong> an einer zugeklappten Mappe: „1/2" heißt, eine von zwei Listen ist eingeblendet. Er leuchtet erst ab einem Haken; „0/2" sagt, dass darunter gerade nichts an ist.'
        => '<strong>The counter</strong> on a folded folder: “1/2” means one of two lists is shown. It lights up only from a tick on; “0/2” says that nothing below it is on right now.',
    '<strong>Der Bereich „Meine Listen"</strong> lässt sich als Ganzes zuklappen — das Dreieck vor der Überschrift —, genauso der Bereich „Geteilt" darunter. Zugeklappt trägt auch er den Zähler, wie viele seiner Listen an sind.'
        => '<strong>The "Meine Listen" area</strong> can be folded as a whole — the triangle before its heading —, and so can the "Geteilt" area below it. Folded, it carries the counter too, saying how many of its lists are on.',
    '<strong>Der Stift</strong> steht an jeder Liste und an jeder Mappe. An einer Liste öffnet er das Fenster der Liste. An einer Mappe kannst du sie nur umbenennen oder löschen. Löschst du eine Mappe, bleiben ihre Listen mit allen Punkten erhalten und stehen danach ohne Mappe da. So sieht das Fenster einer Liste aus, hier für „Pilze":'
        => '<strong>The pencil</strong> stands at every list and at every folder. At a list it opens the list’s window. At a folder you can only rename or delete it. If you delete a folder, its lists stay with all their points and stand there without a folder afterwards. This is what a list’s window looks like, here for “Pilze”:',
    'Das Fenster der Liste „Pilze"'
        => 'The window of the list “Pilze”',
    '<strong>Der Name</strong> der Liste.'
        => '<strong>The name</strong> of the list.',
    '<strong>Mappe:</strong> Hier legst du die Liste in eine Mappe oder nimmst sie wieder heraus. Die Auswahl erscheint, sobald du eine Mappe hast. Die Liste behält dabei alle Punkte und Einstellungen.'
        => 'Folder: here you put the list into a folder or take it out again. The choice appears as soon as you have a folder. The list keeps all its points and settings.',
    '<strong>Farbe</strong> und <strong>Form</strong> der Markierung: Beides gilt für die Marker dieser Liste auf der Karte.'
        => 'Colour and shape of the marker: both apply to this list’s markers on the map.',
    '<strong>Artenliste:</strong> mit dem Haken schlägt das Titelfeld eines Punktes wissenschaftliche Namen vor, und die Fundfelder darunter kommen dazu. Für die Listen der Reiche ist er gesetzt. Aus welchen Namenslisten die Vorschläge kommen — Pilze, Flechten, Pflanzen, Tiere —, legst du in der Verwaltung unter Einstellungen → Arten fest (<a href="#arten">%s</a>).'
        => '<strong>Species list:</strong> with the tick, a point\'s title field suggests scientific names, and the find fields below come with it. For the base lists of the kingdoms it is set. Which name lists the suggestions come from — fungi, lichens, plants, animals — you decide in the administration under Einstellungen → Arten (<a href="#arten">%s</a>).',
    '<strong>Die Fundfelder</strong> einer Artenliste — Finder, Bestimmer, Bestimmungssicherheit, Menge, Substrat, Begleitvegetation, Geruch, Geschmack, Sporenpulverfarbe, Beleg-Nummer, Fundbeschreibung — stehen hier zur Ansicht.'
        => '<strong>The find fields</strong> of a species list — finder, determiner, certainty, quantity, substrate, accompanying vegetation, odour, taste, spore print colour, voucher number, find description — stand here for viewing.',
    '<strong>Eigene Felder:</strong> „Feld hinzufügen" legt ein weiteres Feld an, das jeder Punkt dieser Liste bekommt — einzeilig oder mehrzeilig, mit Name und Vorgabe.'
        => '<strong>Custom fields:</strong> "Feld hinzufügen" creates one more field every point of this list gets — single-line or multi-line, with a name and a default.',
    '<strong>Speichern</strong> unten rechts; „Liste löschen" steht daneben, sobald die Liste keine Artenliste mehr ist — eine Artenliste ist gegen Löschen geschützt.'
        => '<strong>Save</strong> bottom right; "Liste löschen" stands beside it as soon as the list is no species list any more — a species list is protected against deletion.',
    '<strong>Artenliste:</strong> mit dem Haken schlägt das Titelfeld eines Punktes wissenschaftliche Namen vor, und die Fundfelder darunter kommen dazu. Für die Grundlisten der Reiche ist er gesetzt. Aus welchen Namenslisten die Vorschläge kommen — Pilze, Flechten, Pflanzen, Tiere —, legst du in der Verwaltung unter Einstellungen → Arten fest (<a href="#arten">%s</a>).'
        => '<strong>Species list:</strong> with the tick, a point\'s title field suggests scientific names, and the find fields below come with it. For the base lists of the kingdoms it is set. Which name lists the suggestions come from — fungi, lichens, plants, animals — you decide in the administration under Einstellungen → Arten (<a href="#arten">%s</a>).',
    'Unter <strong>Geteilt</strong> stehen die <strong>Freigaben</strong>, in denen du Mitglied bist — deine eigenen und die deiner Freunde —, jede mit allen Listen, die darin hängen: die der anderen und deine eigenen, die du hineingehängt hast. Eine Freigabe legst du in der Verwaltung an, dort lädst du Freunde und Arbeitsgruppen ein und hängst Listen hinein: <a href="#freigaben">%s</a>. Hier in der Leiste blendest du ein und aus, was davon auf die Karte soll.'
        => 'Under <strong>Geteilt</strong> stand the <strong>shares</strong> you are a member of — your own and your friends\' — each with all the lists hung into it: the others\' and your own that you hung in. You create a share in the administration, invite friends and working groups there and hang lists in: <a href="#freigaben">%s</a>. Here in the sidebar you show and hide what of it goes onto the map.',
    'Der Bereich Geteilt mit einer eigenen und einer fremden Freigabe'
        => 'The Geteilt area with a share of your own and a foreign one',
    '<strong>Der Bereich „Geteilt"</strong> — das Dreieck klappt ihn als Ganzes zu, wie „Meine Listen" darüber; zugeklappt trägt er den Zähler mit den eingeblendeten Listen.'
        => '<strong>The "Geteilt" area</strong> — the triangle folds it as a whole, like "Meine Listen" above; folded, it carries the counter with the shown lists.',
    '<strong>Eine Freigabe</strong> — das Dreieck davor klappt ihre Listen auf und zu, ein Klick auf die Zeile ebenso. Die Listen darunter blendest du wie deine eigenen ein und aus; sie tragen den Namen dessen, dem sie gehören.'
        => '<strong>A share</strong> — the triangle before it folds its lists open and shut, a click on the row does the same. The lists below you show and hide like your own; they carry the name of whoever owns them.',
    '<strong>Die Sprechblase</strong> öffnet den Chat dieser Freigabe — alle Mitglieder lesen mit. Ein grüner Punkt daran heißt: ungelesen.'
        => '<strong>The speech bubble</strong> opens this share\'s chat — all members read along. A green dot on it means: unread.',
    '<strong>Der Stift an deiner eigenen Freigabe</strong> öffnet ihr Fenster:'
        => '<strong>The pencil on your own share</strong> opens its window:',
    'Das Fenster der eigenen Freigabe „Gemeinsame Funde"'
        => 'The window of your own share "Gemeinsame Funde"',
    '<strong>Der Name</strong> — alle Mitglieder sehen ihn.'
        => '<strong>The name</strong> — all members see it.',
    '<strong>Besitzer und Mitglieder:</strong> wer die Freigabe angelegt hat und wer dabei ist. Einladen — Freunde und Arbeitsgruppen — geht in der Verwaltung (<a href="#freigaben">%s</a>).'
        => '<strong>Owner and members:</strong> who created the share and who is in. Inviting — friends and working groups — happens in the administration (<a href="#freigaben">%s</a>).',
    '<strong>Der Filter</strong>, der der Freigabe zugrunde liegt: welche Punkte der eingehängten Listen sie überhaupt zeigt — alle, oder nur die aus einem Zeitraum oder Gebiet. Er wird beim Anlegen festgelegt und lässt sich <strong>nachträglich nicht ändern</strong>: Worauf sich die Mitglieder einmal eingelassen haben, darauf können sie sich verlassen.'
        => '<strong>The filter</strong> the share rests on: which points of the hung-in lists it shows at all — all, or only those from a period or an area. It is set when the share is created and <strong>cannot be changed afterwards</strong>: what the members once agreed to, they can rely on.',
    '<strong>Eigene Listen einhängen:</strong> der Baum deiner Listen — ein Haken hängt eine Liste in die Freigabe, die Mitglieder sehen dann ihre Punkte; der Haken weg nimmt sie wieder heraus. Das geht hier, jederzeit, auch nachträglich.'
        => '<strong>Hanging in your own lists:</strong> the tree of your lists — a tick hangs a list into the share, the members then see its points; removing the tick takes it out again. That works here, any time, also later.',
    '<strong>Speichern.</strong>'
        => '<strong>Save.</strong>',
    '<strong>Der Stift an einer fremden Freigabe</strong> öffnet dasselbe Fenster, aber es ändert nichts beim Besitzer:'
        => '<strong>The pencil on a foreign share</strong> opens the same window, but it changes nothing at the owner\'s:',
    'Das Fenster der fremden Freigabe „Pilzfreunde Nord"'
        => 'The window of the foreign share "Pilzfreunde Nord"',
    '<strong>Wie du ihn nennst:</strong> ein Name nur für dich — die anderen sehen weiter den Namen des Erstellers. Leer heißt: wie beim Ersteller.'
        => '<strong>What you call it:</strong> a name only for you — the others keep seeing the creator\'s name. Empty means: as the creator\'s.',
    '<strong>Die Tür hinter deinem Namen</strong> ist dein Austritt: Du verlässt die Freigabe, ihre Listen verschwinden von deiner Karte, und deine eingehängten Listen kommen wieder heraus. Der Besitzer kann dich neu einladen.'
        => '<strong>The door behind your name</strong> is your exit: you leave the share, its lists vanish from your map, and your hung-in lists come out again. The owner can invite you anew.',
    '<strong>Der Filter</strong> des Besitzers — auch hier fest, du siehst, worauf du dich verlässt.'
        => '<strong>The owner\'s filter</strong> — fixed here too; you see what you rely on.',
    '<strong>Eigene Listen einhängen</strong> geht auch in einer fremden Freigabe: Was du hier anhakst, sehen die anderen Mitglieder.'
        => '<strong>Hanging in your own lists</strong> works in a foreign share too: what you tick here, the other members see.',
    '%s — Freigabe „%s" von %s'
        => '%s — share "%s" by %s',
    '%s — meine Freigabe „%s"'
        => '%s — my share "%s"',
    '<strong>Der Stift an einer fremden Liste</strong> öffnet ein kleines Fenster, das nichts beim Besitzer ändert:'
        => '<strong>The pencil on a foreign list</strong> opens a small window that changes nothing at the owner\'s:',
    'Das Fenster einer fremden Liste'
        => 'The window of a foreign list',
    '<strong>Die Überschrift</strong> nennt die Liste, die Freigabe, in der sie hängt, und wem die Freigabe gehört — dieselbe Liste kann in mehreren Freigaben hängen.'
        => '<strong>The heading</strong> names the list, the share it hangs in, and who owns the share — the same list can hang in several shares.',
    '<strong>Deine Rechte:</strong> was dir der Besitzer der Liste an ihren Punkten erlaubt — nur ansehen, oder auch Titel und Notizen ändern, Punkte verschieben, löschen, Bilder löschen, bestimmte Felder ausfüllen. Das legt jeder für seine eigene Liste fest, wenn er sie in eine Freigabe hängt (<a href="#freigaben">%s</a>); du siehst hier, was er dir eingeräumt hat. Im Punktfenster steht dann alles andere ausgegraut.'
        => '<strong>Your rights:</strong> what the list\'s owner allows you on its points — viewing only, or also changing titles and notes, moving points, deleting them, deleting pictures, filling certain fields. Everybody sets that for their own list when hanging it into a share (<a href="#freigaben">%s</a>); here you see what they granted you. In the point window everything else then stands greyed out.',
    '<strong>Farbe auf diesem Gerät:</strong> die Marker der fremden Liste in einer Farbe deiner Wahl — nur auf deiner Karte, damit sie sich von deinen eigenen unterscheiden. Der Besitzer merkt davon nichts.'
        => '<strong>Colour on this device:</strong> the foreign list\'s markers in a colour of your choice — only on your map, so they can be told from your own. The owner notices nothing.',
    '<strong>Form auf diesem Gerät:</strong> ebenso die Form der Marker.'
        => '<strong>Shape on this device:</strong> likewise the markers\' shape.',
    '<strong>Zurücksetzen</strong> nimmt Farbe und Form wieder so, wie der Besitzer sie gewählt hat.'
        => '<strong>Reset</strong> takes colour and shape back to what the owner chose.',
    'Unter <strong>Exkursionen</strong> stehen die gemeinsamen Ausflüge, die du leitest oder mitleitest, und die, an denen du teilgenommen hast. Eingeblendet zeigt eine Exkursion die Funde <strong>aller</strong> Beteiligten — deine, die der Gäste, die der Mitleitenden. Anlegen, den Einladungs-QR-Code zeigen und leiten tust du in der Verwaltung: <a href="#exkursionen">%s</a>.'
        => 'Under <strong>Exkursionen</strong> stand the joint outings you lead or co-lead, and those you took part in. Shown, an excursion brings the finds of <strong>everybody</strong> involved — yours, the guests\', the co-leaders\'. Creating, showing the invitation QR code and leading happens in the administration: <a href="#exkursionen">%s</a>.',
    'Der Bereich Exkursionen in der Seitenleiste'
        => 'The Exkursionen area in the sidebar',
    '<strong>Der Bereich „Exkursionen"</strong> — wie die beiden über ihm als Ganzes zuklappbar, zugeklappt mit dem Zähler.'
        => '<strong>The "Exkursionen" area</strong> — foldable as a whole like the two above it, with the counter when folded.',
    '<strong>Alle Exkursionen</strong> meint jede — auch die noch nicht aufgeklappten und die der nächsten Saison. Wählst du danach eine einzelne, geht „Alle" von selbst wieder aus, und umgekehrt.'
        => '<strong>Alle Exkursionen</strong> means every one — the not yet unfolded ones and next season\'s included. If you then pick a single one, "Alle" switches itself off, and the other way round.',
    '<strong>Eine Exkursion</strong> mit ihrem Namen; bei einer fremden steht dahinter, wer sie leitet. Ein Klick blendet ihre Funde ein und aus. Die zehn jüngsten stehen ausgeschrieben, „Weitere zeigen" klappt den Rest auf — wann sie stattfand, ist eine Frage an den Zeitfilter.'
        => '<strong>An excursion</strong> with its name; on a foreign one, who leads it stands behind. A click shows and hides its finds. The ten youngest are written out, "Weitere zeigen" unfolds the rest — when it took place is a question for the time filter.',
    '<strong>Die Zahl</strong> der Funde auf ihr — deine eigenen mitgezählt.'
        => '<strong>The number</strong> of finds on it — your own counted in.',
    'Die Reiter der Verwaltung'
        => 'The administration\'s tabs',
    'zurück zu %s'
        => 'back to %s',
    'Was die Ortung beim Setzen eines Punktes bewirkt, steht unter <a href="#setzen">%s</a>.'
        => 'What the location does when placing a point is under <a href="#setzen">%s</a>.',
    'Wie ein Punkt entsteht und dieses Fenster erreicht, steht unter <a href="#setzen">%s</a>.'
        => 'How a point comes into being and reaches this window is under <a href="#setzen">%s</a>.',
    'Warum die Listen so aufgebaut sind — eine Liste je Reich —, steht ganz oben unter <a href="#mantra">„So ist die App gedacht"</a> und bei <a href="#setzen">%s</a>.'
        => 'Why the lists are built this way — one list per kingdom — is at the very top under <a href="#mantra">"So ist die App gedacht"</a> and at <a href="#setzen">%s</a>.',
    'Ob das Titelfeld einer Liste Vorschläge bekommt, entscheidet der Haken „Artenliste" im Fenster der Liste — <a href="#listen">%s</a>, Punkt 5.5.'
        => 'Whether a list\'s title field gets suggestions is decided by the "Artenliste" tick in the list\'s window — <a href="#listen">%s</a>, item 5.5.',
    'Mit Freunden chatten tust du auf der Karte über die Sprechblase — <a href="#chat">%s</a>.'
        => 'Chatting with friends happens on the map through the speech bubble — <a href="#chat">%s</a>.',
    'In der Seitenleiste erscheinen Freigaben unter „Geteilt" — beschrieben unter <a href="#geteilt">%s</a>.'
        => 'In the sidebar, shares appear under "Geteilt" — described under <a href="#geteilt">%s</a>.',
    'Die Exkursionen siehst du in der Seitenleiste unter „Exkursionen" — beschrieben unter <a href="#exkursionen-leiste">%s</a>.'
        => 'You see the excursions in the sidebar under "Exkursionen" — described under <a href="#exkursionen-leiste">%s</a>.',
    'Warum Arten in eine Grundliste gehören und Reviere eine Autoliste sind, steht ganz oben unter <a href="#mantra">„So ist die App gedacht"</a> und bei <a href="#setzen">%s</a>.'
        => 'Why species belong in a base list and territories are an auto list is at the very top under <a href="#mantra">"So ist die App gedacht"</a> and at <a href="#setzen">%s</a>.',
    'Der Reiter <strong>Punkte</strong> zeigt genau die Punkte der eingeblendeten Listen — die neuesten zuerst. Jede Zeile trägt den Titel, darunter Fundzeit und Ort; bei einem fremden Punkt steht davor, von wem er ist.'
        => 'The <strong>Punkte</strong> tab shows exactly the points of the shown lists — the newest first. Each row carries the title, find time and place below it; a foreign point says whose it is in front.',
    'Der Reiter Punkte: Filter, Sortierung, ein markierter Fund'
        => 'The Punkte tab: filter, sorting, a chosen find',
    '<strong>Der Filter</strong> — klappt die Filtergruppen auf; die Zahl daran sagt, wie viele Filter gerade wirken.'
        => '<strong>The filter</strong> — unfolds the filter groups; the number on it says how many filters are in effect.',
    '<strong>Die zwei Sortierknöpfe</strong> — nach Zeit, die neuesten zuerst, oder nach Namen, dann stehen gleiche Arten beieinander.'
        => '<strong>The two sort buttons</strong> — by time, the newest first, or by name, then the same species stand together.',
    '<strong>Ein markierter Fund</strong> — die gewählte Zeile trägt den Akzent an ihrer Kante. Am Rechner öffnet ein Klick das Punktfenster und die Karte springt hin; am Handy zeigt er den Punkt erst auf der Karte, ein zweiter Tipp öffnet das Fenster.'
        => '<strong>A chosen find</strong> — the chosen row wears the accent on its edge. At the desk a click opens the point window and the map jumps there; on the phone it first shows the point on the map, a second tap opens the window.',
    'Der Reiter Punkte am Handy, über der Karte'
        => 'The Punkte tab on the phone, over the map',
    '<strong>Zurück zur Karte</strong> — am Rechner der Griff an der Kante der Leiste (ein Klick klappt sie ein, Ziehen macht sie breiter oder schmaler), am Handy der Knopf „Schließen“: Dort liegt die Leiste über der Karte.'
        => '<strong>Back to the map</strong> — at the desk the grip on the sidebar\'s edge (a click folds it in, dragging makes it wider or narrower), on the phone the “Schließen” button: there the sidebar lies over the map.',
    'Filter halten zurück, was gerade nicht gemeint ist — auf der Karte und in der Punkteliste zugleich. Sie sind in acht Gruppen geordnet; der Knopf „Filter" klappt sie auf, die Zahl daran sagt, wie viele Filter wirken. <strong>Dieselben Filter</strong> stehen in der Verwaltung unter Organisieren (<a href="#organisieren">%s</a>) — dort wählen sie Punkte für die Tabelle aus, hier für die Karte.'
        => 'Filters hold back what is not meant right now — on the map and in the point list alike. They are ordered in eight groups; the "Filter" button unfolds them, the number on it says how many filters are in effect. <strong>The same filters</strong> stand in the administration under Organisieren (<a href="#organisieren">%s</a>) — there they pick points for the table, here for the map.',
    'Die acht Filtergruppen'
        => 'The eight filter groups',
    '<strong>Eine Zeile ist eine Bedingung.</strong> Jede Zeile sagt, ob ihr Text vorkommen soll („enthält") oder gerade nicht („enthält nicht") — das Zeichen vor dem Feld stellt um. <strong>Mehrere Zeilen gelten alle zugleich</strong> (und): Jede muss erfüllt sein. <strong>Kommas innerhalb einer Zeile heißen oder</strong>: Einer der Begriffe genügt — bei „enthält nicht": keiner darf vorkommen.'
        => '<strong>One row is one condition.</strong> Each row says whether its text should occur ("enthält") or precisely not ("enthält nicht") — the sign before the field switches. <strong>Several rows all apply at once</strong> (and): each must hold. <strong>Commas inside a row mean or</strong>: one of the terms suffices — with "enthält nicht": none may occur.',
    'Der Zeitfilter'
        => 'The time filter',
    'Der Ortsfilter'
        => 'The place filter',
    'Der Höhenfilter'
        => 'The elevation filter',
    'Der Bestimmungsfilter'
        => 'The identification filter',
    'Der Systematikfilter'
        => 'The taxonomy filter',
    'Der Gefährdungsfilter'
        => 'The threat filter',
    'Die Filter hier sind dieselben wie in der Seitenleiste — erklärt unter <a href="#filter">%s</a>.'
        => 'The filters here are the same as in the sidebar — explained under <a href="#filter">%s</a>.',
    '<strong>Auch die Wegstrecken</strong> (Zusatzlayer, <a href="#layer">%s</a>) gehorchen dem Filter: Eine Strecke bleibt, solange auf ihr ein Fund liegt, der den Filter besteht — und verschwindet, sobald alle ihre Funde ausgefiltert sind. Eine Strecke ohne Funde folgt nur dem Zeitraum und geht, sobald irgendein anderer Filter gesetzt ist.'
        => '<strong>The tracks too</strong> (overlay, <a href="#layer">%s</a>) obey the filter: a track stays as long as a find lies on it that passes the filter — and disappears once all its finds are sifted out. A track without finds follows the time range only and goes as soon as any other filter is set.',
    'Der Filter (<a href="#filter">%s</a>) siebt auch die Wegstrecken: eine Strecke bleibt, solange ein passender Fund auf ihr liegt.'
        => 'The filter (<a href="#filter">%s</a>) sifts the tracks too: a track stays as long as a matching find lies on it.',
    'Beispiel 1: eine Zeile, zwei Arten mit Komma'
        => 'Example 1: one row, two species with a comma',
    'Beispiel 2: eine Zeile auf „enthält nicht" mit zwei Gattungen'
        => 'Example 2: one row set to "enthält nicht" with two genera',
    'Beispiel 1 — nur zwei Arten'
        => 'Example 1 — two species only',
    'Beispiel 2 — ohne zwei Gattungen'
        => 'Example 2 — without two genera',
    'Du willst alle Funde von Boletus edulis und Boletus reticulatus sehen. Das ist <em>eine</em> Zeile mit Komma: <code>Boletus edulis, Boletus reticulatus</code>. Gelesen heißt die Zeile: <em class="help-read">enthält Boletus edulis oder Boletus reticulatus</em>.'
        => 'You want to see every find of Boletus edulis and Boletus reticulatus. That is <em>one</em> row with a comma: <code>Boletus edulis, Boletus reticulatus</code>. Read out, the row says: <em class="help-read">contains Boletus edulis or Boletus reticulatus</em>.',
    'Schreibst du dagegen Boletus edulis in die erste Zeile und Boletus reticulatus in die zweite, liest sich das: <em class="help-read">enthält Boletus edulis und enthält Boletus reticulatus</em> — beides in einem Fund, und das gibt es nicht: Die Karte bliebe leer.'
        => 'Write Boletus edulis into the first row and Boletus reticulatus into the second instead, and it reads: <em class="help-read">contains Boletus edulis and contains Boletus reticulatus</em> — both in one find, and there is no such thing: the map would stay empty.',
    'Du willst alles außer Morcheln und Verpeln. Eine Zeile auf „enthält nicht" stellen und <code>Morchella, Verpa</code> eintragen. Gelesen heißt die Zeile: <em class="help-read">enthält nicht Morchella oder Verpa</em>.'
        => 'You want everything except morels and Verpa. Set one row to "enthält nicht" and enter <code>Morchella, Verpa</code>. Read out, the row says: <em class="help-read">does not contain Morchella or Verpa</em>.',
    'Hier ginge es auch zweizeilig — erste Zeile „enthält nicht" <code>Morchella</code>, zweite Zeile „enthält nicht" <code>Verpa</code> — und läse sich: <em class="help-read">enthält nicht Morchella und enthält nicht Verpa</em>. Beides darf ein Fund zugleich erfüllen, also kommt dasselbe heraus.'
        => 'Here two rows would work as well — first row "enthält nicht" <code>Morchella</code>, second row "enthält nicht" <code>Verpa</code> — and would read: <em class="help-read">does not contain Morchella and does not contain Verpa</em>. A find can meet both at once, so the result is the same.',
    'Sucht in allem, was ein Punkt trägt: Titel, Notiz, Fundfelder, Messtischblatt, Gemeinde, Schutzgebiet.'
        => 'Searches everything a point carries: title, note, find fields, map sheet, municipality, protected area.',
    'Der Textfilter'
        => 'The text filter',
    '„Letzte Tour …" klappt die Liste deiner letzten Touren auf, die deiner Freunde mit „von …" gekennzeichnet; darunter ein Zeitraum von/bis — der kleine Pfeil dazwischen übernimmt das Datum von „von" nach „bis", für einen einzelnen Tag oder als Anfang eines Zeitraums. Der <strong>Jahresregler</strong> darunter hat zwei Anfasser auf Jahresgrenzen — das älteste Jahr deines Bestands links, das jüngste rechts; ein Jahr ist eine Stufe, ganz auseinander heißt alle Jahre. Was du ziehst, steht sofort in „von" und „bis" — zwei Griffe, und du siehst die Funde eines Jahres oder zweier. „ohne Fundzeit" zeigt die Punkte, die keine tragen — solche entstehen nur durch Importe; beim Kartieren mit der App bekommt jeder Punkt seine Fundzeit.'
        => '"Letzte Tour …" unfolds the list of your last tours, your friends\' marked "von …"; below it a range from/to — the small arrow between them copies the date from "von" to "bis", for a single day or as the start of a range. The <strong>year band</strong> below has two handles on year boundaries — the oldest year of your stock on the left, the newest on the right; one year is one step, fully apart means all years. What you drag stands in "von" and "bis" at once — two grips, and you see the finds of one year or two. "ohne Fundzeit" shows the points that carry none — those come from imports only; a point mapped with the app always gets its find time.',
    'Bereiche, die du auf der Karte zeichnest (3.1); Bundesländer; Gemeinden; Messtischblätter („4625/2", Komma heißt oder). Jede Wahl verengt, was die anderen anbieten.'
        => 'Areas you draw on the map (3.1); states; municipalities; map sheets ("4625/2", comma means or). Each choice narrows what the others offer.',
    'Ein Regler mit zwei Enden: die untere und die obere Höhe über dem Meer, ganz außen heißt „alle Höhen". Das obere Ende ist offen: Steht es ganz rechts, zählen auch Funde über 2500 m — beide Enden ganz rechts zeigt also alles ab 2500 m. Die Höhe eines Punktes trägt der Server nach.'
        => 'A slider with two ends: the lower and the upper height above sea level, all the way out means "alle Höhen". The upper end is open: at the far right, finds above 2500 m count too — both ends at the far right thus shows everything from 2500 m up. A point\'s elevation is backfilled by the server.',
    'Nur nicht bestimmte Funde; die Kürzel im Titel (sp., cf., aff., s. l., s. str., agg. — s. str. ist keine offene Bestimmung, sondern die enge, und fällt deshalb nicht unter „nur nicht bestimmte"); ob Material vorliegt: bearbeitet oder unbearbeitet; und das Feld Bestimmungssicherheit — makroskopisch sicher, mikroskopisch sicher, genetisch sicher, unsicher.'
        => 'Undetermined finds only; the marks in the title (sp., cf., aff., s. l., s. str., agg. — s. str. is not an open identification but the narrow one, and so does not fall under "nur nicht bestimmte"); whether material exists: worked or unworked; and the certainty field — macroscopically certain, microscopically certain, genetically certain, uncertain.',
    'Reich, Stamm, Klasse, Ordnung, Familie, Gattung: Was du unten wählst, ergibt die Ränge darüber von selbst; angeboten wird nur, was im Bestand vorkommt. Wo wenige zur Wahl stehen, ist der Rang ein Klappmenü; wo viele, ein Textfeld, das beim Tippen vorschlägt — ein paar Buchstaben genügen, ein Tipp auf den Vorschlag füllt das Feld.'
        => 'Kingdom, phylum, class, order, family, genus: what you choose below yields the ranks above by itself; only what occurs in your stock is offered. Where few are on offer the rank is a drop-down; where many, a text field that suggests as you type — a few letters suffice, a tap on the suggestion fills the field.',
    'Die Kategorien der Roten Liste; mehrere angehakt heißt: eine davon genügt. Darunter der Haken „nur Verantwortungsarten“: Arten, für deren Erhalt Deutschland eine besondere Verantwortung trägt — die Liste der DGfM. Er gilt zusätzlich zu den Kategorien.'
        => 'The Red List categories; several ticked means: one of them suffices. Below them the hook “nur Verantwortungsarten”: species for whose survival Germany bears a particular responsibility — the DGfM\'s list. It applies in addition to the categories.',
    // --- The DGfM lists (17.09.2026): filter groups, point window, administration
    'Speisepilz'
        => 'edible mushroom',
    'uneinheitlich beurteilt'
        => 'inconsistently rated',
    'Giftpilz'
        => 'poisonous mushroom',
    'Verantwortungsart'
        => 'responsibility species',
    'nur Verantwortungsarten'
        => 'responsibility species only',
    'Arten, für deren Erhalt Deutschland eine besondere Verantwortung trägt — die Liste der DGfM'
        => 'Species for whose survival Germany bears a particular responsibility — the DGfM\'s list',
    'Speisewert'
        => 'Food value',
    'Aussage der DGfM über die ganze Gattung'
        => 'The DGfM\'s statement about the whole genus',
    'Liste der DGfM'
        => 'A DGfM list',
    'DGfM-Listen'
        => 'DGfM lists',
    'Migration 0108 ist noch nicht eingespielt.'
        => 'Migration 0108 has not been applied yet.',
    'Einlesen gescheitert: %s'
        => 'Reading failed: %s',
    '%d Zeilen gelesen, %d Arten und %d Gattungen zugeordnet (%d davon über die Schreibweisen-Tabelle), %d Gattungszeilen meinen nur einen Teil und setzen keine Marke, %d Namen nicht gefunden.'
        => '%d rows read, %d species and %d genera matched (%d of them through the spelling table), %d genus rows mean only a part and set no mark, %d names not found.',
    'Migration 0108 ist noch nicht eingespielt — bis dahin gibt es keine DGfM-Marken.'
        => 'Migration 0108 has not been applied yet — until then there are no DGfM marks.',
    'Die vier Listen des DGfM-Fachausschusses Pilzverwertung und Toxikologie und die Verantwortungsarten — als Marken an der Art, für den Filter (Speisewert, Verantwortungsart), das Punktfenster und den Fundbericht. Die Namen werden gegen die Pilzreferenz aufgelöst; was nicht passt, steht unten.'
        => 'The four lists of the DGfM\'s expert committee on mushroom use and toxicology and the responsibility species — as marks on the species, for the filter (food value, responsibility), the point window and the find report. The names are resolved against the fungus reference; what does not fit stands below.',
    'Noch nichts eingelesen.'
        => 'Nothing read yet.',
    'Stand'
        => 'Issue',
    'zugeordnet'
        => 'matched',
    'davon Gattungen'
        => 'of which genera',
    'Nicht in der Pilzreferenz gefunden — die Schreibweise der DGfM, wie sie in der Liste steht. Diese Namen gelten über den Fundtitel: Beginnt der Titel eines Fundes mit dem Namen, trägt er die Marke trotzdem.'
        => 'Not found in the fungus reference — the DGfM\'s spelling as it stands in the list. These names apply through the find\'s title: a find whose title begins with the name carries the mark all the same.',
    'Listen einlesen'
        => 'Read the lists',
    'aus %s, Datei vom %s'
        => 'from %s, file of %s',
    'data/dgfm/marks.csv fehlt'
        => 'data/dgfm/marks.csv is missing',
    'Die drei Listen des DGfM-Fachausschusses Pilzverwertung und Toxikologie: Speisepilz, uneinheitlich beurteilt, Giftpilz — mehrere angehakt heißt: eine davon genügt. Eine Art kann mehrere Marken tragen; Morcheln stehen als Speisepilz mit Warnung, als uneinheitlich beurteilt und roh als Giftpilz. Sagt die Liste etwas über eine ganze Gattung, gilt es für jede ihrer Arten. Die Marken einer Art zeigt das Punktfenster (<a href="#fenster">%s</a>) in seiner Faktenzeile; die Bemerkung der DGfM erscheint, wenn du mit der Maus darüber gehst.'
        => 'The three lists of the DGfM\'s expert committee on mushroom use and toxicology: edible, inconsistently rated, poisonous — several ticked means: one of them suffices. A species may carry several marks; morels stand as edible with a warning, as inconsistently rated, and raw as poisonous. Where the list speaks of a whole genus, it holds for each of its species. The marks of a species show in the point window (<a href="#fenster">%s</a>) on its fact line; the DGfM\'s remark appears when you hover over them.',
    'Die Marken der DGfM-Listen in der Faktenzeile — Speisepilz, Giftpilz, Verantwortungsart — erklärt der Filter (<a href="#filter">%s</a>).'
        => 'The marks of the DGfM lists on the fact line — edible, poisonous, responsibility species — are explained at the filter (<a href="#filter">%s</a>).',
    'Der Speisewertfilter'
        => 'The food-value filter',
    'Probe: Boletus edulis an der Referenz → %s. Funde, deren Titelart eine Marke trägt: %d von %d mit Artverweis.'
        => 'Probe: Boletus edulis at the reference → %s. Finds whose title species carries a mark: %d of %d with a species reference.',
    'keine Marke'
        => 'no mark',
    'Gattungszeilen, die laut ihrer Bemerkung nur einen Teil der Gattung meinen — sie setzen keine Marke, denn die Liste nennt die Arten nicht:'
        => 'Genus rows whose remark means only part of the genus — they set no mark, because the list does not name the species:',
    'Datum von „von" nach „bis" übernehmen'
        => 'Copy the date from "von" to "bis"',
    'Ein Bereich ist ein Stück Karte, das du selbst umrandest — dein Revier, ein Waldstück, eine Wiese. Der Filter zeigt dann nur die Funde darin. Wer ein Gebiet bearbeitet, braucht diesen Filter am häufigsten. Ein Tipp auf „Bereich zeichnen …" (3.1) schiebt die Seitenleiste beiseite und macht die Karte zum Zeichenblatt:'
        => 'An area is a piece of the map you outline yourself — your patch, a wood, a meadow. The filter then shows only the finds inside it. Whoever works a territory needs this filter most. A tap on "Bereich zeichnen …" (3.1) moves the sidebar aside and turns the map into a drawing sheet:',
    'Die Karte beim Zeichnen eines Bereichs'
        => 'The map while an area is being drawn',
    '<strong>Ecke für Ecke antippen.</strong> Jede Ecke wird ein Punkt, und vom letzten folgt die Linie dem Zeiger bis zur nächsten. Drei Ecken sind das Mindeste, nach oben gibt es keine Grenze.'
        => '<strong>Tap corner by corner.</strong> Each corner becomes a dot, and from the last one the line follows the pointer to the next. Three corners are the least; there is no upper limit.',
    '<strong>Ecke zurück</strong> nimmt die letzte Ecke wieder weg; <strong>Verwerfen</strong> die ganze Zeichnung.'
        => '<strong>Ecke zurück</strong> takes the last corner away again; <strong>Verwerfen</strong> the whole drawing.',
    '<strong>Fertig</strong> schließt den Umriss — die Seitenleiste kommt zurück, und der Bereich filtert sofort.'
        => '<strong>Fertig</strong> closes the outline — the sidebar comes back, and the area filters at once.',
    'Die Gruppe Ort mit einem gezeichneten Bereich'
        => 'The Ort group with a drawn area',
    '<strong>Der Bereich</strong> steht als „Bereich 1" mit seiner Fläche in der Gruppe Ort.'
        => '<strong>The area</strong> stands as "Bereich 1" with its size in the Ort group.',
    '<strong>Das Kreuz</strong> nimmt ihn wieder aus dem Filter; die Zeichnung ist dann weg.'
        => '<strong>The cross</strong> takes it out of the filter again; the drawing is gone then.',
    '<strong>Weiterer Bereich …</strong> zeichnet einen zweiten. Mehrere Bereiche heißen: in einem davon genügt.'
        => '<strong>Weiterer Bereich …</strong> draws a second one. Several areas mean: inside one of them suffices.',
    'Die Bereiche merkt sich dein Gerät, auch über einen Neustart. In Organisieren (<a href="#organisieren">%s</a>) gelten dieselben Bereiche; gezeichnet werden sie nur hier auf der Karte.'
        => 'Your device remembers the areas, across a restart too. In Organisieren (<a href="#organisieren">%s</a>) the same areas apply; drawn they are only here on the map.',
    'Tour von heute — der Punkt kommt zu ihr dazu.'
        => 'A tour of today — the point joins it.',
    // Help 1.7, the point window — the way in on the phone, the three
    // tabs, a foreign find (18.09.2026)
    '<strong>Der Weg hinein am Handy.</strong> Am Rechner genügt ein Klick — auf den Marker in der Karte oder auf eine Zeile im Reiter Punkte (<a href="#punkte">%s</a>) —, und das Fenster steht rechts neben der Karte. Am Handy öffnet der Marker das Fenster ebenso direkt; aus der Liste heraus ist der Weg aber einen Schritt länger, denn dort füllt das Fenster den ganzen Schirm und würde genau die Karte verdecken, zu der du gerade wolltest:'
        => '<strong>The way in on the phone.</strong> At the desk one click is enough — on the marker in the map or on a row in the Punkte tab (<a href="#punkte">%s</a>) — and the window stands to the right of the map. On the phone the marker opens the window just as directly; from the list, though, the way is one step longer, because there the window fills the whole screen and would cover exactly the map you were heading for:',
    'Der Reiter Punkte am Handy mit dem gewählten Fund'
        => 'The Punkte tab on the phone with the chosen find',
    'Die Karte mit dem gezeigten Fund und der Zeile darunter'
        => 'The map with the shown find and the line below it',
    'Das Punktfenster am Handy'
        => 'The point window on the phone',
    '<strong>Ein Tipp auf die Zeile</strong> im Reiter Punkte öffnet noch kein Fenster: die Seitenleiste geht zu, und die Karte fliegt zum Fund.'
        => '<strong>A tap on the row</strong> in the Punkte tab opens no window yet: the sidebar closes, and the map flies to the find.',
    '<strong>Der Fund</strong> steht mit einem Ring in der Kartenmitte — du siehst erst einmal, wo er liegt.'
        => '<strong>The find</strong> stands with a ring in the middle of the map — first of all you see where it lies.',
    '<strong>Die Zeile unten</strong> nennt ihn, und <strong>der Stift</strong> daran öffnet das Fenster. Bei einem fremden Fund, den du nur ansehen darfst, steht statt des Stifts ein Auge. Ein Tipp irgendwo in die Karte lässt die Zeile verschwinden — nichts geht auf.'
        => '<strong>The line at the bottom</strong> names it, and <strong>the pencil</strong> on it opens the window. On a foreign find you may only look at, an eye stands in place of the pencil. A tap anywhere into the map makes the line disappear — nothing opens.',
    '<strong>Das Fenster</strong> füllt den Schirm; „Schließen" bringt die Karte zurück.'
        => '<strong>The window</strong> fills the screen; "Schließen" brings the map back.',
    '<strong>Der Kopf:</strong> der Name des Fundes, darunter die <strong>Faktenzeile</strong> — alles, was der Server zum Ort nachträgt und niemand tippt: Fundnummer, Genauigkeit, Höhe, Gemeinde, Messtischblatt bis zum Vierundsechzigstel, Schutzgebiet, Rote Liste und die DGfM-Marke der Art. Sie steht über allen drei Reitern; ein Fingerzeig auf ein Stück davon sagt, was es ist.'
        => '<strong>The head:</strong> the name of the find, below it the <strong>fact line</strong> — everything the server adds for the place and nobody types: find number, accuracy, elevation, municipality, map sheet down to the sixty-fourth, protected area, Red List and the species\' DGfM mark. It stands above all three tabs; pointing at a piece of it says what it is.',
    '<strong>Der Papierkorb</strong> legt den Fund in den Papierkorb (<a href="#papierkorb">%s</a>) — von dort kommt er zurück. Hat der Fund Mitbesitzer, steht hier eine Tür statt des Korbs: du gibst ihn ab, und er bleibt bei den anderen.'
        => '<strong>The bucket</strong> puts the find into the trash (<a href="#papierkorb">%s</a>) — from there it comes back. If the find has co-owners, a door stands here instead of the bucket: you hand it over, and it stays with the others.',
    '<strong>Der Titel.</strong> In einer Artenliste heißt das Feld „Art" und schlägt beim Tippen wissenschaftliche Namen aus der Referenz vor, mit dem Status der Roten Liste dahinter. Aus welchen Artengruppen — Pilze, Flechten, Pflanzen, Tiere — die Vorschläge kommen, wählst du in der Verwaltung unter Einstellungen → Arten (<a href="#arten">%s</a>). Dort schaltest du auch „Schon gefundene Arten zuerst vorschlagen" ein — dann stehen die Arten aus deinen und den mit dir geteilten Funden oben in der Liste.'
        => '<strong>The title.</strong> In a species list the field is called "Art" and suggests scientific names from the reference while you type, with the Red List status behind them. Which species groups — fungi, lichens, plants, animals — the suggestions come from you choose in the administration under Einstellungen → Arten (<a href="#arten">%s</a>). There you also switch on "Schon gefundene Arten zuerst vorschlagen" — then the species from your finds and from those shared with you stand at the top of the list.',
    'Hier hakst du die <strong>Artengruppen</strong> an, deren Namen das Titelfeld im Punktfenster vorschlägt (<a href="#fenster-text">%s</a>, Punkt 1) — Pilze, Flechten, Pflanzen und die Tiergruppen. Dasselbe gilt für die Artfelder einer Liste, Substrat oder Begleitvegetation etwa (dort Punkt 2). Beim Tippen kommen nur Arten aus den gewählten Gruppen, online wie offline; was abgewählt ist, wird nicht mehr auf dieses Gerät geladen und beim nächsten Start wieder entfernt.'
        => 'Here you tick the <strong>species groups</strong> whose names the title field in the point window suggests (<a href="#fenster-text">%s</a>, item 1) — fungi, lichens, plants and the animal groups. The same holds for a list\'s species fields, substrate or companion vegetation say (item 2 there). While you type, only species from the chosen groups come up, online and offline alike; what is unticked is no longer loaded onto this device and is removed at the next start.',
    '<strong>Die Felder der Liste:</strong> was die Liste, in der der Fund liegt, an Feldern mitbringt — hier die einer Artenliste: Finder, Bestimmer, Bestimmungssicherheit, Anzahl, Substrat, Begleitvegetation, Geruch, Geschmack, Sporenpulverfarbe, Beleg-Nr., Fundbeschreibung. Liegt der Fund in mehreren Listen, stehen die Felder aller hier, jedes einmal. Welche Felder eine Liste hat, legst du in ihrem Fenster fest (<a href="#listen">%s</a>). Felder, die dort als Artfeld markiert sind — hier Substrat / Wirt und Begleitvegetation —, schlagen beim Tippen ebenso Namen vor, aus denselben Artengruppen wie der Titel (<a href="#arten">%s</a>); in einem mehrzeiligen Feld zählt jede Zeile als eigener Name.'
        => '<strong>The list\'s fields:</strong> the fields the list the find lies in brings along — here those of a species list: finder, identifier, certainty, count, substrate, companion vegetation, odour, taste, spore print colour, voucher number, find description. If the find lies in several lists, the fields of all of them stand here, each once. Which fields a list has you set in its window (<a href="#listen">%s</a>). Fields marked there as species fields — here Substrat / Wirt and Begleitvegetation — suggest names while you type just the same, from the same species groups as the title (<a href="#arten">%s</a>); in a multi-line field every line counts as a name of its own.',
    '<strong>Die Notiz</strong> — der Sammelplatz für alles, was in kein Feld passt. Sie wächst mit dem Text.'
        => '<strong>The note</strong> — the collecting place for everything that fits no field. It grows with the text.',
    'Der Reiter Belege: die Galerie in Motivgruppen, darunter die Sequenzen'
        => 'The Belege tab: the gallery in motif groups, the sequences below',
    '<strong>Die Zahl am Reiter:</strong> wie viele Belege am Fund hängen, Bilder und Sequenzen — die Bilder mitgezählt, die noch auf Übertragung warten.'
        => '<strong>The number on the tab:</strong> how many vouchers hang on the find, pictures and sequences — counting the pictures still waiting for transfer.',
    '<strong>Aufnehmen</strong> öffnet die Kamera des Handys, mit allem, was sie kann. Vorher fragt die App nach dem Motivnamen. Das Bild wird auf 2048 Pixel verkleinert, auf diesem Server abgelegt und gegen deinen Speicherplatz gerechnet; ohne Netz wartet es auf dem Gerät und geht später von selbst.'
        => '<strong>Aufnehmen</strong> opens the phone\'s camera, with everything it can do. Before that the app asks for the motif name. The picture is shrunk to 2048 pixels, stored on this server and counted against your storage; without a network it waits on the device and goes later by itself.',
    '<strong>Auswählen</strong> nimmt eine fertige Datei — ein Stack, ein Mikrofoto, eine Rohdatei von der richtigen Kamera — und legt sie in voller Größe in deine eigene Cloud, nicht auf diesen Server. Ohne verbundene Cloud (<a href="#cloud">%s</a>) ist der Knopf aus und sagt, warum.'
        => '<strong>Auswählen</strong> takes a finished file — a stack, a micrograph, a raw file from the proper camera — and puts it at full size into your own cloud, not onto this server. Without a connected cloud (<a href="#cloud">%s</a>) the button is off and says why.',
    '<strong>Die Galerie</strong>, nach Motivnamen gruppiert — Hauptmotiv, Schnittbild, Sporen … in der Reihenfolge deiner Vorschlagsliste (<a href="#messen">%s</a>). Ein Tipp auf ein Bild öffnet es groß: dort bekommt es seinen Motivnamen, einen Maßstab und Messungen, und dort wird es gelöscht. Bilder, die andere an den Fund gehängt haben, tragen den Namen dessen, der sie gemacht hat.'
        => '<strong>The gallery</strong>, grouped by motif name — Hauptmotiv, Schnittbild, Sporen … in the order of your suggestion list (<a href="#messen">%s</a>). A tap on a picture opens it large: there it gets its motif name, a scale bar and measurements, and there it is deleted. Pictures others attached to the find carry the name of whoever took them.',
    'Der Reiter Karte: Liste und Umriss'
        => 'The Karte tab: list and outline',
    '<strong>Listen:</strong> deine Listen mit einem Haken bei der Liste, in der der Fund liegt. Die Listen einer Mappe stehen eingerückt unter ihr. Ein anderer Haken legt den Fund um, und mit ihm wechseln die Felder im Reiter Text.'
        => 'Lists: your lists, with a tick at the list the find lies in. The lists of a folder stand indented below it. Another tick moves the find, and with it the fields in the Text tab change.',
    '<strong>Fläche:</strong> ein Fund kann eine Ausdehnung haben — eine Brandfläche, ein Bestand. „Umriss ziehen" macht die Karte zum Zeichenblatt, wie beim Bereich zeichnen im Filter (<a href="#filter">%s</a>); die Koordinate darüber zeigt dann auf einen Punkt in der Fläche.'
        => '<strong>Area:</strong> a find can have an extent — a burnt patch, a stand. "Umriss ziehen" turns the map into a drawing sheet, as when drawing an area in the filter (<a href="#filter">%s</a>); the coordinate above then points at a spot inside the area.',
    '<strong>Fundzeit</strong> mit Uhrzeit: beim Setzen trägt die Uhr des Geräts sie ein; das Feld ist für den Fund von letzter Woche, den du nachträgst. Die Uhrzeit entscheidet mit, zu welcher Tour der Fund gehört (<a href="#setzen">%s</a>).'
        => '<strong>Time of the find</strong> with the hour: on placing, the device\'s clock fills it in; the field is for last week\'s find that you enter afterwards. The hour has a say in which tour the find belongs to (<a href="#setzen">%s</a>).',
    '<strong>Angelegt</strong> — wann der Punkt entstanden ist. Anders als die Fundzeit ändert sich das nie.'
        => '<strong>Created</strong> — when the point came into being. Unlike the time of the find, that never changes.',
    'Ein fremder Fund'
        => 'A foreign find',
    'Ein Fund aus einer Freigabe (<a href="#geteilt">%s</a>) öffnet dasselbe Fenster — nur steht in ihm ausgegraut, was dir sein Besitzer nicht eingeräumt hat. Was er erlaubt, legt er fest, wenn er seine Liste in die Freigabe hängt; der Stift an der fremden Liste in der Seitenleiste sagt es dir in einem Satz.'
        => 'A find from a share (<a href="#geteilt">%s</a>) opens the same window — only what its owner has not granted you stands greyed out in it. What he allows he sets when he hangs his list into the share; the pencil on the foreign list in the sidebar tells you in one sentence.',
    'Das Punktfenster eines fremden Fundes ohne Schreibrechte'
        => 'The point window of a foreign find without write rights',
    '<strong>Die Herkunft:</strong> wem der Fund gehört und über welche Freigabe und Liste er zu dir kommt. Diese Zeile gibt es nur bei fremden Funden, und unter ihr ist alles Weitere zu lesen: was grau ist, ist nicht kaputt — es gehört jemand anderem.'
        => '<strong>The origin:</strong> whom the find belongs to and through which share and list it reaches you. This line exists only on foreign finds, and everything below is to be read under it: what is grey is not broken — it belongs to somebody else.',
    '<strong>Was du nicht ändern darfst, steht als bloßer Text</strong> — ohne Rahmen, ohne Lösch-Kreuz, ohne Klappmenü; ein Feld ohne Inhalt fehlt ganz. Hier ist alles Text, weil der Besitzer nichts freigegeben hat. Mit Rechten stehen genau die Felder als Eingabe da, die er genannt hat: Titel, Notiz, einzelne Felder, oder die Koordinaten fürs Verschieben. Die Fundzeit ist immer nur Text, dafür gibt es kein Recht. Der Listenbaum fehlt: in welcher seiner Listen der Fund liegt, ist die Ordnung des Besitzers.'
        => '<strong>What you may not change stands as plain text</strong> — no frame, no clearing cross, no drop-down; a field without content is missing altogether. Here everything is text, because the owner released nothing. With rights exactly the fields he named stand as input: title, note, single fields, or the coordinates for moving. The time of the find is always text only, there is no right for it. The list tree is missing: in which of his lists the find lies is the owner\'s order.',
    '<strong>Die Fußzeile ohne Speichern:</strong> wo nichts zu ändern ist, gibt es nichts zu speichern. Auch der Papierkorb im Kopf fehlt, solange der Besitzer das Löschen nicht erlaubt hat — und der Reiter Fotos, solange am Fund keine Bilder hängen: ansehen darfst du sie immer, dazutun nur an einem Fund, den du mitbesitzt.'
        => '<strong>The footer without Speichern:</strong> where nothing can be changed, there is nothing to save. The bucket in the head is missing too, as long as the owner has not allowed deleting — and the Fotos tab, as long as no pictures hang on the find: you may always look at them, add one only to a find you co-own.',
    // The point window asks before dropping unsaved input (18.09.2026)
    'Noch nicht gespeichert'
        => 'Not saved yet',
    'Du hast „%s" verschoben — soll die neue Position gespeichert werden? Verwerfen setzt den Marker zurück.'
        => 'You moved "%s" — save the new position? Discarding puts the marker back.',
    'An „%s" ist noch nicht gespeichert: %s. Speichern — oder verwerfen?'
        => 'On "%s" this is not saved yet: %s. Save — or discard?',
    'die neue Position'
        => 'the new position',
    'der geänderte Umriss'
        => 'the changed outline',
    'die Eingaben in den Feldern'
        => 'the input in the fields',
    // Help 1.7: Zeigen on the phone, the way to move a marker — and saving
    '<strong>Die Fußzeile:</strong> <strong>Schließen</strong> legt das Fenster zu; <strong>Speichern</strong> schreibt, was du in Text und Einordnung geändert hast, auf einmal. Fotos brauchen es nicht — sie hängen schon beim Aufnehmen am Fund. Das Fenster speichert nichts von selbst: schließt du es mit Änderungen, fragt es nach, was gespeichert werden soll — mit „Speichern" oder „Verwerfen" als Antwort.'
        => '<strong>The footer:</strong> <strong>Schließen</strong> closes the window; <strong>Speichern</strong> writes what you changed in Text and Einordnung, all at once. Photos do not need it — they hang on the find from the moment they are taken. The window saves nothing by itself: if you close it with changes, it asks what should be saved — with "Speichern" or "Verwerfen" as the answer.',
    '<strong>Zeigen</strong> rückt den Fund in die Kartenmitte. <strong>Am Handy ist Zeigen der Weg, einen Marker zu verschieben:</strong> das Fenster tritt beiseite, und der Marker lässt sich mit dem Finger ziehen.'
        => '<strong>Zeigen</strong> moves the find into the middle of the map. <strong>On the phone Zeigen is the way to move a marker:</strong> the window steps aside, and the marker can be dragged with a finger.',
    'Das Handy nach „Zeigen": die Leiste „Marker verschieben" über der Karte'
        => 'The phone after "Zeigen": the "Marker verschieben" bar above the map',
    '<strong>Die Leiste</strong> nennt die Koordinate und läuft beim Ziehen mit.'
        => '<strong>The bar</strong> names the coordinate and follows while you drag.',
    '<strong>Der Marker</strong> mit dem Ring: ihn ziehst du an die richtige Stelle. Am Rechner geht das ebenso, sobald der Fund gewählt ist — dort steht das Fenster daneben und braucht nicht beiseitezutreten.'
        => '<strong>The marker</strong> with the ring: drag it to the right spot. At the desk this works the same once the find is chosen — there the window stands beside the map and need not step aside.',
    '<strong>Fertig</strong> holt das Fenster zurück. Die neue Stelle steht jetzt im Koordinatenfeld, aber noch nicht in der Datenbank — erst <strong>Speichern</strong> macht sie fest. Schließt du stattdessen, fragt das Fenster: die neue Position speichern oder verwerfen?'
        => '<strong>Fertig</strong> brings the window back. The new spot now stands in the coordinate field, but not yet in the database — only <strong>Speichern</strong> makes it stick. If you close instead, the window asks: save the new position or discard it?',
    '<strong>Koordinaten</strong> in Grad, sechs Nachkommastellen — zum Lesen und Kopieren, nicht zum Tippen: verschoben wird ein Fund in der Karte, mit der Maus oder nach „Zeigen" mit dem Finger (<a href="#fenster">%s</a>, Punkt 5). Der Stift öffnet das Feld für den einen Fall, dass eine Position von woanders kommt — ein Pin aus Google Maps, eine Angabe eines Freundes —, und das Kopierzeichen legt sie in die Zwischenablage.'
        => '<strong>Coordinates</strong> in degrees, six decimals — for reading and copying, not for typing: a find is moved on the map, with the mouse or after "Zeigen" with the finger (<a href="#fenster">%s</a>, item 5). The pencil opens the field for the one case of a position from elsewhere — a pin from Google Maps, a friend\'s note —, and the copy sign puts it on the clipboard.',
    'Das Punktfenster eines eigenen Fundes: Kopf, Reiter, Fußzeile'
        => 'The point window of an own find: head, tabs, footer',
    '<strong>Die drei Reiter:</strong> Text für alles, was der Fund sagt — Titel, Felder, Notiz, Fundzeit, Koordinaten (<a href="#fenster-text">%s</a>); Belege für Fotos und Sequenzen (<a href="#fenster-photos">%s</a>); Karte für Liste und Umriss (<a href="#fenster-place">%s</a>). Die Zahl auf „Belege" sagt, wie viele Bilder und Sequenzen hängen — hinter einem Reiter sieht man das sonst nicht.'
        => '<strong>The three tabs:</strong> Text for everything the find says — title, fields, note, find time, coordinates (<a href="#fenster-text">%s</a>); Belege for pictures and sequences (<a href="#fenster-photos">%s</a>); Karte for list and outline (<a href="#fenster-place">%s</a>). The number on "Belege" says how many pictures and sequences hang there — behind a tab you would not see that otherwise.',
    'Der Reiter Text: Titel, die Felder der Liste, Notiz, Fundzeit, Koordinaten'
        => 'The Text tab: title, the list\'s fields, note, find time, coordinates',
    'Erstes Jahr'
        => 'First year',
    'Letztes Jahr'
        => 'Last year',
    'Alle Jahre'
        => 'All years',
    'Jahre'
        => 'Years',
    // „Schon gefundene Arten zuerst vorschlagen" — familiar species first (19.09.2026; renamed 25.09.2026)
    'Schon gefundene Arten zuerst vorschlagen — die von der Tour ganz oben, dann die häufigsten'
        => 'Suggest species found before first — those of the tour at the very top, then the most frequent',
    'So kommen die Vorschläge: Du tippst im Titelfeld die ersten Buchstaben eines Namens. Ab dem dritten Buchstaben erscheint eine Liste mit passenden Arten. Solange du nichts tippst, erscheint keine Liste.'
        => 'This is how the suggestions come: you type the first letters of a name into the title field. From the third letter on, a list of fitting species appears. As long as you type nothing, no list appears.',
    'Ein Beispiel: Du tippst „bol". Dann siehst du Arten wie Boletus edulis und Boletus reticulatus. Tippst du „bol edu" — mit einem Leerzeichen dazwischen —, bleibt fast nur noch Boletus edulis übrig.'
        => 'An example: you type "bol". Then you see species like Boletus edulis and Boletus reticulatus. If you type "bol edu" — with a space between —, almost only Boletus edulis is left.',
    'Mit diesem Haken stehen oben die Arten, die du schon gefunden hast oder die andere gefunden und mit dir geteilt haben. Zuerst kommen die Arten von heute und von deiner laufenden Tour. Dann Arten, die zur gleichen Jahreszeit gefunden wurden, egal in welchem Jahr. Dann Arten aus der Umgebung, bis 20 Kilometer um den Fundort. Was oft gefunden wurde, steht vor Seltenem. Darunter folgen alle anderen passenden Arten.'
        => 'With this tick, the species you have found before, or that others found and shared with you, stand at the top. First come the species of today and of your running tour. Then species found at the same time of year, in any year. Then species from the surroundings, up to 20 kilometres around the find. What was found often stands before what is rare. Below them follow all other fitting species.',
    'Auf der laufenden Tour schon gefunden'
        => 'Already found on the running tour',
    'Um diese Jahreszeit gefunden — 30 Tage davor und danach, in irgendeinem Jahr'
        => 'Found around this time of year — 30 days before and after, in any year',
    'Im Umkreis von 20 km gefunden'
        => 'Found within 20 km',
    '±30 Tage'
        => '±30 days',
    '1 Fund'
        => '1 find',
    '%d Funde'
        => '%d finds',
    'Bisher 1 Fund dieser Art'
        => '1 find of this species so far',
    'Bisher %d Funde dieser Art'
        => '%d finds of this species so far',
    '<strong>Schon gefundene Arten zuerst vorschlagen</strong> — auf diesem Gerät ein- und ausschaltbar, von Haus aus an: Arten aus deinen und den mit dir geteilten Funden stehen oben in der Vorschlagsliste, mit dem Grund dabei — zuerst die von der laufenden Tour, danach die häufig gefundenen vor den seltenen. Ein Fund aus dieser Jahreszeit (30 Tage vor und nach dem Funddatum, über alle Jahre) zählt dabei doppelt, ebenso einer aus 20 km Umkreis um den Fund. Darunter folgt die Referenz wie bisher.'
        => '<strong>Schon gefundene Arten zuerst vorschlagen</strong> — switched on and off on this device, on by default: species from your finds and from those shared with you stand at the top of the suggestion list, with the reason beside them — first those of the running tour, then the frequently found before the rare. A find from this season (30 days before and after the find day, in any year) counts twice, and so does one from within 20 km of the find. Below them the reference follows as before.',
    'Aktueller Name nach: %s'
        => 'Current name according to: %s',
    // Joint lists — „gemeinsam bearbeiten", step 1 (21./22.09.2026)
    'Gemeinsame Listen brauchen die Migration 0110 — sie ist noch nicht eingespielt.'
        => 'Joint lists need migration 0110 — it is not applied yet.',
    'Gemeinsam bearbeiten schaltet nur, wem die geteilte Liste gehört.'
        => 'Only the owner of the shared list switches it to joint editing.',
    'Gemeinsam bearbeiten — jedes Mitglied hat alle Rechte und setzt selbst Punkte in die Liste; die Rechte unten gelten dann von selbst.'
        => 'Joint editing — every member has every right and sets points into the list; the rights below then apply by themselves.',
    'gemeinsam: alle bearbeiten alles, jeder setzt Punkte'
        => 'joint: everyone edits everything, everyone sets points',
    'Gemeinsame Listen'
        => 'Joint lists',
    'Gemeinsame Liste: alle bearbeiten alles, und jeder setzt Punkte hinein.'
        => 'Joint list: everyone edits everything, and everyone sets points into it.',
    '<strong>Gemeinsam bearbeiten:</strong> Wer eine Liste eingehängt hat, kann sie unter „Rechte der anderen" auf <em>gemeinsam</em> stellen — der Schalter steht dort ganz oben, mit einer Warnung, und fragt beim Einschalten noch einmal nach. Dann hat jedes Mitglied alle Rechte an ihren Punkten und setzt selbst Punkte hinein — die Liste steht beim Punkt setzen unter „Gemeinsame Listen" zur Wahl. Ein Punkt gehört weiter dem, der ihn gesetzt hat; der Filter der Freigabe gilt für eine gemeinsame Liste nicht, sie zeigt alles. <strong>Jede Änderung steht im Chat der Freigabe</strong> — wer was geändert, herausgenommen oder zurückgeholt hat —, und Löschen heißt hier: aus der Liste in den Papierkorb der Liste, wo jedes Mitglied den Punkt zurückholt (<a href="#papierkorb">%s</a>). Die Liste selbst gehört der Gruppe: Löscht ihr Besitzer sie oder verlässt er die Freigabe, geht sie an ein anderes Mitglied über — an das, das am längsten in der Freigabe ist, samt allen Punkten; erst das letzte Mitglied löscht sie wirklich. Wer die Freigabe verlässt, nimmt seine eigenen Punkte aus den gemeinsamen Listen mit, und wer eine Liste aushängt oder „gemeinsam" ausschaltet, gibt den anderen ihre Punkte zurück, in eine eigene Liste „… (ehemals gemeinsam)" — alles steht im Chat.'
        => '<strong>Joint editing:</strong> whoever hung a list in can set it to <em>joint</em> under "Rechte der anderen" — the switch stands at the very top there, with a warning, and asks once more when switched on. Then every member has every right on its points and sets points into it — the list is offered under "Gemeinsame Listen" when placing a point. A point still belongs to whoever set it; the share\'s filter does not apply to a joint list, it shows everything. <strong>Every change stands in the share\'s chat</strong> — who changed, took out or brought back what —, and deleting means here: out of the list into the list\'s trash, where any member brings the point back (<a href="#papierkorb">%s</a>). The list itself belongs to the group: if its owner deletes it or leaves the share, it passes to another member — with all its points; only the last member really deletes it. Whoever leaves the share takes their own points out of the joint lists with them, and whoever unhooks a list or switches "joint" off gives the others their points back, into a list of their own "… (ehemals gemeinsam)" — all of it stands in the chat.',
    'Gelöschte Punkte liegen hier, bis du sie zurückholst oder endgültig entfernst — einzeln oder alle auf einmal. Was aus einer Exkursion stammt, steht bei seiner Exkursion; was du als Mitbesitzer verlassen hast, unter „Ausgestiegen".'
        => 'Deleted points lie here until you bring them back or remove them for good — one by one or all at once. What comes from an excursion stands with its excursion; what you left as a co-owner under "Ausgestiegen".',
    '<strong>Gemeinsame Listen</strong> haben ihren eigenen Papierkorb, je Liste: Wer dort einen Punkt löscht, nimmt nur die Ablage des Punktes aus dieser Liste — der Punkt selbst bleibt beim Finder, und liegt er noch in einer anderen Liste, bleibt er dort. Der Abschnitt zeigt, wer den Punkt wann herausgenommen hat, und <strong>jedes Mitglied</strong> holt ihn zurück. „Endgültig" gibt es hier nicht, und „Papierkorb leeren" greift nicht hinein: Solange eine Ablage im Papierkorb einer gemeinsamen Liste liegt, kann auch der Finder den Punkt nicht endgültig entfernen. Liegt ein Punkt in keiner Liste mehr, ist er als Ganzes im Papierkorb — Zurückholen aus dem Papierkorb der Liste belebt ihn wieder.'
        => '<strong>Joint lists</strong> have a trash of their own, per list: whoever deletes a point there takes only the point\'s filing out of this list — the point itself stays with the finder, and if it still lies in another list, it stays there. The section shows who took the point out and when, and <strong>any member</strong> brings it back. There is no "Endgültig" here, and "Papierkorb leeren" does not reach in: as long as a filing lies in the trash of a joint list, even the finder cannot remove the point for good. If a point lies in no list any more, it is in the trash as a whole — bringing it back from the list\'s trash revives it.',
    // Joint lists, step 2 — the list's trash and the chat lines (22.09.2026)
    'Der Papierkorb der Liste braucht die Migration 0111 — sie ist noch nicht eingespielt.'
        => 'The list\'s trash needs migration 0111 — it is not applied yet.',
    'Du bist in keiner Freigabe dieser Liste.'
        => 'You are in no share of this list.',
    'hat „%s" in „%s" geändert (vorher „%s"): %s'
        => 'changed "%s" in "%s" (before "%s"): %s',
    'hat „%s" in „%s" geändert: %s'
        => 'changed "%s" in "%s": %s',
    'hat „%s" aus der gemeinsamen Liste „%s" genommen'
        => 'took "%s" out of the joint list "%s"',
    'hat „%s" in die gemeinsame Liste „%s" gelegt'
        => 'put "%s" into the joint list "%s"',
    'hat „%s" in den Papierkorb der Liste „%s" gelegt'
        => 'put "%s" into the trash of the list "%s"',
    'hat „%s" in die Liste „%s" zurückgeholt'
        => 'brought "%s" back into the list "%s"',
    'Umriss'
        => 'Outline',
    'Der Punkt liegt noch im Papierkorb einer gemeinsamen Liste — dort holt ihn jedes Mitglied zurück.'
        => 'The point still lies in the trash of a joint list — any member brings it back there.',
    'Zurückgeholt — der Punkt liegt wieder in der Liste, bei allen beim nächsten Abgleich.'
        => 'Brought back — the point lies in the list again, for everyone at the next sync.',
    'Nichts zurückgeholt — die Ablage liegt nicht mehr im Papierkorb.'
        => 'Nothing brought back — the filing is no longer in the trash.',
    'Ein Punkt liegt noch im Papierkorb einer gemeinsamen Liste und bleibt — dort holt ihn jedes Mitglied zurück.'
        => 'One point still lies in the trash of a joint list and stays — any member brings it back there.',
    '%d Punkte liegen noch im Papierkorb einer gemeinsamen Liste und bleiben — dort holt sie jedes Mitglied zurück.'
        => '%d points still lie in the trash of a joint list and stay — any member brings them back there.',
    'Liegt auch im Papierkorb von „%s" — jedes Mitglied holt ihn dort zurück; endgültig geht hier nicht.'
        => 'Also lies in the trash of "%s" — any member brings it back there; for good is not possible here.',
    'Liegt im Papierkorb einer gemeinsamen Liste'
        => 'Lies in the trash of a joint list',
    'Gemeinsame Liste „%s“ — Freigabe „%s“ von %s'
        => 'Joint list "%s" — share "%s" by %s',
    'Herausgenommen'
        => 'Taken out',
    'Liegt in keiner Liste mehr — Zurückholen belebt den Punkt wieder.'
        => 'Lies in no list any more — bringing it back revives the point.',
    'Aus „%s" nehmen'
        => 'Take out of "%s"',
    'in den Papierkorb der Liste — jedes Mitglied holt ihn dort zurück'
        => 'into the list\'s trash — any member brings it back there',
    'Aus allen gemeinsamen Listen nehmen'
        => 'Take out of every joint list',
    'in jeden Papierkorb dieser Listen'
        => 'into each of these lists\' trashes',
    'Ganz in den Papierkorb'
        => 'Into the trash as a whole',
    'aus den gemeinsamen Listen heraus und in deinen eigenen Papierkorb'
        => 'out of the joint lists and into your own trash',
    'Aus der Liste nehmen?'
        => 'Take out of the list?',
    '„%s" geht aus „%s" in den Papierkorb der Liste — jedes Mitglied kann ihn dort zurückholen. Die Gruppe liest davon im Chat.'
        => '"%s" goes out of "%s" into the list\'s trash — any member can bring it back there. The group reads of it in the chat.',
    '„%s" löschen?'
        => 'Delete "%s"?',
    'Der Punkt liegt in einer gemeinsamen Liste. Was herausgenommen wird, landet im Papierkorb der Liste, und die Gruppe liest davon im Chat.'
        => 'The point lies in a joint list. What is taken out lands in the list\'s trash, and the group reads of it in the chat.',
    'In den Papierkorb gelegt — aus den gemeinsamen Listen heraus.'
        => 'Put into the trash — out of the joint lists.',
    'Aus allen gemeinsamen Listen genommen — die Gruppe holt ihn im Papierkorb der Liste zurück.'
        => 'Taken out of every joint list — the group brings it back in the list\'s trash.',
    'Aus „%s" genommen — die Gruppe holt ihn im Papierkorb der Liste zurück.'
        => 'Taken out of "%s" — the group brings it back in the list\'s trash.',
    'Ohne Netz — das Herausnehmen wird nachgereicht.'
        => 'No network — the taking out will be sent later.',
    // Joint lists, step 3 — the handover chain (22.09.2026)
    'hat die Liste „%s" an %s übergeben'
        => 'handed the list "%s" over to %s',
    'hat die Liste „%s" gelöscht — die Punkte der anderen liegen jetzt bei ihnen in einer eigenen Liste „%s"'
        => 'deleted the list "%s" — the others\' points now lie with them in a list of their own, "%s"',
    'ist ausgetreten — der eigene Punkt geht mit aus „%s"'
        => 'left — their own point goes with them out of "%s"',
    'ist ausgetreten — die eigenen %d Punkte gehen mit aus „%s"'
        => 'left — their own %d points go with them out of "%s"',
    'hat die gemeinsame Liste „%s" ausgehängt'
        => 'unhooked the joint list "%s"',
    'hat die gemeinsame Liste „%s" ausgehängt — die Punkte der anderen liegen jetzt bei ihnen in einer eigenen Liste „%s"'
        => 'unhooked the joint list "%s" — the others\' points now lie with them in a list of their own, "%s"',
    'hat „%s" nicht mehr gemeinsam gestellt'
        => 'set "%s" to no longer joint',
    'hat „%s" nicht mehr gemeinsam gestellt — die Punkte der anderen liegen jetzt bei ihnen in einer eigenen Liste „%s"'
        => 'set "%s" to no longer joint — the others\' points now lie with them in a list of their own, "%s"',
    'Die Liste ist an %s übergegangen — sie bleibt der Gruppe, du findest sie unter „Geteilt".'
        => 'The list has passed to %s — it stays with the group, you find it under "Geteilt".',
    'Eine gemeinsame Liste lässt sich nicht zusammenführen — sie gehört der Gruppe. Erst „gemeinsam bearbeiten" ausschalten, dann geht es.'
        => 'A joint list cannot be merged — it belongs to the group. Switch "gemeinsam bearbeiten" off first, then it works.',
    'Die Liste verschwindet aus der Freigabe. Sie ist gemeinsam: die Punkte der anderen liegen danach bei ihnen in einer eigenen Liste „… (ehemals gemeinsam)", und der Chat meldet es.'
        => 'The list disappears from the share. It is joint: the others\' points then lie with them in a list of their own, "… (ehemals gemeinsam)", and the chat reports it.',
    'Liste „%s" abgeben?'
        => 'Hand over list "%s"?',
    'Liste abgeben'
        => 'Hand over list',
    'Sie ist gemeinsam und gehört der Gruppe: sie geht mit allen Punkten an ein anderes Mitglied der Freigabe über, und du findest sie danach unter „Geteilt".'
        => 'It is joint and belongs to the group: it passes with all its points to another member of the share, and you find it under "Geteilt" afterwards.',
    'Sie gehört zu einer gemeinsamen Liste. Die Punkte der anderen liegen danach bei ihnen in einer eigenen Liste „… (ehemals gemeinsam)" — und der Chat der Freigabe meldet es.'
        => 'It belongs to a joint list. The others\' points then lie with them in a list of their own, "… (ehemals gemeinsam)" — and the share\'s chat reports it.',
    // Joint lists — the warning before the switch (22.09.2026)
    'Das ist ein großer Schritt: Die Liste gehört dann allen Mitgliedern — jeder bearbeitet, verschiebt und löscht jeden Punkt darin. Löschst du die Liste oder verlässt du die Freigabe, geht sie mit allen Punkten an ein anderes Mitglied über und ist nicht mehr deine. Zurück auf privat führt nur dieser Schalter, und was die anderen bis dahin geändert haben, bleibt.'
        => 'This is a big step: the list then belongs to every member — each of them edits, moves and deletes every point in it. If you delete the list or leave the share, it passes with all its points to another member and is no longer yours. The only way back to private is this switch, and whatever the others changed until then stays.',
    'Liste wirklich gemeinsam machen?'
        => 'Really make the list joint?',
    'Sie trägt 1 Punkt. Sobald du die Rechte speicherst, gehört sie allen Mitgliedern der Freigabe: jeder bearbeitet, verschiebt und löscht jeden Punkt darin und setzt eigene hinein. Löschst du die Liste oder verlässt du die Freigabe, geht sie an ein anderes Mitglied über. Was die anderen ändern, bleibt — auch wenn du den Schalter später wieder ausstellst.'
        => 'It carries 1 point. As soon as you save the rights, it belongs to every member of the share: each of them edits, moves and deletes every point in it and sets their own into it. If you delete the list or leave the share, it passes to another member. What the others change stays — even if you switch this off again later.',
    'Sie trägt %d Punkte. Sobald du die Rechte speicherst, gehört sie allen Mitgliedern der Freigabe: jeder bearbeitet, verschiebt und löscht jeden Punkt darin und setzt eigene hinein. Löschst du die Liste oder verlässt du die Freigabe, geht sie an ein anderes Mitglied über. Was die anderen ändern, bleibt — auch wenn du den Schalter später wieder ausstellst.'
        => 'It carries %d points. As soon as you save the rights, it belongs to every member of the share: each of them edits, moves and deletes every point in it and sets their own into it. If you delete the list or leave the share, it passes to another member. What the others change stays — even if you switch this off again later.',
    'Ja, gemeinsam'
        => 'Yes, joint',    '%s (ehemals gemeinsam)'
        => '%s (formerly joint)',
    '<strong>Wofür gemeinsame Listen gedacht sind:</strong> für kleine Arbeitslisten, die eine Gruppe zusammen pflegt — Gebiete mit Umrandung, Brandflächen, Stellen, die jeder bewerten und nachschärfen kann. Nicht für Funde. Wer mit anderen eine Wiese kartiert, hängt weiter seine eigene Fundliste ein und macht dafür eine Freigabe mit Gebietsfilter auf die Wiese: Jeder sieht vom anderen genau die Funde von dort, und jede Liste bleibt sauber die ihres Finders.'
        => '<strong>What joint lists are for:</strong> small working lists a group tends together — areas with an outline, burn sites, spots anyone can rate and refine. Not for finds. Whoever maps a meadow with others keeps hanging in their own find list and makes a share with an area filter on the meadow for it: everyone sees exactly the other\'s finds from there, and every list stays cleanly its finder\'s.',
    'gemeinsam'
        => 'joint',
    'Der Punkt gehört zu keiner Tour — die laufende Tour läuft ohne ihn weiter.'
        => 'The point belongs to no tour — the running tour runs on without it.',
    // Sequences at a find (migration 0112, 22.09.2026)
    'Sequenzen' => 'Sequences',
    'Sequenz' => 'Sequence',
    'Sequenz hinzufügen' => 'Add sequence',
    'Marker' => 'Marker',
    'FASTA oder die nackten Basen — mehrere FASTA-Einträge ergeben mehrere Sequenzen'
        => 'FASTA or the bare bases — several FASTA records give several sequences',
    'Datei wählen' => 'Choose file',
    'Zugangsnummer' => 'Accession number',
    'GenBank oder UNITE' => 'GenBank or UNITE',
    'Probennummer' => 'Sample number',
    'Labor' => 'Lab',
    'Primer' => 'Primers',
    'An „%s"' => 'On "%s"',
    '%d bp' => '%d bp',
    'Als FASTA kopieren' => 'Copy as FASTA',
    'Sequenz als FASTA kopiert.' => 'Sequence copied as FASTA.',
    'Kopieren nicht möglich — der Browser gibt die Zwischenablage nicht frei.'
        => 'Cannot copy — the browser does not release the clipboard.',
    'Als FASTA-Datei speichern' => 'Save as FASTA file',
    'Sequenz löschen' => 'Delete sequence',
    'Sequenz löschen?' => 'Delete sequence?',
    'Die Sequenz verschwindet vom Fund — für alle, die ihn sehen. Wiederherstellen geht nicht, nur neu hochladen.'
        => 'The sequence disappears from the find — for everyone who sees it. There is no restoring, only uploading again.',
    'Sequenz gelöscht.' => 'Sequence deleted.',
    'mir' => 'me',
    'Noch keine Sequenz am Fund.' => 'No sequence on the find yet.',
    'Ohne Netz — eine Sequenz braucht die Verbindung zum Server.'
        => 'No network — a sequence needs the connection to the server.',
    'Sequenz gespeichert — %d bp.' => 'Sequence saved — %d bp.',
    '%d Sequenzen gespeichert.' => '%d sequences saved.',
    'Es ist keine Sequenz angekommen.' => 'No sequence arrived.',
    'Das ist keine Sequenz: „%s" ist kein Basen-Zeichen.' => 'That is no sequence: "%s" is not a base letter.',
    'Sequenzen brauchen die Migration 0112 — sie ist noch nicht eingespielt.'
        => 'Sequences need migration 0112 — it has not been applied yet.',
    'Diese Sequenz ist nicht deine — oder es gibt sie nicht mehr.'
        => 'This sequence is not yours — or it no longer exists.',
    'hat eine Sequenz an „%s" in „%s" gehängt (%s)' => 'hung a sequence on "%s" in "%s" (%s)',
    'hat %d Sequenzen an „%s" in „%s" gehängt (%s)' => 'hung %d sequences on "%s" in "%s" (%s)',
    '<strong>Sequenzen:</strong> der zweite Beleg — eine Gensequenz, als Text. „Sequenz hinzufügen" nimmt FASTA oder die nackten Basen, aus einer Datei oder eingefügt, dazu Marker (ITS, LSU …), Zugangsnummer, Probennummer, Labor und Primer; ein FASTA mit mehreren Einträgen ergibt mehrere Sequenzen. Was keine Basen sind, wird abgewiesen. Eine Sequenz gehört dem, der sie hochlädt, wie ein Bild dem Fotografen: an einem fremden Fund macht sie ihn zum Mitbesitzer, und nur er löscht sie wieder. Jede Zeile lässt sich als FASTA kopieren oder als Datei speichern, und der Fundbericht druckt sie mit.'
        => '<strong>Sequences:</strong> the second voucher — a DNA sequence, as text. "Sequenz hinzufügen" takes FASTA or the bare bases, from a file or pasted, plus marker (ITS, LSU …), accession number, sample number, lab and primers; a FASTA with several records gives several sequences. Whatever is not bases is refused. A sequence belongs to whoever uploads it, as a picture belongs to the photographer: on somebody else\'s find it makes them a co-owner, and only they delete it again. Every row can be copied as FASTA or saved as a file, and the find report prints them.',
    'Text, Belege oder Karte'
        => 'Text, Belege or Karte',
    'Belege' => 'Vouchers',
    'Karte' => 'Map',
    'Koordinate eingeben — für eine Position von woanders' => 'Enter coordinate — for a position from elsewhere',
    'Koordinate eingeben' => 'Enter coordinate',
    'Gehört dir' => 'Yours',
    'Gehört %s' => 'Belongs to %s',
    'Durchgestrichen' => 'Struck through',
    'Haken' => 'Tick',
    'Feuer' => 'Fire',
    'Parkplatz' => 'Car park',
    'Es kommen folgende Felder dazu: %s.' => 'These fields come along: %s.',
    'Diese Felder trägt nur der Punkt, seine Liste hat sie nicht. Leer gemacht, verschwinden sie.'
        => 'Only the point carries these fields, its list does not. Emptied, they disappear.',
    'hat bei „%s" %s „%s" eingetragen, %s'
        => 'entered %2$s "%3$s" on "%1$s", %4$s',
    'hat bei „%s" %s „%s" gelöscht, %s'
        => 'deleted %2$s "%3$s" on "%1$s", %4$s',
    'hat bei „%s" %s von „%s" in „%s" geändert, %s'
        => 'changed %2$s on "%1$s" from "%3$s" to "%4$s", %5$s',
    'hat den Titel von „%s" in „%s" geändert, %s'
        => 'changed the title from "%s" to "%s", %s',
    'hat „%s" verschoben, %s'
        => 'moved "%s", %s',
    'hat den Umriss von „%s" geändert, %s'
        => 'changed the outline of "%s", %s',
    'in der Liste „%s"'
        => 'in the list "%s"',
    'in den Listen „%s"'
        => 'in the lists "%s"',
    'schon befreundet' => 'already friends',
    'Anfrage läuft schon' => 'request already pending',
    'Ein Geräte-Zugang reicht für die Cloud nicht — bitte im Browser anmelden.'
        => 'A device token is not enough for the cloud — please sign in in the browser.',
    // E-mail confirmation (migration 0116, 23.09.2026)
    'Konto nicht gefunden.'
        => 'Account not found.',
    'Die Mail ist gerade erst verschickt worden. Bitte warte zwei Minuten, dann kannst du es noch einmal versuchen.'
        => 'The mail has only just been sent. Please wait two minutes, then you can try again.',
    'Hallo %s,'
        => 'Hello %s,',
    'Hallo,'
        => 'Hello,',
    'YASAIMAPP: E-Mail-Adresse bestätigen'
        => 'YASAIMAPP: confirm your e-mail address',
    'bitte klicke auf diesen Link. Damit bestätigst du deine E-Mail-Adresse für YASAIMAPP:'
        => 'please click this link. It confirms your e-mail address for YASAIMAPP:',
    'Der Link funktioniert %d Stunden lang und nur ein einziges Mal. Hast du diese Mail nicht angefordert? Dann lösche sie einfach. Es passiert nichts.'
        => 'The link works for %d hours and only once. Did you not ask for this mail? Then just delete it. Nothing happens.',
    'YASAIMAPP: Änderung deiner E-Mail-Adresse'
        => 'YASAIMAPP: change of your e-mail address',
    'für dein YASAIMAPP-Konto wurde eine neue E-Mail-Adresse eingetragen: %s. Sie gilt erst, wenn der Link in der Mail an diese Adresse angeklickt wird. Bis dahin meldest du dich weiter mit dieser Adresse hier an.'
        => 'a new e-mail address was entered for your YASAIMAPP account: %s. It only counts once the link in the mail to that address is clicked. Until then you keep signing in with this address here.',
    'Warst du das nicht? Dann ändere bitte sofort dein Passwort.'
        => 'Was that not you? Then please change your password at once.',
    'Für diese E-Mail-Adresse gibt es inzwischen ein anderes Konto.'
        => 'Another account now uses this e-mail address.',
    'Gespeichert. Wir haben eine Mail an %s geschickt. Klicke auf den Link darin. Bis dahin meldest du dich weiter mit deiner alten Adresse an.'
        => 'Saved. We sent a mail to %s. Click the link in it. Until then you keep signing in with your old address.',
    'Wir haben eine Mail an %s geschickt. Klicke auf den Link darin.'
        => 'We sent a mail to %s. Click the link in it.',
    'Vor- und Nachname stehen bei deinen Funden als Finder.'
        => 'First and last name appear on your finds as the finder.',
    'Änderst du die Adresse, bekommt die neue Adresse eine Mail mit einem Link. Erst wenn du auf den Link klickst, gilt die neue Adresse.'
        => 'If you change the address, the new address gets a mail with a link. The new address counts only once you click that link.',
    'Die neue Adresse %s ist noch nicht bestätigt. Dorthin ist eine Mail mit einem Link unterwegs. Bitte klicke auf den Link.'
        => 'The new address %s is not confirmed yet. A mail with a link is on its way there. Please click the link.',
    'Deine E-Mail-Adresse ist noch nicht bestätigt — der Link ging bei der Anmeldung an sie.'
        => 'Your e-mail address is not confirmed yet — the link went to it when you signed up.',
    'Link noch einmal schicken'
        => 'Send the link again',
    'E-Mail-Adresse bestätigen'
        => 'Confirm e-mail address',
    'Danke — deine E-Mail-Adresse ist bestätigt.'
        => 'Thank you — your e-mail address is confirmed.',
    'Dieser Link ist abgelaufen oder wurde schon benutzt. In den Einstellungen unter „Name und E-Mail-Adresse" lässt sich ein neuer anfordern.'
        => 'This link has expired or was already used. A new one can be requested in the settings under "Name und E-Mail-Adresse".',
    'Deine E-Mail-Adresse ist bestätigt (%s).' => 'Your e-mail address is confirmed (%s).',
    'Link zum Bestätigen schicken'
        => 'Send the link to confirm',
    'Deine Adresse ist noch nicht bestätigt. So geht es: Klicke auf „Link zum Bestätigen schicken". Dann klicke auf den Link in der Mail.'
        => 'Your address is not confirmed yet. This is how: click "Send the link to confirm". Then click the link in the mail.',
    'Deine Adresse ist noch nicht bestätigt. Das ist freiwillig. Aber nur mit bestätigter Adresse kannst du ein vergessenes Passwort selbst neu setzen. So geht es: Klicke auf „Link zum Bestätigen schicken". Dann klicke auf den Link in der Mail.'
        => 'Your address is not confirmed yet. That is voluntary. But only with a confirmed address can you set a forgotten password yourself. This is how: click "Send the link to confirm". Then click the link in the mail.',
    'Deine Adresse ist noch nicht bestätigt. Das ist freiwillig. Aber nur mit bestätigter Adresse kannst du ein vergessenes Passwort selbst neu setzen und deine Funde an die DGfM-Kartierung melden. So geht es: Klicke auf „Link zum Bestätigen schicken". Dann klicke auf den Link in der Mail.'
        => 'Your address is not confirmed yet. That is voluntary. But only with a confirmed address can you set a forgotten password yourself and report your finds to the DGfM mapping. This is how: click "Send the link to confirm". Then click the link in the mail.',
    'Deine Adresse ist noch nicht bestätigt. Das ist freiwillig. Aber nur mit bestätigter Adresse kannst du deine Funde an die DGfM-Kartierung melden. So geht es: Klicke auf „Link zum Bestätigen schicken". Dann klicke auf den Link in der Mail.'
        => 'Your address is not confirmed yet. That is voluntary. But only with a confirmed address can you report your finds to the DGfM mapping. This is how: click "Send the link to confirm". Then click the link in the mail.',

    // Passwort vergessen (pages/password_reset.php, includes/password_reset.php, login.php)
    'Passwort vergessen?' => 'Forgot your password?',
    'Neues Passwort setzen' => 'Set a new password',
    'Passwort vergessen' => 'Forgot password',
    'Das Zurücksetzen ist hier noch nicht eingerichtet.' => 'Resetting is not set up here yet.',
    'Dieser Link ist abgelaufen oder wurde schon benutzt.' => 'This link has expired or was already used.',
    'Neuen Link anfordern' => 'Request a new link',
    'Für das Konto %s.' => 'For the account %s.',
    'Passwort setzen' => 'Set password',
    'Gehört zu %s ein Konto mit bestätigter Adresse, ist der Link jetzt unterwegs. Er gilt eine Stunde.'
        => 'If %s belongs to an account with a confirmed address, the link is on its way now. It is valid for one hour.',
    'Keine Mail? Dann schau im Spam-Ordner nach. Eine Adresse, die nie bestätigt wurde, bekommt keinen Link.'
        => 'No mail? Then look in the spam folder. An address that was never confirmed gets no link.',
    'Gib die E-Mail-Adresse deines Kontos ein. Ist sie bestätigt, bekommst du einen Link, mit dem du ein neues Passwort setzt.'
        => 'Enter the e-mail address of your account. If it is confirmed, you get a link to set a new password.',
    'Link anfordern' => 'Request link',
    'Zurück zur Anmeldung' => 'Back to sign-in',
    'Zu viele Anfragen von hier. Bitte in %d Minuten noch einmal versuchen.'
        => 'Too many requests from here. Please try again in %d minutes.',
    'YASAIMAPP: Neues Passwort setzen' => 'YASAIMAPP: set a new password',
    'für dein YASAIMAPP-Konto wurde ein neues Passwort angefordert. Mit diesem Link setzt du es:'
        => 'a new password was requested for your YASAIMAPP account. Set it with this link:',
    'Der Link gilt eine Stunde und nur einmal. Wenn du das nicht angestoßen hast, lass die Mail einfach liegen — dein Passwort bleibt, wie es ist.'
        => 'The link is valid for one hour and only once. If you did not ask for this, just ignore the mail — your password stays as it is.',
    // The app's own folder at Drive and S3 (includes/cloud.php, 0120)
    'Ordner von YASAIMAPP in deiner Cloud'
        => 'YASAIMAPP\'s folder in your cloud',
    'Der Ordner braucht einen Namen.' => 'The folder needs a name.',
    'Den Ordnernamen zu ändern braucht Migration 0120 — die ist noch nicht eingespielt.'
        => 'Changing the folder name needs migration 0120 — it has not been applied yet.',
    'Hier schreibt die Karte in den App-Ordner, den der Anbieter ihr gibt — dessen Namen bestimmt die App-Anmeldung, nicht die Karte.'
        => 'Here the map writes into the app folder the provider gives it — its name is set by the app registration, not by the map.',
    'Der Ordner heißt schon „%s".' => 'The folder is already called "%s".',
    'Die Verbindung zur Cloud trägt gerade nicht. Bitte später noch einmal versuchen.'
        => 'The connection to the cloud is not working right now. Please try again later.',
    'Gespeichert — der Ordner entsteht beim nächsten Durchgang als „%s".'
        => 'Saved — the folder will be created as "%s" on the next run.',
    'Gespeichert. Ab dem nächsten Durchgang schreibt die Karte nach „%s" und legt dort alles einmal neu ab. Der alte Ordner bleibt liegen, bis du ihn bei deinem Anbieter löschst.'
        => 'Saved. From the next run on the map writes to "%s" and stores everything there once more. The old folder stays until you delete it at your provider.',
    'Umbenannt — der Ordner heißt in deiner Cloud jetzt „%s".' => 'Renamed — the folder in your cloud is now called "%s".',
    'Hier legt YASAIMAPP alles ab. Ein neuer Name ergibt einen neuen Ordner. YASAIMAPP legt dann alles noch einmal dort ab. Der alte Ordner bleibt, wie er ist.'
        => 'This is where YASAIMAPP stores everything. A new name makes a new folder. YASAIMAPP then stores everything there once more. The old folder stays as it is.',
    'Hier legt YASAIMAPP alles ab, in „Meine Ablage". Du kannst den Ordner auch in Google Drive umbenennen oder verschieben. YASAIMAPP findet ihn trotzdem.'
        => 'This is where YASAIMAPP stores everything, in "My Drive". You can also rename or move the folder in Google Drive. YASAIMAPP still finds it.',

    // Offline-Daten, the protected areas grouped (administration.js)
    // The connected cloud tile (cloud.php CLOUD_PROVIDERS 'connected_hint')
    'Verbunden seit %s.' => 'Connected since %s.',
    'YASAIMAPP sieht dort nur einen eigenen Ordner, nicht deine anderen Dateien.'
        => 'There YASAIMAPP sees only a folder of its own, not your other files.',
    'YASAIMAPP sieht dort nur einen eigenen Ordner unter „Apps", nicht deine anderen Dateien.'
        => 'There YASAIMAPP sees only a folder of its own under "Apps", not your other files.',
    'Hierher gehen die Datensicherung und deine Fotos.'
        => 'The backup and your photos go here.',
    'Hierher geht vorerst nur die Datensicherung. Deine Fotos bleiben noch auf dem Server, bis wir diesen Anbieter ausprobiert haben.'
        => 'For now only the backup goes here. Your photos stay on the server until we have tried this provider.',
    // Joint lists: change lines folded into one (joint_line, 0121)
    'hat „%s" neu angelegt, %s' => 'created "%s", %s',
    'hat bei „%s" einen Umriss gezogen, %s' => 'drew an outline for "%s", %s',
    'hat bei „%s" den Umriss entfernt, %s' => 'removed the outline of "%s", %s',
    'hat „%s" bearbeitet (%s), %s' => 'edited "%s" (%s), %s',
    'Titel „%s" → „%s"' => 'title "%s" → "%s"',
    'verschoben' => 'moved',
    'Umriss gezogen' => 'outline drawn',
    'Umriss entfernt' => 'outline removed',
    'Umriss geändert' => 'outline changed',
    '%s „%s" eingetragen' => '%s "%s" entered',
    '%s „%s" gelöscht' => '%s "%s" deleted',
    '%s „%s" → „%s"' => '%s "%s" → "%s"',
    // The seedling shape (shapes.php 'plant', 24.09.2026)
    'Pflanze' => 'Plant',
    // Three more mushroom shapes (shapes.php, 24.09.2026); the bolete and
    // the fly agaric are labelled „Speisepilz" and „Giftpilz", whose
    // entries stand with the DGfM lists above
    'Einzelner Pilz' => 'Single mushroom',
    // Three more insect shapes (shapes.php, 24.09.2026)
    'Käfer' => 'Beetle',
    'Marienkäfer' => 'Ladybird',
    'Libelle' => 'Dragonfly',
    // Two tree shapes (shapes.php, 24.09.2026)
    'Nadelbaum' => 'Conifer',
    'Laubbaum' => 'Broadleaf tree',
    // A flower and two plants of the wet ground (shapes.php, 24.09.2026)
    'Blütenpflanze' => 'Flowering plant',
    'Binsen' => 'Rushes',
    'Rohrkolben' => 'Cattail',
    // Two grasslands (shapes.php, 24.09.2026)
    'Magerrasen' => 'Nutrient-poor grassland',
    'Kalktrockenrasen' => 'Dry calcareous grassland',
    // The bog (shapes.php, 24.09.2026)
    'Sphagnumsumpf' => 'Sphagnum bog',
    // The foliose lichen (shapes.php, 24.09.2026)
    'Blattflechte' => 'Foliose lichen',
    // The mammals (shapes.php, 24.09.2026)
    'Reh' => 'Roe deer',
    'Wildschwein' => 'Wild boar',
    'Kuh' => 'Cow',
    'Schaf' => 'Sheep',
    // The arachnids (shapes.php, 24.09.2026)
    'Spinne am Faden' => 'Hanging spider',
    'Weberknecht' => 'Harvestman',
    'Zecke' => 'Tick',
    'Skorpion' => 'Scorpion',
    // The molluscs (shapes.php, 24.09.2026)
    'Nacktschnecke' => 'Slug',
    'Schließmundschnecke' => 'Door snail',
    'Muschel' => 'Mussel',
    // The birds (shapes.php, 24.09.2026)
    'Singvogel' => 'Songbird',
    'Wasservogel' => 'Waterbird',
    'Greifvogel' => 'Bird of prey',
    'Eule' => 'Owl',
    'Specht' => 'Woodpecker',
    'Stelzvogel' => 'Wader',
    // Reptiles and amphibians (shapes.php, 24.09.2026)
    'Frosch' => 'Frog',
    'Eidechse' => 'Lizard',
    'Schlange' => 'Snake',
    // The fish (shapes.php, 24.09.2026)
    'Friedfisch' => 'Non-predatory fish',
    'Forelle' => 'Trout',
    'Raubfisch' => 'Predatory fish',
    // The crayfish (shapes.php, 24.09.2026)
    'Krebs' => 'Crayfish',
    // The campsite (shapes.php, 24.09.2026)
    'Campingplatz' => 'Campsite',
    'Pension' => 'Guesthouse',
    'Hotel' => 'Hotel',
    'Übernachtung' => 'Accommodation',
    'Rastplatz' => 'Rest area',
    // The sidebar's tick column (map.js tickBox, 25.09.2026)
    'Auf der Karte ein- oder ausblenden' => 'Show or hide on the map',
    'Alle Listen darunter ein- oder ausblenden' => 'Show or hide every list below',
    // Einstellungen → Geräte in plain words: apps and browsers apart, the
    // pairing in two windows (28.09.2026)
    'Hier koppelst du die YASAIMAPP-App auf deinem Handy mit deinem Konto. Erst dann lädt die App deine aufgezeichneten Wege hoch. Sie zeichnet auch auf, wenn der Bildschirm aus ist.'
        => 'Here you pair the YASAIMAPP app on your phone with your account. Only then does the app upload the routes it recorded. It records even while the screen is off.',
    'Öffnest du die App zum ersten Mal, fragt sie nach einem Schlüssel. Den bekommst du hier über „Gerät hinzufügen". An der Karte meldest du dich in der App danach ganz normal an.'
        => 'When you open the app for the first time, it asks for a key. You get it here with "Add device". After that you sign in to the map in the app as usual.',
    'Gekoppelte Apps' => 'Paired apps',
    'Gerät hinzufügen' => 'Add device',
    'Noch keine App gekoppelt.' => 'No app paired yet.',
    'Angemeldete Browser' => 'Signed-in browsers',
    'Browser, in denen du beim Anmelden „Angemeldet bleiben" gewählt hast.'
        => 'Browsers in which you chose "Stay signed in" when signing in.',
    'Keiner.' => 'None.',
    'Dieser Browser steht nicht in der Liste, weil du dich ohne „Angemeldet bleiben" angemeldet hast. Schließt du ihn, bist du abgemeldet.'
        => 'This browser is not in the list because you signed in without "Stay signed in". When you close it, you are signed out.',
    'angemeldet %s' => 'signed in %s',
    'Du bist gerade in diesem Browser. Du bleibst angemeldet, bis du ihn schließt. Danach musst du dein Passwort wieder eingeben.'
        => 'You are in this browser right now. You stay signed in until you close it. After that you have to enter your password again.',
    '„%s" wird abgemeldet. Dort musst du dein Passwort wieder eingeben.'
        => '"%s" is signed out. There you have to enter your password again.',
    '„%s" kommt danach nicht mehr an deine Funde. Was es schon hochgeladen hat, bleibt.'
        => 'After that, "%s" can no longer reach your finds. What it already uploaded stays.',
    'Diesen Browser abmelden?' => 'Sign out this browser?',
    'Browser abmelden?' => 'Sign out browser?',
    'Abgemeldet. Der Browser kommt nicht mehr an deine Funde.' => 'Signed out. The browser can no longer reach your finds.',
    'Getrennt. Das Gerät kommt nicht mehr an deine Funde.' => 'Unpaired. The device can no longer reach your finds.',
    'z. B. Handy Xiaomi' => 'e.g. Xiaomi phone',
    'Den Namen siehst nur du. So findest du das Gerät in der Liste wieder.'
        => 'Only you see the name. It helps you find the device in the list again.',
    '„%s" koppeln' => 'Pair "%s"',
    'Übertrage den Schlüssel jetzt in die App.' => 'Transfer the key to the app now.',
    'Er erscheint nur dieses eine Mal. Geht er verloren, fügst du das Gerät einfach neu hinzu.'
        => 'It appears only this once. If it gets lost, simply add the device again.',
    'Wähle einen der drei Wege:' => 'Choose one of the three ways:',
    'Diese Seite ist am Rechner offen' => 'This page is open on a computer',
    'Scanne den QR-Code mit der Kamera deines Handys.' => 'Scan the QR code with your phone\'s camera.',
    'Öffnet sie die App nicht von selbst, wähle „Text kopieren".' => 'If it does not open the app by itself, choose "Copy text".',
    'Öffne YASAIMAPP und tippe auf „Link aus der Zwischenablage einfügen".' => 'Open YASAIMAPP and tap "Link aus der Zwischenablage einfügen".',
    'Diese Seite ist am Handy offen' => 'This page is open on the phone',
    'Tippe auf „In der App öffnen". Passiert nichts, ist die App noch nicht installiert.'
        => 'Tap "Open in the app". If nothing happens, the app is not installed yet.',
    'Tippe Adresse und Schlüssel in die App ein, oder kopiere sie hinüber.'
        => 'Type the address and the key into the app, or copy them across.',
    // Organisieren's tick boxes (28.09.2026)
    'Wählen oder abwählen' => 'Choose or unchoose',
    'Alle Listen darunter wählen oder abwählen' => 'Choose or unchoose every list below',
    'Alle Punkte zeigen' => 'Show all points',
    // The MykIS sheet (includes/mykis.php, 25.09.2026)
    'MykIS-Tabelle (Pilzkartierung der DGfM)' => 'MykIS sheet (DGfM fungus mapping)',
    ' Was du unter DGfM-Kartierung → Genauigkeit ausgenommen hast, kommt nicht mit.'
        => ' What you left out under DGfM-Kartierung → Genauigkeit does not go along.',
    // The export window asks for the MykIS sheet itself (27.09.2026)
    'Nach deinen Regeln unter DGfM-Kartierung' => 'By your rules under DGfM-Kartierung',
    'Welche Funde sollen in die Tabelle?' => 'Which finds should go into the sheet?',
    // The DGfM delivery (includes/mykis_content.php, settings, 25.09.2026)
    'DGfM-Kartierung' => 'DGfM mapping',
    // The mark of a find without a pattern (find_number_fallback, 25.09.2026)
    'Ein Muster darf nicht mit „Y-" anfangen. So beginnen die Nummern, die YASAIMAPP selbst vergibt.'
        => 'A pattern may not begin with "Y-". That is how the numbers YASAIMAPP gives itself begin.',
    ' — ein Muster darf nicht mit „Y-" anfangen.' => ' — a pattern may not begin with "Y-".',
    'Fundnummer — von YASAIMAPP vergeben. Ein eigenes Muster stellst du unter Einstellungen → Fundnummer ein.'
        => 'Find number — given by YASAIMAPP. You set a pattern of your own under Einstellungen → Fundnummer.',
    // Oberfläche's own tabs (25.09.2026)
    'Farben' => 'Colours',
    'Koordination' => 'Coordinator',
    'E-Mail' => 'E-mail',
    'Zugestimmt' => 'Agreed',
    'Geliefert' => 'Delivered',
    'Jetzt senden' => 'Send now',
    'Die Kartierung braucht die Migration 0127 — sie ist noch nicht eingespielt.'
        => 'Mapping needs migration 0127 — it has not been applied yet.',
    'Das braucht die Migration 0127 — sie ist noch nicht eingespielt.'
        => 'This needs migration 0127 — it has not been applied yet.',
    'Zustimmung eingetragen.' => 'Consent recorded.',
    'Zustimmung entfernt.' => 'Consent removed.',
    'Es wartet nichts.' => 'Nothing is waiting.',
    'Eine Mail mit %d Funden ist unterwegs.' => 'One mail with %d finds is on its way.',
    '%d Mails mit %d Funden sind unterwegs.' => '%d mails with %d finds are on their way.',
    '%d Mail(s) ließen sich nicht senden — sie warten auf den nächsten Lauf.'
        => '%d mail(s) could not be sent — they wait for the next run.',
    '%d weitere warten noch — die Zeit war um.' => '%d more are still waiting — time was up.',
    'Zweimal im Jahr, Anfang Juni und Anfang Dezember, gehen die eigenen Pilzfunde der Finder, die eingewilligt haben, an die Landeskoordination ihres Bundeslandes — als Tabellen für MykIS, jeder Fund nur einmal, so genau, wie jeder Finder es gewählt hat: ohne Wahl nur der Viertelquadrant des Messtischblatts, auf Wunsch feiner bis zu genauen Koordinaten, die die DGfM nicht veröffentlicht. Eine Sammelmail je Landeskoordination, alle Finder in einer gemeinsamen Tabelle — bei sehr vielen Funden in mehreren etwa gleich großen, jeder Finder ganz in einer; danach bekommt jeder Finder, der es angekreuzt hat, eine Kopie seiner Tabellen. Antworten der Koordination gehen an den ersten Administrator.'
        => 'Twice a year, early in June and early in December, the own fungus finds of every finder who agreed go to the coordinator of their state — as sheets for MykIS, each find once, as precisely as each finder chose — without a choice only the quarter quadrant of the map sheet, finer on request up to exact coordinates, which the DGfM does not publish. One collective mail per coordinator, every finder in one shared sheet — with very many finds in several of about the same size, each finder whole in one; afterwards every finder who ticked it gets a copy of their sheets. Replies from the coordinators go to the first administrator.',
    'Es bekommt nur, wer zugestimmt hat: den Haken setzt du, sobald die Zusage da ist. Unbeaufsichtigt läuft der Versand über cronjobs/daily.php — höchstens 20 Mails je Aufruf, der Rest beim nächsten; außerhalb von Juni und Dezember tut er nichts.'
        => 'Only coordinators who agreed receive anything: tick them once their consent is in. Unattended, the delivery runs through cronjobs/daily.php — at most 20 mails per call, the rest with the next; outside June and December it does nothing.',
    'Grenze Nord/Süd noch offen — Funde von hier warten.' => 'North/south line not settled yet — finds from here wait.',
    'Vertretung: %s' => 'Deputy: %s',
    '%s hat zugestimmt' => '%s has agreed',
    '%d · zuletzt %s' => '%d · last %s',
    'Wartend: ein Fund' => 'Waiting: one find',
    'Wartend: %d Funde von %d eingewilligten Findern' => 'Waiting: %d finds from %d consenting finders',
    'Die wartenden Funde gehen jetzt per Mail an die Landeskoordination, die zugestimmt hat — jeder Fund nur einmal; was gesendet ist, lässt sich nicht zurückholen.'
        => 'The waiting finds now go by mail to the coordinators who agreed — each find once; what is sent cannot be called back.',
    'Liste nach dgfm-ev.de, Landeskoordinatoren für Pilzkartierung — Stand 25.09.2026.'
        => 'List after dgfm-ev.de, state coordinators for fungus mapping — as of 25.09.2026.',
    'Pilzkartierung der DGfM' => 'DGfM fungus mapping',
    // The certainty level for sequenced finds (find_fields.php, 25.09.2026)
    'genetisch sicher' => 'genetically certain',
    // The collective delivery and the finders' confirmations (0128)
    '%d Kopie(n) an Finder sind unterwegs.' => '%d copy/copies to finders on their way.',
    '%d Kopie(n) offen' => '%d copy/copies pending',
    'Beispiel an mich senden' => 'Send a sample to me',
    'Bestätigt am %s' => 'Confirmed on %s',
    // How precisely a finder hands over their finds (0129)
    'Viertelquadrant — etwa 5,5 × 6 km, ohne Koordinaten' => 'Quarter quadrant — about 5.5 × 6 km, no coordinates',
    '16tel-Quadrant — etwa 2,8 × 3 km, ohne Koordinaten' => '16th quadrant — about 2.8 × 3 km, no coordinates',
    '64stel-Quadrant — etwa 1,4 × 1,5 km, ohne Koordinaten' => '64th quadrant — about 1.4 × 1.5 km, no coordinates',
    'Genaue Koordinaten — auf etwa 3 m' => 'Exact coordinates — to about 3 m',
    'Die Größen gelten für Hessen; im Norden sind die Blätter etwas schmaler, im Süden etwas breiter.'
        => 'The sizes hold for Hessen; in the north the sheets are a little narrower, in the south a little wider.',
    'Die Beispielmail ließ sich nicht senden.' => 'The sample mail could not be sent.',
    '%d Beispielmails sind an %s unterwegs.' => '%d sample mails are on their way to %s.',
    'Wie die Mail an eine Landeskoordination aussieht: ein Beispiel mit zwanzig erfundenen Findern in Hessen, an deine eigene Adresse, als „[Beispiel]“ markiert.'
        => 'What the mail to a state coordinator looks like: a sample with twenty invented finders in Hessen, to your own address, marked „[Beispiel]".',
    'Deine Pilzfunde sind an die DGfM-Kartierung gegangen' => 'Your fungus finds went to the DGfM mapping',
    'wie vereinbart sind deine Pilzfunde an die Landeskoordination der DGfM gegangen:'
        => 'as agreed, your fungus finds went to the DGfM state coordination:',
    '%s (%s, %s): ein Fund' => '%s (%s, %s): one find',
    'Viele Grüße' => 'Kind regards',
    '%s (%s, %s): %d Funde' => '%s (%s, %s): %d finds',
    'Die Tabellen hängen an — genau das, was die Landeskoordination bekommen hat. Jeder Fund geht nur einmal; spätere Änderungen gehen nicht nach. Rückfragen kommen direkt an deine Adresse.'
        => 'The sheets are attached — exactly what the coordinator received. Each find goes once; later changes do not follow. Questions come straight to your address.',
    'Die Kopie oder die Meldung selbst stellst du jederzeit in der Verwaltung unter Einstellungen → DGfM-Kartierung ab.'
        => 'You can switch off the copy, or the reporting itself, at any time in the administration under Einstellungen → DGfM-Kartierung.',
    'Auch Funde melden, bei denen jemand anderes Finder ist'
        => 'Also report finds where someone else is the finder',
    'Meine Funde an die DGfM-Kartierung melden' => 'Report my finds to the DGfM mapping',
    // The DGfM tab in plain words, consent first, then „Meldung" and
    // „Genauigkeit" (27.09.2026)
    'Meldung' => 'Report',
    // The DGfM tab shortened, the conditions behind a question mark, two
    // ticks for other finders' finds (27.09.2026)
    'Deine Pilzfunde können an die Pilzkartierung der DGfM gehen, der Deutschen Gesellschaft für Mykologie. YASAIMAPP schickt sie zweimal im Jahr, Anfang Juni und Anfang Dezember. Jeder Fund geht automatisch an den entsprechenden Landeskoordinator.'
        => 'Your fungus finds can go to the fungus mapping of the DGfM, the German Mycological Society. YASAIMAPP sends them twice a year, early in June and early in December. Every find goes automatically to the coordinator of the state it was found in.',
    'Es gehen nur sicher bestimmte Funde. Dein Name und deine E-Mail-Adresse stehen dabei, damit man dich bei Fragen erreicht.'
        => 'Only finds determined with certainty go. Your name and your e-mail address go along, so that you can be reached with questions.',
    'Welche Funde gehen mit?' => 'Which finds go along?',
    'Ein Fund geht nur mit, wenn alles zutrifft:' => 'A find goes along only if all of this is true:',
    'mit einem Namen aus der Artenliste der DGfM,' => 'with a name from the DGfM\'s species list,',
    'Hast du nichts angegeben, gilt ein Fund als sicher — außer im Namen steht cf., aff. oder sp. Sammelarten mit agg. und Namen mit s. l. gehen mit.'
        => 'If you said nothing, a find counts as certain — unless the name carries cf., aff. or sp. Aggregates with agg. and names with s. l. go along.',
    'Auch Funde melden, bei denen ich nicht Finder, aber Bestimmer bin'
        => 'Also report finds where I am not the finder but the determiner',
    'Auch Funde melden, bei denen ich weder Finder noch Bestimmer bin'
        => 'Also report finds where I am neither finder nor determiner',
    'Deine eigenen Funde immer. Dazu:' => 'Your own finds always. In addition:',
    'Auch Funde, bei denen ich nicht Finder, aber Bestimmer bin'
        => 'Also finds where I am not the finder but the determiner',
    'Auch Funde, bei denen ich weder Finder noch Bestimmer bin'
        => 'Also finds where I am neither finder nor determiner',
    'gefunden in Deutschland,' => 'found in Germany,',
    'mit Fundtag,' => 'with a find day,',
    'sicher bestimmt: makroskopisch, mikroskopisch oder genetisch.'
        => 'determined with certainty: macroscopically, microscopically or genetically.',
    'Das geht erst, wenn deine E-Mail-Adresse bestätigt ist. Den Link dafür bekommst du unter Konto.'
        => 'This works only once your e-mail address is confirmed. You get the link for it under Konto.',
    'Zugestimmt am %s. Bisher %d Funde gemeldet. Die nächste Meldung geht %s raus.'
        => 'Agreed on %s. %d finds reported so far. The next report goes out %s.',
    'Anfang Juni' => 'early in June',
    'Anfang Dezember' => 'early in December',
    'Schick mir eine Kopie der Funde, die an die Landeskoordinatoren gingen'
        => 'Send me a copy of the finds that went to the state coordinators',
    'Neuer Reiter: Genauigkeit' => 'New tab: Genauigkeit',
    'Gespeichert. Deine Funde gehen ab jetzt zweimal im Jahr an die Landeskoordinatoren. Die nächste Meldung geht %s raus.'
        => 'Saved. From now on your finds go to the state coordinators twice a year. The next report goes out %s.',
    'Neben „Meldung" gibt es jetzt den Reiter „Genauigkeit". Dort stellst du ein und filterst, was und wie genau du an die DGfM schickst.'
        => 'Beside "Meldung" there is now the tab "Genauigkeit". There you set and filter what you send to the DGfM, and how precisely.',
    'Genauigkeit öffnen' => 'Open Genauigkeit',
    'Gespeichert. Deine Funde gehen nicht mehr an die Landeskoordinatoren.'
        => 'Saved. Your finds no longer go to the state coordinators.',
    'Eigene Regeln für einzelne Arten und Gruppen brauchen die Migration 0143 — sie ist noch nicht eingespielt.'
        => 'Rules of your own for single species and groups need migration 0143 — it has not been applied yet.',
    'Das braucht die Migration 0129 — sie ist noch nicht eingespielt.'
        => 'This needs migration 0129 — it has not been applied yet.',
    'Hier legst du fest, wie genau der Fundort deiner Funde an die Kartierung geht. Du kannst Gattungen und Arten ganz ausnehmen. Und du kannst Gruppen eine eigene Genauigkeit geben: Rote-Liste-Arten, Speisepilzen, einzelnen Gattungen und Arten. Für alle übrigen Funde gilt die Genauigkeit ganz unten.'
        => 'Here you decide how precisely the place of your finds goes to the mapping. You can leave out genera and species entirely. And you can give groups a precision of their own: Red List species, edible mushrooms, single genera and species. All other finds take the precision at the very bottom.',
    'Passen auf einen Fund mehrere Regeln, gilt immer die gröbste. Steht zum Beispiel „Boletus" auf Viertelquadrant und „Boletus regius" auf genau, geht auch der Königsröhrling nur auf den Viertelquadranten genau mit.'
        => 'If several rules fit a find, the coarsest always applies. If, for example, „Boletus" stands at quarter quadrant and „Boletus regius" at exact, the royal bolete too goes only as precisely as the quarter quadrant.',
    'Genaue Koordinaten sieht nur der Landeskoordinator. Die DGfM veröffentlicht sie nicht.'
        => 'Only the state coordinator sees exact coordinates. The DGfM does not publish them.',
    'Viertelquadrant' => 'quarter quadrant',
    '16tel' => '16th',
    '64stel' => '64th',
    'genau' => 'exact',
    'Genauigkeit für %s' => 'Precision for %s',
    '„%s" entfernen' => 'Remove „%s"',
    'Nicht melden' => 'Do not report',
    'Funde dieser Gattungen und Arten gehen gar nicht an die Kartierung.'
        => 'Finds of these genera and species do not go to the mapping at all.',
    'Gattungen' => 'Genera',
    'Gattung eingeben' => 'Enter a genus',
    'Art eingeben' => 'Enter a species',
    'Rote-Liste-Arten' => 'Red List species',
    'Wähle für jede Stufe der Roten Liste, wie genau ihre Funde mitgehen.'
        => 'Choose for each category of the Red List how precisely its finds go along.',
    'wie alle übrigen' => 'like all others',
    'So groß sind die Stufen:' => 'How large the steps are:',
    'Alle Speisepilze' => 'All edible mushrooms',
    'Alle Funde' => 'All finds',
    'Speisepilze' => 'Edible mushrooms',
    'Speisepilz heißt: Die Art steht auf der Speisepilzliste der DGfM. Wer seine Speisepilzstellen nicht verraten möchte, stellt hier eine gröbere Stufe ein.'
        => 'Edible means: the species is on the DGfM\'s list of edible mushrooms. Whoever does not want to give away their spots sets a coarser step here.',
    'Einzelne Gattungen' => 'Single genera',
    'Eine eigene Genauigkeit für eine ganze Gattung.' => 'A precision of its own for a whole genus.',
    'Einzelne Arten' => 'Single species',
    'Eine eigene Genauigkeit für eine Art. Sie gilt auch für ihre Varietäten und Formen.'
        => 'A precision of its own for a species. It applies to its varieties and forms too.',
    'Alle übrigen Funde' => 'All other finds',
    'Wie genau soll der Fundort mitgehen?' => 'How precisely should the place go along?',
    '„%s" steht nicht als Art in der Artenliste der DGfM. Eine Art schreibst du mit zwei Wörtern, zum Beispiel „Boletus edulis".'
        => '„%s" is not a species in the DGfM\'s species list. You write a species with two words, for example „Boletus edulis".',
    '„%s" steht nicht als Gattung in der Artenliste der DGfM. Eine Gattung ist ein Wort, zum Beispiel „Boletus".'
        => '„%s" is not a genus in the DGfM\'s species list. A genus is one word, for example „Boletus".',
    // What an import leaves out, and why (import_screen.php, 25.09.2026)
    'Finder, Bestimmer und die anderen Fundfelder aus der Datei kommen in ihre Felder. Deshalb wird die neue Liste eine Artenliste.'
        => 'Finder, determiner and the other find fields from the file go into their fields. That is why the new list becomes a species list.',
    'Sind das deine Funde, nur anders geschrieben? Dann ändere in deiner Datei den Finder auf „%s" und spiele die Datei noch einmal ein.'
        => 'Are these your finds, just spelled differently? Then change the finder in your file to "%s" and import the file again.',
    // The find-number tab in plain words — two open groups, the restart in
    // examples (25.09.2026)
    'Deine Fundnummer'
        => 'Your find number',
    'Jeder neue Fund in einer Artenliste bekommt eine Fundnummer. Wie sie aussieht, bestimmst du mit einem Muster. Wie du ein Muster baust, steht weiter unten.'
        => 'Every new find in a species list gets a find number. You decide what it looks like with a pattern. How to build a pattern is explained further down.',
    'Lässt du das Muster leer, bekommt der Fund eine Nummer von YASAIMAPP, zum Beispiel %s.'
        => 'If you leave the pattern empty, the find gets a number from YASAIMAPP, for example %s.',
    'Das Muster gilt nur für neue Funde. Funde, die schon eine Nummer haben, behalten sie. Eine Nummer von Hand trägst du im Fundfenster ein. Dort steht auch, ob sie frei ist.'
        => 'The pattern applies to new finds only. Finds that have a number keep it. You enter a number by hand in the point window. It also tells you there whether the number is free.',
    'Legst du einen Punkt erst später in eine Artenliste, bekommt er keine Nummer nach deinem Muster. Im Fundfenster übernimmst du die nächste freie Nummer mit einem Klick.'
        => 'If you put a point into a species list only later, it gets no number from your pattern. In the point window you take the next free number with one click.',
    'Alte Funde nummerierst du unter Organisieren um. Markiere dort die Funde und klicke auf „Fundnummern …". Das Fenster zeigt dir jede neue Nummer, bevor etwas gespeichert wird.'
        => 'You renumber old finds under Organize. Mark the finds there and click "Find numbers …". The window shows you every new number before anything is saved.',
    'Mit jedem neuen %s wieder bei 1 anfangen. Weil das Datum im Muster steht, gibt es trotzdem keine Nummer doppelt.'
        => 'Start again at 1 with every new %s. Because the date is in the pattern, no number appears twice all the same.',
    'Dein nächster Fund bekommt diese Nummer:'
        => 'Your next find gets this number:',
    'eine Nummer von YASAIMAPP, zum Beispiel %s'
        => 'a number from YASAIMAPP, for example %s',
    ' — im Muster fehlt noch die laufende Nummer, zum Beispiel NNNN.'
        => ' — the pattern still lacks the running number, for example NNNN.',
    'Gespeichert. Das gilt für alle Funde, die du ab jetzt anlegst.'
        => 'Saved. This applies to all finds you create from now on.',
    'Das Muster ist zu lang. Es darf höchstens 60 Zeichen haben.'
        => 'The pattern is too long. It may have 60 characters at most.',
    'Im Muster fehlt die laufende Nummer. Schreib zum Beispiel NNNN — oder deine letzte Nummer, zum Beispiel 00543.'
        => 'The pattern lacks the running number. Write NNNN, for example — or your last number, for example 00543.',
    'So baust du das Muster'
        => 'How to build the pattern',
    'das Jahr des Funds, mit vier oder zwei Ziffern: %s oder %s'
        => 'the year of the find, with four or two digits: %s or %s',
    'Monat und Tag des Funds, mit zwei Ziffern: %s und %s'
        => 'month and day of the find, with two digits: %s and %s',
    'die laufende Nummer. So viele N du schreibst, so viele Stellen hat sie: aus NNNN wird %s.'
        => 'the running number. As many N as you write, that many digits it has: NNNN becomes %s.',
    'Statt der N kannst du deine letzte Nummer schreiben. Der nächste Fund zählt dann von dort weiter.'
        => 'Instead of the N you can write your last number. The next find then counts on from there.',
    'bleibt so, wie du es schreibst: Buchstaben, Striche, Leerzeichen'
        => 'stays exactly as you write it: letters, dashes, spaces',
    'wird heute zu'
        => 'becomes today',
    'der nächste nach deiner Nummer 543'
        => 'the next one after your number 543',
    'Mit Haken „wieder bei 1 anfangen"'
        => 'With the tick "start again at 1"',
    'heute · danach'
        => 'today · afterwards',
    'jedes Jahr neu'
        => 'new every year',
    'jeden Tag neu'
        => 'new every day',
    'der erste Fund %s:'
        => 'the first find of %s:',
    'der erste Fund am nächsten Tag:'
        => 'the first find the next day:',
    'Neu anfangen geht nur, wenn ein Jahr im Muster steht. Für jeden Monat braucht es JJ und MM, für jeden Tag JJ, MM und TT.'
        => 'Starting again only works when a year is in the pattern. For every month it takes JJ and MM, for every day JJ, MM and TT.',
    'Ein Muster darf nicht mit „Y-" anfangen. So beginnen die Nummern, die YASAIMAPP selbst vergibt — deshalb kann keine deiner Nummern je gleich lauten wie eine davon.'
        => 'A pattern may not begin with "Y-". That is how the numbers YASAIMAPP gives itself begin — so none of your numbers can ever read the same as one of them.',
    // The find numbers of an import (import_screen.php, 25.09.2026)
    // The import preview's table: every entry, its verdict, the conflicts
    // with their way out (import_content.php, importrows.js, 26.09.2026)
    'Nummer doppelt' => 'Number twice',
    'Anderer Finder' => 'Other finder',
    'Du bist Bestimmer' => 'You are the determiner',
    'Andere Nummer' => 'Other number',
    'Vielleicht umbenannt' => 'Perhaps renamed',
    'Alle Konflikte %d' => 'All conflicts %d',
    'Schon da' => 'Here already',
    'Im Papierkorb' => 'In the trash',
    'Alle' => 'All',
    '%s — %d Funde' => '%s — %d finds',
    'Alle einspielen' => 'Import all',
    'Keinen einspielen' => 'Import none',
    'Ein Fund, bei dem du Bestimmer bist' => 'One find you determined',
    '%d Funde, bei denen du Bestimmer bist' => '%d finds you determined',
    '%d doppelte Nummern' => '%d numbers twice',
    'Allen die nächste freie Nummer geben' => 'Give them all the next free number',
    'Allen keine eigene Nummer geben' => 'Give none of them an own number',
    'Fund und Konflikt' => 'Find and conflict',
    'Deine Entscheidung' => 'Your decision',
    'Schon da · bekommt Nummer' => 'Here already · gets a number',
    'Fundnummer: %s' => 'Find number: %s',
    '%s trägt schon „%s" weiter oben in dieser Datei.' => '%s is already carried by "%s" further up in this file.',
    '%s trägt schon „%s" vom %s.' => '%s is already carried by "%s" of %s.',
    '%s trägt schon „%s".' => '%s is already carried by "%s".',
    'Der liegt im Papierkorb.' => 'That one lies in the trash.',
    'Nicht dein Fund. Eingespielt bleibt %s der Finder.' => 'Not your find. Imported, %s stays the finder.',
    'Die Nummer %s steht dann in der Notiz, sie gehört zur Sammlung des Finders.'
        => 'The number %s then stands in the note; it belongs to the finder\'s collection.',
    'Gefunden hat ihn %s, bestimmt hast du ihn. Eingespielt bleibt %s der Finder.'
        => '%s found it, you determined it. Imported, %s stays the finder.',
    'Die Nummer %s steht dann in der Notiz.' => 'The number %s then stands in the note.',
    'Diesen Fund gibt es schon, mit der Nummer %s.' => 'This find is here already, with the number %s.',
    'Nummer %s' => 'number %s',
    'An genau dieser Stelle liegt schon „%s"%s. Ist das derselbe Fund mit neuem Namen?'
        => 'At exactly this spot lies "%s"%s already. Is it the same find with a new name?',
    'Bekommt die Nummer %s aus der Datei.' => 'Gets the number %s from the file.',
    'Gleicher Name, gleiche Stelle — bleibt, wie er ist.' => 'Same name, same spot — stays as it is.',
    'Liegt in deinem Papierkorb und bleibt draußen.' => 'Lies in your trash and stays out.',
    'Diesen Fund einspielen' => 'Import this find',
    'Alle %d von %s einspielen' => 'Import all %d of %s',
    'Keinen von %s einspielen' => 'Import none of %s',
    'Alle %d einspielen, bei denen du Bestimmer bist' => 'Import all %d you determined',
    'Keinen einspielen, bei dem du Bestimmer bist' => 'Import none you determined',
    'Keine Nummer deiner Reihe' => 'No number of your series',
    'Die nächste freie deiner Reihe' => 'The next free one of your series',
    '%s behalten' => 'Keep %s',
    '%s aus der Datei nehmen' => 'Take %s from the file',
    'Nächste freie Nummer: %s' => 'Next free number: %s',
    'Diese Nummer:' => 'This number:',
    'frei' => 'free',
    'gibt es schon' => 'exists already',
    'Ohne eigene Nummer' => 'Without an own number',
    '— er bekommt eine von YASAIMAPP' => '— it gets one from YASAIMAPP',
    'Bei allen %d doppelten: nächste freie Nummer' => 'For all %d twice: next free number',
    'Bei allen %d doppelten: ohne eigene Nummer' => 'For all %d twice: without an own number',
    'Neuen Namen übernehmen' => 'Take the new name',
    'Alten Namen lassen' => 'Keep the old name',
    'Neuen Fund anlegen' => 'Create a new find',
    'Nicht einspielen' => 'Do not import',
    'Bitte wähle eine Möglichkeit.' => 'Please choose one.',
    'Der neue Fund bekommt %s.' => 'The new find gets %s.',
    'Bei einem Fund brauche ich deine Entscheidung.' => 'One find needs your decision.',
    'Bei %d Funden brauche ich deine Entscheidung.' => '%d finds need your decision.',
    'Bei einem Fund fehlt noch deine Entscheidung.' => 'One find still lacks your decision.',
    'Bei %d Funden fehlt noch deine Entscheidung.' => '%d finds still lack your decision.',
    'Bei einem Fund fehlt noch deine Entscheidung. Er ist rot markiert.'
        => 'One find still lacks your decision. It is marked in red.',
    'Bei %d Funden fehlt noch deine Entscheidung. Sie sind rot markiert.'
        => '%d finds still lack your decision. They are marked in red.',
    // The find number in the point window (index.php, map.js, 26.09.2026)
    'Diese Nummer trägt schon „%s".' => 'This number is already carried by "%s".',
    'Eine Fundnummer darf nicht mit „Y-" anfangen. So beginnen die Nummern, die YASAIMAPP selbst vergibt.'
        => 'A find number may not begin with "Y-". That is how the numbers YASAIMAPP gives itself begin.',
    // The cloud upload's progress line (cloudupload.js)
    '%s%s → %s statt %s' => '%s%s → %s instead of %s',
    // The number field's line in the point window (map.js, drawNumberHint)
    '%s nehmen' => 'Take %s',
    'Ohne eigene Nummer trägt der Fund seine Nummer von YASAIMAPP.' => 'Without an own number the find wears its number from YASAIMAPP.',
    'Nicht dein Muster %s, sondern eine eigene Reihe. Dein Zähler bleibt davon unberührt.'
        => 'Not your pattern %s but a series of its own. Your counter stays untouched.',
    'für diesen Tag' => 'for this day',
    'für diesen Monat' => 'for this month',
    'für dieses Jahr' => 'for this year',
    'Die nächste freie Nummer %s ist %s.' => 'The next free number %s is %s.',
    'Die nächste freie Nummer ist %s.' => 'The next free number is %s.',
    'Nimmst du %s, zählt YASAIMAPP danach bei %s weiter.' => 'If you take %s, YASAIMAPP goes on at %s afterwards.',
    'Wird als %s gespeichert.' => 'Saved as %s.',
    'Das Datum in der Nummer liegt in der Zukunft.' => 'The date in the number lies in the future.',
    'Datum in der Nummer: %s. Fundzeit: %s.' => 'Date in the number: %s. Find time: %s.',
    // Many find numbers at once — Organisieren → Fundnummern (numberdialog.js,
    // numbering.js, find_number.php, organize_content.php; 26.09.2026)
    'Fundnummern&nbsp;…' => 'Find numbers&nbsp;…',
    'Fundnummern vergeben' => 'Give find numbers',
    'Welche Nummern sollen die Funde bekommen?' => 'Which numbers should the finds get?',
    'Hier fehlt noch deine Antwort.' => 'Your answer is still missing here.',
    'Die nächsten freien Nummern deiner Reihe' => 'The next free numbers of your series',
    'Fortlaufend ab' => 'Counting on from',
    'Erste Nummer oder Muster' => 'First number or pattern',
    'Eigene Nummern entfernen' => 'Remove own numbers',
    'Welche Funde sollen eine neue Nummer bekommen?' => 'Which finds should get a new number?',
    'In welcher Reihenfolge?' => 'In which order?',
    'Nach Fundzeit, die ältesten zuerst' => 'By find time, the oldest first',
    'Was passiert mit den bisherigen Nummern?' => 'What happens to the previous numbers?',
    'Nicht aufheben' => 'Do not keep them',
    'Sollen neue Funde danach auch so nummeriert werden?' => 'Should new finds be numbered this way afterwards too?',
    'Bisher' => 'Before',
    'Zwei' => 'Two',
    'Drei' => 'Three',
    'Vier' => 'Four',
    'Fünf' => 'Five',
    'Sechs' => 'Six',
    'Eine Frage beantwortest du selbst. Danach zeigt die Tabelle jede neue Nummer, bevor etwas gespeichert wird.' => 'One question is yours to answer. Then the table shows every new number before anything is saved.',
    '%s Fragen beantwortest du selbst. Danach zeigt die Tabelle jede neue Nummer, bevor etwas gespeichert wird.' => '%s questions are yours to answer. Then the table shows every new number before anything is saved.',
    'Wie beim Kartieren. Jeder Fundtag zählt für sich: %s …' => 'As when mapping. Every find day counts on its own: %s …',
    'Wie beim Kartieren. Jeder Monat zählt für sich: %s …' => 'As when mapping. Every month counts on its own: %s …',
    'Wie beim Kartieren. Jedes Jahr zählt für sich: %s …' => 'As when mapping. Every year counts on its own: %s …',
    'Wie beim Kartieren: %s …' => 'As when mapping: %s …',
    'Tippe die erste Nummer, etwa neu-01. Oder ein Muster wie unter Einstellungen → Fundnummer, etwa CW-JJJJ-NNNN.' => 'Type the first number, say new-01. Or a pattern as under Settings → Find number, say CW-JJJJ-NNNN.',
    'JJJJ wird das Fundjahr' => 'JJJJ becomes the find year',
    'JJ wird das Fundjahr' => 'JJ becomes the find year',
    'MM der Monat' => 'MM the month',
    'TT der Tag' => 'TT the day',
    'Ein Muster: %s, die laufende Nummer beginnt bei %s.' => 'A pattern: %s, the running number begins at %s.',
    'Ein Muster: Die laufende Nummer beginnt bei %s.' => 'A pattern: the running number begins at %s.',
    'Die weiteren zählen hoch: %s, %s …' => 'The following ones count up: %s, %s …',
    'Gespeichert wird %s.' => 'Saved as %s.',
    'Die Funde tragen dann wieder ihre Nummer von YASAIMAPP, etwa %s.' => 'The finds then wear their number from YASAIMAPP again, such as %s.',
    'Beginnt die laufende Nummer jeden Tag wieder bei %s?' => 'Does the running number begin at %s again every day?',
    'Ja, jeden Tag neu' => 'Yes, anew every day',
    'Nein, sie läuft über die Tage durch' => 'No, it runs on across the days',
    'Beginnt die laufende Nummer jeden Monat wieder bei %s?' => 'Does the running number begin at %s again every month?',
    'Ja, jeden Monat neu' => 'Yes, anew every month',
    'Nein, sie läuft über die Monate durch' => 'No, it runs on across the months',
    'Beginnt die laufende Nummer jedes Jahr wieder bei %s?' => 'Does the running number begin at %s again every year?',
    'Ja, jedes Jahr neu' => 'Yes, anew every year',
    'Nein, sie läuft über die Jahre durch' => 'No, it runs on across the years',
    'Nur die ohne eigene Nummer (%d)' => 'Only those without an own number (%d)',
    'Alle gewählten Funde (%d)' => 'All chosen finds (%d)',
    'Wie die Tabelle sortiert ist: nach „%s", aufsteigend' => 'As the table is sorted: by "%s", ascending',
    'Wie die Tabelle sortiert ist: nach „%s", absteigend' => 'As the table is sorted: by "%s", descending',
    'Funde mit eigener Nummer bekommen eine neue.' => 'Finds with an own number get a new one.',
    'Ein Fund verliert seine Nummer %s.' => 'One find loses its number %s.',
    '%d Funde verlieren ihre Nummer, etwa %s.' => '%d finds lose their number, such as %s.',
    'Steht die alte auf einem Belegzettel, findet die Suche den Fund über die Notiz weiter.' => 'If the old one is on a voucher label, the search still finds the find through the note.',
    'In die Notiz schreiben, etwa „%s"' => 'Write it into the note, such as "%s"',
    'Frühere Fundnummer: %s' => 'Former find number: %s',
    'Ja. Das Muster ersetzt %s unter Einstellungen → Fundnummer. Der nächste neue Fund bekommt %s.' => 'Yes. The pattern replaces %s under Settings → Find number. The next new find gets %s.',
    'Ja. Neue Funde bekommen dann Nummern nach diesem Muster, der nächste %s.' => 'Yes. New finds then get numbers after this pattern, the next one %s.',
    'Nein. Neue Funde zählen weiter nach %s.' => 'No. New finds go on counting after %s.',
    'Nein. Neue Funde bekommen weiter keine eigene Nummer.' => 'No. New finds go on without an own number.',
    '%s trägt schon „%s" – bleibt' => '%s is carried by "%s" already – stays',
    'bleibt – ohne Fundzeit fehlt der Nummer ihr Datum' => 'stays – without a find time the number lacks its date',
    'bleibt' => 'stays',
    'Tippe oben die erste Nummer oder ein Muster ein.' => 'Type the first number or a pattern above.',
    'Bei den rot markierten Fragen fehlt noch deine Antwort.' => 'The questions marked in red still lack your answer.',
    'Beantworte zuerst die Frage oben.' => 'Answer the question above first.',
    'Beantworte zuerst die Fragen oben.' => 'Answer the questions above first.',
    'Keiner der gewählten Funde hat eine eigene Nummer.' => 'None of the chosen finds has an own number.',
    'Ein Fund verliert seine eigene Nummer. Er trägt dann wieder seine Nummer von YASAIMAPP.' => 'One find loses its own number. It then wears its number from YASAIMAPP again.',
    '%d Funde verlieren ihre eigene Nummer. Sie tragen dann wieder ihre Nummer von YASAIMAPP.' => '%d finds lose their own number. They then wear their number from YASAIMAPP again.',
    'Keiner der gewählten Funde bekommt eine neue Nummer.' => 'None of the chosen finds gets a new number.',
    'Ein Fund bekommt die Nummer %s.' => 'One find gets the number %s.',
    '%d Funde bekommen eine Nummer, von %s bis %s.' => '%d finds get a number, from %s to %s.',
    '%d Funde bekommen eine Nummer.' => '%d finds get a number.',
    '%s trägt schon „%s". Diesen Fund lasse ich aus.' => '%s is carried by "%s" already. I leave this find out.',
    '%d dieser Nummern tragen schon andere Funde. Diese Funde lasse ich aus.' => '%d of these numbers are carried by other finds already. I leave these finds out.',
    'Ein Fund verliert dabei seine bisherige Nummer: %s.' => 'One find loses its previous number: %s.',
    '%d Funde verlieren dabei ihre bisherige Nummer.' => '%d finds lose their previous number.',
    'Die bisherigen Nummern kommen in die Notiz.' => 'The previous numbers go into the note.',
    'Unter Einstellungen → Fundnummer steht danach %s.' => 'Settings → Find number then holds %s.',
    'Das ist nicht dein Muster %s, sondern eine eigene Reihe. Deine Reihe zählt davon unberührt weiter.' => 'That is not your pattern %s but a series of its own. Your series counts on untouched.',
    'Deine Reihe zählt danach wie bisher bei %s weiter.' => 'Your series goes on at %s as before.',
    'Danach zählt deine Reihe bei %s weiter.' => 'Afterwards your series goes on at %s.',
    '„%s" bekommt keine Nummer: Nummern gibt es nur für eigene Funde aus Artenlisten.' => '"%s" gets no number: numbers are only for own finds from species lists.',
    '%d der gewählten Punkte bekommen keine Nummer: Nummern gibt es nur für eigene Funde aus Artenlisten.' => '%d of the chosen points get no number: numbers are only for own finds from species lists.',
    'Nummern vergeben' => 'Give numbers',
    'Nichts zu ändern' => 'Nothing to change',
    'Eine Nummer entfernen' => 'Remove one number',
    '%d Nummern entfernen' => 'Remove %d numbers',
    'Eine Nummer vergeben' => 'Give one number',
    '%d Nummern vergeben' => 'Give %d numbers',
    'Die Zahl in der ersten Nummer ist zu lang.' => 'The digits of the first number are too long.',
    'Im Muster fehlt die laufende Nummer: NNN oder eine Zahl.' => 'The pattern lacks the running number: NNN or a number.',
    'In der ersten Nummer fehlt eine Zahl, die hochzählen kann.' => 'The first number lacks digits that can count up.',
    'Welche Nummern die Funde bekommen sollen, fehlt noch.' => 'Which numbers the finds should get is still missing.',
    'Für die nächsten freien Nummern brauchst du ein Muster unter Einstellungen → Fundnummer.' => 'For the next free numbers you need a pattern under Settings → Find number.',
    'Die erste Nummer fehlt noch.' => 'The first number is still missing.',
    'Ob die laufende Nummer neu beginnt, fehlt noch.' => 'Whether the running number begins anew is still missing.',
    'Ob neue Funde danach auch so nummeriert werden, fehlt noch.' => 'Whether new finds are numbered this way afterwards is still missing.',
    'Unter den gewählten Punkten ist kein eigener Fund aus einer Artenliste.' => 'None of the chosen points is an own find from a species list.',
    'Welche Funde eine neue Nummer bekommen sollen, fehlt noch.' => 'Which finds should get a new number is still missing.',
    'Was mit den bisherigen Nummern passiert, fehlt noch.' => 'What happens to the previous numbers is still missing.',
    '%s (im Papierkorb)' => '%s (in the trash)',
    'Nichts zu ändern — die Nummern standen schon so.' => 'Nothing to change — the numbers were like that already.',
    'Ein Fund trägt wieder seine Nummer von YASAIMAPP.' => 'One find wears its number from YASAIMAPP again.',
    '%d Funde tragen wieder ihre Nummer von YASAIMAPP.' => '%d finds wear their number from YASAIMAPP again.',
    'Ein Fund trägt jetzt eine neue Nummer.' => 'One find carries a new number now.',
    '%d Funde tragen jetzt eine neue Nummer.' => '%d finds carry a new number now.',
    'Einer blieb, wie er war: Seine neue Nummer trägt schon ein anderer Fund.' => 'One stayed as it was: its new number is carried by another find already.',
    '%d blieben, wie sie waren: Ihre neuen Nummern tragen schon andere Funde.' => '%d stayed as they were: their new numbers are carried by other finds already.',
    'Einer ohne Fundzeit blieb ohne neue Nummer.' => 'One without a find time stayed without a new number.',
    '%d ohne Fundzeit blieben ohne neue Nummer.' => '%d without a find time stayed without a new number.',
    'Die bisherigen Nummern stehen in der Notiz.' => 'The previous numbers stand in the note.',
    'Neue Funde bekommen ihre Nummer jetzt nach %s.' => 'New finds get their number after %s now.',
    // The questions over the answers (import preview, DGfM settings; 26.09.2026)
    'Welche Nummer?' => 'Which number?',
    'Was soll passieren?' => 'What should happen?',
    'Wie genau?' => 'How precisely?',
    // The report window's questions (organize_content.php, 26.09.2026)
    'In welcher Form?' => 'In which form?',
    'Fotos in welcher Größe?' => 'Photos at which size?',
    // The voucher tick (find_fields.php, 26.09.2026)
    'Beleg aufbewahrt' => 'Voucher kept',
    // The move to another cloud (Cloud tab, includes/cloud_moves.php; 26.09.2026)
    'Der Umzug braucht die Migration 0142 — sie ist noch nicht eingespielt.'
        => 'The move needs migration 0142 — it has not been applied yet.',
    'Für den Umzug müssen die alte und die neue Cloud verbunden sein.'
        => 'For the move, the old and the new cloud must both be connected.',
    'Es läuft schon ein Umzug.'
        => 'A move is already running.',
    'Vorlage „%s"'
        => 'Preset "%s"',
    'Belegfoto bei „%s"'
        => 'Voucher photo at "%s"',
    'Belegfoto bei einem Fund ohne Namen'
        => 'Voucher photo at a find without a name',
    'Aufnahme bei „%s"'
        => 'Recording at "%s"',
    'Aufnahme bei einem Fund ohne Namen'
        => 'Recording at a find without a name',
    'Kopie eines Bildes bei „%s"'
        => 'Copy of a picture at "%s"',
    'Kopie eines Bildes bei einem Fund ohne Namen'
        => 'Copy of a picture at a find without a name',
    'In %s nicht mehr zu finden.'
        => 'No longer to be found in %s.',
    'Zu groß für den Umzug (%s). Der Server trägt Dateien bis %s.'
        => 'Too big for the move (%s). The server carries files up to %s.',
    'Der Fund zu diesem Bild ist nicht mehr da.'
        => 'The find this picture belongs to is gone.',
    '%s gibt deine Bilder gerade nicht heraus. Der Umzug versucht es von selbst wieder.'
        => '%s is not handing out your pictures right now. The move will try again by itself.',
    '%s ist gerade nicht erreichbar. Der Umzug versucht es von selbst wieder.'
        => '%s cannot be reached right now. The move will try again by itself.',
    '%d Bilder umgezogen'
        => '%d pictures moved',
    '%d hängen'
        => '%d stuck',
    '%d Umzüge warten auf Platz oder eine Antwort'
        => '%d moves waiting for room or an answer',
    '%d Umzüge fertig'
        => '%d moves finished',
    'Während eines Umzugs lässt sich keine Cloud trennen.'
        => 'No cloud can be disconnected during a move.',
    'Es ist keine Cloud verbunden, aus der man umziehen könnte.'
        => 'No cloud is connected that one could move out of.',
    'In diese Cloud kann YASAIMAPP im Moment nicht umziehen.'
        => 'YASAIMAPP cannot move into this cloud at the moment.',
    'Der Umzug versucht es jetzt noch einmal.'
        => 'The move is trying again now.',
    'Ein Bild wird noch einmal versucht.'
        => 'One picture is being tried again.',
    '%d Bilder werden noch einmal versucht.'
        => '%d pictures are being tried again.',
    'Es läuft kein Umzug.'
        => 'No move is running.',
    'Umzug abgeschlossen. Alle Bilder liegen jetzt in %s.'
        => 'Move finished. All pictures are now in %s.',
    'Umzug abgeschlossen. %d Bilder sind in %s geblieben.'
        => 'Move finished. %d pictures stayed in %s.',
    '1 Belegfoto'
        => '1 voucher photo',
    '%d Belegfotos'
        => '%d voucher photos',
    '1 Eichbild'
        => '1 calibration image',
    '%d Eichbilder'
        => '%d calibration images',
    '1 Kopie von gemeinsamen Funden'
        => '1 copy from shared finds',
    '%d Kopien von gemeinsamen Funden'
        => '%d copies from shared finds',
    '%s und %s'
        => '%s and %s',
    'In %s liegt ein Bild von dir. Es bleibt dort liegen. Aber YASAIMAPP kann es nicht mehr zeigen, bis du %s wieder verbindest.'
        => 'One picture of yours lies in %s. It stays there. But YASAIMAPP can no longer show it until you connect %s again.',
    'In %s liegen %s von dir. Sie bleiben dort liegen. Aber YASAIMAPP kann sie nicht mehr zeigen, bis du %s wieder verbindest.'
        => 'In %s lie %s of yours. They stay there. But YASAIMAPP can no longer show them until you connect %s again.',
    'Willst du die Cloud wechseln? Dann nimm lieber „In andere Cloud umziehen …". So kommen deine Bilder mit.'
        => 'Do you want to change clouds? Then rather use "Move to another cloud …". That way your pictures come along.',
    'Am %s von %s hierher gewechselt. Was dort liegt, ist unberührt.'
        => 'On %s switched here from %s. What lies there is untouched.',
    'Am %s aus %s umgezogen: %d Bilder, %d sind dort geblieben. In %s liegt noch alles wie vorher. Den Ordner dort kannst du selbst löschen.'
        => 'On %s moved from %s: %d pictures, %d stayed there. Everything in %s is still as before. You can delete the folder there yourself.',
    'Am %s aus %s umgezogen: %d Bilder. In %s liegt noch alles wie vorher. Den Ordner dort kannst du selbst löschen.'
        => 'On %s moved from %s: %d pictures. Everything in %s is still as before. You can delete the folder there yourself.',
    '%d von 1 Bild ist umgezogen'
        => '%d of 1 picture has moved',
    '%d von %d Bildern sind umgezogen'
        => '%d of %d pictures have moved',
    'Umzug aus %s'
        => 'Move from %s',
    '%s ist voll. Der Umzug wartet. Mach dort Platz oder buch mehr Speicher dazu. Danach geht es von selbst weiter.'
        => '%s is full. The move is waiting. Make room there or book more storage. Then it goes on by itself.',
    'Bis dahin behält YASAIMAPP den Schlüssel zu %s. Deine Bilder bleiben zu sehen, egal wo sie gerade liegen. Neue Fotos warten so lange auf dem Server.'
        => 'Until then YASAIMAPP keeps the key to %s. Your pictures stay visible, wherever they lie right now. New photos wait on the server meanwhile.',
    'Bis dahin behält YASAIMAPP den Schlüssel zu %s. Deine Bilder bleiben zu sehen, egal wo sie gerade liegen.'
        => 'Until then YASAIMAPP keeps the key to %s. Your pictures stay visible, wherever they lie right now.',
    'Der Umzug läuft von selbst weiter, auch wenn du YASAIMAPP schließt. Deine Bilder bleiben währenddessen zu sehen, egal wo sie gerade liegen.'
        => 'The move goes on by itself, even when you close YASAIMAPP. Your pictures stay visible meanwhile, wherever they lie right now.',
    'Die Datensicherung legt in der ersten Nacht einmal alles in %s ab. Die alten Sicherungen bleiben in %s.'
        => 'On the first night the backup puts everything into %s once. The old backups stay in %s.',
    'Dieses Bild ist noch nicht angekommen:'
        => 'This picture has not arrived yet:',
    'Diese %d Bilder sind noch nicht angekommen:'
        => 'These %d pictures have not arrived yet:',
    'Warum'
        => 'Why',
    'YASAIMAPP versucht es mit ihnen einmal am Tag von selbst noch einmal.'
        => 'YASAIMAPP tries them again by itself once a day.',
    'Ein Bild bleibt dann in %s. YASAIMAPP löscht den Schlüssel zu %s und kann es danach nicht mehr zeigen.'
        => 'One picture then stays in %s. YASAIMAPP deletes the key to %s and can no longer show it afterwards.',
    '%d Bilder bleiben dann in %s. YASAIMAPP löscht den Schlüssel zu %s und kann sie danach nicht mehr zeigen.'
        => '%d pictures then stay in %s. YASAIMAPP deletes the key to %s and can no longer show them afterwards.',
    'Umzug so abschließen?'
        => 'Finish the move like this?',
    'Abschließen'
        => 'Finish',
    'Umzug so abschließen'
        => 'Finish the move like this',
    'Nochmal versuchen'
        => 'Try again',
    'In eine andere Cloud umziehen'
        => 'Move to another cloud',
    'In %s liegen keine Bilder von YASAIMAPP. Du wechselst nur die Cloud.'
        => 'There are no pictures from YASAIMAPP in %s. You only change clouds.',
    'Alle Bilder, die YASAIMAPP in %s abgelegt hat, ziehen in die neue Cloud um: %s, zusammen %s.'
        => 'All pictures YASAIMAPP put into %s move to the new cloud: %s, %s altogether.',
    'Alle Bilder, die YASAIMAPP in %s abgelegt hat, ziehen in die neue Cloud um: %s.'
        => 'All pictures YASAIMAPP put into %s move to the new cloud: %s.',
    'Du wechselst dabei die Cloud. Sobald du bei der neuen zugestimmt hast, ist sie deine Cloud. Neue Fotos und die Datensicherung gehen dann dorthin.'
        => 'In doing so you change clouds. As soon as you have agreed at the new one, it is your cloud. New photos and the backup then go there.',
    '%s ist danach nicht mehr verbunden. YASAIMAPP behält nur den Schlüssel, bis das letzte Bild drüben ist, und löscht ihn dann. In %s selbst wird nichts gelöscht.'
        => '%s is no longer connected afterwards. YASAIMAPP only keeps the key until the last picture is over there, and then deletes it. Nothing is deleted in %s itself.',
    'Der Umzug läuft von selbst. Du kannst YASAIMAPP dabei schließen. Deine Bilder bleiben die ganze Zeit zu sehen.'
        => 'The move runs by itself. You can close YASAIMAPP meanwhile. Your pictures stay visible the whole time.',
    'Wohin sollen deine Bilder?'
        => 'Where should your pictures go?',
    'Weiter zu %s'
        => 'On to %s',
    'In andere Cloud umziehen …'
        => 'Move to another cloud …',
    'Verbindung zu %s trennen?'
        => 'Disconnect %s?',
    'Trotzdem trennen'
        => 'Disconnect anyway',
    'Der Umzug ließ sich nicht beginnen: %s'
        => 'The move could not begin: %s',
    '%s ist jetzt deine Cloud. Deine Bilder ziehen jetzt aus %s um.'
        => '%s is now your cloud. Your pictures are moving over from %s now.',
    'Diese Adresse nimmt nur POST an.'
        => 'This address only takes POST.',
    'Fertig. %s ist jetzt deine Cloud.'
        => 'Done. %s is now your cloud.',
    'Umzug fertig. Das Bild liegt jetzt in %s.'
        => 'Move finished. The picture is now in %s.',
    'Umzug fertig. Alle %d Bilder liegen jetzt in %s.'
        => 'Move finished. All %d pictures are now in %s.',
    'Sobald du bei der neuen Cloud zugestimmt hast, ist sie deine Cloud. Neue Fotos und die Datensicherung gehen dann dorthin. %s ist danach nicht mehr verbunden. Was dort liegt, bleibt unberührt.'
        => 'As soon as you have agreed at the new cloud, it is your cloud. New photos and the backup then go there. %s is no longer connected afterwards. What lies there stays untouched.',
    'Beim Tragen dieses Bildes brach der Server ab — vielleicht ist es zu groß.'
        => 'The server broke off while carrying this picture — perhaps it is too big.',
    // Geräte: phone or tablet, and a name of one's own (28.09.2026)
    'Android-Handy' => 'Android phone',
    'Android-Tablet' => 'Android tablet',
    'Das braucht die Migration 0145 — sie ist noch nicht eingespielt.'
        => 'This needs migration 0145 — it has not been applied yet.',
    'Öffnest du diese Seite auf einem Gerät, steht bei seinem Eintrag „dieser Browser". Mit dem Stift gibst du ihm dann einen Namen, zum Beispiel „Tablet".'
        => 'When you open this page on a device, its entry says "this browser". With the pencil you then give it a name, for example "Tablet".',
    'Namen ändern' => 'Change name',
    'z. B. Tablet' => 'e.g. Tablet',
    'Den Namen siehst nur du. Lässt du das Feld leer, steht hier wieder „%s".'
        => 'Only you see the name. If you leave the field empty, "%s" stands here again.',
    // The note a shrunk picture carries in its EXIF and XMP (exifstamp.js; 26.09.2026)
    'Neu gespeichert durch YASAIMAPP am %s, ohne Verkleinerung (%d × %d Pixel). Originaldatei: %s.'
        => 'Saved anew by YASAIMAPP on %s, without shrinking (%d × %d pixels). Original file: %s.',
    'Verkleinert von %d × %d auf %d × %d Pixel (Faktor %s) durch YASAIMAPP am %s. Originaldatei: %s.'
        => 'Shrunk from %d × %d to %d × %d pixels (factor %s) by YASAIMAPP on %s. Original file: %s.',
    // The point window's facts table behind the ⓘ (fillDetailUneditable; 30.09.2026)
    'Angaben zum Fund' => 'About this find',
    'Nach deinem Muster aus den Einstellungen.' => 'By your pattern from the settings.',
    'Von YASAIMAPP vergeben. Ein eigenes Muster stellst du unter Einstellungen → Fundnummer ein.'
        => 'Given by YASAIMAPP. You set a pattern of your own under Einstellungen → Fundnummer.',
    '±%s m' => '±%s m',
    'So genau kannte das Gerät seinen Standort.' => 'That is how precisely the device knew its position.',
    'Ordner der Belegfotos: %s' => 'Folder of the voucher photos: %s',
    'Vom Bundesamt für Naturschutz.' => 'From the Federal Agency for Nature Conservation.',
    '§ besonders geschützt (Bundesartenschutzverordnung)' => '§ specially protected (Federal Species Protection Ordinance)',
    '§§ streng geschützt (Bundesartenschutzverordnung)' => '§§ strictly protected (Federal Species Protection Ordinance)',
    'Fotos oder Sequenzen' => 'Photos or sequences',
    'Dieser Punkt hat noch keinen Umriss.' => 'This point has no outline yet.',
    'Sequenzen gibt es nur bei Funden in einer Artenliste.' => 'Sequences exist only on finds in a species list.',
    // The Red List levels said in plain words (RL_CATEGORIES in filter.js; 01.10.2026)
    'Eine Aufsammlung bekommt keine Sequenz — sie gehört zum Fund der Art, die daraus gewachsen ist.'
        => 'A collection gets no sequence — it belongs to the find of the species that grew from it.',
    '%s (RL %s)' => '%s (RL %s)',
    'Rote Liste Deutschlands.' => 'Red List of Germany.',
    'Die Art ist in Deutschland verschwunden. Seit langer Zeit hat sie niemand mehr wild gefunden.'
        => 'The species has vanished from Germany. Nobody has found it in the wild for a long time.',
    'Die Art kann bald ganz verschwinden, wenn sich nichts ändert.'
        => 'The species may soon vanish altogether if nothing changes.',
    'Die Art ist stark zurückgegangen oder stark bedroht.'
        => 'The species has declined sharply or is under severe threat.',
    'Die Art ist deutlich zurückgegangen oder bedroht.'
        => 'The species has clearly declined or is under threat.',
    'Die Art ist gefährdet. Wie stark, weiß man noch nicht genau.'
        => 'The species is threatened. How badly is not yet known.',
    'Die Art kommt nur an sehr wenigen Stellen vor. Sie geht nicht zurück, aber ein einziger Eingriff kann sie dort auslöschen.'
        => 'The species occurs in very few places only. It is not declining, but a single intervention can wipe it out there.',
    'Die Art ist noch nicht gefährdet, aber schon deutlich zurückgegangen. Geht es so weiter, wird sie gefährdet.'
        => 'The species is not yet threatened, but it has already clearly declined. If this goes on, it will become threatened.',
    'Die Art ist nicht gefährdet.' => 'The species is not threatened.',
    'Über die Art weiß man zu wenig, um sie einzustufen.' => 'Too little is known about the species to assess it.',
    'Die Art wurde nicht eingestuft.' => 'The species has not been assessed.',
    // The warning before a micrograph goes up shrunk (index.php, map.js microShrinkWarning; 04.10.2026)
    'Mikrofotos werden verkleinert'
        => 'Micrographs are shrunk',
    'Ein Mikrofoto geht auf 2048 Pixel verkleinert in die Cloud. Die volle Größe des hochgeladenen Bildes ist damit verloren, außer du hast noch eine Sicherheitskopie auf der Festplatte und behältst diese. YASAIMAPP speichert sie nicht.'
        => 'A micrograph goes into the cloud shrunk to 2048 pixels. The full size of the uploaded picture is lost with that, unless you still have a backup copy on your hard disk and keep it. YASAIMAPP does not store it.',
    'Abschalten kannst du das unter <b>Einstellungen → Fotos → Maßstab-Vorlagen</b>.'
        => 'You can switch this off under <b>Settings → Photos → Scale presets</b>.',
    'Nicht mehr anzeigen'
        => 'Do not show again',
    // Administration → Cloud-Anbieter → Aufräumen: the sweep of every cloud (cloud_providers_content.php; 04.10.2026, back 22.11.2026)
    'Aufräumen'
        => 'Tidy up',
    'Manchmal bleibt in einer Cloud etwas liegen, das zu nichts mehr gehört: leere Ordner und Messwert-Dateien ohne ihr Bild.'
        => 'Now and then something is left lying in a cloud that belongs to nothing any more: empty folders and measurement files without their picture.',
    'Dieser Knopf räumt das weg, in den Clouds aller Nutzer. Es geht nur, was leer ist oder zu keinem Bild mehr gehört, und es landet im Papierkorb der Cloud. Bei Google Drive bleiben leere Ordner stehen.'
        => 'This button clears that away, in every user\'s cloud. Only what is empty or belongs to no picture any more goes, and it lands in the cloud\'s trash. At Google Drive empty folders stay.',
    'Alles war schon aufgeräumt.'
        => 'Everything was tidy already.',
    'Aufgeräumt: %d Messwert-Dateien ohne Bild und %d leere Ordner entfernt.'
        => 'Tidied up: %d measurement files without a picture and %d empty folders removed.',
    'Bis hierher %d Messwert-Dateien ohne Bild und %d leere Ordner entfernt. Es ist noch nicht alles durch. Drück noch einmal auf „Aufräumen".'
        => 'So far %d measurement files without a picture and %d empty folders removed. Not everything is done yet. Press "Tidy up" once more.',
    'Eine Cloud hat nicht geantwortet.'
        => 'One cloud did not answer.',
    '%d Clouds haben nicht geantwortet.'
        => '%d clouds did not answer.',

    // Administration → Cloud-Anbieter: the registrations at the providers (cloud_providers_content.php; 02.10.2026)
    'Cloud-Anbieter'
        => 'Cloud providers',
    'Hier meldest du YASAIMAPP bei den Cloud-Anbietern an. Das machst du einmal für die ganze Seite. Danach kann jeder im Reiter „Cloud" seine eigene Cloud verbinden.'
        => 'Here you register YASAIMAPP with the cloud providers. You do this once for the whole site. After that everyone can connect their own cloud in the "Cloud" tab.',
    'Du siehst dadurch nichts von den Clouds der anderen. Der Anbieter lernt nur YASAIMAPP kennen.'
        => 'You see nothing of the others\' clouds through this. The provider only gets to know YASAIMAPP.',
    'braucht keine Anmeldung, jeder trägt seinen eigenen Schlüssel ein'
        => 'needs no registration, everyone enters their own key',
    'eingerichtet in der config.php'
        => 'set up in config.php',
    'eingerichtet'
        => 'set up',
    'noch nicht eingerichtet'
        => 'not set up yet',
    '1 Nutzer verbunden'
        => '1 user connected',
    '%d Nutzer verbunden'
        => '%d users connected',
    'Einrichten'
        => 'Set up',
    '%s einrichten'
        => 'Set up %s',
    'Melde YASAIMAPP in %d Schritten bei %s an. Am Ende trägst du zwei Angaben hier unten ein.'
        => 'Register YASAIMAPP with %2$s in %1$d steps. At the end you enter two values down here.',
    'Eingerichtet in der <code>config.php</code>, mit der Client-ID <code>%s</code>. Lehnt %s beim Verbinden ab, vergleiche dort die Rücksprungadresse mit dieser:'
        => 'Set up in <code>config.php</code>, with the client ID <code>%s</code>. If %s refuses when connecting, compare the return address there with this one:',
    'Eingerichtet mit der Client-ID <code>%s</code>. Lehnt %s beim Verbinden ab, vergleiche dort die Rücksprungadresse mit dieser:'
        => 'Set up with the client ID <code>%s</code>. If %s refuses when connecting, compare the return address there with this one:',
    'Wie wurde das eingerichtet?'
        => 'How was this set up?',
    'Was darf YASAIMAPP in %s?'
        => 'What may YASAIMAPP do in %s?',
    'Ändern geht nur in der config.php auf dem Server.'
        => 'It can only be changed in config.php on the server.',
    'Diese Anmeldung steht in der config.php. Ändern geht nur dort.'
        => 'This registration is in config.php. It can only be changed there.',
    'Neue Angaben ersetzen die alten:'
        => 'New values replace the old ones:',
    '<strong>Projekt anlegen.</strong> Geh auf <a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">console.cloud.google.com</a> und lege ein Projekt an. Ein kostenloses Google-Konto genügt.'
        => '<strong>Create a project.</strong> Go to <a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">console.cloud.google.com</a> and create a project. A free Google account is enough.',
    '<strong>„Google Drive API" einschalten.</strong> Unter <em>APIs und Dienste → Bibliothek</em>. Ohne diesen Schritt lehnt Google später alles ab.'
        => '<strong>Switch on the "Google Drive API".</strong> Under <em>APIs &amp; Services → Library</em>. Without this step Google later refuses everything.',
    '<strong>Die App beschreiben und veröffentlichen.</strong> In der <em>Google Auth Platform</em> (im Menü links). Google fragt nach App-Name, Support-Adresse und Kontakt. Wähle als Zielgruppe <strong>„Extern"</strong>. Den App-Namen sehen die Nutzer später beim Verbinden.'
        => '<strong>Describe and publish the app.</strong> In the <em>Google Auth Platform</em> (in the menu on the left). Google asks for app name, support address and contact. Choose <strong>"External"</strong> as the audience. Users see the app name later when connecting.',
    'Unter <em>Branding → Autorisierte Domains</em> trägst du ein: <code>%s</code>'
        => 'Under <em>Branding → Authorised domains</em> you enter: <code>%s</code>',
    '<strong>Wichtig:</strong> Drück danach unter <em>Zielgruppe</em> auf „App veröffentlichen". Sonst reißt jede Verbindung nach sieben Tagen ab, ohne Meldung.'
        => '<strong>Important:</strong> then press "Publish app" under <em>Audience</em>. Otherwise every connection breaks off after seven days, without a message.',
    '<strong>Den Zugang anlegen.</strong> Unter <em>Clients → Client erstellen</em>, Typ <em>Webanwendung</em>. Als <em>autorisierte Weiterleitungs-URI</em> trägst du genau diese Adresse ein:'
        => '<strong>Create the access.</strong> Under <em>Clients → Create client</em>, type <em>Web application</em>. As the <em>authorised redirect URI</em> you enter exactly this address:',
    'Google zeigt dir danach die Client-ID und das Client-Secret. <strong>Das Secret siehst du nur dieses eine Mal.</strong> Kopiere es sofort.'
        => 'Google then shows you the client ID and the client secret. <strong>You see the secret this one time only.</strong> Copy it at once.',
    '<strong>App bei Microsoft anmelden.</strong> Geh auf <a href="https://entra.microsoft.com/" target="_blank" rel="noopener noreferrer">entra.microsoft.com</a> zu <em>App-Registrierungen → Neue Registrierung</em>. Ein kostenloses Microsoft-Konto genügt. Wähle bei den Kontotypen <strong>„Konten in einem beliebigen Organisationsverzeichnis und persönliche Microsoft-Konten"</strong>. Sonst lehnt Microsoft ein privates OneDrive ab.'
        => '<strong>Register the app with Microsoft.</strong> Go to <a href="https://entra.microsoft.com/" target="_blank" rel="noopener noreferrer">entra.microsoft.com</a>, <em>App registrations → New registration</em>. A free Microsoft account is enough. For the account types choose <strong>"Accounts in any organizational directory and personal Microsoft accounts"</strong>. Otherwise Microsoft refuses a private OneDrive.',
    '<strong>Die Rücksprungadresse eintragen.</strong> Bei der Registrierung oder später unter <em>Authentifizierung</em>, Plattform <em>Web</em>, genau diese Adresse:'
        => '<strong>Enter the return address.</strong> During registration or later under <em>Authentication</em>, platform <em>Web</em>, exactly this address:',
    '<strong>Ein Client-Secret anlegen.</strong> Unter <em>Zertifikate &amp; Geheimnisse → Neuer geheimer Clientschlüssel</em>. Den <em>Wert</em> zeigt Microsoft nur einmal. Kopiere ihn sofort.'
        => '<strong>Create a client secret.</strong> Under <em>Certificates &amp; secrets → New client secret</em>. Microsoft shows the <em>value</em> only once. Copy it at once.',
    '<strong>Wichtig:</strong> Wähle dabei die längste Laufzeit, 24 Monate. Microsoft schlägt sonst nur 6 Monate vor. Bis wann das Secret gilt, trägst du hier unten ein. Dann erinnert dich YASAIMAPP rechtzeitig.'
        => '<strong>Important:</strong> choose the longest term, 24 months. Otherwise Microsoft suggests only 6 months. You enter below until when the secret is valid. Then YASAIMAPP reminds you in time.',
    '<strong>Die Client-ID abschreiben.</strong> Sie heißt bei Microsoft <em>Anwendungs-ID (Client)</em> und steht auf der Übersicht der Registrierung.'
        => '<strong>Copy the client ID.</strong> Microsoft calls it <em>Application (client) ID</em>, and it stands on the overview of the registration.',
    '<strong>App bei pCloud anlegen.</strong> Melde dich auf <a href="https://docs.pcloud.com/my_apps/" target="_blank" rel="noopener noreferrer">docs.pcloud.com/my_apps</a> an und wähle „New app". Den Namen der App sehen die Nutzer später beim Verbinden.'
        => '<strong>Create an app at pCloud.</strong> Sign in at <a href="https://docs.pcloud.com/my_apps/" target="_blank" rel="noopener noreferrer">docs.pcloud.com/my_apps</a> and choose "New app". Users see the name of the app later when connecting.',
    '<strong>Die Rücksprungadresse eintragen.</strong> Als <em>Redirect URI</em> trägst du genau diese Adresse ein:'
        => '<strong>Enter the return address.</strong> As the <em>Redirect URI</em> you enter exactly this address:',
    'Ohne Schrägstrich am Ende. pCloud vergleicht Zeichen für Zeichen und lehnt sonst ab.'
        => 'Without a slash at the end. pCloud compares character for character and refuses otherwise.',
    '<strong>Client-ID und Client-Secret abschreiben.</strong> pCloud zeigt beides bei der App an.'
        => '<strong>Copy client ID and client secret.</strong> pCloud shows both with the app.',
    'Nur die Dateien, die YASAIMAPP selbst anlegt. Alles andere im Drive bleibt unsichtbar, auch Fotos und Dokumente. Genau das steht auch auf der Frage, die Google beim Verbinden stellt. Enger geht es bei Google nicht.'
        => 'Only the files YASAIMAPP creates itself. Everything else in the Drive stays invisible, photos and documents too. That is exactly what the question Google asks when connecting says. Google offers nothing narrower.',
    'Darum prüft Google die App nicht. Eine Warnung vor einer „nicht überprüften App" ist nicht zu erwarten.'
        => 'That is why Google does not review the app. A warning about an "unverified app" is not to be expected.',
    'Nur einen eigenen Ordner unter „Apps". Das übrige OneDrive bleibt unsichtbar. Du musst dafür keine Berechtigungen freigeben, und die Nutzer müssen nichts extra erlauben.'
        => 'Only a folder of its own under "Apps". The rest of OneDrive stays invisible. You do not have to grant any permissions for it, and users do not have to allow anything extra.',
    'Das geht noch nicht. Zuerst muss YASAIMAPP bei %s angemeldet werden. Das machst du als Verwalter unter Administration → Cloud-Anbieter.'
        => 'This does not work yet. First YASAIMAPP has to be registered with %s. As an administrator you do that under Administration → Cloud providers.',
    'Zu den Cloud-Anbietern'
        => 'To the cloud providers',
    'Das Client-Secret von %s ist am %s abgelaufen. Bis du ein neues einträgst, kann sich niemand mit %s verbinden, und auch bestehende Verbindungen reißen ab.'
        => 'The client secret of %s expired on %s. Until you enter a new one, nobody can connect to %s, and existing connections break off too.',
    'Das Client-Secret von %s läuft am %s ab. Leg vorher ein neues an und trag es unter Administration → Cloud-Anbieter ein. Sonst reißen alle Verbindungen zu %s ab.'
        => 'The client secret of %s expires on %s. Create a new one before then and enter it under Administration → Cloud providers. Otherwise every connection to %s breaks off.',
    'Das Datum stimmt nicht.'
        => 'The date is not right.',
    'Dieses Datum liegt schon in der Vergangenheit. Trag ein, bis wann das Secret gilt.'
        => 'This date is already in the past. Enter until when the secret is valid.',
    'Gespeichert. YASAIMAPP erinnert dich einen Monat vor dem %s.'
        => 'Saved. YASAIMAPP reminds you a month before %s.',
    'Trag noch ein, bis wann das Secret gilt. Dann erinnert dich YASAIMAPP einen Monat vorher.'
        => 'Also enter until when the secret is valid. Then YASAIMAPP reminds you a month before.',
    'Secret gilt bis %s'
        => 'secret valid until %s',
    'Ablaufdatum des Secrets fehlt'
        => 'expiry date of the secret missing',
    'Bis wann das Secret gilt, steht dort als <code>\'secret_expires\' => \'2028-10-02\'</code> neben der Client-ID. Dann erinnert dich YASAIMAPP einen Monat vorher.'
        => 'Until when the secret is valid goes there as <code>\'secret_expires\' => \'2028-10-02\'</code> beside the client ID. Then YASAIMAPP reminds you a month before.',
    'Secret gilt bis'
        => 'Secret valid until',
    'Das Datum zeigt dir Microsoft in der Liste der geheimen Clientschlüssel. Einen Monat vorher erinnert dich YASAIMAPP, auf der Karte und hier.'
        => 'Microsoft shows you the date in the list of client secrets. A month before, YASAIMAPP reminds you, on the map and here.',
    'Client-Secret erneuern'
        => 'Renew the client secret',
    // Mein Bereich → Kontakte, split off Gemeinsam (pages/administration.php; 02.10.2026)
    'Kontakte'
        => 'Contacts',
    // Gemeinsam → Freunde: the errors of friends.php, translated for the first time (02.10.2026)
    'Diese Person gibt es nicht mehr.'
        => 'This person no longer exists.',
    'Das bist du selbst.'
        => 'That is you yourself.',
    'Mit %s bist du schon befreundet.'
        => 'You are already friends with %s.',
    'Zwischen euch läuft schon eine Anfrage.'
        => 'There is already a request between you.',
    'Diese Anfrage gibt es nicht mehr.'
        => 'This request no longer exists.',
    'Diese Freundschaft gibt es nicht mehr.'
        => 'This friendship no longer exists.',
    'weitere werden geholt …'
        => 'fetching more …',
    // „Direkt teilen" — points shared directly with friends (point_hints.php, hints_content.php, map.js; 02.10.2026)
    'Alle Freunde aufklappen'
        => 'Unfold all friends',
    'Nur die Freunde, mit denen du ihn teilst'
        => 'Only the friends you share it with',
    'Aus „Direkt geteilt" entfernen?'
        => 'Remove from "Direkt geteilt"?',
    'Aus „Direkt geteilt" entfernt.'
        => 'Removed from "Direkt geteilt".',
    'Der Punkt ließ sich nicht aus „Direkt geteilt" entfernen: %s'
        => 'The point could not be removed from "Direkt geteilt": %s',
    'Der Punkt verschwindet aus „Direkt geteilt". Liegt er in einer Liste, die %s mit dir teilt, siehst du ihn dort weiter. Wieder direkt teilen kann ihn nur %s.'
        => 'The point disappears from "Direkt geteilt". If it lies in a list %s shares with you, you keep seeing it there. Only %s can share it with you directly again.',
    'Der Punkt verschwindet aus „Direkt geteilt". Du siehst ihn weiter in der Liste, die %s mit dir teilt.'
        => 'The point disappears from "Direkt geteilt". You keep seeing it in the list %s shares with you.',
    'Der Punkt verschwindet von deiner Karte. Wieder direkt teilen kann ihn nur %s.'
        => 'The point disappears from your map. Only %s can share it with you directly again.',
    '%s teilt diesen Punkt direkt mit dir. Entfernst du ihn aus „Direkt geteilt", bleibt er in der Liste, die %s mit dir teilt.'
        => '%s shares this point with you directly. If you remove it from "Direkt geteilt", it stays in the list %s shares with you.',
    '%s teilt diesen Punkt direkt mit dir. Entfernst du ihn aus „Direkt geteilt", verschwindet er von deiner Karte. Wieder direkt teilen kann ihn nur %s.'
        => '%s shares this point with you directly. If you remove it from "Direkt geteilt", it disappears from your map. Only %s can share it with you directly again.',
    'Einen Punkt teilt %s direkt mit dir.'
        => '%s shares one point with you directly.',
    '%d Punkte teilt %s direkt mit dir.'
        => '%d points are shared with you directly by %s.',
    'Die direkt geteilten Punkte kommen nicht zum Ende.'
        => 'The directly shared points do not come to an end.',
    'Der Server liefert keine direkt geteilten Punkte mehr, meldet aber offene.'
        => 'The server delivers no more directly shared points but reports open ones.',
    'Die direkt geteilten Punkte deiner Freunde konnten nicht geholt werden.'
        => 'The points your friends share with you directly could not be fetched.',
    'Hier stehen die Punkte, die du direkt mit einzelnen Freunden teilst, und die Punkte, die Freunde direkt mit dir teilen. Direkt teilen kannst du einen Punkt im Punktefenster: im Reiter „Karte" unter „Direkt teilen".'
        => 'Here are the points you share directly with single friends, and the points friends share directly with you. You share a point directly in the point window: in the "Karte" tab under "Direkt teilen".',
    'Du teilst noch keinen Punkt direkt mit einem Freund.'
        => 'You do not share any point directly with a friend yet.',
    'Noch kein Freund teilt einen Punkt direkt mit dir.'
        => 'No friend shares a point directly with you yet.',
    'Punkt von %s aus „Direkt geteilt" entfernen'
        => 'Remove the point from %s from "Direkt geteilt"',
    'Direkt teilen kannst du nur mit deinen Freunden.'
        => 'You can share directly only with your friends.',
    '„Direkt geteilt" ist schon vergeben: So heißt unter „Geteilt" die Sammelzeile für die Punkte, die Freunde direkt mit dir teilen.'
        => '"Direkt geteilt" is taken: under "Geteilt" that is the row that gathers the points friends share with you directly.',
    'Diesen direkt geteilten Punkt gibt es nicht mehr.'
        => 'This directly shared point no longer exists.',
    '%s sieht diesen Punkt dann nicht mehr unter „Direkt geteilt".'
        => '%s will no longer see this point under "Direkt geteilt".',
    '%s sieht diesen Punkt jetzt unter „Direkt geteilt".'
        => '%s now sees this point under "Direkt geteilt".',
    '%s sieht diesen Punkt nicht mehr unter „Direkt geteilt".'
        => '%s no longer sees this point under "Direkt geteilt".',
    'Dir direkt geteilte Punkte'
        => 'Points shared with you directly',
    'Direkt geteilt'
        => 'Shared directly',
    'Direkt teilen'
        => 'Share directly',
    'Direkt teilen braucht die Migration 0149 — sie ist noch nicht eingespielt.'
        => 'Sharing directly needs migration 0149 — it has not been applied yet.',
    'Erst nach dem Speichern'
        => 'Only after saving',
    'Freunde findest du in der Verwaltung unter Kontakte.'
        => 'You find friends in the administration under Contacts.',
    'Kein Freund angegeben.'
        => 'No friend given.',
    'Mit niemandem mehr teilen?'
        => 'Share with nobody any more?',
    'Nicht mehr teilen'
        => 'Stop sharing',
    'Von %s'
        => 'From %s',
    'hat den Punkt „%s" direkt mit dir geteilt. Du findest ihn bei den Listen unter „Geteilt" → „Direkt geteilt" → „%s".'
        => 'shared the point "%s" with you directly. You find it among the lists under "Geteilt" → "Direkt geteilt" → "%s".',
    'Alle Freunde darunter ein- oder ausblenden'
        => 'Show or hide all friends below',
    '%d von %d Freunden sichtbar'
        => '%d of %d friends visible',
    'Von dir direkt geteilte Punkte'
        => 'Points you share directly',
    'mit niemandem'
        => 'with nobody',
    'von %s · direkt geteilt'
        => 'by %s · shared directly',
    // „Aufnehmen" in full size into the cloud (settings Motivnamen, map.js captureFullSize; 03.10.2026)
    'Fotos in voller Größe auch über mobile Daten hochladen'
        => 'Upload photos in full size over mobile data too',
    'Mit einer verbundenen Cloud lädt „Aufnehmen" deine Fotos in voller Größe in die Cloud. Im WLAN immer, über mobile Daten nur mit diesem Haken. Ein Handyfoto hat 5 bis 10 MB – das geht von deinem Datenvolumen ab.'
        => 'With a connected cloud, "Aufnehmen" uploads your photos to the cloud in full size. On Wi-Fi always, over mobile data only with this tick. A phone photo has 5 to 10 MB – that comes off your data allowance.',
    'Ohne Haken geht über mobile Daten eine verkleinerte Fassung hoch, etwa ein halbes MB. Ohne Netz wird immer verkleinert, damit der Speicher auf dem Handy nicht vollläuft; das Foto geht hoch, sobald du wieder Netz hast. Ein iPhone verrät nicht, ob es im WLAN ist – dort entscheidet immer der Haken.'
        => 'Without the tick a shrunk version goes up over mobile data, about half a MB. Without reception it is always shrunk, so the phone\'s storage does not fill up; the photo goes up as soon as you have reception again. An iPhone does not say whether it is on Wi-Fi – there the tick always decides.',
    'Foto in voller Größe in der Cloud.'
        => 'Photo in full size in the cloud.',
    '%d Fotos in voller Größe in der Cloud.'
        => '%d photos in full size in the cloud.',
    'In voller Größe ging es nicht in die Cloud — das Foto geht verkleinert hoch.'
        => 'It did not reach the cloud in full size — the photo goes up shrunk.',
    'Mit Netz gehen sie in voller Größe direkt in deine Cloud. Im WLAN immer, über mobile Daten nur, wenn du unten bei deiner Cloud den Haken setzt.'
        => 'With reception they go straight into your cloud in full size. On Wi-Fi always, over mobile data only if you tick the box at your cloud below.',
    'Im WLAN gehen deine Fotos immer in voller Größe in die Cloud. Über mobile Daten nur mit diesem Haken. Ein Handyfoto hat 5 bis 10 MB – das geht von deinem Datenvolumen ab. Ein iPhone verrät nicht, ob es im WLAN ist – dort entscheidet immer der Haken.'
        => 'On Wi-Fi your photos always go into the cloud in full size. Over mobile data only with this tick. A phone photo is 5 to 10 MB — that comes off your data plan. An iPhone does not tell whether it is on Wi-Fi — there the tick always decides.',
    'Sonst rechnet YASAIMAPP sie kleiner, damit dein Handy unterwegs nicht vollläuft: Ohne Empfang warten die Fotos auf dem Gerät, bis du wieder Netz hast. Ohne Cloud werden es immer 1000 Pixel an der langen Seite, mit Cloud 2048.'
        => 'Otherwise YASAIMAPP makes them smaller, so your phone does not fill up on the way: without reception the photos wait on the device until you have reception again. Without a cloud it is always 1000 pixels on the long side, with a cloud 2048.',
    // The app's gallery originals (galleryoriginals.js, map.js captureInApp; 03.10.2026)
    'Das Foto geht in voller Größe in die Cloud.'
        => 'The photo goes into the cloud in full size.',
    '%d Fotos gehen in voller Größe in die Cloud.'
        => '%d photos go into the cloud in full size.',
    'Gespeichert. Das Original liegt in der Galerie und geht in die Cloud, sobald du Netz hast.'
        => 'Saved. The original lies in the gallery and goes into the cloud as soon as you have reception.',
    'Gespeichert. Das Original liegt in der Galerie und geht im WLAN in die Cloud.'
        => 'Saved. The original lies in the gallery and goes into the cloud on Wi-Fi.',
    // Direkt teilen by co-owners, and the holder's chat line (point_hints.php, map.js; 06.10.2026)
    'Zurücknehmen kann das nur, wer es geteilt hat, oder wem der Punkt gehört.'
        => 'Only whoever shared it, or whoever the point belongs to, can take this back.',
    'hat deinen Punkt „%s" direkt mit %s geteilt. Zurücknehmen kannst du das in der Verwaltung unter „Gemeinsam" → „Direkt geteilt".'
        => 'shared your point "%s" directly with %s. You can take that back in the administration under "Gemeinsam" → "Direkt geteilt".',
    'geteilt von %s'
        => 'shared by %s',
    'Außerdem mit 1 Person über einen Mitbesitzer.'
        => 'Also with 1 person through a co-owner.',
    'Außerdem mit %d Personen über Mitbesitzer.'
        => 'Also with %d people through co-owners.',
    // Listen teilen — one share per list, rights and filter per recipient (list_shares.php, migration 0160; 09.10.2026)
    'Listen teilen braucht die Migration 0160 — sie ist noch nicht eingespielt.'
        => 'Sharing lists needs migration 0160 — it has not been applied yet.',
    'Das braucht die Migration 0160 — sie ist noch nicht eingespielt.'
        => 'This needs migration 0160 — it has not been applied yet.',
    'Diese Freigabe stellt der Besitzer an seiner Liste ein, im Fenster „Liste bearbeiten".'
        => 'This share is set by its owner at the list, in the window “Edit list”.',
    'Geteilt wird immer die ganze Liste mit allen Unterlisten.'
        => 'A list is always shared whole, with all its sublists.',
    'Teilen kannst du nur mit Freunden.'
        => 'You can only share with friends.',
    'Teilen kannst du nur mit eigenen Arbeitsgruppen.'
        => 'You can only share with your own working groups.',
    'Unbekannte Farbe.'
        => 'Unknown colour.',
    'Unbekannte Form.'
        => 'Unknown shape.',
    'Suchwort „%s“'
        => 'search word “%s”',
    'ohne Suchwort „%s“'
        => 'without search word “%s”',
    'ein Gebiet'
        => 'one area',
    '%d Gebiete'
        => '%d areas',
    'MTB %s'
        => 'map sheet %s',
    '%d bis %d m'
        => '%d to %d m',
    'Rote Liste %s'
        => 'Red List %s',
    'arbeitet nicht mehr in „%s" mit — der eigene Punkt liegt jetzt in einer eigenen Liste „%s"'
        => 'no longer works in “%s” — their own point now lies in a list of their own, “%s”',
    'arbeitet nicht mehr in „%s" mit — die eigenen %d Punkte liegen jetzt in einer eigenen Liste „%s"'
        => 'no longer works in “%s” — their own %d points now lie in a list of their own, “%s”',
    'hat die gemeinsame Liste „%1$s" verlassen. Da %2$s der Listenadministrator war, gehen diese Rechte nun an %3$s über. %3$s kann jetzt Freunde dazunehmen und herausnehmen und bestimmen, was jeder in der Liste darf.'
        => 'has left the joint list “%1$s”. As %2$s was the list administrator, these rights now pass to %3$s. %3$s can now add and remove friends and decide what everyone may do in the list.',
    'hat ein Bild von %s an „%s" gelöscht'
        => 'deleted a picture by %s at “%s”',
    // Freigaben umstellen — the conversion of the old boxes (share_convert_content.php; 09.10.2026)
    '%d Nachrichten gehen mit zu „%s"'
        => '%d messages move along to “%s”',
    '%d Nachrichten gehen verloren — die Liste trifft hier auf Leute aus einem anderen Kasten, die den Chat nie gesehen haben.'
        => '%d messages are lost — the list meets people from another box here who never saw this chat.',
    'Alle alten Freigaben werden jetzt umgestellt. Wer oben unter „sieht sie nicht mehr" steht, verliert die Sicht auf diese Listen. Das lässt sich nicht rückgängig machen.'
        => 'All old shares are converted now. Whoever is listed above under “no longer sees it” loses sight of these lists. This cannot be undone.',
    'Alter Kasten'
        => 'Old box',
    'Bei %s galten zwei verschiedene Filter — übernommen wird der erste.'
        => 'Two different filters applied to %s — the first one is kept.',
    'Bilder anhängen'
        => 'attach pictures',
    'Bilder bearbeiten'
        => 'edit pictures',
    'Bitte noch nicht drücken: Das Teilen im Fenster „Liste bearbeiten" und die neue Seitenleiste kommen erst. Bis dahin kannst du hier nur nachsehen, was die Umstellung tun wird.'
        => 'Please do not press yet: sharing in the window “Edit list” and the new sidebar are still to come. Until then you can only look here at what the conversion will do.',
    'Die Umstellung ist gescheitert, es wurde nichts geändert: %s'
        => 'The conversion failed, nothing was changed: %s',
    'Die alten Kästen und ihr Chat'
        => 'The old boxes and their chat',
    'Dieser Knopf macht aus jeder Liste in einem alten Kasten eine geteilte Liste ihres Besitzers. Die Leute aus dem Kasten bekommen sie mit denselben Rechten und demselben Filter. Wer mit dem Besitzer der Liste nicht befreundet ist, sieht sie danach nicht mehr — das steht unten bei jeder Liste.'
        => 'This button turns every list in an old box into a shared list of its owner. The people from the box get it with the same rights and the same filter. Whoever is not friends with the list’s owner no longer sees it afterwards — that is shown below for every list.',
    'Es gibt keine alten Freigaben mehr. Alles ist umgestellt.'
        => 'There are no old shares left. Everything is converted.',
    'Filter: %s'
        => 'Filter: %s',
    'Freigaben umstellen'
        => 'Convert shares',
    'Früher war eine Freigabe ein Kasten: Wer darin war, konnte eigene Listen hineinhängen, und der Besitzer des Kastens bestimmte den Filter für alle. Jetzt teilt jeder nur seine eigene Liste und stellt für jeden Freund einzeln ein, was er sieht und darf.'
        => 'A share used to be a box: whoever was in it could hang their own lists into it, and the box’s owner set the filter for everyone. Now everyone shares only their own list and sets for each friend what they see and may do.',
    'Gruppe „%s"'
        => 'group “%s”',
    'Jetzt umstellen'
        => 'Convert now',
    'Suchwörter suchten bisher nur im Titel, jetzt in allen Feldern des Punkts.'
        => 'Search words used to look only in the title, now in all fields of the point.',
    'Umgestellt: %d alte Freigaben wurden zu %d geteilten Listen.'
        => 'Converted: %d old shares became %d shared lists.',
    'Was daraus wird'
        => 'What they become',
    'Wer sie danach sieht'
        => 'Who sees it afterwards',
    'Wer sie nicht mehr sieht'
        => 'Who no longer sees it',
    'Zeitraum: Punkte ohne Fundzeit zählen jetzt mit dem Tag, an dem sie angelegt wurden.'
        => 'Time range: points without a find time now count with the day they were created.',
    'alle Felder'
        => 'all fields',
    'arbeitet mit, darf alles'
        => 'works in it, may do everything',
    'darf: %s'
        => 'may: %s',
    'kein Chat'
        => 'no chat',
    'leer'
        => 'empty',
    'niemand'
        => 'nobody',
    'nimmt %d eigene Punkte mit'
        => 'takes %d own points along',
    'nur ansehen'
        => 'view only',
    'nur diese Unterliste'
        => 'only this sublist',
    '%s (von %s)'
        => '%s (by %s)',
    // Listen teilen, step 2 — the share section, the gear and the filter (listshare.js, sharefilter.js; 09.10.2026)
    '%s (%d Personen)'
        => '%s (%d people)',
    '%s (1 Person)'
        => '%s (1 person)',
    '%s darf …'
        => '%s may …',
    '%s herausnehmen?'
        => 'Take %s out?',
    '%s setzt eigene Punkte in diese Liste. Die gehen dann zurück zu %s, in eine eigene Liste.'
        => '%s sets their own points into this list. They then go back to %s, into a list of their own.',
    'Alles erlauben'
        => 'Allow everything',
    'Bilder an Punkte hängen'
        => 'attach pictures to points',
    'Bilder löschen, auch deine'
        => 'delete pictures, yours too',
    'Dann arbeitet ihr gemeinsam an der Liste. Was %s hineinsetzt, gehört weiter %s. Die Liste steht dann unter „Gemeinsame Listen", und ihr habt einen Chat dazu.'
        => 'Then you work on the list together. What %s sets into it still belongs to %s. The list then stands under “Joint lists”, and you have a chat for it.',
    'Deine Liste hat %d Punkte. %s darf sie danach alle ändern.'
        => 'Your list has %d points. %s may then change all of them.',
    'Die Liste ist gespeichert, das Teilen nicht: %s'
        => 'The list is saved, the sharing is not: %s',
    'Die eigenen Bilder darf jeder immer löschen.'
        => 'Everyone may always delete their own pictures.',
    'Die ganze Liste „%s"'
        => 'The whole list “%s”',
    'Erlauben'
        => 'Allow',
    'Filter aufheben'
        => 'Remove filter',
    'Filter für %s'
        => 'Filter for %s',
    'Filter leeren'
        => 'Clear filter',
    'Gebiete aus dem Filter der Seitenleiste übernehmen'
        => 'Take the areas from the sidebar’s filter',
    'Immer an. Messen kann man auf jedem Bild, das man sieht — aber nur für sich.'
        => 'Always on. One can measure on every picture one sees — but only for oneself.',
    'Kein Filter: %s sieht alle Punkte der Liste.'
        => 'No filter: %s sees every point of the list.',
    'Liste geändert, Teilen gespeichert.'
        => 'List changed, sharing saved.',
    'Liste geändert, Teilen gespeichert. %d Punkte sind zurück bei denen, die nicht mehr mitarbeiten.'
        => 'List changed, sharing saved. %d points are back with those who no longer work in it.',
    'Lädt …'
        => 'Loading …',
    'Mit %s teilen'
        => 'Share with %s',
    'Nur die Unterliste „%s"'
        => 'Only the sublist “%s”',
    'Nur du holst sie zurück.'
        => 'Only you can bring them back.',
    'Ohne diesen Haken misst %s nur für sich. An deinen Bildern ändert sich dann nichts.'
        => 'Without this tick %s measures only for themselves. Nothing changes on your pictures then.',
    'Punkte in deinen Papierkorb legen'
        => 'put points into your trash',
    'Punkte verschieben und ihren Umriss ändern'
        => 'move points and change their outline',
    'Suchwort …'
        => 'Search word …',
    'Teilen braucht eine Verbindung. Versuch es noch einmal, wenn du online bist.'
        => 'Sharing needs a connection. Try again when you are online.',
    'Teilen kannst du, sobald die Liste gespeichert ist.'
        => 'You can share once the list is saved.',
    'Was %s sieht und darf'
        => 'What %s sees and may do',
    'Was du teilst'
        => 'What you share',
    'Wer ein Bild an deinen Fund hängt, wird Mitbesitzer. Deine Bilder dieses Fundes kommen dann auch in die Cloud von %s und bleiben dort, auch wenn eure Freundschaft endet.'
        => 'Whoever attaches a picture to your find becomes a co-owner. Your pictures of this find then also go into the cloud of %s and stay there, even if your friendship ends.',
    'Wirklich so viel erlauben?'
        => 'Really allow this much?',
    'Zu sehen sind dann nur die Punkte, auf die alles zutrifft, was du hier einstellst. Ohne Einstellung sind alle Punkte der Liste zu sehen.'
        => 'Only the points that match everything you set here will be visible. Without a setting, every point of the list is visible.',
    'darf alles'
        => 'may do everything',
    'darf nichts ändern'
        => 'may change nothing',
    'das Feld „%s" ausfüllen'
        => 'fill in the field “%s”',
    'deine Bilder vermessen: Maßstab, Messungen und Bildname'
        => 'measure your pictures: scale, measures and picture name',
    // Foreign edits of a picture (includes/picture_edits.php, migration 0194, 11.11.2026).
    'Bilder vermessen'
        => 'measure pictures',
    'deine Bilder bearbeiten: eigene Bearbeitungen anlegen'
        => 'edit your pictures: add edits of their own',
    '%s legt eigene Bearbeitungen neben deine. Deine Bearbeitungen bleiben, wie sie sind. Gültig machen kannst nur du.'
        => '%s adds edits of their own beside yours. Your edits stay as they are. Only you can make one valid.',
    'Bild nicht gefunden.'
        => 'Picture not found.',
    'Dieses Bild darfst du nicht bearbeiten.'
        => 'You may not edit this picture.',
    'Diese Bearbeitung gibt es nicht mehr.'
        => 'This edit no longer exists.',
    'Mehr als %d Bearbeitungen je Bild gehen nicht.'
        => 'No more than %d edits per picture.',
    'Fremde Bearbeitungen brauchen die Migration 0194 — sie ist noch nicht eingespielt.'
        => 'Edits by others need migration 0194 — it has not been applied yet.',
    'Die Bearbeitungen eines Bildes ändert nur, wer es aufgenommen hat.'
        => 'Only whoever took a picture changes its edits.',
    'Bearbeitung von %s'
        => 'Edit by %s',
    'Bearbeitung %d angelegt — eine Kopie der Bearbeitung von %s.'
        => 'Edit %d created — a copy of the edit by %s.',
    'Deine Bearbeitung %d'
        => 'Your edit %d',
    'Bearbeitung %d von %s'
        => 'Edit %d by %s',
    'Die Bearbeitung ließ sich nicht anlegen.'
        => 'The edit could not be created.',
    'Deine Bearbeitung %d angelegt — eine Kopie der offenen.'
        => 'Your edit %d created — a copy of the open one.',
    'Deine Bearbeitung %d löschen'
        => 'Delete your edit %d',
    'Die Einstellungen deiner Bearbeitung %d gehen verloren. Das Bild und die anderen Bearbeitungen bleiben.'
        => 'The settings of your edit %d are lost. The picture and the other edits stay.',
    'Die Bearbeitung ließ sich nicht löschen.'
        => 'The edit could not be deleted.',
    'Deine Bearbeitung %d gelöscht.'
        => 'Your edit %d deleted.',
    'Höchstens %d Bearbeitungen — lösche erst eine von deinen.'
        => 'At most %d edits — delete one of yours first.',
    'Die Bearbeitung von %s gilt jetzt überall — als deine Bearbeitung %d.'
        => 'The edit by %s now counts everywhere — as your edit %d.',
    'Das ließ sich nicht umstellen.'
        => 'That could not be switched.',
    'Wer dieses Bild sehen darf, darf es jetzt auch bearbeiten.'
        => 'Whoever may see this picture may now edit it too.',
    'Andere dürfen dieses Bild nicht mehr bearbeiten. Ihre Bearbeitungen bleiben.'
        => 'Others may no longer edit this picture. Their edits stay.',
    'der Bearbeitung %d von %s'
        => 'of edit %d by %s',
    'der Bearbeitung von %s'
        => 'of the edit by %s',
    'von Bearbeitung %d'
        => 'of edit %d',
    'Bearbeitung von %s — gilt überall (Taste V wechselt)'
        => 'Edit by %s — counts everywhere (key V switches)',
    'Deine Bearbeitung %d (Taste V wechselt)'
        => 'Your edit %d (key V switches)',
    'Kopie %s'
        => 'Copy %s',
    'Von vorne anfangen'
        => 'Start from scratch',
    'Bearbeitung %d angelegt — sie beginnt beim Original.'
        => 'Edit %d created — it starts from the original.',
    'Höchstens %d Bearbeitungen — lösche erst eine.'
        => 'At most %d edits — delete one first.',
    'Dieser Rechner hat das Bild beim Verkleinern schwarz gerechnet. Es geht deshalb in voller Größe hoch.'
        => 'This computer turned the picture black while shrinking it. It goes up in full size instead.',
    'Dieser Rechner hat das Foto beim Verkleinern schwarz gerechnet. Es wurde nicht übernommen.'
        => 'This computer turned the photo black while shrinking it. It was not taken over.',
    'Der Server hat die Ablage nicht geöffnet (%s).'
        => 'The server did not open the way into the cloud (%s).',
    'Das Original'
        => 'The original',
    'Das Original gilt jetzt überall.'
        => 'The original now counts everywhere.',
    'Das Original — gilt überall (Taste V wechselt)'
        => 'The original — counts everywhere (key V switches)',
    'Das Original (Taste V wechselt)'
        => 'The original (key V switches)',
    'Das Original gilt dann überall: Kacheln, Tafeln, Fundbericht'
        => 'The original then counts everywhere: tiles, plates, find report',
    'Ändere etwas am Bild — daraus wird Bearbeitung 1'
        => 'Change something in the picture — that becomes edit 1',
    'Makro oder Mikro?'
        => 'Macro or micro?',
    '„%s" kommt in deine Motivnamen. In welche Spalte gehört er?'
        => '“%s” joins your picture names. Which column does it belong in?',
    'Ein Foto vom Pilz oder von seinen Teilen, ohne Mikroskop.'
        => 'A photo of the fungus or its parts, without a microscope.',
    'Ein Bild durch das Mikroskop.'
        => 'A picture through the microscope.',
    'Der Name ließ sich nicht in deine Motivnamen aufnehmen.'
        => 'The name could not be added to your picture names.',
    'Welcher Name, und Makro oder Mikro?'
        => 'Which name, and macro or micro?',
    '„%s" aus deinen Motivnamen löschen'
        => 'Delete “%s” from your picture names',
    'Motivnamen löschen'
        => 'Delete picture name',
    '„%s" steht dann nicht mehr in deinen Motivnamen. Bilder, die so heißen, behalten ihren Namen.'
        => '“%s” will no longer be among your picture names. Pictures with that name keep it.',
    '„%s" ist aus deinen Motivnamen gelöscht.'
        => '“%s” is deleted from your picture names.',
    'Der Name ließ sich nicht löschen.'
        => 'The name could not be deleted.',
    'Feste Motivnamen bleiben.'
        => 'Built-in picture names stay.',
    'Deine Bearbeitung %d angelegt — sie beginnt von vorne.'
        => 'Your edit %d created — it starts from scratch.',
    'Bearbeitung %d von %s (Taste V wechselt)'
        => 'Edit %d by %s (key V switches)',
    'Andere dürfen dieses Bild bearbeiten — klicken schaltet es aus'
        => 'Others may edit this picture — click to switch it off',
    'Andere dürfen dieses Bild bearbeiten: aus — klicken schaltet es ein'
        => 'Others may edit this picture: off — click to switch it on',
    'Andere dürfen bearbeiten'
        => 'Others may edit',
    'Die Bearbeitung von %s wird deine und gilt dann überall: Kacheln, Tafeln, Fundbericht'
        => 'The edit by %s becomes yours and then counts everywhere: tiles, plates, find report',
    'Nur zum Anschauen. Mit + legst du eine eigene Bearbeitung an.'
        => 'Only to look at. With + you create an edit of your own.',
    'Die Bearbeitung von %s ist nur zum Anschauen. „Gültig machen" übernimmt sie als deine.'
        => 'The edit by %s is only to look at. “Make valid” takes it over as yours.',
    'hat eine eigene Bearbeitung von deinem Bild „%s" beim Fund „%s" angelegt. Du findest sie im Bildfenster unter „Bearbeiten". Deine Bearbeitungen bleiben, wie sie sind.'
        => 'added an edit of their own to your picture “%s” of the find “%s”. You find it in the picture window under “Edit”. Your edits stay as they are.',
    'hat eine eigene Bearbeitung zum Bild „%s" beim Fund „%s" angelegt. Die Bearbeitungen von %s bleiben, wie sie sind.'
        => 'added an edit of their own to the picture “%s” of the find “%s”. The edits by %s stay as they are.',
    'den Titel ändern'
        => 'change the title',
    'die Notiz ändern'
        => 'change the note',
    'die Punkte sehen, mit allen Bildern'
        => 'see the points, with all pictures',
    'eigene Punkte in die Liste setzen'
        => 'set their own points into the list',
    'erlaubt'
        => 'allowed',
    'setzt eigene Punkte'
        => 'sets own points',
    'setzt eigene Punkte, darf alles'
        => 'sets own points, may do everything',
    'sieht alle Punkte'
        => 'sees every point',
    '„%s" für %s'
        => '“%s” for %s',
    // Listen teilen, step 3 — the sidebar, the look window, the administrator's window (map.js; 09.10.2026)
    '%s erlaubt dir: %s.'
        => '%s allows you to: %s.',
    '%s zeigt dir die Liste nur zum Ansehen.'
        => '%s shows you the list to look at only.',
    'Das ging nicht: %s'
        => 'That did not work: %s',
    'Die Liste verschwindet bei dir. Bei %s läuft sie weiter. Punkte, die du hineingesetzt hast, bekommst du in eine eigene Liste zurück. Wieder sehen kannst du sie nur, wenn %s sie dir noch einmal teilt.'
        => 'The list disappears for you. It goes on at %s’s. Points you set into it come back to you in a list of your own. You can only see it again if %s shares it with you once more.',
    'Du bist jetzt Listenadministrator von „%s"'
        => 'You are now the list administrator of “%s”',
    'Farbe und Form von „%s"'
        => 'Colour and shape of “%s”',
    'In der Hilfe nachlesen'
        => 'Read up in the help',
    'Nicht mehr sehen'
        => 'Stop seeing it',
    'Punkte in den Papierkorb legen'
        => 'put points into the trash',
    'Wer die Liste bisher verwaltet hat, ist gegangen. Weil du am längsten mitarbeitest, verwaltest jetzt du sie: Du bestimmst, wer sie sieht und was jeder darf. Das stellst du im Fenster „Liste bearbeiten" unter „Teilen" ein. Wer schon mitgearbeitet hat, bleibt dabei.'
        => 'Whoever managed the list so far has left. Because you have worked in it longest, you manage it now: you decide who sees it and what everyone may do. You set that in the window “Edit list” under “Share”. Everyone who already worked in it stays.',
    'Zur Liste'
        => 'To the list',
    'die Felder %s ausfüllen'
        => 'fill in the fields %s',
    'eigene Punkte hineinsetzen'
        => 'set your own points into it',
    '„%s" nicht mehr sehen?'
        => 'Stop seeing “%s”?',
    '„%s" von %s'
        => '“%s” by %s',
    // Listen teilen, step 4 — Gemeinsam → Geteilt (given_content.php; 09.10.2026)
    'Alte Freigaben'
        => 'Old shares',
    'Du siehst die Liste nicht mehr. Punkte, die du hineingesetzt hattest, liegen jetzt in einer eigenen Liste.'
        => 'You no longer see the list. Points you had set into it now lie in a list of your own.',
    'Du teilst noch keine Liste.'
        => 'You do not share any list yet.',
    'Hier siehst du, welche Listen du teilst und welche andere mit dir teilen. Wer eine Liste sieht und was er darf, stellst du an der Liste selbst ein: auf der Karte im Fenster „Liste bearbeiten", unter „Teilen". Der Stift führt dorthin.'
        => 'Here you see which lists you share and which others share with you. Who sees a list and what they may do you set at the list itself: on the map in the window “Edit list”, under “Share”. The pencil leads there.',
    'In der Liste ändern'
        => 'Change at the list',
    'Mit dir geteilt'
        => 'Shared with you',
    'Mit wem'
        => 'With whom',
    'Nach Freunden'
        => 'By friends',
    'Nach Listen'
        => 'By lists',
    'Noch teilt niemand eine Liste mit dir.'
        => 'Nobody shares a list with you yet.',
    'Von dir geteilt'
        => 'Shared by you',
    'du siehst alle Punkte'
        => 'you see every point',
    'du siehst: %s'
        => 'you see: %s',
    'oder'
        => 'or',
    '„%s" in der Liste ändern'
        => 'Change “%s” at the list',
    '„%s" nicht mehr sehen'
        => 'Stop seeing “%s”',
    '„%s" verschwindet bei dir. Bei %s läuft die Liste weiter. Punkte, die du hineingesetzt hast, bekommst du in eine eigene Liste zurück.'
        => '“%s” disappears for you. The list goes on at %s’s. Points you set into it come back to you in a list of your own.',
    // Directly shared finds under „Von <Name>" (map.js; 09.10.2026)
    'Alles darunter ein- oder ausblenden'
        => 'Show or hide everything below',
    '%s teilt diesen Punkt direkt mit dir. Ein Klick öffnet ihn.'
        => '%s shares this point directly with you. A click opens it.',
    // Directly shared finds: mark, show, remove (map.js; 09.10.2026)
    'Auf der Karte zeigen'
        => 'Show on the map',
    'Die direkte Teilung ließ sich nicht entfernen: %s'
        => 'The direct share could not be removed: %s',
    'Direkte Teilung entfernen'
        => 'Remove direct share',
    'Direkte Teilung entfernen?'
        => 'Remove the direct share?',
    'Direkte Teilung entfernt.'
        => 'Direct share removed.',
    'Direkte Teilung von „%s" entfernen'
        => 'Remove the direct share of “%s”',
    'direkt geteilt'
        => 'shared directly',
    '„%s" auf der Karte zeigen'
        => 'Show “%s” on the map',
    '„%s" steht dann nicht mehr einzeln unter „Von %s". Du siehst den Punkt weiter in der Liste, die %s mit dir teilt.'
        => '“%s” then no longer stands on its own under “From %s”. You still see the point in the list %s shares with you.',
    '„%s" verschwindet von deiner Karte. Wieder direkt teilen kann ihn nur %s.'
        => '“%s” disappears from your map. Only %s can share it directly again.',
    // „Richtung zeigen" at a directly shared find's row (map.js; 09.10.2026)
    'Richtung zu „%s" zeigen'
        => 'Show the way to “%s”',
    '%s teilt diesen Punkt direkt mit dir.'
        => '%s shares this point directly with you.',
    // Species links — „Internet-Links" (species_links.php, weblinks.js; 10.10.2026)
    'Artlinks'
        => 'Species links',
    'Bitte wähle die Datei aus.'
        => 'Please choose the file.',
    'Das Einspielen ist gescheitert, es wurde nichts geändert: %s'
        => 'The import failed, nothing was changed: %s',
    'Die Artlinks brauchen die Migration 0166 — sie ist noch nicht eingespielt.'
        => 'Species links need migration 0166 — it has not been applied yet.',
    'Die Datei ist größer, als der Server annimmt (%s). Pack sie als .gz und versuch es noch einmal.'
        => 'The file is larger than the server accepts (%s). Pack it as .gz and try again.',
    'Die Datei ist leer.'
        => 'The file is empty.',
    'Die Datei lässt sich nicht öffnen.'
        => 'The file cannot be opened.',
    'Die Links ließen sich nicht laden: %s'
        => 'The links could not be loaded: %s',
    'Eingespielt: %d Links, %d Zeilen übersprungen.'
        => 'Imported: %d links, %d rows skipped.',
    'Erwartet wird die Exportdatei von Pilz-Link (CSV, auch als .gz). Der Server nimmt Dateien bis %s an.'
        => 'Expected is the export file from Pilz-Link (CSV, also as .gz). The server accepts files up to %s.',
    'Hier kommen die Links zu den Artporträts und Verbreitungskarten hinein, die Pilz-Link auf rund dreißig Seiten gesammelt hat. Dazu gehören zum Beispiel Artsdatabanken, Mushroom Observer, 123pilze und Taunuspilz. Sie erscheinen in der Bestimmung bei einer Art hinter dem Knopf „Internet-Links".'
        => 'This is where the links to species portraits and distribution maps go that Pilz-Link gathered on some thirty sites — for example Artsdatabanken, Mushroom Observer, 123pilze and Taunuspilz. They appear in identification at a species behind the button “Internet links”.',
    'In der Kopfzeile fehlt die Spalte „%s".'
        => 'The header line lacks the column “%s”.',
    'Internet-Links'
        => 'Internet links',
    'Jedes Einspielen ersetzt alles, was vorher da war.'
        => 'Every import replaces everything that was there before.',
    'Keine Art angegeben.'
        => 'No species given.',
    'Links'
        => 'Links',
    'Noch sind keine Links eingespielt.'
        => 'No links have been imported yet.',
    'Zu dieser Art sind keine Links bekannt.'
        => 'No links are known for this species.',
    'Suche'
        => 'Search',
    'Verbreitungskarte'
        => 'Distribution map',
    'Porträt'
        => 'Portrait',
    'Wissenschaftlich'
        => 'Scientific',
    'DNA'
        => 'DNA',
    'Weitere'
        => 'More',
    // Species links import per source (web_links_content.php; 10.10.2026)
    'Eingespielt: %d Links aus %d Quellen, %d Zeilen übersprungen.'
        => 'Imported: %d links from %d sources, %d rows skipped.',
    'Eine Datei ersetzt die Links der Seiten, die in ihr stehen. Alle anderen Seiten behalten ihre Links. So lässt sich auch ein Nachtrag mit einzelnen Seiten einspielen.'
        => 'A file replaces the links of the sites it contains. All other sites keep their links. This way an addendum with single sites can be imported too.',
    // Plates — A4 compositions of a find's pictures (plates.js, plate_window.php, plates.php; 06.10.2026)
    '%d pt'
        => '%d pt',
    'Anordnung'
        => 'Arrangement',
    'Aus dem Fund'
        => 'From the find',
    'Bild hierher ziehen'
        => 'Drag a picture here',
    'Bild nicht verfügbar'
        => 'Picture not available',
    'Bilder des Funds'
        => 'Pictures of the find',
    'Die Seite %d und was auf ihr steht, gehen von der Tafel. Die Bilder selbst bleiben.'
        => 'Page %d and what stands on it leave the plate. The pictures themselves stay.',
    'Die Tafel'
        => 'The plate',
    'Die Tafel ist beschädigt und lässt sich so nicht speichern.'
        => 'The plate is damaged and cannot be saved like this.',
    'Die Tafel ist zu groß zum Speichern.'
        => 'The plate is too large to save.',
    'Diese Seite entfernen'
        => 'Remove this page',
    'Dieser Fund hat dazu keine Angabe.'
        => 'This find has nothing on this.',
    'Doppelklick auf den Text, um ihn zu ändern.'
        => 'Double-click the text to change it.',
    'Eine Seite hat höchstens %d Kacheln.'
        => 'A page has at most %d cells.',
    'Eine Tafel hat höchstens %d Seiten.'
        => 'A plate has at most %d pages.',
    'Farbe der Linien'
        => 'Colour of the lines',
    'Fundangaben'
        => 'Find details',
    'Fundangaben entfernen'
        => 'Remove find details',
    'Funddatum'
        => 'Date found',
    'Fundort'
        => 'Place found',
    'Fundort mit Land und Bundesland'
        => 'Place found with country and state',
    'GPS'
        => 'GPS',
    'Deckkraft des Hintergrunds'
        => 'Background opacity',
    'Größer'
        => 'Larger',
    'Inhalt entfernen'
        => 'Remove content',
    'Kachel entfernen'
        => 'Remove cell',
    'Kleiner'
        => 'Smaller',
    'Linien zwischen den Bildern'
        => 'Lines between the pictures',
    'Löschen ging nicht.'
        => 'Deleting did not work.',
    'MTB'
        => 'MTB',
    'in eigener Zeile'
        => 'in a line of its own',
    'Label'
        => 'Label',
    'mit %s'
        => 'with %s',
    'abgekürzt'
        => 'abbreviated',
    'oben links'
        => 'top left',
    'oben rechts'
        => 'top right',
    'unten links'
        => 'bottom left',
    'unten rechts'
        => 'bottom right',
    'Vor jeder Angabe'
        => 'In front of each detail',
    'Symbol'
        => 'Symbol',
    'Ziehen ändert die Reihenfolge'
        => 'Drag to change the order',
    'Abstand hinzufügen'
        => 'Add a gap',
    // Odour, taste, spore print (findtraits.js, find_fields.php; 09.11.2026)
    'Geruch'
        => 'Odour',
    'Geschmack'
        => 'Taste',
    'Sporenpulverfarbe'
        => 'Spore print colour',
    'Sporenpulver'
        => 'Spore print',
    'Farbe wählen'
        => 'Choose a colour',
    'Mehl und Gurke'
        => 'flour and cucumber',
    'süß und blumig'
        => 'sweet and floral',
    'würzig und Gemüse'
        => 'spicy and vegetable',
    'unangenehm'
        => 'unpleasant',
    'erdig und Holz'
        => 'earthy and wood',
    'geruchlos'
        => 'odourless',
    'schwach pilzig'
        => 'faintly fungoid',
    'mehlartig'
        => 'farinaceous',
    'gurkenartig'
        => 'cucumber',
    'wohlriechend'
        => 'fragrant',
    'fruchtig'
        => 'fruity',
    'süßlich'
        => 'sweetish',
    'honigartig'
        => 'honey',
    'blumig'
        => 'floral',
    'Veilchen / Iris'
        => 'violet / iris',
    'Anis'
        => 'aniseed',
    'Bittermandel / Marzipan'
        => 'bitter almond / marzipan',
    'Kokos'
        => 'coconut',
    'Birne'
        => 'pear',
    'würzig'
        => 'spicy',
    'Knoblauch'
        => 'garlic',
    'Kohl'
        => 'cabbage',
    'Rettich / Rübe'
        => 'radish / turnip',
    'rohe Kartoffel'
        => 'raw potato',
    'Kartoffelkeller'
        => 'potato cellar',
    'Maggi / Liebstöckel'
        => 'Maggi / lovage',
    'Curry'
        => 'curry',
    'Pelargonie'
        => 'pelargonium',
    'Sellerie'
        => 'celery',
    'Leuchtgas'
        => 'coal gas',
    'Karbol / Tinte'
        => 'phenol / ink',
    'Hering / Fisch'
        => 'herring / fish',
    'spermatisch'
        => 'spermatic',
    'Chlor / Jod'
        => 'chlorine / iodine',
    'Wanze'
        => 'bug',
    'Weidenbohrer / Raupe'
        => 'goat moth / caterpillar',
    'Aas'
        => 'carrion',
    'Scheunenstaub'
        => 'barn dust',
    'erdig'
        => 'earthy',
    'muffig / Keller'
        => 'musty / cellar',
    'harzig'
        => 'resinous',
    'holzig'
        => 'woody',
    'mild'
        => 'mild',
    'bitter'
        => 'bitter',
    'scharf, sofort'
        => 'acrid, at once',
    'scharf, verzögert'
        => 'acrid, delayed',
    'mehlig'
        => 'farinaceous',
    'rettichartig'
        => 'radish',
    'zusammenziehend'
        => 'astringent',
    'Rosa'
        => 'pink',
    'Braun'
        => 'brown',
    'Purpurbraun – Schwarz'
        => 'purple-brown – black',
    'Grün'
        => 'green',
    'cremeweiß'
        => 'cream-white',
    'creme'
        => 'cream',
    'grünlich gelb'
        => 'greenish yellow',
    'gelb'
        => 'yellow',
    'orangeocker'
        => 'orange ochre',
    'lachsrosa'
        => 'salmon pink',
    'rosa'
        => 'pink',
    'fleischrosa'
        => 'flesh pink',
    'olivbraun'
        => 'olive brown',
    'zimt- bis rostbraun'
        => 'cinnamon to rust brown',
    'rotbraun'
        => 'red-brown',
    'schwarzbraun'
        => 'black-brown',
    'purpurbraun'
        => 'purple-brown',
    'violettschwarz'
        => 'violet-black',
    'gelbgrün'
        => 'yellow-green',
    'graugrün'
        => 'grey-green',
    'blaugrün'
        => 'blue-green',
    'Weiß – Ocker'
        => 'white – ochre',
    'grünlich'
        => 'greenish',
    'rein'
        => 'pure',
    'rötlich'
        => 'reddish',
    'blass ocker'
        => 'pale ochre',
    'ocker'
        => 'ochre',
    'dunkel ocker'
        => 'dark ochre',
    'Nächste Stufe nach Romagnesi: %s'
        => 'Nearest Romagnesi grade: %s',
    'zwischen den gedruckten Stufen'
        => 'between the printed grades',
    'rein weiß'
        => 'pure white',
    'weißlich'
        => 'whitish',
    'blass creme'
        => 'pale cream',
    'mittel creme'
        => 'medium cream',
    'dunkel creme'
        => 'dark cream',
    'blass gelb'
        => 'pale yellow',
    'mittel gelb'
        => 'medium yellow',
    'dunkel gelb'
        => 'dark yellow',
    // The plate's keying ways (plates.js, keyingEl; 09.11.2026)
    'Dieser Schlüsselweg ist nicht mehr bestätigt.'
        => 'This key path is no longer confirmed.',
    'Schlüsselweg entfernen'
        => 'Remove key path',
    'Schlüsselweg: %s'
        => 'Key path: %s',
    'Der Weg und die Bemerkung kommen aus der Bestimmung dieses Fundes. Änderst du dort etwas, steht hier das Neue.'
        => 'The path and the remark come from this find\'s identification. If you change something there, the new version shows here.',
    'Was zeigen'
        => 'What to show',
    'beides'
        => 'both',
    'Schlüsselweg'
        => 'Key path',
    'Beschriftet mit'
        => 'Labelled with',
    'Buch und Schlüssel'
        => 'book and key',
    'Buch'
        => 'Book',
    'Bemerkung'
        => 'Remark',
    'Fundbeschreibung'
        => 'Find description',
    'Nichts angehakt'
        => 'Nothing ticked',
    'Schriftgröße'
        => 'Text size',
    'Seite'
        => 'Page',
    'Seite %d'
        => 'Page %d',
    'Seite entfernen?'
        => 'Remove page?',
    'Seiten'
        => 'Pages',
    'So geht es'
        => 'How it works',
    'Speichern ging nicht.'
        => 'Saving did not work.',
    'Tafel'
        => 'Plate',
    'Tafel gelöscht.'
        => 'Plate deleted.',
    'Nicht gespeichert'
        => 'Not saved',
    'Die Tafel ließ sich gerade nicht speichern. Die letzten Änderungen gehen verloren, wenn du sie jetzt schließt.'
        => 'The plate could not be saved just now. The latest changes are lost if you close it now.',
    'Offen lassen'
        => 'Keep open',
    'Wird gespeichert …'
        => 'Saving …',
    'Gespeichert'
        => 'Saved',
    'Tafel löschen'
        => 'Delete plate',
    'Tafel löschen?'
        => 'Delete plate?',
    'Tafel von %s — nur zum Ansehen.'
        => 'Plate by %s — to look at only.',
    'Tafel, %d Seiten'
        => 'Plate, %d pages',
    'Tafel, eine Seite'
        => 'Plate, one page',
    'Tafeln brauchen die Migration 0155 — sie ist noch nicht eingespielt.'
        => 'Plates need migration 0155 — it has not been applied yet.',
    'Tafeln machen der Besitzer und die Mitbesitzer eines Funds.'
        => 'Plates are made by the owner and the co-owners of a find.',
    'Freitext'
        => 'Free text',
    'Freitext entfernen'
        => 'Remove free text',
    'Text entfernen'
        => 'Remove text',
    'Was im Feld steht'
        => 'What the field shows',
    'breiter'
        => 'wider',
    'fein'
        => 'fine',
    'in einer Zeile'
        => 'in one line',
    'keiner'
        => 'none',
    'nein'
        => 'no',
    'schwarz'
        => 'black',
    'untereinander'
        => 'one below the other',
    'weiß'
        => 'white',
    // Plates on paper — PDF, picture, printer (plateprint.js; 06.10.2026)
    'Seite %d von %d, Kachel %d von %d …'
        => 'Page %d of %d, cell %d of %d …',
    'Kachel %d von %d wird gezeichnet …'
        => 'Drawing cell %d of %d …',
    'Ein Bild ließ sich nicht laden — seine Kachel blieb weiß.'
        => 'A picture could not be loaded — its cell stayed white.',
    '%d Bilder ließen sich nicht laden — ihre Kacheln blieben weiß.'
        => '%d pictures could not be loaded — their cells stayed white.',
    'Die Tafel ließ sich nicht rechnen.'
        => 'The plate could not be drawn.',
    'PDF'
        => 'PDF',
    'Alle Seiten in einer Datei, auch zum Drucken'
        => 'All pages in one file, for printing too',
    'Kachel'
        => 'Cell',
    'Allgemein'
        => 'General',
    'So kommt es auf die Tafel, wenn du es hineinziehst.'
        => 'This is how it lands on the plate when you drag it in.',
    'Klicke auf der Seite auf ein Bild, einen Freitext, auf Werte, Fundangaben, eine Verteilung oder eine Reihe. Dann stellst du hier ein, wie sie aussehen.'
        => 'Click a picture, a free text, values, find details, a distribution or a row on the page. Then you set here how it looks.',
    // Writing on a plate's pictures (plates.js, plateannot.js; 08.10.2026)
    'Im Bildbetrachter öffnen'
        => 'Open in the viewer',
    'Die Beschriftung ließ sich nicht speichern.'
        => 'The labelling could not be saved.',
    'Pfeil gesetzt.'
        => 'Arrow set.',
    'Text dazu'
        => 'Add text',
    'Text ändern'
        => 'Change text',
    'Bildarchiv'
        => 'Picture archive',
    'Funde oder Bildarchiv'
        => 'Finds or picture archive',
    'Bildarchiv wird geladen …'
        => 'Loading the picture archive …',
    'Das Bildarchiv ließ sich nicht laden: %s'
        => 'The picture archive could not be loaded: %s',
    'Die Bilder ließen sich nicht laden.'
        => 'The pictures could not be loaded.',
    'Messungen zeigen'
        => 'Show measurements',
    'Beschriftung zeigen'
        => 'Show writing',
    'Dieses Bild hat noch keine Beschriftung. Text, Pfeil und Zählrechteck setzt du im Bildbetrachter, auf einer Messebene.'
        => 'This picture has no writing yet. You set text, arrows and counting boxes in the viewer, on a measuring layer.',
    'Die Größe der Schrift stellst du unter „Allgemein“ ein.'
        => 'You set the size of the writing under “Allgemein”.',
    'Schriftgröße der Beschriftungen'
        => 'Size of the writing',
    'Nur die Seite, die du gerade siehst'
        => 'Only the page you are looking at',
    // Plates, step 3 — the measures' blocks (plates.js, plateparts.js; 06.10.2026)
    'Zu wenige Messungen für eine Verteilung'
        => 'Too few measures for a distribution',
    'Noch nichts gemessen'
        => 'Nothing measured yet',
    'Aus den Messungen'
        => 'From the measures',
    'Werte'
        => 'Values',
    'In Reihe'
        => 'In a row',
    'Werte: %s'
        => 'Values: %s',
    'Verteilung: %s'
        => 'Distribution: %s',
    '%s in Reihe'
        => '%s in a row',
    '%s in Reihe (%d)'
        => '%s in a row (%d)',
    'Die Zahlen kommen aus allen Messungen dieser Ebene am Fund, wie im Bildbetrachter. Misst du später nach, stehen hier die neuen.'
        => 'The numbers come from all measures of this layer on the find, as in the picture viewer. If you measure more later, the new ones stand here.',
    'Welche Zeilen'
        => 'Which lines',
    'Länge × Breite, mit Bereich'
        => 'Length × breadth, with range',
    'Q und Anzahl'
        => 'Q and count',
    'Streuung und Ausreißer'
        => 'Spread and outliers',
    // measurestats.js, spreadText (09.11.2026)
    'N = %d · Streuung Länge ± %d %%, Breite ± %d %% · Ausreißer %d'
        => 'N = %d · spread length ± %d %%, breadth ± %d %% · outliers %d',
    'Streuung Länge ± %d %%, Breite ± %d %% · Ausreißer %d'
        => 'Spread length ± %d %%, breadth ± %d %% · outliers %d',
    'Anzahl, Streuung und Ausreißer darunter'
        => 'Count, spread and outliers beneath',
    'Durchschnitt'
        => 'Average',
    'Mit dem Namen davor'
        => 'With the name in front',
    'Jeder Punkt ist eine Messung. Die grüne Ellipse umschließt den dichten Kern, etwa 68 % der Messungen. Die gestrichelte zeigt, wie weit die Messungen gewöhnlich reichen. Ab zehn Messungen verschieben einzelne Ausreißer die Ellipsen nicht.'
        => 'Each dot is one measure. The green ellipse encloses the dense core, about 68 % of the measures. The dashed one shows how far the measures usually reach. From ten measures on, single outliers do not shift the ellipses.',
    'Alle in einem Maßstab, mit einem Balken für die ganze Kachel. Spiegeln geht im Bildbetrachter, an der einzelnen Messung.'
        => 'All at one scale, with one bar for the whole cell. Mirroring is done in the picture viewer, on the single measure.',
    'Sortiert nach'
        => 'Sorted by',
    'Werte entfernen'
        => 'Remove values',
    'Verteilung entfernen'
        => 'Remove distribution',
    'Reihe entfernen'
        => 'Remove row',
    'Neue Tafel erstellen'
        => 'Make a new plate',
    'Tafeln'
        => 'Plates',
    'Diese Tafel ist nicht deine — oder es gibt sie nicht mehr.'
        => 'This plate is not yours — or it no longer exists.',
    'Die Tafel ist dann weg. Die Bilder darin bleiben, wo sie sind.'
        => 'The plate is then gone. The pictures in it stay where they are.',
    'Du hast an diesem Fund schon eine Tafel.'
        => 'You already have a plate on this find.',
    'Die Tafel wurde inzwischen auf einem anderen Gerät gespeichert. Schließe sie und öffne sie neu, dann siehst du die neue Fassung.'
        => 'The plate has been saved on another device in the meantime. Close it and open it again to see the new version.',
    'Ausschnitt ändern'
        => 'Change the cutout',
    'Ausschnitt fertig'
        => 'Cutout done',
    // The row's look (plates.js, airControl; 06.10.2026)
    'Wie im Bildbetrachter'
        => 'As in the picture viewer',
    'Rand außen herum'
        => 'Margin around the outside',
    'schmal'
        => 'narrow',
    'breit'
        => 'wide',
    'Größe der Seite auf dem Bildschirm'
        => 'Size of the page on the screen',
    'Seite vergrößern'
        => 'Enlarge the page',
    // The application's tabs (page.php, app_tabs; 09.10.2026)
    'Bereiche'
        => 'Sections',
    'Fundbrowser'
        => 'Find browser',
    'Kommt noch'
        => 'Coming soon',
    // The picture window (includes/figure_window.php, 09.10.2026)
    'Originalgröße'
        => 'Actual size',
    // The photo stage of the picture window (includes/figure_window.php, 13.11.2026)
    'Das Exportfenster fehlt auf dieser Seite.'
        => 'This page has no export window.',
    'Das Bild ließ sich nicht schreiben.'
        => 'The picture could not be written.',
    'Eingepasst'
        => 'Fitted',
    'Voriges Bild'
        => 'Previous picture',
    'Nächstes Bild'
        => 'Next picture',
    'Ganzes Bild'
        => 'Whole picture',
    'Größe im Verhältnis zum Originalbild'
        => 'Size relative to the original picture',
    'Das Bild in seiner echten Größe zeigen'
        => 'Show the picture at its actual size',
    'Übersicht'
        => 'Overview',
    'Zu diesem Namen gibt es gerade kein Porträt.'
        => 'There is no portrait for this name at the moment.',
    'Gibt es nicht.'
        => 'Not found.',
    // Fundbrowser (pages/finds.php, assets/js/finds.js; 10.10.2026)
    'Funde'
        => 'Finds',
    // Sondermessungen (until 25.11.2026 „Zystide in Teilen") — Hebeloma, Conocybe, Paraphyse (assets/js/photowindow.js, measurestats.js; 05.11.2026)
    'Sondermessungen'
        => 'Special measurements',
    'Hebeloma: Kopf, Mitte, unten · Conocybe: Bauch, Köpfchen, Hals · Paraphyse: Breite, Kopf'
        => 'Hebeloma: apex, middle, base · Conocybe: belly, head, neck · Paraphysis: breadth, head',
    'Zuerst der Rahmen: vier Punkte, erst die Längsachse (2), dann die Breite (2). Danach führt die Zeile hier Schritt für Schritt durch die Maße.'
        => 'First the frame: four points, the long axis first (2), then the width (2). Then this line leads you through the measures step by step.',
    'Messart:'
        => 'Method:',
    'Hier fehlt noch: %s'
        => 'Still missing here: %s',
    'Weiter messen'
        => 'Continue measuring',
    'die Länge'
        => 'the length',
    'die Gesamtlänge'
        => 'the total length',
    'der Bauch'
        => 'the belly',
    'das Köpfchen'
        => 'the head',
    'der Hals'
        => 'the neck',
    'Jetzt die Länge: Klicke auf die Basis, dann auf die Spitze.'
        => 'Now the length: click the base, then the tip.',
    'Jetzt die Gesamtlänge: Klicke auf die Basis, dann auf das Ende des Köpfchens.'
        => 'Now the total length: click the base, then the end of the head.',
    'Jetzt der Bauch: Klicke an seiner dicksten Stelle auf beide Ränder.'
        => 'Now the belly: click both edges at its widest point.',
    'Jetzt das Köpfchen: Klicke an seiner breitesten Stelle auf beide Ränder.'
        => 'Now the head: click both edges at its widest point.',
    'Jetzt der Hals: Klicke dort, wo er aus dem Bauch kommt, dann dort, wo das Köpfchen beginnt.'
        => 'Now the neck: click where it leaves the belly, then where the head begins.',
    'Jetzt A: Klicke an der breitesten Stelle des Kopfs, nahe der Spitze, auf beide Ränder.'
        => 'Now A: click both edges at the widest point of the head, near the apex.',
    'Jetzt M: Klicke an der schmalsten Stelle in der Mitte auf beide Ränder.'
        => 'Now M: click both edges at the narrowest point in the middle.',
    'Jetzt B: Klicke an der breitesten Stelle im unteren Drittel auf beide Ränder.'
        => 'Now B: click both edges at the widest point in the lower third.',
    'Köpfchen'
        => 'Head',
    'Hals'
        => 'Neck',
    // „Sondermessungen", the method „Paraphyse" (assets/js/photowindow.js, cystidSteps; 18.11.2026)
    'Paraphyse'
        => 'Paraphysis',
    'Kopf'
        => 'Head',
    'die Breite'
        => 'the breadth',
    'der Kopf'
        => 'the head',
    'Jetzt die Breite: Klicke unterhalb des Kopfs auf beide Ränder.'
        => 'Now the breadth: click both edges below the head.',
    'Jetzt der Kopf: Klicke an seiner breitesten Stelle auf beide Ränder.'
        => 'Now the head: click both edges at its widest point.',
    // „Ältere Bilder" under a species (includes/picture_archives.php; 01.11.2026)
    'ohne Datum'
        => 'no date',
    // Picture archives (includes/archives_content.php, includes/picture_archives.php; 01.11.2026)
    '%d Bilder warten auf einen Namen.'
        => '%d pictures are waiting for a name.',
    '%s Bilder in %s Arten'
        => '%s pictures in %s species',
    '%s Bilder in %s Arten gefunden.'
        => 'Found %s pictures in %s species.',
    '1 Bild wartet auf einen Namen.'
        => '1 picture is waiting for a name.',
    'Anhängen'
        => 'Attach',
    'Archiv entfernen?'
        => 'Remove archive?',
    'Art zu „%s"'
        => 'Species for „%s"',
    'Bildarchive'
        => 'Picture archives',
    'Bildarchive brauchen die Migration 0181 — sie ist noch nicht eingespielt.'
        => 'Picture archives need migration 0181 — it has not been applied yet.',
    // Working names (Einstellungen → Arten, includes/working_names*.php; migration 0189, 05.11.2026)
    'Schreib hinter die Gattung, wie du die Art nennst, zum Beispiel „nova" oder „sp. 1".'
        => 'Write after the genus what you call the species, for example „nova" or „sp. 1".',
    'Ein Arbeitsname darf kein Komma und kein Semikolon enthalten. Damit trennt YASAIMAPP zwei Arten in einem Titel.'
        => 'A working name may not contain a comma or a semicolon. YASAIMAPP uses them to separate two species in a title.',
    '„%s" steht schon in einer Namensliste. Dafür brauchst du keinen Arbeitsnamen.'
        => '„%s" is already in a name list. You do not need a working name for it.',
    'Diesen Arbeitsnamen gibt es nicht mehr.'
        => 'This working name no longer exists.',
    'Den Arbeitsnamen „%s" hast du schon.'
        => 'You already have the working name „%s".',
    'Schreib den veröffentlichten Namen als Gattung und Art, zum Beispiel „Inocybe violacea".'
        => 'Write the published name as genus and species, for example „Inocybe violacea".',
    'hat den Arbeitsnamen „%s" als „%s" veröffentlicht. Dein Fund heißt jetzt „%s".'
        => 'published the working name „%s" as „%s". Your find is now called „%s".',
    'hat den Arbeitsnamen „%s" als „%s" veröffentlicht. Deine %d Funde heißen jetzt „%s".'
        => 'published the working name „%s" as „%s". Your %d finds are now called „%s".',
    'Arbeitsnamen brauchen die Migration 0189 — sie ist noch nicht eingespielt.'
        => 'Working names need migration 0189 — it has not been applied yet.',
    'Veröffentlicht. 1 Fund heißt jetzt „%s".'
        => 'Published. 1 find is now called „%s".',
    'Veröffentlicht. %d Funde heißen jetzt „%s".'
        => 'Published. %d finds are now called „%s".',
    'Arbeitsname gelöscht. Deine Funde behalten ihren Titel.'
        => 'Working name deleted. Your finds keep their title.',
    'Arbeitsnamen'
        => 'Working names',
    'Für Arten, die in keiner Namensliste stehen. Zum Beispiel eine Art, die du gerade beschreibst und die noch nicht veröffentlicht ist.'
        => 'For species that are in no name list. For example a species you are describing right now that is not published yet.',
    'Du legst sie einmal an: die Gattung, deinen Namen für die Art und einen Zusatz wie „ad int.". Danach schlägt YASAIMAPP sie im Titelfeld vor wie jede andere Art. Ein Fund mit diesem Namen gilt als bestimmt und gehört zu seiner Gattung.'
        => 'You create it once: the genus, your name for the species and an addition like „ad int.". After that YASAIMAPP suggests it in the title field like any other species. A find with this name counts as identified and belongs to its genus.',
    'Noch keine.'
        => 'None yet.',
    'Deine'
        => 'Yours',
    'nur für dich'
        => 'only for you',
    'geteilt mit %s'
        => 'shared with %s',
    '„%s" ändern'
        => 'Change „%s"',
    'Veröffentlicht als …'
        => 'Published as …',
    '„%s" ist veröffentlicht'
        => '„%s" is published',
    'Deine Funde mit diesem Namen behalten ihren Titel. Sie gelten dann aber nicht mehr als bestimmte Art, und YASAIMAPP schlägt den Namen nicht mehr vor.'
        => 'Your finds with this name keep their title. But they no longer count as an identified species, and YASAIMAPP no longer suggests the name.',
    '„%s" löschen'
        => 'Delete „%s"',
    'Von Freunden geteilt'
        => 'Shared by friends',
    'Veröffentlicht'
        => 'Published',
    '„%s" heißt seit %s „%s".'
        => '„%s" has been called „%3$s" since %2$s.',
    '„%1$s" bekommt seinen richtigen Namen. Dein Fund und die Funde der Kollegen, die ihn benutzen, heißen danach so. Die Bilder in der Cloud ziehen mit um. Die Kollegen bekommen eine Nachricht.'
        => '„%1$s" gets its real name. Your finds and those of the colleagues who use it are then called so. The pictures in the cloud move along. The colleagues get a message.',
    'Veröffentlichter Name'
        => 'Published name',
    'Gattung und Art, zum Beispiel „Inocybe violacea"'
        => 'Genus and species, for example „Inocybe violacea"',
    'Steht der Name noch in keiner Namensliste, ist das in Ordnung. Neue Arten kommen dort oft erst Monate später an.'
        => 'It is fine if the name is not in any name list yet. New species often arrive there only months later.',
    'Neuer Arbeitsname'
        => 'New working name',
    'Arbeitsname ändern'
        => 'Change working name',
    'zum Beispiel Inocybe'
        => 'for example Inocybe',
    'Dein Name für die Art'
        => 'Your name for the species',
    'zum Beispiel nova, sp. 1 oder \'violacea\''
        => 'for example nova, sp. 1 or \'violacea\'',
    'Welcher Zusatz?'
        => 'Which addition?',
    'So steht er dann im Titel:'
        => 'This is how it will stand in the title:',
    'nur für dich und die, mit denen du ihn teilst'
        => 'only for you and those you share it with',
    'Mit wem teilst du ihn?'
        => 'Whom do you share it with?',
    'Wer hier einen Haken hat, bekommt den Namen im Titelfeld vorgeschlagen und kann seine eigenen Funde so benennen. Ändern kannst nur du ihn.'
        => 'Whoever is ticked here gets the name suggested in the title field and can name their own finds with it. Only you can change it.',
    'Arbeitsname von %s'
        => 'Working name by %s',
    'Arbeitsname'
        => 'Working name',
    '1 Fund geht nicht mit, weil sein Name in keiner Liste der DGfM steht. Das ist zum Beispiel bei Arbeitsnamen so.'
        => '1 find does not go along because its name is in no DGfM list. That is the case with working names, for example.',
    '%d Funde gehen nicht mit, weil ihr Name in keiner Liste der DGfM steht. Das ist zum Beispiel bei Arbeitsnamen so.'
        => '%d finds do not go along because their name is in no DGfM list. That is the case with working names, for example.',
    // Who sees the archives (Bildarchive → Freunde, assets/js/archiveviewers.js; migration 0184, 02.11.2026)
    'Die Auswahl der Freunde braucht die Migration 0184 — sie ist noch nicht eingespielt. Bis dahin sehen alle deine Freunde die Bilder.'
        => 'Choosing friends needs migration 0184 — it has not been applied yet. Until then all your friends see the pictures.',
    'Das braucht die Migration 0184 — sie ist noch nicht eingespielt.'
        => 'This needs migration 0184 — it has not been applied yet.',
    'Hier legst du fest, welche Freunde deine Bilder sehen. Das gilt für alle deine Ordner.'
        => 'Here you decide which friends see your pictures. It applies to all your folders.',
    'Ohne Haken bei „Fundorte" sieht der Freund keine Koordinaten: nicht im Dateinamen und nicht in den Bildern. Den Ortsnamen sieht er weiter, zum Beispiel „Wiesbaden, Nerotal".'
        => 'Without a tick at „Find spots" the friend sees no coordinates: not in the file name and not in the pictures. He still sees the place name, for example „Wiesbaden, Nerotal".',
    'Mit „Filter" nimmst du Gattungen oder Arten aus, zum Beispiel solche, an denen du gerade schreibst. Die sieht der Freund dann gar nicht.'
        => 'With „Filter" you leave out genera or species, for example ones you are writing about right now. The friend then does not see them at all.',
    'Freund'
        => 'Friend',
    'Sieht die Bilder'
        => 'Sees the pictures',
    'Fundorte'
        => 'Find spots',
    'Nicht zu sehen'
        => 'Not shown',
    'Neue Freunde'
        => 'New friends',
    'Wer später dein Freund wird'
        => 'Whoever becomes your friend later',
    '%s sieht die Bilder'
        => '%s sees the pictures',
    '%s sieht die Fundorte'
        => '%s sees the find spots',
    'nichts'
        => 'nothing',
    'Gattungen und Arten, die %s nicht sieht'
        => 'Genera and species %s does not see',
    'Filter für neue Freunde'
        => 'Filter for new friends',
    'Bilder dieser Gattungen und Arten sieht er nicht. Alles andere sieht er wie bisher.'
        => 'He does not see pictures of these genera and species. He sees everything else as before.',
    'Gattung, zum Beispiel „Inocybe"'
        => 'Genus, for example „Inocybe"',
    'Gattung und Art, zum Beispiel „Inocybe geophylla"'
        => 'Genus and species, for example „Inocybe geophylla"',
    'Keine.'
        => 'None.',
    '„%s" kenne ich nicht als Art. Schreib Gattung und Art, zum Beispiel „Inocybe geophylla".'
        => 'I do not know „%s" as a species. Write genus and species, for example „Inocybe geophylla".',
    '„%s" kenne ich nicht als Gattung. Schreib nur die Gattung, zum Beispiel „Inocybe".'
        => 'I do not know „%s" as a genus. Write only the genus, for example „Inocybe".',
    'Mit dieser Person bist du nicht mehr befreundet.'
        => 'You are no longer friends with this person.',
    'Nichts zu speichern.'
        => 'Nothing to save.',
    'Ein Name, den du einmal zuordnest, gilt in allen deinen Ordnern.'
        => 'A name you assign once applies in all your folders.',
    'Das Archiv ist entfernt. Dein Ordner in der Cloud ist unverändert.'
        => 'The archive is removed. Your folder in the cloud is unchanged.',
    'Das ist kein Freigabe-Link von pCloud. Er sieht so aus: https://e.pcloud.link/publink/show?code=…'
        => 'This is not a pCloud share link. It looks like this: https://e.pcloud.link/publink/show?code=…',
    'Der Ordner liess sich nicht lesen: %s'
        => 'The folder could not be read: %s',
    'Der gespeicherte Link lässt sich nicht mehr lesen.'
        => 'The saved link can no longer be read.',
    'Diesen Ordner hast du schon angehängt.'
        => 'You have already attached this folder.',
    'Dieses Archiv gibt es nicht mehr.'
        => 'This archive no longer exists.',
    'Ein Ordner, dessen Name mit einem Tag beginnt, ist ein Fund. Zum Beispiel „2021-07-17 Kirn".'
        => 'A folder whose name begins with a day is a find. For example „2021-07-17 Kirn".',
    'Freigabe-Link deines Ordners'
        => 'Share link of your folder',
    'Gattung Art'
        => 'Genus species',
    'Gefunden'
        => 'Found',
    'Gemeinte Art'
        => 'Species meant',
    'Hast du Pilzbilder aus der Zeit vor YASAIMAPP in einer Cloud? Dann häng den Ordner hier an. Du siehst die Bilder dann in der Bestimmung unter „Funde", und deine Freunde auch. Welche Freunde was sehen, legst du unter „Freunde" fest.'
        => 'Do you have fungus pictures from before YASAIMAPP in a cloud? Then attach the folder here. You then see the pictures in the identification under „Finds", and so do your friends. Which friends see what, you decide under „Friends".',
    'Im Moment geht das nur mit pCloud. Dort: Ordner anklicken, „Teilen", „Link teilen", Link kopieren.'
        => 'For now this works with pCloud only. There: click the folder, „Share", „Share link", copy the link.',
    'Name im Ordner'
        => 'Name in the folder',
    'Neu einlesen'
        => 'Read again',
    'Nicht erkannt'
        => 'Not recognised',
    'Noch einen Ordner anhängen'
        => 'Attach another folder',
    'Ordner'
        => 'Folder',
    'Schon zugeordnet'
        => 'Already assigned',
    'Speichern und neu einlesen'
        => 'Save and read again',
    'Was YASAIMAPP nicht versteht, steht beim Ordner in einer Liste. Dort schreibst du einmal den richtigen Namen dazu. Nur eine Gattung, „spec." oder „cf." bleiben weg.'
        => 'What YASAIMAPP does not understand is listed with the folder. There you write the right name beside it once. A genus alone, „spec." or „cf." stay out.',
    'Wie findet YASAIMAPP die Art?'
        => 'How does YASAIMAPP find the species?',
    'YASAIMAPP liest die Namen deiner Ordner und Dateien. Es versteht „Clitocybe odora", „C. odora" in einem Ordner „Clitocybe" und deutsche Namen wie „Steinpilz". Der Ordner zählt mehr als der Dateiname.'
        => 'YASAIMAPP reads the names of your folders and files. It understands „Clitocybe odora", „C. odora" in a folder „Clitocybe" and German names such as „Steinpilz". The folder counts for more than the file name.',
    'YASAIMAPP vergisst dann, was es in diesem Ordner gelesen hat. Deine Freunde sehen die Bilder nicht mehr. Der Ordner in deiner Cloud bleibt, wie er ist.'
        => 'YASAIMAPP then forgets what it read in this folder. Your friends no longer see the pictures. The folder in your cloud stays as it is.',
    'YASAIMAPP ändert nichts in deinem Ordner. Jede Nacht schaut es nach, was sich dort geändert hat.'
        => 'YASAIMAPP changes nothing in your folder. Every night it looks at what has changed there.',
    'Zuletzt gelesen'
        => 'Last read',
    'noch nicht gelesen'
        => 'not read yet',
    'pCloud kennt diesen Link nicht. Vielleicht wurde die Freigabe beendet.'
        => 'pCloud does not know this link. Perhaps the share was ended.',
    'pCloud zeigt den Ordner gerade nicht (%s).'
        => 'pCloud is not showing the folder right now (%s).',
    'weglassen'
        => 'leave out',
    '„%s" ist kein Artname. Schreib ihn so: Gattung und Art, zum Beispiel „Mycena galopus".'
        => '„%s" is not a species name. Write it like this: genus and species, for example „Mycena galopus".',
    'Funde durchsuchen'
        => 'Search finds',
    'Kachelgröße'
        => 'Tile size',
    'Funde eingrenzen'
        => 'Narrow down finds',
    'Funde werden geladen …'
        => 'Loading finds …',
    'Auf der Karte öffnen'
        => 'Open on the map',
    'Ohne Datum'
        => 'Without date',
    'Unbekannt'
        => 'Unknown',
    '1 Fund · %d Bilder'
        => '1 find · %d images',
    '%d Funde · %d Bilder'
        => '%d finds · %d images',
    'Kein Fund passt zu dieser Auswahl.'
        => 'No find matches this selection.',
    'Die Funde ließen sich nicht laden: %s'
        => 'The finds could not be loaded: %s',
    'Diesen Fund gibt es hier nicht mehr.'
        => 'This find is no longer here.',
    // The preview cache (includes/thumbs_content.php; 10.10.2026)
    'Vorschauen'
        => 'Previews',
    '%d Vorschauen angelegt'
        => '%d previews created',
    '%d ließen sich nicht anlegen (nächster Versuch in einer Woche)'
        => '%d could not be created (next attempt in a week)',
    '%d verwaiste Vorschauen entfernt'
        => '%d orphaned previews removed',
    '%d Vorschauen mit Farbprofil ergänzt' => '%d previews given their colour profile',
    'Die kleinen Vorschaubilder liegen hier auf dem Server, damit Fundbrowser und Galerie schnell laden. Die Bilder selbst bleiben in der Cloud. Jedes Bild bekommt seine Vorschau beim ersten Anschauen, die übrigen legt der Server alle fünf Minuten nach.'
        => 'The small preview images are kept here on the server so the find browser and the gallery load quickly. The images themselves stay in the cloud. Every image gets its preview the first time it is viewed; the server adds the rest every five minutes.',
    'Unbeaufsichtigt erledigt das %s — hier geht es sofort, in Häppchen von %d Sekunden.'
        => '%s does this unattended — here it happens right away, in chunks of %d seconds.',
    'mit Vorschau'
        => 'with preview',
    'noch ohne'
        => 'still without',
    'ließen sich nicht anlegen'
        => 'could not be created',
    'Anlegen (%d offen)'
        => 'Create (%d open)',
    // Mappen umstellen — the conversion of list trees into folders (folders_convert_content.php; 11.10.2026)
    'Mappen umstellen'
        => 'Convert to folders',
    'Mappen brauchen die Migration 0168 — sie ist noch nicht eingespielt.'
        => 'Folders need migration 0168 — it has not been applied yet.',
    'Bisher konnte eine Liste Unterlisten haben, und die Unterlisten bekamen ihre Einstellungen von der Hauptliste. Jetzt hat jede Liste alle Einstellungen selbst. Mehrere Listen kann man in eine Mappe legen. Die Mappe ist nur ein Name, sie fasst die Listen in der Seitenleiste zusammen.'
        => 'Until now a list could have sublists, and the sublists got their settings from the main list. Now every list has all its settings itself. Several lists can be put into a folder. A folder is only a name; it groups the lists in the sidebar.',
    'Dieser Knopf macht aus jeder Hauptliste eine Mappe. Jede Unterliste bekommt eine Kopie von allem, was sie bisher von oben bekam: Artenliste, Felder und Teilen. Ein Beispiel: War „Meine Funde" mit Ben geteilt, ist danach jede Liste in der Mappe „Meine Funde" mit Ben geteilt. Danach kann man jede Liste einzeln ändern.'
        => 'This button turns every main list into a folder. Every sublist gets a copy of everything it used to get from above: species list, fields and sharing. An example: if “Meine Funde” was shared with Ben, every list in the folder “Meine Funde” is shared with Ben afterwards. After that each list can be changed on its own.',
    'Es gibt keine Unterlisten mehr. Alles ist umgestellt.'
        => 'There are no sublists left. Everything is converted.',
    'Eine Hauptliste hängt noch in einer alten Freigabe. Stelle zuerst unter „Freigaben umstellen" um, dann hier.'
        => 'A main list still hangs in an old share. Convert under “Convert shares” first, then here.',
    'Mappe „%s"'
        => 'folder “%s”',
    'wird Artenliste'
        => 'becomes a species list',
    'bekommt die Felder %s'
        => 'gets the fields %s',
    'wird geteilt wie „%s" (%d Empfänger)'
        => 'is shared like “%s” (%d recipients)',
    '„%s" (neu, für %d Punkte, die noch in der Hauptliste lagen)'
        => '“%s” (new, for %d points that still lay in the main list)',
    '%d automatische Listen nehmen statt der Hauptliste deren Listen als Quelle.'
        => '%d automatic lists take the main list’s lists as their source instead of the main list.',
    'Alle Hauptlisten werden jetzt zu Mappen, und jede Unterliste bekommt ihre eigenen Einstellungen. Das lässt sich nicht rückgängig machen.'
        => 'All main lists now become folders, and every sublist gets its own settings. This cannot be undone.',
    'Umgestellt: %d Konten, %d Mappen, %d Listen.'
        => 'Converted: %d accounts, %d folders, %d lists.',
    // Mappen — sidebar, list window, folder window (folders.php, map.js; 11.10.2026)
    'Neue Mappe'
        => 'New folder',
    'Mappe bearbeiten'
        => 'Edit folder',
    'Mappe %s bearbeiten'
        => 'Edit folder %s',
    'Mappe'
        => 'Folder',
    'Keine Mappe'
        => 'No folder',
    'Name der Mappe'
        => 'Name of the folder',
    'Eine Mappe fasst Listen in der Seitenleiste zusammen. Sie hat keine eigenen Einstellungen. Welche Listen in ihr liegen, stellst du bei jeder Liste ein, unter „Liste bearbeiten".'
        => 'A folder groups lists in the sidebar. It has no settings of its own. Which lists lie in it is set at each list, under “Edit list”.',
    'Alle Listen der Mappe ein- oder ausblenden'
        => 'Show or hide all lists of the folder',
    'Listen der Mappe zeigen'
        => 'Show the folder’s lists',
    'Mappe einklappen'
        => 'Collapse folder',
    '%d von %d Listen der Mappe sichtbar'
        => '%d of %d lists of the folder visible',
    'Mappe umbenannt.'
        => 'Folder renamed.',
    'Mappe angelegt. Lege Listen hinein, indem du bei einer Liste unter „Mappe" diese Mappe wählst.'
        => 'Folder created. Put lists into it by choosing this folder under “Folder” at a list.',
    'Mappen lassen sich nur mit Verbindung anlegen und ändern.'
        => 'Folders can only be created and changed with a connection.',
    'Mappe „%s" löschen?'
        => 'Delete folder “%s”?',
    'Nur die Mappe verschwindet. Die Listen darin bleiben, mit allen Punkten, und stehen danach ohne Mappe in der Seitenleiste.'
        => 'Only the folder disappears. The lists in it stay, with all their points, and stand in the sidebar without a folder afterwards.',
    'Mappe löschen'
        => 'Delete folder',
    'Mappe gelöscht. Die Listen sind noch da.'
        => 'Folder deleted. The lists are still there.',
    'Diese Mappe gibt es nicht.'
        => 'This folder does not exist.',
    'Die Mappe braucht einen Namen.'
        => 'The folder needs a name.',
    'Mappe nicht gefunden oder nichts zu ändern.'
        => 'Folder not found or nothing to change.',
    'Mappe nicht gefunden.'
        => 'Folder not found.',
    // Mappen in Organisieren (organize.js; 11.10.2026)
    'In Mappe legen'
        => 'Put into folder',
    'In welche Mappe?'
        => 'Into which folder?',
    '— keine Mappe —'
        => '— no folder —',
    '„%s" behält alle Punkte und Einstellungen. Die Mappe fasst nur zusammen.'
        => '“%s” keeps all its points and settings. The folder only groups.',
    'Listen liegen nicht mehr unter Listen. Lege sie stattdessen in eine Mappe.'
        => 'Lists no longer lie under lists. Put them into a folder instead.',
    '<strong>Teilen:</strong> Hier wählst du, welche Freunde und Arbeitsgruppen die Liste sehen und was jeder damit darf. Das stellst du für jede Liste einzeln ein.'
        => 'Sharing: here you choose which friends and working groups see the list and what each may do with it. You set that for each list on its own.',
    // Fundbrowser as the cockpit (pages/finds.php; 11.10.2026)
    'Art, Fundnummer, Ort oder Tour suchen'
        => 'Search species, find number, place or tour',
    'Treffer'
        => 'Matches',
    '1 Treffer'
        => '1 match',
    '%d Treffer'
        => '%d matches',
    'Kein Fund passt zu „%s“.'
        => 'No find matches „%s“.',
    'Noch keine Funde.'
        => 'No finds yet.',
    'Noch kein Fund mit Bildern. Such oben nach einem Namen, dann siehst du alle Funde.'
        => 'No find with images yet. Search for a name above to see all finds.',
    'Fund öffnen'
        => 'Open find',
    'Zurück'
        => 'Back',
    'Zu diesem Fund gibt es noch keine Bilder.'
        => 'This find has no images yet.',
    'Koordinate'
        => 'Coordinate',
    'Der Fund ließ sich nicht laden: %s'
        => 'The find could not be loaded: %s',
    'Text, Bilder, Sequenzen, Dateien oder Karte'
        => 'Text, pictures, sequences, files or map',
    'Hochladen'
        => 'Upload',
    'Klicke links auf ein Bild, dann steht es hier groß.'
        => 'Click a picture on the left to see it large here.',
    'Tafel oder Hauptmotiv'
        => 'Plate or main subject',
    'Tafel oder Bilder'
        => 'Plate or pictures',
    'Bilder, Dateien und Sequenzen des Fundes'
        => 'Pictures, files and sequences of the find',
    'Im Reiter Tafeln öffnen'
        => 'Open in the Plates tab',
    'Im Reiter Bilder öffnen'
        => 'Open in the Pictures tab',
    'Text, Tafeln, Bilder, Dateien, Sequenzen oder Karte'
        => 'Text, plates, pictures, files, sequences or map',
    'Für eine Tafel braucht der Fund zuerst ein Bild. Lade es im Reiter Bilder hoch.'
        => 'A plate needs a picture of the find first. Upload it in the Pictures tab.',
    'Zu diesem Fund gibt es noch keine Tafel.'
        => 'This find has no plate yet.',
    'Meine Tafel'
        => 'My plate',
    'Tafel von %s'
        => 'Plate by %s',
    'Neue Tafel'
        => 'New plate',
    'Eigener Name …'
        => 'Own name …',
    'Was zeigt das Bild? Zum Beispiel „Hauptmotiv", „Sporen" oder „Schnittbild".'
        => 'What does the picture show? For example "Hauptmotiv", "Sporen" or "Schnittbild".',
    'Im Schlüssel ansehen — mit allen Wegen'
        => 'Look at it in the key — with every way',
    'Schriftfarbe'
        => 'Writing colour',
    'Untergrund weichzeichnen'
        => 'Blur what lies beneath',
    'Das Medium ließ sich nicht speichern.'
        => 'The medium could not be saved.',
    'Modus'
        => 'Mode',
    'Gedrückt halten zeigt das Original'
        => 'Hold to see the original',
    'Nur zum Anschauen: Dieses Bild darfst du nicht bearbeiten.'
        => 'Only to look at: you may not edit this picture.',
    'Ellipsen'
        => 'Ellipses',
    'Mit einer Ellipse machst du eine Stelle heller oder dunkler. „Umkehren" ändert stattdessen alles außerhalb.'
        => 'An ellipse makes a spot lighter or darker. "Invert" changes everything outside it instead.',
    'Ellipse'
        => 'Ellipse',
    'Licht'
        => 'Light',
    'Details'
        => 'Details',
    'Kontrast'
        => 'Contrast',
    'Lichter'
        => 'Highlights',
    'Tiefen'
        => 'Shadows',
    'Weiß'
        => 'Whites',
    'Schwarz'
        => 'Blacks',
    'Temperatur'
        => 'Temperature',
    'Tönung'
        => 'Tint',
    'Dynamik'
        => 'Vibrance',
    'Sättigung'
        => 'Saturation',
    'Klarheit'
        => 'Clarity',
    'Dunst entfernen'
        => 'Dehaze',
    'Schärfen'
        => 'Sharpen',
    'Die Bearbeitung ließ sich nicht speichern.'
        => 'The edit could not be saved.',
    'Doppelklick setzt auf 0 zurück'
        => 'Double click sets it back to 0',
    'Ellipse %d'
        => 'Ellipse %d',
    '(außen)'
        => '(outside)',
    'Umkehren — außerhalb der Ellipse ändern'
        => 'Invert — change outside the ellipse',
    'Ellipse löschen'
        => 'Delete ellipse',
    'Drehen und zuschneiden'
        => 'Turn and crop',
    '↺ 90° links drehen'
        => '↺ Turn 90° left',
    '↻ 90° rechts drehen'
        => '↻ Turn 90° right',
    'Zuschneiden'
        => 'Crop',
    'Ausrichten'
        => 'Straighten',
    'Drehen und Ausrichten zurücksetzen'
        => 'Undo turning and straightening',
    'Horizontal spiegeln'
        => 'Mirror horizontally',
    'Vertikal spiegeln'
        => 'Mirror vertically',
    'Den Urheber kann nur ändern, wer die Aufnahme hochgeladen hat.'
        => 'Only whoever uploaded the recording can change its author.',
    'Der Urheber braucht die Migration 0161 — sie ist noch nicht eingespielt.'
        => 'The author needs migration 0161 — it has not been applied yet.',
    'das Funddatum'
        => 'the find\'s date',
    'die Gemeinde des Fundorts'
        => 'the find\'s municipality',
    'Der Urheber kommt immer vom Bild: bei Belegfotos der, der sie aufgenommen hat, bei Cloud-Aufnahmen der, den du beim Hochladen angegeben hast. Sein Name steht in jedem exportierten Bild in der Datei. Den Fundort schreibt YASAIMAPP nie hinein.'
        => 'The author always comes from the picture: for voucher photos whoever took them, for cloud recordings whoever you named when uploading. Their name is in the file of every exported picture. YASAIMAPP never writes the find spot into it.',
    'Unter jedem Text setzen Knöpfe Angaben ein, die beim Export ausgefüllt werden. „+ Art“ zum Beispiel setzt die Art des Funds ein, „+ Urheber“ den Namen dessen, der das Foto gemacht hat. Was in geschweiften Klammern steht, wird beim Export ersetzt.'
        => 'Under each text, buttons put in details that are filled in on export. "+ Art", for example, puts in the find\'s species, "+ Urheber" the name of whoever took the photo. What stands in braces is replaced on export.',
    'Ich'
        => 'Me',
    'Ich (%s)'
        => 'Me (%s)',
    'In der Bilddatei steht als Fotograf: %s'
        => 'The picture file names as photographer: %s',
    'Wer hat das Foto gemacht?'
        => 'Who took the photo?',
    'Wer hat die %d Fotos gemacht?'
        => 'Who took the %d photos?',
    'Der Urheber ließ sich nicht speichern.'
        => 'The author could not be saved.',
    'Urheber ändern'
        => 'Change the author',
    'Vor- und Nachname'
        => 'First and last name',
    'Wer hat dieses Foto gemacht? Lass das Feld leer, wenn du es selbst warst.'
        => 'Who took this photo? Leave the field empty if it was you.',
    'Der Urheber steht später in jedem Bild, das du exportierst. Ändern kannst du ihn im Bildbetrachter.'
        => 'The author will stand in every picture you export. You can change it in the viewer.',
    'Jemand anderes:'
        => 'Somebody else:',
    'Wer hat die Fotos gemacht?'
        => 'Who took the photos?',
    'Setzt „%s“ ein: %s'
        => 'Puts in "%s": %s',
    'Datum'
        => 'Date',
    'Motiv'
        => 'Subject',
    'Wasserzeichen-Vorlagen'
        => 'Watermark templates',
    'Vorlage'
        => 'Template',
    'die Art des Funds'
        => 'the find\'s species',
    'das Funddatum, als 2026-08-14'
        => 'the find\'s date, as 2026-08-14',
    'die Nummer des Funds'
        => 'the find\'s number',
    'der Motivname des Bildes'
        => 'the picture\'s name',
    'der Fundort, als DE,THU, Arenshausen'
        => 'the find spot, as DE,THU, Arenshausen',
    'der Urheber'
        => 'the author',
    'das Jahr des Exports'
        => 'the year of the export',
    'Die Datei heißt: %s'
        => 'The file is named: %s',
    'Der Dateiname ließ sich nicht merken.'
        => 'The file name could not be kept.',
    'Das Logo ist zu groß.'
        => 'The logo is too large.',
    'Das Logo kam nicht an.'
        => 'The logo did not arrive.',
    'Das Logo ließ sich nicht lesen.'
        => 'The logo could not be read.',
    'Das Logo ließ sich nicht speichern.'
        => 'The logo could not be saved.',
    'Abstand'
        => 'Spacing',
    'Anderes Logo wählen'
        => 'Choose another logo',
    'Beim Exportieren wählst du eine dieser Vorlagen oder keine. Jede kann einen Hinweis, ein Schutzgitter und ein Logo haben, jedes für sich an- und ausschaltbar.'
        => 'When exporting you choose one of these templates or none. Each can have a notice, a protective grid and a logo, each switched on and off by itself.',
    'Das ließ sich nicht speichern.'
        => 'That could not be saved.',
    'Dateiname'
        => 'File name',
    'Deckkraft'
        => 'Opacity',
    'Dein Logo als Bild, am besten ein PNG mit durchsichtigem Hintergrund.'
        => 'Your logo as a picture, best a PNG with a transparent background.',
    'Die Vorlage „%s“ wird gelöscht. Bilder, die du damit schon exportiert hast, bleiben, wie sie sind.'
        => 'The template "%s" will be deleted. Pictures you already exported with it stay as they are.',
    'Ein fast durchsichtiger Text, schräg und immer wieder über das ganze Bild. So kann man das Bild ansehen, aber kaum weiterverwenden.'
        => 'An almost transparent text, slanted and repeated over the whole picture. The picture can be looked at, but hardly used further.',
    'Ein kurzer Text in einer Ecke, zum Beispiel „© Anna Muster 2026“.'
        => 'A short text in a corner, for example "© Anna Muster 2026".',
    'Hinweis'
        => 'Notice',
    'Logo'
        => 'Logo',
    'Logo wählen'
        => 'Choose a logo',
    'Mehr als zwölf Vorlagen gehen nicht.'
        => 'No more than twelve templates.',
    'Mitte'
        => 'Middle',
    'Mitte links'
        => 'Middle left',
    'Mitte rechts'
        => 'Middle right',
    'Noch keine Vorlage. Mit „Neue Vorlage“ legst du die erste an.'
        => 'No template yet. "Neue Vorlage" makes the first one.',
    'Schreibmaschine'
        => 'Typewriter',
    'Schrift'
        => 'Typeface',
    'Schutzgitter'
        => 'Protective grid',
    'Urheber'
        => 'Author',
    'Vorschau an einem Beispielbild. Größen gelten als Anteil der langen Bildkante, darum sieht es bei jeder Exportgröße gleich aus.'
        => 'Preview on a sample picture. Sizes are a share of the picture\'s long edge, so it looks the same at every export size.',
    'Weicher Schatten, damit der Text auf jedem Grund lesbar bleibt'
        => 'A soft shadow, so the text stays readable on any ground',
    'Winkel'
        => 'Angle',
    'Wird hochgeladen …'
        => 'Uploading …',
    'Wo'
        => 'Where',
    'automatisch'
        => 'automatic',
    'Auto'
        => 'Auto',
    'leer: der Text des Hinweises'
        => 'empty: the notice\'s text',
    'mit Serifen'
        => 'with serifs',
    'oben Mitte'
        => 'top middle',
    'schlicht'
        => 'plain',
    'unten Mitte'
        => 'bottom middle',
    'Das Bild wird %d × %d Pixel groß.'
        => 'The picture will be %d × %d pixels.',
    'Volle Größe (%d × %d Pixel)'
        => 'Full size (%d × %d pixels)',
    'keins'
        => 'none',
    'Vorlage %d'
        => 'Template %d',
    '1080 (Instagram)'
        => '1080 (Instagram)',
    'Anteil'
        => 'Share',
    'Das Bild wird so heruntergeladen, wie du es hier siehst. Das Original bleibt, wie es ist.'
        => 'The picture is downloaded as you see it here. The original stays as it is.',
    'Einstellungen → Fotos → Export'
        => 'Settings → Photos → Export',
    'Lange Kante'
        => 'Long edge',
    'Nachschärfen'
        => 'Sharpen afterwards',
    'Nur beim Verkleinern: Ein stark verkleinertes Bild wirkt sonst etwas weich.'
        => 'Only when making smaller: a picture made much smaller looks a little soft otherwise.',
    'Pixel'
        => 'pixels',
    'Volle Größe'
        => 'Full size',
    'Vorlagen legst du an unter %s.'
        => 'You make templates under %s.',
    'Wasserzeichen'
        => 'Watermark',
    'aus'
        => 'off',
    'leicht'
        => 'light',
    'stark'
        => 'strong',
    'Hier legst du fest, wie ein bearbeitetes Bild beim Exportieren aussieht: wer der Urheber ist, wie die Datei heißt, und welche Wasserzeichen du zur Wahl hast. Exportieren kannst du ein Bild im Bildbetrachter unter „Bearbeiten“ mit „Bild exportieren“.'
        => 'Here you set how an edited picture looks when exported: who the author is, what the file is called, and which watermarks you can choose from. You export a picture in the viewer under "Bearbeiten" with "Bild exportieren".',
    'Bild exportieren'
        => 'Export picture',
    'Exportieren'
        => 'Export',
    'Format'
        => 'Format',
    'Qualität'
        => 'Quality',
    'Lädt das Bild so herunter, wie du es hier siehst. Das Original bleibt, wie es ist.'
        => 'Downloads the picture as you see it here. The original stays as it is.',
    'JPEG – jedes Programm kann es öffnen.'
        => 'JPEG – every program can open it.',
    'WebP – kleinere Datei bei gleichem Aussehen. Manche ältere Programme können es nicht öffnen.'
        => 'WebP – a smaller file that looks the same. Some older programs cannot open it.',
    'Dieser Browser kann kein WebP speichern.'
        => 'This browser cannot save WebP.',
    'Weniger Qualität macht die Datei kleiner. Bis etwa 85 % sieht man meist keinen Unterschied.'
        => 'Less quality makes the file smaller. Down to about 85 % you usually see no difference.',
    '„%s" wird heruntergeladen.'
        => '"%s" is being downloaded.',
    'Das Bild ließ sich nicht exportieren: %s'
        => 'The picture could not be exported: %s',
    'Hier ist etwas eingestellt.'
        => 'Something is set here.',
    'Drehen'
        => 'Rotate',
    'Zuschneiden, Drehen'
        => 'Crop, rotate',
    'Zieh an Kanten und Ecken, um zuzuschneiden, und außerhalb des Rahmens, um zu drehen.'
        => 'Drag the edges and corners to crop, and outside the frame to rotate.',
    'Zieh eine Linie an etwas entlang, das waagrecht oder senkrecht stehen soll.'
        => 'Draw a line along something that should stand level or upright.',
    // Several edits of one picture (photowindow.js, versionsDraw — 11.11.2026)
    'Bearbeitung'
        => 'Edit',
    'Bearbeitung %d'
        => 'Edit %d',
    'Bearbeitung %d angelegt — eine Kopie von Bearbeitung %d.'
        => 'Edit %d made — a copy of edit %d.',
    'Bearbeitung %d gilt jetzt überall.'
        => 'Edit %d now counts everywhere.',
    'Bearbeitung %d löschen'
        => 'Delete edit %d',
    'Die Einstellungen von Bearbeitung %d gehen verloren. Das Original und die anderen Bearbeitungen bleiben.'
        => 'The settings of edit %d will be lost. The original and the other edits stay.',
    'Bearbeitung %d gelöscht.'
        => 'Edit %d deleted.',
    'Bearbeitung %d — gilt überall (Taste V wechselt)'
        => 'Edit %d — counts everywhere (key V switches)',
    'Bearbeitung %d (Taste V wechselt)'
        => 'Edit %d (key V switches)',
    'Höchstens %d Bearbeitungen'
        => 'At most %d edits',
    'Neue Bearbeitung'
        => 'New edit',
    'Gültig machen'
        => 'Make valid',
    'Bearbeitung %d gilt dann überall: Kacheln, Tafeln, Fundbericht'
        => 'Edit %d then counts everywhere: tiles, plates, find report',
    'nächste Bearbeitung'
        => 'next edit',
    'Zieh eine Linie an etwas entlang, das waagrecht oder senkrecht stehen soll. Das Bild richtet sich danach aus.'
        => 'Draw a line along something that should stand level or upright. The picture straightens to it.',
    'Zieh an den Kanten und Ecken. Innen verschiebst du den Rahmen, außerhalb drehst du das Bild. Mit gedrückter Umschalttaste bleiben die Form und die Mitte gleich.'
        => 'Drag the edges and corners. Inside, you move the frame; outside, you rotate the picture. Holding Shift keeps the shape and the middle.',
    'Frei'
        => 'Free',
    'Zuschneiden zurücksetzen'
        => 'Undo the crop',
    'Einblenden'
        => 'Show',
    'Kopf einklappen'
        => 'Fold the head away',
    'Seitenleiste einklappen'
        => 'Fold the sidebar away',
    'Seitenleiste ausklappen'
        => 'Unfold the sidebar',
    // What a list holds (map.js listGroupsDraw, settings_content.php, help.php; migration 0195, 12.11.2026).
    'Was enthält diese Liste?'
        => 'What does this list hold?',
    'Bei „Art" bekommst du nur Namen aus diesen Gruppen vorgeschlagen. Ihre Namen kommen auch aufs Handy, damit die Vorschläge im Wald ohne Empfang gehen.'
        => 'Under “Species” you are only offered names from these groups. Their names also come onto your phone, so the suggestions work in the woods without reception.',
    'Woher die Namen kommen'
        => 'Where the names come from',
    'Teile der Liste'
        => 'Parts of the list',
    'Aussehen'
        => 'Look',
    'Gib der Liste einen Namen.'
        => 'Give the list a name.',
    'Ohne Namen wird nichts gespeichert.'
        => 'Without a name nothing is saved.',
    'Nicht mehr mit %s teilen?'
        => 'Stop sharing with %s?',
    '%s sieht die Liste „%s" dann nicht mehr. An den Punkten ändert sich nichts.'
        => '%s will no longer see the list “%s”. Nothing changes about the points.',
    'Mit %s teilen?'
        => 'Share with %s?',
    '%s sieht dann die Punkte der Liste „%s" mit allen Bildern. Ändern darf %s nichts, bis du es am Zahnrad erlaubst.'
        => '%s will then see the points of the list “%s” with all pictures. %s may change nothing until you allow it at the gear.',
    'Teilen gespeichert.'
        => 'Sharing saved.',
    'Teilen gespeichert. %d Punkte sind zurück bei denen, die nicht mehr mitarbeiten.'
        => 'Sharing saved. %d points are back with those who no longer work on it.',
    'Das Teilen ließ sich nicht speichern: %s'
        => 'Sharing could not be saved: %s',
    'Welche Namen du bei „Art" vorgeschlagen bekommst, legst du bei jeder Liste fest: im Fenster „Liste bearbeiten" unter „Was enthält diese Liste?". Die Namen dieser Gruppen kommen dann von selbst aufs Gerät.'
        => 'Which names you are offered under “Species” you set for each list: in the window “Edit list” under “What does this list hold?”. The names of these groups then come onto the device by themselves.',
    'Breite der Seitenleiste ziehen'
        => 'Drag the sidebar\'s width',
    'Bildleiste einklappen'
        => 'Fold the picture strip away',
    'Bildleiste ausklappen'
        => 'Unfold the picture strip',
    'Statistik einklappen'
        => 'Fold the statistics away',
    'Bereich wählen'
        => 'Choose an area',
    'Aus den hellsten Stellen des Bildes, meist dem leeren Hellfeld'
        => 'From the brightest parts of the picture, mostly the empty bright field',
    'Einen Rahmen über eine neutrale Stelle ziehen, etwa leeren Objektträger'
        => 'Draw a frame over a neutral spot, such as empty slide',
    'Der Farbstich ist stärker, als die Regler reichen. Sie stehen am Ende.'
        => 'The colour cast is stronger than the sliders reach. They stand at their end.',
    'Daraus ließ sich kein Weißabgleich bestimmen.'
        => 'No white balance could be measured from that.',
    'Kachelleiste einklappen'
        => 'Fold the tile strip away',
    'Seite hinzufügen'
        => 'Add a page',
    'Kachelleiste ausklappen'
        => 'Unfold the tile strip',
    'Kopf ausklappen'
        => 'Unfold the head',
    'Ausblenden'
        => 'Hide',
    'Alles bleibt, wie es vor dem Zuschneiden war'
        => 'Everything stays as it was before cropping',
    'Umkehren'
        => 'Invert',
    'Die unscharfen Bereiche markieren statt der scharfen'
        => 'Mark the blurred areas instead of the sharp ones',
    'Esc bricht ab und lässt alles, wie es vorher war'
        => 'Esc cancels and leaves everything as it was',
    'Werkzeuge'
        => 'Tools',
    'Masken'
        => 'Masks',
    'Verlauf'
        => 'Gradient',
    'Verlauf %d'
        => 'Gradient %d',
    '(umgekehrt)'
        => '(inverted)',
    'Umkehren — die andere Seite ändern'
        => 'Invert — change the other side',
    'Verlauf löschen'
        => 'Delete gradient',
    'Erst etwas einstellen — sonst wäre es nur eine Kopie des Originals.'
        => 'Change something first — otherwise it would only be a copy of the original.',
    'Wird berechnet …'
        => 'Computing …',
    'Das Original kam nicht an.'
        => 'The original did not arrive.',
    'Das Original ließ sich nicht öffnen.'
        => 'The original could not be opened.',
    'Dieser Browser kann das Bild nicht berechnen.'
        => 'This browser cannot compute the picture.',
    'Das Bild ließ sich nicht speichern.'
        => 'The picture could not be saved.',
    'Das Bild ist für diesen Browser zu groß.'
        => 'The picture is too large for this browser.',
    'Bearbeitet durch YASAIMAPP am %s: Regler, Drehung und Ausschnitt aus „Bearbeiten" sind eingerechnet (%d × %d Pixel). Originaldatei: %s.'
        => 'Edited by YASAIMAPP on %s: the sliders, turn and crop of “Edit” are applied (%d × %d pixels). Original file: %s.',
    'Bearbeitet durch YASAIMAPP am %s: Regler, Drehung und Ausschnitt aus „Bearbeiten" sind eingerechnet (%d × %d Pixel).'
        => 'Edited by YASAIMAPP on %s: the sliders, turn and crop of “Edit” are applied (%d × %d pixels).',
    'Was steht in der Datei?'
        => 'What goes into the file?',
    'Fundort (Koordinaten des Funds)'
        => 'Find spot (the find’s coordinates)',
    'Kamera und Belichtung'
        => 'Camera and exposure',
    'Aufnahmezeitpunkt'
        => 'Moment of the shot',
    'Der Urheber steht immer drin.'
        => 'The author is always included.',
    'Zurück zum Schlüssel'
        => 'Back to the key',
    'Schlüsseln'
        => 'Key out',
    'Bestimmen — mit den Schlüsseln zur Art'
        => 'Identify — with the keys to the species',
    'Mein Weg'
        => 'My way',
    'noch keine Art bestätigt'
        => 'no species confirmed yet',
    'Der Schlüsselweg fehlt.'
        => 'The identification walk is missing.',
    'Der Schlüsselweg ließ sich hier nicht speichern.'
        => 'The identification walk could not be stored here.',
    'Typus-Sequenzen von %s (GenBank)'
        => 'Type sequences of %s (GenBank)',
    '%s als FASTA-Datei speichern'
        => 'Save %s as a FASTA file',
    'GenBank hat die Sequenz gerade nicht geliefert.'
        => 'GenBank did not deliver the sequence just now.',
    'Typus-Sequenzen von %s, heute %s (GenBank)'
        => 'Type sequences of %s, today %s (GenBank)',
    'GenBank war gerade nicht zu erreichen. Später noch einmal öffnen.'
        => 'GenBank could not be reached just now. Open again later.',
    'Bei GenBank ist für %s keine Typus-Sequenz gekennzeichnet.'
        => 'GenBank flags no type sequence for %s.',
    '1 Sequenz ohne Typus ansehen'
        => 'View 1 sequence without type',
    '%d Sequenzen ohne Typus ansehen'
        => 'View %d sequences without type',
    'Bei GenBank gibt es für %s keine Sequenz.'
        => 'GenBank holds no sequence of %s.',
    'Artenhypothesen von %s (UNITE, ITS)'
        => 'Species hypotheses of %s (UNITE, ITS)',
    'UNITE hat für diese Art keine Artenhypothese.'
        => 'UNITE has no species hypothesis for this species.',
    'UNITE war gerade nicht zu erreichen. Später noch einmal öffnen.'
        => 'UNITE could not be reached just now. Open again later.',
    'Referenzsequenz'
        => 'Reference sequence',
    'Vertretersequenz'
        => 'Representative sequence',
    'vom Typus'
        => 'from the type',
    '1 Sequenz'
        => '1 sequence',
    '%d Sequenzen'
        => '%d sequences',
    'gesetzt von %s'
        => 'set by %s',
    'Im Fundbrowser bearbeiten'
        => 'Edit in the find browser',
    'Im Fundbrowser ansehen'
        => 'View in the find browser',
    'Position setzen'
        => 'Set position',
    'Den ganzen Fund ins Bild holen'
        => 'Bring the whole find into view',
    'Zum Fund'
        => 'To the find',
    'Leaflet ließ sich nicht laden.'
        => 'Leaflet could not be loaded.',
    'Die Karte lässt sich in diesem Browser nicht zeichnen.'
        => 'This browser cannot draw the map.',
    'Karte wird geladen …'
        => 'Loading the map …',
    'Die Karte ließ sich nicht laden: %s'
        => 'The map could not be loaded: %s',
    'Ziehen, um den Fund zu verschieben'
        => 'Drag to move the find',
    'Klicke auf die Stelle, an der der Fund liegt, oder ziehe den Punkt dorthin.'
        => 'Click the spot where the find lies, or drag the point there.',
    'Umriss gezogen — %s.'
        => 'Outline drawn — %s.',
    'Klicke Ecke für Ecke auf die Karte, dann „Fertig".'
        => 'Click corner by corner on the map, then “Done”.',
    'Listen ändern'
        => 'Change lists',
    // A plate's drawn pages in the cloud (plate_images.php, api/v1/plates.php; 13.10.2026)
    'Diese Seite gibt es auf der Tafel nicht.'
        => 'This page does not exist on the plate.',
    'Die Seite kam unvollständig an.'
        => 'The page arrived incomplete.',
    'Die Seite kam nicht als Bild an.'
        => 'The page did not arrive as a picture.',
    'Die Cloud hat die Seite gerade nicht angenommen.'
        => 'The cloud did not accept the page just now.',
    'Zuklappen'
        => 'Fold',
    'Chat zuklappen'
        => 'Fold the chat',
    'Chat aufklappen'
        => 'Unfold the chat',
    'Diese Aufsammlung gibt es nicht.'
        => 'This collection does not exist.',
    // Aufsammlungen — point window and setting a point (collectionui.js; 15.10.2026)
    'Aufsammlung'
        => 'Collection',
    'Aufsammlung %s'
        => 'Collection %s',
    'Substrat, das zu Hause in Kultur kommt — die Arten trägst du später an ihm ein'
        => 'Substrate that goes into culture at home — you add the species to it later',
    'Arten aus dieser Aufsammlung'
        => 'Species from this collection',
    'Art hinzufügen'
        => 'Add species',
    'In Fund umwandeln'
        => 'Turn into a find',
    'In Fund umwandeln?'
        => 'Turn into a find?',
    'Aus der Aufsammlung %s wird ein normaler Fund. Ort, Datum, Felder und Bilder bleiben. Die Nummer %s wird nicht noch einmal vergeben.'
        => 'Collection %s becomes an ordinary find. Place, date, fields and pictures stay. The number %s will not be given out again.',
    'Umwandeln'
        => 'Convert',
    'Die Aufsammlung ist jetzt ein Fund. Trag die Art ein.'
        => 'The collection is now a find. Enter the species.',
    'Umwandeln geht nur mit Verbindung.'
        => 'Converting needs a connection.',
    'Nur deine eigene Aufsammlung kannst du in einen Fund umwandeln.'
        => 'You can only turn your own collection into a find.',
    'Aus dieser Aufsammlung sind schon Arten eingetragen. Sie bleibt deshalb eine Aufsammlung.'
        => 'Species have already been entered from this collection. So it stays a collection.',
    'In Kultur seit'
        => 'In culture since',
    'Kultur beendet'
        => 'Culture ended',
    'Bestimmt am'
        => 'Identified on',
    'Erst wenn die Aufsammlung gespeichert ist'
        => 'Only once the collection is saved',
    'Noch keine Art bestimmt.'
        => 'No species identified yet.',
    'Ohne Art'
        => 'Without species',
    'bestimmt %s, nach %d Tagen'
        => 'identified %s, after %d days',
    'bestimmt %s'
        => 'identified %s',
    'Art angelegt. Trag den Namen ein, wenn du sie bestimmt hast.'
        => 'Species created. Enter the name once you have identified it.',
    'Arten lassen sich nur mit Verbindung anlegen.'
        => 'Species can only be created with a connection.',
    '%d Tage nach dem Sammeln'
        => '%d days after collecting',
    'Gesammelt am'
        => 'Collected on',
    'in Kultur'
        => 'in culture',
    'noch nicht in Kultur'
        => 'not in culture yet',
    'Aus Kultur'
        => 'From culture',
    'Aus Kultur gewachsen'
        => 'Grown in culture',
    'Aufsammlung %s öffnen'
        => 'Open collection %s',
    'Zur Aufsammlung'
        => 'To the collection',
    // Fundbrowser, the section „Kultur" (finds.js)
    'aus Kultur'
        => 'from culture',
    'Kultur'
        => 'Culture',
    'Aufsammlungen'
        => 'Collections',
    'aus Kultur, nach %d Tagen bestimmt'
        => 'from culture, identified after %d days',
    '%s · 1 Art'
        => '%s · 1 species',
    '%s · %d Arten'
        => '%s · %d species',
    '%d Aufsammlungen · %d Arten'
        => '%d collections · %d species',
    'In Kultur'
        => 'In culture',
    'Noch nicht in Kultur'
        => 'Not in culture yet',
    'Bezeichnung'
        => 'Name',
    'zum Beispiel Rehlosung am Waldrand'
        => 'for example roe deer droppings at the forest edge',
    'Die Arten, die du daraus bestimmt hast, gehen mit in deinen Papierkorb.'
        => 'The species you identified from it go into your trash with it.',
    // Mikroskopieren — the microscope camera through MicroCam (microscope.js, gallery_windows.php; 24.10.2026)
    'Mikroskopieren'
        => 'Microscope',
    'Welches Objektiv?'
        => 'Which objective?',
    'Welches Medium?'
        => 'Which medium?',
    'Objektiv'
        => 'Objective',
    'Ohne Maßstab'
        => 'No scale',
    'Belichtung'
        => 'Exposure',
    'Automatisch'
        => 'Automatic',
    'Helligkeit'
        => 'Brightness',
    'Verstärkung'
        => 'Gain',
    'Weißabgleich'
        => 'White balance',
    'Weißabgleich …'
        => 'White balance …',
    'Weißabgleich gesetzt.'
        => 'White balance set.',
    'Der Weißabgleich ging nicht.'
        => 'The white balance did not work.',
    'Erneut versuchen'
        => 'Try again',
    'Verbinde mit der Kamera …'
        => 'Connecting to the camera …',
    'Der Browser fragt nach Zugriff auf Geräte in diesem Netzwerk – bitte Erlauben.'
        => 'The browser asks for access to devices on this network – please click Allow.',
    'Die Frage steht oben links bei der Adressleiste. Sie kommt nur einmal. Danach erscheint das Bild von selbst.'
        => 'The question is at the top left by the address bar. It comes only once. After that the picture appears by itself.',
    'Das Kamera-Programm antwortet nicht.'
        => 'The camera program does not answer.',
    'Starte das Programm, das deine Kamera bereitstellt. Bei der BMS-Kamera am Mac ist das MicroCam, es steht dann oben in der Menüleiste. Falls der Browser den Zugriff auf Geräte in diesem Netzwerk abgelehnt hat: Klicke links in der Adressleiste auf das Schloss und erlaube ihn.'
        => 'Start the program that provides your camera. For the BMS camera on a Mac that is MicroCam; it then sits at the top in the menu bar. If the browser refused access to devices on this network: click the lock at the left of the address bar and allow it.',
    'Das Kamera-Programm lässt diese Seite nicht zu.'
        => 'The camera program does not allow this page.',
    'Es arbeitet nur mit wkpilz.de zusammen. Öffne YASAIMAPP dort.'
        => 'It only works with wkpilz.de. Open YASAIMAPP there.',
    'Das Kamera-Programm meldet einen Fehler.'
        => 'The camera program reports an error.',
    'Die Kamera ist nicht angesteckt.'
        => 'The camera is not plugged in.',
    'Die Kamera ist belegt.'
        => 'The camera is in use.',
    'Wahrscheinlich ist das Programm des Herstellers noch offen, bei BMS ist das BMS Pix 3. Schließe es, dann kommt das Bild.'
        => 'The maker\'s program is probably still open; for BMS that is BMS Pix 3. Close it, then the picture comes.',
    'Stecke die Kamera einmal ab und wieder an.'
        => 'Unplug the camera once and plug it in again.',
    'Die Verbindung zum Kamera-Programm ist abgerissen.'
        => 'The connection to the camera program broke off.',
    'Die Einstellung kam beim Kamera-Programm nicht an.'
        => 'The setting did not reach the camera program.',
    'Foto wird aufgenommen …'
        => 'Taking the photo …',
    'Mikroskop %s'
        => 'Microscope %s',
    'Foto verworfen.'
        => 'Photo discarded.',
    // Focus stacking — the shutter held (microscope.js, focusstack.js; 26.10.2026)
    'Stapel: %d Bilder'
        => 'Stack: %d pictures',
    'Stapel aus %d Bildern wird gerechnet …'
        => 'Computing the stack of %d pictures …',
    'Zu wenige Bilder für einen Stapel. Halte länger und dreh dabei am Feintrieb.'
        => 'Too few pictures for a stack. Hold longer and turn the fine focus meanwhile.',
    'Stapel: 1 Bild'
        => 'Stack: 1 picture',
    'Live'
        => 'Live',
    'Der Stapel ließ sich nicht rechnen.'
        => 'The stack could not be computed.',
    'Foto in der Cloud, auf %d Pixel verkleinert.'
        => 'Photo in the cloud, shrunk to %d pixels.',
    '%d Fotos in der Cloud, auf %d Pixel verkleinert.'
        => '%d photos in the cloud, shrunk to %d pixels.',
    '%d Fotos in der Cloud.'
        => '%d photos in the cloud.',
    // Einstellungen → Fotos → Mikroskop-Kamera (settings_content.php, microscopesettings.js; 24.10.2026)
    'Mikroskop-Kamera'
        => 'Microscope camera',
    'Hängt eine Mikroskop-Kamera an diesem Computer, kannst du direkt in YASAIMAPP mikroskopieren. Öffne einen Fund, dann Belege und „Mikroskopieren". Du siehst das Livebild. Jedes Foto geht mit dem Maßstab deines Objektivs in deine Cloud.'
        => 'If a microscope camera is attached to this computer, you can use the microscope directly in YASAIMAPP. Open a find, then Belege and "Microscope". You see the live picture. Every photo goes into your cloud with the scale of your objective.',
    'Sehe nach …'
        => 'Checking …',
    'Eine andere Kamera'
        => 'Another camera',
    'Anleitung herunterladen'
        => 'Download the guide',
    'Erprobt mit: BMS U3CMOS10000KPA am Mac.'
        => 'Tested with: BMS U3CMOS10000KPA on a Mac.',
    'Die Kamera ist belegt. Wahrscheinlich ist das Programm des Herstellers noch offen, bei BMS ist das BMS Pix 3.'
        => 'The camera is in use. The maker\'s program is probably still open; for BMS that is BMS Pix 3.',
    'Die Kamera meldet einen Fehler. Stecke sie einmal ab und wieder an.'
        => 'The camera reports an error. Unplug it once and plug it in again.',
    'Die Kamera ist hängen geblieben.'
        => 'The camera got stuck.',
    'Stecke sie ab, warte kurz und stecke sie wieder an. Das Bild kommt dann von selbst.'
        => 'Unplug it, wait a moment and plug it in again. The picture then comes by itself.',
    'Die Kamera ist hängen geblieben. Stecke sie ab, warte kurz und stecke sie wieder an.'
        => 'The camera got stuck. Unplug it, wait a moment and plug it in again.',
    'Das Bild kommt gleich von selbst wieder. Kommt es nicht, stecke die Kamera einmal ab und wieder an.'
        => 'The picture will come back by itself in a moment. If it does not, unplug the camera and plug it in again.',
    'Die Kamera verbindet sich gerade neu.'
        => 'The camera is reconnecting.',
    'Die Frage steht oben links bei der Adressleiste. Sie kommt nur einmal.'
        => 'The question is at the top left by the address bar. It comes only once.',
    'Auf diesem Computer antwortet kein Kamera-Programm.'
        => 'No camera program answers on this computer.',
    'Läuft das Programm, und der Browser hat den Zugriff auf Geräte in diesem Netzwerk abgelehnt? Dann klicke links in der Adressleiste auf das Schloss und erlaube ihn.'
        => 'Is the program running, and the browser refused access to devices on this network? Then click the lock at the left of the address bar and allow it.',
    'Kamera-Programm'
        => 'Camera program',
    'Kamera: %s, Fotos mit %d × %d Pixeln.'
        => 'Camera: %s, photos with %d × %d pixels.',
    'Kamera: %s.'
        => 'Camera: %s.',
    'Verbunden mit %s.'
        => 'Connected to %s.',
    // The setup, the webcam way and calibrating in the window (24.10.2026)
    'Kamera %d'
        => 'Camera %d',
    'Stecke die Mikroskop-Kamera an diesen Computer. Das Bild kommt dann von selbst.'
        => 'Plug the microscope camera into this computer. The picture then comes by itself.',
    'Der Browser darf die Kamera nicht benutzen.'
        => 'The browser may not use the camera.',
    'Klicke links in der Adressleiste auf das Kamera-Zeichen oder das Schloss und erlaube die Kamera.'
        => 'Click the camera sign or the lock at the left of the address bar and allow the camera.',
    'Die Webcam ist nicht angesteckt.'
        => 'The webcam is not plugged in.',
    'Stecke die Mikroskop-Kamera an diesen Computer und versuche es erneut.'
        => 'Plug the microscope camera into this computer and try again.',
    'Das Foto kam nicht von der Kamera.'
        => 'The photo did not come from the camera.',
    'Strecke im Bild: %d Pixel'
        => 'Stretch in the picture: %d pixels',
    'Lege das Objektmikrometer unter das Mikroskop und stelle seine Skala scharf. Dann klicke auf „Foto für die Eichung".'
        => 'Put the stage micrometer under the microscope and focus its scale. Then click "Photo for calibrating".',
    'Je länger die Strecke im Bild, desto genauer der Maßstab. Mit dem Mausrad vergrößerst du das Bild, mit gedrückter Maustaste verschiebst du es. Trage unten ein, wie lang die Strecke wirklich ist.'
        => 'The longer the stretch in the picture, the more exact the scale. The mouse wheel enlarges the picture, dragging with the mouse button held moves it. Enter below how long the stretch really is.',
    'Klicke im Bild auf den Anfang einer Strecke der Skala.'
        => 'Click the start of a stretch of the scale in the picture.',
    'Klicke jetzt auf das Ende der Strecke.'
        => 'Now click the end of the stretch.',
    'Strecke gesetzt. Ein weiterer Klick fängt sie neu an.'
        => 'Stretch set. Another click starts it anew.',
    'Ersetzen'
        => 'Replace',
    '„%s" gibt es schon'
        => '"%s" already exists',
    'Die Eichung von „%s" wird durch diese ersetzt. Willst du das?'
        => 'The calibration of "%s" will be replaced by this one. Do you want that?',
    'Gib dem Objektiv einen Namen, zum Beispiel 40x.'
        => 'Give the objective a name, for example 40x.',
    '„%s" ist geeicht. Das Eichbild liegt in deiner Cloud im Ordner „Kalibrierung".'
        => '"%s" is calibrated. The calibration picture is in your cloud in the folder "Kalibrierung".',
    'Fertig. Bei jedem Fund steht unter Belege jetzt „Mikroskopieren".'
        => 'Done. Every find now has "Microscope" under Belege.',
    '„Mikroskopieren" erscheint bei den Funden, sobald Kamera und Cloud eingerichtet sind.'
        => '"Microscope" appears at the finds as soon as camera and cloud are set up.',
    'Läuft das Programm, und der Browser hat den Zugriff auf Geräte in diesem Netzwerk abgelehnt? Dann klicke links in der Adressleiste auf das Schloss und erlaube ihn. Ist deine Kamera eine Webcam, nimm „Als Webcam einrichten".'
        => 'Is the program running, and the browser refused access to devices on this network? Then click the lock at the left of the address bar and allow it. If your camera is a webcam, use "Set up as webcam".',
    'Kamera: eingerichtet mit %s.'
        => 'Camera: set up with %s.',
    'Kamera: eingerichtet als Webcam „%s".'
        => 'Camera: set up as webcam "%s".',
    'Kamera'
        => 'Camera',
    'Dieser Computer hat keine Webcam, die der Browser sieht.'
        => 'This computer has no webcam the browser can see.',
    'Dann braucht deine Kamera ein Programm. Die Anleitung weiter unten hilft dabei.'
        => 'Then your camera needs a program. The guide further down helps with that.',
    'Wähle die Kamera, die am Mikroskop steckt. Ihr Bild steht darunter.'
        => 'Choose the camera on the microscope. Its picture is below.',
    'Diese Kamera lässt sich gerade nicht öffnen.'
        => 'This camera cannot be opened right now.',
    'Fotos mit %d × %d Pixeln.'
        => 'Photos with %d × %d pixels.',
    'Die Webcam liefert %d × %d statt %d × %d Pixel. Deine Vorlagen messen damit vielleicht falsch – richte die Webcam neu ein.'
        => 'The webcam delivers %d × %d instead of %d × %d pixels. Your presets may measure wrong with it – set the webcam up again.',
    'Kamera: noch nicht eingerichtet.'
        => 'Camera: not set up yet.',
    'Noch kein Objektiv geeicht. Klicke rechts neben „Objektiv" auf das Maßstab-Zeichen. Dann bekommt jedes Foto gleich seinen Maßstab.'
        => 'No objective calibrated yet. Click the scale symbol to the right of “Objective”. Then every photo gets its scale right away.',
    'Objektiv eichen'
        => 'Calibrate objective',
    'Name des Objektivs'
        => 'Name of the objective',
    'Länge der Strecke'
        => 'Length of the stretch',
    'Foto für die Eichung'
        => 'Photo for calibrating',
    'Neues Foto'
        => 'New photo',
    'Manche Mikroskop-Kameras arbeiten wie eine Webcam, die findet der Browser selbst. Andere brauchen ein kleines Programm auf dem Computer, für die BMS-Kamera am Mac ist das MicroCam.'
        => 'Some microscope cameras work like a webcam; the browser finds those by itself. Others need a small program on the computer; for the BMS camera on a Mac that is MicroCam.',
    'Auf diesem Computer einrichten'
        => 'Set up on this computer',
    'Kamera-Programm suchen'
        => 'Look for a camera program',
    'Als Webcam einrichten'
        => 'Set up as webcam',
    'Auf diesem Computer vergessen'
        => 'Forget on this computer',
    'Welche Kamera ist am Mikroskop?'
        => 'Which camera is on the microscope?',
    'Diese Kamera nehmen'
        => 'Use this camera',
    'Cloud: verbunden mit %s.'
        => 'Cloud: connected to %s.',
    'Cloud: noch keine verbunden.'
        => 'Cloud: none connected yet.',
    'Die Fotos vom Mikroskop gehen in voller Größe in deine Cloud. Verbinden kannst du sie im Reiter Cloud.'
        => 'The photos from the microscope go into your cloud in full size. You can connect it in the Cloud tab.',
    'Maßstab: %s geeicht.'
        => 'Scale: %s calibrated.',
    'Maßstab: noch kein Objektiv geeicht.'
        => 'Scale: no objective calibrated yet.',
    'Das brauchst du nicht zum Fotografieren, nur zum Messen. Eichen kannst du im Mikroskop-Fenster mit dem Maßstab-Zeichen neben „Objektiv": Objektmikrometer unterlegen, Foto machen, eine Strecke der Skala anklicken.'
        => 'You do not need this to take photos, only to measure. You can calibrate in the microscope window with the scale symbol beside “Objective”: put the stage micrometer underneath, take a photo, click a stretch of the scale.',
    'Findet YASAIMAPP deine Kamera weder als Webcam noch über ein Programm, kann eine KI das Programm mit dir zusammen bauen, zum Beispiel Claude oder ChatGPT Codex, auch unter Windows. Lade dazu die Anleitung herunter und gib sie der KI. Sie führt dich Schritt für Schritt, auch wenn du dich mit Computern wenig auskennst.'
        => 'If YASAIMAPP finds your camera neither as a webcam nor through a program, an AI can build the program together with you, for example Claude or ChatGPT Codex, on Windows too. Download the guide and give it to the AI. It leads you step by step, even if you know little about computers.',
    // The photo window's retouching tab (Ausbessern), 10.11.2026.
    'Ausbessern'
        => 'Retouch',
    'Reparieren'
        => 'Heal',
    'Kopieren'
        => 'Clone',
    'Reparieren %d'
        => 'Heal %d',
    'Kopieren %d'
        => 'Clone %d',
    // „Farbstich entfernen" at the foot of „Farbe", 14.11.2026.
    // „Farbmischer" under „Farbe", 22.11.2026 (Rot, Grün, Orange, Lila and Sättigung stand elsewhere already).
    'Farbmischer'
        => 'Colour mixer',
    'Farbton'
        => 'Hue',
    'Luminanz'
        => 'Luminance',
    'Gelb'
        => 'Yellow',
    'Aquamarin'
        => 'Aqua',
    'Blau'
        => 'Blue',
    'Magenta'
        => 'Magenta',
    // „Farbkorrektur nach Helligkeit", 22.11.2026 (Lichter, Tiefen, Stärke stand already).
    'Farbkorrektur'
        => 'Colour grading',
    'Mitteltöne'
        => 'Midtones',
    'Abgleich'
        => 'Balance',
    // „Rückgängig" beside the white balance of „Mikrofotos", 22.11.2026.
    'Temperatur und Tönung wieder so wie vor dem Weißabgleich'
        => 'Temperature and tint back to what they were before the white balance',
    // „Gradationskurve", 22.11.2026 (Rot, Grün, Blau stand already).
    'Gradationskurve'
        => 'Tone curve',
    'RGB'
        => 'RGB',
    'Kurve zurücksetzen'
        => 'Reset curve',
    'Eingang %d → Ausgang %d'
        => 'Input %d → output %d',
    'Loslassen nimmt den Punkt weg'
        => 'Let go to remove the point',
    'Klicken setzt einen Punkt, Ziehen verschiebt ihn. Doppelklick oder aus dem Feld ziehen nimmt ihn weg.'
        => 'Click sets a point, dragging moves it. Double-click or drag it out of the square to remove it.',
    'Farbstich'
        => 'Colour cast',
    // The pipette of „Farbstich", 22.11.2026 (dunkel and hell stand already).
    'Pipette: Klicke im Bild auf etwas, das weiß oder grau sein soll.'
        => 'Eyedropper: click on something in the picture that should be white or grey.',
    'Punkt %d'
        => 'Point %d',
    'mittel'
        => 'medium',
    'Diesen Punkt entfernen'
        => 'Remove this point',
    'Diese Stelle ist ausgebrannt. Klicke auf ein helles Grau, das noch Zeichnung hat.'
        => 'This spot is burned out. Click on a light grey that still shows detail.',
    'Diese Stelle ist zu dunkel, um ihre Farbe zu messen.'
        => 'This spot is too dark to measure its colour.',
    'Mehr als %d Punkte gehen nicht. Nimm erst einen weg.'
        => 'No more than %d points. Remove one first.',
    'Neu messen'
        => 'Measure again',
    'Stärke'
        => 'Strength',
    'Sucht die grauen Stellen im Bild und nimmt ihnen den Farbstich.'
        => 'Finds the grey places in the picture and takes the colour cast out of them.',
    'Den Farbstich wieder lassen'
        => 'Leave the colour cast as it was',
    'Das Bild ließ sich nicht messen.'
        => 'The picture could not be measured.',
    'Im Bild ist zu wenig Graues, um einen Farbstich zu messen.'
        => 'There is too little grey in the picture to measure a colour cast.',
    // The histogram over „Einstellungen", 14.11.2026.
    'Histogramm'
        => 'Histogram',
    // „Reparieren" became one tool with two kinds, 14.11.2026.
    'Angleichen'
        => 'Match',
    'Angleichen %d'
        => 'Match %d',
    'Weiche Kante'
        => 'Feather',
    'Stelle löschen'
        => 'Delete spot',
    'Doppelklick setzt zurück'
        => 'Double click resets it',
    'Struktur der Quelle, Helligkeit und Farbe der Umgebung'
        => 'The source\'s texture, the surroundings\' brightness and colour',
    'Die Quelle genau so übernehmen'
        => 'Take the source exactly as it is',
    'Mehr Stellen lassen sich an diesem Bild nicht ausbessern.'
        => 'No more spots can be retouched on this picture.',
    // The photo window's brush mask (Pinsel), 10.11.2026.
    'Pinsel'
        => 'Brush',
    'Pinsel %d'
        => 'Brush %d',
    'Malen'
        => 'Paint',
    'Radieren'
        => 'Erase',
    'Radieren (oder Alt halten)'
        => 'Erase (or hold Alt)',
    'Fluss'
        => 'Flow',
    'Pinsel löschen'
        => 'Delete brush',
    'Umkehren — außerhalb des Gemalten ändern'
        => 'Invert — change outside what is painted',
    'Mehr Pinselstriche passen an diesem Bild nicht mehr.'
        => 'No more brush strokes fit on this picture.',
    // Bearbeiten, tab Info: what the camera wrote into the file (10.11.2026).
    'Blende'
        => 'Aperture',
    'Belichtungszeit'
        => 'Shutter speed',
    'Brennweite'
        => 'Focal length',
    'Brennweite (Kleinbild)'
        => 'Focal length (35 mm equivalent)',
    'Belichtungskorrektur'
        => 'Exposure compensation',
    'Programm'
        => 'Program',
    'Manuell'
        => 'Manual',
    'Programmautomatik'
        => 'Program AE',
    'Zeitautomatik'
        => 'Aperture priority',
    'Blendenautomatik'
        => 'Shutter priority',
    'Kreativprogramm'
        => 'Creative program',
    'Actionprogramm'
        => 'Action program',
    'Landschaft'
        => 'Landscape',
    'Mittelwert'
        => 'Average',
    'Mittenbetont'
        => 'Center-weighted',
    'Mehrfach-Spot'
        => 'Multi-spot',
    'Mehrfeld'
        => 'Pattern',
    'Selektiv'
        => 'Partial',
    'Blitz'
        => 'Flash',
    'ausgelöst'
        => 'fired',
    'nicht ausgelöst'
        => 'did not fire',
    'manuell'
        => 'manual',
    'Aufgenommen'
        => 'Taken',
    'Fotograf'
        => 'Photographer',
    'Abmessungen'
        => 'Dimensions',
    'Dateigröße'
        => 'File size',
    'Wird gelesen …'
        => 'Reading …',
    'Die Datei konnte nicht gelesen werden.'
        => 'The file could not be read.',
    'In der Datei stehen keine Belichtungsdaten.'
        => 'The file holds no exposure data.',
    // No editing without a cloud (10.11.2026).
    'Bearbeiten geht nur bei Bildern, die in einer Cloud liegen.'
        => 'Editing works only for pictures that lie in a cloud.',
    // The „Entfernen" tool's files and its question (inpaint.js, 11.11.2026).
    '%s MB'
        => '%s MB',
    'Dieser Browser kann keine Dateien für später aufbewahren.'
        => 'This browser cannot keep files for later.',
    'Der Server hat die Datei %s nicht geliefert.'
        => 'The server did not deliver the file %s.',
    'Die Datei %s kam unvollständig an. Versuche es noch einmal.'
        => 'The file %s arrived incomplete. Please try again.',
    'KI-Werkzeug laden?'
        => 'Load the AI tool?',
    '„Entfernen“ arbeitet mit einer künstlichen Intelligenz. Dafür lädt dieses Gerät einmalig rund %s herunter und hebt sie auf. Am besten im WLAN.'
        => '“Remove” works with an artificial intelligence. For it, this device downloads about %s once and keeps them. Best over Wi-Fi.',
    'Die KI rechnet hier auf deinem Gerät. Deine Bilder schickt sie nirgendwohin.'
        => 'The AI computes here on your device. It sends your pictures nowhere.',
    'Unter Verwaltung → Offline → Offline-Daten kannst du die Dateien jederzeit wieder löschen.'
        => 'Under Administration → Offline → Offline data you can delete the files again at any time.',
    'Keine Verbindung. Versuche es mit Netz noch einmal — was schon geladen ist, bleibt.'
        => 'No connection. Try again with a network — what is already loaded stays.',
    'Das Laden hat nicht geklappt.'
        => 'Loading did not work.',
    'Nochmal'
        => 'Again',
    'Das KI-Werkzeug ist nicht auf diesem Gerät.'
        => 'The AI tool is not on this device.',
    'KI-Werkzeug'
        => 'AI tool',
    '„Entfernen“ beim Bearbeiten eines Bildes arbeitet mit einer künstlichen Intelligenz. Sie rechnet hier auf deinem Gerät und braucht dafür eigene Dateien. Das Gerät lädt sie beim ersten Benutzen, nachdem du zugestimmt hast — oder hier.'
        => '“Remove” when editing a picture works with an artificial intelligence. It computes here on your device and needs files of its own for that. The device loads them on first use, after you agreed — or here.',
    '%s von %s geladen'
        => '%s of %s loaded',
    'nicht auf diesem Gerät · %s'
        => 'not on this device · %s',
    'Die Verbindung ist abgebrochen. Versuche es noch einmal — was schon geladen ist, bleibt.'
        => 'The connection broke off. Try again — what is already loaded stays.',
    'Auf diesem Gerät ist nicht genug Platz frei.'
        => 'There is not enough free space on this device.',
    // „Entfernen“ in the photo window (11.11.2026).
    'Eine KI füllt die Stelle mit dem, was dahinter liegen könnte'
        => 'An AI fills the place with what could lie behind it',
    'Entfernen %d'
        => 'Remove %d',
    'KI startet …'
        => 'AI is starting …',
    'wird berechnet …'
        => 'being computed …',
    'Mehr Stellen lassen sich an diesem Bild nicht entfernen.'
        => 'No more places can be removed in this picture.',
    'Die KI konnte die Stelle nicht füllen.'
        => 'The AI could not fill the place.',
    'Füllung'
        => 'Fill',
    'Schnell'
        => 'Quick',
    'Die Füllung des schnellen Modells zeigen'
        => 'Show the quick model\'s fill',
    'Stark'
        => 'Strong',
    'Die Füllung des starken Modells zeigen'
        => 'Show the strong model\'s fill',
    'Noch einmal'
        => 'Once more',
    'Mit starkem Modell füllen'
        => 'Fill with the strong model',
    'Das starke Modell füllt die Stelle noch einmal – jedes Mal anders. Die schnelle Füllung bleibt aufgehoben.'
        => 'The strong model fills the place once more – differently every time. The quick fill is kept.',
    'Füllt die Stelle noch einmal mit dem starken Modell. Das dauert etwa eine halbe bis ganze Minute. Die schnelle Füllung bleibt aufgehoben.'
        => 'Fills the place again with the strong model. That takes about half a minute to a minute. The quick fill is kept.',
    'Starkes Modell rechnet … Schritt %d von %d'
        => 'Strong model at work … step %d of %d',
    'Starkes Modell rechnet … Ausschnitt %d von %d · Schritt %d von %d'
        => 'Strong model at work … section %d of %d · step %d of %d',
    // „Erweitern": a crop past the picture, its border filled by the models (photowindow.js, expandPanel).
    'Erweitern' => 'Expand',
    'Der Rahmen darf über das Bild hinausragen. Was außerhalb liegt, füllt die KI.'
        => 'The frame may reach past the picture. What lies outside is filled by the AI.',
    'Der Rand wird grob gefüllt …' => 'Filling the border roughly …',
    'Der Rand ließ sich nicht füllen.' => 'The border could not be filled.',
    'Der Rand ist für diese Feinheit zu groß.' => 'The border is too large for this fineness.',
    'Die Fläche bleibt, wie sie vorher war' => 'The area stays as it was',
    'Grob füllen' => 'Fill roughly',
    'Das schnelle Modell füllt den Rand in wenigen Sekunden.' => 'The quick model fills the border in a few seconds.',
    'Feinheit' => 'Fineness',
    'Grob' => 'Coarse',
    'Mittel' => 'Medium',
    'Fein' => 'Fine',
    'Sehr fein' => 'Very fine',
    'Eine Stufe: schnell, aber unscharf.' => 'One stage: quick, but blurred.',
    'Einmal in doppelter Größe nachgearbeitet.' => 'Worked over once at twice the size.',
    'Zweimal nachgearbeitet, jedes Mal in doppelter Größe.' => 'Worked over twice, each time at twice the size.',
    'Bis zur vollen Größe des Bildes nachgearbeitet.' => 'Worked over up to the picture\'s full size.',
    'Dieses Bild ist dafür zu klein: Es wird nicht feiner als bei der Stufe davor.' => 'This picture is too small for it: it gets no finer than at the stage before.',
    'Alle Flächen zusammen etwa %s.' => 'All areas together about %s.',
    'Dauert etwa %s-mal so lange wie „Grob“ – alle Flächen zusammen etwa %s.' => 'Takes about %s times as long as “Grob” – all areas together about %s.',
    'Freiheit beim Verfeinern' => 'Freedom in refining',
    'Wie weit das starke Modell beim Nacharbeiten vom Entwurf abweichen darf. Niedrig: bleibt nah am Entwurf und wird weniger scharf. Hoch: erfindet mehr Einzelheiten und kann vom Entwurf abweichen – und rechnet länger, weil jede Kachel mehr Schritte braucht. Doppelklick setzt zurück.'
        => 'How far the strong model may leave the draft when working it over. Low: stays near the draft and comes less sharp. High: invents more detail and may leave the draft – and takes longer, because every square needs more steps. A double click sets it back.',
    // „1" and „2" at every area of the border, and the strong model's row in the crop's bar (photowindow.js, expandPicksDraw, cropBarExpandRow).
    'Variante 1: schnelles Modell' => 'Variant 1: quick model',
    'Variante 2: starkes Modell' => 'Variant 2: strong model',
    'Variante 2: mit dem starken Modell füllen – etwa %s' => 'Variant 2: fill with the strong model – about %s',
    'Variante 2: mit dem starken Modell füllen' => 'Variant 2: fill with the strong model',
    'Variante 2 noch einmal füllen – jedes Mal anders' => 'Fill variant 2 once more – differently every time',
    'Freiheit' => 'Freedom',
    'Füllen abbrechen' => 'Stop filling',
    'Die Fläche ist gefüllt.' => 'The area is filled.',
    'Grob neu füllen' => 'Fill roughly again',
    'Das schnelle Modell füllt den Rand neu. Danach wählst du für jede Fläche zwischen Variante 1 und 2. Die jetzige Füllung geht dabei verloren.'
        => 'The quick model fills the border again. After that you choose between variant 1 and 2 for every area. The present fill is lost.',
    'Starkes Modell rechnet … Stufe %d von %d · Kachel %d von %d · Schritt %d von %d · noch etwa %s'
        => 'Strong model at work … stage %d of %d · square %d of %d · step %d of %d · about %s left',
    'Starkes Modell rechnet … Stufe %d von %d' => 'Strong model at work … stage %d of %d',
    '%d Sekunden' => '%d seconds',
    '%d Minuten' => '%d minutes',
    'Starkes Modell wird gestartet …'
        => 'Starting the strong model …',
    'Die andere Füllung ließ sich nicht laden.'
        => 'The other fill could not be loaded.',
    'Das starke Modell konnte die Stelle nicht füllen.'
        => 'The strong model could not fill the place.',
    'Eine Stelle ist mit dem starken Modell fertig.'
        => 'A place is finished with the strong model.',
    'Starkes Modell laden?'
        => 'Load the strong model?',
    'Das starke Modell füllt große Flächen besser: Es erfindet passende Strukturen, wo das schnelle Modell nur eine weiche Fläche liefert. Dafür lädt dieses Gerät einmalig rund %s herunter und hebt sie auf. Am besten im WLAN.'
        => 'The strong model fills large areas better: it invents fitting structures where the quick model only yields a soft patch. For that this device downloads about %s once and keeps them. Best on Wi-Fi.',
    'Eine Stelle braucht damit etwa eine halbe bis ganze Minute.'
        => 'One place takes about half a minute to a minute with it.',
    'Das starke Modell braucht die Grafikkarte, und dieser Browser gibt sie nicht frei.'
        => 'The strong model needs the graphics card, and this browser does not offer it.',
    'Das starke Modell ist nicht auf diesem Gerät.'
        => 'The strong model is not on this device.',
    'Entfernen – starkes Modell'
        => 'Remove – strong model',
    'Die KI füllt die Stelle mit dem, was dahinter liegen könnte'
        => 'The AI fills the place with what could lie behind it',
    'Methode'
        => 'Method',
    'Die Bearbeitung ließ sich nicht in der Cloud speichern. Sie bleibt auf diesem Gerät, und es wird von selbst wieder versucht.'
        => 'The edit could not be saved in the cloud. It stays on this device and is tried again by itself.',
    'Die Bearbeitung ist jetzt in der Cloud gespeichert.'
        => 'The edit is now saved in the cloud.',
    'Noch nicht in der Cloud gespeichert. Es wird von selbst wieder versucht – klicken, um es sofort zu versuchen.'
        => 'Not yet saved in the cloud. It is tried again by itself – click to try now.',
    // The AI's patches in the cloud (step 3, includes/develop_patches.php).
    'Unbrauchbare Bearbeitung.'
        => 'Unusable edit.',
    'Die Cloud des Bildes ist gerade nicht erreichbar.'
        => 'The picture\'s cloud cannot be reached right now.',
    'Die entfernten Stellen ließen sich nicht in der Cloud speichern.'
        => 'The removed places could not be saved in the cloud.',
    // More mended places than the old column held (migration 0193, 11.11.2026).
    'So viele ausgebesserte Stellen passen erst nach der Migration 0193.'
        => 'This many mended places fit only after migration 0193.',
    'Die entfernten Stellen ließen sich nicht laden.'
        => 'The removed places could not be loaded.',
    'Eine entfernte Stelle ist fertig.'
        => 'A removed place is done.',
    // „Ebenen“ — several renderings of one picture painted into one (assets/js/blend.js).
    'Ebenen'
        => 'Layers',
    'Stacking'
        => 'Stacking',
    'HDR'
        => 'HDR',
    'Die Renderings eines Stapels zusammenrechnen — Helicons Methoden A, B, C'
        => 'Join the renderings of one stack — Helicon\'s methods A, B, C',
    'Verschieden belichtete Bilder zusammenrechnen'
        => 'Join pictures exposed differently',
    'Welche Bilder übereinanderlegen?'
        => 'Which pictures to lay over each other?',
    'Nach oben: %s'
        => 'Up: %s',
    'Nach unten: %s'
        => 'Down: %s',
    'Übereinanderlegen'
        => 'Lay over each other',
    'Nicht dazunehmen'
        => 'Leave out',
    'Dazunehmen'
        => 'Take along',
    'Mehr als vier Bilder lassen sich nicht übereinanderlegen.'
        => 'No more than four pictures can be laid over each other.',
    'Bild %d von %d wird geladen …'
        => 'Loading picture %d of %d …',
    '„%s" ließ sich nicht laden.'
        => '“%s” could not be loaded.',
    '„%s" kann dieser Browser nicht anzeigen.'
        => 'This browser cannot show “%s”.',
    'Dieser Browser kann die Ebenen nicht zeichnen.'
        => 'This browser cannot draw the layers.',
    'Als Grundlage nehmen: %s'
        => 'Use as the ground: %s',
    'Rückgängig'
        => 'Undo',
    'Maske leeren'
        => 'Empty the mask',
    'Ebenen auflösen'
        => 'Dissolve the layers',
    'Auflösen'
        => 'Dissolve',
    'Verrechnen'
        => 'Merge',
    '%d × %d Pixel'
        => '%d × %d pixels',
    'Ebenen auflösen?'
        => 'Dissolve the layers?',
    'Die Ebenen von %s werden aufgelöst. Was du eingemalt, ausgerichtet und eingestellt hast, geht verloren. Die Bilder selbst bleiben, wie sie sind.'
        => 'The layers of %s are dissolved. What you painted in, aligned and set is lost. The pictures themselves stay as they are.',
    'Die Ebenen ließen sich nicht auflösen.'
        => 'The layers could not be dissolved.',
    'Maske'
        => 'Mask',
    'Füllen'
        => 'Fill',
    'Ergebnis'
        => 'Result',
    'Ergebnis (Taste 0)'
        => 'Result (key 0)',
    'Nur „%s" (Taste %s)'
        => 'Only “%s” (key %s)',
    'Ausblenden: %s'
        => 'Hide: %s',
    'Einblenden: %s'
        => 'Show: %s',
    'Grundlage'
        => 'Ground',
    'Maske zeigen'
        => 'Show the mask',
    'Maske zeigen (Taste M)'
        => 'Show the mask (key M)',
    '%d × %d, gezeigt in %d × %d'
        => '%d × %d, shown at %d × %d',
    'Bild %d'
        => 'Picture %d',
    'noch nichts eingemalt'
        => 'nothing painted in yet',
    'unter %d %% eingemalt'
        => 'under %d %% painted in',
    '%d %% eingemalt'
        => '%d %% painted in',
    // „Ebenen“ — the work kept (includes/layer_sets.php, migration 0191).
    'Weitermachen: %s'
        => 'Go on: %s',
    'Arbeitsstand verwerfen'
        => 'Discard the work',
    'Arbeitsstand verwerfen?'
        => 'Discard the work?',
    'Was du bei „%s" eingemalt hast, geht verloren. Die Bilder selbst bleiben, wie sie sind.'
        => 'What you painted in at “%s” is lost. The pictures themselves stay as they are.',
    'Der Arbeitsstand ließ sich nicht verwerfen.'
        => 'The work could not be discarded.',
    'Der Arbeitsstand wird geladen …'
        => 'Loading the work …',
    'Der Arbeitsstand ließ sich nicht laden. Versuch es gleich noch einmal.'
        => 'The work could not be loaded. Try again in a moment.',
    'Die Maske ließ sich nicht speichern.'
        => 'The mask could not be saved.',
    'Der Arbeitsstand ließ sich nicht speichern.'
        => 'The work could not be saved.',
    'Der Arbeitsstand wird gerade nicht gespeichert — keine Verbindung.'
        => 'The work is not being saved right now — no connection.',
    'Der Arbeitsstand wird noch nicht gespeichert — dafür fehlt die Migration 0191.'
        => 'The work is not saved yet — migration 0191 is missing.',
    'wird gespeichert …'
        => 'saving …',
    'noch nicht gespeichert'
        => 'not saved yet',
    'gespeichert'
        => 'saved',
    'Der Arbeitsstand der Ebenen braucht die Migration 0191 — sie ist noch nicht eingespielt.'
        => 'Keeping the layers\' work needs migration 0191 — it has not been applied yet.',
    'Fund nicht gefunden.'
        => 'Find not found.',
    'Die Maske kam nicht als Bild an.'
        => 'The mask did not arrive as a picture.',
    'Die Maske ließ sich nicht in der Cloud speichern.'
        => 'The mask could not be saved in the cloud.',
    'Unbrauchbare Ebenen.'
        => 'Unusable layers.',
    'Ebenen gehen nur mit deinen eigenen Bildern an diesem Fund.'
        => 'Layers only work with your own pictures on this find.',
    'Hake links zwei bis vier Bilder an.'
        => 'Tick two to four pictures at the left.',
    // „Ebenen“ — the layers computed into one picture (blend.js, mergeRun).
    'Wird berechnet … %d %%'
        => 'Computing … %d %%',
    'Das Bild wird gespeichert …'
        => 'Saving the picture …',
    'Wird hochgeladen … %d %%'
        => 'Uploading … %d %%',
    'Das neue Bild liegt beim Fund.'
        => 'The new picture is at the find.',
    'Einzelbilder löschen?'
        => 'Delete the single pictures?',
    'Das neue Bild liegt jetzt beim Fund. Sollen die %d Einzelbilder gelöscht werden — %s? Das lässt sich hier nicht rückgängig machen. pCloud, Google Drive und OneDrive legen gelöschte Dateien in ihren Papierkorb.'
        => 'The new picture is at the find now. Shall the %d single pictures be deleted — %s? This cannot be undone here. pCloud, Google Drive and OneDrive lay deleted files into their trash.',
    'Einzelbilder löschen'
        => 'Delete the single pictures',
    'Behalten'
        => 'Keep',
    'Die Einzelbilder werden gelöscht …'
        => 'Deleting the single pictures …',
    'Nicht gelöscht: %s. Lösche sie von Hand.'
        => 'Not deleted: %s. Delete them by hand.',
    'Das Bild ließ sich nicht verrechnen.'
        => 'The picture could not be computed.',
    'Zu einem Bild verrechnen'
        => 'Compute into one picture',
    'Erst wenn etwas eingemalt ist.'
        => 'Only once something is painted in.',
    'Zusammengesetzt durch YASAIMAPP am %s aus %d Aufnahmen desselben Motivs (%d × %d Pixel). Grundlage: %s.'
        => 'Put together by YASAIMAPP on %s from %d shots of the same subject (%d × %d pixels). Ground: %s.',
    'Zusammengesetzt durch YASAIMAPP am %s aus %d Aufnahmen desselben Motivs (%d × %d Pixel).'
        => 'Put together by YASAIMAPP on %s from %d shots of the same subject (%d × %d pixels).',
    // „Ebenen“ — the proposal from sharpness, and the eraser on the Option key (blend.js).
    'Vorschlag aus der Schärfe'
        => 'Proposal from sharpness',
    'Die Schärfe wird gemessen …'
        => 'Measuring the sharpness …',
    'Die Schärfe ließ sich nicht messen.'
        => 'The sharpness could not be measured.',
    // „Ebenen“ — renderings cut differently, aligned; the proposal from brightness (blend.js, align.js, 11.11.2026).
    'Ausrichten: die gewählte Ebene von selbst auf die anderen ausrichten'
        => 'Align: the chosen layer onto the others by itself',
    'Nachjustieren'
        => 'Adjust',
    'Nachjustieren: Stellen anklicken, die in beiden Bildern dieselben sind'
        => 'Adjust: click places that are the same in both pictures',
    'Die Bilder ließen sich nicht zum Nachjustieren vorbereiten.'
        => 'The pictures could not be made ready for adjusting.',
    'Die Bilder werden vorbereitet …'
        => 'Getting the pictures ready …',
    'Punkt %d: Klicke dieselbe Stelle in „%s" an.'
        => 'Point %d: click the same place in “%s”.',
    'Punkt %d: Klicke eine Stelle in „%s" an.'
        => 'Point %d: click a place in “%s”.',
    'Dieses Bild zeigen (Shift wechselt)'
        => 'Show this picture (Shift switches)',
    'Letzten Punkt löschen'
        => 'Delete the last point',
    'beim Nachjustieren: das andere Bild zeigen'
        => 'while adjusting: show the other picture',
    'Wird ausgerichtet …'
        => 'Aligning …',
    'Die Bilder werden ausgerichtet …'
        => 'Aligning the pictures …',
    'Bild %d von %d wird ausgerichtet …'
        => 'Aligning picture %d of %d …',
    '„%s" ließ sich nicht von selbst ausrichten. Justiere es mit Punkten nach.'
        => '“%s” could not be aligned by itself. Adjust it with points.',
    'Vorschlag aus der Helligkeit'
        => 'Proposal from brightness',
    'Bei dieser Schwelle ist nichts markiert. Schiebe die Schwelle nach links.'
        => 'Nothing is marked at this threshold. Move the threshold to the left.',
    'Vorschlag aus der Helligkeit: markiert, wo das Bild unter dieser Ebene fast ausgebrannt ist'
        => 'Suggestion from brightness: marks where the picture under this layer is almost blown out',
    'Die Helligkeit wird gemessen …'
        => 'Measuring the brightness …',
    'Die Helligkeit ließ sich nicht messen.'
        => 'The brightness could not be measured.',
    'Die dunklen Bereiche markieren statt der hellen'
        => 'Mark the dark areas instead of the bright ones',
    'Schwelle'
        => 'Threshold',
    'Radieren (oder Option-Taste halten, Taste X wechselt)'
        => 'Erase (or hold the Option key; key X switches)',
    'Einpassen (Taste Y)'
        => 'Fit (key Y)',
    // The keys made one system (assets/js/shortcuts.js, 11.11.2026).
    'Tasten'
        => 'Keys',
    'Überall'
        => 'Everywhere',
    'diese Übersicht'
        => 'this overview',
    'Werkzeug beenden, zurück'
        => 'end the tool, go back',
    'Datenschutz' => 'Privacy',
    'Der Schlüsselweg ließ sich nicht speichern. Dafür braucht es eine Verbindung.' => 'The keying way could not be saved. That needs a connection.',
    'Der Text der Datenschutzerklärung fehlt auf dem Server. Bitte gib uns Bescheid.' => 'The text of the privacy statement is missing on the server. Please let us know.',
    'Datenschutzerklärung' => 'Privacy statement',
    'im Vollbild statt Esc'
        => 'instead of Esc in full screen',
    'Vollbild'
        => 'Full screen',
    'Vollbild beenden'
        => 'Leave full screen',
    'Vollbild (im Vollbild: ^ statt Esc)'
        => 'Full screen (in full screen: ^ instead of Esc)',
    '%s (Taste %s)'
        => '%s (key %s)',
    'Malen (Taste B)'
        => 'Paint (key B)',
    'Radieren (Taste E, Option halten, X wechselt)'
        => 'Erase (key E, hold Option, X switches)',
    'Füllen (Taste G)'
        => 'Fill (key G)',
    'Zeichnungen ausgeblendet — M zeigt sie wieder.'
        => 'Drawings hidden — M shows them again.',
    'Zeichnungen wieder eingeblendet.'
        => 'Drawings shown again.',
    'einpassen'
        => 'fit',
    'Leertaste halten'
        => 'hold Space',
    'Bild verschieben'
        => 'move the picture',
    'Zeichnungen aus- und einblenden'
        => 'hide and show the drawings',
    'O halten'
        => 'hold O',
    'das Original zeigen'
        => 'show the original',
    'Shift + Mausrad'
        => 'Shift + wheel',
    'Pinselgröße'
        => 'brush size',
    'Ctrl + Mausrad'
        => 'Ctrl + wheel',
    'weiche Kante'
        => 'soft edge',
    'Entf'
        => 'Del',
    'Enter'
        => 'Enter',
    'gebogene Länge abschließen'
        => 'finish the curved length',
    'Hilfe zum Werkzeug'
        => 'Help for the tool',
    'gewählte Stelle löschen'
        => 'delete the chosen spot',
    'neue Ellipse'
        => 'new ellipse',
    'neuer Verlauf'
        => 'new gradient',
    'neuer Pinsel'
        => 'new brush',
    'Malen und Radieren wechseln'
        => 'switch paint and erase',
    'Option halten'
        => 'hold Option',
    'Malen und Radieren wechseln, solange gehalten'
        => 'swap painting and erasing while held',
    'Neue Ellipse (Taste 1)'
        => 'New ellipse (key 1)',
    'Neuer Verlauf (Taste 2)'
        => 'New gradient (key 2)',
    'Neuer Pinsel (Taste 3)'
        => 'New brush (key 3)',
    'Ergebnis (die Zahl der gezeigten Ebene noch einmal)'
        => 'Result (the shown layer\'s number again)',
    'eine Ebene allein, noch einmal: das Ergebnis'
        => 'one layer alone, again: the result',
    'Cmd + Z'
        => 'Cmd + Z',
    'Seite einpassen'
        => 'fit the page',
    'Seite verschieben'
        => 'move the page',
    'Gewähltes löschen'
        => 'delete the chosen',
    'Ausschnitt schließen'
        => 'close the cutout',
    'in die Suche'
        => 'into the search',
    'zeigen'
        => 'show',
    'Drehung'
        => 'Rotation',
    'R halten + Mausrad'
        => 'hold R + wheel',
    'Quelle der gewählten Stelle drehen'
        => 'turn the chosen spot\'s source',
    'Die KI wird gestartet … (einmal je Besuch, etwa 15 Sekunden)'
        => 'The AI is starting … (once per visit, about 15 seconds)',

    // Blocking, reporting and the rules (23.11.2026, migration 0200).
    'Blockieren'
        => 'Block',
    '%s blockieren'
        => 'Block %s',
    '%s blockieren?'
        => 'Block %s?',
    'Ihr könnt euch danach nicht mehr anfragen und findet euch in der Suche nicht mehr. Eure Funde bleiben, wo sie sind. Unter „Blockierte Personen" kannst du das wieder aufheben.'
        => 'Afterwards neither of you can send the other a request, and you no longer find each other in the search. Your finds stay where they are. You can undo this under "Blocked people".',
    '%s ist blockiert. Ihr seid keine Freunde mehr und könnt euch nicht mehr anfragen.'
        => '%s is blocked. You are no longer friends and cannot send each other requests.',
    'Blockierte Personen'
        => 'Blocked people',
    'blockiert am %s'
        => 'blocked on %s',
    'Blockierung aufheben'
        => 'Unblock',
    'Blockierung von %s aufheben'
        => 'Unblock %s',
    'Die Blockierung ist aufgehoben.'
        => 'The block has been lifted.',
    'Melden'
        => 'Report',
    '%s melden'
        => 'Report %s',
    'Wenn dich jemand belästigt, beleidigt oder etwas Verbotenes zeigt, sag es dem Betreiber von YASAIMAPP. Er bekommt deine Nachricht per E-Mail und kann das Konto sperren.'
        => 'If someone harasses or insults you, or shows something forbidden, tell the operator of YASAIMAPP. The operator gets your message by e-mail and can lock the account.',
    'Die Freundschaft bleibt dabei bestehen. Willst du mit der Person nichts mehr zu tun haben, blockiere sie zusätzlich.'
        => 'The friendship stays as it is. If you want nothing more to do with this person, block them as well.',
    'Was ist passiert?'
        => 'What happened?',
    'Meldung schicken'
        => 'Send report',
    'Schreib bitte kurz, was passiert ist.'
        => 'Please write briefly what happened.',
    'Danke. Deine Meldung ist an den Betreiber verschickt.'
        => 'Thank you. Your report has been sent to the operator.',
    'Die Meldung konnte nicht verschickt werden. Bitte versuch es später noch einmal.'
        => 'The report could not be sent. Please try again later.',
    'Regeln'
        => 'Rules',
    'Der Text der Regeln fehlt auf dem Server. Bitte gib uns Bescheid.'
        => 'The text of the rules is missing on the server. Please let us know.',
    'Bitte lies die Regeln und hak an, dass du dich daran hältst.'
        => 'Please read the rules and tick that you will keep to them.',
    'Ich halte mich an die <a href="%s" target="_blank" rel="noopener">Regeln</a>.'
        => 'I will keep to the <a href="%s" target="_blank" rel="noopener">rules</a>.',

    // Fund · Mikroskop · Schlüssel — the find's three areas (24.11.2026).
    'Bereich'
        => 'Area',
    // The user side's last gaps, closed in one sweep (24.11.2026): the clouds'
    // and services' messages, the file imports, the find report, the
    // general error page, help paragraphs rewritten since their translation.
    '<strong>Artenliste:</strong> mit dem Haken schlägt das Titelfeld eines Punktes wissenschaftliche Namen vor, und die Fundfelder darunter kommen dazu. Für die Listen der Reiche ist er gesetzt. Darunter fragt das Fenster: <strong>Was enthält diese Liste?</strong> Du hakst die Gruppen an — Pilze, Flechten, Pflanzen, die Tiergruppen. Bei „Art" kommen nur Namen aus diesen Gruppen, und ihre Namen kommen von selbst aufs Gerät, damit die Vorschläge auch ohne Empfang gehen (<a href="#arten">%s</a>).'
        => '<strong>Species list:</strong> with the tick, a point\'s title field suggests scientific names, and the find fields below come with it. For the lists of the kingdoms it is set. Below it the window asks: <strong>Was enthält diese Liste?</strong> You tick the groups — fungi, lichens, plants, the animal groups. In "Art" only names from these groups come up, and their names come onto the device by themselves, so the suggestions work without reception too (<a href="#arten">%s</a>).',
    '<strong>Die Art.</strong> Das erste Feld heißt „Art" — in jeder Liste. In einer Artenliste schlägt es beim Tippen wissenschaftliche Namen aus der Referenz vor, mit dem Status der Roten Liste dahinter. Aus welchen Artengruppen die Vorschläge kommen, steht bei der Liste, in der der Fund liegt — in der Pilzliste Pilze, in der Flechtenliste Flechten (<a href="#arten">%s</a>). In der Verwaltung unter Einstellungen → Arten schaltest du „Schon gefundene Arten zuerst vorschlagen" ein — dann stehen die Arten aus deinen und den mit dir geteilten Funden oben in der Liste.'
        => '<strong>The species.</strong> The first field is called "Art" — in every list. In a species list it suggests scientific names from the reference while you type, with the Red List status behind them. Which species groups the suggestions come from is set at the list the find lies in — fungi in the fungi list, lichens in the lichen list (<a href="#arten">%s</a>). In the administration under Einstellungen → Arten you switch on "Schon gefundene Arten zuerst vorschlagen" — then the species from your finds and from those shared with you stand at the top of the list.',
    '<strong>Die Felder der Liste:</strong> was die Liste, in der der Fund liegt, an Feldern mitbringt — hier die einer Artenliste: Finder, Bestimmer, Bestimmungssicherheit, Anzahl, Substrat, Begleitvegetation, Geruch, Geschmack, Sporenpulverfarbe, Beleg-Nr., Fundbeschreibung. Liegt der Fund in mehreren Listen, stehen die Felder aller hier, jedes einmal. Welche Felder eine Liste hat, legst du in ihrem Fenster fest (<a href="#listen">%s</a>). Substrat / Wirt schlägt beim Tippen Pflanzen, Insekten und Pilze vor, Begleitvegetation nur Pflanzen; eigene Felder, die als Artfeld markiert sind, schlagen dieselben Gruppen vor wie der Titel (<a href="#arten">%s</a>). In einem mehrzeiligen Feld zählt jede Zeile als eigener Name.'
        => '<strong>The list\'s fields:</strong> the fields the list the find lies in brings along — here those of a species list: finder, identifier, certainty, count, substrate, companion vegetation, odour, taste, spore print colour, voucher number, find description. If the find lies in several lists, the fields of all of them stand here, each once. Which fields a list has you set in its window (<a href="#listen">%s</a>). Substrat / Wirt suggests plants, insects and fungi while you type, Begleitvegetation only plants; own fields marked as species fields suggest the same groups as the title (<a href="#arten">%s</a>). In a multi-line field every line counts as a name of its own.',
    'Welche <strong>Artengruppen</strong> das Feld „Art" im Punktfenster vorschlägt (<a href="#fenster-text">%s</a>, Punkt 1), legst du bei jeder Liste fest: im Fenster „Liste bearbeiten" unter „Was enthält diese Liste?". In der Pilzliste kommen dann nur Pilze, in der Flechtenliste nur Flechten. Substrat / Wirt schlägt Pflanzen, Insekten und Pilze vor, Begleitvegetation nur Pflanzen. Die Namen aller Gruppen, die deine Listen und die mit dir geteilten brauchen, kommen sofort aufs Gerät — so gehen die Vorschläge auch im Wald ohne Empfang. Braucht keine Liste eine Gruppe mehr, werden ihre Namen wieder entfernt. Hier unter Einstellungen → Arten steht, woher die Namen kommen.'
        => 'Which <strong>species groups</strong> the field "Art" in the point window suggests (<a href="#fenster-text">%s</a>, item 1) you set at each list: in the window "Liste bearbeiten" under "Was enthält diese Liste?". The fungi list then offers only fungi, the lichen list only lichens. Substrat / Wirt suggests plants, insects and fungi, Begleitvegetation only plants. The names of all groups your lists and those shared with you need come onto the device at once — so the suggestions work in the woods without reception too. Once no list needs a group any more, its names are removed again. Here under Einstellungen → Arten it says where the names come from.',
    'Nur lesen.'
        => 'Read only.',
    'Diesen Dienst gibt es hier nicht.'
        => 'This service does not exist here.',
    'Welches Bild?'
        => 'Which picture?',
    'Welches Stück welcher Datei?'
        => 'Which piece of which file?',
    'Diese Offline-Datei gibt es hier nicht.'
        => 'This offline file does not exist here.',
    'Die Offline-Datei ist gerade nicht erreichbar.'
        => 'The offline file cannot be reached right now.',
    'Punktfenster ausklappen'
        => 'Expand the point window',
    'Punktfenster einklappen'
        => 'Collapse the point window',
    'Bearbeitung %d von %s — gilt überall (Taste V wechselt)'
        => 'Edit %d by %s — valid everywhere (key V switches)',
    'Auswertung'
        => 'Evaluation',
    'Tagestour'
        => 'Day tour',
    'Google Drive ist auf diesem Server nicht eingerichtet.'
        => 'Google Drive is not set up on this server.',
    'Google antwortet nicht. Später noch einmal versuchen.'
        => 'Google does not answer. Try again later.',
    'Google antwortet unverständlich.'
        => 'Google gives an answer that cannot be read.',
    'Google nennt kein Zugriffstoken.'
        => 'Google names no access token.',
    'Google hat kein Dauer-Token ausgestellt. Bitte noch einmal verbinden und der Abfrage erneut zustimmen.'
        => 'Google issued no lasting token. Please connect again and agree to the request once more.',
    'Google Drive antwortet nicht. Später noch einmal versuchen.'
        => 'Google Drive does not answer. Try again later.',
    'Google Drive antwortet unverständlich.'
        => 'Google Drive gives an answer that cannot be read.',
    'Google Drive nennt für „%s" keine Ordnerkennung.'
        => 'Google Drive names no folder id for "%s".',
    'Google Drive nimmt die Datei gerade nicht an.'
        => 'Google Drive is not taking the file right now.',
    'Google Drive nennt keine Dateikennung.'
        => 'Google Drive names no file id.',
    'Google Drive hat %d von %d Bytes angenommen.'
        => 'Google Drive took %d of %d bytes.',
    'Google Drive öffnet keine Hochlade-Sitzung (%s).'
        => 'Google Drive opens no upload session (%s).',
    'OneDrive ist auf diesem Server nicht eingerichtet.'
        => 'OneDrive is not set up on this server.',
    'Microsoft antwortet nicht. Später noch einmal versuchen.'
        => 'Microsoft does not answer. Try again later.',
    'Microsoft antwortet unverständlich.'
        => 'Microsoft gives an answer that cannot be read.',
    'Microsoft nennt kein Zugriffstoken.'
        => 'Microsoft names no access token.',
    'Microsoft hat kein Dauer-Token ausgestellt. Bitte noch einmal verbinden und der Abfrage erneut zustimmen.'
        => 'Microsoft issued no lasting token. Please connect again and agree to the request once more.',
    'OneDrive antwortet nicht. Später noch einmal versuchen.'
        => 'OneDrive does not answer. Try again later.',
    'OneDrive antwortet unverständlich.'
        => 'OneDrive gives an answer that cannot be read.',
    'In OneDrive liegt schon eine Datei namens „%s", wo ein Ordner gebraucht wird.'
        => 'In OneDrive there is already a file called "%s" where a folder is needed.',
    'OneDrive nennt für „%s" keine Ordnerkennung.'
        => 'OneDrive names no folder id for "%s".',
    'OneDrive nimmt die Datei gerade nicht an.'
        => 'OneDrive is not taking the file right now.',
    'OneDrive nennt keine Dateikennung.'
        => 'OneDrive names no file id.',
    'OneDrive hat %d von %d Bytes angenommen.'
        => 'OneDrive took %d of %d bytes.',
    'OneDrive lehnt das Löschen ab (HTTP %d).'
        => 'OneDrive refuses the deletion (HTTP %d).',
    'OneDrive nennt keine Kennung für den App-Ordner.'
        => 'OneDrive names no id for the app folder.',
    'OneDrive öffnet keine Hochlade-Sitzung.'
        => 'OneDrive opens no upload session.',
    'Der S3-Endpunkt muss über HTTPS auf Port 443 erreichbar sein.'
        => 'The S3 endpoint must be reachable over HTTPS on port 443.',
    'Den S3-Endpunkt gibt es nicht — der Name löst sich nicht auf.'
        => 'This S3 endpoint does not exist — the name does not resolve.',
    'Dieser S3-Endpunkt liegt nicht im öffentlichen Internet und wird nicht angesprochen.'
        => 'This S3 endpoint is not on the public internet and is not contacted.',
    'Der S3-Speicher antwortet nicht. Ist der Endpunkt richtig?'
        => 'The S3 storage does not answer. Is the endpoint right?',
    'Der S3-Speicher antwortet unverständlich.'
        => 'The S3 storage gives an answer that cannot be read.',
    'Der S3-Speicher hat %d von %d Bytes angenommen.'
        => 'The S3 storage took %d of %d bytes.',
    'Der Ordnerpfad ist zu lang für einen S3-Schlüssel.'
        => 'The folder path is too long for an S3 key.',
    'pCloud nennt keine Kennung für den App-Ordner.'
        => 'pCloud names no id for the app folder.',
    'pCloud gibt das Bild nicht heraus (%s).'
        => 'pCloud does not hand out the picture (%s).',
    'pCloud gibt keine Vorschau heraus (HTTP %d)'
        => 'pCloud hands out no preview (HTTP %d)',
    'pCloud gibt die Datei nicht heraus (%s).'
        => 'pCloud does not hand out the file (%s).',
    '%s ist verbunden — als %s.'
        => '%s is connected — as %s.',
    '%s ist verbunden.'
        => '%s is connected.',
    'Der Rücksprung passt nicht zu dieser Sitzung. Bitte noch einmal auf „Verbinden" drücken.'
        => 'The return does not match this session. Please press "Verbinden" once more.',
    'Abgebrochen — es wurde nichts verbunden.'
        => 'Cancelled — nothing was connected.',
    'Der Höhendienst antwortet nicht.'
        => 'The elevation service does not answer.',
    'Der Höhendienst hat unverständlich geantwortet.'
        => 'The elevation service gave an answer that cannot be read.',
    'Außerhalb des deutschen Höhenmodells.'
        => 'Outside the German elevation model.',
    'Der Höhendienst meldet einen Fehler.'
        => 'The elevation service reports an error.',
    'Der Höhendienst hat keinen brauchbaren Wert geliefert.'
        => 'The elevation service delivered no usable value.',
    'Der weltweite Höhendienst antwortet nicht.'
        => 'The worldwide elevation service does not answer.',
    'Der weltweite Höhendienst hat keinen brauchbaren Wert geliefert.'
        => 'The worldwide elevation service delivered no usable value.',
    'Der Ortsdienst antwortet nicht.'
        => 'The place service does not answer.',
    'Der Ortsdienst hat unverständlich geantwortet.'
        => 'The place service gave an answer that cannot be read.',
    'Dort liegt keine Gemeinde.'
        => 'There is no municipality there.',
    'Ohne Staat ist der Rest wertlos.'
        => 'Without a country the rest is worthless.',
    'Der Schutzgebietsdienst antwortet nicht.'
        => 'The protected-area service does not answer.',
    'Der Freigabe-Link ist kein pCloud-Link.'
        => 'The share link is not a pCloud link.',
    'Stück nicht lesbar: HTTP %d'
        => 'Piece not readable: HTTP %d',
    'Keine Verbindung zu %s'
        => 'No connection to %s',
    'Datei nicht lesbar: %s'
        => 'File not readable: %s',
    'KMZ-Datei nicht lesbar: %s'
        => 'KMZ file not readable: %s',
    'ZIP-Datei nicht lesbar: %s'
        => 'ZIP file not readable: %s',
    'Das sieht nicht nach einer KML-Datei aus.'
        => 'That does not look like a KML file.',
    'In der KMZ-Datei steckt keine KML-Datei.'
        => 'There is no KML file inside the KMZ file.',
    'Das sieht nicht nach einer GPX-Datei aus.'
        => 'That does not look like a GPX file.',
    'Das sieht nicht nach einer GeoJSON-Datei aus.'
        => 'That does not look like a GeoJSON file.',
    'Die Tabelle hat keine Datenzeilen.'
        => 'The table has no data rows.',
    'Der Import braucht einen Namen.'
        => 'The import needs a name.',
    'Diesen Import gibt es nicht (mehr).'
        => 'This import does not exist (any more).',
    'Dieses Format gibt es nicht.'
        => 'This format does not exist.',
    'Ein Ordner auf dem Server lässt sich nicht anlegen.'
        => 'A folder on the server cannot be created.',
    'Der S3-Speicher'
        => 'The S3 storage',
    '%s lehnt ab (%s).'
        => '%s refuses (%s).',
    'Der Speicher ist voll: %s nimmt nichts mehr an.'
        => 'The storage is full: %s takes nothing more.',
    'Diese Artenliste trägt noch Funde. Erst die Funde verschieben oder den Haken „Artenliste" herausnehmen, dann lässt sie sich löschen.'
        => 'This species list still holds finds. Move the finds first or take out the tick "Artenliste", then it can be deleted.',
    'Autolisten brauchen Migration 0052 — die ist hier noch nicht eingespielt.'
        => 'Auto lists need migration 0052 — it has not been applied here yet.',
    'Ungültige Koordinaten.'
        => 'Invalid coordinates.',
    'Ein Gebiet braucht drei bis 200 Ecken.'
        => 'An area needs three to 200 corners.',
    'Ungültige Koordinate in der Umrandung.'
        => 'Invalid coordinate in the outline.',
    'Zu diesem Anker fehlt der Ort.'
        => 'This anchor is missing its place.',
    'Zu diesem Feld gibt es keinen Anker (mehr).'
        => 'There is no anchor (any more) for this field.',
    'Der Rettungsanker braucht die Migration 0044.'
        => 'The rescue anchor needs migration 0044.',
    'Die Gruppe braucht einen Namen.'
        => 'The group needs a name.',
    'Du hast schon eine Gruppe mit diesem Namen.'
        => 'You already have a group with this name.',
    'Diese Gruppe gibt es nicht mehr.'
        => 'This group no longer exists.',
    'In eine Gruppe kannst du nur Freunde aufnehmen.'
        => 'You can only take friends into a group.',
    'Das darfst nur du selbst oder der Besitzer der Gruppe.'
        => 'Only you yourself or the owner of the group may do that.',
    'Die Nachricht ist leer.'
        => 'The message is empty.',
    'Ihr seid nicht (mehr) befreundet.'
        => 'You are not friends (any more).',
    'Die Datenbank ist nicht auf dem Stand des Programms — es fehlt eine Migration.'
        => 'The database is behind the program — a migration is missing.',
    'Es ist ein Serverfehler aufgetreten.'
        => 'A server error occurred.',
    'Es ist ein Fehler aufgetreten.'
        => 'An error occurred.',
    'Eigene Punkte auf amtlichen Luftbildern — auch ohne Netz.'
        => 'Your own points on official aerial imagery — offline too.',
    'Nur über das Formular.'
        => 'Only through the form.',
    'Nichts zu berichten — die Auswahl ist leer.'
        => 'Nothing to report — the selection is empty.',
    'Drucken / als PDF speichern'
        => 'Print / save as PDF',
    'Im Druckfenster als Ziel „Als PDF sichern" wählen.'
        => 'In the print window choose "Save as PDF" as the destination.',
    'Die Karte wird gezeichnet — einen Augenblick.'
        => 'The map is being drawn — one moment.',
    'So viele Spalten passen nicht auf A4 — auch klein geschrieben nicht. Weniger Angaben wählen, oder im Fundbericht-Fenster die Kachelform nehmen.'
        => 'This many columns do not fit on A4 — not even in small print. Choose fewer details, or take the tile form in the find report window.',
    '1 Art aus %s'
        => '1 species from %s',
    '%d Arten aus %s'
        => '%d species from %s',
    '%d Funden'
        => '%d finds',
    'Erstellt am %s mit YASAIMAPP.'
        => 'Made on %s with YASAIMAPP.',

    // „Bestimmung" at a find for every account: Internet-Links and finds (pages/identify.php, assets/js/speciesfinds.js, 24.11.2026).
    'Funde (%d)'
        => 'Finds (%d)',
    'Funde mit Bild'
        => 'Finds with pictures',
    'Ältere Bilder'
        => 'Older pictures',
    '1 Bild'
        => '1 picture',
    '%d Bilder'
        => '%d pictures',
    'Zu dieser Art gibt es noch keinen Fund mit Bild. Weder von dir noch von deinen Freunden.'
        => 'There is no find of this species with a picture yet. Neither from you nor from your friends.',
    'Zurück zum Fund'
        => 'Back to the find',
    'Dieser Fund hat noch keinen Namen. Trag bei „Art" einen Namen ein. Dann stehen hier die Internet-Links und die Funde dazu.'
        => 'This find has no name yet. Enter a name under “Species”. Then the web links and the finds for it appear here.',
    '„%s" ist kein Art- oder Gattungsname, den die Listen kennen. Deshalb gibt es dazu keine Links und keine Funde.'
        => '“%s” is not a species or genus name the lists know. So there are no links and no finds for it.',
    'Der Fund nennt nur die Gattung. Die Funde dazu zeigen wir, sobald eine Art eingetragen ist.'
        => 'The find names only the genus. Its finds are shown as soon as a species is entered.',
    'Bestimmung — Internet-Links und Funde zum Namen'
        => 'Identification — web links and finds for the name',

    // A FIND ON THE PUBLIC SITE (pages/finds.php, findpublish.js, plate_publish.php, 25.11.2026)
    'Veröffentlichen'
        => 'Publish',
    'Tafel veröffentlichen'
        => 'Publish the plate',
    'Nicht veröffentlicht'
        => 'Not published',
    'Wartet auf Einverständnis'
        => 'Waiting for consent',
    'Auf %s zeigen?'
        => 'Show on %s?',
    'der öffentlichen Seite'
        => 'the public site',
    'Lizenz %s: Jeder darf die Tafel mit deinem Namen weitergeben, aber nicht verkaufen.'
        => 'Licence %s: anyone may pass the plate on with your name, but not sell it.',
    'Bilder von anderen auf dieser Tafel'
        => 'Pictures by others on this plate',
    'Hake jeden an, der einverstanden ist. Vorher geht die Tafel nicht hinaus.'
        => 'Tick everyone who agrees. Until then the plate does not go out.',
    '%s ist einverstanden'
        => '%s agrees',
    'Zusatz zur Lizenz'
        => 'Addition to the licence',
    'z. B. Foto unten links mit freundlicher Genehmigung'
        => 'e.g. photo bottom left by kind permission',
    'Noch nicht zu sehen: %s muss erst einverstanden sein.'
        => 'Not visible yet: %s has to agree first.',
    'Veröffentlicht. Änderungen erscheinen innerhalb einer Stunde.'
        => 'Published. Changes appear within an hour.',
    'Die Tafel wird veröffentlicht. Sie erscheint innerhalb einer Stunde.'
        => 'The plate is being published. It appears within an hour.',
    'Die Tafel wird nicht mehr veröffentlicht. Sie verschwindet innerhalb einer Stunde.'
        => 'The plate is no longer published. It disappears within an hour.',
    'Zusatz gespeichert.'
        => 'Addition saved.',
    'Der Zusatz ist zu lang.'
        => 'The addition is too long.',
    'Auf der Tafel sind Bilder von anderen. Bestätige erst, dass sie einverstanden sind.'
        => 'The plate carries pictures by others. Confirm first that they agree.',
    'Veröffentlichen gibt es hier nicht.'
        => 'Publishing is not available here.',

    // THE FUNDBUCH (publication_content.php, publication.php, 25.11.2026)
    'Dein Fundbuch ist eingerichtet.'
        => 'Your find book is set up.',
    'Der neue Schlüssel ist da. Der alte gilt nicht mehr.'
        => 'The new key is here. The old one no longer works.',
    'Das Fundbuch ist entfernt. Deine Homepage bekommt keine neuen Funde mehr.'
        => 'The find book is removed. Your homepage gets no new finds any more.',
    'Fundbuch'
        => 'Find book',
    'Mit dem Fundbuch zeigst du Funde auf deiner eigenen Homepage.'
        => 'With the find book you show finds on your own homepage.',
    'Dort erscheint nur, was du freigibst: Öffne einen Fund im Fundbrowser und klicke oben auf den Knopf mit der Weltkugel.'
        => 'Only what you release appears there: open a find in the find browser and click the button with the globe at the top.',
    'Deine Homepage holt sich jede Tafel einmal ab und zeigt sie dann selbst. Der genaue Fundort bleibt hier: Hinaus gehen nur die Gemeinde und das Viertel des Messtischblatts.'
        => 'Your homepage fetches each plate once and then shows it itself. The exact spot stays here: only the municipality and the quarter of the map sheet go out.',
    'Eingerichtet für %s am %s.'
        => 'Set up for %s on %s.',
    'Deine Homepage hat zuletzt am %s nachgefragt.'
        => 'Your homepage last asked on %s.',
    'Deine Homepage hat noch nie nachgefragt.'
        => 'Your homepage has never asked yet.',
    'Adresse deiner Homepage'
        => 'Address of your homepage',
    'Die Adresse, unter der dein Fundbuch zu sehen ist. Sie steht auch in jeder Tafel, als Stelle für Nachfragen zur Lizenz.'
        => 'The address where your find book can be seen. It also stands in every plate, as the place to ask about the licence.',
    'Fundbuch einrichten'
        => 'Set up find book',
    'Mit dem Schlüssel weist sich deine Homepage hier aus. Er steht dort in einer Datei. Wir kennen ihn nicht mehr: Er wurde dir nur einmal gezeigt.'
        => 'With the key your homepage identifies itself here. It is kept there in a file. We no longer know it: it was shown to you only once.',
    'Einen neuen Schlüssel erzeugen? Der alte gilt dann sofort nicht mehr. Deine Homepage bekommt erst wieder neue Funde, wenn du dort den neuen Schlüssel einträgst.'
        => 'Create a new key? The old one stops working at once. Your homepage gets new finds again only when you enter the new key there.',
    'Neuen Schlüssel erzeugen'
        => 'Create new key',
    'Das Fundbuch entfernen? Der Schlüssel gilt dann nicht mehr, und deine Homepage bekommt keine neuen Funde. Was schon auf deiner Homepage liegt, bleibt dort, bis du es dort löschst.'
        => 'Remove the find book? The key then stops working, and your homepage gets no new finds. What already lies on your homepage stays there until you delete it there.',
    'Fundbuch entfernen'
        => 'Remove find book',
    'Der Schlüssel für dein Fundbuch'
        => 'The key for your find book',
    'Kopiere den Schlüssel jetzt.'
        => 'Copy the key now.',
    'Er erscheint nur dieses eine Mal. Geht er verloren, erzeugst du hier einen neuen.'
        => 'It appears only this once. If it gets lost, you create a new one here.',
    'Er gehört auf deine Homepage, in die Datei „fundbuch-key.php" im Ordner „inc".'
        => 'It belongs on your homepage, in the file “fundbuch-key.php” in the folder “inc”.',
    'Das ist keine Adresse einer Homepage. Ein Beispiel: https://beispiel.de/'
        => 'That is not the address of a homepage. An example: https://example.org/',
];
