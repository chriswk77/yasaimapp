# Taxonomie-Grundlage

Zwei Namensverzeichnisse und ihre Verknüpfung. Sie beantworten die Frage, ob
zwei Namen denselben Pilz meinen — und liefern zu jedem den deutschen Namen und
die Rote-Liste-Einstufung.

## Die Tabellen

| Tabelle | Inhalt |
|---|---|
| `map_tax_gbif` | 397.511 Pilztaxa aus dem GBIF-Backbone, davon 202.235 Synonyme |
| `map_tax_col` | 806.448 Einträge aus dem Catalogue of Life, davon 185.474 Synonyme |
| `map_tax_dgfm` | 39.248 Einträge der DGfM-Referenzliste, davon 15.808 Synonyme |
| `map_tax_lichens` | 2.767 Einträge der Roten Liste der Flechten, davon 358 Synonyme |
| `map_tax_plants` | 5.824 Einträge der Roten Liste der Farn- und Blütenpflanzen, davon 512 Synonyme |
| `map_tax_links` | 36.971 Zuordnungen zwischen DGfM und GBIF |

Die Synonymie funktioniert in beiden gleich, nur anders benannt: bei GBIF zeigt
`parent_key` vom Synonym auf den gültigen Namen, bei der DGfM `sipnr` auf
`taxnr`. Geprüft ist bei beiden, dass kein Verweis ins Leere führt.

## Die Dateien

**`DgfmTaxref.sql`** — vollständiger Abzug der Ursprungstabelle aus dem
PSV-Trainer, mit allen zwanzig Spalten. Archivkopie; sie enthält auch das, was
in `map_tax_dgfm` nicht übernommen wurde (Qualifizierungsvermerk, Anmerkungen,
Gattungsinformation).

**`DgfmTaxref.tsv`** — dieselben Daten in den sechzehn Spalten, die
`admin/dgfm_import.php` einliest. Die Arbeitsfassung.

Beide liegen hier, weil sich diese Liste nicht wie die BfN-Archive mit einem
`curl` ziehen lässt: der PSV-Trainer ist auf fremdes Hosting umgezogen. Neu
zu bekommen ist sie trotzdem — **über die Kontakte des Betreibers zur DGfM**, und
das ist der Weg, wenn ein neuer Stand gebraucht wird.

**Einmal im Jahr genügt.** Die Liste ändert sich nicht stündlich, und die
Gefährdungseinstufungen darin hängen an der Roten Liste der Großpilze
(Dämmrich u. a. 2016) — solange die nicht neu bearbeitet wird, ist die
TAXREF auf dem aktuellen Stand und ihren Einstufungen zu trauen. Eine
Neubearbeitung ist Stand 15.08.2026 weder angekündigt noch in Arbeit.

**Sie vergibt kein „ungefährdet".** Von 39.248 Pilznamen trägt kein
einziger ein `*`; bewertet sind 5.578, und zwar ausschliesslich mit
`D R G 3 1 2 V 0 ♦`. Bei einem Pilz ohne Eintrag lässt sich deshalb
„ungefährdet" nicht von „nicht bewertet" unterscheiden — anders als bei
Flechten (588 mal `*`) und Pflanzen (2.153 mal). Der Gefährdungsfilter der
Karte bietet `*` aus genau diesem Grund nicht zum Anhaken an, siehe
`RL_CATEGORIES` in `assets/js/filter.js`.

**Die BfN-Listen für Pilze bleiben ungenutzt** — Großpilze 2016,
Phytoparasitische Kleinpilze 2023, Schleimpilze 2011. Die Einstufungen
kommen alle aus der TAXREF. Ob deren Werte Art für Art mit der BfN-Fassung
2016 übereinstimmen, ist nicht verglichen worden; die Zahlen legen es nahe
(BfN bewertete 6.162 Taxa), belegt ist es nicht.

