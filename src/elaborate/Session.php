<?php

namespace elaborate;

class Session
{
    protected $name = 'PHPINKSESSID';

    public function __construct()
    {
        $this->init();
    }

    protected function init()
    {
        session_name($this->name);
        session_start();
    }

    public function get()
    {

    }

    public function set()
    {

    }
}