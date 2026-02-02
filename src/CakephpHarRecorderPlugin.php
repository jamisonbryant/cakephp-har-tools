<?php
declare(strict_types=1);

namespace JamisonBryant\CakephpHarRecorder;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;
use Cake\Http\MiddlewareQueue;
use JamisonBryant\CakephpHarRecorder\Middleware\HarRecorderMiddleware;

class CakephpHarRecorderPlugin extends BasePlugin
{
    /**
     * Plugin bootstrap hook.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The application instance.
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);
    }

    /**
     * Add the HAR recorder middleware when enabled.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue.
     * @return \Cake\Http\MiddlewareQueue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue = parent::middleware($middlewareQueue);

        $config = Configure::read('HarRecorder') ?? [];
        if (!($config['enabled'] ?? true)) {
            return $middlewareQueue;
        }

        return $middlewareQueue->add(new HarRecorderMiddleware($config));
    }
}
