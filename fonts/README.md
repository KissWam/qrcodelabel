# Fonts for label export

Both output paths need TrueType fonts on disk: PNG labels are drawn with GD's
`imagettftext()`, and PDF labels embed the font through TCPDF.

**Since v1.4.1, the plugin bundles [Noto Sans](https://fonts.google.com/noto/specimen/Noto+Sans)
(Apache 2.0 license) directly in this directory** so PNG export works
out-of-the-box on any GLPI install, including hosted / sandboxed environments
with no system fonts.

**Since v1.6.1 it also bundles `DroidSansFallbackFull.ttf`** (Apache 2.0) for
Chinese, Japanese and Korean text. Noto Sans has no CJK glyphs, which made
Chinese asset names print as empty boxes.

## Latin vs CJK

The two font families cover different scripts and cannot be swapped:

| Script | Font | Used by |
|---|---|---|
| Latin / digits | `NotoSans-Regular.ttf`, `NotoSans-Bold.ttf` | PDF + PNG |
| CJK | `DroidSansFallbackFull.ttf` | PDF + PNG |

`Label::splitRuns()` cuts every text line into CJK and non-CJK runs and draws
each run with the matching font, so mixed text such as
`研发部工作站 A 区` renders correctly. Latin runs keep their bold weight; the
CJK run uses the single Droid weight available.

On the PDF side the font is imported once into
`files/_plugins/qrcodelabel/fonts/*.json` (via `Com\Tecnick\Pdf\Font\Import`,
since GLPI 12's TCPDF dropped `addTTFfont()`) and embedded as a subset, which
keeps the PDF around 220 KB per page.

## Font lookup order

The plugin searches for a usable TTF in this order:

1. **Plugin bundled** (this directory) — `NotoSans-Regular.ttf`, `LiberationSans-Regular.ttf`, `DejaVuSans.ttf` (and their `-Bold` counterparts).
2. **Common Linux system paths** — `/usr/share/fonts/truetype/liberation/`, `/usr/share/fonts/truetype/dejavu/`, `/usr/share/fonts/truetype/droid/`, etc.
3. **Windows** — `C:\Windows\Fonts\arial.ttf`.

For CJK the bundled `DroidSansFallbackFull.ttf` is tried first, then
`/usr/share/fonts/truetype/droid/DroidSansFallbackFull.ttf`.

## Overriding the bundled font

Drop your own `LiberationSans-Regular.ttf` or `DejaVuSans.ttf` (+ matching
`-Bold.ttf`) in this directory — the plugin uses the first hit in the list
above, so a named file takes precedence over fallbacks only if it comes
earlier in the search order.

The simplest way to switch to another font: delete `NotoSans-*.ttf` from this
directory and drop your preferred TTF in its place (keeping the naming
convention, or patch `Label::findFont()` to recognise your filename).

For CJK, replace `DroidSansFallbackFull.ttf` with any TrueType font that has
`glyf` outlines (CFF/OTF collections such as `NotoSansCJK-*.ttc` are **not**
supported by TCPDF's importer). Delete
`files/_plugins/qrcodelabel/fonts/droidsansfallbackfull.*` afterwards so the
new font gets re-imported.

## License notice

- **Noto Sans** — Apache License 2.0, © Google LLC. See `OFL.txt` upstream.
- **Droid Sans Fallback** — Apache License 2.0, © Google LLC.
- **Liberation Sans** — SIL Open Font License 1.1 (if used).
- **DejaVu Sans** — Bitstream Vera / Arev derivative license (if used).

All of them are free to redistribute with this plugin.
