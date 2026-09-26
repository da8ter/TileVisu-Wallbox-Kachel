<?php

declare(strict_types=1);

class TileVisuWallboxKachel extends IPSModuleStrict
{
    // Die Variablen der Kachel (Referenzen, Nachrichten, Updates, RequestAction)
    private const VARIABLE_PROPERTIES = ['Status', 'Ladeleistung', 'SOC', 'ZielSOC', 'SOCschalter', 'ZielSOCschalter', 'Verbrauchgesamt', 'VerbrauchTag', 'KostenTag', 'KostenGesamt', 'Fehler', 'Phasen', 'MaxLadeleistung', 'Kabel', 'Zugangskontrolle', 'Verriegelung', 'Reichweite'];

    // Mitgelieferte Bilder, die der Bild-Hook ausliefert: Schlüssel => [Datei relativ zum Modulordner, Bildtyp]
    private const BUNDLED_IMAGES = [
        'goe_aus'     => ['assets/go_e.webp', 'image/webp'],
        'goe_an'      => ['assets/go_e_kabel.webp', 'image/webp'],
        'gemini_aus'  => ['assets/go_e_gemini.webp', 'image/webp'],
        'gemini_an'   => ['assets/go_e_gemini_kabel.webp', 'image/webp'],
        'legacy_aus'  => ['assets/legacy.webp', 'image/webp'],
        'legacy_an'   => ['assets/legacy_kabel.webp', 'image/webp'],
        // Platzhalter ohne eigenes Bild; eine WebP-Datei, die bisher als image/png eingebettet war
        'transparent' => ['../imgs/transparent.webp', 'image/webp'],
        'bg_default'  => ['../imgs/kachelhintergrund1.png', 'image/png'],
    ];
    // Bildauswahl 0 bis 2: die mitgelieferten Wallbox-Bilder [aus, an]; jede andere Auswahl nimmt die eigenen Bilder
    private const IMAGE_SETS = [0 => ['goe_aus', 'goe_an'], 1 => ['gemini_aus', 'gemini_an'], 2 => ['legacy_aus', 'legacy_an']];
    // Medienbilder: Hook-Schlüssel => Eigenschaft mit dem Medienobjekt
    private const MEDIA_IMAGES = ['bild_aus' => 'Bild_Aus', 'bild_an' => 'Bild_An', 'bgimage' => 'bgImage'];
    // Eigene Wallbox-Bilder: Dateiendungen wie bisher (Groß-/Kleinschreibung zählt, kein webp)
    private const CUSTOM_IMAGE_TYPES = [
        'bmp' => 'image/bmp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'png' => 'image/png', 'ico' => 'image/x-icon',
    ];
    // Eigenes Hintergrundbild: wie bisher zusätzlich webp, die Endung klein geschrieben verglichen
    private const BACKGROUND_IMAGE_TYPES = self::CUSTOM_IMAGE_TYPES + ['webp' => 'image/webp'];
    private const IMAGE_HOOK = 'wallboximages/';

