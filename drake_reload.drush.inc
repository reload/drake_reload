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
    // 'options' => array(
    //   'file' => 'Use specified file.',
    // ),
    'aliases' => array('drg'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );

  $items['drake-reload-install'] = array(
    'description' => 'Install all parts for drake_reload.',
    // 'options' => array(
    //   'file' => 'Use specified file.',
    // ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
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
    case 'drush:drake_reload_generate':
      return dt("Generate drakefile.");
    case 'drush:drake_reload_install':
      return dt("Install all parts for drake_reload.");
  }
}

function drush_drake_reload_generate($filename = NULL) {
  $template = file_get_contents(dirname(__FILE__) . '/drakefile.template.php');
  $chunks = explode("\n\n", $template);
  $global_replacements = array();

  drush_print('Site name is used in comments and setting the site site_name when sanitizing database.');

  $global_replacements['%site_name%'] = trim(drush_prompt('Site name', '', TRUE));

  $type = drush_choice(array('make' => 'Make', 'git' => 'Git'), dt('Git checkout or make file?'));
  if (!$type) {
    return drush_user_abort();
  }

  $git_flow = $type == 'git' ? FALSE : drush_confirm(dt('Use git flow?'));

  if ($type == 'make') {
    // Special options for make file based drakefile.
    drush_print(dt('Enter makefile URL.'));
    $global_replacements['%make_file_url%'] = trim(drush_prompt("Make file URL: ", '', TRUE));

    drush_print(dt('Enter makefile filename relative to site root. Most likely profiles/<profile>/build_<profile>.make.'));
    $global_replacements['%make_file_path%'] = trim(drush_prompt("Make file path: ", '', TRUE));
  }
  elseif ($type == 'git') {
    // Special options for git based drakefile.
    drush_print(dt('Enter Git repository URL.'));
    $global_replacements['%git_url%'] = trim(drush_prompt("Git repo URL: ", '', TRUE));
  }

  $envs = array();
  drush_print(dt('Please enter environments for site in the form "<env>,<alias>,<name>" where:'));
  drush_print();
  drush_print('<env> is a alphanumeric short string used internally for identification (prod/stg/dev works well).');
  drush_print();
  drush_print('<alias> is the site alias of the environment.');
  drush_print();
  drush_print('<name> is a human readable name used in comments and description, "Production" and "Development" is good suggestions.');
  drush_print();
  drush_print(dt('Finish by entering an empty alias.'));
  do {
    $env = drush_prompt("Env: ", '', FALSE);
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
        $envs[$env_id] = array(trim($parts[1]), trim($parts[2]));
      }
    }
  } while (!empty($env));

  if (empty($envs)) {
    return drush_set_error('NO_ENVS', dt('No environments given.'));
  }

  $new_chunks = array();
  foreach ($chunks as &$chunk) {
    // Add envs to $context.
    if (preg_match('{\$context = }', $chunk)) {
      $context = array();
      foreach ($envs as $env => $def) {
        list($alias, $name) = $def;
        $context[] = '  // ' . $name . ' site alias.';
        $context[] = "  '@env." . $env. "' => '" . $alias . "',";
      }

      if ($type == 'make') {
        $context[] = '  // Bootstrap makefile.';
        $context[] = "  'makefile' => '%make_file_url%',";
      }
      elseif ($type == 'git') {
        $context[] = '  // Git repository.';
        $context[] = "  'repository' => '%git_url%',";
      }
      $chunk = preg_replace('{\$context = array\(\n}', "\$context = array(\n" . implode("\n", $context) . "\n", $chunk);
    }

    if (preg_match('{\$tasks\[\'((?:re)?build)-(make|git)\'\] = }', $chunk, $matches)) {
      if ($matches[2] != $type) {
        continue;
      }
      // Fix the task name.
      $chunk = preg_replace('{\$tasks\[\'((?:re)?build)-(make|git)\'\]}', '\$tasks[\'\1\']', $chunk);
    }

    // Clone import-prod to each env.
    if (preg_match('{\$tasks\[\'import-%env%\'\] = }', $chunk)) {
      $imports = array();
      foreach ($envs as $env => $def) {
        list($alias, $name) = $def;
        $replacements = array(
          '%env%' => $env,
          '%env_name%' => $name,
          '%env_alias%' => $alias,
        );
        $imports[] = strtr($chunk, $replacements);
      }
      $chunk = implode("\n\n", $imports);
    }

    // Remove references to git flow, if not selected.
    if (!$git_flow) {
      $chunk = preg_replace("{, 'reload-flow-setup'}", '', $chunk);
    }

    $new_chunks[] = strtr($chunk, $global_replacements);
  }

  $contents = implode("\n\n", $new_chunks);
  if ($filename) {
    file_put_contents($filename, $contents);
  }
  else {
    drush_print($contents);
  }
}

function drush_drake_reload_install() {
  $res = drush_invoke_process('@self', 'pm-download', array('drush_situs'), array('no' => TRUE), TRUE);
  $res = drush_invoke_process('@self', 'pm-download', array('drush_drake'), array('no' => TRUE), TRUE);
}
