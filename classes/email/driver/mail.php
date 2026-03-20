<?php

declare (strict_types=1);
/**
 * Fuel is a fast, lightweight, community driven PHP 5.4+ framework.
 *
 * @package    Fuel
 * @version    1.8.2
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2019 Fuel Development Team
 * @link       https://fuelphp.com
 */
namespace Email;

class Email_Driver_Mail extends \Email_Driver
{
    /**
     * Send the email using php's mail function.
     *
     * @throws \EmailSendingFailedException Failed sending email
     *
     * @return  bool    success boolean.
     */
    protected function _send()
    {
        $message = $this->build_message();
        $return_path = $this->config['return_path'] !== false ? $this->config['return_path'] : $this->config['from']['email'];
        if (!filter_var($return_path, FILTER_VALIDATE_EMAIL)) {
            throw new \Email_Sending_Failed_Exception('Invalid return-path address: ' . $return_path);
        }
        if (!@mail(static::format_addresses($this->to), $this->subject, (string) $message['body'], $message['header'], '-oi -f ' . escapeshellarg($return_path))) {
            throw new \Email_Sending_Failed_Exception('Failed sending email');
        }
        return true;
    }
}