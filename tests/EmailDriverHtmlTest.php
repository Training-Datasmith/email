<?php

class EmailDriverHtmlTest extends EmailTestCase
{
	public function testCommentsRemovedWithoutEatingBody()
	{
		$email = $this->forgeDriver('noop');
		$email->from('from@example.com');
		$email->to('to@example.com');
		$email->subject('html');
		$email->html_body('<!--a-->KEEP<!--b-->');
		$email->send();
		$this->assertContains('KEEP', $this->noopLogText());
		$this->assertNotContains('<!--', $this->noopLogText());
	}

	public function testRemoveHtmlCommentsConfigOff()
	{
		$email = $this->forgeDriver('noop', array('remove_html_comments' => false));
		$email->from('from@example.com');
		$email->to('to@example.com');
		$email->subject('html');
		$email->html_body('<!--keep-->');
		$email->send();
		$this->assertContains('<!--keep-->', $this->noopLogText());
	}

	public function testAltPreservesPipeCharacters()
	{
		$email = $this->forgeDriver('noop');
		$email->from('from@example.com');
		$email->to('to@example.com');
		$email->subject('alt');
		$email->html_body("<p>a||b\t\tc</p>");
		$email->send();
		$log = $this->noopLogText();
		$plainParts = $this->extractMimeParts($log, 'text/plain');
		$this->assertNotEmpty($plainParts);
		$this->assertSame('a||b c', $plainParts[0]);
	}

	public function testHtmlInlineUsesRelatedMultipart()
	{
		$dir = $this->makeAttachDir();
		$this->writeTempFile($dir, 'photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
		$email = $this->forgeDriver('noop');
		$email->set_config('attach_paths', array($this->attachPathFromDir($dir)));
		$email->from('from@example.com');
		$email->to('to@example.com');
		$email->subject('inline');
		$email->html_body('<img src="photo.png" />', false, true);
		$email->send();
		$log = $this->noopLogText();
		$this->assertContains('multipart/related', $log);
		$this->assertBoundariesWellFormed($log);
	}

	public function testDataUriInlineNoTempLeak()
	{
		$png = base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
		$before = glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'inline-*');
		$email = $this->forgeDriver('noop');
		$email->from('from@example.com');
		$email->to('to@example.com');
		$email->subject('data');
		$email->html_body('<img src="data:image/png;base64, '.$png.'" />');
		$email->send();
		$after = glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'inline-*');
		$this->assertSame(count($before), count($after));
	}
}
