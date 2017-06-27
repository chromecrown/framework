<?php

namespace Wpt\Framework\Database;

interface ModelInterface
{
    function use ($pool);

    function call(callable $callback, $sql, $bindId = null, $async = true);

    function query($sql, $bindId = null);

    function begin();

    function commit($uuid);

    function rollback($uuid);
}
