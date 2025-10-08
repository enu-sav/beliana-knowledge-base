<?php

declare(strict_types=1);

namespace Drupal\bkb_source\Entity;

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
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
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
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
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
 *     "edit-form" = "/source/{source}/edit",
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

    # fail when using local links
    if (substr($label, 0, 6) == "file:/" ) {
      $args = [
        "%label" => $this->t("Label"),
        "%attachment" => $this->t("Attachment") 
      ];
      $msg = $this->t("Link to a local file in field <em>%label</em> is not in @link allowed. If you want to upload a local file, use its field <em>%attachment</em>.", $args);

      $this->messages[] = array(
        "type" => "error",
        "url_text" => (string)$this->t("source_genitiv"),
        "text" => (string)$msg
      );
      return;
    }


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

        $cleanPdfUrl = $this->getPdfUrl($label);
        if ($cleanPdfUrl) {
            $label = $cleanPdfUrl;
        }
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
        } elseif ($this->isPdfUrl($label) ) {
          # Pdfs do not have titles
          $this->get('label')->value = $this->t("Page title was not found");
          #$page_link = Link::fromTextAndUrl($this->t("pdf"), Url::fromUri($label));
          $page_link = Link::fromTextAndUrl($this->t("pdf"), Url::fromUri($label, [
              'attributes' => [
                  'target' => '_blank',
                  'rel' => 'noopener noreferrer',
              ],
          ]));
          $msg = $this->t($msg_title_not_found, [
                "@page_link" => $page_link->toString()
          ]);
          $this->messages[] = array (
              "type" => "warning",
              "url_text" => (string)$this->t("pdf_link"),
              "text" => (string)$msg
          );

          $this->getSourcePdf($label);
          $this->get('source_url')->value = $label;

        #html
        } else {
          $response = $this->getPageTitle($label);
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
              "url_text" => (string)$this->t("url_link"),
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
          if ($this->isPdfUrl($auxurl) ) { #pdf
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

    $fields['label'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Label'))
      ->setDescription(t('source-entity-label-description'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'textarea',
        'weight' => -5,
        'settings' => [
          'rows' => 1,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
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
   * Download a web page using the download service. Download pdfs directly because the service spoils them (adds left panel) 
   */
  private function getSourcePdf($url) {
    /** @var \GuzzleHttp\Client $http_client */
    $http_client = \Drupal::httpClient();
    /** @var \Drupal\Core\File\FileSystem $file_system */
    $file_system = \Drupal::service('file_system');

    try {
      if ($this->isPdfUrl($url) ){
        $response = $http_client->get($url, [
        'verify' => FALSE,
        ]);
      } else {
        $response = $http_client->get('https://pdfcreator.beliana.sav.sk/generate?source=' . $url, [
          'verify' => FALSE,
        ]);
      }

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

  /****************************************************
   * {@inheritdoc}
   */
   private function isPdfUrl($url) {
     parse_str($url, $params);
#\dump($params); \die();
     if (isset($params['url']) and substr($params['url'], -4) == ".pdf") {
        #url like google search link
        return TRUE;
     } elseif (substr($url, -4) == ".pdf" or    #these seem to be pdfs
         strpos($url, ".pdf?") != NULL or
         strpos($url, ".pdf&") != NULL  
       ) {
        return TRUE;
     } else {
         return FALSE;
     }
   }

  /****************************************************
   * {@inheritdoc}
   */
   private function getPdfUrl($url) {
     parse_str($url, $params);
#\dump($params); \die();
     if (isset($params['url']) and substr($params['url'], -4) == ".pdf") {
        #url like google search link
        return $params['url'];
     } elseif (substr($url, -4) == ".pdf" or    #these seem to be pdfs
         strpos($url, ".pdf?") != NULL  or
         strpos($url, ".pdf&") != NULL  
       ) {
        return $url;
     } else {
         return NULL;
     }
   }
}

#https://dl.icdst.org/pdfs/files3/a8cfedd8fd4e1717ecabd62c25a36b16.pdf
#https://www.sciencedirect.com/science/article/pii/S0926224598000308/pdf?crasolve=1&r=8ca9ca718cdd5bb0&ts=1727590023991&rtype=https&vrr=UKN&redir=UKN&redir_fr=UKN&redir_arc=UKN&vhash=UKN&host=d3d3LnNjaWVuY2VkaXJlY3QuY29t&tsoh=d3d3LnNjaWVuY2VkaXJlY3QuY29t&rh=d3d3LnNjaWVuY2VkaXJlY3QuY29t&re=X2JsYW5rXw%3D%3D&ns_h=d3d3LnNjaWVuY2VkaXJlY3QuY29t&ns_e=X2JsYW5rXw%3D%3D&rh_fd=rrr)n%5Ed%60i%5E%60_dm%60%5Eo)%5Ejh&tsoh_fd=rrr)n%5Ed%60i%5E%60_dm%60%5Eo)%5Ejh&iv=dd439770c3aa50381e5fa0a20ebad78c&token=61383863396434393863356166373930613066303731386234343464323730663733333864356334323561353963396130643063383762306239653064373065663266653833636361323939306337653833656462393964303736373430336239653734613834356533306238313334623031643739643238623a376339343266626465396638616334656530346661356462&text=687e8721c24605ec6891f6a01e31696276d0d69deb0cb12c7a421e93667f8bf8e09a162e14466b48673dbc662b427cba102bbe515df9666bfe3222c425ceda8087c07e357df50be03b6e383457be9cd41c7614ea498f0eefdfe26f71196ccc52ce2eeabb3b0c808c2ee7e984ba098cfee9c264e1eba4f5499c1c9b6a396965efc7b08bb6b0d199aa69109cef4737f7892b86daac6349a76f85610fe1339ef61d1bb3be885d9b7e4abf86417f8dfdf258e53c18ba9828f69fe08d7c1f60113a80e39ad05b1bba08d876bfeefa845aed5fb30c7307cc88b0e7c34cf8cc76d9dd3c7407a3a89d828675cf40f43092c6e3d52436211db994440cd80ed0249bc78efdefcd0312c1aaeb86314d03a42ba179c1d7463f59b1ef38a33944e56eccd5f89bbba58aa22558d5f8b315f82ace51d859&original=3f6d64353d3132393865353230323964633836323732396634333335613034366631613938267069643d312d73322e302d53303932363232343539383030303330382d6d61696e2e706466265f76616c636b3d31
#https://askubuntu.com/questions/221962/how-can-i-extract-a-page-range-a-part-of-a-pdf
#https://stirlingpdf.io/ocr-pdf?lang=sk_SK
#http://cloud-1.edupage.org/cloud/Kruznica_uhly_v_kruznici.pdf?z%3ACpJrHxIX4RGhTwzGX2ek9JGOCBdNnzVVQQAJ42W5mU%2BipN0X2WWmBxy9%2ByO0otmY
#https://www.google.com/search?q=linear+functional+pdf&client=ubuntu-sn&hs=QGs&sca_esv=f3ee8fd9fd7caba2&channel=fs&sxsrf=AE3TifNMlSvi5uvtUrZiFunCd-guDGQUZg%3A1759924401584&ei=sVDmaOWvI--Oxc8PjaSw6A8&ved=0ahUKEwilzKamxZSQAxVvR_EDHQ0SDP0Q4dUDCBA&uact=5&oq=linear+functional+pdf&gs_lp=Egxnd3Mtd2l6LXNlcnAiFWxpbmVhciBmdW5jdGlvbmFsIHBkZjIHEAAYgAQYDTIGEAAYFhgeMgYQABgWGB4yBhAAGBYYHjIGEAAYFhgeMgYQABgWGB4yBhAAGBYYHjIGEAAYFhgeMgYQABgWGB4yBhAAGBYYHkiZQVCdOVjVP3AAeAKQAQCYAbEBoAHsBKoBAzAuNLgBA8gBAPgBAZgCBaACwAXCAgQQABhHwgIFEC4YgATCAgUQABiABMICCBAAGIAEGKIEwgIUEC4YgAQYlwUY3AQY3gQY4ATYAQGYAwCIBgGQBgi6BgYIARABGBSSBwMxLjSgB9YksgcDMC40uAe2BcIHBzItMS4zLjHIBz0&sclient=gws-wiz-serp
#
#https://food.ec.europa.eu/document/download/b9e6176b-31ba-40fc-8870-af9240a2f1d7_en?filename=sci-com_ssc_out30_en.pdf&utm_source=chatgpt.com
