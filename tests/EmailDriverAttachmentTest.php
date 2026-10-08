<?php

class EmailDriverAttachmentTest extends EmailTestCase
{
	public function testMultipartNestingForAltInlineAttach()
	{
		$dir = $this->makeAttachDir();
		$this->writeTempFile($dir, 'photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
		$this->writeTempFile($dir, 'file.txt', 'attach me');

		$email = $this->forgeDriver('noop');
		$email->set_config('attach_paths', array($this->attachPathFromDir($dir)));
		$email->from('from@example.com');
		$email->to('to@example.com');
		$email->subject('mix');
		$email->html_body('<p>Hi</p><img src="photo.png" />');
		$email->attach('file.txt');
		$email->send();

		$log = $this->noopLogText();
		$this->assertContains('multipart/mixed', $log);
		$this->assertContains('multipart/alternative', $log);
		$this->assertContains('multipart/related', $log);
		$this->assertContains('Content-Disposition: attachment', $log);
		$this->assertContains('Content-Disposition: inline', $log);
		$boundaries = $this->assertBoundariesWellFormed($log);
		$this->assertGreaterThanOrEqual(2, count($boundaries));
		$this->assertContains('filename="file.txt"', $log);
	}
}
