<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Symfony\Symfony61\Rector\Class_\CommandPropertyToAttributeRector;
use Rector\Symfony\Symfony62\Rector\Class_\MessageHandlerInterfaceToAttributeRector;

return RectorConfig::configure()
    ->withPaths([
//        __DIR__ . '/config',
//        __DIR__ . '/public',
        __DIR__ . '/src/',
//        __DIR__ . '/src/MessageHandler',
//        __DIR__ . '/src/Command',
//        __DIR__ . '/tests',
    ])
    // uncomment to reach your current PHP version
     ->withPhpSets(php83: true)
    ->withRules([
        Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector::class,

        MessageHandlerInterfaceToAttributeRector::class,
        CommandPropertyToAttributeRector::class,
    ])
    ->withComposerBased(symfony: true)
    ->withTypeCoverageLevel(9)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0);
