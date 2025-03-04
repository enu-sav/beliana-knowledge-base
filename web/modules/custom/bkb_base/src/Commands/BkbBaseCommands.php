<?php

namespace Drupal\bkb_base\Commands;

use Drupal;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class BkbBaseCommands extends DrushCommands {

  /**
   * Adds a new column to a CSV file and inserts a fixed text into it.
   *
   * @param string $source_file
   *   The path to the CSV file.
   *
   * @command csv:generate-bibtext
   * @aliases cgb
   * @usage csv:generate-bibtext /path/to/input.csv
   */
  public function addColumnToCsv($source_file) {
    // Ensure the source file exists.
    if (!file_exists($source_file)) {
      $this->output()->writeln("<error>Source file does not exist: $source_file</error>");
      return;
    }

    $separator = ','; // @todo send it with command as parameter?
    // Get the file's directory and name.
    $file_directory = dirname($source_file);
    $file_name = basename($source_file, '.csv');
    $config_factory = \Drupal::configFactory();
    $config = $config_factory->get('bkb_base.settings');
    $prompt = $config->get('ai_prompt');

    // Create the output file path with --ai suffix.
    $destination_file = $file_directory . '/' . $file_name . '--ai-generated.csv';

    // Initialize an array to hold the updated CSV data.
    $rows = [];

    // Open the source CSV file.
    if (($handle = fopen($source_file, 'r')) !== FALSE) {
      // Read the first row (header) and add the new column.
      $header = fgetcsv($handle, 0, $separator);
      if ($header !== FALSE) {
        $header = array_merge($header, [
          'Perplexity - llama-3.1-sonar-small-128k-online',
          'Perplexity - llama-3.1-sonar-large-128k-online',
          'Perplexity - sonar-deep-research',
          'Perplexity - sonar',
          'OpenAI - gpt-4o-mini',
          'OpenAI - gpt-4.5-preview',
          'OpenAI - gpt-4o',
          'OpenAI - chatgpt-4o-latest',
          'OpenAI - o1-mini'
        ]);
        $rows[] = $header;

        // Define the models for perplexity and OpenAI.
        $perplexityModels = [
          'llama-3.1-sonar-small-128k-online',
          'llama-3.1-sonar-large-128k-online',
          'sonar-deep-research',
          'sonar'
        ];

        $openAIModels = [
          'gpt-4o-mini',
          'gpt-4.5-preview',
          'gpt-4o',
          'chatgpt-4o-latest',
          'o1-mini'
        ];

        $aiBibtexService = \Drupal::service('bkb_base.ai_bibtex');

        // Process CSV data.
        while (($data = fgetcsv($handle, 0, $separator)) !== FALSE) {
          // Get Perplexity data.
          foreach ($perplexityModels as $model) {
            $data[] = $aiBibtexService->getBibtexPerplexity($data[0], $prompt, $model);
          }

          // Get OpenAI data.
          foreach ($openAIModels as $model) {
            $data[] = $aiBibtexService->getBibtexOpenAI($data[0], $prompt, $model);
          }

          // Add the processed data to rows.
          $rows[] = $data;
        }
      }
      fclose($handle);
    }

    // Write the modified CSV to the destination file.
    if (($handle = fopen($destination_file, 'w')) !== FALSE) {
      // Ensure that we use the same delimiter when writing the CSV.
      foreach ($rows as $row) {
        fputcsv($handle, $row, $separator);
      }
      fclose($handle);
    }

    $this->output()->writeln("<info>CSV file has been saved to $destination_file</info>");
  }

}
