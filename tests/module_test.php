<?php

declare(strict_types=1);

// Wallbox-Kachel ohne laufendes Symcon: Module Strict, Payload wie bisher (Gegenprobe gegen 24f3ce5).
require __DIR__ . '/bootstrap.php';
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

const ALTSTAND = '24f3ce5'; // Stand vor der Überarbeitung: IPSModule, Base64-Bilder, jede Aktualisierung, Abo auf 0

// Die 17 Variablen-Eigenschaften mit Beispielwerten: Eigenschaft => [Wert, formatierter Wert].
const WERTE = [
    'Status' => [2, 'Laden'], 'Ladeleistung' => [7.4, '7,4 kW'], 'SOC' => [45, '45 %'], 'ZielSOC' => [80, '80 %'],
    'SOCschalter' => [true, 'An'], 'ZielSOCschalter' => [true, 'An'], 'Verbrauchgesamt' => [1234.5, '1.234,5 kWh'],
    'VerbrauchTag' => [12.3, '12,3 kWh'], 'KostenTag' => [3.9, '3,90 €'], 'KostenGesamt' => [390.12, '390,12 €'],
    'Fehler' => [0, 'Kein Fehler'], 'Phasen' => [3, '3'], 'MaxLadeleistung' => [11.0, '11 kW'], 'Kabel' => [32, '32 A'],
    'Zugangskontrolle' => [false, 'Offen'], 'Verriegelung' => [1, 'Verriegelt'], 'Reichweite' => [250, '250 km'],
];

// Alle Variablen ab ID 101 zuordnen; liefert ihre IDs.
function alleVariablen(TileVisuWallboxKachel $m, array $abweichend = []): array
{
    profile('WB.Status', [['Value' => 0.0, 'Name' => 'Bereit', 'Color' => -1], ['Value' => 2.0, 'Name' => 'Laden', 'Color' => 0x00FF00]]);
    $id = 101;
    foreach (array_replace(WERTE, $abweichend) as $property => [$wert, $text]) {
        variable($id, $wert, $text, $property === 'Status' ? 'WB.Status' : '');
        $m->properties[$property] = $id++;
    }
    return range(101, $id - 1);
}

