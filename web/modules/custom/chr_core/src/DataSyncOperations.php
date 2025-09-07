<?php

namespace Drupal\chr_core;

use Drupal\Core\Messenger\MessengerInterface;

/**
 * Provides static methods for batch operations.
 */
class DataSyncOperations {

  /**
   * Batch operation callback.
   *
   * @param string $item
   *   The item to process.
   * @param array $context
   *   The batch context.
   */
  public static function processProfile($item, array &$context) {
    // Perform the operation for the current item.
    // You can store results in $context['results'].
    $context['results'][] = $item . ' processed.';
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   TRUE if the batch completed successfully.
   * @param array $results
   *   The results array from the batch operations.
   * @param array $operations
   *   The array of operations performed.
   */
  public static function finishedCallback($success, array $results, array $operations): void {
    if ($success) {
      \Drupal::messenger()->addStatus(t('Batch process completed successfully.'));
      // Display results or perform further actions based on $results.
    }
    else {
      \Drupal::messenger()->addError(t('Batch process encountered errors.'));
    }
  }

}