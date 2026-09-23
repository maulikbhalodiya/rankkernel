# Self-hosted admin webfonts

Every RankKernel admin font lives in this directory. The plugin principle is
no external HTTP calls from shipped code, so no RankKernel screen requests a
font from Google Fonts or any other host at runtime. Each family ships as one
variable woff2 file with its licence text beside it.

The `@font-face` rules live in `../css/rankkernel-admin.css` and reference
these files through paths relative to that stylesheet.

## Files

| File | Family | Axes | Subset | Size |
| --- | --- | --- | --- | --- |
| `inter-variable-latin.woff2` | Inter | wght 400 to 700 | Latin | 48256 bytes |
| `jetbrains-mono-variable-latin.woff2` | JetBrains Mono | wght 400 to 500 | Latin | 31432 bytes |
| `material-symbols-outlined-variable.woff2` | Material Symbols Outlined | FILL 0 to 1 | 25 icon ligatures | 4564 bytes |

The two text families carry the Latin subset only, which covers the admin UI
copy. Characters outside it fall through to the next family in the token
stack. The icon font carries only the ligature names the admin screens use,
with the component glyphs and the GSUB rules that turn them into icons kept
beside the outlines.

The Inter and JetBrains Mono binaries carry wider weight axes than the table
records (Inter spans 100 to 900, JetBrains Mono spans 400 to 800). The
`@font-face` rules clamp them to the weights the admin UI uses.

Material Symbols ships with four axes (`opsz` 20 to 48, `wght` 100 to 700,
`FILL` 0 to 1, `GRAD` -50 to 200). The subset keeps `FILL` as a range and
instances the other three axes at the values the admin CSS requests: `wght`
400, `GRAD` 0, `opsz` 20. The stylesheet still declares the family weight
range 100 to 700, and the pinned build renders at weight 400, the only
weight the admin CSS requests.

## Sources

| Family | Request | File URL | Version | Fetched |
| --- | --- | --- | --- | --- |
| Inter | `https://fonts.googleapis.com/css2?family=Inter:wght@400..700&display=swap` | `https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7.woff2` | v20 | 2026-09-23 |
| JetBrains Mono | `https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400..500&display=swap` | `https://fonts.gstatic.com/s/jetbrainsmono/v24/tDbv2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8yKwBNntkaToggR7BYRbKPxDcwg.woff2` | v24 | 2026-09-23 |
| Material Symbols Outlined | `https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap` | `https://fonts.gstatic.com/s/materialsymbolsoutlined/v374/kJEhBvYX7BgnkSrUwT8OhrdQw4oELdPIeeII9v6oFsI.woff2` | v374 | 2026-09-23 |

The CSS requests used a desktop Chrome User-Agent string so Google Fonts
returns the woff2 variable files instead of legacy formats.

## Material Symbols subset

`material-symbols-outlined-variable.woff2` is a subset of the source file in
the Sources table. The full variable file is 4001608 bytes, too heavy for a
plugin that markets itself as lightweight, so the shipped file keeps only
the 25 ligature names the redirects screen design uses:

	add, alt_route, arrow_right, assessment, auto_fix_high, cancel,
	check_circle, close, cloud_upload, code_blocks, download, error,
	expand_less, expand_more, filter_alt_off, info, link, priority_high,
	search, search_off, settings, swap_vert, tune, upload, warning

Ligature names shape to a single glyph through the `rlig` GSUB feature, so
the subset must keep the component letter glyphs and the ligature rules
beside the icon outlines. Two names are aliases inside the font: `assessment`
shapes to the `insert_chart` glyph and `auto_fix_high` shapes to the
`auto_fix` glyph. The outline list therefore retains the shaped names, not
the ligature names:

	add, alt_route, arrow_right, insert_chart, auto_fix, cancel,
	check_circle, close, cloud_upload, code_blocks, download, error,
	expand_less, expand_more, filter_alt_off, info, link, priority_high,
	search, search_off, settings, swap_vert, tune, upload, warning