// Konfigurationen für den Vergleich mit dem Altstand: Aufbau der Welt und der Eigenschaften, liefert die
// Variablen, deren Aktualisierung (VM_UPDATE) mit verglichen wird.
function konfigurationen(): array
{
    $bilder = static fn (int $auswahl, array $medien = []): Closure => static function (ProbeTile $m) use ($auswahl, $medien): array {
        $m->properties['Bildauswahl'] = $auswahl;
        foreach ($medien as $eigenschaft => [$id, $datei, $typ]) {
            image($id, $datei, 'BILD-' . $datei, $typ);
            $m->properties[$eigenschaft] = $id;
        }
        return [];
    };
    $bild = static fn (string $datei, int $typ = MEDIATYPE_IMAGE): array => [0, $datei, $typ];
    $zuordnung = static fn (array $zeilen): Closure => static function (ProbeTile $m) use ($zeilen): array {
        $m->properties['ProfilAssoziazionen'] = json_encode($zeilen);
        return alleVariablen($m);
    };
    return [
        'leer' => static fn (ProbeTile $m): array => [],
        'alle Variablen' => static fn (ProbeTile $m): array => alleVariablen($m),
        'Go-e Gemini' => $bilder(1),
        'Universal' => $bilder(2),
        'eigene Bilder ohne Medien' => $bilder(3),
        'eigene Bilder jpg und png' => $bilder(3, ['Bild_An' => [501] + $bild('media/a.jpg'), 'Bild_Aus' => [502] + $bild('media/b.png')]),
        'eigene Bilder bmp und ico' => $bilder(3, ['Bild_An' => [501] + $bild('media/a.bmp'), 'Bild_Aus' => [502] + $bild('media/b.ico')]),
        'eigene Bilder gif und jpeg' => $bilder(3, ['Bild_An' => [501] + $bild('media/a.gif'), 'Bild_Aus' => [502] + $bild('media/b.jpeg')]),
        'eigene Bilder JPG und webp' => $bilder(3, ['Bild_An' => [501] + $bild('media/a.JPG'), 'Bild_Aus' => [502] + $bild('media/b.webp')]),
        'eigene Bilder ohne Bild' => $bilder(3, ['Bild_An' => [501] + $bild('ton.wav', 2), 'Bild_Aus' => [502] + $bild('ton.wav', 2)]),
        'Bildauswahl 7, ein eigenes Bild' => $bilder(7, ['Bild_An' => [501] + $bild('media/a.png')]),
        'Hintergrund jpg' => $bilder(0, ['bgImage' => [510] + $bild('media/bg.jpg')]),
        'Hintergrund WEBP' => $bilder(0, ['bgImage' => [510] + $bild('media/bg.WEBP')]),
        'Hintergrund svg' => $bilder(0, ['bgImage' => [510] + $bild('media/bg.svg')]),
        'Hintergrund kein Bild' => $bilder(0, ['bgImage' => [510] + $bild('ton.wav', 2)]),
        'ohne Standardhintergrund' => static function (ProbeTile $m): array {
            $m->properties['BG_Off'] = false;
            return [];
        },
        'ohne Standardhintergrund, eigenes Bild' => static function (ProbeTile $m): array {
            $m->properties['BG_Off'] = false;
            image(510, 'media/bg.png', 'BG');
            $m->properties['bgImage'] = 510;
            return [];
        },
        'Zuordnungen' => $zuordnung([
            ['AssoziationName' => 'Bereit', 'AssoziationValue' => 0, 'Animation' => 'standby_animation', 'StatusColor' => -1, 'Bildauswahl' => 'goe_aus'],
            ['AssoziationName' => 'Laden', 'AssoziationValue' => 2, 'Animation' => 'laden_animation', 'StatusColor' => 0x00FF00, 'Bildauswahl' => 'goe_an'],
        ]),
        'Zuordnung aus UpdateList' => $zuordnung([
            ['AssoziationName' => 'Laden', 'AssoziationValue' => 2, 'Bildauswahl' => 'goe_aus', 'StatusColor' => '-1'],
        ]),
        'Sonderzeichen' => static function (ProbeTile $m): array {
            $boese = "O'Neil \"x\" \\ Zeile1\nZeile2 </script><script>alert(1)</script> & <b> äöü";
            return alleVariablen($m, ['Kabel' => [$boese, $boese], 'Status' => [2, $boese]]);
        },
        'Farben und Größen' => static function (ProbeTile $m): array {
            $m->properties = array_replace($m->properties, ['Kachelhintergrundfarbe' => 0x123456, 'StatusSchriftgroesse' => 1.5,
                'BildBreite' => 0.0, 'Bildtransparenz' => 0.3, 'BalkenVerlaufFarbe1' => 0xFF0000, 'InfoSchriftgroesse' => 0.8]);
            return [];
        },
    ];
}

// Je Konfiguration: Nachrichten aus ApplyChanges und VM_UPDATE, das Kacheldokument hinter module.html und die
// Warnungen (aufgezeichnet statt geworfen; Symcon protokolliert sie und läuft weiter).
function vergleich(): array
{
    $warnungen = [];
    set_error_handler(static function (int $severity, string $message) use (&$warnungen): bool {
        $warnungen[] = $message;
        return true;
    });
    $html = (string) file_get_contents(dirname((new ReflectionClass(TileVisuWallboxKachel::class))->getFileName()) . '/module.html');
    try {
        world();
        $m = tile();
        $ergebnis = ['_eigenschaften' => [$m->properties, $m->propertyTypes]];
        foreach (konfigurationen() as $name => $aufbau) {
            world();
            $m = tile();
            $ids = $aufbau($m);
            $warnungen = [];
            $m->ApplyChanges();
            $dokument = $m->GetVisualizationTile();
            foreach ($ids as $id) {
                $m->MessageSink(1, $id, VM_UPDATE, [GetValue($id), true, null, 1]);
            }
            $ergebnis[$name] = ['updates' => $m->updates, 'tail' => substr($dokument, strlen($html)), 'warnungen' => $warnungen];
        }
    } finally {
        restore_error_handler();
    }
    return $ergebnis;
}

