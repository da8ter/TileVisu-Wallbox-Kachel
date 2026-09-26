<?php

declare(strict_types=1);

// Isolierte SDK-Attrappe: verbindet sich nie mit einer laufenden Symcon-Installation.
const KR_READY = 10103, IPS_KERNELSTARTED = 10001, VM_UPDATE = 10603, MEDIATYPE_IMAGE = 1, KL_ERROR = 10205;

// Instanzzustand und die SDK-Methoden, die das Modul aufruft. Die Typen folgen IPSModuleStrict: ein falscher
// Typ fällt unter strict_types auch hier als TypeError auf (etwa 1 statt true).
class ModuleDouble
{
    public array $properties = [], $propertyTypes = [], $attributes = [], $buffers = [], $messages = [],
        $references = [], $updates = [], $hooks = [], $formFields = [];
    public int $InstanceID = 12345;
    public int $visualizationType = 0;

    protected function RegisterPropertyInteger(string $Name, int $Value): void { $this->RegisterProperty($Name, 'integer', $Value); }
    protected function RegisterPropertyFloat(string $Name, float $Value): void { $this->RegisterProperty($Name, 'float', $Value); }
    protected function RegisterPropertyString(string $Name, string $Value): void { $this->RegisterProperty($Name, 'string', $Value); }
    protected function RegisterPropertyBoolean(string $Name, bool $Value): void { $this->RegisterProperty($Name, 'boolean', $Value); }
    protected function ReadPropertyInteger(string $Name): int { return $this->Property($Name, 'integer'); }
    protected function ReadPropertyFloat(string $Name): float { return $this->Property($Name, 'float'); }
    protected function ReadPropertyString(string $Name): string { return $this->Property($Name, 'string'); }
    protected function ReadPropertyBoolean(string $Name): bool { return $this->Property($Name, 'boolean'); }
    protected function RegisterAttributeString(string $Name, string $Value): void { $this->attributes[$Name] = $Value; }
    protected function ReadAttributeString(string $Name): string
    {
        return $this->attributes[$Name] ?? throw new RuntimeException('Attribute not registered: ' . $Name);
    }
    protected function WriteAttributeString(string $Name, string $Value): void
    {
        if (!array_key_exists($Name, $this->attributes)) {
            throw new RuntimeException('Attribute not registered: ' . $Name);
        }
        $this->attributes[$Name] = $Value;
    }
    protected function GetBuffer(string $Name): string { return $this->buffers[$Name] ?? ''; }
    protected function SetBuffer(string $Name, string $Data): void { $this->buffers[$Name] = $Data; }
    // Nativer Hook: ohne $hookAvailable gilt er als nicht registriert (etwa vor dem Neuladen des Moduls).
    protected function RegisterHook(string $HookPath): bool { $this->hooks[] = $HookPath; return $GLOBALS['hookAvailable']; }
    protected function SetVisualizationType(int $Type): void { $this->visualizationType = $Type; }
    protected function UpdateVisualizationValue(string $Value): void { $this->updates[] = $Value; }
    protected function UpdateFormField(string $Field, string $Parameter, mixed $Value): void { $this->formFields[] = [$Field, $Parameter, $Value]; }
    protected function GetReferenceList(): array { return array_keys($this->references); }
    protected function RegisterReference(int $ID): void { $this->references[$ID] = true; }
    protected function UnregisterReference(int $ID): void { unset($this->references[$ID]); }
    protected function GetMessageList(): array { return $this->messages; }
    protected function RegisterMessage(int $SenderID, int $Message): void
    {
        if (!in_array($Message, $this->messages[$SenderID] ?? [], true)) {
            $this->messages[$SenderID][] = $Message;
        }
    }
    protected function UnregisterMessage(int $SenderID, int $Message): void
    {
        $this->messages[$SenderID] = array_values(array_diff($this->messages[$SenderID] ?? [], [$Message]));
        if ($this->messages[$SenderID] === []) {
            unset($this->messages[$SenderID]);
        }
    }
    protected function SendDebug(string $Message, string $Data, int $Format): void {}
    protected function LogMessage(string $Message, int $Type): void {}

    private function RegisterProperty(string $Name, string $Type, mixed $Value): void
    {
        $this->properties[$Name] = $Value;
        $this->propertyTypes[$Name] = $Type;
    }

