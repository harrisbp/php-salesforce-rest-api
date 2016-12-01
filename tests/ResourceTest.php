<?php
namespace Tests;
use Salesforce\Resource;

class ResourceTest extends BaseTest {
	public $resources;
	public $foundResources = [];
	public $testUserId;
	public $testOwnerId;

	function __construct() {
		$this->resources = [
			'Account' => gethostname() != 'DESKTOP-SABKJE0' ? '00141000005X5FFAA0' : '0014100000AOqVEAA1',
			'Contact' => gethostname() != 'DESKTOP-SABKJE0' ? '' : '0034100000CkOfE',
			'Lead' => gethostname() != 'DESKTOP-SABKJE0' ? '' : '00Q4100000EryKa',
			'Opportunity' => gethostname() != 'DESKTOP-SABKJE0' ? '' : '00641000005AV3c',
			'Order' => gethostname() != 'DESKTOP-SABKJE0' ? '' : '80141000000Hhfh',
		];

		$this->testOwnerId = gethostname() != 'DESKTOP-SABKJE0' ? '' : '00541000001L9eq';
	}

	public function testCanInstantiateResource() {
		$this->bootstrap();

		foreach ($this->resources as $class => $id) {
			// Need full root namespace for dynamic instantiation
			$class = "\\Salesforce\\Resource\\$class";
			$resource = new $class();

			$this->assertInstanceOf($class, $resource);
		}
	}

	public function testCanFindById() {
		foreach ($this->resources as $class => $id) {
			if (!$id) {
				echo "\n" . 'No ' . $class . ' ID specified for this connection. Skipping find by ID test.';
				continue;
			}

			// Need full root namespace for dynamic instantiation
			$class = "\\Salesforce\\Resource\\$class";
			$resource = $class::find($id);

			//print_r($resource->asArray());

			if ($resource) {
				$this->foundResources[$class] = $resource;
			}

			$this->assertInstanceOf($class, $resource);
		}
	}

	protected function getResourceById($class, $id) {
		if (!empty($this->foundResources[$class])) {
			$resource = $this->foundResources[$class];
		} else {
			// Need full root namespace for dynamic instantiation
			$class = "\\Salesforce\\Resource\\$class";
			$resource = $class::find($id);
			if ($resource) {
				$this->foundResources[$class] = $resource;
				return $resource;
			}
		}
	}

	public function testCanUpdateResource() {
		foreach ($this->resources as $class => $id) {
			if (!$id) {
				echo "\n" . 'No ' . $class . ' ID specified for this connection. Skipping update test.';
				continue;
			}

			$resource = $this->getResourceById($class, $id);
			$resource->Description = 'Test Value ' . rand(1, 999);

			$result = $resource->save();
			$this->assertInternalType('string', $result);
		}
	}

	public function testCanGetAttributeValue() {
		foreach ($this->resources as $class => $id) {
			if (!$id) {
				echo "\n" . 'No ' . $class . ' ID specified for this connection. Skipping get attribute value test.';
				continue;
			}

			$resource = $this->getResourceById($class, $id);
			$id = $resource->Id;
			$this->assertInternalType('string', $id);

			if ($id) {
				//echo "\n" . 'Found Id attribute for resource ' . $class . ': ' . $name . "\n";
			} else {
				echo "\n\n" . '!! Could not access Id attribute for resource ' . $class . "\n";
			}
		}
	}

	public function testSetAttributeValue() {
		foreach ($this->resources as $class => $id) {
			if (!$id) {
				echo "\n" . 'No ' . $class . ' ID specified for this connection. Skipping set attribute value test.';
				continue;
			}

			$resource = $this->getResourceById($class, $id);
			$resource->Description = 'Test Description';
		}
	}

	public function testCanCreateResource() {
		if (!$this->testOwnerId) {
			echo "\n" . '!! Can\'t create records without setting Owner ID';
		}

		$createdIds = [];

		foreach ($this->resources as $class => $id) {
			// Need full root namespace for dynamic instantiation
			$fullClass = "\\Salesforce\\Resource\\$class";
			$resource = new $fullClass();

			foreach ($resource::getMetadata()->getAllFields() as $field) {
				if ($field->required && $field->canCreate && $field->type != $resource->getMetadata()::REFERENCE_TYPE) {
					$resource->{$field->name} = $resource->getMetadata()->getSampleValue($field);
				}

				if ($field->name == 'AccountId') {
					$resource->AccountId = $this->resources['Account'];
				}
			}

			$resource->OwnerId = $this->testOwnerId;

			// Most or all resources have a description field
			// Go ahead and populate it in case there are no required fields
			if ($resource::getMetadata()->hasField('Description')) {
				$resource->Description = 'Test Value';
			}

			$id = $resource->save();
			$this->assertInternalType('string', $id);

			if ($id) {
				$createdIds[$class] = $id;
			} else {
				echo "\n" . '!! Could not create new resource ' . $class . "\n";
			}
		}

		return $createdIds;
	}

	/**
	 * @depends testCanCreateResource
	 */
	public function testCanDeleteResource($createdIds) {
		// Attempt to delete the accounts that were created in the create test
		foreach ($createdIds as $class => $id) {
			// Need full root namespace for dynamic instantiation
			$class = "\\Salesforce\\Resource\\$class";
			$resource = $class::find($id);
			$result = $resource->delete();
			$this->assertEquals(true, $result);
		}
	}
}