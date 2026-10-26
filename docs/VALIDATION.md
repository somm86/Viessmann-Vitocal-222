# Prüfbericht · Version 2.4

Lokal geprüft mit PHP 8.4.24, offiziellen SymconStubs (Commit `31f2e0478eb3763236fcf44ea4144271fdeddfd5`), Python 3, Playwright 1.62.0 und System-Chromium. PHP wurde aus anhand signierter Debian-Indizes geprüften Paketen lokal unter `/tmp/php-local` extrahiert. Die Testlaufzeit ist keine Produktivabhängigkeit.

## Bestanden

- PHP-Syntaxprüfung des Moduls.
- Bibliotheksprüfung: Laden der vollständigen `library.json` mit dem offiziellen SymconStubs-Lader, einschließlich Erkennung aller Modulordner. Der frühere Test lud nur das Einzelmodul und erkannte deshalb den ungültigen Ordner `dist` nicht.
- Build-Ausgaben außerhalb des Repositorys; Ausgabeziele im Checkout werden vor Dateierzeugung abgewiesen. Nicht reservierte sichtbare Verzeichnisse ohne `module.json` verhindern den Build.
- **105 Modulprüfungen**: neun Quellen und Referenzen, Entfernen der Sollwertkonfiguration und der beiden Schreibaktionen, Betriebsart als reiner Status, serverseitige Ablehnung aller Betriebsart- und Sollwert-Schreibaktionen ohne Aufruf der vorhandenen Quellaktion, Quellupdates, HTML-Escaping und wiederholtes ApplyChanges.
- Archivintegration über das echte offizielle **ArchiveControl-Stub-Modul**: aktive/fehlende Archivierung, alle vier Temperaturzuordnungen, Rohwerte in chronologischer Reihenfolge, leere Zeitfenster ohne erfundene Ersatzwerte, Stundenmittel, automatisch und explizit ausgewählte Archive, Archivwechsel, falsche Instanz, Ablehnung von Zählerarchiven, Zeitbereichsgrenzen, Quellen-Allowlist, nichtnumerische Quellen, Erkennung des 10.000-Werte-Limits, korrelierte SDK-Antworten sowie lesbare Fehlermeldungen bei fehlerhaften Anfragen. Archivschreibaufrufe existieren ausschließlich im Test zur Fixture-Erzeugung.
- **123 Browserprüfungen**: vier anklickbare Isttemperaturen ohne Sollwerteingaben, Betriebsart nur als Textstatus ohne Auswahl, ausschließlich lesende Browser-Anfragen, SDK-Verfügbarkeit, Modal und Tastaturbedienung, Zeichnen von Archivdaten, Tabelle, Messpunktanzeige, alle fünf Zeitbereiche, Vor-/Zurückblättern um eine vollständige Fensterbreite, Zukunftssperre, Stundenmittel-Kennzeichnung, einzelner Messpunkt, leere Daten, Archivfehler, Zeitüberschreitung, Verwerfen verspäteter Antworten und Wechsel zwischen Temperaturen. Kein horizontaler Überlauf bei 320, 390, 768 und 1200 Pixel Breite; keine JavaScript-Fehler.
- Höhenanpassung geprüft bei 1200×220, 640×220, 320×260, 800×160, 320×480, 1800×180, 240×150 und 1200×1100 Pixeln: sämtliche Übersichtselemente einschließlich Fußzeile liegen innerhalb des sichtbaren Bereichs, ohne Seiten-Scrollleisten. Größenwechsel funktionieren ohne Neuladen; Vergrößern stellt den Originalmaßstab wieder her. Längere Livewerte lösen eine erneute Einpassung aus. Ein eigener Iframe-Test ändert ausschließlich die Höhe des umgebenden Tiles und prüft Verkleinern und Vergrößern. Die skalierten Temperaturkarten öffnen weiterhin den unabhängig begrenzten Verlaufsdialog.
- Elektrische Werte: W→kW und Wh→kWh, echte Nullwerte, fehlende IDs und unbekannte Einheiten, negative Werte, Quellen-Referenzen, optionale Monatsvariable und deren Graph, Gesamtzählerstand statt Counter-Aggregatmittel, vollständige Pagination über 10.000 Rohwerte, Monatsberechnung mit archiviertem Anfangsstand, fehlende Grenze und Rücksetzung trotz anschließend höherem Endstand. Zeitzonengrenzen explizit bei Sommer-/Winterzeit, Jahreswechsel und Schaltjahr geprüft.
- Elektrische Browserprüfungen: drei Karten, passende kW-/kWh-Einheiten in Anzeige, Tabelle und Tooltip, Live-Updates, fehlende Daten als Strich statt Null, sowie unterbrochene Linien am Monatswechsel.
- Paketprüfung: gültige Manifeste und unveränderte Modulidentitäten, vollständige Laufzeit-/Dokumentationsdateien, ZIP-Integrität und SHA-256-Prüfsumme.

## Grenzen

Keine echte Symcon-Instanz oder Wärmepumpe ist mit dieser Entwicklungsumgebung verbunden. Die Modultests nutzen offizielle Stubs und synthetische Archivdaten, der Browsertest eine simulierte HTML-SDK-Brücke. Die Screenshots enthalten synthetische Beispielwerte einschließlich der elektrischen Daten; der Verlaufs-Screenshot zeigt eine künstliche Kurve und keine Aufzeichnung der Anlage.

Die vorhandenen Live-Quellwerte und Archivdaten müssen nach Installation in deiner Symcon-Instanz verglichen werden. Für Temperatur- und Leistungshistorie ist aktive Standardwert-Archivierung der jeweiligen Variablen erforderlich. Für Verbrauchszähler werden archivierte Rohzählerstände benötigt. Die elektrischen Quellen sind mit 48059 (W) und 58570 (kWh) vorbelegt; ihre Verfügbarkeit muss in der Anlage geprüft werden. Die Monatsberechnung benötigt zusätzlich ein passendes Archiv und einen Anfangsstand. Historie, Profilwerte, Aktionen und tatsächliche Geräteantworten deiner Anlage konnten hier nicht geprüft werden. Die Betriebsart wird ausschließlich als formatierter Quellwert angezeigt; alle früheren Schreibaktionen werden abgewiesen.

Die Quellendiagnose ist aus der Kachel entfernt; die Fußzeile für Quellenstatus und Update-Zeitpunkt verwendet 9 px. Die bestehende Diagnose in der Instanz bleibt verfügbar.

Die Kachel enthält kein Anlagenschema und keine Schema-Exporte mehr. Die Browserprüfung kontrolliert, dass kein Anlagenschema vorhanden ist und die vier Temperaturkarten und Statusfelder weiterhin aktualisiert werden. Das Wärmepumpensymbol wurde aus dem Kopfbereich entfernt.

Der vorhandene GitHub-Workflow führt die lokalen Prüfungen bei Push/PR aus. Ein erfolgreicher lokaler Lauf belegt keinen erfolgreichen GitHub-CI-Lauf oder eine Installation auf dem Symcon-Server.

Version 2.4 prüft zusätzlich archivierte boolesche Statuswechsel einschließlich Anfangszustand, Liniendarstellung und Zustandsbeschriftung für Verdichter und Ventil sowie Verbrauchsbalken ohne Verbindungslinie.

Regression 2.4: Beide booleschen Quellen liefern auch bei Archiv-Aggregationstyp 2 dieselben Rohzustände und Anfangswerte; es werden keine numerischen Mittelwerte für Schaltzustände verwendet.
