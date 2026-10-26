<?php

class Vitocal222CTile extends IPSModule
{
    private const LEGACY = '{4153A8D4-5C33-C65F-C1F3-7B61AAF99B1C}';

    private const SOURCES = [
        'mode' => ['ModeID', 53049, 'Betriebsart Heizung'],
        'heatingPump' => ['HeatingPumpID', 20926, 'Heizkreispumpe'],
        'primaryPump' => ['PrimaryPumpID', 39794, 'Primärpumpe'],
        'return' => ['ReturnID', 21875, 'Rücklauf Sekundär'],
        'valve' => ['ValveID', 53786, 'Umschaltventil Heizung / Warmwasser'],
        'compressor' => ['CompressorID', 31722, 'Verdichter'],
        'primaryFlow' => ['PrimaryFlowID', 18104, 'Vorlauf Primärquelle'],
        'flow' => ['FlowID', 18969, 'Vorlauf Sekundär'],
        'water' => ['WaterID', 56634, 'Warmwasser Ist']
    ];

    private const TEMPERATURES = ['primaryFlow', 'return', 'flow', 'water'];
    private const ARCHIVE = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';

    private const ENERGY = [
        'power' => ['PowerID', 'PowerUnit', 'Momentanleistung', 'kW'],
        'total' => ['TotalEnergyID', 'TotalEnergyUnit', 'Gesamtverbrauch', 'kWh'],
        'month' => ['MonthlyEnergyID', 'MonthlyEnergyUnit', 'Monatsverbrauch', 'kWh']
    ];

    private function energySource(string $key): array
    {
        $spec = self::ENERGY[$key];
        $id = $this->ReadPropertyInteger($spec[0]);
        $entry = ['value' => null, 'unit' => $spec[3], 'updated' => 0, 'variableID' => $id, 'reason' => '', 'factor' => null];
        try {
            if ($id <= 0 || !IPS_VariableExists($id)) { throw new RuntimeException($spec[2] . ': Quellvariable in der Instanz auswählen.'); }
            $v = IPS_GetVariable($id); $value = GetValue($id);
            if (!in_array($v['VariableType'], [1, 2], true) || (!is_float($value) && !is_int($value)) || !is_finite((float) $value) || $value < 0) {
                throw new RuntimeException('Nichtnegativer numerischer Messwert erforderlich.');
            }
            $unit = $this->ReadPropertyString($spec[1]);
            if ($unit === 'auto') {
                $profile = $this->profile($v, $id);
                $p = $this->presentation($id);
                $unit = $profile !== null ? trim($profile['Suffix']) : trim($p['SUFFIX'] ?? '');
            }
            $unit = strtolower(trim($unit));
            $factors = $key === 'power' ? ['w' => 0.001, 'kw' => 1.0] : ['wh' => 0.001, 'kwh' => 1.0];
            if (!isset($factors[$unit])) { throw new RuntimeException('Einheit nicht erkannt. Eingabeeinheit in der Instanz auswählen.'); }
            $entry['factor'] = $factors[$unit];
            $entry['value'] = (float) $value * $entry['factor'];
            $entry['updated'] = (int) $v['VariableUpdated'];
        } catch (Throwable $e) { $entry['reason'] = $e->getMessage(); }
        return $entry;
    }

    private function energyZone(): DateTimeZone
    {
        try { return new DateTimeZone($this->ReadPropertyString('EnergyTimeZone')); }
        catch (Throwable $e) { throw new RuntimeException('Ungültige Zeitzone für die Monatsberechnung. Beispiel: Europe/Vienna.'); }
    }

    private function monthStart(int $stamp): int
    {
        return (new DateTimeImmutable('@' . $stamp))->setTimezone($this->energyZone())->modify('first day of this month')->setTime(0, 0)->getTimestamp();
    }

