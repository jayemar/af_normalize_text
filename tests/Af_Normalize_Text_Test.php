<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Af_Normalize_Text;

/**
 * Test suite for Af_Normalize_Text plugin
 *
 * Tests verify that the plugin correctly:
 * 1. Normalizes fullwidth alphabetic characters to halfwidth ASCII
 * 2. Normalizes fullwidth numeric characters to halfwidth ASCII
 * 3. Normalizes fullwidth punctuation via NFKC
 * 4. Normalizes titles at import time when enabled
 * 5. Normalizes content at import time when enabled
 * 6. Respects per-feature enable/disable settings
 * 7. Handles edge cases gracefully (null, empty, no fullwidth chars)
 */
class Af_Normalize_Text_Test extends TestCase {

    private $plugin;
    private $mockHost;

    protected function setUp(): void {
        $this->mockHost = $this->createMock(\PluginHost::class);

        $this->mockHost->expects($this->any())
            ->method('add_hook')
            ->willReturn(true);

        // Default: normalize_titles=true, normalize_content=false,
        // replace_typographic_entities=true
        $this->mockHost->expects($this->any())
            ->method('get')
            ->willReturnCallback(function($plugin, $key, $default) {
                if ($key === 'normalize_titles') return true;
                if ($key === 'normalize_content') return false;
                if ($key === 'replace_typographic_entities') return true;
                return $default;
            });

        $this->plugin = new Af_Normalize_Text();
        $this->plugin->init($this->mockHost);
    }

    // =====================================================================
    // NORMALIZE METHOD - DIRECT TESTS
    // =====================================================================

    /**
     * Test 1: Fullwidth lowercase alpha normalized to halfwidth
     */
    public function test_normalize_fullwidth_lowercase_alpha() {
        // ｈｅｌｌｏ -> hello
        $result = $this->plugin->normalize("ｈｅｌｌｏ");
        $this->assertEquals("hello", $result,
            'Fullwidth lowercase letters should become halfwidth ASCII');
    }

    /**
     * Test 2: Fullwidth uppercase alpha normalized to halfwidth
     */
    public function test_normalize_fullwidth_uppercase_alpha() {
        // Ｈｅａｄｌｉｎｅ -> Headline
        $result = $this->plugin->normalize("Ｈｅａｄｌｉｎｅ");
        $this->assertEquals("Headline", $result,
            'Fullwidth uppercase letters should become halfwidth ASCII');
    }

    /**
     * Test 3: Fullwidth digits normalized to halfwidth
     */
    public function test_normalize_fullwidth_digits() {
        // １２３ -> 123
        $result = $this->plugin->normalize("１２３");
        $this->assertEquals("123", $result,
            'Fullwidth digits should become halfwidth ASCII');
    }

    /**
     * Test 4: Fullwidth punctuation normalized via NFKC
     */
    public function test_normalize_fullwidth_punctuation() {
        // ！？：／ -> !?:/
        $result = $this->plugin->normalize("！？：／");
        $this->assertEquals("!?:/", $result,
            'Fullwidth punctuation should become halfwidth ASCII via NFKC');
    }

    /**
     * Test 5: Mixed fullwidth and regular text
     */
    public function test_normalize_mixed_fullwidth_and_ascii() {
        $result = $this->plugin->normalize("Hello ｗｏｒｌｄ from １２３");
        $this->assertEquals("Hello world from 123", $result,
            'Mixed text should have only fullwidth chars converted');
    }

    /**
     * Test 6: Real-world headline example
     */
    public function test_normalize_real_world_headline() {
        // Ｈｅｌｌｏ，Ｗｏｒｌｄ！ -> Hello,World!
        $result = $this->plugin->normalize("Ｈｅｌｌｏ，Ｗｏｒｌｄ！");
        $this->assertEquals("Hello,World!", $result,
            'Real-world headline should be fully normalized');
    }

