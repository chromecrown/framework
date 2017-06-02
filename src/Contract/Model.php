<?php

namespace Flower\Contract;

interface Model
{
    function use($pool);
    function call(callable $callback, $sql, $bindId = null, $async = true);
    function query($sql, $bindId = null);
    function begin();
    function commit($uuid);
    function rollback($uuid);
}
