<?php

namespace Symfony\Component\AssetMapper\ImportMap;

use Symfony\Component\Filesystem\Path;

interface ImportMapConfigReaderInterface
{
    /**
     * @return ImportMapEntries<ImportMapEntry>
     */
    public function getEntries(): ImportMapEntries;

    /**
     * @param ImportMapEntries<ImportMapEntry> $entries
     */
    public function writeEntries(ImportMapEntries $entries): void;


    public function findRootImportMapEntry(string $moduleName): ?ImportMapEntry;

    public function createRemoteEntry(string $importName, ImportMapType $type, string $version, string $packageModuleSpecifier, bool $isEntrypoint): ImportMapEntry;

    /**
     * Converts the "path" string from an importmap entry to the filesystem path.
     *
     * The path may already be a filesystem path. But if it starts with ".",
     * then the path is relative and the root directory is prepended.
     */
    public function convertPathToFilesystemPath(string $path): string;

    public function convertFilesystemPathToPath(string $filesystemPath): ?string;

    public function getRootDirectory(): string;

    public static function splitPackageNameAndFilePath(string $packageName): array;
}
