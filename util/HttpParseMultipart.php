<?php

// http://stackoverflow.com/questions/9464935/php-multipart-form-data-put-request/9469615#9469615

namespace deco\essentials\util;

class HttpParseMultipart {

  static public function parse(array &$variables, array &$files, $raw_data = null) {
    // Fetch content and determine boundary
    if (func_num_args() < 3) {
      $raw_data = file_get_contents('php://input');
    }
    $boundary = substr($raw_data, 0, strpos($raw_data, "\r\n"));

    // Fetch each part
    $parts = array_slice(explode($boundary, $raw_data), 1);

    $isAssoc = true;

    foreach ($parts as $part) {
      // If this is the last part, break
      if ($part == "--\r\n") {
        break;
      }

      // Separate content from headers
      $part = ltrim($part, "\r\n");
      list($raw_headers, $body) = explode("\r\n\r\n", $part, 2);

      // Parse the headers list
      $raw_headers = explode("\r\n", $raw_headers);
      $headers = array();
      foreach ($raw_headers as $header) {
        list($name, $value) = explode(':', $header);
        $headers[strtolower($name)] = ltrim($value, ' ');
      }

      // Parse the Content-Disposition to get the field name, etc.
      if (isset($headers['content-disposition'])) {
        $filename = null;
        preg_match(
            '/^(.+); *name="([^"]+)"(; *filename="([^"]+)")?/', $headers['content-disposition'], $matches
        );
        list(, $type, $name) = $matches;
        isset($matches[4]) and $filename = $matches[4];

        if (isset($matches[4])) {
          $file['content'] = $body;
          $file['filename'] = $filename;
          $files[$name] = $file;
          if (!$isAssoc) {
            array_push($ifles, $file);
          } else if (array_key_exists($name, $file)) {
            $files = array_values($files);
            array_push($files, $file);
            $isAssoc = false;
          } else {
            $files[$name] = $file;
          }
        } else {
          $variables[$name] = substr($body, 0, strlen($body) - 2);
        }
      }
    }
  }

}
