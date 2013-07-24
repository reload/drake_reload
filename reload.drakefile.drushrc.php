<?php

/**
 * @file
 * Common drake task for Reload.
 */

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
  'action' => 'reload-git-clone',
  'directory' => context('root'),
  'repository' => context('repository'),
  'branch' => context_optional('branch', ''),
  'sha' => context_optional('sha', ''),
  'reference' => context_optional('reference', ''),
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

/**
 * Build the currently checked out revision using make.
 *
 * Requires some hoop-jumping involving rewriting the makefile.
 */
$tasks['reload-ci-build-make'] = array(
  'action' => 'reload-ci-build-make',
  'root' => context('root'),
  'make-file' => context('make-file'),
);

/**
 * Build the currently checked out revision using git.
 *
 * While this uses git to check out, it uses the --reference option to use the
 * current repo as a cache. Saves network traffic.
 */
$tasks['reload-ci-build-git'] = array(
  'action' => 'reload-ci-build-git',
  'root' => context('root'),
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
  'depends' => array(
    'reload-drop-db',
    'import-db',
    context('reload-sanitize-[core]'),
    'reload-updb',
    'enable-modules',
    'reload-cc-all',
  ),
);

/*
 * Same as reload-import-site, but using the import-file task instead.
 */
$tasks['reload-import-file'] = array(
  // The import-file task is expected to be defined in the site drakefile.
  'depends' => array(
    'reload-drop-db',
    'import-file',
    context('reload-sanitize-[core]'),
    'reload-updb',
    'enable-modules',
    'reload-cc-all',
  ),
);

/*
 * Default no-op task.
 *
 * This just makes enable-modules optional in the site drakefile.
 */
$tasks['enable-modules'] = array();

/*
 * Fixes error_level on ding-sites to avoid notices.
 */
$tasks['reload-ding-fix-error-level'] = array(
  'action' => 'reload-ding-fix-error-level',
  'target' => context('@sync_target'),
);

/*
 * Kills old versions of mobile tools incompatible with drush.
 */
$tasks['reload-disable-old-mobile-tools'] = array(
  'action' => 'drush',
  'target' => context('@sync_target'),
  'command' => 'drake-reload-mobile-tools-workaround',
);

/*
 * Fixes mobile tools settings to point to local site.
 */
$tasks['reload-fix-mobile-tools'] = array(
  'action' => 'reload-fix-mobile-tools',
  'target' => context('@sync_target'),
);

/*
 * Sanitize database post-import, Drupal 7.
 */
