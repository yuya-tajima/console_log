<?php
/*
Plugin Name: Console Log
Description: store the var_dump results as a text file.
Version: 0.5
Author: Yuya Tajima
*/

if ( ! function_exists('console_log') ) {
  function console_log( $dump, $index = 1, $ajax = true, $echo = false ) {

    if ( ! isset( $dump ) ) {
      $dump = NULL;
    }

    if( ! $ajax && ( defined( 'DOING_AJAX' ) && DOING_AJAX  ) ){
      die();
    }

    $debug_log = '';

    if ( defined('WP_CONTENT_DIR') ) {

      $debug_path_file = WP_CONTENT_DIR . '/console_log.php';

      if ( ! file_exists( $debug_path_file ) ) {
        touch( $debug_path_file );
        chmod( $debug_path_file, 0666 );
      }

      $debug_log = include( $debug_path_file );
    }

    if ( ! file_exists( $debug_log ) ) {
      echo 'Debug log File does not exist.' . PHP_EOL;
      return;
    }

    $file_size = filesize( $debug_log );
    $file_size = (int) floor( $file_size / 1024 ) ;

    // if the log file size over 10MB, stop this flow immediately.
    if ( $file_size > 10240 ) {
      $fp = fopen( $debug_log, 'w+b' );
      if ( is_resource( $fp ) ) {
        flock( $fp, LOCK_EX );
        fflush( $fp );
        flock( $fp, LOCK_UN );
        fclose( $fp );
      }
      return;
    }

    if( ! file_exists( $debug_log ) ){
        if ( touch( $debug_log ) ) {
            chmod( $debug_log, 0666);
        } else {
            return;
        }
    }

    ob_start();
    echo '*********************************************' . PHP_EOL;
    _save_debug_log_backtrace($index);
    var_dump( $dump );
    echo '*********************************************' . PHP_EOL;

    $out = ob_get_contents();

    ob_end_clean();

    $save_debug_log = $out;

    file_put_contents( $debug_log, $out, FILE_APPEND | LOCK_EX );

    //if headers are not sending and $echo is true
    //echo $dump
    if( $echo && ! headers_sent() ){
      echo nl2br( $save_debug_log );
    }
  }

  function _save_debug_log_backtrace( $index = 1, $LF = PHP_EOL  ) {

    $debug_traces = debug_backtrace();

    if ( function_exists('date_i18n') ) {
      echo date_i18n('Y-m-d H:i:s') . $LF;
    } else {
      date_default_timezone_set( 'Asia/Tokyo' );
      echo date('Y-m-d H:i:s') . $LF;
    }
    echo 'use memory(MB):' . round( memory_get_usage() / ( 1024 * 1024 ), 2 ) . 'MB' . $LF;
    echo $LF;
    echo 'called_file:' . $debug_traces[$index + 1]['file']. $LF;
    echo 'called_file_line:' . $debug_traces[$index + 1]['line'] . $LF;
    echo $LF;
    echo 'current_file:' . $debug_traces[$index]['file']. $LF;
    echo 'current_line:' . $debug_traces[$index]['line'] . $LF;
    echo $LF;
  }
}
