<?php
/*
Plugin Name: Console Log
Description: store the var_dump results as a text file.
Version: 0.8.6
Author: Yuya Tajima
*/

/**
 * This is a debugging tool. Store the var_dump results as a text file.
 *
 * @type mixed $dump The data you want to dump. default NULL.
 * @param array $args {
 * available arguments.
 *
 * @type bool $any_time Whether to run this method anytime. default true.
 *                      If false, run when $_GET['debug'] variable is setting.
 * @type bool $wp_ajax Whether to run when WordPress Ajax is running. default true.
 * @type int $index the number of index should be tarced. default 3.
 * @type bool $echo Whether to output the $dump to a Web Browser. default false.
 * @type bool $extra Whether to show more information. default false.
 * @type mixed (string|PHP_EOL) $LF End Of Line symbol. default PHP_EOL.
 * @type string $time_zone sets the default timezone that is used for time logging. default 'Asia/Tokyo'.
 * @type bool $display_error Whether to output the last occurred PHP error. default true.
 *
 * @author Yuya Tajima
 * @link https://github.com/yuya-tajima/console_log
 */
if ( ! function_exists( 'console_log' ) ) {
  function console_log( $dump = NULL, array $args = array() ) {

    $defaults = array(
      'any_time'      => true,
      'wp_ajax'       => true,
      'index'         => 3,
      'echo'          => false,
      'extra'         => false,
      'LF'            => PHP_EOL,
      'time_zone'     => 'Asia/Tokyo',
      'display_error' => true,
    );

    $args = array_merge( $defaults, $args );

    if ( ! $args['any_time'] && empty( $_GET['debug'] ) ) {
      return;
    }

    if( ! $args['wp_ajax'] && ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ){
      return;
    }

    $debug_log = '';
    if ( defined( 'CONSOLE_LOG_FILE' ) && is_string( CONSOLE_LOG_FILE ) ) {
      $debug_log = CONSOLE_LOG_FILE;
    }

    $debug_log = _removeNullByte( $debug_log );

    if ( ! $debug_log ) {
      error_log( 'Debug log file name is invalid.' );
      return;
    }

    if ( ! file_exists( $debug_log ) ) {
      if ( touch( $debug_log ) ) {
        chmod( $debug_log, 0666 );
      } else {
        error_log( $debug_log . ' does not exist. and could not be created.' );
        return;
      }
    }

    if ( ! is_writable( $debug_log ) ) {
      error_log( $debug_log . ' is not writable. please change the file permission. or use another log file.' );
      return;
    }

    if ( ! is_readable( $debug_log ) ) {
      error_log( $debug_log . ' is not readable. please change the file permission. or use another log file.' );
      return;
    }

    $file_size = filesize( $debug_log );
    $file_size = (int) ( $file_size / 1024 ) ;

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

    ob_start();
    echo '*********************************************' . $args['LF'];
    _console_log_backtrace( $args );
    if( defined( 'DOING_AJAX' ) && DOING_AJAX ){
      echo 'Ajax is running! by WordPress.' . $args['LF'] . $args['LF'];
      var_dump($_POST);
      echo $args['LF'];
    }
    var_dump( $dump );
    echo $args['LF'];
    echo '*********************************************' . $args['LF'];
    $out = ob_get_clean();

    file_put_contents( $debug_log, $out, FILE_APPEND | LOCK_EX );

    //if headers have not already been sent and $args['echo'] is true
    //output the $dump to a Web Browser
    if( $args['echo'] && ! headers_sent() ){
      echo nl2br( htmlspecialchars( $out, ENT_QUOTES, 'UTF-8' ) );
    }
  }

  function _console_log_backtrace( $args  ) {

    $index     = $args['index'];
    $extra     = $args['extra'];
    $time_zone = $args['time_zone'];
    $LF        = $args['LF'];

    $debug_traces = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, $index + 1 );
    array_shift($debug_traces);

    if ( function_exists('date_i18n') ) {
      echo 'time               : ' . date_i18n( 'Y-m-d H:i:s' ) . $LF;
    } else {
      $default_timezone = date_default_timezone_get();
      date_default_timezone_set( $time_zone );
      echo 'time               : ' . date( 'Y-m-d H:i:s' ) . $LF;
      date_default_timezone_set( $default_timezone );
    }
    echo 'using memory(MB)   : ' . round( memory_get_usage(true) / ( 1024 * 1024 ), 2 ) . ' MB' . $LF;

    echo $LF;

    // get error message
    if ( $last_error = error_get_last() ) {
      echo 'error message      : '. $last_error['message'] .$LF;
      echo 'error file         : '. $last_error['file'] .$LF;
      echo 'error line         : '. $last_error['line'] .$LF;
    } else {
      echo 'error              : Nothing!'. $LF;
    }

    echo $LF;

	// WordPress function
    if ( function_exists('timer_stop') ) {
      echo 'execution time(ms) : ' . timer_stop(0, 5) . $LF;
    }

    echo $LF;

    if ( $extra && ! empty( $_SERVER ) ) {
      var_dump( $_SERVER );
    }

    $current_index = $index;
    while ( $current_index-- >= 0 ) {
      if ( ! isset($debug_traces[$current_index]) ) continue;
      echo isset( $debug_traces[$current_index]['file'] ) ? 'file_name : ' . $debug_traces[$current_index]['file']. $LF : '';
      echo isset( $debug_traces[$current_index]['line'] ) ? 'file_line : ' . $debug_traces[$current_index]['line'] . $LF : '';
      echo isset( $debug_traces[$current_index]['class'] ) ? 'class_name : ' . $debug_traces[$current_index]['class'] . $LF : '';
      echo isset( $debug_traces[$current_index]['function'] ) ? 'func_name : ' . $debug_traces[$current_index]['function'] . $LF : '';
      if ( isset( $debug_traces[$current_index]['args'] ) && ( $args = $debug_traces[$current_index]['args'] ) ) {
        $arg_string = trim( _getStringFromNotString( $args ) );
        echo 'func_args : ' . $arg_string . $LF;
      }
      echo $LF;
    }
  }

  function _getStringFromNotString ( $arg )
  {
    $string = '';
    if ( is_array( $arg ) ) {
      foreach ( $arg as $v ) {
        $string .= _getStringFromNotString( $v );
      }
    } elseif ( is_object( $arg ) ) {
      $string .= ' (class)' . get_class( $arg ) ;
    } elseif ( is_bool( $arg ) ) {
      if ( $arg ) {
        $string .= ' (bool)true';
      } else {
        $string .= ' (bool)false';
      }
    } elseif ( is_resource( $arg ) ) {
        $string .= ' (resource)' . get_resource_type( $arg ) ;
    } elseif ( is_null( $arg ) ) {
        $string .= ' (NULL)';
    } elseif ( is_int( $arg ) || is_float( $arg ) ) {
        $string .= ' (int|float)' . (string) $arg;
    } else {
      if ( $arg === '' ) {
        $string .=  ' \'empty string\'';
      } else {
        if ( function_exists( 'mb_strimwidth' ) ) {
          $string .=  ' '. mb_strimwidth( $arg, 0, 200, '...', 'UTF-8' );
        } else {
          $string .=  ' '. substr( $arg, 0, 200 ) . '...';
        }
      }
    }

    return $string;
  }

  function _removeNullByte( $string ) {
    if ( is_array( $string ) ){
      return array_map( '_removeNullByte', $string );
    }
    return str_replace( "\0", '', $string );
  }
}
