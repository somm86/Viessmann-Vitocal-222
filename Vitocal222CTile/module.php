<?php

class Vitocal222CTile extends IPSModule
{
    private const ENUMERATION = '{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}';
    private const LEGACY = '{4153A8D4-5C33-C65F-C1F3-7B61AAF99B1C}';

    private const SOURCES = [
        'mode' => ['ModeID', 53049, 'Betriebsart Heizung'],
        'heatingPump' => ['HeatingPumpID', 20926, 'Heizkreispumpe'],
        'primaryPump' => ['PrimaryPumpID', 39794, 'Primärpumpe'],
        'roomTarget' => ['RoomTargetID', 10939, 'Raum-Solltemperatur'],
        'return' => ['ReturnID', 21875, 'Rücklauf Sekundär'],
        'valve' => ['ValveID', 53786, 'Umschaltventil Heizung / Warmwasser'],
        'compressor' => ['CompressorID', 31722, 'Verdichter'],
        'primaryFlow' => ['PrimaryFlowID', 18104, 'Vorlauf Primärquelle'],
        'flow' => ['FlowID', 18969, 'Vorlauf Sekundär'],
        'water' => ['WaterID', 56634, 'Warmwasser Ist'],
        'waterTarget' => ['WaterTargetID', 27660, 'Warmwasser-Solltemperatur']
    ];

