<?php

namespace Drupal\social_auth_gitlab\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\social_auth\Form\SocialAuthSettingsForm;

/**
 * Settings form for Social Auth GitLab.
 */
class GitLabAuthSettingsForm extends SocialAuthSettingsForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'social_auth_gitlab_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return array_merge(
      parent::getEditableConfigNames(),
      ['social_auth_gitlab.settings']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('social_auth_gitlab.settings');
    $baseUrl = $config->get('base_url');

    $form['gitlab_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('GitLab Client settings'),
      '#open' => TRUE,
      '#description' => $this->t('You need to first create a GitLab App at <a href=":gitlab-url">:gitlab-url</a>. (Configure self-hosted GitLab URL in advanced settings.)',
        [':gitlab-url' => $baseUrl . '/profile/applications']),
    ];

    $form['gitlab_settings']['client_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('client_id'),
      '#description' => $this->t('Copy the Client ID here.'),
    ];

    $form['gitlab_settings']['client_secret'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Client Secret'),
      '#default_value' => $config->get('client_secret'),
      '#description' => $this->t('Copy the Client Secret here.'),
    ];

    $form['gitlab_settings']['authorized_redirect_url'] = [
      '#type' => 'textfield',
      '#disabled' => TRUE,
      '#title' => $this->t('Callback url'),
      '#description' => $this->t('Copy this value to <em>Callback URL</em> field of your GitLab App settings.'),
      '#default_value' => Url::fromRoute('social_auth_gitlab.callback')->setAbsolute()->toString(),
    ];

    $form['gitlab_settings']['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
    ];

    $form['gitlab_settings']['advanced']['base_url'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('GitLab base URL'),
      '#description' => $this->t('Customize your GitLab base URL, e.g. if you are hosting GitLab CE/EE yourself.'),
      '#default_value' => $baseUrl,
    ];

    $form['gitlab_settings']['advanced']['endpoints'] = [
      '#type' => 'textarea',
      '#title' => $this->t('API calls to be made to collect data'),
      '#default_value' => $config->get('endpoints'),
      '#description' => $this->t('Define the endpoints to be requested when user authenticates with GitLab for the first time<br>
                                  Enter each endpoint in a different line in the format <em>endpoint</em>|<em>name_of_endpoint</em>.<br>
                                  <b>For instance:</b><br>
                                  /api/v4/user/keys|user_keys'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    parent::validateForm($form, $form_state);
    if (!UrlHelper::isValid($values['base_url'], TRUE)) {
      $form_state->setErrorByName('base_url', $this->t("The GitLab base URL is invalid."));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('social_auth_gitlab.settings')
      ->set('client_id', $values['client_id'])
      ->set('client_secret', $values['client_secret'])
      ->set('endpoints', $values['endpoints'])
      ->set('base_url', rtrim($values['base_url'], '/'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