    // Unbekannte Eigenschaft oder falscher Typ ist ein Fehler, keine stille Umwandlung.
    private function Property(string $Name, string $Type): mixed
    {
        if (!isset($this->propertyTypes[$Name])) {
            throw new RuntimeException('Property not registered: ' . $Name);
        }
        if ($this->propertyTypes[$Name] !== $Type) {
            throw new RuntimeException('Property ' . $Name . ' is ' . $this->propertyTypes[$Name] . ', read as ' . $Type);
        }
        return $this->properties[$Name];
    }
}

// Die Methoden, die ein Modul überschreibt, mit den Signaturen von IPSModuleStrict: eine abweichende Signatur
// im Modul scheitert schon beim Laden, wie in Symcon.
class IPSModuleStrict extends ModuleDouble
{
    public function Create(): void {}
    public function ApplyChanges(): void {}
    public function Destroy(): void {}
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void {}
    public function RequestAction(string $Ident, mixed $Value): void {}
    public function GetVisualizationTile(): string { return ''; }
    protected function ProcessHookData(): void {}
}

// Nur für die Gegenprobe gegen den Stand 24f3ce5, der noch auf IPSModule mit untypisierten Signaturen aufsetzt.
class IPSModule extends ModuleDouble
{
    public function Create() {}
    public function ApplyChanges() {}
    public function Destroy() {}
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {}
    public function RequestAction($Ident, $Value) {}
    public function GetVisualizationTile() { return ''; }
    protected function ProcessHookData() {}
}

$runlevel = KR_READY;
$hookAvailable = false;
$options = $variables = $profiles = $media = $actions = [];

// Neue, leere Welt je Prüfabschnitt.
function world(): void
{
    $GLOBALS['options'] = $GLOBALS['variables'] = $GLOBALS['profiles'] = $GLOBALS['media'] = $GLOBALS['actions'] = [];
    $GLOBALS['runlevel'] = KR_READY;
    $GLOBALS['hookAvailable'] = false;
}

function IPS_GetKernelRunlevel(): int { return $GLOBALS['runlevel']; }
function IPS_GetOption(string $Option): mixed { return $GLOBALS['options'][$Option] ?? 1048576; }
function IPS_VariableExists(int $VariableID): bool { return isset($GLOBALS['variables'][$VariableID]); }
function IPS_GetVariable(int $VariableID): array
{
    return $GLOBALS['variables'][$VariableID] ?? throw new RuntimeException('Variable #' . $VariableID . ' does not exist');
}
function GetValue(int $VariableID): mixed { return IPS_GetVariable($VariableID)['value']; }
function GetValueFormatted(int $VariableID): string { return IPS_GetVariable($VariableID)['formatted']; }
function IPS_VariableProfileExists(string $ProfileName): bool { return isset($GLOBALS['profiles'][$ProfileName]); }
function IPS_GetVariableProfile(string $ProfileName): array
{
    return $GLOBALS['profiles'][$ProfileName] ?? throw new RuntimeException('Profile ' . $ProfileName . ' does not exist');
}
function IPS_MediaExists(int $MediaID): bool { return isset($GLOBALS['media'][$MediaID]); }
function IPS_GetMedia(int $MediaID): array
{
    return $GLOBALS['media'][$MediaID] ?? throw new RuntimeException('Media #' . $MediaID . ' does not exist');
}
function IPS_GetMediaContent(int $MediaID): string { return IPS_GetMedia($MediaID)['content']; }
function RequestAction(int $VariableID, mixed $Value): bool
{
    IPS_GetVariable($VariableID);
    $GLOBALS['actions'][] = [$VariableID, $Value];
    return true;
}

// Variable, wie das Modul sie über IPS_GetVariable, GetValue und GetValueFormatted sieht.
function variable(int $id, mixed $value, string $formatted, string $profile = ''): void
{
    $GLOBALS['variables'][$id] = [
        'VariableType' => match (true) { is_bool($value) => 0, is_int($value) => 1, is_float($value) => 2, default => 3 },
        'VariableProfile' => $profile, 'VariableCustomProfile' => '',
        'value' => $value, 'formatted' => $formatted,
    ];
}
// Neuer Wert einer Variable, wie ihn VM_UPDATE meldet (nicht SetValue: PHP-Funktionsnamen kennen keine Groß-/Kleinschreibung).
function changeValue(int $id, mixed $value, string $formatted): void
{
    $GLOBALS['variables'][$id]['value'] = $value;
    $GLOBALS['variables'][$id]['formatted'] = $formatted;
}
function profile(string $name, array $associations): void
{
    $GLOBALS['profiles'][$name] = ['Associations' => $associations];
}
// Medienobjekt; IPS_GetMediaContent liefert den Inhalt wie Symcon base64-kodiert.
function image(int $id, string $file, string $bytes, int $type = MEDIATYPE_IMAGE): void
{
    $GLOBALS['media'][$id] = ['MediaType' => $type, 'MediaFile' => $file, 'content' => base64_encode($bytes)];
}

