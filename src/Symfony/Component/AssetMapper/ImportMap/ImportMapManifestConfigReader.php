<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\ImportMap;

use Symfony\Component\AssetMapper\Exception\RuntimeException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\VarExporter\VarExporter;

/**
 * Reads the manifest.json file and returns the list of entries.
 *
 * @author Damien Fernandes <damien.fernandes24@gmail.com>
 */
class ImportMapManifestConfigReader implements ImportMapConfigReaderInterface
{
    private ImportMapEntries $rootImportMapEntries;

    public function __construct(
        private readonly string $importMapConfigPath
    )
    {
    }

    public function getEntries(): ImportMapEntries
    {
        if (isset($this->rootImportMapEntries)) {
            return $this->rootImportMapEntries;
        }

        $configPath = $this->importMapConfigPath;

        $importMapConfig = json_decode(file_get_contents($configPath), true);
        $entries = new ImportMapEntries();
        $validKeys = ['isEntry', 'file', 'src', "assets", 'css', 'dynamicImports', 'imports', 'isDynamicEntry'];

        foreach ($importMapConfig ?? [] as $importName => $data) {
            if ($invalidKeys = array_diff(array_keys($data), $validKeys)) {
                throw new \InvalidArgumentException(sprintf('The following keys are not valid for the importmap entry "%s": "%s". Valid keys are: "%s".', $importName, implode('", "', $invalidKeys), implode('", "', $validKeys)));
            }

            $isEntrypoint = $data['isEntry'] ?? false;

            if (isset($data['file'])) {
                $extension = pathinfo($data['file'], PATHINFO_EXTENSION);

                switch (ImportMapType::tryFrom($extension)) {
                    case ImportMapType::CSS: {
                        $type = ImportMapType::CSS;
                        break;
                    }
                    case ImportMapType::SVG: {
                        $type = ImportMapType::SVG;
                        break;
                    }
                    default:
                        $type = ImportMapType::JS;
                }

                $entries->add(ImportMapEntry::createLocal($data['file'], $type, $data['file'], $isEntrypoint));
                if ($isEntrypoint){
                    $entries->add(ImportMapEntry::createLocal('app', ImportMapType::JS, $data['file'], $isEntrypoint));
                }
            }
        }

        return $this->rootImportMapEntries = $entries;
    }

    public function writeEntries(ImportMapEntries $entries): void
    {
        throw new \LogicException('ImportMapManifestConfigReader is read-only as its only goal is to read the manifest.json file.');
    }

    public function findRootImportMapEntry(string $moduleName): ?ImportMapEntry
    {
        $entries = $this->getEntries();

        return $entries->has($moduleName) ? $entries->get($moduleName) : null;
    }

    public function createRemoteEntry(string $importName, ImportMapType $type, string $version, string $packageModuleSpecifier, bool $isEntrypoint): ImportMapEntry
    {
        throw new \LogicException('ImportMapManifestConfigReader is read-only as its only goal is to read the manifest.json file.');
    }

    /**
     * Converts the "path" string from an importmap entry to the filesystem path.
     *
     * The path may already be a filesystem path. But if it starts with ".",
     * then the path is relative and the root directory is prepended.
     */
    public function convertPathToFilesystemPath(string $path): string
    {
        if (!str_starts_with($path, '.')) {
            return $path;
        }

        return Path::join($this->getRootDirectory(), $path);
    }

    /**
     * Converts a filesystem path to a relative path that can be used in the importmap.
     *
     * If no relative path could be created - e.g. because the path is not in
     * the same directory/subdirectory as the root importmap.php file - null is returned.
     */
    public function convertFilesystemPathToPath(string $filesystemPath): ?string
    {
        $rootImportMapDir = realpath($this->getRootDirectory());
        $filesystemPath = realpath($filesystemPath);
        if (!str_starts_with($filesystemPath, $rootImportMapDir)) {
            return null;
        }

        // remove the root directory, prepend "./" & normalize slashes
        return './' . str_replace('\\', '/', substr($filesystemPath, \strlen($rootImportMapDir) + 1));
    }

    public function getRootDirectory(): string
    {
        return \dirname($this->importMapConfigPath);
    }

    public static function splitPackageNameAndFilePath(string $packageName): array
    {
        $filePath = '';
        $i = strpos($packageName, '/');

        if ($i && (!str_starts_with($packageName, '@') || $i = strpos($packageName, '/', $i + 1))) {
            // @vendor/package/filepath or package/filepath
            $filePath = substr($packageName, $i);
            $packageName = substr($packageName, 0, $i);
        }

        return [$packageName, $filePath];
    }
}