    /**
     * Test 7: Empty string returns unchanged
     */
    public function test_normalize_empty_string_unchanged() {
        $result = $this->plugin->normalize("");
        $this->assertEquals("", $result,
            'Empty string should be returned unchanged');
    }

    /**
     * Test 8: Pure ASCII text returns unchanged
     */
    public function test_normalize_ascii_text_unchanged() {
        $input = "Hello, World! This is a test - 123 items.";
        $result = $this->plugin->normalize($input);
        $this->assertEquals($input, $result,
            'Pure ASCII text should be returned unchanged');
    }

    /**
     * Test 9: Non-fullwidth Unicode preserved (e.g., accented chars, CJK)
     */
    public function test_normalize_preserves_regular_unicode() {
        // Regular accented chars and CJK should not be mangled
        $input = "Resume: Ren\u{00E9}e visits \u{6771}\u{4EAC}";
        $result = $this->plugin->normalize($input);
        $this->assertStringContainsString("Ren\u{00E9}e", $result,
            'Accented characters should be preserved');
        $this->assertStringContainsString("\u{6771}\u{4EAC}", $result,
            'CJK characters should be preserved');
    }

    /**
     * Test 10: Full range - all fullwidth ASCII variants (U+FF01-U+FF5E)
     */
    public function test_normalize_full_fullwidth_ascii_range() {
        // ! through ~ in fullwidth (U+FF01 = !, U+FF5E = ~)
        $fullwidth = "\u{FF21}\u{FF22}\u{FF23}"; // ＡＢＣ
        $result = $this->plugin->normalize($fullwidth);
        $this->assertEquals("ABC", $result,
            'Fullwidth A-C should normalize to ASCII A-C');
    }

    // =====================================================================
    // HOOK_ARTICLE_FILTER - TITLE NORMALIZATION
    // =====================================================================

    /**
     * Test 11: Title normalized when normalize_titles is enabled
     */
    public function test_hook_normalizes_title_when_enabled() {
        $article = [
            'title' => "Ｎｅｗｓ ｆｒｏｍ Ｔｏｋｙｏ",
            'content' => 'Some content here.'
        ];

        $result = $this->plugin->hook_article_filter($article);

        $this->assertEquals("News from Tokyo", $result['title'],
            'Title should be normalized when normalize_titles is true');
    }

    /**
     * Test 12: Content untouched when normalize_content is disabled (default)
     */
    public function test_hook_leaves_content_unchanged_when_disabled() {
        $fullwidth_content = "Ｓｏｍｅ ｆｕｌｌｗｉｄｔｈ ｃｏｎｔｅｎｔ";
        $article = [
            'title' => 'Normal Title',
            'content' => $fullwidth_content
        ];

        $result = $this->plugin->hook_article_filter($article);

        $this->assertEquals($fullwidth_content, $result['content'],
            'Content should be unchanged when normalize_content is false');
    }

    /**
     * Test 13: Title untouched when normalize_titles is disabled
     */
    public function test_hook_leaves_title_unchanged_when_disabled() {
        $plugin = $this->createPluginWithSettings(['normalize_titles' => false, 'normalize_content' => false]);

        $fullwidth_title = "Ｎｅｗｓ ｆｒｏｍ Ｔｏｋｙｏ";
        $article = [
            'title' => $fullwidth_title,
            'content' => 'Some content.'
        ];

        $result = $plugin->hook_article_filter($article);

        $this->assertEquals($fullwidth_title, $result['title'],
            'Title should be unchanged when normalize_titles is false');
    }

    /**
     * Test 14: Content normalized when normalize_content is enabled
     */
    public function test_hook_normalizes_content_when_enabled() {
        $plugin = $this->createPluginWithSettings(['normalize_titles' => false, 'normalize_content' => true]);

        $article = [
            'title' => 'Normal Title',
            'content' => "<p>Ｓｏｍｅ ｆｕｌｌｗｉｄｔｈ ｃｏｎｔｅｎｔ</p>"
        ];

        $result = $plugin->hook_article_filter($article);

        $this->assertStringContainsString("Some fullwidth content", $result['content'],
            'Content should be normalized when normalize_content is true');
    }

