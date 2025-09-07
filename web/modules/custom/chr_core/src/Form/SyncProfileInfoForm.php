<?php

namespace Drupal\chr_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\chr_core\DataSyncOperations;

/**
 * Implements a form to initiate a batch process.
 */
class SyncProfileInfoForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'chr_core_sync_profile_info_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run Batch Process'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Notice that the IDs of the profile of the new site are the same of the old site, this will make things easier
    // 1. Query to get all profile IDs
    // 2. Loop through all the profile IDs and pass the id to the "processProfile" method
    // 3. The "processProfile" method will query the old database with the id and get the missing info (Mobile Number
    // Landline, Facsimile Number)
    $operations = [];

    for ($i = 1; $i < 100; $i++) {
      $operations[] = ['Drupal\chr_core\DataSyncOperations::processProfile', ['Item ' . $i]];
    }

    $batch = [
      'title' => $this->t('Processing Items'),
      'operations' =>  $operations,
      'finished' => 'Drupal\chr_core\DataSyncOperations::finishedCallback',
    ];

    batch_set($batch);
  }

}
