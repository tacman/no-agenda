<?php

namespace App\Twig;

use App\Entity\Episode;
use Survos\ImgproxyBundle\Service\ImgproxyUrlBuilder;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class CoverExtension extends AbstractExtension
{
    public function __construct(
        private readonly AssetExtension $assetExtension,
        private readonly ImgproxyUrlBuilder $imgproxyUrlBuilder,
    ) {}

    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('episode_cover', $this->episodeCover(...)),
        ];
    }

    public function episodeCover(Episode $episode, string $size = 'small'): string
    {
        if ($coverUri = $episode->getCoverUri()) {
            return $this->imgproxyUrlBuilder->resizePreset($coverUri, $size);
        }

        return $this->assetExtension->getAssetUrl(sprintf('build/images/placeholder_%s.jpg', $size));
    }
}
