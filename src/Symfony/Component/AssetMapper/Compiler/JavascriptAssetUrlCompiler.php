<?php

namespace Symfony\Component\AssetMapper\Compiler;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\Exception\RuntimeException;
use Symfony\Component\AssetMapper\MappedAsset;

class JavascriptAssetUrlCompiler implements AssetCompilerInterface
{
    private readonly array $manifestData;
    private array $assetFiles = [];

    public function __construct(
        private readonly string $manifestPath
    )
    {
        if (!file_exists($this->manifestPath)) {
            throw new RuntimeException(sprintf('Manifest file "%s" does not exist.', $this->manifestPath));
        }

        $this->manifestData = json_decode(file_get_contents($this->manifestPath), true);
        foreach ($this->manifestData as $manifestItem) {
            if (!empty($manifestItem['assets'])) {
                $this->assetFiles = array_merge($this->assetFiles, $manifestItem['assets']);
            }
        }
    }

    public function supports(MappedAsset $asset): bool
    {
        if ('js' !== $asset->publicExtension) {
            return false;
        }

        $manifestItem = $this->findManifestItemByFile($asset->logicalPath);
        if (!empty($manifestItem['assets'])) {
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
    {
        foreach ($this->assetFiles as $assetFile) {
            $mappedAsset = $assetMapper->getAsset($assetFile);
            $content = str_replace($assetFile, substr($mappedAsset->publicPath, 1), $content);
        }

        return $content;
    }

    private function findManifestItemByFile(string $file): ?array
    {
        $filteredManifest = array_filter($this->manifestData, fn($value) => $value['file'] === $file);
        return $filteredManifest[array_key_first($filteredManifest)];
    }
}
