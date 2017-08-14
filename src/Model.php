<?php

namespace Weipaitang\Framework;

use Weipaitang\Container\ContainerInterface;
use Weipaitang\Database\Model as AbstractModel;

/**
 * Class Model
 * @package Weipaitang\Framework
 */
class Model extends AbstractModel
{
    use TraitBase;

    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct($container);
    }
}
