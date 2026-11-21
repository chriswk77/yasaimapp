# Geoidgitter

`egm96_15.i16` — die Undulation des EGM96 auf einem 15-Bogenminuten-Gitter.
Gebraucht wird sie, um die Höhe eines Satellitenempfängers in eine Höhe über
dem Meeresspiegel umzurechnen.

## Wozu

Ein Empfänger misst die Höhe über dem **WGS84-Ellipsoid**, einer glatten
Rechenfigur. Der Meeresspiegel folgt dagegen dem **Geoid**, einer Fläche
gleicher Schwere, und die beiden liegen nicht aufeinander:

| Ort | Geoid über Ellipsoid |
|---|---|
| Deutschland | rund +46 m |
| Norditalien, Alpen | rund +49 m |
| südlich von Indien | −107 m (globales Minimum) |
| bei Neuguinea | +85 m (globales Maximum) |

Die Rohzahl des Empfängers als Meereshöhe auszugeben, liegt in Deutschland also
systematisch etwa 46 Meter zu hoch.

## Herkunft

Aus `us_nga_egm96_15.tif` von <https://cdn.proj.org/> — dem Gitter, das PROJ
für den Übergang von EPSG:4979 (WGS84 dreidimensional) nach EPSG:5773 (EGM96)
benutzt. Ursprung ist die NGA, das Original ist gemeinfrei.

Umgewandelt wurde es hier nur im Format, nicht im Inhalt: Aus Fließkomma in
Metern wurden Ganzzahlen in Zentimetern (`int16`, little-endian). Das ist um
Größenordnungen feiner als jede Messung, die hier ankommt, und halbiert die
Datei auf 2 MB.

    1440 Spalten x 721 Zeilen, 0,25 Grad Schrittweite
    Zeile 0   = 90 Grad Nord,  Zeile 720  = 90 Grad Süd
    Spalte 0  = 180 Grad West, Spalte 1439 = 179,75 Grad Ost

## Geprüft

`node pruefungen/geoid.mjs` vergleicht mit veröffentlichten EGM96-Kennzahlen,
die **nicht** aus dieser Datei stammen: die beiden globalen Extremwerte und die
Größenordnung in Mitteleuropa. Beim Entpacken war der Fließkomma-Predictor des
TIFF die Fehlerquelle — hätte er nicht gestimmt, lägen die Extremwerte daneben.

Gemessen: −106,99 bis +85,39 m. Veröffentlicht für EGM96: −107 und +85 m.

## Wie genau das Ergebnis ist

Zwei Fehler, die zwei Größenordnungen auseinanderliegen:

| Quelle | Größe |
|---|---|
| EGM96 gegen das amtliche deutsche Quasigeoid GCG2016 | wenige Dezimeter |
| die Höhenmessung des Empfängers selbst | **±10 m und mehr** |

Ein Satellitenempfänger ist senkrecht zwei- bis dreimal ungenauer als
waagerecht. Bei ±4 m in der Fläche sind ±10 m in der Höhe normal.

Die Umrechnung beseitigt also einen systematischen Fehler von 46 Metern und
lässt einen zufälligen von rund 10 Metern stehen. Sie lohnt sich — aber sie
macht die Zahl nicht genau. Deshalb wird die Messunsicherheit als
`hoehe_genauigkeit_m` mitgespeichert und angezeigt.

Wer die Geländehöhe wirklich auf den Meter braucht, nimmt sie nicht vom
Empfänger, sondern aus einem Geländemodell: Das DGM1 der Länder löst auf einen
Meter auf, und weil die waagerechte Position auf ±4 m genau ist, erbt man von
dort nur den Fehler aus der Hangneigung — an einem 20-Prozent-Hang unter einem
Meter.
