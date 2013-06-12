<?php

/**
 * @file
 * Drakefile for %site_name%. Requires drake_reload.
 *
 * Custom modifications should go at the end of the file to be retained by
 * drake-rebuild-generate.
 *
 * You can override standard tasks by simply moving them below the marker line
 * (search for "retained" to find it) and modifying them.
 */

$api = 1;

/*
 * Drake Reload settings. This allows us to re-run drg.
 */
$drake_reload = array();

$context = array(
);

$tasks['build-make'] = array(
  'depends' => array('reload-situs', 'reload-flow-setup'),
  'help' => 'Build site from nothing but a make file.',
  'context' => array(
    'root' => drake_argument(1, 'Directory to build to.'),
    'make-file' => context('makefile'),
  ),
);

$tasks['rebuild-make'] = array(
  'depends' => array('reload-situs', 'reload-flow-setup'),
  'help' => 'Rebuild the current site.',
  'context' => array(
    'root' => context('@self:site:root'),
    'make-file' => context('%make_file_path%'),
  ),
);

$tasks['build-git'] = array(
  'depends' => array('reload-git-clone'),
  'help' => 'Build site from a git repo.',
  'context' => array(
    'root' => drake_argument(1, 'Directory to build to.'),
    'repo' => context('repository'),
  ),
);

$tasks['rebuild-git'] = array(
  'depends' => array('reload-git-pull'),
  'help' => 'Rebuild the current site.',
  'context' => array(
    'root' => context('@self:site:root'),
  ),
);

$tasks['build-ding'] = array(
  'depends' => array('reload-ding-build'),
  'help' => 'Build site from a ding_deploy repo.',
  'context' => array(
    'root' => drake_argument(1, 'Directory to build to.'),
    'repo' => context('repository'),
  ),
);

$tasks['rebuild-ding'] = array(
  'depends' => array('reload-ding-rebuild'),
  'help' => 'Rebuild the current site.',
  'context' => array(
    'root' => context('@self:site:root'),
    'repo' => context('repository'),
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

$tasks['import-sql'] = array(
  'depends' => array('reload-import-file'),
  'help' => 'Import database form SQL dump.',
  'context' => array(
    '@sync_target' => drake_argument('1', "Target alias."),
    'file' => drake_argument('2', 'SQL file to load.'),
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
 * Load a database from a SQL dump.
 */
$tasks['import-file'] = array(
  'depends' => array('reload-load-db', 'sanitize'),
);

/*
 * Custom sanitation function. Invoked by our own import-db.
 */
$tasks['sanitize-nonding'] = array(
  'action' => 'drush',
  'help' => 'Sanitizes database post-import.',
  'commands' => array(
    // Set site name to "%site_name% [hostname]"
    array('command' => 'vset', 'args' => array('site_name', '%site_name% ' . php_uname('n'))),
  ),
);

$tasks['redrake'] = array(
  'action' => 'drush',
  'help' => 'Regenerate the drakefile using drake-reload-generate',
  'command' => 'drake-reload-generate',
  'args' => array(__FILE__, 'y' => TRUE),
);

/*
 * Custom sanitation function. Invoked by our own import-db.
 */
$tasks['sanitize-ding'] = array(
  'action' => 'drush',
  'help' => 'Sanitizes database post-import.',
  'commands' => array(
    // Disable trampoline first thing, or else it'll kill everything later on.
    array(
      'command' => 'pm-disable',
      'args' => array('trampoline', 'y' => TRUE),
    ),
    // Set site name to "%site_name% [hostname]"
    array(
      'command' => 'vset',
      'args' => array('site_name', '%site_name% ' . php_uname('n')),
    ),
  ),
);

// ### Everything below this will be retained by drush-reload-generate ###
