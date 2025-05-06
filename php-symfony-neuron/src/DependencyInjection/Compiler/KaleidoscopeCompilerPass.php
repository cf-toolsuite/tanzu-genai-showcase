<?php

namespace App\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Compiler pass for Kaleidoscope configuration
 */
class KaleidoscopeCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // This compiler pass is kept for potential future configuration needs
    }
}
