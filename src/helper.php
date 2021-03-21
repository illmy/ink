<?php 

use elaborate\Response;

/**
 * 下划线转驼峰(首字母大写)
 *
 * @param string $value
 * @return string
 */
function studly(string $value): string
{
    $key = $value;

    $value = ucwords(str_replace(['-', '_'], ' ', $value));

    return str_replace(' ', '', $value);
}

if (!function_exists('json')) {
    /**
     * 获取\elaborate\response\Json对象实例
     * @param mixed $data    返回的数据
     * @param int   $code    状态码
     * @param array $header  头部
     * @param array $options 参数
     * @return \elaborate\response\Json
     */
    function json($data = [], $code = 200, $header = [], $options = [])
    {
        return Response::create($data, 'json', $code)->header($header)->options($options);
    }
}