# af_normalize_text

A TT-RSS plugin that normalizes fullwidth Unicode characters in article titles
and content at import time.

## Problem

Articles from certain feeds - East Asian sources, Substack, or publishers using
stylized text - may contain fullwidth Unicode characters in the range U+FF01-U+FF60.
Examples: `ｈｅｌｌｏ`, `Ｈｅａｄｌｉｎｅ`, `１２３`. These characters can display
poorly in feed readers, appearing too wide, misaligned, or breaking layout.

## Solution

This plugin converts fullwidth characters to their standard ASCII equivalents
at import time, so all clients benefit automatically without any per-client
configuration.

## Normalization Method

The plugin uses NFKC (Unicode Compatibility Decomposition followed by Canonical
Composition) normalization via PHP's `intl` extension (`Normalizer::FORM_KC`).
NFKC handles fullwidth characters, ligatures, fractions, and other compatibility
variants.

If the `intl` extension is unavailable, the plugin falls back to
`mb_convert_kana()` with flags `rns`:

- `r` - fullwidth alphabetic characters to halfwidth
- `n` - fullwidth numeric characters to halfwidth
- `s` - fullwidth ideographic space (U+3000) to halfwidth space

If neither extension is available, the text is returned unchanged.

## Installation

1. Copy this directory to `plugins.local/af_normalize_text/` inside your
   TT-RSS installation.
2. Enable the plugin in **Preferences -> Plugins**.
3. Configure options in **Preferences -> Feeds -> Text Normalization**.

## Configuration

Settings are per-user and available under **Preferences -> Feeds -> Text
Normalization**.

| Option | Default | Description |
|---|---|---|
| Normalize titles | Enabled | Normalize fullwidth characters in article titles |
| Normalize content | Disabled | Normalize fullwidth characters in article body content |

Content normalization is disabled by default because fullwidth characters may
be used intentionally in East Asian text and normalizing them could alter
meaning.

## PHP Requirements

- **Recommended**: `php-intl` extension (provides `Normalizer` class)
- **Fallback**: `mbstring` extension (provides `mb_convert_kana()`)

Most TT-RSS Docker installations include both. The plugin degrades gracefully
if neither is present.

## Hooks Used

- `HOOK_ARTICLE_FILTER` - applies normalization at feed import time
- `HOOK_PREFS_TAB` - adds settings UI under Preferences -> Feeds
