# Viessmann Vitocal 222-C · Kachel für IP-Symcon

Modul für die **Kachelvisualisierung ab IP-Symcon 7.1**. Zeigt vier gemessene Temperaturen, elektrische Momentanleistung, Gesamt- und Monatsverbrauch sowie Pumpen, Verdichter, Umschaltventil und Betriebsart. Kompakte Anzeige der Istwerte und Anlagenzustände mit kompaktem Gerätekopf, ohne Anlagenschema. **Betriebsart Heizung, Raumsolltemperatur und Warmwasser-Solltemperatur sind über vorhandene Variablenaktionen bedienbar. Keine Lüftungsdaten.**

![Kachel mit synthetischen Testwerten](docs/dashboard.png)

## Installation und Update

In **Module Control** das Repository `https://github.com/somm86/Viessmann-Vitocal-222` mit Branch `main` hinzufügen oder aktualisieren. Instanz **Vitocal 222-C Kachel** erstellen bzw. die vorhandene Instanz öffnen, **Übernehmen** drücken und die Kachel neu öffnen. Bibliotheks- und Modul-GUIDs bleiben beim Update gleich; keine neue Instanz erforderlich.

Alternativ das mit `python3 tests/build.py` erzeugte `Vitocal222C-Kachel-3.0.zip` entpacken und den enthaltenen Ordner `Vitocal222C_Kachel` vollständig nach `<Symcon-Datenverzeichnis>/modules/` auf dem Symcon-Server kopieren. Direkt darin muss `library.json` liegen. Symcon-Dienst neu starten. Nicht gleichzeitig per Module Control und lokal installieren.

Die Instanz zur Kachelvisualisierung hinzufügen bzw. verlinken. Die Darstellung passt sich der verfügbaren Kachelbreite und -höhe an. Bei geringer Höhe wird die komplette Übersicht proportional verkleinert, einschließlich Kopfbereich und Fußzeile. Wird die Kachel wieder größer, kehrt sie bis zur ursprünglichen Darstellungsgröße zurück. Die Übersicht benötigt keine vertikale Bildlaufleiste; der Verlaufsdialog besitzt bei Bedarf einen eigenen Scrollbereich. Die Geräteintegration muss die vorhandenen Variablen aktualisieren; die Kachel verbindet sich nicht selbst mit der Wärmepumpe.

## Anpassung an die Kachelgröße

Ein `ResizeObserver` erkennt Änderungen der verfügbaren Fläche, auch beim Vergrößern oder Verkleinern der eingebetteten Symcon-Kachel. Die Übersicht wird unter Beibehaltung der Proportionen in Breite und Höhe eingepasst. Durch die entsprechend angepasste interne Layoutbreite bleibt die sichtbare Kachelbreite ausgenutzt. Live-Updates, längere Statusbeschriftungen und geladene Schriftarten lösen eine erneute Einpassung aus. Bei geringer Höhe werden zunächst Abstände und Innenränder kompakter, bevor die gesamte Ansicht verkleinert wird. In sehr flachen Kacheln wird auch der Text entsprechend kleiner.

Der Temperaturverlauf öffnet außerhalb der skalierten Übersicht als Dialog. Seine Bedienelemente verwenden die normale Schriftgröße; bei geringer Höhe lässt sich der Dialog intern scrollen. Istwerte und Statusanzeigen bleiben schreibgeschützt.

## CPU-Verbrauch

Die erfolgreiche Monatsarchivprüfung wird bei gleichem Gesamtzählerstand höchstens einmal pro Minute durchgeführt. Ein neuer Zählerstand, ein Monatswechsel oder Übernehmen der Instanzkonfiguration löst sofort eine neue Prüfung aus. Nachträgliche Archivkorrekturen bei unverändertem Zählerstand werden spätestens bei der nächsten Prüfung nach 60 Sekunden berücksichtigt. Die aktuellen Quellwerte und Bedienelemente werden weiterhin direkt aktualisiert.

Im Browser bleiben unveränderte Statusfelder erhalten. Die Größenanpassung führt ihre Layoutsuche nur aus, wenn sich die verfügbare Fläche oder die Inhaltsabmessungen ändern; eigene Größenmeldungen lösen keine fortlaufende Neuberechnung aus. Das reduziert unnötige Arbeit bei laufenden Quellenupdates. Eine prozentuale CPU-Einsparung auf der echten Symcon-Anlage wurde hier nicht gemessen.