    private function archiveFor(int $id): int
    {
        $selected = $this->ReadPropertyInteger('ArchiveID');
        $archives = IPS_GetInstanceListByModuleID(self::ARCHIVE);
        if ($selected > 0) {
            if (!in_array($selected, $archives, true)) { throw new RuntimeException('Ausgewähltes Archive Control ist nicht verfügbar.'); }
            $archives = [$selected];
        }
        foreach ($archives as $candidate) {
            if (AC_GetLoggingStatus($candidate, $id)) { return $candidate; }
        }
        throw new RuntimeException('Keine aktive Archivierung für Variable ' . $id . '. Bitte im Archive Control aktivieren; vergangene Werte werden nicht nachträglich erzeugt.');
    }

    // Newest first, bounded-memory pagination. Never silently truncate counter history.
    private function energyRows(int $archive, int $id, int $start, int $end): Generator
    {
        $seen = 0;
        while ($end >= $start) {
            $rows = AC_GetLoggedValues($archive, $id, $start, $end, 10000);
            if (!is_array($rows)) { throw new RuntimeException('Archivdaten konnten nicht gelesen werden.'); }
            if (!$rows) { return; }
            usort($rows, function ($a, $b) { return $b['TimeStamp'] <=> $a['TimeStamp']; });
            foreach ($rows as $row) {
                if (++$seen > 500000) { throw new RuntimeException('Zu viele Zählerwerte für eine vollständige Auswertung (maximal 500.000).'); }
                if (!isset($row['TimeStamp'], $row['Value']) || !is_int($row['TimeStamp']) || $row['TimeStamp'] < $start || $row['TimeStamp'] > $end ||
                    (!is_int($row['Value']) && !is_float($row['Value'])) || !is_finite((float) $row['Value']) || $row['Value'] < 0) {
                    throw new RuntimeException('Ungültiger archivierter Leistungs- oder Zählerwert.');
                }
                yield $row;
            }
            if (count($rows) < 10000) { return; }
            $last = $rows[count($rows) - 1]['TimeStamp'];
            // An incomplete second cannot safely be skipped at a page boundary.
            if ($last === $rows[count($rows) - 2]['TimeStamp']) { throw new RuntimeException('Mehrere Zählerwerte an der Seitengrenze. Vollständige Auswertung nicht möglich.'); }
            if (count(AC_GetLoggedValues($archive, $id, $last, $last, 10000)) !== 1) { throw new RuntimeException('Mehrere Zählerwerte an der Seitengrenze. Vollständige Auswertung nicht möglich.'); }
            $end = $last - 1;
        }
    }

    private function counterBaseline(int $archive, int $id, int $stamp, float $factor): array
    {
        $rows = AC_GetLoggedValues($archive, $id, 0, $stamp, 1);
        if (!$rows) { throw new RuntimeException('Kein archivierter Gesamtzählerstand am oder vor dem Monatsbeginn.'); }
        $row = $rows[0];
        if (!isset($row['TimeStamp'], $row['Value']) || !is_int($row['TimeStamp']) || $row['TimeStamp'] < 0 || $row['TimeStamp'] > $stamp ||
            (!is_int($row['Value']) && !is_float($row['Value'])) || !is_finite((float) $row['Value']) || $row['Value'] < 0) {
            throw new RuntimeException('Ungültiger Zählerstand am Monatsbeginn.');
        }
        return ['value' => (float) $row['Value'] * $factor, 'time' => (int) $row['TimeStamp']];
    }

