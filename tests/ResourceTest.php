<?php
namespace Tests;
use Salesforce\Resource;

class ResourceTest extends BaseTest {
	public $resources;
	public $foundResources = [];
	public $testUserId;
	public $testOwnerId;

	function __construct() {
		parent::__construct();

		$this->resources = [
			'Account' => gethostname() != 'DESKTOP-V7KJORT' ? '00141000005X5FFAA0' : '0014100000AOqVEAA1',
			'Contact' => gethostname() != 'DESKTOP-V7KJORT' ? '' : '0034100000CkOfE',
			'Lead' => gethostname() != 'DESKTOP-V7KJORT' ? '' : '00Q4100000EryKa',
			'Opportunity' => gethostname() != 'DESKTOP-V7KJORT' ? '' : '00641000005AV3c',
			'Order' => gethostname() != 'DESKTOP-V7KJORT' ? '' : '80141000000Hhfh',
			'Product2' => gethostname() != 'DESKTOP-V7KJORT' ? '' : '01t41000001RPM3',
			'Solution' => gethostname() != 'DESKTOP-V7KJORT' ? '' : '50141000000HFx0',
			/* "Case" is a protected PHP keyword. May need to implelent separate string for class name */
			//'Case' => gethostname() != 'DESKTOP-V7KJORT' ? '' : '50041000002euZu',
			'Campaign' => gethostname() != 'DESKTOP-V7KJORT' ? '' : '70141000000R2VF',
			/* Needs contract status to create new */
			//'Contract' => gethostname() != 'DESKTOP-V7KJORT' ? '' : '80041000000HJyG',
			'Asset' => gethostname() != 'DESKTOP-V7KJORT' ? '' : '02i41000000Und6',
			//'Folder' => gethostname() != 'DESKTOP-V7KJORT' ? '' : '00541000001L9eq',
			//'Document' => gethostname() != 'DESKTOP-V7KJORT' ? '' : '015410000013pG7',
		];

		$this->testOwnerId = gethostname() != 'DESKTOP-V7KJORT' ? '' : '00541000001L9eq';
	}


	public function testCanSearchResource() {
		$this->bootstrap();

		foreach ($this->resources as $class => $id) {
			// Need full root namespace for dynamic instantiation
			$search = new \Salesforce\Search;

			$search->select('Id')->from($class)->in('all')->find($id)->execute();

			$this->assertInternalType('array', $search->records());
		}
	}

	public function testCanQueryResource() {
		$this->bootstrap();

		foreach ($this->resources as $class => $id) {
			// Need full root namespace for dynamic instantiation
			$query = new \Salesforce\Query;

			$query->select('Id')->from($class)->where('Id', $id)->execute();

			$this->assertInternalType('array', $query->records());
		}
	}

	public function testCanDescribeResource() {
		$this->bootstrap();

		foreach ($this->resources as $class => $id) {
			// Need full root namespace for dynamic instantiation
			$class = "\\Salesforce\\Resource\\$class";
			$resource = new $class();
			$this->assertInternalType('object', $resource->describe());
		}
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
			$metadata = $resource::getMetadata();

			foreach ($metadata->getAllFields() as $field) {
				if ($field->required && $field->canCreate && $field->type != $metadata::REFERENCE_TYPE) {
					$resource->{$field->name} = $metadata->getSampleValue($field);
				}

				if ($field->name == 'AccountId') {
					$resource->AccountId = $this->resources['Account'];
				}
			}

			$resource->OwnerId = $this->testOwnerId;

			// Most or all resources have a description field
			// Go ahead and populate it in case there are no required fields
			if ($metadata->hasField('Description')) {
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