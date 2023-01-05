<?php

// src/Service/InstallationService.php
namespace CommonGateway\KlantenBundle\Service;

use App\Entity\CollectionEntity;
use App\Entity\DashboardCard;
use App\Entity\Endpoint;
use CommonGateway\CoreBundle\Installer\InstallerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;
use Symfony\Component\Console\Style\SymfonyStyle;

class InstallationService implements InstallerInterface
{
    private EntityManagerInterface $entityManager;
    private SymfonyStyle $io;

    public const OBJECTS_THAT_SHOULD_HAVE_CARDS = [
        'https://klantenBundle.commonground.nu/klant.klant.schema.json',
        'https://klantenBundle.commonground.nu/klant.contactmoment.schema.json',
        'https://klantenBundle.commonground.nu/klant.natuurlijkPersoon.schema.json'
    ];
    //

    public const SCHEMAS_THAT_SHOULD_HAVE_ENDPOINTS = [
        ['reference' => 'https://klantenBundle.commonground.nu/klant.klant.schema.json',                 'path' => ['/klanten'],                    'methods' => []],
        ['reference' => 'https://klantenBundle.commonground.nu/klant.klantAdres.schema.json',                 'path' => ['/adressen'],                      'methods' => []],
        ['reference' => 'https://klantenBundle.commonground.nu/klant.contactmoment.schema.json',                 'path' => ['/contactmomenten'],                    'methods' => []],
        ['reference' => 'https://klantenBundle.commonground.nu/klant.klantContactmoment.schema.json',                 'path' => ['/klantcontactmomenten'],                    'methods' => []],
        ['reference' => 'https://klantenBundle.commonground.nu/klant.objectContactmoment.schema.json',                 'path' => ['/objectcontactmomenten'],                    'methods' => []],
        ['reference' => 'https://klantenBundle.commonground.nu/klant.klantEmail.schema.json',                 'path' => ['/emails'],                    'methods' => []],
        ['reference' => 'https://klantenBundle.commonground.nu/klant.klantTelefoon.schema.json',                 'path' => ['/telefoons'],                    'methods' => []],
        ['reference' => 'https://klantenBundle.commonground.nu/klant.natuurlijkPersoon.schema.json',                 'path' => ['/natuurlijkpersonen'],                    'methods' => []],
        ['reference' => 'https://klantenBundle.commonground.nu/klant.verblijfAdres.schema.json',                 'path' => ['/verblijfadressen'],                    'methods' => []],
        ['reference' => 'https://klantenBundle.commonground.nu/klant.subVerblijfBuitenland.schema.json',                 'path' => ['/subverblijfadressen'],                    'methods' => []],
    ];

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Set symfony style in order to output to the console
     *
     * @param SymfonyStyle $io
     * @return self
     */
    public function setStyle(SymfonyStyle $io): self
    {
        $this->io = $io;

        return $this;
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

    private function createEndpoints($objectsThatShouldHaveEndpoints): array
    {
        $endpointRepository = $this->entityManager->getRepository('App:Endpoint');
        $endpoints = [];
        foreach($objectsThatShouldHaveEndpoints as $objectThatShouldHaveEndpoint) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $objectThatShouldHaveEndpoint['reference']]);
            if (!$endpointRepository->findOneBy(['name' => $entity->getName()])) {
                $endpoint = new Endpoint($entity, $objectThatShouldHaveEndpoint['path'], $objectThatShouldHaveEndpoint['methods']);

                $this->entityManager->persist($endpoint);
                $this->entityManager->flush();
                $endpoints[] = $endpoint;
            }
        }
        (isset($this->io) ? $this->io->writeln(count($endpoints).' Endpoints Created'): '');

        return $endpoints;
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

    private function addSchemasToCollection(CollectionEntity $collection, string $schemaPrefix): CollectionEntity
    {
        $entities = $this->entityManager->getRepository('App:Entity')->findByReferencePrefix($schemaPrefix);
        foreach($entities as $entity) {
            $entity->addCollection($collection);

            // set all entity maxDepth to 5
            $this->setEntityMaxDepth();
        }
        return $collection;
    }

    private function createCollections(): array
    {
        $collectionConfigs = [
            ['name' => 'Klant',  'prefix' => 'klant', 'schemaPrefix' => 'https://klantenBundle.commonground.nu/klant'],
        ];
        $collections = [];
        foreach($collectionConfigs as $collectionConfig) {
            $collectionsFromEntityManager = $this->entityManager->getRepository('App:CollectionEntity')->findBy(['name' => $collectionConfig['name']]);
            if(count($collectionsFromEntityManager) == 0){
                $collection = new CollectionEntity($collectionConfig['name'], $collectionConfig['prefix'], 'KissBundle');
            } else {
                $collection = $collectionsFromEntityManager[0];
            }
            $collection = $this->addSchemasToCollection($collection, $collectionConfig['schemaPrefix']);
            $this->entityManager->persist($collection);
            $this->entityManager->flush();
            $collections[$collectionConfig['name']] = $collection;
        }
        (isset($this->io) ? $this->io->writeln(count($collections).' Collections Created'): '');
        return $collections;
    }

    public function createDashboardCards($objectsThatShouldHaveCards)
    {
        foreach ($objectsThatShouldHaveCards as $object) {
            (isset($this->io) ? $this->io->writeln('Looking for a dashboard card for: ' . $object) : '');
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $object]);
            if (
                !$dashboardCard = $this->entityManager->getRepository('App:DashboardCard')->findOneBy(['entityId' => $entity->getId()])
            ) {
                $dashboardCard = new DashboardCard();
                $dashboardCard->setType('schema');
                $dashboardCard->setEntity('App:Entity');
                $dashboardCard->setObject('App:Entity');
                $dashboardCard->setName($entity->getName());
                $dashboardCard->setDescription($entity->getDescription());
                $dashboardCard->setEntityId($entity->getId());
                $dashboardCard->setOrdering(1);
                $this->entityManager->persist($dashboardCard);
                (isset($this->io) ? $this->io->writeln('Dashboard card created') : '');
                continue;
            }
            (isset($this->io) ? $this->io->writeln('Dashboard card found') : '');
        }
    }

    public function checkDataConsistency()
    {

        // Lets create some genneric dashboard cards
        $this->createDashboardCards($this::OBJECTS_THAT_SHOULD_HAVE_CARDS);

        $this->createCollections();

        // Let create some endpoints
        $this->createEndpoints($this::SCHEMAS_THAT_SHOULD_HAVE_ENDPOINTS);

        $this->entityManager->flush();

        // Lets see if there is a generic search endpoint


    }
}