    private function monthlyEnergy(array $total): array
    {
        $entry = ['value' => null, 'unit' => 'kWh', 'updated' => 0, 'reason' => '', 'variableID' => 0, 'periodLabel' => '', 'note' => ''];
        try {
            $start = $this->monthStart(time());
            $entry['periodLabel'] = (new DateTimeImmutable('@' . $start))->setTimezone($this->energyZone())->format('m.Y');
            if ($this->ReadPropertyInteger('MonthlyEnergyID') > 0) {
                $entry = array_merge($entry, $this->energySource('month'));
                if ($entry['value'] !== null && $entry['updated'] < $start) {
                    $entry['value'] = null; $entry['reason'] = 'Noch kein aktueller Wert der Monatsvariable für diesen Monat.';
                }
                return $entry;
            }
            if ($total['value'] === null) { throw new RuntimeException('Für die Monatsberechnung einen Gesamtverbrauchszähler auswählen und dessen Einheit prüfen.'); }
            if ($total['updated'] < $start) { throw new RuntimeException('Kein aktueller Gesamtzählerstand für diesen Monat.'); }
            $entry['variableID'] = $total['variableID'];
            $archive = $this->archiveFor($total['variableID']);
            $baseline = $this->counterBaseline($archive, $total['variableID'], $start, $total['factor']);
            $newer = $total['value'];
            foreach ($this->energyRows($archive, $total['variableID'], $start + 1, time()) as $row) {
                $value = (float) $row['Value'] * $total['factor'];
                if ($value > $newer + 0.000000001) { throw new RuntimeException('Zählerrücksetzung oder rückläufiger Zählerstand im Monat. Verbrauch nicht eindeutig berechenbar.'); }
                $newer = $value;
            }
            if ($baseline['value'] > $newer + 0.000000001) { throw new RuntimeException('Zählerrücksetzung im Monat. Verbrauch nicht eindeutig berechenbar.'); }
            $entry['value'] = max(0.0, $total['value'] - $baseline['value']);
            $entry['note'] = 'Aus Gesamtzähler berechnet';
            $entry['updated'] = $total['updated']; $entry['baselineTime'] = $baseline['time'];
        } catch (Throwable $e) { $entry['reason'] = $e->getMessage(); }
        return $entry;
    }

    private function energyHistory(string $key, int $start, int $end): array
    {
        $derived = $key === 'month' && $this->ReadPropertyInteger('MonthlyEnergyID') === 0;
        $entry = $this->energySource($derived ? 'total' : $key);
        if ($entry['value'] === null) { throw new RuntimeException($entry['reason']); }
        $archive = $this->archiveFor($entry['variableID']);
        $factor = $entry['factor']; $hourly = $end - $start > 172800;
        $points = []; $buckets = []; $baselines = []; $newer = [];
        if ($key === 'power' && AC_GetAggregationType($archive, $entry['variableID']) !== 0) {
            throw new RuntimeException('Momentanleistung als Standardwert, nicht als Zähler archivieren.');
        }
        if ($key === 'power' && $hourly) {
            $rows = AC_GetAggregatedValues($archive, $entry['variableID'], 0, $start, $end, 10000);
            if (!is_array($rows)) { throw new RuntimeException('Archivdaten konnten nicht gelesen werden.'); }
            if (count($rows) >= 10000) { throw new RuntimeException('Zu viele Leistungswerte. Zeitraum verkleinern.'); }
            foreach ($rows as $r) {
                if (!isset($r['Avg'], $r['TimeStamp']) || !is_numeric($r['Avg']) || !is_finite((float) $r['Avg']) || $r['Avg'] < 0) { throw new RuntimeException('Ungültiger archivierter Leistungswert.'); }
                if ($r['TimeStamp'] >= $start && $r['TimeStamp'] <= $end) $points[] = ['time' => $r['TimeStamp'], 'value' => (float) $r['Avg'] * $factor];
            }
        } else {
            // Derived month values validate ALL raw counter steps since the first month boundary.
            $scanStart = $derived ? $this->monthStart($start) : $start;
            foreach ($this->energyRows($archive, $entry['variableID'], $scanStart, $end) as $row) {
                $time = $row['TimeStamp']; $value = (float) $row['Value'] * $factor;
                $month = $key === 'month' ? $this->monthStart($time) : 0;
                if ($derived) {
                    if (!isset($baselines[$month])) $baselines[$month] = $this->counterBaseline($archive, $entry['variableID'], $month, $factor)['value'];
                    if ((isset($newer[$month]) && $value > $newer[$month] + 0.000000001) || $value < $baselines[$month] - 0.000000001) {
                        throw new RuntimeException('Zählerrücksetzung im gewählten Monat. Monatsverlauf nicht eindeutig berechenbar.');
                    }
                    $newer[$month] = $value; $value = max(0.0, $value - $baselines[$month]);
                }
                if ($time < $start) continue;
                $bucket = $hourly ? (int) floor($time / 3600) : $time;
                if (isset($buckets[$bucket])) continue;
                $buckets[$bucket] = true;
                $point = ['time' => $time, 'value' => $value];
                if ($key === 'month') $point['month'] = $month;
                $points[] = $point;
                if (count($points) >= 10000) throw new RuntimeException('Zu viele Archivwerte. Bitte einen kleineren Zeitraum auswählen.');
            }
        }
        usort($points, function ($a, $b) { return $a['time'] <=> $b['time']; });
        foreach ($points as $i => &$point) {
            if ($key === 'month' && $i > 0 && $point['month'] !== $points[$i - 1]['month']) $point['breakBefore'] = true;
        }
        unset($point);
        return ['key' => $key, 'label' => self::ENERGY[$key][2], 'unit' => self::ENERGY[$key][3], 'variableID' => $entry['variableID'],
            'start' => $start, 'end' => $end, 'resolution' => $derived ? 'monthly' : ($hourly ? ($key === 'power' ? 'hourly' : 'counterHourly') : 'raw'), 'points' => $points];
    }

