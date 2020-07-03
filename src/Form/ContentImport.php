<?php

namespace Drupal\contentimport\Form;

use Drupal\contentimport\Controller\ContentImportController;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;

/**
 * Configure Content Import settings for this site.
 */
class ContentImport extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'contentimport';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'contentimport.settings',
    ];
  }

  /**
   * Content Import Form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $contentTypes = ContentImportController::getAllContentTypes();
    $form['contentimport_contenttype'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Content Type'),
      '#options' => $contentTypes,
      '#default_value' => $this->t('Select'),
      '#required' => TRUE,
      '#description' => $this->t('Select content type to be import'),
    ];

    $form['contentimport_importtype'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Import Type'),
      '#options' => [
        'Select',
        'Create New content',
        'Update existing content',
      ],
      '#default_value' => $this->t('Select'),
      '#required' => TRUE,
      '#description' => $this->t('Select import type to create/update node.'),
      '#ajax' => [
        'event' => 'change',
        'callback' => '::contentImportcallback',
        'wrapper' => 'content_import_fields_change_wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ],
    ];

    $form['file_upload'] = [
      '#type' => 'file',
      '#title' => $this->t('Import CSV File'),
      '#size' => 40,
      '#description' => $this->t('Select the CSV file to be imported.'),
      '#required' => FALSE,
      '#autoupload' => TRUE,
      '#upload_validators' => ['file_validate_extensions' => ['csv']],
    ];

    $form['loglink'] = [
      '#type' => 'link',
      '#title' => $this->t('Check Log..'),
      '#url' => Url::fromUri('base:sites/default/files/contentimportlog.txt'),
      '#description' => $this->t('Upload CSV file only with delimter ","'),
    ];

    $form['import_ct_markup'] = [
      '#suffix' => '<div id="content_import_fields_change_wrapper"></div>',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    if ($form_values['contentimport_contenttype'] == 'none' || $form_values['contentimport_importtype'] == 0) {
      $form_state->setErrorByName('contentimport_contenttype', $this->t('Content type or Import type missing. Please select.'));
    }
    else {
      $this->file = file_save_upload('file_upload', $form['file_upload']['#upload_validators'], FALSE, 0);
      if (!$this->file) {
        $form_state->setErrorByName('file_upload', $this->t('Provided file is not a CSV file or is corrupted.'));
      }
    }
  }

  /**
   * Content Import Sample CSV Creation.
   */
  public function contentImportcallback(array &$form, FormStateInterface $form_state) {
    global $base_url;
    $ajax_response = new AjaxResponse();
    $content_type = $form_state->getValue('contentimport_contenttype');
    $import_type = $form_state->getValue('contentimport_importtype');
    $fields = get_fields($content_type);
    $fieldArray = $fields['name'];
    $content_type_fields = ($import_type == 2) ? 'nodeid,title,langcode' : 'title,langcode';
    foreach ($fieldArray as $val) {
      $content_type_fields .= $val . ',';
    }
    $content_type_fields = substr($content_type_fields, 0, -1);
    $sampleFile = $content_type . '.csv';
    $handle = fopen("sites/default/files/" . $sampleFile, "w+") or die("There is no permission to create log file. Please give permission for sites/default/file!");
    fwrite($handle, $content_type_fields);
    $result = '<a class="button button--primary" href="' . $base_url . '/sites/default/files/' . $sampleFile . '">Click here to download Sample CSV</a>';
    $ajax_response->addCommand(new HtmlCommand('#content_import_fields_change_wrapper', $result));
    return $ajax_response;
  }

  /**
   * Content Import Form Submission.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $content_type = $form_state->getValue('contentimport_contenttype');
    $import_type = $form_state->getValue('contentimport_importtype');
    // import_node($this->file, $content_type, $import_type);.
    $batch = [
      'title' => $this->t('Importing Content'),
      'operations' => [
        [
          'import_node', [
            $this->file,
            $content_type,
            $import_type,
          ],
        ],
      ],
      'init_message' => $this->t('Importing...please wait'),
      'progress_message' => $this->t('Processed @current out of @total.'),
      'finished' => 'import_success',
    ];
    batch_set($batch);
  }

}
