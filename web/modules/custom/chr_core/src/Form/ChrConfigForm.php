<?php

namespace Drupal\chr_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Config settings form for the CHR Core module.
 */
class ChrConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'chr_core.adminsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'chr_core_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('chr_core.adminsettings');

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#size' => 128,
      '#maxlength' => 128,
      '#default_value' => $config->get('invite_subject') ?? "",
      '#description' => $this->t("Enter default e-mail subject."),
      '#required' => TRUE,
    ];

    $form['message_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message Body'),
      '#description' => $this->t('Enter default e-mail body.'),
      '#default_value' => $config->get('message_body'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('chr_core.adminsettings')
      ->set('invite_subject', $form_state->getValue('title'))
      ->save();

    $this->config('chr_core.adminsettings')
      ->set('message_body', $form_state->getValue('message_body'))
      ->save();
  }

}
