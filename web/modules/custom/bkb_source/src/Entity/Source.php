<?php

declare(strict_types=1);

namespace Drupal\bkb_source\Entity;

use Dompdf\Dompdf;
use Dompdf\Options;
use Drupal\bkb_base\BibTeXConverter;
use Drupal\bkb_source\SourceInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\File\FileExists;
use Drupal\user\EntityOwnerTrait;
use Drupal\Component\Utility\Html;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Defines the source entity class.
 *
 * @ContentEntityType(
 *   id = "source",
 *   label = @Translation("Source"),
 *   label_collection = @Translation("Sources"),
 *   label_singular = @Translation("source"),
 *   label_plural = @Translation("sources"),
 *   label_count = @PluralTranslation(
 *     singular = "@count sources",
 *     plural = "@count sources",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\bkb_source\SourceListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\bkb_source\SourceAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\bkb_source\Form\SourceForm",
 *       "edit" = "Drupal\bkb_source\Form\SourceForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" =
 *   "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\bkb_source\Routing\SourceHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "source",
 *   admin_permission = "administer source",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/sources",
 *     "add-form" = "/source/add",
 *     "canonical" = "/source/{source}",
 *     "edit-form" = "/source/{source}",
 *     "delete-form" = "/source/{source}/delete",
 *     "delete-multiple-form" = "/admin/content/sources/delete-multiple",
 *   },
 *   field_ui_base_route = "entity.source.settings",
 * )
 */
final class Source extends ContentEntityBase implements SourceInterface {

  use EntityOwnerTrait;
  use StringTranslationTrait;
  #array to store messages to be issued in the postSave method 
  #they should contain placeholder @link which will be replaced by the message 
  #see usage below
  protected array $messages = [];

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    if (!$this->getOwnerId()) {
      $this->setOwnerId(0);
    }

    $label = $this->get('label')->value;

    // Store local copy of remote source and copy url to the source_url field
    // 4 source types are distinguished: 
    //      textual book desription
    //      link to digitalna-kniznica.geliana.sav.sk
    //      link to a pdf file
    //      link to a html page
    // For user's convenience URL is entered to the same field (label) as the textual book description
    // Here, URL is copied to field source_url and article name is saved in the label field
    if (UrlHelper::isValid($label, TRUE)) {

      if ($label != $this->get('source_url')->value) { #new URL specified
        $msg_title_not_found = "Page title was not found for this @link automatically. Copy it from the @page_link and update the source.";

        #  A special case of DK EnÚ
        if (strpos($label, 'digitalna-kniznica.beliana.sav.sk') == TRUE) {
          #Extract Title for the url
          parse_str($label, $params);

          // Check if the 'title' key exists in the array and get its value
          if (isset($params['title']) and isset($params['cv']) ) {
              $title = $params['title'];
              $page = $params['cv'];
              $page = str_replace("page_", "", $page);
              $tpt = $this->t("%s , page %d"); 
              $this->get('label')->value = sprintf((string)$tpt, $title, (int)$page);
              $this->get('source_url')->value = $label;
          } else {
              echo $this->t("Error, contact the programmer.");
          }

        # pdf
        } elseif (substr($label, -4) == ".pdf") {
          $this->get('label')->value = $this->t("Page title was not found");
          $page_link = Link::fromTextAndUrl($this->t("pdf"), Url::fromUri($label));
          $msg = $this->t($msg_title_not_found, [
                "@page_link" => $page_link->toString()
          ]);
          $this->messages[] = array (
              "type" => "warning",
              "url_text" => (string)$this->t("source"),
              "text" => (string)$msg
          );

          $this->getSourcePdf($label);
          $this->get('source_url')->value = $label;

        #html
        } else {
          $response = $this->getPageTitle($label);
#\dump($response); die();
          if (in_array($response['code'], ['200']) ) {  #Everything OK
            $this->get('label')->value = $response['value'];
            $this->getSourcePdf($label);
            $this->get('source_url')->value = $label;

          } elseif (in_array($response['code'], ['403']) ) {  #Forbidden to download by a script
            $msg_page_no_access = "Downloading of content of the @page_link failed owing to access restrictions.<br />Print its content to a pdf file and upload it manually to @link. Set also the source title.";
            $this->get('label')->value = $this->t("Downloading failed");
            $page_link = Link::fromTextAndUrl($this->t("article"), Url::fromUri($label, [
                'attributes' => [
                    'target' => '_blank',
                    'rel' => 'noopener noreferrer',
                ],
            ]));
            $msg = $this->t($msg_page_no_access, [
                "@page_link" => $page_link->toString()
            ]);

            $this->messages[] = array(
              "type" => "warning",
              "url_text" => (string)$this->t("source"),
              "text" => (string)$msg
            );
            $this->get('source_url')->value = $label;
            return;

          } elseif (in_array($response['code'], ['0']) ) {  #No response
              $msg = $this->t("The URL given in the @link is incorrect, verify and fix it.");
              $this->messages[] = array(
                "type" => "warning",
                "url_text" => (string)$this->t("source"),
                "text" => (string)$msg
              );
              return;
          } else {
              $msg = $this->t("Unexpected error in @link, verify and fix it.");
              $this->messages[] = array(
                "type" => "error",
                "url_text" => (string)$this->t("source"),
                "text" => (string)$msg
              );
              return;
          }
        }
      }
    }