## Vorbelegte Quellen

| Wert | ID | Eigenschaft |
|---|---:|---|
| Betriebsart Heizung | 41758 | `ModeID` |
| Heizkreispumpe | 35797 | `HeatingPumpID` |
| Primärpumpe | 51638 | `PrimaryPumpID` |
| Rücklauftemperatur Sekundär | 26909 | `ReturnID` |
| Ventil Heizen/Warmwasser | 16126 | `ValveID` |
| Verdichterbetrieb | 12943 | `CompressorID` |
| Vorlauftemperatur Primärquelle | 14749 | `PrimaryFlowID` |
| Vorlauftemperatur Sekundär | 35782 | `FlowID` |
| Warmwasser Ist | 46137 | `WaterID` |
| Raumsolltemperatur | 55945 | `RoomTargetID` |
| Warmwasser-Solltemperatur | 10078 | `WaterTargetID` |

Alle IDs sind in der Instanz änderbar. Beim Update werden die bisherigen Standard-IDs automatisch auf die neuen Anlagen-IDs umgestellt. Abweichende eigene Zuordnungen bleiben erhalten. Die elektrischen IDs 48059 und 58570 bleiben bestehen. Eine Raum-Isttemperatur war nicht vorhanden und wird nicht aus dem Raumsollwert abgeleitet. Abluft, Fortluft, Lüftungsaußentemperatur, Lüftungsbetriebsart, Filterrestzeit und Zuluft-Volumenstrom sind ausgeschlossen.

Temperaturen müssen in °C vorliegen. Pumpen und Umschaltventil verwenden die formatierten Quellwerte; die Bedeutung des Ventilwerts wird nicht eigenmächtig umgedreht. Aktive Pumpen-/Verdichtersignale werden in den Statusfeldern grün hervorgehoben.

## Momentanleistung und Stromverbrauch

Die Anzeige verwendet 2 Nachkommastellen für die Momentanleistung, 0 für den Gesamtverbrauch und 1 für den Monatsverbrauch, auch in den Verläufen.

Gesamt- und Monatsverbrauch verwenden bei Zählerarchivierung Balkendiagramme des Verbrauchs je Archivintervall. Verdichter (12943) und Ventil (16126) öffnen Statusverläufe als Balkendiagramme mit derselben Zeitnavigation; hierfür ist aktive Archivierung erforderlich. Boolesche Statusarchive werden unabhängig vom Aggregationstyp über ihre Rohwerte gelesen.

Drei zusätzliche Karten zeigen **elektrische Momentanleistung (kW)**, **kumulierten Gesamtverbrauch (kWh)** und **Verbrauch des laufenden Kalendermonats (kWh)**. Alle Karten öffnen per Klick einen Verlauf mit Zeitbereichswahl und Vor-/Zurücknavigation.

In der Instanz die tatsächlich vorhandenen elektrischen Variablen auswählen:

| Eigenschaft | Bedeutung | Voreinstellung |
|---|---|---|
| `PowerID` | Aktuelle elektrische Leistungsaufnahme | `48059` |
| `TotalEnergyID` | Kumulativer elektrischer Energiezähler | `58570` |
| `MonthlyEnergyID` | Bereits vorhandener Monatszähler, falls verfügbar | `0` – aus Gesamtzähler berechnen |
| `PowerUnit` | Eingabeeinheit W oder kW | `W` |
| `TotalEnergyUnit` | Eingabeeinheit Wh oder kWh | `kWh` |
| `MonthlyEnergyUnit` | Eingabeeinheit eines vorhandenen Monatszählers | `auto` |
| `EnergyTimeZone` | Zeitzone der Kalendermonate | `Europe/Vienna` |

