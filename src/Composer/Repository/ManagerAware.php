<?php

namespace Composer\Repository;

interface ManagerAware {
    public function setRepositoryManager(RepositoryManager $manager);
}
