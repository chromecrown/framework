<?php

namespace Flower\Log;

interface LogHandlerInterface
{
    public function write(array $data);
}
