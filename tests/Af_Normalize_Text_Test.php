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

        // Default: normalize_titles=true, normalize_content=false
        $this->mockHost->expects($this->any())
            ->method('get')
            ->willReturnCallback(function($plugin, $key, $default) {
                if ($key === 'normalize_titles') return true;
                if ($key === 'normalize_content') return false;
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
                return $settings[$key] ?? $default;
            });

        $plugin = new Af_Normalize_Text();
        $plugin->init($host);
        return $plugin;
    }
}
