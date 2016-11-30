<?php
namespace Tests;
use Salesforce\Resource;

class ResourceTest extends BaseTest {
	public $resources;
	public $testIds;

	function __construct() {
		/*
		$this->resources = [
			'Account',
			'Lead',
		];
		*/

		$this->resources = [
			'Account' => gethostname() != 'DESKTOP-SABKJE0' ? '00141000005X5FFAA0' : '0014100000AOqVEAA1',
			'Lead' => gethostname() != 'DESKTOP-SABKJE0' ? '00141000005X5FFAA0' : '00Q4100000EryKa',
		];
	}

	public function testCanInstantiateResource() {
		$this->bootstrap();

		foreach ($this->resources as $class => $id) {
			$class = "\\Salesforce\\Resource\\$class";
			$resource = new $class();
			$this->assertInstanceOf($class, $resource);
		}
	}

	public function testCanFindById() {
		var_dump($this->testIds);


		foreach ($this->resources as $class => $id) {
			$class = "\\Salesforce\\Resource\\$class";
			$resource = $class::find($id);
			print_r($resource);
			$this->assertInstanceOf($class, $resource);
		}
	}
}