Die mitgeteilten Anlagenvariablen **48059 (W)** und **58570 (kWh)** sind vorbelegt. Bei fehlenden Quellen erscheinen **—** und ein Hinweis. Die Einheitenautomatik liest das wirksame Quellprofil-Suffix bzw. `SUFFIX` der Darstellung. Falls keine eindeutige Einheit vorliegt, die Eingabeeinheit ausdrücklich auswählen. W werden durch 1000 in kW, Wh durch 1000 in kWh umgerechnet; die archivierten Rohwerte müssen in derselben Eingabeeinheit vorliegen. Es muss sich um **Stromverbrauch**, nicht um erzeugte Heizwärme handeln. Negative oder nichtnumerische Verbrauchswerte werden abgewiesen. Es werden keine Leistungswerte aus dem Verdichterstatus geschätzt.

### Monatsverbrauch

Bei `MonthlyEnergyID > 0` wird die vorhandene Monatsvariable unverändert in kWh umgerechnet. Ihr Graph verwendet ihr eigenes Archiv. Diese Quelle ist dafür verantwortlich, dass der Wert tatsächlich den laufenden Monat darstellt.

Ohne Monatsvariable wird der aktuelle Gesamtzählerstand abzüglich des letzten archivierten Gesamtzählerstands **am oder vor dem Monatsbeginn** angezeigt. Die Monatsgrenze wird in `EnergyTimeZone` berechnet, einschließlich Sommer-/Winterzeit und Jahreswechsel. `Monatsverbrauch · MM.JJJJ` zeigt den ausgewerteten Monat. Voraussetzung: aktive Archivierung des Gesamtzählers und ein aktueller Wert aus dem laufenden Monat. Der letzte archivierte Wert wird an der Monatsgrenze als gehaltener Zählerstand verwendet; es ist keine rückwirkende exakte Messung zum Monatswechsel. Ein erst später begonnener Archivverlauf ersetzt keinen fehlenden Monatsanfangswert.

Alle Rohschritte seit Monatsbeginn werden auf rückläufige Zählerstände geprüft. Bei Zählerrücksetzung, fehlender Grenze oder ungültiger Einheit erscheint ein Hinweis statt eines negativen, unvollständigen oder erfundenen Monatsverbrauchs. Für besonders dichte Archive kann alternativ ein vorhandener Monatszähler verwendet werden. Die Auswertung liest höchstens 500.000 Datensätze pro Anfrage, seitenweise mit maximal 10.000 Einträgen im Speicher. Wird die Grenze erreicht oder ist eine Sekunde an einer Seitengrenze nicht eindeutig, erfolgt ein Fehlerhinweis statt stiller Kürzung.

### Elektrische Verläufe

- **Momentanleistung:** Einzelwerte für kurze Zeitbereiche; Stundenmittel für mehr als 48 Stunden. Als Standardwert archivieren.
- **Gesamt- und Monatsverbrauch bei Zählerarchivierung:** Archive Control als **Zähler** konfigurieren. Die Balken zeigen den Verbrauch je Intervall aus `AC_GetAggregatedValues()`; bei Zählerarchiven liefert `Avg` die Verbrauchssumme. Bis 48 Stunden werden Stundenaggregate, bei 7/30 Tagen Tagesaggregate verwendet. Wh werden in kWh umgerechnet. Der berechnete Monatsverlauf verwendet das Archiv des Gesamtzählers; eine zugeordnete Monatsvariable verwendet ihr eigenes Zählerarchiv. Es werden keine kumulierten Zählerstände gemittelt oder addiert. Die Zeiträume sind rollende Fenster; die Balken folgen den verfügbaren Archivintervallen. Fehlende Aggregate bleiben leer; gegebenenfalls im Archive Control neu aggregieren lassen.
- **Bestehende Standardwertarchive:** Als Rückwärtskompatibilität zeigen sie weiterhin ausdrücklich beschriftete archivierte Zählerstände bzw. den laufenden Monatsverbrauch, bei langen Bereichen mit stündlichen Stützpunkten. Für Verbrauchssummen je Intervall die Quellen als Zähler archivieren und aggregieren lassen.
- **Aktuelle Karten:** Der Gesamtverbrauch bleibt der aktuelle kumulierte Zählerstand, der Monatsverbrauch der laufende Monatswert. Die Graphenumstellung verändert diese Anzeigen nicht.

