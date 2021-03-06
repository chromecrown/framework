<?php

namespace Wpt\Framework\Pool;

interface PoolInterface
{
    function connect();

    /**
     * @param array $data
     */
    function execute(array $data);

    /**
     * @return string
     */
    function getType();

    /**
     * @return string
     */
    function getName();
}
