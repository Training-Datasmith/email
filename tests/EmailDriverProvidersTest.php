<?php

class EmailDriverProvidersTest extends EmailTestCase
{
	public function testMailgunHtmlFieldIsMarkupNotMimeBlob()
	{
		$email = $this->forgeDriver('mailgun', array(
			'mailgun' => array('key' => 'k', 'domain' => 'example.com'),
		));
		$email->from('from@example.com');
		$email->to('to@example.com');
		$email->subject('html');
		$email->html_body('<p>Hello</p>');
		$email->send();

		$post = MailgunMailgun::$instance->messages->last_post;
		$this->assertArrayHasKey('html', $post);
		$this->assertSame('<p>Hello</p>', $post['html']);
		$this->assertNotRegExp('/^--B1_/', $post['html']);
	}

	public function testMailgunPlainUsesTextField()
	{
		$email = $this->forgeDriver('mailgun', array(
			'mailgun' => array('key' => 'k', 'domain' => 'example.com'),
		));
		$email->from('from@example.com');
		$email->to('to@example.com');
		$email->subject('plain');
		$email->body('hello plain');
		$email->send();

		$post = MailgunMailgun::$instance->messages->last_post;
		$this->assertArrayHasKey('text', $post);
		$this->assertSame('hello plain', $post['text']);
		$this->assertArrayNotHasKey('html', $post);
	}

	public function testMandrillAttachFluentAndRecipients()
	{
		$dir = $this->makeAttachDir();
		$this->writeTempFile($dir, 'photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

		$email = $this->forgeDriver('mandrill', array(
			'mandrill' => array('key' => 'key', 'send_options' => array()),
		));
		$email->set_config('attach_paths', array($this->attachPathFromDir($dir)));
		$email->from('from@example.com');
		$email->to('to@example.com');
		$email->set_merge_var('name', 'Ann', 'to@example.com');
		$email->attach('photo.png', true)->subject('subj');
		$email->body('text');
		$email->send();

		$payload = MandrillMessages::$last_instance->last_payload;
		$this->assertSame('subj', $payload['subject']);
		$this->assertTrue(is_array($payload['to']));
		$this->assertSame(0, key($payload['to']));

		$email->clear_to();
		$this->assertNull($email->get_merge_vars(null, 'to@example.com'));
	}
}
