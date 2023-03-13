<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorGenericBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KernelTest extends KernelTestCase
{
    public function testContainer()
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->assertNotNull($container);
    }
}
