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

function smtp_flush_transcript($transcriptFile, &$log)
{
	file_put_contents($transcriptFile, implode("\n", $log));
}

function smtp_log(&$log, $line)
{
	$log[] = trim($line);
}

function smtp_send_response($conn, $line, &$log, $transcriptFile)
{
	smtp_log($log, 'S: '.$line);
	smtp_flush_transcript($transcriptFile, $log);
	fwrite($conn, $line."\r\n");
}

$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ( ! $server)
{
	fwrite(STDERR, "server failed: $errstr\n");
	exit(1);
}

$addr = stream_socket_get_name($server, false);
list($host, $port) = explode(':', $addr);
file_put_contents($portFile, $port);
smtp_flush_transcript($transcriptFile, $log);

$conn = stream_socket_accept($server, 30);
if ( ! $conn)
{
	fclose($server);
	exit(0);
}

stream_set_timeout($conn, 5);

if ($scenario === 'timeout')
{
	smtp_send_response($conn, '220 localhost ESMTP test', $log, $transcriptFile);
	if ($cmd = fgets($conn, 512))
	{
		smtp_log($log, 'C: '.rtrim($cmd, "\r\n"));
	}
	while (fgets($conn, 512))
	{
	}
	fclose($conn);
	fclose($server);
	exit(0);
}

smtp_send_response($conn, '220 localhost ESMTP test', $log, $transcriptFile);

$state = array('ehlo' => 0, 'auth_stage' => 0);

while ($cmd = fgets($conn, 512))
{
	smtp_log($log, 'C: '.rtrim($cmd, "\r\n"));
	$upper = strtoupper(trim($cmd));

	if (strpos($upper, 'QUIT') === 0)
	{
		if ($scenario !== 'quit_immediate')
		{
			smtp_send_response($conn, '221 bye', $log, $transcriptFile);
		}
		break;
	}

	if (strpos($upper, 'EHLO') === 0)
	{
		$state['ehlo']++;
		if ($scenario === 'ehlo_fail' && $state['ehlo'] === 1)
		{
			smtp_send_response($conn, '500 try helo', $log, $transcriptFile);
			continue;
		}
		if ($scenario === 'multiline_ehlo')
		{
			smtp_send_response($conn, '250-localhost', $log, $transcriptFile);
			smtp_send_response($conn, '250 AUTH LOGIN', $log, $transcriptFile);
			continue;
		}
		smtp_send_response($conn, '250-localhost', $log, $transcriptFile);
		smtp_send_response($conn, '250 AUTH LOGIN', $log, $transcriptFile);
		continue;
	}

	if (strpos($upper, 'HELO') === 0)
	{
		smtp_send_response($conn, '250 hello', $log, $transcriptFile);
		continue;
	}

	if (strpos($upper, 'HELP') === 0)
	{
		if ($scenario === 'help_fail')
		{
			smtp_send_response($conn, '500 no help', $log, $transcriptFile);
		}
		else
		{
			smtp_send_response($conn, '214 help ok', $log, $transcriptFile);
		}
		continue;
	}

	if (strpos($upper, 'STARTTLS') === 0)
	{
		if ($scenario === 'starttls_fail')
		{
			smtp_send_response($conn, '454 not available', $log, $transcriptFile);
		}
		else
		{
			smtp_send_response($conn, '220 ready', $log, $transcriptFile);
		}
		continue;
	}

	if (strpos($upper, 'AUTH LOGIN') === 0)
	{
		$state['auth_stage'] = 1;
		smtp_send_response($conn, '334 user', $log, $transcriptFile);
		continue;
	}

	if ($state['auth_stage'] === 1)
	{
		$state['auth_stage'] = 2;
		smtp_send_response($conn, '334 pass', $log, $transcriptFile);
		continue;
	}

	if ($state['auth_stage'] === 2)
	{
		$state['auth_stage'] = 0;
		if ($scenario === 'auth_fail')
		{
			smtp_send_response($conn, '535 auth failed', $log, $transcriptFile);
		}
		else
		{
			smtp_send_response($conn, '235 ok', $log, $transcriptFile);
		}
		continue;
	}

	if (strpos($upper, 'MAIL FROM') === 0)
	{
		smtp_send_response($conn, '250 ok', $log, $transcriptFile);
		continue;
	}

	if (strpos($upper, 'RCPT TO') === 0)
	{
		if ($scenario === 'rcpt_fail')
		{
			smtp_send_response($conn, '550 no', $log, $transcriptFile);
		}
		else
		{
			smtp_send_response($conn, '250 ok', $log, $transcriptFile);
		}
		continue;
	}

	if (strpos($upper, 'DATA') === 0)
	{
		smtp_send_response($conn, '354 go', $log, $transcriptFile);
		while ($line = fgets($conn, 512))
		{
			smtp_log($log, 'C: '.rtrim($line, "\r\n"));
			smtp_flush_transcript($transcriptFile, $log);
			if (trim($line) === '.')
			{
				break;
			}
		}
		smtp_send_response($conn, '250 queued', $log, $transcriptFile);
		continue;
	}
}

fclose($conn);
fclose($server);
