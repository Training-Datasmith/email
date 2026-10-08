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

	public function testSendmailReturnPathShellEscaped()
	{
		$capture = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sendmail-'.uniqid('', true);
		$this->tempFiles[] = $capture;
		putenv('MAIL_CAPTURE_FILE='.$capture);

		$email = $this->forgeDriver('sendmail', array(
			'sendmail_path' => PHP_BINARY.' '.realpath(__DIR__.'/bin/capture-mail.php'),
		));
		$email->from('from@example.com');
		$email->to('to@example.com');
		$email->subject('Subj');
		$email->body('Body');
		$email->return_path("from@example.com injected");
		$email->send();

		$raw = file_get_contents($capture);
		$this->assertTrue((bool) preg_match('/ARGV:\n(.*?)\n----STDIN----/s', $raw, $matches));
		$argv = explode("\n", trim($matches[1]));
		$fIndex = array_search('-f', $argv, true);
		$this->assertNotFalse($fIndex);
		$this->assertSame('from@example.com injected', $argv[$fIndex + 1]);
		$this->assertSame('-t', $argv[$fIndex + 2]);
	}

	public function testSendmailSubjectZeroInCapturedStream()
	{
		$capture = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sendmail-'.uniqid('', true);
		$this->tempFiles[] = $capture;
		putenv('MAIL_CAPTURE_FILE='.$capture);

		$email = $this->forgeDriver('sendmail', array(
			'sendmail_path' => PHP_BINARY.' '.realpath(__DIR__.'/bin/capture-mail.php'),
		));
		$email->from('from@example.com');
		$email->to('to@example.com');
		$email->subject('0');
		$email->body('Body');
		$email->send();

		$raw = file_get_contents($capture);
		$this->assertContains("\nSubject: 0\n", $raw);
	}
}
