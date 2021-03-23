<?php

namespace elaborate;

use elaborate\orm\Db;

class Application extends Container
{
    /**
     * Ink 框架版本
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * 当前应用类库命名空间
     * @var string
     */
    protected $namespace = 'app';

    /**
     * 应用跟目录
     *
     * @var string
     */
    protected $rootPath  = '';

    /**
     * 应用目录
     *
     * @var string
     */
    protected $appPath = '';

    /**
     * 框架目录
     *
     * @var string
     */
    protected $inkPath = '';

    /**
     * 运行时目录
     *
     * @var string
     */
    protected $runtimePath = '';

    /**
     * 路由定义目录
     * @var string
     */
    protected $routePath = '';

    protected $configExt = 'php';

    /**
     * 绑定
     *
     * @var array
     */
    public $bindings = [
        'app' => Application::class,
        'config' => Config::class,
        'db' => Db::class,
        'session' => Session::class,
        'Log' => Log::class,
        'request' => Request::class,
        'response' => Response::class,
        'middleware' => Middleware::class,
        'route' => Route::class,
        'Psr\Log\LoggerInterface' => Log::class
    ];

    /**
     * 构造方法
     */
    public function __construct()
    {
        $this->inkPath   = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        $this->rootPath    = $this->getDefaultRootPath();
        $this->appPath     = $this->rootPath . 'app' . DIRECTORY_SEPARATOR;
        $this->runtimePath = $this->rootPath . 'runtime' . DIRECTORY_SEPARATOR;

        static::setInstance($this);

        $this->instance('app', $this);
        $this->instance('elaborate\Application', $this);
        $this->instance('elaborate\Container', $this);
    }

    /**
     * 获取框架版本
     * @access public
     * @return string
     */
    public function version(): string
    {
        return static::VERSION;
    }

    /**
     * 获取应用根目录
     * @access public
     * @return string
     */
    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    /**
     * 获取应用基础目录
     * @access public
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->rootPath . 'app' . DIRECTORY_SEPARATOR;
    }

    /**
     * 获取当前应用目录
     * @access public
     * @return string
     */
    public function getAppPath(): string
    {
        return $this->appPath;
    }

    /**
     * 设置应用目录
     * @param string $path 应用目录
     */
    public function setAppPath(string $path)
    {
        $this->appPath = $path;
    }

    /**
     * 获取应用运行时目录
     * @access public
     * @return string
     */
    public function getRuntimePath(): string
    {
        return $this->runtimePath;
    }

    /**
     * 设置runtime目录
     * @param string $path 定义目录
     */
    public function setRuntimePath(string $path): void
    {
        $this->runtimePath = $path;
    }

    /**
     * 获取核心框架目录
     * @access public
     * @return string
     */
    public function getInkPath(): string
    {
        return $this->inkPath;
    }

    /**
     * 获取应用配置目录
     * @access public
     * @return string
     */
    public function getConfigPath(): string
    {
        return $this->rootPath . 'config' . DIRECTORY_SEPARATOR;
    }

    /**
     * 获取路由地址
     *
     * @return string
     */
    public function getRoutePath(): string
    {
        return $this->rootPath . 'route' . DIRECTORY_SEPARATOR;
    }

    /**
     * 运行应用
     *
     * @return Response
     */
    public function run()
    {
        $this->initialize();

        $request = $this->app->make('request');

        // 加载全局中间件
        $this->loadMiddleware();

        // 路由初始化
        $this->dispatchToInit($request);

        // 执行中间件
        $response = $this->app->middleware->pipeline(function () use ($request) {
            // 路由调度  
            return $this->dispatchToRoute();
        });

        return $response;
    }

    /**
     * 初始化
     *
     * @return void
     */
    public function initialize()
    {
        $this->load();
        date_default_timezone_set($this->app->config->get('app.default_timezone', 'Asia/Shanghai'));
        $this->provider();
    }

    /**
     * 注入服务器
     *
     * @return void
     */
    protected function provider(): void
    {
        $db = $this->app->make('db');
    }

    /**
     * 加载配置参数
     *
     * @return void
     */
    public function load()
    {
        $appPath = $this->getAppPath();

        if (is_file($appPath . 'common.php')) {
            include_once $appPath . 'common.php';
        }

        include_once $this->inkPath . 'helper.php';

        $configPath = $this->getConfigPath();

        $files = [];

        if (is_dir($configPath)) {
            $files = glob($configPath . '*' . $this->configExt);
        }

        foreach ($files as $file) {
            $this->app->config->load($file, pathinfo($file, PATHINFO_FILENAME));
        }
    }

    /**
     * 加载路由
     *
     * @return void
     */
    public function loadMiddleware()
    {
        if (is_file($this->getBasePath() . 'middleware.php')) {
            $this->app->middleware->import(include $this->getBasePath() . 'middleware.php');
        }
    }

    /**
     * 路由初始化
     *
     * @param [type] $request
     * @return void
     */
    protected function dispatchToInit($request)
    {
        $withRoute = $this->app->config->get('app.with_route', true) ? function () {
            $this->loadRoutes();
        } : null;

        return $this->app->route->init($request, $withRoute);
    }

    /**
     * 使用路由调度
     *
     * @param Request $request
     * @return Response
     */
    protected function dispatchToRoute()
    {
        return $this->app->route->dispatch();
    }

    /**
     * 加载路由
     *
     * @return void
     */
    protected function loadRoutes()
    {
        // 加载路由定义
        $routePath = $this->getRoutePath();

        if (is_dir($routePath)) {
            $files = glob($routePath . '*.php');
            foreach ($files as $file) {
                include $file;
            }
        }
    }

    /**
     * 解析应用类的类名
     * @access public
     * @param string $layer 层名 controller model ...
     * @param string $name  类名
     * @return string
     */
    public function parseClass(string $layer, string $name): string
    {
        $name  = str_replace(['/', '.'], '\\', $name);
        $array = explode('\\', $name);
        $class = studly(array_pop($array));
        $path  = $array ? implode('\\', $array) . '\\' : '';

        return $this->namespace . '\\' . $layer . '\\' . $path . $class;
    }

    /**
     * 获取应用根目录
     * @access protected
     * @return string
     */
    protected function getDefaultRootPath(): string
    {
        return dirname($this->inkPath, 4) . DIRECTORY_SEPARATOR;
    }
}
