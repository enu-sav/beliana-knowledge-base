<?php

declare(strict_types=1);

namespace Drupal\bkb_base;

/**
 * Class BibTeXConverter
 *
 * This class is used to convert BibTeX entries into Harvard-style citations.
 * It supports books, articles, and online resources.
 */
class BibTeXConverter {

  /**
   * @var array $bibTeXEntries
   * An array of parsed BibTeX entries.
   */
  private $bibTeXEntries;

  /**
   * Constructor for BibTeXConverter.
   *
   * @param string $bibTeXString The BibTeX string to parse.
   */
  public function __construct($bibTeXString) {
    $this->bibTeXEntries = $this->parseBibTeX($bibTeXString);
  }

  /**
   * Parse a BibTeX string into an array of entries.
   *
   * @param string $bibTeXString The BibTeX string to parse.
   *
   * @return array An array of parsed BibTeX entries.
   */
  private function parseBibTeX($bibTeXString) {
    $entries = [];
    $lines = explode("\n", $bibTeXString);
    $currentEntry = [];
    $currentType = NULL;

    foreach ($lines as $line) {
      $line = trim($line); // Trim leading/trailing whitespace
      if (strpos($line, '@') === 0) {
        if (!empty($currentEntry)) {
          $entries[] = $currentEntry;
        }
        $parts = explode('{', $line);
        $currentType = trim($parts[0]);
        $currentEntry = ['type' => $currentType, 'id' => trim($parts[1], ',')];
      }
      elseif (strpos($line, '=') !== FALSE) {
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, ' {},');
        // Remove leading '@' from values (URL and author)
        $value = ltrim($value, '@');
        $currentEntry[$key] = $value;
      }
    }

    if (!empty($currentEntry)) {
      $entries[] = $currentEntry;
    }

    return $entries;
  }

  /**
   * Convert parsed BibTeX entries into Harvard-style citations.
   *
   * @return array An array of Harvard-style citations.
   */
  public function convertToHarvard() {
    $harvardCitations = [];

    foreach ($this->bibTeXEntries as $entry) {
      switch ($entry['type']) {
        case '@book':
          $harvardCitations[] = $this->formatBook($entry);
          break;
        case '@article':
          $harvardCitations[] = $this->formatArticle($entry);
          break;
        case '@online':
        case '@url':
          $harvardCitations[] = $this->formatURL($entry);
          break;
        default:
          echo "Unsupported type: " . $entry['type'] . "\n";
      }
    }

    return $harvardCitations;
  }

  /**
   * Format a book entry into a Harvard-style citation.
   *
   * @param array $entry The book entry.
   *
   * @return string The formatted citation.
   */
  private function formatBook($entry) {
    $authors = $this->formatAuthors($entry['author']);
    $title = $entry['title'];
    $publisher = $entry['publisher'];
    $year = $entry['year'];

    return "$authors ($year) $title. $publisher.";
  }

  /**
   * Format an article entry into a Harvard-style citation.
   *
   * @param array $entry The article entry.
   *
   * @return string The formatted citation.
   */
  private function formatArticle($entry) {
    $authors = $this->formatAuthors($entry['author']);
    $title = $entry['title'];
    $journal = $entry['journal'];
    $volume = $entry['volume'];
    $number = isset($entry['number']) ? $entry['number'] : '';
    $pages = $entry['pages'];
    $year = $entry['year'];

    return "$authors ($year) $title. $journal, $volume($number): $pages";
  }

  /**
   * Format an online resource entry into a Harvard-style citation.
   *
   * @param array $entry The online resource entry.
   *
   * @return string The formatted citation.
   */
  private function formatURL($entry) {
    $authors = isset($entry['author']) ? $this->formatAuthors($entry['author']) : '';
    $title = $entry['title'];
    $url = $entry['url'];
    $year = $entry['year'];
    $accessed = isset($entry['urldate']) ? " (accessed " . $entry['urldate'] . ")" : '';

    return "$authors ($year) $title. Available at: $url$accessed";
  }

  /**
   * Format authors into the "Lastname, Initial." style.
   *
   * @param string $authors The authors string.
   *
   * @return string The formatted authors string.
   */
  private function formatAuthors($authors) {
    $authorsArray = explode(' and ', $authors);
    $formattedAuthors = [];

    foreach ($authorsArray as $author) {
      $names = explode(',', $author);

      if (count($names) > 1) {
        // Format: Lastname, FirstInitial.
        $formattedAuthors[] = trim($names[0]) . ', ' . substr(trim($names[1]), 0, 1) . '.';
      }
      else {
        // Handle single name or no comma separation
        $parts = explode(' ', $author);
        if (count($parts) > 1) {
          // Format: Lastname, FirstInitial.
          $formattedAuthors[] = $parts[count($parts) - 1] . ', ' . substr($parts[0], 0, 1) . '.';
        }
        else {
          // If only one name is provided, use it as is
          $formattedAuthors[] = trim($author);
        }
      }
    }

    // For multiple authors, use "and" before the last author
    if (count($formattedAuthors) > 1) {
      $lastAuthor = array_pop($formattedAuthors);
      $formattedAuthors = implode(', ', $formattedAuthors) . ' and ' . $lastAuthor;
    }
    else {
      $formattedAuthors = implode(', ', $formattedAuthors);
    }

    return $formattedAuthors;
  }
}
