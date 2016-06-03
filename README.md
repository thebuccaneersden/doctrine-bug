# doctrine bug example

A sample project that demonstrates a bug with Doctrine's use of ```spl_object_hash()``` and PHP's garbage collection.

### Summary

Our team was starting to notice that long running unit tests would suddenly start breaking with no clear explanation as to why. After looking into it, we started to suspect the issue was Doctrine gets confused when saving a new entity entity and not writing the record to the database (thinking it already has done so). Outcome of this is that when you then try to seek this entity within the database, it can't find it and then fails the unit test assertion.

Further investigation found that this is related to PHP's garbage collector (when it kicks in) and Doctrine's use of ```spl_object_hash()``` to generate an ID as a reference to the entity for the entities state (See ```UnitOfWork.php```) for persisting entities.

To further illustrate what's happening, this is what should be happening (code below with resulting DB queries mapped to them):

```php
public function myTest()
{
	$repo = $this->entityManager->getRepository("Test");
	$resource = new Test();

	$resource->setName('a');
	$this->entityManager->persist($resource);
	$this->entityManager->flush($resource);
	$this->entityManager->refresh($resource);
	// Query     START TRANSACTION
	// Query     INSERT INTO test (name) VALUES ('a')
	// Query     COMMIT
	// Query     SELECT t0.id AS id1, t0.name AS name2 FROM test t0 WHERE t0.id = 1

	$found = $repo->findOneBy(['name' => 'a']);
	// Query     SELECT t0.id AS id1, t0.name AS name2 FROM test t0 WHERE t0.name = 'a' LIMIT 1

	$this->assertNotNull($found);
	// $found is an object, not null - PASS!

	$this->entityManager->getConnection()->exec("TRUNCATE TABLE `test`;");
	// Query     TRUNCATE TABLE `test`
}

//
// Test passed. Good...
//
```

When we hit the bug, this is what actually happens:

```php
public function myTest()
{
	$repo = $this->entityManager->getRepository("Test");
	$resource = new Test();

	$resource->setName('a');
	$this->entityManager->persist($resource);
	$this->entityManager->flush($resource);
	$this->entityManager->refresh($resource);
	// Query     SELECT t0.id AS id1, t0.name AS name2 FROM test t0 WHERE t0.id = 1

	$found = $repo->findOneBy(['name' => 'a']);
	// Query     SELECT t0.id AS id1, t0.name AS name2 FROM test t0 WHERE t0.name = 'a' LIMIT 1

	$this->assertNotNull($found);
	// $found is null - FAIL!

	$this->entityManager->getConnection()->exec("TRUNCATE TABLE `test`;");
	// Query     TRUNCATE TABLE `test`
}

//
// Test failed!
// Notice, it never ran the INSERT transaction on the database! It think's this
// entity has already been stored
//
```

This project was an attempt to isolate the issue. As mentioned, this bug would normally happen (seemingly) randomly, so our code forces garbage collection in order to make the issue apparent much sooner by using ```gc_collect_cycles();``` (forces collection of any existing garbage cycles) in the setUp function.

[http://php.net/manual/en/function.gc-collect-cycles.php](http://php.net/manual/en/function.gc-collect-cycles.php)


### TL;DR

By using ```spl_object_hash()``` to persist entities, Doctrine can get confused about whether it has written data to the database after PHP's garbage collector kicks in as it causes a collision in the entity hash table and it ends up resolving to another existing entity that was already stored to the database rather than realizing that this is a new entity object.

### Steps to reproduce

* Clone this repo
* Make sure you have a MySQL/MariaDB around for the unit tests to talk to
* Make sure you have ```composer``` installed ([getcomposer.org](http://getcomposer.org))
* Go to the root of the project and run: ```composer install``` (this installs the vendor libraries)
* Modify the database configuration in the file: ```application/libraries/DoctrineSimpleton.php```

At this point, you should be able to run the unit tests that demonstrate how Doctrine fails. There are two unit tests which cover two scenarios:

1. Doctrine + PHP Unit only
2. Doctrine + PHP Unit + CIUnit (for Code Igniter)

````
Please note: The reason for having a seperate test configuration for CIUnit
is because we noticed that this Doctrine issue was still failing but in a
slightly different way when Code Igniter is thrown into the mix.
````

### Result

This is what happens when you run the test for Doctrine + PHP Unit only:

```$ ./vendor/bin/phpunit --repeat 10000 -c ./tests/phpunit-plain.xml```

![alt tag](http://i.imgur.com/JS7lQfC.gif)

This is what happens when you run the test for Doctrine + PHP Unit mixed in with Code Igniter:

```$ ./vendor/bin/phpunit --repeat 10000 -c ./tests/phpunit-ci.xml```

![alt tag](http://i.imgur.com/BDp2fjC.gif)