Der GBIF-Bestand liegt bewusst *nicht* im Repository. Er ist mit einem Befehl
neu ladbar — der steht im Kopf von `admin/gbif_import.php` — und mit 128 MB
wäre er hier nur Ballast.

## Wie gut die beiden zusammenpassen

Von 39.248 deutschen Einträgen finden 36.243 ihren GBIF-Gegenpart, also 92,3 %:

    wörtlich derselbe Name       21.166   53,9 %
    über ein GBIF-Synonym        12.939   33,0 %
    über ein DGfM-Synonym         2.138    5,4 %
    kein Treffer                  3.005    7,7 %

Dass nur gut die Hälfte wörtlich übereinstimmt, ist kein Mangel, sondern der
Grund für die ganze Übung: die beiden Listen folgen verschiedenen Artkonzepten.

**4.391 Zuordnungen sind mehrdeutig** — der Name kommt in GBIF mehrfach vor,
weil verschiedene Pilze gleich heißen. Dort ist die Zuordnung ein Vorschlag,
kein Befund; die Spalte `kandidaten` hält das fest, statt es zu verschweigen.

## Flechten

Die DGfM-Liste führt 157 Einträge unter „Lichenes“, davon zwei mit deutschem
Namen — und die 157 sind überwiegend gar keine Flechten, sondern Pilze, die
*auf* Flechten leben. Praktisch hieß das: „Cladonia“ schlug sieben lichenicole
Pilze vor und keine einzige Cladonia. Kein Versäumnis der DGfM; Flechten sind
ein eigenes Fachgebiet mit eigener Fachgesellschaft.

Deshalb steht daneben die **Rote Liste der Flechten und flechtenbewohnenden
Pilze Deutschlands** (Wirth u. a. 2011), wie sie das Rote-Liste-Zentrum des BfN
als Datentabelle herausgibt:

| Gruppe | Taxa |
|---|---|
| Flechten | 1.974 |
| lichenicole Pilze | 391 |
| flechtenähnliche Pilze | 44 |
| Synonyme | 326 |

Dieselbe Gefährdungsskala wie bei den Pilzen (`0 1 2 3 G R V D *`). **Deutsche
Namen führt sie keine** — für Flechten gibt es kaum eingebürgerte.

