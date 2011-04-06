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

<h1 id="simple">Simple month dropdown field</h1>
<?php $source = <<<HTML
<?php

require_once( '../HtmlForm.php' );

\$form = HtmlForm::hie('simple')->setGet()->setAnchor('simple')
  ->month()
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
<?php echo $html; if( $form->isValid() ) echo '<p><strong>Valid!</strong></p><p>Date is: '.date('c',$form['month']->time).'</p>'; ?>
<hr/>

<h1 id="complete">Complete month dropdown field</h1>
<?php $source = <<<HTML
<?php

require_once( '../HtmlForm.php' );

\$form = HtmlForm::hie('complete')->setGet()->setAnchor('complete')
  ->month('mois_de_naissance')
  ->label('Votre moi de naissance')
  ->choice('-- votre moi de naissance --')
  ->default('now')
  ->alert('Vous avez trichez')
  ->required('Vous devez choisir un mois')
  ->i18n('french')
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
<?php echo $html; if( $form->isValid() ) echo '<p><strong>Valid!</strong></p><p>Date is: '.date('c',$form['mois_de_naissance']->time).'</p>'; ?>
<hr/>

<h1 id="relational">Relational month dropdown field</h1>
<?php $source = <<<HTML
<?php

require_once( '../HtmlForm.php' );

\$form = HtmlForm::hie('relational')->setGet()->setAnchor('relational')
  ->year()->default('now')->readonly()
  ->month('mois_de_naissance','year')
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
<?php echo $html; if( $form->isValid() ) echo '<p><strong>Valid!</strong></p><p>Date is: '.date('c',$form['year']->time).'</p>'; ?>
<hr/>

