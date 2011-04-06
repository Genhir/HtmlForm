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

<h1 id="simple">Simple day field</h1>
<p>Try the <strong>GNU date syntaxe</strong>:</p>
<ul>
  <li>now</li>
  <li>-2 days</li>
  <li>+1 week 2 days 4 hours 2 seconds</li>
  <li>next Thursday</li>
</ul>
<?php $source = <<<HTML
<?php

require_once( '../HtmlForm.php' );

\$form = HtmlForm::hie('simple')->setGet()->setAnchor('simple')
  ->day()
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
<?php echo $html; if( $form->isValid() ) echo '<p><strong>Valid!</strong></p><p>Date is: '.date('c',$form['day']->time).'</p>'; ?>
<hr/>

<h1 id="complete">Complete day field</h1>
<p>The form will valid with these values:</p>
<ul>
  <li>now</li>
  <li>-1 day</li>
  <li>+1 day</li>
</ul>
<?php $source = <<<HTML
<?php

require_once( '../HtmlForm.php' );

\$form = HtmlForm::hie('complete')->setGet()->setAnchor('complete')
  ->day('birthday','February','2005')
  ->bounds('-1 day','+1 day')
  ->ofMonth('February')
  ->ofYear('now')
  ->label('Your birthday')
  ->default('now')
  ->alert('Must be a valid day between yesterday and tomorrow')
  ->required('Day is required')
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
<?php echo $html; if( $form->isValid() ) echo '<p><strong>Valid!</strong></p><p>Date is: '.date('c',$form['birthday']->time).'</p>'; ?>
<hr/>

<h1 id="relational">Relational day field</h1>
<p>Try theses dates:</p>
<ul>
  <li>29 February 2008</li>
  <li>29 February 2007</li>
</ul>
<?php $source = <<<HTML
<?php

require_once( '../HtmlForm.php' );

\$form = HtmlForm::hie('relational')->setGet()->setAnchor('relational')
  ->year('birthyear')->default('now')
  ->month('birthmonth')->default('now')
  ->day('birthday','birthmonth','birthyear')->default('now')
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
<?php echo $html; if( $form->isValid() ) echo '<p><strong>Valid!</strong></p><p>Date is: '.date('c',$form['birthday']->time).'</p>'; ?>
<hr/>

