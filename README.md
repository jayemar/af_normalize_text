# af_normalize_text

A TT-RSS plugin that normalizes fullwidth Unicode characters and replaces
typographic HTML entities in article titles and content at import time.

## Problem

Articles from certain feeds may contain two categories of characters that
display poorly or inconsistently in feed readers:

1. **Fullwidth Unicode characters** (U+FF01-U+FF60) from East Asian sources,
   Substack, or publishers using stylized text. Examples: `ｈｅｌｌｏ`,
   `Ｈｅａｄｌｉｎｅ`, `１２３`. These appear too wide or break layout.

2. **Typographic HTML entities** - curly quotes, dashes, ellipsis, and
   non-breaking spaces stored as named entities (`&rsquo;`), numeric entities
   (`&#8217;`, `&#x2019;`), Unicode code points (U+2019), or double-encoded
   variants (`&amp;rsquo;`) when TT-RSS/SimplePie does not fully decode them.
   These render as literal entity strings instead of the intended characters.

## Solution

This plugin normalizes both categories at import time, so all clients benefit
automatically without any per-client configuration.

## Fullwidth Normalization

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

## Typographic Entity Replacement

The plugin replaces typographic punctuation entities with ASCII equivalents
using a static lookup table. All three encoding forms are handled, as well as
double-encoded variants produced by TT-RSS/SimplePie:

| Entity | Encoding forms handled | Replaced with |
|---|---|---|
| Right single quote | `&rsquo;` `&#8217;` `&#x2019;` `\u{2019}` `&amp;rsquo;` | `'` |
| Left single quote | `&lsquo;` `&#8216;` `&#x2018;` `\u{2018}` `&amp;lsquo;` | `'` |
| Right double quote | `&rdquo;` `&#8221;` `&#x201D;` `\u{201D}` `&amp;rdquo;` | `"` |
| Left double quote | `&ldquo;` `&#8220;` `&#x201C;` `\u{201C}` `&amp;ldquo;` | `"` |
| Em dash | `&mdash;` `&#8212;` `&#x2014;` `\u{2014}` `&amp;mdash;` | `--` |
| En dash | `&ndash;` `&#8211;` `&#x2013;` `\u{2013}` `&amp;ndash;` | `-` |
| Ellipsis | `&hellip;` `&#8230;` `&#x2026;` `\u{2026}` `&amp;hellip;` | `...` |
| Non-breaking space | `&nbsp;` `&#160;` `&#xA0;` `\u{00A0}` `&amp;nbsp;` | ` ` |

Structural HTML entities (`&lt;`, `&amp;`, `&gt;`) are intentionally excluded
and are never altered.

Entity replacement applies to both title and content independently of the
fullwidth normalization settings.

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
| Replace typographic entities | Enabled | Replace typographic HTML entities with ASCII equivalents in titles and content |

Content normalization is disabled by default because fullwidth characters may
be used intentionally in East Asian text and normalizing them could alter
meaning. Typographic entity replacement is safe to leave enabled for all feeds.

## PHP Requirements

- **Recommended**: `php-intl` extension (provides `Normalizer` class)
- **Fallback**: `mbstring` extension (provides `mb_convert_kana()`)

Most TT-RSS Docker installations include both. The plugin degrades gracefully
if neither is present. Typographic entity replacement uses only built-in PHP
string functions and has no extension dependencies.

## Hooks Used

- `HOOK_ARTICLE_FILTER` - applies normalization at feed import time
- `HOOK_PREFS_TAB` - adds settings UI under Preferences -> Feeds
