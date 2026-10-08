<?php

class AllTests
{
	public static function suite()
	{
		$suite = new PHPUnit_Framework_TestSuite('Fuel Email package');

		$suite->addTestFile(__DIR__.'/EmailForgeTest.php');
		$suite->addTestFile(__DIR__.'/EmailDriverCoreTest.php');
		$suite->addTestFile(__DIR__.'/EmailDriverHtmlTest.php');
		$suite->addTestFile(__DIR__.'/EmailDriverAttachmentTest.php');
		$suite->addTestFile(__DIR__.'/EmailDriverMailTest.php');
		$suite->addTestFile(__DIR__.'/EmailDriverSendmailTest.php');
		$suite->addTestFile(__DIR__.'/EmailDriverSmtpTest.php');
		$suite->addTestFile(__DIR__.'/EmailDriverProvidersTest.php');

		return $suite;
	}
}
