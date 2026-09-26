# Tests

Ohne Zugriff auf einen Symcon-Server aus dem Repository-Verzeichnis ausführen:

```sh
php -l Wallbox/module.php
php -l tests/bootstrap.php
php -l tests/szenarien.php
php -l tests/module_test.php
php tests/module_test.php
git diff --check
```

`tests/bootstrap.php` ist eine kleine SDK-Attrappe: `IPSModuleStrict` mit den typisierten Signaturen der Methoden, die das Modul überschreibt, dazu die Instanzmethoden und globalen Funktionen, die es aufruft (Properties, Attribute, Puffer, Referenzen, Nachrichten, nativer Hook, Variablen, Profile, Medien, Kernel-Runlevel, `ScriptOutputBufferLimit`). Sie verbindet sich nie mit einem laufenden Symcon. Der Test wandelt jede Warnung in eine Ausnahme um; unter `strict_types` fällt ein falscher Typ als `TypeError` auf. `ProbeTile` macht `ProcessHookData` öffentlich wie Symcons `HookInstance` und fängt Kopfzeilen und Status ab. Für die Gegenprobe enthält die Attrappe zusätzlich ein untypisiertes `IPSModule`.

`tests/szenarien.php` enthält die Abläufe, die für die Gegenprobe auch mit der alten Kachel laufen (Konfigurationen, Nachrichtenfilter, Abos), und die Werkzeuge dafür. `tests/module_test.php` prüft:

- Module Strict: `strict_types` als erste Anweisung, `IPSModuleStrict`, alle Methoden voll typisiert, kein schließendes `?>`, `BG_Off` als Boolean, Warten auf `KR_READY` mit `IPS_KERNELSTARTED`.
- Icon-Baustein: `module.html` enthält den geteilten Block `symcon-icons-shared` genau einmal im `head` und kein eigenes Script-Tag für `/icons.js` mehr. Ob der Block dem Stand der Quelle entspricht, prüft `visu-messung/baustein/verteilen.py --pruefen`.
- `RequestAction` wie bisher (Boolean umschalten, Zahlen versetzen, sonst schreiben), fremde Idents werden abgewiesen, eine fehlende Variable bleibt unberührt; `UpdateList` füllt die Zuordnungsliste aus dem Profil.
- Werte, die sich nicht kodieren lassen: ungültiges UTF-8 wird ersetzt, NAN ergibt `{}` bzw. kein Update statt eines `TypeError`.
- Bild-Hook `/hook/wallboximages/<ID>?k=<Schlüssel>&v=<Version>&t=<Token>`: nativ in `Create` registriert, Token (128 Bit) aus `ApplyChanges`, stabil. Hook-Adressen statt Base64 für alle Bilder: go-e in drei Varianten (`goe_*`, `gemini_*`, `legacy_*`), Platzhalter `transparent`, Standard-Hintergrund `bg_default`, die Medienbilder `bild_an`, `bild_aus` und `bgimage`; weder das Kacheldokument noch die Nachrichten enthalten `base64,`. Auslieferung mit Bildtyp und Länge, `private, max-age=31536000, immutable` nur für die passende Version, sonst `no-cache`, ETag und 304, `nosniff`; neuer Medieninhalt ergibt eine neue Adresse, die alte bekommt ihn ungecacht und nie 304. 403 bei falschem, fehlendem, leerem oder als Array übergebenem Token, 404 für unbekannte Schlüssel (auch Pfade, andere Schreibweisen, Arrays), fehlende Medienobjekte, andere Medien und nicht unterstützte Dateitypen.
- Rückfall: ohne registrierten Hook kein Token, Data-URIs wie bisher, der Hook antwortet 403. Über der Ausgabegrenze (`ScriptOutputBufferLimit` − 1 KiB) bleibt ein Bild eingebettet und der Hook verweigert es vor jeder Ausgabe (404); ein Bild genau an der Grenze geht über den Hook, eins mit einem Byte mehr nicht. Eine unbrauchbare Option fällt auf 1 MiB zurück.
- Nur bei echter Wertänderung: `VM_UPDATE` mit `$Data[1] === false` schickt nichts, ein neuer Wert seine Nachricht, eine identische Nachricht kein zweites Mal, auch nicht nach der Nachricht einer anderen Variable (Prüfwert je Eigenschaft im Puffer `UpdateHashes`); die nächste echte Änderung geht wieder hinaus. `ApplyChanges` schickt weiter das Voll-Update; danach und nach dem Erstaufbau geht die Nachricht wieder hinaus, ohne `$Data[1]` wird gesendet. Gegenprobe: gegen `24f3ce5` fallen genau die Prüfungen, die keine Nachricht erwarten.
- Abos und Referenzen nur für zugeordnete Objekte (`id > 0`): `VM_UPDATE` nie für Absender 0 (das meldete jede Variable im System an `MessageSink`), eine Kachel ohne Zuordnung meldet nichts an. Gegenprobe: `24f3ce5` meldete Absender 0 an; die Referenzen ließen 0 schon vorher aus und bleiben gleich.
- Payload wie bisher, Gegenprobe gegen den Stand `24f3ce5`: der Test entpackt ihn per `git archive` in ein Temp-Verzeichnis und lässt dieselben 20 Konfigurationen (Bildauswahl, eigene Bilder mit allen Dateitypen, Hintergründe, Zuordnungen, Sonderzeichen, Farben) in einem eigenen Prozess laufen (`WALLBOX_MODULE` lädt dafür die alte Kachel). Verglichen werden die 34 Eigenschaften, die Nachrichten aus `ApplyChanges` und `VM_UPDATE` und das Kacheldokument hinter `module.html`, Byte für Byte. Der Vergleich läuft ohne Hook (Rückfall); einzige gewollte Abweichung ist der Platzhalter, eine WebP-Datei, die bisher als `image/png` eingebettet war, und die tritt genau in den zwei Konfigurationen mit Platzhalter auf. Die beiden Warnungen des alten Stands (Medienobjekt ohne Bild, Zuordnung ohne Animation) treten nicht mehr auf. Am Ende steht die Dokumentgröße vorher/ohne Hook/mit Hook. Ohne git oder ohne den Commit wird die Gegenprobe übersprungen (SKIP).

Nicht abgedeckt ist die echte Symcon-Laufzeit. Vor dem Einsatz in einer Testinstanz prüfen:

- Modul neu laden (neues Attribut, Hook-Registrierung in `Create()`), dann die Kachel öffnen: die Bilder erscheinen im Netzwerk-Tab als `/hook/wallboximages/<ID>?…` mit Status 200, beim erneuten Öffnen aus dem Cache; `/hook/wallboximages/<ID>` ohne Token liefert 403.
- Bildauswahl go-e, Gemini, Universal und eigene Bilder durchschalten, eigenes Hintergrundbild setzen, im selben Medienobjekt ersetzen und wieder entfernen.
- Kachel in der Symcon-App, auch über Connect, öffnen.
- Symcon neu starten und die Kachel nach dem Kernelstart prüfen.
