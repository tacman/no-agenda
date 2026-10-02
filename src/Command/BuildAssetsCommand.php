<?php

namespace App\Command;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;

#[AsCommand(name: 'assets:build', description: 'Build the web manifest and service worker from AssetMapper assets')]
class BuildAssetsCommand extends Command
{
    public function __construct(
        private readonly Environment $twig,
        private readonly AssetMapperInterface $assetMapper,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $icons = [];
        foreach ([512, 192, 128] as $size) {
            $icons[] = [
                'src' => $this->assetMapper->getAsset("images/website-icon-$size.png")->publicPath,
                'sizes' => "{$size}x{$size}",
                'type' => 'image/png',
                'purpose' => 'any',
            ];
        }
        $public = dirname(__DIR__, 2).'/public';
        file_put_contents($public.'/site.webmanifest', json_encode([
            'name' => 'No Agenda Show',
            'short_name' => 'No Agenda',
            'description' => 'The No Agenda player and archive',
            'display' => 'minimal-ui',
            'start_url' => '/',
            'icons' => $icons,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $assets = array_column($icons, 'src');
        file_put_contents($public.'/service-worker.js', $this->twig->render('service_worker.js.twig', [
            'timestamp' => (string) time(),
            'assets' => $assets,
            'logo_asset' => $icons[1]['src'],
        ]));

        return Command::SUCCESS;
    }
}
