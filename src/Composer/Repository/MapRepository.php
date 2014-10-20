<?php

namespace Composer\Repository;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\EventDispatcher\EventDispatcher;

/**
 * Map repository.
 *
 * @author Rasmus Schultz <rasmus@mindplay.dk>
 */
class MapRepository implements RepositoryInterface, ManagerAware
{
    /**
     * Map of repositories
     *
     * @var RepositoryInterface[] map where package name => repository
     */
    private $repositories = array();

    /**
     * @var array[] map where package name => repository configuration
     */
    private $map;

    /**
     * @var string repository map url
     */
    private $url;

    /**
     * @var RepositoryManager
     * @see setRepositoryManager()
     */
    private $manager;

    public function __construct(array $repoConfig, IOInterface $io, Config $config, EventDispatcher $dispatcher = null)
    {
        if (!isset($repoConfig['url'])) {
            throw new InvalidRepositoryException('No url given for Composer map-repository');
        }

        $url = $repoConfig['url'];
        
        if (!preg_match('{^[\w.]+\??://}', $url)) {
            // assume http as the default protocol
            $url = 'http://'.$url;
        }
        $url = rtrim($url, '/');

        if ('https?' === substr($url, 0, 6)) {
            $url = (extension_loaded('openssl') ? 'https' : 'http') . substr($url, 6);
        }

        $urlBits = parse_url($url);
        if ($urlBits === false || empty($urlBits['scheme'])) {
            throw new \UnexpectedValueException('Invalid url given for Composer repository: '.$url);
        }
        
        $this->url = $url;
    }

    /**
     * @param RepositoryManager $manager
     */
    public function setRepositoryManager(RepositoryManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Load the repository map
     *
     * @see $map
     */
    private function loadMap()
    {
        if ($this->map === null) {
            $data = @file_get_contents($this->url);

            if ($data === false) {
                throw new InvalidRepositoryException("repository map not found: {$this->url}");
            }

            $this->map = json_decode($data, true);
        }
    }

    /**
     * Initialize all mapped repositories
     *
     * @see $repositories
     */
    private function initRepositories()
    {
        $this->loadMap();

        foreach (array_keys($this->map) as $name) {
            $this->getRepository($name);
        }
    }
    
    /**
     * @param string $name package name
     *
     * @throws InvalidRepositoryException
     *
     * @return RepositoryInterface|null
     */
    private function getRepository($name)
    {
        $this->loadMap();

        if (isset($this->repositories[$name])) {
            return $this->repositories[$name];
        }

        if (isset($this->map[$name])) {
            $type = $this->map[$name]['type'];

            $repo = $this->manager->createRepository($type, $this->map[$name]);

            $this->repositories[$name] = $repo;

            return $repo;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function hasPackage(PackageInterface $package)
    {
        $name = $package->getName();
        
        $repo = $this->getRepository($name);
        
        if ($repo) {
            if ($repo->hasPackage($package)) {
                return true;
            }

            throw new InvalidRepositoryException("invalid repository for package {$name} in map: {$this->url}");
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function findPackage($name, $version)
    {
        $repo = $this->getRepository($name);
        
        if ($repo) {
            return $repo->findPackage($name, $version);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function findPackages($name, $version = null)
    {
        $repo = $this->getRepository($name);
        
        if ($repo) {
            return $repo->findPackages($name, $version);
        }
        
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function search($query, $mode = 0)
    {
        $this->initRepositories();
        
        $matches = array();
        foreach ($this->repositories as $repository) {
            $matches[] = $repository->search($query, $mode);
        }

        return $matches ? call_user_func_array('array_merge', $matches) : array();
    }

    /**
     * {@inheritdoc}
     */
    public function getPackages()
    {
        $this->initRepositories();

        $packages = array();
        foreach ($this->repositories as $repository) {
            $packages[] = $repository->getPackages();
        }
        
        return $packages ? call_user_func_array('array_merge', $packages) : array();
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        $this->initRepositories();

        $total = 0;
        foreach ($this->repositories as $repository) {
            $total += $repository->count();
        }

        return $total;
    }
}
