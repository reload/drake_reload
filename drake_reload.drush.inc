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
  }
}

function drush_drake_reload_generate($filename = NULL) {
  $template = file_get_contents(dirname(__FILE__) . '/drakefile.template.php');
  $chunks = explode("\n\n", $template);
  $global_replacements = array();

  drush_print('Site name is used in comments and setting the site site_name when sanitizing database.');

  $global_replacements['%site_name%'] = trim(drush_prompt('Site name', '', TRUE));

  $git_flow = drush_confirm(dt('Use git flow?'));

  drush_print(dt('Enter makefile filename relative to site root. Most likely profiles/<profile>/build_<profile>.make.'));
  $global_replacements['%make_file_path%'] = trim(drush_prompt("Make file path: ", '', TRUE));

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
      $aliases = array();
      foreach ($envs as $env => $def) {
        list($alias, $name) = $def;
        $aliases[] = '  // ' . $name . ' site alias.';
        $aliases[] = "  '@env." . $env. "' => '" . $alias . "',";
      }
      $chunk = preg_replace('{\$context = array\(\n}', "\$context = array(\n" . implode("\n", $aliases) . "\n", $chunk);
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
