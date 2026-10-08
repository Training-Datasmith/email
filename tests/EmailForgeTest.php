<?php

class EmailForgeTest extends EmailTestCase
{
	public function testForgeUsesPackageDefaults()
	{
		$email = Email\Email::forge();
		$this->assertInstanceOf('Email\Email_Driver_Mail', $email);
		$this->assertSame('utf-8', $email->get_config('charset'));
		$this->assertSame('8bit', $email->get_config('encoding'));
		$this->assertSame(Email\Email::P_NORMAL, $email->get_config('priority'));
	}

	public function testForgeOverrideDriver()
	{
		$email = Email\Email::forge(null, array('driver' => 'noop', 'smtp' => array('host' => '127.0.0.1')));
		$this->assertInstanceOf('Email\Email_Driver_Noop', $email);
		$this->assertSame('127.0.0.1', $email->get_config('smtp.host'));
	}

	public function testDriverNameCaseInsensitive()
	{
		$this->assertInstanceOf('Email\Email_Driver_Noop', Email\Email::forge(null, array('driver' => 'NoOp')));
	}

	public function testUnknownDriverThrows()
	{
		$this->setExpectedException('FuelException');
		Email\Email::forge(null, array('driver' => 'nope'));
	}

	public function testCallStaticProxy()
	{
		Email\Email::$_instance = false;
		Email\Email::from('from@example.com');
		Email\Email::to('to@example.com');
		$this->assertSame('from@example.com', Email\Email::$_instance->get_from()['email']);
		$this->assertArrayHasKey('to@example.com', Email\Email::$_instance->get_to());
	}

	public function testCallStaticUnknownMethod()
	{
		$this->setExpectedException('BadMethodCallException');
		Email\Email::not_a_method();
	}
}
