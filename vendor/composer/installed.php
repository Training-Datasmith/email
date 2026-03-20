<?php return array(
    'root' => array(
        'name' => 'fuel/email',
        'pretty_version' => 'dev-develop',
        'version' => 'dev-develop',
        'reference' => '9b838279ce27161e7fba9e4547c9672471605b0d',
        'type' => 'fuel-package',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'composer/installers' => array(
            'pretty_version' => 'v1.12.0',
            'version' => '1.12.0.0',
            'reference' => 'd20a64ed3c94748397ff5973488761b22f6d3f19',
            'type' => 'composer-plugin',
            'install_path' => __DIR__ . '/./installers',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'fuel/email' => array(
            'pretty_version' => 'dev-develop',
            'version' => 'dev-develop',
            'reference' => '9b838279ce27161e7fba9e4547c9672471605b0d',
            'type' => 'fuel-package',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'roundcube/plugin-installer' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '*',
            ),
        ),
        'shama/baton' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '*',
            ),
        ),
    ),
);
