<?php

namespace Flower\Contract;

interface LogHandler
{
    public function write(array $data);
}
