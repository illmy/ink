<?php 

namespace elaborate\exception;

class HttpException extends Exception
{
    public function __construct(int $statusCode, string $message = '')
    {
        parent::__construct($message, $statusCode);
    }
}