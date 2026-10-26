# Änderungen

## 2.2

- Gesamt- und Monatsverbrauch als Balkendiagramme der archivierten Zählerstände.
- Verdichter und Heizungs-/Warmwasserventil mit anklickbarem Statusverlauf als Stufendiagramm einschließlich archiviertem Anfangszustand.

## 2.1

- Feste Nachkommastellen in Karten und Verläufen: Momentanleistung 2, Gesamtverbrauch 0, Monatsverbrauch 1.

## 2.0

- Elektrische Momentanleistung, Gesamtverbrauch und laufender Monatsverbrauch mit auswählbaren Quellvariablen.
- Anlagenvariablen 48059 (Leistungsaufnahme in W) und 58570 (Gesamtverbrauch in kWh) vorbelegt.
- Einheitenprüfung und Normalisierung von W/kW und Wh/kWh.
- Monatsberechnung aus archivierten Gesamtzählerständen, mit Zeitzone und Erkennung von Zählerrücksetzungen; alternativ vorhandene Monatsvariable.
- Passende elektrische Graphen mit Einheiten, Zeitnavigation und unterbrochener Monatslinie am Monatswechsel.
- Seitenweise Zählerauswertung ohne stilles Abschneiden; ursprüngliche Quellen und Schreibschutz bleiben erhalten.

## 1.9

- Wärmepumpensymbol aus dem Kopfbereich entfernt.

## 1.8

- Automatische Einpassung der gesamten Übersicht in Kachelbreite und -höhe, mit kompakteren Abständen bei geringer Höhe.
- Proportionale Skalierung bei flachen Kacheln und Rückkehr zur Originalgröße beim Vergrößern.
- Neue Einpassung bei Containergrößenänderungen, Schriftladen und Quellupdates.
- Verlaufsdialog bleibt unabhängig von der Übersicht bedienbar.

## 1.7

- Zusatzbeschriftung „Heizung & Warmwasser“ oben links entfernt.
- Hersteller-/Gerätebeschriftung verkleinert.
- Eigenes kompaktes SVG-Gerätesymbol für die Vitocal 222 im Kopfbereich ergänzt, ohne externe Bildabhängigkeit.

## 1.6

- Quellendiagnose aus der Kachel entfernt; Instanzdiagnose bleibt verfügbar.
- Fußzeile mit Quellenstatus und Update-Zeitpunkt auf 9 px verkleinert.

## 1.5

- Anlagenschema, zugehörige Symbole und separate Schema-Dateien vollständig entfernt.
- Kompakte Ansicht mit Isttemperaturen, Temperaturverläufen und Anlagenstatus.

## 1.4

- Anlagenschema vollständig als neues Funktionsschema mit Wärmequelle, Wärmepumpe und Wärmeabgabe konstruiert.
- Getrennte Medienkanäle an Verdampfer und Verflüssiger, Kältekreis mit Verdichter und Expansionsventil, getrennte Heizungs- und Speicherzweige.
- Eigenständige SVG-/PNG-Exporte und lesbare, seitlich verschiebbare Darstellung auf kleinen Bildschirmen.

## 1.3

- Installationsfehler „ungültiges Module dist (module.json fehlt)“ behoben: Paketordner aus dem Modul-Repository entfernt.
- Build-Ausgaben werden außerhalb des Repositorys erzeugt; interne Ausgabeziele werden abgewiesen.
- CI-Artefakte liegen im temporären Runner-Verzeichnis.
- Regressionstest lädt jetzt die gesamte Bibliothek einschließlich Modulverzeichnis-Erkennung.

## 1.2

- Betriebsartauswahl vollständig entfernt; aktuelle Betriebsart bleibt als Status sichtbar.
- Alle ehemaligen Schreibaktionen auch serverseitig gesperrt.
- Anlagenschema nach der Fotovorlage neu gezeichnet: Heizkreispumpe unter dem Dreiwegeventil, getrennte Vor-/Rückläufe, Primärpumpe auf der rechten Erdsondenleitung.
- Vorhandene Isttemperaturen, Archivverläufe und Zeitnavigation bleiben erhalten.

## 1.1

- Sollwertanzeigen und Eingaben vollständig entfernt; die beiden Sollwert-Schreibaktionen werden auch serverseitig abgewiesen.
- Vier anklickbare Isttemperaturen mit Archive-Control-Verlauf, 1/6/24 Stunden und 7/30 Tagen.
- Vor- und Zurückblättern um den gewählten Zeitraum, Jetzt-Button und Zukunftssperre.
- Stundenmittel für längere Bereiche, Tooltip und Wertetabelle, erkennbare Archivfehler und leere Daten.
- Schutz gegen veraltete Antworten beim Navigieren, Schließen und Temperaturwechsel.
- Eigenes Anlagenschema nach der Fotovorlage mit Heizkreis, Warmwasserspeicher, Kältekreis und Erdsonde.
- Bestehende Modulidentitäten und Betriebsartsteuerung bleiben erhalten.

## 1.0

- Eigenständige Vitocal-222-C-Kachel auf Basis der Vitovent-300-F-Vorlage.
- Elf vorbelegte Heizungs- und Warmwasserquellen aus dem bereitgestellten Screenshot.
- Betriebsartauswahl sowie Raum- und Warmwasser-Solltemperatur über vorhandene Variablenaktionen.
- Serverseitige Typ-, Wertebereichs- und Schrittweitenprüfung; Rückmeldung über echte Quellupdates.
- Responsive Anzeige ohne Lüftungsdaten, Diagnose und lokales Installationspaket.
