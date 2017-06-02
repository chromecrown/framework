<?php

namespace Flower\Contract;

interface Pool
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
