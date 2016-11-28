<?php
namespace Tests;
use Salesforce\Resource\Account;

class AccountTest extends BaseTest {
	public function testCanInstantiateAccountResource() {
		$this->bootstrap();

		$account = new Account();
		$this->assertInstanceOf(Account::class, $account);
	}

	public function testCanFindAccountById() {
		$account = Account::find(gethostname() != 'DESKTOP-SABKJE0' ? '00141000005X5FFAA0' : '0014100000AOqVEAA1');

		//print_r($account);
	}
}