Die Achsen-/Tabellenbeschriftung und der Tooltip verwenden kW oder kWh. Historische Graphen zeigen ausschließlich Archivwerte, nicht nachträglich angehängte aktuelle Werte. Ein aktueller Kartenwert kann deshalb neuer sein als der zuletzt archivierte Messpunkt. Die Kachel aktiviert keine Archivierung und schreibt weder Verbrauchsvariablen noch Archivdaten.

## Temperaturverlauf

Eine der vier Temperaturkarten anklicken oder per Tastatur aktivieren. Es öffnet sich ein Graph mit:

- **1 Stunde, 6 Stunden, 24 Stunden, 7 Tage oder 30 Tage** als Zeitbereich.
- **Zurück** und **Vor**: um einen vollständigen gewählten Zeitraum blättern.
- **Jetzt**: an die aktuelle Zeit springen und Daten neu laden; Navigation in die Zukunft ist gesperrt.
- Zeitpunkt und Temperatur am nächstgelegenen Messpunkt per Maus/Touch sowie einer aufklappbaren Tabelle mit maximal 500 Werten.

Die Zeiträume sind feste, rollende Dauern; 24 Stunden sind unabhängig von Sommerzeitwechseln 86.400 Sekunden. Datum und Uhrzeit verwenden die lokale Browser-Zeitzone. Die Zeitachse reicht immer über den ausgewählten Bereich, nicht nur zwischen den vorhandenen Messpunkten. Ein normaler Livewert-Refresh verschiebt ein geöffnetes historisches Fenster nicht. Zum Aktualisieren **Jetzt** drücken. Der Dialog lässt sich mit **✕** oder **Escape** schließen.

![Verlaufsdialog mit synthetischen Beispieldaten](docs/history.png)

### Archivierung als Voraussetzung

Im Symcon **Archive Control** muss die Archivierung für `14749`, `35782`, `26909` und `46137` bzw. deine abweichenden Isttemperatur-IDs aktiv sein. Archivierung als **Standardwert**, nicht als Zähler konfigurieren. Die Kachel schaltet die Archivierung nicht selbst ein und verändert keine historischen Daten. Alte Werte werden durch Aktivierung nicht nachträglich erzeugt.

In der Instanz kann **Archive Control** ausgewählt werden. Mit `0` sucht die Kachel für die angeklickte Temperatur automatisch das erste Archiv mit aktiver Aufzeichnung. Bei mehreren Archiven eine explizite Instanz auswählen; eine ausdrücklich gewählte Instanz wird nicht stillschweigend ersetzt. Fehlende Archivierung beeinflusst den Graphen, die aktuellen Werte bleiben nutzbar.

Bis einschließlich 48 Stunden werden `AC_GetLoggedValues()`-Einzelwerte gelesen. Für Temperaturverläufe über 7 und 30 Tage werden über `AC_GetAggregatedValues(..., 0, ...)` Stundenmittelwerte verwendet und als solche beschriftet. Keine historischen Werte werden aus dem aktuellen Wert erfunden. Linien verbinden vorhandene Messpunkte; Zwischenwerte sind nicht separat gemessen. Fehlende Daten und Fehler erscheinen im Dialog. Wird das API-Limit von 10.000 Einträgen erreicht, wird der Graph nicht unbemerkt abgeschnitten: einen kleineren Zeitraum oder 7/30 Tage mit Stundenmitteln wählen.

Die Datenübertragung läuft über die HTML-SDK-Brücke und den Visualisierungsnachrichtenkanal. Jede Anfrage hat eine eigene Kennung; verspätete Antworten aus einem anderen Zeitraum, einer anderen Temperatur, einem anderen geöffneten Client oder einem geschlossenen Dialog werden verworfen. Nach 15 Sekunden ohne Antwort wird ein Hinweis angezeigt. Keine WebHooks, CDN-, npm- oder Chartbibliotheken erforderlich.

## Heizung bedienen

Die Betriebsart (41758), Raumsolltemperatur (55945) und Warmwasser-Solltemperatur (10078) besitzen eigene Bedienelemente. Betriebsarten werden direkt ausgewählt; Solltemperaturen werden mit Übernehmen-Schaltflächen gesendet. Befehle laufen ausschließlich über `RequestAction()` der jeweiligen Quellvariable. Es werden keine Quellwerte mit `SetValue()` überschrieben. Ohne vorhandene Variablenaktion bzw. bei falschem Variablentyp ist das betreffende Bedienelement deaktiviert.

