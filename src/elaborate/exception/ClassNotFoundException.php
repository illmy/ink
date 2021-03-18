<?php
namespace elaborate\exception;

/**
 * 类不存在
 */
class ClassNotFoundException extends Exception
{
    protected $class;

    public function __construct(string $message, string $class = '')
    {
        $this->message = $message;
        $this->class   = $class;

        parent::__construct($message, 0);
    }

    /**
     * 获取类名
     * @access public
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }
}