Eleven of those glyphs have a `.fill` twin, and the design's `fill-1` class
switches to them through the `rclt` feature at `FILL` 1. The subset keeps
those twins and the feature: `insert_chart.fill`, `auto_fix.fill`,
`cancel.fill`, `check_circle.fill`, `cloud_upload.fill`, `code_blocks.fill`,
`error.fill`, `filter_alt_off.fill`, `info.fill`, `settings.fill`,
`warning.fill`.

### Regenerating the subset

Install the tools, then run both commands from the plugin root. The first
writes a temporary file, the second replaces the shipped woff2:

```sh
pip install --user fonttools brotli
# when pip refuses to write into an externally managed Python, append
# --break-system-packages or install inside a virtual environment

pyftsubset assets/fonts/material-symbols-outlined-variable.woff2 \
	--output-file=/tmp/material-symbols-subset.woff2 \
	--flavor=woff2 \
	--text='_abcdefghiklmnoprstuvwxy' \
	--glyphs='add,alt_route,arrow_right,insert_chart,auto_fix,cancel,check_circle,close,cloud_upload,code_blocks,download,error,expand_less,expand_more,filter_alt_off,info,link,priority_high,search,search_off,settings,swap_vert,tune,upload,warning,insert_chart.fill,auto_fix.fill,cancel.fill,check_circle.fill,cloud_upload.fill,code_blocks.fill,error.fill,filter_alt_off.fill,info.fill,settings.fill,warning.fill' \
	--layout-features='*' \
	--no-layout-closure \
	--glyph-names \
	--no-hinting

fonttools varLib.instancer /tmp/material-symbols-subset.woff2 \
	FILL=0:1 wght=400 GRAD=0 opsz=20 \
	--output=assets/fonts/material-symbols-outlined-variable.woff2
```

`--text` carries every letter and the underscore the 25 names use, so the
component glyphs and their cmap entries survive. `--glyphs` carries the
outline glyph names listed above. `--no-layout-closure` is required: the
default closure retains every icon whose name reuses the retained letters,
which would balloon the file back toward the full set. The subsetter keeps
`rlig` and `rclt` because `--layout-features='*'` names every feature.

The instancer pins `wght`, `GRAD` and `opsz` and keeps `FILL` as a range
from 0 to 1, so the `fill-1` design class and any consumer opt in still
switch glyphs. Keeping all four axes costs 19304 bytes, while the pinned
build is 4564 bytes.

After a rebuild, decompress the woff2 with fontTools, shape each of the 25
names with HarfBuzz at `FILL` 0 and `FILL` 1, and assert one glyph per name
with the same glyph name the full font returns.

## Licences

| Family | Licence | Text |
| --- | --- | --- |
| Inter | SIL Open Font License 1.1 | `LICENSE-Inter.txt` |
| JetBrains Mono | SIL Open Font License 1.1 | `LICENSE-JetBrainsMono.txt` |
| Material Symbols Outlined | Apache License 2.0 | `LICENSE-MaterialSymbols.txt` |

Both licences require the licence text to travel with the redistributed font
files, which is why the three files above sit next to the woff2 binaries.

## Regenerating

The files are build time artifacts, downloaded once and committed. To refresh
them, request each family CSS with a Chrome User-Agent, read the woff2 URL for
the wanted subset, and download it:

```sh
curl -fsSL -H 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36' \
	'https://fonts.googleapis.com/css2?family=Inter:wght@400..700&display=swap'
```

## Integrity

All three binaries were checked on 2026-09-23 with `file`, which reports Web
Open Font Format (Version 2), and every file begins with the `wOF2` magic
bytes. None is an HTML error page. The Material Symbols subset was rebuilt
and re-checked the same day: `file` reports 4564 bytes of Web Open Font
Format (Version 2), the file starts with `wOF2`, HarfBuzz shapes all 25
names to exactly one glyph at `FILL` 0 and `FILL` 1, those glyph outlines
are identical to the full font instanced at the same settings, and
`npx csstree-validator assets/css/rankkernel-admin.css` exits 0.
