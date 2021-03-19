<?php

namespace elaborate\route;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use elaborate\Application;
use elaborate\Request;
use elaborate\exception\HttpException;
use elaborate\exception\ClassNotFoundException;
use elaborate\Route;
use elaborate\Response;

/**
 * 路由调度
 */
class Dispatch
{
    protected $app;

    protected $request;

    protected $route;

    protected $dispatch;

    protected $param;

    public function __construct(Request $request, Route $route, $dispatch)
    {
        // 解析默认的URL规则
        $dispatch = $this->parseUrl($dispatch);

        $this->request  = $request;
        $this->route   = $route;
        $this->dispatch = $dispatch;
    }

    public function init(Application $app)
    {
        $this->app = $app;

        $result = $this->dispatch;

        if (is_string($result)) {
            $result = explode('/', $result);
        }

        // 获取控制器名
        $controller = strip_tags($result[0]);

        if (strpos($controller, '.')) {
            $pos              = strrpos($controller, '.');
            $this->controller = substr($controller, 0, $pos) . '.' . studly(substr($controller, $pos + 1));
        } else {
            $this->controller = studly($controller);
        }

        // 获取操作名
        $this->actionName = strip_tags($result[1]);

        // 设置当前请求的控制器、操作
        $this->request
            ->setController($this->controller)
            ->setAction($this->actionName);
    }

    public function run()
    {
        try {
            // 实例化控制器
            $instance = $this->controller($this->controller);
        } catch (ClassNotFoundException $e) {
            throw new HttpException(404, 'controller not exists:' . $e->getClass());
        }

        $action = $this->actionName;

        if (is_callable([$instance, $action])) {
            $vars = $this->request->param();
            try {
                $reflect = new ReflectionMethod($instance, $action);
                // 严格获取当前操作方法名
                $actionName = $reflect->getName();

                $this->request->setAction($actionName);
            } catch (ReflectionException $e) {
                $reflect = new ReflectionMethod($instance, '__call');
                $vars    = [$action, $vars];
                $this->request->setAction($action);
            }
        } else {
            // 操作不存在
            throw new HttpException(404, 'method not exists:' . get_class($instance) . '->' . $action . '()');
        }

        $data = $this->app->invokeReflectMethod($instance, $reflect, $vars);

        return $this->autoResponse($data);
    }

    protected function autoResponse($data): Response
    {
        if ($data instanceof Response) {
            $response = $data;
        } elseif (!is_null($data)) {
            // 默认自动识别响应输出类型
            $type     = $this->request->isJson() ? 'json' : 'html';
            $response = Response::create($data, $type);
        } else {
            $data = ob_get_clean();

            $content  = false === $data ? '' : $data;
            $status   = '' === $content && $this->request->isJson() ? 204 : 200;
            $response = Response::create($content, 'html', $status);
        }

        return $response;
    }

    /**
     * 解析url
     *
     * @param string $url
     * @return array
     */
    public function parseUrl(string $url)
    {
        $path = $this->parseUrlPath($url);
        if (empty($path)) {
            return [null, null];
        }

        // 解析控制器
        $controller = !empty($path) ? array_shift($path) : null;

        if ($controller && !preg_match('/^[A-Za-z0-9][\w|\.]*$/', $controller)) {
            throw new HttpException(404, 'controller not exists:' . $controller);
        }

        // 解析操作
        $action = !empty($path) ? array_shift($path) : null;
        $var    = [];

        // 解析额外参数
        if ($path) {
            preg_replace_callback('/(\w+)\|([^\|]+)/', function ($match) use (&$var) {
                $var[$match[1]] = strip_tags($match[2]);
            }, implode('|', $path));
        }

        // 设置当前请求的参数
        $this->param = $var;

        // 封装路由
        $route = [$controller, $action];

        return $route;
    }

    /**
     * 实例化访问控制器
     * @access public
     * @param string $name 资源地址
     * @return object
     * @throws ClassNotFoundException
     */
    public function controller(string $name)
    {
        $controllerLayer = 'controller';
        $emptyController = 'Error';

        $class = $this->app->parseClass($controllerLayer, $name);

        if (class_exists($class)) {
            return $this->app->make($class, [], true);
        } elseif ($emptyController && class_exists($emptyClass = $this->app->parseClass($controllerLayer, $emptyController))) {
            return $this->app->make($emptyClass, [], true);
        }

        throw new ClassNotFoundException('class not exists:' . $class, $class);
    }

    /**
     * 解析URL的pathinfo参数
     * @access public
     * @param  string $url URL地址
     * @return array
     */
    public function parseUrlPath(string $url): array
    {
        // 分隔符替换 确保路由定义使用统一的分隔符
        $url = str_replace('|', '/', $url);
        $url = trim($url, '/');

        if (strpos($url, '/')) {
            // [控制器/操作]
            $path = explode('/', $url);
        } else {
            $path = [$url];
        }

        return $path;
    }
}
