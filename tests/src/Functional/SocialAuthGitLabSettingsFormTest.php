<?php

namespace Drupal\Tests\social_auth_gitlab\Functional;

use Drupal\social_api\SocialApiSettingsFormBaseTest;

/**
 * Test Social Auth GitLab settings form.
 *
 * @group social_auth
 *
 * @ingroup social_auth_gitlab
 */
class SocialAuthGitLabSettingsFormTest extends SocialApiSettingsFormBaseTest {
  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['social_auth_gitlab'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->module = 'social_auth_gitlab';
    $this->socialNetwork = 'gitlab';
    $this->moduleType = 'social-auth';

    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  public function testIsAvailableInIntegrationList() {
    $this->fields = ['client_id', 'client_secret'];

    parent::testIsAvailableInIntegrationList();
  }

  /**
   * {@inheritdoc}
   */
  public function testSettingsFormSubmission() {
    $this->edit = [
      'client_id' => $this->randomString(10),
      'client_secret' => $this->randomString(10),
    ];

    parent::testSettingsFormSubmission();
  }

}
