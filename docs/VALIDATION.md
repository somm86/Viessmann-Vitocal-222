# Prüfbericht · Version 1.0

Geprüft in der Cloud-Entwicklungsumgebung am 25.10.2026 mit PHP 8.4.24, Python 3.12, Playwright 1.62.0 und System-Chromium. PHP wurde aus über signierte Debian-Paketindizes verifizierten Paketen lokal unter `/tmp/php-local` extrahiert, da das Systemverzeichnis schreibgeschützt ist. Keine TLS- oder Paketprüfung wurde deaktiviert. Dieser Pfad ist eine lokale Testhilfe, keine Symcon-Laufzeitabhängigkeit.

## Bestanden

- PHP-Syntaxprüfung des Moduls.
- **45 Modulprüfungen** mit offiziellen SymconStubs, Commit `31f2e0478eb3763236fcf44ea4144271fdeddfd5`: elf Quellen, Default-IDs, Referenzen, Live-Updates, fehlende und gelöschte Quellen, String-Betriebsart aus moderner Aufzählung, exakte Werte, entfernte Optionen, wirkliche Skript-Dispatch-Aufrufe, Raum- und Warmwasser-Sollwerte, Integer-/Float-Erhalt, Bereichs- und Schrittweitenprüfung, deaktivierte Aktionen, wiederholtes ApplyChanges und HTML-Escaping. Die Aktionsfixture verändert die Quelle absichtlich nicht, um optimistische Erfolgsmeldungen auszuschließen.
- **27 Browserprüfungen**: später verfügbare SDK-Brücke, alle drei Steuerungen, Quellbestätigung, kein vorzeitiges Umschalten der Anzeige, Sperre paralleler Anforderungen, sichtbare PHP-Aktionsfehler, 15-Sekunden-Zeitüberschreitung, Bereichsvalidierung, sichere Textausgabe und fehlende Quellen. Kein horizontaler Überlauf bei 320, 390, 768 und 1200 Pixel Breite; keine JavaScript-Fehler.
- Paketprüfung durch `tests/build.py`: gültige Manifeste, eigene GUIDs und Präfix, vollständige Dateiliste, ZIP-Integrität und SHA-256-Datei.

## Grenzen und Test an deiner Anlage

Keine echte Symcon-Instanz oder Wärmepumpe ist mit dieser Cloud-Umgebung verbunden. Die Tests verwenden die offiziellen Stubs und eine simulierte HTML-SDK-Brücke. Gerätefunktionen wurden hier nicht geschaltet. Der Screenshot enthält synthetische Testwerte und Auswahloptionen.

Nach Installation die elf IDs und die vorhandenen Werte prüfen. Betriebsart `53049` benötigt ein wirksames Profil oder eine moderne Aufzählung mit realen Auswahlwerten und eine funktionierende Aktion. Die Sollwertquellen `10939` und `27660` benötigen wirksame klassische numerische Profile mit Bereich und positiver Schrittweite sowie ihre vorhandenen Aktionen. Moderne numerische Darstellungen werden derzeit bewusst nur gelesen. Bei anderen schaltbaren Quell-IDs die Instanzkonfiguration entsprechend anpassen. Keine erfundenen Aktionscodes oder Temperaturgrenzen übernehmen.

Für einen Gerätetest einen laut Quellprofil zulässigen Sollwert bzw. eine angebotene Betriebsart anfordern, Quellupdate und Geräteanzeige vergleichen und bei Bedarf den ursprünglichen Wert wiederherstellen. Eine Quellbestätigung allein beweist nicht unabhängig den physischen Zustand der Anlage. Die Diagnose zeigt gesperrte oder fehlende Voraussetzungen an.

Der GitHub-Workflow wurde als Datei erstellt. Ein tatsächlicher CI-Lauf, Veröffentlichung auf GitHub und Installation auf einem Symcon-Server sind damit nicht belegt.
