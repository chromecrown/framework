<?php

namespace Wpt\Framework\Log;

interface LogHandlerInterface
{
    public function write(array $data);
}
