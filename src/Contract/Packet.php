<?php

namespace Flower\Contract;

interface Packet
{
    function pack($data);
    function unpack(string $string);
}
