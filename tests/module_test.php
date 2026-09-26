<?php

declare(strict_types=1);

// Wallbox-Kachel ohne laufendes Symcon: Module Strict, Bild-Hook, Nachrichtenfilter, Payload wie bisher
// (Gegenprobe gegen 24f3ce5).
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/szenarien.php';
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (in_array($argv[1] ?? '', ['vergleich', 'nachrichtenfilter', 'abos'], true)) {
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
check(count($m->updates) === 1 && !isset($m->messages[0]) && $m->messages === [101 => [VM_UPDATE]] && isset($m->references[101]),
    'Kernel start completes ApplyChanges and leaves no subscription on sender 0');

echo '--- Icon-Baustein' . PHP_EOL;
$moduleHtml = (string) file_get_contents(__DIR__ . '/../Wallbox/module.html');
check(substr_count($moduleHtml, '<!-- symcon-icons-shared: ') === 1 && substr_count($moduleHtml, '<!-- /symcon-icons-shared -->') === 1
    && strpos($moduleHtml, '<!-- symcon-icons-shared: ') < strpos($moduleHtml, '</head>'), 'Shared icon block is built into the head exactly once');
check(preg_match('~<script src="/icons\.js"[^>]*>\s*</script>~', $moduleHtml) === 0, 'The tile no longer loads /icons.js with a script tag of its own');

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

echo '--- Bild-Hook: Adressen statt Base64' . PHP_EOL;
$root = dirname(__DIR__);
$bytes = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);
world();
$hookAvailable = true;
$h = tile(23456);
check($h->hooks === ['wallboximages/23456'] && $h->buffers['ImageHook'] === '1', 'Image hook is registered natively in Create');
$hookMethod = new ReflectionMethod(TileVisuWallboxKachel::class, 'ProcessHookData');
check($hookMethod->getDeclaringClass()->getName() === TileVisuWallboxKachel::class && $hookMethod->isProtected()
    && (string) $hookMethod->getReturnType() === 'void', 'ProcessHookData is protected and returns void, compatible with the HookInstance overlay');
check($h->attributes['ImageHookToken'] === '', 'No token before ApplyChanges');
alleVariablen($h);
$h->ApplyChanges();
$token = $h->attributes['ImageHookToken'];
check(strlen($token) === 32 && ctype_xdigit($token), 'ApplyChanges creates a 128-bit hook token');
$h->ApplyChanges();
check($h->attributes['ImageHookToken'] === $token, 'Token stays stable across ApplyChanges');
$adresse = static fn (string $key): string => '~\A/hook/wallboximages/23456\?k=' . $key . '&v=[0-9a-f]{16}&t=' . $token . '\z~';
$t = parts($h);
$groesse = ['mit Hook' => strlen($t['html']), 'Voll-Update mit Hook' => strlen(end($h->updates))];
check(preg_match($adresse('goe_aus'), $t['assets']['aus']) === 1 && preg_match($adresse('goe_an'), $t['assets']['an']) === 1, 'Go-e images are hook addresses');
check(preg_match($adresse('bg_default'), $t['state']['image1']) === 1 && latest($h)['image1'] === $t['state']['image1'],
    'Default background is the same hook address in the tile and in the ApplyChanges message');
check(!str_contains($t['html'], 'base64,') && !str_contains(implode('', $h->updates), 'base64,'), 'Neither the tile document nor the messages carry Base64');
check(!preg_match('~[\s"\'()\\\\]~', $t['state']['image1']), 'Background address is valid inside an unquoted CSS url()');
foreach ([1 => 'gemini', 2 => 'legacy'] as $auswahl => $name) {
    $h->properties['Bildauswahl'] = $auswahl;
    $t = parts($h);
    check(preg_match($adresse($name . '_aus'), $t['assets']['aus']) === 1 && preg_match($adresse($name . '_an'), $t['assets']['an']) === 1,
        'Image selection ' . $auswahl . ' uses the ' . $name . ' images via the hook');
}
$h->properties['Bildauswahl'] = 3;
$t = parts($h);
check(preg_match($adresse('transparent'), $t['assets']['aus']) === 1 && $t['assets']['an'] === $t['assets']['aus'], 'Custom images without media: transparent placeholder via the hook');
image(501, 'media/an.jpg', 'JPEG-AN');
image(502, 'media/aus.png', 'PNG-AUS');
image(510, 'media/bg.WEBP', 'WEBP-BG');
$h->properties = array_replace($h->properties, ['Bild_An' => 501, 'Bild_Aus' => 502, 'bgImage' => 510]);
$h->ApplyChanges();
$t = parts($h);
check(preg_match($adresse('bild_an'), $t['assets']['an']) === 1 && preg_match($adresse('bild_aus'), $t['assets']['aus']) === 1, 'Custom media images are hook addresses');
check(preg_match($adresse('bgimage'), $t['state']['image1']) === 1 && latest($h)['image1'] === $t['state']['image1'], 'Own background is a hook address');
check(!str_contains($t['html'], 'base64,') && !str_contains(end($h->updates), 'base64,'), 'Media images: no Base64 in the tile document or the message');

