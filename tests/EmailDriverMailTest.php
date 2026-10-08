<?php

class EmailDriverMailTest extends EmailTestCase
{
	public function testMailCapture()
	{
		$capture = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mail-'.uniqid('', true);
		$this->tempFiles[] = $capture;
		putenv('MAIL_CAPTURE_FILE='.$capture);

		$email = $this->forgeDriver('mail');
		$email->from('from@example.com');
		$email->to('to@example.com');
		$email->subject('Subj');
		$email->body('Body');
		$email->header('X-Test', '1');
		$this->assertTrue($email->send());

		$raw = file_get_contents($capture);
		$this->assertContains('Body', $raw);
		$this->assertContains('X-Test: 1', $raw);
	}

	public function testReturnPathShellEscaped()
	{
		$capture = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mail-'.uniqid('', true);
		$this->tempFiles[] = $capture;
		putenv('MAIL_CAPTURE_FILE='.$capture);

		$email = $this->forgeDriver('mail');
		$email->from('from@example.com');
		$email->to('to@example.com');
		$email->subject('Subj');
		$email->body('Body');
		$email->return_path('from@example.com injected');
		$email->send();

		$raw = file_get_contents($capture);
		$this->assertContains("-f\nfrom@example.com injected", $raw);
	}
}
