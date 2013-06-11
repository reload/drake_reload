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

/**
 * Build a ding site.
 */
$tasks['reload-ding-build'] = array(
  'action' => 'reload-ding-build',
  'root' => context('root'),
  'repository' => context('repository'),
);

/**
 * Re-build a ding site.
 */
$tasks['reload-ding-rebuild'] = array(
  'action' => 'reload-ding-rebuild',
  'root' => context('root'),
  'repository' => context('repository'),
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

$actions['reload-ding-build'] = array(
  'callback' => 'reload_ding_build',
  'parameters' => array(
    'root' => 'Directory to build to.',
    'repository' => 'URL to the ding_deploy repository.',
  ),
);

function reload_ding_build($context) {
  // Initial sanity checks.
  if (file_exists($context['root'])) {
    return drake_action_error(dt('Root "@path" already exists.', array('@path' => $context['root'])));
  }
  $parent = dirname($context['root']);
  if (!file_exists($parent)) {
    return drake_action_error(dt('Root parent "@parent" does not exists.', array('@parent' => $parent)));
  }

  // Temporary directory for ding_deploy checkout.
  $deploy = drush_tempdir('ding_deploy_') . '/ding-deploy';
  $command = 'git 2>&1 clone ' . $context['repository'] . ' ' . $deploy;
  drush_print($command);
  if (!drush_shell_exec($command)) {
    foreach (drush_shell_exec_output() as $line) {
      drush_log('git: ' . $line, 'error');
    }

    return drake_action_error(dt('Error cloning ding_deploy from "@repo"', array('@repo' => $context['repository'])));
  }

  // Run make with same args as ding_build.py would.
  $args = array($deploy . '/ding.make', $context['root']);
  $options = array(
    'contrib-destination' => 'profiles/ding',
    'working-copy' => TRUE,
  );
  $res = drush_invoke_process('@none', 'make', $args, $options, TRUE);

  if (!$res || $res['error_status'] != 0) {
    return drake_action_error(dt('Drush Make failed.'));
  }

  // Copy ding.profile into the profile folder.
  $command = 'cp ' . $deploy . '/ding.profile ' . $context['root'] . '/profiles/ding';
  if (!drush_shell_exec($command)) {
    return drake_action_error(dt('Error copying ding.profile.'));
  }

  // Copy the drakefile into the profile folder.
  $command = 'cp ' . $deploy . '/drakefile.php ' . $context['root'] . '/profiles/ding';
  if (!drush_shell_exec($command)) {
    return drake_action_error(dt('Error copying drakefile.'));
  }
}

$actions['reload-ding-rebuild'] = array(
  'callback' => 'reload_ding_rebuild',
  'parameters' => array(
    'root' => 'Directory to rebuild to.',
    'repository' => 'URL to the ding_deploy repository.',
  ),
);

function reload_ding_rebuild($context) {
  // Initial sanity checks.
  $profile = $context['root'] . '/profiles/ding';
  if (!file_exists($profile)) {
    return drake_action_error(dt("Couldn't find ding profile dir in @path.", array('@path' => $context['root'])));
  }

  // Temporary directory for ding_deploy checkout.
  $deploy = drush_tempdir('ding_deploy_') . '/ding-deploy';
  $command = 'git 2>&1 clone ' . $context['repository'] . ' ' . $deploy;
  if (!drush_shell_exec($command)) {
    foreach (drush_shell_exec_output() as $line) {
      drush_log('git: ' . $line, 'error');
    }

    return drake_action_error(dt('Error cloning ding_deploy from "@repo"', array('@repo' => $context['repository'])));
  }

  $command = 'rm -rf 2>&1 ' . $profile;
  if (!drush_shell_exec($command)) {
    foreach (drush_shell_exec_output() as $line) {
      drush_log('rm: ' . $line, 'error');
    }

    return drake_action_error(dt('Error deleting profile directory "@profile"', array('@profile' => $profile)));
  }


  // Run make with same args as ding_build.py would.
  $args = array($deploy . '/ding.make', $profile);
  $options = array(
    'no-core' => TRUE,
    'contrib-destination' => '.',
    'working-copy' => TRUE,
  );
  $res = drush_invoke_process('@none', 'make', $args, $options, TRUE);

  if (!$res || $res['error_status'] != 0) {
    return drake_action_error(dt('Drush Make failed.'));
  }

  // Copy ding.profile into the profile folder.
  $command = 'cp ' . $deploy . '/ding.profile ' . $context['root'] . '/profiles/ding';
  if (!drush_shell_exec($command)) {
    return drake_action_error(dt('Error copying ding.profile.'));
  }

  // Copy the drakefile into the profile folder.
  $command = 'cp ' . $deploy . '/drakefile.php ' . $context['root'] . '/profiles/ding';
  if (!drush_shell_exec($command)) {
    return drake_action_error(dt('Error copying drakefile.'));
  }
}
