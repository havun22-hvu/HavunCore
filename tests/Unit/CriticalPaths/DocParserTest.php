<?php

namespace Tests\Unit\CriticalPaths;

use App\Services\CriticalPaths\DocParser;
use Tests\TestCase;

class DocParserTest extends TestCase
{
    public function test_parses_single_path_with_test_references(): void
    {
        $md = <<<'MD'
## Pad 1 — Vault

**Tests die dit afdekken:**

- `tests/Feature/VaultControllerTest.php`
- `tests/Unit/Vault/VaultServiceTest.php`
MD;

        $result = (new DocParser)->parse($md);

        $this->assertCount(1, $result);
        $this->assertSame('Vault', $result[0]['name']);
        $this->assertSame([
            'tests/Feature/VaultControllerTest.php',
            'tests/Unit/Vault/VaultServiceTest.php',
        ], $result[0]['references']);
    }

    public function test_ignores_parenthetical_commentary_in_references(): void
    {
        $md = <<<'MD'
## Pad 1 — Vault

**Tests die dit afdekken:**

- `tests/Feature/VaultControllerTest.php` (happy path + auth)
- `tests/Unit/Vault/EncryptionTest.php` (only encrypt/decrypt cycle)
MD;

        $result = (new DocParser)->parse($md);

        $this->assertSame([
            'tests/Feature/VaultControllerTest.php',
            'tests/Unit/Vault/EncryptionTest.php',
        ], $result[0]['references']);
    }

    public function test_handles_glob_reference_unchanged_in_parsed_output(): void
    {
        $md = <<<'MD'
## Pad 1 — Vault

**Tests die dit afdekken:**

- `tests/Unit/Vault/*.php`
MD;

        $result = (new DocParser)->parse($md);

        $this->assertSame(['tests/Unit/Vault/*.php'], $result[0]['references']);
    }

    public function test_missing_references_section_yields_empty_list(): void
    {
        $md = <<<'MD'
## Pad 1 — Vault

Some description but no tests section.

**Mutation-score target:** 90 %.
MD;

        $result = (new DocParser)->parse($md);

        $this->assertCount(1, $result);
        $this->assertSame([], $result[0]['references']);
    }

    public function test_multiple_paths_parsed_with_correct_names(): void
    {
        $md = <<<'MD'
## Pad 1 — Vault

**Tests die dit afdekken:**

- `tests/Feature/VaultTest.php`

## Pad 2 — AI Proxy

**Tests die dit afdekken:**

- `tests/Feature/AiChatTest.php`
- `tests/Unit/AIProxyServiceTest.php`
MD;

        $result = (new DocParser)->parse($md);

        $this->assertCount(2, $result);
        $this->assertSame('Vault', $result[0]['name']);
        $this->assertSame('AI Proxy', $result[1]['name']);
        $this->assertCount(1, $result[0]['references']);
        $this->assertCount(2, $result[1]['references']);
    }

    public function test_references_section_stops_at_next_bold_heading(): void
    {
        $md = <<<'MD'
## Pad 1 — Vault

**Tests die dit afdekken:**

- `tests/Feature/A.php`
- `tests/Feature/B.php`

**Mutation-score target:** 90 %.

- `tests/Feature/ShouldNotBeIncluded.php`
MD;

        $result = (new DocParser)->parse($md);

        $this->assertSame([
            'tests/Feature/A.php',
            'tests/Feature/B.php',
        ], $result[0]['references']);
    }

    public function test_parse_file_returns_empty_for_missing_file(): void
    {
        $this->assertSame([], (new DocParser)->parseFile('/nonexistent/critical-paths.md'));
    }

    public function test_picks_up_typescript_and_javascript_references(): void
    {
        // Non-Laravel projects (React Native / Jest) reference `.ts` / `.tsx` /
        // `.js` / `.jsx` test-files — the parser must not limit itself to `.php`.
        $md = <<<'MD'
## Pad 1 — Mobile

**Tests die dit afdekken:**

- `src/services/__tests__/storage.test.ts`
- `src/components/__tests__/Button.test.tsx`
- `src/legacy/helpers.test.js`
- `src/utils/old-util.test.jsx`
MD;

        $result = (new DocParser)->parse($md);

        $this->assertSame([
            'src/services/__tests__/storage.test.ts',
            'src/components/__tests__/Button.test.tsx',
            'src/legacy/helpers.test.js',
            'src/utils/old-util.test.jsx',
        ], $result[0]['references']);
    }

