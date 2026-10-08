<?php

namespace Mailgun {

class Mailgun
{
	public static function create($key, $endpoint = null)
	{
		\MailgunMailgun::$create_args = array($key, $endpoint);
		\MailgunMailgun::$instance = new \MailgunMailgun();
		return \MailgunMailgun::$instance;
	}
}

}