// Altstand in ein Temp-Verzeichnis entpacken; null ohne git oder ohne den Commit (etwa in einer flachen Kopie).
function altstand(): ?string
{
    $repo = escapeshellarg(dirname(__DIR__));
    exec('git -C ' . $repo . ' cat-file -e ' . escapeshellarg(ALTSTAND . '^{commit}') . ' 2>/dev/null', $unused, $code);
    if ($code !== 0) {
        return null;
    }
    $dir = sys_get_temp_dir() . '/wallbox-test-' . bin2hex(random_bytes(6));
    mkdir($dir, 0700, true);
    exec('git -C ' . $repo . ' archive ' . escapeshellarg(ALTSTAND) . ' | tar -x -C ' . escapeshellarg($dir), $unused, $code);
    return $code === 0 && is_file($dir . '/Wallbox/module.php') ? $dir : null;
}

function aufraeumen(string $dir): void
{
    $eintraege = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($eintraege as $eintrag) {
        $eintrag->isDir() ? rmdir($eintrag->getPathname()) : unlink($eintrag->getPathname());
    }
    rmdir($dir);
}

// Führt einen Modus dieser Datei mit der Kachel des Altstands in einem eigenen Prozess aus.
function imAltstand(string $dir, string $modus): mixed
{
    $datei = $dir . '/' . $modus . '.json';
    $command = 'WALLBOX_MODULE=' . escapeshellarg($dir . '/Wallbox/module.php') . ' ' . escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg(__FILE__) . ' ' . escapeshellarg($modus) . ' ' . escapeshellarg($datei) . ' 2>&1';
    exec($command, $ausgabe, $code);
    if ($code !== 0 || !is_file($datei)) {
        throw new RuntimeException('Mode ' . $modus . ' failed in ' . ALTSTAND . ': ' . implode("\n", $ausgabe));
    }
    return json_decode((string) file_get_contents($datei), true, 512, JSON_THROW_ON_ERROR);
}

if (in_array($argv[1] ?? '', ['vergleich'], true)) {
    // Gegenprobe: dieselben Schritte mit der Kachel, die WALLBOX_MODULE geladen hat
    file_put_contents($argv[2], json_encode(($argv[1])(), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION));
    exit(0);
}

echo '--- Module Strict' . PHP_EOL;
$source = (string) file_get_contents(__DIR__ . '/../Wallbox/module.php');
check(str_starts_with($source, "<?php\n\ndeclare(strict_types=1);\n"), 'strict_types is the first statement');
check(get_parent_class(TileVisuWallboxKachel::class) === 'IPSModuleStrict', 'Module extends IPSModuleStrict');
$untyped = [];
foreach ((new ReflectionClass(TileVisuWallboxKachel::class))->getMethods() as $method) {
    if ($method->getDeclaringClass()->getName() !== TileVisuWallboxKachel::class) {
        continue;
    }
    $typed = $method->hasReturnType();
    foreach ($method->getParameters() as $parameter) {
        $typed = $typed && $parameter->hasType();
    }
    if (!$typed) {
        $untyped[] = $method->getName();
    }
}
check($untyped === [], 'Every module method has typed parameters and a return type');
check(!str_contains($source, '?>'), 'No closing PHP tag');
try {
    (new class extends IPSModuleStrict { public function probe(): void { $this->RegisterPropertyBoolean('X', 1); } })->probe();
    throw new LogicException('int accepted as bool');
} catch (TypeError $e) {
    check(true, 'SDK double enforces strict types (RegisterPropertyBoolean with 1 fails)');
}

world();
variable(101, 2, 'Laden');
$m = tile();
check($m->visualizationType === 1, 'Tile uses the HTML SDK');
check($m->properties['BG_Off'] === true, 'BG_Off is a real Boolean (was registered with 1)');
$m->properties['Status'] = 101;
$runlevel = 0;
$m->ApplyChanges();
check($m->updates === [] && $m->references === [] && $m->messages === [0 => [IPS_KERNELSTARTED]], 'Before KR_READY only the kernel start is awaited');
$runlevel = KR_READY;
$m->MessageSink(0, 0, IPS_KERNELSTARTED, []);
check(count($m->updates) === 1 && ($m->messages[0] ?? []) !== [IPS_KERNELSTARTED] && isset($m->references[101]), 'Kernel start completes ApplyChanges');

