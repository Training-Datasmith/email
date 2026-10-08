<?php

class EmailDriverSendmailTest extends EmailTestCase
{
	public function testSendmailFailure()
	{
		$email = $this->forgeDriver('sendmail', array(
			'sendmail_path' => PHP_BINARY.' '.__DIR__.'/bin/fail-mail.php',
		));
		$email->from('from@example.com');
		$email->to('to@example.com');
		$email->subject('x');
		$email->body('x');
		$this->setExpectedException('Email\SendmailFailedException');
		$email->send();
	}
}
