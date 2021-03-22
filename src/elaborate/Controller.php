<?php

declare(strict_types=1);

namespace elaborate;

use elaborate\Application;

/**
 * 控制器基础类
 */
abstract class Controller
{
    /**
     * Request实例
     * @var \elaborate\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \elaborate\Application
     */
    protected $app;

    /**
     * 构造方法
     * @access public
     * @param  App  $app  应用对象
     */
    public function __construct(Application $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;

        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {
    }
}
