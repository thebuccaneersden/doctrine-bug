<?php

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;
use Doctrine\DBAL\Configuration;

class DoctrineSingleton
{
    /**
     * @var Singleton The reference to *Singleton* instance of this class
     */
    private static $instance;
    public $entityMan;
    public $rand;
    
    /**
     * Returns the *Singleton* instance of this class.
     *
     * @return Singleton The *Singleton* instance.
     */
    public static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
            static::$instance->connect();
            static::$instance->rand = rand(0,999999);
        }

        return static::$instance;
    }

    protected function connect()
    {
        $config = Setup::createAnnotationMetadataConfiguration(array(__DIR__."/src"), true ); //, $isDevMode);

		$connectionParams = array(
			'dbname' => 'doctrine_bug',
			'user' => 'root',
			'password' => '',
			'host' => 'localhost',
			'driver' => 'mysqli',
		);

		// obtaining the entity manager
		static::$instance->entityMan = EntityManager::create($connectionParams, $config);
    }

    /**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the `new` operator from outside of this class.
     */
    protected function __construct()
    {
    }

    /**
     * Private clone method to prevent cloning of the instance of the
     * *Singleton* instance.
     *
     * @return void
     */
    private function __clone()
    {
    }

    /**
     * Private unserialize method to prevent unserializing of the *Singleton*
     * instance.
     *
     * @return void
     */
    private function __wakeup()
    {
    }
}