echo '--- Auslieferung über den Hook' . PHP_EOL;
$lieferungen = [
    'goe_aus' => ['Wallbox/assets/go_e.webp', 'image/webp'], 'goe_an' => ['Wallbox/assets/go_e_kabel.webp', 'image/webp'],
    'gemini_aus' => ['Wallbox/assets/go_e_gemini.webp', 'image/webp'], 'gemini_an' => ['Wallbox/assets/go_e_gemini_kabel.webp', 'image/webp'],
    'legacy_aus' => ['Wallbox/assets/legacy.webp', 'image/webp'], 'legacy_an' => ['Wallbox/assets/legacy_kabel.webp', 'image/webp'],
    'transparent' => ['imgs/transparent.webp', 'image/webp'], 'bg_default' => ['imgs/kachelhintergrund1.png', 'image/png'],
];
foreach ($lieferungen as $key => [$datei, $mime]) {
    $body = $h->hook(['k' => $key, 't' => $token]);
    check($h->status === 200 && $body === $bytes($datei) && $h->header('Content-Type') === $mime
        && $h->header('Content-Length') === (string) strlen($body), 'Hook delivers ' . $key . ' as ' . $mime);
}
$q = query($t['assets']['an']);
$body = $h->hook($q);
check($h->status === 200 && $body === 'JPEG-AN' && $h->header('Content-Type') === 'image/jpeg', 'Hook delivers the custom image with its type');
check($h->header('Cache-Control') === 'private, max-age=31536000, immutable', 'Current version is cached long, privately');
check($h->header('ETag') === '"' . $q['v'] . '"' && $h->header('X-Content-Type-Options') === 'nosniff', 'ETag is the version, nosniff is set');
check($h->hook(query($t['assets']['aus'])) === 'PNG-AUS' && $h->header('Content-Type') === 'image/png', 'Second custom image is delivered as PNG');
check($h->hook(query($t['state']['image1'])) === 'WEBP-BG' && $h->header('Content-Type') === 'image/webp', 'Own background (WEBP in capitals) is delivered as WebP');
$body = $h->hook($q, ['HTTP_IF_NONE_MATCH' => '"' . $q['v'] . '"']);
check($h->status === 304 && $body === '' && $h->header('Content-Type') === null, 'Matching If-None-Match answers 304 without body');
$body = $h->hook(['k' => 'bild_an', 'v' => 'veraltet', 't' => $token], ['HTTP_IF_NONE_MATCH' => '"' . $q['v'] . '"']);
check($h->status === 200 && $body === 'JPEG-AN' && $h->header('Cache-Control') === 'no-cache', 'Outdated address gets the current image uncached, never 304');
$h->hook(['k' => 'bild_an', 't' => $token]);
check($h->status === 200 && $h->header('Cache-Control') === 'no-cache', 'Address without version is not cached');
image(501, 'media/an.jpg', 'JPEG-NEU');
$neu = parts($h)['assets']['an'];
check($neu !== $t['assets']['an'] && $h->hook(query($neu)) === 'JPEG-NEU', 'Changed media content gets a new address');
check($h->hook($q) === 'JPEG-NEU' && $h->header('Cache-Control') === 'no-cache', 'The old address gets the new content uncached');
foreach (['wrong token' => ['k' => 'goe_an', 't' => str_repeat('0', 32)], 'missing token' => ['k' => 'goe_an'],
    'empty token' => ['k' => 'goe_an', 't' => ''], 'token as array' => ['k' => 'goe_an', 't' => [$token]]] as $label => $get) {
    $body = $h->hook($get);
    check($h->status === 403 && $body === '' && $h->sent === [], 'Hook rejects the request with 403 (' . $label . ')');
}
foreach (['unbekannt', '', '../module.php', 'assets/go_e.webp', 'img_goe_an', 'GOE_AN', 'Status', 'bgImage', 'Bild_An'] as $key) {
    $body = $h->hook(['k' => $key, 't' => $token]);
    check($h->status === 404 && $body === '' && $h->sent === [], 'Unknown key "' . $key . '" is rejected with 404');
}
$h->hook(['k' => ['goe_an'], 't' => $token]);
check($h->status === 404 && $h->sent === [], 'Key as array is rejected with 404');
image(502, 'media/aus.webp', 'WEBP');
image(510, 'ton.wav', 'RIFF', 2);
foreach (['bild_aus' => 'custom image with unsupported type', 'bgimage' => 'media object that is no image'] as $key => $label) {
    $body = $h->hook(['k' => $key, 't' => $token]);
    check($h->status === 404 && $body === '', 'Hook answers 404 for a ' . $label);
}
check(preg_match($adresse('bg_default'), parts($h)['state']['image1']) === 1, 'A background media object that is no image shows the default background as before');
unset($media[501], $media[502], $media[510]);
foreach (['bild_an', 'bild_aus', 'bgimage'] as $key) {
    $h->hook(['k' => $key, 't' => $token]);
    check($h->status === 404, 'Hook answers 404 for "' . $key . '" without media object');
}

