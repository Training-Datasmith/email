<?php

class EmailDriverCoreTest extends EmailTestCase
{
	protected function readyNoop(array $config = array())
	{
		$email = $this->forgeDriver('noop', $config);
		$email->from('from@example.com', 'From');
		$email->to('to@example.com');
		$email->subject('Subject');
		$email->body('Body text');
		return $email;
	}

	public function testPlainSendViaNoop()
	{
		$email = $this->readyNoop();
		$this->assertTrue($email->send());
		$log = $this->noopLogText();
		$this->assertContains('to@example.com', $log);
		$this->assertContains('Subject: Subject', $log);
		$this->assertContains('Content-Type: text/plain', $log);
		$this->assertContains('Body text', $log);
	}

	public function testMissingRecipients()
	{
		$email = $this->forgeDriver('noop');
		$email->from('from@example.com');
		$email->body('x');
		$this->setExpectedException('FuelException');
		$email->send();
	}

	public function testMissingFrom()
	{
		$email = $this->forgeDriver('noop');
		$email->to('to@example.com');
		$email->body('x');
		$this->setExpectedException('FuelException');
		$email->send();
	}

	public function testValidationFailure()
	{
		$email = $this->forgeDriver('noop');
		$email->from('from@example.com');
		$email->to('bad');
		$email->body('x');
		$this->setExpectedException('Email\EmailValidationFailedException');
		$email->send();
	}

	public function testHeaderZeroIsKept()
	{
		$email = $this->readyNoop();
		$email->header('X-Zero', '0');
		$email->send();
		$this->assertContains('X-Zero: 0', $this->noopLogText());
	}

	public function testAssociativeRecipientNameEncoded()
	{
		$email = $this->forgeDriver('noop');
		$email->from('from@example.com');
		$email->to(array('email' => 'to@example.com', 'name' => 'José'));
		$email->body('x');
		$email->send();
		$recipient = $email->get_to();
		$this->assertContains('=?', $recipient['to@example.com']['name']);
	}

	public function testForceToRewritesRecipients()
	{
		$email = $this->forgeDriver('noop', array('force_to' => 'sink@example.com'));
		$email->from('from@example.com');
		$email->to('to@example.com');
		$email->cc('cc@example.com');
		$email->body('x');
		$email->send();
		$log = $this->noopLogText();
		$this->assertContains('sink@example.com', $log);
		$this->assertNotContains('to@example.com', $log);
	}

	public function testInvalidEncoding()
	{
		$email = $this->readyNoop(array('encoding' => 'uuencode'));
		$this->setExpectedException('Email\InvalidEmailStringEncoding');
		$email->send();
	}

	public function testSmtpDotStuffingSinglePrefix()
	{
		$server = $this->startSmtpServer('normal');
		try
		{
			$email = $this->forgeDriver('smtp', array(
				'smtp' => array('host' => '127.0.0.1', 'port' => $server['port'], 'timeout' => 3),
			));
			$email->from('from@example.com');
			$email->to('to@example.com');
			$email->subject('dot');
			$email->body(".dot\nnormal");
			$email->send();
			$transcript = $this->readSmtpTranscript($server);
			$this->assertContains('C: ..dot', $transcript);
			$this->assertNotContains('C: ...dot', $transcript);
		}
		finally
		{
			$this->stopSmtpServer($server);
		}
	}
}
