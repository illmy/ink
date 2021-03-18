<?php

namespace elaborate;

use Closure;

/**
 * 中间件
 */
class Middleware
{
    /**
     * 中间件执行队列
     * @var array
     */
    protected $queue = [];

    /**
     * 应用对象
     * @var App
     */
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * 导入中间件
     * @access public
     * @param array  $middlewares
     * @return void
     */
    public function import(array $middlewares = []): void
    {
        foreach ($middlewares as $middleware) {
            $this->add($middleware);
        }
    }

    /**
     * 注册中间件
     * @access public
     * @param mixed  $middleware
     * @return void
     */
    public function add($middleware): void
    {
        if (!empty($middleware)) {
            $this->queue[] = $middleware;
            $this->queue   = array_unique($this->queue, SORT_REGULAR);
        }
    }

    /**
     * 管道调用
     *
     * @param Closure $stack
     * @return void
     */
    public function pipeline(Closure $stack)
    {
        $callback = array_reduce($this->queue, function ($stack, $pipe) {
            return function () use ($stack, $pipe) {
                return $pipe::handle($this->app->request, $stack);
            };
        }, $stack);

        return call_user_func($callback);
    }
}
