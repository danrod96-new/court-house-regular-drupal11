<?php

namespace Drupal\chr_core;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\chr_core\DataSyncOperations;
use Drupal\Core\Database\Database;
use Drupal\user\Entity\User;
use Drupal\profile\Entity\Profile;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\Entity\File;


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
    $context['results'][] = "PID: " . $item["pid"] . " - UID: " . $item["uid"] . " processed.";

    //self::updateFieldFacsimileNumberNumber($item["pid"], $item["uid"]);
    //self::updateFieldLandLine($item["pid"], $item["uid"]);
    //self::updateFieldMobileNumber($item["pid"], $item["uid"]);
    self::updateUseerProfileImage($item["pid"], $item["uid"]);
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   TR UE if the batch completed successfully.
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

  /**
   * Callback to update the field field_facsimile_number.
   *
   * @param int $pid
   *   Profile ID of the profile.
   * @param int $uid
   *   Used ID of the user owner of the profile.
   *
   * @return bool
   *   Returns TRUE if success, FALSE if error.
   */
  public static function updateFieldFacsimileNumberNumber(int $pid, int $uid): bool {
    $user = \Drupal\user\Entity\User::load($uid);
    $profile = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadByUser($user, 'recieve_assignments');

    Database::setActiveConnection('migrate');

    if (!$profile) {
      return FALSE;
    } else {
      $query = Database::getConnection()->select('dr_field_revision_field_facsimile_number', 't')
      ->fields('t', ['field_facsimile_number_number', 'field_facsimile_number_country_codes'])
      ->condition('t.entity_id', $pid, "=")
      ->execute();

      $results = $query->fetchAll(\PDO::FETCH_ASSOC);

      if (count($results)) {
        foreach ($results as $row) {
          if (isset($row["field_facsimile_number_number"]) && !empty(($row["field_facsimile_number_number"]))) {
            // All numbers seem to be US numbers, so I'll add this as default
            $profile->set('field_facsimile_number', "+1" . $row["field_facsimile_number_number"]);
          } else {
            $profile->set('field_facsimile_number', "");
          }
        }
      }

      try {
        $profile->save();
      } catch (Exception $e) {
        \Drupal::logger('chr_core')->error('Error when saving the data @dataerror, error message: @error_message', ['@data_error' => $row["field_facsimile_number_number"], '@error_message' => $e->getMessage()]);
        return FALSE;
      }

      return TRUE;
    }
  }

  /**
   * Callback to update the field field_landline.
   *
   * @param int $pid
   *   Profile ID of the profile.
   * @param int $uid
   *   Used ID of the user owner of the profile.
   *
   * @return bool
   *   Returns TRUE if success, FALSE if error.
   */
  public static function updateFieldLandLine(int $pid, int $uid): bool {
    $user = \Drupal\user\Entity\User::load($uid);
    $profile = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadByUser($user, 'recieve_assignments');

    Database::setActiveConnection('migrate');

    if (!$profile) {
      return FALSE;
    } else {
      $query = Database::getConnection()->select('dr_field_revision_field_landline', 't')
      ->fields('t', ['field_landline_number', 'field_landline_country_codes'])
      ->condition('t.entity_id', $pid, "=")
      ->execute();

      $results = $query->fetchAll(\PDO::FETCH_ASSOC);

      if (count($results)) {
        foreach ($results as $row) {
          if (isset($row["field_landline_number"]) && !empty(($row["field_landline_number"]))) {
            // All numbers seem to be US numbers, so I'll add this as default
            $profile->set('field_landline', "+1" . $row["field_landline_number"]);
          } else {
            $profile->set('field_landline', "");
          }
        }
      }

      try {
        $profile->save();
      } catch (Exception $e) {
        \Drupal::logger('chr_core')->error('Error when saving the data @dataerror, error message: @error_message', ['@data_error' => $row["field_landline_number"], '@error_message' => $e->getMessage()]);
        return FALSE;
      }

      return TRUE;
    }
  }

  /**
   * Callback to update the field field_mobile_number.
   *
   * @param int $pid
   *   Profile ID of the profile.
   * @param int $uid
   *   Used ID of the user owner of the profile.
   *
   * @return bool
   *   Returns TRUE if success, FALSE if error.
   */
  public static function updateFieldMobileNumber(int $pid, int $uid): bool {
    $user = \Drupal\user\Entity\User::load($uid);
    $profile = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadByUser($user, 'recieve_assignments');

    Database::setActiveConnection('migrate');

    if (!$profile) {
      return FALSE;
    } else {
      $query = Database::getConnection()->select('dr_field_revision_field_mobile_number', 't')
      ->fields('t', ['field_mobile_number_number', 'field_mobile_number_country_codes'])
      ->condition('t.entity_id', $pid, "=")
      ->execute();

      $results = $query->fetchAll(\PDO::FETCH_ASSOC);

      if (count($results)) {
        foreach ($results as $row) {
          if (isset($row["field_mobile_number_number"]) && !empty(($row["field_mobile_number_number"]))) {
            // All numbers seem to be US numbers, so I'll add this as default
            $profile->set('field_mobile_number', "+1" . $row["field_mobile_number_number"]);
          } else {
            $profile->set('field_mobile_number', "");
          }
        }
      }

      try {
        $profile->save();
      } catch (Exception $e) {
        \Drupal::logger('chr_core')->error('Error when saving the data @dataerror, error message: @error_message', ['@data_error' => $row["field_mobile_number_number"], '@error_message' => $e->getMessage()]);
        return FALSE;
      }

      return TRUE;
    }
  }

  /**
   * Callback to update the profile picture.
   *
   * @param int $pid
   *   Profile ID of the profile.
   * @param int $uid
   *   Used ID of the user owner of the profile.
   *
   * @return bool
   *   Returns TRUE if success, FALSE if error.
   */
  public static function updateUseerProfileImage(int $pid, int $uid): bool {
    $user = \Drupal\user\Entity\User::load($uid);
    $profile = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadByUser($user, 'recieve_assignments');

    Database::setActiveConnection('migrate');

    if (!$profile) {
      return FALSE;
    } else {
      $query = Database::getConnection()->select('dr_file_managed', 't')
      ->fields('t', ['fid', 'uid', 'filename', 'uri'])
      ->condition('t.uid', $uid, "=")
      ->execute();

      $results = $query->fetchAll(\PDO::FETCH_ASSOC);

      if (count($results)) {
        foreach ($results as $row) {
          if (isset($row["uri"]) && !empty(($row["uri"]))) {
            // Create a new managed file entity.
            $file = File::create([
              'uri' => $row["uri"], // Adjust the URI as needed.
              'uid' => $row["uid"],
            ]);
            $file->setPermanent();
            $file->save();

            // Get the file ID.
            $fid = $file->id();

            try {
              $user->set('user_picture', $fid);
              $user->save();
            } catch (Exception $e) {
              \Drupal::logger('chr_core')->error('Error when saving the picture @userpicture, error message: @error_message', ['@userpicture' => $row["uid"], '@error_message' => $e->getMessage()]);
            }
          }
        }
      }

      try {
        $profile->save();
      } catch (Exception $e) {
        \Drupal::logger('chr_core')->error('Error when saving the data @phonenumber, error message: @error_message', ['@phonenumber' => $row["field_mobile_number_number"], '@error_message' => $e->getMessage()]);
        return FALSE;
      }

      return TRUE;
    }
  }


}