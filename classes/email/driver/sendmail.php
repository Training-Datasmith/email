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

class Sendmail_Connection_Exception extends \Fuel_Exception
{
}
class Sendmail_Failed_Exception extends \Email_Sending_Failed_Exception
{
}
class Email_Driver_Sendmail extends \Email_Driver
{
    /**
     * Initalted all needed for Sendmail mailing.
     *
     * @throws \SendmailConnectionException Could not open a sendmail connection
     * @throws \SendmailFailedException     Failed sending email through sendmail
     *
     * @return  bool    Success boolean
     */
    protected function _send()
    {
        // Build the message
        $message = $this->build_message();
        // Open a connection
        $return_path = $this->config['return_path'] !== false ? $this->config['return_path'] : $this->config['from']['email'];
        if (!filter_var($return_path, FILTER_VALIDATE_EMAIL)) {
            throw new \Sendmail_Connection_Exception('Invalid return-path address: ' . $return_path);
        }
        $handle = @popen(escapeshellcmd($this->config['sendmail_path']) . ' -oi -f ' . escapeshellarg($return_path) . ' -t', 'w');
        // No connection?
        if (!is_resource($handle)) {
            throw new \Sendmail_Connection_Exception('Could not open a sendmail connection at: ' . $this->config['sendmail_path']);
        }
        // Send the headers
        fputs($handle, (string) $message['header']);
        // Send the body
        fputs($handle, (string) $message['body']);
        if (pclose($handle) === -1) {
            throw new \Sendmail_Failed_Exception('Failed sending email through sendmail.');
        }
        return true;
    }
}