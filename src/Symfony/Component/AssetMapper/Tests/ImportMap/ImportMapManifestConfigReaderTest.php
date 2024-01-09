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
    "file": "main.9ca0e350.css",
    "src": "assets/main.css"
  },
  "assets/main.ts": {
    "assets": [
      "logo.277e0e97.svg"
    ],
    "css": [
      "main.9ca0e350.css"
    ],
    "dynamicImports": [
      "assets/views/AboutView.vue",
      "assets/views/ContactView.vue"
    ],
    "file": "main.384b8127.js",
    "isEntry": true,
    "src": "assets/main.ts"
  },
  "assets/views/AboutView.vue": {
    "css": [
      "AboutView.fe0787ef.css"
    ],
    "file": "AboutView.c755ff27.js",
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
        $this->assertCount(4, $allEntries);

        $typeCssEntry = $allEntries[0];
        $this->assertFalse($typeCssEntry->isRemotePackage());
        $this->assertSame('main.9ca0e350.css', $typeCssEntry->importName);
        $this->assertSame('main.9ca0e350.css', $typeCssEntry->path);
        $this->assertSame('css', $typeCssEntry->type->value);

        $localPackageEntry = $allEntries[1];
        $this->assertFalse($localPackageEntry->isRemotePackage());
        $this->assertSame('main.384b8127.js', $localPackageEntry->importName);
        $this->assertSame('main.384b8127.js', $localPackageEntry->path);
        $this->assertSame('js', $localPackageEntry->type->value);
        $this->assertTrue($localPackageEntry->isEntrypoint);

        $localPackageAppEntry = $allEntries[2];
        $this->assertFalse($localPackageAppEntry->isRemotePackage());
        $this->assertSame('app', $localPackageAppEntry->importName);
        $this->assertSame('main.384b8127.js', $localPackageAppEntry->path);
        $this->assertSame('js', $localPackageAppEntry->type->value);
        $this->assertTrue($localPackageAppEntry->isEntrypoint);

        $localPackage = $allEntries[3];
        $this->assertFalse($localPackage->isRemotePackage());
        $this->assertSame('AboutView.c755ff27.js', $localPackage->importName);
        $this->assertSame('AboutView.c755ff27.js', $localPackage->path);
        $this->assertSame('js', $localPackage->type->value);
        $this->assertFalse($localPackage->isEntrypoint);
    }

    public function testWriteEntries()
    {

        $manifestJson = <<<'JSON'
{
  "assets/assets/logo.svg": {
    "file": "logo.277e0e97.svg",
    "src": "assets/assets/logo.svg"
  },
  "assets/main.css": {
    "file": "main.9ca0e350.css",
    "src": "assets/main.css"
  },
  "assets/main.ts": {
    "assets": [
      "logo.277e0e97.svg"
    ],
    "css": [
      "main.9ca0e350.css"
    ],
    "dynamicImports": [
      "assets/views/AboutView.vue",
      "assets/views/ContactView.vue"
    ],
    "file": "main.384b8127.js",
    "isEntry": true,
    "src": "assets/main.ts"
  },
  "assets/views/AboutView.css": {
    "file": "AboutView.fe0787ef.css",
    "src": "assets/views/AboutView.css"
  },
  "assets/views/AboutView.vue": {
    "css": [
      "AboutView.fe0787ef.css"
    ],
    "file": "AboutView.c755ff27.js",
    "imports": [
      "assets/main.ts"
    ],
    "isDynamicEntry": true,
    "src": "assets/views/AboutView.vue"
  },
  "assets/views/ContactView.css": {
    "file": "ContactView.80bdbd08.css",
    "src": "assets/views/ContactView.css"
  },
  "assets/views/ContactView.vue": {
    "css": [
      "ContactView.80bdbd08.css"
    ],
    "file": "ContactView.06bda5cf.js",
    "imports": [
      "assets/main.ts"
    ],
    "isDynamicEntry": true,
    "src": "assets/views/ContactView.vue"
  }
}
JSON;

        file_put_contents(__DIR__ . '/../Fixtures/importmaps_for_writing_from_manifest/manifest.json', $manifestJson);
        $reader = new ImportMapManifestConfigReader(
            __DIR__ . '/../Fixtures/importmaps_for_writing_from_manifest/manifest.json'
        );
        $entries = $reader->getEntries();
        // expect to throw Exception because manifest.json is not writable
        $this->expectException(\LogicException::class);
        $reader->writeEntries($entries);
    }

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
