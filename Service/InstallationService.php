<?php

// src/Service/InstallationService.php
namespace CommonGateway\KlantenBundle\Service;

use CommonGateway\CoreBundle\Installer\InstallerInterface;
use Doctrine\ORM\EntityManagerInterface;

class InstallationService implements InstallerInterface
{
    private EntityManagerInterface $entityManager;
    
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function install()
    {
        $this->checkDataConsistency();
    }

    public function update()
    {
        $this->checkDataConsistency();
    }

    public function uninstall()
    {
        // Do some cleanup
    }

    public function setEntityMaxDepth()
    {
        $entities = $this->entityManager->getRepository('App:Entity')->findAll();
        foreach ($entities as $entity) {
            // set maxDepth for an entity to 5
            $entity->setMaxDepth(5);
            $this->entityManager->persist($entity);
        }
    }

    public function checkDataConsistency()
    {
    
        $this->entityManager->flush();

        // Lets see if there is a generic search endpoint


    }
}
