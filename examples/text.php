<?php

require_once( '../HtmlForm.php' );

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

<h1>Simple text field</h1>
<?php $source = <<<HTML
<?php

require_once( '../HtmlForm.php' );

\$form = HtmlForm::hie('simple')->setGet()
  ->text('test')
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

<h1>Complete text field</h1>
<?php $source = <<<HTML
<?php

require_once( '../HtmlForm.php' );

\$form = HtmlForm::hie('complete')->setGet()
  ->text('test')
  ->label('Your pseudo')
  ->default('write here')
  ->alert('Only 3 to 16 alphanumeric characters')
  ->check('/^\w{3,16}$/')
  ->required('You need enter a value')
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

<h1>Light text field</h1>
<?php $source = <<<HTML
<?php

require_once( '../HtmlForm.php' );

\$form = HtmlForm::hie('light')->setGet()
  ->text('test')
  ->label(false)
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

