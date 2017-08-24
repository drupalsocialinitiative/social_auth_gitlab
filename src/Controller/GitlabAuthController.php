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
  private $networkManager;

  /**
   * The user manager.
   *
   * @var \Drupal\social_auth\SocialAuthUserManager
   */
  private $userManager;

  /**
   * The gitlab authentication manager.
   *
   * @var \Drupal\social_auth_gitlab\GitlabAuthManager
   */
  private $gitlabManager;

  /**
   * Used to access GET parameters.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $request;

  /**
   * The Social Auth Data Handler.
   *
   * @var \Drupal\social_auth\SocialAuthDataHandler
   */
  private $dataHandler;


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
  public function __construct(NetworkManager $network_manager, SocialAuthUserManager $user_manager, GitlabAuthManager $gitlab_manager, RequestStack $request, SocialAuthDataHandler $social_auth_data_handler, LoggerChannelFactoryInterface $logger_factory) {

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
      $container->get('social_auth.social_auth_data_handler'),
      $container->get('logger.factory')
    );
  }

  /**
   * Response for path 'user/login/gitlab'.
   *
   * Redirects the user to Gitlab for authentication.
   */
  public function redirectToGitlab() {
    /* @var \League\OAuth2\Client\Provider\Gitlab false $gitlab */
    $gitlab = $this->networkManager->createInstance('social_auth_gitlab')->getSdk();

    // If gitlab client could not be obtained.
    if (!$gitlab) {
      drupal_set_message($this->t('Social Auth Gitlab not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // Gitlab service was returned, inject it to $gitlabManager.
    $this->gitlabManager->setClient($gitlab);

    // Generates the URL where the user will be redirected for Gitlab login.
    // If the user did not have email permission granted on previous attempt,
    // we use the re-request URL requesting only the email address.
    $gitlab_login_url = $this->gitlabManager->getGitlabLoginUrl();

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

    /* @var \League\OAuth2\Client\Provider\Gitlab false $gitlab */
    $gitlab = $this->networkManager->createInstance('social_auth_gitlab')->getSdk();

    // If Gitlab client could not be obtained.
    if (!$gitlab) {
      drupal_set_message($this->t('Social Auth Gitlab not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    $state = $this->dataHandler->get('oauth2state');

    // Retreives $_GET['state'].
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
    if (!$gitlab_profile = $this->gitlabManager->getUserInfo()) {
      drupal_set_message($this->t('Gitlab login failed, could not load Gitlab profile. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // Store the data mapped with data points define is
    // social_auth_github settings.
    $data = [];
    if (!$this->userManager->checkIfUserExists($gitlab_profile->getId())) {
      $api_calls = explode(PHP_EOL, $this->gitlabManager->getAPICalls());

      // Iterate through api calls define in settings and try to retrieve them.
      foreach ($api_calls as $api_call) {
        $call = $this->gitlabManager->getExtraDetails($api_call);
        array_push($data, $call);
      }
    }

    // If user information could be retrieved.
    return $this->userManager->authenticateUser($gitlab_profile->getName(), $gitlab_profile->getEmail(),$github_profile->getId(), $this->gitlabManager->getAccessToken(), $gitlab_profile->getAvatarUrl(), json_encode($data));
  }

}
