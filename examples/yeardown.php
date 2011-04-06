<?php

require_once( '../HtmlForm.php' );
require_once( 'debug.php' );
set_time_limit(3);

?>
<style>
code { float: right; width: 32%; overflow: auto; background: #ddd; padding: 1px 3px; margin: 0px 0px 10px 10px; font: 11px monospace; white-space: nowrap; }
pre { float: right; width: 32%; overflow: auto; background: #ddd; padding: 1px 3px; margin: 0px 0px 10px 10px; font: 11px monospace; }
hr { clear: right; }
strong { background: #9f9; }
form { background: #9cf; }
form div.element { margin: 10px 0px; }
form div.element div.label { font-weight: bold; font-size: 0.8em; margin-top: 10px; }
form div.error { background: #f99; }
form div.alert p { margin: 0px; }
</style>

<a href="index.php">Return to exemples index</a>
<hr>

<h1>Simple year dropdown field</h1>
<?php $source = <<<HTML
<?php

require_once( '../HtmlForm.php' );

\$form = HtmlForm::hie('simple')->setGet()
  ->yeardown()
  ->submit();

HtmlOut::display(\$form);

?>
HTML;
highlight_string(strtr($source,array('../'=>'')));
?>
<?php ob_start(); eval(strtr($source,array('<?php'=>'','?>'=>''))); $html = ob_get_clean(); ?>
<pre>
<?php echo htmlentities($html); ?>
</pre>
<?php echo $html; if( $form->isValid() ) echo '<strong>Valid!</strong>'; ?>
<hr/>

<h1>Complete year dropdown field</h1>
<?php $source = <<<HTML
<?php

require_once( '../HtmlForm.php' );

\$form = HtmlForm::hie('complete')->setGet()
  ->yeardown('date')
  ->bounds('-40 year','-15 year')
  ->label('Your birthyear')
  ->choice('-- your birthyear --')
  ->default('-20 year')
  ->alert('You need to have between 15 and 40 yearsold, sorry')
  ->required('Enter your birthyear')
  ->submit();

HtmlOut::display(\$form);

?>
HTML;
highlight_string(strtr($source,array('../'=>'')));
?>
<?php ob_start(); eval(strtr($source,array('<?php'=>'','?>'=>''))); $html = ob_get_clean(); ?>
<pre>
<?php echo htmlentities($html); ?>
</pre>
<?php echo $html; if( $form->isValid() ) echo '<strong>Valid!</strong>'; debug($form['date']); ?>
<hr/>

