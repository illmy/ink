<?php 
namespace elaborate;

use Closure;
use elaborate\route\Dispatch;

/** 
 * 路由
 */
class Route
{
    protected $app;

    protected $config = [];

    /**
     * 请求对象
     *
     * @var Request
     */
    protected $request;

    protected $dispatch;

    /**
     * 构造方法
     *
     * @param App $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        
        $this->config = array_merge($this->config, $this->app->config->get('route'));
    }

    public function init(Request $request, $withRoute = true)
    {
        $this->request = $request;
        
        if ($withRoute) {
            //加载路由
            if ($withRoute instanceof Closure) {
                $withRoute();
            }
            $dispatch = $this->check();
        } else {
            $dispatch = $this->url($this->path());
        }

        $dispatch->init($this->app);

        $this->dispatch = $dispatch;
    }

    /**
     * 路由调度
     * @param Request $request
     * @param Closure|bool $withRoute
     * @return Response
     */
    public function dispatch()
    {
        return $this->dispatch->run();
    }

    /**
     * 检测URL路由 未实现
     * @access public
     * @return Dispatch|false
     * @throws RouteNotFoundException
     */
    public function check()
    {
        // 自动检测域名路由
        $url = $this->path();

        return $this->url($url);
    }

    /**
     * 获取当前请求URL的pathinfo信息(不含URL后缀)
     * @access protected
     * @return string
     */
    protected function path(): string
    {
        $pathinfo = $this->request->pathinfo();

        return $pathinfo;
    }

    /**
     * 默认URL解析
     * @access public
     * @param string $url URL地址
     * @return Dispatch
     */
    public function url(string $url): Dispatch
    {
        return new Dispatch($this->request, $this, $url);
    }
}