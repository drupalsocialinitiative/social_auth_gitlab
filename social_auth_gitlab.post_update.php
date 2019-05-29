<?php

/**
 * @file
 * Post update functions for the Social Auth GitLab module.
 */

/**
 * Adds GitLab base URL setting.
 */
function social_auth_gitlab_add_url_setting() {
  $config = \Drupal::configFactory()
    ->getEditable('social_auth_gitlab.settings');

  $config->set('base_url', 'https://gitlab.com/api')
    ->save(TRUE);
}
