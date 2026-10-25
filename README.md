# Viessmann Vitocal 222-C · Kachel für IP-Symcon

Eigenständiges Modul für die **Kachelvisualisierung ab IP-Symcon 7.1**, aufgebaut auf der [Vitovent-300-F-Kachel](https://github.com/somm86/Viessmann-Vitovent-300F-Kachel-Symcon) (Referenzstand `75693d21426088d10e37771b681850b7c8e770f3`). Eigene Bibliotheks- und Modul-GUIDs erlauben die parallele Installation. Keine Lüftungsdaten, keine Viessmann-Cloud-Anmeldung, keine externen JavaScript- oder Laufzeitpakete.

![Vitocal-Kachel mit synthetischen Testdaten](docs/dashboard.png)

Die Darstellung der Wärmepumpe ist schematisch. Screenshot und Test-Betriebsarten stammen aus synthetischen Fixtures; Auswahlwerte und Temperaturgrenzen deiner Anlage werden nicht daraus abgeleitet.

## Installation

**Lokal mit ZIP:** `dist/Vitocal222C-Kachel-1.0.zip` entpacken und den enthaltenen Ordner `Vitocal222C_Kachel` vollständig nach `<Symcon-Datenverzeichnis>/modules/` auf deinem Symcon-Server kopieren. Direkt darin muss `library.json` liegen. Symcon-Dienst neu starten.

**Über Module Control:** In Module Control `https://github.com/somm86/Viessmann-Vitocal-222` mit Branch `main` hinzufügen. Nicht gleichzeitig lokal und über Module Control installieren.

Danach:

1. Instanz **Vitocal 222-C Kachel** erstellen.
2. Quellvariablen prüfen und **Übernehmen** drücken. Die IDs aus deinem Screenshot sind vorbelegt und einzeln änderbar.
3. Instanz zur Kachelvisualisierung hinzufügen bzw. verlinken. Für die Übersicht eine große Kachel verwenden; bei kleinen Kacheln kann vertikal gescrollt werden.
4. Prüfen, ob die angezeigten Quellwerte mit deiner Symcon-Konsole übereinstimmen. Die Kachel fragt die Wärmepumpe nicht selbst ab; die bestehende Geräteintegration muss die Variablen aktualisieren.

## Deine Variablen

| Wert | ID | Eigenschaft | Verwendung |
|---|---:|---|---|
| Betriebsart Heizung | 53049 | `ModeID` | Anzeige und Auswahl |
| Heizkreispumpe | 20926 | `HeatingPumpID` | Status |
| Primärpumpe | 39794 | `PrimaryPumpID` | Status |
| Raum-Solltemperatur | 10939 | `RoomTargetID` | Anzeige und Sollwertsteuerung |
| Rücklauftemperatur Sekundär | 21875 | `ReturnID` | Temperatur |
| Ventil Heizen/Warmwasser | 53786 | `ValveID` | Status nach Quellprofil |
| Verdichterbetrieb | 31722 | `CompressorID` | Status |
| Vorlauftemperatur Primärquelle | 18104 | `PrimaryFlowID` | Temperatur |
| Vorlauftemperatur Sekundär | 18969 | `FlowID` | Temperatur |
| Warmwasser Ist | 56634 | `WaterID` | Temperatur |
| Warmwasser-Solltemperatur | 27660 | `WaterTargetID` | Anzeige und Sollwertsteuerung |

Ausgeschlossen: Abluft, Fortluft, Lüftungsaußentemperatur, Lüftungsbetriebsart, Filterrestzeit und Zuluft-Volumenstrom. Eine Raum-Isttemperatur ist im Screenshot nicht enthalten. `10939` wird daher ausdrücklich als **Raum-Solltemperatur** angezeigt. Temperaturen müssen in °C vorliegen. Für Pumpen und Umschaltventil verwendet die Kachel `GetValueFormatted()`; insbesondere wird die Bedeutung des Ventilwerts nicht eigenmächtig umgedreht.

## Bedienung

Alle Änderungen laufen über **`RequestAction(QuellvariablenID, Wert)`**. Die Kachel schreibt keine Quellwerte mit `SetValue()` und verändert keine Profile. Die wirksame Aktion muss auf eine vorhandene Instanz oder ein vorhandenes Skript zeigen; benutzerdefinierte Aktion `1` deaktiviert die Bedienung. Sollwerte und Betriebsart bleiben bis zum Quellupdate beim tatsächlich gelesenen Wert. Nach 15 Sekunden ohne passenden Quellwert erscheint ein Hinweis, keine Erfolgsmeldung. Ein Quellupdate bestätigt die Variable, nicht unabhängig davon den physischen Zustand des Geräts.

- **Betriebsart:** ausschließlich Werte des wirksamen Profils bzw. einer modernen Darstellung **Aufzählung** (Darstellungs-API ab Symcon 8.1). Der Stringtyp deiner Variable wird erhalten. Es werden keine Betriebsartcodes erfunden. Ist `53049` nur ein Anzeigetext ohne Auswahloptionen/Aktion, in der Instanz die tatsächlich schaltbare Betriebsartvariable auswählen.
- **Raum und Warmwasser:** wirksames numerisches klassisches Quellprofil mit passendem Datentyp, gültigen Min-/Max-Werten, positiver Schrittweite und ohne Aufzählungsassoziationen erforderlich. Bereich und Schrittweite werden bei jedem Aufruf serverseitig erneut geprüft. Keine frei angenommenen Temperaturgrenzen. Moderne numerische Darstellungen sind in Version 1.0 noch nicht als Bereichsquelle implementiert; solche Sollwerte bleiben lesbar, die Eingabe bleibt mit Diagnose gesperrt. Falls deine schaltbare Sollwertvariable eine andere ID hat, diese in der Instanz auswählen.

Die tatsächlichen Profile, Aktionen und Geräteantworten deiner Symcon-Installation waren in dieser Entwicklungsumgebung nicht verfügbar. Öffne bei gesperrter Bedienung **Variablen- und Steuerungsdiagnose** in der Kachel oder **Betriebsart-Diagnose anzeigen** in der Instanz (enthält auch die Sollwertkonfiguration). Dort stehen erkannte IDs, Typen, Auswahlwerte und Gründe. Keine Zugangsdaten erforderlich.

## Entwicklung und Prüfung

Benötigt PHP mit `mbstring`, Python 3 und für den Browsertest Playwright/Chromium. Der produktive Symcon-Betrieb benötigt keine Testabhängigkeiten.

```sh
# Im Repository-Verzeichnis:
git clone https://github.com/symcon/SymconStubs.git /tmp/SymconStubs
git -C /tmp/SymconStubs checkout 31f2e0478eb3763236fcf44ea4144271fdeddfd5
php -l Vitocal222CTile/module.php
SYMCON_STUBS=/tmp/SymconStubs php tests/module_test.php
python3 -m pip install playwright==1.62.0
# Ohne System-Chromium: python3 -m playwright install chromium
CHROMIUM_PATH=/usr/bin/chromium python3 tests/browser_test.py
python3 tests/build.py
```

`CHROMIUM_PATH` auf eine vorhandene Chromium-Datei setzen. Der Browsertest verwendet eine simulierte HTML-SDK-Brücke und synthetische Daten. Keine Verbindung zur Wärmepumpe und keine echte Schaltaktion. [Prüfbericht](docs/VALIDATION.md).

Das Build-Skript erstellt das ZIP und seine SHA-256-Datei, prüft Manifeste, Dateiliste und separate GUIDs und schließt Tests und Git-Daten aus. Die GitHub-Prüfung ist vorbereitet; eine Ausführung auf GitHub wird dadurch nicht behauptet.

Keine offizielle Verbindung zu Viessmann oder Symcon. Die referenzierte Vorlage enthält keine Lizenzdatei; dieses Projekt erteilt keine zusätzliche Lizenz für übernommenen Code.
