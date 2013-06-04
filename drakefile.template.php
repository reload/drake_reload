<?php

/**
 * @file
 * Drakefile for %site_name%. Requires drake_reload.
 */

$api = 1;

$context = array(
);

$tasks['build'] = array(
  'depends' => array('reload-situs', 'reload-flow-setup'),
  'help' => 'Build site from nothing but a make file.',
  'context' => array(
    'root' => drake_argument(1, 'Directory to build to.'),
    'make-file' => context('makefile'),
  ),
);

$tasks['rebuild'] = array(
  'depends' => array('reload-situs', 'reload-flow-setup'),
  'help' => 'Rebuild the current site.',
  'context' => array(
    'root' => context('@self:site:root'),
    'make-file' => context('[@self:site:root]/%make_file_path%'),
  ),
);

$tasks['import-%env%'] = array(
  'depends' => array('reload-import-site'),
  'help' => 'Import database form "%env_name%".',
  'context' => array(
    '@sync_source' => context('@env.%env%'),
    '@sync_target' => drake_argument('1', "Target alias."),
  ),
);

/*
 * Defines some way of loading an existing database from somewhere. It is
 * invoked by reload-import-site.
 *
 * This is just a normal task, but it is recommended that it's implemented by
 * depending on a reload helper task such as reload-sync-db or reload-import-db.
 */
$tasks['import-db'] = array(
  'depends' => array('reload-sync-db', 'sanitize'),
);

/*
 * Example alternative import-db, this using reload-load-db.
 */
$tasks['import-db-alternative'] = array(
  'depends' => array('reload-load-db', 'sanitize'),
  'context' => array(
    'file' => drake_argument(1, 'SQL file to load.'),
    'target' => drake_argument('2', "Target alias."),
  ),
);

/*
 * Custom sanitation function. Invoked by our own import-db.
 */
$tasks['sanitize'] = array(
  'action' => 'drush',
  'help' => 'Sanitizes database post-import.',
  'commands' => array(
    // Set site name to "%site_name% [hostname]"
    array('command' => 'vset', 'args' => array('site_name', '%site_name% ' . php_uname('n'))),
  ),
);

$tasks['enable-modules'] = array(
  'action' => 'drush',
  'command' => 'pm-enable',
  'args' => array(
    'y' => TRUE,
    'ida_test_config',
  ),
);