$tasks['reload-sanitize-7.x'] = array(
  'action' => 'drush',
  'help' => 'Sanitizes database post-import (7.x common).',
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
 * Sanitize database post-import, Drupal 6.
 */
$tasks['reload-sanitize-6.x'] = array(
  'action' => 'drush',
  'help' => 'Sanitizes database post-import (6.x common).',
  'commands' => array(
    // Disable aggregation and cache.
    array('command' => 'vset', 'args' => array('preprocess_js', '0')),
    array('command' => 'vset', 'args' => array('preprocess_css', '0')),
    array('command' => 'vset', 'args' => array('cache', '0')),
    // Set file paths.
    array(
      'command' => 'vset',
      'args' => array('file_directory_path', context('[@sync_target:site:path]/files')),
    ),
    array(
      'command' => 'vset',
      'args' => array('file_directory_temp', '/tmp'),
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
 * Log information about the current revision into a version.txt.
 *
 * Copy this task to your local drakefile if you want to define git-root via a
 * local context and not directly on the commandline.
 *
 * log-envs references OS environment variables.
 *
 * Sample invocation (with temporary envs):
 *   Build_number=123 Build_time="Around Noon" drush @self drake log-version \
 *     git-root=/path/to/my/git/root log-envs="Build_number, Build_time"
 */
$tasks['log-version'] = array(
  'action' => 'log-version',
  'log-filename' => context_optional('log-filename', context('[@self:site:root]/version.txt')),
  'git-root' => context_optional('git-root'),
  'header' => context_optional('log-header', 'Release Info'),
  'log-envs' => context_optional('log-envs'),
  'log-enabled' => context_optional('log-enabled'),
  'log-disabled' => context_optional('log-disabled'),
);

/*
 * Log information about the current revision into a version.txt.
 */
$actions['log-version'] = array(
  'default_message' => 'Logging version.',
  'callback' => 'drake_log_version',
  'parameters' => array(
    'log-filename' => 'Output filename.',
    'header'       => 'Output file header.',
    'git-root'     => array(
      'description' => 'Full path to a .git directory.',
      'default'     => NULL,
    ),
    'log-enabled'   => array(
      'description' => 'Commaseperated list of buildin values to log, currently supports git_branch, git_tag, git_sha or all',
      'default'     => 'all',
    ),
    'log-disabled'   => array(
      'description' => 'Commaseperated list of buildin values not to log, currently supports git_branch, git_tag, git_sha',
      'default'     => '',
    ),
    'log-envs'     => array(
      'description' => 'Comma separated list of environement variables that should be included. Names will be stripped of underscores.',
      'default'     => NULL,
    ),
  ),
);

/**
 * Logs details about a revision into a text-file.
 */
function drake_log_version($context) {

  // Prepare switches that tells us what to log.
  if ($context['log-enabled'] == 'all') {
    $context['log-enabled'] = 'git_branch,git_tag,git_sha';
  }
  // Split and trim enabled things to log.
  $log_enabled = array_map('trim', explode(',', $context['log-enabled']));

  // Remove disabled things if any are specified.
  if (!empty($context['log-disabled'])) {
    $log_enabled = array_diff($log_enabled, array_map('trim', explode(',', $context['log-disabled'])));
  }

  // Keys/values to be logged.
  $log_lines = array();
  // Determine GIT information if git-root is known.
  if ($context['git-root'] !== NULL) {

    // Determine branch - this takes some manual handling.
    if (!drush_shell_exec("git --git-dir=%s/.git symbolic-ref -q HEAD", $context['git-root'])) {
      $branch = '(no branch)';
    }
    else {
      $output = implode(drush_shell_exec_output());
      $branch = empty($output) ? '(no branch)' : str_replace('refs/heads/', '', $output);
    }
    $log_lines['Branch'] = $branch;

    // Remaining commands are straight forward so handle them the same way.
    if (in_array('git_sha', $log_enabled)) {
     $cmds['SHA'] = 'rev-parse HEAD';
    }

    if (in_array('git_tag', $log_enabled)) {
     $cmds['Tags'] = 'tag --contains HEAD';
    }

    if (!empty($cmds)) {
      // Map keys to command output.
      $mapper = function($cmd) use ($context){
        if (!drush_shell_exec("git --git-dir=%s/.git $cmd", $context['git-root'])) {
          return drake_action_error(dt('Error running git command "@cmd" at git-root "@root". Output: @output', array('@root' => $context['git-root'], '@cmd' => $cmd, '@output' => implode("\n", drush_shell_exec_output()))));
        }
        $output = drush_shell_exec_output();

        // Implode or return ''.
        return (empty($output) || !is_array($output)) ? '' : implode(' ', $output);
      };

      // Have the mapper run the commands and merge the result into the log_lines.
      $log_lines = array_merge($log_lines, array_map($mapper, $cmds));
    }
  }

  // Get enviroment variables if any.
  if ($context['log-envs'] !== NULL) {
    $envs = explode(',', $context['log-envs']);

    foreach ($envs as $env) {
      $env = trim($env);
      $log_lines[str_replace('_', ' ', $env)] = getenv($env);
    }
  }

  // Generate output;
  $output_lines = array();
  // Start with the header with a nice underlining.
  $output_lines[] = $context['header'];
  $output_lines[] = str_repeat('-', strlen($context['header']));
  foreach ($log_lines as $name => $line) {
    $output_lines[] = $name . ': ' . $line;
  }
  file_put_contents($context['log-filename'], implode("\r\n", $output_lines) . "\r\n");
}

/**
 * Action to clone a repository.
 */
$actions['reload-git-clone'] = array(
  'callback' => 'reload_git_clone',
  'parameters' => array(
    'directory' => 'Directory to clone into',
    'repository' => 'Repository to clone',
    'branch' => array(
      'description' => 'Branch to check out.',
      'default' => '',
    ),
  ),
);

/**
 * Clone a repository.
 */
function reload_git_clone($context) {
  $args = '';
  if (!empty($context['reference'])) {
    $args = '--reference ' . $context['reference'];
  }
  if (!drush_shell_exec('git 2>&1 clone ' . $args . ' %s %s', $context['repository'], $context['directory'])) {
    foreach (drush_shell_exec_output() as $line) {
      drush_log('git: ' . $line, 'error');
    }
    return drake_action_error(dt('Error cloning repository.'));
  }

  if (empty($context['branch']) && empty($context['sha'])) {
    return TRUE;
  }

  $cwd = getcwd();
  // Change into the working copy of the cloned repo.
  chdir($context['directory']);

  $res = TRUE;
  if (!empty($context['sha'])) {
    $what = $context['sha'];
    $git_res = drush_shell_exec('git 2>&1 checkout %s', $context['sha']);
  }
  elseif (!empty($context['branch'])) {
    $what = $context['branch'];
    $git_res = drush_shell_exec('git 2>&1 checkout -b %s %s', $context['branch'], 'origin/' . $context['branch']);
  }

  if (!$git_res) {
    foreach (drush_shell_exec_output() as $line) {
      drush_log('git: ' . $line, 'error');
    }
    $res = drake_action_error(dt('Could not check out @what branch from @repo.', array('@branch' => $what, '@repo' => $context['repository'])));
  }

  chdir($cwd);
  return $res;
}

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

/**
 * Import a SQL file.
 *
 * Will attempt to decompress common complession types.
 */
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

    $res = drush_shell_exec('%s 2>>%s %s | %s 2>>%s', $cat_command, $err_file, $file, $res['output'], $err_file);
    $errors = file_get_contents($err_file);
    if (!$res || !empty($errors)) {
      foreach (explode("\n", $errors) as $error) {
        drush_log($error, 'error');
      }
      return drake_action_error(dt('Database import failed.'));
    }
    else {
      drush_log(dt('Database imported.'), 'ok');
    }
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

/**
 * Setup git flow in a repository.
 */
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
    // Check whether the branch exists. git branch doesn't if it does.
    drush_shell_exec('git --git-dir=%s/.git branch --list %s', $context['git-root'], $branch);
    $output = drush_shell_exec_output();
    if (!empty($output)) {
      continue;
    }
    // Else tell git to create a remote tracking branch.
    $branch_name = $context['prefix'] . $branch;
    if (!drush_shell_exec_interactive('git --git-dir=%s/.git branch -t %s remotes/origin/%s', $context['git-root'], $branch_name, $branch_name)) {
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

/**
 * Build a ding site.
 *
 * Clones ding-deploy, runs make and copies ding.profile and drakefile.php in.
 */
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
  if (!drush_shell_exec('git 2>&1 clone %s %s', $context['repository'], $deploy)) {
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
  if (!drush_shell_exec('cp %s/ding.profile %s/profiles/ding', $deploy, $context['root'])) {
    return drake_action_error(dt('Error copying ding.profile.'));
  }

  // Copy the drakefile into the profile folder.
  if (!drush_shell_exec('cp %s/drakefile.php %s/profiles/ding', $deploy, $context['root'])) {
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

/**
 * Rebuild a ding site.
 *
 * Basically the same as reload_ding_build, but only overwriting the profile
 * dir.
 */
function reload_ding_rebuild($context) {
  // Initial sanity checks.
  $profile = $context['root'] . '/profiles/ding';
  if (!file_exists($profile)) {
    return drake_action_error(dt("Couldn't find ding profile dir in @path.", array('@path' => $context['root'])));
  }

  // Temporary directory for ding_deploy checkout.
  $deploy = drush_tempdir('ding_deploy_') . '/ding-deploy';
  if (!drush_shell_exec('git 2>&1 clone %s %s', $context['repository'], $deploy)) {
    foreach (drush_shell_exec_output() as $line) {
      drush_log('git: ' . $line, 'error');
    }

    return drake_action_error(dt('Error cloning ding_deploy from "@repo"', array('@repo' => $context['repository'])));
  }

  if (!drush_shell_exec('rm -rf 2>&1 %s', $profile)) {
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
  if (!drush_shell_exec('cp %s/ding.profile %s/profiles/ding', $deploy, $context['root'])) {
    return drake_action_error(dt('Error copying ding.profile.'));
  }

  // Copy the drakefile into the site/all/drush folder.
  // Create the dir.
  if (!drush_shell_exec('mkdir -p %s/sites/all/drush', $context['root'])) {
    return drake_action_error(dt('Error creating sites/all/drush.'));
  }
  if (!drush_shell_exec('cp %s/drakefile.php %s/sites/all/drush', $deploy, $context['root'])) {
    return drake_action_error(dt('Error copying drakefile.'));
  }
}

$actions['reload-ci-build-make'] = array(
  'callback' => 'reload_ci_build_make',
  'parameters' => array(
    'root' => 'The directory to build into.',
    'make-file' => "The source makefile that's massaged.",
  ),
);

/**
 * Build a make site for CI.
 *
 * Attempts to rewrite the makefile to fetch the exact same version as is
 * checked out in the current dir.
 */
function reload_ci_build_make($context) {
  // Figure out the SHA and origin of the current dir.
  $sha = trim(`git rev-parse HEAD`);
  if (empty($sha)) {
    return drake_action_error(dt('Could not find revision of current directory.'));
  }
  $origin = trim(`git config --get remote.origin.url`);
  if (empty($origin)) {
    return drake_action_error(dt('Could not find origin of current directory.'));
  }

  $make_data = file_get_contents($context['make-file']);
  if (empty($make_data)) {
    return drake_action_error(dt('Could not read makefile.'));
  }

  $make_data = explode("\n", $make_data);
  foreach ($make_data as $line) {
    if (preg_match("/^(\w+)(?:\[(\w+)\])((\[\w+\])+)\s+=\s+\"?([^\"]*)\"?/", $line, $matches)) {
      if ($matches[3] == '[download][url]' && $matches[5] == $origin) {
        $type = $matches[1];
        $project_name = $matches[2];
        break;
      }
    }
  }

  if (empty($project_name)) {
    drake_action_error(dt('Could not find repoository in make file.'));
  }

  $new_make_file = array();
  foreach ($make_data as $line) {
    if (preg_match("/^($type)(?:\[(${project_name})\])((\[\w+\])+)\s+=\s+\"?([^\"]*)\"?/", $line, $matches)) {
      if ($matches[3] == '[download][url]' && $matches[5] == $origin) {
        $new_make_file[] = $line;
        // Add new revision specifier.
        $new_make_file[] = $type . '[' . $project_name . '][download][revision] = ' . $sha;
      }
      elseif (preg_match('/^\[(branch|tag|revision)\]$/', $matches[4])) {
        // Remove previous tag/branch/revision specifiers.
      }
      else {
        $new_make_file[] = $line;
      }
    }
    else {
      $new_make_file[] = $line;
    }
  }

  $tmp_make = drush_tempnam('drake_reload_make');
  if (!file_put_contents($tmp_make, implode("\n", $new_make_file))) {
    return drake_action_error(dt('Could not save temporary make file.'));
  }

  $args = array(
    'build',
    $context['root'],
    'makefile=' . $tmp_make,
  );
  $res = drush_invoke_process('@none', 'drake', $args, array(), TRUE);

  if (!$res || $res['error_status'] != 0) {
    drush_set_error(dt('Building failed.'));
  }
}

$actions['reload-ci-build-git'] = array(
  'callback' => 'reload_ci_build_git',
  'parameters' => array(
    'root' => 'The directory to build into.',
  ),
);

/**
 * Build a git site for CI.
 *
 * Clones the repo and checks out the same SHA as is in the current directory,
 * but uses --reference to save bandwidth, while still working like cloning the
 * origin.
 */
function reload_ci_build_git($context) {
  $git_root = `git rev-parse --show-toplevel`;
  if (empty($git_root)) {
    return drake_action_error(dt('Could not find a git checkout in current directory.'));
  }

  // Figure out the SHA and origin of the current dir.
  $sha = trim(`git rev-parse HEAD`);
  if (empty($sha)) {
    return drake_action_error(dt('Could not find revision of current directory.'));
  }
  $origin = trim(`git config --get remote.origin.url`);
  if (empty($origin)) {
    return drake_action_error(dt('Could not find origin of current directory.'));
  }

  // Delete existing build dir.
  if (file_exists($context['root'])) {
    drush_delete_dir($context['root']);
  }

  $args = array(
    'build',
    $context['root'],
    'sha=' . $sha,
    'reference=' . $git_root,
  );
  $res = drush_invoke_process('@self', 'drake', $args, array(), TRUE);

  if (!$res || $res['error_status'] != 0) {
    drush_set_error(dt('Building failed.'));
  }
}

$actions['reload-ding-fix-error-level'] = array(
  'callback' => 'reload_ding_fix_error_level',
  'parameters' => array(
    'target' => 'Alias of site to fix error level for.',
  ),
);

/**
 * Modifies the site settings.php to suppress warnings.
 *
 * Only needed for Drupal 6 sites.
 */
function reload_ding_fix_error_level($context) {
  $alias = $context['target'];
  $site_record = drush_sitealias_get_record($alias);
  $site_folder = drush_sitealias_local_site_path($site_record);
  if (!file_exists($site_folder)) {
    return drake_action_error(dt('Could not find site directory for @alias.', array('@alias' => $alias)));
  }

  $settings_file = $site_folder . '/settings.php';
  if (!file_exists($settings_file)) {
    return drake_action_error(dt('Could not find site settings.php in "@folder".', array('@folder' => $site_folder)));
  }

  $settings = file_get_contents($settings_file);
  if (!preg_match('/error_reporting\(/', $settings)) {
    drush_print(dt('No error_reporting setting found in settings.php, adding it.'));
    $settings = trim($settings) . '

// Drupal 6 is rather noisy on the notice front. We used to just silence
// notices in php.ini, but that\'s considered bad practice these days, so
// instead we set it for this site only.
error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED);
';
    file_put_contents($settings_file, $settings);
  }
}

$actions['reload-fix-mobile-tools'] = array(
  'callback' => 'reload_fix_mobile_tools',
  'parameters' => array(
    'target' => 'Alias of site to fix mobile_tools on.',
  ),
);

/**
 * Fixes the URLs of mobile_tools to point to the local installation.
 */
function reload_fix_mobile_tools($context) {
  $alias = $context['target'];
  $args = array(
    'mobile_tools_desktop_url',
  );
  $res = drush_invoke_process($alias, 'vget', $args, array(), TRUE);
  if (!isset($res['object']['mobile_tools_desktop_url'])) {
    // Variable not set, we assume that mobile_tools are not enabled.
    drush_print(dt('mobile_tools_desktop_url variable not set, assuming mobile_tools is disabled.'));
    return;
  }

  $site_record = drush_sitealias_get_record($alias);

  if (!empty($site_record['uri'])) {
    $url = $site_record['uri'];

    if (preg_match('{^(https?://)(.*)$}', $url, $matches)) {
      $mobile_url = $matches[1] . 'm.' . $matches[2];

      $args = array('mobile_tools_desktop_url', $url);
      $res = drush_invoke_process($alias, 'vset', $args, array(), TRUE);

      $args = array('mobile_tools_mobile_url', $mobile_url);
      $res = drush_invoke_process($alias, 'vset', $args, array(), TRUE);
    }
    else {
      return drake_action_error(dt('Malformed URL "@url" for @alias.', array('@alias' => $alias, '@url' => $url)));
    }
  }
  else {
    return drake_action_error(dt('Could not find local site url for @alias.', array('@alias' => $alias)));
  }
}
