<?php

require_once( '../HtmlForm.php' );

// executed when the form being validate
function valid( HtmlForm $form )
{
  echo <<<HTML
<h1>Valid!</h1>
<pre>INSERT users {$form->mysqlSet()} ON DUPLICATE KEY UPDATE {$form->mysqlDuplicateValues()}</pre>

HTML;
  exit;
}

// create e new form ...
$form = HtmlForm::hie('subscribe')->useRequest()

  // ... and add some field
  ->text('pseudo')->required(true)->check('/^\w{3,16}$/')->alert('Must be 3 to 16 alphanumeric characters')
  ->email()
  ->country()->required(true)
  ->submit()

  ->onValid('valid');

// display the form
HtmlOut::display( $form );

?>
<style>
.error { background: #f99; }
</style>
