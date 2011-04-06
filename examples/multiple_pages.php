<?php

require_once( '../HtmlForm.php' );

// executed when the form is valid
function valid( HtmlForm $form )
{
  echo <<<HTML
<h1>Valid!</h1>
HTML;
  if( $form->mysqlValues() )
echo <<<HTML
<pre>INSERT users {$form->mysqlValues()} ON DUPLICATE KEY UPDATE {$form->mysqlDuplicateValues()}</pre>
HTML;

  exit;
}

// create the seconde page ...
function page2( HtmlForm $form )
{
  return HtmlForm::hie('page2')

    // ... add previous fields from page1 ...
    ->hiddenFrom( $form )

    // ... and add some fields
    ->text('name')->required()
    ->birthdate()->required()
    ->submit()
    ->doValid('valid');
}

// create a first page ...
$form = HtmlForm::hie('page1')

  // ... and add some fields
  ->text('pseudo')->required()
  ->passwords()->required()
  ->submit()->label('Next')
  ->doValid('page2');

// display the form
HtmlOut::display( $form );

?>
<style>
.error { background: #f99; }
</style>
