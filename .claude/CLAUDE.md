# Wallbox-Kachel

Symcon-HTML-Kachel, die den Zustand einer Wallbox zeigt: Gerätebild je Status (go-e, Gemini, Universal oder eigene Bilder), Ladeleistung, SOC und Ziel-SOC, Phasen, Verbrauch, Kosten, Fehler, Kabel, Verriegelung. Öffentliches Repo `da8ter/TileVisu-Wallbox-Kachel`.

Betriebsdaten dieses Rechners stehen in `CLAUDE.local.md` (nicht eingecheckt). Eine `docs/` gibt es nicht; Begründungen stehen in Code-Kommentaren, Commit-Botschaften und `tests/README.md`.

## Aufbau

- **`Wallbox/`** (Präfix `WAL`, Klasse `TileVisuWallboxKachel`, `IPSModuleStrict`, ab Symcon 8.1): `module.php`, `module.html`, `form.json`, `locale.json`, `assets/` (Gerätebilder).
- **`module.html` und `locale.json` im Wurzelordner sind alte Kopien** ohne Wirkung (kein `module.json` daneben). Änderungen gehören nach `Wallbox/`.
- **17 Variablen-Eigenschaften** (`VARIABLE_PROPERTIES`). `RequestAction` weist jeden Ident ab: Die Kachel schaltet nichts, und Kachel-Idents erreicht jeder Visu-Browser.
- **Bilder** über den nativen Hook `/hook/wallboximages/<ID>?k=…&v=…&t=…` (registriert in `Create()`, Token aus `ApplyChanges`). Ohne Hook oder über `ScriptOutputBufferLimit` bleiben sie als Data-URI eingebettet.
- **Startwerte** im Skriptblock mit `JSON_HEX_TAG | JSON_HEX_AMP`; `phasecount` wird nur als Zahl eingesetzt.
- **`MessageSink`** prüft jede Eigenschaft, die auf die gemeldete Variable zeigt (ohne `break`): Zeigen zwei Eigenschaften auf dieselbe Variable, bekommen beide ihr Update. Gesendet wird nur bei echter Änderung (`$Data[1]`, Prüfwert je Eigenschaft im Puffer `UpdateHashes`); ID 0 wird nie abonniert.
- LED- und Fehler-Animationen laufen nur bei passendem Status. Dauer-Animationen im Leerlauf kosten die ganze Visu (siehe Verweis unten).

## Prüfen

```bash
php -l Wallbox/module.php
php tests/module_test.php      # inkl. Gegenprobe gegen den Altstand 24f3ce5 per git archive
git diff --check
```

`tests/bootstrap.php` ist eine eigene SDK-Attrappe, `tests/szenarien.php` hält die Abläufe, die auch mit dem Altstand laufen. Abdeckung und Proben für eine Testinstanz: `tests/README.md`.

## Regeln

- **Commits:** nach jeder abgeschlossenen Änderung, ein Thema je Commit, deutsche Botschaft, **ohne** Co-Authored-By-Zeile. Tests vorher.
- **Nie** `git checkout`/`git restore` auf Dateien: die Arbeitskopie kann nicht committete Arbeit enthalten.
- **Push nur auf Zuruf.** Arbeitszweig ist `beta`; auf GitHub wird `symcon-beta` nachgezogen (`git push origin HEAD:symcon-beta`), `beta` und `main` nur auf ausdrücklichen Zuruf. Vorher `git log HEAD..origin/<Zweig>` prüfen (auf `origin/beta` liegen Upload-Commits, die lokal fehlen), nie force-pushen.
- **Release:** `version`, `build` und `date` in `library.json` hochsetzen.
- **Öffentliches Repo:** keine IP-Adressen, Ports, Instanz-IDs, Token, Pfade unter `/Users/` – auch nicht in Tests oder Doku.
- **Symcon-Hausregeln:** `declare(strict_types=1)`, typisierte Signaturen, kein Heavy Work vor `KR_READY`, Darstellungen statt Variablenprofilen für eigene Variablen, neue Nutzertexte sagen „Symcon“.

## Weiteres Wissen

- Symcon-Plattformwissen (Hooks und Ausgabegrenze, Lebenszyklus, Kacheln und Icons): https://github.com/da8ter/SymDo-Family-Organizer/tree/SymDo-Beta/.claude/docs/plattform, lokal `../List/.claude/docs/plattform/`.
- Sparsame Updates und Animationen in Kacheln: https://github.com/da8ter/SymDo-Family-Organizer/blob/SymDo-Beta/.claude/docs/entscheidungen/kacheln-ressourcen.md
