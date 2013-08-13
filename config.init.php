<?php

// Database:
$config['db_user']      = '';
$config['db_pass']      = '';
$config['db_host']      = '';
$config['db_name']      = '';
$config['site_title']   = '';

// Data storage directory (no trailing slash):
// [The default is not a good idea; put your data directory outside of htdocs.]
$config['data_dir'] = dirname(__FILE__).DIRECTORY_SEPARATOR.'data';

// Replacements that should be made to database table and column names:
$config['label_replacements'] = array(
    'id'   => 'ID',
    'in'   => 'in',
    'at'   => 'at',
    'of'   => 'of',
    'for'  => 'for',
    'cant' => 'can&lsquo;t'
);

error_reporting(0);
