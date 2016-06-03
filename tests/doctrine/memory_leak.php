<?php

require_once( 'application/models/Test.php' );
require_once( 'application/libraries/DoctrineSingleton.php' );

class DoctrineBugTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var EntityManager $entityManager
     */
    private $entityManager;

    /**
     * Wipes out all data from the cdn_resource table
     */
    public function setUp()
    {
		gc_collect_cycles();

		$connectionParams = array(
			'dbname' => 'doctrine_bug',
			'user' => 'root',
			'password' => '',
			'host' => 'localhost',
			'driver' => 'mysqli',
		);

		// obtaining the entity manager
		$this->entityManager = DoctrineSingleton::getInstance()->entityMan;
    }

    public function testDb()
    {
        /** @var \Doctrine\ORM\EntityRepository $repo */
        $repo = $this->entityManager->getRepository("Test");

        $resource = new Test();

        $resource->setName('a');
        $this->entityManager->persist($resource);
        $this->entityManager->flush($resource);
        $this->entityManager->refresh($resource);

        $found = $repo->findOneBy(array(
            'name' => 'a',
        ));

        $this->assertNotNull($found);
        $this->entityManager->getConnection()->exec("truncate table `test`;");
    }
}
