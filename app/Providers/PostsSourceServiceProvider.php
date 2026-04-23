<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\PostsSource\Application\Contracts\PostsRepository;
use App\Features\PostsSource\Infrastructure\Config\PostsSourceConfig;
use App\Features\PostsSource\Infrastructure\Repositories\DatabasePostsRepository;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\ServiceProvider;

class PostsSourceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PostsSourceConfig::class, function ($app): PostsSourceConfig {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('posts.source', []);

            return PostsSourceConfig::fromConfig($config);
        });

        $this->app->singleton(PostsRepository::class, function ($app): PostsRepository {
            return new DatabasePostsRepository(
                $app->make(ConnectionResolverInterface::class),
                $app->make(PostsSourceConfig::class),
            );
        });
    }
}
