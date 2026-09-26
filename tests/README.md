# Tests

Ohne Zugriff auf einen Symcon-Server aus dem Repository-Verzeichnis ausführen:

```sh
php -l Wallbox/module.php
php -l tests/bootstrap.php
php -l tests/module_test.php
php tests/module_test.php
git diff --check
```

`tests/bootstrap.php` ist eine kleine SDK-Attrappe: `IPSModuleStrict` mit den typisierten Signaturen der Methoden, die das Modul überschreibt, dazu die Instanzmethoden und globalen Funktionen, die es aufruft (Properties, Attribute, Puffer, Referenzen, Nachrichten, nativer Hook, Variablen, Profile, Medien, Kernel-Runlevel, `ScriptOutputBufferLimit`). Sie verbindet sich nie mit einem laufenden Symcon. Der Test wandelt jede Warnung in eine Ausnahme um; unter `strict_types` fällt ein falscher Typ als `TypeError` auf. `ProbeTile` macht `ProcessHookData` öffentlich wie Symcons `HookInstance` und fängt Kopfzeilen und Status ab. Für die Gegenprobe enthält die Attrappe zusätzlich ein untypisiertes `IPSModule`.

`tests/module_test.php` prüft:

- Module Strict: `strict_types` als erste Anweisung, `IPSModuleStrict`, alle Methoden voll typisiert, kein schließendes `?>`, `BG_Off` als Boolean, Warten auf `KR_READY` mit `IPS_KERNELSTARTED`.
- `RequestAction` wie bisher (Boolean umschalten, Zahlen versetzen, sonst schreiben), fremde Idents werden abgewiesen, eine fehlende Variable bleibt unberührt; `UpdateList` füllt die Zuordnungsliste aus dem Profil.
- Werte, die sich nicht kodieren lassen: ungültiges UTF-8 wird ersetzt, NAN ergibt `{}` bzw. kein Update statt eines `TypeError`.
- Payload wie bisher, Gegenprobe gegen den Stand `24f3ce5`: der Test entpackt ihn per `git archive` in ein Temp-Verzeichnis und lässt dieselben 20 Konfigurationen (Bildauswahl, eigene Bilder mit allen Dateitypen, Hintergründe, Zuordnungen, Sonderzeichen, Farben) in einem eigenen Prozess laufen (`WALLBOX_MODULE` lädt dafür die alte Kachel). Verglichen werden die 34 Eigenschaften, die Nachrichten aus `ApplyChanges` und `VM_UPDATE` und das Kacheldokument hinter `module.html`, Byte für Byte. Die beiden Warnungen des alten Stands (Medienobjekt ohne Bild, Zuordnung ohne Animation) treten nicht mehr auf. Ohne git oder ohne den Commit wird die Gegenprobe übersprungen (SKIP).

Nicht abgedeckt ist die echte Symcon-Laufzeit. Vor dem Einsatz in einer Testinstanz prüfen:

- Modul neu laden, dann die Kachel öffnen.
- Symcon neu starten und die Kachel nach dem Kernelstart prüfen.
