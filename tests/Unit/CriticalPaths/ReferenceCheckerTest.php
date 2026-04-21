<?php

namespace Tests\Unit\CriticalPaths;

use App\Services\CriticalPaths\ReferenceChecker;
use Tests\TestCase;

class ReferenceCheckerTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . '/critpath-' . uniqid();
        mkdir($this->tempRoot . '/tests/Unit/Vault', 0755, true);
        mkdir($this->tempRoot . '/tests/Feature', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tempRoot);
        parent::tearDown();
    }

    public function test_existing_file_reports_exists_true(): void
    {
        file_put_contents($this->tempRoot . '/tests/Feature/VaultTest.php', '<?php');

        $result = (new ReferenceChecker($this->tempRoot))
            ->check('tests/Feature/VaultTest.php');

        $this->assertTrue($result['exists']);
        $this->assertNull($result['error']);
        $this->assertSame(['tests/Feature/VaultTest.php'], $result['matches']);
    }

    public function test_missing_file_reports_exists_false_with_error(): void
    {
        $result = (new ReferenceChecker($this->tempRoot))
            ->check('tests/Feature/GoneTest.php');

        $this->assertFalse($result['exists']);
        $this->assertSame('file missing', $result['error']);
        $this->assertSame([], $result['matches']);
    }

    public function test_glob_expansion_returns_actual_matches(): void
    {
        file_put_contents($this->tempRoot . '/tests/Unit/Vault/AlphaTest.php', '<?php');
        file_put_contents($this->tempRoot . '/tests/Unit/Vault/BravoTest.php', '<?php');

        $result = (new ReferenceChecker($this->tempRoot))
            ->check('tests/Unit/Vault/*.php');

        $this->assertTrue($result['exists']);
        $this->assertNull($result['error']);
        $this->assertCount(2, $result['matches']);
        $this->assertContains('tests/Unit/Vault/AlphaTest.php', $result['matches']);
        $this->assertContains('tests/Unit/Vault/BravoTest.php', $result['matches']);
    }

    public function test_glob_zero_matches_reports_error(): void
    {
        $result = (new ReferenceChecker($this->tempRoot))
            ->check('tests/Unit/DoesNotExist/*.php');

        $this->assertFalse($result['exists']);
        $this->assertSame('glob matched 0 files', $result['error']);
        $this->assertSame([], $result['matches']);
    }

    public function test_check_all_preserves_order_and_returns_list(): void
    {
        file_put_contents($this->tempRoot . '/tests/Feature/A.php', '<?php');

        $results = (new ReferenceChecker($this->tempRoot))->checkAll([
            'tests/Feature/A.php',
            'tests/Feature/B.php',
        ]);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['exists']);
        $this->assertFalse($results[1]['exists']);
        $this->assertSame('tests/Feature/A.php', $results[0]['path']);
        $this->assertSame('tests/Feature/B.php', $results[1]['path']);
    }

    /**
     * `check()` calls `ltrim($reference, '/\\')` so references written with
     * a leading slash (`/tests/Feature/X.php`) still resolve. Without the
     * ltrim (mutation), the double separator would break the lookup — i.e.
     * `exists` would flip to false.
     */
    public function test_leading_slash_in_reference_is_stripped_before_lookup(): void
    {
        file_put_contents($this->tempRoot . '/tests/Feature/WithSlash.php', '<?php');

        $result = (new ReferenceChecker($this->tempRoot))
            ->check('/tests/Feature/WithSlash.php');

        // The critical invariant: the file IS found despite the leading slash.
        // Without `ltrim`, `basePath . DIRECTORY_SEPARATOR . "/path"` would yield
        // a double-separator which fails `is_file()` on most filesystems.
        $this->assertTrue($result['exists']);
        $this->assertNull($result['error']);
    }

    /**
     * Windows-style leading backslash must also be stripped.
     */
    public function test_leading_backslash_in_reference_is_stripped_before_lookup(): void
    {
        file_put_contents($this->tempRoot . '/tests/Feature/WithBackslash.php', '<?php');

        $result = (new ReferenceChecker($this->tempRoot))
            ->check('\\tests/Feature/WithBackslash.php');

        $this->assertTrue($result['exists']);
    }

    /**
     * `checkAll()` wraps `array_map` in `array_values()` so the result is a
     * list (sequential 0-indexed keys). If `array_values()` is removed
     * (mutation), the returned array could keep non-sequential keys when
     * upstream arrays have gaps.
     */
    public function test_check_all_returns_list_with_sequential_integer_keys(): void
    {
        file_put_contents($this->tempRoot . '/tests/Feature/A.php', '<?php');

        // Start from a gapped array (keys 0 and 2) — exactly the kind of
        // input where `array_values()` matters.
        $references = [];
        $references[0] = 'tests/Feature/A.php';
        $references[2] = 'tests/Feature/B.php';

        $results = (new ReferenceChecker($this->tempRoot))->checkAll($references);

        $this->assertSame([0, 1], array_keys($results));
    }

    /**
     * Glob-expanded matches are relativised and normalised to forward slashes.
     * On Windows, globbing yields backslash paths; without the str_replace
     * (mutation), the returned match would contain `\` and fail string-equal
     * comparisons that downstream code performs against doc-declared paths.
     */
    public function test_glob_matches_use_forward_slashes_regardless_of_os(): void
    {
        file_put_contents($this->tempRoot . '/tests/Unit/Vault/DeepTest.php', '<?php');

        $result = (new ReferenceChecker($this->tempRoot))
            ->check('tests/Unit/Vault/*.php');

        $this->assertTrue($result['exists']);
        foreach ($result['matches'] as $match) {
            $this->assertStringNotContainsString('\\', $match);
            $this->assertStringStartsWith('tests/Unit/Vault/', $match);
        }
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($full) ? $this->rrmdir($full) : unlink($full);
        }
        rmdir($dir);
    }
}
