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

<h1 id="simple">Simple email field</h1>
<?php $source = <<<HTML
<?php

require_once( '../HtmlForm.php' );

\$form = HtmlForm::hie('simple')->setGet()->setAnchor('simple')
  ->email()
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

<h1 id="complete">Complete text field</h1>
<?php $source = <<<HTML
<?php

require_once( '../HtmlForm.php' );

\$form = HtmlForm::hie('complete')->setGet()->setAnchor('complete')
  ->email('e-mail')
  ->label('Your email address')
  ->default('my@address.email')
  ->alert('Enter a valid email address!')
  ->required('Enter your email address')
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

<h1 id="multiple">Multiple email field</h1>
<?php $source = <<<HTML
<?php

require_once( '../HtmlForm.php' );

function check_to( HtmlFormElement \$element, HtmlForm \$form )
{
  return ( \$form['to']->value == \$form['from']->value );
}

\$form = HtmlForm::hie('multiple')->setGet()->setAnchor('multiple')
  ->email('to')
  ->required()
  ->email('from')
  ->check('check_to')
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
