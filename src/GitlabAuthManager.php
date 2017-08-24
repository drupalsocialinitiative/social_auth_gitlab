<?php

namespace Drupal\social_auth_gitlab;

use Drupal\social_auth\AuthManager\OAuth2Manager;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Contains all the logic for Gitlab login integration.
 */
class GitlabAuthManager extends OAuth2Manager {

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The url generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * The Gitlab client object.
   *
   * @var \League\OAuth2\Client\Provider\Gitlab
   */
  protected $client;
  /**
   * The Gitlab access token.
   *
   * @var \League\OAuth2\Client\Token\AccessToken
   */
  protected $token;

  /**
   * The Gitlab user.
   *
   * @var \League\OAuth2\Client\Provider\GitlabUser
   */
  protected $user;

  /**
   * The data point to be collected.
   *
   * @var string
   */
  protected $scopes;

  /**
   * Social Auth Gitlab Settings.
   *
   * @var array
   */
  protected $settings;


  /**
   * Constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Used for logging errors.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Used for dispatching events to other modules.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Used for accessing Drupal user picture preferences.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   Used for generating absoulute URLs.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   Used for accessing configuration object factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, EventDispatcherInterface $event_dispatcher, EntityFieldManagerInterface $entity_field_manager, UrlGeneratorInterface $url_generator, ConfigFactory $configFactory) {
    $this->loggerFactory      = $logger_factory;
    $this->eventDispatcher    = $event_dispatcher;
    $this->entityFieldManager = $entity_field_manager;
    $this->urlGenerator       = $url_generator;
    $this->config             = $configFactory->getEditable('social_auth_gitlab.settings');
  }

  /**
   * Authenticates the users by using the access token.
   *
   * @return $this
   *   The current object.
   */
  public function authenticate() {
    $this->token = $this->client->getAccessToken('authorization_code',
      ['code' => $_GET['code']]);
  }

  /**
   * Gets the data by using the access token returned.
   *
   * @return League\OAuth2\Client\Provider\GitlabUser
   *   User info returned by the Gitlab.
   */
  public function getUserInfo() {
    $this->user = $this->client->getResourceOwner($this->token);
    return $this->user;
  }

  /**
   * Gets the data by using the access token returned.
   *
   * @return string
   *   Data returned by Making API Call.
   */
  public function getExtraDetails($url, $user_id) {
    if($url) {
      $baseUrl = 'https://gitlab.example.com/api/v4';
      $params  = 'private_token=' . $this->token;

      //Replacing user_id with numerical user_id
      $url = str_replace('user_id',$user_id,$url);

      $response = file_get_contents($baseUrl . $url . '?' . $params);
      return json_decode($response, TRUE);
    }
  }

  /**
   * Returns token generated after authorization.
   *
   * @return string
   *   Used for making API calls.
   */
  public function getAccessToken() {
    return $this->token;
  }

  /**
   * Returns the Gitlab login URL where user will be redirected.
   *
   * @return string
   *   Absolute Gitlab login URL where user will be redirected
   */
  public function getGitlabLoginUrl() {
    $scopes = [];

    $gitlab_scopes = explode(PHP_EOL, $this->getScopes());
    foreach ($gitlab_scopes as $scope) {
      array_push($scopes, $scope);
    }

    if ($this->getScopes()) {
      $options = [
        'scope' => $scopes,
      ];
      $login_url = $this->client->getAuthorizationUrl($options);
    }
    else {
      $login_url = $this->client->getAuthorizationUrl();
    }

    // Generate and return the URL where we should redirect the user.
    return $login_url;
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

  /**
   * Gets the data Point defined the settings form page.
   *
   * @return string
   *   Data points separtated by comma.
   */
  public function getScopes() {
    if (!$this->scopes) {
      $this->scopes = $this->config->get('scopes');
    }
    return $this->scopes;
  }

  /**
   * Gets the API calls to collect data.
   *
   * @return string
   *   API calls separtated by comma.
   */
  public function getAPICalls() {
    return $this->config->get('api_calls');
  }

}
