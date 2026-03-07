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

        echo __('Settings saved.');
    }

    // =====================================================================
    // MAIN ARTICLE FILTER HOOK
    // =====================================================================

    public function hook_article_filter($article) {
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

        return $article;
    }

    // =====================================================================
    // NORMALIZATION
    // =====================================================================

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
