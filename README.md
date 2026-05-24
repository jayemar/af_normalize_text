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

3. **HTML character entities** for accented and extended characters such as
   `&eacute;`, `&agrave;`, `&copy;`, `&reg;`, and any other named, decimal
   (`&#233;`), or hex (`&#xE9;`) entity that was not decoded before storage.
   These appear as raw entity strings rather than the intended characters.

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

Fullwidth ASCII punctuation (e.g., ；，（）：！？ and all others in the
U+FF01-U+FF0F, U+FF1A-U+FF20, U+FF3B-U+FF40, and U+FF5B-U+FF5E ranges) is
not covered by those flags, so the fallback additionally applies an explicit
map to convert all 32 fullwidth punctuation code points to their halfwidth
ASCII equivalents.

If neither extension is available, the text is returned unchanged.

## Typographic Entity Replacement

The plugin replaces typographic punctuation entities with ASCII equivalents
using a static lookup table. All three encoding forms are handled, as well as
double-encoded variants produced by TT-RSS/SimplePie:

| Entity | Encoding forms handled | Replaced with |
|---|---|---|
| Apostrophe (XML) | `&apos;` `&#39;` `&#x27;` `&amp;apos;` `&amp;#39;` `&amp;#x27;` | `'` |
| Right single quote | `&rsquo;` `&#8217;` `&#x2019;` `\u{2019}` `&amp;rsquo;` `&amp;#8217;` `&amp;#x2019;` | `'` |
| Left single quote | `&lsquo;` `&#8216;` `&#x2018;` `\u{2018}` `&amp;lsquo;` `&amp;#8216;` `&amp;#x2018;` | `'` |
| Straight double quote | `&quot;` `&#34;` `&#x22;` `&amp;quot;` `&amp;#34;` `&amp;#x22;` | `"` |
| Right double quote | `&rdquo;` `&#8221;` `&#x201D;` `\u{201D}` `&amp;rdquo;` `&amp;#8221;` `&amp;#x201D;` | `"` |
| Left double quote | `&ldquo;` `&#8220;` `&#x201C;` `\u{201C}` `&amp;ldquo;` `&amp;#8220;` `&amp;#x201C;` | `"` |
| Em dash | `&mdash;` `&#8212;` `&#x2014;` `\u{2014}` `&amp;mdash;` `&amp;#8212;` `&amp;#x2014;` | `--` |
| En dash | `&ndash;` `&#8211;` `&#x2013;` `\u{2013}` `&amp;ndash;` `&amp;#8211;` `&amp;#x2013;` | `-` |
| Ellipsis | `&hellip;` `&#8230;` `&#x2026;` `\u{2026}` `&amp;hellip;` `&amp;#8230;` `&amp;#x2026;` | `...` |
| Non-breaking space | `&nbsp;` `&#160;` `&#xA0;` `\u{00A0}` `&amp;nbsp;` `&amp;#160;` `&amp;#xA0;` | ` ` |
| Double-encoded ampersand | `&amp;amp;` | `&amp;` |

Structural HTML entities (`&lt;`, `&amp;`, `&gt;`) are intentionally excluded
and are never altered.

Entity replacement applies to both title and content independently of the
fullwidth normalization settings. It runs before NFKC normalization so that
any fullwidth character encoded as an HTML entity (e.g., `&#xFF1B;` for ；)
is decoded to its Unicode code point first, and then NFKC converts it to the
ASCII halfwidth equivalent in the same pass.

## HTML Character Entity Decoding

Beyond the typographic entities above, the plugin also decodes any remaining
HTML character entities to their Unicode equivalents. This handles accented
and extended characters that feeds may leave encoded:

| Example entity | Encoding forms handled | Decoded to |
|---|---|---|
| e with acute | `&eacute;` `&#233;` `&#xE9;` | `é` |
| a with grave | `&agrave;` `&#224;` `&#xE0;` | `à` |
| Copyright | `&copy;` `&#169;` `&#xA9;` | `©` |
| Registered trademark | `&reg;` `&#174;` `&#xAE;` | `®` |
| Any other named/numeric entity | `&name;` `&#decimal;` `&#xhex;` | decoded Unicode character |

This step runs after typographic entity replacement. Structural entities
(`&lt;`, `&gt;`, `&amp;`) are always preserved. Unknown entity names are
left unchanged.

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
