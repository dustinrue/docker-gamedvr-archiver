#!/usr/bin/env php
<?php
  define("OUTPUT_DIR", "destination");
  date_default_timezone_set('Etc/GMT');

  $shortopts = "";
  $shortopts .= "g:";
  $shortopts .= "d::";
  $shortopts .= "s::";

  $options = getopt($shortopts);

  if (count($options) == 0) {
    printf("Usage: %s <options>\n\n", $argv[0]);
    printf("  -g       Your Xbox Gamertag\n");
    printf("  -d       Download GameDVR clips\n");
    printf("  -s       Download screenshots\n");
    printf("\n");
    exit;
  }

  $gamertag = (array_key_exists('g', $options)) ? $options['g']:false;

  // php's getopt doesn't deal with -sd or -ds so we handle it here
  $download_clips = array_key_exists('d', $options) || array_key_exists('s', $options) && $options['s'] == 'd';
  $download_screenshots = array_key_exists('s', $options) || array_key_exists('d', $options) && $options['d'] == 's';

  if (!$gamertag) {
    printf("Please provide your gamertag with -g\n");
    exit;
  }

  if (!$download_clips && !$download_screenshots) {
    printf("Please specify if you want to download clips (-d) and/or screenshots (-s)\n");
    exit;
  }
  
  if ($download_clips) {
    download_clips($gamertag);
  }

  if ($download_screenshots) {
    download_screenshots($gamertag);
  }

  function create_output_directory($output_directory) {
    if (!file_exists($output_directory)) {
      if (!@mkdir($output_directory, 0777, true)) {
        return false;
      }
    }
    return true;
  }
  function download_clips($gamertag) {
    $gamedvr_url = sprintf("https://xboxrecord.us/gamer/%s/gamedvr/json", urlencode($gamertag));
    $output_dir = sprintf("%s/GameDVR/%s", OUTPUT_DIR, $gamertag);
    $raw_clip_data = json_decode(file_get_contents($gamedvr_url));

    if(!$raw_clip_data) {
      print("Failed to download clip data, please try again later\n");
      return;
    }

    $download_queue = 0;
    foreach($raw_clip_data->gameClips AS $game_clip) {
      $filename = sprintf("%s/%s", $output_dir, generate_gamedvr_name($game_clip));

      if (file_exists($filename)) {
        continue;
      }
      else {
        $download_queue++;
      }
    }
    
    printf("Beginning download of %s clips\n", $download_queue);
    foreach($raw_clip_data->gameClips AS $game_clip) {
      foreach($game_clip->gameClipUris AS $uri) {
        if ($uri->uriType == "Download") {
          $filename = sprintf("%s/%s", $output_dir, generate_gamedvr_name($game_clip));

          if (file_exists($filename)) {
            printf("Skipping %s\n", $game_clip->gameClipId);
            continue;
          }

          if (!create_output_directory(dirname($filename))) {
            print("Unable to create output dir, please check permissions\n");
            return;
          }
          printf("Downloading \"%s\"...", ($game_clip->userCaption != "") ? $game_clip->userCaption:$game_clip->gameClipId);
          $source_file = fopen($uri->uri, 'r');
          $output_file = fopen($filename, 'w');

          // this method is much faster when using Docker on OS X
          // because of an issue with osxfs
          file_put_contents($filename, file_get_contents($uri->uri));
          /*
          while (($content = fgets($source_file)) !== false) {
            fputs($output_file, $content);
          }

          fclose($source_file);
          fclose($output_file);
          */
          touch($filename, strtotime($game_clip->dateRecorded));
          print("done\n");
        }
      }
    }

  }

  function download_screenshots($gamertag) {
    $screenshots_url = sprintf("https://xboxrecord.us/gamer/%s/screenshots/json", urlencode($gamertag));
    $output_dir = sprintf("%s/Screenshots/%s", OUTPUT_DIR, $gamertag);
    $raw_screenshot_data = json_decode(file_get_contents($screenshots_url));
  
    $download_queue = 0;
    foreach($raw_screenshot_data->screenshots AS $screenshot) {
      $filename = sprintf("%s/%s", $output_dir, generate_screenshot_name($screenshot));

      if (file_exists($filename)) {
        continue;
      }
      else {
        $download_queue++;
      }
    }
    
    printf("Beginning download of %s screenshots\n", $download_queue);
    foreach($raw_screenshot_data->screenshots AS $screenshot) {
      foreach($screenshot->screenshotUris AS $uri) {
        if ($uri->uriType == "Download") {
          $filename = sprintf("%s/%s", $output_dir, generate_screenshot_name($screenshot));

          if (file_exists($filename)) {
            printf("Skipping %s\n", $screenshot->screenshotId);
            continue;
          }

          if (!create_output_directory(dirname($filename))) {
            print("Unable to create output directory for screenshots, please check permissions\n");
            return;
          }
          printf("Downloading \"%s\"...", ($screenshot->userCaption != "") ? $screenshot->userCaption:$screenshot->screenshotId);
          $source_file = fopen($uri->uri, 'r');
          $output_file = fopen($filename, 'w');
          file_put_contents($filename, file_get_contents($uri->uri));
          /*
          while (($content = fgets($source_file)) !== false) {
            fputs($output_file, $content);
          }

          fclose($source_file);
          fclose($output_file);
          */
          touch($filename, strtotime($screenshot->dateTaken));
          print("done\n");
        }
      }
    }

  }

  function generate_gamedvr_name($game_clip) {
    if ($game_clip->titleName == "") {
      throw new Exception("Clip doesn't have a title, this is bad and unusual");
    }

    
    if ($game_clip->userCaption != "") {
      $filename = sprintf("%s/%s (%s).mp4", $game_clip->titleName, $game_clip->userCaption, $game_clip->gameClipId); 
    }
    else {
      $filename = sprintf("%s/%s.mp4", $game_clip->titleName, $game_clip->gameClipId);
    }

    return $filename;
  }

  function generate_screenshot_name($game_clip) {
    if ($game_clip->titleName == "") {
      throw new Exception("Screenshot doesn't have a title, this is bad and unusual");
    }

    
    if ($game_clip->userCaption != "") {
      $filename = sprintf("%s/%s (%s).png", $game_clip->titleName, $game_clip->userCaption, $game_clip->gameClipId); 
    }
    else {
      $filename = sprintf("%s/%s.png", $game_clip->titleName, $game_clip->screenshotId);
    }

    return $filename;
  }