    /**
     * Test 15: Article with no fullwidth chars unchanged
     */
    public function test_hook_unchanged_when_no_fullwidth_chars() {
        $article = [
            'title' => 'Normal ASCII Title',
            'content' => '<p>Normal ASCII content.</p>'
        ];

        $result = $this->plugin->hook_article_filter($article);

        $this->assertEquals($article['title'], $result['title'],
            'Plain ASCII title should not change');
        $this->assertEquals($article['content'], $result['content'],
            'Plain ASCII content should not change');
    }

    /**
     * Test 16: Missing title key handled gracefully
     */
    public function test_hook_handles_missing_title_key() {
        $article = [
            'content' => '<p>Some content.</p>'
        ];

        // Should not throw or error
        $result = $this->plugin->hook_article_filter($article);

        $this->assertArrayNotHasKey('title', $result,
            'Missing title key should remain absent (not added)');
    }

    /**
     * Test 17: Missing content key handled gracefully
     */
    public function test_hook_handles_missing_content_key() {
        $plugin = $this->createPluginWithSettings(['normalize_titles' => true, 'normalize_content' => true]);

        $article = [
            'title' => 'Some Title'
        ];

        $result = $plugin->hook_article_filter($article);

        $this->assertEquals('Some Title', $result['title'],
            'Title should be returned unchanged when content key is absent');
    }

    /**
     * Test 18: Empty title handled gracefully
     */
    public function test_hook_handles_empty_title() {
        $article = [
            'title' => '',
            'content' => '<p>Content here.</p>'
        ];

        $result = $this->plugin->hook_article_filter($article);

        $this->assertEquals('', $result['title'],
            'Empty title should remain empty');
    }

    /**
     * Test 19: Both title and content normalized when both enabled
     */
    public function test_hook_normalizes_both_when_both_enabled() {
        $plugin = $this->createPluginWithSettings(['normalize_titles' => true, 'normalize_content' => true]);

        $article = [
            'title' => "Ｈｅａｄｌｉｎｅ Ｔｅｓｔ",
            'content' => "<p>Ｂｏｄｙ ｔｅｘｔ ｈｅｒｅ．</p>"
        ];

        $result = $plugin->hook_article_filter($article);

        $this->assertEquals("Headline Test", $result['title'],
            'Title should be normalized');
        $this->assertStringContainsString("Body text here.", $result['content'],
            'Content should be normalized');
    }

    /**
     * Test 20: Return value is array (structure preserved)
     */
    public function test_hook_preserves_article_structure() {
        $article = [
            'title' => "Ｔｅｓｔ Ａｒｔｉｃｌｅ",
            'content' => '<p>Content.</p>',
            'link' => 'https://example.com/article',
            'author' => 'Test Author',
            'feed_id' => 42
        ];

        $result = $this->plugin->hook_article_filter($article);

        $this->assertEquals('https://example.com/article', $result['link'],
            'link field should be preserved');
        $this->assertEquals('Test Author', $result['author'],
            'author field should be preserved');
        $this->assertEquals(42, $result['feed_id'],
            'feed_id field should be preserved');
    }

    // =====================================================================
    // HELPER
    // =====================================================================

    // =====================================================================
    // REPLACE_TYPOGRAPHIC - DIRECT TESTS
    // =====================================================================

    /**
     * Test 21: &rsquo; replaced with ASCII apostrophe
     */
    public function test_replace_typographic_rsquo_entity() {
        $result = $this->plugin->replace_typographic("it&rsquo;s");
        $this->assertEquals("it's", $result,
            '&rsquo; should become ASCII apostrophe');
    }