    /**
     * Content before the first `## Pad` heading must be silently dropped.
     * If the parser defaults `inTestsSection` to `true` (mutation), preamble
     * bullet-references would leak into an empty path.
     */
    public function test_preamble_before_first_pad_is_ignored(): void
    {
        $md = <<<'MD'
Some intro paragraph.

**Tests die dit afdekken:**

- `tests/Feature/ShouldNotLeak.php`

## Pad 1 — Real

**Tests die dit afdekken:**

- `tests/Feature/Real.php`
MD;

        $result = (new DocParser)->parse($md);

        $this->assertCount(1, $result);
        $this->assertSame('Real', $result[0]['name']);
        $this->assertSame(['tests/Feature/Real.php'], $result[0]['references']);
    }

    /**
     * After a new `## Pad` heading, `inTestsSection` MUST reset to false.
     * Otherwise bullets directly under the heading (before `**Tests:**`)
     * would be captured as references.
     */
    public function test_new_pad_resets_tests_section_flag(): void
    {
        $md = <<<'MD'
## Pad 1 — First

**Tests die dit afdekken:**

- `tests/Feature/First.php`

## Pad 2 — Second

- `tests/Feature/ShouldNotLeakFromSecondPreamble.php`

**Tests die dit afdekken:**

- `tests/Feature/Second.php`
MD;

        $result = (new DocParser)->parse($md);

        $this->assertCount(2, $result);
        $this->assertSame(['tests/Feature/First.php'], $result[0]['references']);
        // Only the test listed after the `**Tests:**` header of Pad 2 survives.
        $this->assertSame(['tests/Feature/Second.php'], $result[1]['references']);
    }

    /**
     * The heading name is trimmed — if the trim() is removed (mutation),
     * trailing whitespace from the regex capture would survive.
     */
    public function test_pad_name_is_trimmed_of_trailing_whitespace(): void
    {
        // Trailing tabs/spaces between the name and line-end must be stripped.
        $md = "## Pad 1 — Vault   \t\n\n**Tests die dit afdekken:**\n\n- `tests/Feature/V.php`\n";

        $result = (new DocParser)->parse($md);

        $this->assertSame('Vault', $result[0]['name']);
    }

    /**
     * The `**Tests:**` header regex uses the `^` anchor. Without it (mutation),
     * a `**Tests...**` fragment appearing mid-line (e.g. inside a bullet)
     * would wrongly flip `inTestsSection` on.
     */
    public function test_tests_header_must_anchor_at_line_start(): void
    {
        $md = <<<'MD'
## Pad 1 — Anchor

- note: **Tests die dit afdekken:** is normally on its own line

- `tests/Feature/ShouldNotLeak.php`

Paragraph end.
MD;

        $result = (new DocParser)->parse($md);

        // Bullet reference must NOT have been captured: the `**Tests:**`
        // fragment was not at line-start, so inTestsSection stays false.
        $this->assertSame([], $result[0]['references']);
    }

    /**
     * References use the `/u` (unicode) regex flag. Without it, a backtick
     * containing a multi-byte char before `.php` would fail to match on
     * non-unicode engines. We use an accented char in the path to verify.
     */
    public function test_reference_regex_handles_unicode_path(): void
    {
        $md = "## Pad 1 — Unicode\n\n**Tests die dit afdekken:**\n\n- `tests/Feature/Café.php`\n";

        $result = (new DocParser)->parse($md);

        $this->assertSame(['tests/Feature/Café.php'], $result[0]['references']);
    }

    /**
     * When `$current === null` (i.e. before the first `## Pad` heading),
     * the loop continues to the next line. A mutation that replaces
     * `continue` with `break` would abort processing and yield no paths.
     */
    public function test_lines_before_first_pad_do_not_abort_parsing(): void
    {
        $md = <<<'MD'
Line 1 of preamble.
Line 2 of preamble.
Line 3 of preamble.

## Pad 1 — Reached

**Tests die dit afdekken:**

- `tests/Feature/Reached.php`
MD;

        $result = (new DocParser)->parse($md);

        // If `break` replaced `continue`, we'd never reach the `## Pad` heading
        // and this assertion would fail.
        $this->assertCount(1, $result);
        $this->assertSame('Reached', $result[0]['name']);
    }
}
