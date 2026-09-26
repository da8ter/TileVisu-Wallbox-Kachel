<?php

declare(strict_types=1);

// Szenarien des Modultests, die auch mit der Kachel des Altstands laufen (Gegenprobe im eigenen Prozess), und die
// Werkzeuge dafür. Führt selbst nichts aus.

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

// VM_UPDATE nur bei echter Wertänderung: Zeilen [Bezeichnung, erwartete Nachrichten, gesendete Nachrichten].
// Läuft auch gegen den Altstand; dort müssen genau die Zeilen fallen, die keine Nachricht erwarten.
function nachrichtenfilter(): array
{
    world();
    variable(701, 45, '45 %');
    variable(702, 7.4, '7,4 kW');
    $m = tile(12500);
    $m->properties['SOC'] = 701;
    $m->properties['Ladeleistung'] = 702;
    $m->ApplyChanges();
    $zeilen = [];
    $zaehle = static function (string $label, int $erwartet, callable $aktion) use ($m, &$zeilen): void {
        $vorher = count($m->updates);
        $aktion();
        $zeilen[] = [$label, $erwartet, count($m->updates) - $vorher];
    };
    // $Data wie von Symcon: [neuer Wert, geändert, alter Wert, Zeitstempel]
    $update = static fn (int $id, array $data): callable => static fn () => $m->MessageSink(0, $id, VM_UPDATE, $data);
    $zaehle('Update without a new value ($Data[1] false) sends nothing', 0, $update(701, [45, false, 45, 1]));
    changeValue(701, 46, '46 %');
    $zaehle('Changed value sends its message', 1, $update(701, [46, true, 45, 2]));
    changeValue(702, 11.0, '11 kW');
    $zaehle('Another variable sends its own message', 1, $update(702, [11.0, true, 7.4, 3]));
    // Die Kachel liest den aktuellen Wert: nach mehreren schnellen Änderungen ist die Nachricht dieselbe
    $zaehle('Identical message is not sent again (memory per variable)', 0, $update(701, [46, true, 45, 4]));
    changeValue(701, 47, '47 %');
    $zaehle('The next real change goes out again', 1, $update(701, [47, true, 46, 5]));
    $zaehle('ApplyChanges still sends the full update', 1, static fn () => $m->ApplyChanges());
    $zaehle('After ApplyChanges the same message goes out again', 1, $update(701, [47, true, 46, 6]));
    $zaehle('... but only once', 0, $update(701, [47, true, 46, 7]));
    $m->GetVisualizationTile();
    $zaehle('After the initial build of a tile it goes out again', 1, $update(701, [47, true, 46, 8]));
    $m->GetVisualizationTile();
    $zaehle('Without $Data[1] (other format) the update is sent', 1, $update(702, []));
    return $zeilen;
}

// Abos und Referenzen nach ApplyChanges: zwei zugeordnete Variablen und ein Hintergrundbild, alles andere 0;
// dazu eine Kachel ganz ohne Zuordnung.
function abos(): array
{
    world();
    variable(701, 45, '45 %');
    variable(702, 7.4, '7,4 kW');
    image(510, 'media/bg.png', 'BG');
    $m = tile(12600);
    $m->properties = array_replace($m->properties, ['SOC' => 701, 'Ladeleistung' => 702, 'bgImage' => 510]);
    $m->ApplyChanges();
    $leer = tile(12601);
    $leer->ApplyChanges();
    return ['abos' => $m->messages, 'referenzen' => array_keys($m->references), 'leer' => $leer->messages, 'leerReferenzen' => array_keys($leer->references)];
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

// Führt einen Modus des Modultests mit der Kachel des Altstands in einem eigenen Prozess aus.
function imAltstand(string $dir, string $modus): mixed
{
    $datei = $dir . '/' . $modus . '.json';
    $command = 'WALLBOX_MODULE=' . escapeshellarg($dir . '/Wallbox/module.php') . ' ' . escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg(__DIR__ . '/module_test.php') . ' ' . escapeshellarg($modus) . ' ' . escapeshellarg($datei) . ' 2>&1';
    exec($command, $ausgabe, $code);
    if ($code !== 0 || !is_file($datei)) {
        throw new RuntimeException('Mode ' . $modus . ' failed in ' . ALTSTAND . ': ' . implode("\n", $ausgabe));
    }
    return json_decode((string) file_get_contents($datei), true, 512, JSON_THROW_ON_ERROR);
}