    /**
     * Test 22: &lsquo; replaced with ASCII apostrophe
     */
    public function test_replace_typographic_lsquo_entity() {
        $result = $this->plugin->replace_typographic("&lsquo;quoted&rsquo;");
        $this->assertEquals("'quoted'", $result,
            '&lsquo; and &rsquo; should both become ASCII apostrophes');
    }

    /**
     * Test 23: &ldquo; and &rdquo; replaced with ASCII double quotes
     */
    public function test_replace_typographic_dquote_entities() {
        $result = $this->plugin->replace_typographic("&ldquo;hello&rdquo;");
        $this->assertEquals('"hello"', $result,
            '&ldquo; and &rdquo; should become ASCII double quotes');
    }

    /**
     * Test 24: &mdash; replaced with double hyphen
     */
    public function test_replace_typographic_mdash_entity() {
        $result = $this->plugin->replace_typographic("one&mdash;two");
        $this->assertEquals("one--two", $result,
            '&mdash; should become --');
    }

    /**
     * Test 25: &ndash; replaced with single hyphen
     */
    public function test_replace_typographic_ndash_entity() {
        $result = $this->plugin->replace_typographic("pp. 10&ndash;20");
        $this->assertEquals("pp. 10-20", $result,
            '&ndash; should become -');
    }

    /**
     * Test 26: &hellip; replaced with three dots
     */
    public function test_replace_typographic_hellip_entity() {
        $result = $this->plugin->replace_typographic("wait&hellip;");
        $this->assertEquals("wait...", $result,
            '&hellip; should become ...');
    }

    /**
     * Test 27: &nbsp; replaced with regular space
     */
    public function test_replace_typographic_nbsp_entity() {
        $result = $this->plugin->replace_typographic("hello&nbsp;world");
        $this->assertEquals("hello world", $result,
            '&nbsp; should become a regular space');
    }

    /**
     * Test 28: Decimal numeric entity &#8217; replaced
     */
    public function test_replace_typographic_numeric_decimal() {
        $result = $this->plugin->replace_typographic("it&#8217;s");
        $this->assertEquals("it's", $result,
            '&#8217; (decimal rsquo) should become ASCII apostrophe');
    }

    /**
     * Test 29: Hex numeric entity &#x2019; replaced
     */
    public function test_replace_typographic_numeric_hex() {
        $result = $this->plugin->replace_typographic("it&#x2019;s");
        $this->assertEquals("it's", $result,
            '&#x2019; (hex rsquo) should become ASCII apostrophe');
    }

    /**
     * Test 30: Unicode U+2019 RIGHT SINGLE QUOTATION MARK replaced
     */
    public function test_replace_typographic_unicode_rsquo() {
        $result = $this->plugin->replace_typographic("it\u{2019}s");
        $this->assertEquals("it's", $result,
            'Unicode U+2019 should become ASCII apostrophe');
    }

    /**
     * Test 31: Unicode U+201C LEFT DOUBLE QUOTATION MARK replaced
     */
    public function test_replace_typographic_unicode_ldquo() {
        $result = $this->plugin->replace_typographic("\u{201C}hello\u{201D}");
        $this->assertEquals('"hello"', $result,
            'Unicode U+201C/U+201D should become ASCII double quotes');
    }

    /**
     * Test 32: Entity replaced in title via hook
     */
    public function test_replace_typographic_in_title_via_hook() {
        $article = [
            'title' => "What&rsquo;s New",
            'content' => '<p>Content.</p>'
        ];

        $result = $this->plugin->hook_article_filter($article);

        $this->assertEquals("What's New", $result['title'],
            '&rsquo; in title should be replaced via hook');
    }

    /**
     * Test 33: Entity replaced in content via hook (independent of normalize_content)
     */
    public function test_replace_typographic_in_content_via_hook() {
        // normalize_content is false by default, but entity replacement should
        // still apply to content
        $article = [
            'title' => 'Normal Title',
            'content' => "<p>It&rsquo;s a &ldquo;test&rdquo;.</p>"
        ];

        $result = $this->plugin->hook_article_filter($article);

        $this->assertStringContainsString("It's", $result['content'],
            '&rsquo; in content should be replaced even when normalize_content is off');
        $this->assertStringContainsString('"test"', $result['content'],
            '&ldquo;/&rdquo; in content should be replaced');
    }