function check(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    echo 'PASS: ' . $label . PHP_EOL;
}

function query(string $url): array
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    return $query;
}

// WALLBOX_MODULE wählt eine andere Fassung der Kachel (Gegenprobe gegen den Stand 24f3ce5).
require getenv('WALLBOX_MODULE') ?: __DIR__ . '/../Wallbox/module.php';

// Wie Symcons HookInstance: macht ProcessHookData öffentlich (die Signatur muss dazu passen) und fängt
// Kopfzeilen und Status ab, damit der Hook ohne Webserver läuft.
class ProbeTile extends TileVisuWallboxKachel
{
    public array $sent = [];
    public int $status = 200;

    public function ProcessHookData(): void
    {
        parent::ProcessHookData();
    }

    public function hook(array $get, array $server = []): string
    {
        $_GET = $get;
        $_SERVER['HTTP_IF_NONE_MATCH'] = $server['HTTP_IF_NONE_MATCH'] ?? '';
        $this->sent = [];
        $this->status = 200;
        ob_start();
        try {
            $this->ProcessHookData();
        } finally {
            $body = (string) ob_get_clean();
        }
        return $body;
    }

    public function header(string $name): ?string
    {
        foreach ($this->sent as $header) {
            if (stripos($header, $name . ': ') === 0) {
                return substr($header, strlen($name) + 2);
            }
        }
        return null;
    }

    protected function SendHeader(string $header): void
    {
        $this->sent[] = $header;
    }

    protected function SendStatus(int $code): void
    {
        $this->status = $code;
    }
}

function tile(int $instanceID = 12345): ProbeTile
{
    $m = new ProbeTile();
    $m->InstanceID = $instanceID;
    $m->Create();
    return $m;
}

// Kacheldokument in seine Teile zerlegen: module.html der geladenen Fassung, Zuordnungen, window.assets und der
// Startzustand (handleMessage mit dem JSON-Text als JS-Stringliteral). Ein anderer Aufbau wirft.
function parts(TileVisuWallboxKachel $m): array
{
    $html = $m->GetVisualizationTile();
    $base = (string) file_get_contents(dirname((new ReflectionClass(TileVisuWallboxKachel::class))->getFileName()) . '/module.html');
    if (!str_starts_with($html, $base)) {
        throw new RuntimeException('Tile does not start with module.html');
    }
    $pattern = '~\A<script type="text/javascript">var statusImages = (?<statusImages>.*?);var statusColor = (?<statusColor>.*?);'
        . 'var statusAnimation = (?<statusAnimation>.*?);var phasecount = (?<phasecount>.*?);var wallboxstatus = (?<wallboxstatus>.*?);</script>'
        . '<script>window\.assets = \{\};\Rwindow\.assets\.img_goe_aus = (?<aus>"[^"\\\\]*");\Rwindow\.assets\.img_goe_an = (?<an>"[^"\\\\]*");\R</script>'
        . '<script>handleMessage\((?<state>"(?:[^"\\\\]++|\\\\.)*+")\)</script>\z~s';
    $tail = substr($html, strlen($base));
    // Possessiv: der Startzustand ist mit Base64-Bildern rund 100 kB lang, sonst reicht der JIT-Stapel nicht
    if (preg_match($pattern, $tail, $part) !== 1) {
        throw new RuntimeException('Unexpected tile layout (' . preg_last_error_msg() . ')');
    }
    $json = static fn (string $text): mixed => json_decode($text, true, 512, JSON_THROW_ON_ERROR);
    return [
        'html' => $html, 'tail' => $tail, 'raw' => $part,
        'statusImages' => $json($part['statusImages']), 'statusColor' => $json($part['statusColor']),
        'statusAnimation' => $json($part['statusAnimation']),
        'assets' => ['aus' => $json($part['aus']), 'an' => $json($part['an'])],
        'state' => $json($json($part['state'])),
    ];
}
function latest(ModuleDouble $m): array { return json_decode(end($m->updates), true, 512, JSON_THROW_ON_ERROR); }
