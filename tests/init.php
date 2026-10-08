<?php

define('DOCROOT', dirname(__DIR__).DIRECTORY_SEPARATOR);
define('MBSTRING', extension_loaded('mbstring'));

require __DIR__.'/support/FuelHarness.php';
require __DIR__.'/support/MailgunStub.php';
require __DIR__.'/support/EmailTestCase.php';

FuelHarness::bootstrap();
