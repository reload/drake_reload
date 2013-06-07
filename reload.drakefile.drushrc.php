<?php

$api = 1;

/*
 * (Re-)Build a site using Situs.
 */
$tasks['reload-situs'] = array(
  'action' => 'drush',
  'command' => 'situs-build',
  'args' => array(
    'root' => context('root'),
    'make-file' => context('make-file'),
  ),
);

/**
 * Build a site by cloning a git repository.
 */
$tasks['reload-git-clone'] = array(
  'action' => 'shell',
  'command' => context('cd [root:file:dirname]; git clone [repo] [root:file:basename]'),
);

/**
 * Rebuild git checkout by using git pull.
 */
$tasks['reload-git-pull'] = array(
  'action' => 'shell',
  'command' => context('cd [root]; git pull'),
);

/*
 * Set up git flow in a git repository.
 */
$tasks['reload-flow-setup'] = array(
  'action' => 'reload-git-flow-setup',
  'message' => 'Set up git flow',
  'git-root' => context('git-root'),
  'prefix' => context_optional('flow-prefix', ''),
);

/*
 * Imports a site, sanitizes and runs updb.
 *
 * Expects the site drakefile to define the import-db task to import the
 * database from somewhere by in turn depending on reload-sync-db or
 * reload-import-db.
 */
$tasks['reload-import-site'] = array(
  // The import-db task is expected to be defined in the site drakefile.
  'depends' => array('reload-drop-db', 'import-db', 'reload-sanitize', 'reload-updb', 'enable-modules', 'reload-cc-all'),
);

/*
 * Same as reload-import-site, but using the import-file task instead.
 */
$tasks['reload-import-file'] = array(
  // The import-file task is expected to be defined in the site drakefile.
  'depends' => array('reload-drop-db', 'import-file', 'reload-sanitize', 'reload-updb', 'enable-modules', 'reload-cc-all'),
);

/*
 * Default no-op task.
 *
 * This just makes enable-modules optional in the site drakefile.
 */
$tasks['enable-modules'] = array();

/*
 * Sanitize database post-import.
 */
$tasks['reload-sanitize'] = array(
  'action' => 'drush',
  'help' => 'Sanitizes database post-import (common).',
  'commands' => array(
    // Disable aggregation and cache.
    array('command' => 'vset', 'args' => array('preprocess_js', '0')),
    array('command' => 'vset', 'args' => array('preprocess_css', '0')),
    array('command' => 'vset', 'args' => array('cache', '0')),
    // Set file paths.
    array(
      'command' => 'vset',
      'args' => array('file_private_path', context('[@sync_target:site:path]/files/private')),
    ),
    array(
      'command' => 'vset',
      'args' => array('file_public_path', context('[@sync_target:site:path]/files/public')),
    ),
    array(
      'command' => 'vset',
      'args' => array('file_temporary_path', '/tmp'),
    ),
    // Logging level.
    array('command' => 'vset', 'args' => array('error_level', '2')),
  ),
);

/*
 * Run updb.
 */
$tasks['reload-updb'] = array(
  'action' => 'drush',
  'command' => 'updatedb',
  'args' => array(
    'y' => TRUE,
  ),
  'target' => context('@sync_target'),
);

/**
 * Clear cache.
 */
$tasks['reload-cc-all'] = array(
  'action' => 'drush',
  'command' => 'cc',
  'args' => array('all'),
  'target' => context('@sync_target'),
);

/*
 * Drop database pre-import.
 */
$tasks['reload-drop-db'] = array(
  'action' => 'drush',
  'command' => 'sql-drop',
  'args' => array(
    'y' => TRUE,
  ),
  'target' => context('@sync_target'),
);

/*
 * Use drush sync-db to import a database.
 */
$tasks['reload-sync-db'] = array(
  'action' => 'drush',
  'command' => 'sql-sync',
  'args' => array(
    context('@sync_source'),
    context('@sync_target'),
    'y' => TRUE,
  ),
);

/*
 * Import a database by loading an (possibly compressed) SQL dump.
 */
$tasks['reload-load-db'] = array(
  'action' => 'reload-import-db',
  'file' => context('file'),
  'target' => context('@sync_target'),
);

/*
 * Action that loads an SQL file.
 */
$actions['reload-import-db'] = array(
  'callback' => 'reload_import_db',
  'parameters' => array(
    'file' => 'File to import.',
    'target' => 'Target alias to import database to.',
  ),
);

function reload_import_db($context) {
  $file = realpath($context['file']);
  if (!file_exists($file)) {
    return drake_action_error(dt('No such SQL file: @file', array('@file' => $file)));
  }

  $ext = array_pop(explode('.', $file));
  switch ($ext) {
    case 'gz':
      $cat_command = 'zcat';
      break;
    case 'bz':
    case 'bz2':
        $cat_command = 'bzcat';
        break;
    case 'sql':
    default:
      $cat_command = 'cat';
  }

  $res = drush_invoke_process($context['target'], 'sql-connect', array(), array(), array('override-simulated' => TRUE, 'integrate' => FALSE));

  if (!empty($res['output'])) {
    // Use a temp file for error output. We could use proc_open to get the file
    // handles directly and avoid this, but then we'd have to implement all the
    // logic of drush_shell_proc_open and piping manually.
    $err_file = drush_tempnam('drake_reload_');
    $command = $cat_command . " 2>>$err_file "  . $file . ' | ' . $res['output'] . "2>>$err_file";

    $res = drush_shell_exec($command);
    $errors = file_get_contents($err_file);
    if (!$res || !empty($errors)) {
      foreach (explode("\n", $errors) as $error) {
        drush_log($error, 'error');
      }
      return drake_action_error(dt('Database import failed.'));
    }
    else {
      drush_print('eh');
    }
    drush_log(dt('Database imported.'), 'ok');
  }
  else {
    return drake_action_error(dt('Could not get database credentials. (try sql-connect yourself)'));
  }
}

$actions['reload-git-flow-setup'] = array(
  'callback' => 'reload_git_flow_setup',
  'parameters' => array(
    'git-root' => 'Path to the git checkout.',
    'prefix' => array(
      'description' => 'Branch/tag prefix.',
      'default' => '',
    ),
  ),
);

function reload_git_flow_setup($context) {
  $prefix = $context['prefix'];
  $config = <<<EOF
[gitflow "branch"]
        master = ${prefix}master
        develop = ${prefix}develop
[gitflow "prefix"]
        feature = ${prefix}feature/
        release = ${prefix}release/
        hotfix = ${prefix}hotfix/
        support = ${prefix}support/
        versiontag = ${prefix}
EOF;
  file_put_contents($context['git-root'] . '/.git/config', $config, FILE_APPEND);
  // Ensure that master and develop branches is available locally, or else git
  // flow complains.
  $branches = array('master', 'develop');
  foreach ($branches as $branch) {
    // Check whether the branch exists. git branch doesn't like that.
    $command = 'git --git-dir=' . $context['git-root'] . '/.git branch --list ' . $branch;
    drush_shell_exec($command);
    $output = drush_shell_exec_output();
    if (!empty($output)) {
      continue;
    }
    // Else tell git to create a remote tracking branch.
    $command = 'git --git-dir=' . $context['git-root'] . '/.git branch -t ' . $context['prefix'] . $branch . ' remotes/origin/' . $context['prefix'] . $branch;
    if (!drush_shell_exec_interactive($command)) {
      return drake_action_error(dt('Error checking out branch "@branch"', array('@branch' => $branch)));
    }
  }
}
