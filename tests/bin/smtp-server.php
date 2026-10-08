<?php

$portFile = getenv('SMTP_PORT_FILE');
$scenario = getenv('SMTP_SCENARIO');
if (empty($portFile) || empty($scenario))
{
	fwrite(STDERR, "missing SMTP_PORT_FILE or SMTP_SCENARIO\n");
	exit(1);
}

$transcriptFile = $portFile.'.transcript';
$GLOBALS['smtp_test_log'] = array();
$log =& $GLOBALS['smtp_test_log'];

register_shutdown_function(function () use ($transcriptFile) {
	if (isset($GLOBALS['smtp_test_log']))
	{
		file_put_contents($transcriptFile, implode("\n", $GLOBALS['smtp_test_log']));
	}
});

$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ( ! $server)
{
	fwrite(STDERR, "server failed: $errstr\n");
	exit(1);
}

$addr = stream_socket_get_name($server, false);
list($host, $port) = explode(':', $addr);
file_put_contents($portFile, $port);

$conn = @stream_socket_accept($server, 30);
if ( ! $conn)
{
	file_put_contents($transcriptFile, implode("\n", $log));
	fclose($server);
	exit(0);
}

stream_set_timeout($conn, 5);

function smtp_log(&$log, $line, $transcriptFile = null)
{
	$log[] = trim($line);
	if ($transcriptFile !== null)
	{
		file_put_contents($transcriptFile, implode("\n", $log));
	}
}

function smtp_send($conn, $line)
{
	fwrite($conn, $line."\r\n");
}

function smtp_read(&$log, $conn)
{
	$data = '';
	while ($line = fgets($conn, 512))
	{
		$data .= $line;
		smtp_log($log, 'C: '.rtrim($line, "\r\n"), $transcriptFile);
		if (isset($line[3]) && $line[3] === ' ')
		{
			break;
		}
	}
	return $data;
}

if ($scenario === 'timeout')
{
	usleep(6000000);
	fclose($conn);
	fclose($server);
	file_put_contents($transcriptFile, implode("\n", $log));
	exit(0);
}

if ($scenario !== 'timeout')
{
	smtp_send($conn, '220 localhost ESMTP test');
}

$state = array('ehlo' => 0, 'auth_stage' => 0);

while ($cmd = fgets($conn, 512))
{
	smtp_log($log, 'C: '.rtrim($cmd, "\r\n"), $transcriptFile);
	$upper = strtoupper(trim($cmd));

	if (strpos($upper, 'QUIT') === 0)
	{
		if ($scenario !== 'quit_immediate')
		{
			smtp_send($conn, '221 bye');
		}
		break;
	}

	if (strpos($upper, 'EHLO') === 0)
	{
		$state['ehlo']++;
		if ($scenario === 'ehlo_fail' && $state['ehlo'] === 1)
		{
			smtp_send($conn, '500 try helo');
			continue;
		}
		if ($scenario === 'multiline_ehlo')
		{
			smtp_send($conn, '250-localhost');
			smtp_send($conn, '250 AUTH LOGIN');
			continue;
		}
		smtp_send($conn, '250-localhost');
		smtp_send($conn, '250 AUTH LOGIN');
		continue;
	}

	if (strpos($upper, 'HELO') === 0)
	{
		smtp_send($conn, '250 hello');
		continue;
	}

	if (strpos($upper, 'HELP') === 0)
	{
		if ($scenario === 'help_fail')
		{
			smtp_send($conn, '500 no help');
		}
		else
		{
			smtp_send($conn, '214 help ok');
		}
		continue;
	}

	if (strpos($upper, 'STARTTLS') === 0)
	{
		if ($scenario === 'starttls_fail')
		{
			smtp_send($conn, '454 not available');
		}
		else
		{
			smtp_send($conn, '220 ready');
		}
		continue;
	}

	if (strpos($upper, 'AUTH LOGIN') === 0)
	{
		$state['auth_stage'] = 1;
		smtp_send($conn, '334 user');
		continue;
	}

	if ($state['auth_stage'] === 1)
	{
		$state['auth_stage'] = 2;
		smtp_send($conn, '334 pass');
		continue;
	}

	if ($state['auth_stage'] === 2)
	{
		$state['auth_stage'] = 0;
		if ($scenario === 'auth_fail')
		{
			smtp_send($conn, '535 auth failed');
		}
		else
		{
			smtp_send($conn, '235 ok');
		}
		continue;
	}

	if (strpos($upper, 'MAIL FROM') === 0)
	{
		smtp_send($conn, '250 ok');
		continue;
	}

	if (strpos($upper, 'RCPT TO') === 0)
	{
		if ($scenario === 'rcpt_fail')
		{
			smtp_send($conn, '550 no');
		}
		else
		{
			smtp_send($conn, '250 ok');
		}
		continue;
	}

	if (strpos($upper, 'DATA') === 0)
	{
		smtp_send($conn, '354 go');
		while ($line = fgets($conn, 512))
		{
			smtp_log($log, 'C: '.rtrim($line, "\r\n"), $transcriptFile);
			if (trim($line) === '.')
			{
				break;
			}
		}
		if ($scenario === 'rcpt_fail')
		{
			// already failed earlier
		}
		smtp_send($conn, '250 queued');
		continue;
	}
}

fclose($conn);
fclose($server);
file_put_contents($transcriptFile, implode("\n", $log));
