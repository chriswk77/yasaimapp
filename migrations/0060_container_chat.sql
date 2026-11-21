-- Der Chat eines Containers.
--
-- Ein Container sammelt die Listen mehrerer Leute; wer darin steckt, sieht
-- dieselben Punkte auf seiner Karte. Was bisher fehlte, war die Zeile
-- daneben — „das war letzte Woche schon fast verholzt", „ich war Samstag
-- oben, nichts". Der Wunsch vom 20.08.2026.
--
-- WER MITLIEST, ENTSCHEIDET DER CONTAINER, nicht die Nachricht. Es gibt
-- keine Empfänger, keine Sichtbarkeit je Zeile: Mitglied sein IST das
-- Leserecht. Deshalb steht hier auch keine Kopie je Leser, sondern eine
-- Nachricht je Container — und wer neu dazukommt, liest alles, was vorher
-- war. Das ist Absicht und nicht Nachlässigkeit: ein Container ist ein
-- gemeinsames Gedächtnis, und ein Gedächtnis, das beim Beitritt anfängt,
-- ist für den Neuen wertlos. Wer das nicht will, macht einen neuen
-- Container.
--
-- KEIN LÖSCHEN JE NACHRICHT. Es gibt keine Spalte dafür und keinen Weg
-- dorthin: in einem Gespräch zwischen wenigen Leuten wäre eine
-- verschwundene Zeile schlimmer als eine bereute — die anderen haben sie
-- gelesen, und was übrig bliebe, wäre eine Lücke, die niemand erklären
-- kann. Mit dem Container geht alles.
CREATE TABLE map_container_nachrichten (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    container_id INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL COMMENT 'wer geschrieben hat',
    text         VARCHAR(2000) NOT NULL,
    erstellt_am  DATETIME NOT NULL,
    PRIMARY KEY (id),
    -- Die Abfrage lautet immer „alles in diesem Container ab Nummer X".
    -- Genau dafür ist dieser Index, und ein zweiter wird nicht gebraucht.
    KEY container_lauf (container_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Der Lesestand, und zwar in der Tabelle, die es schon gibt.
--
-- map_container_gesehen führt bereits eine Zeile je Mitglied und Container
-- (sie merkt sich, welche Mitglieder man schon gesehen hat) und wird beim
-- Austritt und beim Löschen des Containers mit aufgeräumt. Eine zweite
-- Tabelle daneben hätte dieselbe Aufräumarbeit ein zweites Mal gebraucht —
-- und beim ersten Vergessen bleiben Lesestände von Leuten liegen, die
-- längst draußen sind.
--
-- 0 heißt „noch nichts gelesen" und ist der richtige Anfangswert: die
-- Nummern beginnen bei 1, und wer neu dazukommt, hat wirklich noch nichts
-- gelesen — er soll ja sehen, dass da etwas ist.
ALTER TABLE map_container_gesehen
    ADD COLUMN gelesen_bis INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'höchste gelesene Nachrichtennummer';