**Seit Migration 0132 (25.09.2026) stehen sie trotzdem da, aus GBIF.**
`admin/lichens_german.php` gleicht jeden gültigen Namen mit dem GBIF-Backbone ab
(genau, Reich Fungi) und sammelt die deutschen Namen der anerkannten Art —
fast alle aus dem Catalogue of Life. 2.346 von 2.409 Namen erkennt GBIF genau,
246 haben einen deutschen Namen, **237 Arten bekommen ihn**: Wo GBIF eine Art
unserer Liste als Synonym einer anderen führt, die ebenfalls in der Liste
steht, gehört der Name der anderen. Mehrere Namen stehen wie bei der DGfM in
einem Feld, durch Komma getrennt, der Hauptname vorn („Pflaumenflechte,
Eichenmoos"); wie der Hauptname gewählt wird, steht im Kopf des Skripts, drei
hat der Betreiber selbst bestimmt.

Die Namen kamen als UPDATEs und nicht über die Übertragung: Deren Einspielen
füllt eine Tabelle bis zu ihrer Zeilenzahl und hielte dieselben Zeilen mit
neuen Namen für fertig. **Die Übertragungsdatei `uebertrag/map_tax_lichens.tsv.gz`
kennt die Namen deshalb noch nicht** — wer die Flechtentabelle je neu überträgt,
schreibt sie vorher mit `php admin/tax_export.php` frisch aus. (0132 ein zweites
Mal einspielen geht nicht: Der Migrationslauf führt jede Datei nur einmal aus.)

Eigene Tabelle und nicht mit in `map_tax_dgfm`, weil `dgfm_import.php` die
Tabelle leert und neu füllt: Die Flechten lägen nach dem nächsten Einspielen
im Nichts, und es fiele niemandem auf, bis jemand im Wald sucht.

**377 Namen stehen in beiden Listen.** Für einen davon gilt die Flechtenliste —
sie stammt von den Lichenologen und hat die Einstufung für genau dieses Taxon.
Die Regel steht in `species_branch()` und noch einmal in `assets/js/species.js`;
stünde sie nur an einer Stelle, sähe man online etwas anderes als offline.

## Pflanzen

Viele Pilze sind ohne ihre Pflanze nicht zu führen: Ein Rostpilz hängt an
seinem Wirt, ein Mykorrhizapilz an seinem Partner, und wer wissen will, warum
auf einer Magerwiese Saftlinge stehen und auf der nächsten nicht, notiert die
Pflanzengesellschaft dazu.

Aus der **Roten Liste der Farn- und Blütenpflanzen Deutschlands** (Metzing
u. a. 2018), wieder vom Rote-Liste-Zentrum und im selben Archivformat wie die
Flechten:

| | |
|---|--:|
| Taxa | 5.312 |
| davon mit deutschem Namen | 5.051 — **95,1 %** |
| einheimisch / Neophyt / unbeständig | 4.752 / 503 / 48 |
| Synonyme | 512 |

Hier stehen die deutschen Namen also fast lückenlos, bei den Flechten waren es
zwei von 157. Das ist der Unterschied zwischen einer Gruppe, die jeder benennt,
und einer, die kaum jemand benennt — und der Grund, warum die Suche seit dieser
Liste auch nach dem Anfang des **deutschen** Namens sortiert. Ohne das stünde
auf „sal weide“ die Silber-Weide vor der Sal-Weide, weil jede Weide
wissenschaftlich mit „Salix“ anfängt.

Kein einziger Name überschneidet sich mit den Pilzen oder Flechten; die drei
Deutschlandlisten stehen nebeneinander, ohne einander etwas wegzunehmen.

**Die Checkliste im selben Archiv wird nicht gelesen.** Sie führt mehr Namen,
aber in einem ganz anderen Format — siebzig Spalten Nomenklaturapparat und
keine deutschen Namen.

## Neu aufbauen

    php admin/gbif_import.php       fungi.tsv                          --wirklich
    php admin/dgfm_import.php       daten/taxonomie/DgfmTaxref.tsv     --wirklich
    php admin/flechten_import.php   Download_RoteListe_Flechten….zip   --wirklich
    php admin/pflanzen_import.php   Download_RoteListe_Farn-….zip      --wirklich
    php admin/tax_verknuepfen.php                                      --wirklich

Ohne `--wirklich` läuft jedes Skript als Probelauf.

Die beiden BfN-Archive liegen nicht im Repository, weil sie mit einem Befehl
neu zu beziehen sind — die Einspielskripte lesen das Archiv unmittelbar, samt
der Tabellenblätter darin (`includes/bfn_liste.php`):

    curl -sLO https://www.rote-liste-zentrum.de/wp-content/uploads/Download_RoteListe_Flechten20und20flechtenbewohnende20Pilze_2011_20210317-1606.zip
    curl -sLO https://www.rote-liste-zentrum.de/wp-content/uploads/Download_RoteListe_Farn-20und20Bluetenpflanzen_2018_20240820-1534.zip

Geprüft wurde die Zerlegung gegen die Quelldateien: alle 2.409 Flechten- und
alle 5.312 Pflanzennamen stimmen mit Autor, deutschem Namen und Einstufung
Zeichen für Zeichen überein.

**Sammelzeilen** fächert das Einspielen auf. Das BfN bewertet mitunter mehrere
Unterarten in einer Zeile und lässt ab dem zweiten Namen das Artepitheton weg:

    Armeria maritima subsp. bottendorfensis#Armeria subsp. halleri#…

Ungeteilt stünde das als 117 Zeichen langes Ungetüm in der Liste, das niemand
findet und das keinen Namen bezeichnet. Betrifft 21 Pflanzen- und 2
Flechtenzeilen, alle in der Synonymspalte.

## GBIF gegen Catalogue of Life

Der GBIF-Backbone ist vom 28.08.2023 — nicht versehentlich alt, sondern GBIFs
aktueller Stand; der Live-Index führt dieselbe Zahl an Pilznamen. COL erscheint
dagegen monatlich, hier die Ausgabe vom 17.07.2026.

| | GBIF 2023 | COL 2026 |
|---|---|---|
| Einträge | 397.511 | 806.448 |
| akzeptiert | 195.276 | 620.974 |
| Synonyme | **202.235** | 185.474 |

COL kennt weit mehr Namen, GBIF verknüpft mehr davon als Synonyme. Das ist kein
Widerspruch: COL nimmt in der *extended release* viele Namen auf, die schlicht
noch niemand synonymisiert hat — sie gelten mangels Prüfung als akzeptiert.

**Überschneidung der Namen**

    in beiden      349.298
    nur in GBIF     21.890
    nur in COL     441.822

**Und was die drei Jahre kosten:** von den 349.298 gemeinsamen Namen haben
3.977 ihren Status geändert, also 1,1 %.

    2023 akzeptiert, heute Synonym   3.014
    2023 Synonym, heute akzeptiert     963

Beispiele: *Tilletiopsis pallescens* heißt heute *Golubevia pallescens*,
*Coleosporium senecionis* ist heute *Coleosporium tussilaginis*.

Deshalb stehen beide nebeneinander statt einer statt des anderen — GBIF für die
dichtere Synonymie, COL für den aktuellen Stand und die neueren Namen.

## Autor und Jahr

Zu einem Artnamen gehören Autor und Jahr; ohne sie ist er mehrdeutig.
*Agaricus edulis* steht bei GBIF fünfmal — von Bulliard, von Pilát, von Konrad
& Maublanc und zwei weiteren. *Sphaeria brassicae* siebenmal.

Deshalb entscheidet der Autor über die mehrdeutigen Zuordnungen. Von 4.754
solchen Fällen ließen sich 4.512 klären, und in **1.897 davon war die Zuordnung
über den Namen allein falsch** — sie zeigte auf den Namensvetter. Auch die
Widersprüche zwischen COL und GBIF gingen dabei von 1.567 auf 1.268 zurück; ein
Teil war gar kein Widerspruch, sondern ein Fehlgriff.

Verglichen wird nicht wörtlich, sondern auf Buchstaben eingedampft und dann
geprüft, ob eine Angabe in der anderen steckt: `(Pers. : Fr.) P. Karst.` und
`(Pers.) P.Karst.` meinen denselben Autor, nur führt die DGfM die
Sanktionierung `: Fr.` mit.

**Das Jahr hilft dabei fast nicht**, und das liegt an der Nomenklatur selbst:
zoologische Namen zitieren es, mykologische nicht.

    DGfM       98,3 %  haben ein Jahr
    GBIF        6,6 %
    COL     unter 1 %

Zum Vergleich: bei Tieren führt GBIF für 68,7 % ein Jahr. Die DGfM-Liste ist
damit die einzige belastbare Jahresquelle — erfasst ist es in allen dreien,
soweit vorhanden.

**Belastbarkeit der 36.368 Zuordnungen**

    eindeutig, Autor nicht nötig    31.614
    durch den Autor bestätigt        4.512
    mehrdeutig, ungeklärt              242

## Was noch offen ist

**2.880 deutsche Einträge (7,3 %)** finden in keiner der beiden Listen einen
Gegenpart, meist sehr kleine oder alte Namen. Und **242 bleiben mehrdeutig**,
weil kein Autor zusammenpasste.

Index Fungorum braucht es nicht: der Bestand steckt bereits in GBIF.
