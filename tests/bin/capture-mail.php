<?php

$raw = stream_get_contents(STDIN);
$target = getenv('MAIL_CAPTURE_FILE');
if (empty($target))
{
	$target = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mail-capture-'.uniqid('', true);
}

file_put_contents($target, "ARGV:\n".implode("\n", $argv)."\n----STDIN----\n".$raw);
exit(0);