Die Betriebsart wird über direkt sichtbare Auswahlbuttons geschaltet; die aktuelle Betriebsart ist hervorgehoben. Ein Klick übergibt den Befehl, die Hervorhebung folgt erst der Quellrückmeldung. Die Auswahl verwendet vorhandene String-Assoziationen des Quellprofils bzw. der Darstellung. Ohne Auswahlliste wird ein Textfeld für den exakten Betriebsartwert angeboten; die Geräteintegration muss den Wert unterstützen. Es werden keine Betriebsartcodes geraten.

Die beiden Solltemperaturen werden über Dropdown-Menüs in ganzen 1-°C-Schritten ausgewählt. Temperaturgrenzen werden aus dem wirksamen Quellprofil bzw. der Darstellung übernommen und serverseitig geprüft. Ohne gültigen Profilbereich gelten 10–30 °C für den Raum und 10–60 °C für Warmwasser bei 1 °C Schrittweite. Nichtnumerische Werte, unzulässige Schritte und Werte außerhalb des Bereichs werden abgewiesen. Die Kachel zeigt nach Befehlsübergabe weiterhin den tatsächlichen Quellwert; erst die Rückmeldung der Geräteintegration bestätigt eine Änderung. Fehler der Quellaktion erscheinen in der Kachel.

Die Diagnose ist ausschließlich in der Instanz über **Quellen-Diagnose anzeigen** mit den konfigurierten Quell-IDs und dem Archiv verfügbar. In der Kachel wird keine Quellendiagnose eingeblendet. Die Fußzeile mit Quellenstatus und letztem Quellupdate verwendet 9 px Schriftgröße.

## Entwicklung und Prüfungen

Benötigt PHP mit `mbstring`, Python 3 und für den Browsertest Playwright/Chromium. Produktiv werden nur die PHP-/HTML-Dateien des Moduls benötigt, keine Testpakete oder Viessmann-Zugangsdaten.

```sh
git clone https://github.com/symcon/SymconStubs.git /tmp/SymconStubs
git -C /tmp/SymconStubs checkout 31f2e0478eb3763236fcf44ea4144271fdeddfd5
php -l Vitocal222CTile/module.php
SYMCON_STUBS=/tmp/SymconStubs php tests/module_test.php
python3 -m pip install playwright==1.62.0
python3 -m playwright install chromium
python3 tests/browser_test.py
# Alternativ: CHROMIUM_PATH=/usr/bin/chromium python3 tests/browser_test.py
python3 tests/build.py
```

Der Build legt ZIP und SHA-256-Datei standardmäßig **außerhalb** des Repositorys im benachbarten Ordner `Viessmann-Vitocal-222-artifacts/` ab. Ein anderes externes Ziel ist mit `python3 tests/build.py --output-dir /tmp/vitocal-artifacts` möglich. Ausgaben innerhalb des Repositorys werden abgewiesen: Symcon behandelt nicht reservierte Unterordner wie `dist` als Module und verlangt dort eine `module.json`. GitHub Actions stellt die Pakete als Build-Artefakt bereit; ZIP-Dateien werden nicht im Modul-Repository abgelegt.

Tests erzeugen ihre Fixtures unter `tests/output/` und aktualisieren die ausdrücklich synthetischen Vorschau-Screenshots. `tests/build.py` erstellt das ZIP und die SHA-256-Datei und prüft Manifeste, Identitäten, Dateiliste und ZIP-Integrität. [Prüfbericht und Grenzen](docs/VALIDATION.md).

Basiert auf der [Vitovent-300-F-Kachel](https://github.com/somm86/Viessmann-Vitovent-300F-Kachel-Symcon), Referenzcommit `75693d21426088d10e37771b681850b7c8e770f3`, mit eigenen GUIDs und eigenem Präfix. Keine offizielle Verbindung zu Viessmann oder Symcon. Die referenzierte Vorlage enthält keine Lizenzdatei; dieses Projekt erteilt keine zusätzliche Lizenz für übernommenen Code.
