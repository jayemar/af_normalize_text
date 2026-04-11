<?php
/**
 * af_normalize_text - Normalize fullwidth Unicode characters in RSS feeds
 *
 * Articles from certain feeds (East Asian sources, Substack, or publishers
 * using "stylized" text) may contain fullwidth Unicode characters (U+FF01-U+FF60
 * range) such as ｈｅｌｌｏ, Ｈｅａｄｌｉｎｅ, or １２３. These can display
 * poorly in feed readers - appearing too wide, misaligned, or breaking layout.
 *
 * This plugin normalizes fullwidth characters to their standard ASCII equivalents
 * at article import time, so all clients benefit automatically.
 *
 * Normalization uses NFKC (Unicode Compatibility Decomposition + Canonical
 * Composition) via the PHP intl extension, with mb_convert_kana() as a fallback.
 *
 * Installation:
 * 1. Copy this directory to plugins.local/af_normalize_text/
 * 2. Enable the plugin in Preferences -> Plugins
 * 3. Configure in Preferences -> Feeds -> Text Normalization
 *
 * Version: 1.0
 * Author: jayemar
 */
class Af_Normalize_Text extends Plugin {

    private $host;

    // HTML entity strings -> ASCII equivalents
    // Only typographic/punctuation entities; plain structural entities (&lt;, &gt;,
    // &amp;) are excluded, but &amp;amp; (double-encoded ampersand from
    // TT-RSS/SimplePie) is handled by converting it back to &amp;.
    // Double-encoded variants (&amp;rsquo; etc.) are listed first to avoid partial
    // matches when TT-RSS/SimplePie stores entities as &amp;rsquo; instead of &rsquo;.
    private static $ENTITY_MAP = [
        '&amp;amp;'    => '&amp;',
        '&amp;rsquo;'  => "'",
        '&amp;#8217;'  => "'",
        '&amp;#x2019;' => "'",
        '&amp;lsquo;'  => "'",
        '&amp;#8216;'  => "'",
        '&amp;#x2018;' => "'",
        '&amp;rdquo;'  => '"',
        '&amp;#8221;'  => '"',
        '&amp;#x201D;' => '"',
        '&amp;ldquo;'  => '"',
        '&amp;#8220;'  => '"',
        '&amp;#x201C;' => '"',
        '&amp;mdash;'  => '--',
        '&amp;#8212;'  => '--',
        '&amp;#x2014;' => '--',
        '&amp;ndash;'  => '-',
        '&amp;#8211;'  => '-',
        '&amp;#x2013;' => '-',
        '&amp;hellip;' => '...',
        '&amp;#8230;'  => '...',
        '&amp;#x2026;' => '...',
        '&amp;nbsp;'   => ' ',
        '&amp;#160;'   => ' ',
        '&amp;#xA0;'   => ' ',
        '&rsquo;'  => "'",
        '&#8217;'  => "'",
        '&#x2019;' => "'",
        '&lsquo;'  => "'",
        '&#8216;'  => "'",
        '&#x2018;' => "'",
        '&rdquo;'  => '"',
        '&#8221;'  => '"',
        '&#x201D;' => '"',
        '&ldquo;'  => '"',
        '&#8220;'  => '"',
        '&#x201C;' => '"',
        '&mdash;'  => '--',
        '&#8212;'  => '--',
        '&#x2014;' => '--',
        '&ndash;'  => '-',
        '&#8211;'  => '-',
        '&#x2013;' => '-',
        '&hellip;' => '...',
        '&#8230;'  => '...',
        '&#x2026;' => '...',
        '&nbsp;'   => ' ',
        '&#160;'   => ' ',
        '&#xA0;'   => ' ',
    ];

    // Unicode typographic characters -> ASCII equivalents
    // Handles entities that have already been decoded to Unicode code points.
    private static $UNICODE_MAP = [
        "\u{2019}" => "'",
        "\u{2018}" => "'",
        "\u{201D}" => '"',
        "\u{201C}" => '"',
        "\u{2014}" => '--',
        "\u{2013}" => '-',
        "\u{2026}" => '...',
        "\u{00A0}" => ' ',
    ];

