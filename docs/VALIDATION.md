# Prüfbericht · Version 1.5

Lokal geprüft mit PHP 8.4.24, offiziellen SymconStubs (Commit `31f2e0478eb3763236fcf44ea4144271fdeddfd5`), Python 3, Playwright 1.62.0 und System-Chromium. PHP wurde aus anhand signierter Debian-Indizes geprüften Paketen lokal unter `/tmp/php-local` extrahiert. Die Testlaufzeit ist keine Produktivabhängigkeit.

## Bestanden

- PHP-Syntaxprüfung des Moduls.
- Bibliotheksprüfung: Laden der vollständigen `library.json` mit dem offiziellen SymconStubs-Lader, einschließlich Erkennung aller Modulordner. Der frühere Test lud nur das Einzelmodul und erkannte deshalb den ungültigen Ordner `dist` nicht.
- Build-Ausgaben außerhalb des Repositorys; Ausgabeziele im Checkout werden vor Dateierzeugung abgewiesen. Nicht reservierte sichtbare Verzeichnisse ohne `module.json` verhindern den Build.
- **63 Modulprüfungen**: neun Quellen und Referenzen, Entfernen der Sollwertkonfiguration und der beiden Schreibaktionen, Betriebsart als reiner Status, serverseitige Ablehnung aller Betriebsart- und Sollwert-Schreibaktionen ohne Aufruf der vorhandenen Quellaktion, Quellupdates, HTML-Escaping und wiederholtes ApplyChanges.
- Archivintegration über das echte offizielle **ArchiveControl-Stub-Modul**: aktive/fehlende Archivierung, alle vier Temperaturzuordnungen, Rohwerte in chronologischer Reihenfolge, leere Zeitfenster ohne erfundene Ersatzwerte, Stundenmittel, automatisch und explizit ausgewählte Archive, Archivwechsel, falsche Instanz, Ablehnung von Zählerarchiven, Zeitbereichsgrenzen, Quellen-Allowlist, nichtnumerische Quellen, Erkennung des 10.000-Werte-Limits, korrelierte SDK-Antworten sowie lesbare Fehlermeldungen bei fehlerhaften Anfragen. Archivschreibaufrufe existieren ausschließlich im Test zur Fixture-Erzeugung.
- **71 Browserprüfungen**: vier anklickbare Isttemperaturen ohne Sollwerteingaben, Betriebsart nur als Textstatus ohne Auswahl, ausschließlich lesende Browser-Anfragen, SDK-Verfügbarkeit, Modal und Tastaturbedienung, Zeichnen von Archivdaten, Tabelle, Messpunktanzeige, alle fünf Zeitbereiche, Vor-/Zurückblättern um eine vollständige Fensterbreite, Zukunftssperre, Stundenmittel-Kennzeichnung, einzelner Messpunkt, leere Daten, Archivfehler, Zeitüberschreitung, Verwerfen verspäteter Antworten und Wechsel zwischen Temperaturen. Kein horizontaler Überlauf bei 320, 390, 768 und 1200 Pixel Breite; keine JavaScript-Fehler.
- Paketprüfung: gültige Manifeste und unveränderte Modulidentitäten, vollständige Laufzeit-/Dokumentationsdateien, ZIP-Integrität und SHA-256-Prüfsumme.

## Grenzen

Keine echte Symcon-Instanz oder Wärmepumpe ist mit dieser Entwicklungsumgebung verbunden. Die Modultests nutzen offizielle Stubs und synthetische Archivdaten, der Browsertest eine simulierte HTML-SDK-Brücke. Die Screenshots enthalten synthetische Beispielwerte; der Verlaufs-Screenshot zeigt eine künstliche Kurve und keine Aufzeichnung der Anlage.

Die vorhandenen Live-Quellwerte und Archivdaten müssen nach Installation in deiner Symcon-Instanz verglichen werden. Für Historie ist aktive Standardwert-Archivierung der vier Isttemperaturen erforderlich. Historie, Profilwerte, Aktionen und tatsächliche Geräteantworten deiner Anlage konnten hier nicht geprüft werden. Die Betriebsart wird ausschließlich als formatierter Quellwert angezeigt; alle früheren Schreibaktionen werden abgewiesen.

Die Kachel enthält kein Anlagenschema und keine Schema-Exporte mehr. Die Browserprüfung kontrolliert, dass ausschließlich der Verlaufsgraph als SVG verbleibt und die vier Temperaturkarten und Statusfelder weiterhin aktualisiert werden.

Der vorhandene GitHub-Workflow führt die lokalen Prüfungen bei Push/PR aus. Ein erfolgreicher lokaler Lauf belegt keinen erfolgreichen GitHub-CI-Lauf oder eine Installation auf dem Symcon-Server.
