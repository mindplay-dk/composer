<?php

namespace Composer\Test\Repository;

use Composer\Factory;
use Composer\Package\Package;
use Composer\Repository\InvalidRepositoryException;
use Composer\Repository\MapRepository;
use Composer\IO\NullIO;
use Composer\Config;
use Composer\Repository\RepositoryManager;
use Composer\TestCase;

class MapRepositoryTest extends TestCase
{
    public function testRepositoryMap()
    {
        $config = new Config();

        $rm = new RepositoryManager(
            $this->getMock('Composer\IO\IOInterface'),
            $this->getMock('Composer\Config'),
            $this->getMockBuilder('Composer\EventDispatcher\EventDispatcher')->disableOriginalConstructor()->getMock()
        );
        $rm->setRepositoryClass('package', 'Composer\Repository\PackageRepository');
        $rm->setRepositoryClass('map', 'Composer\Repository\MapRepository');

        /** @var MapRepository $repo */
        $repo = $rm->createRepository('map', array('url' => 'file:///' . __DIR__ . '/Fixtures/map.json'));

        $this->assertEquals($repo->hasPackage(new Package('foo/bar', '1.0.0.0', '1.0.0')), false, 'package foo/bar not found');
        $this->assertEquals($repo->hasPackage(new Package('test/a', '1.0.0.0', '1.0.0')), true, 'package test/a found');
        $this->assertEquals($repo->hasPackage(new Package('test/b', '2.0.0.0', '2.0.0')), true, 'package test/b found');

        try {
            $test = $repo->hasPackage(new Package('test/a', '2.0.0.0', '2.0.0'));
            $this->fail('should throw because mapped repository did not deliver as required');
        } catch (InvalidRepositoryException $e) {
            $this->assertTrue(true, 'expected exception thrown because mapped repository did not deliver as required');
        }
    }
}
