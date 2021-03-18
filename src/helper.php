<?php 

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