    /**
     * Test 34: Entity replacement disabled leaves entities unchanged
     */
    public function test_replace_typographic_disabled_leaves_entities_unchanged() {
        $plugin = $this->createPluginWithSettings([
            'normalize_titles'           => true,
            'normalize_content'          => false,
            'replace_typographic_entities' => false,
        ]);

        $article = [
            'title' => "What&rsquo;s New",
            'content' => "<p>It&rsquo;s fine.</p>"
        ];

        $result = $plugin->hook_article_filter($article);

        $this->assertEquals("What&rsquo;s New", $result['title'],
            '&rsquo; should be untouched when replace_typographic_entities is false');
        $this->assertStringContainsString("&rsquo;", $result['content'],
            'Content entities should be untouched when feature is disabled');
    }

    /**
     * Test 35: HTML structural entities &lt; &amp; &gt; are preserved
     */
    public function test_html_structural_entities_preserved() {
        $input = "<p>a &lt; b &amp;&amp; c &gt; d</p>";
        $result = $this->plugin->replace_typographic($input);
        $this->assertEquals($input, $result,
            'HTML structural entities must not be altered');
    }

    // =====================================================================
    // DOUBLE-ENCODED ENTITY TESTS
    // TT-RSS/SimplePie can double-encode entities from feed content,
    // storing &rsquo; as &amp;rsquo; in the database.
    // =====================================================================

    /**
     * Test 36: Double-encoded &amp;rsquo; replaced with ASCII apostrophe
     */
    public function test_replace_double_encoded_rsquo() {
        $result = $this->plugin->replace_typographic("North City&amp;rsquo;s eateries");
        $this->assertEquals("North City's eateries", $result,
            '&amp;rsquo; (double-encoded) should become ASCII apostrophe');
    }

    /**
     * Test 37: Double-encoded &amp;lsquo; replaced with ASCII apostrophe
     */
    public function test_replace_double_encoded_lsquo() {
        $result = $this->plugin->replace_typographic("&amp;lsquo;quoted&amp;rsquo;");
        $this->assertEquals("'quoted'", $result,
            '&amp;lsquo; (double-encoded) should become ASCII apostrophe');
    }

    /**
     * Test 38: Double-encoded &amp;rdquo; and &amp;ldquo; replaced with ASCII quotes
     */
    public function test_replace_double_encoded_double_quotes() {
        $result = $this->plugin->replace_typographic("&amp;ldquo;hello&amp;rdquo;");
        $this->assertEquals('"hello"', $result,
            '&amp;ldquo;/&amp;rdquo; (double-encoded) should become ASCII double quotes');
    }

    /**
     * Test 39: Double-encoded &amp;mdash; replaced with double hyphen
     */
    public function test_replace_double_encoded_mdash() {
        $result = $this->plugin->replace_typographic("one&amp;mdash;two");
        $this->assertEquals("one--two", $result,
            '&amp;mdash; (double-encoded) should become --');
    }

    /**
     * Test 40: Double-encoded &amp;ndash; replaced with single hyphen
     */
    public function test_replace_double_encoded_ndash() {
        $result = $this->plugin->replace_typographic("pp. 10&amp;ndash;20");
        $this->assertEquals("pp. 10-20", $result,
            '&amp;ndash; (double-encoded) should become -');
    }

    /**
     * Test 41: Double-encoded &amp;hellip; replaced with three dots
     */
    public function test_replace_double_encoded_hellip() {
        $result = $this->plugin->replace_typographic("wait&amp;hellip;");
        $this->assertEquals("wait...", $result,
            '&amp;hellip; (double-encoded) should become ...');
    }

