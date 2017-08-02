<?php

/**
 * @file
 * Contains \Drupal\contentimport\Form\ContentImport.
 */

namespace Drupal\contentimport\Form;

use Drupal\contentimport\Controller\ContentImportController;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\Form;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;

/**
 * Configure Content Import settings for this site.
**/

class ContentImport extends ConfigFormBase { 

  public function getFormID() {
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
    $ContentTypes = ContentImportController::getAllContentTypes(); 
    $selected = 0;
    $form['contentimport_contenttype'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Content Type'),
      '#options' => $ContentTypes,
      '#default_value' => $selected,
    ];  

    $form['file_upload'] = [
      '#type' => 'managed_file',
      '#title' => t('Import CSV File'),
      '#size' => 40,
      '#description' => t('Select the CSV file to be imported. '),
      '#required' => FALSE,
      '#autoupload' => TRUE,
      '#upload_validators' => array('file_validate_extensions' => array('csv'))
    ];

    $form['submit'] = [
    '#type' => 'submit', 
    '#value' => t('Import'),
    '#button_type' => 'primary',
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $contentType= $form_state->getValue('contentimport_contenttype');    
    $form_state_values = $form_state->getValues();
    $csvFile = $form_state->getValue('file_upload');
    $file = File::load( $csvFile[0] );
    $file->setPermanent();
    $file->save();
    ContentImport::createNode($contentType);
  }

  /**
   * To get all Content Type Fields.
  */

  public function getFields($contentType) {
    $entityManager = \Drupal::service('entity.manager');
    $fields = []; 
    foreach (\Drupal::entityManager()
         ->getFieldDefinitions('node', $contentType) as $field_name => $field_definition) {
      if (!empty($field_definition->getTargetBundle())) {
        $fields[] = $field_definition->getName();
      }
    }
    return $fields;
  }

  /**
   * To import data as Content type nodes.
  */

  public function createNode($contentType){ 
    global $base_url;  
    $loc = db_query('SELECT file_managed.uri FROM file_managed ORDER BY file_managed.fid DESC limit 1', array());
    foreach($loc as $val){
      $location = $val->uri; // To get location of the csv file imported
    }
    $mimetype = mime_content_type($location);
    $fields = ContentImport::getFields($contentType);
    $files = glob('sites/default/files/'.$contentType.'/images/*.*');
    $images = [];
    foreach ($files as $file_name) {
      file_unmanaged_copy($file_name, 'sites/default/files/'.$contentType.'/images/' .basename($file_name));
      $image = File::create(array('uri' => 'sites/default/files/'.$contentType.'/images/' .basename($file_name)));
      $image->save();
      $images[basename($file_name)] = $image;
    }
    if($mimetype == "text/plain"){ //Code for import csv file
      if (($handle = fopen($location, "r")) !== FALSE) {
          $nodeData = []; $keyIndex = [];
          $index = 0;
          while (($data = fgetcsv($handle)) !== FALSE) { 
            $index++;
            if ($index < 2) {
              array_push($fields,'title');
              foreach($fields AS $fieldValues){
                $i = 0;
                  foreach($data AS $dataValues){
                    if($fieldValues == $dataValues){
                      $keyIndex[$fieldValues] = $i;
                    }
                    $i++;
                  }
              }              
              continue;
            }
            foreach($fields AS $fieldValues){
              $pos = strpos($fieldValues, 'image');
              $pos1 = strpos($fieldValues, 'img');
              if ($pos === false && $pos1 ==  false) {
                $nodeArray[$fieldValues] = $data[$keyIndex[$fieldValues]];
              }else{
                if (!empty($images[$data[$keyIndex[$fieldValues]]])) {
                  $nodeArray[$fieldValues] = array(array('target_id' => $images[$data[$keyIndex[$fieldValues]]]->id()));
                }
              }
            }
            $nodeArray['type'] = strtolower($contentType);
            $nodeArray['uid'] = 1;
            $node = \Drupal\node\Entity\Node::create($nodeArray);
            $node->save();

      }
      fclose($handle);
      $url = $base_url."/admin/content";
      header('Location:'.$url);
      exit;
    }
    }
  }

 
}