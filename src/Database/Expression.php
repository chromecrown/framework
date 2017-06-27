<?php

namespace Wpt\Framework\Database;

/**
 * Class Expression
 *
 * @package Wpt\Framework\Database
 */
class Expression
{
    /**
     * @var string
     */
    private $expression = null;

    /**
     * Expression constructor.
     *
     * @param null $expression
     */
    public function __construct($expression = null)
    {
        $this->set($expression);
    }

    /**
     * @return string
     */
    public function get()
    {
        return $this->expression;
    }

    /**
     * @param string $expression
     */
    public function set(string $expression = null)
    {
        if ($expression) {
            $this->expression = $expression;
        }
    }
}