echo '--- Rückfall ohne registrierten Hook' . PHP_EOL;
world();
$n = tile(12346);
alleVariablen($n);
$n->ApplyChanges();
check($n->hooks === ['wallboximages/12346'] && $n->buffers['ImageHook'] === '' && $n->attributes['ImageHookToken'] === '', 'Unregistered hook: remembered as inactive, no token');
$t = parts($n);
check($t['assets']['aus'] === 'data:image/webp;base64,' . base64_encode($bytes('Wallbox/assets/go_e.webp'))
    && $t['state']['image1'] === 'data:image/png;base64,' . base64_encode($bytes('imgs/kachelhintergrund1.png')), 'Without hook the images stay data URIs as before');
$groesse['ohne Hook'] = strlen($t['html']);
$n->hook(['k' => 'goe_aus', 't' => '']);
check($n->status === 403, 'Without hook the endpoint answers nothing');

echo '--- Bilder über der Ausgabegrenze bleiben eingebettet' . PHP_EOL;
world();
$hookAvailable = true;
$g = tile(34567);
$g->ApplyChanges();
$gt = $g->attributes['ImageHookToken'];
$options['ScriptOutputBufferLimit'] = 1024 + strlen($bytes('Wallbox/assets/go_e.webp')); // Nutzlast genau go_e.webp
$t = parts($g);
check(str_starts_with($t['assets']['aus'], '/hook/wallboximages/34567?k=goe_aus&'), 'Image exactly at the limit goes through the hook');
check($t['assets']['an'] === 'data:image/webp;base64,' . base64_encode($bytes('Wallbox/assets/go_e_kabel.webp'))
    && $t['state']['image1'] === 'data:image/png;base64,' . base64_encode($bytes('imgs/kachelhintergrund1.png')), 'Larger images stay data URIs');
check($g->hook(['k' => 'goe_aus', 't' => $gt]) === $bytes('Wallbox/assets/go_e.webp'), 'Hook delivers the image at the limit');
$options['ScriptOutputBufferLimit']--;
check(parts($g)['assets']['aus'] === 'data:image/webp;base64,' . base64_encode($bytes('Wallbox/assets/go_e.webp')), 'One byte above the limit stays a data URI');
$options['ScriptOutputBufferLimit']++;
foreach (['goe_an', 'bg_default'] as $key) {
    $body = $g->hook(['k' => $key, 't' => $gt]);
    check($g->status === 404 && $body === '' && $g->sent === [], 'Hook refuses "' . $key . '" above the limit before any output');
}
$options['ScriptOutputBufferLimit'] = 'kaputt';
check(str_starts_with(parts($g)['state']['image1'], '/hook/'), 'An unusable limit falls back to the factory 1 MiB');
unset($options['ScriptOutputBufferLimit']);

echo '--- Nur bei echter Wertänderung' . PHP_EOL;
$zeilen = nachrichtenfilter();
foreach ($zeilen as [$label, $erwartet, $ist]) {
    check($ist === $erwartet, $label . ' (' . $ist . ' messages)');
}

echo '--- Abos und Referenzen nur für zugeordnete Objekte' . PHP_EOL;
$abos = abos();
check(array_keys($abos['abos']) === [702, 701] && array_unique(array_merge(...array_values($abos['abos']))) === [VM_UPDATE],
    'VM_UPDATE only for the two assigned variables');
