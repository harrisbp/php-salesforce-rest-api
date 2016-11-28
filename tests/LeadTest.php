<?php
namespace Tests;
use Salesforce\Resource\Lead;

class LeadTest extends BaseTest {
	public function testCanInstantiateLeadResource() {
		$this->bootstrap();

		$lead = new Lead();
		$this->assertInstanceOf(Lead::class, $lead);
	}
}