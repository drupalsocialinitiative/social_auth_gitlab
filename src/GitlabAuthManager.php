<?php

namespace Drupal\social_auth_gitlab;

use Drupal\social_auth\AuthManager\OAuth2Manager;
use Drupal\Core\Config\ConfigFactory;

/**
 * Contains all the logic for Gitlab login integration.
 */
class GitlabAuthManager extends OAuth2Manager {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   Used for accessing configuration object factory.
   */
  public function __construct(ConfigFactory $configFactory) {
    parent::__construct($configFactory->get('social_auth_gitlab.settings'));
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate() {
    $this->setAccessToken($this->client->getAccessToken('authorization_code',
      ['code' => $_GET['code']]));
  }

  /**
   * {@inheritdoc}
   */
  public function getUserInfo() {
    $this->user = $this->client->getResourceOwner($this->getAccessToken());
    return $this->user;
  }

  /**
   * Returns the Gitlab login URL where user will be redirected.
   *
   * @TODO: We should allow extra scopes after GitLab fixes this issue:
   * @see https://gitlab.com/gitlab-org/gitlab-ce/issues/36203
   *
   * @return string
   *   Absolute Gitlab login URL where user will be redirected
   */
  public function getAuthorizationUrl() {
    $scopes = ['api'];

    // Returns the URL where user will be redirected.
    return $this->client->getAuthorizationUrl([
      'scope' => $scopes,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function requestEndPoint($path) {
    $url = 'https://gitlab.com/api' . $path;

    $request = $this->client->getAuthenticatedRequest('GET', $url, $this->getAccessToken());

    $response = $this->client->getResponse($request);

    return $response->getBody()->getContents();
  }

  /**
   * Returns the Gitlab login URL where user will be redirected.
   *
   * @return string
   *   Absolute Gitlab login URL where user will be redirected
   */
  public function getState() {
    $state = $this->client->getState();

    // Generate and return the URL where we should redirect the user.
    return $state;
  }

}
