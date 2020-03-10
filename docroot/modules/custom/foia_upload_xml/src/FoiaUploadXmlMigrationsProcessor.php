<?php

namespace Drupal\foia_upload_xml;

use Drupal\migrate\Row;
use Drupal\file\FileInterface;
use Drupal\migrate\MigrateMessage;
use Drupal\Core\Cache\NullBackend;
use Drupal\migrate\MigrateException;
use Drupal\Core\Session\AccountInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\migrate_tools\MigrateExecutable;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationPluginManager;

/**
 * Configures and runs migrations for the batch processor or import worker.
 *
 * @package Drupal\foia_upload_xml
 */
class FoiaUploadXmlMigrationsProcessor {

  /**
   * The migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationPluginManager;

  /**
   * The migration message.
   *
   * @var \Drupal\migrate\MigrateMessageInterface
   */
  protected $migrateMessage;

  /**
   * Configuration overrides if processing all migrations.
   *
   * @var array
   */
  protected $sourceOverrides;

  /**
   * FoiaUploadXmlMigrationsProcessor constructor.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManager $migrationPluginManager
   *   The migration plugin manager.
   */
  public function __construct(MigrationPluginManager $migrationPluginManager) {
    $this->migrationPluginManager = $migrationPluginManager;
    $this->migrationPluginManager->setCacheBackend(new NullBackend('discovery'), 'migration_plugins', ['migration_plugins']);
    $this->migrateMessage = new MigrateMessage();
    $this->sourceOverrides = [];
  }

  /**
   * Process a single migration.
   *
   * @param string $migration_id
   *   The migration id to be processed.
   * @param array $configuration
   *   Configuration to pass to the migrationPluginManager when creating the
   *   migration instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\migrate\MigrateException
   */
  public function process($migration_id, array $configuration = []) {
    $configuration = NestedArray::mergeDeep($configuration, $this->sourceOverrides);
    /** @var \Drupal\foia_upload_xml\Plugin\migrate\FoiaUploadXmlMigration $migration */
    $migration = $this->migrationPluginManager->createInstance($migration_id, $configuration);
    // Ensure that the migration's source url is only using the url as
    // configured by the FoiaUploadXmlMigrationsProcessor::setSourceFile()
    // method, if it has been set.  This overwrites the source url that
    // is set based on the migration's configuration and that may or may not
    // exist.
    $migration = $migration->setMigrationSourceUrls($migration);
    $migration->getIdMap()->prepareUpdate();
    $executable = new class($migration, $this->migrateMessage) extends MigrateExecutable {
      public function processRow(Row $row, array $process = NULL, $value = NULL) {
        try {
          return parent::processRow($row, $process, $value);
        } catch (MigrateException $e) {
          if ($this->migration->id() === 'foia_agency_report' && $e->getStatus() == MigrateIdMapInterface::STATUS_FAILED) {
            $sourceFile = pathinfo($this->migration->getSourceConfiguration()['urls']);
            \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_STATUS);
            \Drupal::messenger()->addError(\Drupal::translation()->translate('Failed to process file @file.', [
              '@file' => $sourceFile['basename']
            ]));
          }
          throw $e;
        }
      }
    };
    $result = $executable->import();
    if ($result == MigrationInterface::RESULT_FAILED) {
      \Drupal::messenger()->addError(\Drupal::translation()
        ->t('Migration @migration failed.', [
          '@migration' => $migration_id,
        ]));
    }
  }

  /**
   * Process all the migrations at once.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\migrate\MigrateException
   */
  public function processAll() {
    foreach ($this->getMigrationsList() as $migration_id) {
      $this->process($migration_id, $this->sourceOverrides);
    }
  }

  /**
   * Load the migrations, set them to use the source file's path, and save them.
   *
   * @param \Drupal\file\FileInterface $file
   *   The current migration's source file.
   *
   * @return $this
   *   The current object, for chaining.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setSourceFile(FileInterface $file) {
    $this->sourceOverrides['source']['urls'] = $file->getFileUri();

    return $this;
  }

  /**
   * Set the user id to be passed to all migrations.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user id to pass to the migration configuration when processing.
   *
   * @return $this
   */
  public function setUser(AccountInterface $user) {
    $this->sourceOverrides['source']['constants']['user_id'] = $user->id();

    return $this;
  }

  /**
   * Change the MigrateMessage class if need be.
   *
   * @param \Drupal\migrate\MigrateMessageInterface $messenger
   *   The new migrate message object.
   *
   * @return $this
   */
  public function setMessenger(MigrateMessageInterface $messenger) {
    $this->migrateMessage = $messenger;

    return $this;
  }

  /**
   * Fetches an array of migrations to run to import the Annual Report XML.
   *
   * @return string[]
   *   List of migrations.
   */
  public function getMigrationsList() {
    $migrations_list = [
      'component',
      'component_ix_personnel',
      'component_iv_statutes',
      'component_va_requests',
      'component_vb1_requests',
      'component_vb2_requests',
      'component_vb3_requests',
      'component_via_disposition',
      'component_vib_disposition',
      'component_vic1_applied_exemptions',
      'component_vic2_nonexemption_denial',
      'component_vic3_other_denial',
      'component_vic4_response_time',
      'component_vic5_oldest_pending',
      'component_viia_processed_requests',
      'component_viib_processed_requests',
      'component_viic1_simple_response',
      'component_viic2_complex_response',
      'component_viic3_expedited_response',
      'component_viid_pending_requests',
      'component_viie_oldest_pending',
      'component_viiia_expedited_processing',
      'component_viiib_fee_waiver',
      'component_xia_subsection_c',
      'component_xib_subsection_a2',
      'component_xiia',
      'component_xiib',
      'component_xiic',
      'component_xiid1',
      'component_xiid2',
      'component_xiie1',
      'component_xiie2',
      'component_x_fees',
      'foia_vb2_other',
      'foia_vic3_other',
      'foia_iv_details',
      'foia_iv_statute',
      'foia_va_requests',
      'foia_vb1_requests',
      'foia_vb2',
      'foia_vb3_requests',
      'foia_via_disposition',
      'foia_vib_disposition',
      'foia_vic1_applied_exemptions',
      'foia_vic2_nonexemption_denial',
      'foia_vic3',
      'foia_vic4_response_time',
      'foia_vic5_oldest_pending',
      'foia_viia_processed_requests',
      'foia_viib_processed_requests',
      'foia_viic1_simple_response',
      'foia_viic2_complex_response',
      'foia_viic3_expedited_response',
      'foia_viid_pending_requests',
      'foia_viie_oldest_pending',
      'foia_viiia_expedited_processing',
      'foia_viiib_fee_waiver',
      'foia_ix_personnel',
      'foia_x_fees',
      'foia_xia_subsection_c',
      'foia_xib_subsection_a2',
      'foia_xiia',
      'foia_xiib',
      'foia_xiic',
      'foia_xiid1',
      'foia_xiid2',
      'foia_xiie1',
      'foia_xiie2',
      'foia_agency_report',
    ];

    return $migrations_list;
  }

}