    public function Create()
    {
        parent::Create();
        foreach (self::SOURCES as $source) {
            $this->RegisterPropertyInteger($source[0], $source[1]);
        }
        foreach (self::ENERGY as $key => $spec) {
            $this->RegisterPropertyInteger($spec[0], ['power' => 48059, 'total' => 58570][$key] ?? 0);
            $this->RegisterPropertyString($spec[1], ['power' => 'W', 'total' => 'kWh'][$key] ?? 'auto');
        }
        $this->RegisterPropertyString('EnergyTimeZone', 'Europe/Vienna');
        $this->RegisterPropertyInteger('ArchiveID', 0);
        $this->RegisterTimer('Refresh', 0, 'V222_Refresh($_IPS["TARGET"]);');
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        // Only remove subscriptions owned by this module; retain the kernel subscription.
        foreach ($this->GetMessageList() as $sender => $messages) {
            foreach ($messages as $message) {
                if (in_array($message, $this->sourceMessages(), true)) {
                    $this->UnregisterMessage((int) $sender, $message);
                }
            }
        }
        foreach ($this->GetReferenceList() as $id) {
            $this->UnregisterReference($id);
        }
        foreach (array_unique($this->sourceIDs()) as $id) {
            if ($id > 0 && IPS_VariableExists($id)) {
                $this->RegisterReference($id);
                foreach ($this->sourceMessages() as $message) {
                    $this->RegisterMessage($id, $message);
                }
            }
        }
        $archive = $this->ReadPropertyInteger('ArchiveID');
        if ($archive > 0 && IPS_InstanceExists($archive)) { $this->RegisterReference($archive); }
        $this->SetTimerInterval('Refresh', 30000);
        if (IPS_GetKernelRunlevel() === KR_READY) {
            $this->Refresh();
        }
    }

    public function MessageSink($timeStamp, $senderID, $messageID, $data)
    {
        parent::MessageSink($timeStamp, $senderID, $messageID, $data);
        if ($messageID === IPS_KERNELSTARTED ||
            (in_array($messageID, $this->sourceMessages(), true) && in_array((int) $senderID, $this->sourceIDs(), true))) {
            $this->Refresh();
        }
    }

