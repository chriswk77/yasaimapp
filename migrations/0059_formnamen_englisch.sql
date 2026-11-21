-- Die Namen der Markierungsformen ziehen ins Englische.
--
-- Der Code spricht englisch, die Oberfläche deutsch — das gilt seit dem
-- 15./16.08.2026 überall, nur diese siebzehn Namen waren liegengeblieben.
-- Sie sind Bezeichner: sie stehen als Schlüssel in shapes.php, in
-- LIST_SYMBOLS, in SHAPE_NAMES und SHAPE_ANCHOR, und sie reisen als Wert
-- durch die API. Dass sie zugleich in map_lists.symbol GESPEICHERT sind,
-- macht sie nicht zu Inhalt — es macht diese Migration nötig.
--
-- KEINE ÜBERSETZUNGSTABELLE IM CODE, ausdrücklich (20.08.2026).
-- Für 'reh' gab es eine, damals mit gutem Grund: ein Handy mit altem
-- Programm im Zwischenspeicher hätte sonst den alten Namen geschickt. Der
-- Nutzerkreis ist heute der Betreiber selbst, und eine Schicht, die eine
-- Umbenennung überdauert, ist genau die Sorte Halbheit, die uns zwei Tage
-- gekostet hat. Die alte Tabelle ist mit dieser Migration ebenfalls
-- verschwunden.
--
-- ZWISCHEN AUSLIEFERUNG UND MIGRATION liegt ein Fenster: der Code geht vor
-- der Migration live, und in dieser Zeit kennt er die deutschen Werte in der
-- Spalte nicht mehr. Jede Liste zeigt so lange den Kreis. Nichts geht
-- verloren, nichts wird überschrieben — die Zeile behält ihren Wert, bis
-- diese Datei ihn umschreibt. Wer die Migration gleich nach dem Push
-- einspielt, sieht das Fenster gar nicht.

UPDATE map_lists SET symbol = CASE symbol
    WHEN 'kreis'     THEN 'circle'
    WHEN 'quadrat'   THEN 'square'
    WHEN 'dreieck'   THEN 'triangle'
    WHEN 'raute'     THEN 'diamond'
    WHEN 'faehnchen' THEN 'flag'
    WHEN 'kreuz'     THEN 'cross'
    WHEN 'pilz'      THEN 'mushroom'
    WHEN 'baum'      THEN 'tree'
    WHEN 'insekt'    THEN 'insect'
    WHEN 'hirsch'    THEN 'deer'
    WHEN 'flechte'   THEN 'lichen'
    WHEN 'spinne'    THEN 'spider'
    WHEN 'schnecke'  THEN 'snail'
    WHEN 'vogel'     THEN 'bird'
    WHEN 'lurch'     THEN 'amphibian'
    WHEN 'fisch'     THEN 'fish'
    WHEN 'wurm'      THEN 'worm'
    ELSE symbol
END;

-- Die Vorgabe der Spalte stand seit 0009 auf 'kreis' und muss mitwandern —
-- sonst legt die nächste eingefügte Zeile wieder einen deutschen Wert an,
-- den nichts mehr kennt.
ALTER TABLE map_lists MODIFY symbol VARCHAR(40) NOT NULL DEFAULT 'circle';
