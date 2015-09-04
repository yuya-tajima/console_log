<?php
/*
Plugin Name: Console Log
Description: store the var_dump results as a text file.
Version: 0.8
Author: Yuya Tajima
*/

/**
 * This is a debugging tool. Store the var_dump results as a text file.
 *
 * @type mixed $dump The data you want to dump.
 * @param array $args {
 * available arguments.
 *
 * @type bool $any_time Whether to run this method anytime. default true.
 *                      If false, run when $_GET['debug'] variable is setting.
 * @type bool $wp_ajax Whether to run when WordPress Ajax is running. default true.
 * @type int $index the number of index should be tarced. default 3.
 * @type bool $echo Whether to output the $dump. default false.
 * @type bool $extra Whether to show more information. default false.
 *
 * @author Yuya Tajima
 * @link https://github.com/yuya-tajima/console_log
 */

if ( ! function_exists( 'console_log' ) ) {
  function console_log( $dump, array $args = array() ) {

    if ( ! isset( $dump ) ) {
      $dump = NULL;
    }

    $defaults = array(
      'any_time' => true,
      'wp_ajax'  => true,
      'index'    => 3,
      'echo'     => false,
      'extra'    => false,
    );

    $args = array_merge( $defaults, $args );

    if ( ! $args['any_time'] && empty( $_GET['debug'] ) ) {
      return;
    }

    if( ! $args['wp_ajax'] && ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ){
      return;
    }

    $debug_log = '';


    if ( defined( 'WP_CONTENT_DIR' ) ) {

      $debug_path_file = WP_CONTENT_DIR . '/console_log.php';

      if ( ! file_exists( $debug_path_file ) ) {
        touch( $debug_path_file );
        chmod( $debug_path_file, 0666 );
        $str = <<<EOD
<?php
return '/var/log/console.log';
EOD;
        file_put_contents( $debug_path_file, $str, LOCK_EX );
      }

      $debug_log = include( $debug_path_file );
    }

    if ( defined( 'CONSOLE_LOG_FILE' ) && is_string( CONSOLE_LOG_FILE ) ) {
      $debug_log = CONSOLE_LOG_FILE;
    }

    if ( ! file_exists( $debug_log ) ) {
      error_log( $debug_log . ' does not exist.' );
      return;
    }

    if ( ! is_writable( $debug_log ) ) {
      error_log( $debug_log . ' is not writable. please change the file permission. or use another log file.' );
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
        chmod( $debug_log, 0666 );
      } else {
        return;
      }
    }

    ob_start();
    echo '*********************************************' . PHP_EOL;
    _console_log_backtrace( $args['index'], PHP_EOL, $args['extra'] );
    if( defined( 'DOING_AJAX' ) && DOING_AJAX ){
      echo 'Ajax is running! by WordPress.' . PHP_EOL . PHP_EOL;
      var_dump($_POST);
      echo PHP_EOL;
    }
    var_dump( $dump );
    echo PHP_EOL;
    echo '*********************************************' . PHP_EOL;

    $out = ob_get_contents();

    ob_end_clean();

    file_put_contents( $debug_log, $out, FILE_APPEND | LOCK_EX );

    //if headers have not already been sent and $args['echo'] is true
    //echo $dump
    if( $args['echo'] && ! headers_sent() ){
      echo nl2br( htmlspecialchars( $out, ENT_QUOTES, 'UTF-8' ) );
    }
  }

  function _console_log_backtrace( $index, $LF = PHP_EOL, $extra = false  ) {

    $debug_traces = debug_backtrace();

    if ( function_exists('date_i18n') ) {
      echo 'time              : ' . date_i18n( 'Y-m-d H:i:s' ) . $LF;
    } else {
      date_default_timezone_set( 'Asia/Tokyo' );
      echo 'time              : ' . date( 'Y-m-d H:i:s' ) . $LF;
    }
    echo 'using memory(MB)  : ' . round( memory_get_usage() / ( 1024 * 1024 ), 2 ) . ' MB' . $LF;
    echo $LF;

    if ( $extra ) {
      var_dump( $_SERVER );
    }

    for ( $i = 0 ; ( $_index = $index - $i ) > 0 ; $i++ )  {
      echo isset( $debug_traces[$_index]['file'] ) ? 'file_name : ' . $debug_traces[$_index]['file']. $LF : '';
      echo isset( $debug_traces[$_index]['line'] ) ? 'file_line : ' . $debug_traces[$_index]['line'] . $LF : '';
      echo isset( $debug_traces[$_index]['class'] ) ? 'class_name : ' . $debug_traces[$_index]['class'] . $LF : '';
      echo isset( $debug_traces[$_index]['function'] ) ? 'func_name : ' . $debug_traces[$_index]['function'] . $LF : '';
      if ( isset( $debug_traces[$_index]['args'] ) && ( $args = $debug_traces[$_index]['args'] ) )  {
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
}
