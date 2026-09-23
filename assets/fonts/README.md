# Self-hosted admin webfonts

Every RankKernel admin font lives in this directory. The plugin principle is
no external HTTP calls from shipped code, so no RankKernel screen requests a
font from Google Fonts or any other host at runtime. Each family ships as one
variable woff2 file with its licence text beside it.

The `@font-face` rules live in `../css/rankkernel-admin.css` and reference
these files through paths relative to that stylesheet.

## Files

| File | Family | Weight range | Subset | Size |
| --- | --- | --- | --- | --- |
| `inter-variable-latin.woff2` | Inter | 400 to 700 | Latin | 48256 bytes |
| `jetbrains-mono-variable-latin.woff2` | JetBrains Mono | 400 to 500 | Latin | 31432 bytes |
| `material-symbols-outlined-variable.woff2` | Material Symbols Outlined | 100 to 700 | Full glyph set | 4001608 bytes |

The two text families carry the Latin subset only, which covers the admin UI
copy. Characters outside it fall through to the next family in the token
stack. The icon font is the complete variable file because the design uses
both the outlined and the filled instance, and ligature substitution needs
every icon glyph present.

The Inter and JetBrains Mono binaries carry wider weight axes than the table
records (Inter spans 100 to 900, JetBrains Mono spans 400 to 800). The
`@font-face` rules clamp them to the weights the admin UI uses.

## Sources

| Family | Request | File URL | Version | Fetched |
| --- | --- | --- | --- | --- |
| Inter | `https://fonts.googleapis.com/css2?family=Inter:wght@400..700&display=swap` | `https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7.woff2` | v20 | 2026-09-23 |
| JetBrains Mono | `https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400..500&display=swap` | `https://fonts.gstatic.com/s/jetbrainsmono/v24/tDbv2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8yKwBNntkaToggR7BYRbKPxDcwg.woff2` | v24 | 2026-09-23 |
| Material Symbols Outlined | `https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap` | `https://fonts.gstatic.com/s/materialsymbolsoutlined/v374/kJEhBvYX7BgnkSrUwT8OhrdQw4oELdPIeeII9v6oFsI.woff2` | v374 | 2026-09-23 |

The CSS requests used a desktop Chrome User-Agent string so Google Fonts
returns the woff2 variable files instead of legacy formats.

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
bytes. None is an HTML error page.
