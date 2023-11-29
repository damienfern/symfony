<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\ImportMap;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntries;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManifestConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapType;
use Symfony\Component\AssetMapper\ImportMap\RemotePackageStorage;
use Symfony\Component\Filesystem\Filesystem;

class ImportMapManifestConfigReaderTest extends TestCase
{
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        if (!file_exists(__DIR__ . '/../Fixtures/importmaps_for_writing_from_manifest')) {
            $this->filesystem->mkdir(__DIR__ . '/../Fixtures/importmaps_for_writing_from_manifest');
        }
        if (!file_exists(__DIR__ . '/../Fixtures/importmaps_for_writing_from_manifest/assets')) {
            $this->filesystem->mkdir(__DIR__ . '/../Fixtures/importmaps_for_writing_from_manifest/assets');
        }
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove(__DIR__ . '/../Fixtures/importmaps_for_writing_from_manifest');
    }

    public function testGetEntries()
    {
        $manifestJson = <<<'JSON'
{
  "assets/main.css": {
    "file": "main.css",
    "src": "assets/main.css"
  },
  "assets/main.ts": {
    "assets": [
      "logo.svg"
    ],
    "css": [
      "main.css"
    ],
    "dynamicImports": [
      "assets/views/AboutView.vue"
    ],
    "file": "main.js",
    "isEntry": true,
    "src": "assets/main.ts"
  },
  "assets/views/AboutView.vue": {
    "file": "AboutView.js",
    "imports": [
      "assets/main.ts"
    ],
    "isDynamicEntry": true,
    "src": "assets/views/AboutView.vue"
  }
}
JSON;

        file_put_contents(__DIR__ . '/../Fixtures/importmaps_for_writing_from_manifest/manifest.json', $manifestJson);

        $reader = new ImportMapManifestConfigReader(
            __DIR__ . '/../Fixtures/importmaps_for_writing_from_manifest/manifest.json'
        );
        $entries = $reader->getEntries();
        $this->assertInstanceOf(ImportMapEntries::class, $entries);
        /** @var ImportMapEntry[] $allEntries */
        $allEntries = iterator_to_array($entries);
        $this->assertNotEmpty($allEntries);
        $this->assertCount(3, $allEntries);

        $typeCssEntry = $allEntries[0];
        $this->assertFalse($typeCssEntry->isRemotePackage());
        $this->assertSame('./main.css', $typeCssEntry->importName);
        $this->assertSame('main.css', $typeCssEntry->path);
        $this->assertSame('css', $typeCssEntry->type->value);

        $localPackageEntry = $allEntries[1];
        $this->assertFalse($localPackageEntry->isRemotePackage());
        $this->assertSame('./main.js', $localPackageEntry->importName);
        $this->assertSame('main.js', $localPackageEntry->path);
        $this->assertSame('js', $localPackageEntry->type->value);
        $this->assertTrue($localPackageEntry->isEntrypoint);

        $localPackage = $allEntries[2];
        $this->assertFalse($localPackage->isRemotePackage());
        $this->assertSame('./AboutView.js', $localPackage->importName);
        $this->assertSame('AboutView.js', $localPackage->path);
        $this->assertSame('js', $localPackage->type->value);
        $this->assertFalse($localPackage->isEntrypoint);
    }
//
//    public function testWriteEntries()
//    {
        // TODO: Implement testWriteEntries() method.
//    }

    /**
     * @dataProvider getPathToFilesystemPathTests
     */
    public function testConvertPathToFilesystemPath(string $path, string $expectedPath)
    {
        $configReader = new ImportMapConfigReader(realpath(__DIR__ . '/../Fixtures/importmap.php'), $this->createMock(RemotePackageStorage::class));
        // normalize path separators for comparison
        $expectedPath = str_replace('\\', '/', $expectedPath);
        $this->assertSame($expectedPath, $configReader->convertPathToFilesystemPath($path));
    }

    public static function getPathToFilesystemPathTests()
    {
        yield 'no change' => [
            'path' => 'dir1/file2.js',
            'expectedPath' => 'dir1/file2.js',
        ];

        yield 'prefixed with relative period' => [
            'path' => './dir1/file2.js',
            'expectedPath' => realpath(__DIR__ . '/../Fixtures') . '/dir1/file2.js',
        ];
    }

    /**
     * @dataProvider getFilesystemPathToPathTests
     */
    public function testConvertFilesystemPathToPath(string $path, ?string $expectedPath)
    {
        $configReader = new ImportMapConfigReader(__DIR__ . '/../Fixtures/importmap.php', $this->createMock(RemotePackageStorage::class));
        $this->assertSame($expectedPath, $configReader->convertFilesystemPathToPath($path));
    }

    public static function getFilesystemPathToPathTests()
    {
        yield 'not in root directory' => [
            'path' => __FILE__,
            'expectedPath' => null,
        ];

        yield 'converted to relative path' => [
            'path' => __DIR__ . '/../Fixtures/dir1/file2.js',
            'expectedPath' => './dir1/file2.js',
        ];
    }

    public function testFindRootImportMapEntry()
    {
        $configReader = new ImportMapConfigReader(__DIR__ . '/../Fixtures/importmap.php', $this->createMock(RemotePackageStorage::class));
        $entry = $configReader->findRootImportMapEntry('file2');
        $this->assertSame('file2', $entry->importName);
        $this->assertSame('file2.js', $entry->path);
    }
}