    public function GetConfigurationForm()
    {
        $elements = [['type' => 'Label', 'caption' => 'Vorhandene Wärmepumpenvariablen auswählen. Reine Anzeige mit Messwertverläufen, keine Schaltaktionen.']];
        foreach (self::SOURCES as $source) {
            $elements[] = ['type' => 'SelectVariable', 'name' => $source[0], 'caption' => $source[2]];
        }
        $elements[] = ['type' => 'Label', 'caption' => 'Elektrischer Verbrauch: optionale Quellvariablen auswählen. Leistung in W/kW, kumulierter Gesamtverbrauch in Wh/kWh. Ohne Monatsvariable wird aus dem archivierten Gesamtzähler berechnet.'];
        foreach (self::ENERGY as $key => $spec) {
            $elements[] = ['type' => 'SelectVariable', 'name' => $spec[0], 'caption' => $spec[2] . ($key === 'month' ? ' (optional, 0 = berechnet)' : '')];
            $options = [['caption' => 'Aus Quellprofil erkennen', 'value' => 'auto']];
            foreach ($key === 'power' ? ['W', 'kW'] : ['Wh', 'kWh'] as $unit) $options[] = ['caption' => $unit, 'value' => $unit];
            $elements[] = ['type' => 'Select', 'name' => $spec[1], 'caption' => 'Eingabeeinheit ' . $spec[2], 'options' => $options];
        }
        $elements[] = ['type' => 'ValidationTextBox', 'name' => 'EnergyTimeZone', 'caption' => 'Zeitzone der Kalendermonate (z. B. Europe/Vienna)'];
        $elements[] = ['type' => 'Label', 'caption' => 'Temperaturen in °C. Für Verläufe muss die Archivierung der vier Isttemperaturen im Archive Control aktiv sein. 0 = passendes Archiv automatisch suchen.'];
        $elements[] = ['type' => 'SelectInstance', 'name' => 'ArchiveID', 'caption' => 'Archive Control (0 = automatisch)', 'moduleID' => self::ARCHIVE];
        return $this->encode(['elements' => $elements, 'actions' => [['type' => 'Button', 'caption' => 'Quellen-Diagnose anzeigen', 'onClick' => 'echo V222_GetDiagnostics($id);']], 'status' => [
            ['code' => 102, 'icon' => 'active', 'caption' => 'Konfigurierte Quellvariablen verfügbar'],
            ['code' => 104, 'icon' => 'inactive', 'caption' => 'Quelle, Einheit oder Monatsberechnung unvollständig']
        ]]);
    }

