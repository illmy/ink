<?php

namespace elaborate\response;

use elaborate\Response;
/**
 * Html Response
 */
class Html extends Response
{
    /**
     * 输出type
     * @var string
     */
    protected $contentType = 'text/html';

    public function __construct($data = '', int $code = 200)
    {
        $this->init($data, $code);
    }
}