echo '--- RequestAction und UpdateList' . PHP_EOL;
world();
$m = tile();
$ids = alleVariablen($m);
variable(118, 'Typ 2', 'Typ 2');
$m->properties['Kabel'] = 118;
$m->RequestAction('SOCschalter', 'egal');
$m->RequestAction('SOC', 5);
$m->RequestAction('Ladeleistung', '0.5');
$m->RequestAction('ZielSOC', 'kein Versatz');
$m->RequestAction('Kabel', 'Typ 1');
check($actions === [[105, false], [103, 50], [102, 7.9], [104, 'kein Versatz'], [118, 'Typ 1']], 'RequestAction toggles Booleans, offsets numbers and writes other values as before');
$actions = [];
$m->properties['Reichweite'] = 999;
$m->RequestAction('Reichweite', 1);
check($actions === [], 'A missing variable is not touched');
foreach (['bgImage', 'Bildauswahl', 'Unbekannt', ''] as $ident) {
    try {
        $m->RequestAction($ident, 1);
        throw new LogicException('Ident accepted');
    } catch (Exception $e) {
        check(str_starts_with($e->getMessage(), 'Invalid ident:') && $actions === [], 'Ident "' . $ident . '" is rejected');
    }
}
$m->UpdateList(101);
check($m->formFields === [['ProfilAssoziazionen', 'values', json_encode([
    ['AssoziationName' => 'Bereit', 'AssoziationValue' => 0.0, 'Bildauswahl' => 'goe_aus', 'StatusColor' => '-1'],
    ['AssoziationName' => 'Laden', 'AssoziationValue' => 2.0, 'Bildauswahl' => 'goe_aus', 'StatusColor' => '-1'],
])]], 'UpdateList fills the status mapping from the profile as before');

echo '--- Werte, die sich nicht als JSON kodieren lassen' . PHP_EOL;
world();
$m = tile();
alleVariablen($m, ['Kabel' => ["Typ \xFF", "Typ \xFF"]]);
$m->ApplyChanges();
check(latest($m)['Kabel'] === "Typ \u{FFFD}" && parts($m)['state']['Kabel'] === "Typ \u{FFFD}", 'Invalid UTF-8 is replaced instead of losing the whole message');
changeValue(103, NAN, 'NAN %');
$m->ApplyChanges();
check(end($m->updates) === '{}' && parts($m)['state'] === [], 'An unencodable value gives {} instead of a TypeError');
$count = count($m->updates);
$m->MessageSink(0, 103, VM_UPDATE, [NAN, true, 45, 1]);
check(count($m->updates) === $count, 'An unencodable update is not sent (no TypeError)');

echo '--- Payload wie bisher (Gegenprobe gegen ' . ALTSTAND . ')' . PHP_EOL;
$alt = altstand();
if ($alt === null) {
    echo 'SKIP: ' . ALTSTAND . ' not available, no comparison with the previous tile' . PHP_EOL;
} else {
    try {
        $vorher = imAltstand($alt, 'vergleich');
        $nachher = vergleich();
        check($nachher['_eigenschaften'] === $vorher['_eigenschaften'], 'Same 34 properties with the same types and defaults');
        unset($nachher['_eigenschaften'], $vorher['_eigenschaften']);
        check(array_keys($nachher) === array_keys($vorher) && count($nachher) === count(konfigurationen()), 'Both versions ran every configuration');
        // Warnungen des Altstands: undefinierte Variable bei Medien ohne Bild, fehlende Animation aus UpdateList
        $alteWarnungen = array_filter(array_map(static fn (array $k): array => $k['warnungen'], $vorher));
        check(array_keys($alteWarnungen) === ['eigene Bilder ohne Bild', 'Zuordnung aus UpdateList'], 'The previous tile warned in exactly two configurations');
        foreach ($nachher as $name => $konfiguration) {
            check($konfiguration['warnungen'] === [], $name . ': no warning');
            check($konfiguration['updates'] === $vorher[$name]['updates'], $name . ': messages byte for byte as before');
            check($konfiguration['tail'] === $vorher[$name]['tail'], $name . ': tile document byte for byte as before');
        }
    } finally {
        aufraeumen($alt);
    }
}
echo 'OK' . PHP_EOL;
