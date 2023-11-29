<?php

namespace Symfony\Component\AssetMapper\Compiler;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;
use Symfony\Component\AssetMapper\Exception\RuntimeException;
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReaderInterface;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\Filesystem\Path;

/**
 *
 */
class JavascriptCSSUrlComplier implements AssetCompilerInterface
{
    private readonly array $manifestData;
    private array $cssFiles = [];

    public function __construct(
        private readonly string $manifestPath
    )
    {
        if (!file_exists($this->manifestPath)) {
            throw new RuntimeException(sprintf('Manifest file "%s" does not exist.', $this->manifestPath));
        }

        $this->manifestData = json_decode(file_get_contents($this->manifestPath), true);
        foreach ($this->manifestData as $manifestItem) {
            if (!empty($manifestItem['css'])) {
                $this->cssFiles = array_merge($this->cssFiles, $manifestItem['css']);
            }
        }
    }

    public function supports(MappedAsset $asset): bool
    {
        if ('js' !== $asset->publicExtension) {
            return false;
        }
        $manifestItem = $this->findManifestItemByFile($asset->logicalPath);
        if (!empty($manifestItem['css'])) {
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
    {
        $toto = array_filter($this->manifestData, fn($value) => $value['file'] === $asset->logicalPath);
        $manifestItem = ($toto[array_key_first($toto)]);

        foreach ($this->cssFiles as $cssFile) {
            $cssAsset = $assetMapper->getAsset($cssFile);
            $content = str_replace($cssFile, substr($cssAsset->publicPath, 1), $content);
        }

        return $content;
    }

    private function findManifestItemByFile(string $file): ?array
    {
        $filteredManifest = array_filter($this->manifestData, fn($value) => $value['file'] === $file);
        return $filteredManifest[array_key_first($filteredManifest)];
    }
}
