<?php
use CRM_Mautic_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Mautic_Upgrader extends CRM_Mautic_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Example: Run an external SQL script when the module is installed.
   */
  public function install() {
    $activityTypeGroup = civicrm_api3('OptionGroup', 'getsingle', [
      'name' => "activity_type",
    ]);
    $groupId = $activityTypeGroup['id'];
    // Create activity type.
    $params = [
      'option_group_id' =>  $groupId,
      'label' => 'Mautic Webhook Triggered',
      'name' => 'Mautic_Webhook_Triggered',
      'filter' =>  '0',
      'is_active' => '1'
    ];
    $this->createIfNotExists('OptionValue', $params, ['name', 'option_group_id']);
  }
  
  protected function createIfNotExists($entity, $params, $lookupKeys = ['name']) {
    try {
      $lookupParams = array_intersect_key($params, array_flip($lookupKeys));
      $lookupParams['sequential'] = 1;
      $existingResult = civicrm_api3($entity, 'get', $lookupParams);
      if (!empty($existingResult['values'])) {
        return $existingResult['values'][0];
      }
      // Doesn't exist, create.
      $createResult = civicrm_api3($entity, 'create', $params);
      return $createResult['values'];
    }
    catch (Exception $e) {
      // Let Civi Handle it.
      throw($e); 
    }
  }

  /**
   * Example: Work with entities usually not available during the install step.
   *
   * This method can be used for any post-install tasks. For example, if a step
   * of your installation depends on accessing an entity that is itself
   * created during the installation (e.g., a setting or a managed entity), do
   * so here to avoid order of operation problems.
   */
  public function postInstall() {
    // Add Custom Data for Mautic_Webhook_Triggered activity type.
    $file = $this->extensionDir . '/xml/activity_data.xml';
    $this->executeCustomDataFileByAbsPath($file);
  }

  /**
   * Example: Run an external SQL script when the module is uninstalled.
   *
  public function uninstall() {
   $this->executeSqlFile('sql/myuninstall.sql');
  }

  /**
   * Example: Run a simple query when a module is enabled.
   *
  public function enable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 1 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a simple query when a module is disabled.
   *
  public function disable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 0 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a couple simple queries.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_4200() {
    // Create a cron job to do sync data between CiviCRM and Mautic.
    $params = array(
      'sequential' => 1,
      'name'          => 'Mautic Push Sync',
      'description'   => 'Sync contacts between CiviCRM and Mautic, assuming CiviCRM to be correct. Please understand the implications before using this.',
      'run_frequency' => 'Daily',
      'api_entity'    => 'Mautic',
      'api_action'    => 'pushsync',
      'is_active'     => 0,
    );
    $result = $this->createIfNotExists('Job', $params);
    // $result = civicrm_api3('job', 'create', $params);
    
    
    // Create Pull Sync job.
    /**
     * Not implemented yet, so don't expose it as a job.
    $params = array(
      'sequential' => 1,
      'name'          => 'Mautic Pull Sync',
      'description'   => 'Sync contacts between CiviCRM and Mautic, assuming Mautic to be correct. Please understand the implications before using this.',
      'run_frequency' => 'Daily',
      'api_entity'    => 'Mautic',
      'api_action'    => 'pullsync',
      'is_active'     => 0,
    );
    $result = civicrm_api3('job', 'create', $params);
    **/
    return !empty($result); 
  } // */


  /**
   * Insert Civirules trigger for WebHook.
   *
   * @return TRUE on success
   * @throws Exception
   **/
  public function upgrade_4201() {
    $this->ctx->log->info('Applying update 4201');
    if (class_exists('CRM_Civirules_Utils_Upgrader')) {
      CRM_Civirules_Utils_Upgrader::insertTriggersFromJson($this->extensionDir . DIRECTORY_SEPARATOR . 'sql/civirules/triggers.json');
      CRM_Civirules_Utils_Upgrader::insertConditionsFromJson($this->extensionDir . DIRECTORY_SEPARATOR . 'sql/civirules/conditions.json');
      CRM_Civirules_Utils_Upgrader::insertActionsFromJson($this->extensionDir . DIRECTORY_SEPARATOR . 'sql/civirules/actions.json');
    }
    return TRUE;
  } // */


  /**
   * Example: Run a slow upgrade process by breaking it up into smaller chunk.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4202() {
    $this->ctx->log->info('Planning update 4202'); // PEAR Log interface

    $this->addTask(E::ts('Process first step'), 'processPart1', $arg1, $arg2);
    $this->addTask(E::ts('Process second step'), 'processPart2', $arg3, $arg4);
    $this->addTask(E::ts('Process second step'), 'processPart3', $arg5);
    return TRUE;
  }
  public function processPart1($arg1, $arg2) { sleep(10); return TRUE; }
  public function processPart2($arg3, $arg4) { sleep(10); return TRUE; }
  public function processPart3($arg5) { sleep(10); return TRUE; }
  // */


  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4203() {
    $this->ctx->log->info('Planning update 4203'); // PEAR Log interface

    $minId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(min(id),0) FROM civicrm_contribution');
    $maxId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(max(id),0) FROM civicrm_contribution');
    for ($startId = $minId; $startId <= $maxId; $startId += self::BATCH_SIZE) {
      $endId = $startId + self::BATCH_SIZE - 1;
      $title = E::ts('Upgrade Batch (%1 => %2)', array(
        1 => $startId,
        2 => $endId,
      ));
      $sql = '
        UPDATE civicrm_contribution SET foobar = whiz(wonky()+wanker)
        WHERE id BETWEEN %1 and %2
      ';
      $params = array(
        1 => array($startId, 'Integer'),
        2 => array($endId, 'Integer'),
      );
      $this->addTask($title, 'executeSql', $sql, $params);
    }
    return TRUE;
  } // */

}
