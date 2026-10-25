# Änderungen

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
