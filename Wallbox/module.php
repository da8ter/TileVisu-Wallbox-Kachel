<?php
class TileVisuWallboxKachel extends IPSModule
{
    public function Create()
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
        $this->RegisterPropertyBoolean('BG_Off', 1);
        $this->RegisterPropertyInteger("bgImage", 0);
        $this->RegisterPropertyFloat('Bildtransparenz', 0.7);
        $this->RegisterPropertyInteger('Kachelhintergrundfarbe', -1);

        // Visualisierungstyp auf 1 setzen, da wir HTML anbieten möchten
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        //Referenzen Registrieren
        $ids = array_unique(array_filter(array_merge(
            [$this->ReadPropertyInteger('bgImage')],
            array_map(fn(string $prop) => $this->ReadPropertyInteger($prop), self::VARIABLE_PROPERTIES)
        )));

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
            $this->RegisterMessage($this->ReadPropertyInteger($VariableProperty), VM_UPDATE);
        }

        // Schicke eine komplette Update-Nachricht an die Darstellung, da sich ja Parameter geändert haben können
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
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message !== VM_UPDATE) {
            return;
        }

        foreach (self::VARIABLE_PROPERTIES as $property) {
            if ($SenderID === $this->ReadPropertyInteger($property)) {
                $this->UpdateVisualizationValue(json_encode([
                    $property           => GetValueFormatted($SenderID),
                    $property . 'Value' => GetValue($SenderID)
                ]));
                break;
            }
        }
    }

    /**
     * Receives actions from the HTML visualization and relays them to the
     * corresponding IPS variable. Supports boolean toggle and numeric offset
     * writes for integer & float variables.
     *
     * @param string $Ident  Name of the module property that holds the variable ID
     * @param mixed  $value  Value or offset supplied by the front-end
     */
    public function RequestAction($Ident, $value): void
    {
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
                $newValue = is_numeric($value) ? $currentValue + $value : $value;
                break;
            default:
                $newValue = $value;
                break;
        }

        RequestAction($variableID, $newValue);
    }

    public function GetVisualizationTile()
    {
        // Füge ein Skript hinzu, um beim Laden, analog zu Änderungen bei Laufzeit, die Werte zu setzen
        $initialHandling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ')</script>';
        $bildauswahl = $this->ReadPropertyInteger('Bildauswahl');



        if($bildauswahl == '0') {
            $assets = '<script>';
            $assets .= 'window.assets = {};' . PHP_EOL;
            $assets .= 'window.assets.img_goe_aus = "data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/go_e.webp')) . '";' . PHP_EOL;
            $assets .= 'window.assets.img_goe_an = "data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/go_e_kabel.webp')) . '";' . PHP_EOL;
            $assets .= '</script>';
        }
        elseif($bildauswahl == '1') {
            $assets = '<script>';
            $assets .= 'window.assets = {};' . PHP_EOL;
            $assets .= 'window.assets.img_goe_aus = "data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/go_e_gemini.webp')) . '";' . PHP_EOL;
            $assets .= 'window.assets.img_goe_an = "data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/go_e_gemini_kabel.webp')) . '";' . PHP_EOL;
            $assets .= '</script>';
        }
        elseif($bildauswahl == '2') {
            $assets = '<script>';
            $assets .= 'window.assets = {};' . PHP_EOL;
            $assets .= 'window.assets.img_goe_aus = "data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/legacy.webp')) . '";' . PHP_EOL;
            $assets .= 'window.assets.img_goe_an = "data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/legacy_kabel.webp')) . '";' . PHP_EOL;
            $assets .= '</script>';
        }
        else {

        // Prüfe vorweg, ob ein Bild ausgewählt wurde
        $imageID_Bild_An = $this->ReadPropertyInteger('Bild_An');
        if (IPS_MediaExists($imageID_Bild_An)) {
            $image = IPS_GetMedia($imageID_Bild_An);
            if ($image['MediaType'] === MEDIATYPE_IMAGE) {
                $imageFile = explode('.', $image['MediaFile']);
                $imageContent = '';
                // Falls ja, ermittle den Anfang der src basierend auf dem Dateitypen
                switch (end($imageFile)) {
                    case 'bmp':
                        $imageContent = 'data:image/bmp;base64,';
                        break;

                    case 'jpg':
                    case 'jpeg':
                        $imageContent = 'data:image/jpeg;base64,';
                        break;

                    case 'gif':
                        $imageContent = 'data:image/gif;base64,';
                        break;

                    case 'png':
                        $imageContent = 'data:image/png;base64,';
                        break;

                    case 'ico':
                        $imageContent = 'data:image/x-icon;base64,';
                        break;
                }

                // Nur fortfahren, falls Inhalt gesetzt wurde. Ansonsten ist das Bild kein unterstützter Dateityp
                if ($imageContent) {
                    // Hänge base64-codierten Inhalt des Bildes an
                    $imageContent .= IPS_GetMediaContent($imageID_Bild_An);
                }

            }
        }
        else {
            $imageContent = 'data:image/png;base64,';

            $imageContent .= base64_encode(file_get_contents(__DIR__ . '/../imgs/transparent.webp'));

            
        } 

                // Prüfe vorweg, ob ein Bild ausgewählt wurde
                $imageID_Bild_Aus = $this->ReadPropertyInteger('Bild_Aus');
                if (IPS_MediaExists($imageID_Bild_Aus)) {
                    $image2 = IPS_GetMedia($imageID_Bild_Aus);
                    if ($image2['MediaType'] === MEDIATYPE_IMAGE) {
                        $imageFile2 = explode('.', $image2['MediaFile']);
                        $imageContent2 = '';
                        // Falls ja, ermittle den Anfang der src basierend auf dem Dateitypen
                        switch (end($imageFile2)) {
                            case 'bmp':
                                $imageContent2 = 'data:image/bmp;base64,';
                                break;
        
                            case 'jpg':
                            case 'jpeg':
                                $imageContent2 = 'data:image/jpeg;base64,';
                                break;
        
                            case 'gif':
                                $imageContent2 = 'data:image/gif;base64,';
                                break;
        
                            case 'png':
                                $imageContent2 = 'data:image/png;base64,';
                                break;
        
                            case 'ico':
                                $imageContent2 = 'data:image/x-icon;base64,';
                                break;
                        }
        
                        // Nur fortfahren, falls Inhalt gesetzt wurde. Ansonsten ist das Bild kein unterstützter Dateityp
                        if ($imageContent2) {
                            // Hänge base64-codierten Inhalt des Bildes an
                            $imageContent2 .= IPS_GetMediaContent($imageID_Bild_Aus);
                        }
        
                    }
                }
                else {
                    $imageContent2 = 'data:image/png;base64,';

                    $imageContent2 .= base64_encode(file_get_contents(__DIR__ . '/../imgs/transparent.webp'));

                    
                }  

            $assets = '<script>';
            $assets .= 'window.assets = {};' . PHP_EOL;
            $assets .= 'window.assets.img_goe_aus = "' . $imageContent2 . '";' . PHP_EOL;
            $assets .= 'window.assets.img_goe_an = "' . $imageContent . '";' . PHP_EOL;
            $assets .= '</script>';
        }


        // Formulardaten lesen und Statusmapping Array für Bild und Farbe erstellen
        $assoziationsArray = json_decode($this->ReadPropertyString('ProfilAssoziazionen'), true);
        $statusMappingImage = [];
        $statusMappingColor = [];
        $statusMappingAnimation = [];
        foreach ($assoziationsArray as $item) {
            $statusMappingImage[$item['AssoziationValue']] = $item['Bildauswahl'];
            $statusMappingAnimation[$item['AssoziationValue']] = $item['Animation'];
                      
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

    private const VARIABLE_PROPERTIES = ['Status', 'Ladeleistung', 'SOC', 'ZielSOC', 'SOCschalter', 'ZielSOCschalter', 'Verbrauchgesamt', 'VerbrauchTag', 'KostenTag', 'KostenGesamt', 'Fehler', 'Phasen', 'MaxLadeleistung', 'Kabel', 'Zugangskontrolle', 'Verriegelung', 'Reichweite'];

    // Generiere eine Nachricht, die alle Elemente in der HTML-Darstellung aktualisiert
    private function GetFullUpdateMessage() {
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

        // Hintergrundbild
        $bgImage = $this->GetBackgroundImageBase64();
        if ($bgImage !== '') {
            $result['image1'] = $bgImage;
        }

        return json_encode($result);
    }

    /**
     * Generic helper to convert an IPS media image to a base64 data URI.
     * Provides a fallback image if the media ID is not valid.
     *
     * @param int    $imageID      IPS media object ID
     * @param string $fallbackPath Absolute filesystem path to fallback image
     *
     * @return string Base64 encoded data URI
     */
    private function GetMediaImageBase64(int $imageID, string $fallbackPath): string
    {
        if (IPS_MediaExists($imageID)) {
            $image = IPS_GetMedia($imageID);
            if ($image['MediaType'] === MEDIATYPE_IMAGE) {
                $extension = strtolower(pathinfo($image['MediaFile'], PATHINFO_EXTENSION));
                $mime = '';
                switch ($extension) {
                    case 'bmp':  $mime = 'image/bmp'; break;
                    case 'jpg':
                    case 'jpeg': $mime = 'image/jpeg'; break;
                    case 'gif':  $mime = 'image/gif'; break;
                    case 'png':  $mime = 'image/png'; break;
                    case 'webp': $mime = 'image/webp'; break;
                    case 'ico':  $mime = 'image/x-icon'; break;
                }
                if ($mime !== '') {
                    return 'data:' . $mime . ';base64,' . IPS_GetMediaContent($imageID);
                }
            }
        }

        $extension = strtolower(pathinfo($fallbackPath, PATHINFO_EXTENSION));
        $mime = $extension === 'webp' ? 'image/webp' : 'image/' . $extension;
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fallbackPath));
    }

    /**
     * Compatibility wrapper for old code – provides the background image
     * Base64 string while preserving the original BG_Off behaviour.
     *
     * @return string Base64 encoded data URI or empty string
     */
    private function GetBackgroundImageBase64(): string
    {
        $imageID = $this->ReadPropertyInteger('bgImage');
        $fallback = __DIR__ . '/../imgs/kachelhintergrund1.png';

        // Try media image first
        if (IPS_MediaExists($imageID)) {
            return $this->GetMediaImageBase64($imageID, $fallback);
        }

        // If BG_Off is enabled, deliver fallback image; otherwise no image
        if ($this->ReadPropertyBoolean('BG_Off')) {
            return 'data:image/png;base64,' . base64_encode(file_get_contents($fallback));
        }

        return '';
    }

    private function CheckAndGetValueFormatted($property) {
        $id = $this->ReadPropertyInteger($property);
        if (IPS_VariableExists($id)) {
            return GetValueFormatted($id);
        }
        return false;
    }

    private function GetColor($id) {
        $variable = IPS_GetVariable($id);
        $Value = GetValue($id);
        $profile = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];

        if ($profile && IPS_VariableProfileExists($profile)) {
            $p = IPS_GetVariableProfile($profile);
            
            foreach ($p['Associations'] as $association) {
                if (isset($association['Value'], $association['Color']) && $association['Value'] == $Value) {
                    return $association['Color'] === -1 ? "" : sprintf('%06X', $association['Color']);
                    
                }
            }
        }
        return "";
    }

    private function GetColorRGB($hexcolor) {
        $transparenz = $this->ReadPropertyFloat('InfoMenueTransparenz');
        if($hexcolor != "-1")
        {
                $hexColor = sprintf('%06X', $hexcolor);
                // Prüft, ob der Hex-Farbwert gültig ist
                if (strlen($hexColor) == 6) {
                    $r = hexdec(substr($hexColor, 0, 2));
                    $g = hexdec(substr($hexColor, 2, 2));
                    $b = hexdec(substr($hexColor, 4, 2));
                    return "rgba($r, $g, $b, $transparenz)";
                } else {
                    // Fallback für ungültige Eingaben
                    return $hexColor;
                }
        }
        else {
            return "";
        }
    }

    private function GetIcon($id, $varicon) {
        $variable = IPS_GetVariable($id);
        $Value = GetValue($id);
        $icon = "";
        //Abfragen ob das Variablen-Icon oder das Profil-Icon verwendet werden soll
        if($varicon == true){
        $icon = IPS_GetObject($id);
            if($icon['ObjectIcon'] != ""){
                $icon = $icon['ObjectIcon'];
            }
            else {
                $icon = "Transparent";
            }
        }
        else {
        // Profil-Icon abrufen
        $profile = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];
        $icon = "";

        if ($profile && IPS_VariableProfileExists($profile)) {
            $p = IPS_GetVariableProfile($profile);

            foreach ($p['Associations'] as $association) {
                if (isset($association['Value']) && $association['Icon'] != "" && $association['Value'] == $Value) {
                    $icon = $association['Icon'];
                    break;
                }
            }

            if ($icon == "" && isset($p['Icon']) && $p['Icon'] != "") {
                $icon = $p['Icon'];
            }

            if ($icon == "") {
                $icon = "Transparent";
            }
        }
        else {
            $icon = "Transparent";
        }
        
        }
        return $icon;
    }

    public function UpdateList($StatusID)
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
?>