    /**
     * Test 42: Double-encoded &amp;nbsp; replaced with regular space
     */
    public function test_replace_double_encoded_nbsp() {
        $result = $this->plugin->replace_typographic("hello&amp;nbsp;world");
        $this->assertEquals("hello world", $result,
            '&amp;nbsp; (double-encoded) should become a regular space');
    }

    /**
     * Test 43: Double-encoded numeric entity &amp;#8217; replaced
     */
    public function test_replace_double_encoded_numeric_decimal() {
        $result = $this->plugin->replace_typographic("it&amp;#8217;s");
        $this->assertEquals("it's", $result,
            '&amp;#8217; (double-encoded decimal rsquo) should become ASCII apostrophe');
    }

    /**
     * Test 44: Double-encoded hex entity &amp;#x2019; replaced
     */
    public function test_replace_double_encoded_numeric_hex() {
        $result = $this->plugin->replace_typographic("it&amp;#x2019;s");
        $this->assertEquals("it's", $result,
            '&amp;#x2019; (double-encoded hex rsquo) should become ASCII apostrophe');
    }

    /**
     * Test 45: Double-encoded entity replaced in content via hook
     * Reproduces the sandiegoreader.com issue where &rsquo; is stored as &amp;rsquo;
     */
    public function test_replace_double_encoded_in_content_via_hook() {
        $article = [
            'title' => 'Normal Title',
            'content' => "<p>North City&amp;rsquo;s eateries for sips and bites.</p>"
        ];

        $result = $this->plugin->hook_article_filter($article);

        $this->assertStringContainsString("North City's eateries", $result['content'],
            '&amp;rsquo; in content should be replaced via hook');
        $this->assertStringNotContainsString('&amp;rsquo;', $result['content'],
            'No double-encoded rsquo should remain in content');
        $this->assertStringNotContainsString('&rsquo;', $result['content'],
            'No rsquo entity should remain in content');
    }

    /**
     * Test 46: Double-encoded &amp;amp; replaced with &amp;
     * Reproduces the KPBS figcaption case where &amp; is stored as &amp;amp;
     */
    public function test_replace_double_encoded_amp() {
        $result = $this->plugin->replace_typographic(
            "Convention &amp;amp; Entertainment Center"
        );
        $this->assertEquals(
            "Convention &amp; Entertainment Center",
            $result,
            '&amp;amp; (double-encoded) should become &amp;'
        );
    }

    /**
     * Test 47: Double-encoded &amp;amp; in figcaption content via hook
     * Reproduces the KPBS article where figcaption shows &amp; literally
     */
    public function test_replace_double_encoded_amp_in_figcaption_via_hook() {
        $article = [
            'title' => 'Normal Title',
            'content' => '<figure><figcaption>Fresno Convention &amp;amp; '
                       . 'Entertainment Center</figcaption></figure>',
        ];

        $result = $this->plugin->hook_article_filter($article);

        $this->assertStringContainsString(
            'Convention &amp; Entertainment',
            $result['content'],
            '&amp;amp; in figcaption should become &amp; via hook'
        );
        $this->assertStringNotContainsString(
            '&amp;amp;',
            $result['content'],
            'No double-encoded amp should remain in content'
        );
    }

    // =====================================================================
    // HELPER
    // =====================================================================

    /**
     * Create a plugin instance with specific settings
     */
    private function createPluginWithSettings(array $settings): Af_Normalize_Text {
        $host = $this->createMock(\PluginHost::class);

        $host->expects($this->any())
            ->method('add_hook')
            ->willReturn(true);

        $host->expects($this->any())
            ->method('get')
            ->willReturnCallback(function($plugin, $key, $default) use ($settings) {
                if (array_key_exists($key, $settings)) return $settings[$key];
                // Default: replace_typographic_entities on
                if ($key === 'replace_typographic_entities') return true;
                return $default;
            });

        $plugin = new Af_Normalize_Text();
        $plugin->init($host);
        return $plugin;
    }
}
