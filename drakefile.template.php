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

/*
 * Global context.
 */
$context = array();

/*
 * Build site using make.
 */
$tasks['build-make'] = array(
  'depends' => array('reload-situs', 'reload-flow-setup'),
  'help' => 'Build site from nothing but a make file.',
  'context' => array(
    'root' => drake_argument(1, 'Directory to build to.'),
    'make-file' => context('makefile'),
  ),
);

/*
 * Rebuild site using make.
 */
$tasks['rebuild-make'] = array(
  'depends' => array('reload-situs', 'reload-flow-setup'),
  'help' => 'Rebuild the current site.',
  'context' => array(
    'root' => context('@self:site:root'),
    'make-file' => context('%make_file_path%'),
  ),
);

/*
 * Build site from GIT.
 */
$tasks['build-git'] = array(
  'depends' => array('reload-git-clone', 'reload-flow-setup'),
  'help' => 'Build site from a git repo.',
  'context' => array(
    'root' => drake_argument(1, 'Directory to build to.'),
    'repository' => context('repository'),
  ),
);

/*
 * Relbuild site from GIT.
 */
$tasks['rebuild-git'] = array(
  'depends' => array('reload-git-pull'),
  'help' => 'Rebuild the current site.',
  'context' => array(
    'root' => context('@self:site:root'),
  ),
);

/*
 * Build site via ding_deploy.
 */
$tasks['build-ding'] = array(
  'depends' => array('reload-ding-build'),
  'help' => 'Build site from a ding_deploy repository.',
  'context' => array(
    'root' => drake_argument(1, 'Directory to build to.'),
    'repository' => context('repository'),
  ),
);

/*
 * Rebuild site via ding_deploy.
 */
$tasks['rebuild-ding'] = array(
  'depends' => array('reload-ding-rebuild'),
  'help' => 'Rebuild the current site.',
  'context' => array(
    'root' => context('@self:site:root'),
    'repository' => context('repository'),
  ),
);

/*
 * Build site for CI testing.
 *
 * Assumes it's being run from inside a git checkout.
 */
$tasks['ci-build-make'] = array(
  'depends' => 'reload-ci-build-make',
  'context' => array(
    'root' => drake_argument(1, 'Directory to build to.'),
    'make-file' => context('%make_file_path%'),
  ),
);

/*
 * Build site for CI testing.
 *
 * Assumes it's being run from inside a git checkout.
 */
$tasks['ci-build-git'] = array(
  'depends' => 'reload-ci-build-git',
  'context' => array(
    'root' => drake_argument(1, 'Directory to build to.'),
  ),
);

/*
 * Import database from "%env_name%".
 */
$tasks['import-%env%'] = array(
  'depends' => array('reload-import-site'),
  'help' => 'Import database form "%env_name%".',
  'context' => array(
    '@sync_source' => context('@env.%env%'),
    '@sync_target' => drake_argument('1', "Target alias."),
  ),
);

/*
 * Import database from a file.
 */
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
    array(
      'command' => 'vset',
      'args' => array('site_name', '%site_name% ' . php_uname('n')),
    ),
  ),
);

/*
 * Custom sanitation function. Invoked by our own import-db.
 */
$tasks['sanitize-ding'] = array(
  'depends' => array(
    'reload-disable-old-mobile-tools',
    'reload-ding-fix-error-level',
    'sanitize-drush',
    'reload-fix-mobile-tools',
  ),
  'help' => 'Sanitizes database post-import.',
);

/*
 * Runs misc sanitation drush commands.
 */
$tasks['sanitize-ding-drush'] = array(
  'action' => 'drush',
  'commands' => array(
    // Disable trampoline first thing, or else it'll kill everything later on.
    // Same for memcache_admin.
    array(
      'command' => 'pm-disable',
      'args' => array(
        'trampoline',
        'memcache_admin',
        'securepages',
        'y' => TRUE,
      ),
    ),
    // Set site name to "%site_name% [hostname]"
    array(
      'command' => 'vset',
      'args' => array('site_name', '%site_name% ' . php_uname('n')),
    ),
  ),
);

/*
 * Regenerate drakefile.
 */
$tasks['redrake'] = array(
  'action' => 'drush',
  'help' => 'Regenerate the drakefile using drake-reload-generate',
  'command' => 'drake-reload-generate',
  'args' => array(__FILE__, 'y' => TRUE),
);

// ### Everything below this will be retained by drush-reload-generate ###
