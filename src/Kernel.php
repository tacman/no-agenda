<?php

// src/Kernel.php
namespace App;

use Minishlink\WebPush\WebPush;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    // ...

    public function process(ContainerBuilder $container): void
    {
        // in this method you can manipulate the service container:
        // for example, changing some container service:
//        $container->getDefinition('app.some_private_service')->setPublic(true);

        $vapidPrivateKey = $_SERVER['VAPID_PRIVATE_KEY'] ?? null;
        $vapidPublicKey = $_SERVER['VAPID_PUBLIC_KEY'] ?? null;

        if (!$vapidPrivateKey || !$vapidPublicKey) {
            $container->removeDefinition(WebPush::class);
        }

        // or processing tagged services:
//        foreach ($container->findTaggedServiceIds('some_tag') as $id => $tags) {
//            // ...
//        }
    }
}
