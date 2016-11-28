<?php
namespace Tests;
use Salesforce\Client;
use Salesforce\Exception;
use Salesforce\Resource\Account;

class AccountTest extends BaseTest
{
    public function testCanInstantiateAccountResource()
    {
        $this->bootstrap();

        $account = new Account();
        $this->assertInstanceOf(Account::class, $account);
    }

    public function testSetUnknownAccountField()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unknown attribute: asdasd');

        $account = new Account();
        $account->asdasd = "asdasd";
    }

    public function testCanFindAccountById()
    {
        $account = Account::find('00141000005X5FFAA0');
        
        print_r($account);
    }
}