<?php

abstract class EmailTestCase extends PHPUnit_Framework_TestCase
{
	protected $tempFiles = array();
	protected $tempDirs = array();

	protected function setUp()
	{
		parent::setUp();
		Config::reset();
		FuelHarness::resetSinks();
		Email\Email::$_instance = false;
		$_SERVER = array();
		if (getenv('MAIL_CAPTURE_FILE'))
		{
			putenv('MAIL_CAPTURE_FILE');
		}
		if (getenv('SMTP_PORT_FILE'))
		{
			putenv('SMTP_PORT_FILE');
		}
		if (getenv('SMTP_SCENARIO'))
		{
			putenv('SMTP_SCENARIO');
		}
	}

	protected function tearDown()
	{
		foreach ($this->tempFiles as $file)
		{
			if (is_file($file))
			{
				@unlink($file);
			}
		}
		foreach ($this->tempDirs as $dir)
		{
			if (is_dir($dir))
			{
				$files = glob($dir.'/*');
				if (is_array($files))
				{
					foreach ($files as $file)
					{
						@unlink($file);
					}
				}
				@rmdir($dir);
			}
		}
		Email\Email::$_instance = false;
		parent::tearDown();
	}

	protected function defaults(array $overrides = array())
	{
		Email\Email::_init();
		$defaults = Config::get('email.defaults', array());
		return Arr::merge($defaults, $overrides);
	}

	protected function forgeDriver($driver, array $config = array())
	{
		$config['driver'] = $driver;
		return Email\Email::forge(null, $config);
	}

	protected function makeAttachDir()
	{
		$dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'email-test-'.uniqid('', true);
		mkdir($dir);
		$this->tempDirs[] = $dir;
		return $dir;
	}

	protected function attachPathFromDir($dir)
	{
		return rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
	}

	protected function writeTempFile($dir, $name, $contents)
	{
		$path = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name;
		file_put_contents($path, $contents);
		$this->tempFiles[] = $path;
		return $path;
	}

	protected function noopLogText()
	{
		$text = '';
		foreach (TestLogger::$entries as $entry)
		{
			$text .= $entry['msg']."\n";
		}
		return $text;
	}

	protected function assertNoopContains($needle)
	{
		$this->assertContains($needle, $this->noopLogText());
	}

	protected function assertBoundariesWellFormed($body)
	{
		preg_match_all('/boundary="([^"]+)"/', $body, $matches);
		$boundaries = array();
		if ( ! empty($matches[1]))
		{
			$boundaries = $matches[1];
		}

		foreach ($boundaries as $boundary)
		{
			$this->assertContains('--'.$boundary, $body);
			$this->assertContains('--'.$boundary.'--', $body);
		}

		return $boundaries;
	}

	protected function readMailCapture()
	{
		$file = getenv('MAIL_CAPTURE_FILE');
		$this->assertNotEmpty($file);
		$this->assertFileExists($file);
		return file_get_contents($file);
	}

	protected function startSmtpServer($scenario)
	{
		$portFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'smtp-port-'.uniqid('', true);
		$this->tempFiles[] = $portFile;
		putenv('SMTP_PORT_FILE='.$portFile);
		putenv('SMTP_SCENARIO='.$scenario);

		$script = realpath(dirname(__DIR__).'/bin/smtp-server.php');
		$cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($script);
		$descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
		$proc = proc_open($cmd, $descriptors, $pipes);
		$this->assertTrue(is_resource($proc));

		$deadline = microtime(true) + 5;
		$port = null;
		while (microtime(true) < $deadline)
		{
			if (is_file($portFile) && filesize($portFile) > 0)
			{
				$port = trim(file_get_contents($portFile));
				break;
			}
			usleep(50000);
		}
		$this->assertNotEmpty($port, 'SMTP test server did not publish its port');

		return array(
			'proc' => $proc,
			'pipes' => $pipes,
			'port' => (int) $port,
			'port_file' => $portFile,
			'transcript_file' => $portFile.'.transcript',
		);
	}

	protected function stopSmtpServer($server)
	{
		if (is_resource($server['proc']))
		{
			@fclose($server['pipes'][0]);
			@fclose($server['pipes'][1]);
			@fclose($server['pipes'][2]);
			proc_terminate($server['proc']);
			proc_close($server['proc']);
			usleep(200000);
		}
	}

	protected function readSmtpTranscript(array $server)
	{
		$path = $server['transcript_file'];
		$deadline = microtime(true) + 2;
		while (microtime(true) < $deadline)
		{
			if (is_file($path) && filesize($path) > 0)
			{
				return file_get_contents($path);
			}
			usleep(50000);
		}

		return is_file($path) ? file_get_contents($path) : '';
	}
}