    // add decriptive text (pdf, book. ...) to title, if missing
    $auxlabel =  $this->get('label')->value;
    if ($this->get('source_url')->isEmpty() ) { #book without url 
       if (strpos($auxlabel, "(kniha)") === FALSE) {
          $this->get('label')->value = sprintf("%s (%s)", $auxlabel,  "kniha");
       }
    } else { # some kind of URL
      $auxurl = $this->get('source_url')->value; 
      if (strpos($auxurl, 'digitalna-kniznica.beliana.sav.sk') === FALSE ) {
          if (substr($auxurl, -4) == ".pdf" ) { #pdf
             if (strpos((string)$auxlabel, "(pdf)") === FALSE) {
                $this->get('label')->value = sprintf("%s (%s)", $auxlabel,  "pdf");
             }
          } else {  #html page
             if (strpos((string)$auxlabel, "(web)") === FALSE) {
                $this->get('label')->value = sprintf("%s (%s)", $auxlabel,  "web");
             }
          }
      } else {  # DK EnÚ 
         if (strpos((string)$auxlabel, "(DK EnÚ)") === FALSE) {
            $this->get('label')->value = sprintf("%s (%s)", $auxlabel,  "DK EnÚ");
         }
      }
    }

    /** Temporary disabled to save credit, possible to fetch later **/
    //    if ($this->isNew()) {
    //      $config = \Drupal::configFactory()->get('bkb_base.settings');
    //      $response_text = \Drupal::service('bkb_base.ai_bibtex')
    //        ->getBibtex($config->get('api_key'), $label, $config->get('ai_prompt'));
    //
    //      if ($response_text) {
    //        $this->set('data', $response_text);
    //      }
    //    }
    //
    //    // Create citation form the bibtex value
    //    if (!$this->get('data')->isEmpty()) {
    //      $escapedValue = Html::escape($this->get('data')->value);
    //
    //      // Create a new BibTeXConverter instance with the escaped value.
    //      $converter = new BibTeXConverter($escapedValue);
    //      $harvardCitations = $converter->convertToHarvard();
    //      $citation = reset($harvardCitations);
    //
    //      // Set the 'citation' field with the first Harvard citation.
    //      $this->set('citation', $citation);
    //    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(\Drupal\Core\Entity\EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // print messages collected in this->preSave()
    foreach ($this->messages as $message) {
      $link = Link::fromTextAndUrl($message["url_text"], $this->toUrl());
      $msg = str_replace('@link', (string)$link->toString(), $message["text"]);
#\dump($this->messages, $link, $msg); die();
      if ($message['type'] == 'warning' ) {
        \Drupal::messenger()->addWarning( $msg );
      } elseif ($message['type'] == 'error' ) {
        \Drupal::messenger()->addError( $msg );
      } else {
        \Drupal::messenger()->addStatus( $msg );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setDescription(t('source-entity-label-description'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['data'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Data'))
      ->setDisplayOptions('form', [
        'type' => 'textarea',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['attachment'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Attachment'))
      ->setDescription(t('source-entity-attachment-description'))
      ->setSettings([
        'file_extensions' => 'pdf',
        'uri_scheme' => 'private',
      ])
      ->setDisplayOptions('form', [
        'type' => 'file_generic',
        'weight' => 11,
        'settings' => [
          'file_extensions' => 'pdf',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'file_default',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['citation'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Citation'))
      ->setReadOnly(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 11,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 11,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['source_url'] = BaseFieldDefinition::create('string')
      ->setLabel(t('SourceURL'))
      ->setDescription(t('source-entity-source-url-description'))
      ->setReadOnly(False)
      ->setSetting('max_length', 1024)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  private function getSourcePdf($url) {
    /** @var \GuzzleHttp\Client $http_client */
    $http_client = \Drupal::httpClient();
    /** @var \Drupal\Core\File\FileSystem $file_system */
    $file_system = \Drupal::service('file_system');

    try {
      $response = $http_client->get('https://pdfcreator.beliana.sav.sk/generate?source=' . $url, [
        'verify' => FALSE,
      ]);

      if ($response->getStatusCode() === 200) {
        $pdf_data = $response->getBody()->getContents();
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('bkb_source')
        ->error('Error getting PDF: ' . $e->getMessage());
      return;
    }

    $path = 'private://';
    $filename = $this->uuid() . '.pdf';

    $file_system->prepareDirectory($path);
    $file_system->saveData($pdf_data, $file_system->realpath($path) . DIRECTORY_SEPARATOR . $filename, FileExists::Replace);

    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityTypeManager()->getStorage('file')->create([
      'uri' => $path . $filename,
    ]);
    $file->setPermanent();
    $file->save();

    $this->set('attachment', ['target_id' => $file->id()]);
  }

  /****************************************************
   * {@inheritdoc}
   */
   private function getPageTitle($url) {
   // Fetch the HTML content from the URL

    $http_client = \Drupal::httpClient();
    try {
      $response = $http_client->get($url, [
        'verify' => FALSE,
        'timeout' => 10.0,
      ]);
      #\dump("OK",$response); die();

      $html = $response->getBody()->getContents();
    }
    catch (\Exception $e) { #mostly codes 403 and 0
      #\dump("FAIL",$e); die();
      \Drupal::logger('bkb_source')
        ->error('Error getting page title: ' . $e->getMessage());
      return array(
          "code" => $e->getCode(),
          "value" => $e->getMessage() 
      );
    }

    # Success, parse the content
    // Create a new DOMDocument object
    $doc = new \DOMDocument();

    // Suppress warnings for malformed HTML
    libxml_use_internal_errors(true);

    // Load the HTML into the DOMDocument object
    $doc->loadHTML($html);

    // Clear parsing errors
    libxml_clear_errors();

    // Get the title element
    $titleNode = $doc->getElementsByTagName('title')->item(0);

    // Check if the title element exists
    if ($titleNode) {
       return array(
         "code" => 200,
         "value" => $titleNode->nodeValue
       );
    } else {
       return array(
         "code" => 200, // We return 200 here, 
                        //'value' will be printed directly and content will be downloaded
         "value" => $this->t("Page title was not found")
       );
    }
   }
}