check(!isset($abos['abos'][0]) && $abos['leer'] === [], 'No VM_UPDATE for sender 0 (it would report every variable in the system to MessageSink)');
check($abos['referenzen'] === [510, 702, 701] && $abos['leerReferenzen'] === [], 'References only for assigned objects');

echo '--- Gegenprobe gegen ' . ALTSTAND . PHP_EOL;
$alt = altstand();
if ($alt === null) {
    echo 'SKIP: ' . ALTSTAND . ' not available, no comparison with the previous tile' . PHP_EOL;
} else {
    try {
        // Nachrichtenfilter: ohne ihn fallen genau die Prüfungen, die keine Nachricht erwarten
        $vorherFilter = imAltstand($alt, 'nachrichtenfilter');
        $fallend = array_column(array_filter($vorherFilter, static fn (array $z): bool => $z[1] !== $z[2]), 0);
        $still = array_column(array_filter($zeilen, static fn (array $z): bool => $z[1] === 0), 0);
        check(count($vorherFilter) === count($zeilen) && $still !== [] && $fallend === $still,
            'Without the filter (' . ALTSTAND . ') exactly the ' . count($still) . ' checks expecting no message fail');

        // Abos: der Altstand meldete VM_UPDATE auch für nicht zugeordnete Eigenschaften (0) an
        $vorherAbos = imAltstand($alt, 'abos');
        check(($vorherAbos['abos'][0] ?? null) === [VM_UPDATE] && $vorherAbos['leer'] === [[VM_UPDATE]],
            ALTSTAND . ' registered VM_UPDATE for sender 0: the check above fails there');
        check($vorherAbos['referenzen'] === $abos['referenzen'] && $vorherAbos['leerReferenzen'] === [], 'References skipped 0 already before and stay the same');

        // Payload wie bisher: dieselben Konfigurationen, Byte für Byte
        $vorher = imAltstand($alt, 'vergleich');
        $nachher = vergleich();
        check($nachher['_eigenschaften'] === $vorher['_eigenschaften'], 'Same 34 properties with the same types and defaults');
        unset($nachher['_eigenschaften'], $vorher['_eigenschaften']);
        check(array_keys($nachher) === array_keys($vorher) && count($nachher) === count(konfigurationen()), 'Both versions ran every configuration');
        // Warnungen des Altstands: undefinierte Variable bei Medien ohne Bild, fehlende Animation aus UpdateList
        $alteWarnungen = array_filter(array_map(static fn (array $k): array => $k['warnungen'], $vorher));
        check(array_keys($alteWarnungen) === ['eigene Bilder ohne Bild', 'Zuordnung aus UpdateList'], 'The previous tile warned in exactly two configurations');
        // Einzige gewollte Abweichung (ohne Hook): der Platzhalter ist eine WebP-Datei und kam bisher als image/png
        $platzhalter = base64_encode($bytes('imgs/transparent.webp'));
        $korrigiert = static fn (string $text): string => str_replace('data:image/png;base64,' . $platzhalter, 'data:image/webp;base64,' . $platzhalter, $text);
        $betroffen = array_keys(array_filter($vorher, static fn (array $k): bool => $korrigiert($k['tail']) !== $k['tail']));
        check($betroffen === ['eigene Bilder ohne Medien', 'Bildauswahl 7, ein eigenes Bild'], 'The placeholder type differs only where the placeholder is shown');
        foreach ($nachher as $name => $konfiguration) {
            check($konfiguration['warnungen'] === [], $name . ': no warning');
            check($konfiguration['updates'] === $vorher[$name]['updates'], $name . ': messages byte for byte as before');
            check($konfiguration['tail'] === $korrigiert($vorher[$name]['tail']), $name . ': tile document byte for byte as before');
        }
        $html = static fn (string $dir): int => strlen((string) file_get_contents($dir . '/Wallbox/module.html'));
        echo 'Kacheldokument ' . ALTSTAND . ' / ohne Hook / mit Hook: ' . ($html($alt) + strlen($vorher['alle Variablen']['tail'])) . ' / '
            . $groesse['ohne Hook'] . ' / ' . $groesse['mit Hook'] . ' Bytes; Voll-Update aus ApplyChanges ' . ALTSTAND . ' / mit Hook: '
            . strlen($vorher['alle Variablen']['updates'][0]) . ' / ' . $groesse['Voll-Update mit Hook'] . ' Bytes' . PHP_EOL;
    } finally {
        aufraeumen($alt);
    }
}
echo 'OK' . PHP_EOL;