    public function Create(): void
    {
        // Nie diese Zeile löschen!
        parent::Create();


        // Drei Eigenschaften für die dargestellten Zähler
        $this->RegisterPropertyInteger("Status", 0);
        $this->RegisterPropertyInteger("Ladeleistung", 0);
        $this->RegisterPropertyInteger("SOC", 0);
        $this->RegisterPropertyInteger("ZielSOC", 0);
        $this->RegisterPropertyInteger("SOCschalter", 0);
        $this->RegisterPropertyInteger("ZielSOCschalter", 0);
        $this->RegisterPropertyInteger("Verbrauchgesamt", 0);
        $this->RegisterPropertyInteger("VerbrauchTag", 0);
        $this->RegisterPropertyInteger("KostenTag", 0);
        $this->RegisterPropertyInteger("KostenGesamt", 0);
        $this->RegisterPropertyInteger("Fehler", 0);
        $this->RegisterPropertyInteger("Phasen", 0);
        $this->RegisterPropertyInteger("MaxLadeleistung", 0);
        $this->RegisterPropertyInteger("Kabel", 0);
        $this->RegisterPropertyInteger("Zugangskontrolle", 0);
        $this->RegisterPropertyInteger("Verriegelung", 0);
        $this->RegisterPropertyInteger("Reichweite", 0);
        $this->RegisterPropertyFloat("StatusSchriftgroesse", 1);
        $this->RegisterPropertyFloat("ProgrammSchriftgroesse", 1);
        $this->RegisterPropertyFloat("InfoSchriftgroesse", 1);
        $this->RegisterPropertyFloat("BalkenSchriftgroesse", 1);
        $this->RegisterPropertyInteger("BalkenVerlaufFarbe1", 2674091);
        $this->RegisterPropertyInteger("BalkenVerlaufFarbe2", 2132596);
        $this->RegisterPropertyInteger("BalkenVerlaufSOCFarbe1", 7257660);
        $this->RegisterPropertyInteger("BalkenVerlaufSOCFarbe2", 5281320);
        $this->RegisterPropertyInteger("Bildauswahl", 0);
        //$this->RegisterPropertyInteger("Bild", 0);
        $this->RegisterPropertyFloat("BildBreite", 20);
        $this->RegisterPropertyString('ProfilAssoziazionen', '[]');
        $this->RegisterPropertyInteger("Bild_An", 0);
        $this->RegisterPropertyInteger("Bild_Aus", 0);
        $this->RegisterPropertyBoolean('BG_Off', true);
        $this->RegisterPropertyInteger("bgImage", 0);
        $this->RegisterPropertyFloat('Bildtransparenz', 0.7);
        $this->RegisterPropertyInteger('Kachelhintergrundfarbe', -1);

        // Visualisierungstyp auf 1 setzen, da wir HTML anbieten möchten
        $this->SetVisualizationType(1);

        // Bilder über einen eigenen Hook statt als Data-URI in jedem Kacheldokument: der Browser cacht sie unter
        // versionierter Adresse, das Dokument bleibt klein. Ohne Hook bleibt es bei den Data-URIs.
        $this->RegisterAttributeString('ImageHookToken', '');
        $this->SetBuffer('ImageHook', $this->RegisterHook(self::IMAGE_HOOK . $this->InstanceID) ? '1' : '');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // Kein Heavy Work vor KR_READY: Referenzen, Nachrichten und Variablenzugriffe erst, wenn der Kernel
        // bereit ist. IPS_KERNELSTARTED ruft ApplyChanges dann erneut auf.
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }

        $this->EnsureImageHookToken();

        //Referenzen Registrieren (0 = nicht zugeordnet)
        $ids = array_unique(array_filter(array_merge(
            [$this->ReadPropertyInteger('bgImage')],
            array_map(fn(string $prop): int => $this->ReadPropertyInteger($prop), self::VARIABLE_PROPERTIES)
        ), static fn (int $id): bool => $id > 0));

        // Bestehende Referenzen leeren und neu setzen
        foreach ($this->GetReferenceList() as $ref) {
            $this->UnregisterReference($ref);
        }
        foreach ($ids as $id) {
            $this->RegisterReference($id);
        }

        // Aktualisiere registrierte Nachrichten
        foreach ($this->GetMessageList() as $senderID => $messageIDs)
        {
            foreach ($messageIDs as $messageID)
            {
                $this->UnregisterMessage($senderID, $messageID);
            }
        }

        foreach (self::VARIABLE_PROPERTIES as $VariableProperty) {
            $id = $this->ReadPropertyInteger($VariableProperty);
            // 0 = nicht zugeordnet. RegisterMessage(0, VM_UPDATE) meldete jede Variable im System an MessageSink.
            if ($id > 0) {
                $this->RegisterMessage($id, VM_UPDATE);
            }
        }

