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
				unlink($file);
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
						if (is_file($file))
						{
							unlink($file);
						}
					}
				}
				rmdir($dir);
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

	protected function extractMimeParts($body, $contentType)
	{
		$parts = array();
		$needle = 'Content-Type: '.$contentType;
		$offset = 0;
		while (($pos = stripos($body, $needle, $offset)) !== false)
		{
			$headerEnd = strpos($body, "\n\n", $pos);
			if ($headerEnd === false)
			{
				break;
			}
			$contentStart = $headerEnd + 2;
			$boundaryPos = strpos($body, "\n--", $contentStart);
			$content = $boundaryPos === false
				? substr($body, $contentStart)
				: substr($body, $contentStart, $boundaryPos - $contentStart);
			$parts[] = rtrim($content, "\r\n");
			$offset = $contentStart + 1;
		}

		return $parts;
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
		$write = null;
		$except = null;
		while (microtime(true) < $deadline)
		{
			clearstatcache(true, $portFile);
			if (is_file($portFile) && filesize($portFile) > 0)
			{
				$port = trim(file_get_contents($portFile));
				break;
			}
			$read = array($pipes[1], $pipes[2]);
			stream_select($read, $write, $except, 0, 50000);
		}
		$this->assertNotEmpty($port, 'SMTP test server did not publish its port');
		$transcriptFile = $portFile.'.transcript';

		if (is_resource($pipes[0]))
		{
			fclose($pipes[0]);
		}
		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);

		return array(
			'proc' => $proc,
			'pipes' => $pipes,
			'port' => (int) $port,
			'port_file' => $portFile,
			'transcript_file' => $transcriptFile,
		);
	}

	protected function stopSmtpServer($server)
	{
		if (is_resource($server['proc']))
		{
			if (is_resource($server['pipes'][0]))
			{
				fclose($server['pipes'][0]);
			}
			if (is_resource($server['pipes'][1]))
			{
				fclose($server['pipes'][1]);
			}
			if (is_resource($server['pipes'][2]))
			{
				fclose($server['pipes'][2]);
			}
			proc_terminate($server['proc']);
			proc_close($server['proc']);
		}
	}

	protected function readSmtpTranscript(array $server)
	{
		$path = $server['transcript_file'];
		$deadline = microtime(true) + 2;
		$write = null;
		$except = null;
		while (microtime(true) < $deadline)
		{
			clearstatcache(true, $path);
			if (is_file($path) && filesize($path) > 0)
			{
				return file_get_contents($path);
			}
			$read = array();
			if (isset($server['pipes'][1]) && is_resource($server['pipes'][1]))
			{
				$read[] = $server['pipes'][1];
			}
			if (isset($server['pipes'][2]) && is_resource($server['pipes'][2]))
			{
				$read[] = $server['pipes'][2];
			}
			if ( ! empty($read))
			{
				stream_select($read, $write, $except, 0, 50000);
			}
		}

		$this->assertFileExists($path);

		return is_file($path) ? file_get_contents($path) : '';
	}
}