    public function about() {
        return array(
            1.0,
            "Normalize fullwidth Unicode characters in article titles and content",
            "jayemar"
        );
    }

    public function init($host) {
        $this->host = $host;
        $host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
        $host->add_hook($host::HOOK_PREFS_TAB, $this);
    }

    public function api_version() {
        return 2;
    }

    // =====================================================================
    // CONFIGURATION UI
    // =====================================================================

    public function hook_prefs_tab($args) {
        if ($args != "prefFeeds") return;

        $normalize_titles = $this->host->get($this, "normalize_titles", true);
        $normalize_content = $this->host->get($this, "normalize_content", false);
        $replace_typographic_entities = $this->host->get($this, "replace_typographic_entities", true);
        ?>
        <div dojoType="dijit.layout.AccordionPane"
            title="<i class='material-icons'>text_fields</i> <?= __('Text Normalization') ?>">

            <form dojoType="dijit.form.Form">

                <?= \Controls\pluginhandler_tags($this, "save") ?>

                <script type="dojo/method" event="onSubmit" args="evt">
                    evt.preventDefault();
                    if (this.validate()) {
                        Notify.progress('Saving data...', true);
                        xhr.post("backend.php", this.getValues(), (reply) => {
                            Notify.info(reply);
                        });
                    }
                </script>

                <fieldset>
                    <legend><?= __('Fullwidth Character Normalization') ?></legend>
                    <p class="help-text" style="color: #666;">
                        <?= __('Converts fullwidth Unicode characters (e.g., ｈｅｌｌｏ, Ｈｅａｄｌｉｎｅ, １２３) to standard ASCII equivalents at import time.') ?>
                    </p>
                    <label class="checkbox">
                        <input dojoType="dijit.form.CheckBox" type="checkbox" name="normalize_titles" value="1"
                            <?= $normalize_titles ? 'checked' : '' ?>>
                        <?= __('Normalize titles') ?>
                    </label>
                    <p class="help-text" style="margin-left: 24px; color: #666;">
                        <?= __('Remove fullwidth characters from article titles (recommended)') ?>
                    </p>
                    <label class="checkbox">
                        <input dojoType="dijit.form.CheckBox" type="checkbox" name="normalize_content" value="1"
                            <?= $normalize_content ? 'checked' : '' ?>>
                        <?= __('Normalize content') ?>
                    </label>
                    <p class="help-text" style="margin-left: 24px; color: #666;">
                        <?= __('Remove fullwidth characters from article content (disabled by default - may affect intentional use of fullwidth in East Asian text)') ?>
                    </p>
                </fieldset>

                <fieldset>
                    <legend><?= __('Typographic Entity Replacement') ?></legend>
                    <p class="help-text" style="color: #666;">
                        <?= __('Replaces typographic HTML entities and Unicode characters with ASCII equivalents (e.g., &amp;rsquo; or \u2019 \u2192 \', &amp;mdash; \u2192 --, &amp;hellip; \u2192 ...).') ?>
                    </p>
                    <label class="checkbox">
                        <input dojoType="dijit.form.CheckBox" type="checkbox"
                            name="replace_typographic_entities" value="1"
                            <?= $replace_typographic_entities ? 'checked' : '' ?>>
                        <?= __('Replace typographic entities with ASCII') ?>
                    </label>
                    <p class="help-text" style="margin-left: 24px; color: #666;">
                        <?= __('Applies to titles and content. Structural HTML entities (&amp;lt;, &amp;amp;, &amp;gt;) are never altered.') ?>
                    </p>
                </fieldset>

                <hr>

                <?= \Controls\submit_tag(__("Save")) ?>
            </form>
        </div>
        <?php
    }

    public function csrf_ignore($method) {
        return $method === 'save';
    }

    public function save() {
        $normalize_titles = ($_POST['normalize_titles'] ?? '') === '1';
        $this->host->set($this, "normalize_titles", $normalize_titles);

        $normalize_content = ($_POST['normalize_content'] ?? '') === '1';
        $this->host->set($this, "normalize_content", $normalize_content);

        $replace_typographic_entities = ($_POST['replace_typographic_entities'] ?? '') === '1';
        $this->host->set($this, "replace_typographic_entities", $replace_typographic_entities);

        echo __('Settings saved.');
    }