    public function Refresh()
    {
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }
        $snapshot = $this->snapshot();
        $this->SetStatus(count($snapshot['missing']) === 0 ? 102 : 104);
        $json = $this->encode($snapshot);
        // Polling also catches edits inside profiles without publishing unchanged data.
        if ($json !== $this->GetBuffer('LastSnapshot')) {
            $this->SetBuffer('LastSnapshot', $json);
            $this->UpdateVisualizationValue($json);
        }
    }

    public function GetVisualizationTile()
    {
        return str_replace('/*__INITIAL_DATA__*/null', $this->encode($this->snapshot()), file_get_contents(__DIR__ . '/tile.html'));
    }

    public function RequestAction($ident, $value)
    {
        if ($ident === 'RequestHistory') {
            $response = ['kind' => 'history', 'requestID' => '', 'error' => '', 'points' => []];
            try {
                if (!is_string($value) || strlen($value) > 1024) { throw new InvalidArgumentException('Ungültige Verlaufsanfrage.'); }
                $request = json_decode($value, true, 8, JSON_THROW_ON_ERROR);
                if (!is_array($request) || !isset($request['requestID']) || !is_string($request['requestID']) ||
                    !preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $request['requestID'])) {
                    throw new InvalidArgumentException('Ungültige Anfragekennung.');
                }
                $response['requestID'] = $request['requestID'];
                if (!isset($request['key'], $request['start'], $request['end']) || !is_string($request['key']) ||
                    !is_int($request['start']) || !is_int($request['end'])) {
                    throw new InvalidArgumentException('Ungültige Messgröße oder ungültiger Zeitbereich.');
                }
                $response = array_merge($response, json_decode($this->GetHistory($request['key'], $request['start'], $request['end']), true, 512, JSON_THROW_ON_ERROR));
            } catch (Throwable $e) {
                $response['error'] = $e->getMessage();
            }
            // SDK requestAction is void. Correlated replies also support multiple open tiles.
            $this->UpdateVisualizationValue($this->encode($response));
            return;
        }
        throw new InvalidArgumentException('Die Kachel ist schreibgeschützt. Nur Verlaufsanfragen sind erlaubt.');
    }

    public function GetDiagnostics(): string
    {
        return $this->encode(['sources' => array_combine(array_merge(array_keys(self::SOURCES), array_keys(self::ENERGY)), $this->sourceIDs()), 'archiveID' => $this->ReadPropertyInteger('ArchiveID'), 'readOnly' => true]);
    }

    private function sourceMessages(): array
    {
        return [VM_UPDATE, VM_DELETE, VM_CHANGEPROFILENAME, VM_CHANGEPROFILEACTION];
    }

    private function sourceIDs(): array
    {
        return array_merge(array_map(function ($source) { return $this->ReadPropertyInteger($source[0]); }, array_values(self::SOURCES)), array_map(function ($source) { return $this->ReadPropertyInteger($source[0]); }, array_values(self::ENERGY)));
    }

    private function presentation(int $id): array
    {
        return function_exists('IPS_GetVariablePresentation') ? IPS_GetVariablePresentation($id) : [];
    }

    private function profile(array $variable, int $id): ?array
    {
        $presentation = $this->presentation($id);
        if ($presentation !== []) {
            if (($presentation['PRESENTATION'] ?? '') !== self::LEGACY) {
                return null; // A modern presentation takes precedence over obsolete legacy fields.
            }
            $name = $presentation['PROFILE'] ?? '';
        } else {
            $name = ($variable['VariableCustomProfile'] ?? '') !== '' ? $variable['VariableCustomProfile'] : ($variable['VariableProfile'] ?? '');
        }
        return $name !== '' && IPS_VariableProfileExists($name) ? IPS_GetVariableProfile($name) : null;
    }

    public function GetHistory(string $Key, int $Start, int $End): string
    {
        if (IPS_GetKernelRunlevel() !== KR_READY) { throw new RuntimeException('IP-Symcon ist noch nicht bereit.'); }
        if (!in_array($Key, array_merge(self::TEMPERATURES, array_keys(self::ENERGY), ['compressor', 'valve']), true)) { throw new InvalidArgumentException('Keine freigegebene Messgröße.'); }
        if ($Start < 0 || $End <= $Start || $End - $Start > 2678400 || $End > time() + 60) {
            throw new InvalidArgumentException('Ungültiger Zeitraum (maximal 31 Tage, keine Zukunft).');
        }
        if (in_array($Key, ['compressor', 'valve'], true)) {
            $id = $this->ReadPropertyInteger(self::SOURCES[$Key][0]);
            if ($id <= 0 || !IPS_VariableExists($id) || IPS_GetVariable($id)['VariableType'] !== 0) {
                throw new RuntimeException('Boolesche Statusvariable fehlt.');
            }
            $archive = $this->archiveFor($id);
            if (AC_GetAggregationType($archive, $id) !== 0) throw new RuntimeException('Status muss als Standardwert archiviert werden.');
            $rows = AC_GetLoggedValues($archive, $id, $Start, $End, 10000);
            $before = AC_GetLoggedValues($archive, $id, 0, $Start, 1);
            if (!is_array($rows) || !is_array($before)) throw new RuntimeException('Archivdaten konnten nicht gelesen werden.');
            if (count($rows) >= 10000) throw new RuntimeException('Zu viele Statuswechsel. Bitte kleineren Zeitraum auswählen.');
            $points = [];
            foreach (array_merge($before, $rows) as $row) {
                $stamp = $row['TimeStamp'] ?? null;
                $value = $row['Value'] ?? null;
                if (!is_int($stamp) || $stamp > $End || !in_array($value, [false, true, 0, 1, 0.0, 1.0], true)) continue;
                $points[max($Start, $stamp)] = ['time' => max($Start, $stamp), 'value' => (int) $value];
            }
            ksort($points);
            return $this->encode(['key' => $Key, 'label' => self::SOURCES[$Key][2], 'variableID' => $id,
                'unit' => '', 'start' => $Start, 'end' => $End, 'resolution' => 'status', 'points' => array_values($points)]);
        }
        if (isset(self::ENERGY[$Key])) return $this->encode($this->energyHistory($Key, $Start, $End));
        $id = $this->ReadPropertyInteger(self::SOURCES[$Key][0]);
        if ($id <= 0 || !IPS_VariableExists($id) || !in_array(IPS_GetVariable($id)['VariableType'], [1, 2], true)) {
            throw new RuntimeException('Temperaturvariable fehlt oder ist nicht numerisch.');
        }
        $archive = $this->archiveFor($id);
        if (AC_GetAggregationType($archive, $id) !== 0) { throw new RuntimeException('Temperaturarchiv muss als Standardwert, nicht als Zähler archiviert werden.'); }
        $hourly = $End - $Start > 172800;
        $rows = $hourly ? AC_GetAggregatedValues($archive, $id, 0, $Start, $End, 10000) :
            AC_GetLoggedValues($archive, $id, $Start, $End, 10000);
        if (!is_array($rows)) { throw new RuntimeException('Archivdaten konnten nicht gelesen werden.'); }
        if (count($rows) >= 10000) { throw new RuntimeException('Zu viele Archivwerte. Bitte einen kleineren Zeitraum auswählen.'); }
        $points = [];
        foreach ($rows as $row) {
            $value = $row[$hourly ? 'Avg' : 'Value'] ?? null;
            $stamp = $row['TimeStamp'] ?? null;
            if (!is_int($stamp) || $stamp < $Start || $stamp > $End ||
                (!is_int($value) && !is_float($value)) || !is_finite((float) $value)) { continue; }
            $points[] = ['time' => $stamp, 'value' => (float) $value];
        }
        usort($points, function ($a, $b) { return $a['time'] <=> $b['time']; });
        return $this->encode(['key' => $Key, 'label' => self::SOURCES[$Key][2], 'variableID' => $id,
            'unit' => '°C', 'start' => $Start, 'end' => $End, 'resolution' => $hourly ? 'hourly' : 'raw', 'points' => $points]);
    }

    private function snapshot(): array
    {
        $data = ['values' => [], 'missing' => []];
        foreach (self::SOURCES as $key => $source) {
            $id = $this->ReadPropertyInteger($source[0]);
            $entry = ['value' => null, 'formatted' => '—', 'updated' => 0, 'hasUnit' => false];
            try {
                if ($id > 0 && IPS_VariableExists($id)) {
                    $variable = IPS_GetVariable($id);
                    $value = GetValue($id);
                    if (($key === 'mode' && (is_bool($value) || is_string($value))) ||
                        (in_array($key, ['heatingPump', 'primaryPump', 'valve', 'compressor'], true) && is_bool($value)) ||
                        (!in_array($key, ['heatingPump', 'primaryPump', 'valve', 'compressor'], true) && in_array($variable['VariableType'], [1, 2], true) && (is_int($value) || is_float($value)) && is_finite((float) $value))) {
                        $profile = $this->profile($variable, $id);
                        $presentation = $this->presentation($id);
                        $min = $profile !== null ? $profile['MinValue'] : ($presentation['MIN'] ?? null);
                        $max = $profile !== null ? $profile['MaxValue'] : ($presentation['MAX'] ?? null);
                        $entry = ['value' => $value, 'formatted' => GetValueFormatted($id),
                            'updated' => (int) $variable['VariableUpdated'],
                            'hasUnit' => $profile !== null ? (trim($profile['Prefix']) !== '' || trim($profile['Suffix']) !== '') :
                                (trim($presentation['PREFIX'] ?? '') !== '' || trim($presentation['SUFFIX'] ?? '') !== ''),
                            'range' => is_numeric($min) && is_numeric($max) && $max > $min ? ['min' => $min, 'max' => $max] : null];
                    }
                }
            } catch (Throwable $e) {
                // Deletion/reconfiguration between Exists and Get must not break the whole tile.
                $this->SendDebug('Quelle nicht verfügbar', $source[2] . ': ' . $e->getMessage(), 0);
            }
            $data['values'][$key] = $entry;
            if ($entry['value'] === null) {
                $data['missing'][] = $source[2];
            }
        }
        $data['energy'] = ['power' => $this->energySource('power'), 'total' => $this->energySource('total')];
        $data['energy']['month'] = $this->monthlyEnergy($data['energy']['total']);
        foreach (self::ENERGY as $key => $spec) {
            if (($this->ReadPropertyInteger($spec[0]) > 0 || ($key === 'month' && $this->ReadPropertyInteger('TotalEnergyID') > 0)) && $data['energy'][$key]['value'] === null) $data['missing'][] = $spec[2];
        }
        $data['sourceCount'] = count(array_unique(array_filter($this->sourceIDs(), function ($id) { return $id > 0; })));
        return $data;
    }

    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }
}
