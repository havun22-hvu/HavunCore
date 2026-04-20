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
}
