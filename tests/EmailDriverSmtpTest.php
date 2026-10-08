<?php

class EmailDriverSmtpTest extends EmailTestCase
{
	protected function smtpEmail($port, $extra = array())
	{
		$config = Arr::merge(array(
			'smtp' => array('host' => '127.0.0.1', 'port' => $port, 'timeout' => 3),
		), $extra);
		$email = $this->forgeDriver('smtp', $config);
		$email->from('from@example.com');
		$email->to('to@example.com');
		$email->subject('smtp');
		$email->body('hello');
		return $email;
	}

	public function testSmtpHappyPath()
	{
		$server = $this->startSmtpServer('normal');
		try
		{
			$this->smtpEmail($server['port'])->send();
			$transcript = $this->readSmtpTranscript($server);
			$this->assertContains('EHLO', $transcript);
			$this->assertContains('MAIL FROM', $transcript);
			$this->assertContains('RCPT TO', $transcript);
			$this->assertContains('DATA', $transcript);
			$this->assertContains('QUIT', $transcript);
		}
		finally
		{
			$this->stopSmtpServer($server);
		}
	}

	public function testSmtpAuth()
	{
		$server = $this->startSmtpServer('normal');
		try
		{
			$this->smtpEmail($server['port'], array(
				'smtp' => array('username' => 'user', 'password' => 'pass'),
			))->send();
			$transcript = $this->readSmtpTranscript($server);
			$this->assertContains('AUTH LOGIN', $transcript);
		}
		finally
		{
			$this->stopSmtpServer($server);
		}
	}

	public function testSmtpAuthFailureDisconnects()
	{
		$server = $this->startSmtpServer('auth_fail');
		try
		{
			$email = $this->smtpEmail($server['port'], array(
				'smtp' => array('username' => 'user', 'password' => 'bad'),
			));
			try
			{
				$email->send();
				$this->fail('Expected authentication failure');
			}
			catch (Email\SmtpAuthenticationFailedException $e)
			{
			}
			$transcript = $this->readSmtpTranscript($server);
			$this->assertContains('QUIT', $transcript);
		}
		finally
		{
			$this->stopSmtpServer($server);
		}
	}

	public function testSmtpRcptFailureDisconnects()
	{
		$server = $this->startSmtpServer('rcpt_fail');
		try
		{
			$email = $this->smtpEmail($server['port']);
			try
			{
				$email->send();
				$this->fail('Expected RCPT failure');
			}
			catch (Email\SmtpCommandFailureException $e)
			{
			}
			$transcript = $this->readSmtpTranscript($server);
			$this->assertContains('QUIT', $transcript);
		}
		finally
		{
			$this->stopSmtpServer($server);
		}
	}

	public function testSmtpTimeout()
	{
		$server = $this->startSmtpServer('timeout');
		try
		{
			$email = $this->smtpEmail($server['port'], array(
				'smtp' => array('timeout' => 1),
			));
			$this->setExpectedException('Email\SmtpTimeoutException');
			$email->send();
		}
		finally
		{
			$this->stopSmtpServer($server);
		}
	}
}
