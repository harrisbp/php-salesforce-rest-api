<?php
namespace Tests;
use Salesforce\Client;
use Salesforce\Exception;
use Salesforce\Resource\Lead;

class LeadTest extends BaseTest
{
    public function testCanInstantiateLeadResource()
    {
        $this->bootstrap();

        $lead = new Lead();
        $this->assertInstanceOf(Lead::class, $lead);
    }

    public function testSetUnknowLeadField()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unknown attribute: asdasd');

        $lead = new Lead();
        $lead->asdasd = "asdasd";
    }
}