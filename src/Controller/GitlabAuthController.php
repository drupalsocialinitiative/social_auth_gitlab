<?php

namespace Drupal\social_auth_gitlab\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\SocialAuthDataHandler;
use Drupal\social_auth\SocialAuthUserManager;
use Drupal\social_auth_gitlab\GitlabAuthManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Returns responses for Simple Gitlab Connect module routes.
 */
class GitlabAuthController extends ControllerBase {

  /**
   * The network plugin manager.
   *
   * @var \Drupal\social_api\Plugin\NetworkManager
   */
  protected $networkManager;

  /**
   * The user manager.
   *
   * @var \Drupal\social_auth\SocialAuthUserManager
   */
  protected $userManager;

  /**
   * The gitlab authentication manager.
   *
   * @var \Drupal\social_auth_gitlab\GitlabAuthManager
   */
  protected $gitlabManager;

  /**
   * Used to access GET parameters.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The Social Auth Data Handler.
   *
   * @var \Drupal\social_auth\SocialAuthDataHandler
   */
  protected $dataHandler;


  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * GitlabAuthController constructor.
   *
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   *   Used to get an instance of social_auth_gitlab network plugin.
   * @param \Drupal\social_auth\SocialAuthUserManager $user_manager
   *   Manages user login/registration.
   * @param \Drupal\social_auth_gitlab\GitlabAuthManager $gitlab_manager
   *   Used to manage authentication methods.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   Used to access GET parameters.
   * @param \Drupal\social_auth\SocialAuthDataHandler $social_auth_data_handler
   *   SocialAuthDataHandler object.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Used for logging errors.
   */
  public function __construct(NetworkManager $network_manager,
                              SocialAuthUserManager $user_manager,
                              GitlabAuthManager $gitlab_manager,
                              RequestStack $request,
                              SocialAuthDataHandler $social_auth_data_handler,
                              LoggerChannelFactoryInterface $logger_factory) {

    $this->networkManager = $network_manager;
    $this->userManager = $user_manager;
    $this->gitlabManager = $gitlab_manager;
    $this->request = $request;
    $this->dataHandler = $social_auth_data_handler;
    $this->loggerFactory = $logger_factory;

    // Sets the plugin id.
    $this->userManager->setPluginId('social_auth_gitlab');

    // Sets the session keys to nullify if user could not logged in.
    $this->userManager->setSessionKeysToNullify(['access_token', 'oauth2state']);
    $this->setting = $this->config('social_auth_gitlab.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.network.manager'),
      $container->get('social_auth.user_manager'),
      $container->get('social_auth_gitlab.manager'),
      $container->get('request_stack'),
      $container->get('social_auth.data_handler'),
      $container->get('logger.factory')
    );
  }

  /**
   * Response for path 'user/login/gitlab'.
   *
   * Redirects the user to Gitlab for authentication.
   */
  public function redirectToGitlab() {
    /* @var \Omines\OAuth2\Client\Provider\Gitlab|false $gitlab */
    $gitlab = $this->networkManager->createInstance('social_auth_gitlab')->getSdk();

    // If gitlab client could not be obtained.
    if (!$gitlab) {
      drupal_set_message($this->t('Social Auth Gitlab not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // Gitlab service was returned, inject it to $gitlabManager.
    $this->gitlabManager->setClient($gitlab);

    // Generates the URL where the user will be redirected for authorization.
    $gitlab_login_url = $this->gitlabManager->getAuthorizationUrl();

    $state = $this->gitlabManager->getState();

    $this->dataHandler->set('oauth2state', $state);

    return new TrustedRedirectResponse($gitlab_login_url);
  }

  /**
   * Response for path 'user/login/gitlab/callback'.
   *
   * Gitlab returns the user here after user has authenticated in Gitlab.
   */
  public function callback() {
    // Checks if user cancel login via Gitlab.
    $error = $this->request->getCurrentRequest()->get('error');
    if ($error == 'access_denied') {
      drupal_set_message($this->t('You could not be authenticated.'), 'error');
      return $this->redirect('user.login');
    }

    /* @var \Omines\OAuth2\Client\Provider\Gitlab|false $gitlab */
    $gitlab = $this->networkManager->createInstance('social_auth_gitlab')->getSdk();

    // If Gitlab client could not be obtained.
    if (!$gitlab) {
      drupal_set_message($this->t('Social Auth Gitlab not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    $state = $this->dataHandler->get('oauth2state');

    // Retrieves $_GET['state'].
    $retrievedState = $this->request->getCurrentRequest()->query->get('state');
    if (empty($retrievedState) || ($retrievedState !== $state)) {
      $this->userManager->nullifySessionKeys();
      drupal_set_message($this->t('Gitlab login failed. Unvalid oAuth2 State.'), 'error');
      return $this->redirect('user.login');
    }

    // Saves access token to session.
    $this->dataHandler->set('access_token', $this->gitlabManager->getAccessToken());

    $this->gitlabManager->setClient($gitlab)->authenticate();

    // Gets user's info from Gitlab API.
    /* @var \Omines\OAuth2\Client\Provider\GitlabResourceOwner $profile */
    if (!$profile = $this->gitlabManager->getUserInfo()) {
      drupal_set_message($this->t('Gitlab login failed, could not load Gitlab profile. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // Gets (or not) extra initial data.
    $data = $this->userManager->checkIfUserExists($profile->getId()) ? NULL : $this->gitlabManager->getExtraDetails();

    // If user information could be retrieved.
    return $this->userManager->authenticateUser($profile->getName(), $profile->getEmail(), $profile->getId(), $this->gitlabManager->getAccessToken(), $profile->getAvatarUrl(), $data);
  }

}
