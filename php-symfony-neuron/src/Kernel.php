<?php

namespace App;

use App\DependencyInjection\Compiler\ApiClientCompilerPass;
use App\DependencyInjection\Compiler\NewsApiClientCompilerPass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Register compiler passes
     */
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Register the API client compiler pass
        $container->addCompilerPass(new ApiClientCompilerPass());

        // Register the News API client compiler pass
        $container->addCompilerPass(new NewsApiClientCompilerPass());
    }
}
