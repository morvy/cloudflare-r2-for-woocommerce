<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;
use Rector\Php55\Rector\Class_\ClassConstantToSelfClassRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php81\Rector\Array_\FirstClassCallableRector;
use Fsylum\RectorWordPress\Set\WordPressLevelSetList;
use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/includes',
    ])
    ->withPhpSets(
        php82: true
    )
    ->withSets([
        LevelSetList::UP_TO_PHP_82,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
        WordPressLevelSetList::UP_TO_WP_6_8,
    ])
    ->withSkip([
        // Skip vendor directory
        __DIR__ . '/vendor',
        AddArrowFunctionReturnTypeRector::class,
        ClosureToArrowFunctionRector::class,
        ClassConstantToSelfClassRector::class,
        FirstClassCallableRector::class,
        // Skip rules that conflict with WordPress Coding Standards
        LongArrayToShortArrayRector::class, // WPCS prefers array() for consistency
        RemoveUselessVarTagRector::class, // WPCS requires @var tags even with type hints
        ClassPropertyAssignToConstructorPromotionRector::class, // WPCS requires specific doc format
    ]);