        // Schicke eine komplette Update-Nachricht an die Darstellung, da sich ja Parameter geändert haben können.
        // Die Prüfwerte der zuletzt gesendeten Nachrichten gelten danach nicht mehr.
        $this->SetBuffer('UpdateHashes', '');
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    /**
     * Handles IPS variable update messages and forwards changed values
     * to the HTML visualization in a single payload.
     *
     * @param int   $TimeStamp Milliseconds since epoch
     * @param int   $SenderID  ID of the variable that triggered the message
     * @param int   $Message   IPS message type (e.g. VM_UPDATE)
     * @param array $Data      Additional message data
     */
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === IPS_KERNELSTARTED) {
            $this->ApplyChanges();
            return;
        }
        if ($Message !== VM_UPDATE) {
            return;
        }
        // Symcon meldet jede Aktualisierung einer Variable, auch ohne neuen Wert: dann bleibt die Kachel, wie sie ist
        if (!self::ValueChanged($Data)) {
            return;
        }

        foreach (self::VARIABLE_PROPERTIES as $property) {
            if ($SenderID === $this->ReadPropertyInteger($property)) {
                // null: der Wert lässt sich nicht als JSON kodieren (NAN, INF) - dann kein Update statt eines TypeError.
                // Eine unveränderte Nachricht geht kein zweites Mal hinaus (Prüfwert je Eigenschaft).
                $message = $this->EncodeJSON([
                    $property           => GetValueFormatted($SenderID),
                    $property . 'Value' => GetValue($SenderID)
                ]);
                if ($message !== null) {
                    $this->SendUpdateIfChanged($property, $message);
                }
                break;
            }
        }
    }

    // Symcon meldet mit VM_UPDATE jede Aktualisierung; $Data[1] sagt, ob sich der Wert geändert hat.
    // Fehlt die Angabe (anderes Format), gilt sie als Änderung: lieber senden als eine verschlucken.
    private static function ValueChanged(array $Data): bool
    {
        return !isset($Data[1]) || (bool) $Data[1];
    }

    // Schickt die Nachrichten eines Schlüssels nur, wenn sie sich von den zuletzt dazu gesendeten unterscheiden.
    // Die Prüfwerte (md5) stehen im Puffer UpdateHashes; ApplyChanges und der Erstaufbau leeren ihn.
    private function SendUpdateIfChanged(string $key, string ...$messages): void
    {
        $hashes = json_decode($this->GetBuffer('UpdateHashes'), true);
        $hashes = is_array($hashes) ? $hashes : [];
        $hash = md5(serialize($messages));
        if (($hashes[$key] ?? null) === $hash) {
            return;
        }
        foreach ($messages as $message) {
            $this->UpdateVisualizationValue($message);
        }
        $hashes[$key] = $hash;
        $this->SetBuffer('UpdateHashes', (string) json_encode($hashes));
    }

    /**
     * Receives actions from the HTML visualization and relays them to the
     * corresponding IPS variable. Supports boolean toggle and numeric offset
     * writes for integer & float variables.
     *
     * @param string $Ident  Name of the module property that holds the variable ID
     * @param mixed  $Value  Value or offset supplied by the front-end
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        // Nur die Variablen-Eigenschaften der Kachel; andere Idents werden an der Systemgrenze abgewiesen,
        // statt beim Lesen einer unbekannten Eigenschaft zu scheitern.
        if (!in_array($Ident, self::VARIABLE_PROPERTIES, true)) {
            throw new Exception('Invalid ident: ' . $Ident);
        }
        $variableID = $this->ReadPropertyInteger($Ident);
        if (!IPS_VariableExists($variableID)) {
            $this->SendDebug('RequestAction', "Variable for ident {$Ident} does not exist", 0);
            return;
        }

        $currentValue = GetValue($variableID);
        $variable     = IPS_GetVariable($variableID);

        switch ($variable['VariableType']) {
            case 0: // Boolean
                $newValue = !$currentValue;
                break;
            case 1: // Integer
            case 2: // Float
                $newValue = is_numeric($Value) ? $currentValue + $Value : $Value;
                break;
            default:
                $newValue = $Value;
                break;
        }

        RequestAction($variableID, $newValue);
    }

    public function GetVisualizationTile(): string
    {
        // Erstaufbau: die Kachel bekommt den vollen Stand, danach geht jede Wertänderung wieder hinaus
        $this->SetBuffer('UpdateHashes', '');

        // Füge ein Skript hinzu, um beim Laden, analog zu Änderungen bei Laufzeit, die Werte zu setzen
        $initialHandling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ')</script>';

        // Wallbox-Bilder: Hook-Adresse, ohne Hook oder über der Ausgabegrenze als Data-URI wie bisher
        [$imageOff, $imageOn] = $this->WallboxImageSources();
        $assets = '<script>';
        $assets .= 'window.assets = {};' . PHP_EOL;
        $assets .= 'window.assets.img_goe_aus = ' . json_encode($imageOff, JSON_UNESCAPED_SLASHES) . ';' . PHP_EOL;
        $assets .= 'window.assets.img_goe_an = ' . json_encode($imageOn, JSON_UNESCAPED_SLASHES) . ';' . PHP_EOL;
        $assets .= '</script>';


        // Formulardaten lesen und Statusmapping Array für Bild und Farbe erstellen
        $assoziationsArray = json_decode($this->ReadPropertyString('ProfilAssoziazionen'), true);
        $statusMappingImage = [];
        $statusMappingColor = [];
        $statusMappingAnimation = [];
        foreach ($assoziationsArray as $item) {
            $statusMappingImage[$item['AssoziationValue']] = $item['Bildauswahl'];
            // Zeilen aus UpdateList haben bis zur ersten Auswahl keine Animation: null wie bisher, aber ohne Warnung
            $statusMappingAnimation[$item['AssoziationValue']] = $item['Animation'] ?? null;
                      
            $statusMappingColor[$item['AssoziationValue']] = $item['StatusColor'] === -1 ? "" : sprintf('%06X', $item['StatusColor']);


        }

        $statusImagesJson = json_encode($statusMappingImage);
        $statusColorJson = json_encode($statusMappingColor);
        $statusAnimationJson = json_encode($statusMappingAnimation);
        $images = '<script type="text/javascript">';
        $images .= 'var statusImages = ' . $statusImagesJson . ';';
        $images .= 'var statusColor = ' . $statusColorJson . ';';
        $images .= 'var statusAnimation = ' . $statusAnimationJson . ';';
        $images .= 'var phasecount = ' . (IPS_VariableExists($this->ReadPropertyInteger('Phasen')) ? GetValue($this->ReadPropertyInteger('Phasen')) : 'null') . ';';
        $images .= 'var wallboxstatus = ' . (IPS_VariableExists($this->ReadPropertyInteger('Status')) ? (int)GetValue($this->ReadPropertyInteger('Status')) : 'null') . ';';
        $images .= '</script>';

        //var_dump(IPS_VariableExists($this->ReadPropertyInteger('Status')) ? GetValue($this->ReadPropertyInteger('Status')) : null);


        // Füge statisches HTML aus Datei hinzu
        $module = file_get_contents(__DIR__ . '/module.html');

        // Gebe alles zurück.
        // Wichtig: $initialHandling nach hinten, da die Funktion handleMessage erst im HTML definiert wird
        return $module . $images . $assets . $initialHandling;
    }

    // Generiere eine Nachricht, die alle Elemente in der HTML-Darstellung aktualisiert
    private function GetFullUpdateMessage(): string
    {
        $result = [];

        // Abbildung: Eigenschaft -> Schlüsselname in der Darstellung (formatierter Wert)
        $formattedKeys = [
            'Status'             => 'status',
            'Ladeleistung'       => 'ladeleistung',
            'SOC'                => 'SOC',
            'ZielSOC'            => 'ZielSOC',
            'Verbrauchgesamt'    => 'Verbrauchgesamt',
            'VerbrauchTag'       => 'verbrauchtag',
            'KostenTag'          => 'kostentag',
            'KostenGesamt'       => 'kostengesamt',
            'Fehler'             => 'Fehler',
            'MaxLadeleistung'    => 'MaxLadeleistung',
            'Kabel'              => 'Kabel',
            'Zugangskontrolle'   => 'Zugangskontrolle',
            'Verriegelung'       => 'Verriegelung',
            'Reichweite'         => 'Reichweite'
        ];

        // Abbildung: Eigenschaft -> Schlüsselname in der Darstellung (Rohwert)
        $rawKeys = [
            'Status'           => 'statusvalue',
            'Ladeleistung'     => 'ladeleistungvalue',
            'MaxLadeleistung'  => 'maxladeleistungvalue',
            'SOC'              => 'SOCvalue',
            'ZielSOC'          => 'ZielSOCvalue',
            'SOCschalter'      => 'SOCschaltervalue',
            'ZielSOCschalter'  => 'ZielSOCschaltervalue',
            'Phasen'           => 'Phasen',
            'Fehler'           => 'fehlervalue'
        ];

        // Durchlaufe alle Variablen-Eigenschaften
        foreach (self::VARIABLE_PROPERTIES as $property) {
            $id = $this->ReadPropertyInteger($property);
            if (!IPS_VariableExists($id)) {
                continue;
            }

            if (isset($formattedKeys[$property])) {
                $result[$formattedKeys[$property]] = $this->CheckAndGetValueFormatted($property);
            }
            if (isset($rawKeys[$property])) {
                $result[$rawKeys[$property]] = GetValue($id);
            }
        }

        // Float-/Style Eigenschaften
        $floatProps = [
            'StatusSchriftgroesse'    => 'statusschriftgroesse',
            'ProgrammSchriftgroesse'  => 'programmschriftgroesse',
            'InfoSchriftgroesse'      => 'infoschriftgroesse',
            'BalkenSchriftgroesse'    => 'balkenschriftgroesse',
            'BildBreite'              => 'BildBreite',
            'Bildtransparenz'         => 'bildtransparenz'
        ];
        foreach ($floatProps as $prop => $key) {
            $result[$key] = $this->ReadPropertyFloat($prop);
        }

        // Integer Farb-Eigenschaften (Hex-Werte)
        $colorProps = [
            'BalkenVerlaufFarbe1'     => 'BalkenVerlaufFarbe1',
            'BalkenVerlaufFarbe2'     => 'BalkenVerlaufFarbe2',
            'BalkenVerlaufSOCFarbe1'  => 'BalkenVerlaufSOCFarbe1',
            'BalkenVerlaufSOCFarbe2'  => 'BalkenVerlaufSOCFarbe2',
            'Kachelhintergrundfarbe'  => 'kachelhintergrundfarbe'
        ];
        foreach ($colorProps as $prop => $key) {
            $result[$key] = '#' . sprintf('%06X', $this->ReadPropertyInteger($prop));
        }

        // Hintergrundbild: Hook-Adresse oder wie bisher Data-URI, ohne Hintergrund kein Schlüssel
        $bgImage = $this->BackgroundSource();
        if ($bgImage !== '') {
            $result['image1'] = $bgImage;
        }

        // Ein Wert, der sich nicht kodieren lässt, ergab bisher false (die Kachel zeigte nichts an); jetzt '{}'
        return $this->EncodeJSON($result) ?? '{}';
    }

    // json_encode wie bisher mit Standard-Flags (maskierte Schrägstriche halten Werte aus dem Skript-Tag heraus);
    // ungültiges UTF-8 wird ersetzt, statt die ganze Nachricht zu verlieren. null, wenn sich ein Wert nicht
    // kodieren lässt (NAN, INF): UpdateVisualizationValue verlangt unter Module Strict einen String.
    private function EncodeJSON(array $data): ?string
    {
        $json = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $this->SendDebug('JSON', json_last_error_msg(), 0);
            return null;
        }
        return $json;
    }

    // Die beiden Wallbox-Bilder [aus, an]: mitgelieferte Bilder (Bildauswahl 0 bis 2) oder die eigenen Medienbilder.
    private function WallboxImageSources(): array
    {
        $set = self::IMAGE_SETS[$this->ReadPropertyInteger('Bildauswahl')] ?? null;
        if ($set !== null) {
            return [$this->BundledImageSource($set[0]), $this->BundledImageSource($set[1])];
        }
        return [$this->CustomImageSource('bild_aus'), $this->CustomImageSource('bild_an')];
    }

    // Eigenes Wallbox-Bild wie bisher: ohne Medienobjekt der transparente Platzhalter, ein Medienobjekt ohne
    // unterstütztes Bild ergibt ''.
    private function CustomImageSource(string $key): string
    {
        if (!IPS_MediaExists($this->ReadPropertyInteger(self::MEDIA_IMAGES[$key]))) {
            return $this->BundledImageSource('transparent');
        }
        return $this->MediaImageSource($key);
    }

    // Hintergrund wie bisher: ein Medienobjekt hat Vorrang - als unterstütztes Bild selbst, sonst das Standardbild,
    // unabhängig von BG_Off. Ohne Medienobjekt je nach BG_Off das Standardbild oder keins ('').
    private function BackgroundSource(): string
    {
        if (IPS_MediaExists($this->ReadPropertyInteger('bgImage'))) {
            $source = $this->MediaImageSource('bgimage');
            return $source !== '' ? $source : $this->BundledImageSource('bg_default');
        }
        return $this->ReadPropertyBoolean('BG_Off') ? $this->BundledImageSource('bg_default') : '';
    }

    // Quelle eines Medienbilds für das Dokument: Hook-Adresse oder Data-URI, '' ohne unterstütztes Bild.
    private function MediaImageSource(string $key): string
    {
        $dataUri = $this->MediaImageDataUri($key);
        return $dataUri === '' ? '' : $this->ImageReference($key, $dataUri);
    }

    // Quelle eines mitgelieferten Bilds für das Dokument: Hook-Adresse oder Data-URI, '' ohne Datei.
    private function BundledImageSource(string $key): string
    {
        $dataUri = $this->BundledImageDataUri($key);
        return $dataUri === '' ? '' : $this->ImageReference($key, $dataUri);
    }

    // Data-URI eines Medienbilds; '' ohne Medienobjekt, für andere Medien und nicht unterstützte Dateiendungen.
    // Eigene Wallbox-Bilder wie bisher: Text nach dem letzten Punkt, Groß-/Kleinschreibung zählt. Eigenes
    // Hintergrundbild wie bisher: Dateiendung klein geschrieben, dazu webp.
    private function MediaImageDataUri(string $key): string
    {
        $imageID = $this->ReadPropertyInteger(self::MEDIA_IMAGES[$key]);
        $image = IPS_MediaExists($imageID) ? IPS_GetMedia($imageID) : null;
        if ($image === null || $image['MediaType'] !== MEDIATYPE_IMAGE) {
            return '';
        }
        if ($key === 'bgimage') {
            $mime = self::BACKGROUND_IMAGE_TYPES[strtolower(pathinfo($image['MediaFile'], PATHINFO_EXTENSION))] ?? '';
        } else {
            $imageFile = explode('.', $image['MediaFile']);
            $mime = self::CUSTOM_IMAGE_TYPES[end($imageFile)] ?? '';
        }
        // IPS_GetMediaContent liefert den Inhalt bereits base64-kodiert
        return $mime === '' ? '' : 'data:' . $mime . ';base64,' . IPS_GetMediaContent($imageID);
    }

    // Data-URI eines mitgelieferten Bilds; '' für unbekannte Schlüssel und fehlende Dateien. Die Dateien sind klein
    // (höchstens 64 kB) und werden je Aufruf gelesen, nicht in einem Instanzpuffer gehalten.
    private function BundledImageDataUri(string $key): string
    {
        [$file, $mime] = self::BUNDLED_IMAGES[$key] ?? ['', ''];
        $path = __DIR__ . '/' . $file;
        $bytes = $file !== '' && is_file($path) ? file_get_contents($path) : false;
        return $bytes === false ? '' : 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    // Version eines Bilds: 16 Zeichen des Hashs seiner Data-URI. Neuer Inhalt ergibt eine neue Adresse.
    private function ImageVersion(string $dataUri): string
    {
        return substr(hash('sha256', $dataUri), 0, 16);
    }

    // Hook-Adresse statt Data-URI, sobald der Hook in diesem Kernel-Lauf registriert ist und das Token steht; die
    // versionierte Adresse darf der Browser lange cachen. Über der Ausgabegrenze bleibt es eingebettet wie bisher.
    // Die Bildgröße ergibt sich aus der Base64-Länge ohne das Auffüllen (=), genau wie der Hook sie prüft.
    private function ImageReference(string $key, string $dataUri): string
    {
        $token = $this->ImageHookActive() ? $this->ReadAttributeString('ImageHookToken') : '';
        $comma = strpos($dataUri, ',');
        if ($token === '' || $comma === false || !str_starts_with($dataUri, 'data:')
            || intdiv((strlen($dataUri) - $comma - 1) * 3, 4) - substr_count(substr($dataUri, -2), '=') > $this->HookBodyLimit()) {
            return $dataUri;
        }
        return '/hook/' . self::IMAGE_HOOK . $this->InstanceID . '?k=' . rawurlencode($key)
            . '&v=' . $this->ImageVersion($dataUri) . '&t=' . $token;
    }

    private function ImageHookActive(): bool
    {
        return $this->GetBuffer('ImageHook') === '1';
    }

    private function EnsureImageHookToken(): void
    {
        if ($this->ImageHookActive() && $this->ReadAttributeString('ImageHookToken') === '') {
            $this->WriteAttributeString('ImageHookToken', bin2hex(random_bytes(16)));
        }
    }

    // Größte Antwort, die Symcon unverändert ausliefert (ScriptOutputBufferLimit, ab Werk 1 MiB), mit Reserve.
    // @: eine Warnung (etwa eine unbekannte Option) landete im Hook vor den Kopfzeilen in der Antwort.
    private function HookBodyLimit(): int
    {
        $limit = 1048576;
        try {
            $option = @IPS_GetOption('ScriptOutputBufferLimit');
            if (is_numeric($option) && (int) $option > 0) {
                $limit = (int) $option;
            }
        } catch (Throwable $e) {
            $this->SendDebug('Image', 'ScriptOutputBufferLimit: ' . $e->getMessage(), 0);
        }
        return max(0, $limit - 1024);
    }

    // /hook/wallboximages/<ID>?k=<Bildschlüssel>&v=<Version>&t=<Token>: ein Bild der Kachel.
    // Ohne gültiges Token 403, für unbekannte Schlüssel und fehlende oder nicht unterstützte Bilder 404.
    protected function ProcessHookData(): void
    {
        $token = $this->ImageHookActive() ? $this->ReadAttributeString('ImageHookToken') : '';
        $given = isset($_GET['t']) && is_string($_GET['t']) ? $_GET['t'] : '';
        if ($token === '' || !hash_equals($token, $given)) {
            $this->SendStatus(403);
            return;
        }
        $image = $this->HookImage(isset($_GET['k']) && is_string($_GET['k']) ? $_GET['k'] : '');
        if ($image === null) {
            $this->SendStatus(404);
            return;
        }
        // Nur die passende Version darf lange gecacht werden, eine alte Adresse bekommt den neuen Inhalt ungecacht.
        // private: die Adresse trägt das Token, geteilte Caches sollen sie nicht halten.
        $current = isset($_GET['v']) && $_GET['v'] === $image['version'];
        $this->SendHeader('Cache-Control: ' . ($current ? 'private, max-age=31536000, immutable' : 'no-cache'));
        $this->SendHeader('ETag: "' . $image['version'] . '"');
        $this->SendHeader('X-Content-Type-Options: nosniff');
        if ($current && trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === '"' . $image['version'] . '"') {
            $this->SendStatus(304);
            return;
        }
        $this->SendHeader('Content-Type: ' . $image['mime']);
        $this->SendHeader('Content-Length: ' . strlen($image['bytes']));
        echo $image['bytes'];
    }

    // Bild zum Hook-Schlüssel oder null (unbekannt, fehlt, kein unterstütztes Bild, über der Ausgabegrenze). Die
    // Grenze muss vor der Ausgabe greifen, sonst ersetzt Symcon die ganze Antwort durch einen Fehlertext.
    private function HookImage(string $key): ?array
    {
        $dataUri = match (true) {
            isset(self::BUNDLED_IMAGES[$key]) => $this->BundledImageDataUri($key),
            isset(self::MEDIA_IMAGES[$key])   => $this->MediaImageDataUri($key),
            default                           => '',
        };
        $comma = strpos($dataUri, ',');
        $bytes = $comma === false ? false : base64_decode(substr($dataUri, $comma + 1), true);
        if (!str_starts_with($dataUri, 'data:image/') || $bytes === false || strlen($bytes) > $this->HookBodyLimit()) {
            return null;
        }
        return ['mime' => substr($dataUri, 5, (int) strpos($dataUri, ';') - 5), 'bytes' => $bytes, 'version' => $this->ImageVersion($dataUri)];
    }

    // Eigene Methoden für Kopfzeilen und Status, damit die Tests den Hook ohne Webserver prüfen können.
    protected function SendHeader(string $header): void
    {
        header($header);
    }

    protected function SendStatus(int $code): void
    {
        http_response_code($code);
    }

    private function CheckAndGetValueFormatted(string $property): string|false
    {
        $id = $this->ReadPropertyInteger($property);
        if (IPS_VariableExists($id)) {
            return GetValueFormatted($id);
        }
        return false;
    }

    public function UpdateList(int $StatusID): void
    {
        $listData = []; // Hier sammeln Sie die Daten für Ihre Liste
    
        $id = $StatusID;

        // Prüfen, ob die übergebene ID einer existierenden Variable entspricht
        if (IPS_VariableExists($id)) {
            // Auslesen des Variablenprofils
            $variable = IPS_GetVariable($id);
            $profileName = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];
            
            if ($profileName != '') {
                $profile = IPS_GetVariableProfile($profileName);
    
                // Durchlaufen der Profilassoziationen
                foreach ($profile['Associations'] as $association) {
                    $listData[] = [
                        'AssoziationName' => $association['Name'],
                        'AssoziationValue' => $association['Value'],
                        'Bildauswahl' => 'goe_aus',
                        'StatusColor' => '-1'
                    ];
                }
            }
        } 
    
        // Konvertieren Sie Ihre Liste in JSON und aktualisieren Sie das Konfigurationsformular
        $jsonListData = json_encode($listData);
        $this->UpdateFormField('ProfilAssoziazionen', 'values', $jsonListData);
    }
}
