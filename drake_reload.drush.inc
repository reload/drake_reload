<?php

/**
 * @file
 * Drush command for generating drake_reload based drakefiles.
 */


/**
 * Implements hook_drush_command().
 */
function drake_reload_drush_command() {
  $items['drake-reload-generate'] = array(
    'description' => 'Generate drakefile.',
    'arguments' => array(
      'filename' => 'File to write to (optional).',
    ),
    'aliases' => array('drg'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );

  $items['drake-reload-install'] = array(
    'description' => 'Install all parts for drake_reload.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );

  $items['drake-reload-bootstrap'] = array(
    'description' => 'Run Drake build for a site alias.',
    'arguments' => array(
      'alias' => 'Alias for site to bootstrap.',
      'path' => 'The path to build to.',
    ),
    'aliases' => array('drb'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );

  $items['drake-reload-sites'] = array(
    'description' => 'Show list of mole_zero known sites.',
    'aliases' => array('drs'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );

  $items['drake-reload-mobile-tools-workaround'] = array(
    'description' => 'Disable mobile_tools redirection if it is in a version know no be incompatible with drush.',
    'aliases' => array('drmtw'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_DATABASE,
  );

  return $items;
}

/**
 * Implements hook_drush_help().
 */
function drake_reload_drush_help($section) {
  switch ($section) {
    case 'meta:drake_reload:title':
      return dt('Drake Reload');

    case 'meta:drake_reload:summary':
      return dt('Generate Drake Reload based drakefiles.');

    case 'drush:drake-reload-generate':
      return dt("Generate drakefile.");

    case 'drush:drake-reload-install':
      return dt("Install all parts for drake_reload.");

    case 'drush:drake-reload-bootstrap':
      return dt("Run Drake build for a site alias.

The site alias should have a drake_reload setting that points to the drakefile.

Example:
\$aliases['site.common'] = array(
  'remote-host' => 'site.dk',
  'remote-user' => 'reload',
  'drake_reload' => array(
    'drakefile' => 'https://raw.github.com/reload/site/master/sites/all/drush/drakefile.php',
  ),
);

Preferably in mole_zero (ask someone if you have no clue what that means).

");

    case 'drush:drake-reload-sites':
      return dt("Show list of mole_zero known sites.");

    case 'drush:drake-reload-mobile-tools-workaround':
      return dt("Disable redirection mobile_tools if it is in a version know no be incompatible with drush.

This is not foolproof, as Features could possibly revert the variable, but you
can re-run this command to set it back.");
  }
}

/**
 * Ask user some questions.
 *
 * Given an array of questions and optional default values, gets the answers
 * from the user, taking care of dependencies, affirmative mode and other
 * details.
 */
function drake_reload_run_questions($questions, $settings) {
  $new_settings = array();
  $rebuilding = !empty($settings);
  foreach ($questions as $key => $question) {
    $default = !empty($settings[$key]) ? $settings[$key] : '';
    // Check question dependencies.
    if (!empty($question['depends'])) {
      foreach ($question['depends'] as $field => $value) {
        if (is_array($value)) {
          // If the dependency is an array, use in_array.
          if (!in_array($new_settings[$field], $value)) {
            // Skip question.
            continue 2;
          }
        }
        else {
          // If dependency is not an array, simply compare.
          if ($new_settings[$field] != $value) {
            // Skip question.
            continue 2;
          }
        }
      }
    }
    if (!empty($question['description'])) {
      drush_print($question['description']);
    }
    switch ($question['type']) {
      case 'string':
        $new_settings[$key] = trim(drush_prompt($question['prompt'], $default, $question['required']));
        break;

      case 'option':
        $new_settings[$key] = drush_choice($question['options'], $question['prompt'] . ($rebuilding ? '[' . $default . ']' : ''));
        if ($rebuilding) {
          if (!$new_settings[$key] || drush_get_context('DRUSH_AFFIRMATIVE')) {
            $new_settings[$key] = $default;
          }
        }
        if (!$new_settings[$key]) {
          return drush_user_abort();
        }
        break;

      case 'bool':
        if ($rebuilding && drush_get_context('DRUSH_AFFIRMATIVE')) {
          $new_settings[$key] = $default;
        }
        $new_settings[$key] = drush_confirm($question['prompt']);
        break;

      case 'envs':
        $envs = array();
        if ($rebuilding && drush_get_context('DRUSH_AFFIRMATIVE')) {
          $new_settings[$key] = $default;
        }
        $env_default = array();
        if (!empty($settings[$key])) {
          foreach ($settings[$key] as $env_name => $env) {
            $env_default[] = implode(',', array(
                               $env_name,
                               $env['alias'],
                               $env['name'],
                             ));
          }
        }
        do {
          $def = array_shift($env_default);
          $env = drush_prompt($question['prompt'], $def, FALSE);
          if (!empty($env)) {
            $parts = explode(',', $env, 3);
            $env_id = trim($parts[0]);
            if (!preg_match('/^[a-zA-Z0-9]*$/', $env_id)) {
              drush_print(dt('Invalid env id.'));
              continue;
            }
            if (!preg_match('/^@[a-zA-Z0-9_.-]*$/', trim($parts[1]))) {
              drush_print(dt('Invalid alias.'));
              continue;
            }
            if (isset($envs[$env_id])) {
              drush_print(dt('Duplicate env.'));
              continue;
            }
            else {
              $envs[$env_id] = array(
                'alias' => trim($parts[1]),
                'name' => trim($parts[2]),
              );
            }
          }
        } while (!empty($env));
        $new_settings['envs'] = $envs;
        break;

      default:
        return drush_set_error('BAD_QUESTION', dt('Unknown question type @type.', array('@type' => $question['type'])));
    }
  }
  return $new_settings;
}

/**
 * Load the question answers from an existing file.
 */
function drake_reload_load_settings($filename) {
  if (file_exists($filename)) {
    include $filename;
  }
  if (is_array($drake_reload)) {
    return $drake_reload;
  }
  return array();
}

/**
 * Command callback; generate a drakefile by asking questions.
 */
function drush_drake_reload_generate($filename = NULL) {
  $template = file_get_contents(dirname(__FILE__) . '/drakefile.template.php');
  $chunks = explode("\n\n", $template);
  $global_replacements = array();

  // Load custom additions from the previous drakefile.
  $additions = '';
  $custom_tasks = array();
  $custom_tasks_regexp = '';
  if (file_exists($filename)) {
    $contents = file_get_contents($filename);
    if (preg_match('{// ### Everything below this will be retained by drush-reload-generate ###(.*)$}s', $contents, $matches)) {
      $additions = trim($matches[1]);
      if ($additions) {
        // Collect a list of tasks defined. Yeah, the regexp will fail on ' in "
        // strings and " in ' strings, but then you're asking for it.
        if (preg_match_all('{\$tasks\[(\'|")([^\'"]+)\1\]\s*=\s*}', $additions, $matches)) {
          $custom_tasks = $matches[2];
          $custom_tasks_regexp = array();
          foreach ($custom_tasks as $task) {
            $custom_tasks_regexp[] = preg_quote($task, '/');
          }
          $custom_tasks_regexp = '(' . implode('|', $custom_tasks_regexp) . ')';
        }
      }
    }
  }

  $previous_settings = drake_reload_load_settings($filename);
  $questions = array(
    'core' => array(
      'type' => 'option',
      'required' => TRUE,
      'prompt' => dt('Drupal version?'),
      'options' => array(
        '6.x' => 'Drupal 6',
        '7.x' => 'Drupal 7',
      ),
    ),
    'site_name' => array(
      'type' => 'string',
      'required' => TRUE,
      'prompt' => dt('Site name'),
      'description' => dt('Site name is used in comments and setting the site site_name when sanitizing database.'),
    ),
    'type' => array(
      'type' => 'option',
      'required' => TRUE,
      'prompt' => dt('Building method?'),
      'options' => array(
        'make' => 'Make',
        'git' => 'Git',
        'ding' => 'Ding',
      ),
    ),
    'git_branch' => array(
      'type' => 'string',
      'depends' => array(
        'type' => 'git',
      ),
      'required' => FALSE,
      'prompt' => dt('Branch to check out (empty for default).'),
    ),
    'git_flow' => array(
      'depends' => array(
        'type' => array(
          'make',
          'git',
        ),
      ),
      'type' => 'bool',
      'prompt' => dt('Use git flow?'),
    ),
    'flow_path' => array(
      'depends' => array(
        'git_flow' => TRUE,
      ),
      'type' => 'string',
      'required' => FALSE,
      'prompt' => dt("Flowify repo"),
      'description' => dt('Enter path to flowify (relative to site root, example: "profiles/<profile>"), or just press enter to flowify root.'),
    ),
    'make_file_url' => array(
      'depends' => array(
        'type' => 'make',
      ),
      'type' => 'string',
      'prompt' => dt("Enter makefile URL"),
    ),
    'make_file_path' => array(
      'depends' => array(
        'type' => 'make',
      ),
      'type' => 'string',
      'prompt' => dt("Make file path"),
      'description' => dt('Enter makefile filename relative to site root. Most likely profiles/<profile>/build_<profile>.make. Or leave empty to always build from the URL.'),
    ),
    'git_url' => array(
      'depends' => array(
        'type' => 'git',
      ),
      'type' => 'string',
      'prompt' => dt('Enter Git repository URL'),
    ),
    'ding_url' => array(
      'depends' => array(
        'type' => 'ding',
      ),
      'type' => 'string',
      'prompt' => dt('Enter ding_deploy repository URL'),
    ),
    'envs' => array(
      'type' => 'envs',
      'prompt' => dt('Env'),
      'description' => dt('Please enter environments for site in the form "<env>,<alias>,<name>" where:

<env> is a alphanumeric short string used internally for identification (prod/stg/dev works well).

<alias> is the site alias of the environment.

<name> is a human readable name used in comments and description, "Production" and "Development" is good suggestions.

Finish by entering an empty alias.'),

    ),
  );

  $settings = drake_reload_run_questions($questions, $previous_settings);
  if (!$settings) {
    return FALSE;
  }
  $global_replacements['%core%'] = $settings['core'];
  $global_replacements['%site_name%'] = $settings['site_name'];
  $global_replacements['%git_branch%'] = $settings['git_branch'];
  $global_replacements['%flow_path%'] = $settings['flow_path'];
  $global_replacements['%make_file_url%'] = $settings['make_file_url'];
  $global_replacements['%make_file_path%'] = empty($settings['make_file_path']) ? "makefile" : "[@self:site:root]/" . $settings['make_file_path'];
  $global_replacements['%git_url%'] = $settings['git_url'];
  $global_replacements['%ding_url%'] = $settings['ding_url'];

  $new_chunks = array();
  foreach ($chunks as &$chunk) {
    // Save settings for re-running.
    $dump = var_export($settings, TRUE);
    // Clean up some var_export ugliness.
    $dump = preg_replace('/array \(/', 'array(', $dump);
    $dump = preg_replace('/=>\s+/', '=> ', $dump);
    if (preg_match('{\$drake_reload = }', $chunk)) {
      $chunk = preg_replace('{\$drake_reload = array\(.*\);}', "\$drake_reload = " . $dump . ";\n", trim($chunk));
    }

    // Modify global context.
    if (preg_match('{\$context = }', $chunk)) {
      $context = array();
      $context[] = '  // Drupal core version.';
      $context[] = "  'core' => '%core%',";
      // Add envs to $context.
      foreach ($settings['envs'] as $env_name => $env) {
        $context[] = '  // ' . $env['name'] . ' site alias.';
        $context[] = "  '@env." . $env_name . "' => '" . $env['alias'] . "',";
      }

      if ($settings['type'] == 'make') {
        $context[] = '  // Bootstrap makefile.';
        $context[] = "  'makefile' => '%make_file_url%',";
      }
      elseif ($settings['type'] == 'git') {
        $context[] = '  // Git repository.';
        $context[] = "  'repository' => '%git_url%',";
        $context[] = '  // Branch to checkout.';
        $context[] = "  'branch' => '%git_branch%',";
      }
      elseif ($settings['type'] == 'ding') {
        $context[] = '  // ding_deploy repository.';
        $context[] = "  'repository' => '%ding_url%',";
      }
      if ($settings['git_flow']) {
        $context[] = '  // Git Flow path.';
        $context[] = "  'git-root' => context('[root]/%flow_path%'),";
      }
      $chunk = preg_replace('{\$context = array\(\);}', "\$context = array(\n" . implode("\n", $context) . "\n);", $chunk);
    }

    // Filter out build tasks that's not needed.
    if (preg_match('{\$tasks\[\'((?:re)?build)-(make|git|ding)\'\] = }', $chunk, $matches)) {
      if ($matches[2] != $settings['type']) {
        continue;
      }
      // Fix the task name.
      $chunk = preg_replace('{\$tasks\[\'((?:re)?build)-(make|git|ding)\'\]}', '\$tasks[\'\1\']', $chunk);
    }

    // Filter out sanitize tasks that's not needed.
    if (preg_match('{\$tasks\[\'(sanitize)-(nonding|ding)([^\']*)\'\] = }', $chunk, $matches)) {
      if (($matches[2] == 'nonding' && $settings['type'] == 'ding') ||
        ($matches[2] == 'ding' && $settings['type'] != 'ding')) {
        continue;
      }
      // Fix the task name.
      $chunk = preg_replace('{\$tasks\[\'(sanitize)-(nonding|ding)([^\']*)\'\]}', '\$tasks[\'\1\3\']', $chunk);
    }

    // Clone import-prod to each env.
    if (preg_match('{\$tasks\[\'import-%env%\'\] = }', $chunk)) {
      $imports = array();
      foreach ($settings['envs'] as $env_name => $env) {
        $replacements = array(
          '%env%' => $env_name,
          '%env_name%' => $env['name'],
          '%env_alias%' => $env['alias'],
        );
        $imports[] = strtr($chunk, $replacements);
      }
      $chunk = implode("\n\n", $imports);
    }

    // Filter out any tasks that in custom additions. We're doing it last so the
    // above can rename tasks first.
    if (preg_match('{\$tasks\[\'' . $custom_tasks_regexp . '\'\] = }', $chunk, $matches)) {
      continue;
    }

    // Remove references to git flow, if not selected.
    if (!$settings['git_flow']) {
      $chunk = preg_replace("{, 'reload-flow-setup'}", '', $chunk);
    }

    // Remove references to the error_level fixer, if not a ding site..
    if ($settings['type'] != 'ding') {
      $chunk = preg_replace("{, 'reload-ding-fix-error-level'}", '', $chunk);
    }

    // Replace global replacements.
    $new_chunks[] = strtr($chunk, $global_replacements);
  }

  $contents = implode("\n\n", $new_chunks) . "\n" . $additions;
  if ($filename) {
    if (file_exists($filename)) {
      $tmp_file = drush_tempnam('drake_reload_');
      file_put_contents($tmp_file, file_get_contents($filename));
    }
    drush_log(dt('Wrote @file.', array('@file' => $filename)), 'status');
    file_put_contents($filename, $contents);
    if (!empty($tmp_file)) {
      drush_print(dt('Difference from previous:'));
      drush_shell_exec_interactive('diff ' . $tmp_file . ' ' . $filename);
    }
  }
  else {
    drush_print($contents);
  }
}

/**
 * Command callback; install dependencies.
 */
function drush_drake_reload_install() {
  $res = drush_invoke_process('@self', 'pm-download', array('drush_situs'), array('no' => TRUE), TRUE);
  $res = drush_invoke_process('@self', 'pm-download', array('drush_drake'), array('no' => TRUE), TRUE);

  // Check that mole_zero is available.
  $per_user_config_dir = drush_get_context('DRUSH_PER_USER_CONFIGURATION');
  if (!empty($per_user_config_dir)) {
    if (!file_exists($per_user_config_dir . '/r.aliases.drushrc.php')) {
      drush_log(dt('mole_zero not detected. Fetching r.aliases.drushrc.php from reload@ding.reload.dk...'), 'status');
      $fetch = TRUE;
    }
    else {
      $fetch = drush_confirm(dt('mole_zero detected, refresh it?'));
    }
    if ($fetch) {
      $command = 'scp 2>&1 reload@ding.reload.dk:mole_zero/r.aliases.drushrc.php ' .
        $per_user_config_dir . '/r.aliases.drushrc.php';
      $res = drush_shell_exec($command);
      $errors = file_get_contents($err_file);
      if (!$res) {
        drush_print('Error fetching r.aliases.drushrc.php, scp output:');
        drush_print(implode("\n", drush_shell_exec_output()));
        return drush_set_error('MOLE_ZERO_SETUP_ERROR', dt('Error setting up mole_zero.'));
      }
    }
  }
}

/**
 * Command callback validator; checks arguments for the boostrap command.
 */
function drush_drake_reload_bootstrap_validate($alias = NULL, $path = NULL) {
  if (empty($alias)) {
    return drush_set_error('NO_ALIAS', dt('No alias? What am I supposed to do?'));
  }
  $site_record = drush_sitealias_get_record($alias);
  if (empty($site_record)) {
    return drush_set_error('BAD_ALIAS', dt("Don't know that alias."));
  }
  drush_set_context('DRAKE_RELOAD_SITEALIAS', $site_record);

  if (empty($site_record['drake_reload']['drakefile'])) {
    return drush_set_error('NO_DRAKEFILE', dt('No drakefile set on project. See drush help drb for help on this.'));
  }

  if (empty($path)) {
    return drush_set_error('NO_PATH', dt("No path to build to? You think I'm a mind reader?"));
  }

  if (!file_exists(dirname($path))) {
    return drush_set_error('PARENT_DOES_NOT_EXIST', dt("Build path parent does not exist."));
  }

  if (file_exists($path)) {
    return drush_set_error('DIR_EXISTS', dt("Build path already exists, aborting."));
  }
}

/**
 * Command callback; bootstrap a site from an alias.
 */
function drush_drake_reload_bootstrap($alias = NULL, $path = NULL) {
  $site_record = drush_get_context('DRAKE_RELOAD_SITEALIAS', NULL);

  drush_log('Running Drush build', 'status');
  $res = drush_invoke_process('@none', 'drake', array('build', $path), array('file' => $site_record['drake_reload']['drakefile']), TRUE);

  if (!$res || $res['error_status'] != 0) {
    return drush_set_error('DRAKE_FAILED', dt("Drake failed. I'm sorry about that."));
  }

  if (!empty($site_record['drake_reload']['bootstrap_message'])) {
    $message = $site_record['drake_reload']['bootstrap_message'];
    if (is_array($message)) {
      $message = implode("\n", $message);
    }
    drush_print("There's a message attached to the alias:");
    drush_print();
    drush_print(trim($message));
  }
  drush_print('Done.');
}

/**
 * Command callback; list sites known to mole_zero.
 */
function drush_drake_reload_sites($alias = NULL, $path = NULL) {
  // You'd think it would be much easier to just ask Drush for a list of all
  // aliases. You'd be wrong.
  $per_user_config_dir = drush_get_context('DRUSH_PER_USER_CONFIGURATION');
  if (!empty($per_user_config_dir)) {
    if (!file_exists($per_user_config_dir . '/r.aliases.drushrc.php')) {
      return drush_set_error(dt('mole_zero not detected. Please run drush-reload-install to install it.'));
    }
    $aliasname = 'r.shangri-la';
    include $per_user_config_dir . '/r.aliases.drushrc.php';
    $sorted = array();
    foreach ($aliases as $name => $def) {
      $key = empty($def['parent']) ? '' : $def['parent'];
      $sorted[$key][$name] = $def;
    }

    _drake_reload_print_sites('', $sorted);
    if (!empty($sorted)) {
      foreach ($sorted as $parent => $list) {
        foreach ($list as $name => $def) {
          drush_log(dt('Unknown parent @parent for @alias', array('@parent' => $parent, '@alias' => $name)), 'error');
        }
      }
    }
  }
  else {
    return drush_set_error(dt("I'm loosing my mind, I can't figure out where the .drush directory is?"));
  }
}

/**
 * Print an array of sites, ordered by parent-child.
 */
function _drake_reload_print_sites($name, &$all, $prefix = '') {
  foreach ($all[$name] as $key_name => $def) {
    $alias_name = '@r.' . $key_name;
    $suffix = '';
    if (!empty($def['drake_reload']['title'])) {
      $suffix = ' - ' . $def['drake_reload']['title'];
    }
    drush_print($prefix . $alias_name . $suffix);
    if (!empty($all[$alias_name])) {
      drush_print();
      _drake_reload_print_sites($alias_name, $all, $prefix . '  ');
      drush_print();
    }
    unset($all[$name]);
  }
}

/**
 * Command callback; avoid having mobile_tools kill all drush commands.
 */
function drush_drake_reload_mobile_tools_workaround() {
  $select = drush_db_select('system', array('info'), "name = 'mobile_tools' AND status = 1", array());

  while ($row = drush_db_fetch_object($select)) {
    if ($info = unserialize($row->info)) {
      if (preg_match('/^([67]).x-(\d+)\.(\d+)$/', $info['version'], $m)) {
        // If version < 2.4.
        if ($m[1] == 6 && ($m[2] < 2 || ($m[2] == 2 && $m[3] < 4))) {
          drush_print();
          drush_print(dt('WARNING'));
          drush_print();
          drush_print(dt('Disabling redirection in mobile_tools (version @ver) because releases before 2.4 has been known to cause all drush commands to fail when attempting to redirect.', array('@ver' => $m[2] . '.' . $m[3])));
          drush_print();
          drush_print(dt('If your drush commands starts to mysteriously fail at some point, try re-running drush drmtw to reset this variable. Features might revernt the variable.'));
          drush_print();

          variable_set('mobile_tools_redirect', FALSE);
        }
      }
    }
  }
}