    // =====================================================================
    // MAIN ARTICLE FILTER HOOK
    // =====================================================================

    public function hook_article_filter($article) {
        $replace_entities = $this->host->get($this, "replace_typographic_entities", true);

        if ($this->host->get($this, "normalize_titles", true)) {
            $original = $article['title'] ?? '';
            if (!empty($original)) {
                $normalized = $this->normalize($original);
                if ($normalized !== $original) {
                    $article['title'] = $normalized;
                    Debug::log("af_normalize_text: Normalized title: $normalized",
                        Debug::LOG_VERBOSE);
                }
            }
        }

        if ($this->host->get($this, "normalize_content", false)) {
            $original = $article['content'] ?? '';
            if (!empty($original)) {
                $normalized = $this->normalize($original);
                if ($normalized !== $original) {
                    $article['content'] = $normalized;
                    Debug::log("af_normalize_text: Normalized content for: " .
                        ($article['title'] ?? 'unknown'), Debug::LOG_VERBOSE);
                }
            }
        }

        if ($replace_entities) {
            $title = $article['title'] ?? '';
            if (!empty($title)) {
                $replaced = $this->replace_typographic($title);
                if ($replaced !== $title) {
                    $article['title'] = $replaced;
                    Debug::log("af_normalize_text: Replaced entities in title: $replaced",
                        Debug::LOG_VERBOSE);
                }
            }

            $content = $article['content'] ?? '';
            if (!empty($content)) {
                $replaced = $this->replace_typographic($content);
                if ($replaced !== $content) {
                    $article['content'] = $replaced;
                    Debug::log("af_normalize_text: Replaced entities in content for: " .
                        ($article['title'] ?? 'unknown'), Debug::LOG_VERBOSE);
                }
            }
        }

        return $article;
    }

    // =====================================================================
    // NORMALIZATION
    // =====================================================================

    /**
     * Replace typographic HTML entities and Unicode characters with ASCII.
     *
     * Handles both named/numeric HTML entities (e.g., &rsquo;, &#8217;, &#x2019;)
     * and their Unicode code point equivalents (e.g., U+2019). Structural HTML
     * entities (&lt;, &gt;, &amp;) are intentionally excluded.
     *
     * @param string $str Input string possibly containing typographic entities
     * @return string String with typographic entities replaced by ASCII equivalents
     */
    public function replace_typographic(string $str): string {
        if (empty($str)) {
            return $str;
        }

        $str = str_replace(
            array_keys(self::$ENTITY_MAP),
            array_values(self::$ENTITY_MAP),
            $str
        );

        $str = str_replace(
            array_keys(self::$UNICODE_MAP),
            array_values(self::$UNICODE_MAP),
            $str
        );

        return $str;
    }

    /**
     * Normalize fullwidth Unicode characters to their ASCII equivalents.
     *
     * Uses NFKC normalization (via PHP intl extension) as the primary method.
     * NFKC converts fullwidth characters and other compatibility variants
     * (e.g., ligatures, fractions) to their standard forms.
     *
     * Falls back to mb_convert_kana() if the intl extension is unavailable.
     * The 'rns' flags convert:
     *   r - fullwidth alphabetic characters to halfwidth
     *   n - fullwidth numeric characters to halfwidth
     *   s - fullwidth space (U+3000) to halfwidth space
     *
     * @param string $str Input string possibly containing fullwidth characters
     * @return string String with fullwidth characters replaced by ASCII equivalents
     */
    public function normalize(string $str): string {
        if (empty($str)) {
            return $str;
        }

        if (class_exists('Normalizer')) {
            $result = \Normalizer::normalize($str, \Normalizer::FORM_KC);
            return ($result !== false) ? $result : $str;
        }

        if (function_exists('mb_convert_kana')) {
            return mb_convert_kana($str, 'rns');
        }

        return $str;
    }
}
