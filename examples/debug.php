<?php

// {{{ dump

function dump( $var, $depth = 3, $children = 128, $data = 512 )
{
  if( ! extension_loaded('xdebug') ) return var_dump($var);
  ini_set( 'xdebug.var_display_max_depth', (integer)$depth );
  ini_set( 'xdebug.var_display_max_children', (integer)$children );
  ini_set( 'xdebug.var_display_max_data', (integer)$data );

  ob_start();
  var_dump( $var );
  $dump = substr(ob_get_clean(),strlen('<pre>'),-strlen('</pre>'));

  for( $i=0; $i< abs($depth); $i++ )
    $dump =
      preg_replace_callback(
        '/((?:  )*  )(<b>(?:object|array)<\/b>(?:\\(<i>(?:.*?)<\/i>\\)\\[<i>(?:\\d+)<\/i>\\])?)(\\r?\\n?)(\\r?\\n?(?:(?<!  )\\1.*\\r?\\n?)*)/',
        '__dump_object',
      $dump );

  $dump =
  preg_replace_callback(
    '/(<i>(?:private|protected|public)<\/i> \'(?:.*?)\' <font color=\'#777777\'>=&gt;<\/font> <font color=\'#bb00bb\'>)\'([\\r\\n]*.*)\'(<\/font> <i>\\(length=(\\d+)\\)<\/i>)/',
    '__dump_string',
    $dump );

  $trace = debug_backtrace();

  echo '<pre>',substr($dump,0,-1),'</pre>';
}

// }}}
// {{{ __dump_object()

function __dump_object( $m )
{
  static $id = 0;
  $id++;
  return $m[1]."<a href=\"#\" onclick=\"if(document.getElementById('dump".$id."').style.display=='none')document.getElementById('dump".$id."').style.display='';else document.getElementById('dump".$id."').style.display='none';return false;\" style=\"color:black\">".$m[2]."</a><div style=\"display:none\" id=\"dump".$id."\">".$m[4]."</div>".$m[3];
}

// }}}
// {{{ __dump_string()

function __dump_string( $m )
{
  static $id = 0;
  if( substr_count($m[2],'&#10;') == 0 )
    return $m[0];
  $id++;
  if( substr($m[2],0,strlen('&lt;?php')) == '&lt;?php' )
    $m[2] = highlight_string(html_entity_decode($m[2]),true);
  return $m[1]."<a href=\"#\" onclick=\"if(document.getElementById('dumpstring".$id."').style.display=='none')document.getElementById('dumpstring".$id."').style.display='';else document.getElementById('dumpstring".$id."').style.display='none';return false;\" style=\"color:black\"><b>string</b></a><div style=\"display:none\" id=\"dumpstring".$id."\">".$m[2]."</div>".$m[3];
}

// }}}
// {{{ debug()

function debug( $var = null, $name = null, $depth = 3, $children = 128, $data = 512 )
{
  static $vars = array();
  if( func_num_args() == 0 and $vars )
  {
    $dump = '';
    echo <<<HTML
<style type="text/css">
div.debug { background: black !important; color: #6f6 !important; padding: 1px !important; overflow: hidden; clear: both; }
html div.debug { text-align: left !important; }
div.debug * { font: 10px/12px lucida console, monospace !important; margin: 0px !important; padding: 0px !important; border: none !important; width: auto !important; }
div.debug pre { background: black !important; }
div.debug b { color: #f66 !important; font-weight: normal !important; }
div.debug i { color: #ccc !important; font-style: normal !important; }
div.debug h1 { background: #666 !important; color: yellow !important; }
div.debug h2 { background: #333 !important; color: orange !important; }
div.debug a { text-decoration: underline !important; background: url("bullet_toggle_plus.png") -4px center no-repeat !important; color: white !important; }
div.debug a b { text-decoration: underline !important; color: #f66 !important; }
div.debug a i { text-decoration: underline !important; color: #ccc !important; }
div.debug li { display: block !important; float: left !important; margin-bottom: 12px !important; margin-right: 7px !important; }
div.debug div.break { clear: both !important; }
div.debug small { color: gray !important; }
div.debug li.mark { background: #225 !important; }
div.debug li.mark h2 { background: #336 !important; }
div.debug li.mark pre { background: #225 !important; }
</style>
<div class="debug">
<div class="php"><h1>php debug</h1><ul>

HTML;
    foreach( $vars as $var )
    {
      echo "<li title=\"$var[file]:$var[line]\"",(is_integer($var['name'])?' class="mark"':''),"><h2>$var[name]</h2>";
      dump($var['var'], $var['depth'], $var['children'], $var['data']);
      echo '</li>';
    }
    echo <<<HTML
</ul><div class="break"></div></div>
</div>

HTML;
  }
  else
  {
    $trace = debug_backtrace();
    if( is_null($name) and isset($trace[0]['line']) )
      $name = $trace[0]['line'];
    elseif( is_null($name) and isset($trace[0]['function']) )
      $name = $trace[0]['function'];
    $vars[] = array(
      'name' => $name,
      'var' => &$var,
      'depth' => $depth,
      'children' => $children,
      'data' => $data,
      'file' => basename(dirname(@$trace[0]['file'])).'/'.basename(@$trace[0]['file']),
      'line' => @$trace[0]['line']
      );

  }
}

// }}}

register_shutdown_function( 'debug' );

?>
