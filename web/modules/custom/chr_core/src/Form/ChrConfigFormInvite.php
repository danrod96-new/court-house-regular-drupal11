<?php

namespace Drupal\chr_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Invite settings form for the CHR site.
 */
class ChrConfigFormInvite extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'chr_core.adminsettingsinvite',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'chr_core_settings_form_invite';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('chr_core.adminsettingsinvite');

    $subject = "[yourname] has sent you an invite!";

    $body = "[yourname] has invited you to join Court House Regular at [home_link].
    To become a member of Court House Regular, click the link below or 
    paste it into the address bar of your browser.[registration_link]";

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#size' => 128,
      '#maxlength' => 128,
      '#default_value' => $config->get('invite_subject') ?? $subject,
      '#description' => $this->t("Enter default e-mail subject."),
      '#required' => TRUE,
    ];

    $form['message_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message Body'),
      '#description' => $this->t('Enter default e-mail body.'),
      '#default_value' => $config->get('message_body') ?? $body,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('chr_core.adminsettingsinvite')
      ->set('invite_subject', $form_state->getValue('title'))
      ->save();

    $this->config('chr_core.adminsettingsinvite')
      ->set('message_body', $form_state->getValue('message_body'))
      ->save();
  }

}
