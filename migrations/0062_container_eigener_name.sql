-- Ein eigener Name für einen Container.
--
-- Den Namen gibt, wer den Container anlegt — und er gilt für alle. Das ist
-- richtig, solange er beschreibt, WAS der Container ist. Er beschreibt aber
-- nicht, wofür ICH ihn benutze: „Waldbrände 2026" heißt für den einen die
-- Kartierung, für den anderen „Erics Sachen". Und wenn drei Container
-- untereinander stehen, entscheidet der Name auch die Reihenfolge
-- (20.08.2026).
--
-- NUR FÜR MICH, UND NIEMAND SONST SIEHT IHN. Das ist der ganze Unterschied
-- zum Umbenennen: der Besitzer behält seinen Namen, die anderen Mitglieder
-- ihren, und niemand muss gefragt werden. Deshalb steht der Name auch
-- nicht in map_container, sondern hier, je Mitglied und Container.
--
-- IN DIESER TABELLE UND NICHT IN EINER NEUEN. map_container_gesehen führt
-- bereits eine Zeile je Mitglied und Container — sie merkt sich die
-- gesehenen Mitglieder und seit 0060 den Lesestand des Chats — und sie
-- wird beim Austritt und beim Löschen des Containers mit aufgeräumt. Eine
-- zweite Tabelle daneben bräuchte dieselbe Aufräumarbeit ein drittes Mal,
-- und beim ersten Vergessen bleiben Namen von Leuten liegen, die längst
-- draußen sind.
--
-- NULL heißt „kein eigener Name" und nicht „leerer Name": wer das Feld
-- leert, will den ursprünglichen zurück, und das ist etwas anderes als ein
-- Container ohne Bezeichnung.
ALTER TABLE map_container_gesehen
    ADD COLUMN eigener_name VARCHAR(100) NULL
        COMMENT 'wie dieses Mitglied den Container nennt; NULL = wie der Besitzer';