    public function Create()
    {
        parent::Create();
        foreach (self::SOURCES as $source) {
            $this->RegisterPropertyInteger($source[0], $source[1]);
        }
        $this->RegisterTimer('Refresh', 0, 'V222_Refresh($_IPS["TARGET"]);');
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetBuffer('ActionError', '');
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
        foreach (self::SOURCES as $source) {
            $id = $this->ReadPropertyInteger($source[0]);
            if ($id > 0 && IPS_VariableExists($id)) {
                $this->RegisterReference($id);
                foreach ($this->sourceMessages() as $message) {
                    $this->RegisterMessage($id, $message);
                }
            }
        }
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
        $elements = [['type' => 'Label', 'caption' => 'Vorhandene Wärmepumpenvariablen auswählen. Steuerung über vorhandene Variablenaktionen; keine direkte Geräteverbindung.']];
        foreach (self::SOURCES as $source) {
            $elements[] = ['type' => 'SelectVariable', 'name' => $source[0], 'caption' => $source[2]];
        }
        $elements[] = ['type' => 'Label', 'caption' => 'Temperaturen müssen in °C vorliegen. Sollwerte sind nur mit gültigem Profilbereich und vorhandener Aktion bedienbar.'];
        return $this->encode(['elements' => $elements, 'actions' => [['type' => 'Button', 'caption' => 'Betriebsart-Diagnose anzeigen', 'onClick' => 'echo V222_GetDiagnostics($id);']], 'status' => [
            ['code' => 102, 'icon' => 'active', 'caption' => 'Alle elf Quellvariablen verfügbar'],
            ['code' => 104, 'icon' => 'inactive', 'caption' => 'Quellvariable fehlt oder hat einen ungeeigneten Datentyp']
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
        $this->SetBuffer('ActionError', '');
        try {
            if (in_array($ident, ['SetRoomTarget', 'SetWaterTarget'], true)) {
                $key = $ident === 'SetRoomTarget' ? 'roomTarget' : 'waterTarget';
                $config = $this->temperatureConfiguration($key);
                if (!$config['writable']) {
                    throw new RuntimeException($config['reason']);
                }
                if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) ||
                    $value < $config['min'] || $value > $config['max']) {
                    throw new InvalidArgumentException('Solltemperatur außerhalb des gültigen Profilbereichs.');
                }
                $steps = ($value - $config['min']) / $config['step'];
                if (abs($steps - round($steps)) > 0.000001 || ($config['type'] === 1 && floor($value) != $value)) {
                    throw new InvalidArgumentException('Solltemperatur passt nicht zur Schrittweite des Quellprofils.');
                }
                RequestAction($config['variableID'], $config['type'] === 1 ? (int) $value : (float) $value);
                $this->Refresh();
                return;
            }
            if ($ident !== 'ChangeMode') {
                throw new InvalidArgumentException('Unbekannte Aktion.');
            }
            if (IPS_GetKernelRunlevel() !== KR_READY) {
                throw new RuntimeException('IP-Symcon ist noch nicht bereit.');
            }
            // Re-read the effective presentation/profile and action on EVERY request.
            $mode = $this->modeConfiguration();
            if (!$mode['writable']) {
                throw new RuntimeException($mode['reason']);
            }
            $candidate = $value;
            if ($mode['type'] === 1 && is_float($value)) {
                $candidate = is_finite($value) && floor($value) === $value && abs($value) <= 9007199254740991 ? (int) $value : null;
            } elseif ($mode['type'] === 2 && (is_float($value) || is_int($value))) {
                $candidate = (float) $value;
            }
            foreach ($mode['choices'] as $choice) {
                if ($candidate === $choice['value']) {
                    // Never SetValue: use the source's effective action and original value type.
                    RequestAction($this->ReadPropertyInteger('ModeID'), $choice['value']);
                    $this->Refresh();
                    return;
                }
            }
            throw new InvalidArgumentException('Dieser Wert ist in der aktuellen Betriebsartauswahl nicht freigegeben.');
        } catch (Throwable $e) {
            // requestAction() is documented as void: PHP errors need a separate SDK push.
            $this->SetBuffer('ActionError', $e->getMessage());
            $this->SendDebug('Betriebsartaktion fehlgeschlagen', $e->getMessage(), 0);
            $this->Refresh();
            throw $e;
        }
    }

    public function GetDiagnostics(): string
    {
        return $this->encode(['mode' => $this->modeConfiguration(), 'roomTarget' => $this->temperatureConfiguration('roomTarget'), 'waterTarget' => $this->temperatureConfiguration('waterTarget')]);
    }

    private function sourceMessages(): array
    {
        return [VM_UPDATE, VM_DELETE, VM_CHANGEPROFILENAME, VM_CHANGEPROFILEACTION];
    }

    private function sourceIDs(): array
    {
        return array_map(function ($source) { return $this->ReadPropertyInteger($source[0]); }, array_values(self::SOURCES));
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

    private function modeConfiguration(): array
    {
        $id = $this->ReadPropertyInteger('ModeID');
        $result = ['choices' => [], 'writable' => false, 'reason' => 'Betriebsartvariable fehlt.',
            'type' => null, 'variableID' => $id, 'source' => '', 'actionID' => 0];
        if ($id <= 0 || !IPS_VariableExists($id)) {
            return $result;
        }
        $variable = IPS_GetVariable($id);
        $result['type'] = $variable['VariableType'];
        $presentation = $this->presentation($id);
        $rawChoices = [];
        if (($presentation['PRESENTATION'] ?? '') === self::ENUMERATION) {
            $result['source'] = 'Aufzählung';
            $options = json_decode($presentation['OPTIONS'] ?? '[]', true);
            if (is_array($options)) {
                foreach ($options as $option) {
                    if (is_array($option) && array_key_exists('Value', $option) && isset($option['Caption'])) {
                        $rawChoices[] = ['value' => $option['Value'], 'label' => (string) $option['Caption']];
                    }
                }
            }
        } else {
            $profile = $this->profile($variable, $id);
            if ($profile !== null && $profile['ProfileType'] === $result['type']) {
                $result['source'] = 'Profil: ' . $profile['ProfileName'];
                foreach ($profile['Associations'] as $association) {
                    $value = $association['Value'];
                    // Legacy boolean associations use numeric 0/1, not JSON booleans.
                    if ($result['type'] === 0 && in_array($value, [0, 1, 0.0, 1.0], true)) {
                        $value = (bool) $value;
                    }
                    $rawChoices[] = ['value' => $value, 'label' => (string) $association['Name']];
                }
            }
        }
        foreach ($rawChoices as $choice) {
            $value = $choice['value'];
            if ($result['type'] === 0 && is_bool($value)) {
                $result['choices'][] = $choice;
            } elseif ($result['type'] === 3 && is_string($value)) {
                $result['choices'][] = $choice;
            } elseif (in_array($result['type'], [1, 2], true) && (is_int($value) || is_float($value)) && is_finite((float) $value)) {
                if ($result['type'] === 1 && (float) (int) $value !== (float) $value) {
                    continue;
                }
                $choice['value'] = $result['type'] === 1 ? (int) $value : (float) $value;
                $result['choices'][] = $choice;
            }
        }
        $action = ($variable['VariableCustomAction'] ?? 0) > 0 ? $variable['VariableCustomAction'] : ($variable['VariableAction'] ?? 0);
        $result['actionID'] = $action;
        if ($action < 10000 || (!IPS_InstanceExists($action) && !IPS_ScriptExists($action))) {
            $result['reason'] = 'Keine gültige Variablenaktion vorhanden oder Aktion deaktiviert.';
        } elseif (count($result['choices']) === 0) {
            $result['reason'] = 'Variable ' . $id . ': Keine Auswahlwerte im wirksamen Profil oder in der Darstellung „Aufzählung“. Bitte die Auswahl der Quellvariable in IP-Symcon prüfen.';
        } else {
            $result['writable'] = true;
            $result['reason'] = '';
        }
        return $result;
    }

    private function temperatureConfiguration(string $key): array
    {
        $id = $this->ReadPropertyInteger(self::SOURCES[$key][0]);
        $result = ['variableID' => $id, 'writable' => false, 'reason' => 'Sollwertvariable fehlt.',
            'type' => null, 'min' => null, 'max' => null, 'step' => null, 'actionID' => 0];
        if ($id <= 0 || !IPS_VariableExists($id)) { return $result; }
        $v = IPS_GetVariable($id);
        $result['type'] = $v['VariableType'];
        $action = ($v['VariableCustomAction'] ?? 0) > 0 ? $v['VariableCustomAction'] : ($v['VariableAction'] ?? 0);
        $result['actionID'] = $action;
        $p = $this->profile($v, $id);
        $min = $p['MinValue'] ?? null;
        $max = $p['MaxValue'] ?? null;
        $step = $p['StepSize'] ?? null;
        if ($p !== null && ($p['ProfileType'] !== $v['VariableType'] || count($p['Associations']) > 0)) {
            $min = $max = $step = null;
        }
        if (!in_array($v['VariableType'], [1, 2], true)) {
            $result['reason'] = 'Sollwert muss eine numerische Variable sein.';
        } elseif ($action < 10000 || (!IPS_InstanceExists($action) && !IPS_ScriptExists($action))) {
            $result['reason'] = 'Keine gültige Variablenaktion vorhanden oder Aktion deaktiviert.';
        } elseif (!is_numeric($min) || !is_numeric($max) || !is_numeric($step) ||
            !is_finite((float) $min) || !is_finite((float) $max) || !is_finite((float) $step) || $max <= $min || $step <= 0) {
            $result['reason'] = 'Kein gültiger numerischer Profilbereich mit Schrittweite. Quellprofil prüfen.';
        } else {
            $result['min'] = $min; $result['max'] = $max; $result['step'] = $step;
            $result['writable'] = true; $result['reason'] = '';
        }
        return $result;
    }

    private function snapshot(): array
    {
        $data = ['values' => [], 'missing' => [], 'actionError' => $this->GetBuffer('ActionError')];
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
        try {
            $data['mode'] = $this->modeConfiguration();
        } catch (Throwable $e) {
            $data['mode'] = ['choices' => [], 'writable' => false, 'reason' => 'Betriebsartkonfiguration nicht verfügbar.', 'type' => null];
        }
        $data['controls'] = ['roomTarget' => $this->temperatureConfiguration('roomTarget'), 'waterTarget' => $this->temperatureConfiguration('waterTarget')];
        return $data;
    }

    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }
}
