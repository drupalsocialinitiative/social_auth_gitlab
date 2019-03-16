<?php

namespace Drupal\social_auth_gitlab\Plugin\Network;

use Drupal\Core\Url;
use Drupal\social_api\SocialApiException;
use Drupal\social_auth\Plugin\Network\NetworkBase;
use Drupal\social_auth_gitlab\Settings\GitLabAuthSettings;
use Omines\OAuth2\Client\Provider\Gitlab;

/**
 * Defines a Network Plugin for Social Auth GitLab.
 *
 * @package Drupal\social_auth_gitlab\Plugin\Network
 *
 * @Network(
 *   id = "social_auth_gitlab",
 *   social_network = "GitLab",
 *   type = "social_auth",
 *   handlers = {
 *     "settings": {
 *       "class": "\Drupal\social_auth_gitlab\Settings\GitLabAuthSettings",
 *       "config_id": "social_auth_gitlab.settings"
 *     }
 *   }
 * )
 */
class GitLabAuth extends NetworkBase implements GitLabAuthInterface {

  /**
   * Sets the underlying SDK library.
   *
   * @return \Omines\OAuth2\Client\Provider\Gitlab|bool
   *   The initialized 3rd party library instance.
   *
   * @throws SocialApiException
   *   If the SDK library does not exist.
   */
  protected function initSdk() {

    $class_name = 'Omines\OAuth2\Client\Provider\Gitlab';
    if (!class_exists($class_name)) {
      throw new SocialApiException(sprintf('The GitLab library for PHP League OAuth2 not found. Class: %s.', $class_name));
    }
    /* @var \Drupal\social_auth_gitlab\Settings\GitlabAuthSettings $settings */
    $settings = $this->settings;

    if ($this->validateConfig($settings)) {
      // All these settings are mandatory.
      $league_settings = [
        'clientId' => $settings->getClientId(),
        'clientSecret' => $settings->getClientSecret(),
        'redirectUri' => Url::fromRoute('social_auth_gitlab.callback')->setAbsolute()->toString(),
      ];

      // Proxy configuration data for outward proxy.
      $proxyUrl = $this->siteSettings->get('http_client_config')['proxy']['http'];
      if ($proxyUrl) {
        $league_settings['proxy'] = $proxyUrl;
      }

      return new Gitlab($league_settings);
    }

    return FALSE;
  }

  /**
   * Checks that module is configured.
   *
   * @param \Drupal\social_auth_gitlab\Settings\GitLabAuthSettings $settings
   *   The GitLab auth settings.
   *
   * @return bool
   *   True if module is configured.
   *   False otherwise.
   */
  protected function validateConfig(GitLabAuthSettings $settings) {
    $client_id = $settings->getClientId();
    $client_secret = $settings->getClientSecret();
    if (!$client_id || !$client_secret) {
      $this->loggerFactory
        ->get('social_auth_gitlab')
        ->error('Define Client ID and Client Secret on module settings.');

      return FALSE;
    }

    return TRUE;
  }

}
