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
