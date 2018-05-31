<?php

namespace Drupal\social_auth_gitlab\Settings;

/**
 * Defines an interface for Social Auth GitLab settings.
 */
interface GitLabAuthSettingsInterface {

  /**
   * Gets the client ID.
   *
   * @return string
   *   The client ID.
   */
  public function getClientId();

  /**
   * Gets the client secret.
   *
   * @return string
   *   The client secret.
   */
  public function getClientSecret();

}
